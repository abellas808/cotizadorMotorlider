<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

function jok($a = []) {
  echo json_encode(array_merge(["ok" => true], $a), JSON_UNESCAPED_UNICODE);
  exit;
}

function jerr($m, $a = []) {
  http_response_code(500);
  echo json_encode(array_merge(["ok" => false, "mensaje" => $m], $a), JSON_UNESCAPED_UNICODE);
  exit;
}

// DB del admin
require_once(__DIR__ . "/../../config/config.inc.php");
require_once(__DIR__ . "/../../includes/database.php");
require_once(__DIR__ . "/../../includes/funciones.php");

global $db;
if (!isset($db) || !$db) {
  jerr("DB no inicializada (\$db).");
}

function db_escape($db, $value) {
  return method_exists($db, 'escape') ? $db->escape($value) : addslashes($value);
}

function slugify($s) {
  $s = trim((string)$s);

  $map = [
    'á'=>'a','à'=>'a','ä'=>'a','â'=>'a','Á'=>'a','À'=>'a','Ä'=>'a','Â'=>'a',
    'é'=>'e','è'=>'e','ë'=>'e','ê'=>'e','É'=>'e','È'=>'e','Ë'=>'e','Ê'=>'e',
    'í'=>'i','ì'=>'i','ï'=>'i','î'=>'i','Í'=>'i','Ì'=>'i','Ï'=>'i','Î'=>'i',
    'ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','Ó'=>'o','Ò'=>'o','Ö'=>'o','Ô'=>'o',
    'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u','Ú'=>'u','Ù'=>'u','Ü'=>'u','Û'=>'u',
    'ñ'=>'n','Ñ'=>'n','ç'=>'c','Ç'=>'c'
  ];

  $s = strtr($s, $map);
  $s = strtolower($s);
  $s = preg_replace('/[^a-z0-9]+/', '-', $s);
  $s = preg_replace('/-+/', '-', $s);

  return trim($s, '-');
}

function buildDeepUrl($brandName, $modelName) {
  $b = slugify($brandName);
  $m = slugify($modelName);
  return rtrim("{$b}/{$m}/usado/_NoIndex_True", '/');
}

/*
  Criterio:
  - act_marcas.prioridad = 1
  - act_modelo.prioridad = 1
  - relación por id_marca
*/
$sql = "
  SELECT
    ma.id_marca   AS brand_id,
    ma.nombre     AS brand_name,
    mo.id_model   AS model_id,
    mo.nombre     AS model_name
  FROM act_marcas ma
  INNER JOIN act_modelo mo
    ON mo.id_marca = ma.id_marca
  WHERE
    COALESCE(ma.prioridad, 0) = 1
    AND COALESCE(mo.prioridad, 0) = 1
  ORDER BY ma.nombre, mo.nombre
";

$q = $db->query($sql);
if (!$q) {
  jerr("Error consultando marcas/modelos prioritarios.");
}

$jobs = [];
while ($r = $db->fetch_array($q)) {
  $brandId   = intval($r['brand_id'] ?? 0);
  $brandName = trim((string)($r['brand_name'] ?? ''));
  $modelId   = intval($r['model_id'] ?? 0);
  $modelName = trim((string)($r['model_name'] ?? ''));

  if ($brandId <= 0 || $modelId <= 0 || $brandName === '' || $modelName === '') {
    continue;
  }

  $jobs[] = [
    'brand_id'   => $brandId,
    'brand_name' => $brandName,
    'model_id'   => $modelId,
    'model_name' => $modelName
  ];
}

if (!$jobs) {
  jerr("No hay combinaciones marca/modelo con prioridad=1 en act_marcas y act_modelo.");
}

$ins = 0;
$upd = 0;
$errors = [];

foreach ($jobs as $job) {
  $brand_id   = intval($job['brand_id']);
  $brand_name = (string)$job['brand_name'];
  $model_id   = intval($job['model_id']);
  $model_name = (string)$job['model_name'];

  $deep = buildDeepUrl($brand_name, $model_name);

  $deepEsc = db_escape($db, $deep);
  $bnEsc   = db_escape($db, $brand_name);
  $mnEsc   = db_escape($db, $model_name);

  // Verificamos existencia previa SOLO por brand_id + model_id
  $qx = $db->query("
    SELECT id, estado
    FROM apify_jobs
    WHERE brand_id = {$brand_id}
      AND model_id = {$model_id}
    LIMIT 1
  ");

  $ex = $qx ? $db->fetch_array($qx) : null;

  $ok = $db->query("
    INSERT INTO apify_jobs (
      brand_id,
      brand_name,
      model_id,
      model_name,
      deep_url,
      estado,
      intentos,
      next_run_at,
      mensaje
    )
    VALUES (
      {$brand_id},
      '{$bnEsc}',
      {$model_id},
      '{$mnEsc}',
      '{$deepEsc}',
      'PENDIENTE',
      0,
      NULL,
      NULL
    )
    ON DUPLICATE KEY UPDATE
      brand_name  = VALUES(brand_name),
      model_name  = VALUES(model_name),
      deep_url    = VALUES(deep_url),
      estado      = 'PENDIENTE',
      intentos    = 0,
      next_run_at = NULL,
      mensaje     = NULL,
      updated_at  = NOW()
  ");

  if (!$ok) {
    $errors[] = [
      'brand_id'   => $brand_id,
      'brand_name' => $brand_name,
      'model_id'   => $model_id,
      'model_name' => $model_name,
      'deep_url'   => $deep
    ];
    continue;
  }

  if ($ex) {
    $upd++;
  } else {
    $ins++;
  }
}

jok([
  "criterio" => [
    "act_marcas.prioridad" => 1,
    "act_modelo.prioridad" => 1,
    "clave_job" => "brand_id + model_id",
    "deep_url_solo_dato" => true
  ],
  "jobs_encontrados" => count($jobs),
  "inserted" => $ins,
  "updated" => $upd,
  "errors" => count($errors),
  "detalle_errores" => $errors
]);