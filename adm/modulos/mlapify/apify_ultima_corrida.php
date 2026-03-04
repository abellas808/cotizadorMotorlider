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

global $db;
if (!isset($db) || !$db) {
	http_response_code(500);
	echo json_encode(["ok"=>false,"mensaje"=>"DB no inicializada"]);
	exit;
}

$q = $db->query("SELECT * FROM apify_corridas ORDER BY created_at DESC LIMIT 1");
$row = $db->fetch_array($q);

if (!$row) {
	echo json_encode(["ok"=>true], JSON_UNESCAPED_UNICODE);
	exit;
}

echo json_encode([
	"ok" => true,
	"corrida_id" => $row['corrida_id'] ?? null,
	"estado" => $row['estado'] ?? null,
	"mensaje" => $row['mensaje'] ?? null,
	"total_items" => $row['total_items'] ?? null,
	"items_guardados" => $row['items_guardados'] ?? null,
], JSON_UNESCAPED_UNICODE);