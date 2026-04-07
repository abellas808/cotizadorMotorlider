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
            "mensaje" => "FATAL: " . $e['message'],
            "file" => $e['file'],
            "line" => $e['line'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
});

function jok($a = []) {
    echo json_encode(array_merge(["ok" => true], $a), JSON_UNESCAPED_UNICODE);
    exit;
}

function jerr($m, $a = []) {
    http_response_code(500);
    echo json_encode(array_merge(["ok" => false, "mensaje" => $m], $a), JSON_UNESCAPED_UNICODE);
    exit;
}

function db_num_rows_safe($db, $res) {
    if (method_exists($db, 'num_rows')) {
        return $db->num_rows($res);
    }
    if (method_exists($db, 'numrows')) {
        return $db->numrows($res);
    }
    if (method_exists($db, 'NumRows')) {
        return $db->NumRows($res);
    }
    if (is_object($res) && isset($res->num_rows)) {
        return (int)$res->num_rows;
    }
    if (function_exists('mysqli_num_rows')) {
        return @mysqli_num_rows($res);
    }
    return 0;
}

// ====== DB del admin ======
require_once(__DIR__ . "/../../config/config.inc.php");
require_once(__DIR__ . "/../../includes/database.php");
require_once(__DIR__ . "/../../includes/funciones.php");

global $db;
if (!isset($db) || !$db) {
    jerr("DB no inicializada (\$db).");
}

$esc = function($s) use (&$db) {
    if (method_exists($db, 'escape')) return $db->escape((string)$s);
    return addslashes((string)$s);
};

date_default_timezone_set('America/Montevideo');
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
$sql = "
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
";

$q = $db->query($sql);
if (!$q) {
    jerr("Error consultando marcas/modelos", [
        "sql" => $sql,
        "db_error" => $db->error ?? null
    ]);
}

$jobs_procesados   = 0;
$jobs_creados      = 0;
$jobs_existentes   = 0;
$jobs_actualizados = 0;
$errores           = [];
$detalle           = [];

while ($row = $db->fetch_array($q)) {
    $jobs_procesados++;

    $marca_id      = intval($row['marca_id']);
    $marca_nombre  = trim((string)($row['marca_nombre'] ?? ''));
    $modelo_id     = intval($row['modelo_id']);
    $modelo_nombre = trim((string)($row['modelo_nombre'] ?? ''));

    if (!$marca_id || !$modelo_id) {
        $errores[] = [
            'tipo' => 'ids_invalidos',
            'marca_id' => $marca_id,
            'marca' => $marca_nombre,
            'modelo_id' => $modelo_id,
            'modelo' => $modelo_nombre
        ];
        continue;
    }

    $marca_slug  = slugify_local($marca_nombre);
    $modelo_slug = slugify_local($modelo_nombre);
    $deep_url    = "{$marca_slug}/{$modelo_slug}/usado/_NoIndex_True";

    $sqlExiste = "
        SELECT id, estado, intentos, deep_url
        FROM apify_jobs
        WHERE brand_id = {$marca_id}
          AND model_id = {$modelo_id}
          AND deep_url = '".$esc($deep_url)."'
        LIMIT 1
    ";

    $qExiste = $db->query($sqlExiste);
    if (!$qExiste) {
        $errores[] = [
            'tipo' => 'select_existente_fail',
            'marca_id' => $marca_id,
            'marca' => $marca_nombre,
            'modelo_id' => $modelo_id,
            'modelo' => $modelo_nombre,
            'deep_url' => $deep_url,
            'sql' => $sqlExiste,
            'db_error' => $db->error ?? null
        ];
        continue;
    }

    if (db_num_rows_safe($db, $qExiste) > 0) {
        $job = $db->fetch_array($qExiste);
        $jobs_existentes++;

        $sqlUpdate = "
            UPDATE apify_jobs
            SET estado='PENDIENTE',
                intentos=0,
                updated_at='{$now}',
                mensaje='Reactivado por planner'
            WHERE id=" . intval($job['id']) . "
            LIMIT 1
        ";

        $rUpdate = $db->query($sqlUpdate);
        if ($rUpdate) {
            $jobs_actualizados++;
            $detalle[] = [
                'accion' => 'actualizado',
                'job_id' => intval($job['id']),
                'marca_id' => $marca_id,
                'marca' => $marca_nombre,
                'modelo_id' => $modelo_id,
                'modelo' => $modelo_nombre,
                'deep_url' => $deep_url
            ];
        } else {
            $errores[] = [
                'tipo' => 'update_existente_fail',
                'job_id' => intval($job['id']),
                'marca_id' => $marca_id,
                'marca' => $marca_nombre,
                'modelo_id' => $modelo_id,
                'modelo' => $modelo_nombre,
                'deep_url' => $deep_url,
                'sql' => $sqlUpdate,
                'db_error' => $db->error ?? null
            ];
        }

        continue;
    }

    $sqlJob = "
        INSERT INTO apify_jobs
        (
            brand_id, brand_name, model_id, model_name, deep_url,
            estado, intentos, created_at, updated_at, mensaje
        )
        VALUES
        (
            {$marca_id},
            '".$esc($marca_nombre)."',
            {$modelo_id},
            '".$esc($modelo_nombre)."',
            '".$esc($deep_url)."',
            'PENDIENTE',
            0,
            '{$now}',
            '{$now}',
            'Creado por planner'
        )
        ON DUPLICATE KEY UPDATE
            estado='PENDIENTE',
            intentos=0,
            updated_at='{$now}',
            mensaje='Reactivado por planner'
    ";

    $r = $db->query($sqlJob);
    if ($r) {
        $jobs_creados++;
        $detalle[] = [
            'accion' => 'creado',
            'marca_id' => $marca_id,
            'marca' => $marca_nombre,
            'modelo_id' => $modelo_id,
            'modelo' => $modelo_nombre,
            'deep_url' => $deep_url
        ];
    } else {
        $errores[] = [
            'tipo' => 'insert_fail',
            'marca_id' => $marca_id,
            'marca' => $marca_nombre,
            'modelo_id' => $modelo_id,
            'modelo' => $modelo_nombre,
            'deep_url' => $deep_url,
            'sql' => $sqlJob,
            'db_error' => $db->error ?? null
        ];
    }
}

jok([
    "fecha" => $now,
    "jobs_procesados" => $jobs_procesados,
    "jobs_creados" => $jobs_creados,
    "jobs_existentes" => $jobs_existentes,
    "jobs_actualizados" => $jobs_actualizados,
    "errores_count" => count($errores),
    "errores" => $errores,
    "detalle_count" => count($detalle),
    "detalle" => $detalle
]);