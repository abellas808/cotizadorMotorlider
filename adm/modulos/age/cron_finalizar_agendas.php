<?php
ob_start();

require_once(__DIR__ . '/../../config/config.inc.php');

if (!isset($config['db_tablePrefix'])) {
    $config['db_tablePrefix'] = '';
}

require_once(__DIR__ . '/../../includes/database.php');
require_once(__DIR__ . '/../../includes/funciones.php');

global $db;

date_default_timezone_set('America/Montevideo');

function logCron($msg) {
    $file = __DIR__ . '/cron_finalizar_agendas.log';
    file_put_contents($file, date('Y-m-d H:i:s') . ' - ' . $msg . PHP_EOL, FILE_APPEND);
}

logCron("==== INICIO CRON FINALIZAR ====");

$fechaHoy = date('Y-m-d');
$horaAhora = date('H:i:s');

try {

    $sql = "
        UPDATE agendas
        SET 
            finalizada = 1,
            fecha_finalizacion = NOW(),
            detalle_estado = 'Finalizada automáticamente por fecha/hora'
        WHERE (cancelado = 0 OR cancelado IS NULL)
          AND (finalizada = 0 OR finalizada IS NULL)
          AND (
                fecha < '{$fechaHoy}'
                OR (fecha = '{$fechaHoy}' AND hora < '{$horaAhora}')
              )
    ";

    $ok = $db->query($sql);

    if (!$ok) {
        logCron("ERROR - Falló el UPDATE");
        echo json_encode([
            'ok' => false,
            'mensaje' => 'Falló el UPDATE'
        ]);
        exit;
    }

    $rowCount = $db->query_first("SELECT ROW_COUNT() AS cantidad");
    $afectadas = intval($rowCount['cantidad'] ?? 0);

    logCron("OK - Agendas finalizadas: " . $afectadas);
    logCron("Fecha hoy: " . $fechaHoy . " | Hora ahora: " . $horaAhora);

    echo json_encode([
        'ok' => true,
        'finalizadas' => $afectadas,
        'fecha_hoy' => $fechaHoy,
        'hora_ahora' => $horaAhora,
        'mensaje' => 'Proceso ejecutado correctamente'
    ]);

} catch (Throwable $e) {
    logCron("EXCEPTION - " . $e->getMessage());
    logCron("FILE - " . $e->getFile());
    logCron("LINE - " . $e->getLine());

    echo json_encode([
        'ok' => false,
        'mensaje' => $e->getMessage()
    ]);
}

logCron("==== FIN CRON FINALIZAR ====");