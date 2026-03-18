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

// ===============================
// PARAMS: leer desde app_params
// ===============================
function get_app_param($db, $key, $default = null) {
    $k = method_exists($db, 'escape') ? $db->escape($key) : addslashes($key);
    $q = $db->query("SELECT param_value FROM app_params WHERE param_key='{$k}' LIMIT 1");
    if (!$q) return $default;
    $r = $db->fetch_array($q);
    if (!$r) return $default;
    $v = $r['param_value'] ?? null;
    if ($v === null || $v === '') return $default;
    return $v;
}

// ===============================
// URL builder (MercadoLibre)
// ===============================
function slugify($s) {
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
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = preg_replace('/-+/', '-', $s);
    $s = trim($s, '-');
    return $s;
}

function build_ml_url($marca_txt, $modelo_txt) {
    $b = slugify($marca_txt);
    $m = slugify($modelo_txt);
    return rtrim("https://autos.mercadolibre.com.uy/{$b}/{$m}/usado/montevideo/_NoIndex_True", '/');
}

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

// ✅ Espera a que el dataset tenga items
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
// 1) Leer marca paramétrica
// ===============================
$manual_brand_id = intval(get_app_param($db, 'manual_brand_id', 0));
$manual_limit_models = intval(get_app_param($db, 'manual_limit_models', 0));

if ($manual_brand_id <= 0) {
    jerr("No está configurado app_params.manual_brand_id (marcos2022_api).");
}

// Traer marca
$qB = $db->query("SELECT id_marca, nombre FROM act_marcas WHERE id_marca=".$manual_brand_id." LIMIT 1");
$brand = $qB ? $db->fetch_array($qB) : null;
if (!$brand) jerr("No existe act_marcas.id_marca=".$manual_brand_id);

$marca_id  = intval($brand['id_marca']);
$marca_txt = (string)$brand['nombre'];

// Traer modelos
$sqlM = "SELECT id_model, nombre FROM act_modelo WHERE id_marca=".$marca_id." ORDER BY nombre";
if ($manual_limit_models > 0) $sqlM .= " LIMIT ".$manual_limit_models;

$qM = $db->query($sqlM);
$modelos = [];
if ($qM) {
    while ($rm = $db->fetch_array($qM)) {
        $mid = intval($rm['id_model'] ?? 0);
        $mname = (string)($rm['nombre'] ?? '');
        if ($mid > 0 && $mname !== '') $modelos[] = ["id"=>$mid, "nombre"=>$mname];
    }
}

if (!$modelos) jerr("La marca {$marca_txt} (id={$marca_id}) no tiene modelos en act_modelo.");

// ====== corrida global (una sola para toda la marca) ======
$corridaId = date('YmdHis') . '_' . substr(md5(uniqid('', true)), 0, 8);
$now = date('Y-m-d H:i:s');

$params = [
    "modo" => "manual_por_parametro",
    "manual_brand_id" => $manual_brand_id,
    "manual_limit_models" => $manual_limit_models,
    "marca_id" => $marca_id,
    "marca_txt" => $marca_txt,
    "modelos_total" => count($modelos),
];

$db->query("
    INSERT INTO apify_corridas (corrida_id, estado, mensaje, params_json, total_items, items_guardados, created_at, updated_at)
    VALUES (
        '".(method_exists($db,'escape')?$db->escape($corridaId):addslashes($corridaId))."',
        'corriendo',
        'Iniciando búsqueda por modelos...',
        '".(method_exists($db,'escape')?$db->escape(json_encode($params, JSON_UNESCAPED_UNICODE)):addslashes(json_encode($params, JSON_UNESCAPED_UNICODE)))."',
        0,0,
        '".$now."','".$now."'
    )
");

// ====== localizar tabla act_version (schema) ======
$schemaVer = findSchemaForTable($db, 'act_version');
$tblVersion = $schemaVer ? "{$schemaVer}.act_version" : "act_version";

// Acumuladores globales
$total_global = 0;
$guardados_global = 0;
$errores = [];
$modelos_ok = 0;
$modelos_error = 0;

// ===============================
// 2) Foreach modelos: correr Apify
// ===============================
foreach ($modelos as $m) {
    $modelo_id = intval($m['id']);
    $modelo_txt = (string)$m['nombre'];

    $mlUrl = build_ml_url($marca_txt, $modelo_txt);

    $now = date('Y-m-d H:i:s');
    $db->query("
        UPDATE apify_corridas
        SET mensaje='".(method_exists($db,'escape')?$db->escape("Corriendo: {$marca_txt} {$modelo_txt}"):addslashes("Corriendo: {$marca_txt} {$modelo_txt}"))."',
            updated_at='".$now."'
        WHERE corrida_id='".(method_exists($db,'escape')?$db->escape($corridaId):addslashes($corridaId))."'
    ");

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
        $modelos_error++;
        $errores[] = [
            "modelo_id"=>$modelo_id,
            "modelo_txt"=>$modelo_txt,
            "error"=>$curlErr ? ("cURL: ".$curlErr) : ("HTTP ".$code),
            "body"=>($code >= 400 ? $resp : null),
            "ml_url"=>$mlUrl,
        ];
        usleep(250000);
        continue;
    }

    $run = json_decode($resp, true);
    if (!is_array($run)) {
        $modelos_error++;
        $errores[] = ["modelo_id"=>$modelo_id, "modelo_txt"=>$modelo_txt, "error"=>"JSON inválido run", "ml_url"=>$mlUrl];
        usleep(250000);
        continue;
    }

    $data = $run['data'] ?? $run;
    $datasetId = $data['defaultDatasetId'] ?? null;

    if (!$datasetId) {
        $modelos_error++;
        $errores[] = ["modelo_id"=>$modelo_id, "modelo_txt"=>$modelo_txt, "error"=>"No defaultDatasetId", "ml_url"=>$mlUrl, "run"=>$run];
        usleep(250000);
        continue;
    }

    $w = apify_wait_items($datasetId, $token, 100, 90);
    if (!($w['ok'] ?? false)) {
        $modelos_error++;
        $errores[] = ["modelo_id"=>$modelo_id, "modelo_txt"=>$modelo_txt, "error"=>($w['error'] ?? 'Error dataset'), "ml_url"=>$mlUrl];
        usleep(250000);
        continue;
    }

    $items = $w['items'] ?? [];
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

        $raw = json_encode($it, JSON_UNESCAPED_UNICODE);
        $now = date('Y-m-d H:i:s');

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
                '".(method_exists($db,'escape')?$db->escape($corridaId):addslashes($corridaId))."',
                'ml',
                '".(method_exists($db,'escape')?$db->escape($mlid):addslashes($mlid))."',
                '".(method_exists($db,'escape')?$db->escape(substr($titulo,0,255)):addslashes(substr($titulo,0,255)))."',
                ".($precio === null || $precio === '' ? "NULL" : floatval($precio)).",
                '".(method_exists($db,'escape')?$db->escape(substr((string)$moneda,0,10)):addslashes(substr((string)$moneda,0,10)))."',
                ".($anio === null ? "NULL" : intval($anio)).",
                ".($km === null ? "NULL" : intval($km)).",
                '".(method_exists($db,'escape')?$db->escape(substr($ubic,0,180)):addslashes(substr($ubic,0,180)))."',
                '".(method_exists($db,'escape')?$db->escape(substr($url,0,500)):addslashes(substr($url,0,500)))."',
                '".(method_exists($db,'escape')?$db->escape(substr($vendedor,0,180)):addslashes(substr($vendedor,0,180)))."',
                ".intval($es_oficial).",
                ".intval($marca_id).",
                ".intval($modelo_id).",
                '".(method_exists($db,'escape')?$db->escape(substr((string)$marca_txt,0,80)):addslashes(substr((string)$marca_txt,0,80)))."',
                '".(method_exists($db,'escape')?$db->escape(substr((string)$modelo_txt,0,120)):addslashes(substr((string)$modelo_txt,0,120)))."',
                ".($version_id ? intval($version_id) : "NULL").",
                ".($version ? "'".(method_exists($db,'escape')?$db->escape(substr($version,0,120)):addslashes(substr($version,0,120)))."'" : "NULL").",
                '".(method_exists($db,'escape')?$db->escape($raw):addslashes($raw))."',
                '".$now."'
            )
        ";

        $r = $db->query($sql);
        if ($r) $guardados++;
    }

    $total_global += $total;
    $guardados_global += $guardados;
    $modelos_ok++;

    usleep(300000);
}

// ====== cerrar corrida ======
$now = date('Y-m-d H:i:s');
$msgFinal = "OK marca={$marca_txt} modelos_ok={$modelos_ok} modelos_error={$modelos_error}";
if ($modelos_error > 0) $msgFinal .= " (ver errores en respuesta)";

$db->query("
    UPDATE apify_corridas
    SET estado='ok',
        mensaje='".(method_exists($db,'escape')?$db->escape($msgFinal):addslashes($msgFinal))."',
        total_items=".intval($total_global).",
        items_guardados=".intval($guardados_global).",
        updated_at='".$now."'
    WHERE corrida_id='".(method_exists($db,'escape')?$db->escape($corridaId):addslashes($corridaId))."'
");

jok([
    "corrida_id" => $corridaId,
    "modo" => "manual_brand_id",
    "manual_brand_id" => $manual_brand_id,
    "manual_limit_models" => $manual_limit_models,
    "marca_id" => $marca_id,
    "marca_txt" => $marca_txt,
    "modelos_total" => count($modelos),
    "modelos_ok" => $modelos_ok,
    "modelos_error" => $modelos_error,
    "total_items" => $total_global,
    "items_guardados" => $guardados_global,
    "errores" => $errores
]);