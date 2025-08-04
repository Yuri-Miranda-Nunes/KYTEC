<?php
session_start();

$mensagemSucesso = '';
if (isset($_SESSION['mensagem_sucesso'])) {
  $mensagemSucesso = $_SESSION['mensagem_sucesso'];
  unset($_SESSION['mensagem_sucesso']); // exibe uma vez só
}

// Verifica se está logado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
  header("Location: login.php");
  exit;
}

// Função para verificar permissões
function temPermissao($permissao)
{
  return in_array($permissao, $_SESSION['permissoes'] ?? []);
}

require_once '../conexao.php';
$bd = new BancoDeDados();
$sql = "SELECT * FROM fornecedores ORDER BY nome_empresa ASC";
$stmt = $bd->pdo->query($sql);
$fornecedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

      .suppliers-table {
        font-size: 0.75rem;
      }

      .suppliers-table th,
      .suppliers-table td {
        padding: 12px 8px;
      }
    }

    /* Success Message */
    .alert-success {
      background-color: #dcfce7;
      color: #166534;
      padding: 12px 20px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-weight: 500;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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
                        <a href="index.php" class="nav-link">
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
                            <a href="listar_produtos.php" class="nav-link">
                                <i class="fas fa-list"></i>
                                <span>Listar Produtos</span>
                            </a>
                        </div>
                        <?php if (temPermissao('cadastrar_produtos')): ?>
                            <div class="nav-item">
                                <a href="cadastrar_prod.php" class="nav-link">
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
                        <a href="listar_fornecedores.php" class="nav-link active">
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
                            <a href="listar_usuarios.php" class="nav-link">
                                <i class="fas fa-users"></i>
                                <span>Listar Usuários</span>
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="cadastrar_usuario.php" class="nav-link">
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
                        <a href="logout.php" class="nav-link">
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
        <?php if (!empty($mensagemSucesso)): ?>
          <div class="alert-success">
            <?= htmlspecialchars($mensagemSucesso) ?>
          </div>
        <?php endif; ?>

        <div class="header-left">
          <h1>Lista de Fornecedores</h1>
          <p class="header-subtitle">Gerencie e visualize todos os fornecedores cadastrados</p>
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
          <a href="logout.php" class="btn-logout">
            <i class="fas fa-sign-out-alt"></i>
            Sair
          </a>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="action-buttons">
        <a href="cadastrar_fornecedor.php" class="btn btn-primary">
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
                  <th>Nome</th>
                  <th>Atividade</th>
                  <th>Contato</th>
                  <th>Representante</th>
                  <th>Email Representante</th>
                  <th>Endereço</th>
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
            <a href="cadastrar_fornecedor.php" class="btn btn-primary">
              <i class="fas fa-truck"></i>
              Cadastrar Primeiro Fornecedor
            </a>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>

  <script>
    // Adiciona feedback visual no clique das linhas
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