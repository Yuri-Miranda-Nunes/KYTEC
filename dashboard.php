<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}
?>

<h1>Bem-vindo, <?= $_SESSION['usuario_nome'] ?>!</h1>
<p>Perfil: <?= $_SESSION['usuario_perfil'] ?></p>

<a href="logout.php">Sair</a>
