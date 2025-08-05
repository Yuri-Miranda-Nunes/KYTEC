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
    $_SESSION['mensagem_erro'] = "ID de fornecedor inválido.";
    header("Location: ../read/read_supplier.php");
    exit;
}

$bd = new BancoDeDados();

// Buscar dados do fornecedor
try {
    $stmt = $bd->pdo->prepare("SELECT * FROM fornecedores WHERE id_fornecedor = ?");
    $stmt->execute([$id]);
    $fornecedor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fornecedor) {
        $_SESSION['mensagem_erro'] = "Fornecedor não encontrado.";
        header("Location: ../read/read_supplier.php");
        exit;
    }
} catch (Exception $e) {
    $_SESSION['mensagem_erro'] = "Erro ao buscar fornecedor: " . $e->getMessage();
    header("Location: ../read/read_supplier.php");
    exit;
}

// Função para formatar telefone
function formatarTelefone($telefone)
{
    if (empty($telefone)) return '-';

    $telefone = preg_replace('/\D/', '', $telefone);

    if (strlen($telefone) == 11) {
        return sprintf(
            '(%s) %s-%s',
            substr($telefone, 0, 2),
            substr($telefone, 2, 5),
            substr($telefone, 7)
        );
    } elseif (strlen($telefone) == 10) {
        return sprintf(
            '(%s) %s-%s',
            substr($telefone, 0, 2),
            substr($telefone, 2, 4),
            substr($telefone, 6)
        );
    }

    return $telefone;
}

// Função para formatar CNPJ
function formatarCNPJ($cnpj)
{
    if (empty($cnpj)) return '-';

    $cnpj = preg_replace('/\D/', '', $cnpj);

    if (strlen($cnpj) == 14) {
        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($cnpj, 0, 2),
            substr($cnpj, 2, 3),
            substr($cnpj, 5, 3),
            substr($cnpj, 8, 4),
            substr($cnpj, 12, 2)
        );
    }

    return $cnpj;
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Fornecedor - Sistema de Estoque</title>
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

        /* Supplier Header */
        .supplier-header {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .supplier-main-info {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }

        .supplier-icon {
            width: 64px;
            height: 64px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }

        .supplier-details h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .supplier-details p {
            font-size: 1rem;
            opacity: 0.9;
        }

        .supplier-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
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

        .info-value.system-info {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 0.85rem;
            background: #f1f5f9;
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
        }

        .supplier-id {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        /* Activity Section */
        .activity-section {
            grid-column: 1 / -1;
        }

        .activity-text {
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

        .btn-ghost {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-ghost:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .btn-ghost-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .btn-ghost-danger:hover {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.3);
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

            .supplier-main-info {
                flex-direction: column;
                text-align: center;
            }

            .supplier-actions {
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
                            <a href="../read/read_product.php" class="nav-link">
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
                        <a href="../read/read_supplier.php" class="nav-link active">
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
                    <h1>Detalhes do Fornecedor</h1>
                    <p class="header-subtitle">Visualize todas as informações do fornecedor</p>
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

            <!-- Supplier Header -->
            <div class="supplier-header">
                <div class="supplier-main-info">
                    <div class="supplier-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="supplier-details">
                        <h1><?= htmlspecialchars($fornecedor['nome_empresa']) ?></h1>
                        <p><?= !empty($fornecedor['atividade']) ? htmlspecialchars($fornecedor['atividade']) : 'Atividade não informada' ?></p>
                    </div>
                </div>

            </div>

            <!-- Information Grid -->
            <div class="info-grid">
                <!-- Informações da Empresa -->
                <div class="info-card">
                    <h3>
                        <i class="fas fa-building"></i>
                        Informações da Empresa
                    </h3>

                    <div class="info-item">
                        <span class="info-label">Nome da Empresa</span>
                        <span class="info-value"><?= htmlspecialchars($fornecedor['nome_empresa']) ?></span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">CNPJ</span>
                        <span class="info-value <?= empty($fornecedor['cnpj']) ? 'empty' : '' ?>">
                            <?= formatarCNPJ($fornecedor['cnpj']) ?>
                        </span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Atividade Principal</span>
                        <span class="info-value <?= empty($fornecedor['atividade']) ? 'empty' : '' ?>">
                            <?= !empty($fornecedor['atividade']) ? htmlspecialchars($fornecedor['atividade']) : 'Não informado' ?>
                        </span>
                    </div>
                </div>

                <!-- Informações do Representante -->
                <div class="info-card">
                    <h3>
                        <i class="fas fa-user-tie"></i>
                        Representante
                    </h3>

                    <div class="info-item">
                        <span class="info-label">Nome do Representante</span>
                        <span class="info-value <?= empty($fornecedor['nome_representante']) ? 'empty' : '' ?>">
                            <?= !empty($fornecedor['nome_representante']) ? htmlspecialchars($fornecedor['nome_representante']) : 'Não informado' ?>
                        </span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Telefone do Representante</span>
                        <span class="info-value <?= empty($fornecedor['telefone_representante']) ? 'empty' : '' ?>">
                            <?= formatarTelefone($fornecedor['telefone_representante']) ?>
                        </span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Email do Representante</span>
                        <span class="info-value <?= empty($fornecedor['email_representante']) ? 'empty' : '' ?>">
                            <?= !empty($fornecedor['email_representante']) ? htmlspecialchars($fornecedor['email_representante']) : 'Não informado' ?>
                        </span>
                    </div>
                </div>

                <!-- Endereço -->
                <div class="info-card">
                    <h3>
                        <i class="fas fa-map-marker-alt"></i>
                        Endereço
                    </h3>

                    <div class="info-item">
                        <span class="info-label">Endereço Completo</span>
                        <span class="info-value <?= empty($fornecedor['endereco']) ? 'empty' : '' ?>">
                            <?= !empty($fornecedor['endereco']) ? htmlspecialchars($fornecedor['endereco']) : 'Endereço não informado' ?>
                        </span>
                    </div>
                </div>

                <!-- Informações de Controle -->
                <div class="info-card">
                    <h3>
                        <i class="fas fa-info-circle"></i>
                        Informações do Sistema
                    </h3>

                    <div class="info-item">
                        <span class="info-label">Cadastrado em</span>
                        <span class="info-value">
                            <?= date('d/m/Y \à\s H:i', strtotime($fornecedor['criado_em'])) ?>
                        </span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Última Atualização</span>
                        <span class="info-value">
                            <?= date('d/m/Y \à\s H:i', strtotime($fornecedor['atualizado_em'])) ?>
                        </span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">ID do Fornecedor</span>
                        <span class="info-value">
                            <span class="supplier-id">#<?= str_pad($fornecedor['id_fornecedor'], 4, '0', STR_PAD_LEFT) ?></span>
                        </span>
                    </div>
                </div>

                <!-- Atividade -->
                <div class="info-card activity-section">
                    <h3>
                        <i class="fas fa-briefcase"></i>
                        Descrição Detalhada da Atividade
                    </h3>

                    <div class="activity-text">
                        <?= !empty($fornecedor['atividade']) ? nl2br(htmlspecialchars($fornecedor['atividade'])) : '<em style="color: #94a3b8;">Descrição detalhada não informada</em>' ?>
                    </div>
                </div>
            </div>

            <!-- Navigation Actions -->
            <div class="info-section">
                <div class="nav-actions">
                    <a href="../read/read_supplier.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Voltar à Lista
                    </a>
                    <a href="../update/update_supplier.php?id=<?= $fornecedor['id_fornecedor'] ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i>
                        Editar Fornecedor
                    </a>
                    <a href="../delete/delete_supplier.php?id=<?= $fornecedor['id_fornecedor'] ?>" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja excluir este fornecedor? Esta ação não pode ser desfeita.')">
                        <i class="fas fa-trash"></i>
                        Excluir Fornecedor
                    </a>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Função para confirmar exclusão
        function confirmarExclusao() {
            return confirm('Tem certeza que deseja excluir este fornecedor?\n\nEsta ação não pode ser desfeita e removerá permanentemente todos os dados do fornecedor do sistema.');
        }

        // Adicionar confirmação aos links de exclusão
        document.querySelectorAll('a[href*="excluir_fornecedor"]').forEach(link => {
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