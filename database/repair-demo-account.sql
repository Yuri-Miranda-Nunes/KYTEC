-- Restore the public demo login with full app permissions.
-- Run this in phpMyAdmin after selecting the demo database.

START TRANSACTION;

INSERT INTO `usuarios` (
  `matricula`,
  `nome`,
  `email`,
  `departamento`,
  `cargo`,
  `senha`,
  `perfil`,
  `ativo`
)
SELECT
  (SELECT COALESCE(MAX(`matricula`), 9000) + 1 FROM `usuarios`),
  'Conta de demonstração',
  'demo@kytec.local',
  'Demonstração',
  'Administrador de demonstração',
  '$2y$12$8uuRUfF59XZGQRF6Kwif..67LtTdtUaqI3K/SoUXroviS5nZms4OG',
  'admin',
  1
WHERE NOT EXISTS (
  SELECT 1 FROM `usuarios` WHERE `email` = 'demo@kytec.local'
);

UPDATE `usuarios`
SET
  `nome` = 'Conta de demonstração',
  `departamento` = 'Demonstração',
  `cargo` = 'Administrador de demonstração',
  `senha` = '$2y$12$8uuRUfF59XZGQRF6Kwif..67LtTdtUaqI3K/SoUXroviS5nZms4OG',
  `perfil` = 'admin',
  `ativo` = 1
WHERE `email` = 'demo@kytec.local';

INSERT INTO `permissoes` (`nome_permissao`) VALUES
  ('listar_produtos'),
  ('cadastrar_produtos'),
  ('editar_produtos'),
  ('excluir_produtos'),
  ('gerenciar_usuarios')
ON DUPLICATE KEY UPDATE `nome_permissao` = VALUES(`nome_permissao`);

INSERT IGNORE INTO `usuario_permissoes` (`usuario_id`, `permissao_id`)
SELECT u.`id`, p.`id`
FROM `usuarios` u
JOIN `permissoes` p
  ON p.`nome_permissao` IN (
    'listar_produtos',
    'cadastrar_produtos',
    'editar_produtos',
    'excluir_produtos',
    'gerenciar_usuarios'
  )
WHERE u.`email` = 'demo@kytec.local';

DELETE up
FROM `usuario_permissoes` up
JOIN `usuarios` u ON u.`id` = up.`usuario_id`
JOIN `permissoes` p ON p.`id` = up.`permissao_id`
WHERE u.`email` = 'demo@kytec.local'
  AND p.`nome_permissao` NOT IN (
    'listar_produtos',
    'cadastrar_produtos',
    'editar_produtos',
    'excluir_produtos',
    'gerenciar_usuarios'
  );

COMMIT;
