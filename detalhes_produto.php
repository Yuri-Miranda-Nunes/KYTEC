<?php
require_once 'conexao.php';

if (!isset($_GET['id'])) {
    echo "ID do produto não informado.";
    exit;
}

$bd = new BancoDeDados();

$sql = "SELECT * FROM produtos WHERE id_produto = ?";
$stmt = $bd->pdo->prepare($sql);
$stmt->execute([$_GET['id']]);

$produto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$produto) {
    echo "Produto não encontrado.";
    exit;
}
?>
<h1>Detalhes do Produto</h1>
<p><strong>Nome:</strong> <?= htmlspecialchars($produto['nome']) ?></p>
<p><strong>Código de Barras:</strong> <?= htmlspecialchars($produto['codigo']) ?></p>
<p><strong>Descrição:</strong> <?= htmlspecialchars($produto['descricao']) ?></p>
<p><strong>Estoque Atual:</strong> <?= $produto['estoque_atual'] ?></p>
<p><strong>Preço de Custo:</strong> R$ <?= number_format($produto['preco_unitario'], 2, ',', '.') ?></p>
