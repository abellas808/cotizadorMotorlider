<?php
error_reporting(E_ERROR | E_PARSE);
header("Content-Type: application/json; charset=utf-8");

require_once(__DIR__ . "/../../config/config.inc.php");
require_once(__DIR__ . "/../../includes/database.php");
require_once(__DIR__ . "/../../includes/funciones.php");

session_start();
require_once(__DIR__ . "/../../includes/chk_login.php");

// Permiso
if (!isset($_SESSION[$config['codigo_unico']]['login_permisos']['mlapify']) || $_SESSION[$config['codigo_unico']]['login_permisos']['mlapify'] <= 0) {
	echo json_encode(['ok' => false, 'mensaje' => 'Sin permisos para mlapify']);
	exit;
}

$now = date('Y-m-d H:i:s');

function slugify_local($s) {
	$s = trim((string)$s);
	$map = [
		'á'=>'a','à'=>'a','ä'=>'a','â'=>'a','Á'=>'a','À'=>'a','Ä'=>'a','Â'=>'a',
		'é'=>'e','è'=>'e','ë'=>'e','ê'=>'e','É'=>'e','È'=>'e','Ë'=>'e','Ê'=>'e',
		'í'=>'i','ì'=>'i','ï'=>'i','î'=>'i','Í'=>'i','Ì'=>'i','Ï'=>'i','Î'=>'i',
		'ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','Ó'=>'o','Ò'=>'o','Ö'=>'o','Ô'=>'o',
		'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u','Ú'=>'u','Ù'=>'u','Ü'=>'u','Û'=>'u',
		'ñ'=>'n','Ñ'=>'n'
	];
	$s = strtr($s, $map);
	$s = strtolower($s);
	$s = preg_replace('/[^a-z0-9]+/', '-', $s);
	$s = preg_replace('/-+/', '-', $s);
	return trim($s, '-');
}

// ===============================
// 1) Obtener marcas y modelos prioridad=1
// ===============================
$q = $db->query("
	SELECT 
		m.id_marca AS marca_id,
		m.nombre AS marca_nombre,
		mo.id_model AS modelo_id,
		mo.nombre AS modelo_nombre
	FROM act_marcas m
	INNER JOIN act_modelo mo 
		ON mo.id_marca = m.id_marca
	WHERE m.prioridad = 1
	  AND mo.prioridad = 1
	ORDER BY m.nombre, mo.nombre
");

if (!$q) {
	echo json_encode([
		'ok' => false,
		'mensaje' => 'Error consultando marcas/modelos'
	]);
	exit;
}

$jobs_creados = 0;
$errores = [];

while ($row = $db->fetch_array($q)) {

	$marca_id = intval($row['marca_id']);
	$marca_nombre = trim((string)$row['marca_nombre']);
	$modelo_id = intval($row['modelo_id']);
	$modelo_nombre = trim((string)$row['modelo_nombre']);

	if (!$marca_id || !$modelo_id) continue;

	$marca_slug = slugify_local($marca_nombre);
	$modelo_slug = slugify_local($modelo_nombre);

	$deep_url = "{$marca_slug}/{$modelo_slug}/usado/_NoIndex_True";

	$sqlJob = "
		INSERT INTO apify_jobs
		(
			brand_id, brand_name, model_id, model_name, deep_url,
			estado, intentos, created_at, updated_at
		)
		VALUES
		(
			{$marca_id},
			'".$db->escape($marca_nombre)."',
			{$modelo_id},
			'".$db->escape($modelo_nombre)."',
			'".$db->escape($deep_url)."',
			'PENDIENTE',
			0,
			'{$now}',
			'{$now}'
		)
	";

	$r = $db->query($sqlJob);
	if ($r) {
		$jobs_creados++;
	} else {
		$errores[] = [
			'marca_id' => $marca_id,
			'marca' => $marca_nombre,
			'modelo_id' => $modelo_id,
			'modelo' => $modelo_nombre
		];
	}
}

echo json_encode([
	'ok' => true,
	'jobs_creados' => $jobs_creados,
	'errores' => $errores
]);
exit;