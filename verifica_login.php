<?php
session_start();
require_once 'conexao.php';
$pdo = (new BancoDeDados())->pdo;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';

    $sql = "SELECT * FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($senha, $usuario['senha'])) {
        $_SESSION['usuario_id'] = $usuario['id_usuario'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_perfil'] = $usuario['perfil'];

        // Buscar permissões
        $stmt = $pdo->prepare("
            SELECT p.nome 
            FROM usuario_permissoes up 
            JOIN permissoes p ON up.permissao_id = p.id 
            WHERE up.usuario_id = ?
        ");
        $stmt->execute([$usuario['id_usuario']]);
        $_SESSION['permissoes'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

        header('Location: dashboard.php');
        exit;
    } else {
        $_SESSION['erro'] = "E-mail ou senha inválidos!";
        header('Location: login.php');
        exit;
    }
}
