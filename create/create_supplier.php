<?php
session_start();

// Verifica se tem permissão para cadastrar produtos (assumindo que quem cadastra produtos também pode cadastrar fornecedores)
if (!in_array('cadastrar_produtos', $_SESSION['permissoes'])) {
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

$mensagemSucesso = '';
$mensagemErro = '';

// Função para limpar CNPJ (remover formatação)
function limparCNPJ($cnpj) {
    return preg_replace('/[^0-9]/', '', $cnpj);
}

// Função para validar CNPJ
function validarCNPJ($cnpj) {
    $cnpj = limparCNPJ($cnpj);
    
    if (strlen($cnpj) != 14) {
        return false;
    }
    
    // Elimina CNPJs inválidos conhecidos
    if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
        return false;
    }
    
    // Validação do primeiro dígito verificador
    $soma = 0;
    $peso = 5;
    for ($i = 0; $i < 12; $i++) {
        $soma += $cnpj[$i] * $peso;
        $peso = $peso == 2 ? 9 : $peso - 1;
    }
    $dv1 = ($soma % 11) < 2 ? 0 : 11 - ($soma % 11);
    
    if ($cnpj[12] != $dv1) {
        return false;
    }
    
    // Validação do segundo dígito verificador
    $soma = 0;
    $peso = 6;
    for ($i = 0; $i < 13; $i++) {
        $soma += $cnpj[$i] * $peso;
        $peso = $peso == 2 ? 9 : $peso - 1;
    }
    $dv2 = ($soma % 11) < 2 ? 0 : 11 - ($soma % 11);
    
    return $cnpj[13] == $dv2;
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validações básicas
        $cnpj = trim($_POST['cnpj'] ?? '');
        $cnpj_limpo = limparCNPJ($cnpj);
        $nome_empresa = trim($_POST['nome_empresa'] ?? '');
        $atividade = trim($_POST['atividade'] ?? '');
        $nome_representante = trim($_POST['nome_representante'] ?? '');
        $telefone_representante = trim($_POST['telefone_representante'] ?? '');
        $email_representante = trim($_POST['email_representante'] ?? '');
        
        // Dados de endereço
        $cep = trim($_POST['cep'] ?? '');
        $logradouro = trim($_POST['logradouro'] ?? '');
        $numero = trim($_POST['numero'] ?? '');
        $complemento = trim($_POST['complemento'] ?? '');
        $bairro = trim($_POST['bairro'] ?? '');
        $cidade = trim($_POST['cidade'] ?? '');
        $estado = trim($_POST['estado'] ?? '');

        // Validações obrigatórias
        if (empty($nome_empresa)) {
            throw new Exception("Nome da empresa é obrigatório.");
        }

        if (empty($cnpj_limpo)) {
            throw new Exception("CNPJ é obrigatório.");
        }

        if (!validarCNPJ($cnpj_limpo)) {
            throw new Exception("CNPJ inválido. Verifique os números digitados.");
        }

        if (empty($nome_representante)) {
            throw new Exception("Nome do representante é obrigatório.");
        }

        // Validar email se fornecido
        if (!empty($email_representante) && !filter_var($email_representante, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("E-mail inválido.");
        }

        // Validar CEP se fornecido
        if (!empty($cep) && !preg_match('/^\d{5}-?\d{3}$/', $cep)) {
            throw new Exception("CEP inválido. Use o formato 00000-000.");
        }

        // Verifica se CNPJ já existe
        $stmt = $bd->pdo->prepare("SELECT id_fornecedor FROM fornecedores WHERE cnpj = ?");
        $stmt->execute([$cnpj]); // Salvar com formatação
        if ($stmt->fetch()) {
            throw new Exception("CNPJ já cadastrado no sistema.");
        }

        // Inserir fornecedor
        $sql = "INSERT INTO fornecedores (
                    cnpj, nome_empresa, atividade, nome_representante, 
                    telefone_representante, email_representante, 
                    cep, logradouro, numero, complemento, bairro, cidade, estado,
                    criado_em, atualizado_em
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $stmt = $bd->pdo->prepare($sql);
        $stmt->execute([
            $cnpj, // Salvar com formatação
            $nome_empresa,
            $atividade,
            $nome_representante,
            $telefone_representante,
            $email_representante,
            $cep,
            $logradouro,
            $numero,
            $complemento,
            $bairro,
            $cidade,
            $estado
        ]);

        $fornecedorId = $bd->pdo->lastInsertId();

        // Log da ação
        $sqlLog = "INSERT INTO logs (usuario_id, acao, tabela, registro_id, dados_novos, detalhes, ip, user_agent, criado_em, descricao) 
                   VALUES (?, 'CREATE', 'fornecedores', ?, ?, ?, ?, ?, NOW(), ?)";

        $dadosNovos = json_encode([
            'cnpj' => $cnpj,
            'nome_empresa' => $nome_empresa,
            'atividade' => $atividade,
            'nome_representante' => $nome_representante,
            'telefone_representante' => $telefone_representante,
            'email_representante' => $email_representante,
            'endereco_completo' => implode(', ', array_filter([$logradouro, $numero, $complemento, $bairro, $cidade, $estado, $cep]))
        ]);

        $stmtLog = $bd->pdo->prepare($sqlLog);
        $stmtLog->execute([
            $_SESSION['usuario_id'],
            $fornecedorId,
            $dadosNovos,
            'Fornecedor cadastrado via formulário.',
            $_SERVER['REMOTE_ADDR'] ?? '::1',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'Cadastro de fornecedor - ' . $nome_empresa
        ]);

        $mensagemSucesso = "Fornecedor '{$nome_empresa}' cadastrado com sucesso!";

        // Limpar campos após sucesso
        $_POST = [];
    } catch (Exception $e) {
        $mensagemErro = $e->getMessage();
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
    <title>Cadastrar Fornecedor - Sistema de Estoque</title>
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
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 32px;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group.half-width {
            grid-column: span 1;
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
            border: 2px solid #e2e8f0;
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

        .form-help {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 4px;
        }

        /* Input com ícone */
        .input-icon {
            position: relative;
        }

        .input-icon input,
        .input-icon select {
            padding-left: 40px;
        }

        .input-icon i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
        }

        /* Seção de endereço */
        .address-grid {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .address-full-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        /* Action Buttons */
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-start;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
            text-decoration: none;
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
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
        }

        /* Messages */
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        /* Info Card */
        .info-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .info-card h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-card p {
            font-size: 0.875rem;
            color: #64748b;
            line-height: 1.5;
        }

        /* Estados brasileiros select */
        .estado-select {
            text-transform: uppercase;
        }

        /* Validation styles */
        .form-input.valid {
            border-color: #10b981;
            background-color: #f0fdf4;
        }

        .form-input.invalid {
            border-color: #ef4444;
            background-color: #fef2f2;
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
            .form-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .address-grid,
            .address-full-row {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .header {
                flex-direction: column;
                gap: 16px;
                text-align: center;
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
                <?php if (temPermissao('cadastrar_produtos')): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">Fornecedores</div>
                        <div class="nav-item">
                            <a href="../read/read_supplier.php" class="nav-link <?= isActivePage('read_supplier.php') ?>">
                                <i class="fas fa-truck"></i>
                                <span>Listar Fornecedores</span>
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="../create/create_supplier.php" class="nav-link <?= isActivePage('create_supplier.php') ?>">
                                <i class="fas fa-plus"></i>
                                <span>Cadastrar Fornecedor</span>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Logs -->
                <?php if (temPermissao('cadastrar_produtos')): ?>
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
                    <h1>Cadastrar Fornecedor</h1>
                    <p class="header-subtitle">Adicione um novo fornecedor ao sistema</p>
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

            <!-- Messages -->
            <?php if (!empty($mensagemSucesso)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($mensagemSucesso) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($mensagemErro)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($mensagemErro) ?>
                </div>
            <?php endif; ?>

            <!-- Info Card -->
            <div class="info-card">
                <h3><i class="fas fa-info-circle"></i> Informações sobre Fornecedores</h3>
                <p>Cadastre os dados completos do fornecedor incluindo informações da empresa e do representante comercial.
                    Todos os campos marcados com asterisco (*) são obrigatórios.</p>
            </div>

            <!-- Form Section -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-truck"></i>
                    Dados do Fornecedor
                </h2>

                <form method="POST" id="supplierForm">
                    <!-- Dados da Empresa -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="cnpj" class="form-label">
                                CNPJ <span class="required">*</span>
                            </label>
                            <div class="input-icon">
                                <i class="fas fa-building"></i>
                                <input type="text"
                                    id="cnpj"
                                    name="cnpj"
                                    class="form-input"
                                    placeholder="00.000.000/0000-00"
                                    value="<?= htmlspecialchars($_POST['cnpj'] ?? '') ?>"
                                    required
                                    maxlength="18">
                            </div>
                            <div class="form-help">Digite os números do CNPJ, a formatação será aplicada automaticamente</div>
                        </div>

                        <div class="form-group">
                            <label for="nome_empresa" class="form-label">
                                Nome da Empresa <span class="required">*</span>
                            </label>
                            <div class="input-icon">
                                <i class="fas fa-building"></i>
                                <input type="text"
                                    id="nome_empresa"
                                    name="nome_empresa"
                                    class="form-input"
                                    placeholder="Ex: Empresa ABC Ltda"
                                    value="<?= htmlspecialchars($_POST['nome_empresa'] ?? '') ?>"
                                    required
                                    maxlength="150">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="atividade" class="form-label">
                                Ramo de Atividade
                            </label>
                            <div class="input-icon">
                                <i class="fas fa-industry"></i>
                                <input type="text"
                                    id="atividade"
                                    name="atividade"
                                    class="form-input"
                                    placeholder="Ex: Materiais de Construção"
                                    value="<?= htmlspecialchars($_POST['atividade'] ?? '') ?>"
                                    maxlength="100">
                            </div>
                        </div>
                    </div>

                    <!-- Dados do Representante -->
                    <h3 style="margin: 32px 0 24px 0; color: #374151; font-size: 1.125rem; font-weight: 600;">
                        <i class="fas fa-user-tie"></i> Dados do Representante
                    </h3>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nome_representante" class="form-label">
                                Nome do Representante <span class="required">*</span>
                            </label>
                            <div class="input-icon">
                                <i class="fas fa-user"></i>
                                <input type="text"
                                    id="nome_representante"
                                    name="nome_representante"
                                    class="form-input"
                                    placeholder="Nome completo"
                                    value="<?= htmlspecialchars($_POST['nome_representante'] ?? '') ?>"
                                    required
                                    maxlength="100">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="telefone_representante" class="form-label">
                                Telefone do Representante
                            </label>
                            <div class="input-icon">
                                <i class="fas fa-phone"></i>
                                <input type="text"
                                    id="telefone_representante"
                                    name="telefone_representante"
                                    class="form-input"
                                    placeholder="(11) 99999-9999"
                                    value="<?= htmlspecialchars($_POST['telefone_representante'] ?? '') ?>"
                                    maxlength="20">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email_representante" class="form-label">
                                E-mail do Representante
                            </label>
                            <div class="input-icon">
                                <i class="fas fa-envelope"></i>
                                <input type="email"
                                    id="email_representante"
                                    name="email_representante"
                                    class="form-input"
                                    placeholder="representante@empresa.com"
                                    value="<?= htmlspecialchars($_POST['email_representante'] ?? '') ?>"
                                    maxlength="100">
                            </div>
                        </div>
                    </div>

                    <!-- Endereço -->
                    <h3 style="margin: 32px 0 24px 0; color: #374151; font-size: 1.125rem; font-weight: 600;">
                        <i class="fas fa-map-marker-alt"></i> Endereço
                    </h3>

                    <div class="address-grid">
                        <div class="form-group">
                            <label for="cep" class="form-label">CEP</label>
                            <div class="input-icon">
                                <i class="fas fa-mail-bulk"></i>
                                <input type="text"
                                    id="cep"
                                    name="cep"
                                    class="form-input"
                                    placeholder="00000-000"
                                    value="<?= htmlspecialchars($_POST['cep'] ?? '') ?>"
                                    maxlength="10">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="logradouro" class="form-label">Logradouro</label>
                            <div class="input-icon">
                                <i class="fas fa-road"></i>
                                <input type="text"
                                    id="logradouro"
                                    name="logradouro"
                                    class="form-input"
                                    placeholder="Rua, Avenida, etc."
                                    value="<?= htmlspecialchars($_POST['logradouro'] ?? '') ?>"
                                    maxlength="200">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="numero" class="form-label">Número</label>
                            <div class="input-icon">
                                <i class="fas fa-hashtag"></i>
                                <input type="text"
                                    id="numero"
                                    name="numero"
                                    class="form-input"
                                    placeholder="123"
                                    value="<?= htmlspecialchars($_POST['numero'] ?? '') ?>"
                                    maxlength="20">
                            </div>
                        </div>
                    </div>

                    <div class="address-full-row">
                        <div class="form-group">
                            <label for="complemento" class="form-label">Complemento</label>
                            <div class="input-icon">
                                <i class="fas fa-plus-circle"></i>
                                <input type="text"
                                    id="complemento"
                                    name="complemento"
                                    class="form-input"
                                    placeholder="Apartamento, Sala, etc."
                                    value="<?= htmlspecialchars($_POST['complemento'] ?? '') ?>"
                                    maxlength="100">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="bairro" class="form-label">Bairro</label>
                            <div class="input-icon">
                                <i class="fas fa-map"></i>
                                <input type="text"
                                    id="bairro"
                                    name="bairro"
                                    class="form-input"
                                    placeholder="Nome do bairro"
                                    value="<?= htmlspecialchars($_POST['bairro'] ?? '') ?>"
                                    maxlength="100">
                            </div>
                        </div>
                    </div>

                    <div class="address-full-row">
                        <div class="form-group">
                            <label for="cidade" class="form-label">Cidade</label>
                            <div class="input-icon">
                                <i class="fas fa-city"></i>
                                <input type="text"
                                    id="cidade"
                                    name="cidade"
                                    class="form-input"
                                    placeholder="Nome da cidade"
                                    value="<?= htmlspecialchars($_POST['cidade'] ?? '') ?>"
                                    maxlength="100">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="estado" class="form-label">Estado</label>
                            <div class="input-icon">
                                <i class="fas fa-flag"></i>
                                <select id="estado" name="estado" class="form-input estado-select">
                                    <option value="">Selecione o estado</option>
                                    <option value="AC" <?= ($_POST['estado'] ?? '') === 'AC' ? 'selected' : '' ?>>Acre</option>
                                    <option value="AL" <?= ($_POST['estado'] ?? '') === 'AL' ? 'selected' : '' ?>>Alagoas</option>
                                    <option value="AP" <?= ($_POST['estado'] ?? '') === 'AP' ? 'selected' : '' ?>>Amapá</option>
                                    <option value="AM" <?= ($_POST['estado'] ?? '') === 'AM' ? 'selected' : '' ?>>Amazonas</option>
                                    <option value="BA" <?= ($_POST['estado'] ?? '') === 'BA' ? 'selected' : '' ?>>Bahia</option>
                                    <option value="CE" <?= ($_POST['estado'] ?? '') === 'CE' ? 'selected' : '' ?>>Ceará</option>
                                    <option value="DF" <?= ($_POST['estado'] ?? '') === 'DF' ? 'selected' : '' ?>>Distrito Federal</option>
                                    <option value="ES" <?= ($_POST['estado'] ?? '') === 'ES' ? 'selected' : '' ?>>Espírito Santo</option>
                                    <option value="GO" <?= ($_POST['estado'] ?? '') === 'GO' ? 'selected' : '' ?>>Goiás</option>
                                    <option value="MA" <?= ($_POST['estado'] ?? '') === 'MA' ? 'selected' : '' ?>>Maranhão</option>
                                    <option value="MT" <?= ($_POST['estado'] ?? '') === 'MT' ? 'selected' : '' ?>>Mato Grosso</option>
                                    <option value="MS" <?= ($_POST['estado'] ?? '') === 'MS' ? 'selected' : '' ?>>Mato Grosso do Sul</option>
                                    <option value="MG" <?= ($_POST['estado'] ?? '') === 'MG' ? 'selected' : '' ?>>Minas Gerais</option>
                                    <option value="PA" <?= ($_POST['estado'] ?? '') === 'PA' ? 'selected' : '' ?>>Pará</option>
                                    <option value="PB" <?= ($_POST['estado'] ?? '') === 'PB' ? 'selected' : '' ?>>Paraíba</option>
                                    <option value="PR" <?= ($_POST['estado'] ?? '') === 'PR' ? 'selected' : '' ?>>Paraná</option>
                                    <option value="PE" <?= ($_POST['estado'] ?? '') === 'PE' ? 'selected' : '' ?>>Pernambuco</option>
                                    <option value="PI" <?= ($_POST['estado'] ?? '') === 'PI' ? 'selected' : '' ?>>Piauí</option>
                                    <option value="RJ" <?= ($_POST['estado'] ?? '') === 'RJ' ? 'selected' : '' ?>>Rio de Janeiro</option>
                                    <option value="RN" <?= ($_POST['estado'] ?? '') === 'RN' ? 'selected' : '' ?>>Rio Grande do Norte</option>
                                    <option value="RS" <?= ($_POST['estado'] ?? '') === 'RS' ? 'selected' : '' ?>>Rio Grande do Sul</option>
                                    <option value="RO" <?= ($_POST['estado'] ?? '') === 'RO' ? 'selected' : '' ?>>Rondônia</option>
                                    <option value="RR" <?= ($_POST['estado'] ?? '') === 'RR' ? 'selected' : '' ?>>Roraima</option>
                                    <option value="SC" <?= ($_POST['estado'] ?? '') === 'SC' ? 'selected' : '' ?>>Santa Catarina</option>
                                    <option value="SP" <?= ($_POST['estado'] ?? '') === 'SP' ? 'selected' : '' ?>>São Paulo</option>
                                    <option value="SE" <?= ($_POST['estado'] ?? '') === 'SE' ? 'selected' : '' ?>>Sergipe</option>
                                    <option value="TO" <?= ($_POST['estado'] ?? '') === 'TO' ? 'selected' : '' ?>>Tocantins</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Cadastrar Fornecedor
                        </button>

                        <a href="../read/read_supplier.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Voltar à Lista
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Máscara para CNPJ
        document.getElementById('cnpj').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            // Limita a 14 dígitos
            if (value.length > 14) {
                value = value.substring(0, 14);
            }
            
            // Aplica formatação
            if (value.length > 2) {
                value = value.replace(/^(\d{2})(\d)/, '$1.$2');
            }
            if (value.length > 6) {
                value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
            }
            if (value.length > 10) {
                value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
            }
            if (value.length > 13) {
                value = value.replace(/(\d{4})(\d)/, '$1-$2');
            }
            
            e.target.value = value;
            
            // Validação visual em tempo real
            if (value.replace(/\D/g, '').length === 14) {
                if (validarCNPJ(value)) {
                    e.target.classList.remove('invalid');
                    e.target.classList.add('valid');
                } else {
                    e.target.classList.remove('valid');
                    e.target.classList.add('invalid');
                }
            } else {
                e.target.classList.remove('valid', 'invalid');
            }
        });

        // Máscara para CEP
        document.getElementById('cep').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 5) {
                value = value.replace(/^(\d{5})(\d)/, '$1-$2');
            }
            if (value.length > 9) {
                value = value.substring(0, 9);
            }
            e.target.value = value;
            
            // Buscar endereço pelo CEP quando completo
            if (value.replace(/\D/g, '').length === 8) {
                buscarEnderecoPorCEP(value.replace(/\D/g, ''));
            }
        });

        // Máscara para telefone
        document.getElementById('telefone_representante').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/^(\d{2})(\d)/, '($1) $2');
                if (value.length <= 14) {
                    value = value.replace(/(\d{4})(\d)/, '$1-$2');
                } else {
                    value = value.replace(/(\d{5})(\d)/, '$1-$2');
                }
            }
            e.target.value = value;
        });

        // Validação de CNPJ
        function validarCNPJ(cnpj) {
            cnpj = cnpj.replace(/[^\d]+/g, '');

            if (cnpj == '' || cnpj.length != 14) return false;

            // Elimina CNPJs inválidos conhecidos
            if (/^(\d)\1{13}$/.test(cnpj)) return false;

            // Validação do primeiro dígito verificador
            let soma = 0;
            let peso = 5;
            for (let i = 0; i < 12; i++) {
                soma += parseInt(cnpj.charAt(i)) * peso;
                peso = peso == 2 ? 9 : peso - 1;
            }
            let resto = soma % 11;
            let dv1 = resto < 2 ? 0 : 11 - resto;
            
            if (parseInt(cnpj.charAt(12)) !== dv1) return false;

            // Validação do segundo dígito verificador
            soma = 0;
            peso = 6;
            for (let i = 0; i < 13; i++) {
                soma += parseInt(cnpj.charAt(i)) * peso;
                peso = peso == 2 ? 9 : peso - 1;
            }
            resto = soma % 11;
            let dv2 = resto < 2 ? 0 : 11 - resto;
            
            return parseInt(cnpj.charAt(13)) === dv2;
        }

        // Buscar endereço por CEP via API ViaCEP
        async function buscarEnderecoPorCEP(cep) {
            try {
                const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
                const data = await response.json();
                
                if (!data.erro) {
                    document.getElementById('logradouro').value = data.logradouro || '';
                    document.getElementById('bairro').value = data.bairro || '';
                    document.getElementById('cidade').value = data.localidade || '';
                    document.getElementById('estado').value = data.uf || '';
                    
                    // Focar no campo número se o endereço foi preenchido
                    if (data.logradouro) {
                        document.getElementById('numero').focus();
                    }
                }
            } catch (error) {
                console.log('Erro ao buscar CEP:', error);
            }
        }

        // Validação do formulário
        document.getElementById('supplierForm').addEventListener('submit', function(e) {
            const cnpj = document.getElementById('cnpj').value;
            const nomeEmpresa = document.getElementById('nome_empresa').value.trim();
            const nomeRepresentante = document.getElementById('nome_representante').value.trim();
            const email = document.getElementById('email_representante').value.trim();
            const cep = document.getElementById('cep').value.trim();

            // Validar CNPJ
            if (!validarCNPJ(cnpj)) {
                e.preventDefault();
                alert('CNPJ inválido. Verifique os dados digitados.');
                document.getElementById('cnpj').focus();
                return;
            }

            // Validar campos obrigatórios
            if (!nomeEmpresa) {
                e.preventDefault();
                alert('Nome da empresa é obrigatório.');
                document.getElementById('nome_empresa').focus();
                return;
            }

            if (!nomeRepresentante) {
                e.preventDefault();
                alert('Nome do representante é obrigatório.');
                document.getElementById('nome_representante').focus();
                return;
            }

            // Validar e-mail se preenchido
            if (email && !isValidEmail(email)) {
                e.preventDefault();
                alert('E-mail inválido.');
                document.getElementById('email_representante').focus();
                return;
            }

            // Validar CEP se preenchido
            if (cep && !isValidCEP(cep)) {
                e.preventDefault();
                alert('CEP inválido. Use o formato 00000-000.');
                document.getElementById('cep').focus();
                return;
            }

            // Confirmação antes de enviar
            if (!confirm('Confirma o cadastro do fornecedor?')) {
                e.preventDefault();
                return;
            }
        });

        // Função para validar e-mail
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        // Função para validar CEP
        function isValidCEP(cep) {
            const cepRegex = /^\d{5}-?\d{3}$/;
            return cepRegex.test(cep);
        }

        // Feedback visual nos campos
        const inputs = document.querySelectorAll('.form-input, select');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });

            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });

            // Validação em tempo real
            input.addEventListener('input', function() {
                if (this.hasAttribute('required') && this.value.trim() === '') {
                    this.style.borderColor = '#ef4444';
                } else {
                    this.style.borderColor = '#e2e8f0';
                }
            });
        });

        // Auto-focus no primeiro campo
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('cnpj').focus();
        });

        // Capitalizar primeira letra dos nomes
        document.getElementById('nome_empresa').addEventListener('blur', function() {
            this.value = this.value.replace(/\b\w/g, l => l.toUpperCase());
        });

        document.getElementById('nome_representante').addEventListener('blur', function() {
            this.value = this.value.replace(/\b\w/g, l => l.toUpperCase());
        });

        document.getElementById('logradouro').addEventListener('blur', function() {
            this.value = this.value.replace(/\b\w/g, l => l.toUpperCase());
        });

        document.getElementById('bairro').addEventListener('blur', function() {
            this.value = this.value.replace(/\b\w/g, l => l.toUpperCase());
        });

        document.getElementById('cidade').addEventListener('blur', function() {
            this.value = this.value.replace(/\b\w/g, l => l.toUpperCase());
        });

        // Animação de sucesso no envio
        if (document.querySelector('.alert-success')) {
            setTimeout(() => {
                const alert = document.querySelector('.alert-success');
                if (alert) {
                    alert.style.animation = 'slideUp 0.5s ease-out forwards';
                }
            }, 5000);
        }

        // CSS adicional para animações
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideUp {
                from {
                    opacity: 1;
                    transform: translateY(0);
                }
                to {
                    opacity: 0;
                    transform: translateY(-20px);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>

</html>