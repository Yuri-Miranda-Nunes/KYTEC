<?php
session_start();
require_once '../conexao.php';

// Se não for POST, volta pro login
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

try {
    $pdo = (new BancoDeDados())->pdo;
    
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'] ?? '';
    
    // Validações básicas
    if (empty($email) || empty($senha)) {
        $_SESSION['erro'] = 'Email e senha são obrigatórios!';
        header('Location: ../login.php');
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['erro'] = 'Email inválido!';
        header('Location: ../login.php');
        exit;
    }
    
    // CORREÇÃO: Verificar se tabela usa 'id' ou 'id_usuario'
    // Primeiro, descobrir qual coluna existe
    $stmt_desc = $pdo->query("DESCRIBE usuarios");
    $colunas = $stmt_desc->fetchAll(PDO::FETCH_COLUMN);
    
    $campo_id = in_array('id', $colunas) ? 'id' : 'id_usuario';
    
    // Buscar o usuário com a coluna correta
    $sql = "SELECT $campo_id as id, nome, email, senha, perfil, ativo FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug detalhado
    if (isset($_GET['debug'])) {
        echo "<h3>DEBUG INFO:</h3>";
        echo "Campo ID usado: $campo_id<br>";
        echo "Email recebido: " . htmlspecialchars($email) . "<br>";
        echo "Senha recebida: " . (empty($senha) ? 'VAZIA' : 'PREENCHIDA (' . strlen($senha) . ' chars)') . "<br>";
        echo "Usuario encontrado: " . ($usuario ? 'SIM' : 'NAO') . "<br>";
        
        if ($usuario) {
            echo "ID do usuario: " . $usuario['id'] . "<br>";
            echo "Nome: " . htmlspecialchars($usuario['nome']) . "<br>";
            echo "Email no banco: " . htmlspecialchars($usuario['email']) . "<br>";
            echo "Perfil: " . htmlspecialchars($usuario['perfil'] ?? 'não definido') . "<br>";
            echo "Ativo: " . ($usuario['ativo'] ? 'SIM' : 'NAO') . "<br>";
            echo "Password verify result: " . (password_verify($senha, $usuario['senha']) ? 'SUCESSO' : 'FALHOU') . "<br>";
            
            // Buscar permissões
            $sql_perm = "SELECT p.nome_permissao 
                        FROM usuario_permissoes up 
                        JOIN permissoes p ON up.permissao_id = p.id 
                        WHERE up.usuario_id = ?";
            $stmt_perm = $pdo->prepare($sql_perm);
            $stmt_perm->execute([$usuario['id']]);
            $permissoes_usuario = $stmt_perm->fetchAll(PDO::FETCH_COLUMN);
            
            echo "Permissões encontradas: " . (empty($permissoes_usuario) ? 'NENHUMA' : implode(', ', $permissoes_usuario)) . "<br>";
        }
        
        // Mostrar todos os usuários para debug
        echo "<h4>Todos os usuários no banco:</h4>";
        $stmt_all = $pdo->query("SELECT $campo_id as id, nome, email, perfil, ativo FROM usuarios");
        $todos_usuarios = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Email</th><th>Perfil</th><th>Ativo</th></tr>";
        foreach ($todos_usuarios as $u) {
            echo "<tr>";
            echo "<td>{$u['id']}</td>";
            echo "<td>" . htmlspecialchars($u['nome']) . "</td>";
            echo "<td>" . htmlspecialchars($u['email']) . "</td>";
            echo "<td>" . htmlspecialchars($u['perfil']) . "</td>";
            echo "<td>" . ($u['ativo'] ? 'Sim' : 'Não') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<br><a href='login.php'>Voltar para login</a>";
        exit;
    }
    
    // Verifica login
    if ($usuario && password_verify($senha, $usuario['senha'])) {
        session_regenerate_id(true);
        
        // Salva na sessão
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_email'] = $usuario['email'];
        $_SESSION['usuario_perfil'] = $usuario['perfil'] ?? 'comum';
        $_SESSION['logado'] = true;
        
        // Buscar permissões específicas do banco
        $sql_permissoes = "SELECT p.nome_permissao 
                          FROM usuario_permissoes up 
                          JOIN permissoes p ON up.permissao_id = p.id 
                          WHERE up.usuario_id = ?";
        $stmt_permissoes = $pdo->prepare($sql_permissoes);
        $stmt_permissoes->execute([$usuario['id']]);
        $permissoes_db = $stmt_permissoes->fetchAll(PDO::FETCH_COLUMN);
        
        // Fallback baseado no perfil se não houver permissões no banco
        if (empty($permissoes_db)) {
            switch (strtolower($usuario['perfil'] ?? 'comum')) {
                case 'admin':
                case 'administrador':
                    $permissoes_db = ['listar_produtos', 'cadastrar_produtos', 'editar_produtos', 'excluir_produtos', 'gerenciar_usuarios'];
                    break;
                case 'estoquista':
                    $permissoes_db = ['listar_produtos', 'cadastrar_produtos', 'editar_produtos'];
                    break;
                case 'vendedor':
                    $permissoes_db = ['listar_produtos'];
                    break;
                default:
                    $permissoes_db = ['listar_produtos'];
                    break;
            }
        }
        
        $_SESSION['permissoes'] = $permissoes_db;
        unset($_SESSION['erro']);
        
        header('Location: index.php');
        exit;
    }
    
    // Falha no login
    if (!$usuario) {
        $_SESSION['erro'] = 'Usuário não encontrado ou inativo!';
    } else {
        $_SESSION['erro'] = 'Senha incorreta!';
    }
    
    header('Location: login.php');
    exit;
    
} catch (Exception $e) {
    $_SESSION['erro'] = 'Erro interno: ' . $e->getMessage();
    header('Location: login.php');
    exit;
}
?>