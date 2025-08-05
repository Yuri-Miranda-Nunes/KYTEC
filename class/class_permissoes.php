<?php
// Script para corrigir as permissões existentes no banco de dados
// Execute este arquivo uma vez para corrigir os dados

require_once '../conexao.php';

try {
    $pdo = (new BancoDeDados())->pdo;
    
    echo "<h2>Correção das Permissões do Sistema</h2>";
    
    // 1. Verificar se as permissões básicas existem na tabela permissoes
    $permissoes_necessarias = [
        'listar_produtos' => 'Listar Produtos',
        'cadastrar_produtos' => 'Cadastrar Produtos',
        'editar_produtos' => 'Editar Produtos',
        'excluir_produtos' => 'Excluir Produtos',
        'gerenciar_usuarios' => 'Gerenciar Usuários'
    ];
    
    echo "<h3>1. Verificando/Inserindo permissões básicas...</h3>";
    
    foreach ($permissoes_necessarias as $nome_permissao => $descricao) {
        // Verifica se a permissão já existe
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM permissoes WHERE nome_permissao = ?");
        $stmt_check->execute([$nome_permissao]);
        
        if ($stmt_check->fetchColumn() == 0) {
            // Insere a permissão se não existir
            $stmt_insert = $pdo->prepare("INSERT INTO permissoes (nome_permissao) VALUES (?)");
            if ($stmt_insert->execute([$nome_permissao])) {
                echo "✅ Permissão '$nome_permissao' inserida com sucesso.<br>";
            } else {
                echo "❌ Erro ao inserir permissão '$nome_permissao'.<br>";
            }
        } else {
            echo "ℹ️ Permissão '$nome_permissao' já existe.<br>";
        }
    }
    
    // 2. Listar usuários sem permissões adequadas
    echo "<h3>2. Verificando usuários e suas permissões...</h3>";
    
    $stmt_usuarios = $pdo->query("SELECT id, nome, email, perfil FROM usuarios WHERE ativo = 1");
    $usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($usuarios as $usuario) {
        echo "<h4>Usuário: {$usuario['nome']} ({$usuario['email']}) - Perfil: {$usuario['perfil']}</h4>";
        
        // Buscar permissões atuais
        $stmt_perm_atual = $pdo->prepare("
            SELECT p.nome_permissao 
            FROM usuario_permissoes up 
            JOIN permissoes p ON up.permissao_id = p.id 
            WHERE up.usuario_id = ?
        ");
        $stmt_perm_atual->execute([$usuario['id']]);
        $permissoes_atuais = $stmt_perm_atual->fetchAll(PDO::FETCH_COLUMN);
        
        echo "Permissões atuais: " . (empty($permissoes_atuais) ? 'Nenhuma' : implode(', ', $permissoes_atuais)) . "<br>";
        
        // Definir permissões ideais baseadas no perfil
        $permissoes_ideais = [];
        switch (strtolower($usuario['perfil'])) {
            case 'admin':
            case 'administrador':
                $permissoes_ideais = array_keys($permissoes_necessarias);
                break;
            case 'estoquista':
                $permissoes_ideais = ['listar_produtos', 'cadastrar_produtos', 'editar_produtos'];
                break;
            case 'vendedor':
                $permissoes_ideais = ['listar_produtos'];
                break;
            default:
                $permissoes_ideais = ['listar_produtos'];
                break;
        }
        
        echo "Permissões ideais para o perfil: " . implode(', ', $permissoes_ideais) . "<br>";
        
        // Verificar se precisa atualizar
        $permissoes_faltando = array_diff($permissoes_ideais, $permissoes_atuais);
        
        if (!empty($permissoes_faltando)) {
            echo "Permissões faltando: " . implode(', ', $permissoes_faltando) . "<br>";
            
            // Adicionar permissões faltando
            foreach ($permissoes_faltando as $permissao_faltando) {
                // Buscar ID da permissão
                $stmt_perm_id = $pdo->prepare("SELECT id FROM permissoes WHERE nome_permissao = ?");
                $stmt_perm_id->execute([$permissao_faltando]);
                $permissao_id = $stmt_perm_id->fetchColumn();
                
                if ($permissao_id) {
                    // Verificar se já não existe (evitar duplicatas)
                    $stmt_check_exists = $pdo->prepare("SELECT COUNT(*) FROM usuario_permissoes WHERE usuario_id = ? AND permissao_id = ?");
                    $stmt_check_exists->execute([$usuario['id'], $permissao_id]);
                    
                    if ($stmt_check_exists->fetchColumn() == 0) {
                        // Inserir na tabela usuario_permissoes
                        $stmt_insert_perm = $pdo->prepare("INSERT INTO usuario_permissoes (usuario_id, permissao_id) VALUES (?, ?)");
                        if ($stmt_insert_perm->execute([$usuario['id'], $permissao_id])) {
                            echo "✅ Permissão '$permissao_faltando' adicionada ao usuário.<br>";
                        } else {
                            echo "❌ Erro ao adicionar permissão '$permissao_faltando' ao usuário.<br>";
                        }
                    }
                }
            }
        } else {
            echo "✅ Usuário já possui todas as permissões necessárias.<br>";
        }
        
        echo "<hr>";
    }
    
    // 3. Mostrar estado final
    echo "<h3>3. Estado final das permissões:</h3>";
    
    $stmt_final = $pdo->query("
        SELECT u.nome, u.email, u.perfil, 
               GROUP_CONCAT(p.nome_permissao SEPARATOR ', ') as permissoes
        FROM usuarios u
        LEFT JOIN usuario_permissoes up ON u.id = up.usuario_id
        LEFT JOIN permissoes p ON up.permissao_id = p.id
        WHERE u.ativo = 1
        GROUP BY u.id, u.nome, u.email, u.perfil
        ORDER BY u.nome
    ");
    
    $usuarios_final = $stmt_final->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Nome</th><th>Email</th><th>Perfil</th><th>Permissões</th></tr>";
    
    foreach ($usuarios_final as $user) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($user['nome']) . "</td>";
        echo "<td>" . htmlspecialchars($user['email']) . "</td>";
        echo "<td>" . htmlspecialchars($user['perfil']) . "</td>";
        echo "<td>" . htmlspecialchars($user['permissoes'] ?? 'Nenhuma') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<h3>✅ Correção concluída!</h3>";
    echo "<p><a href='login.php'>Ir para Login</a> | <a href='dashboard.php'>Ir para Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<h3>❌ Erro durante a correção:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>