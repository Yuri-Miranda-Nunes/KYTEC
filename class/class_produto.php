<?php
session_start();

// Verifica se está logado e tem permissão
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php");
    exit;
}

if (!in_array('cadastrar_produtos', $_SESSION['permissoes'] ?? [])) {
    $_SESSION['mensagem'] = "Acesso negado. Você não tem permissão para cadastrar produtos.";
    $_SESSION['tipo_mensagem'] = "error";
    header("Location: ../index.php");
    exit;
}

require_once '../conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../create/create_product.php");
    exit;
}

try {
    $bd = new BancoDeDados();
    
    // Validar e sanitizar dados
    $nome = trim($_POST['nome'] ?? '');
    $codigo = trim($_POST['codigo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $tipo = $_POST['tipo'] ?? 'acabado';
    $preco_unitario = floatval($_POST['preco_unitario'] ?? 0);
    $estoque_minimo = intval($_POST['estoque_minimo'] ?? 0);
    $estoque_atual = intval($_POST['estoque_atual'] ?? 0);
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    
    // Validações
    $erros = [];
    
    if (empty($nome)) {
        $erros[] = "Nome do produto é obrigatório.";
    } elseif (strlen($nome) > 100) {
        $erros[] = "Nome do produto deve ter no máximo 100 caracteres.";
    }
    
    if (empty($codigo)) {
        $erros[] = "Código do produto é obrigatório.";
    } elseif (strlen($codigo) > 50) {
        $erros[] = "Código do produto deve ter no máximo 50 caracteres.";
    }
    
    if (!in_array($tipo, ['acabado', 'matéria-prima', 'outro'])) {
        $erros[] = "Tipo de produto inválido.";
    }
    
    if ($preco_unitario < 0) {
        $erros[] = "Preço unitário não pode ser negativo.";
    }
    
    if ($estoque_minimo < 0) {
        $erros[] = "Estoque mínimo não pode ser negativo.";
    }
    
    if ($estoque_atual < 0) {
        $erros[] = "Estoque atual não pode ser negativo.";
    }
    
    if (strlen($descricao) > 1000) {
        $erros[] = "Descrição deve ter no máximo 1000 caracteres.";
    }
    
    // Verificar se código já existe
    if (empty($erros)) {
        $stmt_check = $bd->pdo->prepare("SELECT COUNT(*) FROM produtos WHERE codigo = ?");
        $stmt_check->execute([$codigo]);
        if ($stmt_check->fetchColumn() > 0) {
            $erros[] = "Código já existe. Escolha outro código.";
        }
    }
    
    // Se houver erros, redireciona de volta com mensagem
    if (!empty($erros)) {
        $_SESSION['mensagem'] = implode(" ", $erros);
        $_SESSION['tipo_mensagem'] = "error";
        $_SESSION['form_data'] = $_POST; // Preserva os dados do formulário
        header("Location: ../create/create_product.php");
        exit;
    }
    
    // Preparar e executar inserção
    $sql = "INSERT INTO produtos (
                codigo, 
                nome, 
                descricao, 
                tipo, 
                preco_unitario, 
                estoque_minimo, 
                estoque_atual,
                ativo,
                usuario_criacao,
                criado_em
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $bd->pdo->prepare($sql);
    $resultado = $stmt->execute([
        $codigo,
        $nome,
        $descricao,
        $tipo,
        $preco_unitario,
        $estoque_minimo,
        $estoque_atual,
        $ativo,
        $_SESSION['usuario_id']
    ]);
    
    if ($resultado) {
        $produto_id = $bd->pdo->lastInsertId();
        
        // Registrar log da operação
        $log_sql = "INSERT INTO logs (
                        usuario_id, 
                        acao, 
                        tabela, 
                        registro_id, 
                        detalhes, 
                        ip, 
                        user_agent, 
                        criado_em
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $log_stmt = $bd->pdo->prepare($log_sql);
        $log_stmt->execute([
            $_SESSION['usuario_id'],
            'CREATE',
            'produtos',
            $produto_id,
            json_encode([
                'codigo' => $codigo,
                'nome' => $nome,
                'tipo' => $tipo,
                'preco_unitario' => $preco_unitario,
                'estoque_inicial' => $estoque_atual
            ]),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        // Registrar movimentação inicial de estoque se houver quantidade
        if ($estoque_atual > 0) {
            $mov_sql = "INSERT INTO movimentacoes_estoque (
                            produto_id,
                            tipo_movimentacao,
                            quantidade,
                            quantidade_anterior,
                            quantidade_atual,
                            motivo,
                            usuario_id,
                            criado_em
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $mov_stmt = $bd->pdo->prepare($mov_sql);
            $mov_stmt->execute([
                $produto_id,
                'entrada',
                $estoque_atual,
                0,
                $estoque_atual,
                'Estoque inicial do produto',
                $_SESSION['usuario_id']
            ]);
        }
        
        $_SESSION['mensagem'] = "Produto cadastrado com sucesso! Código: " . htmlspecialchars($codigo);
        $_SESSION['tipo_mensagem'] = "success";
        
        // Limpar dados do formulário da sessão
        unset($_SESSION['form_data']);
        
        // Redirecionar para a listagem ou para cadastrar outro
        if (isset($_POST['action']) && $_POST['action'] === 'save_and_new') {
            header("Location: ../create/create_product.php?novo=1");
        } else {
            header("Location: ../read/read_product.php");
        }
        exit;
        
    } else {
        throw new Exception("Erro ao inserir produto no banco de dados.");
    }
    
} catch (PDOException $e) {
    error_log("Erro PDO ao cadastrar produto: " . $e->getMessage());
    $_SESSION['mensagem'] = "Erro interno do sistema. Tente novamente mais tarde.";
    $_SESSION['tipo_mensagem'] = "error";
    $_SESSION['form_data'] = $_POST;
    header("Location: ../create/create_product.php");
    exit;
    
} catch (Exception $e) {
    error_log("Erro ao cadastrar produto: " . $e->getMessage());
    $_SESSION['mensagem'] = "Erro: " . $e->getMessage();
    $_SESSION['tipo_mensagem'] = "error";
    $_SESSION['form_data'] = $_POST;
    header("Location: ../create/create_product.php");
    exit;
}
?>