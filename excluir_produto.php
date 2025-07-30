<?php
session_start();

// Verifica se tem permissão para excluir produtos
if (!in_array('excluir_produtos', $_SESSION['permissoes'])) {
    echo "Acesso negado.";
    exit;
}

// Verifica se está logado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'conexao.php';

// Verifica se foi passado o ID do produto
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['erro'] = "ID do produto não informado.";
    header("Location: listar_produtos.php");
    exit;
}

$id_produto = (int)$_GET['id'];
$bd = new BancoDeDados();

try {
    // Primeiro, verifica se o produto existe
    $sql_check = "SELECT id_produto, nome FROM produtos WHERE id_produto = ?";
    $stmt_check = $bd->pdo->prepare($sql_check);
    $stmt_check->execute([$id_produto]);
    $produto = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$produto) {
        $_SESSION['erro'] = "Produto não encontrado.";
        header("Location: listar_produtos.php");
        exit;
    }
    
    // Se chegou até aqui via POST, processa a exclusão
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verifica se foi confirmada a exclusão
        if (isset($_POST['confirmar']) && $_POST['confirmar'] === 'sim') {
            // Inicia uma transação para garantir consistência
            $bd->pdo->beginTransaction();
            
            try {
                // Verifica se existem movimentações relacionadas ao produto
                $sql_mov = "SELECT COUNT(*) as total FROM movimentacoes WHERE id_produto = ?";
                $stmt_mov = $bd->pdo->prepare($sql_mov);
                $stmt_mov->execute([$id_produto]);
                $movimentacoes = $stmt_mov->fetch(PDO::FETCH_ASSOC);
                
                if ($movimentacoes['total'] > 0) {
                    // Se há movimentações, não permite excluir diretamente
                    // Opção 1: Inativar o produto ao invés de excluir
                    $sql_inativar = "UPDATE produtos SET ativo = 0 WHERE id_produto = ?";
                    $stmt_inativar = $bd->pdo->prepare($sql_inativar);
                    $stmt_inativar->execute([$id_produto]);
                    
                    $bd->pdo->commit();
                    $_SESSION['sucesso'] = "Produto '" . htmlspecialchars($produto['nome']) . "' foi inativado com sucesso (possui histórico de movimentações).";
                } else {
                    // Se não há movimentações, pode excluir
                    $sql_delete = "DELETE FROM produtos WHERE id_produto = ?";
                    $stmt_delete = $bd->pdo->prepare($sql_delete);
                    $stmt_delete->execute([$id_produto]);
                    
                    $bd->pdo->commit();
                    $_SESSION['sucesso'] = "Produto '" . htmlspecialchars($produto['nome']) . "' foi excluído com sucesso.";
                }
                
                header("Location: listar_produtos.php");
                exit;
                
            } catch (Exception $e) {
                $bd->pdo->rollBack();
                $_SESSION['erro'] = "Erro ao excluir produto: " . $e->getMessage();
                header("Location: listar_produtos.php");
                exit;
            }
        } else {
            // Se não foi confirmado, volta para a listagem
            header("Location: listar_produtos.php");
            exit;
        }
    }
    
} catch (Exception $e) {
    $_SESSION['erro'] = "Erro ao processar solicitação: " . $e->getMessage();
    header("Location: listar_produtos.php");
    exit;
}

// Função para verificar permissões
function temPermissao($permissao) {
    return in_array($permissao, $_SESSION['permissoes'] ?? []);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excluir Produto - Sistema de Estoque</title>
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
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }

        .warning-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 24px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
        }

        .title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #64748b;
            margin-bottom: 24px;
            font-size: 0.95rem;
        }

        .product-info {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 32px;
            border: 1px solid #e2e8f0;
        }

        .product-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .product-id {
            color: #64748b;
            font-size: 0.875rem;
        }

        .warning-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 32px;
            color: #991b1b;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .warning-message i {
            color: #dc2626;
        }

        .button-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
            min-width: 120px;
            justify-content: center;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
            color: #475569;
        }

        .back-link {
            position: absolute;
            top: 20px;
            left: 20px;
            color: #64748b;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.875rem;
            transition: color 0.2s ease;
        }

        .back-link:hover {
            color: #3b82f6;
        }

        @media (max-width: 640px) {
            .container {
                padding: 24px;
                margin: 20px 10px;
            }

            .button-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .back-link {
                position: static;
                margin-bottom: 20px;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <a href="listar_produtos.php" class="back-link">
        <i class="fas fa-arrow-left"></i>
        Voltar para Lista
    </a>

    <div class="container">
        <div class="warning-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>

        <h1 class="title">Confirmar Exclusão</h1>
        <p class="subtitle">Esta ação não pode ser desfeita. Tem certeza?</p>

        <div class="product-info">
            <div class="product-name"><?= htmlspecialchars($produto['nome']) ?></div>
            <div class="product-id">ID: <?= $produto['id_produto'] ?></div>
        </div>

        <div class="warning-message">
            <i class="fas fa-info-circle"></i>
            <span>Se este produto possui histórico de movimentações, ele será apenas inativado ao invés de excluído.</span>
        </div>

        <form method="POST" class="button-group">
            <input type="hidden" name="confirmar" value="sim">
            <button type="submit" class="btn btn-danger">
                <i class="fas fa-trash"></i>
                Sim, Excluir
            </button>
            <a href="listar_produtos.php" class="btn btn-secondary">
                <i class="fas fa-times"></i>
                Cancelar
            </a>
        </form>
    </div>

    <script>
        // Adiciona confirmação extra via JavaScript
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!confirm('ATENÇÃO: Tem certeza absoluta que deseja excluir este produto?')) {
                e.preventDefault();
            }
        });

        // Auto-focus no botão cancelar (mais seguro)
        document.querySelector('.btn-secondary').focus();
    </script>
</body>
</html>