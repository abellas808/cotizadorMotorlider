<?php
error_reporting(E_ERROR | E_PARSE);
header("Content-Type: application/json; charset=utf-8");

require_once(__DIR__ . "/../../config/config.inc.php");
require_once(__DIR__ . "/../../includes/database.php");
require_once(__DIR__ . "/../../includes/funciones.php");
require_once(__DIR__ . "/apify_adapter.php");

session_start();
require_once(__DIR__ . "/../../includes/chk_login.php");

// Permiso
if (!isset($_SESSION[$config['codigo_unico']]['login_permisos']['mlapify']) || $_SESSION[$config['codigo_unico']]['login_permisos']['mlapify'] <= 0) {
	echo json_encode(['ok' => false, 'mensaje' => 'Sin permisos para mlapify']);
	exit;
}

// Params
$marca = trim((string)($_POST['marca'] ?? ''));
$modelo = trim((string)($_POST['modelo'] ?? ''));
$anio_desde = trim((string)($_POST['anio_desde'] ?? ''));
$anio_hasta = trim((string)($_POST['anio_hasta'] ?? ''));
$km_desde   = trim((string)($_POST['km_desde'] ?? ''));
$km_hasta   = trim((string)($_POST['km_hasta'] ?? ''));

// solo agregamos si viene con valor
if ($anio_desde !== '') $params['anio_desde'] = (int)$anio_desde;
if ($anio_hasta !== '') $params['anio_hasta'] = (int)$anio_hasta;
if ($km_desde   !== '') $params['km_desde']   = (int)$km_desde;
if ($km_hasta   !== '') $params['km_hasta']   = (int)$km_hasta;

$params = [
	'marca'  => $marca,
  	'modelo' => $modelo,
	'anio_desde' => $anio_desde,
	'anio_hasta' => $anio_hasta,
	'km_desde' => $km_desde,
	'km_hasta' => $km_hasta,
];

$corridaId = date('YmdHis') . '_' . substr(md5(uniqid('', true)), 0, 8);
$now = date('Y-m-d H:i:s');

try {
	// 1) Crear corrida
	$db->query("
		INSERT INTO apify_corridas (corrida_id, estado, mensaje, params_json, total_items, items_guardados, created_at, updated_at)
		VALUES (
			'".$db->escape($corridaId)."',
			'corriendo',
			'Iniciando...',
			'".$db->escape(json_encode($params, JSON_UNESCAPED_UNICODE))."',
			0,0,
			'".$now."',
			'".$now."'
		)
	");

	// 2) Ejecutar Apify (tu clase real)
	$items = apify_buscar_publicaciones($params);
	if (!is_array($items)) $items = [];

	$total = count($items);
	$guardados = 0;

	// 3) Persistir publicaciones (por URL única)
	foreach ($items as $it) {
		$url = (string)($it['url'] ?? $it['permalink'] ?? '');
		if ($url === '') continue;

		$titulo = (string)($it['title'] ?? $it['titulo'] ?? '');
		$precio = $it['price'] ?? $it['precio'] ?? null;
		$moneda = (string)($it['currency'] ?? $it['moneda'] ?? '');
		$anio = $it['year'] ?? $it['anio'] ?? null;
		$km = $it['km'] ?? $it['kilometros'] ?? null;
		$ubic = (string)($it['location'] ?? $it['ubicacion'] ?? '');
		$mlid = (string)($it['id'] ?? $it['ml_id'] ?? '');
		$vendedor = (string)($it['seller'] ?? $it['vendedor'] ?? '');
		$es_oficial = (!empty($it['official_store']) || !empty($it['es_oficial'])) ? 1 : 0;

		$raw = json_encode($it, JSON_UNESCAPED_UNICODE);

		$sql = "
			INSERT INTO apify_publicaciones
			(corrida_id, fuente, ml_id, titulo, precio, moneda, anio, km, ubicacion, url, vendedor, es_oficial, raw_json, created_at)
			VALUES
			(
				'".$db->escape($corridaId)."',
				'ml',
				'".$db->escape($mlid)."',
				'".$db->escape(substr($titulo, 0, 255))."',
				".($precio === null || $precio === '' ? "NULL" : floatval($precio)).",
				'".$db->escape(substr($moneda, 0, 10))."',
				".($anio === null || $anio === '' ? "NULL" : intval($anio)).",
				".($km === null || $km === '' ? "NULL" : intval($km)).",
				'".$db->escape(substr($ubic, 0, 180))."',
				'".$db->escape(substr($url, 0, 500))."',
				'".$db->escape(substr($vendedor, 0, 180))."',
				".intval($es_oficial).",
				'".$db->escape($raw)."',
				'".$now."'
			)
			ON DUPLICATE KEY UPDATE
				corrida_id = VALUES(corrida_id),
				ml_id = VALUES(ml_id),
				titulo = VALUES(titulo),
				precio = VALUES(precio),
				moneda = VALUES(moneda),
				anio = VALUES(anio),
				km = VALUES(km),
				ubicacion = VALUES(ubicacion),
				vendedor = VALUES(vendedor),
				es_oficial = VALUES(es_oficial),
				raw_json = VALUES(raw_json)
		";

		$db->query($sql);
		$guardados++;
	}

	// 4) Finalizar corrida
	$db->query("
		UPDATE apify_corridas
		SET estado='ok',
		    mensaje='OK',
		    total_items=".intval($total).",
		    items_guardados=".intval($guardados).",
		    updated_at='".$now."'
		WHERE corrida_id='".$db->escape($corridaId)."'
	");

	echo json_encode(['ok' => true, 'corrida_id' => $corridaId]);
	exit;

} catch (Exception $e) {
	$msg = substr($e->getMessage(), 0, 250);

	// intentar actualizar corrida si llegó a insertarla
	$db->query("
		UPDATE apify_corridas
		SET estado='error',
		    mensaje='".$db->escape($msg)."',
		    updated_at='".$now."'
		WHERE corrida_id='".$db->escape($corridaId)."'
	");

	echo json_encode(['ok' => false, 'mensaje' => $msg, 'corrida_id' => $corridaId]);
	exit;
}