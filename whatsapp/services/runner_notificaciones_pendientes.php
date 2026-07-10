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

    $cn->set_charset('utf8mb4');

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
                $vehiculo,
                intval($row['id_cotizacion'] ?? 0)
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

            $ok = TwilioMessageService::enviarTemplateRecordatorioConfirmacionAgenda3Hs($telefono);

            if ($ok) {
                NotificacionPendienteService::marcarProcesada($id);

                $idCotizacion = intval($row['id_cotizacion'] ?? 0);

                if ($tipo === 'RECORDATORIO_CONFIRMACION_AGENDA_10HS' && $idCotizacion > 0) {
                    NotificacionPendienteService::crear(
                        $idCotizacion,
                        intval($row['id_agenda'] ?? 0) > 0 ? intval($row['id_agenda']) : null,
                        $telefono,
                        'ABANDONO_AGENDA_POST_RECORDATORIO_3HS',
                        'AGENDA',
                        date('Y-m-d H:i:s', strtotime('+10 hours')),
                        $payload,
                        'Control interno: si no responde al recordatorio de agenda en 10 hs, enviar a carrito abandonado'
                    );
                }

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

        case 'RECORDATORIO_ASISTENCIA_AGENDA_24HS':
        case 'RECORDATORIO_ASISTENCIA_AGENDA_48HS':

            $idAgenda = intval($row['id_agenda'] ?? 0);
            $idCotizacion = intval($row['id_cotizacion'] ?? 0);

            if ($idAgenda <= 0) {
                NotificacionPendienteService::marcarError($id, 'No se pudo enviar confirmación de asistencia: id_agenda vacío');
                continue 2;
            }

            $cnAgenda = wa_db();
            $rsAgenda = $cnAgenda->query("
                SELECT id_agenda, id_cotizacion, telefono, nombre, auto, fecha, hora, cancelado, finalizada, confirmacion_asistencia
                FROM agendas
                WHERE id_agenda = " . intval($idAgenda) . "
                LIMIT 1
            ");
            $agenda = $rsAgenda ? $rsAgenda->fetch_assoc() : null;

            if (!$agenda) {
                $cnAgenda->close();
                NotificacionPendienteService::marcarError($id, 'No se pudo enviar confirmación de asistencia: agenda no encontrada');
                continue 2;
            }

            if (
                intval($agenda['cancelado'] ?? 0) === 1
                || intval($agenda['finalizada'] ?? 0) === 1
                || strtotime((string)$agenda['fecha'] . ' ' . (string)$agenda['hora']) < time()
            ) {
                $cnAgenda->close();
                NotificacionPendienteService::cancelar($id, 'Agenda cancelada, finalizada o vencida');
                continue 2;
            }

            if (trim((string)($agenda['confirmacion_asistencia'] ?? '')) !== '') {
                $cnAgenda->close();
                NotificacionPendienteService::cancelar($id, 'La agenda ya tiene confirmación de asistencia');
                continue 2;
            }

            $telefonoAgenda = trim((string)($agenda['telefono'] ?? $telefono));
            $nombreAgenda = trim((string)($agenda['nombre'] ?? ($payload['nombre'] ?? '')));
            $autoAgenda = trim((string)($agenda['auto'] ?? ($payload['auto'] ?? '')));
            $fechaAgenda = (string)($agenda['fecha'] ?? ($payload['fecha'] ?? ''));
            $horaAgenda = (string)($agenda['hora'] ?? ($payload['hora'] ?? ''));

            $ok = TwilioMessageService::enviarTemplateAsistenciaAgenda(
                $telefonoAgenda,
                $nombreAgenda,
                $autoAgenda,
                $fechaAgenda,
                $horaAgenda,
                $idCotizacion
            );

            if ($ok) {
                $cnAgenda->query("
                    UPDATE agendas
                    SET confirmacion_asistencia = 'PENDIENTE',
                        fecha_confirmacion_asistencia = NOW()
                    WHERE id_agenda = " . intval($idAgenda) . "
                    LIMIT 1
                ");

                $tipoAgenda = $tipo === 'RECORDATORIO_ASISTENCIA_AGENDA_48HS'
                    ? 'confirmacion_48h'
                    : 'confirmacion_24h';

                $cnAgenda->query("
                    INSERT INTO whatsapp_agenda_notificaciones
                    (
                        id_agenda,
                        telefono,
                        tipo_notificacion,
                        fecha_agenda,
                        hora_agenda,
                        fecha_envio,
                        estado_envio,
                        mensaje_enviado,
                        sid_mensaje,
                        respuesta_api
                    )
                    VALUES
                    (
                        " . intval($idAgenda) . ",
                        '" . $cnAgenda->real_escape_string($telefonoAgenda) . "',
                        '" . $cnAgenda->real_escape_string($tipoAgenda) . "',
                        '" . $cnAgenda->real_escape_string($fechaAgenda) . "',
                        '" . $cnAgenda->real_escape_string($horaAgenda) . "',
                        NOW(),
                        'ENVIADO',
                        'Confirmación de asistencia enviada desde cola central',
                        '',
                        '" . $cnAgenda->real_escape_string(json_encode(['origen' => 'whatsapp_notificaciones_pendientes'], JSON_UNESCAPED_UNICODE)) . "'
                    )
                ");

                $cnAgenda->close();
                NotificacionPendienteService::marcarProcesada($id);
                NotificacionPendienteService::programarAbandonoRecordatorioAgenda10Hs(
                    $idCotizacion,
                    $idAgenda,
                    $telefonoAgenda,
                    [
                        'fecha' => $fechaAgenda,
                        'hora' => $horaAgenda,
                        'nombre' => $nombreAgenda,
                        'auto' => $autoAgenda,
                        'tipo_recordatorio_origen' => $tipo
                    ]
                );
            } else {
                $cnAgenda->close();
                NotificacionPendienteService::marcarError($id, 'Error al enviar confirmación de asistencia de agenda');
            }

            continue 2;

        case 'ABANDONO_RECORDATORIO_AGENDA_10HS':

            $idCotizacion = intval($row['id_cotizacion'] ?? 0);
            $idAgenda = intval($row['id_agenda'] ?? 0);

            if ($idCotizacion <= 0 || $idAgenda <= 0) {
                NotificacionPendienteService::marcarError(
                    $id,
                    'No se pudo procesar abandono recordatorio agenda: faltan id_cotizacion o id_agenda'
                );
                continue 2;
            }

            $cnAbandonoAgenda = wa_db();
            $rsAgenda = $cnAbandonoAgenda->query("
                SELECT id_agenda, id_cotizacion, telefono, cancelado, finalizada, confirmacion_asistencia
                FROM agendas
                WHERE id_agenda = " . intval($idAgenda) . "
                  AND id_cotizacion = " . intval($idCotizacion) . "
                LIMIT 1
            ");
            $agenda = $rsAgenda ? $rsAgenda->fetch_assoc() : null;

            if (!$agenda) {
                $cnAbandonoAgenda->close();
                NotificacionPendienteService::marcarError($id, 'No se pudo procesar abandono recordatorio agenda: agenda no encontrada');
                continue 2;
            }

            $confirmacionAsistencia = strtoupper(trim((string)($agenda['confirmacion_asistencia'] ?? '')));

            if (
                intval($agenda['cancelado'] ?? 0) === 1
                || intval($agenda['finalizada'] ?? 0) === 1
                || !in_array($confirmacionAsistencia, ['', 'PENDIENTE', 'PTE RESP.', 'PTE_RESP', 'SIN_RESPUESTA'], true)
            ) {
                $cnAbandonoAgenda->close();
                NotificacionPendienteService::marcarProcesada($id);
                continue 2;
            }

            $telefonoAgenda = trim((string)($agenda['telefono'] ?? $telefono));
            if ($telefonoAgenda === '') {
                $telefonoAgenda = $telefono;
            }

            $rsConv = $cnAbandonoAgenda->query("
                SELECT id, datos_json
                FROM whatsapp_conversaciones
                WHERE telefono = '" . $cnAbandonoAgenda->real_escape_string($telefonoAgenda) . "'
                  AND id_cotizacion = " . intval($idCotizacion) . "
                ORDER BY id DESC
                LIMIT 1
            ");
            $conv = $rsConv ? $rsConv->fetch_assoc() : null;
            $idConversacion = intval($conv['id'] ?? 0);

            if ($idConversacion <= 0) {
                $rsConvFallback = $cnAbandonoAgenda->query("
                    SELECT id, datos_json
                    FROM whatsapp_conversaciones
                    WHERE telefono = '" . $cnAbandonoAgenda->real_escape_string($telefonoAgenda) . "'
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $conv = $rsConvFallback ? $rsConvFallback->fetch_assoc() : $conv;
                $idConversacion = intval($conv['id'] ?? 0);
            }

            $datos = [];
            if (!empty($conv['datos_json'])) {
                $tmp = json_decode($conv['datos_json'], true);
                if (is_array($tmp)) {
                    $datos = $tmp;
                }
            }

            if (!CarritoAbandonadoService::existePendiente(
                $idCotizacion,
                'NO_RESPONDE_RECORDATORIO_AGENDA',
                'AGENDA'
            )) {
                CarritoAbandonadoService::registrar(
                    $idCotizacion,
                    $idConversacion,
                    $telefonoAgenda,
                    'Sin respuesta luego del recordatorio automÃ¡tico de agenda',
                    'NO_RESPONDE_RECORDATORIO_AGENDA',
                    'AGENDA',
                    'Alan'
                );
            }

            $nuevoDatos = $datos;
            $nuevoDatos['step'] = 'cerrado';
            $nuevoDatos['sub_step'] = 'carrito_abandonado_sin_respuesta_recordatorio_agenda';
            $nuevoDatos['id_cotizacion'] = $idCotizacion;
            $nuevoDatos['motivo_abandono'] = 'NO_RESPONDE_RECORDATORIO_AGENDA';
            $nuevoDatos['origen_abandono'] = 'AGENDA';

            if ($idConversacion > 0) {
                $cnAbandonoAgenda->query("
                    UPDATE whatsapp_conversaciones
                    SET
                        estado = 'CERRADO',
                        modo_atencion = 'BOT',
                        datos_json = '" . $cnAbandonoAgenda->real_escape_string(json_encode($nuevoDatos, JSON_UNESCAPED_UNICODE)) . "',
                        fecha_mod = NOW()
                    WHERE id = " . intval($idConversacion) . "
                    LIMIT 1
                ");
            }

            $okRecordatorio10Hs = TwilioMessageService::enviarTemplateRecordatorioConfirmacionAgenda10Hs($telefonoAgenda);

            $cnAbandonoAgenda->close();

            if ($okRecordatorio10Hs) {
                NotificacionPendienteService::marcarProcesada($id);
            } else {
                NotificacionPendienteService::marcarError(
                    $id,
                    'Error al enviar recordatorio de confirmación de agenda 10 hs'
                );
            }

            continue 2;

        case 'NOTIFICACION_NO_ASISTIO_AGENDA':

            $ok = TwilioMessageService::enviarTemplateNoAsistioAgenda(
                $telefono,
                intval($row['id_cotizacion'] ?? 0)
            );

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

                $idCotizacion = intval($row['id_cotizacion'] ?? 0);

                if ($idCotizacion > 0) {
                    NotificacionPendienteService::crear(
                        $idCotizacion,
                        null,
                        $telefono,
                        'ABANDONO_TASACION_FINAL_POST_RECORDATORIO_3HS',
                        'TASACION_FINAL',
                        date('Y-m-d H:i:s', strtotime('+3 hours')),
                        [
                            'id_cotizacion' => $idCotizacion,
                            'nombre' => $nombre,
                            'vehiculo' => $vehiculo
                        ],
                        'Control interno: si no responde al recordatorio de tasacion final en 3 hs, enviar a carrito abandonado'
                    );
                }

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
                'NO_RESPONDE_RECORDATORIO_AGENDA',
                'AGENDA'
            )) {
                CarritoAbandonadoService::registrar(
                    $idCotizacion,
                    $idConversacion,
                    $telefono,
                    'Sin respuesta luego del recordatorio de agenda',
                    'NO_RESPONDE_RECORDATORIO_AGENDA',
                    'AGENDA',
                    'Alan'
                );
            }

            $nuevoDatos = $datos;
            $nuevoDatos['step'] = 'cerrado';
            $nuevoDatos['sub_step'] = 'carrito_abandonado_sin_confirmar_agenda';
            $nuevoDatos['id_cotizacion'] = $idCotizacion;
            $nuevoDatos['motivo_abandono'] = 'NO_RESPONDE_RECORDATORIO_AGENDA';
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
