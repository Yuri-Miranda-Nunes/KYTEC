<?php
session_start();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Login ‑ Sistema de Estoque</title>
  <style>
    body { font-family: Arial; max-width: 400px; margin: 50px auto; }
    form { display: flex; flex-direction: column; }
    input, button { margin-bottom: 10px; padding: 8px; font-size: 1rem; }
    .erro { color: red; }
  </style>
</head>
<body>

  <h2>Login</h2>

  <?php if (!empty($_SESSION['erro'])): ?>
    <p class="erro"><?= $_SESSION['erro']; unset($_SESSION['erro']); ?></p>
  <?php endif; ?>

  <form action="verifica_login.php" method="post">
    <input type="email" name="email" placeholder="E‑mail" required>
    <input type="password" name="senha" placeholder="Senha" required>
    <button type="submit">Entrar</button>
  </form>
</body>
</html>
