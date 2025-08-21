<?php
session_start();

if (!in_array('listar_produtos', $_SESSION['permissoes'])) {
    echo "Acesso negado.";
    exit;
}
// Verifica se está logado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php");
    exit;
}

// Função para verificar permissões
function temPermissao($permissao)
{
    return in_array($permissao, $_SESSION['permissoes'] ?? []);
}

require_once '../conexao.php';
$bd = new BancoDeDados();

// Parâmetros de ordenação
$ordem = $_GET['ordem'] ?? 'nome';
$direcao = $_GET['direcao'] ?? 'asc';

// Colunas permitidas para ordenação (segurança)
$colunasPermitidas = ['nome', 'codigo', 'tipo', 'descricao', 'preco_unitario', 'estoque_minimo', 'estoque_atual', 'ativo'];

// Validar ordem e direção
if (!in_array($ordem, $colunasPermitidas)) {
    $ordem = 'nome';
}
if (!in_array($direcao, ['asc', 'desc'])) {
    $direcao = 'asc';
}

$sql = "SELECT * FROM produtos ORDER BY {$ordem} {$direcao}";
$stmt = $bd->pdo->query($sql);
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

function novaDirecao($coluna)
{
    $ordemAtual = $_GET['ordem'] ?? '';
    $direcaoAtual = $_GET['direcao'] ?? 'asc';
    return ($ordemAtual === $coluna && $direcaoAtual === 'asc') ? 'desc' : 'asc';
}

function iconeOrdenacao($coluna)
{
    $ordemAtual = $_GET['ordem'] ?? '';
    $direcaoAtual = $_GET['direcao'] ?? 'asc';
    if ($ordemAtual === $coluna) {
        return $direcaoAtual === 'asc' ? '↑' : '↓';
    }
    return '';
}

function urlOrdenar($coluna)
{
    $direcao = novaDirecao($coluna);
    $query = $_GET;
    $query['ordem'] = $coluna;
    $query['direcao'] = $direcao;
    return '?' . http_build_query($query);
}
// Função para determinar se a página atual está ativa
function isActivePage($page)
{
    $current = basename($_SERVER['PHP_SELF']);
    return $current === $page ? 'active' : '';
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Produtos - Sistema de Estoque</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.6;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: #1e293b;
            color: white;
            padding: 0;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 24px 20px;
            background: #0f172a;
            border-bottom: 1px solid #334155;
        }

        .sidebar-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .nav-section {
            margin-bottom: 24px;
        }

        .nav-section-title {
            color: #94a3b8;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0 20px 8px;
            margin-bottom: 8px;
        }

        .nav-item {
            margin-bottom: 2px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: #cbd5e1;
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }

        .nav-link:hover {
            background: #334155;
            color: white;
            transform: translateX(4px);
        }

        .nav-link.active {
            background: #3b82f6;
            color: white;
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 24px;
        }

        /* Header */
        .header {
            background: white;
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 24px;
        }

        .header-left {
            flex-shrink: 0;
        }

        .header-left h1 {
            font-size: 1.875rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .header-subtitle {
            color: #64748b;
            font-size: 0.875rem;
        }

        /* Search Bar Styles */
        .search-container {
            flex: 1;
            max-width: 500px;
            position: relative;
        }

        .search-wrapper {
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 12px 16px 12px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.875rem;
            background: #f8fafc;
            transition: all 0.2s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #3b82f6;
            background: white;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 1rem;
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            max-height: 400px;
            overflow-y: auto;
            display: none;
            margin-top: 4px;
        }

        .search-results.show {
            display: block;
        }

        .search-result-item {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            transition: background 0.2s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .search-result-item:hover {
            background: #f8fafc;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            flex-shrink: 0;
        }

        .search-result-content {
            flex: 1;
            min-width: 0;
        }

        .search-result-title {
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-result-subtitle {
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 2px;
        }

        .search-result-description {
            font-size: 0.75rem;
            color: #94a3b8;
        }

        .search-result-badge {
            background: #fef3c7;
            color: #d97706;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.625rem;
            font-weight: 500;
        }

        .search-result-badge.badge-warning {
            background: #fef3c7;
            color: #d97706;
        }

        .search-result-badge.badge-danger {
            background: #fee2e2;
            color: #dc2626;
        }

        .search-no-results {
            padding: 24px;
            text-align: center;
            color: #64748b;
            font-size: 0.875rem;
        }

        .search-loading {
            padding: 16px;
            text-align: center;
            color: #64748b;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid #e2e8f0;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-shrink: 0;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 8px;
            text-decoration: none;
            color: inherit;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        .user-info:hover {
            background: rgba(0, 0, 0, 0.1);
            /* fundo leve */
            cursor: pointer;
            transform: scale(1.02);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .user-details h3 {
            font-size: 0.875rem;
            font-weight: 600;
            color: #1e293b;
        }

        .user-details p {
            font-size: 0.75rem;
            color: #64748b;
        }

        .btn-logout {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-logout:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }

        /* Products Table */
        .products-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 32px;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .products-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .products-table th {
            background: #f8fafc;
            color: #374151;
            font-weight: 600;
            padding: 16px 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }

        .products-table td {
            padding: 16px 12px;
            border-bottom: 1px solid #f1f5f9;
            color: #64748b;
            vertical-align: top;
        }

        .products-table tbody tr {
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .products-table tbody tr:hover {
            background: #f8fafc;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .products-table tbody tr:last-child td {
            border-bottom: none;
        }

        .product-name {
            font-weight: 500;
            color: #1e293b;
        }

        .product-code {
            font-family: 'Courier New', monospace;
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.75rem;
            color: #475569;
        }

        .type-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .type-acabado {
            background: #dcfce7;
            color: #16a34a;
        }

        .type-materia-prima {
            background: #fef3c7;
            color: #d97706;
        }

        .type-outro {
            background: #e0e7ff;
            color: #3730a3;
        }

        .stock-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .stock-low {
            background: #fee2e2;
            color: #dc2626;
        }

        .stock-normal {
            background: #dcfce7;
            color: #16a34a;
        }

        .stock-medium {
            background: #fef3c7;
            color: #d97706;
        }

        .price {
            font-weight: 600;
            color: #16a34a;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-ativo {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-inativo {
            background: #fee2e2;
            color: #dc2626;
        }

        .description-cell {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .description-full {
            cursor: help;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
        }

        .btn {
            padding: 10px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            color: #cbd5e1;
        }

        .empty-state h3 {
            font-size: 1.125rem;
            margin-bottom: 8px;
            color: #374151;
        }

        /* Color Schemes for Search Results */
        .blue {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .green {
            background: #dcfce7;
            color: #16a34a;
        }

        .yellow {
            background: #fef3c7;
            color: #d97706;
        }

        .red {
            background: #fee2e2;
            color: #dc2626;
        }

        .purple {
            background: #f3e8ff;
            color: #9333ea;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .main-content {
                margin-left: 0;
            }

            .header {
                flex-direction: column;
                gap: 16px;
            }

            .search-container {
                order: -1;
                max-width: none;
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }

            .header-right {
                flex-direction: column;
                gap: 12px;
                width: 100%;
            }

            .products-table {
                font-size: 0.75rem;
            }

            .products-table th,
            .products-table td {
                padding: 12px 8px;
            }

            .description-cell {
                max-width: 120px;
            }
        }

        /* Success Message */
        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-boxes"></i> KYTEC</h2>
            </div>

            <nav class="sidebar-nav">
                <!-- Dashboard -->
                <div class="nav-section">
                    <div class="nav-item">
                        <a href="../index.php" class="nav-link <?= isActivePage('index.php') ?>">
                            <i class="fas fa-chart-line"></i>
                            <span>Dashboard</span>
                        </a>
                    </div>
                </div>

                <!-- Produtos -->
                <?php if (temPermissao('listar_produtos')): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">Produtos</div>
                        <div class="nav-item">
                            <a href="../read/read_product.php" class="nav-link <?= isActivePage('read_product.php') ?>">
                                <i class="fas fa-list"></i>
                                <span>Listar Produtos</span>
                            </a>
                        </div>
                        <?php if (temPermissao('cadastrar_produtos')): ?>
                            <div class="nav-item">
                                <a href="../create/create_product.php"
                                    class="nav-link <?= isActivePage('create_product.php') ?>">
                                    <i class="fas fa-plus"></i>
                                    <span>Cadastrar Produto</span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Fornecedores -->
                <?php if (temPermissao('cadastrar_produtos')): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">Fornecedores</div>
                        <div class="nav-item">
                            <a href="../read/read_supplier.php" class="nav-link <?= isActivePage('read_supplier.php') ?>">
                                <i class="fas fa-truck"></i>
                                <span>Listar Fornecedores</span>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Logs -->
                <?php if (temPermissao('cadastrar_produtos')): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">Logs</div>
                        <div class="nav-item">
                            <a href="../log/product_input_and_output_log.php"
                                class="nav-link <?= isActivePage('product_input_and_output_log.php') ?>">
                                <i class="fas fa-history"></i>
                                <span>Movimentações</span>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Usuários -->
                <?php if (temPermissao('gerenciar_usuarios')): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">Usuários</div>
                        <div class="nav-item">
                            <a href="../read/read_user.php" class="nav-link <?= isActivePage('read_user.php') ?>">
                                <i class="fas fa-users"></i>
                                <span>Listar Usuários</span>
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="../create/create_user.php" class="nav-link <?= isActivePage('create_user.php') ?>">
                                <i class="fas fa-user-plus"></i>
                                <span>Cadastrar Usuário</span>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Sistema -->
                <div class="nav-section">
                    <div class="nav-section-title">Sistema</div>
                    <div class="nav-item">
                        <a href="../perfil.php" class="nav-link <?= isActivePage('perfil.php') ?>">
                            <i class="fas fa-user-circle"></i>
                            <span>Meu Perfil</span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="../logout.php" class="nav-link">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Sair</span>
                        </a>
                    </div>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <div class="header-left">
                    <h1>Lista de Produtos</h1>
                    <p class="header-subtitle">Gerencie e visualize todos os produtos do estoque</p>
                </div>

                <!-- Search Bar -->
                <div class="search-container">
                    <div class="search-wrapper">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="searchInput" class="search-input" placeholder="Pesquisar produtos, fornecedores, usuários...">
                        <div id="searchResults" class="search-results"></div>
                    </div>
                </div>

                <div class="header-right">
                    <a href="../perfil.php" class="user-info">
                        <div class="user-avatar">
                            <?= strtoupper(substr($_SESSION['usuario_nome'], 0, 1)) ?>
                        </div>
                        <div class="user-details">
                            <h3><?= htmlspecialchars($_SESSION['usuario_nome']) ?></h3>
                            <p><?= htmlspecialchars(ucfirst($_SESSION['usuario_perfil'])) ?></p>
                        </div>
                    </a>

                    <a href="logout.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                        Sair
                    </a>
                </div>

            </div>

            <!-- Success Message -->
            <?php if (!empty($mensagemSucesso)): ?>
                <div class="alert-success">
                    <?= htmlspecialchars($mensagemSucesso) ?>
                </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <?php if (temPermissao('cadastrar_produtos')): ?>
                    <a href="../create/create_product.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Novo Produto
                    </a>
                <?php endif; ?>
            </div>

            <!-- Products Section -->
            <div class="products-section">
                <h2 class="section-title">
                    <i class="fas fa-boxes"></i>
                    Produtos Cadastrados
                </h2>

                <?php if (count($produtos) > 0): ?>
                    <div class="table-container">
                        <table class="products-table">
                            <thead>
                                <tr>
                                    <th><a href="<?= urlOrdenar('nome') ?>">Nome <?= iconeOrdenacao('nome') ?></a></th>
                                    <th><a href="<?= urlOrdenar('codigo') ?>">Código <?= iconeOrdenacao('codigo') ?></a></th>
                                    <th><a href="<?= urlOrdenar('tipo') ?>">Tipo <?= iconeOrdenacao('tipo') ?></a></th>
                                    <th><a href="<?= urlOrdenar('descricao') ?>">Descrição <?= iconeOrdenacao('descricao') ?></a></th>
                                    <th><a href="<?= urlOrdenar('preco_unitario') ?>">Preço Unitário <?= iconeOrdenacao('preco_unitario') ?></a></th>
                                    <th><a href="<?= urlOrdenar('estoque_minimo') ?>">Estoque Mín. <?= iconeOrdenacao('estoque_minimo') ?></a></th>
                                    <th><a href="<?= urlOrdenar('estoque_atual') ?>">Estoque Atual <?= iconeOrdenacao('estoque_atual') ?></a></th>
                                    <th><a href="<?= urlOrdenar('ativo') ?>">Status <?= iconeOrdenacao('ativo') ?></a></th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($produtos as $p): ?>
                                    <tr onclick="window.location.href='focus_product.php?id=<?= $p['id_produto'] ?>'">
                                        <td class="product-name"><?= htmlspecialchars($p['nome']) ?></td>
                                        <td>
                                            <span class="product-code"><?= htmlspecialchars($p['codigo']) ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $tipo = $p['tipo'];
                                            $tipoClass = 'type-outro';
                                            if ($tipo === 'acabado') $tipoClass = 'type-acabado';
                                            elseif ($tipo === 'matéria-prima') $tipoClass = 'type-materia-prima';
                                            ?>
                                            <span class="type-badge <?= $tipoClass ?>">
                                                <?= htmlspecialchars(ucfirst($tipo)) ?>
                                            </span>
                                        </td>
                                        <td class="description-cell" title="<?= htmlspecialchars($p['descricao']) ?>">
                                            <?= htmlspecialchars(strlen($p['descricao']) > 50 ? substr($p['descricao'], 0, 50) . '...' : ($p['descricao'] ?: 'Sem descrição')) ?>
                                        </td>
                                        <td class="price">
                                            R$ <?= number_format($p['preco_unitario'], 2, ',', '.') ?>
                                        </td>
                                        <td>
                                            <span class="stock-badge stock-normal">
                                                <?= $p['estoque_minimo'] ?> unid.
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $estoque = $p['estoque_atual'];
                                            $estoqueMinimo = $p['estoque_minimo'] ?? 10;
                                            $badgeClass = 'stock-normal';

                                            if ($estoque <= $estoqueMinimo) {
                                                $badgeClass = 'stock-low';
                                            } elseif ($estoque <= ($estoqueMinimo * 2)) {
                                                $badgeClass = 'stock-medium';
                                            }
                                            ?>
                                            <span class="stock-badge <?= $badgeClass ?>">
                                                <?= $estoque ?> unid.
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= $p['ativo'] ? 'status-ativo' : 'status-inativo' ?>">
                                                <?= $p['ativo'] ? 'Ativo' : 'Inativo' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-boxes"></i>
                        <h3>Nenhum produto encontrado</h3>
                        <p>Não há produtos cadastrados no sistema ainda.</p>
                        <?php if (temPermissao('cadastrar_produtos')): ?>
                            <br>
                            <a href="../create/create_product.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i>
                                Cadastrar Primeiro Produto
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Search functionality
        let searchTimeout;
        const searchInput = document.getElementById('searchInput');
        const searchResults = document.getElementById('searchResults');

        searchInput.addEventListener('input', function() {
            const query = this.value.trim();

            clearTimeout(searchTimeout);

            if (query.length < 2) {
                hideSearchResults();
                return;
            }

            // Show loading
            showLoading();

            searchTimeout = setTimeout(() => {
                performSearch(query);
            }, 300);
        });

        // Hide results when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-container')) {
                hideSearchResults();
            }
        });

        // Handle keyboard navigation
        searchInput.addEventListener('keydown', function(e) {
            const items = searchResults.querySelectorAll('.search-result-item');
            const activeItem = searchResults.querySelector('.search-result-item.active');

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!activeItem) {
                    items[0]?.classList.add('active');
                } else {
                    activeItem.classList.remove('active');
                    const nextItem = activeItem.nextElementSibling;
                    if (nextItem && nextItem.classList.contains('search-result-item')) {
                        nextItem.classList.add('active');
                    } else {
                        items[0]?.classList.add('active');
                    }
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (!activeItem) {
                    items[items.length - 1]?.classList.add('active');
                } else {
                    activeItem.classList.remove('active');
                    const prevItem = activeItem.previousElementSibling;
                    if (prevItem && prevItem.classList.contains('search-result-item')) {
                        prevItem.classList.add('active');
                    } else {
                        items[items.length - 1]?.classList.add('active');
                    }
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const activeItem = searchResults.querySelector('.search-result-item.active');
                if (activeItem) {
                    activeItem.click();
                }
            } else if (e.key === 'Escape') {
                hideSearchResults();
                searchInput.blur();
            }
        });

        function performSearch(query) {
            fetch(`../class/class_search.php?q=${encodeURIComponent(query)}&from=read`)
                .then(response => {
                    // Verificar se a resposta está ok
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    // Verificar o content-type
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        // Se não for JSON, ler como texto para debug
                        return response.text().then(text => {
                            console.error('Resposta não é JSON:', text);
                            throw new Error('Resposta inválida do servidor');
                        });
                    }

                    return response.json();
                })
                .then(data => {
                    hideLoading();

                    // Verificar se há erro na resposta
                    if (data.error) {
                        showError(data.error);
                        return;
                    }

                    displayResults(data.results || []);
                })
                .catch(error => {
                    console.error('Erro na pesquisa:', error);
                    hideLoading();
                    showError('Erro ao realizar pesquisa: ' + error.message);
                });
        }

        function displayResults(results) {
            if (results.length === 0) {
                searchResults.innerHTML = '<div class="search-no-results">Nenhum resultado encontrado</div>';
                showSearchResults();
                return;
            }

            const html = results.map(result => {
                const badgeHtml = result.badge ?
                    `<span class="search-result-badge ${result.badgeClass || ''}">${result.badge}</span>` : '';

                return `
                    <div class="search-result-item" data-url="${result.url || '#'}" data-type="${result.type}">
                        <div class="search-result-icon ${getIconClass(result.type)}">
                            <i class="${result.icon}"></i>
                        </div>
                        <div class="search-result-content">
                            <div class="search-result-title">
                                ${result.title}
                                ${badgeHtml}
                            </div>
                            ${result.subtitle ? `<div class="search-result-subtitle">${result.subtitle}</div>` : ''}
                            ${result.description ? `<div class="search-result-description">${result.description}</div>` : ''}
                        </div>
                    </div>
                `;
            }).join('');

            searchResults.innerHTML = html;

            // Add click events
            searchResults.querySelectorAll('.search-result-item').forEach(item => {
                item.addEventListener('click', function() {
                    const url = this.getAttribute('data-url');
                    if (url && url !== '#') {
                        window.location.href = url;
                    }
                });

                // Add hover effect for keyboard navigation
                item.addEventListener('mouseenter', function() {
                    searchResults.querySelectorAll('.search-result-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                });
            });

            showSearchResults();
        }

        function getIconClass(type) {
            const classes = {
                'produto': 'blue',
                'fornecedor': 'green',
                'usuario': 'purple',
                'secao': 'yellow'
            };
            return classes[type] || 'blue';
        }

        function showSearchResults() {
            searchResults.classList.add('show');
        }

        function hideSearchResults() {
            searchResults.classList.remove('show');
            searchResults.querySelectorAll('.search-result-item').forEach(item => {
                item.classList.remove('active');
            });
        }

        function showLoading() {
            searchResults.innerHTML = `
                <div class="search-loading">
                    <div class="spinner"></div>
                    Pesquisando...
                </div>
            `;
            showSearchResults();
        }

        function hideLoading() {
            const loading = searchResults.querySelector('.search-loading');
            if (loading) {
                loading.remove();
            }
        }

        function showError(message) {
            searchResults.innerHTML = `<div class="search-no-results" style="color: #dc2626;">${message}</div>`;
            showSearchResults();
        }

        // Adiciona feedback visual no clique das linhas da tabela
        document.querySelectorAll('.products-table tbody tr').forEach(row => {
            row.addEventListener('click', function() {
                this.style.transform = 'scale(0.98)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
            });
        });
    </script>
</body>

</html>