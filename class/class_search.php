<?php
// class/class_search.php
session_start();

// Inclui funções comuns
require_once '../includes/functions.php';

// Verifica se está logado
if (!estaLogado()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

// Define o content-type como JSON
header('Content-Type: application/json; charset=utf-8');

require_once '../conexao.php';

$termo = trim($_GET['q'] ?? '');
if (empty($termo) || strlen($termo) < 2) {
    echo json_encode(['results' => [], 'total' => 0, 'query' => $termo]);
    exit;
}

// Função para determinar o prefixo da URL baseado no referer - CORRIGIDA
function getUrlPrefix() {
    // Método 1: Verificar parâmetro 'from' na URL
    $from = $_GET['from'] ?? '';
    if (!empty($from)) {
        switch ($from) {
            case 'dashboard':
            case 'index':
                return '';  // Dashboard está na raiz, não precisa de prefixo
            case 'suppliers':
            case 'read':
            case 'create':
            case 'update':
                return '';  // Já estamos dentro da pasta, não precisa de ../
            default:
                return '';
        }
    }
    
    // Método 2: Verificar HTTP_REFERER
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    
    // Se a busca foi feita do dashboard principal (index.php na raiz)
    if (strpos($referer, '/index.php') !== false || 
        (strpos($referer, '.php') === false && preg_match('/\/$/', $referer))) {
        return '';
    }
    
    // Se a busca foi feita de uma subpasta (read/, create/, etc.)
    // Neste caso, estamos em class/ e queremos ir para read/, então não precisamos de ../
    if (strpos($referer, '/read/') !== false || 
        strpos($referer, '/create/') !== false || 
        strpos($referer, '/update/') !== false) {
        return '';
    }
    
    // Padrão: não usar prefixo
    return '';
}

try {
    $bd = new BancoDeDados();
    $resultados = [];
    $urlPrefix = getUrlPrefix();

    // Buscar produtos (se tem permissão)
    if (temPermissao('listar_produtos')) {
        $stmt = $bd->pdo->prepare("
            SELECT id_produto, nome, codigo, tipo, estoque_atual, preco_unitario, estoque_minimo
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
            $estoqueBaixo = $produto['estoque_atual'] <= ($produto['estoque_minimo'] ?? 10);
            $resultados[] = [
                'id' => $produto['id_produto'],
                'title' => sanitizar($produto['nome']),
                'subtitle' => "Código: " . sanitizar($produto['codigo']) . " • Estoque: {$produto['estoque_atual']} unid.",
                'description' => "Tipo: " . ucfirst(sanitizar($produto['tipo'])) . " • " . formatarMoeda($produto['preco_unitario']),
                'icon' => 'fas fa-box',
                'type' => 'produto',
                'url' => '../read/focus_product.php?id=' . $produto['id_produto'],
                'badge' => $estoqueBaixo ? 'Estoque baixo' : null,
                'badgeClass' => $estoqueBaixo ? 'badge-warning' : null
            ];
        }
    }

    // Buscar fornecedores - CAMINHO CORRETO
    $stmt = $bd->pdo->prepare("
        SELECT id_fornecedor, nome_empresa, atividade, telefone_representante, nome_representante, cnpj
        FROM fornecedores 
        WHERE nome_empresa LIKE :termo OR atividade LIKE :termo OR nome_representante LIKE :termo OR cnpj LIKE :termo
        ORDER BY 
            CASE 
                WHEN nome_empresa LIKE :termo_exato THEN 1
                WHEN nome_empresa LIKE :termo_inicio THEN 2
                WHEN cnpj LIKE :termo_exato THEN 3
                ELSE 4
            END
        LIMIT 5
    ");
    
    $stmt->execute([
        'termo' => "%{$termo}%",
        'termo_exato' => $termo,
        'termo_inicio' => "{$termo}%"
    ]);
    
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fornecedor) {
        // Formatar CNPJ se disponível
        $cnpjFormatado = '';
        if (!empty($fornecedor['cnpj'])) {
            $cnpj = preg_replace('/\D/', '', $fornecedor['cnpj']);
            if (strlen($cnpj) == 14) {
                $cnpjFormatado = sprintf(
                    '%s.%s.%s/%s-%s',
                    substr($cnpj, 0, 2),
                    substr($cnpj, 2, 3),
                    substr($cnpj, 5, 3),
                    substr($cnpj, 8, 4),
                    substr($cnpj, 12, 2)
                );
            } else {
                $cnpjFormatado = $fornecedor['cnpj'];
            }
        }
        
        // Construir descrição com informações disponíveis
        $descricao = '';
        if (!empty($fornecedor['nome_representante'])) {
            $descricao = "Representante: " . sanitizar($fornecedor['nome_representante']);
        }
        if (!empty($cnpjFormatado)) {
            $descricao .= (!empty($descricao) ? ' • ' : '') . "CNPJ: " . $cnpjFormatado;
        }
        
        $resultados[] = [
            'id' => $fornecedor['id_fornecedor'],
            'title' => sanitizar($fornecedor['nome_empresa']),
            'subtitle' => sanitizar($fornecedor['atividade'] ?? 'Atividade não informada'),
            'description' => !empty($descricao) ? $descricao : 'Informações adicionais não disponíveis',
            'icon' => 'fas fa-truck',
            'type' => 'fornecedor',
            'url' => '../read/visualizar.php?id=' . $fornecedor['id_fornecedor'], // CAMINHO FIXO
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
                'title' => sanitizar($usuario['nome']),
                'subtitle' => sanitizar($usuario['email']),
                'description' => "Perfil: " . ucfirst(sanitizar($usuario['perfil'])),
                'icon' => 'fas fa-user',
                'type' => 'usuario',
                'url' => '../read/read_user.php?id=' . $usuario['id'],
                'badge' => !$usuario['ativo'] ? 'Inativo' : null,
                'badgeClass' => !$usuario['ativo'] ? 'badge-danger' : null
            ];
        }
    }

    // Adicionar seções/páginas relevantes - CAMINHOS CORRETOS
    $secoes = [];
    
    if (temPermissao('listar_produtos')) {
        if (stripos('produtos', $termo) !== false || stripos('estoque', $termo) !== false) {
            $secoes[] = [
                'title' => 'Lista de Produtos',
                'subtitle' => 'Ver todos os produtos cadastrados',
                'description' => 'Gerencie o estoque e visualize produtos',
                'icon' => 'fas fa-boxes',
                'type' => 'secao',
                'url' => '../read/read_product.php'
            ];
        }
        
        if (temPermissao('cadastrar_produtos') && (stripos('cadastrar', $termo) !== false || stripos('novo produto', $termo) !== false)) {
            $secoes[] = [
                'title' => 'Cadastrar Produto',
                'subtitle' => 'Adicionar novo produto ao estoque',
                'description' => 'Formulário de cadastro de produtos',
                'icon' => 'fas fa-plus',
                'type' => 'secao',
                'url' => '../create/create_product.php'
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
            'url' => '../read/read_supplier.php'
        ];
    }

    if (temPermissao('gerenciar_usuarios') && (stripos('usuarios', $termo) !== false || stripos('users', $termo) !== false)) {
        $secoes[] = [
            'title' => 'Lista de Usuários',
            'subtitle' => 'Gerenciar usuários do sistema',
            'description' => 'Administração de usuários e permissões',
            'icon' => 'fas fa-users',
            'type' => 'secao',
            'url' => '../read/read_user.php'
        ];
    }

    if (stripos('dashboard', $termo) !== false || stripos('inicio', $termo) !== false || stripos('home', $termo) !== false) {
        $secoes[] = [
            'title' => 'Dashboard',
            'subtitle' => 'Visão geral do sistema',
            'description' => 'Estatísticas e resumo geral',
            'icon' => 'fas fa-chart-line',
            'type' => 'secao',
            'url' => '../index.php'
        ];
    }

    $resultados = array_merge($resultados, $secoes);

    // Limitar total de resultados
    $resultados = array_slice($resultados, 0, 10);

    echo json_encode([
        'results' => $resultados,
        'total' => count($resultados),
        'query' => $termo,
        'debug_info' => [
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'not_set',
            'url_prefix' => $urlPrefix,
            'from_param' => $_GET['from'] ?? 'not_set',
            'script_path' => __FILE__,
            'working_directory' => getcwd()
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Erro na pesquisa: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>