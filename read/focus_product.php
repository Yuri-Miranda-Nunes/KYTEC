<?php
session_start();

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

// Verifica se o ID foi fornecido
$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    $_SESSION['mensagem_erro'] = "ID de produto inválido.";
    header("Location: ../read/read_product.php");
    exit;
}

$bd = new BancoDeDados();

// Buscar dados do produto
try {
    $stmt = $bd->pdo->prepare("SELECT * FROM produtos WHERE id_produto = ?");
    $stmt->execute([$id]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        $_SESSION['mensagem_erro'] = "Produto não encontrado.";
        header("Location: ../read/read_product.php");
        exit;
    }
} catch (Exception $e) {
    $_SESSION['mensagem_erro'] = "Erro ao buscar produto: " . $e->getMessage();
    header("Location: ../read/read_product.php");
    exit;
}

// Função para formatar preço
function formatarPreco($preco)
{
    return 'R$ ' . number_format($preco, 2, ',', '.');
}

// Função para obter classe do badge de tipo
function getTipoBadgeClass($tipo)
{
    switch ($tipo) {
        case 'acabado':
            return 'type-acabado';
        case 'matéria-prima':
            return 'type-materia-prima';
        default:
            return 'type-outro';
    }
}
// Função para determinar se a página atual está ativa
function isActivePage($page)
{
    $current = basename($_SERVER['PHP_SELF']);
    return $current === $page ? 'active' : '';
}
// Função para obter classe do badge de estoque
function getEstoqueBadgeClass($estoqueAtual, $estoqueMinimo)
{
    if ($estoqueAtual <= $estoqueMinimo) {
        return 'stock-low';
    } elseif ($estoqueAtual <= ($estoqueMinimo * 2)) {
        return 'stock-medium';
    }
    return 'stock-normal';
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Produto - Sistema de Estoque</title>
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

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
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

        /* Info Section */
        .info-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
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

        /* Product Header */
        .product-header {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
        }

        .product-main-info {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }

        .product-icon {
            width: 64px;
            height: 64px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }

        .product-details h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .product-details p {
            font-size: 1rem;
            opacity: 0.9;
        }

        .product-status {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-top: 12px;
        }

        /* Stock Actions */
        .stock-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        .stock-action-card {
            background: white;
            border-radius: 16px;
            padding: 32px 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            position: relative;
            overflow: hidden;
        }

        .stock-action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            transition: all 0.3s ease;
        }

        .stock-action-card.entrada::before {
            background: linear-gradient(90deg, #16a34a, #22c55e);
        }

        .stock-action-card.saida::before {
            background: linear-gradient(90deg, #ef4444, #f87171);
        }

        .stock-action-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }

        .stock-action-card:hover::before {
            height: 6px;
        }

        .action-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 16px;
            transition: all 0.3s ease;
        }

        .stock-action-card.entrada .action-icon {
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            color: #16a34a;
        }

        .stock-action-card.saida .action-icon {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #ef4444;
        }

        .stock-action-card:hover .action-icon {
            transform: scale(1.1);
        }

        .action-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .stock-action-card.entrada .action-title {
            color: #16a34a;
        }

        .stock-action-card.saida .action-title {
            color: #ef4444;
        }

        .action-description {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 16px;
        }

        .action-details {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #94a3b8;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .stock-action-card.entrada .action-details {
            color: #16a34a;
        }

        .stock-action-card.saida .action-details {
            color: #ef4444;
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }

        .info-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
        }

        .info-card h3 {
            color: #1e293b;
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 16px;
        }

        .info-item:last-child {
            margin-bottom: 0;
        }

        .info-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .info-value {
            font-size: 0.9rem;
            color: #1e293b;
            font-weight: 500;
            word-break: break-word;
        }

        .info-value.empty {
            color: #94a3b8;
            font-style: italic;
        }

        .product-id {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .product-code {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 0.9rem;
            background: #f1f5f9;
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
            color: #475569;
            font-weight: 600;
        }

        /* Type Badges */
        .type-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: capitalize;
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

        /* Stock Badges */
        .stock-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
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

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-ativo {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .status-inativo {
            background: rgba(239, 68, 68, 0.2);
            color: #fef2f2;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .price-display {
            font-size: 1.5rem;
            font-weight: 700;
            color: #16a34a;
            background: #f0fdf4;
            padding: 8px 16px;
            border-radius: 8px;
            border-left: 4px solid #16a34a;
        }

        /* Description Section */
        .description-section {
            grid-column: 1 / -1;
        }

        .description-text {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            color: #374151;
            line-height: 1.6;
            min-height: 120px;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
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

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
            color: #374151;
        }

        /* Navigation Actions */
        .nav-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        /* Stock Alert */
        .stock-alert {
            background: linear-gradient(135deg, #fef3c7, #fed7aa);
            border: 1px solid #f59e0b;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .stock-alert.critical {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border-color: #ef4444;
        }

        .stock-alert .alert-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            background: rgba(255, 255, 255, 0.8);
        }

        .stock-alert .alert-content {
            flex: 1;
        }

        .stock-alert .alert-title {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 4px;
        }

        .stock-alert .alert-message {
            font-size: 0.85rem;
            opacity: 0.9;
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

            .stock-actions {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }

            .header-right {
                width: 100%;
                justify-content: center;
            }

            .product-main-info {
                flex-direction: column;
                text-align: center;
            }

            .product-status {
                justify-content: center;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .nav-actions {
                flex-direction: column;
            }
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
                <div class="nav-section">
                    <div class="nav-section-title">Fornecedores</div>
                    <div class="nav-item">
                        <a href="../read/read_supplier.php" class="nav-link <?= isActivePage('read_supplier.php') ?>">
                            <i class="fas fa-truck"></i>
                            <span>Listar Fornecedores</span>
                        </a>
                    </div>
                </div>

                <!-- Logs -->
                <?php if (temPermissao('listar_produtos')): ?>
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
                    <h1>Detalhes do Produto</h1>
                    <p class="header-subtitle">Visualize todas as informações e gerencie o estoque</p>
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

            <!-- Product Header -->
            <div class="product-header">
                <div class="product-main-info">
                    <div class="product-icon">
                        <i class="fas fa-cube"></i>
                    </div>
                    <div class="product-details">
                        <h1><?= htmlspecialchars($produto['nome']) ?></h1>
                        <p>Código: <?= htmlspecialchars($produto['codigo']) ?></p>
                        <div class="product-status">
                            <span class="type-badge <?= getTipoBadgeClass($produto['tipo']) ?>">
                                <?= htmlspecialchars(ucfirst($produto['tipo'])) ?>
                            </span>
                            <span class="status-badge <?= $produto['ativo'] ? 'status-ativo' : 'status-inativo' ?>">
                                <?= $produto['ativo'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stock Alert -->
            <?php
            $showAlert = false;
            $alertClass = '';
            $alertTitle = '';
            $alertMessage = '';
            $alertIcon = '';

            if ($produto['estoque_atual'] <= $produto['estoque_minimo']) {
                $showAlert = true;
                $alertClass = 'critical';
                $alertTitle = 'Estoque Crítico!';
                $alertMessage = 'O estoque atual (' . $produto['estoque_atual'] . ' unidades) está no nível mínimo ou abaixo. Reposição urgente necessária!';
                $alertIcon = 'fas fa-exclamation-triangle';
            } elseif ($produto['estoque_atual'] <= ($produto['estoque_minimo'] * 2)) {
                $showAlert = true;
                $alertClass = '';
                $alertTitle = 'Atenção: Estoque Baixo';
                $alertMessage = 'O estoque atual (' . $produto['estoque_atual'] . ' unidades) está se aproximando do limite mínimo. Considere fazer reposição.';
                $alertIcon = 'fas fa-exclamation-circle';
            }
            ?>

            <?php if ($showAlert): ?>
                <div class="stock-alert <?= $alertClass ?>">
                    <div class="alert-icon">
                        <i class="<?= $alertIcon ?>"
                            style="color: <?= $alertClass === 'critical' ? '#ef4444' : '#f59e0b' ?>;"></i>
                    </div>
                    <div class="alert-content">
                        <div class="alert-title" style="color: <?= $alertClass === 'critical' ? '#dc2626' : '#d97706' ?>">
                            <?= $alertTitle ?>
                        </div>
                        <div class="alert-message" style="color: <?= $alertClass === 'critical' ? '#991b1b' : '#92400e' ?>">
                            <?= $alertMessage ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Stock Management Actions -->
            <div class="stock-actions">
                <!-- Entrada de Estoque -->
                <a href="entrada_estoque.php?id=<?= $produto['id_produto'] ?>" class="stock-action-card entrada">
                    <div class="action-icon">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <div class="action-title">Entrada de Estoque</div>
                    <div class="action-description">
                        Registre a entrada de produtos no estoque, seja por compra, produção ou transferência.
                    </div>
                    <div class="action-details">
                        <i class="fas fa-arrow-up"></i>
                        <span>Adicionar ao estoque</span>
                    </div>
                </a>

                <!-- Saída de Estoque -->
                <a href="saida_estoque.php?id=<?= $produto['id_produto'] ?>" class="stock-action-card saida">
                    <div class="action-icon">
                        <i class="fas fa-minus-circle"></i>
                    </div>
                    <div class="action-title">Saída de Estoque</div>
                    <div class="action-description">
                        Registre a retirada de produtos do estoque por venda, uso interno ou transferência.
                    </div>
                    <div class="action-details">
                        <i class="fas fa-arrow-down"></i>
                        <span>Remover do estoque</span>
                    </div>
                </a>
            </div>

            <!-- Information Grid -->
            <div class="info-grid">
                <!-- Informações Básicas -->
                <div class="info-card">
                    <h3>
                        <i class="fas fa-info-circle"></i>
                        Informações Básicas
                    </h3>

                    <div class="info-item">
                        <span class="info-label">Nome do Produto</span>
                        <span class="info-value"><?= htmlspecialchars($produto['nome']) ?></span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Código</span>
                        <span class="info-value">
                            <span class="product-code"><?= htmlspecialchars($produto['codigo']) ?></span>
                        </span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Tipo</span>
                        <span class="info-value">
                            <span class="type-badge <?= getTipoBadgeClass($produto['tipo']) ?>">
                                <?= htmlspecialchars(ucfirst($produto['tipo'])) ?>
                            </span>
                        </span>
                    </div>
                </div>

                <!-- Informações Financeiras -->
                <div class="info-card">
                    <h3>
                        <i class="fas fa-dollar-sign"></i>
                        Informações Financeiras
                    </h3>

                    <div class="info-item">
                        <span class="info-label">Preço Unitário</span>
                        <span class="info-value">
                            <div class="price-display"><?= formatarPreco($produto['preco_unitario']) ?></div>
                        </span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Valor Total em Estoque</span>
                        <span class="info-value">
                            <?php $valorTotal = $produto['preco_unitario'] * $produto['estoque_atual']; ?>
                            <strong><?= formatarPreco($valorTotal) ?></strong>
                        </span>
                    </div>
                </div>

                <!-- Controle de Estoque -->
                <div class="info-card">
                    <h3>
                        <i class="fas fa-warehouse"></i>
                        Controle de Estoque
                    </h3>

                    <div class="info-item">
                        <span class="info-label">Estoque Atual</span>
                        <span class="info-value">
                            <span
                                class="stock-badge <?= getEstoqueBadgeClass($produto['estoque_atual'], $produto['estoque_minimo']) ?>">
                                <?= $produto['estoque_atual'] ?> unidades
                            </span>
                        </span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Estoque Mínimo</span>
                        <span class="info-value">
                            <span class="stock-badge stock-normal">
                                <?= $produto['estoque_minimo'] ?> unidades
                            </span>
                        </span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Situação do Estoque</span>
                        <span class="info-value">
                            <?php
                            $situacao = '';
                            $classe = '';
                            if ($produto['estoque_atual'] <= $produto['estoque_minimo']) {
                                $situacao = 'Estoque Baixo - Reposição Urgente';
                                $classe = 'stock-low';
                            } elseif ($produto['estoque_atual'] <= ($produto['estoque_minimo'] * 2)) {
                                $situacao = 'Estoque em Alerta';
                                $classe = 'stock-medium';
                            } else {
                                $situacao = 'Estoque Normal';
                                $classe = 'stock-normal';
                            }
                            ?>
                            <span class="stock-badge <?= $classe ?>"><?= $situacao ?></span>
                        </span>
                    </div>
                </div>

                <!-- Informações do Sistema -->
                <div class="info-card">
                    <h3>
                        <i class="fas fa-cog"></i>
                        Informações do Sistema
                    </h3>

                    <div class="info-item">
                        <span class="info-label">Cadastrado em</span>
                        <span class="info-value">
                            <?= date('d/m/Y \à\s H:i', strtotime($produto['criado_em'])) ?>
                        </span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Última Atualização</span>
                        <span class="info-value">
                            <?= date('d/m/Y \à\s H:i', strtotime($produto['atualizado_em'])) ?>
                        </span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">ID do Produto</span>
                        <span class="info-value">
                            <span
                                class="product-id">#<?= str_pad($produto['id_produto'], 4, '0', STR_PAD_LEFT) ?></span>
                        </span>
                    </div>
                </div>

                <!-- Descrição -->
                <div class="info-card description-section">
                    <h3>
                        <i class="fas fa-align-left"></i>
                        Descrição do Produto
                    </h3>

                    <div class="description-text">
                        <?= !empty($produto['descricao']) ? nl2br(htmlspecialchars($produto['descricao'])) : '<em style="color: #94a3b8;">Descrição não informada</em>' ?>
                    </div>
                </div>
            </div>

            <!-- Navigation Actions -->
            <div class="info-section">
                <div class="nav-actions">
                    <a href="../read/read_product.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Voltar à Lista
                    </a>
                    <?php if (temPermissao('editar_produtos')): ?>
                        <a href="../update/update_product.php?id=<?= $produto['id_produto'] ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i>
                            Editar Produto
                        </a>
                    <?php endif; ?>
                    <?php if (temPermissao('excluir_produtos')): ?>
                        <a href="../delete/delete_product.php?id=<?= $produto['id_produto'] ?>" class="btn btn-danger"
                            onclick="return confirm('Tem certeza que deseja excluir este produto? Esta ação não pode ser desfeita.')">
                            <i class="fas fa-trash"></i>
                            Excluir Produto
                        </a>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>

    <script>
        // Função para confirmar exclusão
        function confirmarExclusao() {
            return confirm('Tem certeza que deseja excluir este produto?\n\nEsta ação não pode ser desfeita e removerá permanentemente todos os dados do produto do sistema.');
        }

        // Adicionar confirmação aos links de exclusão
        document.querySelectorAll('a[href*="delete_product"]').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!confirmarExclusao()) {
                    e.preventDefault();
                }
            });
        });

        // Animação de entrada suave
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.info-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Animação para os cartões de ações de estoque
            const actionCards = document.querySelectorAll('.stock-action-card');
            actionCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';

                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, (index + 1) * 200);
            });
        });

        // Efeito de hover adicional para os cartões de ação
        document.querySelectorAll('.stock-action-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px) scale(1.02)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(-4px) scale(1)';
            });
        });
    </script>
</body>

</html>