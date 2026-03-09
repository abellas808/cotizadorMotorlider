<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

ob_start();
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level()) { ob_end_clean(); }
        http_response_code(500);
        echo json_encode([
            "ok" => false,
            "fatal" => true,
            "mensaje" => "FATAL: ".$e['message'],
            "file" => $e['file'],
            "line" => $e['line'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
});

function jok($a = []) { echo json_encode(array_merge(["ok"=>true], $a), JSON_UNESCAPED_UNICODE); exit; }
function jerr($m, $a = []) { http_response_code(500); echo json_encode(array_merge(["ok"=>false,"mensaje"=>$m], $a), JSON_UNESCAPED_UNICODE); exit; }

if (!function_exists('curl_init')) jerr("PHP sin cURL (curl_init no existe).");

// ====== DB del admin (igual que planner/run_ml) ======
require_once(__DIR__ . "/../../config/config.inc.php");
require_once(__DIR__ . "/../../includes/database.php");
require_once(__DIR__ . "/../../includes/funciones.php");

global $db;
if (!isset($db) || !$db) jerr("DB no inicializada (\$db).");

$esc = function($s) use (&$db) {
    if (method_exists($db, 'escape')) return $db->escape((string)$s);
    return addslashes((string)$s);
};

// ===============================
// Helpers ENV (.env de apicotizador)
// ===============================
function load_env_file($path) {
    if (!is_readable($path)) return false;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if ($v !== '' && (
            ($v[0] === '"' && substr($v, -1) === '"') ||
            ($v[0] === "'" && substr($v, -1) === "'")
        )) $v = substr($v, 1, -1);

        if (getenv($k) === false || getenv($k) === '') {
            putenv("$k=$v");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }
    return true;
}

// ✅ Ruta: public_html/apicotizador/.env
$envPath = dirname(__DIR__, 3) . "/apicotizador/.env";
load_env_file($envPath);

// ====== Apify env ======
$token = trim((string)(getenv('APIFY_TOKEN') ?: ($_ENV['APIFY_TOKEN'] ?? '')));
$actor = trim((string)(getenv('APIFY_ACTOR_ID') ?: ($_ENV['APIFY_ACTOR_ID'] ?? '')));
if ($token === '') jerr("Falta APIFY_TOKEN (envPath=".$envPath.")");
if ($actor === '') jerr("Falta APIFY_ACTOR_ID (envPath=".$envPath.")");
$actor = str_replace('/', '~', $actor);

// ===============================
// HTTP helpers
// ===============================
function apify_post_json($url, $payload) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($ch, CURLOPT_TIMEOUT, 240);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
    $resp = curl_exec($ch);
    $err  = curl_errno($ch) ? curl_error($ch) : null;
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $resp, $err];
}

function apify_get($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    $resp = curl_exec($ch);
    $err  = curl_errno($ch) ? curl_error($ch) : null;
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $resp, $err];
}

function apify_wait_items($datasetId, $token, $limit = 100, $maxSeconds = 90) {
    $start = time();
    while (true) {
        $itemsUrl = "https://api.apify.com/v2/datasets/".rawurlencode($datasetId)."/items?token=".rawurlencode($token)."&clean=true&limit=".$limit;
        [$code, $resp, $err] = apify_get($itemsUrl);

        if ($err) return ["ok"=>false, "error"=>"cURL dataset: ".$err, "items"=>[]];
        if ($code >= 400) return ["ok"=>false, "error"=>"HTTP $code dataset", "body"=>$resp, "items"=>[]];

        $items = json_decode($resp, true);
        if (!is_array($items)) $items = [];

        if (count($items) > 0) return ["ok"=>true, "items"=>$items, "count"=>count($items)];

        if ((time() - $start) >= $maxSeconds) return ["ok"=>true, "items"=>[], "count"=>0, "timeout"=>true];

        usleep(800000);
    }
}

// ===============================
// 1) Tomar 1 job (PENDIENTE/REINTENTAR)
// ===============================
$now = date('Y-m-d H:i:s');

// OJO: esto reduce mucho el riesgo de que dos runners tomen el mismo job
$db->query("START TRANSACTION");

$qJob = $db->query("
    SELECT *
    FROM apify_jobs
    WHERE estado IN ('PENDIENTE','REINTENTAR')
      AND (next_run_at IS NULL OR next_run_at <= NOW())
    ORDER BY id ASC
    LIMIT 1
    FOR UPDATE
");
$job = $qJob ? $db->fetch_array($qJob) : null;

if (!$job) {
    $db->query("COMMIT");
    jok(["mensaje"=>"No hay jobs pendientes", "job"=>null]);
}

$jobId = intval($job['id']);
$intentos = intval($job['intentos'] ?? 0) + 1;

$db->query("
    UPDATE apify_jobs
    SET estado='CORRIENDO',
        intentos={$intentos},
        started_at=NOW(),
        mensaje='Ejecutando...',
        next_run_at=NULL
    WHERE id={$jobId}
");
$db->query("COMMIT");

// ===============================
// 2) Ejecutar Apify para este job
// ===============================
$brand_id   = intval($job['brand_id']);
$brand_name = (string)($job['brand_name'] ?? '');
$model_id   = intval($job['model_id']);
$model_name = (string)($job['model_name'] ?? '');
$deep_url   = (string)($job['deep_url'] ?? '');

if ($deep_url === '') {
    // job inválido
    $db->query("
        UPDATE apify_jobs
        SET estado='ERROR',
            finished_at=NOW(),
            mensaje='Job sin deep_url'
        WHERE id={$jobId}
    ");
    jerr("Job sin deep_url", ["job_id"=>$jobId]);
}

// construir URL final a scrapear
$mlUrl = trim((string)$deep_url);
if (stripos($mlUrl, 'http') !== 0) {
    $mlUrl = "https://autos.mercadolibre.com.uy/" . ltrim($mlUrl, '/');
}
$mlUrl = rtrim($mlUrl, '/');

// input del actor (usa urls, como tu run_ml)
$input = [
    "country_code" => "uy",
    "ignore_url_failures" => false,
    "max_items_per_url" => 100,
    "max_retries_per_url" => 5,
    "proxy" => [
        "useApifyProxy" => true,
        "apifyProxyGroups" => ["RESIDENTIAL"],
        "apifyProxyCountry" => "US"
    ],
    "urls" => [$mlUrl]
];

$runUrl = "https://api.apify.com/v2/acts/".rawurlencode($actor)."/runs?token=".rawurlencode($token)."&wait=240";
[$code, $resp, $curlErr] = apify_post_json($runUrl, $input);

if ($curlErr || $code >= 400) {
    $msg = $curlErr ? ("cURL: ".$curlErr) : ("HTTP ".$code." al lanzar actor");
    // backoff: 10min * intentos (máx 60min)
    $mins = min(60, 10 * $intentos);
    $estado = ($intentos < 5) ? "REINTENTAR" : "ERROR";

    $db->query("
        UPDATE apify_jobs
        SET estado='{$estado}',
            finished_at=NOW(),
            next_run_at = ".(($estado==='REINTENTAR') ? "DATE_ADD(NOW(), INTERVAL {$mins} MINUTE)" : "NULL").",
            mensaje='".$esc($msg)."'
        WHERE id={$jobId}
    ");

    jerr($msg, ["job_id"=>$jobId, "body"=>$resp]);
}

$run = json_decode($resp, true);
$data = is_array($run) ? ($run['data'] ?? $run) : null;

$runId = is_array($data) ? ($data['id'] ?? null) : null;
$datasetId = is_array($data) ? ($data['defaultDatasetId'] ?? null) : null;

if (!$datasetId) {
    $estado = ($intentos < 5) ? "REINTENTAR" : "ERROR";
    $mins = min(60, 10 * $intentos);
    $db->query("
        UPDATE apify_jobs
        SET estado='{$estado}',
            finished_at=NOW(),
            next_run_at = ".(($estado==='REINTENTAR') ? "DATE_ADD(NOW(), INTERVAL {$mins} MINUTE)" : "NULL").",
            mensaje='No defaultDatasetId'
        WHERE id={$jobId}
    ");
    jerr("No vino defaultDatasetId", ["job_id"=>$jobId, "run"=>$run]);
}

// guardar runId en job
if ($runId) {
    $db->query("UPDATE apify_jobs SET apify_run_id='".$esc($runId)."' WHERE id={$jobId}");
}

// ===============================
// 3) Leer items dataset y persistir
// ===============================
$w = apify_wait_items($datasetId, $token, 100, 90);
if (!($w['ok'] ?? false)) {
    $estado = ($intentos < 5) ? "REINTENTAR" : "ERROR";
    $mins = min(60, 10 * $intentos);

    $db->query("
        UPDATE apify_jobs
        SET estado='{$estado}',
            finished_at=NOW(),
            next_run_at = ".(($estado==='REINTENTAR') ? "DATE_ADD(NOW(), INTERVAL {$mins} MINUTE)" : "NULL").",
            mensaje='".$esc($w['error'] ?? 'Error dataset')."'
        WHERE id={$jobId}
    ");

    jerr($w['error'] ?? 'Error dataset', ["job_id"=>$jobId, "body"=>$w['body'] ?? null]);
}

$items = $w['items'] ?? [];
$total = count($items);
$guardados = 0;

// schema act_version (si existe)
function findSchemaForTable($db, $tableName) {
    $t = method_exists($db, 'escape') ? $db->escape($tableName) : addslashes($tableName);
    $q = $db->query("
        SELECT TABLE_SCHEMA
        FROM information_schema.TABLES
        WHERE TABLE_NAME = '{$t}'
        ORDER BY TABLE_SCHEMA
        LIMIT 1
    ");
    if (!$q) return null;
    $r = $db->fetch_array($q);
    return $r ? ($r['TABLE_SCHEMA'] ?? null) : null;
}
$schemaVer = findSchemaForTable($db, 'act_version');
$tblVersion = $schemaVer ? "{$schemaVer}.act_version" : "act_version";

foreach ($items as $it) {
    if (!is_array($it)) continue;

    $mlid = (string)($it['id'] ?? '');
    $url  = (string)($it['url'] ?? '');
    if ($url !== '' && stripos($url, 'http') !== 0) $url = 'https://' . ltrim($url, '/');
    if ($url === '') continue;

    $titulo = '';
    $precio = null;
    $moneda = '';
    $anio = null;
    $km = null;
    $ubic = '';
    $vendedor = '';
    $es_oficial = 0;

    $components = $it['components'] ?? [];
    if (is_array($components)) {
        foreach ($components as $c) {
            if (!is_array($c)) continue;
            $type = $c['type'] ?? '';

            if ($type === 'title') {
                $titulo = (string)($c['title']['text'] ?? '');
            } elseif ($type === 'price') {
                $precio = $c['price']['current_price']['value'] ?? null;
                $moneda = (string)($c['price']['current_price']['currency'] ?? '');
            } elseif ($type === 'attributes_list') {
                $texts = $c['attributes_list']['texts'] ?? [];
                if (is_array($texts) && count($texts) >= 2) {
                    $anio = is_numeric($texts[0]) ? intval($texts[0]) : null;
                    $kmTxt = (string)$texts[1];
                    $kmTxt = str_ireplace('km', '', $kmTxt);
                    $kmTxt = str_replace(['.', ' '], '', $kmTxt);
                    $km = is_numeric($kmTxt) ? intval($kmTxt) : null;
                }
            } elseif ($type === 'location') {
                $ubic = (string)($c['location']['text'] ?? '');
            } elseif ($type === 'seller') {
                $vend = (string)($c['seller']['text'] ?? '');
                $vendedor = trim(str_replace('{icon_cockade}', '', $vend));
                $vals = $c['seller']['values'] ?? [];
                if (is_array($vals)) {
                    foreach ($vals as $v) {
                        if (($v['key'] ?? '') === 'icon_cockade') { $es_oficial = 1; break; }
                    }
                }
            }
        }
    }

    // versión (si hay tabla y campos)
    $version_id = null;
    $version = null;

    if ($brand_id && $model_id && $titulo !== '') {
        $titulo_upper = strtoupper($titulo);

        // OJO: act_version podría usar id_model o id_modelo según tu schema.
        // Probamos primero con id_model (tu caso), y si falla, cae al catch.
        $qv = $db->query("
            SELECT id_version, nombre
            FROM {$tblVersion}
            WHERE id_marca = ".intval($brand_id)."
              AND id_modelo = ".intval($model_id)."
              AND activo = 1
            ORDER BY LENGTH(nombre) DESC
        ");

        if ($qv) {
            while ($rv = $db->fetch_array($qv)) {
                $nomV = trim((string)($rv['nombre'] ?? ''));
                if ($nomV === '') continue;
                if (strpos($titulo_upper, strtoupper($nomV)) !== false) {
                    $version_id = intval($rv['id_version']);
                    $version = $nomV;
                    break;
                }
            }
        }
    }

    $raw = json_encode($it, JSON_UNESCAPED_UNICODE);
    $created_at = date('Y-m-d H:i:s');

    $sql = "
        INSERT INTO apify_publicaciones
        (
            corrida_id, fuente, ml_id, titulo, precio, moneda, anio, km, ubicacion, url,
            vendedor, es_oficial,
            marca_id, modelo_id, marca_txt, modelo_txt,
            version_id, version,
            raw_json, created_at
        )
        VALUES
        (
            '".$esc("job_".$jobId)."',
            'ml',
            '".$esc($mlid)."',
            '".$esc(substr($titulo,0,255))."',
            ".($precio === null || $precio === '' ? "NULL" : floatval($precio)).",
            '".$esc(substr((string)$moneda,0,10))."',
            ".($anio === null ? "NULL" : intval($anio)).",
            ".($km === null ? "NULL" : intval($km)).",
            '".$esc(substr($ubic,0,180))."',
            '".$esc(substr($url,0,500))."',
            '".$esc(substr($vendedor,0,180))."',
            ".intval($es_oficial).",
            ".intval($brand_id).",
            ".intval($model_id).",
            '".$esc(substr($brand_name,0,80))."',
            '".$esc(substr($model_name,0,120))."',
            ".($version_id ? intval($version_id) : "NULL").",
            ".($version ? "'".$esc(substr($version,0,120))."'" : "NULL").",
            '".$esc($raw)."',
            '".$created_at."'
        )
        ON DUPLICATE KEY UPDATE
            ml_id=VALUES(ml_id),
            titulo=VALUES(titulo),
            precio=VALUES(precio),
            moneda=VALUES(moneda),
            anio=VALUES(anio),
            km=VALUES(km),
            ubicacion=VALUES(ubicacion),
            vendedor=VALUES(vendedor),
            es_oficial=VALUES(es_oficial),
            marca_id=VALUES(marca_id),
            modelo_id=VALUES(modelo_id),
            marca_txt=VALUES(marca_txt),
            modelo_txt=VALUES(modelo_txt),
            version_id=VALUES(version_id),
            version=VALUES(version),
            raw_json=VALUES(raw_json)
    ";

    $r = $db->query($sql);
    if ($r) $guardados++;
}

// ===============================
// 4) Marcar OK
// ===============================
$db->query("
    UPDATE apify_jobs
    SET estado='OK',
        finished_at=NOW(),
        mensaje='".$esc("OK items={$total} guardados={$guardados}")."'
    WHERE id={$jobId}
");

jok([
    "job_id" => $jobId,
    "brand_id" => $brand_id,
    "brand_name" => $brand_name,
    "model_id" => $model_id,
    "model_name" => $model_name,
    "ml_url" => $mlUrl,
    "apify_run_id" => $runId,
    "items_total" => $total,
    "items_guardados" => $guardados,
    "estado" => "OK"
]);