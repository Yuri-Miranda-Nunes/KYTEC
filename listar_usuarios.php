<?php
require_once 'conexao.php';
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'admin') {
    echo "Acesso negado.";
    exit;
}

$bd = new BancoDeDados();
$sql = "SELECT * FROM usuarios ORDER BY nome";
$usuarios = $bd->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Usuários Cadastrados</h2>
<table border="1">
  <tr>
    <th>Nome</th>
    <th>Email</th>
    <th>Perfil</th>
    <th>Status</th>
    <th>Ações</th>
  </tr>
  <?php foreach ($usuarios as $usuario): ?>
    <tr>
      <td><?= htmlspecialchars($usuario['nome']) ?></td>
      <td><?= htmlspecialchars($usuario['email']) ?></td>
      <td><?= $usuario['perfil'] ?></td>
      <td><?= $usuario['ativo'] ? 'Ativo' : 'Inativo' ?></td>
      <td>
        <a href="editar_usuario.php?id=<?= $usuario['id_usuario'] ?>">Editar</a> |
        <a href="excluir_usuario.php?id=<?= $usuario['id_usuario'] ?>"
           onclick="return confirm('Tem certeza que deseja excluir este usuário?')">Excluir</a>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
