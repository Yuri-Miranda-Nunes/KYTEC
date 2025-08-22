<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Verifica se está logado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

require_once '../includes/functions.php';
require_once '../conexao.php';

// Verifica permissões
if (!temPermissao('listar_produtos')) {
    http_response_code(403);
    echo json_encode(['error' => 'Sem permissão']);
    exit;
}

try {
    $bd = new BancoDeDados();
    $pdo = $bd->pdo;
    
    // Pega o número de dias (padrão 7)
    $dias = isset($_GET['dias']) ? (int)$_GET['dias'] : 7;
    
    // Valida o número de dias
    if ($dias < 1 || $dias > 365) {
        $dias = 7;
    }
    
    $labels = [];
    $entradas = [];
    $saidas = [];
    
    for ($i = $dias - 1; $i >= 0; $i--) {
        $data = date('Y-m-d', strtotime("-$i days"));
        
        // Formato da label baseado no período
        if ($dias <= 7) {
            $labels[] = date('d/m', strtotime($data));
        } elseif ($dias <= 31) {
            $labels[] = date('d/m', strtotime($data));
        } else {
            // Para períodos maiores, agrupa por semana
            if ($i % 7 == 0) {
                $labels[] = date('d/m', strtotime($data));
            } else {
                continue;
            }
        }
        
        // Entradas do dia
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(quantidade), 0) 
            FROM movimentacoes_estoque 
            WHERE DATE(criado_em) = ? AND tipo_movimentacao = 'entrada'
        ");
        $stmt->execute([$data]);
        $entradas[] = (int)$stmt->fetchColumn();
        
        // Saídas do dia
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(quantidade), 0) 
            FROM movimentacoes_estoque 
            WHERE DATE(criado_em) = ? AND tipo_movimentacao = 'saida'
        ");
        $stmt->execute([$data]);
        $saidas[] = (int)$stmt->fetchColumn();
    }
    
    // Para períodos maiores que 31 dias, agrupa por semanas
    if ($dias > 31) {
        $labels_agrupadas = [];
        $entradas_agrupadas = [];
        $saidas_agrupadas = [];
        
        $semanas = ceil($dias / 7);
        for ($s = 0; $s < $semanas; $s++) {
            $data_inicio = date('Y-m-d', strtotime("-" . (($s + 1) * 7) . " days"));
            $data_fim = date('Y-m-d', strtotime("-" . ($s * 7) . " days"));
            
            $labels_agrupadas[] = date('d/m', strtotime($data_inicio)) . ' - ' . date('d/m', strtotime($data_fim));
            
            // Entradas da semana
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(quantidade), 0) 
                FROM movimentacoes_estoque 
                WHERE DATE(criado_em) BETWEEN ? AND ? AND tipo_movimentacao = 'entrada'
            ");
            $stmt->execute([$data_inicio, $data_fim]);
            $entradas_agrupadas[] = (int)$stmt->fetchColumn();
            
            // Saídas da semana
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(quantidade), 0) 
                FROM movimentacoes_estoque 
                WHERE DATE(criado_em) BETWEEN ? AND ? AND tipo_movimentacao = 'saida'
            ");
            $stmt->execute([$data_inicio, $data_fim]);
            $saidas_agrupadas[] = (int)$stmt->fetchColumn();
        }
        
        // Inverte arrays para mostrar cronologicamente
        $labels = array_reverse($labels_agrupadas);
        $entradas = array_reverse($entradas_agrupadas);
        $saidas = array_reverse($saidas_agrupadas);
    }
    
    // Resposta JSON
    echo json_encode([
        'success' => true,
        'labels' => $labels,
        'entradas' => $entradas,
        'saidas' => $saidas,
        'periodo' => $dias . ' dias',
        'total_entradas' => array_sum($entradas),
        'total_saidas' => array_sum($saidas),
        'saldo' => array_sum($entradas) - array_sum($saidas)
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro no banco de dados',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro interno',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>