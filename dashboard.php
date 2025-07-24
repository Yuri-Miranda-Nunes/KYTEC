<?php
session_start();

// Verifica se está logado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: login.php");
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
    <title>Dashboard - Sistema de Estoque</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            display: flex;
            min-height: 100vh;
        }
        .menu-lateral {
            width: 250px;
            background-color: #2c3e50;
            color: white;
            padding: 20px 0;
        }
        .menu-lateral ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .menu-lateral li {
            padding: 10px 20px;
        }
        .menu-lateral li:first-child {
            background-color: #34495e;
            font-weight: bold;
        }
        .menu-lateral a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 8px 0;
        }
        .menu-lateral a:hover {
            background-color: #34495e;
            padding-left: 10px;
            transition: all 0.3s;
        }
        .conteudo {
            flex: 1;
            padding: 20px;
        }
        .header {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .info-usuario {
            background-color: #e8f4f8;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .permissoes {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .btn-sair {
            background-color: #dc3545;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin-top: 10px;
        }
        .btn-sair:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
    <div class="container">
        <nav class="menu-lateral">
            <ul>
                <li><strong>📋 Menu Principal</strong></li>

                <?php if (temPermissao('listar_produtos')): ?>
                    <li><a href="listar_produtos.php">📦 Produtos</a></li>
                <?php endif; ?>

                <?php if (temPermissao('gerenciar_usuarios')): ?>
                    <li><a href="gerenciar_usuarios.php">👥 Usuários</a></li>
                <?php endif; ?>

                <?php if (temPermissao('visualizar_relatorios')): ?>
                    <li><a href="relatorios.php">📊 Relatórios</a></li>
                <?php endif; ?>
                
                <li><a href="logout.php">🚪 Sair</a></li>
            </ul>
        </nav>

        <div class="conteudo">
            <div class="header">
                <h1>Bem-vindo ao Sistema de Estoque!</h1>
                
                <div class="info-usuario">
                    <h3>Informações do Usuário:</h3>
                    <p><strong>Nome:</strong> <?= htmlspecialchars($_SESSION['usuario_nome']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($_SESSION['usuario_email']) ?></p>
                    <p><strong>Perfil:</strong> <?= htmlspecialchars($_SESSION['usuario_perfil']) ?></p>
                </div>

                <div class="permissoes">
                    <h3>Suas Permissões:</h3>
                    <?php if (!empty($_SESSION['permissoes'])): ?>
                        <ul>
                            <?php foreach ($_SESSION['permissoes'] as $permissao): ?>
                                <li><?= htmlspecialchars($permissao) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>Nenhuma permissão específica definida.</p>
                    <?php endif; ?>
                </div>

                <a href="logout.php" class="btn-sair">Sair do Sistema</a>
            </div>

            <!-- Aqui você pode adicionar widgets, resumos, etc. -->
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <h2>Área de Trabalho</h2>
                <p>Selecione uma opção no menu lateral para começar.</p>
                
                <!-- Debug info (remova em produção) -->
                <?php if (isset($_GET['debug'])): ?>
                    <div style="background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px; margin-top: 20px;">
                        <h4>Informações de Debug:</h4>
                        <pre><?php print_r($_SESSION); ?></pre>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>