<?php
session_start();
require_once 'conexao.php';

header('Access-Control-Allow-Origin: https://kytec.rf.gd');
header('Content-Type: text/html; charset=utf-8');

// Configurações de erro (apenas em desenvolvimento)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Se não for POST, volta pro login
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

try {
    // Conecta ao banco
    $pdo = (new BancoDeDados())->pdo;
    
    // Captura e limpa os dados do formulário
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'] ?? '';
    
    // Validações básicas
    if (empty($email) || empty($senha)) {
        $_SESSION['erro'] = 'Email e senha são obrigatórios!';
        header('Location: login.php');
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['erro'] = 'Email inválido!';
        header('Location: login.php');
        exit;
    }
    
    // Busca o usuário
    $sql = 'SELECT id, nome, email, senha FROM usuarios WHERE email = ? LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug temporário - REMOVA depois de resolver
    
    // echo "Email: " . $email . "<br>";
    // echo "Usuario encontrado: " . ($usuario ? 'SIM' : 'NAO') . "<br>";
    // if ($usuario) {
    //     echo "Password verify: " . (password_verify($senha, $usuario['senha']) ? 'OK' : 'FALHOU') . "<br>";
    // }
    // exit;
    
    
    // Verifica se existe e se a senha bate
    if ($usuario && password_verify($senha, $usuario['senha'])) {
        // Login bem-sucedido: regenera ID da sessão por segurança
        session_regenerate_id(true);
        
        // Salva na sessão
        $_SESSION['id_usuario'] = $usuario['id'];
        $_SESSION['nome'] = $usuario['nome'];
        $_SESSION['email'] = $usuario['email'];
        $_SESSION['logado'] = true;
        
        // Limpa mensagens de erro
        unset($_SESSION['erro']);
        
        header('Location: dashboard.php');
        exit;
    }
    
    // Falha no login
    $_SESSION['erro'] = 'Email ou senha incorretos!';
    header('Location: login.php');
    exit;
    
} catch (Exception $e) {
    // Log do erro (em produção, salve em arquivo de log)
    error_log("Erro no login: " . $e->getMessage());
    
    $_SESSION['erro'] = 'Erro interno. Tente novamente.';
    header('Location: login.php');
    exit;
}