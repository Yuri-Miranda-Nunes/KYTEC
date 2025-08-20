<?php
session_start();

// Verifica se está logado e tem permissão
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php");
    exit;
}

if (!in_array('gerenciar_usuarios', $_SESSION['permissoes'] ?? [])) {
    echo "Acesso negado.";
    exit;
}

// Função para verificar permissões
function temPermissao($permissao)
{
    return in_array($permissao, $_SESSION['permissoes'] ?? []);
}

require_once '../conexao.php';

// Definir permissões por perfil
$permissoes_por_perfil = [
    'admin' => ['listar_produtos', 'cadastrar_produtos', 'editar_produtos', 'excluir_produtos', 'gerenciar_usuarios'],
    'estoquista' => ['listar_produtos', 'cadastrar_produtos', 'editar_produtos', 'excluir_produtos'],
    'visualizador' => ['listar_produtos']
];

$permissoes_descricoes = [
    'listar_produtos' => 'Visualizar lista de produtos',
    'cadastrar_produtos' => 'Cadastrar novos produtos',
    'editar_produtos' => 'Editar produtos existentes',
    'excluir_produtos' => 'Excluir produtos',
    'gerenciar_usuarios' => 'Gerenciar usuários do sistema'
];

// Processar formulário quando enviado
$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $bd = new BancoDeDados();

        // Validar campos obrigatórios
        $matricula = trim($_POST['matricula'] ?? '');
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $departamento = trim($_POST['departamento'] ?? '');
        $cargo = trim($_POST['cargo'] ?? '');
        $data_admissao = $_POST['data_admissao'] ?? '';
        $senha = $_POST['senha'] ?? '';
        $confirmar_senha = $_POST['confirmar_senha'] ?? '';
        $perfil = $_POST['perfil'] ?? '';
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if (empty($matricula)) {
            throw new Exception("Matrícula é obrigatória.");
        }

        if (!is_numeric($matricula) || $matricula <= 0) {
            throw new Exception("Matrícula deve ser um número válido.");
        }

        if (empty($nome)) {
            throw new Exception("Nome do usuário é obrigatório.");
        }

        if (empty($email)) {
            throw new Exception("Email é obrigatório.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email inválido.");
        }

        if (empty($senha)) {
            throw new Exception("Senha é obrigatória.");
        }

        if (strlen($senha) < 6) {
            throw new Exception("A senha deve ter pelo menos 6 caracteres.");
        }

        if ($senha !== $confirmar_senha) {
            throw new Exception("As senhas não coincidem.");
        }

        if (empty($perfil)) {
            throw new Exception("Perfil do usuário é obrigatório.");
        }

        // Validar data de admissão se fornecida
        if (!empty($data_admissao)) {
            $date = DateTime::createFromFormat('Y-m-d', $data_admissao);
            if (!$date || $date->format('Y-m-d') !== $data_admissao) {
                throw new Exception("Data de admissão inválida.");
            }
        }

        // Verificar se matrícula já existe
        $stmt_check_matricula = $bd->pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE matricula = ?");
        $stmt_check_matricula->execute([$matricula]);
        if ($stmt_check_matricula->fetchColumn() > 0) {
            throw new Exception("Esta matrícula já está cadastrada.");
        }

        // Verificar se email já existe
        $stmt_check_email = $bd->pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
        $stmt_check_email->execute([$email]);
        if ($stmt_check_email->fetchColumn() > 0) {
            throw new Exception("Este email já está cadastrado.");
        }

        // Inicia transação
        $bd->pdo->beginTransaction();

        try {
            // Hash da senha
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

            // Preparar valores para inserção (NULL para campos vazios)
            $telefone = !empty($telefone) ? $telefone : null;
            $departamento = !empty($departamento) ? $departamento : null;
            $cargo = !empty($cargo) ? $cargo : null;
            $data_admissao = !empty($data_admissao) ? $data_admissao : null;

            // Inserir usuário
            $sql = "INSERT INTO usuarios (matricula, nome, email, telefone, departamento, cargo, data_admissao, senha, perfil, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $bd->pdo->prepare($sql);
            $stmt->execute([
                $matricula,
                $nome,
                $email,
                $telefone,
                $departamento,
                $cargo,
                $data_admissao,
                $senha_hash,
                $perfil,
                $ativo
            ]);

            $usuario_id = $bd->pdo->lastInsertId();

            // Inserir permissões baseadas no perfil
            if (isset($permissoes_por_perfil[$perfil])) {
                $permissoes = $permissoes_por_perfil[$perfil];

                // Buscar IDs das permissões
                $placeholders = str_repeat('?,', count($permissoes) - 1) . '?';
                $stmt_perm_ids = $bd->pdo->prepare("SELECT id FROM permissoes WHERE nome_permissao IN ($placeholders)");
                $stmt_perm_ids->execute($permissoes);
                $permissoes_ids = $stmt_perm_ids->fetchAll(PDO::FETCH_COLUMN);

                // Inserir na tabela usuario_permissoes
                $stmt_user_perm = $bd->pdo->prepare("INSERT INTO usuario_permissoes (usuario_id, permissao_id) VALUES (?, ?)");
                foreach ($permissoes_ids as $permissao_id) {
                    $stmt_user_perm->execute([$usuario_id, $permissao_id]);
                }
            }

            // Confirma a transação
            $bd->pdo->commit();

            // Salva a mensagem na sessão
            $_SESSION['mensagem_sucesso'] = "Usuário cadastrado com sucesso!";

            // Redireciona para a listagem
            header("Location: ../read/read_user.php");
            exit;

        } catch (Exception $e) {
            $bd->pdo->rollBack();
            throw $e;
        }
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
    <title>Cadastrar Usuário - Sistema de Estoque</title>
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
        .form-select {
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: white;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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

        /* Perfil Info Box */
        .perfil-info {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-top: 12px;
        }

        .perfil-info h4 {
            color: #1e293b;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .perfil-info p {
            color: #64748b;
            font-size: 0.8rem;
            margin-bottom: 8px;
        }

        .permissoes-list {
            list-style: none;
            padding: 0;
        }

        .permissoes-list li {
            color: #059669;
            font-size: 0.75rem;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .permissoes-list li:before {
            content: "✓";
            font-weight: bold;
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
                    <h1>Cadastrar Usuário</h1>
                    <p class="header-subtitle">Adicione um novo usuário ao sistema</p>
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

            <!-- Form Section -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-user-plus"></i>
                    Informações do Usuário
                </h2>

                <form method="POST" action="">
                    <div class="form-grid">
                        <!-- Matrícula -->
                        <div class="form-group">
                            <label class="form-label" for="matricula">
                                Matrícula <span class="required">*</span>
                            </label>
                            <input type="number"
                                id="matricula"
                                name="matricula"
                                class="form-input"
                                value="<?= htmlspecialchars($_POST['matricula'] ?? '') ?>"
                                required
                                min="1"
                                placeholder="Ex: 123456">
                            <div class="input-hint">Número único de identificação do funcionário</div>
                        </div>

                        <!-- Nome -->
                        <div class="form-group">
                            <label class="form-label" for="nome">
                                Nome Completo <span class="required">*</span>
                            </label>
                            <input type="text"
                                id="nome"
                                name="nome"
                                class="form-input"
                                value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>"
                                required
                                placeholder="Ex: João Silva Santos">
                            <div class="input-hint">Nome completo do usuário</div>
                        </div>

                        <!-- Email -->
                        <div class="form-group">
                            <label class="form-label" for="email">
                                Email <span class="required">*</span>
                            </label>
                            <input type="email"
                                id="email"
                                name="email"
                                class="form-input"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                required
                                placeholder="Ex: joao@fabrica.com">
                            <div class="input-hint">Email único para login no sistema</div>
                        </div>

                        <!-- Telefone -->
                        <div class="form-group">
                            <label class="form-label" for="telefone">
                                Telefone
                            </label>
                            <input type="tel"
                                id="telefone"
                                name="telefone"
                                class="form-input"
                                value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>"
                                placeholder="Ex: (11) 99999-9999">
                            <div class="input-hint">Telefone de contato do funcionário</div>
                        </div>

                        <!-- Departamento -->
                        <div class="form-group">
                            <label class="form-label" for="departamento">
                                Departamento
                            </label>
                            <select id="departamento" name="departamento" class="form-select">
                                <option value="">Selecione um departamento</option>
                                <option value="administracao" <?= ($_POST['departamento'] ?? '') === 'administracao' ? 'selected' : '' ?>>
                                    Administração
                                </option>
                                <option value="vendas" <?= ($_POST['departamento'] ?? '') === 'vendas' ? 'selected' : '' ?>>
                                    Vendas
                                </option>
                                <option value="compras" <?= ($_POST['departamento'] ?? '') === 'compras' ? 'selected' : '' ?>>
                                    Compras
                                </option>
                                <option value="estoque" <?= ($_POST['departamento'] ?? '') === 'estoque' ? 'selected' : '' ?>>
                                    Estoque
                                </option>
                                <option value="producao" <?= ($_POST['departamento'] ?? '') === 'producao' ? 'selected' : '' ?>>
                                    Produção
                                </option>
                                <option value="ti" <?= ($_POST['departamento'] ?? '') === 'ti' ? 'selected' : '' ?>>
                                    T.I.
                                </option>
                                <option value="rh" <?= ($_POST['departamento'] ?? '') === 'rh' ? 'selected' : '' ?>>
                                    Recursos Humanos
                                </option>
                                <option value="financeiro" <?= ($_POST['departamento'] ?? '') === 'financeiro' ? 'selected' : '' ?>>
                                    Financeiro
                                </option>
                            </select>
                            <div class="input-hint">Departamento onde o funcionário trabalha</div>
                        </div>

                        <!-- Cargo -->
                        <div class="form-group">
                            <label class="form-label" for="cargo">
                                Cargo
                            </label>
                            <input type="text"
                                id="cargo"
                                name="cargo"
                                class="form-input"
                                value="<?= htmlspecialchars($_POST['cargo'] ?? '') ?>"
                                placeholder="Ex: Analista de Sistemas">
                            <div class="input-hint">Cargo ou função do funcionário</div>
                        </div>

                        <!-- Data de Admissão -->
                        <div class="form-group">
                            <label class="form-label" for="data_admissao">
                                Data de Admissão
                            </label>
                            <input type="date"
                                id="data_admissao"
                                name="data_admissao"
                                class="form-input"
                                value="<?= htmlspecialchars($_POST['data_admissao'] ?? '') ?>">
                            <div class="input-hint">Data de admissão na empresa</div>
                        </div>

                        <!-- Senha -->
                        <div class="form-group">
                            <label class="form-label" for="senha">
                                Senha <span class="required">*</span>
                            </label>
                            <input type="password"
                                id="senha"
                                name="senha"
                                class="form-input"
                                required
                                minlength="6"
                                placeholder="Mínimo 6 caracteres">
                            <div class="input-hint">Senha deve ter pelo menos 6 caracteres</div>
                        </div>

                        <!-- Confirmar Senha -->
                        <div class="form-group">
                            <label class="form-label" for="confirmar_senha">
                                Confirmar Senha <span class="required">*</span>
                            </label>
                            <input type="password"
                                id="confirmar_senha"
                                name="confirmar_senha"
                                class="form-input"
                                required
                                minlength="6"
                                placeholder="Digite a senha novamente">
                            <div class="input-hint">Repita a senha para confirmação</div>
                        </div>

                        <!-- Perfil -->
                        <div class="form-group full-width">
                            <label class="form-label" for="perfil">
                                Perfil do Usuário <span class="required">*</span>
                            </label>
                            <select id="perfil" name="perfil" class="form-select" required>
                                <option value="">Selecione um perfil</option>
                                <option value="admin" <?= ($_POST['perfil'] ?? '') === 'admin' ? 'selected' : '' ?>>
                                    Administrador
                                </option>
                                <option value="estoquista" <?= ($_POST['perfil'] ?? '') === 'estoquista' ? 'selected' : '' ?>>
                                    Estoquista
                                </option>
                                <option value="visualizador" <?= ($_POST['perfil'] ?? '') === 'visualizador' ? 'selected' : '' ?>>
                                    Visualizador
                                </option>
                            </select>
                            <div class="input-hint">Escolha o nível de acesso do usuário</div>

                            <!-- Informações do Perfil -->
                            <div class="perfil-info" id="perfil-info" style="display: none;">
                                <div id="admin-info" style="display: none;">
                                    <h4><i class="fas fa-crown"></i> Administrador</h4>
                                    <p>Acesso completo ao sistema, pode gerenciar usuários</p>
                                    <ul class="permissoes-list">
                                        <li>Visualizar lista de produtos</li>
                                        <li>Cadastrar novos produtos</li>
                                        <li>Editar produtos existentes</li>
                                        <li>Excluir produtos</li>
                                        <li>Gerenciar usuários do sistema</li>
                                    </ul>
                                </div>

                                <div id="estoquista-info" style="display: none;">
                                    <h4><i class="fas fa-boxes"></i> Estoquista</h4>
                                    <p>Gerencia produtos e estoque</p>
                                    <ul class="permissoes-list">
                                        <li>Visualizar lista de produtos</li>
                                        <li>Cadastrar novos produtos</li>
                                        <li>Editar produtos existentes</li>
                                        <li>Excluir produtos</li>
                                    </ul>
                                </div>

                                <div id="visualizador-info" style="display: none;">
                                    <h4><i class="fas fa-eye"></i> Visualizador</h4>
                                    <p>Acesso limitado, apenas visualização de produtos</p>
                                    <ul class="permissoes-list">
                                        <li>Visualizar lista de produtos</li>
                                    </ul>
                                </div>
                            </div>
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
                                    Usuário ativo no sistema
                                </label>
                            </div>
                            <div class="input-hint">Usuários inativos não conseguem fazer login</div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <a href="../read/read_user.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Cadastrar Usuário
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Auto-focus no primeiro campo
        document.getElementById('matricula').focus();

        // Mostrar informações do perfil quando selecionado
        document.getElementById('perfil').addEventListener('change', function() {
            const perfilInfo = document.getElementById('perfil-info');
            const adminInfo = document.getElementById('admin-info');
            const estoquistaInfo = document.getElementById('estoquista-info');
            const visualizadorInfo = document.getElementById('visualizador-info');

            // Ocultar todas as informações
            adminInfo.style.display = 'none';
            estoquistaInfo.style.display = 'none';
            visualizadorInfo.style.display = 'none';

            if (this.value) {
                perfilInfo.style.display = 'block';

                switch (this.value) {
                    case 'admin':
                        adminInfo.style.display = 'block';
                        break;
                    case 'estoquista':
                        estoquistaInfo.style.display = 'block';
                        break;
                    case 'visualizador':
                        visualizadorInfo.style.display = 'block';
                        break;
                }
            } else {
                perfilInfo.style.display = 'none';
            }
        });

        // Validação de confirmação de senha
        document.getElementById('confirmar_senha').addEventListener('input', function() {
            const senha = document.getElementById('senha').value;
            const confirmarSenha = this.value;

            if (senha !== confirmarSenha) {
                this.setCustomValidity('As senhas não coincidem');
                this.style.borderColor = '#dc2626';
            } else {
                this.setCustomValidity('');
                this.style.borderColor = '';
            }
        });

        // Validação de matrícula (apenas números)
        document.getElementById('matricula').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });

        // Formatação do telefone
        document.getElementById('telefone').addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            
            if (value.length >= 11) {
                value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
            } else if (value.length >= 7) {
                value = value.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
            } else if (value.length >= 3) {
                value = value.replace(/(\d{2})(\d{0,5})/, '($1) $2');
            }
            
            this.value = value;
        });

        // Disparar o evento de mudança para exibir as permissões no carregamento
        window.addEventListener('DOMContentLoaded', function() {
            const perfilSelect = document.getElementById('perfil');
            const event = new Event('change');
            perfilSelect.dispatchEvent(event);
        });

        // Validação da data de admissão (não pode ser futura)
        document.getElementById('data_admissao').addEventListener('change', function() {
            const dataAdmissao = new Date(this.value);
            const hoje = new Date();
            
            if (dataAdmissao > hoje) {
                alert('A data de admissão não pode ser futura.');
                this.value = '';
            }
        });

        // Definir data máxima como hoje
        document.getElementById('data_admissao').max = new Date().toISOString().split('T')[0];
    </script>
</body>

</html>