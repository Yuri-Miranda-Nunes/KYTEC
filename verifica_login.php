<?php
session_start();
require_once 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    $bd = new BancoDeDados();

    $sql = "SELECT * FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1";
    $stmt = $bd->pdo->prepare($sql);
    $stmt->execute([$email]);

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($senha, $usuario['senha'])) {
        $_SESSION['usuario_id'] = $usuario['id_usuario'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_perfil'] = $usuario['perfil'];
        header('Location: dashboard.php');
        exit;
    } else {
        $_SESSION['erro'] = "E-mail ou senha inválidos!";
        header('Location: login.php');
        exit;
    }
}
?>
