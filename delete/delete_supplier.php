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

// Verifica se tem permissão para gerenciar fornecedores (usando permissão de admin ou similar)
if (!temPermissao('gerenciar_usuarios')) {
    $_SESSION['mensagem_erro'] = "Acesso negado. Você não tem permissão para excluir fornecedores.";
    header("Location: ../read/read_supplier.php");
    exit;
}

require_once '../conexao.php';

$bd = new BancoDeDados();

// Verifica se o ID foi fornecido
$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    $_SESSION['mensagem_erro'] = "ID de fornecedor inválido.";
    header("Location: ../read/read_supplier.php");
    exit;
}

// Buscar dados do fornecedor para confirmação
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

// Verifica se o fornecedor tem relacionamentos (movimentações de estoque)
try {
    $stmt = $bd->pdo->prepare("SELECT COUNT(*) as total FROM movimentacoes_estoque WHERE fornecedor_id = ?");
    $stmt->execute([$id]);
    $movimentacoes = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $bd->pdo->prepare("SELECT COUNT(*) as total FROM entradas_estoque WHERE id_fornecedor = ?");
    $stmt->execute([$id]);
    $entradas = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $temRelacionamentos = ($movimentacoes['total'] > 0 || $entradas['total'] > 0);
} catch (Exception $e) {
    $temRelacionamentos = false;
}

// Processar exclusão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_exclusao'])) {
    try {
        $bd->pdo->beginTransaction();

        // Se tem relacionamentos, apenas marca como inativo ou remove as referências
        if ($temRelacionamentos) {
            // Atualiza as referências para NULL nas tabelas relacionadas
            $bd->pdo->prepare("UPDATE entradas_estoque SET id_fornecedor = NULL WHERE id_fornecedor = ?")->execute([$id]);
            $bd->pdo->prepare("UPDATE movimentacoes_estoque SET fornecedor_id = NULL WHERE fornecedor_id = ?")->execute([$id]);
        }

        // Salvar dados do fornecedor para o log antes de excluir
        $dadosAnteriores = json_encode($fornecedor, JSON_UNESCAPED_UNICODE);

        // Excluir o fornecedor
        $stmt = $bd->pdo->prepare("DELETE FROM fornecedores WHERE id_fornecedor = ?");
        $stmt->execute([$id]);

        // Registrar no log
        $stmt = $bd->pdo->prepare("
            INSERT INTO logs (usuario_id, acao, tabela, registro_id, dados_anteriores, detalhes, ip, user_agent, descricao)
            VALUES (?, 'DELETE', 'fornecedores', ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['usuario_id'],
            $id,
            $dadosAnteriores,
            'Fornecedor excluído: ' . $fornecedor['nome_empresa'],
            $_SERVER['REMOTE_ADDR'] ?? '::1',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            'Exclusão do fornecedor ' . $fornecedor['nome_empresa']
        ]);

        $bd->pdo->commit();

        $_SESSION['mensagem_sucesso'] = "Fornecedor '{$fornecedor['nome_empresa']}' excluído com sucesso.";
        header("Location: ../read/read_supplier.php");
        exit;

    } catch (Exception $e) {
        $bd->pdo->rollback();
        $_SESSION['mensagem_erro'] = "Erro ao excluir fornecedor: " . $e->getMessage();
    }
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

// Função para determinar se a página atual está ativa
function isActivePage($page)
{
    $current = basename($_SERVER['PHP_SELF']);
    return $current === $page ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excluir Fornecedor - Sistema de Estoque</title>
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

        /* Delete Section */
        .delete-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }

        /* Warning Header */
        .warning-header {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .warning-main-info {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }

        .warning-icon {
            width: 64px;
            height: 64px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }

        .warning-details h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .warning-details p {
            font-size: 1rem;
            opacity: 0.9;
        }

        /* Supplier Info Card */
        .supplier-info-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .supplier-info-card h3 {
            color: #1e293b;
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-row {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 12px;
            margin-bottom: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .info-label {
            font-weight: 500;
            color: #64748b;
            font-size: 0.9rem;
        }

        .info-value {
            color: #1e293b;
            font-weight: 500;
        }

        .info-value.empty {
            color: #94a3b8;
            font-style: italic;
        }

        /* Alert Boxes */
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .alert-warning {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
        }

        .alert-info {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }

        .alert-icon {
            font-size: 1.2rem;
            margin-top: 2px;
        }

        .alert-content h4 {
            font-weight: 600;
            margin-bottom: 4px;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
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

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        /* Confirmation Form */
        .confirmation-form {
            background: #fef2f2;
            border: 2px solid #ef4444;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .confirmation-checkbox {
            margin-bottom: 20px;
        }

        .confirmation-checkbox label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            color: #991b1b;
            cursor: pointer;
        }

        .confirmation-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #ef4444;
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

            .warning-main-info {
                flex-direction: column;
                text-align: center;
            }

            .info-row {
                grid-template-columns: 1fr;
                gap: 4px;
            }

            .form-actions {
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
                    <h1>Excluir Fornecedor</h1>
                    <p class="header-subtitle">Confirme a exclusão do fornecedor do sistema</p>
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

                    <a href="../logout.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                        Sair
                    </a>
                </div>
            </div>

            <!-- Warning Header -->
            <div class="warning-header">
                <div class="warning-main-info">
                    <div class="warning-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="warning-details">
                        <h1>Atenção!</h1>
                        <p>Você está prestes a excluir permanentemente o fornecedor do sistema</p>
                    </div>
                </div>
            </div>

            <!-- Mensagens -->
            <?php if (isset($_SESSION['mensagem_erro'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle alert-icon"></i>
                    <div class="alert-content">
                        <h4>Erro!</h4>
                        <p><?= htmlspecialchars($_SESSION['mensagem_erro']) ?></p>
                    </div>
                </div>
                <?php unset($_SESSION['mensagem_erro']); ?>
            <?php endif; ?>

            <!-- Supplier Information -->
            <div class="supplier-info-card">
                <h3>
                    <i class="fas fa-building"></i>
                    Dados do Fornecedor a ser Excluído
                </h3>

                <div class="info-row">
                    <span class="info-label">Nome da Empresa:</span>
                    <span class="info-value"><?= htmlspecialchars($fornecedor['nome_empresa']) ?></span>
                </div>

                <div class="info-row">
                    <span class="info-label">CNPJ:</span>
                    <span class="info-value <?= empty($fornecedor['cnpj']) ? 'empty' : '' ?>">
                        <?= formatarCNPJ($fornecedor['cnpj']) ?>
                    </span>
                </div>

                <div class="info-row">
                    <span class="info-label">Atividade:</span>
                    <span class="info-value <?= empty($fornecedor['atividade']) ? 'empty' : '' ?>">
                        <?= !empty($fornecedor['atividade']) ? htmlspecialchars($fornecedor['atividade']) : 'Não informado' ?>
                    </span>
                </div>

                <div class="info-row">
                    <span class="info-label">Representante:</span>
                    <span class="info-value <?= empty($fornecedor['nome_representante']) ? 'empty' : '' ?>">
                        <?= !empty($fornecedor['nome_representante']) ? htmlspecialchars($fornecedor['nome_representante']) : 'Não informado' ?>
                    </span>
                </div>

                <div class="info-row">
                    <span class="info-label">Cadastrado em:</span>
                    <span class="info-value">
                        <?= date('d/m/Y \à\s H:i', strtotime($fornecedor['criado_em'])) ?>
                    </span>
                </div>
            </div>

            <!-- Warnings -->
            <?php if ($temRelacionamentos): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle alert-icon"></i>
                    <div class="alert-content">
                        <h4>Atenção - Fornecedor com Relacionamentos</h4>
                        <p>Este fornecedor possui movimentações de estoque ou entradas registradas no sistema. Ao excluí-lo, essas referências serão removidas automaticamente.</p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle alert-icon"></i>
                <div class="alert-content">
                    <h4>Esta ação é irreversível!</h4>
                    <p>Uma vez excluído, todos os dados do fornecedor serão permanentemente removidos do sistema e não poderão ser recuperados.</p>
                </div>
            </div>

            <!-- Confirmation Form -->
            <div class="delete-section">
                <form method="POST" id="deleteForm">
                    <div class="confirmation-form">
                        <div class="confirmation-checkbox">
                            <label>
                                <input type="checkbox" id="confirmDelete" name="confirm_delete" required>
                                <span>Eu entendo que esta ação é irreversível e desejo excluir permanentemente o fornecedor <strong><?= htmlspecialchars($fornecedor['nome_empresa']) ?></strong></span>
                            </label>
                        </div>

                        <div class="confirmation-checkbox">
                            <label>
                                <input type="checkbox" id="confirmResponsibility" name="confirm_responsibility" required>
                                <span>Eu assumo total responsabilidade por esta exclusão</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="../read/read_supplier.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </a>
                        <a href="../read/visualizar.php?id=<?= $fornecedor['id_fornecedor'] ?>" class="btn btn-secondary">
                            <i class="fas fa-eye"></i>
                            Ver Detalhes
                        </a>
                        <button type="submit" name="confirmar_exclusao" class="btn btn-danger" id="deleteButton" disabled>
                            <i class="fas fa-trash"></i>
                            Confirmar Exclusão
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Controla a habilitação do botão de exclusão
        document.addEventListener('DOMContentLoaded', function() {
            const confirmDelete = document.getElementById('confirmDelete');
            const confirmResponsibility = document.getElementById('confirmResponsibility');
            const deleteButton = document.getElementById('deleteButton');

            function updateButtonState() {
                const canDelete = confirmDelete.checked && confirmResponsibility.checked;
                deleteButton.disabled = !canDelete;
                
                if (canDelete) {
                    deleteButton.style.opacity = '1';
                    deleteButton.style.cursor = 'pointer';
                } else {
                    deleteButton.style.opacity = '0.6';
                    deleteButton.style.cursor = 'not-allowed';
                }
            }

            confirmDelete.addEventListener('change', updateButtonState);
            confirmResponsibility.addEventListener('change', updateButtonState);

            // Confirmação adicional no submit
            document.getElementById('deleteForm').addEventListener('submit', function(e) {
                if (!confirmDelete.checked || !confirmResponsibility.checked) {
                    e.preventDefault();
                    alert('Por favor, confirme ambas as opções antes de prosseguir.');
                    return false;
                }

                const supplierName = '<?= addslashes($fornecedor['nome_empresa']) ?>';
                const confirmMessage = `CONFIRMAÇÃO FINAL\n\nVocê tem CERTEZA ABSOLUTA que deseja excluir o fornecedor "${supplierName}"?\n\nEsta ação é IRREVERSÍVEL e removerá permanentemente:\n- Todos os dados do fornecedor\n- Referências em movimentações de estoque\n- Histórico de entradas\n\nDigite "EXCLUIR" (sem aspas) para confirmar:`;
                
                const userConfirmation = prompt(confirmMessage);
                
                if (userConfirmation !== 'EXCLUIR') {
                    e.preventDefault();
                    alert('Exclusão cancelada. Para confirmar, você deve digitar exatamente "EXCLUIR".');
                    return false;
                }

                // Último aviso
                if (!confirm('ÚLTIMA CONFIRMAÇÃO!\n\nEsta é sua última chance de cancelar.\n\nTem certeza que deseja prosseguir com a exclusão?')) {
                    e.preventDefault();
                    return false;
                }

                return true;
            });

            // Animação de entrada suave
            const cards = document.querySelectorAll('.supplier-info-card, .alert, .delete-section');
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

        // Previne o fechamento acidental da página
        window.addEventListener('beforeunload', function(e) {
            const confirmDelete = document.getElementById('confirmDelete');
            const confirmResponsibility = document.getElementById('confirmResponsibility');
            
            if (confirmDelete.checked || confirmResponsibility.checked) {
                e.preventDefault();
                e.returnValue = '';
                return 'Você tem alterações não salvas. Tem certeza que deseja sair?';
            }
        });

        // Remove o aviso ao submeter o formulário
        document.getElementById('deleteForm').addEventListener('submit', function() {
            window.removeEventListener('beforeunload', arguments.callee);
        });
    </script>
</body>

</html>