<?php
require_once __DIR__ . '/../conexao.php'; // Caminho correto para a classe BancoDeDados

// Cria o objeto de conexão
$db = new BancoDeDados();
$pdo = $db->pdo;

// Agora cria o LogManager com a conexão
$logManager = new LogManager($pdo); // ajuste o caminho conforme sua organização
class LogManager {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    /**
     * Registra um log geral no sistema
     */
    public function registrarLog($usuario_id, $acao, $tabela = null, $registro_id = null, $dados_anteriores = null, $dados_novos = null, $detalhes = null, $descricao = null) {
        try {
            $sql = "INSERT INTO logs (
                        usuario_id, acao, tabela, registro_id, dados_anteriores, dados_novos, detalhes, descricao, ip, user_agent, criado_em
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                $usuario_id,
                $acao,
                $tabela,
                $registro_id,
                is_array($dados_anteriores) ? json_encode($dados_anteriores) : $dados_anteriores,
                is_array($dados_novos) ? json_encode($dados_novos) : $dados_novos,
                $detalhes,
                $descricao,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            error_log("Erro ao registrar log: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Registra saída de produto do estoque
     */
    public function registrarSaidaEstoque($produto_id, $usuario_id, $quantidade, $motivo = 'uso interno', $destino = null, $observacoes = null) {
        try {
            $stmt = $this->pdo->prepare("SELECT estoque_atual, nome FROM produtos WHERE id_produto = ?");
            $stmt->execute([$produto_id]);
            $produto = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$produto) {
                throw new Exception("Produto não encontrado");
            }

            $quantidade_anterior = $produto['estoque_atual'];
            $quantidade_nova = $quantidade_anterior - $quantidade;
            $nome_produto = $produto['nome'];

            if ($quantidade_nova < 0) {
                throw new Exception("Estoque insuficiente para o produto: {$nome_produto}");
            }

            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("UPDATE produtos SET estoque_atual = ?, atualizado_em = NOW() WHERE id_produto = ?");
            $stmt->execute([$quantidade_nova, $produto_id]);

            $dados_movimentacao = [
                'produto_id' => $produto_id,
                'usuario_id' => $usuario_id,
                'tipo_movimentacao' => 'saida',
                'quantidade' => $quantidade,
                'quantidade_anterior' => $quantidade_anterior,
                'quantidade_atual' => $quantidade_nova,
                'motivo' => $motivo,
                'destino' => $destino,
                'observacoes' => $observacoes
            ];

            $this->registrarMovimentacaoEstoque($dados_movimentacao);

            try {
                $stmt = $this->pdo->prepare("INSERT INTO saidas_estoque (id_produto, quantidade, motivo, destino, data_saida, observacao) VALUES (?, ?, ?, ?, NOW(), ?)");
                $stmt->execute([$produto_id, $quantidade, $motivo, $destino, $observacoes]);
            } catch (Exception $e) {
                // Tabela pode não existir, não é crítico
            }

            $this->pdo->commit();
            return true;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Erro ao registrar saída de estoque: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Registra entrada de produto no estoque
     */
    public function registrarEntradaEstoque($produto_id, $usuario_id, $quantidade, $fornecedor_id = null, $valor_unitario = null, $nota_fiscal = null, $observacoes = null) {
        try {
            // Validações de entrada
            if (empty($produto_id) || empty($usuario_id) || !isset($quantidade) || $quantidade <= 0) {
                throw new Exception("Dados insuficientes ou inválidos para registrar entrada de estoque.");
            }
    
            // Converter valores para tipos apropriados
            $produto_id = (int) $produto_id;
            $usuario_id = (int) $usuario_id;
            $quantidade = (int) $quantidade;
            $fornecedor_id = !empty($fornecedor_id) ? (int) $fornecedor_id : null;
            $valor_unitario = !empty($valor_unitario) ? (float) $valor_unitario : 0.00;
            $nota_fiscal = !empty($nota_fiscal) ? trim($nota_fiscal) : null;
            $observacoes = !empty($observacoes) ? trim($observacoes) : null;
    
            // Buscar dados atuais do produto
            $stmt = $this->pdo->prepare("SELECT estoque_atual, nome FROM produtos WHERE id_produto = ? AND ativo = 1");
            $stmt->execute([$produto_id]);
            $produto = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if (!$produto) {
                throw new Exception("Produto não encontrado ou inativo.");
            }
    
            $quantidade_anterior = (int) $produto['estoque_atual'];
            $quantidade_nova = $quantidade_anterior + $quantidade;
            $nome_produto = $produto['nome'];
    
            // Iniciar transação
            $this->pdo->beginTransaction();
    
            // 1. Atualizar estoque do produto
            $stmt = $this->pdo->prepare("
                UPDATE produtos 
                SET estoque_atual = ?, 
                    atualizado_em = NOW(), 
                    usuario_atualizacao = ? 
                WHERE id_produto = ?
            ");
            $stmt->execute([$quantidade_nova, $usuario_id, $produto_id]);
    
            if ($stmt->rowCount() === 0) {
                throw new Exception("Falha ao atualizar o estoque do produto.");
            }
    
            // 2. Registrar na tabela entradas_estoque
            $stmt = $this->pdo->prepare("
                INSERT INTO entradas_estoque 
                (id_produto, id_fornecedor, quantidade, valor_unitario, data_entrada, nota_fiscal) 
                VALUES (?, ?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([$produto_id, $fornecedor_id, $quantidade, $valor_unitario, $nota_fiscal]);
            $entrada_id = $this->pdo->lastInsertId();
    
            // 3. Registrar na tabela movimentacoes_estoque
            $stmt = $this->pdo->prepare("
                INSERT INTO movimentacoes_estoque 
                (produto_id, usuario_id, tipo_movimentacao, quantidade, quantidade_anterior, quantidade_atual, 
                 motivo, fornecedor_id, nota_fiscal, valor_unitario, observacoes, criado_em) 
                VALUES (?, ?, 'entrada', ?, ?, ?, 'Entrada de estoque', ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $produto_id, $usuario_id, $quantidade, $quantidade_anterior, $quantidade_nova, 
                $fornecedor_id, $nota_fiscal, $valor_unitario, $observacoes
            ]);
            $movimentacao_id = $this->pdo->lastInsertId();
    
            // 4. Registrar no log
            $dados_anteriores = json_encode(['quantidade_anterior' => $quantidade_anterior]);
            $dados_novos = json_encode(['quantidade_atual' => $quantidade_nova]);
            $detalhes = "Entrada de estoque: {$quantidade} unidades do produto ID {$produto_id} (nome: {$nome_produto}). " .
                       "Estoque anterior: {$quantidade_anterior}, estoque atual: {$quantidade_nova}. " .
                       "Fornecedor ID: " . ($fornecedor_id ?? 'N/D') . ", Nota Fiscal: " . ($nota_fiscal ?? 'N/D') . ".";
    
            $stmt = $this->pdo->prepare("
                INSERT INTO logs 
                (usuario_id, acao, tabela, registro_id, dados_anteriores, dados_novos, detalhes, 
                 ip, user_agent, criado_em, descricao) 
                VALUES (?, 'ENTRADA_ESTOQUE', 'produtos', ?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/D';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'N/D';
            $descricao = "Entrada de {$quantidade} unidades - {$nome_produto}";
            
            $stmt->execute([
                $usuario_id, $produto_id, $dados_anteriores, $dados_novos, $detalhes,
                $ip, $user_agent, $descricao
            ]);
    
            // Confirmar transação
            $this->pdo->commit();
    
            return [
                'sucesso' => true,
                'entrada_id' => $entrada_id,
                'movimentacao_id' => $movimentacao_id,
                'produto_nome' => $nome_produto,
                'quantidade_anterior' => $quantidade_anterior,
                'quantidade_atual' => $quantidade_nova,
                'quantidade_adicionada' => $quantidade,
                'valor_total' => $quantidade * $valor_unitario,
                'data_entrada' => date('Y-m-d H:i:s')
            ];
    
        } catch (Exception $e) {
            // Rollback em caso de erro
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            
            error_log("Erro ao registrar entrada de estoque: " . $e->getMessage());
            
            return [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }
    
    public function registrarMovimentacaoEstoque($dados) {
        try {
            $sql = "INSERT INTO movimentacoes_estoque 
                    (produto_id, usuario_id, tipo_movimentacao, quantidade, quantidade_anterior, 
                     quantidade_atual, motivo, destino, fornecedor_id, nota_fiscal, valor_unitario, 
                     observacoes, criado_em) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $dados['produto_id'],
                $dados['usuario_id'],
                $dados['tipo_movimentacao'],
                $dados['quantidade'],
                $dados['quantidade_anterior'],
                $dados['quantidade_atual'],
                $dados['motivo'] ?? '',
                $dados['destino'] ?? null,
                $dados['fornecedor_id'] ?? null,
                $dados['nota_fiscal'] ?? null,
                $dados['valor_unitario'] ?? 0,
                $dados['observacoes'] ?? ''
            ]);
    
            return $this->pdo->lastInsertId();
    
        } catch (PDOException $e) {
            error_log("Erro ao registrar movimentação: " . $e->getMessage());
            throw new Exception("Erro ao registrar movimentação de estoque.");
        }
    }
    
    


    /**
     * Busca movimentações de estoque com filtros
     */
    public function buscarMovimentacoesEstoque($filtros = [], $limite = 50, $offset = 0) {
        try {
            $where = [];
            $params = [];
    
            // Construção dos filtros WHERE
            if (!empty($filtros['produto_id'])) {
                $where[] = "m.produto_id = ?";
                $params[] = $filtros['produto_id'];
            }
    
            if (!empty($filtros['usuario_id'])) {
                $where[] = "m.usuario_id = ?";
                $params[] = $filtros['usuario_id'];
            }
    
            if (!empty($filtros['tipo_movimentacao'])) {
                $where[] = "m.tipo_movimentacao = ?";
                $params[] = $filtros['tipo_movimentacao'];
            }
    
            if (!empty($filtros['data_inicio'])) {
                $where[] = "DATE(m.criado_em) >= ?";
                $params[] = $filtros['data_inicio'];
            }
    
            if (!empty($filtros['data_fim'])) {
                $where[] = "DATE(m.criado_em) <= ?";
                $params[] = $filtros['data_fim'];
            }
    
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
            // Query simplificada e mais robusta
            $sql = "SELECT 
                    m.id,
                    m.produto_id,
                    m.usuario_id,
                    m.tipo_movimentacao,
                    m.quantidade,
                    m.quantidade_anterior,
                    m.quantidade_atual,
                    m.motivo,
                    m.destino,
                    m.fornecedor_id,
                    m.nota_fiscal,
                    m.valor_unitario,
                    m.observacoes,
                    m.criado_em,
                    DATE_FORMAT(m.criado_em, '%d/%m/%Y %H:%i:%s') as data_hora,
                    COALESCE(p.nome, CONCAT('Produto ID: ', m.produto_id)) as produto_nome,
                    COALESCE(p.codigo, '') as produto_codigo,
                    COALESCE(u.nome, 'Sistema') as usuario_nome,
                    COALESCE(f.nome, '') as fornecedor_nome
                FROM movimentacoes_estoque m
                LEFT JOIN produtos p ON m.produto_id = p.id_produto
                LEFT JOIN usuarios u ON m.usuario_id = u.id
                LEFT JOIN fornecedores f ON m.fornecedor_id = f.id_fornecedor
                {$whereClause}
                ORDER BY m.criado_em DESC";
    
            // Preparar statement
            $stmt = $this->pdo->prepare($sql);
    
            // Bind dos parâmetros dos filtros
            foreach ($params as $index => $value) {
                $stmt->bindValue($index + 1, $value);
            }
    
            // Executar sem LIMIT primeiro para debug
            error_log("SQL executada: " . $sql);
            error_log("Parâmetros: " . json_encode($params));
            
            $stmt->execute();
            $todos_resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Total encontrado sem LIMIT: " . count($todos_resultados));
    
            // Aplicar LIMIT e OFFSET manualmente
            $resultado_paginado = array_slice($todos_resultados, $offset, $limite);
            
            error_log("Resultado paginado: " . count($resultado_paginado));
            
            return $resultado_paginado;
    
        } catch (PDOException $e) {
            error_log("Erro na busca de movimentações: " . $e->getMessage());
            error_log("SQL que falhou: " . ($sql ?? 'SQL não definida'));
            return [];
        } catch (Exception $e) {
            error_log("Erro geral na busca de movimentações: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca logs com filtros
     */
    public function buscarLogs($filtros = [], $limite = 50, $offset = 0) {
        $where = [];
        $params = [];

        if (!empty($filtros['usuario_id'])) {
            $where[] = "l.usuario_id = ?";
            $params[] = $filtros['usuario_id'];
        }

        if (!empty($filtros['acao'])) {
            $where[] = "l.acao = ?";
            $params[] = $filtros['acao'];
        }

        if (!empty($filtros['tabela'])) {
            $where[] = "l.tabela = ?";
            $params[] = $filtros['tabela'];
        }

        if (!empty($filtros['data_inicio'])) {
            $where[] = "DATE(l.criado_em) >= ?";
            $params[] = $filtros['data_inicio'];
        }

        if (!empty($filtros['data_fim'])) {
            $where[] = "DATE(l.criado_em) <= ?";
            $params[] = $filtros['data_fim'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $limite = (int) $limite;
        $offset = (int) $offset;

        $sql = "SELECT 
            l.*,
            DATE_FORMAT(l.criado_em, '%d/%m/%Y %H:%i:%s') as data_hora,
            u.nome as usuario_nome,
            u.email as usuario_email,
            COALESCE(l.descricao, 
                CASE 
                    WHEN l.tabela = 'produtos' AND l.acao LIKE '%ESTOQUE%' THEN 
                        CONCAT(REPLACE(l.acao, '_ESTOQUE', ''), ' de estoque')
                    WHEN l.tabela IS NOT NULL AND l.registro_id IS NOT NULL THEN 
                        CONCAT(l.acao, ' em ', l.tabela, ' ID: ', l.registro_id)
                    ELSE l.acao
                END
            ) as descricao
        FROM logs l
        LEFT JOIN usuarios u ON l.usuario_id = u.id
        {$whereClause}
        ORDER BY l.criado_em DESC
        LIMIT {$limite} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Método auxiliar para registrar logs de CRUD em produtos
     */
    public function registrarLogProduto($usuario_id, $acao, $produto_id, $dados_anteriores = null, $dados_novos = null, $nome_produto = null) {
        // Se não temos o nome do produto, buscamos
        if (!$nome_produto) {
            try {
                $stmt = $this->pdo->prepare("SELECT nome FROM produtos WHERE id_produto = ?");
                $stmt->execute([$produto_id]);
                $produto = $stmt->fetch(PDO::FETCH_ASSOC);
                $nome_produto = $produto['nome'] ?? "Produto ID: {$produto_id}";
            } catch (Exception $e) {
                $nome_produto = "Produto ID: {$produto_id}";
            }
        }

        // Criar descrição baseada na ação
        $acoes_descricao = [
            'CREATE' => "Produto criado: {$nome_produto}",
            'UPDATE' => "Produto atualizado: {$nome_produto}",
            'DELETE' => "Produto excluído: {$nome_produto}",
        ];

        $descricao = $acoes_descricao[$acao] ?? "{$acao} - {$nome_produto}";

        // Criar detalhes
        $detalhes = null;
        if ($dados_anteriores || $dados_novos) {
            $detalhes = "Produto: {$nome_produto} (ID: {$produto_id})";
            if ($dados_anteriores) {
                $detalhes .= "\nDados anteriores: " . (is_array($dados_anteriores) ? json_encode($dados_anteriores, JSON_UNESCAPED_UNICODE) : $dados_anteriores);
            }
            if ($dados_novos) {
                $detalhes .= "\nDados novos: " . (is_array($dados_novos) ? json_encode($dados_novos, JSON_UNESCAPED_UNICODE) : $dados_novos);
            }
        }

        return $this->registrarLog(
            $usuario_id,
            $acao,
            'produtos',
            $produto_id,
            $dados_anteriores,
            $dados_novos,
            $detalhes,
            $descricao
        );
    }
    
}
?>