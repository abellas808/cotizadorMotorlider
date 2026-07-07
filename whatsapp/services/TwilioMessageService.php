<?php

class TwilioMessageService
{
    private const TEMPLATE_ASISTENCIA_AGENDA_DEFAULT = 'HX4e31eca8dab14ba5842ffc5d78ec93d0';

    public static function enviarTemplateDatosFinalesAgendaConfirmacion(
        string $to,
        string $fecha,
        string $hora
    ): bool {
        $contentSid = ParametroSistemaService::obtener(
            'twilio',
            'template_datos_finales_agenda_confirmacion'
        );

        return self::enviarTemplate(
            $to,
            $contentSid,
            [
                "1" => $fecha,
                "2" => substr($hora, 0, 5)
            ],
            'template_datos_finales_agenda_confirmacion',
            "¡Agenda confirmada! ✅\n\nFecha: {$fecha}\nHora: " . substr($hora, 0, 5)
        );
    }

    public static function enviarTemplate(
        string $to,
        string $contentSid,
        array $variables,
        string $origen,
        string $mensajeHistorial = '',
        ?int $idCotizacionHistorial = null
    ): bool {
        $accountSid = ParametroSistemaService::obtener('twilio', 'account_sid');
        $authToken = ParametroSistemaService::obtener('twilio', 'auth_token');
        $from = ParametroSistemaService::obtener('twilio', 'whatsapp_from');

        if ($accountSid === '' || $authToken === '' || $from === '' || $contentSid === '') {
            wa_log('TWILIO_SERVICE_PARAMETROS_INCOMPLETOS', [
                'to' => $to,
                'content_sid' => $contentSid,
                'account_sid_ok' => $accountSid !== '',
                'auth_token_ok' => $authToken !== '',
                'from_ok' => $from !== ''
            ]);

            return false;
        }

        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $accountSid . '/Messages.json';

        $params = [
            'From' => $from,
            'To' => $to,
            'ContentSid' => $contentSid
        ];

        if (!empty($variables)) {
            $params['ContentVariables'] = json_encode(
                (object)$variables,
                JSON_UNESCAPED_UNICODE
            );
        }

        $postFields = http_build_query($params);

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_USERPWD => $accountSid . ':' . $authToken,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        wa_log('TWILIO_SERVICE_TEMPLATE_SEND', [
            'to' => $to,
            'content_sid' => $contentSid,
            'variables' => $variables,
            'http_code' => $httpCode,
            'error' => $error,
            'response' => $response
        ]);

        if ($httpCode < 200 || $httpCode >= 300) {
            return false;
        }

        $decoded = json_decode((string)$response, true);
        $sidMensaje = is_array($decoded) ? trim((string)($decoded['sid'] ?? '')) : '';

        if ($mensajeHistorial !== '') {
            $metaHistorial = [
                'origen' => $origen,
                'content_sid' => $contentSid,
                'variables' => $variables
            ];

            if ($idCotizacionHistorial !== null && $idCotizacionHistorial > 0) {
                $metaHistorial['id_cotizacion'] = $idCotizacionHistorial;
            }

            if (function_exists('wa_save_last_bot_message')) {
                wa_save_last_bot_message(
                    $to,
                    $mensajeHistorial,
                    $metaHistorial,
                    $sidMensaje,
                    'BOT'
                );
            } else {
                self::guardarMensajeHistorial(
                    $to,
                    $mensajeHistorial,
                    $metaHistorial,
                    $idCotizacionHistorial,
                    $sidMensaje
                );
            }
        }

        return true;
    }

    public static function enviarTemplateAsistenciaAgenda(
        string $to,
        string $nombre,
        string $auto,
        string $fecha,
        string $hora,
        ?int $idCotizacion = null
    ): bool {
        $contentSid = ParametroSistemaService::obtener(
            'twilio',
            'template_asistencia_agenda',
            self::TEMPLATE_ASISTENCIA_AGENDA_DEFAULT
        );

        $fechaFmt = self::formatearFechaEs($fecha);
        $horaFmt = substr($hora, 0, 5);

        return self::enviarTemplate(
            $to,
            $contentSid,
            [
                '1' => $nombre,
                '2' => $auto,
                '3' => $fechaFmt,
                '4' => $horaFmt
            ],
            'template_asistencia_agenda',
            "Hola {$nombre}, te escribimos para confirmar tu asistencia a la agenda de inspección de tu vehículo {$auto}. Fecha: {$fechaFmt}. Hora: {$horaFmt}.",
            $idCotizacion
        );
    }

    private static function formatearFechaEs(string $fecha): string
    {
        $ts = strtotime($fecha);
        if (!$ts) {
            return $fecha;
        }

        $dias = [
            'Sunday' => 'Domingo',
            'Monday' => 'Lunes',
            'Tuesday' => 'Martes',
            'Wednesday' => 'Miércoles',
            'Thursday' => 'Jueves',
            'Friday' => 'Viernes',
            'Saturday' => 'Sábado',
        ];

        $dayName = date('l', $ts);

        return ($dias[$dayName] ?? $dayName) . ' ' . date('d/m/Y', $ts);
    }

    private static function db(): mysqli
    {
        if (function_exists('wa_db')) {
            return wa_db();
        }

        $cn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($cn->connect_errno) {
            throw new RuntimeException('Error conexión MySQL: ' . $cn->connect_error);
        }

        $cn->set_charset('utf8mb4');

        return $cn;
    }

    private static function limpiarEmojisParaHistorial(string $texto): string
    {
        $texto = preg_replace('/[\x{1F000}-\x{1FAFF}]/u', '', $texto);
        $texto = preg_replace('/[\x{FE00}-\x{FE0F}]/u', '', (string)$texto);
        $texto = preg_replace('/\x{200D}/u', '', (string)$texto);

        return trim((string)$texto);
    }

    private static function guardarMensajeHistorial(
        string $telefono,
        string $mensaje,
        array $meta,
        ?int $idCotizacion = null,
        string $sidMensaje = ''
    ): void {
        try {
            $cn = self::db();

            $whereCotizacion = '';
            if ($idCotizacion !== null && $idCotizacion > 0) {
                $whereCotizacion = ' AND id_cotizacion = ' . intval($idCotizacion);
            }

            $sqlConv = "
                SELECT id
                FROM whatsapp_conversaciones
                WHERE telefono = '" . $cn->real_escape_string($telefono) . "'
                {$whereCotizacion}
                ORDER BY id DESC
                LIMIT 1
            ";

            $rsConv = $cn->query($sqlConv);
            $conv = $rsConv ? $rsConv->fetch_assoc() : null;
            $idConversacion = intval($conv['id'] ?? 0);

            if ($idConversacion <= 0 && $whereCotizacion !== '') {
                $sqlConvFallback = "
                    SELECT id
                    FROM whatsapp_conversaciones
                    WHERE telefono = '" . $cn->real_escape_string($telefono) . "'
                    ORDER BY id DESC
                    LIMIT 1
                ";

                $rsConvFallback = $cn->query($sqlConvFallback);
                $convFallback = $rsConvFallback ? $rsConvFallback->fetch_assoc() : null;
                $idConversacion = intval($convFallback['id'] ?? 0);
            }

            if ($idConversacion <= 0) {
                if (function_exists('wa_log')) {
                    wa_log('TWILIO_SERVICE_HISTORIAL_SKIP', [
                        'telefono' => $telefono,
                        'id_cotizacion' => $idCotizacion,
                        'motivo' => 'sin_conversacion'
                    ]);
                }
                $cn->close();
                return;
            }

            $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);
            $sql = "
                INSERT INTO whatsapp_conversacion_mensajes
                (id_conversacion, telefono, direccion, emisor, mensaje, meta_json, sid_mensaje, fecha)
                VALUES (?, ?, 'SALIENTE', 'BOT', ?, ?, ?, NOW())
            ";

            $st = $cn->prepare($sql);
            if (!$st) {
                throw new RuntimeException('Error prepare historial Twilio: ' . $cn->error);
            }

            $mensajeGuardar = $mensaje;
            $sidGuardar = $sidMensaje !== '' ? $sidMensaje : null;
            $st->bind_param('issss', $idConversacion, $telefono, $mensajeGuardar, $metaJson, $sidGuardar);

            if (!$st->execute()) {
                $st->close();
                $mensajeGuardar = self::limpiarEmojisParaHistorial($mensaje);

                $st = $cn->prepare($sql);
                if (!$st) {
                    throw new RuntimeException('Error prepare historial Twilio fallback: ' . $cn->error);
                }

                $st->bind_param('issss', $idConversacion, $telefono, $mensajeGuardar, $metaJson, $sidGuardar);
                $st->execute();
            }

            $st->close();
            $cn->close();
        } catch (Throwable $e) {
            if (function_exists('wa_log')) {
                wa_log('TWILIO_SERVICE_HISTORIAL_ERROR', [
                    'telefono' => $telefono,
                    'id_cotizacion' => $idCotizacion,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    public static function enviarTemplateMotivoNoAgendar(string $to): bool
    {
        $contentSid = ParametroSistemaService::obtener(
            'twilio',
            'template_motivo_no_agendar'
        );

        return self::enviarTemplate(
            $to,
            $contentSid,
            [],
            'template_motivo_no_agendar',
            'Gracias por tu respuesta, nos gustaría saber el motivo de tu decisión.'
        );
    }

    public static function enviarTemplateRecordatorioPrecotizacion24Hs(
        string $to,
        string $nombre,
        string $vehiculo,
        ?int $idCotizacion = null
    ): bool {
        $contentSid = ParametroSistemaService::obtener(
            'twilio',
            'template_recordatorio_precotizacion_24hs'
        );

        return self::enviarTemplate(
            $to,
            $contentSid,
            [
                "1" => $nombre,
                "2" => $vehiculo
            ],
            'template_recordatorio_precotizacion_24hs',
            "¡Hola {$nombre}! 👋 Ayer te envié la cotización preliminar por tu {$vehiculo}. ¿Pudiste evaluarla? Avísame si querés agendar una revisión rápida sin costo o si por ahora preferís dejarlo en pausa.",
            $idCotizacion
        );
    }

    public static function enviarTemplateRecordatorioConfirmacionAgenda3Hs(
        string $to
    ): bool {
        $contentSid = ParametroSistemaService::obtener(
            'twilio',
            'template_recordatorio_confirmacion_agenda_3hs'
        );

        return self::enviarTemplate(
            $to,
            $contentSid,
            [],
            'template_recordatorio_confirmacion_agenda_3hs',
            'Recordatorio 3 hs para confirmar agenda'
        );
    }

    public static function enviarTemplateMotivoCancelacionAgenda(string $to): bool
    {
        $contentSid = ParametroSistemaService::obtener(
            'twilio',
            'template_motivo_recordatorio_no_agendar',
            ParametroSistemaService::obtener(
                'twilio',
                'motivo_recordatorio_no_agendar',
                'HXe9db678711733da0bb008b307d9ff19c'
            )
        );

        return self::enviarTemplate(
            $to,
            $contentSid,
            [],
            'template_motivo_recordatorio_no_agendar',
            'Gracias por tu respuesta, nos gustaría saber el motivo de tu decisión.'
        );
    }

    public static function enviarTemplateRecordatorioConfirmacionAgenda10Hs(
        string $to
    ): bool {
        $contentSid = ParametroSistemaService::obtener(
            'twilio',
            'template_recordatorio_confirmacion_agenda_10hs',
            ParametroSistemaService::obtener(
                'twilio',
                'template_recordatorio_confirmacion_agenda_3hs'
            )
        );

        return self::enviarTemplate(
            $to,
            $contentSid,
            [],
            'template_recordatorio_confirmacion_agenda_10hs',
            '¡Hola! Noté que no llegaste a confirmar tu agenda para la revisión. ¿Querés continuar con ella?'
        );
    }

    public static function enviarTemplateNoAsistioAgenda(
        string $to,
        ?int $idCotizacion = null
    ): bool {
        $contentSid = ParametroSistemaService::obtener(
            'twilio',
            'template_no_asistio_agenda'
        );

        return self::enviarTemplate(
            $to,
            $contentSid,
            [],
            'template_no_asistio_agenda',
            '¡Hola! Noté que no asististe a tu agenda para la revisión. ¿Querés coordinar una nueva agenda?',
            $idCotizacion
        );
    }

    public static function enviarTemplateMotivoRechazoTasacionFinal(string $to): bool
    {
        $contentSid = ParametroSistemaService::obtener(
            'twilio',
            'template_motivo_rechazo_tasacion_final'
        );

        if (!$contentSid) {
            $contentSid = ParametroSistemaService::obtener(
                'twilio',
                'motivo_no_agendamiento_tasacion_final'
            );
        }

        if (!$contentSid) {
            $contentSid = 'HXa222fc590b92c992a00799e12ddc527e';
        }

        return self::enviarTemplate(
            $to,
            $contentSid,
            [],
            'template_motivo_rechazo_tasacion_final',
            'Gracias por tu respuesta, nos gustaría saber el motivo de tu decisión.'
        );
    }

    public static function enviarTemplateRecordatorioTasacionFinal24Hs(
        string $to,
        string $nombre,
        string $vehiculo
    ): bool {
        $contentSid = ParametroSistemaService::obtener(
            'twilio',
            'template_recordatorio_tasacion_final_24hs'
        );

        return self::enviarTemplate(
            $to,
            $contentSid,
            [
                '1' => $nombre
            ],
            'template_recordatorio_tasacion_final_24hs',
            "Recordatorio 24 hs de tasación final para {$nombre} - {$vehiculo}"
        );
    }
}
