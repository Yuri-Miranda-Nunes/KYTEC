<?php
session_start();

// Verifica se está logado e tem permissão
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php");
    exit;
}

if (!in_array('cadastrar_produtos', $_SESSION['permissoes'] ?? [])) {
    echo "Acesso negado.";
    exit;
}

// Função para verificar permissões
function temPermissao($permissao)
{
    return in_array($permissao, $_SESSION['permissoes'] ?? []);
}

require_once '../conexao.php';
require_once '../log\log_manager.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $bd = new BancoDeDados();
        
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

        // Verificar se código já existe
        $stmt_check = $bd->pdo->prepare("SELECT COUNT(*) FROM produtos WHERE codigo = ?");
        $stmt_check->execute([$codigo]);
        if ($stmt_check->fetchColumn() > 0) {
            throw new Exception("Código já existe. Escolha outro código.");
        }

        // Inserir produto
        $sql = "INSERT INTO produtos (nome, codigo, descricao, tipo, preco_unitario, estoque_minimo, estoque_atual, ativo, criado_em) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $bd->pdo->prepare($sql);
        $stmt->execute([
            $nome,
            $codigo,
            $descricao,
            $tipo,
            $preco_unitario,
            $estoque_minimo,
            $estoque_atual,
            $ativo
        ]);
        
        $mensagem = "Produto cadastrado com sucesso!";
        $tipo_mensagem = "success";
        
        // Limpar campos após sucesso
        $_POST = [];
        
    } catch (Exception $e) {
        $mensagem = "Erro: " . $e->getMessage();
        $tipo_mensagem = "error";
    }
}


?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Produto - Sistema de Estoque</title>
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

        /* Search Bar Styles */
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

        .search-result-item {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            transition: background 0.2s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .search-result-item:hover {
            background: #f8fafc;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            flex-shrink: 0;
        }

        .search-result-content {
            flex: 1;
            min-width: 0;
        }

        .search-result-title {
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-result-subtitle {
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 2px;
        }

        .search-result-description {
            font-size: 0.75rem;
            color: #94a3b8;
        }

        .search-result-badge {
            background: #fef3c7;
            color: #d97706;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.625rem;
            font-weight: 500;
        }

        .search-result-badge.badge-warning {
            background: #fef3c7;
            color: #d97706;
        }

        .search-result-badge.badge-danger {
            background: #fee2e2;
            color: #dc2626;
        }

        .search-no-results {
            padding: 24px;
            text-align: center;
            color: #64748b;
            font-size: 0.875rem;
        }

        .search-loading {
            padding: 16px;
            text-align: center;
            color: #64748b;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid #e2e8f0;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
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
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
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

        /* Color Schemes for search icons */
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
                                <a href="create_product.php" class="nav-link active">
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
                            <a href="create_user.php" class="nav-link">
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
                    <h1>Cadastrar Produto</h1>
                    <p class="header-subtitle">Adicione um novo produto ao estoque</p>
                </div>

                <!-- Search Bar -->
                <div class="search-container">
                    <div class="search-wrapper">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="searchInput" class="search-input" placeholder="Pesquisar produtos, fornecedores, usuários...">
                        <div id="searchResults" class="search-results"></div>
                    </div>
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

            <!-- Messages -->
            <?php if (!empty($mensagem)): ?>
                <div class="message <?= $tipo_mensagem ?>">
                    <i class="fas fa-<?= $tipo_mensagem === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                    <?= htmlspecialchars($mensagem) ?>
                </div>
            <?php endif; ?>

            <!-- Form Section -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-plus-circle"></i>
                    Informações do Produto
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
                                value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>"
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
                                value="<?= htmlspecialchars($_POST['codigo'] ?? '') ?>"
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
                                <option value="acabado" <?= ($_POST['tipo'] ?? '') === 'acabado' ? 'selected' : '' ?>>
                                    Produto Acabado
                                </option>
                                <option value="matéria-prima" <?= ($_POST['tipo'] ?? '') === 'matéria-prima' ? 'selected' : '' ?>>
                                    Matéria-prima
                                </option>
                                <option value="outro" <?= ($_POST['tipo'] ?? '') === 'outro' ? 'selected' : '' ?>>
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
                                value="<?= htmlspecialchars($_POST['preco_unitario'] ?? '') ?>"
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
                                value="<?= htmlspecialchars($_POST['estoque_minimo'] ?? '0') ?>"
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
                                value="<?= htmlspecialchars($_POST['estoque_atual'] ?? '0') ?>"
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
                                placeholder="Descreva as características do produto..."><?= htmlspecialchars($_POST['descricao'] ?? '') ?></textarea>
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
                                    <?= (isset($_POST['ativo']) || !isset($_POST['nome'])) ? 'checked' : '' ?>>
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
                            Cadastrar Produto
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Search functionality
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

            // Show loading
            showLoading();

            searchTimeout = setTimeout(() => {
                performSearch(query);
            }, 300);
        });

        // Hide results when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-container')) {
                hideSearchResults();
            }
        });

        // Handle keyboard navigation
        searchInput.addEventListener('keydown', function(e) {
            const items = searchResults.querySelectorAll('.search-result-item');
            const activeItem = searchResults.querySelector('.search-result-item.active');

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!activeItem) {
                    items[0]?.classList.add('active');
                } else {
                    activeItem.classList.remove('active');
                    const nextItem = activeItem.nextElementSibling;
                    if (nextItem && nextItem.classList.contains('search-result-item')) {
                        nextItem.classList.add('active');
                    } else {
                        items[0]?.classList.add('active');
                    }
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (!activeItem) {
                    items[items.length - 1]?.classList.add('active');
                } else {
                    activeItem.classList.remove('active');
                    const prevItem = activeItem.previousElementSibling;
                    if (prevItem && prevItem.classList.contains('search-result-item')) {
                        prevItem.classList.add('active');
                    } else {
                        items[items.length - 1]?.classList.add('active');
                    }
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const activeItem = searchResults.querySelector('.search-result-item.active');
                if (activeItem) {
                    activeItem.click();
                }
            } else if (e.key === 'Escape') {
                hideSearchResults();
                searchInput.blur();
            }
        });

        function performSearch(query) {
            fetch(`../class/class_search.php?q=${encodeURIComponent(query)}&from=create`)
                .then(response => {
                    // Verificar se a resposta está ok
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    // Verificar o content-type
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        // Se não for JSON, ler como texto para debug
                        return response.text().then(text => {
                            console.error('Resposta não é JSON:', text);
                            throw new Error('Resposta inválida do servidor');
                        });
                    }

                    return response.json();
                })
                .then(data => {
                    hideLoading();

                    // Verificar se há erro na resposta
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

            // Add click events
            searchResults.querySelectorAll('.search-result-item').forEach(item => {
                item.addEventListener('click', function() {
                    const url = this.getAttribute('data-url');
                    if (url && url !== '#') {
                        window.location.href = url;
                    }
                });

                // Add hover effect for keyboard navigation
                item.addEventListener('mouseenter', function() {
                    searchResults.querySelectorAll('.search-result-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
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
            searchResults.querySelectorAll('.search-result-item').forEach(item => {
                item.classList.remove('active');
            });
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
        });
    </script>
</body>

</html>