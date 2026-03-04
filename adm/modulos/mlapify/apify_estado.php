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

$run = $db->query_first("SELECT * FROM apify_corridas WHERE corrida_id='" . $db->escape($corridaId) . "'");
if (!$run) {
	echo json_encode(['ok' => false, 'mensaje' => 'Corrida no encontrada']);
	exit;
}

echo json_encode([
	'ok' => true,
	'estado' => $run['estado'] ?? 'error',
	'total_items' => (int)($run['total_items'] ?? 0),
	'items_guardados' => (int)($run['items_guardados'] ?? 0),
	'mensaje' => $run['mensaje'] ?? '',
]);