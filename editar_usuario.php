<?php
require_once 'conexao.php';
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'admin') {
    echo "Acesso negado.";
    exit;
}

$bd = new BancoDeDados();
$id = $_GET['id'] ?? null;

if (!$id) {
    echo "ID inválido.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $perfil = $_POST['perfil'];
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    $sql = "UPDATE usuarios SET nome = ?, email = ?, perfil = ?, ativo = ? WHERE id_usuario = ?";
    $stmt = $bd->pdo->prepare($sql);
    $stmt->execute([$nome, $email, $perfil, $ativo, $id]);

    header("Location: listar_usuarios.php");
    exit;
}

$sql = "SELECT * FROM usuarios WHERE id_usuario = ?";
$stmt = $bd->pdo->prepare($sql);
$stmt->execute([$id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    echo "Usuário não encontrado.";
    exit;
}
?>

<h2>Editar Usuário</h2>
<form method="POST">
  <input type="text" name="nome" value="<?= htmlspecialchars($usuario['nome']) ?>" required><br>
  <input type="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>" required><br>
  <select name="perfil">
    <option value="admin" <?= $usuario['perfil'] === 'admin' ? 'selected' : '' ?>>Admin</option>
    <option value="estoquista" <?= $usuario['perfil'] === 'estoquista' ? 'selected' : '' ?>>Estoquista</option>
    <option value="vendedor" <?= $usuario['perfil'] === 'vendedor' ? 'selected' : '' ?>>Vendedor</option>
  </select><br>
  <label>
    <input type="checkbox" name="ativo" <?= $usuario['ativo'] ? 'checked' : '' ?>> Ativo
  </label><br><br>
  <button type="submit">Salvar Alterações</button>
</form>
