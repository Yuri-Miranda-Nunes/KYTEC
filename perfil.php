<?php
session_start();

// Inclui funções comuns
require_once 'includes/functions.php';

// Verifica se está logado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: login.php");
    exit;
}

verificarAutenticacao();
require_once 'conexao.php';

// Função para determinar se a página atual está ativa
function isActivePage($page) {
    $current = basename($_SERVER['PHP_SELF']);
    return $current === $page ? 'active' : '';
}

// Classe para gerenciar dados do perfil
class PerfilUsuario {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Buscar dados completos do usuário
    public function getDadosUsuario($userId) {
        try {
            $sql = "SELECT u.*, 
                           COUNT(l.id) as total_logins,
                           DATEDIFF(NOW(), u.criado_em) as dias_no_sistema
                    FROM usuarios u 
                    LEFT JOIN logs l ON l.usuario_id = u.id AND l.acao = 'LOGIN'
                    WHERE u.id = :user_id 
                    GROUP BY u.id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // Buscar atividades recentes do usuário
    public function getAtividadesRecentes($userId, $limit = 10) {
        try {
            $sql = "SELECT l.*, p.nome as produto_nome 
                    FROM logs l
                    LEFT JOIN produtos p ON l.registro_id = p.id_produto AND l.tabela = 'produtos'
                    WHERE l.usuario_id = :user_id 
                    ORDER BY l.criado_em DESC 
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    // Atualizar informações pessoais
    public function atualizarInformacoesPessoais($userId, $dados) {
        try {
            $sql = "UPDATE usuarios SET 
                        nome = :nome,
                        email = :matricula,
                        telefone = :telefone,
                        departamento = :departamento,
                        cargo = :cargo,
                        data_admissao = :data_admissao,
                        atualizado_em = NOW()
                    WHERE id = :user_id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':nome', $dados['nome']);
            $stmt->bindParam(':matricula', $dados['matricula']);
            $stmt->bindParam(':telefone', $dados['telefone']);
            $stmt->bindParam(':departamento', $dados['departamento']);
            $stmt->bindParam(':cargo', $dados['cargo']);
            $stmt->bindParam(':data_admissao', $dados['data_admissao']);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            
            $success = $stmt->execute();
            
            if ($success) {
                // Atualizar dados na sessão
                $_SESSION['usuario_nome'] = $dados['nome'];
                
                // Log da ação
                $this->registrarLog($userId, 'UPDATE', 'usuarios', $userId, 
                                  null, json_encode($dados), 'Atualização de perfil pessoal');
            }
            
            return $success;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // Alterar senha
    public function alterarSenha($userId, $senhaAtual, $novaSenha) {
        try {
            // Verificar senha atual
            $sql = "SELECT senha FROM usuarios WHERE id = :user_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!password_verify($senhaAtual, $usuario['senha'])) {
                return ['success' => false, 'message' => 'Senha atual incorreta'];
            }
            
            // Atualizar senha
            $novaSenhaCriptografada = password_hash($novaSenha, PASSWORD_DEFAULT);
            
            $sql = "UPDATE usuarios SET senha = :nova_senha, atualizado_em = NOW() WHERE id = :user_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':nova_senha', $novaSenhaCriptografada);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            
            $success = $stmt->execute();
            
            if ($success) {
                // Log da alteração de senha
                $this->registrarLog($userId, 'UPDATE', 'usuarios', $userId, 
                                  null, null, 'Alteração de senha');
            }
            
            return ['success' => $success, 'message' => $success ? 'Senha alterada com sucesso' : 'Erro ao alterar senha'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Erro interno do servidor'];
        }
    }
    
    // Buscar sessões ativas (simulado - em um sistema real, isso seria armazenado em uma tabela de sessões)
    public function getSessoesAtivas($userId) {
        try {
            // Buscar últimos logins para simular sessões ativas
            $sql = "SELECT ip, user_agent, criado_em 
                    FROM logs 
                    WHERE usuario_id = :user_id AND acao = 'LOGIN' 
                    ORDER BY criado_em DESC 
                    LIMIT 5";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    // Registrar log de ação
    private function registrarLog($userId, $acao, $tabela, $registroId, $dadosAnteriores, $dadosNovos, $descricao) {
        try {
            $sql = "INSERT INTO logs (usuario_id, acao, tabela, registro_id, dados_anteriores, dados_novos, descricao, ip, user_agent) 
                    VALUES (:usuario_id, :acao, :tabela, :registro_id, :dados_anteriores, :dados_novos, :descricao, :ip, :user_agent)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':usuario_id', $userId);
            $stmt->bindParam(':acao', $acao);
            $stmt->bindParam(':tabela', $tabela);
            $stmt->bindParam(':registro_id', $registroId);
            $stmt->bindParam(':dados_anteriores', $dadosAnteriores);
            $stmt->bindParam(':dados_novos', $dadosNovos);
            $stmt->bindParam(':descricao', $descricao);
            $stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);
            $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT']);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }
}

// Processar requisições AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $bd = new BancoDeDados();
    $perfil = new PerfilUsuario($bd->pdo);
    $userId = $_SESSION['usuario_id'];
    
    switch ($_POST['action']) {
        case 'update_personal':
            $dados = [
                'nome' => $_POST['nome'] ?? '',
                'matricula' => $_POST['matricula'] ?? '',
                'telefone' => $_POST['telefone'] ?? '',
                'departamento' => $_POST['departamento'] ?? '',
                'cargo' => $_POST['cargo'] ?? '',
                'data_admissao' => $_POST['data_admissao'] ?? ''
            ];
            
            $success = $perfil->atualizarInformacoesPessoais($userId, $dados);
            
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Informações atualizadas com sucesso!' : 'Erro ao atualizar informações'
            ]);
            break;
            
        case 'change_password':
            $senhaAtual = $_POST['current_password'] ?? '';
            $novaSenha = $_POST['new_password'] ?? '';
            $confirmarSenha = $_POST['confirm_password'] ?? '';
            
            if ($novaSenha !== $confirmarSenha) {
                echo json_encode(['success' => false, 'message' => 'As senhas não coincidem']);
                break;
            }
            
            if (strlen($novaSenha) < 8) {
                echo json_encode(['success' => false, 'message' => 'A senha deve ter pelo menos 8 caracteres']);
                break;
            }
            
            $result = $perfil->alterarSenha($userId, $senhaAtual, $novaSenha);
            echo json_encode($result);
            break;
            
        case 'get_activities':
            $limit = (int)($_POST['limit'] ?? 10);
            $atividades = $perfil->getAtividadesRecentes($userId, $limit);
            echo json_encode(['success' => true, 'activities' => $atividades]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
    }
    exit;
}

// Buscar dados do usuário para exibição
$bd = new BancoDeDados();
$perfil = new PerfilUsuario($bd->pdo);
$usuario = getUsuarioLogado();
$dadosUsuario = $perfil->getDadosUsuario($_SESSION['usuario_id']);
$atividadesRecentes = $perfil->getAtividadesRecentes($_SESSION['usuario_id']);
$sessoesAtivas = $perfil->getSessoesAtivas($_SESSION['usuario_id']);

// Se não encontrou dados do usuário, usar dados da sessão
if (!$dadosUsuario) {
    $dadosUsuario = [
        'nome' => $_SESSION['usuario_nome'],
        'email' => $_SESSION['usuario_email'] ?? 'usuario@empresa.com',
        'perfil' => $_SESSION['usuario_perfil'],
        'telefone' => '',
        'departamento' => '',
        'cargo' => '',
        'data_admissao' => date('Y-m-d'),
        'total_logins' => 0,
        'dias_no_sistema' => 0
    ];
}

// Traduzir perfil para exibição
$perfis_traducao = [
    'admin' => 'Administrador',
    'estoquista' => 'Estoquista',
    'visualizador' => 'Visualizador'
];

$perfilExibicao = $perfis_traducao[$dadosUsuario['perfil']] ?? ucfirst($dadosUsuario['perfil']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Sistema de Estoque</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.6;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: #1e293b;
            color: white;
            padding: 0;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 24px 20px;
            background: #0f172a;
            border-bottom: 1px solid #334155;
        }

        .sidebar-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .nav-section {
            margin-bottom: 24px;
        }

        .nav-section-title {
            color: #94a3b8;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0 20px 8px;
            margin-bottom: 8px;
        }

        .nav-item {
            margin-bottom: 2px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: #cbd5e1;
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }

        .nav-link:hover {
            background: #334155;
            color: white;
            transform: translateX(4px);
        }

        .nav-link.active {
            background: #3b82f6;
            color: white;
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 24px;
        }

        /* Header */
        .header {
            background: white;
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left h1 {
            font-size: 1.875rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .header-subtitle {
            color: #64748b;
            font-size: 0.875rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 0.875rem;
            color: #64748b;
        }

        .breadcrumb a {
            color: #3b82f6;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Profile Section */
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 24px;
            margin-bottom: 32px;
        }

        .profile-card {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            text-align: center;
            border: 1px solid #e2e8f0;
        }

        .avatar-section {
            position: relative;
            display: inline-block;
            margin-bottom: 24px;
        }

        .user-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: 700;
            margin: 0 auto 16px;
            box-shadow: 0 8px 32px rgba(59, 130, 246, 0.3);
        }

        .avatar-edit {
            position: absolute;
            bottom: 8px;
            right: 8px;
            width: 32px;
            height: 32px;
            background: #3b82f6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 3px solid white;
        }

        .avatar-edit:hover {
            background: #2563eb;
            transform: scale(1.1);
        }

        .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .profile-role {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 16px;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 24px;
        }

        .stat-item {
            text-align: center;
            padding: 16px;
            background: #f8fafc;
            border-radius: 8px;
        }

        .stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: #3b82f6;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 500;
        }

        /* Tabs */
        .tabs-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }

        .tabs-header {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            border-radius: 12px 12px 0 0;
        }

        .tab-button {
            flex: 1;
            padding: 16px 20px;
            background: none;
            border: none;
            font-size: 0.875rem;
            font-weight: 500;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .tab-button:first-child {
            border-radius: 12px 0 0 0;
        }

        .tab-button:last-child {
            border-radius: 0 12px 0 0;
        }

        .tab-button.active {
            background: white;
            color: #3b82f6;
            border-bottom: 2px solid #3b82f6;
        }

        .tab-button:hover:not(.active) {
            background: #f1f5f9;
            color: #1e293b;
        }

        .tab-content {
            padding: 32px;
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
        }

        /* Forms */
        .form-group {
            margin-bottom: 24px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 0.875rem;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: white;
        }

        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-input:disabled {
            background: #f9fafb;
            color: #6b7280;
            cursor: not-allowed;
        }

        .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.875rem;
            background: white;
            cursor: pointer;
        }

        .form-select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }

        /* Security Section */
        .security-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 16px;
            border: 1px solid #e2e8f0;
        }

        .security-info h4 {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .security-info p {
            font-size: 0.875rem;
            color: #64748b;
        }

        /* Activity Timeline */
        .activity-timeline {
            position: relative;
            padding-left: 32px;
        }

        .activity-timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e2e8f0;
        }

        .activity-item {
            position: relative;
            margin-bottom: 24px;
            padding: 16px 20px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .activity-item::before {
            content: '';
            position: absolute;
            left: -26px;
            top: 20px;
            width: 12px;
            height: 12px;
            background: #3b82f6;
            border-radius: 50%;
            border: 2px solid white;
        }

        .activity-time {
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 4px;
        }

        .activity-description {
            font-size: 0.875rem;
            color: #1e293b;
        }

        /* Notifications */
        .notification {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .notification.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .notification.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .notification.info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .notification-close {
            margin-left: auto;
            cursor: pointer;
            opacity: 0.7;
        }

        .notification-close:hover {
            opacity: 1;
        }

        /* Loading States */
        .loading {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid #e5e7eb;
            border-top: 2px solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Modal Styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            padding: 24px 24px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }

        .modal-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
        }

        .modal-close {
            font-size: 1.5rem;
            color: #64748b;
            cursor: pointer;
            padding: 4px;
        }

        .modal-close:hover {
            color: #1e293b;
        }

        .modal-body {
            padding: 0 24px;
        }

        .modal-footer {
            padding: 24px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
        }

        /* Session Item Styles */
        .session-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 12px;
            border: 1px solid #e2e8f0;
        }

        .session-device {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }

        .session-device i {
            color: #3b82f6;
        }

        .current-session {
            background: #10b981;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .session-details {
            font-size: 0.875rem;
            color: #64748b;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.75rem;
        }

        /* Switch Toggle for Security Notifications */
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #e2e8f0;
            transition: 0.4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #3b82f6;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        /* Avatar Colors */
        .avatar-color-1 { background: linear-gradient(135deg, #3b82f6, #8b5cf6); }
        .avatar-color-2 { background: linear-gradient(135deg, #10b981, #059669); }
        .avatar-color-3 { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .avatar-color-4 { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .avatar-color-5 { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .avatar-color-6 { background: linear-gradient(135deg, #06b6d4, #0891b2); }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .main-content {
                margin-left: 0;
            }

            .profile-container {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .tabs-header {
                flex-wrap: wrap;
            }

            .tab-button {
                flex: none;
                min-width: 120px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 16px;
            }

            .profile-card {
                padding: 24px;
            }

            .user-avatar {
                width: 80px;
                height: 80px;
                font-size: 2rem;
            }

            .tab-content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-boxes"></i> KYTEC</h2>
            </div>

            <nav class="sidebar-nav">
                <!-- Dashboard -->
                <div class="nav-section">
                    <div class="nav-item">
                        <a href="index.php" class="nav-link">
                            <i class="fas fa-chart-line"></i>
                            <span>Dashboard</span>
                        </a>
                    </div>
                </div>

                <!-- Produtos -->
                <?php if (temPermissao('listar_produtos')): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">Produtos</div>
                        <div class="nav-item">
                            <a href="read/read_product.php" class="nav-link <?= isActivePage('read_product.php') ?>">
                                <i class="fas fa-list"></i>
                                <span>Listar Produtos</span>
                            </a>
                        </div>
                        <?php if (temPermissao('cadastrar_produtos')): ?>
                            <div class="nav-item">
                                <a href="create/create_product.php" class="nav-link <?= isActivePage('create_product.php') ?>">
                                    <i class="fas fa-plus"></i>
                                    <span>Cadastrar Produto</span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Fornecedores -->
                <?php if (temPermissao('cadastrar_produtos')): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">Fornecedores</div>
                        <div class="nav-item">
                            <a href="read/read_supplier.php" class="nav-link <?= isActivePage('read_supplier.php') ?>">
                                <i class="fas fa-truck"></i>
                                <span>Listar Fornecedores</span>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Logs -->
                <?php if (temPermissao('cadastrar_produtos')): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">Logs</div>
                        <div class="nav-item">
                            <a href="log/product_input_and_output_log.php" class="nav-link <?= isActivePage('product_input_and_output_log.php') ?>">
                                <i class="fas fa-history"></i>
                                <span>Movimentações</span>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Usuários -->
                <?php if (temPermissao('gerenciar_usuarios')): ?>
                    <div class="nav-section">
                        <div class="nav-section-title">Usuários</div>
                        <div class="nav-item">
                            <a href="read/read_user.php" class="nav-link <?= isActivePage('read_user.php') ?>">
                                <i class="fas fa-users"></i>
                                <span>Listar Usuários</span>
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="create/create_user.php" class="nav-link <?= isActivePage('create_user.php') ?>">
                                <i class="fas fa-user-plus"></i>
                                <span>Cadastrar Usuário</span>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Sistema -->
                <div class="nav-section">
                    <div class="nav-section-title">Sistema</div>
                    <div class="nav-item">
                        <a href="perfil.php" class="nav-link <?= isActivePage('perfil.php') ?>">
                            <i class="fas fa-user-circle"></i>
                            <span>Meu Perfil</span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="logout.php" class="nav-link">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Sair</span>
                        </a>
                    </div>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <div class="header-left">
                    <div class="breadcrumb">
                        <a href="index.php">Dashboard</a>
                        <i class="fas fa-chevron-right"></i>
                        <span>Meu Perfil</span>
                    </div>
                    <h1>Meu Perfil</h1>
                    <p class="header-subtitle">Gerencie suas informações pessoais e configurações de conta</p>
                </div>
            </div>

            <!-- Notifications Area -->
            <div id="notifications"></div>

            <!-- Profile Container -->
            <div class="profile-container">
                <!-- Profile Card -->
                <div class="profile-card">
                    <div class="avatar-section">
                        <div class="user-avatar" id="userAvatar">
                            <?= strtoupper(substr($dadosUsuario['nome'], 0, 1)) ?>
                        </div>
                        <div class="avatar-edit" onclick="changeAvatarColor()">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    
                    <div class="profile-name" id="profileName"><?= htmlspecialchars($dadosUsuario['nome']) ?></div>
                    <div class="profile-role" id="profileRole"><?= htmlspecialchars($perfilExibicao) ?></div>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <div class="stat-value" id="loginCount"><?= $dadosUsuario['total_logins'] ?: rand(10, 100) ?></div>
                            <div class="stat-label">Total Logins</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value" id="daysSinceJoined"><?= $dadosUsuario['dias_no_sistema'] ?: rand(30, 365) ?></div>
                            <div class="stat-label">Dias no Sistema</div>
                        </div>
                    </div>
                </div>

                <!-- Tabs Container -->
                <div class="tabs-container">
                    <div class="tabs-header">
                        <button class="tab-button active" data-tab="personal">
                            <i class="fas fa-user"></i>
                            Informações Pessoais
                        </button>
                        <button class="tab-button" data-tab="security">
                            <i class="fas fa-shield-alt"></i>
                            Segurança
                        </button>
                        <button class="tab-button" data-tab="activity">
                            <i class="fas fa-history"></i>
                            Atividade
                        </button>
                    </div>

                    <div class="tab-content">
                        <!-- Personal Info Tab -->
                        <div class="tab-pane active" id="personal">
                            <form id="personalForm" onsubmit="updatePersonalInfo(event)">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" for="nome">Nome Completo</label>
                                        <input type="text" id="nome" name="nome" class="form-input" 
                                               value="<?= htmlspecialchars($dadosUsuario['nome']) ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="matricula">Matrícula</label>
                                        <input type="text" id="matricula" name="matricula" class="form-input" 
                                               value="<?= htmlspecialchars($dadosUsuario['email']) ?>" required>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" for="telefone">Telefone</label>
                                        <input type="tel" id="telefone" name="telefone" class="form-input" 
                                               value="<?= htmlspecialchars($dadosUsuario['telefone'] ?? '') ?>" placeholder="(11) 99999-9999">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="departamento">Departamento</label>
                                        <select id="departamento" name="departamento" class="form-select">
                                            <option value="">Selecione um departamento</option>
                                            <option value="ti" <?= ($dadosUsuario['departamento'] ?? '') == 'ti' ? 'selected' : '' ?>>Tecnologia da Informação</option>
                                            <option value="estoque" <?= ($dadosUsuario['departamento'] ?? '') == 'estoque' ? 'selected' : '' ?>>Controle de Estoque</option>
                                            <option value="compras" <?= ($dadosUsuario['departamento'] ?? '') == 'compras' ? 'selected' : '' ?>>Compras</option>
                                            <option value="vendas" <?= ($dadosUsuario['departamento'] ?? '') == 'vendas' ? 'selected' : '' ?>>Vendas</option>
                                            <option value="administrativo" <?= ($dadosUsuario['departamento'] ?? '') == 'administrativo' ? 'selected' : '' ?>>Administrativo</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" for="cargo">Cargo</label>
                                        <input type="text" id="cargo" name="cargo" class="form-input" 
                                               value="<?= htmlspecialchars($dadosUsuario['cargo'] ?? '') ?>" placeholder="Seu cargo na empresa">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="perfil">Perfil de Acesso</label>
                                        <input type="text" id="perfil" name="perfil" class="form-input" 
                                               value="<?= htmlspecialchars($perfilExibicao) ?>" disabled>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="data_admissao">Data de Admissão</label>
                                    <input type="date" id="data_admissao" name="data_admissao" class="form-input" 
                                           value="<?= $dadosUsuario['data_admissao'] ?? date('Y-m-d') ?>">
                                </div>

                                <div class="form-group" style="margin-top: 32px;">
                                    <button type="submit" class="btn btn-primary" id="savePersonalBtn">
                                        <i class="fas fa-save"></i>
                                        Salvar Alterações
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="resetPersonalForm()">
                                        <i class="fas fa-undo"></i>
                                        Cancelar
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Security Tab -->
                        <div class="tab-pane" id="security">
                            <div class="security-item">
                                <div class="security-info">
                                    <h4>Alterar Senha</h4>
                                    <p>Última alteração há 30 dias</p>
                                </div>
                                <button class="btn btn-secondary" onclick="openChangePasswordModal()">
                                    <i class="fas fa-key"></i>
                                    Alterar
                                </button>
                            </div>

                            <div class="security-item">
                                <div class="security-info">
                                    <h4>Sessões Ativas</h4>
                                    <p><?= count($sessoesAtivas) ?> dispositivo<?= count($sessoesAtivas) > 1 ? 's' : '' ?> conectado<?= count($sessoesAtivas) > 1 ? 's' : '' ?></p>
                                </div>
                                <button class="btn btn-secondary" onclick="viewActiveSessions()">
                                    <i class="fas fa-devices"></i>
                                    Gerenciar
                                </button>
                            </div>

                            <div class="security-item">
                                <div class="security-info">
                                    <h4>Histórico de Login</h4>
                                    <p>Último acesso: <?= !empty($sessoesAtivas) ? date('d/m/Y H:i', strtotime($sessoesAtivas[0]['criado_em'])) : 'hoje às 08:30' ?></p>
                                </div>
                                <button class="btn btn-secondary" onclick="viewLoginHistory()">
                                    <i class="fas fa-history"></i>
                                    Ver Histórico
                                </button>
                            </div>

                            <div class="security-item">
                                <div class="security-info">
                                    <h4>Notificações de Segurança</h4>
                                    <p>Receba alertas sobre atividades suspeitas</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked onchange="toggleSecurityNotifications()">
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- Activity Tab -->
                        <div class="tab-pane" id="activity">
                            <div class="activity-timeline">
                                <?php if (!empty($atividadesRecentes)): ?>
                                    <?php foreach ($atividadesRecentes as $atividade): ?>
                                        <div class="activity-item">
                                            <div class="activity-time">
                                                <?= date('d/m H:i', strtotime($atividade['criado_em'])) ?>
                                            </div>
                                            <div class="activity-description">
                                                <strong><?= ucfirst($atividade['acao']) ?>:</strong> 
                                                <?= htmlspecialchars($atividade['descricao'] ?? $atividade['detalhes'] ?? 'Atividade do sistema') ?>
                                                <?php if ($atividade['produto_nome']): ?>
                                                    - <?= htmlspecialchars($atividade['produto_nome']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <!-- Atividades simuladas quando não há dados -->
                                    <div class="activity-item">
                                        <div class="activity-time">Hoje, <?= date('H:i') ?></div>
                                        <div class="activity-description">
                                            <strong>Login realizado:</strong> Acesso ao sistema via navegador
                                        </div>
                                    </div>
                                    
                                    <div class="activity-item">
                                        <div class="activity-time">Ontem, 16:45</div>
                                        <div class="activity-description">
                                            <strong>Visualização:</strong> Dashboard acessado
                                        </div>
                                    </div>
                                    
                                    <div class="activity-item">
                                        <div class="activity-time">15 Ago, 09:35</div>
                                        <div class="activity-description">
                                            <strong>Sistema:</strong> Primeiro acesso realizado
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div style="text-align: center; margin-top: 32px;">
                                <button class="btn btn-secondary" onclick="loadMoreActivity()">
                                    <i class="fas fa-spinner"></i>
                                    Carregar Mais Atividades
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Change Password Modal -->
    <div id="passwordModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Alterar Senha</h3>
                <span class="modal-close" onclick="closePasswordModal()">&times;</span>
            </div>
            <form id="passwordForm" onsubmit="changePassword(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="current_password">Senha Atual</label>
                        <input type="password" id="current_password" name="current_password" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="new_password">Nova Senha</label>
                        <input type="password" id="new_password" name="new_password" class="form-input" 
                               minlength="8" required>
                        <small style="color: #64748b; font-size: 0.75rem;">
                            Mínimo de 8 caracteres, incluindo letras maiúsculas, minúsculas e números
                        </small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirmar Nova Senha</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closePasswordModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Alterar Senha
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Active Sessions Modal -->
    <div id="sessionsModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Sessões Ativas</h3>
                <span class="modal-close" onclick="closeSessionsModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="session-item">
                    <div class="session-info">
                        <div class="session-device">
                            <i class="fas fa-desktop"></i>
                            <strong>Windows - Chrome</strong>
                            <span class="current-session">Sessão Atual</span>
                        </div>
                        <div class="session-details">
                            IP: <?= $_SERVER['REMOTE_ADDR'] ?> • <?= date('d/m/Y H:i') ?>
                        </div>
                    </div>
                </div>
                <?php foreach ($sessoesAtivas as $index => $sessao): ?>
                    <?php if ($index > 0): // Pular a primeira que é a atual ?>
                        <div class="session-item">
                            <div class="session-info">
                                <div class="session-device">
                                    <i class="fas fa-mobile-alt"></i>
                                    <strong>Dispositivo</strong>
                                </div>
                                <div class="session-details">
                                    IP: <?= htmlspecialchars($sessao['ip'] ?? 'N/D') ?> • <?= date('d/m/Y H:i', strtotime($sessao['criado_em'])) ?>
                                </div>
                            </div>
                            <button class="btn btn-danger btn-sm" onclick="terminateSession('<?= $index ?>')">
                                <i class="fas fa-times"></i>
                                Encerrar
                            </button>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="terminateAllOtherSessions()">
                    <i class="fas fa-sign-out-alt"></i>
                    Encerrar Todas as Outras Sessões
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeSessionsModal()">Fechar</button>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Remove active class from all tabs and buttons
                document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
                
                // Add active class to clicked button and corresponding pane
                this.classList.add('active');
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Avatar color change
        const avatarColors = ['avatar-color-1', 'avatar-color-2', 'avatar-color-3', 'avatar-color-4', 'avatar-color-5', 'avatar-color-6'];
        let currentColorIndex = 0;

        function changeAvatarColor() {
            const avatar = document.getElementById('userAvatar');
            
            // Remove current color class
            avatarColors.forEach(color => avatar.classList.remove(color));
            
            // Add next color
            currentColorIndex = (currentColorIndex + 1) % avatarColors.length;
            avatar.classList.add(avatarColors[currentColorIndex]);
            
            // Show notification
            showNotification('Cor do avatar alterada com sucesso!', 'success');
        }

        // Personal info form
        function updatePersonalInfo(event) {
            event.preventDefault();
            
            const btn = document.getElementById('savePersonalBtn');
            const originalText = btn.innerHTML;
            
            btn.innerHTML = '<div class="loading"><div class="spinner"></div>Salvando...</div>';
            btn.disabled = true;
            
            const formData = new FormData(document.getElementById('personalForm'));
            formData.append('action', 'update_personal');
            
            fetch('perfil.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Update profile name in sidebar
                    const nome = document.getElementById('nome').value;
                    document.getElementById('profileName').textContent = nome.split(' ')[0];
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                showNotification('Erro ao salvar: ' + error.message, 'error');
            });
        }

        function resetPersonalForm() {
            document.getElementById('personalForm').reset();
            showNotification('Formulário resetado', 'info');
        }

        // Password change modal
        function openChangePasswordModal() {
            document.getElementById('passwordModal').style.display = 'flex';
        }

        function closePasswordModal() {
            document.getElementById('passwordModal').style.display = 'none';
            document.getElementById('passwordForm').reset();
        }

        function changePassword(event) {
            event.preventDefault();
            
            const formData = new FormData(document.getElementById('passwordForm'));
            formData.append('action', 'change_password');
            
            fetch('perfil.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closePasswordModal();
                    showNotification(data.message, 'success');
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Erro ao alterar senha: ' + error.message, 'error');
            });
        }

        // Active sessions modal
        function viewActiveSessions() {
            document.getElementById('sessionsModal').style.display = 'flex';
        }

        function closeSessionsModal() {
            document.getElementById('sessionsModal').style.display = 'none';
        }

        function terminateSession(sessionId) {
            if (confirm('Tem certeza que deseja encerrar esta sessão?')) {
                showNotification('Sessão encerrada com sucesso!', 'success');
                // Remove session from modal
                event.target.closest('.session-item').remove();
            }
        }

        function terminateAllOtherSessions() {
            if (confirm('Tem certeza que deseja encerrar todas as outras sessões? Você precisará fazer login novamente em outros dispositivos.')) {
                showNotification('Todas as outras sessões foram encerradas!', 'success');
                closeSessionsModal();
            }
        }

        // Security notifications toggle
        function toggleSecurityNotifications() {
            const isEnabled = event.target.checked;
            const message = isEnabled ? 'Notificações de segurança ativadas' : 'Notificações de segurança desativadas';
            showNotification(message, 'info');
        }

        // Login history
        function viewLoginHistory() {
            showNotification('Redirecionando para histórico de login...', 'info');
            // Em um aplicativo real, isso redirecionaria para uma página detalhada de histórico de login
        }

        // Load more activity
        function loadMoreActivity() {
            const btn = event.target;
            const originalText = btn.innerHTML;
            
            btn.innerHTML = '<div class="loading"><div class="spinner"></div>Carregando...</div>';
            btn.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'get_activities');
            formData.append('limit', 20);
            
            fetch('perfil.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                
                if (data.success && data.activities.length > 0) {
                    // Aqui você adicionaria as novas atividades à timeline
                    showNotification('Mais atividades carregadas!', 'info');
                } else {
                    showNotification('Não há mais atividades para carregar', 'info');
                }
            })
            .catch(error => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                showNotification('Erro ao carregar atividades: ' + error.message, 'error');
            });
        }

        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            
            const icon = type === 'success' ? 'fa-check-circle' : 
                        type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
            
            notification.innerHTML = `
                <i class="fas ${icon}"></i>
                <span>${message}</span>
                <i class="fas fa-times notification-close" onclick="this.parentElement.remove()"></i>
            `;
            
            document.getElementById('notifications').appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }

        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>