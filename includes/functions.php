<?php
// includes/functions.php

/**
 * Verifica se o usuário tem uma permissão específica
 * @param string $permissao Nome da permissão
 * @return bool
 */
function temPermissao($permissao) {
    return in_array($permissao, $_SESSION['permissoes'] ?? []);
}

/**
 * Verifica se o usuário está logado
 * @return bool
 */
function estaLogado() {
    return isset($_SESSION['usuario_id']) && 
           isset($_SESSION['logado']) && 
           $_SESSION['logado'] === true;
}

/**
 * Redireciona para login se não estiver autenticado
 */
function verificarAutenticacao() {
    if (!estaLogado()) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Retorna informações do usuário logado
 * @return array
 */
function getUsuarioLogado() {
    return [
        'id' => $_SESSION['usuario_id'] ?? null,
        'nome' => $_SESSION['usuario_nome'] ?? '',
        'email' => $_SESSION['usuario_email'] ?? '',
        'perfil' => $_SESSION['usuario_perfil'] ?? '',
        'permissoes' => $_SESSION['permissoes'] ?? []
    ];
}

/**
 * Sanitiza string para evitar XSS
 * @param string $string
 * @return string
 */
function sanitizar($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Formata valor monetário
 * @param float $valor
 * @return string
 */
function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

/**
 * Formata número
 * @param int|float $numero
 * @return string
 */
function formatarNumero($numero) {
    return number_format($numero, 0, ',', '.');
}
?>