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

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 3000;
if ($limit <= 0) $limit = 3000;
if ($limit > 10000) $limit = 10000;

$marcaId  = isset($_GET['marca_id']) ? intval($_GET['marca_id']) : 0;
$modeloId = isset($_GET['modelo_id']) ? intval($_GET['modelo_id']) : 0;

/*
 * Reglas:
 * - Sin filtros: solo marcas/modelos con prioridad=1
 * - Con marca: filtra esa marca
 * - Con modelo: filtra ese modelo
 * - Siempre: solo la ÚLTIMA corrida OK por combinación marca_id + modelo_id
 */

$wherePrioridad = "";
$whereOuter = "";
$whereUlt = "";

if ($marcaId > 0) {
    $wherePrioridad .= " AND am.id_marca = " . intval($marcaId) . " ";
    $whereOuter     .= " AND p.marca_id = " . intval($marcaId) . " ";
    $whereUlt       .= " AND p2.marca_id = " . intval($marcaId) . " ";
}

if ($modeloId > 0) {
    $wherePrioridad .= " AND amo.id_model = " . intval($modeloId) . " ";
    $whereOuter     .= " AND p.modelo_id = " . intval($modeloId) . " ";
    $whereUlt       .= " AND p2.modelo_id = " . intval($modeloId) . " ";
}

/*
 * Subconsulta "priorizadas":
 * devuelve combinaciones válidas marca/modelo según prioridad=1
 * y opcionalmente filtradas por marca/modelo.
 */
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
    INNER JOIN (
        SELECT
            pr.id_marca AS marca_id,
            pr.id_model AS modelo_id,
            MAX(ult.id_corrida_db) AS last_corrida_db_id
        FROM (
            SELECT
                am.id_marca,
                amo.id_model
            FROM act_marcas am
            INNER JOIN act_modelo amo
                ON amo.id_marca = am.id_marca
            WHERE am.prioridad = 1
              AND amo.prioridad = 1
              {$wherePrioridad}
            GROUP BY am.id_marca, amo.id_model
        ) pr
        INNER JOIN (
            SELECT
                p2.marca_id,
                p2.modelo_id,
                c2.id AS id_corrida_db
            FROM apify_publicaciones p2
            INNER JOIN apify_corridas c2
                ON c2.corrida_id = p2.corrida_id
            WHERE c2.estado = 'ok'
              {$whereUlt}
            GROUP BY
                p2.marca_id,
                p2.modelo_id,
                c2.id
        ) ult
            ON ult.marca_id = pr.id_marca
           AND ult.modelo_id = pr.id_model
        GROUP BY
            pr.id_marca,
            pr.id_model
    ) x
        ON x.marca_id = p.marca_id
       AND x.modelo_id = p.modelo_id
       AND x.last_corrida_db_id = c.id
    WHERE c.estado = 'ok'
      {$whereOuter}
    ORDER BY
        p.marca_txt ASC,
        p.modelo_txt ASC,
        p.id DESC
    LIMIT " . intval($limit);

$q = $db->query($sql);
if (!$q) {
    jerr("No se pudo consultar apify_publicaciones/apify_corridas.", [
        "sql" => $sql
    ]);
}

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
    "rows"      => $rows,
    "count"     => count($rows),
    "limit"     => $limit,
    "marca_id"  => $marcaId,
    "modelo_id" => $modeloId
]);