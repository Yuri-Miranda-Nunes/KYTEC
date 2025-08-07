<?php
// search_api.php
session_start();

// Verifica se está logado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

// Função para verificar permissões
function temPermissao($permissao) {
    return in_array($permissao, $_SESSION['permissoes'] ?? []);
}

require_once 'conexao.php';

$termo = trim($_GET['q'] ?? '');
if (empty($termo) || strlen($termo) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$bd = new BancoDeDados();
$resultados = [];

try {
    // Buscar produtos (se tem permissão)
    if (temPermissao('listar_produtos')) {
        $stmt = $bd->pdo->prepare("
            SELECT id_produto, nome, codigo, tipo, estoque_atual, preco_unitario
            FROM produtos 
            WHERE (nome LIKE :termo OR codigo LIKE :termo OR descricao LIKE :termo) 
            AND ativo = 1
            ORDER BY 
                CASE 
                    WHEN nome LIKE :termo_exato THEN 1
                    WHEN codigo LIKE :termo_exato THEN 2
                    WHEN nome LIKE :termo_inicio THEN 3
                    ELSE 4
                END
            LIMIT 5
        ");
        
        $stmt->execute([
            'termo' => "%{$termo}%",
            'termo_exato' => $termo,
            'termo_inicio' => "{$termo}%"
        ]);
        
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $produto) {
            $estoqueBaixo = $produto['estoque_atual'] <= 10;
            $resultados[] = [
                'id' => $produto['id_produto'],
                'title' => $produto['nome'],
                'subtitle' => "Código: {$produto['codigo']} • Estoque: {$produto['estoque_atual']} unid.",
                'description' => "Tipo: " . ucfirst($produto['tipo']) . " • R$ " . number_format($produto['preco_unitario'], 2, ',', '.'),
                'icon' => 'fas fa-box',
                'type' => 'produto',
                'url' => 'read/focus_product.php?id=' . $produto['id_produto'],
                'badge' => $estoqueBaixo ? 'Estoque baixo' : null,
                'badgeClass' => $estoqueBaixo ? 'badge-warning' : null
            ];
        }
    }

    // Buscar fornecedores
    $stmt = $bd->pdo->prepare("
        SELECT id_fornecedor, nome_empresa, atividade, telefone_representante, nome_representante
        FROM fornecedores 
        WHERE nome_empresa LIKE :termo OR atividade LIKE :termo OR nome_representante LIKE :termo
        ORDER BY 
            CASE 
                WHEN nome_empresa LIKE :termo_exato THEN 1
                WHEN nome_empresa LIKE :termo_inicio THEN 2
                ELSE 3
            END
        LIMIT 5
    ");
    
    $stmt->execute([
        'termo' => "%{$termo}%",
        'termo_exato' => $termo,
        'termo_inicio' => "{$termo}%"
    ]);
    
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fornecedor) {
        $resultados[] = [
            'id' => $fornecedor['id_fornecedor'],
            'title' => $fornecedor['nome_empresa'],
            'subtitle' => $fornecedor['atividade'],
            'description' => $fornecedor['nome_representante'] ? "Representante: {$fornecedor['nome_representante']}" : null,
            'icon' => 'fas fa-truck',
            'type' => 'fornecedor',
            'url' => 'read/read_supplier.php',
            'badge' => null,
            'badgeClass' => null
        ];
    }

    // Buscar usuários (se tem permissão)
    if (temPermissao('gerenciar_usuarios')) {
        $stmt = $bd->pdo->prepare("
            SELECT id, nome, email, perfil, ativo
            FROM usuarios 
            WHERE (nome LIKE :termo OR email LIKE :termo)
            ORDER BY 
                CASE 
                    WHEN nome LIKE :termo_exato THEN 1
                    WHEN nome LIKE :termo_inicio THEN 2
                    ELSE 3
                END
            LIMIT 5
        ");
        
        $stmt->execute([
            'termo' => "%{$termo}%",
            'termo_exato' => $termo,
            'termo_inicio' => "{$termo}%"
        ]);
        
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $usuario) {
            $resultados[] = [
                'id' => $usuario['id'],
                'title' => $usuario['nome'],
                'subtitle' => $usuario['email'],
                'description' => "Perfil: " . ucfirst($usuario['perfil']),
                'icon' => 'fas fa-user',
                'type' => 'usuario',
                'url' => 'read/read_user.php',
                'badge' => !$usuario['ativo'] ? 'Inativo' : null,
                'badgeClass' => !$usuario['ativo'] ? 'badge-danger' : null
            ];
        }
    }

    // Adicionar seções/páginas relevantes
    $secoes = [];
    
    if (temPermissao('listar_produtos')) {
        if (stripos('produtos', $termo) !== false || stripos('estoque', $termo) !== false) {
            $secoes[] = [
                'title' => 'Lista de Produtos',
                'subtitle' => 'Ver todos os produtos cadastrados',
                'description' => 'Gerencie o estoque e visualize produtos',
                'icon' => 'fas fa-boxes',
                'type' => 'secao',
                'url' => 'read/read_product.php'
            ];
        }
        
        if (temPermissao('cadastrar_produtos') && (stripos('cadastrar', $termo) !== false || stripos('novo produto', $termo) !== false)) {
            $secoes[] = [
                'title' => 'Cadastrar Produto',
                'subtitle' => 'Adicionar novo produto ao estoque',
                'description' => 'Formulário de cadastro de produtos',
                'icon' => 'fas fa-plus',
                'type' => 'secao',
                'url' => 'create/create_product.php'
            ];
        }
    }

    if (stripos('fornecedores', $termo) !== false || stripos('suppliers', $termo) !== false) {
        $secoes[] = [
            'title' => 'Lista de Fornecedores',
            'subtitle' => 'Ver todos os fornecedores cadastrados',
            'description' => 'Gerencie seus fornecedores',
            'icon' => 'fas fa-truck',
            'type' => 'secao',
            'url' => 'read/read_supplier.php'
        ];
    }

    if (temPermissao('gerenciar_usuarios') && (stripos('usuarios', $termo) !== false || stripos('users', $termo) !== false)) {
        $secoes[] = [
            'title' => 'Lista de Usuários',
            'subtitle' => 'Gerenciar usuários do sistema',
            'description' => 'Administração de usuários e permissões',
            'icon' => 'fas fa-users',
            'type' => 'secao',
            'url' => 'read/read_user.php'
        ];
    }

    if (stripos('dashboard', $termo) !== false || stripos('inicio', $termo) !== false || stripos('home', $termo) !== false) {
        $secoes[] = [
            'title' => 'Dashboard',
            'subtitle' => 'Visão geral do sistema',
            'description' => 'Estatísticas e resumo geral',
            'icon' => 'fas fa-chart-line',
            'type' => 'secao',
            'url' => 'index.php'
        ];
    }

    $resultados = array_merge($resultados, $secoes);

    // Limitar total de resultados
    $resultados = array_slice($resultados, 0, 10);

    echo json_encode([
        'results' => $resultados,
        'total' => count($resultados),
        'query' => $termo
    ]);

} catch (Exception $e) {
    error_log("Erro na pesquisa: " . $e->getMessage());
    echo json_encode(['error' => 'Erro interno do servidor']);
}