<?php
session_start();
require_once 'conexao.php';

// Instancia a conexão
$bd  = new BancoDeDados();
$pdo = $bd->pdo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// Captura e saneia os inputs
$email = trim($_POST['email']   ?? '');
$senha = $_POST['senha']       ?? '';

// Busca o usuário pelo e‑mail
$sql  = 'SELECT * FROM usuarios WHERE email = ? LIMIT 1';
$stmt = $pdo->prepare($sql);
$stmt->execute([$email]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if ($usuario && password_verify($senha, $usuario['senha'])) {
    // Autenticação bem‑sucedida: grava na sessão
    $_SESSION['id_usuario'] = $usuario['id'];
    $_SESSION['nome']       = $usuario['nome'];
    $_SESSION['email']      = $usuario['email'];
    // Se tiver coluna perfil, ajuste aqui; caso não, comente a linha abaixo
    // $_SESSION['perfil']     = $usuario['perfil'];

    header('Location: dashboard.php');
    exit;
}

// Falha na autenticação
$_SESSION['erro'] = 'E‑mail ou senha inválidos!';
header('Location: login.php');
exit;
