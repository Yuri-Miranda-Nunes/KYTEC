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
    $sql = 'SELECT id, nome, email, senha, perfil, ativo FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug detalhado - ATIVE temporariamente para diagnosticar
    if (isset($_GET['debug'])) {
        echo "<h3>DEBUG INFO:</h3>";
        echo "Email recebido: " . htmlspecialchars($email) . "<br>";
        echo "Senha recebida: " . (empty($senha) ? 'VAZIA' : 'PREENCHIDA (' . strlen($senha) . ' chars)') . "<br>";
        echo "Usuario encontrado: " . ($usuario ? 'SIM' : 'NAO') . "<br>";
        
        if ($usuario) {
            echo "ID do usuario: " . $usuario['id'] . "<br>";
            echo "Nome: " . htmlspecialchars($usuario['nome']) . "<br>";
            echo "Email no banco: " . htmlspecialchars($usuario['email']) . "<br>";
            echo "Perfil: " . htmlspecialchars($usuario['perfil'] ?? 'não definido') . "<br>";
            echo "Ativo: " . ($usuario['ativo'] ? 'SIM' : 'NAO') . "<br>";
            echo "Hash da senha no banco: " . substr($usuario['senha'], 0, 20) . "...<br>";
            echo "Password verify result: " . (password_verify($senha, $usuario['senha']) ? 'SUCESSO' : 'FALHOU') . "<br>";
            
            // Buscar permissões do usuário
            $sql_perm = "SELECT p.nome_permissao 
                        FROM usuario_permissoes up 
                        JOIN permissoes p ON up.permissao_id = p.id 
                        WHERE up.usuario_id = ?";
            $stmt_perm = $pdo->prepare($sql_perm);
            $stmt_perm->execute([$usuario['id']]);
            $permissoes_usuario = $stmt_perm->fetchAll(PDO::FETCH_COLUMN);
            
            echo "Permissões encontradas: " . implode(', ', $permissoes_usuario) . "<br>";
        }
        
        echo "<br><a href='login.php'>Voltar para login</a>";
        exit;
    }
    
    // Verifica se existe, está ativo e se a senha bate
    if ($usuario && password_verify($senha, $usuario['senha'])) {
        // Login bem-sucedido: regenera ID da sessão por segurança
        session_regenerate_id(true);
        
        // Salva na sessão
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_email'] = $usuario['email'];
        $_SESSION['usuario_perfil'] = $usuario['perfil'] ?? 'comum';
        $_SESSION['logado'] = true;
        
        // Busca as permissões específicas do usuário no banco de dados
        $sql_permissoes = "SELECT p.nome_permissao 
                          FROM usuario_permissoes up 
                          JOIN permissoes p ON up.permissao_id = p.id 
                          WHERE up.usuario_id = ?";
        $stmt_permissoes = $pdo->prepare($sql_permissoes);
        $stmt_permissoes->execute([$usuario['id']]);
        $permissoes_db = $stmt_permissoes->fetchAll(PDO::FETCH_COLUMN);
        
        // Se não houver permissões específicas no banco, define baseadas no perfil como fallback
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
        
        // Limpa mensagens de erro
        unset($_SESSION['erro']);
        
        // Adiciona log de sucesso
        error_log("Login bem-sucedido para: " . $email . " com permissões: " . implode(', ', $permissoes_db));
        
        // Verifica se o arquivo dashboard.php existe
        if (!file_exists('dashboard.php')) {
            $_SESSION['erro'] = 'Página de dashboard não encontrada!';
            header('Location: login.php');
            exit;
        }
        
        header('Location: dashboard.php');
        exit;
    }
    
    // Se chegou aqui, falha no login
    if (!$usuario) {
        $_SESSION['erro'] = 'Usuário não encontrado ou inativo!';
        error_log("Tentativa de login com email inexistente ou inativo: " . $email);
    } else {
        $_SESSION['erro'] = 'Senha incorreta!';
        error_log("Tentativa de login com senha incorreta para: " . $email);
    }
    
    header('Location: login.php');
    exit;
    
} catch (PDOException $e) {
    // Erro específico de banco
    error_log("Erro de banco no login: " . $e->getMessage());
    $_SESSION['erro'] = 'Erro de conexão com banco de dados.';
    header('Location: login.php');
    exit;
    
} catch (Exception $e) {
    // Outros erros
    error_log("Erro geral no login: " . $e->getMessage());
    $_SESSION['erro'] = 'Erro interno. Tente novamente.';
    header('Location: login.php');
    exit;
}
?>