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

            // Valor total do estoque (usando preco_unitario já que preco não existe)
            $stmt = $this->pdo->query("SELECT SUM(estoque_atual * preco_unitario) as valor_total FROM produtos WHERE ativo = 1");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['valor_total'] = $result['valor_total'] ?: 0;

            // Total de fornecedores
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM fornecedores");
            $stats['total_fornecedores'] = $stmt->fetchColumn();

            // Total de usuários
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM usuarios WHERE ativo = 1");
            $stats['total_usuarios'] = $stmt->fetchColumn();

            // Movimentações do mês atual
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM movimentacoes_estoque WHERE MONTH(criado_em) = MONTH(CURDATE()) AND YEAR(criado_em) = YEAR(CURDATE())");
            $stats['movimentacoes_mes'] = $stmt->fetchColumn();

        } catch (PDOException $e) {
            // Em caso de erro, retorna valores zerados
            $stats = [
                'total_produtos' => 0,
                'estoque_baixo' => 0,
                'valor_total' => 0,
                'total_fornecedores' => 0,
                'total_usuarios' => 0,
                'movimentacoes_mes' => 0
            ];
        }

        return $stats;
    }

    // Buscar produtos com estoque baixo
    public function getProdutosEstoqueBaixo($limit = 10)
    {
        try {
            $sql = "SELECT p.nome, p.estoque_atual, p.estoque_minimo, p.codigo
                    FROM produtos p 
                    WHERE p.estoque_atual <= p.estoque_minimo AND p.ativo = 1 
                    ORDER BY (p.estoque_atual - p.estoque_minimo) ASC 
                    LIMIT :limit";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // Buscar atividades recentes
    public function getAtividadesRecentes($limit = 10)
    {
        try {
            $sql = "SELECT 
                        m.tipo_movimentacao as tipo, 
                        m.quantidade, 
                        m.criado_em as data_movimentacao, 
                        m.observacoes as observacao,
                        p.nome as produto_nome, 
                        u.nome as usuario_nome,
                        m.motivo,
                        m.destino
                    FROM movimentacoes_estoque m
                    LEFT JOIN produtos p ON m.produto_id = p.id_produto
                    LEFT JOIN usuarios u ON m.usuario_id = u.id
                    ORDER BY m.criado_em DESC
                    LIMIT :limit";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // Dados para gráfico de movimentações reais
    public function getDadosGrafico($dias = 7)
    {
        try {
            $labels = [];
            $entradas = [];
            $saidas = [];

            for ($i = $dias - 1; $i >= 0; $i--) {
                $data = date('Y-m-d', strtotime("-$i days"));
                $labels[] = date('d/m', strtotime($data));

                // Entradas do dia
                $stmt = $this->pdo->prepare("
                    SELECT COALESCE(SUM(quantidade), 0) 
                    FROM movimentacoes_estoque 
                    WHERE DATE(criado_em) = ? AND tipo_movimentacao = 'entrada'
                ");
                $stmt->execute([$data]);
                $entradas[] = (int)$stmt->fetchColumn();

                // Saídas do dia
                $stmt = $this->pdo->prepare("
                    SELECT COALESCE(SUM(quantidade), 0) 
                    FROM movimentacoes_estoque 
                    WHERE DATE(criado_em) = ? AND tipo_movimentacao = 'saida'
                ");
                $stmt->execute([$data]);
                $saidas[] = (int)$stmt->fetchColumn();
            }

            return [
                'labels' => $labels,
                'entradas' => $entradas,
                'saidas' => $saidas
            ];
        } catch (Exception $e) {
            // Dados padrão em caso de erro
            return [
                'labels' => array_map(function($i) { return date('d/m', strtotime("-$i days")); }, range($dias-1, 0)),
                'entradas' => array_fill(0, $dias, 0),
                'saidas' => array_fill(0, $dias, 0)
            ];
        }
    }

    // Produtos mais movimentados
    public function getProdutosMaisMovimentados($limit = 5)
    {
        try {
            $sql = "SELECT 
                        p.nome,
                        p.codigo,
                        p.estoque_atual,
                        COUNT(m.id) as total_movimentacoes,
                        SUM(CASE WHEN m.tipo_movimentacao = 'entrada' THEN m.quantidade ELSE 0 END) as total_entradas,
                        SUM(CASE WHEN m.tipo_movimentacao = 'saida' THEN m.quantidade ELSE 0 END) as total_saidas
                    FROM produtos p
                    LEFT JOIN movimentacoes_estoque m ON p.id_produto = m.produto_id
                    WHERE p.ativo = 1 AND m.criado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY p.id_produto, p.nome, p.codigo, p.estoque_atual
                    ORDER BY total_movimentacoes DESC
                    LIMIT :limit";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
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
$produtos_movimentados = $dashboard->getProdutosMaisMovimentados();
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
            flex: 1;
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

        .stat-change.warning {
            color: #d97706;
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
            text-align: right;
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
            justify-content: space-between;
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

        .produto-codigo {
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

        .orange {
            background: #fed7aa;
            color: #ea580c;
        }

        /* Produtos Movimentados */
        .produto-movimentado {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .produto-movimentado:last-child {
            border-bottom: none;
        }

        .produto-movimentado-info {
            flex: 1;
        }

        .produto-movimentado-nome {
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 2px;
        }

        .produto-movimentado-codigo {
            font-size: 0.75rem;
            color: #64748b;
        }

        .produto-movimentado-stats {
            text-align: right;
            font-size: 0.875rem;
        }

        .movimento-count {
            font-weight: 600;
            color: #3b82f6;
        }

        .movimento-detail {
            color: #64748b;
            font-size: 0.75rem;
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

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

            .stats-grid {
                grid-template-columns: 1fr;
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
                                <div class="produto-codigo">
                                    Código: <?= htmlspecialchars($produto['codigo']) ?>
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
                                        <i class="fas fa-boxes"></i>
                                        <span>Produtos ativos</span>
                                    </div>
                                </div>
                                <div class="stat-icon blue">
                                    <i class="fas fa-cubes"></i>
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
                                    <i class="fas fa-chart-line-down"></i>
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

                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Movimentações (Mês)</div>
                                    <div class="stat-value"><?= number_format($stats['movimentacoes_mes'], 0, ',', '.') ?></div>
                                    <div class="stat-change positive">
                                        <i class="fas fa-exchange-alt"></i>
                                        <span>Este mês</span>
                                    </div>
                                </div>
                                <div class="stat-icon orange">
                                    <i class="fas fa-arrows-rotate"></i>
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
                                    'transferencia' => ['fas fa-exchange-alt', 'blue', '↔']
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
                                            <?= ucfirst($tipo) ?> - <?= htmlspecialchars($atividade['produto_nome'] ?? 'Produto não identificado') ?>
                                        </div>
                                        <div class="activity-meta">
                                            <?= $data_formatada ?>
                                            <?php if (isset($atividade['usuario_nome'])): ?>
                                                • <?= htmlspecialchars($atividade['usuario_nome']) ?>
                                            <?php endif; ?>
                                            <?php if (isset($atividade['motivo']) && $atividade['motivo']): ?>
                                                • <?= htmlspecialchars($atividade['motivo']) ?>
                                            <?php endif; ?>
                                            <?php if (isset($atividade['destino']) && $atividade['destino']): ?>
                                                • Para: <?= htmlspecialchars($atividade['destino']) ?>
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
                            <i class="fas fa-fire"></i>
                            Produtos Mais Movimentados
                        </h3>

                        <?php if (!empty($produtos_movimentados)): ?>
                            <?php foreach ($produtos_movimentados as $produto): ?>
                                <div class="produto-movimentado">
                                    <div class="produto-movimentado-info">
                                        <div class="produto-movimentado-nome"><?= htmlspecialchars($produto['nome']) ?></div>
                                        <div class="produto-movimentado-codigo">
                                            <?= htmlspecialchars($produto['codigo']) ?> • Estoque: <?= $produto['estoque_atual'] ?> unid.
                                        </div>
                                    </div>
                                    <div class="produto-movimentado-stats">
                                        <div class="movimento-count"><?= $produto['total_movimentacoes'] ?> mov.</div>
                                        <div class="movimento-detail">
                                            ↑<?= $produto['total_entradas'] ?: 0 ?> ↓<?= $produto['total_saidas'] ?: 0 ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="activity-item">
                                <div class="activity-avatar yellow">
                                    <i class="fas fa-chart-bar"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">Sem dados de movimentação</div>
                                    <div class="activity-meta">Últimos 30 dias</div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Seção para usuários sem permissão de visualizar produtos -->
                <div class="stats-section">
                    <h2 class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Bem-vindo ao Sistema
                    </h2>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Seu Perfil</div>
                                    <div class="stat-value"><?= htmlspecialchars(ucfirst($_SESSION['usuario_perfil'])) ?></div>
                                    <div class="stat-change positive">
                                        <i class="fas fa-user"></i>
                                        <span>Perfil ativo</span>
                                    </div>
                                </div>
                                <div class="stat-icon blue">
                                    <i class="fas fa-id-card"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-title">Sistema</div>
                                    <div class="stat-value">Online</div>
                                    <div class="stat-change positive">
                                        <i class="fas fa-check-circle"></i>
                                        <span>Funcionando</span>
                                    </div>
                                </div>
                                <div class="stat-icon green">
                                    <i class="fas fa-server"></i>
                                </div>
                            </div>
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
                                    <div class="activity-title">Nenhuma permissão específica</div>
                                    <div class="activity-meta">Entre em contato com o administrador para mais acesso</div>
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

        // Inicializar gráfico apenas se houver permissão
        <?php if (temPermissao('listar_produtos')): ?>
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
                        fill: true,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    }, {
                        label: 'Saídas',
                        data: dadosGrafico.saidas,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#ef4444',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            borderColor: '#3b82f6',
                            borderWidth: 1,
                            cornerRadius: 8,
                            displayColors: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f1f5f9',
                                drawBorder: false
                            },
                            ticks: {
                                color: '#64748b',
                                font: {
                                    size: 12
                                }
                            }
                        },
                        x: {
                            grid: {
                                color: '#f1f5f9',
                                drawBorder: false
                            },
                            ticks: {
                                color: '#64748b',
                                font: {
                                    size: 12
                                }
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
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
            // Adicionar indicador de loading
            const chartContainer = document.querySelector('.chart-container');
            const loadingIndicator = document.createElement('div');
            loadingIndicator.innerHTML = '<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #64748b;"><i class="fas fa-spinner fa-spin"></i> Carregando...</div>';
            loadingIndicator.style.position = 'absolute';
            loadingIndicator.style.top = '0';
            loadingIndicator.style.left = '0';
            loadingIndicator.style.right = '0';
            loadingIndicator.style.bottom = '0';
            loadingIndicator.style.backgroundColor = 'rgba(255, 255, 255, 0.8)';
            loadingIndicator.style.zIndex = '10';
            chartContainer.appendChild(loadingIndicator);

            // Fazer requisição para buscar novos dados
            fetch(`ajax/get_chart_data.php?dias=${dias}`)
                .then(response => response.json())
                .then(data => {
                    if (stockChart && data.labels && data.entradas && data.saidas) {
                        stockChart.data.labels = data.labels;
                        stockChart.data.datasets[0].data = data.entradas;
                        stockChart.data.datasets[1].data = data.saidas;
                        stockChart.update('active');

                        // Atualizar título do gráfico
                        const titulo = document.querySelector('.chart-title');
                        const textos = {
                            '7': 'Últimos 7 dias',
                            '30': 'Último mês',
                            '90': 'Últimos 3 meses'
                        };
                        titulo.textContent = `Movimentação de Estoque - ${textos[dias] || 'Período selecionado'}`;
                    }
                    
                    // Remover loading
                    chartContainer.removeChild(loadingIndicator);
                })
                .catch(error => {
                    console.error('Erro ao atualizar gráfico:', error);
                    // Remover loading mesmo em caso de erro
                    if (chartContainer.contains(loadingIndicator)) {
                        chartContainer.removeChild(loadingIndicator);
                    }
                });
        }
        <?php endif; ?>

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
                icon: '/favicon.ico',
                tag: 'estoque-baixo'
            });
        } else if ('Notification' in window && Notification.permission !== 'denied') {
            Notification.requestPermission().then(function(permission) {
                if (permission === 'granted') {
                    new Notification('Atenção: Produtos com Estoque Baixo', {
                        body: '<?= count($produtos_estoque_baixo) ?> produto(s) precisam de reposição',
                        icon: '/favicon.ico',
                        tag: 'estoque-baixo'
                    });
                }
            });
        }
        <?php endif; ?>

        // Animações suaves para cards ao carregar
        window.addEventListener('load', function() {
            const cards = document.querySelectorAll('.stat-card, .activity-card, .chart-section');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Atualizar timestamp do header a cada minuto
        setInterval(function() {
            const now = new Date();
            const timestamp = now.toLocaleDateString('pt-BR') + ' ' + now.toLocaleTimeString('pt-BR', {
                hour: '2-digit',
                minute: '2-digit'
            });
            const subtitle = document.querySelector('.header-subtitle');
            if (subtitle) {
                subtitle.textContent = `Visão geral do sistema de estoque - Atualizado em ${timestamp}`;
            }
        }, 60000); // 1 minuto
    </script>
</body>

</html>