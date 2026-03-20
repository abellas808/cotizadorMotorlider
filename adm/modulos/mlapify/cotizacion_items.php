<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once(__DIR__ . "/../../../apicotizador/src/db.php");

try {
    $db = Database::getInstance();

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id <= 0) {
        echo json_encode([
            'ok' => false,
            'msg' => 'ID inválido',
            'rows' => []
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sql = "
        SELECT
            cotizacion_id,
            brand,
            modelo,
            item_url,
            title
        FROM marcos2022_api_cotizador.cotizacion_items
        WHERE cotizacion_id = :id
        ORDER BY id ASC
    ";

    $rows = $db->mysqlQuery(trim($sql), [
        ':id' => $id
    ]);

    echo json_encode([
        'ok' => true,
        'id_buscado' => $id,
        'total_rows' => is_array($rows) ? count($rows) : 0,
        'rows' => is_array($rows) ? $rows : []
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'msg' => $e->getMessage(),
        'rows' => []
    ], JSON_UNESCAPED_UNICODE);
}