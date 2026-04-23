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

function obtener_parametro_sistema($grupo, $clave) {
	global $db;

	$grupoEsc = $db->escape($grupo);
	$claveEsc = $db->escape($clave);

	$row = $db->query_first("
		SELECT valor
		FROM parametros_sistema
		WHERE grupo = '{$grupoEsc}'
		  AND clave = '{$claveEsc}'
		  AND activo = 1
		ORDER BY id DESC
		LIMIT 1
	");

	return $row['valor'] ?? null;
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

$elemento = $db->query_first("
	SELECT *
	FROM cotizaciones_generadas
	WHERE id_cotizaciones_generadas = '" . intval($id) . "'
	LIMIT 1
");

if (!$elemento) {
	j(['ok' => false, 'mensaje' => 'Cotización no encontrada.']);
}

$conv = $db->query_first("
	SELECT *
	FROM whatsapp_conversaciones
	WHERE id_cotizacion = '" . intval($id) . "'
	ORDER BY id DESC
	LIMIT 1
");

if (!$conv) {
	j(['ok' => false, 'mensaje' => 'No se encontró conversación de WhatsApp asociada a la cotización.']);
}

$estadoConv = trim((string)($conv['estado'] ?? ''));

if ($estadoConv === '') {
	j(['ok' => false, 'mensaje' => 'La conversación no tiene estado válido.']);
}

$telefono = trim((string)($conv['telefono'] ?? ''));
if ($telefono === '') {
	j(['ok' => false, 'mensaje' => 'La conversación no tiene teléfono de WhatsApp.']);
}

$datosJson = [];
if (!empty($conv['datos_json'])) {
	$tmp = json_decode((string)$conv['datos_json'], true);
	if (is_array($tmp)) {
		$datosJson = $tmp;
	}
}

$tipoVenta = trim((string)($datosJson['tipo_venta'] ?? ''));
if ($tipoVenta === '') {
	j(['ok' => false, 'mensaje' => 'No se encontró tipo_venta en la conversación.']);
}

if ($tipoVenta === 'venta_contado') {
	$clavePlantilla = 'respuesta_humana_venta_contado';
} elseif ($tipoVenta === 'entrega_forma_pago') {
	$clavePlantilla = 'respuesta_humana_forma_pago';
} else {
	j(['ok' => false, 'mensaje' => 'tipo_venta inválido: ' . $tipoVenta]);
}

$plantilla = obtener_parametro_sistema('whatsapp_cotizador', $clavePlantilla);
if ($plantilla === null || trim($plantilla) === '') {
	j(['ok' => false, 'mensaje' => 'No se encontró la plantilla en parametros_sistema.']);
}

$nombreCliente = trim((string)($conv['nombre'] ?? ''));
if ($nombreCliente === '') {
	$nombreCliente = trim((string)($elemento['nombre'] ?? ''));
}
if ($nombreCliente === '') {
	$nombreCliente = 'Estimado/a cliente';
}

$desdeFmt = number_format((float)$pretasacion_desde, 0, ',', '.');
$hastaFmt = number_format((float)$pretasacion_hasta, 0, ',', '.');

$mensaje = $plantilla;
$mensaje = str_replace('{nombre_cliente}', $nombreCliente, $mensaje);
$mensaje = str_replace('{pre_tasacion_desde}', $desdeFmt, $mensaje);
$mensaje = str_replace('{pre_tasacion_hasta}', $hastaFmt, $mensaje);

$envio = enviar_whatsapp_twilio($telefono, $mensaje);

if (!$envio['ok']) {
	j([
		'ok' => false,
		'mensaje' => 'No se pudo enviar el WhatsApp: ' . ($envio['mensaje'] ?? 'Error desconocido.')
	]);
}

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
	UPDATE whatsapp_conversaciones
	SET
		estado = 'HUMANO_EN_CONVERSACION',
		modo_atencion = 'HUMANO',
		ultima_respuesta_bot = '{$mensajeEsc}',
		humano_tomado_por = '{$humanoEsc}',
		fecha_ultima_interaccion = NOW(),
		fecha_mod = NOW()
	WHERE id = '" . intval($conv['id']) . "'
	LIMIT 1
");

$db->query("
	UPDATE cotizaciones_generadas
	SET
		pretasacion_desde = " . floatval($pretasacion_desde) . ",
		pretasacion_hasta = " . floatval($pretasacion_hasta) . ",
		msg = '{$mensajeEsc}',
		detalle_estado = 'Respuesta humana enviada por WhatsApp', 
		estado = 'PRELIMINAR',
		estado_id=3,
		fecha_mod = NOW()

	WHERE id_cotizaciones_generadas = '" . intval($id) . "'
	LIMIT 1
");

j([
	'ok' => true,
	'mensaje' => 'Respuesta enviada correctamente por WhatsApp.',
	'whatsapp_sid' => $envio['sid'] ?? '',
	'tipo_venta' => $tipoVenta
]);