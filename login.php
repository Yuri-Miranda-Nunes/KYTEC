<?php
session_start();

// Se já estiver logado, redireciona para dashboard
if (!empty($_SESSION['logado']) && $_SESSION['logado'] === true) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login ‑ Sistema de Estoque</title>
  <style>
    body { 
      font-family: Arial, sans-serif; 
      max-width: 400px; 
      margin: 50px auto; 
      padding: 20px;
      background-color: #f5f5f5;
    }
    .container {
      background: white;
      padding: 30px;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    form { 
      display: flex; 
      flex-direction: column; 
    }
    input, button { 
      margin-bottom: 15px; 
      padding: 12px; 
      font-size: 1rem;
      border: 1px solid #ddd;
      border-radius: 4px;
    }
    button {
      background-color: #007bff;
      color: white;
      border: none;
      cursor: pointer;
      font-weight: bold;
    }
    button:hover {
      background-color: #0056b3;
    }
    .erro { 
      color: #dc3545; 
      background-color: #f8d7da;
      border: 1px solid #f5c6cb;
      padding: 10px;
      border-radius: 4px;
      margin-bottom: 15px;
    }
    .demo-info {
      margin: 20px 0;
      padding: 14px;
      border: 1px solid #cfe2ff;
      border-radius: 6px;
      background: #eef6ff;
      color: #193b63;
      font-size: .9rem;
      line-height: 1.6;
    }
    .demo-info p {
      margin: 0 0 8px;
    }
    .demo-info p:last-child {
      margin-bottom: 0;
    }
    .demo-info code {
      overflow-wrap: anywhere;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Login - Sistema de Estoque</h2>

    <div class="demo-info">
      <p><strong>Demonstração para recrutadores</strong></p>
      <p>E-mail: <code>demo@kytec.local</code><br>Senha: <code>KYTEC-Demo-Estoque-2026!</code></p>
      <p>Use apenas os dados fictícios. As alterações são compartilhadas com outros visitantes.</p>
    </div>

    <?php if (!empty($_SESSION['erro'])): ?>
      <div class="erro">
        <?= htmlspecialchars($_SESSION['erro']); ?>
        <?php unset($_SESSION['erro']); ?>
      </div>
    <?php endif; ?>

    <form action="class/verifica_login.php" method="post">
      <input type="email" name="email" placeholder="E‑mail" required>
      <input type="password" name="senha" placeholder="Senha" required>
      <button type="submit">Entrar</button>
    </form>
  </div>
</body>
</html>
