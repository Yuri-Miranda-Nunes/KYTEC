<?php
require_once '../conexao.php'; // Ajuste o caminho para onde está seu arquivo BancoDeDados.php
require_once '../log/log_manager.php';

// Instancia a classe de conexão
$banco = new BancoDeDados();
$pdo = $banco->pdo; // Pega o objeto PDO

// Agora instancia o LogManager passando o PDO
$logManager = new LogManager($pdo);

// Recebe os dados do formulário (exemplo)
$produto_id = $_POST['produto_id'] ?? null;
$usuario_id = $_POST['usuario_id'] ?? null;
$quantidade = $_POST['quantidade'] ?? null;
$motivo = $_POST['motivo'] ?? 'venda';
$destino = $_POST['destino'] ?? null;
$observacoes = $_POST['observacoes'] ?? null;

try {
    $logManager->registrarSaidaEstoque($produto_id, $usuario_id, $quantidade, $motivo, $destino, $observacoes);
    echo "Saída registrada com sucesso!";
} catch (Exception $e) {
    echo "Erro ao registrar saída: " . $e->getMessage();
}