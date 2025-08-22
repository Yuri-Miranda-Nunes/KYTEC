<?php
session_start();
 
// Verifica se está logado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php");
    exit;
}
 
// Verificar se a requisição é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['mensagem_erro'] = 'Método de requisição inválido.';
    header("Location: ../read/read_product.php");
    exit;
}
 
try {
    require_once '../conexao.php';
    require_once '../log/log_manager.php';
 
    // Obter dados do formulário
    $produto_id = isset($_POST['produto_id']) ? (int) $_POST['produto_id'] : null;
    $quantidade = isset($_POST['quantidade']) ? (int) $_POST['quantidade'] : null;
    $fornecedor_id = !empty($_POST['fornecedor_id']) ? (int) $_POST['fornecedor_id'] : null;
    $valor_unitario = !empty($_POST['valor_unitario']) ? (float) $_POST['valor_unitario'] : null;
    $nota_fiscal = !empty($_POST['nota_fiscal']) ? trim($_POST['nota_fiscal']) : null;
    $observacoes = !empty($_POST['observacoes']) ? trim($_POST['observacoes']) : null;
    $usuario_id = $_SESSION['usuario_id'];
 
    // Validar dados obrigatórios
    if (!$produto_id || !$quantidade || $quantidade <= 0) {
        throw new Exception('Produto e quantidade são obrigatórios e devem ser válidos.');
    }
 
    // Instanciar classes necessárias
    $banco = new BancoDeDados();
    $pdo = $banco->pdo;
    $logManager = new LogManager($pdo);
 
    // Verificar se o produto existe
    $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id_produto = ? AND ativo = 1");
    $stmt->execute([$produto_id]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);
 
    if (!$produto) {
        throw new Exception('Produto não encontrado ou inativo.');
    }
 
    // Verificar permissões do usuário
    $stmt = $pdo->prepare("
        SELECT u.perfil, GROUP_CONCAT(p.nome_permissao) as permissoes
        FROM usuarios u
        LEFT JOIN usuario_permissoes up ON u.id = up.usuario_id
        LEFT JOIN permissoes p ON up.permissao_id = p.id
        WHERE u.id = ? AND u.ativo = 1
        GROUP BY u.id
    ");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
 
    if (!$usuario) {
        throw new Exception('Usuário não encontrado ou inativo.');
    }
 
    // Verificar permissão (admin sempre pode, outros precisam da permissão específica)
    $tem_permissao = ($usuario['perfil'] === 'admin') || 
                     strpos($usuario['permissoes'], 'cadastrar_produtos') !== false ||
                     strpos($usuario['permissoes'], 'editar_produtos') !== false;
 
    if (!$tem_permissao) {
        throw new Exception('Usuário não possui permissão para registrar entradas de estoque.');
    }
 
    // Verificar se fornecedor existe (se fornecido)
    if ($fornecedor_id) {
        $stmt = $pdo->prepare("SELECT id_fornecedor FROM fornecedores WHERE id_fornecedor = ? AND ativo = 1");
        $stmt->execute([$fornecedor_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Fornecedor não encontrado ou inativo.');
        }
    }
 
    // Usar o preço do produto se valor unitário não foi informado
    if (!$valor_unitario) {
        $valor_unitario = $produto['preco_unitario'];
    }
 
    // Calcular novo estoque
    $novoEstoque = $produto['estoque_atual'] + $quantidade;
 
    // Registrar a entrada usando o LogManager
    $resultado = $logManager->registrarEntradaEstoque(
        $produto_id,
        $usuario_id,
        $quantidade,
        $fornecedor_id,
        $valor_unitario,
        $nota_fiscal,
        $observacoes
    );
 
    if (!$resultado) {
        throw new Exception('Erro ao registrar a entrada de estoque.');
    }
 
    // Preparar mensagem de sucesso
    $mensagemSucesso = "Entrada de estoque registrada com sucesso!<br>";
    $mensagemSucesso .= "Produto: " . htmlspecialchars($produto['nome']) . "<br>";
    $mensagemSucesso .= "Quantidade: " . number_format($quantidade, 0, ',', '.') . " unidades<br>";
    $mensagemSucesso .= "Novo estoque: " . number_format($novoEstoque, 0, ',', '.') . " unidades<br>";
    $mensagemSucesso .= "Valor total: R$ " . number_format($quantidade * $valor_unitario, 2, ',', '.');
 
    // Adicionar informações adicionais se disponíveis
    if ($fornecedor_id) {
        $stmt = $pdo->prepare("SELECT nome FROM fornecedores WHERE id_fornecedor = ?");
        $stmt->execute([$fornecedor_id]);
        $fornecedor = $stmt->fetch();
        if ($fornecedor) {
            $mensagemSucesso .= "<br>Fornecedor: " . htmlspecialchars($fornecedor['nome']);
        }
    }
 
    if ($nota_fiscal) {
        $mensagemSucesso .= "<br>Nota Fiscal: " . htmlspecialchars($nota_fiscal);
    }
 
    $_SESSION['mensagem_sucesso'] = $mensagemSucesso;
 
    // Redirecionar de volta para a página do produto
    header("Location: focus_product.php?id={$produto_id}");
    exit;
 
} catch (Exception $e) {
    error_log("Erro em processar_entrada.php: " . $e->getMessage());
    $_SESSION['mensagem_erro'] = $e->getMessage();
    // Redirecionar de volta para a página de entrada de estoque se houver produto_id
    if (isset($produto_id) && $produto_id) {
        header("Location: entrada_estoque.php?id={$produto_id}");
    } else {
        header("Location: ../read/read_product.php");
    }
    exit;
}
?>