<?php
session_start();

// Verifica se está logado e tem permissão
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php");
    exit;
}

// Função para verificar permissões
function temPermissao($permissao) {
    return in_array($permissao, $_SESSION['permissoes'] ?? []);
}

if (!temPermissao('listar_produtos')) {
    echo "Acesso negado.";
    exit;
}

require_once '../conexao.php';
require_once 'log_manager.php';

try {
    $bd = new BancoDeDados();
    $pdo = $bd->pdo;
    $logManager = new LogManager($pdo);

    // Parâmetros de paginação
    $pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    $registros_por_pagina = 50;
    $offset = ($pagina_atual - 1) * $registros_por_pagina;

    // Filtros
    $filtros = [];
    if (!empty($_GET['tipo'])) {
        $filtros['tipo_movimentacao'] = $_GET['tipo'];
    }
    if (!empty($_GET['data_inicio'])) {
        $filtros['data_inicio'] = $_GET['data_inicio'];
    }
    if (!empty($_GET['data_fim'])) {
        $filtros['data_fim'] = $_GET['data_fim'];
    }
    if (!empty($_GET['produto_id'])) {
        $filtros['produto_id'] = (int)$_GET['produto_id'];
    }

    // Debug: vamos testar a query diretamente primeiro
    $debug_query = "SELECT 
        m.*,
        DATE_FORMAT(m.criado_em, '%d/%m/%Y %H:%i:%s') as data_hora,
        p.nome as produto_nome,
        p.codigo as produto_codigo,
        u.nome as usuario_nome
    FROM movimentacoes_estoque m
    LEFT JOIN produtos p ON m.produto_id = p.id_produto
    LEFT JOIN usuarios u ON m.usuario_id = u.id
    ORDER BY m.criado_em DESC 
    LIMIT 10";
    
    $debug_stmt = $pdo->prepare($debug_query);
    $debug_stmt->execute();
    $debug_movimentacoes = $debug_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agora buscar usando o LogManager
    $movimentacoes = $logManager->buscarMovimentacoesEstoque($filtros, $registros_por_pagina, $offset);
    
    // Log de debug
    error_log("Debug - Registros direto do banco: " . count($debug_movimentacoes));
    error_log("Debug - Registros via LogManager: " . count($movimentacoes));
    if (!empty($debug_movimentacoes)) {
        error_log("Debug - Primeira movimentação direta: " . json_encode($debug_movimentacoes[0]));
    }
    if (!empty($movimentacoes)) {
        error_log("Debug - Primeira movimentação LogManager: " . json_encode($movimentacoes[0]));
    }

    // Contagem total para paginação
    $sql_count = "SELECT COUNT(*) FROM movimentacoes_estoque m WHERE 1=1";
    $params_count = [];
    
    if (!empty($filtros['tipo_movimentacao'])) {
        $sql_count .= " AND m.tipo_movimentacao = ?";
        $params_count[] = $filtros['tipo_movimentacao'];
    }
    if (!empty($filtros['data_inicio'])) {
        $sql_count .= " AND DATE(m.criado_em) >= ?";
        $params_count[] = $filtros['data_inicio'];
    }
    if (!empty($filtros['data_fim'])) {
        $sql_count .= " AND DATE(m.criado_em) <= ?";
        $params_count[] = $filtros['data_fim'];
    }
    if (!empty($filtros['produto_id'])) {
        $sql_count .= " AND m.produto_id = ?";
        $params_count[] = $filtros['produto_id'];
    }
    
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params_count);
    $total_registros = $stmt_count->fetchColumn();
    $total_paginas = ceil($total_registros / $registros_por_pagina);

    // Buscar produtos para filtro
    $stmt_produtos = $pdo->prepare("SELECT id_produto, nome FROM produtos WHERE ativo = 1 ORDER BY nome");
    $stmt_produtos->execute();
    $produtos = $stmt_produtos->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Erro ao carregar movimentações: " . $e->getMessage());
    $movimentacoes = [];
    $total_registros = 0;
    $total_paginas = 0;
    $produtos = [];
}

// Função para determinar se a página atual está ativa
function isActivePage($page) {
    $current = basename($_SERVER['PHP_SELF']);
    return $current === $page ? 'active' : '';
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimentações de Estoque - Sistema de Estoque</title>
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
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
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
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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

        /* Filters */
        .filters-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .filters-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 4px;
        }

        .form-control {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        /* Logs Section */
        .logs-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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

        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .logs-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .logs-table th {
            background: #f8fafc;
            color: #374151;
            font-weight: 600;
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }

        .logs-table td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
            color: #64748b;
            vertical-align: top;
        }

        .logs-table tbody tr:hover {
            background: #f8fafc;
        }

        .action-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .action-entrada { background: #dcfce7; color: #16a34a; }
        .action-saida { background: #fee2e2; color: #dc2626; }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
            font-size: 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #f8fafc;
            padding: 16px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .stat-card strong {
            color: #1e293b;
        }

        /* Paginação */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
        }

        .page-link {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            color: #374151;
            text-decoration: none;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .page-link:hover {
            background: #f3f4f6;
        }

        .page-link.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .page-link.disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 0.875rem;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #f59e0b;
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

            .filters-form {
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
                                <a href="../create/create_product.php" class="nav-link <?= isActivePage('create_product.php') ?>">
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
                            <a href="product_input_and_output_log.php" class="nav-link <?= isActivePage('product_input_and_output_log.php') ?>">
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
                    <h1>Movimentações de Estoque</h1>
                    <p class="header-subtitle">Visualize todas as entradas e saídas do estoque</p>
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
                    <a href="../logout.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                        Sair
                    </a>
                </div>
            </div>

            <!-- Filtros -->
            <section class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="form-group">
                        <label for="tipo">Tipo de Movimentação</label>
                        <select name="tipo" id="tipo" class="form-control">
                            <option value="">Todos</option>
                            <option value="entrada" <?= isset($_GET['tipo']) && $_GET['tipo'] === 'entrada' ? 'selected' : '' ?>>Entrada</option>
                            <option value="saida" <?= isset($_GET['tipo']) && $_GET['tipo'] === 'saida' ? 'selected' : '' ?>>Saída</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="produto_id">Produto</label>
                        <select name="produto_id" id="produto_id" class="form-control">
                            <option value="">Todos os produtos</option>
                            <?php foreach ($produtos as $produto): ?>
                                <option value="<?= $produto['id_produto'] ?>" <?= isset($_GET['produto_id']) && $_GET['produto_id'] == $produto['id_produto'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($produto['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="data_inicio">Data Início</label>
                        <input type="date" name="data_inicio" id="data_inicio" class="form-control" 
                               value="<?= htmlspecialchars($_GET['data_inicio'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="data_fim">Data Fim</label>
                        <input type="date" name="data_fim" id="data_fim" class="form-control" 
                               value="<?= htmlspecialchars($_GET['data_fim'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Filtrar
                        </button>
                        <a href="product_input_and_output_log.php" class="btn btn-secondary" style="margin-left: 8px;">
                            <i class="fas fa-times"></i>
                            Limpar
                        </a>
                    </div>
                </form>
            </section>

            <!-- Verificação de dados -->
            <?php if (empty($movimentacoes) && $total_registros === 0): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Nenhuma movimentação encontrada. Isso pode indicar que:
                    <ul style="margin: 8px 0 0 20px;">
                        <li>Não há dados na tabela <code>movimentacoes_estoque</code></li>
                        <li>Os filtros aplicados não retornaram resultados</li>
                        <li>Há um problema na estrutura do banco de dados</li>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Resumo das Movimentações -->
            <?php if (!empty($movimentacoes)): ?>
            <section class="logs-section">
                <div class="section-title">
                    <i class="fas fa-chart-bar"></i>
                    Resumo das Movimentações
                </div>
                
                <?php
                // Calcular estatísticas
                $total_entradas = 0;
                $total_saidas = 0;
                $produtos_afetados = [];
                
                foreach ($movimentacoes as $mov) {
                    if ($mov['tipo_movimentacao'] === 'entrada') {
                        $total_entradas += $mov['quantidade'];
                    } else {
                        $total_saidas += $mov['quantidade'];
                    }
                    
                    if (isset($mov['produto_id'])) {
                        $produtos_afetados[$mov['produto_id']] = $mov['produto_nome'];
                    }
                }
                
                $saldo = $total_entradas - $total_saidas;
                ?>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <strong>Total de Entradas:</strong><br>
                        <span class="action-badge action-entrada"><?= number_format($total_entradas, 0, ',', '.') ?> unidades</span>
                    </div>
                    <div class="stat-card">
                        <strong>Total de Saídas:</strong><br>
                        <span class="action-badge action-saida"><?= number_format($total_saidas, 0, ',', '.') ?> unidades</span>
                    </div>
                    <div class="stat-card">
                        <strong>Saldo:</strong><br>
                        <span class="action-badge <?= $saldo >= 0 ? 'action-entrada' : 'action-saida' ?>">
                            <?= number_format($saldo, 0, ',', '.') ?> unidades
                        </span>
                    </div>
                    <div class="stat-card">
                        <strong>Produtos Movimentados:</strong><br>
                        <?= count($produtos_afetados) ?> produtos diferentes
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- Tabela de Movimentações -->
            <section class="logs-section">
                <div class="section-title">
                    <i class="fas fa-exchange-alt"></i>
                    Histórico de Movimentações
                    <?php if ($total_registros > 0): ?>
                        <span style="font-size: 0.875rem; font-weight: normal; color: #64748b;">
                            (<?= number_format($total_registros, 0, ',', '.') ?> registros encontrados)
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="table-container">
                    <?php if (count($movimentacoes) === 0): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <p>Nenhuma movimentação encontrada.</p>
                        </div>
                    <?php else: ?>
                        <table class="logs-table">
                            <thead>
                                <tr>
                                    <th>Data/Hora</th>
                                    <th>Usuário</th>
                                    <th>Produto</th>
                                    <th>Tipo</th>
                                    <th>Quantidade</th>
                                    <th>Estoque Anterior</th>
                                    <th>Estoque Atual</th>
                                    <th>Motivo</th>
                                    <th>Detalhes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($movimentacoes as $mov): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($mov['data_hora'] ?? date('d/m/Y H:i:s', strtotime($mov['criado_em']))) ?></td>
                                        <td><?= htmlspecialchars($mov['usuario_nome'] ?? 'Sistema') ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($mov['produto_nome'] ?? 'Produto não encontrado') ?></strong>
                                            <?php if (!empty($mov['produto_codigo'])): ?>
                                                <br><small style="color: #64748b;">Código: <?= htmlspecialchars($mov['produto_codigo']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $tipo = strtoupper($mov['tipo_movimentacao']);
                                            $class = ($tipo === 'ENTRADA') ? 'action-entrada' : 'action-saida';
                                            ?>
                                            <span class="action-badge <?= $class ?>">
                                                <?= $tipo === 'ENTRADA' ? 'ENTRADA' : 'SAÍDA' ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center; font-weight: bold;">
                                            <?= number_format($mov['quantidade'], 0, ',', '.') ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <?= number_format($mov['quantidade_anterior'] ?? 0, 0, ',', '.') ?>
                                        </td>
                                        <td style="text-align: center; font-weight: bold;">
                                            <?= number_format($mov['quantidade_atual'] ?? 0, 0, ',', '.') ?>
                                        </td>
                                        <td><?= htmlspecialchars($mov['motivo'] ?? '-') ?></td>
                                        <td>
                                            <?php if (!empty($mov['observacoes'])): ?>
                                                <div><strong>Obs:</strong> <?= htmlspecialchars($mov['observacoes']) ?></div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($mov['fornecedor_nome'])): ?>
                                                <div><small><strong>Fornecedor:</strong> <?= htmlspecialchars($mov['fornecedor_nome']) ?></small></div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($mov['nota_fiscal'])): ?>
                                                <div><small><strong>NF:</strong> <?= htmlspecialchars($mov['nota_fiscal']) ?></small></div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($mov['valor_unitario']) && $mov['valor_unitario'] > 0): ?>
                                                <div><small><strong>Valor Unit.:</strong> R$ <?= number_format($mov['valor_unitario'], 2, ',', '.') ?></small></div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($mov['destino'])): ?>
                                                <div><small><strong>Destino:</strong> <?= htmlspecialchars($mov['destino']) ?></small></div>
                                            <?php endif; ?>
                                            
                                            <?php if (empty($mov['observacoes']) && empty($mov['fornecedor_nome']) && empty($mov['nota_fiscal']) && empty($mov['destino']) && (empty($mov['valor_unitario']) || $mov['valor_unitario'] == 0)): ?>
                                                <span style="color: #94a3b8;">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Paginação -->
                <?php if ($total_paginas > 1): ?>
                    <div class="pagination">
                        <?php
                        $query_params = $_GET;
                        ?>
                        
                        <!-- Primeira página -->
                        <?php if ($pagina_atual > 1): ?>
                            <?php
                            $query_params['pagina'] = 1;
                            $url = 'product_input_and_output_log.php?' . http_build_query($query_params);
                            ?>
                            <a href="<?= $url ?>" class="page-link">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-link disabled">
                                <i class="fas fa-angle-double-left"></i>
                            </span>
                        <?php endif; ?>

                        <!-- Página anterior -->
                        <?php if ($pagina_atual > 1): ?>
                            <?php
                            $query_params['pagina'] = $pagina_atual - 1;
                            $url = 'product_input_and_output_log.php?' . http_build_query($query_params);
                            ?>
                            <a href="<?= $url ?>" class="page-link">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-link disabled">
                                <i class="fas fa-angle-left"></i>
                            </span>
                        <?php endif; ?>

                        <!-- Páginas numeradas -->
                        <?php
                        $inicio = max(1, $pagina_atual - 2);
                        $fim = min($total_paginas, $pagina_atual + 2);

                        for ($i = $inicio; $i <= $fim; $i++):
                        ?>
                            <?php if ($i == $pagina_atual): ?>
                                <span class="page-link active"><?= $i ?></span>
                            <?php else: ?>
                                <?php
                                $query_params['pagina'] = $i;
                                $url = 'product_input_and_output_log.php?' . http_build_query($query_params);
                                ?>
                                <a href="<?= $url ?>" class="page-link"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <!-- Próxima página -->
                        <?php if ($pagina_atual < $total_paginas): ?>
                            <?php
                            $query_params['pagina'] = $pagina_atual + 1;
                            $url = 'product_input_and_output_log.php?' . http_build_query($query_params);
                            ?>
                            <a href="<?= $url ?>" class="page-link">
                                <i class="fas fa-angle-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-link disabled">
                                <i class="fas fa-angle-right"></i>
                            </span>
                        <?php endif; ?>

                        <!-- Última página -->
                        <?php if ($pagina_atual < $total_paginas): ?>
                            <?php
                            $query_params['pagina'] = $total_paginas;
                            $url = 'product_input_and_output_log.php?' . http_build_query($query_params);
                            ?>
                            <a href="<?= $url ?>" class="page-link">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-link disabled">
                                <i class="fas fa-angle-double-right"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div style="text-align: center; margin-top: 12px; color: #64748b; font-size: 0.875rem;">
                        Página <?= $pagina_atual ?> de <?= $total_paginas ?> 
                        (<?= number_format($total_registros, 0, ',', '.') ?> registros no total)
                    </div>
                <?php endif; ?>
            </section>

            <!-- Debug detalhado -->
            <section class="logs-section">
                <div class="section-title">
                    <i class="fas fa-bug"></i>
                    Informações de Debug (sempre visível para diagnóstico)
                </div>
                <div style="background: #f8f9fa; padding: 16px; border-radius: 8px; font-family: monospace; font-size: 0.8rem;">
                    <div style="background: #e3f2fd; padding: 12px; margin-bottom: 12px; border-radius: 4px;">
                        <strong>🔍 DIAGNÓSTICO:</strong><br>
                        <strong>Total de registros encontrados:</strong> <?= $total_registros ?><br>
                        <strong>Registros via busca direta:</strong> <?= count($debug_movimentacoes ?? []) ?><br>
                        <strong>Registros via LogManager:</strong> <?= count($movimentacoes) ?><br>
                        <strong>Página atual:</strong> <?= $pagina_atual ?><br>
                        <strong>Offset:</strong> <?= $offset ?><br>
                    </div>
                    
                    <?php if (!empty($debug_movimentacoes)): ?>
                        <div style="background: #e8f5e8; padding: 12px; margin-bottom: 12px; border-radius: 4px;">
                            <strong>✅ BUSCA DIRETA (primeiros 10 registros encontrados):</strong><br>
                            <pre style="max-height: 200px; overflow-y: auto; font-size: 0.7rem;">
<?= json_encode(array_slice($debug_movimentacoes, 0, 3), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?>
                            </pre>
                        </div>
                    <?php else: ?>
                        <div style="background: #ffebee; padding: 12px; margin-bottom: 12px; border-radius: 4px;">
                            <strong>❌ BUSCA DIRETA NÃO RETORNOU DADOS</strong><br>
                            Isso indica que a tabela movimentacoes_estoque está vazia ou não existe.
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($movimentacoes)): ?>
                        <div style="background: #e8f5e8; padding: 12px; margin-bottom: 12px; border-radius: 4px;">
                            <strong>✅ LOGMANAGER RETORNOU DADOS:</strong><br>
                            <pre style="max-height: 200px; overflow-y: auto; font-size: 0.7rem;">
<?= json_encode(array_slice($movimentacoes, 0, 2), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?>
                            </pre>
                        </div>
                    <?php else: ?>
                        <div style="background: #ffebee; padding: 12px; margin-bottom: 12px; border-radius: 4px;">
                            <strong>❌ LOGMANAGER NÃO RETORNOU DADOS</strong><br>
                            O problema está na função buscarMovimentacoesEstoque do LogManager.
                        </div>
                    <?php endif; ?>
                    
                    <div style="background: #fff3e0; padding: 12px; margin-bottom: 12px; border-radius: 4px;">
                        <strong>🔧 FILTROS APLICADOS:</strong><br>
                        <?= json_encode($filtros, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?>
                    </div>
                    
                    <div style="background: #fce4ec; padding: 12px; border-radius: 4px;">
                        <strong>📊 VERIFICAÇÃO DA TABELA:</strong><br>
                        <?php
                        try {
                            $check_table = $pdo->query("SHOW TABLES LIKE 'movimentacoes_estoque'");
                            $table_exists = $check_table->rowCount() > 0;
                            echo "Tabela movimentacoes_estoque existe: " . ($table_exists ? "SIM" : "NÃO") . "<br>";
                            
                            if ($table_exists) {
                                $check_columns = $pdo->query("DESCRIBE movimentacoes_estoque");
                                $columns = $check_columns->fetchAll(PDO::FETCH_ASSOC);
                                echo "Colunas da tabela:<br>";
                                foreach ($columns as $col) {
                                    echo "- " . $col['Field'] . " (" . $col['Type'] . ")<br>";
                                }
                                
                                $count_all = $pdo->query("SELECT COUNT(*) FROM movimentacoes_estoque")->fetchColumn();
                                echo "<br>Total de registros na tabela: " . $count_all;
                            }
                        } catch (Exception $e) {
                            echo "Erro ao verificar tabela: " . $e->getMessage();
                        }
                        ?>
                    </div>
                </div>
            </section>

            <!-- Rodapé com informações adicionais -->
            <div style="text-align: center; color: #94a3b8; font-size: 0.8rem; margin-top: 40px; padding: 20px;">
                <p>Sistema de Controle de Estoque - KYTEC</p>
                <p>Para problemas técnicos, adicione <code>?debug=1</code> na URL para ver informações de debug</p>
            </div>
        </main>
    </div>

    <script>
        // Script para melhorar a UX
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-submit do formulário quando mudar o select de tipo
            const tipoSelect = document.getElementById('tipo');
            const produtoSelect = document.getElementById('produto_id');
            
            // Opcional: submeter automaticamente quando alterar filtros principais
            // tipoSelect.addEventListener('change', function() {
            //     this.form.submit();
            // });
            
            // Destacar linha da tabela ao passar o mouse
            const tableRows = document.querySelectorAll('.logs-table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f1f5f9';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });

            // Confirmar limpeza de filtros
            const clearBtn = document.querySelector('a[href="product_input_and_output_log.php"]');
            if (clearBtn && (window.location.search.length > 0)) {
                clearBtn.addEventListener('click', function(e) {
                    if (!confirm('Deseja limpar todos os filtros aplicados?')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>