<?php
session_start();

$mensagemSucesso = '';
if (isset($_SESSION['mensagem_sucesso'])) {
  $mensagemSucesso = $_SESSION['mensagem_sucesso'];
  unset($_SESSION['mensagem_sucesso']); // exibe uma vez só
}

// Verifica se está logado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
  header("Location: ../login.php");
  exit;
}

// Verifica se tem permissão para gerenciar usuários
if (!in_array('gerenciar_usuarios', $_SESSION['permissoes'])) {
  echo "Acesso negado.";
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
$colunasPermitidas = ['nome', 'email', 'perfil', 'ativo', 'departamento', 'cargo', 'data_admissao', 'ultimo_login'];

// Validar ordem e direção
if (!in_array($ordem, $colunasPermitidas)) {
  $ordem = 'nome';
}
if (!in_array($direcao, ['asc', 'desc'])) {
  $direcao = 'asc';
}

// Query para buscar usuários com suas permissões
$sql = "SELECT 
          u.*, 
          GROUP_CONCAT(p.nome_permissao SEPARATOR ', ') as permissoes_usuario,
          DATEDIFF(CURDATE(), u.data_admissao) as dias_empresa
        FROM usuarios u 
        LEFT JOIN usuario_permissoes up ON u.id = up.usuario_id 
        LEFT JOIN permissoes p ON up.permissao_id = p.id 
        GROUP BY u.id 
        ORDER BY {$ordem} {$direcao}";

$stmt = $bd->pdo->query($sql);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

function isActivePage($page)
{
  $current = basename($_SERVER['PHP_SELF']);
  return $current === $page ? 'active' : '';
}

function urlOrdenar($coluna)
{
  $direcao = novaDirecao($coluna);
  $query = $_GET;
  $query['ordem'] = $coluna;
  $query['direcao'] = $direcao;
  return '?' . http_build_query($query);
}

function formatarTelefone($telefone) {
  if (!$telefone) return 'Não informado';
  return $telefone;
}

function formatarData($data) {
  if (!$data) return 'Não informado';
  return date('d/m/Y', strtotime($data));
}

function formatarDataHora($dataHora) {
  if (!$dataHora) return 'Nunca';
  return date('d/m/Y H:i', strtotime($dataHora));
}

function formatarDepartamento($departamento) {
  if (!$departamento) return 'Não informado';
  return ucfirst($departamento);
}

function getStatusBadge($ativo) {
  return $ativo ? 'status-ativo' : 'status-inativo';
}

function getPerfilBadge($perfil) {
  switch($perfil) {
    case 'admin': return 'perfil-admin';
    case 'estoquista': return 'perfil-estoquista';
    default: return 'perfil-visualizador';
  }
}

// Criar array com dados dos usuários para JavaScript
$usuariosJS = [];
foreach($usuarios as $user) {
  $usuariosJS[$user['id']] = [
    'id' => $user['id'],
    'nome' => $user['nome'],
    'email' => $user['email'],
    'matricula' => $user['matricula'],
    'telefone' => $user['telefone'],
    'departamento' => $user['departamento'],
    'cargo' => $user['cargo'],
    'data_admissao' => $user['data_admissao'],
    'perfil' => $user['perfil'],
    'ativo' => $user['ativo'],
    'ultimo_login' => $user['ultimo_login'],
    'total_logins' => $user['total_logins'],
    'criado_em' => $user['criado_em'],
    'dias_empresa' => $user['dias_empresa'],
    'permissions' => explode(', ', $user['permissoes_usuario'] ?? '')
  ];
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lista de Usuários - Sistema de Estoque</title>
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

    .header-right {
      display: flex;
      align-items: center;
      gap: 16px;
      flex-shrink: 0;
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

    /* Users Table */
    .users-section {
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

    .stats-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin-bottom: 24px;
    }

    .stat-card {
      background: linear-gradient(135deg, #f8fafc, #e2e8f0);
      padding: 20px;
      border-radius: 12px;
      border-left: 4px solid #3b82f6;
    }

    .stat-card.warning {
      border-left-color: #f59e0b;
    }

    .stat-card.success {
      border-left-color: #10b981;
    }

    .stat-card.danger {
      border-left-color: #ef4444;
    }

    .stat-number {
      font-size: 2rem;
      font-weight: 700;
      color: #1e293b;
      margin-bottom: 4px;
    }

    .stat-label {
      color: #64748b;
      font-size: 0.875rem;
    }

    .table-container {
      overflow-x: auto;
      border-radius: 8px;
      border: 1px solid #e2e8f0;
    }

    .users-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.875rem;
    }

    .users-table th {
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

    .users-table td {
      padding: 16px 12px;
      border-bottom: 1px solid #f1f5f9;
      color: #64748b;
      vertical-align: middle;
    }

    .users-table tbody tr {
      transition: all 0.2s ease;
      cursor: pointer;
    }

    .users-table tbody tr:hover {
      background: #f8fafc;
      transform: scale(1.01);
    }

    .users-table tbody tr:last-child td {
      border-bottom: none;
    }

    .user-info-cell {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .user-mini-avatar {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: linear-gradient(135deg, #3b82f6, #8b5cf6);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 600;
      font-size: 0.875rem;
      flex-shrink: 0;
    }

    .user-info-text {
      min-width: 0;
    }

    .user-name {
      font-weight: 600;
      color: #1e293b;
      margin-bottom: 2px;
    }

    .user-email {
      color: #64748b;
      font-size: 0.8rem;
    }

    .user-matricula {
      color: #94a3b8;
      font-size: 0.75rem;
    }

    .department-info {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .department-name {
      font-weight: 500;
      color: #1e293b;
    }

    .department-role {
      color: #64748b;
      font-size: 0.8rem;
    }

    .perfil-badge {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 0.75rem;
      font-weight: 500;
    }

    .perfil-admin {
      background: #fef3c7;
      color: #d97706;
    }

    .perfil-estoquista {
      background: #e0e7ff;
      color: #3730a3;
    }

    .perfil-visualizador {
      background: #f3f4f6;
      color: #374151;
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

    .login-stats {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .login-count {
      font-weight: 500;
      color: #1e293b;
    }

    .last-login {
      color: #64748b;
      font-size: 0.8rem;
    }

    .date-info {
      color: #64748b;
      font-size: 0.875rem;
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

    .action-cell {
      display: flex;
      gap: 8px;
    }

    .action-btn {
      padding: 6px 8px;
      border-radius: 6px;
      text-decoration: none;
      font-size: 0.875rem;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .action-btn:hover {
      transform: translateY(-1px);
    }

    .btn-edit {
      background: #e0e7ff;
      color: #3730a3;
    }

    .btn-edit:hover {
      background: #c7d2fe;
    }

    .btn-delete {
      background: #fee2e2;
      color: #dc2626;
    }

    .btn-delete:hover {
      background: #fecaca;
    }

    /* Modal */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.6);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease;
      backdrop-filter: blur(4px);
    }

    .modal-overlay.show {
      opacity: 1;
      visibility: visible;
    }

    .modal {
      background: white;
      border-radius: 16px;
      max-width: 600px;
      width: 90%;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
      transform: scale(0.9) translateY(-20px);
      transition: all 0.3s ease;
    }

    .modal-overlay.show .modal {
      transform: scale(1) translateY(0);
    }

    .modal-header {
      padding: 24px;
      border-bottom: 1px solid #e2e8f0;
      display: flex;
      align-items: center;
      justify-content: between;
      gap: 16px;
    }

    .modal-user-info {
      display: flex;
      align-items: center;
      gap: 16px;
      flex: 1;
    }

    .modal-avatar {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: linear-gradient(135deg, #3b82f6, #8b5cf6);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 700;
      font-size: 1.5rem;
    }

    .modal-user-details h2 {
      font-size: 1.5rem;
      font-weight: 700;
      color: #1e293b;
      margin-bottom: 4px;
    }

    .modal-user-email {
      color: #64748b;
      font-size: 0.875rem;
    }

    .btn-close {
      background: #f1f5f9;
      border: none;
      border-radius: 50%;
      width: 36px;
      height: 36px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      color: #64748b;
      transition: all 0.2s ease;
    }

    .btn-close:hover {
      background: #e2e8f0;
      color: #1e293b;
    }

    .modal-body {
      padding: 24px;
    }

    .modal-section {
      margin-bottom: 24px;
    }

    .modal-section:last-child {
      margin-bottom: 0;
    }

    .modal-section-title {
      font-size: 1rem;
      font-weight: 600;
      color: #1e293b;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
    }

    .info-item {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .info-label {
      color: #64748b;
      font-size: 0.75rem;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .info-value {
      color: #1e293b;
      font-weight: 500;
    }

    .info-value.empty {
      color: #94a3b8;
      font-style: italic;
    }

    .permissions-list {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .permission-tag {
      background: #e0e7ff;
      color: #3730a3;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 0.75rem;
      font-weight: 500;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
      gap: 16px;
    }

    .stat-item {
      text-align: center;
      padding: 16px;
      background: #f8fafc;
      border-radius: 8px;
    }

    .stat-item-value {
      font-size: 1.5rem;
      font-weight: 700;
      color: #1e293b;
      margin-bottom: 4px;
    }

    .stat-item-label {
      color: #64748b;
      font-size: 0.75rem;
    }

    .modal-footer {
      padding: 16px 24px;
      border-top: 1px solid #e2e8f0;
      display: flex;
      justify-content: flex-end;
      gap: 12px;
    }

    .btn-secondary {
      background: #f1f5f9;
      color: #374151;
      border: 1px solid #d1d5db;
    }

    .btn-secondary:hover {
      background: #e2e8f0;
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

    .order-link {
      color: inherit;
      text-decoration: none;
      cursor: pointer;
      font-weight: inherit;
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .order-link:visited,
    .order-link:active,
    .order-link:focus {
      color: inherit;
      text-decoration: none;
    }

    .order-link:hover {
      color: #1e293b;
      text-decoration: underline dotted;
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

      .search-container {
        order: -1;
        max-width: none;
      }

      .users-table {
        font-size: 0.75rem;
      }

      .users-table th,
      .users-table td {
        padding: 12px 8px;
      }

      .user-info-cell {
        gap: 8px;
      }

      .user-mini-avatar {
        width: 32px;
        height: 32px;
        font-size: 0.75rem;
      }

      .stats-cards {
        grid-template-columns: 1fr 1fr;
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
          <h1>Gerenciamento de Usuários</h1>
          <p class="header-subtitle">Visualize e gerencie todos os usuários do sistema</p>
        </div>

        <!-- Search Bar -->
        <div class="search-container">
          <div class="search-wrapper">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="searchInput" class="search-input" placeholder="Pesquisar usuários...">
          </div>
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

      <!-- Success Message -->
      <?php if (!empty($mensagemSucesso)): ?>
        <div style="
        background-color: #dcfce7;
        color: #166534;
        padding: 12px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-weight: 500;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    ">
          <?= htmlspecialchars($mensagemSucesso) ?>
        </div>
      <?php endif; ?>

      <!-- Statistics Cards -->
      <div class="stats-cards">
        <?php
        $totalUsuarios = count($usuarios);
        $usuariosAtivos = count(array_filter($usuarios, fn($u) => $u['ativo']));
        $usuariosInativos = $totalUsuarios - $usuariosAtivos;
        $admins = count(array_filter($usuarios, fn($u) => $u['perfil'] === 'admin'));
        ?>
        <div class="stat-card">
          <div class="stat-number"><?= $totalUsuarios ?></div>
          <div class="stat-label">Total de Usuários</div>
        </div>
        <div class="stat-card success">
          <div class="stat-number"><?= $usuariosAtivos ?></div>
          <div class="stat-label">Usuários Ativos</div>
        </div>
        <div class="stat-card danger">
          <div class="stat-number"><?= $usuariosInativos ?></div>
          <div class="stat-label">Usuários Inativos</div>
        </div>
        <div class="stat-card warning">
          <div class="stat-number"><?= $admins ?></div>
          <div class="stat-label">Administradores</div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="action-buttons">
        <a href="../create/create_user.php" class="btn btn-primary">
          <i class="fas fa-user-plus"></i>
          Novo Usuário
        </a>
      </div>

      <!-- Users Section -->
      <div class="users-section">
        <h2 class="section-title">
          <i class="fas fa-users"></i>
          Usuários Cadastrados (<?= count($usuarios) ?>)
        </h2>

        <?php if (count($usuarios) > 0): ?>
          <div class="table-container">
            <table class="users-table">
              <thead>
                <tr>
                  <th>
                    <a class="order-link" href="<?= urlOrdenar('nome') ?>">
                      <span>Usuário</span>
                      <span><?= iconeOrdenacao('nome') ?></span>
                    </a>
                  </th>
                  <th>
                    <a class="order-link" href="<?= urlOrdenar('departamento') ?>">
                      <span>Departamento</span>
                      <span><?= iconeOrdenacao('departamento') ?></span>
                    </a>
                  </th>
                  <th>
                    <a class="order-link" href="<?= urlOrdenar('perfil') ?>">
                      <span>Perfil</span>
                      <span><?= iconeOrdenacao('perfil') ?></span>
                    </a>
                  </th>
                  <th>
                    <a class="order-link" href="<?= urlOrdenar('ativo') ?>">
                      <span>Status</span>
                      <span><?= iconeOrdenacao('ativo') ?></span>
                    </a>
                  </th>
                  <th>
                    <a class="order-link" href="<?= urlOrdenar('ultimo_login') ?>">
                      <span>Último Login</span>
                      <span><?= iconeOrdenacao('ultimo_login') ?></span>
                    </a>
                  </th>
                  <th>
                    <a class="order-link" href="<?= urlOrdenar('data_admissao') ?>">
                      <span>Data Admissão</span>
                      <span><?= iconeOrdenacao('data_admissao') ?></span>
                    </a>
                  </th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($usuarios as $u): ?>
                  <tr onclick="openUserModal(<?= $u['id'] ?>)">
                    <td>
                      <div class="user-info-cell">
                        <div class="user-mini-avatar">
                          <?= strtoupper(substr($u['nome'], 0, 1)) ?>
                        </div>
                        <div class="user-info-text">
                          <div class="user-name"><?= htmlspecialchars($u['nome']) ?></div>
                          <div class="user-email"><?= htmlspecialchars($u['email']) ?></div>
                          <div class="user-matricula">Matrícula: <?= htmlspecialchars($u['matricula']) ?></div>
                        </div>
                      </div>
                    </td>
                    <td>
                      <div class="department-info">
                        <div class="department-name"><?= formatarDepartamento($u['departamento']) ?></div>
                        <div class="department-role"><?= htmlspecialchars($u['cargo'] ?? 'Cargo não informado') ?></div>
                      </div>
                    </td>
                    <td>
                      <span class="perfil-badge <?= getPerfilBadge($u['perfil']) ?>">
                        <?= htmlspecialchars(ucfirst($u['perfil'])) ?>
                      </span>
                    </td>
                    <td>
                      <span class="status-badge <?= getStatusBadge($u['ativo']) ?>">
                        <?= $u['ativo'] ? 'Ativo' : 'Inativo' ?>
                      </span>
                    </td>
                    <td>
                      <div class="login-stats">
                        <div class="login-count"><?= $u['total_logins'] ?? 0 ?> logins</div>
                        <div class="last-login"><?= formatarDataHora($u['ultimo_login']) ?></div>
                      </div>
                    </td>
                    <td>
                      <div class="date-info">
                        <?= formatarData($u['data_admissao']) ?>
                        <?php if ($u['dias_empresa'] !== null): ?>
                          <div style="font-size: 0.7rem; color: #94a3b8;">
                            <?= $u['dias_empresa'] ?> dias na empresa
                          </div>
                        <?php endif; ?>
                      </div>
                    </td>
                    <td onclick="event.stopPropagation()">
                      <div class="action-cell">
                        <a href="../update/update_user.php?id=<?= $u['id'] ?>" class="action-btn btn-edit" title="Editar">
                          <i class="fas fa-edit"></i>
                        </a>
                        <a href="../delete/delete_user.php?id=<?= $u['id'] ?>" 
                           class="action-btn btn-delete" 
                           title="Excluir"
                           onclick="return confirm('Tem certeza que deseja excluir este usuário?')">
                          <i class="fas fa-trash"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <i class="fas fa-users"></i>
            <h3>Nenhum usuário encontrado</h3>
            <p>Não há usuários cadastrados no sistema ainda.</p>
            <br>
            <a href="../create/create_user.php" class="btn btn-primary">
              <i class="fas fa-user-plus"></i>
              Cadastrar Primeiro Usuário
            </a>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>

  <!-- User Detail Modal -->
  <div id="userModal" class="modal-overlay">
    <div class="modal">
      <div class="modal-header">
        <div class="modal-user-info">
          <div class="modal-avatar" id="modalAvatar">U</div>
          <div class="modal-user-details">
            <h2 id="modalUserName">Nome do Usuário</h2>
            <div class="modal-user-email" id="modalUserEmail">email@exemplo.com</div>
          </div>
        </div>
        <button class="btn-close" onclick="closeUserModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      
      <div class="modal-body">
        <!-- Informações Pessoais -->
        <div class="modal-section">
          <h3 class="modal-section-title">
            <i class="fas fa-user"></i>
            Informações Pessoais
          </h3>
          <div class="info-grid">
            <div class="info-item">
              <div class="info-label">Matrícula</div>
              <div class="info-value" id="modalMatricula">-</div>
            </div>
            <div class="info-item">
              <div class="info-label">Telefone</div>
              <div class="info-value" id="modalTelefone">-</div>
            </div>
            <div class="info-item">
              <div class="info-label">Departamento</div>
              <div class="info-value" id="modalDepartamento">-</div>
            </div>
            <div class="info-item">
              <div class="info-label">Cargo</div>
              <div class="info-value" id="modalCargo">-</div>
            </div>
            <div class="info-item">
              <div class="info-label">Data de Admissão</div>
              <div class="info-value" id="modalDataAdmissao">-</div>
            </div>
          </div>
        </div>

        <!-- Sistema -->
        <div class="modal-section">
          <h3 class="modal-section-title">
            <i class="fas fa-cog"></i>
            Informações do Sistema
          </h3>
          <div class="info-grid">
            <div class="info-item">
              <div class="info-label">Perfil</div>
              <div class="info-value" id="modalPerfil">-</div>
            </div>
            <div class="info-item">
              <div class="info-label">Status</div>
              <div class="info-value" id="modalStatus">-</div>
            </div>
            <div class="info-item">
              <div class="info-label">Último Login</div>
              <div class="info-value" id="modalUltimoLogin">-</div>
            </div>
          </div>
        </div>

        <!-- Permissões -->
        <div class="modal-section">
          <h3 class="modal-section-title">
            <i class="fas fa-shield-alt"></i>
            Permissões
          </h3>
          <div id="modalPermissions" class="permissions-list">
            <!-- Permissões serão inseridas aqui via JavaScript -->
          </div>
        </div>

        <!-- Estatísticas -->
        <div class="modal-section">
          <h3 class="modal-section-title">
            <i class="fas fa-chart-bar"></i>
            Estatísticas
          </h3>
          <div class="stats-grid">
            <div class="stat-item">
              <div class="stat-item-value" id="modalTotalLogins">0</div>
              <div class="stat-item-label">Total de Logins</div>
            </div>
            <div class="stat-item">
              <div class="stat-item-value" id="modalDaysActive">0</div>
              <div class="stat-item-label">Dias Ativo</div>
            </div>
            <div class="stat-item">
              <div class="stat-item-value" id="modalLastActivity">-</div>
              <div class="stat-item-label">Última Atividade</div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" onclick="closeUserModal()">Fechar</button>
        <a id="modalEditButton" href="#" class="btn btn-primary">
          <i class="fas fa-edit"></i>
          Editar Usuário
        </a>
      </div>
    </div>
  </div>

  <script>
    // Dados dos usuários para JavaScript
    const usersData = <?= json_encode($usuariosJS) ?>;

    // Search functionality
    let searchTimeout;
    const searchInput = document.getElementById('searchInput');
    const tableRows = document.querySelectorAll('.users-table tbody tr');

    searchInput.addEventListener('input', function() {
      const query = this.value.toLowerCase().trim();
      
      clearTimeout(searchTimeout);
      
      searchTimeout = setTimeout(() => {
        tableRows.forEach(row => {
          const text = row.textContent.toLowerCase();
          const matches = text.includes(query);
          row.style.display = matches ? '' : 'none';
        });
      }, 300);
    });

    // Modal functionality
    function openUserModal(userId) {
      const user = usersData[userId];
      if (!user) return;

      // Atualizar avatar
      const avatar = document.getElementById('modalAvatar');
      avatar.textContent = user.nome.charAt(0).toUpperCase();

      // Atualizar informações básicas
      document.getElementById('modalUserName').textContent = user.nome;
      document.getElementById('modalUserEmail').textContent = user.email;

      // Atualizar detalhes
      document.getElementById('modalMatricula').textContent = user.matricula;
      document.getElementById('modalTelefone').textContent = user.telefone || 'Não informado';
      document.getElementById('modalDepartamento').textContent = capitalizeFirst(user.departamento) || 'Não informado';
      document.getElementById('modalCargo').textContent = user.cargo || 'Não informado';
      
      // Formatar data de admissão
      if (user.data_admissao) {
        const date = new Date(user.data_admissao + 'T00:00:00');
        document.getElementById('modalDataAdmissao').textContent = date.toLocaleDateString('pt-BR');
      } else {
        document.getElementById('modalDataAdmissao').textContent = 'Não informado';
      }

      // Atualizar perfil
      const perfilElement = document.getElementById('modalPerfil');
      const perfilClass = getPerfilClass(user.perfil);
      perfilElement.innerHTML = `<span class="perfil-badge ${perfilClass}">${capitalizeFirst(user.perfil)}</span>`;

      // Atualizar status
      const statusElement = document.getElementById('modalStatus');
      const statusClass = user.ativo ? 'status-ativo' : 'status-inativo';
      const statusText = user.ativo ? 'Ativo' : 'Inativo';
      statusElement.innerHTML = `<span class="status-badge ${statusClass}">${statusText}</span>`;

      // Atualizar último login
      const ultimoLoginElement = document.getElementById('modalUltimoLogin');
      if (user.ultimo_login) {
        const loginDate = new Date(user.ultimo_login);
        ultimoLoginElement.textContent = formatDateTime(loginDate);
      } else {
        ultimoLoginElement.textContent = 'Nunca';
        ultimoLoginElement.classList.add('empty');
      }

      // Atualizar permissões
      updatePermissions(user.permissions);

      // Atualizar estatísticas
      document.getElementById('modalTotalLogins').textContent = user.total_logins;
      
      // Calcular dias ativo
      const createdDate = new Date(user.criado_em);
      const today = new Date();
      const daysActive = Math.floor((today - createdDate) / (1000 * 60 * 60 * 24));
      document.getElementById('modalDaysActive').textContent = daysActive;

      // Última atividade (simulado)
      const lastActivityElement = document.getElementById('modalLastActivity');
      if (user.ultimo_login) {
        const loginDate = new Date(user.ultimo_login);
        const diffDays = Math.floor((today - loginDate) / (1000 * 60 * 60 * 24));
        if (diffDays === 0) {
          lastActivityElement.textContent = 'Hoje';
        } else if (diffDays === 1) {
          lastActivityElement.textContent = 'Ontem';
        } else {
          lastActivityElement.textContent = `${diffDays} dias`;
        }
      } else {
        lastActivityElement.textContent = 'Nunca';
      }

      // Atualizar botão de editar
      document.getElementById('modalEditButton').href = `../update/update_user.php?id=${user.id}`;

      // Mostrar modal
      document.getElementById('userModal').classList.add('show');
    }

    // Função para fechar o modal
    function closeUserModal() {
      document.getElementById('userModal').classList.remove('show');
    }

    // Função para atualizar permissões
    function updatePermissions(permissions) {
      const permissionsContainer = document.getElementById('modalPermissions');
      const permissionNames = {
        'gerenciar_usuarios': 'Gerenciar Usuários',
        'listar_produtos': 'Listar Produtos',
        'cadastrar_produtos': 'Cadastrar Produtos',
        'editar_produtos': 'Editar Produtos',
        'excluir_produtos': 'Excluir Produtos'
      };

      const html = permissions.map(permission => {
        const name = permissionNames[permission] || permission.replace('_', ' ');
        return `<span class="permission-tag">${name}</span>`;
      }).join('');

      permissionsContainer.innerHTML = html || '<span class="permission-tag" style="background: #fee2e2; color: #dc2626;">Nenhuma permissão</span>';
    }

    // Função para obter classe do perfil
    function getPerfilClass(perfil) {
      const classes = {
        'admin': 'perfil-admin',
        'estoquista': 'perfil-estoquista',
        'visualizador': 'perfil-visualizador'
      };
      return classes[perfil] || 'perfil-visualizador';
    }

    // Função para capitalizar primeira letra
    function capitalizeFirst(str) {
      if (!str) return '';
      return str.charAt(0).toUpperCase() + str.slice(1);
    }

    // Função para formatar data e hora
    function formatDateTime(date) {
      return date.toLocaleDateString('pt-BR') + ' às ' + date.toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit'
      });
    }

    // Fechar modal ao clicar no overlay
    document.getElementById('userModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeUserModal();
      }
    });

    // Fechar modal com ESC
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeUserModal();
      }
    });

    // Prevenir scroll do body quando modal estiver aberto
    const modal = document.getElementById('userModal');
    const observer = new MutationObserver(function(mutations) {
      mutations.forEach(function(mutation) {
        if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
          if (modal.classList.contains('show')) {
            document.body.style.overflow = 'hidden';
          } else {
            document.body.style.overflow = '';
          }
        }
      });
    });

    observer.observe(modal, {
      attributes: true,
      attributeFilter: ['class']
    });

    // Efeitos visuais adicionais
    document.addEventListener('DOMContentLoaded', function() {
      // Adicionar efeito hover nas linhas da tabela
      const tableRows = document.querySelectorAll('.users-table tbody tr');
      tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
          this.style.transform = 'translateX(2px)';
        });
        
        row.addEventListener('mouseleave', function() {
          this.style.transform = 'translateX(0)';
        });
      });

      // Efeito de loading nos botões
      const buttons = document.querySelectorAll('.btn');
      buttons.forEach(button => {
        button.addEventListener('click', function(e) {
          // Efeito ripple
          const ripple = document.createElement('span');
          ripple.style.position = 'absolute';
          ripple.style.borderRadius = '50%';
          ripple.style.background = 'rgba(255,255,255,0.3)';
          ripple.style.transform = 'scale(0)';
          ripple.style.animation = 'ripple 0.6s linear';
          ripple.style.left = (e.offsetX - 10) + 'px';
          ripple.style.top = (e.offsetY - 10) + 'px';
          ripple.style.width = '20px';
          ripple.style.height = '20px';
          
          this.style.position = 'relative';
          this.style.overflow = 'hidden';
          this.appendChild(ripple);
          
          setTimeout(() => {
            ripple.remove();
          }, 600);
        });
      });
    });

    // CSS para animação ripple
    const style = document.createElement('style');
    style.textContent = `
      @keyframes ripple {
        to {
          transform: scale(4);
          opacity: 0;
        }
      }
    `;
    document.head.appendChild(style);
  </script>
</body>

</html>