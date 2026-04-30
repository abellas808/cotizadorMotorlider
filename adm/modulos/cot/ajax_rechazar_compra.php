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

// =========================
// CONFIG TWILIO
// =========================
// Usar los mismos valores que tenés en ajax_enviar_cotizacion.php
const TWILIO_ACCOUNT_SID = 'AC4a648c5c55de9d9b1f1f6601b14d4c4d';
const TWILIO_AUTH_TOKEN  = '58f767d26211d9d0c20ea687df00b4c3';
const TWILIO_WHATSAPP_FROM = 'whatsapp:+59898057857';

function j($a) {
	echo json_encode($a);
	exit;
}

function normalizar_telefono_whatsapp($telefono)
{
	$telefono = trim((string)$telefono);

	if ($telefono === '') {
		return '';
	}

	if (strpos($telefono, 'whatsapp:+') === 0) {
		return $telefono;
	}

	$soloNumeros = preg_replace('/[^0-9]/', '', $telefono);

	if ($soloNumeros === '') {
		return '';
	}

	if (strlen($soloNumeros) == 9 && substr($soloNumeros, 0, 1) == '0') {
		return 'whatsapp:+598' . substr($soloNumeros, 1);
	}

	if (strlen($soloNumeros) >= 11 && substr($soloNumeros, 0, 3) == '598') {
		return 'whatsapp:+' . $soloNumeros;
	}

	return 'whatsapp:+' . $soloNumeros;
}

function obtener_id_conversacion_por_telefono($telefono)
{
	global $db;

	$telefono = trim((string)$telefono);

	if ($telefono === '') {
		return 1;
	}

	$row = $db->query_first("
		SELECT id_conversacion
		FROM whatsapp_conversacion_mensajes
		WHERE telefono = '" . $db->escape($telefono) . "'
		ORDER BY id DESC
		LIMIT 1
	");

	$idConversacion = intval($row['id_conversacion'] ?? 0);

	return $idConversacion > 0 ? $idConversacion : 1;
}

function enviar_whatsapp_twilio($to, $body)
{
	$url = 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_ACCOUNT_SID . '/Messages.json';

	$postFields = http_build_query([
		'From' => TWILIO_WHATSAPP_FROM,
		'To'   => $to,
		'Body' => $body
	]);

	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => $postFields,
		CURLOPT_USERPWD => TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN,
		CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
		CURLOPT_TIMEOUT => 45,
		CURLOPT_CONNECTTIMEOUT => 15,
	]);

	$response = curl_exec($ch);
	$error = curl_error($ch);
	$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	if ($error !== '') {
		return [
			'ok' => false,
			'mensaje' => 'Error cURL Twilio: ' . $error,
			'http_code' => $httpCode,
			'raw' => $response
		];
	}

	$decoded = json_decode((string)$response, true);

	if ($httpCode < 200 || $httpCode >= 300) {
		return [
			'ok' => false,
			'mensaje' => $decoded['message'] ?? ('Twilio devolvió HTTP ' . $httpCode),
			'http_code' => $httpCode,
			'raw' => $response
		];
	}

	return [
		'ok' => true,
		'sid' => $decoded['sid'] ?? '',
		'status' => $decoded['status'] ?? '',
		'raw' => $response
	];
}

function registrar_mensaje_whatsapp_backend(
	$idConversacion,
	$telefono,
	$mensaje,
	$sidMensaje = '',
	$origen = 'backend',
	$extraMeta = [],
	$idUsuario = null,
	$nombreUsuario = ''
) {
	global $db;

	$idConversacion = intval($idConversacion);
	$telefono = trim((string)$telefono);
	$mensaje = trim((string)$mensaje);
	$sidMensaje = trim((string)$sidMensaje);

	if ($idConversacion <= 0 || $telefono === '' || $mensaje === '') {
		return false;
	}

	$metaArray = array_merge([
		'origen' => $origen
	], $extraMeta);

	$meta = json_encode($metaArray, JSON_UNESCAPED_UNICODE);

	$db->query("
		INSERT INTO whatsapp_conversacion_mensajes
		(id_conversacion, telefono, direccion, emisor, id_usuario, nombre_usuario, mensaje, meta_json, sid_mensaje, fecha)
		VALUES
		(
			'" . intval($idConversacion) . "',
			'" . $db->escape($telefono) . "',
			'SALIENTE',
			'BOT',
			" . ($idUsuario !== null ? intval($idUsuario) : "NULL") . ",
			'" . $db->escape($nombreUsuario) . "',
			'" . $db->escape($mensaje) . "',
			'" . $db->escape($meta) . "',
			" . ($sidMensaje !== '' ? "'" . $db->escape($sidMensaje) . "'" : "NULL") . ",
			NOW()
		)
	");

	return true;
}

// =========================
// INPUT
// =========================
$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
	j(['ok' => false, 'mensaje' => 'ID inválido.']);
}

$idUsuario = intval($_SESSION[$config['codigo_unico']]['login_usuario_id'] ?? 0);

$usuario = null;
if ($idUsuario > 0) {
	$usuario = $db->query_first("
		SELECT id, nombre, email
		FROM admin_usuarios
		WHERE id = " . intval($idUsuario) . "
		LIMIT 1
	");
}

// =========================
// COTIZACION
// =========================
$elemento = $db->query_first("
	SELECT *
	FROM cotizaciones_generadas
	WHERE id_cotizaciones_generadas = '" . intval($id) . "'
	LIMIT 1
");

if (!$elemento) {
	j(['ok' => false, 'mensaje' => 'Cotización no encontrada.']);
}

$estadoId = isset($elemento['estado_id']) ? intval($elemento['estado_id']) : 0;

if (!in_array($estadoId, [1, 2])) {
	j([
		'ok' => false,
		'mensaje' => 'Solo se puede rechazar una cotización en estado NO COTIZÓ o PENDIENTE.'
	]);
}

$telefono = normalizar_telefono_whatsapp($elemento['telefono'] ?? '');

if ($telefono === '') {
	j(['ok' => false, 'mensaje' => 'La cotización no tiene teléfono válido.']);
}

// =========================
// ARMAR MENSAJE RECHAZO
// =========================
$nombreCliente = trim((string)($elemento['nombre'] ?? ''));

if ($nombreCliente === '') {
	$nombreCliente = 'Estimado/a cliente';
}

$vehiculo = trim((string)($elemento['auto'] ?? ''));

$mensaje = "Hola " . $nombreCliente . ", muchas gracias por enviarnos los datos de tu vehículo";

if ($vehiculo !== '') {
	$mensaje .= " " . $vehiculo;
}

$mensaje .= ".\n\n";
$mensaje .= "En esta oportunidad no vamos a avanzar con la compra del auto.\n\n";
$mensaje .= "De todas formas agradecemos mucho que hayas considerado a Motorlider.\n\n";
$mensaje .= "Saludos,\nMotorlider";

// =========================
// ENVIAR WHATSAPP
// =========================
$envio = enviar_whatsapp_twilio($telefono, $mensaje);

if (!$envio['ok']) {
	j([
		'ok' => false,
		'mensaje' => 'No se pudo enviar el WhatsApp: ' . ($envio['mensaje'] ?? 'Error desconocido.')
	]);
}

// =========================
// GUARDAR CHAT
// =========================
$idConversacion = obtener_id_conversacion_por_telefono($telefono);

registrar_mensaje_whatsapp_backend(
	$idConversacion,
	$telefono,
	$mensaje,
	(string)($envio['sid'] ?? ''),
	'backend_rechazo_compra',
	[
		'id_cotizacion' => intval($id),
		'id_usuario' => intval($idUsuario),
		'accion' => 'RECHAZAR_COMPRA'
	],
	$idUsuario,
	(string)($usuario['nombre'] ?? '')
);

// =========================
// GUARDAR HISTORIAL
// =========================
$db->query("
	INSERT INTO cotizaciones_usuarios_historial
	(
		id_cotizacion,
		id_usuario,
		nombre_usuario,
		accion,
		mensaje,
		monto_desde,
		monto_hasta,
		monto_final,
		sid_mensaje,
		fecha
	)
	VALUES
	(
		" . intval($id) . ",
		" . intval($idUsuario) . ",
		'" . $db->escape((string)($usuario['nombre'] ?? '')) . "',
		'RECHAZAR_COMPRA',
		'" . $db->escape($mensaje) . "',
		NULL,
		NULL,
		NULL,
		'" . $db->escape((string)($envio['sid'] ?? '')) . "',
		NOW()
	)
");

// =========================
// UPDATE COTIZACION
// =========================
$mensajeEsc = $db->escape($mensaje);

$db->query("
	UPDATE cotizaciones_generadas
	SET
		msg = '{$mensajeEsc}',
		detalle_estado = 'Compra rechazada por Motorlider',
		estado = 'RECHAZADO',
		estado_id = 5,
		id_usuario_cotizo = " . intval($idUsuario) . ",
		fecha_mod = NOW()
	WHERE id_cotizaciones_generadas = '" . intval($id) . "'
	LIMIT 1
");

j([
	'ok' => true,
	'mensaje' => 'Mensaje de rechazo enviado correctamente por WhatsApp.',
	'whatsapp_sid' => $envio['sid'] ?? '',
	'id_conversacion' => $idConversacion
]);