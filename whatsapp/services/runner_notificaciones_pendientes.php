<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__, 2) . '/config.php';

require_once __DIR__ . '/ParametroSistemaService.php';
require_once __DIR__ . '/TwilioMessageService.php';
require_once __DIR__ . '/NotificacionPendienteService.php';

function wa_db(): mysqli
{
    mysqli_report(MYSQLI_REPORT_OFF);

    $cn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($cn->connect_errno) {
        throw new RuntimeException('Error conexión MySQL: ' . $cn->connect_error);
    }

    $cn->set_charset('utf8');

    return $cn;
}

function wa_log(string $tag, array $data = []): void
{
    $dir = dirname(__DIR__) . '/logs';

    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $file = $dir . '/runner_notificaciones_' . date('Ymd') . '.log';

    @file_put_contents(
        $file,
        date('Y-m-d H:i:s') . " [$tag] " . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND
    );
}

$cn = wa_db();

$sql = "
    SELECT *
    FROM whatsapp_notificaciones_pendientes
    WHERE estado = 'PENDIENTE'
    AND fecha_programada <= NOW() and id=1
    ORDER BY fecha_programada ASC
    LIMIT 20
";

$rs = $cn->query($sql);

if (!$rs) {
    wa_log('RUNNER_NOTIFICACIONES_QUERY_ERROR', [
        'error' => $cn->error
    ]);
    $cn->close();
    exit;
}

while ($row = $rs->fetch_assoc()) {

    $id = intval($row['id']);
    $telefono = trim((string)$row['telefono']);
    $tipo = trim((string)$row['tipo_notificacion']);

    $payload = [];

    if (!empty($row['payload_json'])) {
        $payloadTmp = json_decode($row['payload_json'], true);
        if (is_array($payloadTmp)) {
            $payload = $payloadTmp;
        }
    }

    $ok = false;

    switch ($tipo) {

        case 'RECORDATORIO_PRECOTIZACION_24HS':

            $nombre = trim((string)($payload['nombre'] ?? ''));
            $vehiculo = trim((string)($payload['vehiculo'] ?? ''));

            if ($nombre === '') {
                $nombre = '!';
            }

            if ($vehiculo === '') {
                $vehiculo = 'tu vehículo';
            }

            $ok = TwilioMessageService::enviarTemplateRecordatorioPrecotizacion24Hs(
                $telefono,
                $nombre,
                $vehiculo
            );

            break;

        default:
            NotificacionPendienteService::marcarError(
                $id,
                'Tipo de notificación no soportado: ' . $tipo
            );
            continue 2;
    }

    if ($ok) {
        NotificacionPendienteService::marcarProcesada($id);
    } else {
        NotificacionPendienteService::marcarError(
            $id,
            'Error al enviar notificación tipo ' . $tipo
        );
    }
}

$cn->close();