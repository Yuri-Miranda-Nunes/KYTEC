
<?php
require_once 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = $_POST['senha'];
    $permissao = $_POST['permissao'];

    // Criptografar senha
    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, permissao) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nome, $email, $senha_hash, $permissao]);

        header("Location: listar_usuarios.php?success=1");
        exit;
    } catch (PDOException $e) {
        echo "Erro ao cadastrar usuário: " . $e->getMessage();
    }
}
?>


<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Cadastrar Usuário</title>
  <style>
    body { font-family: Arial; max-width: 500px; margin: auto; }
    label { display: block; margin-top: 10px; }
  </style>
</head>
<body>

<h2>Cadastrar Novo Usuário</h2>
<?php if ($mensagem): ?>
  <p><strong><?= $mensagem ?></strong></p>
<?php endif; ?>

<form method="post">
  <label>Nome:
    <input type="text" name="nome" required>
  </label>

  <label>Email:
    <input type="email" name="email" required>
  </label>

  <label>Senha:
    <input type="password" name="senha" required>
  </label>

  <fieldset>
    <legend>Permissões</legend>
    <?php foreach ($permissoes_possiveis as $permissao): ?>
      <label>
        <input type="checkbox" name="permissoes[]" value="<?= $permissao ?>">
        <?= ucfirst(str_replace('_', ' ', $permissao)) ?>
      </label>
    <?php endforeach; ?>
  </fieldset>

  <button type="submit">Cadastrar</button>
</form>

</body>
</html>
