<?php
require_once '../conexao.php'; // ajuste o caminho conforme sua organização
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
     * Registra movimentação de estoque
     */
    public function registrarMovimentacaoEstoque($dados) {
        try {
            $sql = "INSERT INTO movimentacoes_estoque (
                        produto_id, usuario_id, tipo_movimentacao, quantidade, quantidade_anterior,
                        quantidade_atual, motivo, destino, fornecedor_id, nota_fiscal, valor_unitario,
                        observacoes, criado_em
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmt = $this->pdo->prepare($sql);
            $resultado = $stmt->execute([
                $dados['produto_id'],
                $dados['usuario_id'],
                $dados['tipo_movimentacao'],
                $dados['quantidade'],
                $dados['quantidade_anterior'],
                $dados['quantidade_atual'],
                $dados['motivo'] ?? null,
                $dados['destino'] ?? null,
                $dados['fornecedor_id'] ?? null,
                $dados['nota_fiscal'] ?? null,
                $dados['valor_unitario'] ?? null,
                $dados['observacoes'] ?? null
            ]);

            // Buscar nome do produto para descrição
            $stmt_produto = $this->pdo->prepare("SELECT nome FROM produtos WHERE id_produto = ?");
            $stmt_produto->execute([$dados['produto_id']]);
            $produto = $stmt_produto->fetch(PDO::FETCH_ASSOC);
            $nome_produto = $produto['nome'] ?? 'Produto ID: ' . $dados['produto_id'];

            // Criar descrição detalhada
            $tipo_movimento = ucfirst($dados['tipo_movimentacao']);
            $descricao = "{$tipo_movimento} de {$dados['quantidade']} unidades - {$nome_produto}";

            // Também registra no log geral
            $this->registrarLog(
                $dados['usuario_id'],
                strtoupper($dados['tipo_movimentacao']) . '_ESTOQUE',
                'produtos',
                $dados['produto_id'],
                ['quantidade_anterior' => $dados['quantidade_anterior']],
                ['quantidade_atual' => $dados['quantidade_atual']],
                "Movimentação: {$dados['tipo_movimentacao']} de {$dados['quantidade']} unidades. Estoque anterior: {$dados['quantidade_anterior']}, atual: {$dados['quantidade_atual']}",
                $descricao
            );

            return $resultado;

        } catch (Exception $e) {
            error_log("Erro ao registrar movimentação de estoque: " . $e->getMessage());
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
            if (empty($produto_id) || empty($usuario_id) || !isset($quantidade) || $quantidade <= 0) {
                throw new Exception("Dados insuficientes ou inválidos para registrar entrada de estoque.");
            }

            $stmt = $this->pdo->prepare("SELECT estoque_atual, nome FROM produtos WHERE id_produto = ?");
            $stmt->execute([$produto_id]);
            $produto = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$produto) {
                throw new Exception("Produto não encontrado.");
            }

            $quantidade_anterior = (int) $produto['estoque_atual'];
            $quantidade_nova = $quantidade_anterior + (int) $quantidade;
            $nome_produto = $produto['nome'] ?? 'N/D';

            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("UPDATE produtos SET estoque_atual = ?, atualizado_em = NOW() WHERE id_produto = ?");
            $stmt->execute([$quantidade_nova, $produto_id]);

            $dados_movimentacao = [
                'produto_id' => $produto_id,
                'usuario_id' => $usuario_id,
                'tipo_movimentacao' => 'entrada',
                'quantidade' => $quantidade,
                'quantidade_anterior' => $quantidade_anterior,
                'quantidade_atual' => $quantidade_nova,
                'motivo' => 'Entrada de estoque',
                'fornecedor_id' => $fornecedor_id,
                'nota_fiscal' => $nota_fiscal,
                'valor_unitario' => $valor_unitario,
                'observacoes' => $observacoes
            ];

            $detalhes = sprintf(
                "Entrada de estoque: %d unidades do produto '%s' (ID: %d). Estoque anterior: %d, estoque atual: %d. Fornecedor ID: %s, Nota Fiscal: %s.",
                (int)$quantidade,
                $nome_produto,
                $produto_id,
                $quantidade_anterior,
                $quantidade_nova,
                $fornecedor_id ?? 'N/D',
                $nota_fiscal ?? 'N/D'
            );

            $this->registrarMovimentacaoEstoque($dados_movimentacao);

            $this->pdo->commit();
            return true;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Erro ao registrar entrada de estoque: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca movimentações de estoque com filtros
     */
    public function buscarMovimentacoesEstoque($filtros = [], $limite = 50, $offset = 0) {
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
    
        // Query com placeholders para filtros, mas LIMIT e OFFSET nomeados
        $sql = "SELECT 
                m.*,
                DATE_FORMAT(m.criado_em, '%d/%m/%Y %H:%i:%s') as data_hora,
                p.nome as produto_nome,
                p.codigo as produto_codigo,
                u.nome as usuario_nome,
                f.nome as fornecedor_nome,
                CONCAT(
                    UPPER(m.tipo_movimentacao), 
                    ' de ', 
                    m.quantidade, 
                    ' unidades - ', 
                    COALESCE(p.nome, CONCAT('Produto ID: ', m.produto_id))
                ) as descricao,
                CONCAT(
                    'Movimentação: ', m.tipo_movimentacao, 
                    ' de ', m.quantidade, ' unidades. ',
                    'Estoque anterior: ', m.quantidade_anterior, 
                    ', atual: ', m.quantidade_atual,
                    CASE 
                        WHEN m.motivo IS NOT NULL THEN CONCAT('. Motivo: ', m.motivo)
                        ELSE ''
                    END,
                    CASE 
                        WHEN m.observacoes IS NOT NULL THEN CONCAT('. Observações: ', m.observacoes)
                        ELSE ''
                    END
                ) as detalhes,
                m.tipo_movimentacao as acao,
                'movimentacoes_estoque' as tabela
            FROM movimentacoes_estoque m
            LEFT JOIN produtos p ON m.produto_id = p.id_produto
            LEFT JOIN usuarios u ON m.usuario_id = u.id
            LEFT JOIN fornecedores f ON m.fornecedor_id = f.id_fornecedor
            {$whereClause}
            ORDER BY m.criado_em DESC 
            LIMIT :limite OFFSET :offset";
    
        $stmt = $this->pdo->prepare($sql);
    
        // Bind dos parâmetros dos filtros (posicionais)
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value);
        }
    
        // Bind dos parâmetros LIMIT e OFFSET como inteiros
        $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    
        $stmt->execute();
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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