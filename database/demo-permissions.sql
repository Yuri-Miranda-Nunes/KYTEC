-- Run once in phpMyAdmin to restrict the existing shared demo account.
-- Keeps product and stock workflows available while disabling user administration.
UPDATE `usuarios`
SET `perfil` = 'estoquista', `cargo` = 'Estoquista de demonstração'
WHERE `email` = 'demo@kytec.local';

DELETE up
FROM `usuario_permissoes` up
JOIN `usuarios` u ON u.`id` = up.`usuario_id`
JOIN `permissoes` p ON p.`id` = up.`permissao_id`
WHERE u.`email` = 'demo@kytec.local'
  AND p.`nome_permissao` = 'gerenciar_usuarios';
