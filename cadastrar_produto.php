<?php
require_once 'conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $bd = new BancoDeDados();

    $nome = $_POST['nome'];
    $descricao = $_POST['descricao'];
    $tipo = $_POST['tipo'];
    $unidade = $_POST['unidade'];
    $preco_unitario = $_POST['preco_unitario'];
    $preco_venda = $_POST['preco_venda'];
    $estoque_minimo = $_POST['estoque_minimo'];
    $estoque_atual = $_POST['estoque_atual'];
    $id_categoria = $_POST['id_categoria'];

    $sql = "INSERT INTO produtos 
        (nome, descricao, tipo, unidade, preco_unitario, preco_venda, estoque_minimo, estoque_atual, id_categoria)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $bd->pdo->prepare($sql);
    
    if ($stmt->execute([
        $nome, $descricao, $tipo, $unidade, $preco_unitario, $preco_venda, $estoque_minimo, $estoque_atual, $id_categoria
    ])) {
        echo "Produto cadastrado com sucesso!";
    } else {
        echo "Erro ao cadastrar o produto.";
    }
}
?>
