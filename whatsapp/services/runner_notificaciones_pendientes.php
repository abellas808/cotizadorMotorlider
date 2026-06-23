<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('America/Montevideo');

require_once dirname(__DIR__, 2) . '/config.php';

require_once __DIR__ . '/ParametroSistemaService.php';
require_once __DIR__ . '/TwilioMessageService.php';
require_once __DIR__ . '/NotificacionPendienteService.php';
require_once __DIR__ . '/NotificacionAutomaticaGuardService.php';
require_once __DIR__ . '/CarritoAbandonadoService.php';

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

    $control = NotificacionAutomaticaGuardService::evaluar($row);
    $accionControl = (string)($control['accion'] ?? 'OMITIR');
    $motivoControl = (string)($control['motivo'] ?? 'Control de seguridad sin resultado');

    wa_log('RUNNER_KILL_SWITCH', [
        'id' => $id,
        'id_cotizacion' => intval($row['id_cotizacion'] ?? 0),
        'tipo' => $tipo,
        'accion' => $accionControl,
        'motivo' => $motivoControl,
        'fecha_programada' => $control['fecha_programada'] ?? null
    ]);

    if ($accionControl === 'CANCELAR_TODAS') {
        NotificacionPendienteService::cancelarPendientesPorCotizacion(
            intval($row['id_cotizacion'] ?? 0),
            $motivoControl
        );
        continue;
    }

    if ($accionControl === 'CANCELAR') {
        NotificacionPendienteService::cancelar($id, $motivoControl);
        continue;
    }

    if ($accionControl === 'REPROGRAMAR') {
        NotificacionPendienteService::reprogramar(
            $id,
            (string)$control['fecha_programada'],
            $motivoControl
        );
        continue;
    }

    if ($accionControl !== 'CONTINUAR') {
        continue;
    }

    $payload = [];

    if (!empty($row['payload_json'])) {
        $payloadTmp = json_decode($row['payload_json'], true);
        if (is_array($payloadTmp)) {
            $payload = $payloadTmp;
        }
    }

    switch ($tipo) {

        case 'RECORDATORIO_PRECOTIZACION_24HS':

            $nombre = trim((string)($payload['nombre'] ?? ''));
            $vehiculo = trim((string)($payload['vehiculo'] ?? ''));

            if ($nombre === '') {
                $nombre = 'cliente';
            }

            if ($vehiculo === '') {
                $vehiculo = 'tu vehículo';
            }

            $ok = TwilioMessageService::enviarTemplateRecordatorioPrecotizacion24Hs(
                $telefono,
                $nombre,
                $vehiculo
            );

            if ($ok) {
                NotificacionPendienteService::marcarProcesada($id);

                $idCotizacionPre = intval($row['id_cotizacion'] ?? 0);

                if ($idCotizacionPre > 0) {
                    $cnAbandonoPre = wa_db();

                    $sqlConvPre = "
                        SELECT id
                        FROM whatsapp_conversaciones
                        WHERE telefono = '" . $cnAbandonoPre->real_escape_string($telefono) . "'
                          AND id_cotizacion = " . intval($idCotizacionPre) . "
                        ORDER BY id DESC
                        LIMIT 1
                    ";

                    $rsConvPre = $cnAbandonoPre->query($sqlConvPre);
                    $convPre = $rsConvPre ? $rsConvPre->fetch_assoc() : null;
                    $idConversacionPre = intval($convPre['id'] ?? 0);

                    if (!CarritoAbandonadoService::existePendiente(
                        $idCotizacionPre,
                        'NO_RESPONDE_PRETASACION',
                        'PRETASACION'
                    )) {
                        CarritoAbandonadoService::registrar(
                            $idCotizacionPre,
                            $idConversacionPre,
                            $telefono,
                            'Sin respuesta luego de la pre-cotización',
                            'NO_RESPONDE_PRETASACION',
                            'PRETASACION',
                            'Alan'
                        );
                    }

                    $cnAbandonoPre->close();
                }

                NotificacionPendienteService::crear(
                    intval($row['id_cotizacion'] ?? 0),
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

            continue 2;


        case 'RECORDATORIO_CONFIRMACION_AGENDA_3HS':
        case 'RECORDATORIO_CONFIRMACION_AGENDA_10HS':

            wa_log('RUNNER_RECORDATORIO_CONFIRMACION_AGENDA_INICIO', [
                'id' => $id,
                'telefono' => $telefono
            ]);

            $ok = TwilioMessageService::enviarTemplateRecordatorioConfirmacionAgenda10Hs(
                $telefono
            );

            if ($ok) {
                NotificacionPendienteService::marcarProcesada($id);

                NotificacionPendienteService::crear(
                    intval($row['id_cotizacion'] ?? 0),
                    null,
                    $telefono,
                    'ABANDONO_AGENDA_POST_RECORDATORIO_3HS',
                    'AGENDA',
                    date('Y-m-d H:i:s', strtotime('+10 hours')),
                    $payload,
                    'Control de abandono 10 hs luego del recordatorio de confirmación de agenda'
                );

                wa_log('RUNNER_RECORDATORIO_CONFIRMACION_AGENDA_OK', [
                    'id' => $id,
                    'telefono' => $telefono
                ]);
            } else {
                NotificacionPendienteService::marcarError(
                    $id,
                    'Error al enviar notificación tipo ' . $tipo
                );
            }

            continue 2;

        case 'NOTIFICACION_NO_ASISTIO_AGENDA':

            $ok = TwilioMessageService::enviarTemplateNoAsistioAgenda($telefono);

            if ($ok) {
                NotificacionPendienteService::marcarProcesada($id);
            } else {
                NotificacionPendienteService::marcarError(
                    $id,
                    'Error al enviar notificación tipo ' . $tipo
                );
            }

            continue 2;

        case 'RECORDATORIO_TASACION_FINAL_24HS':

            $nombre = trim((string)($payload['nombre'] ?? ''));
            $vehiculo = trim((string)($payload['vehiculo'] ?? ''));

            if ($nombre === '') {
                $nombre = 'cliente';
            }

            if ($vehiculo === '') {
                $vehiculo = 'vehículo';
            }

            $ok = TwilioMessageService::enviarTemplateRecordatorioTasacionFinal24Hs(
                $telefono,
                $nombre,
                $vehiculo
            );

            if ($ok) {
                NotificacionPendienteService::marcarProcesada($id);

                NotificacionPendienteService::crear(
                    intval($row['id_cotizacion'] ?? 0),
                    null,
                    $telefono,
                    'ABANDONO_TASACION_FINAL_POST_RECORDATORIO_3HS',
                    'TASACION_FINAL',
                    date('Y-m-d H:i:s', strtotime('+24 hours')),
                    $payload,
                    'Control de abandono a las 48 hs de enviada la tasación final'
                );
            } else {
                NotificacionPendienteService::marcarError(
                    $id,
                    'Error al enviar notificación tipo ' . $tipo
                );
            }

            continue 2;


        case 'ABANDONO_PRECOTIZACION_POST_RECORDATORIO_3HS':

            $idCotizacion = intval($row['id_cotizacion'] ?? 0);

            if ($idCotizacion <= 0) {
                NotificacionPendienteService::marcarError(
                    $id,
                    'No se pudo procesar abandono pre-cotización: id_cotizacion vacío'
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

            if (!CarritoAbandonadoService::existePendiente(
                $idCotizacion,
                'NO_RESPONDE_PRETASACION',
                'PRETASACION'
            )) {
                CarritoAbandonadoService::registrar(
                    $idCotizacion,
                    $idConversacion,
                    $telefono,
                    'Sin respuesta luego de la pre-cotización',
                    'NO_RESPONDE_PRETASACION',
                    'PRETASACION',
                    'Alan'
                );
            }

            $nuevoDatos = $datos;
            $nuevoDatos['step'] = 'cerrado';
            $nuevoDatos['sub_step'] = 'carrito_abandonado_sin_respuesta_recordatorio';
            $nuevoDatos['id_cotizacion'] = $idCotizacion;
            $nuevoDatos['motivo_abandono'] = 'NO_RESPONDE_PRETASACION';
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


        case 'ABANDONO_AGENDA_POST_RECORDATORIO_3HS':

            $idCotizacion = intval($row['id_cotizacion'] ?? 0);

            if ($idCotizacion <= 0) {
                NotificacionPendienteService::marcarError(
                    $id,
                    'No se pudo procesar abandono agenda: id_cotizacion vacío'
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

            if (in_array($stepActual, ['pendiente_humano', 'cerrado'], true)) {
                NotificacionPendienteService::marcarProcesada($id);
                $cnAbandono->close();
                continue 2;
            }

            if (!CarritoAbandonadoService::existePendiente(
                $idCotizacion,
                'NO_CONFIRMA_AGENDA',
                'AGENDA'
            )) {
                CarritoAbandonadoService::registrar(
                    $idCotizacion,
                    $idConversacion,
                    $telefono,
                    'Sin respuesta luego del recordatorio de confirmación de agenda',
                    'NO_CONFIRMA_AGENDA',
                    'AGENDA',
                    'Alan'
                );
            }

            $nuevoDatos = $datos;
            $nuevoDatos['step'] = 'cerrado';
            $nuevoDatos['sub_step'] = 'carrito_abandonado_sin_confirmar_agenda';
            $nuevoDatos['id_cotizacion'] = $idCotizacion;
            $nuevoDatos['motivo_abandono'] = 'NO_CONFIRMA_AGENDA';
            $nuevoDatos['origen_abandono'] = 'AGENDA';

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

        case 'ABANDONO_TASACION_FINAL_POST_RECORDATORIO_3HS':

            $idCotizacion = intval($row['id_cotizacion'] ?? 0);

            if ($idCotizacion <= 0) {
                NotificacionPendienteService::marcarError(
                    $id,
                    'No se pudo procesar abandono tasación final: id_cotizacion vacío'
                );
                continue 2;
            }

            $cnAbandono = wa_db();

            $sqlConv = "
                SELECT id, datos_json
                FROM whatsapp_conversaciones
                WHERE telefono = '" . $cnAbandono->real_escape_string($telefono) . "'
                  AND id_cotizacion = " . intval($idCotizacion) . "
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

            if ($stepActual !== 'tasacion_final_enviado') {
                NotificacionPendienteService::marcarProcesada($id);
                $cnAbandono->close();
                continue 2;
            }

            if (!CarritoAbandonadoService::existePendiente(
                $idCotizacion,
                'NO_RESPONDE_TASACION_FINAL',
                'TASACION_FINAL'
            )) {
                CarritoAbandonadoService::registrar(
                    $idCotizacion,
                    $idConversacion,
                    $telefono,
                    'Sin respuesta luego del recordatorio de tasación final',
                    'NO_RESPONDE_TASACION_FINAL',
                    'TASACION_FINAL',
                    'Alan'
                );
            }

            $datos['step'] = 'cerrado';
            $datos['sub_step'] = 'carrito_abandonado_sin_respuesta_tasacion_final';
            $datos['id_cotizacion'] = $idCotizacion;
            $datos['motivo_abandono'] = 'NO_RESPONDE_TASACION_FINAL';
            $datos['origen_abandono'] = 'TASACION_FINAL';

            $cnAbandono->query("
                UPDATE whatsapp_conversaciones
                SET
                    estado = 'CERRADO',
                    modo_atencion = 'BOT',
                    datos_json = '" . $cnAbandono->real_escape_string(
                        json_encode($datos, JSON_UNESCAPED_UNICODE)
                    ) . "',
                    fecha_mod = NOW()
                WHERE id = " . intval($idConversacion) . "
                LIMIT 1
            ");

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
}
