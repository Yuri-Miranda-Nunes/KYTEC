<?php
session_start();

// Verifica se está logado e tem permissão
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php");
    exit;
}

if (!in_array('editar_produtos', $_SESSION['permissoes'] ?? [])) {
    echo "Acesso negado.";
    exit;
}

// Função para verificar permissões
function temPermissao($permissao)
{
    return in_array($permissao, $_SESSION['permissoes'] ?? []);
}

require_once '../conexao.php';

// Verificar se o ID foi fornecido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ../read/read_product.php");
    exit;
}

$produto_id = intval($_GET['id']);
$mensagem = '';
$tipo_mensagem = '';
$produto = null;

try {
    $bd = new BancoDeDados();

    // Buscar produto existente
    $stmt = $bd->pdo->prepare("SELECT * FROM produtos WHERE id_produto = ?");
    $stmt->execute([$produto_id]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        $mensagem = "Produto não encontrado.";
        $tipo_mensagem = "error";
    }
} catch (Exception $e) {
    $mensagem = "Erro ao carregar produto: " . $e->getMessage();
    $tipo_mensagem = "error";
}

// Processar formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $produto) {
    try {
        // Validar campos obrigatórios
        $nome = trim($_POST['nome'] ?? '');
        $codigo = trim($_POST['codigo'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $tipo = $_POST['tipo'] ?? 'acabado';
        $preco_unitario = floatval($_POST['preco_unitario'] ?? 0);
        $estoque_minimo = intval($_POST['estoque_minimo'] ?? 0);
        $estoque_atual = intval($_POST['estoque_atual'] ?? 0);
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if (empty($nome)) {
            throw new Exception("Nome do produto é obrigatório.");
        }

        if (empty($codigo)) {
            throw new Exception("Código do produto é obrigatório.");
        }

        // Verificar se código já existe para outro produto
        $stmt_check = $bd->pdo->prepare("SELECT COUNT(*) FROM produtos WHERE codigo = ? AND id_produto != ?");
        $stmt_check->execute([$codigo, $produto_id]);
        if ($stmt_check->fetchColumn() > 0) {
            throw new Exception("Código já existe para outro produto. Escolha outro código.");
        }

        // Atualizar produto
        $sql = "UPDATE produtos SET 
                nome = ?, 
                codigo = ?, 
                descricao = ?, 
                tipo = ?, 
                preco_unitario = ?, 
                estoque_minimo = ?, 
                estoque_atual = ?, 
                ativo = ?, 
                atualizado_em = NOW() 
                WHERE id_produto = ?";

        $stmt = $bd->pdo->prepare($sql);
        $stmt->execute([
            $nome,
            $codigo,
            $descricao,
            $tipo,
            $preco_unitario,
            $estoque_minimo,
            $estoque_atual,
            $ativo,
            $produto_id
        ]);

        $mensagem = "Produto atualizado com sucesso!";
        $tipo_mensagem = "success";
        header("Location: ../read/read_product.php");
        exit;


        // Recarregar dados do produto
        $stmt = $bd->pdo->prepare("SELECT * FROM produtos WHERE id_produto = ?");
        $stmt->execute([$produto_id]);
        $produto = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $mensagem = "Erro: " . $e->getMessage();
        $tipo_mensagem = "error";
    }
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
    <title>Editar Produto - Sistema de Estoque</title>
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

        /* Form Section */
        .form-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 32px;
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

        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .required {
            color: #ef4444;
        }

        .form-input,
        .form-select,
        .form-textarea {
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: white;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
        }

        .checkbox-input {
            width: 18px;
            height: 18px;
            accent-color: #3b82f6;
        }

        .checkbox-label {
            font-size: 0.875rem;
            color: #374151;
            cursor: pointer;
        }

        /* Buttons */
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

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
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
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

        /* Messages */
        .message {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .message.success {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }

        .message.error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        /* Input hints */
        .input-hint {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 4px;
        }

        /* Product Info */
        .product-info {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .product-info h3 {
            color: #1e293b;
            font-size: 1rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .product-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            font-size: 0.875rem;
        }

        .product-info-item {
            display: flex;
            flex-direction: column;
        }

        .product-info-label {
            color: #64748b;
            font-weight: 500;
            margin-bottom: 2px;
        }

        .product-info-value {
            color: #1e293b;
            font-weight: 600;
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

            .form-grid {
                grid-template-columns: 1fr;
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
                            <a href="../log/product_input_and_output_log.php" class="nav-link <?= isActivePage('product_input_and_output_log.php') ?>">
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
                    <h1>Editar Produto</h1>
                    <p class="header-subtitle">Altere as informações do produto selecionado</p>
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

                    <a href="logout.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                        Sair
                    </a>
                </div>

            </div>

            <!-- Messages -->
            <?php if (!empty($mensagem)): ?>
                <div class="message <?= $tipo_mensagem ?>">
                    <i class="fas fa-<?= $tipo_mensagem === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                    <?= htmlspecialchars($mensagem) ?>
                </div>
            <?php endif; ?>

            <?php if ($produto): ?>
                <!-- Product Info -->
                <div class="product-info">
                    <h3>
                        <i class="fas fa-info-circle"></i>
                        Informações Atuais do Produto
                    </h3>
                    <div class="product-info-grid">
                        <div class="product-info-item">
                            <span class="product-info-label">ID:</span>
                            <span class="product-info-value">#<?= $produto['id_produto'] ?></span>
                        </div>
                        <div class="product-info-item">
                            <span class="product-info-label">Criado em:</span>
                            <span class="product-info-value">
                                <?= $produto['criado_em'] ? date('d/m/Y H:i', strtotime($produto['criado_em'])) : 'N/A' ?>
                            </span>
                        </div>
                        <?php if ($produto['atualizado_em']): ?>
                            <div class="product-info-item">
                                <span class="product-info-label">Última atualização:</span>
                                <span class="product-info-value">
                                    <?= date('d/m/Y H:i', strtotime($produto['atualizado_em'])) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Form Section -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-edit"></i>
                        Editar Informações do Produto
                    </h2>

                    <form method="POST" action="">
                        <div class="form-grid">
                            <!-- Nome -->
                            <div class="form-group">
                                <label class="form-label" for="nome">
                                    Nome do Produto <span class="required">*</span>
                                </label>
                                <input type="text"
                                    id="nome"
                                    name="nome"
                                    class="form-input"
                                    value="<?= htmlspecialchars($produto['nome']) ?>"
                                    required
                                    placeholder="Ex: Mouse Gamer XYZ">
                                <div class="input-hint">Nome que identificará o produto no sistema</div>
                            </div>

                            <!-- Código -->
                            <div class="form-group">
                                <label class="form-label" for="codigo">
                                    Código do Produto <span class="required">*</span>
                                </label>
                                <input type="text"
                                    id="codigo"
                                    name="codigo"
                                    class="form-input"
                                    value="<?= htmlspecialchars($produto['codigo']) ?>"
                                    required
                                    placeholder="Ex: MGX001">
                                <div class="input-hint">Código único para identificação</div>
                            </div>

                            <!-- Tipo -->
                            <div class="form-group">
                                <label class="form-label" for="tipo">
                                    Tipo do Produto
                                </label>
                                <select id="tipo" name="tipo" class="form-select">
                                    <option value="acabado" <?= $produto['tipo'] === 'acabado' ? 'selected' : '' ?>>
                                        Produto Acabado
                                    </option>
                                    <option value="matéria-prima" <?= $produto['tipo'] === 'matéria-prima' ? 'selected' : '' ?>>
                                        Matéria-prima
                                    </option>
                                    <option value="outro" <?= $produto['tipo'] === 'outro' ? 'selected' : '' ?>>
                                        Outro
                                    </option>
                                </select>
                                <div class="input-hint">Categoria do tipo de produto</div>
                            </div>

                            <!-- Preço Unitário -->
                            <div class="form-group">
                                <label class="form-label" for="preco_unitario">
                                    Preço Unitário (R$)
                                </label>
                                <input type="number"
                                    id="preco_unitario"
                                    name="preco_unitario"
                                    class="form-input"
                                    step="0.01"
                                    min="0"
                                    value="<?= number_format($produto['preco_unitario'], 2, '.', '') ?>"
                                    placeholder="0,00">
                                <div class="input-hint">Preço de custo do produto</div>
                            </div>

                            <!-- Estoque Mínimo -->
                            <div class="form-group">
                                <label class="form-label" for="estoque_minimo">
                                    Estoque Mínimo
                                </label>
                                <input type="number"
                                    id="estoque_minimo"
                                    name="estoque_minimo"
                                    class="form-input"
                                    min="0"
                                    value="<?= $produto['estoque_minimo'] ?>"
                                    placeholder="0">
                                <div class="input-hint">Quantidade mínima em estoque antes do alerta</div>
                            </div>

                            <!-- Estoque Atual -->
                            <div class="form-group">
                                <label class="form-label" for="estoque_atual">
                                    Estoque Atual
                                </label>
                                <input type="number"
                                    id="estoque_atual"
                                    name="estoque_atual"
                                    class="form-input"
                                    min="0"
                                    value="<?= $produto['estoque_atual'] ?>"
                                    placeholder="0">
                                <div class="input-hint">Quantidade atual disponível em estoque</div>
                            </div>

                            <!-- Descrição -->
                            <div class="form-group full-width">
                                <label class="form-label" for="descricao">
                                    Descrição
                                </label>
                                <textarea id="descricao"
                                    name="descricao"
                                    class="form-textarea"
                                    placeholder="Descreva as características do produto..."><?= htmlspecialchars($produto['descricao']) ?></textarea>
                                <div class="input-hint">Informações adicionais sobre o produto</div>
                            </div>

                            <!-- Ativo -->
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <div class="checkbox-group">
                                    <input type="checkbox"
                                        id="ativo"
                                        name="ativo"
                                        class="checkbox-input"
                                        <?= $produto['ativo'] ? 'checked' : '' ?>>
                                    <label for="ativo" class="checkbox-label">
                                        Produto ativo no sistema
                                    </label>
                                </div>
                                <div class="input-hint">Produtos inativos não aparecem nas listagens</div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="form-actions">
                            <a href="../read/read_product.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Salvar Alterações
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Produto não encontrado -->
                <div class="form-section">
                    <div style="text-align: center; padding: 40px; color: #64748b;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 16px; color: #f59e0b;"></i>
                        <h3 style="margin-bottom: 8px; color: #374151;">Produto não encontrado</h3>
                        <p style="margin-bottom: 24px;">O produto solicitado não foi encontrado ou você não tem permissão para acessá-lo.</p>
                        <a href="../read/read_product.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i>
                            Voltar à Lista
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        <?php if ($produto): ?>
            // Auto-focus no primeiro campo
            document.getElementById('nome').focus();

            // Formatting para campos de preço
            document.getElementById('preco_unitario').addEventListener('input', function(e) {
                let value = e.target.value;
                if (value < 0) {
                    e.target.value = 0;
                }
            });

            // Validação de estoque
            document.getElementById('estoque_atual').addEventListener('input', function(e) {
                let value = e.target.value;
                if (value < 0) {
                    e.target.value = 0;
                }
            });

            document.getElementById('estoque_minimo').addEventListener('input', function(e) {
                let value = e.target.value;
                if (value < 0) {
                    e.target.value = 0;
                }
            });

            // Validação do formulário
            document.querySelector('form').addEventListener('submit', function(e) {
                const nome = document.getElementById('nome').value.trim();
                const codigo = document.getElementById('codigo').value.trim();

                if (!nome) {
                    alert('Nome do produto é obrigatório.');
                    e.preventDefault();
                    document.getElementById('nome').focus();
                    return;
                }

                if (!codigo) {
                    alert('Código do produto é obrigatório.');
                    e.preventDefault();
                    document.getElementById('codigo').focus();
                    return;
                }

                // Confirmação antes de salvar
                if (!confirm('Tem certeza que deseja salvar as alterações?')) {
                    e.preventDefault();
                    return;
                }
            });
        <?php endif; ?>
    </script>