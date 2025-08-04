<?php
session_start();

// Verifica se está logado e tem permissão
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: login.php");
    exit;
}

if (!in_array('listar_produtos', $_SESSION['permissoes'] ?? [])) {
    echo "Acesso negado.";
    exit;
}

// Função para verificar permissões
function temPermissao($permissao) {
    return in_array($permissao, $_SESSION['permissoes'] ?? []);
}

require_once 'conexao.php';
require_once 'log_manager.php';

$bd = new BancoDeDados();
$logManager = new LogManager($bd->pdo);

// Parâmetros de filtro
$filtros = [
    'usuario_id' => $_GET['usuario_id'] ?? '',
    'acao' => $_GET['acao'] ?? '',
    'tabela' => $_GET['tabela'] ?? '',
    'data_inicio' => $_GET['data_inicio'] ?? '',
    'data_fim' => $_GET['data_fim'] ?? '',
];

$tipo_view = $_GET['tipo'] ?? 'logs'; // 'logs' ou 'movimentacoes'

// Buscar dados
if ($tipo_view === 'movimentacoes') {
    $registros = $logManager->buscarMovimentacoesEstoque($filtros, 100);
} else {
    $registros = $logManager->buscarLogs($filtros, 100);
}

// Buscar usuários para o filtro
$stmt = $bd->pdo->query("SELECT id, nome FROM usuarios ORDER BY nome");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs do Sistema - Sistema de Estoque</title>
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
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 24px;
        }

        .filters-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
        }

        .filter-input, .filter-select {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
        }

        .filter-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
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

        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 24px;
        }

        .tab {
            padding: 12px 20px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            text-decoration: none;
            color: #64748b;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .tab.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        /* Logs Table */
        .logs-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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

        .action-create { background: #dcfce7; color: #16a34a; }
        .action-update { background: #fef3c7; color: #d97706; }
        .action-delete { background: #fee2e2; color: #dc2626; }
        .action-login { background: #e0e7ff; color: #3730a3; }
        .action-logout { background: #f3f4f6; color: #4b5563; }
        .action-entrada { background: #dcfce7; color: #16a34a; }
        .action-saida { background: #fee2e2; color: #dc2626; }

        .details-toggle {
            background: none;
            border: none;
            color: #3b82f6;
            cursor: pointer;
            font-size: 0.75rem;
        }

        .details-content {
            display: none;
            margin-top: 8px;
            padding: 8px;
            background: #f8fafc;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
            font-size: 1rem;
        }
    </style>
    <script>
        function toggleDetails(id) {
            const content = document.getElementById('details-' + id);
            if (content.style.display === 'block') {
                content.style.display = 'none';
            } else {
                content.style.display = 'block';
            }
        }
    </script>
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-clipboard-list"></i> Sistema Estoque</h2>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Menu</div>
                    <div class="nav-item">
                        <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Início</a>
                    </div>
                    <div class="nav-item">
                        <a href="cadastrar_prod.php" class="nav-link"><i class="fas fa-box"></i> Produtos</a>
                    </div>
                    <div class="nav-item">
                        <a href="listar_produtos.php" class="nav-link"><i class="fas fa-warehouse"></i> Estoque</a>
                    </div>
                    <div class="nav-item">
                        <a href="logs.php" class="nav-link active"><i class="fas fa-file-alt"></i> Logs</a>
                    </div>
                    <?php if(temPermissao('admin')): ?>
                    <div class="nav-item">
                        <a href="usuarios.php" class="nav-link"><i class="fas fa-users"></i> Usuários</a>
                    </div>
                    <?php endif; ?>
                </div>
            </nav>
        </aside>
 
        <main class="main-content">
            <header class="header">
                <div class="header-left">
                    <h1>Logs do Sistema</h1>
                    <p class="header-subtitle">Visualize e filtre os registros de atividades</p>
                </div>
                <div class="header-right">
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php
                            // Exibir iniciais do usuário logado
                            $nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';
                            $iniciais = implode('', array_map(function($n){ return strtoupper($n[0]); }, explode(' ', $nomeUsuario)));
                            echo $iniciais;
                            ?>
                        </div>
                        <div class="user-details">
                            <h3><?=htmlspecialchars($nomeUsuario)?></h3>
                            <p><?=htmlspecialchars($_SESSION['usuario_email'] ?? '')?></p>
                        </div>
                    </div>
                    <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Sair</a>
                </div>
            </header>
 
            <section class="filters-section">
                <form method="GET" action="logs.php">
                    <div class="filters-title"><i class="fas fa-filter"></i> Filtros</div>
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label class="filter-label" for="usuario_id">Usuário</label>
                            <select name="usuario_id" id="usuario_id" class="filter-select">
                                <option value="">Todos</option>
                                <?php foreach($usuarios as $usuario): ?>
                                    <option value="<?= $usuario['id'] ?>" <?= ($filtros['usuario_id'] == $usuario['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($usuario['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
 
                        <div class="filter-group">
                            <label class="filter-label" for="acao">Ação</label>
                            <select name="acao" id="acao" class="filter-select">
                                <option value="">Todas</option>
                                <option value="create" <?= ($filtros['acao'] === 'create') ? 'selected' : '' ?>>Criar</option>
                                <option value="update" <?= ($filtros['acao'] === 'update') ? 'selected' : '' ?>>Atualizar</option>
                                <option value="delete" <?= ($filtros['acao'] === 'delete') ? 'selected' : '' ?>>Deletar</option>
                                <option value="login" <?= ($filtros['acao'] === 'login') ? 'selected' : '' ?>>Login</option>
                                <option value="logout" <?= ($filtros['acao'] === 'logout') ? 'selected' : '' ?>>Logout</option>
                                <option value="entrada" <?= ($filtros['acao'] === 'entrada') ? 'selected' : '' ?>>Entrada</option>
                                <option value="saida" <?= ($filtros['acao'] === 'saida') ? 'selected' : '' ?>>Saída</option>
                                <option value="ENTRADA_ESTOQUE" <?= ($filtros['acao'] === 'ENTRADA_ESTOQUE') ? 'selected' : '' ?>>Entrada Estoque</option>
                                <option value="SAIDA_ESTOQUE" <?= ($filtros['acao'] === 'SAIDA_ESTOQUE') ? 'selected' : '' ?>>Saída Estoque</option>
                            </select>
                        </div>
 
                        <div class="filter-group">
                            <label class="filter-label" for="tabela">Tabela</label>
                            <input type="text" id="tabela" name="tabela" class="filter-input" value="<?=htmlspecialchars($filtros['tabela'])?>">
                        </div>
 
                        <div class="filter-group">
                            <label class="filter-label" for="data_inicio">Data Início</label>
                            <input type="date" id="data_inicio" name="data_inicio" class="filter-input" value="<?=htmlspecialchars($filtros['data_inicio'])?>">
                        </div>
 
                        <div class="filter-group">
                            <label class="filter-label" for="data_fim">Data Fim</label>
                            <input type="date" id="data_fim" name="data_fim" class="filter-input" value="<?=htmlspecialchars($filtros['data_fim'])?>">
                        </div>
 
                        <div class="filter-group">
                            <label class="filter-label" for="tipo">Tipo de Visualização</label>
                            <select name="tipo" id="tipo" class="filter-select">
                                <option value="logs" <?= ($tipo_view === 'logs') ? 'selected' : '' ?>>Logs</option>
                                <option value="movimentacoes" <?= ($tipo_view === 'movimentacoes') ? 'selected' : '' ?>>Movimentações de Estoque</option>
                            </select>
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrar</button>
                        <a href="logs.php" class="btn btn-secondary"><i class="fas fa-eraser"></i> Limpar</a>
                    </div>
                </form>
            </section>
 
            <section class="logs-section">
                <div class="section-title">
                    <i class="fas fa-file-alt"></i>
                    <?= ($tipo_view === 'movimentacoes') ? 'Movimentações de Estoque' : 'Logs do Sistema' ?>
                </div>
                <div class="table-container">
                    <?php if (count($registros) === 0): ?>
                        <div class="empty-state">Nenhum registro encontrado para os filtros selecionados.</div>
                    <?php else: ?>
                        <table class="logs-table" role="table" aria-label="Tabela de logs">
                            <thead>
                                <tr>
                                    <th>Data/Hora</th>
                                    <th>Usuário</th>
                                    <th>Ação</th>
                                    <th>Tabela</th>
                                    <th>Descrição</th>
                                    <th>Detalhes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registros as $log): ?>
                                    <tr>
                                        <td class="data-hora">
                                            <?php
                                            // Tentar diferentes campos de data
                                            $data_exibir = $log['data_hora'] ?? $log['data'] ?? $log['criado_em'] ?? '';
                                            // Se a data não estiver formatada, formatar
                                            if ($data_exibir && !strpos($data_exibir, '/')) {
                                                $data_exibir = date('d/m/Y H:i:s', strtotime($data_exibir));
                                            }
                                            echo htmlspecialchars($data_exibir);
                                            ?>
                                        </td>
                                        <td class="usuario-nome"><?=htmlspecialchars($log['usuario_nome'] ?? 'Sistema')?></td>
                                        <td>
                                            <?php
                                            $acao = $log['acao'] ?? $log['tipo_movimentacao'] ?? '';
                                            $classes = [
                                                'create' => 'action-create',
                                                'CREATE' => 'action-create',
                                                'update' => 'action-update',
                                                'UPDATE' => 'action-update',
                                                'delete' => 'action-delete',
                                                'DELETE' => 'action-delete',
                                                'login' => 'action-login',
                                                'LOGIN' => 'action-login',
                                                'logout' => 'action-logout',
                                                'LOGOUT' => 'action-logout',
                                                'entrada' => 'action-entrada',
                                                'ENTRADA' => 'action-entrada',
                                                'ENTRADA_ESTOQUE' => 'action-entrada',
                                                'saida' => 'action-saida',
                                                'SAIDA' => 'action-saida',
                                                'SAIDA_ESTOQUE' => 'action-saida',
                                            ];
                                            $class = $classes[strtoupper($acao)] ?? $classes[$acao] ?? '';
                                            $acao_exibir = ucfirst(strtolower(str_replace('_ESTOQUE', '', $acao)));
                                            ?>
                                            <span class="action-badge <?= $class ?>"><?= $acao_exibir ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $tabela = $log['tabela'] ?? '';
                                            if ($tipo_view === 'movimentacoes') {
                                                $tabela = 'produtos'; // Movimentações sempre são de produtos
                                            }
                                            echo $tabela ? '<span class="tabela-badge">' . htmlspecialchars($tabela) . '</span>' : '-';
                                            ?>
                                        </td>
                                        <td class="descricao">
                                            <?php
                                            // Priorizar campo descricao, senão criar uma baseada nos dados
                                            $descricao = $log['descricao'] ?? '';
                                           
                                            if (empty($descricao) && $tipo_view === 'movimentacoes') {
                                                // Para movimentações, criar descrição baseada nos dados
                                                $tipo = ucfirst($log['tipo_movimentacao'] ?? '');
                                                $quantidade = $log['quantidade'] ?? '';
                                                $produto_nome = $log['produto_nome'] ?? 'Produto ID: ' . ($log['produto_id'] ?? '');
                                                $descricao = "{$tipo} de {$quantidade} unidades - {$produto_nome}";
                                            } elseif (empty($descricao)) {
                                                // Para logs gerais, tentar criar descrição baseada na ação
                                                $acao_desc = ucfirst(strtolower(str_replace('_ESTOQUE', '', $acao)));
                                                if ($log['tabela'] && $log['registro_id']) {
                                                    $descricao = "{$acao_desc} em {$log['tabela']} ID: {$log['registro_id']}";
                                                } else {
                                                    $descricao = $acao_desc;
                                                }
                                            }
                                           
                                            echo htmlspecialchars($descricao);
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $detalhes = $log['detalhes'] ?? '';
                                           
                                            // Se não tem detalhes mas tem dados de movimentação, criar detalhes
                                            
                                           
                                            if (!empty($detalhes)):
                                                $log_id = $log['id'] ?? uniqid();
                                            ?>
                                                <button class="details-toggle" onclick="toggleDetails('<?= $log_id ?>')">Mostrar</button>
                                                <div id="details-<?= $log_id ?>" class="details-content"><?= nl2br(htmlspecialchars($detalhes)) ?></div>
                                            <?php else: ?>
                                                -
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
 
    <script>
        function toggleDetails(id) {
            const detailsElement = document.getElementById('details-' + id);
            const toggleButton = detailsElement.previousElementSibling;
           
            if (detailsElement.style.display === 'none' || !detailsElement.style.display) {
                detailsElement.style.display = 'block';
                toggleButton.textContent = 'Ocultar';
            } else {
                detailsElement.style.display = 'none';
                toggleButton.textContent = 'Mostrar';
            }
        }
    </script>
</body>
</html>
