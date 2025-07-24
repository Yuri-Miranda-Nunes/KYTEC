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
            text-align: center;
        }
        .menu-lateral a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 8px 0;
            border-radius: 4px;
        }
        .menu-lateral a:hover {
            background-color: #34495e;
            padding-left: 10px;
            transition: all 0.3s;
        }
        .menu-secao {
            color: #bdc3c7;
            font-size: 0.9rem;
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 5px;
            text-transform: uppercase;
            border-bottom: 1px solid #34495e;
            padding-bottom: 5px;
        }
        .menu-item {
            padding: 8px 20px;
        }
        .menu-item a {
            padding: 8px 10px;
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
        .permissoes ul {
            margin: 10px 0;
            padding-left: 20px;
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
        .estatisticas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .card-stat {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        .card-stat h3 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }
        .card-stat .numero {
            font-size: 2rem;
            font-weight: bold;
            color: #3498db;
        }
    </style>
</head>
<body>
    <div class="container">
        <nav class="menu-lateral">
            <ul>
                <li><strong>📋 Sistema de Estoque</strong></li>

                <!-- Seção Produtos -->
                <?php if (temPermissao('listar_produtos')): ?>
                    <li class="menu-secao">📦 Produtos</li>
                    <li class="menu-item">
                        <a href="listar_produtos.php">📋 Listar Produtos</a>
                    </li>
                    <?php if (temPermissao('cadastrar_produtos')): ?>
                        <li class="menu-item">
                            <a href="cadastrar_produto.php">➕ Cadastrar Produto</a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Seção Usuários -->
                <?php if (temPermissao('gerenciar_usuarios')): ?>
                    <li class="menu-secao">👥 Usuários</li>
                    <li class="menu-item">
                        <a href="listar_usuarios.php">📋 Listar Usuários</a>
                    </li>
                    <li class="menu-item">
                        <a href="cadastrar_usuario.php">➕ Cadastrar Usuário</a>
                    </li>
                <?php endif; ?>

                <!-- Seção Estoque (se houver permissões relacionadas) -->
                <?php if (temPermissao('listar_produtos')): ?>
                    <li class="menu-secao">📊 Estoque</li>
                    <li class="menu-item">
                        <a href="entradas_estoque.php">📈 Entradas</a>
                    </li>
                    <li class="menu-item">
                        <a href="saidas_estoque.php">📉 Saídas</a>
                    </li>
                <?php endif; ?>

                <!-- Seção Relatórios -->
                <?php if (temPermissao('listar_produtos')): ?>
                    <li class="menu-secao">📋 Relatórios</li>
                    <li class="menu-item">
                        <a href="relatorio_estoque.php">📊 Relatório de Estoque</a>
                    </li>
                    <?php if (temPermissao('gerenciar_usuarios')): ?>
                        <li class="menu-item">
                            <a href="relatorio_usuarios.php">👥 Relatório de Usuários</a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Seção Sistema -->
                <li class="menu-secao">⚙️ Sistema</li>
                <li class="menu-item">
                    <a href="perfil.php">👤 Meu Perfil</a>
                </li>
                <li class="menu-item">
                    <a href="logout.php">🚪 Sair</a>
                </li>
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
                                <li>
                                    <?php
                                    // Traduzir permissões para português
                                    $traducoes = [
                                        'listar_produtos' => '📋 Listar Produtos',
                                        'cadastrar_produtos' => '➕ Cadastrar Produtos', 
                                        'editar_produtos' => '✏️ Editar Produtos',
                                        'excluir_produtos' => '🗑️ Excluir Produtos',
                                        'gerenciar_usuarios' => '👑 Gerenciar Usuários'
                                    ];
                                    echo $traducoes[$permissao] ?? ucfirst(str_replace('_', ' ', $permissao));
                                    ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>Nenhuma permissão específica definida.</p>
                    <?php endif; ?>
                </div>

                <a href="logout.php" class="btn-sair">🚪 Sair do Sistema</a>
            </div>

            <!-- Dashboard com estatísticas -->
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <h2>📊 Painel de Controle</h2>
                <p>Bem-vindo(a), <strong><?= htmlspecialchars($_SESSION['usuario_nome']) ?></strong>! Selecione uma opção no menu lateral para começar.</p>
                
                <!-- Cards de estatísticas rápidas -->
                <?php if (temPermissao('listar_produtos')): ?>
                    <div class="estatisticas">
                        <?php
                        // Buscar estatísticas básicas do sistema
                        require_once 'conexao.php';
                        try {
                            $bd = new BancoDeDados();
                            
                            // Total de produtos
                            $stmt_produtos = $bd->pdo->query("SELECT COUNT(*) FROM produtos WHERE ativo = 1");
                            $total_produtos = $stmt_produtos->fetchColumn();
                            
                            // Produtos com estoque baixo
                            $stmt_estoque_baixo = $bd->pdo->query("SELECT COUNT(*) FROM produtos WHERE estoque_atual <= estoque_minimo AND ativo = 1");
                            $estoque_baixo = $stmt_estoque_baixo->fetchColumn();
                            
                            // Total de usuários (se tiver permissão)
                            $total_usuarios = 0;
                            if (temPermissao('gerenciar_usuarios')) {
                                $stmt_usuarios = $bd->pdo->query("SELECT COUNT(*) FROM usuarios WHERE ativo = 1");
                                $total_usuarios = $stmt_usuarios->fetchColumn();
                            }
                        } catch (Exception $e) {
                            $total_produtos = 0;
                            $estoque_baixo = 0;
                            $total_usuarios = 0;
                        }
                        ?>
                        
                        <div class="card-stat">
                            <h3>Total de Produtos</h3>
                            <div class="numero"><?= $total_produtos ?></div>
                        </div>
                        
                        <div class="card-stat">
                            <h3>Estoque Baixo</h3>
                            <div class="numero" style="color: <?= $estoque_baixo > 0 ? '#e74c3c' : '#27ae60' ?>;">
                                <?= $estoque_baixo ?>
                            </div>
                        </div>
                        
                        <?php if (temPermissao('gerenciar_usuarios')): ?>
                            <div class="card-stat">
                                <h3>Usuários Ativos</h3>
                                <div class="numero"><?= $total_usuarios ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-stat">
                            <h3>Seu Perfil</h3>
                            <div style="font-size: 1.2rem; font-weight: bold; color: #2c3e50; margin-top: 10px;">
                                <?= htmlspecialchars(ucfirst($_SESSION['usuario_perfil'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Debug info (remova em produção) -->
                <?php if (isset($_GET['debug'])): ?>
                    <div style="background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px; margin-top: 20px;">
                        <h4>🔧 Informações de Debug:</h4>
                        <pre><?php print_r($_SESSION); ?></pre>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>