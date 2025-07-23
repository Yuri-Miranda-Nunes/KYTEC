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
    // Aqui os names do seu form são email e senha, ajuste se forem diferentes
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';

    // Consulta na tabela usuarios
    $sql  = "SELECT * FROM usuarios WHERE email = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($senha, $usuario['senha'])) {
        // Grava na sessão usando as colunas corretas
        $_SESSION['id_usuario']   = $usuario['id']; 
        $_SESSION['email']        = $usuario['email'];
        $_SESSION['perfil']       = $usuario['perfil'] ?? 'usuario';

        // Se você tiver tabela pessoas e quiser capturar o nome:
        $sqlNome  = "SELECT nome FROM pessoas WHERE id_usuario = ?";
        $stmtNome = $pdo->prepare($sqlNome);
        $stmtNome->execute([ $usuario['id'] ]);
        $pessoa = $stmtNome->fetch(PDO::FETCH_ASSOC);

        $_SESSION['nome'] = $pessoa['nome'] ?? $usuario['nome'];

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
