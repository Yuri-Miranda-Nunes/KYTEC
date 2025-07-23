<?php
require_once 'conexao.php';

$bd = new BancoDeDados();  // <-- AQUI É O PULO DO GATO
$pdo = $bd->pdo;

$permissoes_possiveis = [
  'listar_produtos',
  'cadastrar_produtos',
  'editar_produtos',
  'excluir_produtos',
  'gerenciar_usuarios'
];

$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
    $permissoes = $_POST['permissoes'] ?? [];

    try {
        // Inserir o usuário
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)");
        if ($stmt->execute([$nome, $email, $senha])) {
            $usuario_id = $pdo->lastInsertId();

            // Inserir permissões
            $stmtPermissao = $pdo->prepare("INSERT INTO permissoes (usuario_id, nome_permissao) VALUES (?, ?)");
            foreach ($permissoes as $permissao) {
                $stmtPermissao->execute([$usuario_id, $permissao]);
            }

            $mensagem = "✅ Usuário cadastrado com sucesso!";
        } else {
            $mensagem = "❌ Erro ao cadastrar usuário.";
        }
    } catch (PDOException $e) {
        $mensagem = "❌ Erro: " . $e->getMessage();
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
