<?php
session_start();

// Inclui funções comuns
require_once 'includes/functions.php';

// Verifica se está logado
verificarAutenticacao();

require_once 'conexao.php';
$bd = new BancoDeDados();
$usuario = getUsuarioLogado();

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Estoque</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
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
            gap: 12px;
            padding: 8px 16px;
            background: #f1f5f9;
            border-radius: 8px;
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

        /* Stats Cards */
        .stats-section {
            margin-bottom: 32px;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .stat-title {
            font-size: 0.875rem;
            font-weight: 500;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .stat-change {
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .stat-change.positive {
            color: #059669;
        }

        .stat-change.negative {
            color: #dc2626;
        }

        /* Chart Section */
        .chart-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 32px;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .chart-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
        }

        .chart-filters {
            display: flex;
            gap: 8px;
        }

        .filter-btn {
            padding: 6px 12px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .filter-btn.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .chart-container {
            height: 300px;
            position: relative;
        }

        /* Recent Activity */
        .activity-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        .activity-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-avatar {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .activity-meta {
            font-size: 0.875rem;
            color: #64748b;
        }

        .activity-value {
            font-weight: 600;
            color: #1e293b;
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

            .activity-section {
                grid-template-columns: 1fr;
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
                padding: 16px;
            }

            .header-right {
                flex-direction: column;
                gap: 12px;
                width: 100%;
            }
        }

        /* Color Schemes */
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
                        <a href="index.php" class="nav-link">
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
                            <a href="read/read_product.php" class="nav-link">
                                <i class="fas fa-list"></i>
                                <span>Listar Produtos</span>
                            </a>
                        </div>
                        <?php if (temPermissao('cadastrar_produtos')): ?>
                            <div class="nav-item">
                                <a href="create/create_product.php" class="nav-link">
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
                        <a href="read/read_supplier.php" class="nav-link">
                            <i class="fas fa-truck"></i>
                            <span>Listar Fornecedores</span>
                        </a>
                    </div>
                </div>

                <!-- Usuários -->
                <?php if (temPermissao('gerenciar_usuarios')): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">Usuários</div>
                        <div class="nav-item">
                            <a href="read/read_user.php" class="nav-link">
                                <i class="fas fa-users"></i>
                                <span>Listar Usuários</span>
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="create/create_user.php" class="nav-link">
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
                        <a href="perfil.php" class="nav-link">
                            <i class="fas fa-user-circle"></i>
                            <span>Meu Perfil</span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="logout.php" class="nav-link">
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
                    <h1>Dashboard</h1>
                    <p class="header-subtitle">Visão geral do sistema de estoque</p>
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
                    <div class="user-info">
                        <div class="user-avatar">
                            <?= strtoupper(substr($_SESSION['usuario_nome'], 0, 1)) ?>
                        </div>
                        <div class="user-details">
                            <h3><?= htmlspecialchars($_SESSION['usuario_nome']) ?></h3>
                            <p><?= htmlspecialchars(ucfirst($_SESSION['usuario_perfil'])) ?></p>
                        </div>
                    </div>
                    <a href="logout.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                        Sair
                    </a>
                </div>
            </div>

            <!-- Stats Section -->
            <?php if (temPermissao('listar_produtos')): ?>
                <div class="stats-section">
                    <h2 class="section-title">
                        <i class="fas fa-chart-pie"></i>
                        Estatísticas Gerais
                    </h2>

                    <?php
                    // Buscar estatísticas básicas do sistema
                    require_once 'conexao.php';
                    try {
                        $bd = new BancoDeDados();

                        // Total de produtos
                        $stmt_produtos = $bd->pdo->query("SELECT COUNT(*) FROM produtos WHERE ativo = 1");
                        $total_produtos = $stmt_produtos->fetchColumn();

                        // Produtos com estoque baixo
                        $stmt_estoque_baixo = $bd->pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque_atual <= estoque_minimo AND ativo = 1");
                        $estoque_baixo = $stmt_estoque_baixo->fetchColumn();

                        // Valor total do estoque
                        $stmt_valor = $bd->pdo->query("SELECT SUM(estoque_atual * preco) FROM produtos WHERE ativo = 1");
                        $valor_total = $stmt_valor->fetchColumn() ?: 0;

                        // Total de usuários (se tiver permissão)
                        $total_usuarios = 0;
                        if (temPermissao('gerenciar_usuarios')) {
                            $stmt_usuarios = $bd->pdo->query("SELECT COUNT(*) FROM usuarios WHERE ativo = 1");
                            $total_usuarios = $stmt_usuarios->fetchColumn();
                        }
                    } catch (Exception $e) {
                        $total_produtos = 0;
                        $estoque_baixo = 0;
                        $valor_total = 0;
                        $total_usuarios = 0;
                    }
                    ?>

                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Total de Produtos</div>
                                    <div class="stat-value"><?= number_format($total_produtos, 0, ',', '.') ?></div>
                                    <div class="stat-change positive">
                                        <i class="fas fa-arrow-up"></i>
                                        <span>Produtos ativos</span>
                                    </div>
                                </div>
                                <div class="stat-icon blue">
                                    <i class="fas fa-boxes"></i>
                                </div>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Estoque Baixo</div>
                                    <div class="stat-value" style="color: <?= $estoque_baixo > 0 ? '#dc2626' : '#16a34a' ?>;">
                                        <?= number_format($estoque_baixo, 0, ',', '.') ?>
                                    </div>
                                    <div class="stat-change <?= $estoque_baixo > 0 ? 'negative' : 'positive' ?>">
                                        <i class="fas fa-<?= $estoque_baixo > 0 ? 'exclamation-triangle' : 'check-circle' ?>"></i>
                                        <span><?= $estoque_baixo > 0 ? 'Requer atenção' : 'Situação normal' ?></span>
                                    </div>
                                </div>
                                <div class="stat-icon <?= $estoque_baixo > 0 ? 'red' : 'green' ?>">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Valor Total Estoque</div>
                                    <div class="stat-value">R$ <?= number_format($valor_total, 2, ',', '.') ?></div>
                                    <div class="stat-change positive">
                                        <i class="fas fa-dollar-sign"></i>
                                        <span>Valor inventário</span>
                                    </div>
                                </div>
                                <div class="stat-icon green">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                            </div>
                        </div>

                        <?php if (temPermissao('gerenciar_usuarios')): ?>
                            <div class="stat-card">
                                <div class="stat-header">
                                    <div>
                                        <div class="stat-title">Usuários Ativos</div>
                                        <div class="stat-value"><?= number_format($total_usuarios, 0, ',', '.') ?></div>
                                        <div class="stat-change positive">
                                            <i class="fas fa-users"></i>
                                            <span>Usuários cadastrados</span>
                                        </div>
                                    </div>
                                    <div class="stat-icon purple">
                                        <i class="fas fa-user-friends"></i>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chart Section -->
                <div class="chart-section">
                    <div class="chart-header">
                        <h3 class="chart-title">Movimentação de Estoque - Últimos 30 dias</h3>
                        <div class="chart-filters">
                            <button class="filter-btn active">1 semana</button>
                            <button class="filter-btn">1 mês</button>
                            <button class="filter-btn">3 meses</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="stockChart"></canvas>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="activity-section">
                    <div class="activity-card">
                        <h3 class="section-title">
                            <i class="fas fa-history"></i>
                            Atividades Recentes
                        </h3>

                        <div class="activity-item">
                            <div class="activity-avatar blue">
                                <i class="fas fa-plus"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">Novo produto cadastrado</div>
                                <div class="activity-meta">Mouse Gamer XYZ • há 2 horas</div>
                            </div>
                            <div class="activity-value">+50 unid.</div>
                        </div>

                        <div class="activity-item">
                            <div class="activity-avatar green">
                                <i class="fas fa-arrow-up"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">Entrada de estoque</div>
                                <div class="activity-meta">Teclado Mecânico ABC • há 4 horas</div>
                            </div>
                            <div class="activity-value">+25 unid.</div>
                        </div>

                        <div class="activity-item">
                            <div class="activity-avatar red">
                                <i class="fas fa-arrow-down"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">Saída de estoque</div>
                                <div class="activity-meta">Monitor LED 24" • há 6 horas</div>
                            </div>
                            <div class="activity-value">-10 unid.</div>
                        </div>

                        <div class="activity-item">
                            <div class="activity-avatar yellow">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">Estoque baixo detectado</div>
                                <div class="activity-meta">Cabo USB-C • há 1 dia</div>
                            </div>
                            <div class="activity-value">5 unid.</div>
                        </div>
                    </div>

                    <div class="activity-card">
                        <h3 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Suas Permissões
                        </h3>

                        <?php if (!empty($_SESSION['permissoes'])): ?>
                            <?php
                            $traducoes = [
                                'listar_produtos' => ['Listar Produtos', 'fas fa-list', 'blue'],
                                'cadastrar_produtos' => ['Cadastrar Produtos', 'fas fa-plus', 'green'],
                                'editar_produtos' => ['Editar Produtos', 'fas fa-edit', 'yellow'],
                                'excluir_produtos' => ['Excluir Produtos', 'fas fa-trash', 'red'],
                                'gerenciar_usuarios' => ['Gerenciar Usuários', 'fas fa-users-cog', 'purple']
                            ];
                            foreach ($_SESSION['permissoes'] as $permissao):
                                $info = $traducoes[$permissao] ?? [ucfirst(str_replace('_', ' ', $permissao)), 'fas fa-check', 'blue'];
                            ?>
                                <div class="activity-item">
                                    <div class="activity-avatar <?= $info[2] ?>">
                                        <i class="<?= $info[1] ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title"><?= $info[0] ?></div>
                                        <div class="activity-meta">Permissão ativa</div>
                                    </div>
                                    <div class="activity-value">
                                        <i class="fas fa-check-circle" style="color: #16a34a;"></i>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="activity-item">
                                <div class="activity-avatar red">
                                    <i class="fas fa-times"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">Nenhuma permissão</div>
                                    <div class="activity-meta">Entre em contato com o administrador</div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
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
            fetch(`class/class_search.php?q=${encodeURIComponent(query)}`)
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

        // Chart.js configuration
        const ctx = document.getElementById('stockChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'],
                    datasets: [{
                        label: 'Entradas',
                        data: [12, 19, 8, 15, 25, 13, 18],
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Saídas',
                        data: [8, 11, 13, 9, 16, 12, 14],
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f1f5f9'
                            }
                        },
                        x: {
                            grid: {
                                color: '#f1f5f9'
                            }
                        }
                    }
                }
            });
        }

        // Filter buttons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                // Aqui você pode implementar lógica para mudar os dados do gráfico com base na seleção
                // Ex: trocar datasets para refletir "1 mês" ou "3 meses"
            });
        });
        // js/search.js
        class SearchManager {
            constructor() {
                this.searchTimeout = null;
                this.searchInput = document.getElementById('searchInput');
                this.searchResults = document.getElementById('searchResults');
                this.initializeEventListeners();
            }

            initializeEventListeners() {
                // Input event for search
                this.searchInput.addEventListener('input', (e) => {
                    const query = e.target.value.trim();

                    clearTimeout(this.searchTimeout);

                    if (query.length < 2) {
                        this.hideSearchResults();
                        return;
                    }

                    this.showLoading();

                    this.searchTimeout = setTimeout(() => {
                        this.performSearch(query);
                    }, 300);
                });

                // Hide results when clicking outside
                document.addEventListener('click', (e) => {
                    if (!e.target.closest('.search-container')) {
                        this.hideSearchResults();
                    }
                });

                // Keyboard navigation
                this.searchInput.addEventListener('keydown', (e) => this.handleKeyNavigation(e));
            }

            async performSearch(query) {
                try {
                    const response = await fetch(`class/class_search.php?q=${encodeURIComponent(query)}`);

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const text = await response.text();
                    console.log('Resposta do servidor:', text);

                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error('Erro ao fazer parse do JSON:', e);
                        console.error('Texto da resposta:', text);
                        throw new Error('Resposta inválida do servidor');
                    }

                    this.hideLoading();

                    if (data.error) {
                        this.showError(data.error);
                        return;
                    }

                    this.displayResults(data.results || []);

                } catch (error) {
                    console.error('Erro na pesquisa:', error);
                    this.hideLoading();
                    this.showError('Erro ao realizar pesquisa: ' + error.message);
                }
            }

            displayResults(results) {
                if (results.length === 0) {
                    this.searchResults.innerHTML = '<div class="search-no-results">Nenhum resultado encontrado</div>';
                    this.showSearchResults();
                    return;
                }

                const html = results.map(result => {
                    const badgeHtml = result.badge ?
                        `<span class="search-result-badge ${result.badgeClass || ''}">${result.badge}</span>` : '';

                    return `
                <div class="search-result-item" data-url="${result.url || '#'}" data-type="${result.type}">
                    <div class="search-result-icon ${this.getIconClass(result.type)}">
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

                this.searchResults.innerHTML = html;

                this.addItemEventListeners();
                this.showSearchResults();
            }

            addItemEventListeners() {
                this.searchResults.querySelectorAll('.search-result-item').forEach(item => {
                    item.addEventListener('click', () => {
                        const url = item.getAttribute('data-url');
                        if (url && url !== '#') {
                            window.location.href = url;
                        }
                    });

                    item.addEventListener('mouseenter', () => {
                        this.searchResults.querySelectorAll('.search-result-item').forEach(i => i.classList.remove('active'));
                        item.classList.add('active');
                    });
                });
            }

            handleKeyNavigation(e) {
                const items = this.searchResults.querySelectorAll('.search-result-item');
                const activeItem = this.searchResults.querySelector('.search-result-item.active');

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
                    const activeItem = this.searchResults.querySelector('.search-result-item.active');
                    if (activeItem) {
                        activeItem.click();
                    }
                } else if (e.key === 'Escape') {
                    this.hideSearchResults();
                    this.searchInput.blur();
                }
            }

            getIconClass(type) {
                const classes = {
                    'produto': 'blue',
                    'fornecedor': 'green',
                    'usuario': 'purple',
                    'secao': 'yellow'
                };
                return classes[type] || 'blue';
            }

            showSearchResults() {
                this.searchResults.classList.add('show');
            }

            hideSearchResults() {
                this.searchResults.classList.remove('show');
                this.searchResults.querySelectorAll('.search-result-item').forEach(item => {
                    item.classList.remove('active');
                });
            }

            showLoading() {
                this.searchResults.innerHTML = `
            <div class="search-loading">
                <div class="spinner"></div>
                Pesquisando...
            </div>
        `;
                this.showSearchResults();
            }

            hideLoading() {
                const loading = this.searchResults.querySelector('.search-loading');
                if (loading) {
                    loading.remove();
                }
            }

            showError(message) {
                this.searchResults.innerHTML = `<div class="search-no-results" style="color: #dc2626;">${message}</div>`;
                this.showSearchResults();
            }
        }

        // Inicializar quando o DOM estiver carregado
        document.addEventListener('DOMContentLoaded', function() {
            new SearchManager();
        });
    </script>
</body>

</html>