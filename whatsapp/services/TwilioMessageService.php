<?php

class TwilioMessageService
{
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
        string $mensajeHistorial = ''
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

        if ($mensajeHistorial !== '' && function_exists('wa_save_last_bot_message')) {
            wa_save_last_bot_message(
                $to,
                $mensajeHistorial,
                [
                    'origen' => $origen,
                    'content_sid' => $contentSid,
                    'variables' => $variables
                ],
                '',
                'BOT'
            );
        }

        return true;
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
        string $vehiculo
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
            "Recordatorio 24 hs pre-cotización para {$nombre} - {$vehiculo}"
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
            'template_motivo_cancelacion_agenda',
            ParametroSistemaService::obtener(
                'twilio',
                'template_motivo_no_agendar'
            )
        );

        return self::enviarTemplate(
            $to,
            $contentSid,
            [],
            'template_motivo_cancelacion_agenda',
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
        string $to
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
            '¡Hola! Noté que no asististe a tu agenda para la revisión. ¿Querés coordinar una nueva agenda?'
        );
    }

    public static function enviarTemplateMotivoRechazoTasacionFinal(string $to): bool
    {
        $contentSid = ParametroSistemaService::obtener(
            'twilio',
            'template_motivo_rechazo_tasacion_final'
        );

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
                '1' => $nombre,
                '2' => $vehiculo
            ],
            'template_recordatorio_tasacion_final_24hs',
            "Recordatorio 24 hs de tasación final para {$nombre} - {$vehiculo}"
        );
    }
}
