<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header("Content-Type: application/json; charset=utf-8");

require_once(__DIR__ . "/../../config/config.inc.php");
require_once(__DIR__ . "/../../includes/database.php");
require_once(__DIR__ . "/../../includes/funciones.php");

echo json_encode([
    'ok' => true,
    'paso' => 'funciones_ok'
], JSON_UNESCAPED_UNICODE);
exit;