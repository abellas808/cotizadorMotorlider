<?php
ob_start();

require_once(__DIR__ . '/../../config/config.inc.php');

if (!isset($config['db_tablePrefix'])) {
    $config['db_tablePrefix'] = '';
}

require_once(__DIR__ . '/../../includes/database.php');
require_once(__DIR__ . '/../../includes/funciones.php');

session_start();
require_once(__DIR__ . '/../../includes/chk_login.php');

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Montevideo');

global $db;

function j($a) {
    echo json_encode($a);
    exit;
}

function obtener_cotizacion_actual_para_agenda_manual($db, $telefono, $anio, $marca, $modelo, $auto) {
    $telefono = trim((string)$telefono);
    $anio = trim((string)$anio);
    $marca = trim((string)$marca);
    $modelo = trim((string)$modelo);
    $auto = trim((string)$auto);

    if ($telefono === '' || $anio === '') {
        return 0;
    }

    $likeModelo = '%' . $db->escape($modelo) . '%';
    $likeAuto = '%' . $db->escape($auto) . '%';

    $sql = "
        SELECT id_cotizaciones_generadas
        FROM cotizaciones_generadas
        WHERE telefono = '" . $db->escape($telefono) . "'
          AND anio = '" . $db->escape($anio) . "'
          AND (
                auto LIKE '" . $likeModelo . "'
                OR auto LIKE '" . $likeAuto . "'
              )
        ORDER BY id_cotizaciones_generadas DESC
        LIMIT 1
    ";

    $row = $db->query_first($sql);

    return intval($row['id_cotizaciones_generadas'] ?? 0);
}

$idCotizacion = intval($_POST['id_cotizacion'] ?? 0);

$nombre = trim((string)($_POST['nombre'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$telefono = trim((string)($_POST['telefono'] ?? ''));

$fecha = trim((string)($_POST['fecha_reserva'] ?? $_POST['fecha'] ?? ''));
$hora = trim((string)($_POST['horario_reserva'] ?? $_POST['hora'] ?? ''));
$idSucursal = intval($_POST['suc'] ?? $_POST['sucursal'] ?? 1);

$marca = trim((string)($_POST['marca'] ?? ''));
$modelo = trim((string)($_POST['modelo'] ?? ''));
$anio = trim((string)($_POST['anio'] ?? ''));
$familia = trim((string)($_POST['familia'] ?? $_POST['version'] ?? ''));
$auto = trim((string)($_POST['auto'] ?? ''));

if ($nombre === '') {
    j(['ok' => false, 'mensaje' => 'Ingresá el nombre del cliente.']);
}

if ($telefono === '') {
    j(['ok' => false, 'mensaje' => 'Ingresá el teléfono del cliente.']);
}

if ($fecha === '') {
    j(['ok' => false, 'mensaje' => 'Seleccioná una fecha para la agenda.']);
}

if ($hora === '') {
    j(['ok' => false, 'mensaje' => 'Seleccioná una hora para la agenda.']);
}

if ($auto === '') {
    $auto = trim($marca . ' ' . $modelo . ' ' . $anio . ' ' . $familia);
}

if ($idCotizacion <= 0) {
    $idCotizacion = obtener_cotizacion_actual_para_agenda_manual(
        $db,
        $telefono,
        $anio,
        $marca,
        $modelo,
        $auto
    );
}

$randString = bin2hex(random_bytes(25));

$sql = "
    INSERT INTO agendas
    (
        id_sucursal,
        fecha,
        hora,
        modelo,
        marca,
        anio,
        familia,
        auto,
        nombre,
        ci,
        email,
        telefono,
        rand_string,
        direccion,
        inspeccion_domiciliaria,
        id_cotizacion,
        cancelado,
        finalizada,
        fecha_creacion
    )
    VALUES
    (
        " . intval($idSucursal) . ",
        '" . $db->escape($fecha) . "',
        '" . $db->escape($hora) . "',
        '" . $db->escape($modelo) . "',
        '" . $db->escape($marca) . "',
        '" . $db->escape($anio) . "',
        '" . $db->escape($familia) . "',
        '" . $db->escape($auto) . "',
        '" . $db->escape($nombre) . "',
        '0',
        '" . $db->escape($email) . "',
        '" . $db->escape($telefono) . "',
        '" . $db->escape($randString) . "',
        'N/A',
        0,
        " . intval($idCotizacion) . ",
        0,
        0,
        NOW()
    )
";

$db->query($sql);

$rowAgenda = $db->query_first("
    SELECT id_agenda
    FROM agendas
    WHERE fecha = '" . $db->escape($fecha) . "'
      AND hora = '" . $db->escape($hora) . "'
      AND telefono = '" . $db->escape($telefono) . "'
      AND nombre = '" . $db->escape($nombre) . "'
    ORDER BY id_agenda DESC
    LIMIT 1
");

$idAgenda = intval($rowAgenda['id_agenda'] ?? 0);

if ($idAgenda <= 0) {
    j([
        'ok' => false,
        'mensaje' => 'No se pudo crear la agenda manual.',
        'debug' => 'Insert ejecutado pero no se encontró la agenda creada.'
    ]);
}

j([
    'ok' => true,
    'mensaje' => 'Agenda manual creada correctamente.',
    'id_agenda' => $idAgenda,
    'id_cotizacion' => $idCotizacion
]);