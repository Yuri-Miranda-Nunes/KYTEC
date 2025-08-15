<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: detalhes_produto.php"); // ajuste o caminho para sua página do produto
    exit;
}

$produto_id = $_POST['produto_id'] ?? null;
$usuario_id = $_POST['usuario_id'] ?? null;
$quantidade = $_POST['quantidade'] ?? null;
$motivo = $_POST['motivo'] ?? 'venda';
$destino = $_POST['destino'] ?? null;
$observacoes = $_POST['observacoes'] ?? null;

if (!$produto_id || !$usuario_id || !$quantidade) {
    die("Dados insuficientes para confirmar a saída.");
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <title>Confirmar Saída de Estoque</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 30px; background: #f5f5f5; }
        .confirm-box { background: white; padding: 20px; border-radius: 6px; max-width: 500px; margin: auto; box-shadow: 0 0 10px rgba(0,0,0,0.1);}
        h2 { margin-bottom: 20px; }
        .info { margin-bottom: 10px; }
        button { background: #e74c3c; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px; }
        button.cancel { background: #999; margin-left: 10px; }
    </style>
</head>
<body>

<div class="confirm-box">
    <h2>Confirmar Saída de Estoque</h2>

    <p class="info"><strong>Produto ID:</strong> <?= htmlspecialchars($produto_id) ?></p>
    <p class="info"><strong>Usuário ID:</strong> <?= htmlspecialchars($usuario_id) ?></p>
    <p class="info"><strong>Quantidade:</strong> <?= htmlspecialchars($quantidade) ?></p>
    <p class="info"><strong>Motivo:</strong> <?= htmlspecialchars($motivo) ?></p>
    <p class="info"><strong>Destino:</strong> <?= htmlspecialchars($destino ?: '-') ?></p>
    <p class="info"><strong>Observações:</strong> <?= nl2br(htmlspecialchars($observacoes ?: '-')) ?></p>

    <form action="processar_saida.php" method="POST" style="margin-top: 20px;">
        <input type="hidden" name="produto_id" value="<?= htmlspecialchars($produto_id) ?>">
        <input type="hidden" name="usuario_id" value="<?= htmlspecialchars($usuario_id) ?>">
        <input type="hidden" name="quantidade" value="<?= htmlspecialchars($quantidade) ?>">
        <input type="hidden" name="motivo" value="<?= htmlspecialchars($motivo) ?>">
        <input type="hidden" name="destino" value="<?= htmlspecialchars($destino) ?>">
        <input type="hidden" name="observacoes" value="<?= htmlspecialchars($observacoes) ?>">

        <button type="submit">Confirmar Saída</button>
        <a href="detalhes_produto.php?id=<?= $produto_id ?>" class="cancel" style="text-decoration:none; padding:10px 20px; background:#999; color:white; border-radius:4px; margin-left:10px;">Cancelar</a>
    </form>
</div>

</body>
</html>
