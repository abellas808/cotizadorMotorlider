<?php
ob_start();

require_once(__DIR__ . '/../../config/config.inc.php');

if (!isset($config['db_tablePrefix'])) {
    $config['db_tablePrefix'] = '';
}

require_once(__DIR__ . '/../../includes/database.php');
require_once(__DIR__ . '/../../includes/funciones.php');
require_once(__DIR__ . '/../../includes/class.phpmailer.php');

session_start();
require_once(__DIR__ . '/../../includes/chk_login.php');

header('Content-Type: application/json; charset=utf-8');

global $db;

// por ahora fijo
$id_sucursal = 1;

$fecha    = trim((string)($_POST['fecha'] ?? ''));
$hora     = trim((string)($_POST['hora'] ?? ''));
$nombre   = trim((string)($_POST['nombre'] ?? ''));
$email    = trim((string)($_POST['email'] ?? ''));
$telefono = trim((string)($_POST['telefono'] ?? ''));
$auto     = trim((string)($_POST['auto'] ?? ''));
$marca    = trim((string)($_POST['marca'] ?? ''));
$modelo   = trim((string)($_POST['modelo'] ?? ''));
$anio     = trim((string)($_POST['anio'] ?? ''));
$familia  = trim((string)($_POST['familia'] ?? ''));

if ($fecha === '' || $hora === '' || $nombre === '' || $email === '') {
	echo json_encode([
		'ok' => false,
		'mensaje' => 'Faltan datos obligatorios.'
	]);
	exit;
}

$fechaEsc    = $db->escape($fecha);
$horaEsc     = $db->escape($hora);
$nombreEsc   = $db->escape($nombre);
$emailEsc    = $db->escape($email);
$telefonoEsc = $db->escape($telefono);
$autoEsc     = $db->escape($auto);
$marcaEsc    = $db->escape($marca);
$modeloEsc   = $db->escape($modelo);
$anioEsc     = $db->escape($anio);
$familiaEsc  = $db->escape($familia);

// validar agenda existente activa
$existente = $db->query_first("
	SELECT id_agenda
	FROM agendas
	WHERE id_sucursal = '{$id_sucursal}'
	  AND fecha = '{$fechaEsc}'
	  AND hora = '{$horaEsc}'
	  AND (cancelado = 0 OR cancelado IS NULL)
	LIMIT 1
");

if ($existente) {
	echo json_encode([
		'ok' => false,
		'mensaje' => 'Ya existe una agenda activa en ese horario.'
	]);
	exit;
}

// validar bloqueo manual
$bloqueo = $db->query_first("
	SELECT id_bloqueo
	FROM agenda_bloqueos
	WHERE id_sucursal = '{$id_sucursal}'
	  AND fecha = '{$fechaEsc}'
	  AND activo = 1
	  AND (
		hora IS NULL
		OR TIME_FORMAT(hora, '%H:%i') = '{$horaEsc}'
	  )
	LIMIT 1
");

if ($bloqueo) {
	echo json_encode([
		'ok' => false,
		'mensaje' => 'Ese horario está bloqueado.'
	]);
	exit;
}

$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
$rand = '';
for ($i = 0; $i < 50; $i++) {
	$rand .= $chars[rand(0, strlen($chars) - 1)];
}
$randEsc = $db->escape($rand);

$ci = 0;
$direccion = 'N/A';
$inspeccion_domiciliaria = 0;
$id_cotizacion = 0;
$cancelado = 0;

$direccionEsc = $db->escape($direccion);

$ok = $db->query("
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
		cancelado
	)
	VALUES
	(
		'{$id_sucursal}',
		'{$fechaEsc}',
		'{$horaEsc}',
		'{$modeloEsc}',
		'{$marcaEsc}',
		'{$anioEsc}',
		'{$familiaEsc}',
		'{$autoEsc}',
		'{$nombreEsc}',
		'{$ci}',
		'{$emailEsc}',
		'{$telefonoEsc}',
		'{$randEsc}',
		'{$direccionEsc}',
		'{$inspeccion_domiciliaria}',
		'{$id_cotizacion}',
		'{$cancelado}'
	)
");

if (!$ok) {
	echo json_encode([
		'ok' => false,
		'mensaje' => 'Error al guardar la agenda.'
	]);
	exit;
}

// obtener ID insertado
$nuevaAgenda = $db->query_first("
	SELECT id_agenda
	FROM agendas
	WHERE id_sucursal = '{$id_sucursal}'
	  AND fecha = '{$fechaEsc}'
	  AND hora = '{$horaEsc}'
	  AND email = '{$emailEsc}'
	ORDER BY id_agenda DESC
	LIMIT 1
");

$id_agenda = $nuevaAgenda['id_agenda'] ?? 0;

// datos sucursal para mail
$sucursal = $db->query_first("
	SELECT nombre, direccion, email, telefono
	FROM agenda_sucursal
	WHERE id_sucursal = '{$id_sucursal}'
	LIMIT 1
");

$suc_name      = $sucursal['nombre'] ?? '';
$suc_direccion = $sucursal['direccion'] ?? '';
$suc_email     = $sucursal['email'] ?? '';
$suc_telefono  = $sucursal['telefono'] ?? '';

// mail de confirmación, no rompe flujo
try {
	$mail = new PHPMailer(true);
	$mail->isHTML(true);
	$mail->From = "noresponder@motorliderweb.com.uy";
	$mail->FromName = "MOTORLIDER";
	$mail->AddAddress($email, $nombre);
	$mail->Subject = "Reserva de Agenda MOTORLIDER";
	$mail->Body =
		"Tu agenda fue confirmada.<br><br>" .
		"<strong>Fecha:</strong> " . date('d/m/Y', strtotime($fecha)) . "<br>" .
		"<strong>Hora:</strong> " . $hora . "<br>" .
		(!empty($auto) ? "<strong>Vehículo:</strong> " . $auto . "<br>" : "") .
		(!empty($suc_name) ? "<strong>Sucursal:</strong> " . $suc_name . "<br>" : "") .
		(!empty($suc_direccion) ? "<strong>Dirección:</strong> " . $suc_direccion . "<br>" : "") .
		(!empty($suc_email) ? "<strong>Email sucursal:</strong> " . $suc_email . "<br>" : "") .
		(!empty($suc_telefono) ? "<strong>Teléfono sucursal:</strong> " . $suc_telefono . "<br>" : "");

	$mail->send();
} catch (Exception $e) {
	// no romper flujo si falla el mail
}

echo json_encode([
	'ok' => true,
	'id_agenda' => $id_agenda,
	'mensaje' => 'Agenda creada correctamente.'
]);