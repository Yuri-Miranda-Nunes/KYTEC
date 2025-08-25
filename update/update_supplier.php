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
$mensagem_sucesso = '';
$mensagem_erro = '';

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

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar campos obrigatórios
        $nome_empresa = trim($_POST['nome_empresa'] ?? '');
        
        if (empty($nome_empresa)) {
            throw new Exception("O nome da empresa é obrigatório.");
        }

        // Preparar dados para atualização
        $dados = [
            'nome_empresa' => $nome_empresa,
            'cnpj' => trim($_POST['cnpj'] ?? ''),
            'email_representante' => trim($_POST['email_representante'] ?? ''),
            'telefone_representante' => trim($_POST['telefone_representante'] ?? ''),
            'nome_representante' => trim($_POST['nome_representante'] ?? ''),
            'atividade' => trim($_POST['atividade'] ?? ''),
            'endereco' => trim($_POST['endereco'] ?? ''),
            'cep' => trim($_POST['cep'] ?? ''),
            'logradouro' => trim($_POST['logradouro'] ?? ''),
            'numero' => trim($_POST['numero'] ?? ''),
            'complemento' => trim($_POST['complemento'] ?? ''),
            'bairro' => trim($_POST['bairro'] ?? ''),
            'cidade' => trim($_POST['cidade'] ?? ''),
            'estado' => trim($_POST['estado'] ?? ''),
            'atualizado_em' => date('Y-m-d H:i:s')
        ];

        // Validar email se fornecido
        if (!empty($dados['email_representante']) && !filter_var($dados['email_representante'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email inválido.");
        }

        // Validar CNPJ se fornecido
        if (!empty($dados['cnpj'])) {
            $cnpj = preg_replace('/\D/', '', $dados['cnpj']);
            if (strlen($cnpj) != 14) {
                throw new Exception("CNPJ deve ter 14 dígitos.");
            }
            $dados['cnpj'] = $cnpj;
        }

        // Verificar se já existe outro fornecedor com mesmo CNPJ
        if (!empty($dados['cnpj'])) {
            $stmt = $bd->pdo->prepare("SELECT id_fornecedor FROM fornecedores WHERE cnpj = ? AND id_fornecedor != ?");
            $stmt->execute([$dados['cnpj'], $id]);
            if ($stmt->fetch()) {
                throw new Exception("Já existe um fornecedor cadastrado com este CNPJ.");
            }
        }

        // Verificar se já existe outro fornecedor com mesmo email
        if (!empty($dados['email_representante'])) {
            $stmt = $bd->pdo->prepare("SELECT id_fornecedor FROM fornecedores WHERE email_representante = ? AND id_fornecedor != ?");
            $stmt->execute([$dados['email_representante'], $id]);
            if ($stmt->fetch()) {
                throw new Exception("Já existe um fornecedor cadastrado com este email.");
            }
        }

        // Preparar query de atualização
        $campos = [];
        $valores = [];
        
        foreach ($dados as $campo => $valor) {
            $campos[] = "$campo = ?";
            $valores[] = $valor;
        }
        
        $valores[] = $id; // Para o WHERE
        
        $sql = "UPDATE fornecedores SET " . implode(', ', $campos) . " WHERE id_fornecedor = ?";
        $stmt = $bd->pdo->prepare($sql);
        $stmt->execute($valores);

        // Registrar log de atualização
        if (class_exists('Logger')) {
            Logger::registrarLog(
                $_SESSION['usuario_id'],
                'UPDATE',
                'fornecedores',
                $id,
                json_encode($fornecedor, JSON_UNESCAPED_UNICODE),
                json_encode($dados, JSON_UNESCAPED_UNICODE),
                "Fornecedor atualizado: {$dados['nome_empresa']}"
            );
        }

        $mensagem_sucesso = "Fornecedor atualizado com sucesso!";
        
        // Atualizar dados do fornecedor para exibir os novos valores
        $fornecedor = array_merge($fornecedor, $dados);
        
    } catch (Exception $e) {
        $mensagem_erro = $e->getMessage();
    }
}

// Função para determinar se a página atual está ativa
function isActivePage($page)
{
    $current = basename($_SERVER['PHP_SELF']);
    return $current === $page ? 'active' : '';
}

// Função para formatar CNPJ para exibição
function formatarCNPJDisplay($cnpj)
{
    if (empty($cnpj)) return '';
    
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

// Função para formatar telefone para exibição
function formatarTelefoneDisplay($telefone)
{
    if (empty($telefone)) return '';
    
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

// Estados brasileiros
$estados = [
    'AC' => 'Acre',
    'AL' => 'Alagoas',
    'AP' => 'Amapá',
    'AM' => 'Amazonas',
    'BA' => 'Bahia',
    'CE' => 'Ceará',
    'DF' => 'Distrito Federal',
    'ES' => 'Espírito Santo',
    'GO' => 'Goiás',
    'MA' => 'Maranhão',
    'MT' => 'Mato Grosso',
    'MS' => 'Mato Grosso do Sul',
    'MG' => 'Minas Gerais',
    'PA' => 'Pará',
    'PB' => 'Paraíba',
    'PR' => 'Paraná',
    'PE' => 'Pernambuco',
    'PI' => 'Piauí',
    'RJ' => 'Rio de Janeiro',
    'RN' => 'Rio Grande do Norte',
    'RS' => 'Rio Grande do Sul',
    'RO' => 'Rondônia',
    'RR' => 'Roraima',
    'SC' => 'Santa Catarina',
    'SP' => 'São Paulo',
    'SE' => 'Sergipe',
    'TO' => 'Tocantins'
];
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Fornecedor - Sistema de Estoque</title>
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
            max-width: calc(100vw - 280px);
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

        /* Messages */
        .message {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .message-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .message-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        /* Form Container */
        .form-container {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }

        .form-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-subtitle {
            color: #64748b;
            margin-bottom: 32px;
            font-size: 0.9rem;
        }

        /* Form Layout */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-section {
            border-top: 2px solid #f1f5f9;
            padding-top: 24px;
            margin-top: 24px;
        }

        .form-section:first-child {
            border-top: none;
            padding-top: 0;
            margin-top: 0;
        }

        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Form Elements */
        .form-label {
            font-weight: 500;
            color: #374151;
            font-size: 0.875rem;
            margin-bottom: 4px;
        }

        .form-label.required::after {
            content: " *";
            color: #ef4444;
        }

        .form-input,
        .form-select,
        .form-textarea {
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            background: white;
            color: #1e293b;
            width: 100%;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
        }

        /* Input with Icon */
        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.875rem;
        }

        .input-group .form-input {
            padding-left: 40px;
        }

        /* Buttons */
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
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
            font-family: inherit;
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
            background: #f8fafc;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #f1f5f9;
            color: #374151;
        }

        /* Address Grid */
        .address-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 16px;
        }

        .address-row {
            display: grid;
            grid-template-columns: 3fr 1fr;
            gap: 16px;
        }

        /* Info Box */
        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .info-box h4 {
            color: #374151;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-box p {
            color: #64748b;
            font-size: 0.8rem;
            line-height: 1.5;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .main-content {
                margin-left: 0;
                max-width: 100vw;
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
                flex-direction: column-reverse;
            }

            .address-grid,
            .address-row {
                grid-template-columns: 1fr;
            }

            .form-container {
                padding: 20px;
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
                    <h1>Editar Fornecedor</h1>
                    <p class="header-subtitle">Atualize as informações do fornecedor</p>
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
            <?php if (!empty($mensagem_sucesso)): ?>
                <div class="message message-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($mensagem_sucesso) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($mensagem_erro)): ?>
                <div class="message message-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($mensagem_erro) ?>
                </div>
            <?php endif; ?>

            <!-- Form Container -->
            <div class="form-container">
                <div class="form-title">
                    <i class="fas fa-edit"></i>
                    Editar Fornecedor
                </div>
                <p class="form-subtitle">
                    Atualize as informações do fornecedor <?= htmlspecialchars($fornecedor['nome_empresa']) ?>
                </p>

                <div class="info-box">
                    <h4>
                        <i class="fas fa-info-circle"></i>
                        Informações do Sistema
                    </h4>
                    <p>
                        <strong>ID:</strong> #<?= str_pad($fornecedor['id_fornecedor'], 4, '0', STR_PAD_LEFT) ?> |
                        <strong>Cadastrado em:</strong> <?= date('d/m/Y H:i', strtotime($fornecedor['criado_em'])) ?> |
                        <strong>Última atualização:</strong> <?= date('d/m/Y H:i', strtotime($fornecedor['atualizado_em'])) ?>
                    </p>
                </div>

                <form method="POST" id="supplierForm">
                    <!-- Informações da Empresa -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-building"></i>
                            Informações da Empresa
                        </h3>
                        <label for="nome_empresa" class="form-label required">Nome da Empresa</label>
                                <div class="input-group">
                                    <i class="fas fa-building input-icon"></i>
                                    <input type="text" 
                                           id="nome_empresa" 
                                           name="nome_empresa" 
                                           class="form-input" 
                                           value="<?= htmlspecialchars($fornecedor['nome_empresa']) ?>"
                                           required 
                                           maxlength="150"
                                           placeholder="Ex: Empresa Ltda">
                                </div>
                            </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="cnpj" class="form-label">CNPJ</label>
                                <div class="input-group">
                                    <i class="fas fa-file-alt input-icon"></i>
                                    <input type="text" 
                                           id="cnpj" 
                                           name="cnpj" 
                                           class="form-input" 
                                           value="<?= formatarCNPJDisplay($fornecedor['cnpj']) ?>"
                                           maxlength="18"
                                           placeholder="00.000.000/0000-00">
                                </div>
                            </div>

                            <div class="form-group full-width">
                                <label for="atividade" class="form-label">Atividade Principal</label>
                                <div class="input-group">
                                    <i class="fas fa-briefcase input-icon"></i>
                                    <input type="text" 
                                           id="atividade" 
                                           name="atividade" 
                                           class="form-input" 
                                           value="<?= htmlspecialchars($fornecedor['atividade']) ?>"
                                           maxlength="100"
                                           placeholder="Ex: Fornecimento de materiais de construção">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Informações do Representante -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-user-tie"></i>
                            Dados do Representante
                        </h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nome_representante" class="form-label">Nome do Representante</label>
                                <div class="input-group">
                                    <i class="fas fa-user input-icon"></i>
                                    <input type="text" 
                                           id="nome_representante" 
                                           name="nome_representante" 
                                           class="form-input" 
                                           value="<?= htmlspecialchars($fornecedor['nome_representante']) ?>"
                                           maxlength="100"
                                           placeholder="Nome completo do representante">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="telefone_representante" class="form-label">Telefone do Representante</label>
                                <div class="input-group">
                                    <i class="fas fa-phone input-icon"></i>
                                    <input type="tel" 
                                           id="telefone_representante" 
                                           name="telefone_representante" 
                                           class="form-input" 
                                           value="<?= formatarTelefoneDisplay($fornecedor['telefone_representante']) ?>"
                                           maxlength="15"
                                           placeholder="(00) 00000-0000">
                                </div>
                            </div>

                            <div class="form-group full-width">
                                <label for="email_representante" class="form-label">Email do Representante</label>
                                <div class="input-group">
                                    <i class="fas fa-envelope input-icon"></i>
                                    <input type="email" 
                                           id="email_representante" 
                                           name="email_representante" 
                                           class="form-input" 
                                           value="<?= htmlspecialchars($fornecedor['email_representante']) ?>"
                                           maxlength="100"
                                           placeholder="contato@empresa.com.br">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Endereço -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-map-marker-alt"></i>
                            Endereço
                        </h3>
                        
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="endereco" class="form-label">Endereço Completo</label>
                                <div class="input-group">
                                    <i class="fas fa-home input-icon"></i>
                                    <input type="text" 
                                           id="endereco" 
                                           name="endereco" 
                                           class="form-input" 
                                           value="<?= htmlspecialchars($fornecedor['endereco']) ?>"
                                           maxlength="255"
                                           placeholder="Endereço completo (opcional - para referência rápida)">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="cep" class="form-label">CEP</label>
                                <div class="input-group">
                                    <i class="fas fa-map-pin input-icon"></i>
                                    <input type="text" 
                                           id="cep" 
                                           name="cep" 
                                           class="form-input" 
                                           value="<?= htmlspecialchars($fornecedor['cep']) ?>"
                                           maxlength="10"
                                           placeholder="00000-000">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="logradouro" class="form-label">Logradouro</label>
                                <div class="input-group">
                                    <i class="fas fa-road input-icon"></i>
                                    <input type="text" 
                                           id="logradouro" 
                                           name="logradouro" 
                                           class="form-input" 
                                           value="<?= htmlspecialchars($fornecedor['logradouro']) ?>"
                                           maxlength="200"
                                           placeholder="Rua, Avenida, etc.">
                                </div>
                            </div>

                            <div class="address-row">
                                <div class="form-group">
                                    <label for="numero" class="form-label">Número</label>
                                    <div class="input-group">
                                        <i class="fas fa-hashtag input-icon"></i>
                                        <input type="text" 
                                               id="numero" 
                                               name="numero" 
                                               class="form-input" 
                                               value="<?= htmlspecialchars($fornecedor['numero']) ?>"
                                               maxlength="20"
                                               placeholder="123">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="complemento" class="form-label">Complemento</label>
                                    <div class="input-group">
                                        <i class="fas fa-plus input-icon"></i>
                                        <input type="text" 
                                               id="complemento" 
                                               name="complemento" 
                                               class="form-input" 
                                               value="<?= htmlspecialchars($fornecedor['complemento']) ?>"
                                               maxlength="100"
                                               placeholder="Apt, Sala, etc.">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="bairro" class="form-label">Bairro</label>
                                <div class="input-group">
                                    <i class="fas fa-location-dot input-icon"></i>
                                    <input type="text" 
                                           id="bairro" 
                                           name="bairro" 
                                           class="form-input" 
                                           value="<?= htmlspecialchars($fornecedor['bairro']) ?>"
                                           maxlength="100"
                                           placeholder="Nome do bairro">
                                </div>
                            </div>

                            <div class="address-row">
                                <div class="form-group">
                                    <label for="cidade" class="form-label">Cidade</label>
                                    <div class="input-group">
                                        <i class="fas fa-city input-icon"></i>
                                        <input type="text" 
                                               id="cidade" 
                                               name="cidade" 
                                               class="form-input" 
                                               value="<?= htmlspecialchars($fornecedor['cidade']) ?>"
                                               maxlength="100"
                                               placeholder="Nome da cidade">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="estado" class="form-label">Estado</label>
                                    <select id="estado" name="estado" class="form-select">
                                        <option value="">Selecione o estado</option>
                                        <?php foreach ($estados as $sigla => $nome): ?>
                                            <option value="<?= $sigla ?>" <?= $fornecedor['estado'] === $sigla ? 'selected' : '' ?>>
                                                <?= $sigla ?> - <?= $nome ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <a href="../read/read_supplier.php" class="btn btn-secondary">
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
        </main>
    </div>

    <script>
        // Máscaras de entrada
        document.addEventListener('DOMContentLoaded', function() {
            // Máscara para CNPJ
            const cnpjInput = document.getElementById('cnpj');
            cnpjInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length <= 14) {
                    value = value.replace(/^(\d{2})(\d)/, '$1.$2');
                    value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                    value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
                    value = value.replace(/(\d{4})(\d)/, '$1-$2');
                    e.target.value = value;
                }
            });

            // Máscara para telefone
            const telefoneInput = document.getElementById('telefone_representante');
            telefoneInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length <= 11) {
                    if (value.length <= 10) {
                        value = value.replace(/^(\d{2})(\d)/, '($1) $2');
                        value = value.replace(/(\d{4})(\d)/, '$1-$2');
                    } else {
                        value = value.replace(/^(\d{2})(\d)/, '($1) $2');
                        value = value.replace(/(\d{5})(\d)/, '$1-$2');
                    }
                    e.target.value = value;
                }
            });

            // Máscara para CEP
            const cepInput = document.getElementById('cep');
            cepInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length <= 8) {
                    value = value.replace(/^(\d{5})(\d)/, '$1-$2');
                    e.target.value = value;
                }
            });

            // Buscar CEP automaticamente
            cepInput.addEventListener('blur', function(e) {
                const cep = e.target.value.replace(/\D/g, '');
                if (cep.length === 8) {
                    buscarCEP(cep);
                }
            });

            // Validação de email em tempo real
            const emailInput = document.getElementById('email_representante');
            emailInput.addEventListener('blur', function(e) {
                const email = e.target.value;
                if (email && !isValidEmail(email)) {
                    showFieldError(e.target, 'Email inválido');
                } else {
                    clearFieldError(e.target);
                }
            });

            // Validação de CNPJ em tempo real
            cnpjInput.addEventListener('blur', function(e) {
                const cnpj = e.target.value.replace(/\D/g, '');
                if (cnpj && cnpj.length !== 14) {
                    showFieldError(e.target, 'CNPJ deve ter 14 dígitos');
                } else {
                    clearFieldError(e.target);
                }
            });
        });

        // Buscar CEP via API
        function buscarCEP(cep) {
            fetch(`https://viacep.com.br/ws/${cep}/json/`)
                .then(response => response.json())
                .then(data => {
                    if (!data.erro) {
                        document.getElementById('logradouro').value = data.logradouro || '';
                        document.getElementById('bairro').value = data.bairro || '';
                        document.getElementById('cidade').value = data.localidade || '';
                        document.getElementById('estado').value = data.uf || '';
                        
                        // Atualizar campo de endereço completo
                        const enderecoCompleto = `${data.logradouro || ''}, ${data.bairro || ''}, ${data.localidade || ''} - ${data.uf || ''}`.replace(/, ,/g, ',').replace(/^, |, $/g, '');
                        document.getElementById('endereco').value = enderecoCompleto;
                    }
                })
                .catch(error => {
                    console.log('Erro ao buscar CEP:', error);
                });
        }

        // Validação de email
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        // Mostrar erro no campo
        function showFieldError(field, message) {
            clearFieldError(field);
            field.style.borderColor = '#ef4444';
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'field-error';
            errorDiv.style.cssText = 'color: #ef4444; font-size: 0.75rem; margin-top: 4px;';
            errorDiv.textContent = message;
            
            field.parentNode.appendChild(errorDiv);
        }

        // Limpar erro do campo
        function clearFieldError(field) {
            field.style.borderColor = '#d1d5db';
            const errorDiv = field.parentNode.querySelector('.field-error');
            if (errorDiv) {
                errorDiv.remove();
            }
        }

        // Validação do formulário antes do envio
        document.getElementById('supplierForm').addEventListener('submit', function(e) {
            const nomeEmpresa = document.getElementById('nome_empresa').value.trim();
            
            if (!nomeEmpresa) {
                e.preventDefault();
                document.getElementById('nome_empresa').focus();
                showFieldError(document.getElementById('nome_empresa'), 'Nome da empresa é obrigatório');
                return;
            }

            // Validar email se preenchido
            const email = document.getElementById('email_representante').value;
            if (email && !isValidEmail(email)) {
                e.preventDefault();
                document.getElementById('email_representante').focus();
                showFieldError(document.getElementById('email_representante'), 'Email inválido');
                return;
            }

            // Validar CNPJ se preenchido
            const cnpj = document.getElementById('cnpj').value.replace(/\D/g, '');
            if (cnpj && cnpj.length !== 14) {
                e.preventDefault();
                document.getElementById('cnpj').focus();
                showFieldError(document.getElementById('cnpj'), 'CNPJ deve ter 14 dígitos');
                return;
            }
        });

        // Auto-save (opcional)
        let autoSaveTimeout;
        const formInputs = document.querySelectorAll('#supplierForm input, #supplierForm select, #supplierForm textarea');
        
        formInputs.forEach(input => {
            input.addEventListener('input', function() {
                clearTimeout(autoSaveTimeout);
                autoSaveTimeout = setTimeout(() => {
                    // Aqui você pode implementar um auto-save se desejar
                    console.log('Auto-save would happen here');
                }, 2000);
            });
        });

        // Confirmação antes de sair da página se houver mudanças
        let formChanged = false;
        formInputs.forEach(input => {
            input.addEventListener('input', () => formChanged = true);
        });

        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Marcar como não mudado quando form é submetido
        document.getElementById('supplierForm').addEventListener('submit', () => {
            formChanged = false;
        });

        // Animações suaves
        document.querySelectorAll('.form-input, .form-select').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentNode.style.transform = 'translateY(-1px)';
                this.parentNode.style.transition = 'transform 0.2s ease';
            });

            input.addEventListener('blur', function() {
                this.parentNode.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>

</html>