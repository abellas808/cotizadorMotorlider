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

	$db->query("
		UPDATE cotizaciones_generadas
		SET
			estado = 'FINALIZADO',
			estado_id = 4,
			detalle_estado = 'Tasación final enviada por WhatsApp',
			fecha_mod = NOW()
		WHERE id_cotizaciones_generadas = " . intval($id) . "
		LIMIT 1
	");

	j([
		'ok' => true,
		'mensaje' => 'Tasación final enviada correctamente.',
		'id_usuario_cotizo' => $idUsuario,
		'usuario_nombre' => $usuario['nombre'] ?? '',
		'usuario_email' => $usuario['email'] ?? ''
	]);

	registrar_mensaje_whatsapp_backend(
		(int)$conv['id'],
		$telefono,
		$mensaje,
		(string)($envio['sid'] ?? ''),
		'backend_tasacion_final'
	);
}

j([
	'ok' => true,
	'mensaje' => 'Tasación guardada correctamente.',
	'id_usuario_cotizo' => $idUsuario,
	'usuario_nombre' => $usuario['nombre'] ?? '',
	'usuario_email' => $usuario['email'] ?? ''
]);