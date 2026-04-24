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
const TWILIO_TEMPLATE_TASACION_FINAL = 'HXc41819850772f4d568aa242c5045f1e6';
const TWILIO_ACCOUNT_SID = 'AC4a648c5c55de9d9b1f1f6601b14d4c4d';
const TWILIO_AUTH_TOKEN  = '58f767d26211d9d0c20ea687df00b4c3';
const TWILIO_WHATSAPP_FROM = 'whatsapp:+59898057857';

function j($a) {
	if (ob_get_length()) {
		ob_clean();
	}
	echo json_encode($a);
	exit;
}

function registrar_mensaje_whatsapp_backend($idConversacion, $telefono, $mensaje, $sidMensaje = '', $origen = 'backend')
{
	global $db;

	$idConversacion = intval($idConversacion);
	$telefono = trim((string)$telefono);
	$mensaje = trim((string)$mensaje);
	$sidMensaje = trim((string)$sidMensaje);

	if ($idConversacion <= 0 || $telefono === '' || $mensaje === '') {
		return false;
	}

	$meta = json_encode([
		'origen' => $origen
	], JSON_UNESCAPED_UNICODE);

	$db->query("
		INSERT INTO whatsapp_conversacion_mensajes
		(
			id_conversacion,
			telefono,
			direccion,
			emisor,
			mensaje,
			meta_json,
			sid_mensaje,
			fecha
		)
		VALUES
		(
			'" . intval($idConversacion) . "',
			'" . $db->escape($telefono) . "',
			'SALIENTE',
			'BOT',
			'" . $db->escape($mensaje) . "',
			'" . $db->escape($meta) . "',
			" . ($sidMensaje !== '' ? "'" . $db->escape($sidMensaje) . "'" : "NULL") . ",
			NOW()
		)
	");

	return true;
}

function enviar_whatsapp_template_twilio($to, $contentSid, $variables = []) {
	$url = 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_ACCOUNT_SID . '/Messages.json';

	$postData = [
		'From' => TWILIO_WHATSAPP_FROM,
		'To' => $to,
		'ContentSid' => $contentSid
	];

	if (!empty($variables)) {
		$postData['ContentVariables'] = json_encode($variables, JSON_UNESCAPED_UNICODE);
	}

	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => http_build_query($postData),
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
$tasacion_final = trim((string)($_POST['tasacion_final'] ?? ''));

if ($id <= 0) {
	j(['ok' => false, 'mensaje' => 'ID inválido.']);
}

$idUsuario = intval($_SESSION[$config['codigo_unico']]['login_usuario_id'] ?? 0);

$sets = [];

if ($pretasacion_desde !== '') {
	$sets[] = "pretasacion_desde = " . floatval($pretasacion_desde);
}

if ($pretasacion_hasta !== '') {
	$sets[] = "pretasacion_hasta = " . floatval($pretasacion_hasta);
}

if ($tasacion_final !== '') {
	$sets[] = "tasacion_final = " . floatval($tasacion_final);
}

if ($idUsuario > 0) {
	$sets[] = "id_usuario_cotizo = " . intval($idUsuario);
}

$sets[] = "fecha_mod = NOW()";

$sql = "
	UPDATE cotizaciones_generadas
	SET " . implode(", ", $sets) . "
	WHERE id_cotizaciones_generadas = " . intval($id) . "
	LIMIT 1
";

$ok = $db->query($sql);

if (!$ok) {
	j([
		'ok' => false,
		'mensaje' => 'No se pudo guardar la tasación.',
		'sql' => $sql
	]);
}

$usuario = null;
if ($idUsuario > 0) {
	$usuario = $db->query_first("
		SELECT nombre, email
		FROM admin_usuarios
		WHERE id = " . intval($idUsuario) . "
		LIMIT 1
	");
}

$modoEnvio = trim((string)($_POST['modo_envio'] ?? ''));

if ($modoEnvio === 'final') {

	if ($tasacion_final === '' || floatval($tasacion_final) <= 0) {
		j([
			'ok' => false,
			'mensaje' => 'Para finalizar, completá la tasación final.'
		]);
	}

	// =========================
	// BUSCAR DATOS DE LA COTIZACIÓN
	// =========================
	$cot = $db->query_first("
		SELECT nombre, telefono
		FROM cotizaciones_generadas
		WHERE id_cotizaciones_generadas = " . intval($id) . "
		LIMIT 1
	");

	if (!$cot) {
		j([
			'ok' => false,
			'mensaje' => 'No se encontró la cotización.'
		]);
	}

	$nombreCliente = trim((string)($cot['nombre'] ?? 'Cliente'));
	$telefono = trim((string)($cot['telefono'] ?? ''));

	if ($telefono === '') {
		j([
			'ok' => false,
			'mensaje' => 'La cotización no tiene teléfono.'
		]);
	}

	$tasacionFmt = number_format(floatval($tasacion_final), 0, ',', '.');

	// =========================
	// MENSAJE QUE SE ENVÍA AL CLIENTE
	// =========================
	$mensaje = "👋 ¡Hola de nuevo, {$nombreCliente}!\n\n"
		. "Muchas gracias por visitarnos hoy en Motorlider. Luego de revisar tu vehículo, la tasación final es de USD {$tasacionFmt}.\n\n"
		. "¿Te gustaría avanzar con el negocio?\n"
		. "1 = Sí, quiero avanzar ✅\n"
		. "2 = Por ahora no ❌";

	// =========================
	// ENVIAR WHATSAPP
	// =========================
	$envio = enviar_whatsapp_template_twilio(
	$telefono,
		TWILIO_TEMPLATE_TASACION_FINAL,
		[
			'Nombre del Cliente' => $nombreCliente,
			'Monto' => $tasacionFmt
		]
	);

	if (!$envio['ok']) {
		j([
			'ok' => false,
			'mensaje' => 'No se pudo enviar el WhatsApp: ' . ($envio['mensaje'] ?? 'Error desconocido.')
		]);
	}

	// =========================
	// OBTENER ID_CONVERSACION DESDE MENSAJES
	// No usamos whatsapp_conversaciones
	// =========================
	$rowConv = $db->query_first("
		SELECT id_conversacion
		FROM whatsapp_conversacion_mensajes
		WHERE telefono = '" . $db->escape($telefono) . "'
		ORDER BY id DESC
		LIMIT 1
	");

	$idConversacion = intval($rowConv['id_conversacion'] ?? 0);

	if ($idConversacion > 0) {
		registrar_mensaje_whatsapp_backend(
			$idConversacion,
			$telefono,
			$mensaje,
			(string)($envio['sid'] ?? ''),
			'backend_tasacion_final'
		);
	}

	// =========================
	// ACTUALIZAR ESTADO FINALIZADO
	// =========================
	$db->query("
		UPDATE cotizaciones_generadas
		SET
			estado = 'FINALIZADO',
			estado_id = 4,
			msg = '" . $db->escape($mensaje) . "',
			detalle_estado = 'Tasación final enviada por WhatsApp',
			fecha_mod = NOW()
		WHERE id_cotizaciones_generadas = " . intval($id) . "
		LIMIT 1
	");

	j([
		'ok' => true,
		'mensaje' => 'Tasación final enviada correctamente.',
		'whatsapp_sid' => $envio['sid'] ?? '',
		'id_usuario_cotizo' => $idUsuario,
		'usuario_nombre' => $usuario['nombre'] ?? '',
		'usuario_email' => $usuario['email'] ?? ''
	]);
}

j([
	'ok' => true,
	'mensaje' => 'Tasación guardada correctamente.',
	'id_usuario_cotizo' => $idUsuario,
	'usuario_nombre' => $usuario['nombre'] ?? '',
	'usuario_email' => $usuario['email'] ?? ''
]);