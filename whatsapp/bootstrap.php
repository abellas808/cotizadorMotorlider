<?php

require_once dirname(__DIR__) . '/config/conexion.php';

function wa_db(): mysqli
{
    mysqli_report(MYSQLI_REPORT_OFF);

    $cn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($cn->connect_errno) {
        throw new RuntimeException('Error conexión MySQL: ' . $cn->connect_error);
    }

    if (!$cn->set_charset('utf8')) {
        throw new RuntimeException('Error charset MySQL: ' . $cn->error);
    }

    return $cn;
}