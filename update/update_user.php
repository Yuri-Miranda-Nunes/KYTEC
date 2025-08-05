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

// Verifica se o ID foi fornecido
$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
  $_SESSION['mensagem_erro'] = "ID de usuário inválido.";
  header("Location: ../read/read_user.php");
  exit;
}

$bd = new BancoDeDados();

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

// Buscar dados do usuário
try {
  $stmt = $bd->pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
  $stmt->execute([$id]);
  $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$usuario) {
    $_SESSION['mensagem_erro'] = "Usuário não encontrado.";
    header("Location: ../read/read_user.php");
    exit;
  }
} catch (Exception $e) {
  $_SESSION['mensagem_erro'] = "Erro ao buscar usuário: " . $e->getMessage();
  header("Location: ../read/read_user.php");
  exit;
}

// Processar formulário quando enviado
$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    // Validar campos obrigatórios
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    $perfil = $_POST['perfil'] ?? '';
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    if (empty($nome)) {
      throw new Exception("Nome do usuário é obrigatório.");
    }

    if (empty($email)) {
      throw new Exception("Email é obrigatório.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new Exception("Email inválido.");
    }

    if (empty($perfil)) {
      throw new Exception("Perfil do usuário é obrigatório.");
    }

    // Verificar se email já existe (exceto para o próprio usuário)
    $stmt_check = $bd->pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ? AND id != ?");
    $stmt_check->execute([$email, $id]);
    if ($stmt_check->fetchColumn() > 0) {
      throw new Exception("Este email já está sendo usado por outro usuário.");
    }

    // Validar nova senha se fornecida
    if (!empty($nova_senha)) {
      if (strlen($nova_senha) < 6) {
        throw new Exception("A nova senha deve ter pelo menos 6 caracteres.");
      }

      if ($nova_senha !== $confirmar_senha) {
        throw new Exception("As senhas não coincidem.");
      }
    }

    // Inicia transação
    $bd->pdo->beginTransaction();

    try {
      // Atualizar dados básicos do usuário
      if (!empty($nova_senha)) {
        // Com nova senha
        $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        $sql = "UPDATE usuarios SET nome = ?, email = ?, senha = ?, perfil = ?, ativo = ? WHERE id = ?";
        $stmt = $bd->pdo->prepare($sql);
        $stmt->execute([$nome, $email, $senha_hash, $perfil, $ativo, $id]);
      } else {
        // Sem alterar senha
        $sql = "UPDATE usuarios SET nome = ?, email = ?, perfil = ?, ativo = ? WHERE id = ?";
        $stmt = $bd->pdo->prepare($sql);
        $stmt->execute([$nome, $email, $perfil, $ativo, $id]);
      }

      // Remover permissões antigas
      $stmt_del = $bd->pdo->prepare("DELETE FROM usuario_permissoes WHERE usuario_id = ?");
      $stmt_del->execute([$id]);

      // Inserir novas permissões baseadas no perfil
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
          $stmt_user_perm->execute([$id, $permissao_id]);
        }
      }

      // Confirma a transação
      $bd->pdo->commit();

      // Atualizar dados locais para exibição
      $usuario['nome'] = $nome;
      $usuario['email'] = $email;
      $usuario['perfil'] = $perfil;
      $usuario['ativo'] = $ativo;

      // Salva a mensagem na sessão
      $_SESSION['mensagem_sucesso'] = "Usuário atualizado com sucesso!";

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
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Editar Usuário - Sistema de Estoque</title>
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

    /* User Info Display */
    .user-current-info {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 24px;
    }

    .user-current-info h3 {
      color: #1e293b;
      font-size: 1rem;
      font-weight: 600;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .current-info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
    }

    .current-info-item {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .current-info-label {
      font-size: 0.75rem;
      font-weight: 500;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .current-info-value {
      font-size: 0.875rem;
      color: #1e293b;
      font-weight: 500;
    }

    .current-status {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 0.75rem;
      font-weight: 500;
    }

    .current-status.ativo {
      background: #dcfce7;
      color: #16a34a;
    }

    .current-status.inativo {
      background: #fee2e2;
      color: #dc2626;
    }

    .current-perfil {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 0.75rem;
      font-weight: 500;
    }

    .current-perfil.admin {
      background: #fef3c7;
      color: #d97706;
    }

    .current-perfil.estoquista {
      background: #e0e7ff;
      color: #3730a3;
    }

    .current-perfil.visualizador {
      background: #f3f4f6;
      color: #374151;
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

    /* Password Section */
    .password-section {
      background: #fef9e7;
      border: 1px solid #fbbf24;
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 20px;
    }

    .password-section h4 {
      color: #92400e;
      font-size: 0.875rem;
      font-weight: 600;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .password-section p {
      color: #b45309;
      font-size: 0.8rem;
      margin-bottom: 16px;
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

      .current-info-grid {
        grid-template-columns: 1fr;
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
          <h1>Editar Usuário</h1>
          <p class="header-subtitle">Modifique as informações do usuário selecionado</p>
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

      <!-- Current User Info -->
      <div class="form-section">
        <h2 class="section-title">
          <i class="fas fa-user"></i>
          Informações Atuais
        </h2>

        <div class="user-current-info">
          <h3>
            <i class="fas fa-info-circle"></i>
            Dados do Usuário
          </h3>
          <div class="current-info-grid">
            <div class="current-info-item">
              <span class="current-info-label">Nome</span>
              <span class="current-info-value"><?= htmlspecialchars($usuario['nome']) ?></span>
            </div>
            <div class="current-info-item">
              <span class="current-info-label">Email</span>
              <span class="current-info-value"><?= htmlspecialchars($usuario['email']) ?></span>
            </div>
            <div class="current-info-item">
              <span class="current-info-label">Perfil</span>
              <span class="current-info-value">
                <span class="current-perfil <?= $usuario['perfil'] ?>">
                  <?= htmlspecialchars(ucfirst($usuario['perfil'])) ?>
                </span>
              </span>
            </div>
            <div class="current-info-item">
              <span class="current-info-label">Status</span>
              <span class="current-info-value">
                <span class="current-status <?= $usuario['ativo'] ? 'ativo' : 'inativo' ?>">
                  <?= $usuario['ativo'] ? 'Ativo' : 'Inativo' ?>
                </span>
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Form Section -->
      <div class="form-section">
        <h2 class="section-title">
          <i class="fas fa-edit"></i>
          Atualizar Informações
        </h2>

        <form method="POST" action="">
          <div class="form-grid">
            <!-- Nome -->
            <div class="form-group">
              <label class="form-label" for="nome">
                Nome Completo <span class="required">*</span>
              </label>
              <input type="text"
                id="nome"
                name="nome"
                class="form-input"
                value="<?= htmlspecialchars($usuario['nome']) ?>"
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
                value="<?= htmlspecialchars($usuario['email']) ?>"
                required
                placeholder="Ex: joao@fabrica.com">
              <div class="input-hint">Email único para login no sistema</div>
            </div>

            <!-- Perfil -->
            <div class="form-group full-width">
              <label class="form-label" for="perfil">
                Perfil do Usuário <span class="required">*</span>
              </label>
              <select name="perfil" id="perfil" class="form-select" required>
                <option value="">Selecione um perfil</option>
                <?php foreach ($permissoes_por_perfil as $perfil_nome => $permissoes): ?>
                  <option value="<?= $perfil_nome ?>" <?= $usuario['perfil'] === $perfil_nome ? 'selected' : '' ?>>
                    <?= ucfirst($perfil_nome) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="perfil-info">
                <h4><i class="fas fa-key"></i> Permissões deste perfil:</h4>
                <?php if (!empty($usuario['perfil']) && isset($permissoes_por_perfil[$usuario['perfil']])): ?>
                  <ul class="permissoes-list">
                    <?php foreach ($permissoes_por_perfil[$usuario['perfil']] as $permissao): ?>
                      <li><?= $permissoes_descricoes[$permissao] ?? $permissao ?></li>
                    <?php endforeach; ?>
                  </ul>
                <?php else: ?>
                  <p>Selecione um perfil para ver as permissões.</p>
                <?php endif; ?>
              </div>
            </div>

            <!-- Status -->
            <div class="form-group">
              <label class="form-label" for="ativo">
                Status do Usuário
              </label>
              <div class="checkbox-group">
                <input type="checkbox" id="ativo" name="ativo" class="checkbox-input" <?= $usuario['ativo'] ? 'checked' : '' ?>>
                <label for="ativo" class="checkbox-label">Usuário Ativo</label>
              </div>
            </div>

            <!-- Senha -->
            <div class="form-group full-width password-section">
              <h4><i class="fas fa-lock"></i> Alterar Senha (opcional)</h4>
              <p>Preencha abaixo apenas se desejar alterar a senha deste usuário.</p>

              <div class="form-group">
                <label class="form-label" for="nova_senha">Nova Senha</label>
                <input type="password" id="nova_senha" name="nova_senha" class="form-input" placeholder="Nova senha (mínimo 6 caracteres)">
              </div>

              <div class="form-group">
                <label class="form-label" for="confirmar_senha">Confirmar Nova Senha</label>
                <input type="password" id="confirmar_senha" name="confirmar_senha" class="form-input" placeholder="Repita a nova senha">
              </div>
            </div>
          </div>

          <!-- Ações -->
          <div class="form-actions">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save"></i> Salvar Alterações
            </button>
            <a href="../read/read_user.php" class="btn btn-secondary">
              <i class="fas fa-arrow-left"></i> Cancelar
            </a>
          </div>
        </form>
      </div>
    </main>
  </div>
  <script>
    // Auto-focus no primeiro campo
    document.getElementById('nome').focus();

    // Mostrar informações do perfil quando selecionado
    document.getElementById('perfil').addEventListener('change', function() {
      const perfilInfo = document.querySelector('.perfil-info');
      const perfilSelect = this;

      if (perfilSelect.value) {
        // Fazer requisição AJAX ou usar dados já disponíveis
        // Como os dados já estão disponíveis no PHP, vamos usar uma abordagem simples

        // Definir permissões por perfil (mesmo array do PHP)
        const permissoesPorPerfil = {
          'admin': ['Visualizar lista de produtos', 'Cadastrar novos produtos', 'Editar produtos existentes', 'Excluir produtos', 'Gerenciar usuários do sistema'],
          'estoquista': ['Visualizar lista de produtos', 'Cadastrar novos produtos', 'Editar produtos existentes', 'Excluir produtos'],
          'visualizador': ['Visualizar lista de produtos']
        };

        const permissoes = permissoesPorPerfil[perfilSelect.value] || [];

        // Atualizar o conteúdo da div de permissões
        let permissoesHtml = '<h4><i class="fas fa-key"></i> Permissões deste perfil:</h4>';

        if (permissoes.length > 0) {
          permissoesHtml += '<ul class="permissoes-list">';
          permissoes.forEach(function(permissao) {
            permissoesHtml += '<li>' + permissao + '</li>';
          });
          permissoesHtml += '</ul>';
        } else {
          permissoesHtml += '<p>Nenhuma permissão encontrada para este perfil.</p>';
        }

        perfilInfo.innerHTML = permissoesHtml;
      } else {
        perfilInfo.innerHTML = '<h4><i class="fas fa-key"></i> Permissões deste perfil:</h4><p>Selecione um perfil para ver as permissões.</p>';
      }
    });

    // Validação de confirmação de senha
    document.getElementById('confirmar_senha').addEventListener('input', function() {
      const novaSenha = document.getElementById('nova_senha').value;
      const confirmarSenha = this.value;

      if (novaSenha && novaSenha !== confirmarSenha) {
        this.setCustomValidity('As senhas não coincidem');
        this.style.borderColor = '#dc2626';
      } else {
        this.setCustomValidity('');
        this.style.borderColor = '';
      }
    });

    // Validar que se nova_senha foi preenchida, confirmar_senha também deve ser
    document.getElementById('nova_senha').addEventListener('input', function() {
      const confirmarSenha = document.getElementById('confirmar_senha');
      if (this.value) {
        confirmarSenha.required = true;
      } else {
        confirmarSenha.required = false;
        confirmarSenha.value = '';
      }
    });
    // Dados originais do usuário (capturados do PHP)
    const dadosOriginais = {
      nome: <?= json_encode($usuario['nome']) ?>,
      email: <?= json_encode($usuario['email']) ?>,
      perfil: <?= json_encode($usuario['perfil']) ?>,
      ativo: <?= $usuario['ativo'] ? 'true' : 'false' ?>,
      nova_senha: '',
      confirmar_senha: ''
    };

    // Função para verificar se houve alterações
    function verificarAlteracoes() {
      const btnSalvar = document.querySelector('.btn-primary');

      const dadosAtuais = {
        nome: document.getElementById('nome').value.trim(),
        email: document.getElementById('email').value.trim(),
        perfil: document.getElementById('perfil').value,
        ativo: document.getElementById('ativo').checked ? 'true' : 'false',
        nova_senha: document.getElementById('nova_senha').value,
        confirmar_senha: document.getElementById('confirmar_senha').value
      };

      // Verificar se algum campo foi alterado
      let houveAlteracao = false;

      // Verificar campos básicos
      if (dadosAtuais.nome !== dadosOriginais.nome ||
        dadosAtuais.email !== dadosOriginais.email ||
        dadosAtuais.perfil !== dadosOriginais.perfil ||
        dadosAtuais.ativo !== dadosOriginais.ativo) {
        houveAlteracao = true;
      }

      // Verificar se foi preenchida nova senha
      if (dadosAtuais.nova_senha.length > 0 || dadosAtuais.confirmar_senha.length > 0) {
        houveAlteracao = true;
      }

      // Mostrar/ocultar botão baseado nas alterações
      if (houveAlteracao) {
        btnSalvar.style.display = 'flex';
        btnSalvar.style.opacity = '1';
        btnSalvar.disabled = false;
      } else {
        btnSalvar.style.display = 'none';
        btnSalvar.disabled = true;
      }
    }

    // Auto-focus no primeiro campo
    document.getElementById('nome').focus();

    // Adicionar listeners para todos os campos
    document.getElementById('nome').addEventListener('input', verificarAlteracoes);
    document.getElementById('email').addEventListener('input', verificarAlteracoes);
    document.getElementById('perfil').addEventListener('change', function() {
      // Atualizar permissões (código anterior)
      const perfilInfo = document.querySelector('.perfil-info');
      const perfilSelect = this;

      if (perfilSelect.value) {
        const permissoesPorPerfil = {
          'admin': ['Visualizar lista de produtos', 'Cadastrar novos produtos', 'Editar produtos existentes', 'Excluir produtos', 'Gerenciar usuários do sistema'],
          'estoquista': ['Visualizar lista de produtos', 'Cadastrar novos produtos', 'Editar produtos existentes', 'Excluir produtos'],
          'visualizador': ['Visualizar lista de produtos']
        };

        const permissoes = permissoesPorPerfil[perfilSelect.value] || [];

        let permissoesHtml = '<h4><i class="fas fa-key"></i> Permissões deste perfil:</h4>';

        if (permissoes.length > 0) {
          permissoesHtml += '<ul class="permissoes-list">';
          permissoes.forEach(function(permissao) {
            permissoesHtml += '<li>' + permissao + '</li>';
          });
          permissoesHtml += '</ul>';
        } else {
          permissoesHtml += '<p>Nenhuma permissão encontrada para este perfil.</p>';
        }

        perfilInfo.innerHTML = permissoesHtml;
      } else {
        perfilInfo.innerHTML = '<h4><i class="fas fa-key"></i> Permissões deste perfil:</h4><p>Selecione um perfil para ver as permissões.</p>';
      }

      // Verificar alterações após mudança de perfil
      verificarAlteracoes();
    });
    document.getElementById('ativo').addEventListener('change', verificarAlteracoes);
    document.getElementById('nova_senha').addEventListener('input', function() {
      const confirmarSenha = document.getElementById('confirmar_senha');
      if (this.value) {
        confirmarSenha.required = true;
      } else {
        confirmarSenha.required = false;
        confirmarSenha.value = '';
      }
      verificarAlteracoes();
    });
    document.getElementById('confirmar_senha').addEventListener('input', function() {
      const novaSenha = document.getElementById('nova_senha').value;
      const confirmarSenha = this.value;

      if (novaSenha && novaSenha !== confirmarSenha) {
        this.setCustomValidity('As senhas não coincidem');
        this.style.borderColor = '#dc2626';
      } else {
        this.setCustomValidity('');
        this.style.borderColor = '';
      }
      verificarAlteracoes();
    });

    // Verificar alterações no carregamento da página
    window.addEventListener('DOMContentLoaded', function() {
      verificarAlteracoes();
    });
  </script>
</body>

</html>