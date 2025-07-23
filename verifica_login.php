<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'conexao.php';

$bd  = new BancoDeDados();
$pdo = $bd->pdo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

$sql  = 'SELECT * FROM usuarios WHERE email = ? LIMIT 1';
$stmt = $pdo->prepare($sql);
$stmt->execute([$email]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Debug: veja o que está chegando e o que o banco retornou
echo '<pre>';
var_dump(
    'REQUEST_METHOD=' . $_SERVER['REQUEST_METHOD'],
    'POST email='        . ($email),
    'POST senha='        . (isset($_POST['senha']) ? '****' : 'not set'),
    'USUARIO='           . print_r($usuario, true)
);
echo '</pre>';
exit;

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
