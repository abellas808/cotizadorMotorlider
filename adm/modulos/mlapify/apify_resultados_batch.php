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

require_once(__DIR__ . "/../../config/config.inc.php");
require_once(__DIR__ . "/../../includes/database.php");
require_once(__DIR__ . "/../../includes/funciones.php");

session_start();
require_once(__DIR__ . "/../../includes/chk_login.php");

global $db;
if (!isset($db) || !$db) jerr("DB no inicializada (\$db).");

@($db->show_errors = false);
@($db->supress_errors = true);
@($db->suppress_errors = true);
@($db->debug = false);

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 300;
if ($limit <= 0) $limit = 300;
if ($limit > 1000) $limit = 1000;

$marcaId  = isset($_GET['marca_id']) ? intval($_GET['marca_id']) : 0;
$modeloId = isset($_GET['modelo_id']) ? intval($_GET['modelo_id']) : 0;

$sql = "
    SELECT
        p.id,
        p.corrida_id,
        p.marca_id,
        p.modelo_id,
        p.marca_txt AS marca,
        p.modelo_txt AS modelo,
        p.version,
        p.titulo,
        p.precio,
        p.moneda,
        p.anio,
        p.km,
        p.ubicacion,
        p.url,
        p.created_at
    FROM apify_publicaciones p
    INNER JOIN apify_corridas c
        ON c.corrida_id = p.corrida_id
    WHERE c.estado = 'ok'
";

if ($marcaId > 0) {
    $sql .= " AND p.marca_id = " . intval($marcaId) . " ";
}

if ($modeloId > 0) {
    $sql .= " AND p.modelo_id = " . intval($modeloId) . " ";
}

$sql .= "
    ORDER BY
        COALESCE(c.updated_at, c.created_at) DESC,
        p.created_at DESC,
        p.id DESC
    LIMIT " . intval($limit);

$q = $db->query($sql);
if (!$q) jerr("No se pudo consultar apify_publicaciones/apify_corridas.");

$rows = [];
while ($r = $db->fetch_array($q)) {
    $rows[] = [
        "id"         => intval($r['id'] ?? 0),
        "corrida_id" => (string)($r['corrida_id'] ?? ''),
        "marca_id"   => intval($r['marca_id'] ?? 0),
        "modelo_id"  => intval($r['modelo_id'] ?? 0),
        "marca"      => (string)($r['marca'] ?? ''),
        "modelo"     => (string)($r['modelo'] ?? ''),
        "version"    => (string)($r['version'] ?? ''),
        "titulo"     => (string)($r['titulo'] ?? ''),
        "precio"     => $r['precio'] !== null ? (float)$r['precio'] : null,
        "moneda"     => (string)($r['moneda'] ?? ''),
        "anio"       => $r['anio'] !== null ? intval($r['anio']) : null,
        "km"         => $r['km'] !== null ? intval($r['km']) : null,
        "ubicacion"  => (string)($r['ubicacion'] ?? ''),
        "url"        => (string)($r['url'] ?? ''),
        "created_at" => (string)($r['created_at'] ?? ''),
    ];
}

jok([
    "rows"  => $rows,
    "count" => count($rows),
    "limit" => $limit
]);