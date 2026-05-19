<?php

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
ini_set('display_errors', 0);

ob_start();

require_once(__DIR__ . '/../../config/config.inc.php');

if (!isset($config['db_tablePrefix'])) {
    $config['db_tablePrefix'] = '';
}

require_once(__DIR__ . '/../../includes/database.php');
require_once(__DIR__ . '/../../includes/funciones.php');

session_start();

header('Content-Type: application/json; charset=utf-8');

global $db;

function j($a) {
    if (ob_get_length()) {
        ob_clean();
    }

    echo json_encode($a);
    exit;
}

$idUsuario = intval($_SESSION[$config['codigo_unico']]['login_usuario_id'] ?? 0);

if ($idUsuario <= 0) {
    http_response_code(401);
    j([
        'ok' => false,
        'session_expired' => true,
        'mensaje' => 'La sesión expiró. Volvé a iniciar sesión.'
    ]);
}

$idCotizacion = intval($_POST['id'] ?? 0);

if ($idCotizacion <= 0) {
    j([
        'ok' => false,
        'mensaje' => 'ID de cotización inválido.'
    ]);
}

$usuario = $db->query_first("
    SELECT nombre, email
    FROM admin_usuarios
    WHERE id = " . intval($idUsuario) . "
    LIMIT 1
");

$nombreUsuario = (string)($usuario['nombre'] ?? 'Sistema');

$cot = $db->query_first("
    SELECT
        id_cotizaciones_generadas,
        telefono,
        nombre,
        email,
        marca,
        familia AS modelo,
        anio,
        kilometros,
        tasacion_final,
        estado_id
    FROM cotizaciones_generadas
    WHERE id_cotizaciones_generadas = " . intval($idCotizacion) . "
    LIMIT 1
");

if (!$cot) {
    j([
        'ok' => false,
        'mensaje' => 'No se encontró la cotización.'
    ]);
}

$telefono = trim((string)$cot['telefono']);
$tasacionFinal = floatval($cot['tasacion_final'] ?? 0);

if ($tasacionFinal > 0) {
    j([
        'ok' => false,
        'mensaje' => 'No se puede marcar como NO ASISTIÓ porque ya existe tasación final.'
    ]);
}

$agenda = $db->query_first("
    SELECT
        id_agenda,
        fecha,
        hora,
        detalle_estado
    FROM agendas
    WHERE telefono = '" . $db->escape($telefono) . "'
      AND id_cotizacion = " . intval($idCotizacion) . "
      AND cancelado = 0
      AND detalle_estado = 'Finalizada automáticamente por fecha/hora'
    ORDER BY fecha DESC, hora DESC, id_agenda DESC
    LIMIT 1
");

if (!$agenda) {
    j([
        'ok' => false,
        'mensaje' => 'No se encontró una agenda finalizada automáticamente por fecha/hora para esta cotización.'
    ]);
}

$okEstado = $db->query("
    UPDATE cotizaciones_generadas
    SET
        estado_id = 12,
        estado = 'NO ASISTIÓ',
        fecha_mod = NOW()
    WHERE id_cotizaciones_generadas = " . intval($idCotizacion) . "
    LIMIT 1
");

if (!$okEstado) {
    j([
        'ok' => false,
        'mensaje' => 'No se pudo actualizar el estado de la cotización.'
    ]);
}

$sqlCarrito = "
    INSERT INTO carrito_abandonado
    (
        id_cotizacion,
        id_conversacion,
        telefono,
        nombre,
        email,
        marca,
        modelo,
        anio,
        kilometros,
        tasacion_final,
        mensaje_cliente,
        motivo_abandono,
        origen_abandono,
        fecha_respuesta,
        usuario,
        estado,
        observaciones,
        fecha_ultima_gestion,
        usuario_ultima_gestion,
        fecha_alta
    )
    VALUES
    (
        " . intval($idCotizacion) . ",
        0,
        '" . $db->escape($telefono) . "',
        '" . $db->escape((string)$cot['nombre']) . "',
        '" . $db->escape((string)$cot['email']) . "',
        '" . $db->escape((string)$cot['marca']) . "',
        '" . $db->escape((string)$cot['modelo']) . "',
        " . intval($cot['anio']) . ",
        " . intval($cot['kilometros']) . ",
        " . floatval($cot['tasacion_final']) . ",
        'Cliente no asistió a la agenda',
        'NO_ASISTIO_AGENDA',
        'AGENDA',
        NOW(),
        '" . $db->escape($nombreUsuario) . "',
        'PENDIENTE',
        '',
        NULL,
        '',
        NOW()
    )
";

$okCarrito = $db->query($sqlCarrito);

if (!$okCarrito) {
    j([
        'ok' => false,
        'mensaje' => 'Se actualizó la cotización, pero no se pudo registrar el carrito abandonado.'
    ]);
}

j([
    'ok' => true,
    'mensaje' => 'Cotización marcada como NO ASISTIÓ y enviada a carritos abandonados.'
]);