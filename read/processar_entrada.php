<?php
session_start();

// Configurações de resposta JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Usuário não autenticado'
    ]);
    exit;
}

// Verificar se a requisição é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Método não permitido'
    ]);
    exit;
}

try {
    // Incluir arquivos necessários
    require_once '../conexao.php';
    require_once '../log/log_manager.php'; // ou onde estiver a classe que contém registrarEntradaEstoque

    // Obter dados da requisição
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Se não for JSON, tentar $_POST
    if (!$input) {
        $input = $_POST;
    }

    // Validar dados obrigatórios
    $produto_id = isset($input['produto_id']) ? (int) $input['produto_id'] : null;
    $quantidade = isset($input['quantidade']) ? (int) $input['quantidade'] : null;
    
    if (!$produto_id || !$quantidade || $quantidade <= 0) {
        throw new Exception('Produto ID e quantidade são obrigatórios e devem ser válidos');
    }

    // Dados opcionais
    $fornecedor_id = !empty($input['fornecedor_id']) ? (int) $input['fornecedor_id'] : null;
    $valor_unitario = !empty($input['valor_unitario']) ? (float) $input['valor_unitario'] : null;
    $nota_fiscal = !empty($input['nota_fiscal']) ? trim($input['nota_fiscal']) : null;
    $observacoes = !empty($input['observacoes']) ? trim($input['observacoes']) : null;
    
    $usuario_id = $_SESSION['usuario_id'];

    // Verificar permissões do usuário (opcional - adapte conforme seu sistema)
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
        throw new Exception('Usuário não encontrado ou inativo');
    }

    // Verificar se tem permissão (admin sempre pode, outros precisam da permissão específica)
    $tem_permissao = ($usuario['perfil'] === 'admin') || 
                     strpos($usuario['permissoes'], 'cadastrar_produtos') !== false ||
                     strpos($usuario['permissoes'], 'editar_produtos') !== false;

    if (!$tem_permissao) {
        throw new Exception('Usuário não possui permissão para registrar entradas de estoque');
    }

    // Criar instância da classe de estoque
    $estoque = new LogManager($pdo); // Ajuste conforme sua estrutura de classes

    // Registrar a entrada
    $resultado = $estoque->registrarEntradaEstoque(
        $produto_id,
        $usuario_id,
        $quantidade,
        $fornecedor_id,
        $valor_unitario,
        $nota_fiscal,
        $observacoes
    );

    header("Location: ../read/focus_product.php?produto_id={$produto_id}");

} catch (Exception $e) {
    error_log("Erro em processar_entrada.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ]);
}
?>