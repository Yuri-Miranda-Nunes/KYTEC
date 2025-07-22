<?php
require_once 'conexao.php';
session_start();

// Proteção: só ADMIN pode cadastrar usuários
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_perfil'] !== 'admin') {
    echo "Acesso negado.";
    exit;
}

// Se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $perfil = $_POST['perfil'] ?? 'estoquista';

    if (!empty($nome) && !empty($email) && !empty($senha)) {
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

        try {
            $bd = new BancoDeDados();
            $sql = "INSERT INTO usuarios (nome, email, senha, perfil, ativo)
                    VALUES (?, ?, ?, ?, 1)";
            $stmt = $bd->pdo->prepare($sql);
            $stmt->execute([$nome, $email, $senha_hash, $perfil]);

            echo "<p style='color:green;'>Usuário cadastrado com sucesso!</p>";
        } catch (PDOException $e) {
            echo "<p style='color:red;'>Erro: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color:red;'>Preencha todos os campos.</p>";
    }
}
?>

<h2>Cadastrar Novo Usuário</h2>
<form method="POST">
  <input type="text" name="nome" placeholder="Nome completo" required><br>
  <input type="email" name="email" placeholder="E-mail" required><br>
  <input type="password" name="senha" placeholder="Senha" required><br>
  <select name="perfil">
    <option value="admin">Admin</option>
    <option value="estoquista">Estoquista</option>
    <option value="vendedor">Vendedor</option>
  </select><br><br>
  <button type="submit">Cadastrar Usuário</button>
</form>
