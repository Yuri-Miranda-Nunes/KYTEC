<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Apenas admin pode acessar
if ($_SESSION['usuario_perfil'] !== 'admin') {
    echo "Acesso negado. Você não tem permissão para acessar esta página.";
    exit;
}

require_once 'conexao.php';
$bd = new BancoDeDados();
$sql = "SELECT p.*, c.nome as categoria_nome 
        FROM produtos p 
        LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
        ORDER BY p.nome ASC";
$stmt = $bd->pdo->query($sql);
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Lista de Produtos</h2>
<table border="1">
  <tr>
    <th>ID</th>
    <th>Nome</th>
    <th>Tipo</th>
    <th>Categoria</th>
    <th>Estoque</th>
    <th>Preço Venda</th>
  </tr>
  <?php foreach ($produtos as $p): ?>
    <tr>
      <td><?= $p['id_produto'] ?></td>
      <td><?= $p['nome'] ?></td>
      <td><?= $p['tipo'] ?></td>
      <td><?= $p['categoria_nome'] ?? 'Sem Categoria' ?></td>
      <td><?= $p['estoque_atual'] ?></td>
      <td>R$ <?= number_format($p['preco_venda'], 2, ',', '.') ?></td>
    </tr>
  <?php endforeach; ?>
</table>
