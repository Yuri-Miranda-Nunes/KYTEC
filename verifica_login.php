<?php
session_start();
require_once 'conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email_usuario'] ?? '';
    $senha = $_POST['senha_usuario'] ?? '';

    $sql = "SELECT * FROM usuarios WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($senha, $usuario['senha'])) {
        // Ajuste os nomes abaixo conforme sua tabela
        $_SESSION['id_usuario'] = $usuario['id']; 
        $_SESSION['email'] = $usuario['email'];
        $_SESSION['perfil'] = $usuario['perfil'] ?? 'usuario';

        // Se quiser buscar nome da tabela pessoas:
        $sqlNome = "SELECT nome FROM pessoas WHERE id_usuario = ?";
        $stmtNome = $conn->prepare($sqlNome);
        $stmtNome->execute([$usuario['id']]);
        $pessoa = $stmtNome->fetch(PDO::FETCH_ASSOC);
        $_SESSION['nome'] = $pessoa['nome'] ?? 'Usuário';

        header("Location: dashboard.php");
        exit;
    } else {
        header("Location: login.php?erro=1");
        exit;
    }
} else {
    header("Location: login.php");
    exit;
}
?>
