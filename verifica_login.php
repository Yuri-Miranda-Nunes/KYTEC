<?php
session_start();
require_once 'conexao.php';

// Instancia a conexão
$bd  = new BancoDeDados();
$pdo = $bd->pdo;

ini_set('display_errors', 1);

ini_set('display_startup_errors', 1);

error_reporting(E_ALL);

 
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Ajuste aqui se seus campos HTML tiverem outros nomes
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';

    // Consulta na tabela usuarios
    $sql  = "SELECT * FROM usuarios WHERE email = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($senha, $usuario['senha'])) {
        // Grava na sessão usando as colunas existentes
        $_SESSION['id_usuario']   = $usuario['id']; 
        $_SESSION['email']        = $usuario['email'];
        $_SESSION['perfil']       = $usuario['perfil'] ?? 'usuario';
        // Se você tiver um campo 'nome' em 'usuarios', use-o:
        $_SESSION['nome']         = $usuario['nome']  ?? $usuario['email'];

        header("Location: dashboard.php");
        exit;
    } else {
        $_SESSION['erro'] = "E‑mail ou senha inválidos!";
        header("Location: login.php");
        exit;
    }
} else {
    header("Location: login.php");
    exit;
}
