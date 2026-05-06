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
    SELECT id_cotizaciones_generadas
    FROM cotizaciones_generadas
    WHERE id_cotizaciones_generadas = " . intval($id) . "
    LIMIT 1
");

if (!$row) {
    j(['ok' => false, 'mensaje' => 'Cotización no encontrada.']);
}

$db->query("
    UPDATE cotizaciones_generadas
    SET
        avanzo = " . intval($avanzo) . ",
        fecha_avanzo = " . ($avanzo === 1 ? "NOW()" : "NULL") . ",
        id_usuario_avanzo = " . ($avanzo === 1 ? intval($idUsuario) : "NULL") . ",
        fecha_mod = NOW()
    WHERE id_cotizaciones_generadas = " . intval($id) . "
    LIMIT 1
");

j([
    'ok' => true,
    'mensaje' => $avanzo === 1 ? 'Cliente marcado como avanzó.' : 'Se quitó la marca de avanzó.',
    'avanzo' => $avanzo
]);