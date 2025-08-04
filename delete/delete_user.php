<?php
session_start();

// Verifica se está logado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php");
    exit;
}

// Verifica permissão para gerenciar usuários
if (!in_array('gerenciar_usuarios', $_SESSION['permissoes'])) {
    echo "Acesso negado. Você não tem permissão para excluir usuários.";
    exit;
}

require_once '../conexao.php';

// Verifica se foi passado o ID do usuário
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ../read/read_user.php?erro=id_invalido");
    exit;
}

$id_usuario = (int) $_GET['id'];
$bd = new BancoDeDados();

// Impedir que o usuário exclua a si mesmo
if ($id_usuario === $_SESSION['usuario_id']) {
    header("Location: ../read/read_user.php?erro=nao_pode_excluir_proprio_usuario");
    exit;
}

try {
    // Primeiro, verifica se o usuário existe
    $sql_verificar = "SELECT id, nome, email, perfil, ativo FROM usuarios WHERE id = :id";
    $stmt_verificar = $bd->pdo->prepare($sql_verificar);
    $stmt_verificar->bindParam(':id', $id_usuario, PDO::PARAM_INT);
    $stmt_verificar->execute();
    
    $usuario = $stmt_verificar->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        header("Location: listar_usuarios.php?erro=usuario_nao_encontrado");
        exit;
    }
    
    // Verifica se o usuário tem a permissão de gerenciar usuários
    $sql_permissoes = "SELECT COUNT(*) as total FROM usuario_permissoes up 
                       INNER JOIN permissoes p ON up.permissao_id = p.id 
                       WHERE up.usuario_id = :id AND p.nome_permissao = 'gerenciar_usuarios'";
    $stmt_permissoes = $bd->pdo->prepare($sql_permissoes);
    $stmt_permissoes->bindParam(':id', $id_usuario, PDO::PARAM_INT);
    $stmt_permissoes->execute();
    $permissoes = $stmt_permissoes->fetch(PDO::FETCH_ASSOC);
    
    $tem_gerenciar_usuarios = ($permissoes['total'] > 0);
    
    // Se foi confirmada a exclusão
    if (isset($_POST['confirmar_exclusao']) && $_POST['confirmar_exclusao'] === 'sim') {
        $bd->pdo->beginTransaction();
        
        try {
            // Se tem permissão de gerenciar usuários, apenas desativa o usuário
            if ($tem_gerenciar_usuarios) {
                $sql_desativar = "UPDATE usuarios SET ativo = 0 WHERE id = :id";
                $stmt_desativar = $bd->pdo->prepare($sql_desativar);
                $stmt_desativar->bindParam(':id', $id_usuario, PDO::PARAM_INT);
                $stmt_desativar->execute();
                
                $bd->pdo->commit();
                header("Location: listar_usuarios.php?sucesso=usuario_desativado");
                exit;
            } else {
                // Se não tem permissão de gerenciar usuários, exclui fisicamente
                $sql_excluir = "DELETE FROM usuarios WHERE id = :id";
                $stmt_excluir = $bd->pdo->prepare($sql_excluir);
                $stmt_excluir->bindParam(':id', $id_usuario, PDO::PARAM_INT);
                $stmt_excluir->execute();
                
                $bd->pdo->commit();
                header("Location: listar_usuarios.php?sucesso=usuario_excluido");
                exit;
            }
        } catch (Exception $e) {
            $bd->pdo->rollBack();
            $erro_exclusao = "Erro ao processar exclusão: " . $e->getMessage();
        }
    }
    
} catch (Exception $e) {
    $erro_geral = "Erro ao carregar usuário: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excluir Usuário - Sistema de Estoque</title>
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
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            max-width: 500px;
            width: 100%;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 24px;
            text-align: center;
        }

        .header i {
            font-size: 3rem;
            margin-bottom: 12px;
            opacity: 0.9;
        }

        .header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .header p {
            opacity: 0.9;
            font-size: 0.875rem;
        }

        .content {
            padding: 32px 24px;
        }

        .user-info {
            background: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
            border-left: 4px solid #3b82f6;
        }

        .user-info h3 {
            color: #1e293b;
            font-size: 1.125rem;
            margin-bottom: 8px;
        }

        .user-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            font-size: 0.875rem;
        }

        .user-detail strong {
            color: #374151;
        }

        .user-detail span {
            color: #64748b;
        }

        .perfil-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .perfil-admin {
            background: #fef3c7;
            color: #d97706;
        }

        .perfil-estoquista {
            background: #e0e7ff;
            color: #3730a3;
        }

        .perfil-visualizador {
            background: #f3f4f6;
            color: #374151;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-ativo {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-inativo {
            background: #fee2e2;
            color: #dc2626;
        }

        .warning-box {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .warning-box.danger {
            background: #fee2e2;
            border-color: #ef4444;
        }

        .warning-box i {
            color: #d97706;
            margin-right: 8px;
        }

        .warning-box.danger i {
            color: #ef4444;
        }

        .warning-box p {
            font-size: 0.875rem;
            color: #92400e;
            margin: 0;
        }

        .warning-box.danger p {
            color: #991b1b;
        }

        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 0.875rem;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #f87171;
        }

        .buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 120px;
            justify-content: center;
        }

        .btn-cancel {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .btn-warning:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
        }

        @media (max-width: 480px) {
            .buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <i class="fas fa-user-times"></i>
            <h1>Confirmar Exclusão</h1>
            <p>Esta ação requer confirmação</p>
        </div>

        <div class="content">
            <?php if (isset($erro_geral)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($erro_geral) ?>
                </div>
                <div class="buttons">
                    <a href="listar_usuarios.php" class="btn btn-cancel">
                        <i class="fas fa-arrow-left"></i>
                        Voltar
                    </a>
                </div>
            <?php elseif (isset($erro_exclusao)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($erro_exclusao) ?>
                </div>
                <div class="buttons">
                    <a href="listar_usuarios.php" class="btn btn-cancel">
                        <i class="fas fa-arrow-left"></i>
                        Voltar
                    </a>
                </div>
            <?php else: ?>
                <div class="user-info">
                    <h3>Informações do Usuário</h3>
                    <div class="user-detail">
                        <strong>Nome:</strong>
                        <span><?= htmlspecialchars($usuario['nome']) ?></span>
                    </div>
                    <div class="user-detail">
                        <strong>Email:</strong>
                        <span><?= htmlspecialchars($usuario['email']) ?></span>
                    </div>
                    <div class="user-detail">
                        <strong>Perfil:</strong>
                        <span>
                            <?php 
                            $perfil = $usuario['perfil'];
                            $perfilClass = 'perfil-visualizador';
                            if ($perfil === 'admin') $perfilClass = 'perfil-admin';
                            elseif ($perfil === 'estoquista') $perfilClass = 'perfil-estoquista';
                            ?>
                            <span class="perfil-badge <?= $perfilClass ?>">
                                <?= htmlspecialchars(ucfirst($perfil)) ?>
                            </span>
                        </span>
                    </div>
                    <div class="user-detail">
                        <strong>Status:</strong>
                        <span>
                            <span class="status-badge <?= $usuario['ativo'] ? 'status-ativo' : 'status-inativo' ?>">
                                <?= $usuario['ativo'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </span>
                    </div>
                </div>

                <?php if ($tem_gerenciar_usuarios): ?>
                    <div class="warning-box">
                        <p>
                            <i class="fas fa-info-circle"></i>
                            <strong>Este usuário possui permissão para gerenciar usuários.</strong><br>
                            Por segurança, o usuário será apenas <strong>desativado</strong> em vez de excluído fisicamente. 
                            Ele não conseguirá mais acessar o sistema, mas o histórico será preservado.
                        </p>
                    </div>

                    <form method="POST" action="">
                        <input type="hidden" name="confirmar_exclusao" value="sim">
                        <div class="buttons">
                            <a href="listar_usuarios.php" class="btn btn-cancel">
                                <i class="fas fa-times"></i>
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-user-slash"></i>
                                Desativar Usuário
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="warning-box danger">
                        <p>
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Atenção:</strong> Esta ação é irreversível!<br>
                            O usuário será excluído permanentemente do sistema, pois não possui a permissão de gerenciar usuários.
                        </p>
                    </div>

                    <form method="POST" action="">
                        <input type="hidden" name="confirmar_exclusao" value="sim">
                        <div class="buttons">
                            <a href="listar_usuarios.php" class="btn btn-cancel">
                                <i class="fas fa-times"></i>
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i>
                                Excluir Definitivamente
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>