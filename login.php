<?php session_start(); ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Login - Sistema de Estoque</title>
</head>
<body>
  <h2>Login</h2>
  <?php if (!empty($_SESSION['erro'])): ?>
    <p style="color:red"><?= $_SESSION['erro']; unset($_SESSION['erro']); ?></p>
  <?php endif; ?>
  <form action="verifica_login.php" method="POST">
    <input type="email" name="email" placeholder="Email" required><br>
    <input type="password" name="senha" placeholder="Senha" required><br>
    <button type="submit">Entrar</button>
  </form>
</body>
</html>
