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
$colunasPermitidas = ['nome', 'email', 'perfil', 'ativo'];

// Validar ordem e direção
if (!in_array($ordem, $colunasPermitidas)) {
    $ordem = 'nome';
}
if (!in_array($direcao, ['asc', 'desc'])) {
    $direcao = 'asc';
}

$sql = "SELECT * FROM usuarios ORDER BY {$ordem} {$direcao}";
$stmt = $bd->pdo->query($sql);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Função para determinar se a página atual está ativa
function isActivePage($page) {
  $current = basename($_SERVER['PHP_SELF']);
  return $current === $page ? 'active' : '';

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
      vertical-align: top;
    }

    .users-table tbody tr {
      transition: background-color 0.2s ease;
    }

    .users-table tbody tr:hover {
      background: #f8fafc;
    }

    .users-table tbody tr:last-child td {
      border-bottom: none;
    }

    .user-name {
      font-weight: 500;
      color: #1e293b;
    }

    .user-email {
      color: #64748b;
      font-size: 0.875rem;
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
    }
    
    .order-link {
      color: inherit;
      text-decoration: none;
      cursor: pointer;
      font-weight: inherit;
    }
    .order-link:visited, .order-link:active, .order-link:focus {
      color: inherit;
      text-decoration: none;
    }
    .order-link:hover {
      color: #1e293b;
      text-decoration: underline dotted;
    }

    /* Color Schemes for search results */
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
          <h1>Lista de Usuários</h1>
          <p class="header-subtitle">Gerencie e visualize todos os usuários do sistema</p>
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
          Usuários Cadastrados
        </h2>

        <?php if (count($usuarios) > 0): ?>
          <div class="table-container">
            <table class="users-table">
              <thead>
                <tr>
                  <th><a class="order-link" href="<?= urlOrdenar('nome') ?>">Nome <?= iconeOrdenacao('nome') ?></a></th>
                  <th><a class="order-link" href="<?= urlOrdenar('email') ?>">Email <?= iconeOrdenacao('email') ?></a></th>
                  <th><a class="order-link" href="<?= urlOrdenar('perfil') ?>">Perfil <?= iconeOrdenacao('perfil') ?></a></th>
                  <th><a class="order-link" href="<?= urlOrdenar('ativo') ?>">Status <?= iconeOrdenacao('ativo') ?></a></th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($usuarios as $u): ?>
                  <tr>
                    <td class="user-name"><?= htmlspecialchars($u['nome']) ?></td>
                    <td class="user-email"><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                      <?php
                      $perfil = $u['perfil'];
                      $perfilClass = 'perfil-visualizador';
                      if ($perfil === 'admin') $perfilClass = 'perfil-admin';
                      elseif ($perfil === 'estoquista') $perfilClass = 'perfil-estoquista';
                      ?>
                      <span class="perfil-badge <?= $perfilClass ?>">
                        <?= htmlspecialchars(ucfirst($perfil)) ?>
                      </span>
                    </td>
                    <td>
                      <span class="status-badge <?= $u['ativo'] ? 'status-ativo' : 'status-inativo' ?>">
                        <?= $u['ativo'] ? 'Ativo' : 'Inativo' ?>
                      </span>
                    </td>
                    <td>
                      <div style="display: flex; gap: 8px;">
                        <a href="../update/update_user.php?id=<?= $u['id'] ?>"
                          style="color: #3b82f6; font-size: 0.875rem; text-decoration: none;"
                          title="Editar">
                          <i class="fas fa-edit"></i>
                        </a>
                        <a href="../delete/delete_user.php?id=<?= $u['id'] ?>"
                          style="color: #ef4444; font-size: 0.875rem; text-decoration: none;"
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
      fetch(`../class/class_search.php?q=${encodeURIComponent(query)}&from=read_user`)
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

    // Search Manager Class
    class SearchManager {
      constructor() {
        this.searchTimeout = null;
        this.searchInput = document.getElementById('searchInput');
        this.searchResults = document.getElementById('searchResults');
        this.initializeEventListeners();
      }

      initializeEventListeners() {
        // Input event for search
        this.searchInput.addEventListener('input', (e) => {
          const query = e.target.value.trim();

          clearTimeout(this.searchTimeout);

          if (query.length < 2) {
            this.hideSearchResults();
            return;
          }

          this.showLoading();

          this.searchTimeout = setTimeout(() => {
            this.performSearch(query);
          }, 300);
        });

        // Hide results when clicking outside
        document.addEventListener('click', (e) => {
          if (!e.target.closest('.search-container')) {
            this.hideSearchResults();
          }
        });

        // Keyboard navigation
        this.searchInput.addEventListener('keydown', (e) => this.handleKeyNavigation(e));
      }

      async performSearch(query) {
        try {
          const response = await fetch(`../class/class_search.php?q=${encodeURIComponent(query)}`);

          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }

          const text = await response.text();
          console.log('Resposta do servidor:', text);

          let data;
          try {
            data = JSON.parse(text);
          } catch (e) {
            console.error('Erro ao fazer parse do JSON:', e);
            console.error('Texto da resposta:', text);
            throw new Error('Resposta inválida do servidor');
          }

          this.hideLoading();

          if (data.error) {
            this.showError(data.error);
            return;
          }

          this.displayResults(data.results || []);

        } catch (error) {
          console.error('Erro na pesquisa:', error);
          this.hideLoading();
          this.showError('Erro ao realizar pesquisa: ' + error.message);
        }
      }

      displayResults(results) {
        if (results.length === 0) {
          this.searchResults.innerHTML = '<div class="search-no-results">Nenhum resultado encontrado</div>';
          this.showSearchResults();
          return;
        }

        const html = results.map(result => {
          const badgeHtml = result.badge ?
            `<span class="search-result-badge ${result.badgeClass || ''}">${result.badge}</span>` : '';

          return `
                <div class="search-result-item" data-url="${result.url || '#'}" data-type="${result.type}">
                    <div class="search-result-icon ${this.getIconClass(result.type)}">
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

        this.searchResults.innerHTML = html;

        this.addItemEventListeners();
        this.showSearchResults();
      }

      addItemEventListeners() {
        this.searchResults.querySelectorAll('.search-result-item').forEach(item => {
          item.addEventListener('click', () => {
            const url = item.getAttribute('data-url');
            if (url && url !== '#') {
              window.location.href = url;
            }
          });

          item.addEventListener('mouseenter', () => {
            this.searchResults.querySelectorAll('.search-result-item').forEach(i => i.classList.remove('active'));
            item.classList.add('active');
          });
        });
      }

      handleKeyNavigation(e) {
        const items = this.searchResults.querySelectorAll('.search-result-item');
        const activeItem = this.searchResults.querySelector('.search-result-item.active');

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
          const activeItem = this.searchResults.querySelector('.search-result-item.active');
          if (activeItem) {
            activeItem.click();
          }
        } else if (e.key === 'Escape') {
          this.hideSearchResults();
          this.searchInput.blur();
        }
      }

      getIconClass(type) {
        const classes = {
          'produto': 'blue',
          'fornecedor': 'green',
          'usuario': 'purple',
          'secao': 'yellow'
        };
        return classes[type] || 'blue';
      }

      showSearchResults() {
        this.searchResults.classList.add('show');
      }

      hideSearchResults() {
        this.searchResults.classList.remove('show');
        this.searchResults.querySelectorAll('.search-result-item').forEach(item => {
          item.classList.remove('active');
        });
      }

      showLoading() {
        this.searchResults.innerHTML = `
            <div class="search-loading">
                <div class="spinner"></div>
                Pesquisando...
            </div>
        `;
        this.showSearchResults();
      }

      hideLoading() {
        const loading = this.searchResults.querySelector('.search-loading');
        if (loading) {
          loading.remove();
        }
      }

      showError(message) {
        this.searchResults.innerHTML = `<div class="search-no-results" style="color: #dc2626;">${message}</div>`;
        this.showSearchResults();
      }
    }

    // Inicializar quando o DOM estiver carregado
    document.addEventListener('DOMContentLoaded', function() {
      new SearchManager();
    });
  </script>
</body>

</html>
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