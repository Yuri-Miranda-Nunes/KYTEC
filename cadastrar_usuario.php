<?php
session_start();

// Verifica se está logado e tem permissão
if (!isset($_SESSION['usuario_id']) || !in_array('gerenciar_usuarios', $_SESSION['permissoes'] ?? [])) {
    echo "Acesso negado.";
    exit;
}

require_once 'conexao.php';

$bd = new BancoDeDados();
$pdo = $bd->pdo;

$permissoes_possiveis = [
    'listar_produtos' => 'Listar Produtos',
    'cadastrar_produtos' => 'Cadastrar Produtos',
    'editar_produtos' => 'Editar Produtos',
    'excluir_produtos' => 'Excluir Produtos',
    'gerenciar_usuarios' => 'Gerenciar Usuários'
];

$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $perfil = $_POST['perfil'] ?? '';
    $permissoes = $_POST['permissoes'] ?? [];

    // Validações
    if (empty($nome) || empty($email) || empty($senha) || empty($perfil)) {
        $mensagem = "❌ Todos os campos são obrigatórios!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "❌ Email inválido!";
    } elseif (strlen($senha) < 6) {
        $mensagem = "❌ A senha deve ter pelo menos 6 caracteres!";
    } else {
        try {
            // Verifica se email já existe
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
            $stmt_check->execute([$email]);
            
            if ($stmt_check->fetchColumn() > 0) {
                $mensagem = "❌ Este email já está cadastrado!";
            } else {
                // Hash da senha
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                
                // Inserir o usuário
                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, perfil, ativo) VALUES (?, ?, ?, ?, 1)");
                if ($stmt->execute([$nome, $email, $senha_hash, $perfil])) {
                    $usuario_id = $pdo->lastInsertId();

                    // Inserir permissões
                    if (!empty($permissoes)) {
                        $stmtPermissao = $pdo->prepare("INSERT INTO permissoes (usuario_id, nome_permissao) VALUES (?, ?)");
                        foreach ($permissoes as $permissao) {
                            if (array_key_exists($permissao, $permissoes_possiveis)) {
                                $stmtPermissao->execute([$usuario_id, $permissao]);
                            }
                        }
                    }

                    $mensagem = "✅ Usuário cadastrado com sucesso!";
                    
                    // Limpa os campos
                    $nome = $email = $senha = $perfil = '';
                    $permissoes = [];
                } else {
                    $mensagem = "❌ Erro ao cadastrar usuário.";
                }
            }
        } catch (PDOException $e) {
            $mensagem = "❌ Erro: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Usuário</title>
</head>
<body>

<h2>Cadastrar Novo Usuário</h2>

<a href="dashboard.php">← Voltar ao Dashboard</a> | 
<a href="listar_usuarios.php">Ver Usuários Cadastrados</a>

<br><br>

<?php if ($mensagem): ?>
    <p><strong><?= $mensagem ?></strong></p>
<?php endif; ?>

<form method="post">
    <table>
        <tr>
            <td><label for="nome">Nome:</label></td>
            <td><input type="text" id="nome" name="nome" value="<?= htmlspecialchars($nome ?? '') ?>" required></td>
        </tr>
        
        <tr>
            <td><label for="email">Email:</label></td>
            <td><input type="email" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required></td>
        </tr>
        
        <tr>
            <td><label for="senha">Senha:</label></td>
            <td><input type="password" id="senha" name="senha" required minlength="6"></td>
        </tr>
        
        <tr>
            <td><label for="perfil">Tipo de Perfil:</label></td>
            <td>
                <select id="perfil" name="perfil" required>
                    <option value="">Selecione um perfil</option>
                    <option value="admin" <?= (isset($perfil) && $perfil === 'admin') ? 'selected' : '' ?>>Administrador</option>
                    <option value="estoquista" <?= (isset($perfil) && $perfil === 'estoquista') ? 'selected' : '' ?>>Estoquista</option>
                    <option value="vendedor" <?= (isset($perfil) && $perfil === 'vendedor') ? 'selected' : '' ?>>Vendedor</option>
                </select>
            </td>
        </tr>
    </table>

    <br>

    <fieldset>
        <legend>Permissões do Usuário</legend>
        <p><em>Selecione as permissões que este usuário terá no sistema:</em></p>
        
        <?php foreach ($permissoes_possiveis as $perm_key => $perm_nome): ?>
            <label>
                <input type="checkbox" name="permissoes[]" value="<?= $perm_key ?>" 
                    <?= (isset($permissoes) && in_array($perm_key, $permissoes)) ? 'checked' : '' ?>>
                <?= $perm_nome ?>
            </label>
            <br>
        <?php endforeach; ?>
        
        <br>
        <small><strong>Dica:</strong> Administradores geralmente precisam de todas as permissões.</small>
    </fieldset>

    <br>

    <button type="submit">Cadastrar Usuário</button>
    <button type="reset">Limpar Formulário</button>
</form>

<br><br>

<h3>Descrição dos Perfis:</h3>
<ul>
    <li><strong>Administrador:</strong> Acesso completo ao sistema, pode gerenciar usuários e todas as funcionalidades</li>
    <li><strong>Estoquista:</strong> Foca no gerenciamento de produtos e estoque</li>
    <li><strong>Vendedor:</strong> Acesso limitado, geralmente apenas visualização de produtos</li>
</ul>

<h3>Descrição das Permissões:</h3>
<ul>
    <li><strong>Listar Produtos:</strong> Pode visualizar a lista de produtos</li>
    <li><strong>Cadastrar Produtos:</strong> Pode adicionar novos produtos ao sistema</li>
    <li><strong>Editar Produtos:</strong> Pode modificar informações dos produtos existentes</li>
    <li><strong>Excluir Produtos:</strong> Pode remover produtos do sistema</li>
    <li><strong>Gerenciar Usuários:</strong> Pode cadastrar, editar e excluir outros usuários</li>
</ul>

</body>
</html>