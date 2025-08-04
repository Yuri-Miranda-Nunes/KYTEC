<?php
require_once 'conexao.php';
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'admin') {
    echo "Acesso negado.";
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    echo "ID inválido.";
    exit;
}

// Evitar que admin exclua a si mesmo
if ($_SESSION['usuario_id'] == $id) {
    echo "Você não pode excluir a si mesmo!";
    exit;
}

$bd = new BancoDeDados();
$sql = "DELETE FROM usuarios WHERE id_usuario = ?";
$stmt = $bd->pdo->prepare($sql);
$stmt->execute([$id]);

header("Location: listar_usuarios.php");
exit;
