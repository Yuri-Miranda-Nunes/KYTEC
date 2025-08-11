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
                            <a href="../read/read_product.php" class="nav-link active">
                                <i class="fas fa-list"></i>
                                <span>Listar Produtos</span>
                            </a>
                        </div>
                        <?php if (temPermissao('cadastrar_produtos')): ?>
                            <div class="nav-item">
                                <a href="../create/create_product.php" class="nav-link">
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
                        <a href="../read/read_supplier.php" class="nav-link">
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
                            <a href="../read/read_user.php" class="nav-link">
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
                        <a href="../perfil.php" class="nav-link">
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
                    <p class="header-subtitle">Visualize todas as informações do produto</p>
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
                            <span class="stock-badge <?= getEstoqueBadgeClass($produto['estoque_atual'], $produto['estoque_minimo']) ?>">
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
                            <span class="product-id">#<?= str_pad($produto['id_produto'], 4, '0', STR_PAD_LEFT) ?></span>
                        </span>
                    </div>
                </div>

                <div class="info-card">
                <form action="saida_estoque_confirm.php" method="POST" style="margin-top: 30px; padding: 20px; border: 1px solid #ccc; border-radius: 8px; max-width: 400px;">
    <h3>Registrar Saída do Estoque</h3>
    <input type="hidden" name="produto_id" value="<?= htmlspecialchars($produto['id_produto']) ?>">
    <input type="hidden" name="usuario_id" value="<?= htmlspecialchars($_SESSION['usuario_id']) ?>">

    <label for="quantidade">Quantidade:</label>
    <input type="number" id="quantidade" name="quantidade" min="1" max="<?= $produto['estoque_atual'] ?>" required>

    <label for="motivo">Motivo:</label>
    <input type="text" id="motivo" name="motivo" value="uso" required>

    <label for="destino">Destino (opcional):</label>
    <input type="text" id="destino" name="destino">

    <label for="observacoes">Observações (opcional):</label>
    <textarea id="observacoes" name="observacoes" rows="3"></textarea>

    <button type="submit" style="margin-top: 10px; background-color:#e74c3c; color: white; padding: 8px 16px; border:none; border-radius: 4px; cursor:pointer;">
        Registrar Saída
    </button>
</form>

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
                        <a href="../delete/delete_product.php?id=<?= $produto['id_produto'] ?>" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja excluir este produto? Esta ação não pode ser desfeita.')">
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
        });
    </script>
</body>

</html>