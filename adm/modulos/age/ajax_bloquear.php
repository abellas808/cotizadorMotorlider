<?php
require_once(__DIR__ . '/../../config/config.inc.php');

if (!isset($config['db_tablePrefix'])) {
	$config['db_tablePrefix'] = '';
}

require_once(__DIR__ . '/../../includes/database.php');
require_once(__DIR__ . '/../../includes/funciones.php');

header('Content-Type: application/json; charset=utf-8');

session_start();

date_default_timezone_set('America/Montevideo');

global $db;

function age_json($arr) {
	echo json_encode($arr);
	exit;
}

function age_normalizar_hora($hora) {
	$hora = trim((string)$hora);

	if ($hora === '') {
		return '';
	}

	if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $hora)) {
		return substr($hora, 0, 5);
	}

	if (preg_match('/^\d{2}:\d{2}$/', $hora)) {
		return $hora;
	}

	return '';
}

function age_fecha_hora_pasada($fecha, $hora = '') {
	$tz = new DateTimeZone('America/Montevideo');
	$now = new DateTime('now', $tz);

	if ($hora === '') {
		$dt = DateTime::createFromFormat('Y-m-d H:i:s', $fecha . ' 00:00:00', $tz);
		if (!$dt) return true;

		$today = new DateTime('today', $tz);
		return $dt < $today;
	}

	$dt = DateTime::createFromFormat('Y-m-d H:i', $fecha . ' ' . $hora, $tz);
	if (!$dt) return true;

	return $dt < $now;
}

$accion = trim((string)($_POST['accion'] ?? 'bloquear')); // bloquear | desbloquear
$fecha  = trim((string)($_POST['fecha'] ?? ''));
$horaIn = trim((string)($_POST['hora'] ?? ''));
$hora   = age_normalizar_hora($horaIn);
$motivo = trim((string)($_POST['motivo'] ?? 'Bloqueo manual'));

// por ahora fijo sucursal 1
$id_sucursal = 1;

if ($fecha === '') {
	age_json(['ok' => false, 'mensaje' => 'Falta fecha']);
}

$date = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$date || $date->format('Y-m-d') !== $fecha) {
	age_json(['ok' => false, 'mensaje' => 'Fecha inválida']);
}

if ($horaIn !== '' && $hora === '') {
	age_json(['ok' => false, 'mensaje' => 'Hora inválida']);
}

$fechaEsc  = $db->escape($fecha);
$horaEsc   = $hora !== '' ? $db->escape($hora . ':00') : null;
$motivoEsc = $db->escape($motivo);

if ($accion === 'desbloquear') {

	if ($horaEsc === null) {
		$ok = $db->query("
			UPDATE agenda_bloqueos
			SET activo = 0
			WHERE id_sucursal = '{$id_sucursal}'
			  AND fecha = '{$fechaEsc}'
			  AND hora IS NULL
			  AND activo = 1
		");
	} else {
		$ok = $db->query("
			UPDATE agenda_bloqueos
			SET activo = 0
			WHERE id_sucursal = '{$id_sucursal}'
			  AND fecha = '{$fechaEsc}'
			  AND hora = '{$horaEsc}'
			  AND activo = 1
		");
	}

	age_json([
		'ok' => (bool)$ok,
		'accion' => 'desbloquear',
		'mensaje' => $ok ? 'Bloqueo removido correctamente.' : 'No se pudo desbloquear.'
	]);
}

// =========================
// BLOQUEAR
// =========================

// no dejar bloquear pasado
if (age_fecha_hora_pasada($fecha, $hora)) {
	age_json([
		'ok' => false,
		'mensaje' => 'No se puede bloquear una fecha/hora pasada.'
	]);
}

// evitar duplicado de bloqueo activo
if ($horaEsc === null) {
	$existe = $db->query_first("
		SELECT id_bloqueo
		FROM agenda_bloqueos
		WHERE id_sucursal = '{$id_sucursal}'
		  AND fecha = '{$fechaEsc}'
		  AND hora IS NULL
		  AND activo = 1
		LIMIT 1
	");
} else {
	$existe = $db->query_first("
		SELECT id_bloqueo
		FROM agenda_bloqueos
		WHERE id_sucursal = '{$id_sucursal}'
		  AND fecha = '{$fechaEsc}'
		  AND hora = '{$horaEsc}'
		  AND activo = 1
		LIMIT 1
	");
}

if ($existe) {
	age_json([
		'ok' => true,
		'accion' => 'bloquear',
		'ya_existia' => true,
		'mensaje' => 'Ese bloqueo ya existía.'
	]);
}

// insertar bloqueo
if ($horaEsc === null) {
	$ok = $db->query("
		INSERT INTO agenda_bloqueos (id_sucursal, fecha, hora, motivo, activo)
		VALUES ('{$id_sucursal}', '{$fechaEsc}', NULL, '{$motivoEsc}', 1)
	");
} else {
	$ok = $db->query("
		INSERT INTO agenda_bloqueos (id_sucursal, fecha, hora, motivo, activo)
		VALUES ('{$id_sucursal}', '{$fechaEsc}', '{$horaEsc}', '{$motivoEsc}', 1)
	");
}

if (!$ok) {
	age_json([
		'ok' => false,
		'mensaje' => 'No se pudo guardar el bloqueo.'
	]);
}

// =========================
// CANCELAR SOLO AGENDAS FUTURAS AFECTADAS
// =========================
$cantidad_afectadas = 0;

if ($horaEsc === null) {
	$qAgendas = $db->query("
		SELECT id_agenda, email, nombre, auto, fecha, hora
		FROM agendas
		WHERE id_sucursal = '{$id_sucursal}'
		  AND fecha = '{$fechaEsc}'
		  AND (cancelado = 0 OR cancelado IS NULL)
		  AND STR_TO_DATE(CONCAT(fecha, ' ', LEFT(hora, 5)), '%Y-%m-%d %H:%i') >= NOW()
	");

	$db->query("
		UPDATE agendas
		SET cancelado = 1,
			motivo_cancelacion = 'Bloqueo manual',
			fecha_cancelacion = NOW()
		WHERE id_sucursal = '{$id_sucursal}'
		  AND fecha = '{$fechaEsc}'
		  AND (cancelado = 0 OR cancelado IS NULL)
		  AND STR_TO_DATE(CONCAT(fecha, ' ', LEFT(hora, 5)), '%Y-%m-%d %H:%i') >= NOW()
	");
} else {
	$qAgendas = $db->query("
		SELECT id_agenda, email, nombre, auto, fecha, hora
		FROM agendas
		WHERE id_sucursal = '{$id_sucursal}'
		  AND fecha = '{$fechaEsc}'
		  AND hora = '" . substr($horaEsc, 0, 5) . "'
		  AND (cancelado = 0 OR cancelado IS NULL)
		  AND STR_TO_DATE(CONCAT(fecha, ' ', LEFT(hora, 5)), '%Y-%m-%d %H:%i') >= NOW()
	");

	$db->query("
		UPDATE agendas
		SET cancelado = 1,
			motivo_cancelacion = 'Bloqueo manual',
			fecha_cancelacion = NOW()
		WHERE id_sucursal = '{$id_sucursal}'
		  AND fecha = '{$fechaEsc}'
		  AND hora = '" . substr($horaEsc, 0, 5) . "'
		  AND (cancelado = 0 OR cancelado IS NULL)
		  AND STR_TO_DATE(CONCAT(fecha, ' ', LEFT(hora, 5)), '%Y-%m-%d %H:%i') >= NOW()
	");
}

if ($qAgendas && isset($qAgendas->num_rows)) {
	$cantidad_afectadas = (int)$qAgendas->num_rows;
}

age_json([
	'ok' => true,
	'accion' => 'bloquear',
	'afectadas' => $cantidad_afectadas,
	'mensaje' => 'Bloqueo guardado correctamente.'
]);
