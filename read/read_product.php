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
            transition: background-color 0.2s ease;
        }

        .products-table tbody tr:hover {
            background: #f8fafc;
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
                        <a href="../index.php" class="nav-link">
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
                            <a href="../read/read_product.php" class="nav-link">
                                <i class="fas fa-list"></i>
                                <span>Listar Produtos</span>
                            </a>
                        </div>
                        <?php if (temPermissao('cadastrar_produtos')): ?>
                            <div class="nav-item">
                                <a href="../create\create_product.php" class="nav-link">
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
                        <a href="read_supplier.php" class="nav-link active">
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
                            <a href="read_user.php" class="nav-link">
                                <i class="fas fa-users"></i>
                                <span>Listar Usuários</span>
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="../create/create_user.php" class="nav-link">
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
                                    <?php if (temPermissao('editar_produtos') || temPermissao('excluir_produtos')): ?>
                                        <th>Ações</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($produtos as $p): ?>
                                    <tr>
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
                                            <?= htmlspecialchars($p['descricao'] ?: 'Sem descrição') ?>
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
                                        <?php if (temPermissao('editar_produtos') || temPermissao('excluir_produtos')): ?>
                                            <td>
                                                <div style="display: flex; gap: 8px;">
                                                    <?php if (temPermissao('editar_produtos')): ?>
                                                        <a href="../update/update_product.php?id=<?= $p['id_produto'] ?>"
                                                            style="color: #3b82f6; font-size: 0.875rem; text-decoration: none;"
                                                            title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (temPermissao('excluir_produtos')): ?>
                                                        <a href="../delete/delete_product.php?id=<?= $p['id_produto'] ?>"
                                                            style="color: #ef4444; font-size: 0.875rem; text-decoration: none;"
                                                            title="Excluir"
                                                            onclick="return confirm('Tem certeza que deseja excluir este produto?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        <?php endif; ?>
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
</body>

</html>

</html>