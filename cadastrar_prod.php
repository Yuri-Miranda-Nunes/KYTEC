<form method="POST" action="cadastrar_produto.php">
  <input type="text" name="nome" placeholder="Nome do Produto" required>
  <textarea name="descricao" placeholder="Descrição"></textarea>
  <select name="tipo">
    <option value="acabado">Acabado</option>
    <option value="matéria-prima">Matéria-prima</option>
    <option value="outro">Outro</option>
  </select>
  <input type="text" name="unidade" placeholder="Unidade (ex: un, m)">
  <input type="number" step="0.01" name="preco_unitario" placeholder="Preço Unitário">
  <input type="number" step="0.01" name="preco_venda" placeholder="Preço de Venda">
  <input type="number" name="estoque_minimo" placeholder="Estoque Mínimo">
  <input type="number" name="estoque_atual" placeholder="Estoque Atual">
  <input type="number" name="id_categoria" placeholder="ID da Categoria">
  <button type="submit">Cadastrar Produto</button>
</form>
