<?php
session_start();
require_once 'conexao.php';

header('Access-Control-Allow-Origin: https://kytec.rf.gd');
header('Content-Type: application/json; charset=utf-8');
// resto do seu código...

// Ajuste o display de erros apenas enquanto estiver em desenvolvimento
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Conecta ao banco
$pdo = (new BancoDeDados())->pdo;

// Se não for POST, volta pro login
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// Captura o e‑mail e a senha do formulário
$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

// Busca o usuário
$sql  = 'SELECT * FROM usuarios WHERE email = ? LIMIT 1';
$stmt = $pdo->prepare($sql);
$stmt->execute([$email]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Verifica se existe e se a senha bate
if ($usuario && password_verify($senha, $usuario['senha'])) {
    // Login bem‑sucedido: salva na sessão
    $_SESSION['id_usuario'] = $usuario['id'];
    $_SESSION['nome']       = $usuario['nome'];
    $_SESSION['email']      = $usuario['email'];

    header('Location: dashboard.php');
    exit;
}

// Falha no login
$_SESSION['erro'] = 'E‑mail ou senha inválidos!';
header('Location: login.php');
exit;
