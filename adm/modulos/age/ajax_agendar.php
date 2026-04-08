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
date_default_timezone_set('America/Montevideo');

global $db;

$id_sucursal = 1;

function age_json($arr) {
	echo json_encode($arr);
	exit;
}

function age_normalizar_hora($hora) {
	$hora = trim((string)$hora);

	if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $hora)) {
		return substr($hora, 0, 5);
	}

	if (preg_match('/^\d{2}:\d{2}$/', $hora)) {
		return $hora;
	}

	return '';
}

function age_fecha_hora_pasada($fecha, $hora) {
	$tz = new DateTimeZone('America/Montevideo');
	$dt = DateTime::createFromFormat('Y-m-d H:i', $fecha . ' ' . $hora, $tz);

	if (!$dt) {
		return true;
	}

	$now = new DateTime('now', $tz);
	return $dt < $now;
}

function age_table_columns($db, $table) {
	$tableEsc = $db->escape($table);
	$cols = [];

	$q = $db->query("SHOW COLUMNS FROM `{$tableEsc}`");
	if ($q) {
		while ($r = $db->fetch_array($q)) {
			if (!empty($r['Field'])) {
				$cols[$r['Field']] = true;
			}
		}
	}

	return $cols;
}

$fecha         = trim((string)($_POST['fecha'] ?? ''));
$hora          = age_normalizar_hora($_POST['hora'] ?? '');
$nombre        = trim((string)($_POST['nombre'] ?? ''));
$email         = trim((string)($_POST['email'] ?? ''));
$telefono      = trim((string)($_POST['telefono'] ?? ''));
$auto          = trim((string)($_POST['auto'] ?? ''));
$marca         = trim((string)($_POST['marca'] ?? ''));
$modelo        = trim((string)($_POST['modelo'] ?? ''));
$anio          = trim((string)($_POST['anio'] ?? ''));
$familia       = trim((string)($_POST['familia'] ?? ''));
$id_cotizacion = intval($_POST['id_cotizacion'] ?? 0);

if ($fecha === '' || $hora === '' || $nombre === '' || $email === '') {
	age_json([
		'ok' => false,
		'mensaje' => 'Faltan datos obligatorios.'
	]);
}

$date = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$date || $date->format('Y-m-d') !== $fecha) {
	age_json([
		'ok' => false,
		'mensaje' => 'Fecha inválida.'
	]);
}

if (!preg_match('/^\d{2}:\d{2}$/', $hora)) {
	age_json([
		'ok' => false,
		'mensaje' => 'Hora inválida.'
	]);
}

if (age_fecha_hora_pasada($fecha, $hora)) {
	age_json([
		'ok' => false,
		'mensaje' => 'No se puede agendar en una fecha/hora pasada.'
	]);
}

if ($id_cotizacion <= 0) {
	age_json([
		'ok' => false,
		'mensaje' => 'No llegó un id_cotizacion válido.'
	]);
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
	age_json([
		'ok' => false,
		'mensaje' => 'Ya existe una agenda activa en ese horario.'
	]);
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
	age_json([
		'ok' => false,
		'mensaje' => 'Ese horario está bloqueado.'
	]);
}

$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
$rand = '';
for ($i = 0; $i < 50; $i++) {
	$rand .= $chars[rand(0, strlen($chars) - 1)];
}
$randEsc = $db->escape($rand);

// defaults originales
$ci = 0;
$direccion = 'N/A';
$inspeccion_domiciliaria = 0;
$cancelado = 0;
$direccionEsc = $db->escape($direccion);

// INSERT SOLO EN AGENDAS
$fields = [
	'id_sucursal' => "'{$id_sucursal}'",
	'fecha' => "'{$fechaEsc}'",
	'hora' => "'{$horaEsc}'",
	'modelo' => "'{$modeloEsc}'",
	'marca' => "'{$marcaEsc}'",
	'anio' => "'{$anioEsc}'",
	'familia' => "'{$familiaEsc}'",
	'auto' => "'{$autoEsc}'",
	'nombre' => "'{$nombreEsc}'",
	'ci' => "'{$ci}'",
	'email' => "'{$emailEsc}'",
	'telefono' => "'{$telefonoEsc}'",
	'rand_string' => "'{$randEsc}'",
	'direccion' => "'{$direccionEsc}'",
	'inspeccion_domiciliaria' => "'{$inspeccion_domiciliaria}'",
	'id_cotizacion' => "'{$id_cotizacion}'",
	'cancelado' => "'{$cancelado}'",
];

$sql = "INSERT INTO agendas (" . implode(",\n\t", array_keys($fields)) . ") VALUES (\n\t" . implode(",\n\t", array_values($fields)) . "\n)";
$ok = $db->query($sql);

if (!$ok) {
	age_json([
		'ok' => false,
		'mensaje' => 'Error al guardar la agenda.'
	]);
}

// obtener agenda creada
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

$id_agenda = intval($nuevaAgenda['id_agenda'] ?? 0);

// GUARDAR USUARIO EN cotizaciones_generadas
$idUsuarioCotizo = intval($_SESSION[$config['codigo_unico']]['login_usuario_id'] ?? 0);

$colsCot = age_table_columns($db, 'cotizaciones_generadas');
$setsCot = [];

if (isset($colsCot['id_usuario_cotizo']) && $idUsuarioCotizo > 0) {
	$setsCot[] = "id_usuario_cotizo = " . intval($idUsuarioCotizo);
}

if (!empty($setsCot)) {
	$db->query("
		UPDATE cotizaciones_generadas
		SET " . implode(', ', $setsCot) . "
		WHERE id_cotizaciones_generadas = " . intval($id_cotizacion) . "
		LIMIT 1
	");
}

// mail
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

age_json([
	'ok' => true,
	'id_agenda' => $id_agenda,
	'id_cotizacion' => $id_cotizacion,
	'id_usuario_cotizo' => $idUsuarioCotizo,
	'mensaje' => 'Agenda creada correctamente.'
]);