<?php
session_start();
require_once '../conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['codigo_barras'])) {
    $codigo = trim($_POST['codigo_barras']);

    $bd = new BancoDeDados();

    $sql = "SELECT id_produto FROM produtos WHERE codigo = ?";
    $stmt = $bd->pdo->prepare($sql);
    $stmt->execute([$codigo]);

    $produto = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($produto) {
        header("Location: detalhes_produto.php?id=" . $produto['id_produto']);
        exit;
    } else {
        $_SESSION['erro'] = "Produto com código '{$codigo}' não encontrado.";
        header("Location: buscar_produto_codigo.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Leitor de Código de Barras</title>
</head>
<body>
    <?php if (!empty($_SESSION['erro'])): ?>
        <p style="color: red;"><?= $_SESSION['erro']; unset($_SESSION['erro']); ?></p>
    <?php endif; ?>

    <form method="POST" id="formCodigo">
        <label for="codigo_barras">Escaneie o Código de Barras:</label><br>
        <input type="text" name="codigo_barras" id="codigo_barras" autofocus autocomplete="off">
    </form>

    <script>
        document.getElementById('codigo_barras').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('formCodigo').submit();
            }
        });
    </script>
</body>
</html>
