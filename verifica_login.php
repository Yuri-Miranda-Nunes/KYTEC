<?php
session_start();
require_once 'conexao.php';
// No seu arquivo conexao.php, a conexão deve ser assim:
$pdo = new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
]);

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
    
    // Força UTF-8 na conexão para compatibilidade
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
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
    
    // Busca o usuário - usando os nomes corretos das colunas
    $sql = 'SELECT id, nome, email, senha, perfil, ativo FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    
    echo "Email digitado: '$email'<br>";
    echo "Senha digitada: '$senha'<br>";
    echo "Usuario encontrado: " . ($usuario ? 'SIM' : 'NAO') . "<br>";
    if ($usuario) {
        echo "Email no banco: '" . $usuario['email'] . "'<br>";
        echo "Hash no banco: '" . $usuario['senha'] . "'<br>";
        echo "Usuario ativo: " . ($usuario['ativo'] ? 'SIM' : 'NAO') . "<br>";
        echo "Password verify: " . (password_verify($senha, $usuario['senha']) ? 'OK' : 'FALHOU') . "<br>";
        
        // Testa senhas comuns
        $senhas_teste = ['admin', '123456', 'password', 'admin123'];
        echo "<br>Testando senhas comuns:<br>";
        foreach($senhas_teste as $teste) {
            echo "- '$teste': " . (password_verify($teste, $usuario['senha']) ? 'MATCH!' : 'não') . "<br>";
        }
    }
    exit;

    
    // Verifica se existe e se a senha bate
    if ($usuario && password_verify($senha, $usuario['senha'])) {
        // Login bem-sucedido: regenera ID da sessão por segurança
        session_regenerate_id(true);
        
        // Salva na sessão (usando os nomes das colunas do seu banco)
        $_SESSION['id_usuario'] = $usuario['id'];
        $_SESSION['nome'] = $usuario['nome'];
        $_SESSION['email'] = $usuario['email'];
        $_SESSION['perfil'] = $usuario['perfil'];
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
    
} catch (PDOException $e) {
    // Erro de banco de dados
    error_log("Erro de banco no login: " . $e->getMessage());
    $_SESSION['erro'] = 'Erro de conexão com o banco. Tente novamente.';
    header('Location: login.php');
    exit;
    
} catch (Exception $e) {
    // Outros erros
    error_log("Erro no login: " . $e->getMessage());
    $_SESSION['erro'] = 'Erro interno. Tente novamente.';
    header('Location: login.php');
    exit;
}
?>