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
    $usuario_id = isset($_POST['usuario_id']) ? (int) $_POST['usuario_id'] : null;
    $quantidade = isset($_POST['quantidade']) ? (int) $_POST['quantidade'] : null;
    $motivo = isset($_POST['motivo']) ? trim($_POST['motivo']) : null;
    $destino = !empty($_POST['destino']) ? trim($_POST['destino']) : null;
    $observacoes = !empty($_POST['observacoes']) ? trim($_POST['observacoes']) : null;
 
    // Validar dados obrigatórios
    if (!$produto_id || !$usuario_id || !$quantidade || !$motivo) {
        throw new Exception('Todos os campos obrigatórios devem ser preenchidos.');
    }
 
    if ($quantidade <= 0) {
        throw new Exception('A quantidade deve ser maior que zero.');
    }
 
    // Verificar se o usuário da sessão confere com o enviado
    if ($usuario_id !== $_SESSION['usuario_id']) {
        throw new Exception('Usuário inválido para esta operação.');
    }
 
    // Instanciar classes necessárias
    $banco = new BancoDeDados();
    $pdo = $banco->pdo;
    $logManager = new LogManager($pdo);
 
    // Verificar se o produto existe e obter dados atuais
    $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id_produto = ? AND ativo = 1");
    $stmt->execute([$produto_id]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);
 
    if (!$produto) {
        throw new Exception('Produto não encontrado ou inativo.');
    }
 
    // Verificar se há estoque suficiente
    if ($quantidade > $produto['estoque_atual']) {
        throw new Exception("Estoque insuficiente. Disponível: {$produto['estoque_atual']} unidades.");
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
                     strpos($usuario['permissoes'], 'editar_produtos') !== false ||
                     strpos($usuario['permissoes'], 'cadastrar_produtos') !== false;
 
    if (!$tem_permissao) {
        throw new Exception('Usuário não possui permissão para registrar saídas de estoque.');
    }
 
    // Validar motivos permitidos
    $motivosPermitidos = [
        'venda', 'uso_interno', 'transferencia', 'perda', 
        'devolucao', 'amostra', 'producao', 'outros'
    ];
 
    if (!in_array($motivo, $motivosPermitidos)) {
        throw new Exception('Motivo de saída inválido.');
    }
 
    // Calcular novo estoque
    $novoEstoque = $produto['estoque_atual'] - $quantidade;
 
    // Registrar a saída usando o LogManager
    $resultado = $logManager->registrarSaidaEstoque(
        $produto_id,
        $usuario_id,
        $quantidade,
        $motivo,
        $destino,
        $observacoes
    );
 
    if (!$resultado) {
        throw new Exception('Erro ao registrar a saída de estoque.');
    }
 
    // Preparar mensagem de sucesso
    $mensagemSucesso = "Saída de estoque registrada com sucesso!<br>";
    $mensagemSucesso .= "Produto: " . htmlspecialchars($produto['nome']) . "<br>";
    $mensagemSucesso .= "Quantidade: " . number_format($quantidade, 0, ',', '.') . " unidades<br>";
    $mensagemSucesso .= "Novo estoque: " . number_format($novoEstoque, 0, ',', '.') . " unidades";
 
    // Adicionar alerta se estoque ficou baixo
    if ($novoEstoque <= $produto['estoque_minimo']) {
        $mensagemSucesso .= "<br><strong style='color: #dc2626;'>⚠️ Atenção: Estoque abaixo do mínimo recomendado!</strong>";
    } elseif ($novoEstoque <= ($produto['estoque_minimo'] * 1.5)) {
        $mensagemSucesso .= "<br><strong style='color: #d97706;'>⚠️ Cuidado: Estoque se aproximando do limite mínimo!</strong>";
    }
 
    $_SESSION['mensagem_sucesso'] = $mensagemSucesso;
 
    // Redirecionar de volta para a página do produto
    header("Location: focus_product.php?id={$produto_id}");
    exit;
 
} catch (Exception $e) {
    error_log("Erro em processar_saida.php: " . $e->getMessage());
    $_SESSION['mensagem_erro'] = $e->getMessage();
    // Redirecionar de volta para a página de saída de estoque se houver produto_id
    if (isset($produto_id) && $produto_id) {
        header("Location: saida_estoque.php?id={$produto_id}");
    } else {
        header("Location: ../read/read_product.php");
    }
    exit;
}
?>