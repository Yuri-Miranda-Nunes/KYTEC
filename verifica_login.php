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
    // Depois do login bem-sucedido:
    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['usuario_nome'] = $usuario['nome'];
    $_SESSION['usuario_perfil'] = $usuario['perfil'];

    // Buscar permissões
    $stmt = $mysqli->prepare("SELECT p.nome FROM usuario_permissoes up JOIN permissoes p ON up.permissao_id = p.id WHERE up.usuario_id = ?");
    $stmt->bind_param("i", $usuario['id']);
    $stmt->execute();
    $result = $stmt->get_result();

    $_SESSION['permissoes'] = [];
    while ($row = $result->fetch_assoc()) {
        $_SESSION['permissoes'][] = $row['nome'];
    }
}
