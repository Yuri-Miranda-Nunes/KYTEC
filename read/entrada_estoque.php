<?php
session_start();

// Verifica se está logado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php");
    exit;
}

// Função para verificar permissões
function temPermissao($permissao)
{
    return in_array($permissao, $_SESSION['permissoes'] ?? []);
}

require_once '../conexao.php';

// Verifica se o ID foi fornecido
$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    $_SESSION['mensagem_erro'] = "ID de produto inválido.";
    header("Location: ../read/read_product.php");
    exit;
}

$bd = new BancoDeDados();

// Buscar dados do produto
try {
    $stmt = $bd->pdo->prepare("SELECT * FROM produtos WHERE id_produto = ?");
    $stmt->execute([$id]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        $_SESSION['mensagem_erro'] = "Produto não encontrado.";
        header("Location: ../read/read_product.php");
        exit;
    }
} catch (Exception $e) {
    $_SESSION['mensagem_erro'] = "Erro ao buscar produto: " . $e->getMessage();
    header("Location: ../read/read_product.php");
    exit;
}

// Buscar fornecedores para o select
try {
    $stmt = $bd->pdo->prepare("SELECT id_fornecedor, nome FROM fornecedores WHERE ativo = 1 ORDER BY nome");
    $stmt->execute();
    $fornecedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $fornecedores = [];
}

// Função para formatar preço
function formatarPreco($preco)
{
    return 'R$ ' . number_format($preco, 2, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrada de Estoque - <?= htmlspecialchars($produto['nome']) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.6;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 24px;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 32px;
            box-shadow: 0 8px 24px rgba(22, 163, 74, 0.3);
            text-align: center;
        }

        .header-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 20px;
        }

        .header h1 {
            font-size: 2.25rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* Product Info Card */
        .product-info {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #16a34a;
        }

        .product-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }

        .product-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #16a34a;
            font-size: 1.5rem;
        }

        .product-details h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .product-details span {
            color: #64748b;
            font-size: 0.9rem;
        }

        .product-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .stat-item {
            text-align: center;
            padding: 16px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #16a34a;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Form Card */
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }

        .form-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-subtitle {
            color: #64748b;
            margin-bottom: 24px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .form-group label .required {
            color: #ef4444;
            margin-left: 4px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: #16a34a;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
        }

        .form-control:hover {
            border-color: #cbd5e1;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .input-group .form-control {
            padding-left: 40px;
        }

        .help-text {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 4px;
        }

        /* Buttons */
        .button-group {
            display: flex;
            gap: 16px;
            justify-content: flex-end;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.4);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
            color: #374151;
        }

        /* Alerts */
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fed7aa;
        }

        /* Calculation Preview */
        .calculation-preview {
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border: 2px solid #bbf7d0;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }

        .preview-title {
            font-weight: 600;
            color: #166534;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .preview-items {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
        }

        .preview-item {
            text-align: center;
        }

        .preview-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: #16a34a;
        }

        .preview-label {
            font-size: 0.8rem;
            color: #15803d;
            margin-top: 2px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .button-group {
                flex-direction: column-reverse;
            }

            .product-stats {
                grid-template-columns: 1fr;
            }

            .preview-items {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-icon">
                <i class="fas fa-plus-circle"></i>
            </div>
            <h1>Entrada de Estoque</h1>
            <p>Registre a entrada de produtos no estoque</p>
        </div>

        <!-- Alerts -->
        <?php if (!empty($_SESSION['mensagem_sucesso'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= $_SESSION['mensagem_sucesso']; unset($_SESSION['mensagem_sucesso']); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($_SESSION['mensagem_erro'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?= $_SESSION['mensagem_erro']; unset($_SESSION['mensagem_erro']); ?>
            </div>
        <?php endif; ?>

        <!-- Product Info -->
        <div class="product-info">
            <div class="product-header">
                <div class="product-icon">
                    <i class="fas fa-cube"></i>
                </div>
                <div class="product-details">
                    <h3><?= htmlspecialchars($produto['nome']) ?></h3>
                    <span>Código: <?= htmlspecialchars($produto['codigo']) ?></span>
                </div>
            </div>

            <div class="product-stats">
                <div class="stat-item">
                    <div class="stat-value"><?= $produto['estoque_atual'] ?></div>
                    <div class="stat-label">Estoque Atual</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= $produto['estoque_minimo'] ?></div>
                    <div class="stat-label">Estoque Mínimo</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= formatarPreco($produto['preco_unitario']) ?></div>
                    <div class="stat-label">Preço Unitário</div>
                </div>
            </div>
        </div>

        <!-- Form -->
        <div class="form-card">
            <div class="form-title">
                <i class="fas fa-clipboard-list"></i>
                Dados da Entrada
            </div>
            <div class="form-subtitle">
                Preencha as informações da entrada de estoque
            </div>

            <form action="processar_entrada.php" method="POST" id="entradaForm">
                <input type="hidden" name="produto_id" value="<?= $produto['id_produto'] ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="quantidade">Quantidade <span class="required">*</span></label>
                        <div class="input-group">
                            <i class="input-icon fas fa-hashtag"></i>
                            <input type="number" id="quantidade" name="quantidade" class="form-control" min="1" required>
                        </div>
                        <div class="help-text">Quantidade de produtos a ser adicionada ao estoque</div>
                    </div>

                    <div class="form-group">
                        <label for="valor_unitario">Valor Unitário</label>
                        <div class="input-group">
                            <i class="input-icon fas fa-dollar-sign"></i>
                            <input type="number" step="0.01" id="valor_unitario" name="valor_unitario" class="form-control" placeholder="<?= number_format($produto['preco_unitario'], 2, ',', '.') ?>">
                        </div>
                        <div class="help-text">Deixe em branco para usar o preço padrão (<?= formatarPreco($produto['preco_unitario']) ?>)</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="fornecedor_id">Fornecedor</label>
                        <select id="fornecedor_id" name="fornecedor_id" class="form-control">
                            <option value="">Selecione um fornecedor (opcional)</option>
                            <?php foreach ($fornecedores as $fornecedor): ?>
                                <option value="<?= $fornecedor['id_fornecedor'] ?>">
                                    <?= htmlspecialchars($fornecedor['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="nota_fiscal">Nota Fiscal</label>
                        <div class="input-group">
                            <i class="input-icon fas fa-receipt"></i>
                            <input type="text" id="nota_fiscal" name="nota_fiscal" class="form-control" placeholder="Ex: NF-123456">
                        </div>
                        <div class="help-text">Número da nota fiscal (opcional)</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="observacoes">Observações</label>
                    <textarea id="observacoes" name="observacoes" class="form-control" rows="3" placeholder="Informações adicionais sobre a entrada..."></textarea>
                </div>

                <!-- Calculation Preview -->
                <div class="calculation-preview" id="previewCalculation" style="display: none;">
                    <div class="preview-title">
                        <i class="fas fa-calculator"></i>
                        Resumo da Entrada
                    </div>
                    <div class="preview-items">
                        <div class="preview-item">
                            <div class="preview-value" id="previewQuantidade">-</div>
                            <div class="preview-label">Quantidade</div>
                        </div>
                        <div class="preview-item">
                            <div class="preview-value" id="previewNovoEstoque">-</div>
                            <div class="preview-label">Novo Estoque</div>
                        </div>
                        <div class="preview-item">
                            <div class="preview-value" id="previewValorTotal">-</div>
                            <div class="preview-label">Valor Total</div>
                        </div>
                    </div>
                </div>

                <div class="button-group">
                    <a href="focus_product.php?id=<?= $produto['id_produto'] ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-plus-circle"></i>
                        Registrar Entrada
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const estoqueAtual = <?= $produto['estoque_atual'] ?>;
        const precoUnitario = <?= $produto['preco_unitario'] ?>;

        // Elementos do formulário
        const quantidadeInput = document.getElementById('quantidade');
        const valorUnitarioInput = document.getElementById('valor_unitario');
        const previewDiv = document.getElementById('previewCalculation');
        const previewQuantidade = document.getElementById('previewQuantidade');
        const previewNovoEstoque = document.getElementById('previewNovoEstoque');
        const previewValorTotal = document.getElementById('previewValorTotal');
        const submitBtn = document.getElementById('submitBtn');

        // Função para atualizar a prévia dos cálculos
        function atualizarPreview() {
            const quantidade = parseInt(quantidadeInput.value) || 0;
            const valorUnit = parseFloat(valorUnitarioInput.value) || precoUnitario;

            if (quantidade > 0) {
                const novoEstoque = estoqueAtual + quantidade;
                const valorTotal = quantidade * valorUnit;

                previewQuantidade.textContent = quantidade.toLocaleString('pt-BR');
                previewNovoEstoque.textContent = novoEstoque.toLocaleString('pt-BR') + ' unidades';
                previewValorTotal.textContent = 'R$ ' + valorTotal.toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

                previewDiv.style.display = 'block';
                previewDiv.style.animation = 'fadeIn 0.3s ease';
            } else {
                previewDiv.style.display = 'none';
            }
        }

        // Event listeners para atualização em tempo real
        quantidadeInput.addEventListener('input', atualizarPreview);
        valorUnitarioInput.addEventListener('input', atualizarPreview);

        // Validação do formulário
        document.getElementById('entradaForm').addEventListener('submit', function(e) {
            const quantidade = parseInt(quantidadeInput.value);
            
            if (!quantidade || quantidade <= 0) {
                e.preventDefault();
                alert('Por favor, informe uma quantidade válida.');
                quantidadeInput.focus();
                return false;
            }

            // Confirmação antes do envio
            const valorUnit = parseFloat(valorUnitarioInput.value) || precoUnitario;
            const valorTotal = quantidade * valorUnit;
            const novoEstoque = estoqueAtual + quantidade;

            const confirmacao = confirm(
                `Confirmar entrada de estoque?\n\n` +
                `Produto: <?= addslashes($produto['nome']) ?>\n` +
                `Quantidade: ${quantidade.toLocaleString('pt-BR')} unidades\n` +
                `Estoque atual: ${estoqueAtual.toLocaleString('pt-BR')} → ${novoEstoque.toLocaleString('pt-BR')} unidades\n` +
                `Valor total: R$ ${valorTotal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`
            );

            if (!confirmacao) {
                e.preventDefault();
                return false;
            }

            // Desabilitar botão para evitar duplo clique
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
        });

        // Animação de entrada suave
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.product-info, .form-card');
            elements.forEach((element, index) => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    element.style.transition = 'all 0.6s ease';
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, index * 200);
            });
        });

        // Foco automático no campo quantidade
        quantidadeInput.focus();

        // Formatação do campo valor unitário
        valorUnitarioInput.addEventListener('blur', function() {
            if (this.value) {
                const valor = parseFloat(this.value);
                if (!isNaN(valor)) {
                    this.value = valor.toFixed(2);
                }
            }
        });
    </script>

    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .form-control:invalid {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }
    </style>
</body>
</html>