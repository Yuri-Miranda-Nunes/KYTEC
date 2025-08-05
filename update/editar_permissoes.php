<?php
require_once '../conexao.php';
$id = $_GET['id'] ?? 0;

// Todas permissões possíveis:
$permissoes_possiveis = [
  'listar_produtos',
  'cadastrar_produtos',
  'editar_produtos',
  'excluir_produtos',
  'gerenciar_usuarios'
];

// Permissões atuais do usuário
$stmt = $pdo->prepare("SELECT nome_permissao FROM permissoes WHERE usuario_id = ?");
$stmt->execute([$id]);
$permissoes_usuario = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'nome_permissao');

// Atualização via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $novas = $_POST['permissoes'] ?? [];

    // Remove todas
    $pdo->prepare("DELETE FROM permissoes WHERE usuario_id = ?")->execute([$id]);

    // Insere novas
    $stmt = $pdo->prepare("INSERT INTO permissoes (usuario_id, nome_permissao) VALUES (?, ?)");
    foreach ($novas as $permissao) {
        $stmt->execute([$id, $permissao]);
    }

    echo "<p>✅ Permissões atualizadas!</p>";
    $permissoes_usuario = $novas;
}
?>

<h2>Editar Permissões do Usuário #<?= $id ?></h2>
<form method="post">
  <?php foreach ($permissoes_possiveis as $permissao): ?>
    <label>
      <input type="checkbox" name="permissoes[]" value="<?= $permissao ?>" 
        <?= in_array($permissao, $permissoes_usuario) ? 'checked' : '' ?>>
      <?= ucfirst(str_replace('_', ' ', $permissao)) ?>
    </label><br>
  <?php endforeach; ?>
  <button type="submit">Salvar</button>
</form>
