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

$bd = new BancoDeDados();
$logManager = new LogManager($bd->pdo);

// Buscar todas as movimentações de estoque
$movimentacoes = $logManager->buscarMovimentacoesEstoque([], 100);

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

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .main-content {
                margin-left: 0;
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

            <!-- Resumo das Movimentações -->
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

            <!-- Tabela de Movimentações -->
            <section class="logs-section">
                <div class="section-title">
                    <i class="fas fa-exchange-alt"></i>
                    Histórico de Movimentações
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
                                    <th>Observações</th>
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
                                                <?= htmlspecialchars($mov['observacoes']) ?>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($mov['fornecedor_nome'])): ?>
                                                <br><small><strong>Fornecedor:</strong> <?= htmlspecialchars($mov['fornecedor_nome']) ?></small>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($mov['nota_fiscal'])): ?>
                                                <br><small><strong>NF:</strong> <?= htmlspecialchars($mov['nota_fiscal']) ?></small>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($mov['valor_unitario'])): ?>
                                                <br><small><strong>Valor Unit.:</strong> R$ <?= number_format($mov['valor_unitario'], 2, ',', '.') ?></small>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($mov['destino'])): ?>
                                                <br><small><strong>Destino:</strong> <?= htmlspecialchars($mov['destino']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
</body>
</html>