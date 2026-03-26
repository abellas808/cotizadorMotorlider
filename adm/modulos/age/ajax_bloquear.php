<?php
require_once(__DIR__ . '/../../config/config.inc.php');
require_once(__DIR__ . '/../../includes/database.php');
require_once(__DIR__ . '/../../includes/funciones.php');

header('Content-Type: application/json; charset=utf-8');

session_start();

global $db;

$accion = trim((string)($_POST['accion'] ?? 'bloquear')); // bloquear | desbloquear
$fecha  = trim((string)($_POST['fecha'] ?? ''));
$hora   = trim((string)($_POST['hora'] ?? ''));
$motivo = trim((string)($_POST['motivo'] ?? 'Bloqueo manual'));

// por ahora fijo sucursal 1
$id_sucursal = 1;

if ($fecha === '') {
	echo json_encode(['ok' => false, 'mensaje' => 'Falta fecha']);
	exit;
}

$fechaEsc = $db->escape($fecha);
$horaEsc  = $hora !== '' ? $db->escape($hora . ':00') : null;
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

	echo json_encode([
		'ok' => (bool)$ok,
		'accion' => 'desbloquear',
		'mensaje' => $ok ? 'Bloqueo removido correctamente.' : 'No se pudo desbloquear.'
	]);
	exit;
}

// =========================
// BLOQUEAR
// =========================

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
	echo json_encode([
		'ok' => true,
		'accion' => 'bloquear',
		'ya_existia' => true,
		'mensaje' => 'Ese bloqueo ya existía.'
	]);
	exit;
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
	echo json_encode([
		'ok' => false,
		'mensaje' => 'No se pudo guardar el bloqueo.'
	]);
	exit;
}

// =========================
// CANCELAR AGENDAS AFECTADAS
// =========================

// asumo que agendas tiene columna cancelado
$cantidad_afectadas = 0;

if ($horaEsc === null) {
	$qAgendas = $db->query("
		SELECT id_agenda, email, nombre, auto, fecha, hora
		FROM agendas
		WHERE id_sucursal = '{$id_sucursal}'
		  AND fecha = '{$fechaEsc}'
		  AND (cancelado = 0 OR cancelado IS NULL)
	");

	$db->query("
		UPDATE agendas
		SET cancelado = 1,
        motivo_cancelacion = 'Bloqueo manual',
        fecha_cancelacion = NOW()
		WHERE id_sucursal = '{$id_sucursal}'
		  AND fecha = '{$fechaEsc}'
		  AND (cancelado = 0 OR cancelado IS NULL)
	");
} else {
	$qAgendas = $db->query("
		SELECT id_agenda, email, nombre, auto, fecha, hora
		FROM agendas
		WHERE id_sucursal = '{$id_sucursal}'
		  AND fecha = '{$fechaEsc}'
		  AND hora = '" . substr($horaEsc, 0, 5) . "'
		  AND (cancelado = 0 OR cancelado IS NULL)
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
	");
}

if ($qAgendas && isset($qAgendas->num_rows)) {
	$cantidad_afectadas = (int)$qAgendas->num_rows;
}

// por ahora solo devolvemos cuántas agendas afectó
// el próximo paso es mandar mails a cada agenda afectada

echo json_encode([
	'ok' => true,
	'accion' => 'bloquear',
	'afectadas' => $cantidad_afectadas,
	'mensaje' => 'Bloqueo guardado correctamente.'
]);