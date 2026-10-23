-- Demonstration database: synthetic records only.
-- Import this into a new, empty MySQL database.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE `categorias` (
  `id_categoria` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) NOT NULL,
  `descricao` text DEFAULT NULL,
  PRIMARY KEY (`id_categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `matricula` bigint unsigned NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `departamento` varchar(50) DEFAULT NULL,
  `cargo` varchar(100) DEFAULT NULL,
  `data_admissao` date DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `senha` varchar(255) NOT NULL,
  `perfil` enum('admin','estoquista','visualizador') NOT NULL DEFAULT 'visualizador',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_login` datetime DEFAULT NULL,
  `total_logins` int DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuarios_email` (`email`),
  UNIQUE KEY `usuarios_matricula` (`matricula`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `fornecedores` (
  `id_fornecedor` int NOT NULL AUTO_INCREMENT,
  `cnpj` varchar(18) DEFAULT NULL,
  `email_representante` varchar(100) DEFAULT NULL,
  `endereco` text DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `logradouro` varchar(200) DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `complemento` varchar(100) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` varchar(2) DEFAULT NULL,
  `nome_empresa` varchar(150) DEFAULT NULL,
  `atividade` varchar(100) DEFAULT NULL,
  `telefone_representante` varchar(20) DEFAULT NULL,
  `nome_representante` varchar(100) DEFAULT NULL,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_fornecedor`),
  UNIQUE KEY `fornecedores_cnpj` (`cnpj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permissoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome_permissao` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissoes_nome` (`nome_permissao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuario_permissoes` (
  `usuario_id` int NOT NULL,
  `permissao_id` int NOT NULL,
  PRIMARY KEY (`usuario_id`,`permissao_id`),
  CONSTRAINT `demo_usuario_permissoes_usuario_fk` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `demo_usuario_permissoes_permissao_fk` FOREIGN KEY (`permissao_id`) REFERENCES `permissoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `configuracoes_usuario` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `notificacoes_seguranca` tinyint(1) DEFAULT 1,
  `tema` varchar(20) DEFAULT 'light',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `configuracoes_usuario_usuario_idx` (`usuario_id`),
  CONSTRAINT `demo_configuracoes_usuario_fk` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `produtos` (
  `id_produto` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `tipo` enum('acabado','matéria-prima','outro') DEFAULT 'acabado',
  `unidade` varchar(10) DEFAULT 'un',
  `preco_unitario` decimal(10,2) DEFAULT 0.00,
  `preco_venda` decimal(10,2) DEFAULT 0.00,
  `estoque_minimo` int DEFAULT 0,
  `estoque_atual` int DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` datetime DEFAULT NULL,
  `id_categoria` int DEFAULT NULL,
  `usuario_criacao` int DEFAULT NULL,
  `usuario_atualizacao` int DEFAULT NULL,
  PRIMARY KEY (`id_produto`),
  UNIQUE KEY `produtos_codigo` (`codigo`),
  KEY `produtos_categoria_idx` (`id_categoria`),
  CONSTRAINT `demo_produtos_categoria_fk` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `movimentacoes_estoque` (
  `id` int NOT NULL AUTO_INCREMENT,
  `produto_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `tipo_movimentacao` enum('entrada','saida','ajuste','transferencia') NOT NULL,
  `quantidade` int NOT NULL,
  `quantidade_anterior` int NOT NULL,
  `quantidade_atual` int NOT NULL,
  `motivo` varchar(100) DEFAULT NULL,
  `destino` varchar(100) DEFAULT NULL,
  `fornecedor_id` int DEFAULT NULL,
  `nota_fiscal` varchar(50) DEFAULT NULL,
  `valor_unitario` decimal(10,2) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `movimentacoes_produto_idx` (`produto_id`),
  KEY `movimentacoes_usuario_idx` (`usuario_id`),
  KEY `movimentacoes_fornecedor_idx` (`fornecedor_id`),
  KEY `movimentacoes_criado_em_idx` (`criado_em`),
  CONSTRAINT `demo_movimentacoes_produto_fk` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id_produto`) ON DELETE CASCADE,
  CONSTRAINT `demo_movimentacoes_usuario_fk` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `demo_movimentacoes_fornecedor_fk` FOREIGN KEY (`fornecedor_id`) REFERENCES `fornecedores` (`id_fornecedor`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `entradas_estoque` (
  `id_entrada` int NOT NULL AUTO_INCREMENT,
  `id_produto` int NOT NULL,
  `id_fornecedor` int DEFAULT NULL,
  `quantidade` int NOT NULL,
  `valor_unitario` decimal(10,2) DEFAULT 0.00,
  `data_entrada` datetime DEFAULT CURRENT_TIMESTAMP,
  `nota_fiscal` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_entrada`),
  KEY `entradas_produto_idx` (`id_produto`),
  KEY `entradas_fornecedor_idx` (`id_fornecedor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `saidas_estoque` (
  `id_saida` int NOT NULL AUTO_INCREMENT,
  `id_produto` int NOT NULL,
  `quantidade` int NOT NULL,
  `motivo` enum('venda','uso interno','perda') DEFAULT 'venda',
  `destino` varchar(100) DEFAULT NULL,
  `data_saida` datetime DEFAULT CURRENT_TIMESTAMP,
  `observacao` text DEFAULT NULL,
  PRIMARY KEY (`id_saida`),
  KEY `saidas_produto_idx` (`id_produto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `acao` enum('CREATE','UPDATE','DELETE','LOGIN','LOGOUT','ENTRADA_ESTOQUE','SAIDA_ESTOQUE','VIEW') NOT NULL,
  `tabela` varchar(50) DEFAULT NULL,
  `registro_id` int DEFAULT NULL,
  `dados_anteriores` text DEFAULT NULL,
  `dados_novos` text DEFAULT NULL,
  `detalhes` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  `descricao` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `logs_usuario_idx` (`usuario_id`),
  KEY `logs_criado_em_idx` (`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sessoes_ativas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_activity` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sessoes_usuario_idx` (`usuario_id`),
  CONSTRAINT `demo_sessoes_usuario_fk` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO `categorias` (`id_categoria`, `nome`, `descricao`) VALUES
(1, 'Materiais de exemplo', 'Categoria fictícia para demonstração');

INSERT INTO `permissoes` (`id`, `nome_permissao`) VALUES
(1, 'listar_produtos'),
(2, 'cadastrar_produtos'),
(3, 'editar_produtos'),
(4, 'excluir_produtos'),
(5, 'gerenciar_usuarios');

INSERT INTO `usuarios` (`id`, `matricula`, `nome`, `email`, `departamento`, `cargo`, `senha`, `perfil`, `ativo`) VALUES
(1, 9001, 'Conta de demonstração', 'demo@kytec.local', 'Demonstração', 'Administrador de demonstração', '$2y$12$8uuRUfF59XZGQRF6Kwif..67LtTdtUaqI3K/SoUXroviS5nZms4OG', 'admin', 1);

INSERT INTO `usuario_permissoes` (`usuario_id`, `permissao_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5);

INSERT INTO `fornecedores` (`id_fornecedor`, `nome_empresa`, `atividade`, `nome_representante`, `email_representante`, `cidade`, `estado`) VALUES
(1, 'Fornecedor Fictício de Madeira', 'Materiais para marcenaria', 'Pessoa Exemplo', 'contato@example.invalid', 'Cidade Exemplo', 'SP'),
(2, 'Fornecedor Fictício de Ferragens', 'Ferragens e acessórios', 'Representante Exemplo', 'vendas@example.invalid', 'Cidade Exemplo', 'SP');

INSERT INTO `produtos` (`id_produto`, `codigo`, `nome`, `descricao`, `tipo`, `unidade`, `preco_unitario`, `preco_venda`, `estoque_minimo`, `estoque_atual`, `ativo`, `id_categoria`, `usuario_criacao`) VALUES
(1, 'DEMO-MAT-001', 'Chapa de madeira de demonstração', 'Registro sintético para experimentar o controle de estoque.', 'matéria-prima', 'un', 45.00, 0.00, 10, 24, 1, 1, 1),
(2, 'DEMO-PRD-001', 'Moldura de demonstração', 'Produto fictício para testar entradas e saídas.', 'acabado', 'un', 32.50, 59.90, 5, 12, 1, 1, 1),
(3, 'DEMO-FER-001', 'Parafuso de demonstração', 'Item fictício para testar alertas de estoque baixo.', 'outro', 'un', 0.35, 0.00, 20, 8, 1, 1, 1);

INSERT INTO `movimentacoes_estoque` (`produto_id`, `usuario_id`, `tipo_movimentacao`, `quantidade`, `quantidade_anterior`, `quantidade_atual`, `motivo`, `fornecedor_id`, `valor_unitario`, `observacoes`, `criado_em`) VALUES
(1, 1, 'entrada', 24, 0, 24, 'Carga inicial fictícia', 1, 45.00, 'Movimentação sintética para demonstração.', CURRENT_TIMESTAMP - INTERVAL 2 DAY),
(2, 1, 'entrada', 12, 0, 12, 'Carga inicial fictícia', 2, 32.50, 'Movimentação sintética para demonstração.', CURRENT_TIMESTAMP - INTERVAL 1 DAY),
(3, 1, 'entrada', 8, 0, 8, 'Carga inicial fictícia', 2, 0.35, 'Movimentação sintética para demonstração.', CURRENT_TIMESTAMP);
