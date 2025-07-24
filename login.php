<?php
session_start();

// Se já estiver logado, redireciona para dashboard
if (!empty($_SESSION['logado']) && $_SESSION['logado'] === true) {
    header('Location: dashboard.php');
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
    .debug-link {
      font-size: 0.8rem;
      color: #666;
      text-align: center;
      margin-top: 10px;
    }
    .debug-link a {
      color: #666;
      text-decoration: none;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Login - Sistema de Estoque</h2>

    <?php if (!empty($_SESSION['erro'])): ?>
      <div class="erro">
        <?= htmlspecialchars($_SESSION['erro']); ?>
        <?php unset($_SESSION['erro']); ?>
      </div>
    <?php endif; ?>

    <form action="verifica_login.php" method="post">
      <input type="email" name="email" placeholder="E‑mail" required>
      <input type="password" name="senha" placeholder="Senha" required>
      <button type="submit">Entrar</button>
    </form>
    
    <!-- Link para debug (remova em produção) -->
    <div class="debug-link">
      <a href="verifica_login.php?debug=1" onclick="return confirm('Modo debug ativado. Continuar?')">
        Debug Mode
      </a>
    </div>
  </div>
</body>
</html>
