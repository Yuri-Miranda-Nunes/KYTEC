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
                // Inicia transação para garantir consistência
                $pdo->beginTransaction();
                
                try {
                    // Hash da senha
                    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                    
                    // Inserir o usuário (usar 'id' como nome da coluna, não 'id_usuario')
                    $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, perfil, ativo) VALUES (?, ?, ?, ?, 1)");
                    if ($stmt->execute([$nome, $email, $senha_hash, $perfil])) {
                        $usuario_id = $pdo->lastInsertId();

                        // Inserir permissões na tabela usuario_permissoes (NÃO na tabela permissoes)
                        if (!empty($permissoes)) {
                            // Primeiro, obter os IDs das permissões selecionadas
                            $placeholders = str_repeat('?,', count($permissoes) - 1) . '?';
                            $stmt_perm_ids = $pdo->prepare("SELECT id, nome_permissao FROM permissoes WHERE nome_permissao IN ($placeholders)");
                            $stmt_perm_ids->execute($permissoes);
                            $permissoes_db = $stmt_perm_ids->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Inserir na tabela de relacionamento usuario_permissoes
                            $stmt_user_perm = $pdo->prepare("INSERT INTO usuario_permissoes (usuario_id, permissao_id) VALUES (?, ?)");
                            foreach ($permissoes_db as $permissao) {
                                $stmt_user_perm->execute([$usuario_id, $permissao['id']]);
                            }
                        }

                        // Confirma a transação
                        $pdo->commit();
                        
                        $mensagem = "✅ Usuário cadastrado com sucesso!";
                        
                        // Limpa os campos
                        $nome = $email = $senha = $perfil = '';
                        $permissoes = [];
                    } else {
                        throw new Exception("Erro ao inserir usuário");
                    }
                } catch (Exception $e) {
                    // Desfaz a transação em caso de erro
                    $pdo->rollBack();
                    throw $e;
                }
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $mensagem = "❌ Erro no banco de dados: " . $e->getMessage();
        } catch (Exception $e) {
            $pdo->rollBack();
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
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td {
            padding: 10px;
            vertical-align: top;
        }
        input, select, textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        button {
            padding: 10px 20px;
            margin: 5px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        button[type="submit"] {
            background-color: #28a745;
            color: white;
        }
        button[type="reset"] {
            background-color: #6c757d;
            color: white;
        }
        .mensagem {
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
            font-weight: bold;
        }
        .sucesso {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .erro {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        fieldset {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin: 10px 0;
        }
        legend {
            font-weight: bold;
            padding: 0 10px;
        }
        .permissoes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin: 10px 0;
        }
        .permissao-item {
            padding: 8px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .links {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #e9ecef;
            border-radius: 4px;
        }
        .links a {
            color: #007bff;
            text-decoration: none;
            margin-right: 10px;
        }
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Cadastrar Novo Usuário</h2>

        <div class="links">
            <a href="dashboard.php">← Voltar ao Dashboard</a> | 
            <a href="listar_usuarios.php">Ver Usuários Cadastrados</a>
        </div>

        <?php if ($mensagem): ?>
            <div class="mensagem <?= strpos($mensagem, '✅') !== false ? 'sucesso' : 'erro' ?>">
                <?= $mensagem ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <table>
                <tr>
                    <td width="150"><label for="nome">Nome:</label></td>
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

            <fieldset>
                <legend>Permissões do Usuário</legend>
                <p><em>Selecione as permissões que este usuário terá no sistema:</em></p>
                
                <div class="permissoes-grid">
                    <?php foreach ($permissoes_possiveis as $perm_key => $perm_nome): ?>
                        <div class="permissao-item">
                            <label>
                                <input type="checkbox" name="permissoes[]" value="<?= $perm_key ?>" 
                                    <?= (isset($permissoes) && in_array($perm_key, $permissoes)) ? 'checked' : '' ?>>
                                <?= $perm_nome ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="margin-top: 15px;">
                    <small><strong>Dica:</strong> Administradores geralmente precisam de todas as permissões.</small>
                </div>
            </fieldset>

            <div style="text-align: center; margin-top: 20px;">
                <button type="submit">Cadastrar Usuário</button>
                <button type="reset">Limpar Formulário</button>
            </div>
        </form>

        <div style="margin-top: 30px;">
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
        </div>
    </div>
</body>
</html>