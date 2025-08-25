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
$bd = new BancoDeDados();

// Parâmetros de ordenação
$ordem = $_GET['ordem'] ?? 'nome_empresa';
$direcao = $_GET['direcao'] ?? 'asc';

// Colunas permitidas para ordenação (segurança)
$colunasPermitidas = ['nome_empresa', 'atividade', 'telefone_representante', 'nome_representante', 'email_representante', 'endereco'];

// Validar ordem e direção
if (!in_array($ordem, $colunasPermitidas)) {
  $ordem = 'nome_empresa';
}
if (!in_array($direcao, ['asc', 'desc'])) {
  $direcao = 'asc';
}

$sql = "SELECT * FROM fornecedores ORDER BY {$ordem} {$direcao}";
$stmt = $bd->pdo->query($sql);
$fornecedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lista de Fornecedores - Sistema de Estoque</title>
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

    /* Suppliers Table */
    .suppliers-section {
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

    .suppliers-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.875rem;
    }

    .suppliers-table th {
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

    .suppliers-table td {
      padding: 16px 12px;
      border-bottom: 1px solid #f1f5f9;
      color: #64748b;
      vertical-align: top;
    }

    .suppliers-table tbody tr {
      transition: all 0.2s ease;
      cursor: pointer;
    }

    .suppliers-table tbody tr:hover {
      background: #f8fafc;
      transform: translateY(-1px);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .suppliers-table tbody tr:last-child td {
      border-bottom: none;
    }

    .supplier-name {
      font-weight: 500;
      color: #1e293b;
    }

    .supplier-contact {
      color: #64748b;
      font-size: 0.875rem;
    }

    .activity-cell {
      max-width: 200px;
    }

    .activity-text {
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      text-overflow: ellipsis;
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

    /* Success Message */
    .alert-success {
      background-color: #dcfce7;
      color: #166534;
      padding: 12px 20px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-weight: 500;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .order-link {
      color: inherit;
      text-decoration: none;
      cursor: pointer;
      font-weight: inherit;
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

    /* Color Schemes para icons dos resultados de pesquisa */
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
        padding: 16px;
      }

      .header-right {
        flex-direction: column;
        gap: 12px;
        width: 100%;
      }

      .suppliers-table {
        font-size: 0.75rem;
      }

      .suppliers-table th,
      .suppliers-table td {
        padding: 12px 8px;
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
          <h1>Lista de Fornecedores</h1>
          <p class="header-subtitle">Gerencie e visualize todos os fornecedores cadastrados</p>
        </div>

        <!-- Search Bar -->
        <div class="search-container">
          <div class="search-wrapper">
            <i class="fas fa-search search-icon"></i>
            <input type="text"
              id="searchInput"
              class="search-input"
              placeholder="Pesquisar fornecedores..."
              autocomplete="off">

            <div id="searchResults" class="search-results"></div>
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

          <a href="logout.php" class="btn-logout">
            <i class="fas fa-sign-out-alt"></i>
            Sair
          </a>
        </div>
      </div>

      <!-- Success Message -->
      <?php if (!empty($mensagemSucesso)): ?>
        <div class="alert-success">
          <?= htmlspecialchars($mensagemSucesso) ?>
        </div>
      <?php endif; ?>

      <!-- Action Buttons -->
      <div class="action-buttons">
        <a href="../create/create_supplier.php" class="btn btn-primary">
          <i class="fas fa-truck"></i>
          Novo Fornecedor
        </a>
      </div>

      <!-- Suppliers Section -->
      <div class="suppliers-section">
        <h2 class="section-title">
          <i class="fas fa-truck"></i>
          Fornecedores Cadastrados
        </h2>

        <?php if (count($fornecedores) > 0): ?>
          <div class="table-container">
            <table class="suppliers-table">
              <thead>
                <tr>
                  <th><a class="order-link" href="<?= urlOrdenar('nome_empresa') ?>">Nome <?= iconeOrdenacao('nome_empresa') ?></a></th>
                  <th><a class="order-link" href="<?= urlOrdenar('atividade') ?>">Atividade <?= iconeOrdenacao('atividade') ?></a></th>
                  <th><a class="order-link" href="<?= urlOrdenar('telefone_representante') ?>">Contato <?= iconeOrdenacao('telefone_representante') ?></a></th>
                  <th><a class="order-link" href="<?= urlOrdenar('nome_representante') ?>">Representante <?= iconeOrdenacao('nome_representante') ?></a></th>
                  <th><a class="order-link" href="<?= urlOrdenar('email_representante') ?>">Email Representante <?= iconeOrdenacao('email_representante') ?></a></th>
                  <th><a class="order-link" href="<?= urlOrdenar('endereco') ?>">Endereço <?= iconeOrdenacao('endereco') ?></a></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($fornecedores as $f): ?>
                  <tr onclick="window.location.href='visualizar.php?id=<?= $f['id_fornecedor'] ?>'">
                    <td class="supplier-name"><?= htmlspecialchars($f['nome_empresa']) ?></td>
                    <td class="activity-cell">
                      <div class="activity-text">
                        <?= htmlspecialchars(strlen($f['atividade']) > 50 ? substr($f['atividade'], 0, 50) . '...' : $f['atividade']) ?>
                      </div>
                    </td>
                    <td class="supplier-contact">
                      <?= $f['telefone_representante'] ? htmlspecialchars($f['telefone_representante']) : '-' ?>
                    </td>
                    <td><?= $f['nome_representante'] ? htmlspecialchars($f['nome_representante']) : '-' ?></td>
                    <td class="supplier-contact">
                      <?= $f['email_representante'] ? htmlspecialchars($f['email_representante']) : '-' ?>
                    </td>
                    <td>
                      <?= $f['endereco'] ? htmlspecialchars(strlen($f['endereco']) > 40 ? substr($f['endereco'], 0, 40) . '...' : $f['endereco']) : '-' ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <i class="fas fa-truck"></i>
            <h3>Nenhum fornecedor encontrado</h3>
            <p>Não há fornecedores cadastrados no sistema ainda.</p>
            <br>
            <a href="../create/create_supplier" class="btn btn-primary">
              <i class="fas fa-truck"></i>
              Cadastrar Primeiro Fornecedor
            </a>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>

  <script>
    // Search functionality for local filtering
    let searchTimeout;
    const searchInput = document.getElementById('searchInput');
    const tableRows = document.querySelectorAll('.suppliers-table tbody tr');
    const searchResults = document.getElementById('searchResults');

    searchInput.addEventListener('input', function() {
      const query = this.value.toLowerCase().trim();

      // Hide the search results dropdown since we're doing local filtering
      hideSearchResults();

      clearTimeout(searchTimeout);

      searchTimeout = setTimeout(() => {
        filterSuppliers(query);
      }, 300);
    });

    function filterSuppliers(query) {
      let visibleCount = 0;

      tableRows.forEach(row => {
        // Get text content from all cells
        const cells = row.querySelectorAll('td');
        let rowText = '';

        cells.forEach(cell => {
          // Include all cell content for supplier search
          rowText += cell.textContent.toLowerCase() + ' ';
        });

        const matches = rowText.includes(query) || query === '';

        if (matches) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });

      // Update section title with count
      updateSupplierCount(visibleCount);

      // Show/hide empty state
      toggleEmptyState(visibleCount === 0 && query !== '');
    }

    function updateSupplierCount(count) {
      const sectionTitle = document.querySelector('.section-title');
      const totalSuppliers = tableRows.length;

      if (searchInput.value.trim()) {
        sectionTitle.innerHTML = `
            <i class="fas fa-truck"></i>
            Fornecedores Encontrados (${count} de ${totalSuppliers})
        `;
      } else {
        sectionTitle.innerHTML = `
            <i class="fas fa-truck"></i>
            Fornecedores Cadastrados (${totalSuppliers})
        `;
      }
    }

    function toggleEmptyState(show) {
      let emptyState = document.getElementById('searchEmptyState');
      const tableContainer = document.querySelector('.table-container');

      if (show && !emptyState) {
        emptyState = document.createElement('div');
        emptyState.id = 'searchEmptyState';
        emptyState.className = 'empty-state';
        emptyState.innerHTML = `
            <i class="fas fa-search"></i>
            <h3>Nenhum fornecedor encontrado</h3>
            <p>Tente ajustar sua pesquisa ou limpe o campo de busca.</p>
            <br>
            <button onclick="clearSearch()" class="btn btn-primary">
                <i class="fas fa-times"></i>
                Limpar Busca
            </button>
        `;

        // Insert after table container
        tableContainer.parentNode.insertBefore(emptyState, tableContainer.nextSibling);
        tableContainer.style.display = 'none';
      } else if (!show && emptyState) {
        emptyState.remove();
        tableContainer.style.display = 'block';
      } else if (show && emptyState) {
        tableContainer.style.display = 'none';
        emptyState.style.display = 'block';
      } else if (!show && emptyState) {
        tableContainer.style.display = 'block';
        emptyState.style.display = 'none';
      }
    }

    function clearSearch() {
      searchInput.value = '';
      filterSuppliers('');
      searchInput.focus();
    }

    // Hide search results dropdown functions (keeping for compatibility)
    function hideSearchResults() {
      if (searchResults) {
        searchResults.classList.remove('show');
      }
    }

    // Hide results when clicking outside (modified to not interfere with local search)
    document.addEventListener('click', function(e) {
      if (!e.target.closest('.search-container')) {
        hideSearchResults();
      }
    });

    // Enhanced keyboard navigation for search
    searchInput.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        clearSearch();
        searchInput.blur();
      } else if (e.key === 'Enter') {
        e.preventDefault();
        // Focus on first visible row if any
        const firstVisibleRow = document.querySelector('.suppliers-table tbody tr[style=""], .suppliers-table tbody tr:not([style*="none"])');
        if (firstVisibleRow) {
          firstVisibleRow.click();
        }
      }
    });

    // Add CSS for search highlighting and effects
    const searchStyle = document.createElement('style');
    searchStyle.textContent = `
    .search-highlight {
        background-color: #fef3c7;
        color: #d97706;
        padding: 1px 2px;
        border-radius: 2px;
        font-weight: 500;
    }
    
    .search-wrapper {
        transition: transform 0.2s ease;
    }
`;
    document.head.appendChild(searchStyle);

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
      // Set up initial state
      updateSupplierCount(tableRows.length);

      // Add placeholder text with supplier count
      searchInput.placeholder = `Pesquisar entre ${tableRows.length} fornecedores...`;

      // Focus enhancement
      searchInput.addEventListener('focus', function() {
        this.parentElement.style.transform = 'scale(1.02)';
      });

      searchInput.addEventListener('blur', function() {
        this.parentElement.style.transform = 'scale(1)';
      });
    });

    // Adiciona feedback visual no clique das linhas da tabela
    document.querySelectorAll('.suppliers-table tbody tr').forEach(row => {
      row.addEventListener('click', function() {
        this.style.transform = 'scale(0.98)';
        setTimeout(() => {
          this.style.transform = '';
        }, 150);
      });
    });

    // Adiciona feedback visual no clique das linhas da tabela
    document.querySelectorAll('.suppliers-table tbody tr').forEach(row => {
      row.addEventListener('click', function() {
        this.style.transform = 'scale(0.98)';
        setTimeout(() => {
          this.style.transform = '';
        }, 150);
      });
    });
  </script>
</body>

</html>