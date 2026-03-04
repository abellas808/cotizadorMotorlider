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

// ====== includes mínimos para DB del admin ======
require_once(__DIR__ . "/../../config/config.inc.php");
require_once(__DIR__ . "/../../includes/database.php");
require_once(__DIR__ . "/../../includes/funciones.php");

session_start();
require_once(__DIR__ . "/../../includes/chk_login.php");

global $db;
if (!isset($db) || !$db) jerr("DB no inicializada (\$db).");

// Intentar apagar “echo” de errores del wrapper (si existe)
@($db->show_errors = false);
@($db->supress_errors = true);
@($db->suppress_errors = true);
@($db->debug = false);

$esc = function($s) use (&$db) {
    if (method_exists($db, 'escape')) return $db->escape((string)$s);
    return addslashes((string)$s);
};

// helper: detectar schema real de una tabla
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

// ====== HARD CODE (por ahora FIJO) ======
$marca_id  = 58955;      // Chevrolet
$modelo_id = 123123;     // Onix (tu ID real)
$marca_txt  = "Chevrolet";
$modelo_txt = "Onix";

// ====== CONFIG: URL fija ======
$mlUrl = "https://autos.mercadolibre.com.uy/chevrolet/onix/usado/montevideo/";

// ====== CARGA .ENV ======
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
        )) {
            $v = substr($v, 1, -1);
        }

        putenv("$k=$v");
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
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

// aceptar / o ~
$actor = str_replace('/', '~', $actor);

// ====== HTTP helpers ======
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

// ✅ Espera a que el dataset tenga items (evita items=0 por timing)
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

        usleep(800000); // 0.8s
    }
}

// ====== corrida ======
$corridaId = date('YmdHis') . '_' . substr(md5(uniqid('', true)), 0, 8);
$now = date('Y-m-d H:i:s');

$params = [
    "ml_url" => $mlUrl,
    "marca_id" => $marca_id,
    "modelo_id" => $modelo_id,
    "marca_txt" => $marca_txt,
    "modelo_txt" => $modelo_txt,
];

$db->query("
    INSERT INTO apify_corridas (corrida_id, estado, mensaje, params_json, total_items, items_guardados, created_at, updated_at)
    VALUES (
        '".$esc($corridaId)."',
        'corriendo',
        'Lanzando actor...',
        '".$esc(json_encode($params, JSON_UNESCAPED_UNICODE))."',
        0,0,
        '".$now."','".$now."'
    )
");

// ====== run actor ======
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

if ($curlErr) {
    $db->query("UPDATE apify_corridas SET estado='error', mensaje='".$esc($curlErr)."', updated_at='".$now."' WHERE corrida_id='".$esc($corridaId)."'");
    jerr("cURL: ".$curlErr, ["corrida_id"=>$corridaId]);
}
if ($code >= 400) {
    $db->query("UPDATE apify_corridas SET estado='error', mensaje='".$esc("HTTP $code run")."', updated_at='".$now."' WHERE corrida_id='".$esc($corridaId)."'");
    jerr("HTTP $code al lanzar actor", ["corrida_id"=>$corridaId, "body"=>$resp]);
}

$run = json_decode($resp, true);
if (!is_array($run)) {
    $db->query("UPDATE apify_corridas SET estado='error', mensaje='JSON inválido run', updated_at='".$now."' WHERE corrida_id='".$esc($corridaId)."'");
    jerr("JSON inválido al lanzar actor", ["corrida_id"=>$corridaId, "raw"=>$resp]);
}

$data = $run['data'] ?? $run;

$runId = $data['id'] ?? null;
$runStatus = $data['status'] ?? null;
$datasetId = $data['defaultDatasetId'] ?? null;

if (!$datasetId) {
    $db->query("UPDATE apify_corridas SET estado='error', mensaje='No defaultDatasetId', updated_at='".$now."' WHERE corrida_id='".$esc($corridaId)."'");
    jerr("No vino defaultDatasetId", ["corrida_id"=>$corridaId, "run"=>$run]);
}

$db->query("
    UPDATE apify_corridas
    SET mensaje='".$esc("runId=$runId status=$runStatus dataset=$datasetId")."',
        updated_at='".$now."'
    WHERE corrida_id='".$esc($corridaId)."'
");

// ====== get dataset items (con espera) ======
$w = apify_wait_items($datasetId, $token, 100, 90);
if (!($w['ok'] ?? false)) {
    $db->query("UPDATE apify_corridas SET estado='error', mensaje='".$esc($w['error'] ?? 'Error dataset')."', updated_at='".$now."' WHERE corrida_id='".$esc($corridaId)."'");
    jerr($w['error'] ?? 'Error dataset', ["corrida_id"=>$corridaId, "body"=>$w['body'] ?? null]);
}
$items = $w['items'] ?? [];

// ====== localizar tabla act_version (schema) ======
$schemaVer = findSchemaForTable($db, 'act_version');
$tblVersion = $schemaVer ? "{$schemaVer}.act_version" : "act_version";

// ====== persist ======
$total = count($items);
$guardados = 0;

foreach ($items as $it) {
    if (!is_array($it)) continue;

    $mlid = (string)($it['id'] ?? '');
    $url  = (string)($it['url'] ?? '');
    if ($url !== '' && strpos($url, 'http') !== 0) $url = 'https://' . ltrim($url, '/');
    if ($url === '') continue;

    $titulo = '';
    $precio = null;
    $moneda = '';
    $anio = null;
    $km = null;
    $ubic = '';
    $vendedor = '';
    $es_oficial = 0;

    // 1) Parseo components (acá saco título)
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
                    $kmTxt = str_ireplace('km','', $kmTxt);
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

    // 2) Detectar versión DESPUÉS del título
    $version_id = null;
    $version = null;

    if ($marca_id && $modelo_id && $titulo !== '') {
        $titulo_upper = strtoupper($titulo);

        $qv = $db->query("
            SELECT id_version, nombre
            FROM {$tblVersion}
            WHERE id_marca = ".intval($marca_id)."
              AND id_modelo = ".intval($modelo_id)."
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

    // 3) Persistir
    $raw = json_encode($it, JSON_UNESCAPED_UNICODE);

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
            '".$esc($corridaId)."',
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
            ".intval($marca_id).",
            ".intval($modelo_id).",
            '".$esc(substr((string)$marca_txt,0,80))."',
            '".$esc(substr((string)$modelo_txt,0,120))."',
            ".($version_id ? intval($version_id) : "NULL").",
            ".($version ? "'".$esc(substr($version,0,120))."'" : "NULL").",
            '".$esc($raw)."',
            '".$now."'
        )
        ON DUPLICATE KEY UPDATE
            corrida_id  = VALUES(corrida_id),
            ml_id       = VALUES(ml_id),
            titulo      = VALUES(titulo),
            precio      = VALUES(precio),
            moneda      = VALUES(moneda),
            anio        = VALUES(anio),
            km          = VALUES(km),
            ubicacion   = VALUES(ubicacion),
            vendedor    = VALUES(vendedor),
            es_oficial  = VALUES(es_oficial),

            -- ✅ actualizar campos nuevos SIEMPRE
            marca_id    = VALUES(marca_id),
            modelo_id   = VALUES(modelo_id),
            marca_txt   = VALUES(marca_txt),
            modelo_txt  = VALUES(modelo_txt),
            version_id  = VALUES(version_id),
            version     = VALUES(version),

            raw_json    = VALUES(raw_json)
    ";

    $r = $db->query($sql);
    if ($r) $guardados++;
}

$db->query("
    UPDATE apify_corridas
    SET estado='ok',
        mensaje='OK',
        total_items=".intval($total).",
        items_guardados=".intval($guardados).",
        updated_at='".$now."'
    WHERE corrida_id='".$esc($corridaId)."'
");

jok([
    "corrida_id" => $corridaId,
    "ml_url" => $mlUrl,
    "total_items" => $total,
    "items_guardados" => $guardados
]);