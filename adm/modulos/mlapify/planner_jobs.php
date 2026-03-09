<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0');
error_reporting(E_ALL);

function jok($a=[]) { echo json_encode(array_merge(["ok"=>true],$a), JSON_UNESCAPED_UNICODE); exit; }
function jerr($m,$a=[]) { http_response_code(500); echo json_encode(array_merge(["ok"=>false,"mensaje"=>$m],$a), JSON_UNESCAPED_UNICODE); exit; }

// DB del admin (mismo patrón que tu run_ml.php)
require_once(__DIR__ . "/../../config/config.inc.php");
require_once(__DIR__ . "/../../includes/database.php");
require_once(__DIR__ . "/../../includes/funciones.php");

global $db;
if (!isset($db) || !$db) jerr("DB no inicializada (\$db).");

function get_app_param($db, $key, $default=null) {
  $k = method_exists($db,'escape') ? $db->escape($key) : addslashes($key);
  $q = $db->query("SELECT param_value FROM app_params WHERE param_key='{$k}' LIMIT 1");
  if (!$q) return $default;
  $r = $db->fetch_array($q);
  if (!$r) return $default;
  $v = $r['param_value'] ?? null;
  return ($v === null || $v === '') ? $default : $v;
}

function slugify($s) {
  $s = trim((string)$s);
  $map = ['á'=>'a','à'=>'a','ä'=>'a','â'=>'a','Á'=>'a','À'=>'a','Ä'=>'a','Â'=>'a','é'=>'e','è'=>'e','ë'=>'e','ê'=>'e','É'=>'e','È'=>'e','Ë'=>'e','Ê'=>'e','í'=>'i','ì'=>'i','ï'=>'i','î'=>'i','Í'=>'i','Ì'=>'i','Ï'=>'i','Î'=>'i','ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','Ó'=>'o','Ò'=>'o','Ö'=>'o','Ô'=>'o','ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u','Ú'=>'u','Ù'=>'u','Ü'=>'u','Û'=>'u','ñ'=>'n','Ñ'=>'n'];
  $s = strtr($s, $map);
  $s = strtolower($s);
  $s = preg_replace('/[^a-z0-9]+/','-', $s);
  $s = preg_replace('/-+/','-', $s);
  return trim($s,'-');
}

function buildDeepUrl($brandName, $modelName) {
  $b = slugify($brandName);
  $m = slugify($modelName);
  return rtrim("{$b}/{$m}/usado/montevideo/_NoIndex_True", '/');
}

$manual_brand_id = intval(get_app_param($db, 'manual_brand_id', 0));
if ($manual_brand_id <= 0) jerr("Falta app_params.manual_brand_id");

$qB = $db->query("SELECT id_marca, nombre FROM act_marcas WHERE id_marca={$manual_brand_id} LIMIT 1");
$brand = $qB ? $db->fetch_array($qB) : null;
if (!$brand) jerr("No existe act_marcas.id_marca={$manual_brand_id}");

$brand_id = intval($brand['id_marca']);
$brand_name = (string)$brand['nombre'];

$qM = $db->query("SELECT id_model, nombre FROM act_modelo WHERE id_marca={$brand_id} ORDER BY nombre");
$modelos = [];
if ($qM) while($r = $db->fetch_array($qM)) {
  $mid = intval($r['id_model'] ?? 0);
  $mname = (string)($r['nombre'] ?? '');
  if ($mid && $mname !== '') $modelos[] = ["id"=>$mid,"nombre"=>$mname];
}
if (!$modelos) jerr("La marca {$brand_name} no tiene modelos en act_modelo");

$ins = 0; $upd = 0;

foreach($modelos as $m) {
  $model_id = intval($m['id']);
  $model_name = (string)$m['nombre'];
  $deep = buildDeepUrl($brand_name, $model_name);

  $deepEsc = method_exists($db,'escape') ? $db->escape($deep) : addslashes($deep);
  $bnEsc = method_exists($db,'escape') ? $db->escape($brand_name) : addslashes($brand_name);
  $mnEsc = method_exists($db,'escape') ? $db->escape($model_name) : addslashes($model_name);

  // existe?
  $qx = $db->query("SELECT id FROM apify_jobs WHERE brand_id={$brand_id} AND model_id={$model_id} AND deep_url='{$deepEsc}' LIMIT 1");
  $ex = $qx ? $db->fetch_array($qx) : null;

  $db->query("
    INSERT INTO apify_jobs (brand_id,brand_name,model_id,model_name,deep_url,estado,intentos,next_run_at,mensaje)
    VALUES ({$brand_id},'{$bnEsc}',{$model_id},'{$mnEsc}','{$deepEsc}','PENDIENTE',0,NULL,NULL)
    ON DUPLICATE KEY UPDATE
      brand_name=VALUES(brand_name),
      model_name=VALUES(model_name),
      deep_url=VALUES(deep_url),
      -- si estaba OK lo dejamos OK (para no recargar siempre)
      estado=IF(estado='OK','OK','PENDIENTE'),
      updated_at=NOW()
  ");

  if ($ex) $upd++; else $ins++;
}

jok([
  "brand_id"=>$brand_id,
  "brand_name"=>$brand_name,
  "modelos"=>count($modelos),
  "inserted"=>$ins,
  "updated"=>$upd
]);