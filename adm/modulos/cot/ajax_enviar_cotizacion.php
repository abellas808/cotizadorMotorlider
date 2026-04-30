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

	// Uruguay: 098995624 => whatsapp:+59898995624
	if (strlen($soloNumeros) == 9 && substr($soloNumeros, 0, 1) == '0') {
		return 'whatsapp:+598' . substr($soloNumeros, 1);
	}

	// Ya viene con 598...
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

function enviar_whatsapp_twilio($to, $body) {
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
	)	{
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
$pretasacion_desde = trim((string)($_POST['pretasacion_desde'] ?? ''));
$pretasacion_hasta = trim((string)($_POST['pretasacion_hasta'] ?? ''));

if ($id <= 0) {
	j(['ok' => false, 'mensaje' => 'ID inválido.']);
}

if ($pretasacion_desde === '' || $pretasacion_hasta === '') {
	j(['ok' => false, 'mensaje' => 'Completá pre tasación desde y hasta.']);
}

if (floatval($pretasacion_hasta) < floatval($pretasacion_desde)) {
	j(['ok' => false, 'mensaje' => 'La pre tasación hasta no puede ser menor que la desde.']);
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

$telefono = normalizar_telefono_whatsapp($elemento['telefono'] ?? '');

if ($telefono === '') {
	j(['ok' => false, 'mensaje' => 'La cotización no tiene teléfono válido.']);
}

// =========================
// TIPO DE PLANTILLA
// =========================
$tipoVenta = trim((string)($elemento['tipo_venta'] ?? ''));

if ($tipoVenta === 'venta_contado') {
	$clavePlantilla = 'respuesta_humana_venta_contado';
} elseif ($tipoVenta === 'entrega_forma_pago') {
	$clavePlantilla = 'respuesta_humana_forma_pago';
} else {
	$clavePlantilla = 'respuesta_humana_forma_pago';
}

$plantillasWhatsapp = [
	'respuesta_humana_venta_contado' =>
		"{nombre_cliente}, estaríamos comprando su vehículo en un valor estimado entre *USD {pre_tasacion_desde}* y *USD {pre_tasacion_hasta}*.\n\n"
		. "Para continuar, un asesor de nuestro equipo se comunicará contigo para revisar los detalles.",

	'respuesta_humana_forma_pago' =>
    "{nombre_cliente}, estaríamos comprando su vehículo al contado entre *USD {pre_tasacion_desde}* a *USD {pre_tasacion_hasta}* (nosotros asumimos los honorarios y gastos de escribanos)"
    . "Para definir el precio exacto y revisar el vehículo será necesaria la inspección mecánica. La misma es realizada en nuestro local ubicado en Av. de las Américas 7868 (Frente al Puente de las Américas), tiene una duración de 30 min y es sin costo.\n\n"
    . "¿Le gustaría agendarse para la revisión?",
	
	'respuesta_cierre_no_agenda' =>
		"Gracias por comunicarte con Motorlider. Quedamos a las órdenes para cuando quieras retomar la cotización."
];

$plantilla = $plantillasWhatsapp[$clavePlantilla] ?? '';

if (trim($plantilla) === '') {
	j(['ok' => false, 'mensaje' => 'No se encontró la plantilla configurada en código para la clave: ' . $clavePlantilla]);
}

// =========================
// ARMAR MENSAJE
// =========================
$nombreCliente = trim((string)($elemento['nombre'] ?? ''));

if ($nombreCliente === '') {
	$nombreCliente = 'Estimado/a cliente';
}

$desdeFmt = number_format((float)$pretasacion_desde, 0, ',', '.');
$hastaFmt = number_format((float)$pretasacion_hasta, 0, ',', '.');

$mensaje = $plantilla;
$mensaje = str_replace('{nombre_cliente}', $nombreCliente, $mensaje);
$mensaje = str_replace('{pre_tasacion_desde}', $desdeFmt, $mensaje);
$mensaje = str_replace('{pre_tasacion_hasta}', $hastaFmt, $mensaje);

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
// GUARDAR HISTORIAL WHATSAPP
// =========================
$idConversacion = obtener_id_conversacion_por_telefono($telefono);

registrar_mensaje_whatsapp_backend(
	$idConversacion,
	$telefono,
	$mensaje,
	(string)($envio['sid'] ?? ''),
	'backend_pre_tasacion',
	[
		'id_cotizacion' => intval($id),
		'id_usuario' => intval($idUsuario),
		'tipo_venta' => $tipoVenta,
		'clave_plantilla' => $clavePlantilla
	],
	$idUsuario,
	(string)($usuario['nombre'] ?? '')
);

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
		'ENVIO_PRE_TASACION',
		'" . $db->escape($mensaje) . "',
		" . floatval($pretasacion_desde) . ",
		" . floatval($pretasacion_hasta) . ",
		NULL,
		'" . $db->escape((string)($envio['sid'] ?? '')) . "',
		NOW()
	)
");

// =========================
// UPDATE COTIZACION
// =========================
$humanoTomadoPor = '';
if (!empty($usuario['nombre'])) {
	$humanoTomadoPor = $usuario['nombre'];
	if (!empty($usuario['email'])) {
		$humanoTomadoPor .= ' <' . $usuario['email'] . '>';
	}
}

$mensajeEsc = $db->escape($mensaje);
$humanoEsc = $db->escape($humanoTomadoPor);

$db->query("
	UPDATE cotizaciones_generadas
	SET
		pretasacion_desde = " . floatval($pretasacion_desde) . ",
		pretasacion_hasta = " . floatval($pretasacion_hasta) . ",
		msg = '{$mensajeEsc}',
		detalle_estado = 'Respuesta humana enviada por WhatsApp',
		estado = 'PRELIMINAR',
		estado_id = 3,
		id_usuario_cotizo = " . intval($idUsuario) . ",
		fecha_mod = NOW()
	WHERE id_cotizaciones_generadas = '" . intval($id) . "'
	LIMIT 1
");

j([
	'ok' => true,
	'mensaje' => 'Respuesta enviada correctamente por WhatsApp.',
	'whatsapp_sid' => $envio['sid'] ?? '',
	'tipo_venta' => $tipoVenta,
	'id_conversacion' => $idConversacion
]);