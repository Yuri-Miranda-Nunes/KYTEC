<?php
session_start();

if (!in_array('listar_produtos', $_SESSION['permissoes'])) {
  echo "Acesso negado.";
  exit;
}

require_once 'conexao.php';
$bd = new BancoDeDados();
$sql = "SELECT p.*, c.nome as categoria_nome 
        FROM produtos p 
        LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
        ORDER BY p.nome ASC";
$stmt = $bd->pdo->query($sql);
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
</head>
<style>
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
</style>

<body>
  <aside class="sidebar">
    <div class="sidebar-header">
      <h2><i class="fas fa-boxes"></i> KYTEC</h2>
    </div>

    <nav class="sidebar-nav">
      <!-- Dashboard -->
      <div class="nav-section">
        <div class="nav-item">
          <a href="index.php" class="nav-link active">
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
        </div>
      <?php endif; ?>

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
  
  <h2>Lista de Produtos</h2>
  <table border="1">
    <tr>
      <th>ID</th>
      <th>Nome</th>
      <th>Tipo</th>
      <th>Categoria</th>
      <th>Estoque</th>
      <th>Preço Venda</th>
    </tr>
    <?php foreach ($produtos as $p): ?>
      <tr>
        <td><?= $p['id_produto'] ?></td>
        <td><?= $p['nome'] ?></td>
        <td><?= $p['tipo'] ?></td>
        <td><?= $p['categoria_nome'] ?? 'Sem Categoria' ?></td>
        <td><?= $p['estoque_atual'] ?></td>
        <td>R$ <?= number_format($p['preco_venda'], 2, ',', '.') ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</body>

</html>