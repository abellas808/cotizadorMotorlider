<?php
require_once(__DIR__ . '/../../config/config.inc.php');

if (!isset($config['db_tablePrefix'])) {
    $config['db_tablePrefix'] = '';
}

require_once(__DIR__ . '/../../includes/database.php');
require_once(__DIR__ . '/../../includes/funciones.php');

session_start();
require_once(__DIR__ . '/../../includes/chk_login.php');

header('Content-Type: application/json; charset=utf-8');

global $db;

function j($a) {
    echo json_encode($a);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$avanzo = intval($_POST['avanzo'] ?? 0) === 1 ? 1 : 0;

if ($id <= 0) {
    j(['ok' => false, 'mensaje' => 'ID inválido.']);
}

$idUsuario = intval($_SESSION[$config['codigo_unico']]['login_usuario_id'] ?? 0);

$row = $db->query_first("
    SELECT id_cotizaciones_generadas, estado_id
    FROM cotizaciones_generadas
    WHERE id_cotizaciones_generadas = " . intval($id) . "
    LIMIT 1
");

if (!$row) {
    j(['ok' => false, 'mensaje' => 'Cotización no encontrada.']);
}

$estadoActual = intval($row['estado_id'] ?? 0);

if (!in_array($estadoActual, [6, 11, 9], true)) {
    j([
        'ok' => false,
        'mensaje' => 'Solo se puede modificar Avanzó desde COMUNICARSE CON CLIENTE o COTIZACIÓN FINAL.'
    ]);
}

if ($avanzo === 1) {

    $db->query("
        UPDATE cotizaciones_generadas
        SET
            avanzo = 1,
            estado_id = 9,
            estado = 'AVANZO',
            fecha_avanzo = NOW(),
            id_usuario_avanzo = " . intval($idUsuario) . ",
            fecha_mod = NOW()
        WHERE id_cotizaciones_generadas = " . intval($id) . "
        LIMIT 1
    ");

} else {

    $db->query("
        UPDATE cotizaciones_generadas
        SET
            avanzo = 0,
            estado_id = 11,
            estado = 'COTIZACIÓN FINAL',
            fecha_avanzo = NULL,
            id_usuario_avanzo = NULL,
            fecha_mod = NOW()
        WHERE id_cotizaciones_generadas = " . intval($id) . "
        LIMIT 1
    ");

}

j([
    'ok' => true,
    'mensaje' => $avanzo === 1 ? 'Cliente marcado como avanzó.' : 'Se quitó la marca de avanzó.',
    'avanzo' => $avanzo
]);