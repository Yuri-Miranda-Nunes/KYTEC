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

// Função para formatar preço
function formatarPreco($preco)
{
    return 'R$ ' . number_format($preco, 2, ',', '.');
}

// Motivos predefinidos para saída
$motivosSaida = [
    'venda' => 'Venda',
    'uso_interno' => 'Uso Interno',
    'transferencia' => 'Transferência',
    'perda' => 'Perda/Avaria',
    'devolucao' => 'Devolução',
    'amostra' => 'Amostra',
    'producao' => 'Produção',
    'outros' => 'Outros'
];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saída de Estoque - <?= htmlspecialchars($produto['nome']) ?></title>
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
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 32px;
            box-shadow: 0 8px 24px rgba(239, 68, 68, 0.3);
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
            border-left: 4px solid #ef4444;
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
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ef4444;
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
            margin-bottom: 4px;
        }

        .stat-value.current {
            color: #ef4444;
        }

        .stat-value.minimum {
            color: #f59e0b;
        }

        .stat-value.price {
            color: #16a34a;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Stock Warning */
        .stock-warning {
            background: linear-gradient(135deg, #fef3c7, #fed7aa);
            border: 2px solid #f59e0b;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .stock-warning.critical {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border-color: #ef4444;
        }

        .warning-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            background: rgba(255, 255, 255, 0.8);
        }

        .warning-content {
            flex: 1;
        }

        .warning-title {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 4px;
        }

        .warning-message {
            font-size: 0.85rem;
            opacity: 0.9;
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
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        .form-control:hover {
            border-color: #cbd5e1;
        }

        .form-control.error {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
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

        .error-text {
            font-size: 0.8rem;
            color: #ef4444;
            margin-top: 4px;
            display: none;
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

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
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

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
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
            background: linear-gradient(135deg, #fef2f2, #fee2e2);
            border: 2px solid #fecaca;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }

        .preview-title {
            font-weight: 600;
            color: #991b1b;
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
            color: #ef4444;
        }

        .preview-label {
            font-size: 0.8rem;
            color: #dc2626;
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

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-icon">
                <i class="fas fa-minus-circle"></i>
            </div>
            <h1>Saída de Estoque</h1>
            <p>Registre a saída de produtos do estoque</p>
        </div>

        <!-- Alerts -->
       

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
                    <div class="stat-value current"><?= $produto['estoque_atual'] ?></div>
                    <div class="stat-label">Estoque Atual</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value minimum"><?= $produto['estoque_minimo'] ?></div>
                    <div class="stat-label">Estoque Mínimo</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value price"><?= formatarPreco($produto['preco_unitario']) ?></div>
                    <div class="stat-label">Preço Unitário</div>
                </div>
            </div>
        </div>

        <!-- Stock Warning -->
        <?php 
        $showWarning = false;
        $warningClass = '';
        $warningTitle = '';
        $warningMessage = '';
        $warningIcon = '';

        if ($produto['estoque_atual'] <= $produto['estoque_minimo']) {
            $showWarning = true;
            $warningClass = 'critical';
            $warningTitle = 'Atenção: Estoque Crítico!';
            $warningMessage = 'O estoque atual já está no nível mínimo ou abaixo. Tenha cuidado ao registrar saídas.';
            $warningIcon = 'fas fa-exclamation-triangle';
        } elseif ($produto['estoque_atual'] <= ($produto['estoque_minimo'] * 2)) {
            $showWarning = true;
            $warningClass = '';
            $warningTitle = 'Cuidado: Estoque Baixo';
            $warningMessage = 'O estoque está se aproximando do limite mínimo. Considere a necessidade de reposição.';
            $warningIcon = 'fas fa-exclamation-circle';
        }
        ?>

        <?php if ($showWarning): ?>
        <div class="stock-warning <?= $warningClass ?>">
            <div class="warning-icon">
                <i class="<?= $warningIcon ?>" style="color: <?= $warningClass === 'critical' ? '#ef4444' : '#f59e0b' ?>;"></i>
            </div>
            <div class="warning-content">
                <div class="warning-title" style="color: <?= $warningClass === 'critical' ? '#dc2626' : '#d97706' ?>"><?= $warningTitle ?></div>
                <div class="warning-message" style="color: <?= $warningClass === 'critical' ? '#991b1b' : '#92400e' ?>"><?= $warningMessage ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <div class="form-card">
            <div class="form-title">
                <i class="fas fa-clipboard-list"></i>
                Dados da Saída
            </div>
            <div class="form-subtitle">
                Preencha as informações da saída de estoque
            </div>

            <form action="processar_saida.php" method="POST" id="saidaForm">
                <input type="hidden" name="produto_id" value="<?= $produto['id_produto'] ?>">
                <input type="hidden" name="usuario_id" value="<?= $_SESSION['usuario_id'] ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="quantidade">Quantidade <span class="required">*</span></label>
                        <div class="input-group">
                            <i class="input-icon fas fa-hashtag"></i>
                            <input type="number" id="quantidade" name="quantidade" class="form-control" min="1" max="<?= $produto['estoque_atual'] ?>" required>
                        </div>
                        <div class="help-text">Máximo disponível: <?= $produto['estoque_atual'] ?> unidades</div>
                        <div class="error-text" id="quantidadeError">Quantidade inválida</div>
                    </div>

                    <div class="form-group">
                        <label for="motivo">Motivo <span class="required">*</span></label>
                        <select id="motivo" name="motivo" class="form-control" required>
                            <option value="">Selecione o motivo</option>
                            <?php foreach ($motivosSaida as $value => $label): ?>
                                <option value="<?= $value ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="destino">Destino</label>
                    <div class="input-group">
                        <i class="input-icon fas fa-map-marker-alt"></i>
                        <input type="text" id="destino" name="destino" class="form-control" placeholder="Ex: Cliente João, Setor Produção, etc.">
                    </div>
                    <div class="help-text">Para onde está sendo destinado o produto (opcional)</div>
                </div>

                <div class="form-group">
                    <label for="observacoes">Observações</label>
                    <textarea id="observacoes" name="observacoes" class="form-control" rows="3" placeholder="Informações adicionais sobre a saída..."></textarea>
                </div>

                <!-- Calculation Preview -->
                <div class="calculation-preview" id="previewCalculation" style="display: none;">
                    <div class="preview-title">
                        <i class="fas fa-calculator"></i>
                        Resumo da Saída
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
                    <button type="submit" class="btn btn-danger" id="submitBtn">
                        <i class="fas fa-minus-circle"></i>
                        Registrar Saída
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const estoqueAtual = <?= $produto['estoque_atual'] ?>;
        const estoqueMinimo = <?= $produto['estoque_minimo'] ?>;
        const precoUnitario = <?= $produto['preco_unitario'] ?>;

        // Elementos do formulário
        const quantidadeInput = document.getElementById('quantidade');
        const motivoSelect = document.getElementById('motivo');
        const previewDiv = document.getElementById('previewCalculation');
        const previewQuantidade = document.getElementById('previewQuantidade');
        const previewNovoEstoque = document.getElementById('previewNovoEstoque');
        const previewValorTotal = document.getElementById('previewValorTotal');
        const submitBtn = document.getElementById('submitBtn');
        const quantidadeError = document.getElementById('quantidadeError');

        // Função para validar quantidade
        function validarQuantidade() {
            const quantidade = parseInt(quantidadeInput.value) || 0;
            const isValid = quantidade > 0 && quantidade <= estoqueAtual;
            
            if (!isValid && quantidadeInput.value) {
                quantidadeInput.classList.add('error');
                quantidadeError.style.display = 'block';
                if (quantidade > estoqueAtual) {
                    quantidadeError.textContent = `Quantidade não pode ser maior que ${estoqueAtual}`;
                } else {
                    quantidadeError.textContent = 'Quantidade deve ser maior que 0';
                }
            } else {
                quantidadeInput.classList.remove('error');
                quantidadeError.style.display = 'none';
            }
            
            return isValid;
        }

        // Função para atualizar a prévia dos cálculos
        function atualizarPreview() {
            const quantidade = parseInt(quantidadeInput.value) || 0;

            if (quantidade > 0 && quantidade <= estoqueAtual) {
                const novoEstoque = estoqueAtual - quantidade;
                const valorTotal = quantidade * precoUnitario;

                previewQuantidade.textContent = quantidade.toLocaleString('pt-BR');
                previewNovoEstoque.textContent = novoEstoque.toLocaleString('pt-BR') + ' unidades';
                previewValorTotal.textContent = 'R$ ' + valorTotal.toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

                // Adicionar alerta se o novo estoque ficar abaixo do mínimo
                if (novoEstoque < estoqueMinimo) {
                    previewNovoEstoque.textContent += ' ⚠️';
                    previewNovoEstoque.style.color = '#ef4444';
                } else if (novoEstoque <= estoqueMinimo * 1.5) {
                    previewNovoEstoque.textContent += ' ⚠️';
                    previewNovoEstoque.style.color = '#f59e0b';
                } else {
                    previewNovoEstoque.style.color = '#ef4444';
                }

                previewDiv.style.display = 'block';
                previewDiv.style.animation = 'fadeIn 0.3s ease';
            } else {
                previewDiv.style.display = 'none';
            }
        }

        // Event listeners
        quantidadeInput.addEventListener('input', function() {
            validarQuantidade();
            atualizarPreview();
        });

        quantidadeInput.addEventListener('blur', validarQuantidade);

        // Validação do formulário
        document.getElementById('saidaForm').addEventListener('submit', function(e) {
            const quantidade = parseInt(quantidadeInput.value);
            const motivo = motivoSelect.value;
            
            if (!validarQuantidade()) {
                e.preventDefault();
                quantidadeInput.focus();
                return false;
            }

            if (!motivo) {
                e.preventDefault();
                alert('Por favor, selecione um motivo para a saída.');
                motivoSelect.focus();
                return false;
            }

            // Verificação especial para estoque crítico
            const novoEstoque = estoqueAtual - quantidade;
            let confirmationMessage = 
                `Confirmar saída de estoque?\n\n` +
                `Produto: <?= addslashes($produto['nome']) ?>\n` +
                `Quantidade: ${quantidade.toLocaleString('pt-BR')} unidades\n` +
                `Motivo: ${motivoSelect.options[motivoSelect.selectedIndex].text}\n` +
                `Estoque atual: ${estoqueAtual.toLocaleString('pt-BR')} → ${novoEstoque.toLocaleString('pt-BR')} unidades\n` +
                `Valor total: R$ ${(quantidade * precoUnitario).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

            if (novoEstoque < estoqueMinimo) {
                confirmationMessage += `\n\n⚠️ ATENÇÃO: O estoque ficará abaixo do mínimo recomendado (${estoqueMinimo} unidades)!`;
            }

            const confirmacao = confirm(confirmationMessage);

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
            const elements = document.querySelectorAll('.product-info, .stock-warning, .form-card');
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

        // Prevenção de valores negativos
        quantidadeInput.addEventListener('keypress', function(e) {
            // Permitir apenas números
            if (!/[0-9]/.test(e.key) && !['Backspace', 'Delete', 'Tab', 'Enter'].includes(e.key)) {
                e.preventDefault();
            }
        });

        // Atualizar máximo do input quando necessário
        quantidadeInput.setAttribute('max', estoqueAtual);

        // Feedback visual para campos obrigatórios
        [quantidadeInput, motivoSelect].forEach(field => {
            field.addEventListener('blur', function() {
                if (this.hasAttribute('required') && !this.value) {
                    this.classList.add('error');
                } else {
                    this.classList.remove('error');
                }
            });

            field.addEventListener('input', function() {
                if (this.value) {
                    this.classList.remove('error');
                }
            });
        });

        // Adicionar informações contextuais baseadas no motivo selecionado
        motivoSelect.addEventListener('change', function() {
            const helpTexts = {
                'venda': 'Produto vendido para cliente',
                'uso_interno': 'Utilizado internamente na empresa',
                'transferencia': 'Transferido para outro local/setor',
                'perda': 'Produto perdido, danificado ou vencido',
                'devolucao': 'Produto devolvido ao fornecedor',
                'amostra': 'Produto utilizado como amostra',
                'producao': 'Utilizado no processo produtivo',
                'outros': 'Outro motivo não listado'
            };

            const helpText = this.parentElement.querySelector('.help-text');
            if (helpText && helpTexts[this.value]) {
                helpText.textContent = helpTexts[this.value];
                helpText.style.color = '#64748b';
            }
        });
    </script>
</body>
</html>