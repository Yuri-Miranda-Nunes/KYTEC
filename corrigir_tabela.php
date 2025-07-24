<?php
require_once 'conexao.php';

try {
    $pdo = (new BancoDeDados())->pdo;
    
    echo "<h2>Verificação e Correção das Tabelas</h2>";
    
    // 1. Verificar estrutura da tabela usuarios
    echo "<h3>1. Estrutura da tabela usuarios:</h3>";
    $stmt_desc = $pdo->query("DESCRIBE usuarios");
    $colunas = $stmt_desc->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($colunas as $coluna) {
        echo "<tr>";
        echo "<td>{$coluna['Field']}</td>";
        echo "<td>{$coluna['Type']}</td>";
        echo "<td>{$coluna['Null']}</td>";
        echo "<td>{$coluna['Key']}</td>";
        echo "<td>{$coluna['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Verificar se existe coluna id ou id_usuario
    $tem_id = false;
    $tem_id_usuario = false;
    foreach ($colunas as $coluna) {
        if ($coluna['Field'] === 'id') $tem_id = true;
        if ($coluna['Field'] === 'id_usuario') $tem_id_usuario = true;
    }
    
    echo "<p>Tem coluna 'id': " . ($tem_id ? 'SIM' : 'NÃO') . "</p>";
    echo "<p>Tem coluna 'id_usuario': " . ($tem_id_usuario ? 'SIM' : 'NÃO') . "</p>";
    
    // 2. Listar usuários existentes
    echo "<h3>2. Usuários existentes:</h3>";
    
    // Tentar com 'id' primeiro, depois 'id_usuario'
    $sql_usuarios = $tem_id ? "SELECT id, nome, email, perfil, ativo FROM usuarios" : "SELECT id_usuario as id, nome, email, perfil, ativo FROM usuarios";
    $stmt_usuarios = $pdo->query($sql_usuarios);
    $usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Nome</th><th>Email</th><th>Perfil</th><th>Ativo</th></tr>";
    foreach ($usuarios as $usuario) {
        echo "<tr>";
        echo "<td>{$usuario['id']}</td>";
        echo "<td>" . htmlspecialchars($usuario['nome']) . "</td>";
        echo "<td>" . htmlspecialchars($usuario['email']) . "</td>";
        echo "<td>" . htmlspecialchars($usuario['perfil']) . "</td>";
        echo "<td>" . ($usuario['ativo'] ? 'Sim' : 'Não') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 3. Verificar tabela permissoes
    echo "<h3>3. Tabela permissoes:</h3>";
    try {
        $stmt_perm = $pdo->query("SELECT * FROM permissoes");
        $permissoes = $stmt_perm->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($permissoes)) {
            echo "<p>Tabela permissoes está vazia. Inserindo permissões básicas...</p>";
            
            $permissoes_basicas = [
                'listar_produtos',
                'cadastrar_produtos', 
                'editar_produtos',
                'excluir_produtos',
                'gerenciar_usuarios'
            ];
            
            $stmt_insert = $pdo->prepare("INSERT INTO permissoes (nome_permissao) VALUES (?)");
            foreach ($permissoes_basicas as $perm) {
                if ($stmt_insert->execute([$perm])) {
                    echo "Permissão '$perm' inserida.<br>";
                }
            }
        } else {
            echo "<table border='1'>";
            echo "<tr><th>ID</th><th>Nome Permissão</th></tr>";
            foreach ($permissoes as $perm) {
                echo "<tr>";
                echo "<td>{$perm['id']}</td>";
                echo "<td>{$perm['nome_permissao']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "<p>Erro ao verificar tabela permissoes: " . $e->getMessage() . "</p>";
    }
    
    // 4. Verificar tabela usuario_permissoes
    echo "<h3>4. Tabela usuario_permissoes:</h3>";
    try {
        $sql_up = "SELECT up.usuario_id, p.nome_permissao 
                   FROM usuario_permissoes up 
                   JOIN permissoes p ON up.permissao_id = p.id 
                   ORDER BY up.usuario_id";
        $stmt_up = $pdo->query($sql_up);
        $user_perms = $stmt_up->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($user_perms)) {
            echo "<p>Nenhuma permissão de usuário encontrada.</p>";
        } else {
            echo "<table border='1'>";
            echo "<tr><th>ID Usuário</th><th>Permissão</th></tr>";
            foreach ($user_perms as $up) {
                echo "<tr>";
                echo "<td>{$up['usuario_id']}</td>";
                echo "<td>{$up['nome_permissao']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "<p>Erro ao verificar usuario_permissoes: " . $e->getMessage() . "</p>";
    }
    
    // 5. Teste de inserção simples
    echo "<h3>5. Teste de inserção:</h3>";
    echo "<form method='post'>";
    echo "<p>Nome: <input type='text' name='test_nome' value='Usuario Teste'></p>";
    echo "<p>Email: <input type='email' name='test_email' value='teste@teste.com'></p>";
    echo "<p>Perfil: <select name='test_perfil'>";
    echo "<option value='admin'>admin</option>";
    echo "<option value='estoquista'>estoquista</option>";
    echo "<option value='vendedor'>vendedor</option>";
    echo "</select></p>";
    echo "<p><button type='submit' name='testar'>Testar Inserção</button></p>";
    echo "</form>";
    
    if (isset($_POST['testar'])) {
        $test_nome = $_POST['test_nome'];
        $test_email = $_POST['test_email'];
        $test_perfil = $_POST['test_perfil'];
        $test_senha = password_hash('123456', PASSWORD_DEFAULT);
        
        echo "<h4>Tentando inserir usuário de teste...</h4>";
        echo "Nome: $test_nome<br>";
        echo "Email: $test_email<br>";
        echo "Perfil: $test_perfil<br>";
        
        try {
            $sql_insert = "INSERT INTO usuarios (nome, email, senha, perfil, ativo) VALUES (?, ?, ?, ?, 1)";
            $stmt_test = $pdo->prepare($sql_insert);
            
            if ($stmt_test->execute([$test_nome, $test_email, $test_senha, $test_perfil])) {
                echo "<p style='color: green;'>SUCESSO! Usuário inserido com ID: " . $pdo->lastInsertId() . "</p>";
            } else {
                echo "<p style='color: red;'>ERRO na inserção!</p>";
                print_r($stmt_test->errorInfo());
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>EXCEÇÃO: " . $e->getMessage() . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<h3>Erro geral:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>