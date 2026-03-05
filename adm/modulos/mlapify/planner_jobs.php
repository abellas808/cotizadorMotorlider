<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0');
error_reporting(E_ALL);

function jok($a=[]) { echo json_encode(array_merge(["ok"=>true],$a), JSON_UNESCAPED_UNICODE); exit; }
function jerr($m,$a=[]) { http_response_code(500); echo json_encode(array_merge(["ok"=>false,"mensaje"=>$m],$a), JSON_UNESCAPED_UNICODE); exit; }

// =============================
// DB (tu ruta real)
// =============================
require_once __DIR__ . '/../../../apicotizador/src/db.php';
$db = Database::getInstance();

// =============================
// Params
// =============================
$brandId     = isset($_GET['brand_id']) ? (int)$_GET['brand_id'] : 0;
$modelId     = isset($_GET['model_id']) ? (int)$_GET['model_id'] : 0;
$limitModels = isset($_GET['limit_models']) ? max(0, (int)$_GET['limit_models']) : 0;

$dry   = (isset($_GET['dry']) && $_GET['dry'] == '1');
$force = (isset($_GET['force']) && $_GET['force'] == '1');

// =============================
// Helpers
// =============================
function slugify($s){
    $s = trim((string)$s);
    $map = [
        'á'=>'a','à'=>'a','ä'=>'a','â'=>'a','Á'=>'a','À'=>'a','Ä'=>'a','Â'=>'a',
        'é'=>'e','è'=>'e','ë'=>'e','ê'=>'e','É'=>'e','È'=>'e','Ë'=>'e','Ê'=>'e',
        'í'=>'i','ì'=>'i','ï'=>'i','î'=>'i','Í'=>'i','Ì'=>'i','Ï'=>'i','Î'=>'i',
        'ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','Ó'=>'o','Ò'=>'o','Ö'=>'o','Ô'=>'o',
        'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u','Ú'=>'u','Ù'=>'u','Ü'=>'u','Û'=>'u',
        'ñ'=>'n','Ñ'=>'n',
    ];
    $s = strtr($s, $map);
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/','-', $s);
    $s = preg_replace('/-+/','-', $s);
    return trim($s,'-');
}

// tu formato definido
function buildDeepUrl($marcaNombre,$modeloNombre){
    $b = slugify($marcaNombre);
    $m = slugify($modeloNombre);
    return "{$b}/{$m}-usado-montevideo_NoIndex_True";
}

function esc($db, $s){
    // tu Database tiene escape? Si no, fallback simple
    if (method_exists($db, 'escape')) return $db->escape($s);
    return addslashes($s);
}

// =============================
// 1) Buscar marcas (con filtro opcional)
// =============================
$sqlMarcas = "SELECT * FROM act_marcas";
if ($brandId > 0) $sqlMarcas .= " WHERE id_marca = {$brandId}";
$sqlMarcas .= " ORDER BY nombre";

$qM = $db->mysqlQuery($sqlMarcas);

$marcas = [];
while ($r = $qM->fetch_assoc()) {
    if (!isset($r['id_marca']) || !isset($r['nombre'])) continue;
    $marcas[] = $r;
}

if (!$marcas) {
    jerr("No se encontraron marcas (filtro brand_id={$brandId})");
}

// =============================
// 2) Por cada marca, buscar modelos (con filtro opcional)
// =============================
$inserted = 0;
$updated  = 0;
$skipped  = 0;
$totalCombos = 0;
$ejemplos = [];

foreach ($marcas as $marca) {

    $marcaId     = (int)$marca['id_marca'];
    $marcaNombre = (string)$marca['nombre'];

    $sqlModelos = "
        SELECT * 
        FROM act_modelo
        WHERE id_marca = {$marcaId}
    ";

    if ($modelId > 0) {
        $sqlModelos .= " AND id_modelo = {$modelId} ";
    }

    $sqlModelos .= " ORDER BY nombre ";

    if ($limitModels > 0) {
        $sqlModelos .= " LIMIT {$limitModels} ";
    }

    $qMo = $db->mysqlQuery($sqlModelos);

    $cantModelos = 0;

    while ($modelo = $qMo->fetch_assoc()) {
        $cantModelos++;

        $modeloId     = isset($modelo['id_modelo']) ? (int)$modelo['id_modelo'] : 0;
        $modeloNombre = isset($modelo['nombre']) ? (string)$modelo['nombre'] : '';

        if (!$modeloId || !$modeloNombre) continue;

        $totalCombos++;

        $deep = buildDeepUrl($marcaNombre, $modeloNombre);

        if (count($ejemplos) < 8) {
            $ejemplos[] = [
                "brand_id"=>$marcaId,
                "brand"=>$marcaNombre,
                "model_id"=>$modeloId,
                "model"=>$modeloNombre,
                "deep_url"=>$deep
            ];
        }

        if ($dry) continue;

        $deepEsc  = esc($db, $deep);
        $bnameEsc = esc($db, $marcaNombre);
        $mnameEsc = esc($db, $modeloNombre);

        // Si existe y está OK, lo dejo quieto salvo force=1
        $qEx = $db->mysqlQuery("
            SELECT id, estado
            FROM apify_jobs
            WHERE brand_id={$marcaId} AND model_id={$modeloId} AND deep_url='{$deepEsc}'
            LIMIT 1
        ");
        $ex = $qEx ? $qEx->fetch_assoc() : null;

        if ($ex && ($ex['estado'] ?? '') === 'OK' && !$force) {
            // Igual actualizo nombres por si cambiaron
            $db->mysqlNonQuery("
                UPDATE apify_jobs
                SET brand_name='{$bnameEsc}', model_name='{$mnameEsc}'
                WHERE id=".(int)$ex['id']."
            ");
            $skipped++;
            continue;
        }

        // Upsert: crea o resetea a PENDIENTE
        // Si estaba y no era OK, también lo dejamos PENDIENTE y reseteamos intentos
        $sqlUpsert = "
            INSERT INTO apify_jobs
                (brand_id, brand_name, model_id, model_name, deep_url, estado, intentos, next_run_at, mensaje)
            VALUES
                ({$marcaId}, '{$bnameEsc}', {$modeloId}, '{$mnameEsc}', '{$deepEsc}', 'PENDIENTE', 0, NULL, NULL)
            ON DUPLICATE KEY UPDATE
                brand_name=VALUES(brand_name),
                model_name=VALUES(model_name),
                estado='PENDIENTE',
                intentos=0,
                next_run_at=NULL,
                mensaje=NULL,
                updated_at=NOW()
        ";

        $db->mysqlNonQuery($sqlUpsert);

        if ($ex) $updated++; else $inserted++;
    }
}

// =============================
// Response
// =============================
jok([
    "dry" => $dry,
    "force" => $force,
    "filtros" => [
        "brand_id" => $brandId,
        "model_id" => $modelId,
        "limit_models" => $limitModels
    ],
    "marcas_procesadas" => count($marcas),
    "combos_marca_modelo" => $totalCombos,
    "inserted" => $inserted,
    "updated" => $updated,
    "skipped_ok" => $skipped,
    "ejemplos" => $ejemplos
]);