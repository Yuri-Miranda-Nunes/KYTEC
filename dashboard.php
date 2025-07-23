<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}
function temPermissao($permissao) {
    return in_array($permissao, $_SESSION['permissoes'] ?? []);
}

?>


<nav class="menu-lateral">
  <ul>
    <li><strong>Menu</strong></li>

    <?php if (temPermissao('listar_produtos')): ?>
      <li><a href="listar_produtos.php">📦 Produtos</a></li>
    <?php endif; ?>

    <?php if (temPermissao('gerenciar_usuarios')): ?>
      <li><a href="gerenciar_usuarios.php">👥 Usuários</a></li>
    <?php endif; ?>

    <?php if (temPermissao('visualizar_relatorios')): ?>
      <li><a href="relatorios.php">📊 Relatórios</a></li>
    <?php endif; ?>
    
    <li><a href="logout.php">🚪 Sair</a></li>
  </ul>
</nav>


<h1>Bem-vindo, <?= $_SESSION['usuario_nome'] ?>!</h1>
<p>Perfil: <?= $_SESSION['usuario_perfil'] ?></p>

<a href="logout.php">Sair</a>
