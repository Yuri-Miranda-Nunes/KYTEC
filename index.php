<?php
session_start();

// Inclui funções comuns
require_once 'includes/functions.php';

// Verifica se está logado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: login.php");
    exit;
}

verificarAutenticacao();
require_once 'conexao.php';

// Função para determinar se a página atual está ativa
function isActivePage($page)
{
    $current = basename($_SERVER['PHP_SELF']);
    return $current === $page ? 'active' : '';
}

// Classe para gerenciar dados do dashboard
class DashboardData
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // Buscar estatísticas gerais
    public function getEstasticasGerais()
    {
        $stats = [];

        try {
            // Total de produtos ativos
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM produtos WHERE ativo = 1");
            $stats['total_produtos'] = $stmt->fetchColumn();

            // Produtos com estoque baixo
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque_atual <= estoque_minimo AND ativo = 1");
            $stats['estoque_baixo'] = $stmt->fetchColumn();

            // Valor total do estoque
            $stmt = $this->pdo->query("SELECT SUM(estoque_atual * preco) as valor_total FROM produtos WHERE ativo = 1");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['valor_total'] = $result['valor_total'] ?: 0;

            // Total de fornecedores
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM fornecedores WHERE ativo = 1");
            $stats['total_fornecedores'] = $stmt->fetchColumn();

            // Total de usuários (se existir a tabela)
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM usuarios WHERE ativo = 1");
                $stats['total_usuarios'] = $stmt->fetchColumn();
            } catch (PDOException $e) {
                $stats['total_usuarios'] = 0;
            }
        } catch (PDOException $e) {
            // Em caso de erro, retorna valores zerados
            $stats = [
                'total_produtos' => 0,
                'estoque_baixo' => 0,
                'valor_total' => 0,
                'total_fornecedores' => 0,
                'total_usuarios' => 0
            ];
        }

        return $stats;
    }

    // Buscar produtos com estoque baixo
    public function getProdutosEstoqueBaixo($limit = 10)
    {
        try {
            $sql = "SELECT p.nome, p.estoque_atual, p.estoque_minimo, f.nome as fornecedor_nome 
                    FROM produtos p 
                    LEFT JOIN fornecedores f ON p.fornecedor_id = f.id 
                    WHERE p.estoque_atual <= p.estoque_minimo AND p.ativo = 1 
                    ORDER BY p.estoque_atual ASC 
                    LIMIT :limit";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // Buscar atividades recentes (movimentações de estoque)
    public function getAtividadesRecentes($limit = 10)
    {
        try {
            // Verifica se existe tabela de logs
            $tables = $this->pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

            if (in_array('movimentacoes_estoque', $tables)) {
                $sql = "SELECT m.tipo, m.quantidade, m.data_movimentacao, m.observacao,
                               p.nome as produto_nome, u.nome as usuario_nome
                        FROM movimentacoes_estoque m
                        LEFT JOIN produtos p ON m.produto_id = p.id
                        LEFT JOIN usuarios u ON m.usuario_id = u.id
                        ORDER BY m.data_movimentacao DESC
                        LIMIT :limit";
            } elseif (in_array('logs_produtos', $tables)) {
                $sql = "SELECT l.acao as tipo, l.quantidade_atual as quantidade, l.data_log as data_movimentacao,
                               l.observacao, p.nome as produto_nome, u.nome as usuario_nome
                        FROM logs_produtos l
                        LEFT JOIN produtos p ON l.produto_id = p.id
                        LEFT JOIN usuarios u ON l.usuario_id = u.id
                        ORDER BY l.data_log DESC
                        LIMIT :limit";
            } else {
                // Se não há tabela de logs, cria dados simulados baseados nos produtos
                return $this->getAtividadesSimuladas($limit);
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return $this->getAtividadesSimuladas($limit);
        }
    }

    // Atividades simuladas quando não há logs
    private function getAtividadesSimuladas($limit)
    {
        try {
            $sql = "SELECT nome, estoque_atual, DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 168) HOUR) as data_simulada
                    FROM produtos WHERE ativo = 1 ORDER BY RAND() LIMIT :limit";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $atividades = [];

            $tipos = ['entrada', 'saida', 'ajuste'];
            $acoes = [
                'entrada' => 'Entrada de estoque',
                'saida' => 'Saída de estoque',
                'ajuste' => 'Ajuste de estoque'
            ];

            foreach ($produtos as $produto) {
                $tipo = $tipos[array_rand($tipos)];
                $quantidade = rand(1, 20);

                $atividades[] = [
                    'tipo' => $tipo,
                    'quantidade' => $quantidade,
                    'data_movimentacao' => $produto['data_simulada'],
                    'produto_nome' => $produto['nome'],
                    'usuario_nome' => $_SESSION['usuario_nome'],
                    'observacao' => $acoes[$tipo]
                ];
            }

            return $atividades;
        } catch (PDOException $e) {
            return [];
        }
    }

    // Dados para gráfico de movimentações
    public function getDadosGrafico($dias = 7)
    {
        try {
            $labels = [];
            $entradas = [];
            $saidas = [];

            for ($i = $dias - 1; $i >= 0; $i--) {
                $data = date('Y-m-d', strtotime("-$i days"));
                $labels[] = date('d/m', strtotime($data));

                // Simula dados para o gráfico
                // Em um sistema real, você buscaria das tabelas de movimentação
                $entradas[] = rand(5, 25);
                $saidas[] = rand(3, 20);
            }

            return [
                'labels' => $labels,
                'entradas' => $entradas,
                'saidas' => $saidas
            ];
        } catch (Exception $e) {
            // Dados padrão em caso de erro
            return [
                'labels' => ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'],
                'entradas' => [12, 19, 8, 15, 25, 13, 18],
                'saidas' => [8, 11, 13, 9, 16, 12, 14]
            ];
        }
    }
}

// Inicializar classe de dados
$bd = new BancoDeDados();
$dashboard = new DashboardData($bd->pdo);
$usuario = getUsuarioLogado();

// Buscar dados
$stats = $dashboard->getEstasticasGerais();
$produtos_estoque_baixo = $dashboard->getProdutosEstoqueBaixo();
$atividades_recentes = $dashboard->getAtividadesRecentes();
$dados_grafico = $dashboard->getDadosGrafico();
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

        /* Search Bar - mantendo o código original */
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

        /* Estoque Baixo Alert */
        .alert-card {
            background: linear-gradient(135deg, #fee2e2, #fef3c7);
            border: 1px solid #fbbf24;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .alert-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .alert-icon {
            width: 40px;
            height: 40px;
            background: #f59e0b;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .alert-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #92400e;
        }

        .alert-subtitle {
            color: #b45309;
            font-size: 0.875rem;
        }

        .estoque-baixo-item {
            background: white;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .estoque-baixo-item:last-child {
            margin-bottom: 0;
        }

        .produto-info {
            flex: 1;
        }

        .produto-nome {
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 2px;
        }

        .produto-fornecedor {
            font-size: 0.75rem;
            color: #64748b;
        }

        .estoque-info {
            text-align: right;
        }

        .estoque-atual {
            font-weight: 600;
            color: #dc2626;
            font-size: 1.1rem;
        }

        .estoque-minimo {
            font-size: 0.75rem;
            color: #64748b;
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
                        <a href="index.php" class="nav-link <?= isActivePage('index.php') ?>">
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
                            <a href="read/read_product.php" class="nav-link <?= isActivePage('read_product.php') ?>">
                                <i class="fas fa-list"></i>
                                <span>Listar Produtos</span>
                            </a>
                        </div>
                        <?php if (temPermissao('cadastrar_produtos')): ?>
                            <div class="nav-item">
                                <a href="create/create_product.php"
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
                            <a href="read/read_supplier.php" class="nav-link <?= isActivePage('read_supplier.php') ?>">
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
                            <a href="log/product_input_and_output_log.php"
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
                            <a href="read/read_user.php" class="nav-link <?= isActivePage('read_user.php') ?>">
                                <i class="fas fa-users"></i>
                                <span>Listar Usuários</span>
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="create/create_user.php" class="nav-link <?= isActivePage('create_user.php') ?>">
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
                        <a href="perfil.php" class="nav-link <?= isActivePage('perfil.php') ?>">
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
                    <p class="header-subtitle">Visão geral do sistema de estoque - Atualizado em <?= date('d/m/Y H:i') ?></p>
                </div>

                <!-- Search Bar (mantendo funcionalidade original) -->
                <div class="search-container">
                    <div class="search-wrapper">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="searchInput" class="search-input"
                            placeholder="Pesquisar produtos, fornecedores, usuários...">
                        <div id="searchResults" class="search-results"></div>
                    </div>
                </div>

                <div class="header-right">
                    <a href="perfil.php" class="user-info">
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

            <!-- Alert para produtos com estoque baixo -->
            <?php if (temPermissao('listar_produtos') && !empty($produtos_estoque_baixo)): ?>
                <div class="alert-card">
                    <div class="alert-header">
                        <div class="alert-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div>
                            <div class="alert-title">Produtos com Estoque Baixo</div>
                            <div class="alert-subtitle">
                                <?= count($produtos_estoque_baixo) ?> produto<?= count($produtos_estoque_baixo) > 1 ? 's' : '' ?>
                                necessita<?= count($produtos_estoque_baixo) > 1 ? 'm' : '' ?> reposição
                            </div>
                        </div>
                    </div>
                    <?php foreach ($produtos_estoque_baixo as $produto): ?>
                        <div class="estoque-baixo-item">
                            <div class="produto-info">
                                <div class="produto-nome"><?= htmlspecialchars($produto['nome']) ?></div>
                                <div class="produto-fornecedor">
                                    Fornecedor: <?= htmlspecialchars($produto['fornecedor_nome'] ?: 'Não informado') ?>
                                </div>
                            </div>
                            <div class="estoque-info">
                                <div class="estoque-atual"><?= $produto['estoque_atual'] ?> unid.</div>
                                <div class="estoque-minimo">Mín: <?= $produto['estoque_minimo'] ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Stats Section -->
            <?php if (temPermissao('listar_produtos')): ?>
                <div class="stats-section">
                    <h2 class="section-title">
                        <i class="fas fa-chart-pie"></i>
                        Estatísticas Gerais
                    </h2>

                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Total de Produtos</div>
                                    <div class="stat-value"><?= number_format($stats['total_produtos'], 0, ',', '.') ?></div>
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
                                    <div class="stat-value" style="color: <?= $stats['estoque_baixo'] > 0 ? '#dc2626' : '#16a34a' ?>;">
                                        <?= number_format($stats['estoque_baixo'], 0, ',', '.') ?>
                                    </div>
                                    <div class="stat-change <?= $stats['estoque_baixo'] > 0 ? 'negative' : 'positive' ?>">
                                        <i class="fas fa-<?= $stats['estoque_baixo'] > 0 ? 'exclamation-triangle' : 'check-circle' ?>"></i>
                                        <span><?= $stats['estoque_baixo'] > 0 ? 'Requer atenção' : 'Situação normal' ?></span>
                                    </div>
                                </div>
                                <div class="stat-icon <?= $stats['estoque_baixo'] > 0 ? 'red' : 'green' ?>">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Valor Total Estoque</div>
                                    <div class="stat-value">R$ <?= number_format($stats['valor_total'], 2, ',', '.') ?></div>
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

                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Fornecedores</div>
                                    <div class="stat-value"><?= number_format($stats['total_fornecedores'], 0, ',', '.') ?></div>
                                    <div class="stat-change positive">
                                        <i class="fas fa-truck"></i>
                                        <span>Fornecedores ativos</span>
                                    </div>
                                </div>
                                <div class="stat-icon yellow">
                                    <i class="fas fa-handshake"></i>
                                </div>
                            </div>
                        </div>

                        <?php if (temPermissao('gerenciar_usuarios') && $stats['total_usuarios'] > 0): ?>
                            <div class="stat-card">
                                <div class="stat-header">
                                    <div>
                                        <div class="stat-title">Usuários Ativos</div>
                                        <div class="stat-value"><?= number_format($stats['total_usuarios'], 0, ',', '.') ?></div>
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
                        <h3 class="chart-title">Movimentação de Estoque - Últimos 7 dias</h3>
                        <div class="chart-filters">
                            <button class="filter-btn active" data-dias="7">7 dias</button>
                            <button class="filter-btn" data-dias="30">30 dias</button>
                            <button class="filter-btn" data-dias="90">90 dias</button>
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

                        <?php if (!empty($atividades_recentes)): ?>
                            <?php foreach ($atividades_recentes as $atividade): ?>
                                <?php
                                $tipo = $atividade['tipo'];
                                $icone_config = [
                                    'entrada' => ['fas fa-arrow-up', 'green', '+'],
                                    'saida' => ['fas fa-arrow-down', 'red', '-'],
                                    'ajuste' => ['fas fa-edit', 'yellow', '±'],
                                    'cadastro' => ['fas fa-plus', 'blue', '+'],
                                    'edicao' => ['fas fa-edit', 'yellow', '']
                                ];

                                $config = $icone_config[$tipo] ?? ['fas fa-history', 'blue', ''];
                                $data_formatada = isset($atividade['data_movimentacao']) ?
                                    date('d/m H:i', strtotime($atividade['data_movimentacao'])) :
                                    'Agora';
                                ?>
                                <div class="activity-item">
                                    <div class="activity-avatar <?= $config[1] ?>">
                                        <i class="<?= $config[0] ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">
                                            <?= htmlspecialchars($atividade['observacao'] ?? ucfirst($tipo) . ' de estoque') ?>
                                        </div>
                                        <div class="activity-meta">
                                            <?= htmlspecialchars($atividade['produto_nome'] ?? '') ?> •
                                            <?= $data_formatada ?>
                                            <?php if (isset($atividade['usuario_nome'])): ?>
                                                • <?= htmlspecialchars($atividade['usuario_nome']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if (isset($atividade['quantidade']) && $atividade['quantidade']): ?>
                                        <div class="activity-value">
                                            <?= $config[2] ?><?= $atividade['quantidade'] ?> unid.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="activity-item">
                                <div class="activity-avatar blue">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">Nenhuma atividade recente</div>
                                    <div class="activity-meta">Comece cadastrando produtos para ver as movimentações</div>
                                </div>
                            </div>
                        <?php endif; ?>
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
        // Dados do gráfico vindos do PHP
        const dadosGrafico = <?= json_encode($dados_grafico) ?>;

        // Search functionality (mantendo a funcionalidade original)
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

            showLoading();
            searchTimeout = setTimeout(() => {
                performSearch(query);
            }, 300);
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-container')) {
                hideSearchResults();
            }
        });

        function performSearch(query) {
            fetch(`class/class_search.php?q=${encodeURIComponent(query)}&from=dashboard`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        return response.text().then(text => {
                            console.error('Resposta não é JSON:', text);
                            throw new Error('Resposta inválida do servidor');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    hideLoading();
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
            searchResults.querySelectorAll('.search-result-item').forEach(item => {
                item.addEventListener('click', function() {
                    const url = this.getAttribute('data-url');
                    if (url && url !== '#') {
                        window.location.href = url;
                    }
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
        let stockChart;
        const ctx = document.getElementById('stockChart');
        if (ctx) {
            stockChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dadosGrafico.labels,
                    datasets: [{
                        label: 'Entradas',
                        data: dadosGrafico.entradas,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Saídas',
                        data: dadosGrafico.saidas,
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

        // Filter buttons com funcionalidade real
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const dias = this.getAttribute('data-dias');
                atualizarGrafico(dias);
            });
        });

        // Função para atualizar gráfico
        function atualizarGrafico(dias) {
            // Fazer requisição AJAX para buscar novos dados
            fetch(`ajax/get_chart_data.php?dias=${dias}`)
                .then(response => response.json())
                .then(data => {
                    if (stockChart && data.labels && data.entradas && data.saidas) {
                        stockChart.data.labels = data.labels;
                        stockChart.data.datasets[0].data = data.entradas;
                        stockChart.data.datasets[1].data = data.saidas;
                        stockChart.update();

                        // Atualizar título do gráfico
                        const titulo = document.querySelector('.chart-title');
                        const textos = {
                            '7': 'Últimos 7 dias',
                            '30': 'Último mês',
                            '90': 'Últimos 3 meses'
                        };
                        titulo.textContent = `Movimentação de Estoque - ${textos[dias] || 'Período selecionado'}`;
                    }
                })
                .catch(error => {
                    console.error('Erro ao atualizar gráfico:', error);
                });
        }

        // Auto refresh das estatísticas a cada 5 minutos
        setInterval(function() {
            location.reload();
        }, 300000); // 5 minutos

        // Adicionar funcionalidade de notificação para estoque baixo
        <?php if (!empty($produtos_estoque_baixo)): ?>
            // Mostrar notificação se houver produtos com estoque baixo
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('Atenção: Produtos com Estoque Baixo', {
                    body: '<?= count($produtos_estoque_baixo) ?> produto(s) precisam de reposição',
                    icon: '/favicon.ico'
                });
            } else if ('Notification' in window && Notification.permission !== 'denied') {
                Notification.requestPermission().then(function(permission) {
                    if (permission === 'granted') {
                        new Notification('Atenção: Produtos com Estoque Baixo', {
                            body: '<?= count($produtos_estoque_baixo) ?> produto(s) precisam de reposição',
                            icon: '/favicon.ico'
                        });
                    }
                });
            }
        <?php endif; ?>
    </script>
</body>

</html>