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

$sets[] = "estado = 'FINALIZADO'";
$sets[] = "estado_id = 4";
$sets[] = "fecha_mod = NOW()";

if (empty($sets)) {
	j([
		'ok' => true,
		'mensaje' => 'No hubo cambios para guardar.',
		'id_usuario_cotizo' => $idUsuario
	]);
}

$sql = "
	UPDATE cotizaciones_generadas
	SET " . implode(", ", $sets) . "
	WHERE id_cotizaciones_generadas = " . intval($id) . "
	LIMIT 1
";

$ok = $db->query($sql);

if (!$ok) {
	j(['ok' => false, 'mensaje' => 'No se pudo guardar la tasación.']);
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

j([
	'ok' => true,
	'mensaje' => 'Tasación guardada correctamente.',
	'id_usuario_cotizo' => $idUsuario,
	'usuario_nombre' => $usuario['nombre'] ?? '',
	'usuario_email' => $usuario['email'] ?? ''
]);