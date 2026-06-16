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
    AND fecha_programada <= NOW() 
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

        case 'ABANDONO_PRECOTIZACION_POST_RECORDATORIO_3HS':

            $idCotizacion = intval($row['id_cotizacion'] ?? 0);

            if ($idCotizacion <= 0) {
                NotificacionPendienteService::marcarError(
                    $id,
                    'No se pudo procesar abandono: id_cotizacion vacío'
                );
                continue 2;
            }

            $cnAbandono = wa_db();

            $sqlConv = "
                SELECT id, datos_json
                FROM whatsapp_conversaciones
                WHERE telefono = '" . $cnAbandono->real_escape_string($telefono) . "'
                ORDER BY id DESC
                LIMIT 1
            ";

            $rsConv = $cnAbandono->query($sqlConv);
            $conv = $rsConv ? $rsConv->fetch_assoc() : null;

            $idConversacion = intval($conv['id'] ?? 0);

            $datos = [];
            if (!empty($conv['datos_json'])) {
                $tmp = json_decode($conv['datos_json'], true);
                if (is_array($tmp)) {
                    $datos = $tmp;
                }
            }

            $stepActual = (string)($datos['step'] ?? '');

            if (in_array($stepActual, ['agenda_dia', 'pendiente_humano', 'cerrado'], true)) {
                NotificacionPendienteService::marcarProcesada($id);
                $cnAbandono->close();
                continue 2;
            }

            $mensajeCliente = 'Sin respuesta luego del recordatorio 24 hs';

            CarritoAbandonadoService::registrar(
                $idCotizacion,
                $idConversacion,
                $telefono,
                'Sin respuesta luego del recordatorio 24 hs',
                'SIN_RESPUESTA_RECORDATORIO_24HS',
                'PRETASACION',
                'Alan'
            );

            $nuevoDatos = $datos;
            $nuevoDatos['step'] = 'cerrado';
            $nuevoDatos['sub_step'] = 'carrito_abandonado_sin_respuesta_recordatorio';
            $nuevoDatos['id_cotizacion'] = $idCotizacion;
            $nuevoDatos['motivo_abandono'] = 'SIN_RESPUESTA_RECORDATORIO_24HS';
            $nuevoDatos['origen_abandono'] = 'PRETASACION';

            $sqlUpdateConv = "
                UPDATE whatsapp_conversaciones
                SET
                    estado = 'CERRADO',
                    modo_atencion = 'BOT',
                    datos_json = '" . $cnAbandono->real_escape_string(json_encode($nuevoDatos, JSON_UNESCAPED_UNICODE)) . "',
                    fecha_mod = NOW()
                WHERE id = " . intval($idConversacion) . "
                LIMIT 1
            ";

            $cnAbandono->query($sqlUpdateConv);
            $cnAbandono->close();

            NotificacionPendienteService::marcarProcesada($id);

            continue 2;
            
            default:
                NotificacionPendienteService::marcarError(
                    $id,
                    'Tipo de notificación no soportado: ' . $tipo
                );
                continue 2;
        }

    if ($ok) {
        NotificacionPendienteService::marcarProcesada($id);

        NotificacionPendienteService::crear(
            intval($row['id_cotizacion']),
            null,
            $telefono,
            'ABANDONO_PRECOTIZACION_POST_RECORDATORIO_3HS',
            'PRETASACION',
            date('Y-m-d H:i:s', strtotime('+3 hours')),
            $payload,
            'Control interno: si no responde luego del recordatorio 24 hs, enviar a carrito abandonado'
        );
    } else {
        NotificacionPendienteService::marcarError(
            $id,
            'Error al enviar notificación tipo ' . $tipo
        );
    }
}

$cn->close();