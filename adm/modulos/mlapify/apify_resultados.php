<?php
error_reporting(E_ERROR | E_PARSE);
header("Content-Type: application/json; charset=utf-8");

require_once(__DIR__ . "/../../config/config.inc.php");
require_once(__DIR__ . "/../../includes/database.php");
require_once(__DIR__ . "/../../includes/funciones.php");

session_start();
require_once(__DIR__ . "/../../includes/chk_login.php");

if (!isset($_SESSION[$config['codigo_unico']]['login_permisos']['mlapify']) || $_SESSION[$config['codigo_unico']]['login_permisos']['mlapify'] <= 0) {
	echo json_encode(['ok' => false, 'mensaje' => 'Sin permisos para mlapify']);
	exit;
}

$corridaId = trim((string)($_GET['corrida_id'] ?? ''));
if ($corridaId === '') {
	echo json_encode(['ok' => false, 'mensaje' => 'corrida_id requerido']);
	exit;
}

$rows = [];
$q = $db->query("
	SELECT titulo, precio, moneda, anio, km, ubicacion, url
	FROM apify_publicaciones
	WHERE corrida_id='" . $db->escape($corridaId) . "'
	ORDER BY id DESC
	LIMIT 200
");

while ($r = $db->fetch_array($q)) {
	$rows[] = [
		'titulo' => $r['titulo'],
		'precio' => $r['precio'],
		'moneda' => $r['moneda'],
		'anio' => $r['anio'],
		'km' => $r['km'],
		'ubicacion' => $r['ubicacion'],
		'url' => $r['url'],
	];
}

echo json_encode(['ok' => true, 'rows' => $rows]);