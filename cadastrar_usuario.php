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
$nome = '';
$email = '';
$perfil = '';
$permissoes = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $perfil = $_POST['perfil'] ?? '';
    $permissoes = $_POST['permissoes'] ?? [];

    // Debug - remova depois
    echo "<h3>DEBUG POST:</h3>";
    echo "Nome: " . htmlspecialchars($nome) . "<br>";
    echo "Email: " . htmlspecialchars($email) . "<br>";
    echo "Perfil: " . htmlspecialchars($perfil) . "<br>";
    echo "Permissões: " . implode(', ', $permissoes) . "<br><br>";

    // Validações
    if (empty($nome) || empty($email) || empty($senha) || empty($perfil)) {
        $mensagem = "Todos os campos são obrigatórios!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "Email inválido!";
    } elseif (strlen($senha) < 6) {
        $mensagem = "A senha deve ter pelo menos 6 caracteres!";
    } else {
        try {
            // Verifica se email já existe
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
            $stmt_check->execute([$email]);
            
            if ($stmt_check->fetchColumn() > 0) {
                $mensagem = "Este email já está cadastrado!";
            } else {
                // Inicia transação
                $pdo->beginTransaction();
                
                try {
                    // Hash da senha
                    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                    
                    // CORREÇÃO: Usar 'id' como nome da coluna de ID na tabela usuarios
                    $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, perfil, ativo) VALUES (?, ?, ?, ?, 1)");
                    if ($stmt->execute([$nome, $email, $senha_hash, $perfil])) {
                        $usuario_id = $pdo->lastInsertId();
                        
                        echo "Usuario inserido com ID: " . $usuario_id . "<br>";

                        // Inserir permissões se selecionadas
                        if (!empty($permissoes)) {
                            // Buscar IDs das permissões
                            $placeholders = str_repeat('?,', count($permissoes) - 1) . '?';
                            $stmt_perm_ids = $pdo->prepare("SELECT id, nome_permissao FROM permissoes WHERE nome_permissao IN ($placeholders)");
                            $stmt_perm_ids->execute($permissoes);
                            $permissoes_db = $stmt_perm_ids->fetchAll(PDO::FETCH_ASSOC);
                            
                            echo "Permissões encontradas no banco: ";
                            foreach ($permissoes_db as $p) {
                                echo $p['nome_permissao'] . " (ID: " . $p['id'] . "), ";
                            }
                            echo "<br>";
                            
                            // Inserir na tabela usuario_permissoes
                            $stmt_user_perm = $pdo->prepare("INSERT INTO usuario_permissoes (usuario_id, permissao_id) VALUES (?, ?)");
                            foreach ($permissoes_db as $permissao) {
                                if ($stmt_user_perm->execute([$usuario_id, $permissao['id']])) {
                                    echo "Permissão '{$permissao['nome_permissao']}' adicionada ao usuário.<br>";
                                } else {
                                    echo "Erro ao adicionar permissão '{$permissao['nome_permissao']}'.<br>";
                                }
                            }
                        }

                        // Confirma a transação
                        $pdo->commit();
                        
                        $mensagem = "Usuário cadastrado com sucesso!";
                        
                        // Limpa os campos
                        $nome = $email = $senha = $perfil = '';
                        $permissoes = [];
                    } else {
                        throw new Exception("Erro ao inserir usuário");
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $mensagem = "Erro no banco de dados: " . $e->getMessage();
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensagem = "Erro: " . $e->getMessage();
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

    <p>
        <a href="index.php">Voltar ao Dashboard</a> | 
        <a href="listar_usuarios.php">Ver Usuários Cadastrados</a>
    </p>

    <?php if ($mensagem): ?>
        <div style="border: 1px solid; padding: 10px; margin: 10px 0;">
            <?= $mensagem ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <table>
            <tr>
                <td>Nome:</td>
                <td><input type="text" name="nome" value="<?= htmlspecialchars($nome) ?>" required></td>
            </tr>
            
            <tr>
                <td>Email:</td>
                <td><input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required></td>
            </tr>
            
            <tr>
                <td>Senha:</td>
                <td><input type="password" name="senha" required minlength="6"></td>
            </tr>
            
            <tr>
                <td>Tipo de Perfil:</td>
                <td>
                    <select name="perfil" required>
                        <option value="">Selecione um perfil</option>
                        <option value="admin" <?= ($perfil === 'admin') ? 'selected' : '' ?>>Administrador</option>
                        <option value="estoquista" <?= ($perfil === 'estoquista') ? 'selected' : '' ?>>Estoquista</option>
                        <option value="visualizador" <?= ($perfil === 'visualizador') ? 'selected' : '' ?>>Visualizador</option>
                    </select>
                </td>
            </tr>
        </table>

        <fieldset>
            <legend>Permissões do Usuário</legend>
            <p>Selecione as permissões que este usuário terá no sistema:</p>
            
            <?php foreach ($permissoes_possiveis as $perm_key => $perm_nome): ?>
                <div>
                    <label>
                        <input type="checkbox" name="permissoes[]" value="<?= $perm_key ?>" 
                            <?= in_array($perm_key, $permissoes) ? 'checked' : '' ?>>
                        <?= $perm_nome ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </fieldset>

        <div>
            <button type="submit">Cadastrar Usuário</button>
            <button type="reset">Limpar Formulário</button>
        </div>
    </form>

    <div>
        <h3>Descrição dos Perfis:</h3>
        <ul>
            <li><strong>Administrador:</strong> Acesso completo ao sistema, pode gerenciar usuários</li>
            <li><strong>Estoquista:</strong> Gerencia produtos e estoque</li>
            <li><strong>Visualizador:</strong> Acesso limitado, visualização de produtos</li>
        </ul>
    </div>
</body>
</html>