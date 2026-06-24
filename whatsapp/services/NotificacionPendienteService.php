<?php

class NotificacionPendienteService
{
    private const TIPO_CONFIRMACION_AGENDA_24HS = 'RECORDATORIO_ASISTENCIA_AGENDA_24HS';
    private const TIPO_CONFIRMACION_AGENDA_48HS = 'RECORDATORIO_ASISTENCIA_AGENDA_48HS';
    private const TIPO_ABANDONO_RECORDATORIO_AGENDA_10HS = 'ABANDONO_RECORDATORIO_AGENDA_10HS';

    private static function db(): mysqli
    {
        global $db;

        if (isset($db) && is_object($db) && isset($db->link_id) && $db->link_id instanceof mysqli) {
            return $db->link_id;
        }

        if (function_exists('wa_db')) {
            return wa_db();
        }

        throw new RuntimeException('No se pudo obtener conexión MySQL para NotificacionPendienteService');
    }

    private static function log(string $tag, array $data = []): void
    {
        if (function_exists('wa_log')) {
            wa_log($tag, $data);
        } else {
            error_log($tag . ' ' . json_encode($data, JSON_UNESCAPED_UNICODE));
        }
    }

    public static function crear(
        ?int $idCotizacion,
        ?int $idAgenda,
        string $telefono,
        string $tipoNotificacion,
        string $origen,
        string $fechaProgramada,
        array $payload = [],
        string $observaciones = ''
    ): bool {
        $cn = self::db();

        $payloadJson = !empty($payload)
            ? json_encode($payload, JSON_UNESCAPED_UNICODE)
            : null;

        $sqlExiste = "
            SELECT id
            FROM whatsapp_notificaciones_pendientes
            WHERE estado = 'PENDIENTE'
            AND tipo_notificacion = '" . $cn->real_escape_string($tipoNotificacion) . "'
            AND id_cotizacion " . ($idCotizacion !== null ? "= " . intval($idCotizacion) : "IS NULL") . "
            AND id_agenda " . ($idAgenda !== null ? "= " . intval($idAgenda) : "IS NULL") . "
            LIMIT 1
        ";

        $rsExiste = $cn->query($sqlExiste);

        if ($rsExiste && $rsExiste->num_rows > 0) {
            self::log('NOTIFICACION_PENDIENTE_YA_EXISTE', [
                'id_cotizacion' => $idCotizacion,
                'tipo' => $tipoNotificacion
            ]);
            //$cn->close();
            return true;
        }

        $sqlInsert = "
            INSERT INTO whatsapp_notificaciones_pendientes
            (
                id_cotizacion,
                id_agenda,
                telefono,
                tipo_notificacion,
                origen,
                payload_json,
                fecha_programada,
                estado,
                observaciones,
                created_at
            )
            VALUES
            (
                " . ($idCotizacion !== null ? intval($idCotizacion) : "NULL") . ",
                " . ($idAgenda !== null ? intval($idAgenda) : "NULL") . ",
                '" . $cn->real_escape_string($telefono) . "',
                '" . $cn->real_escape_string($tipoNotificacion) . "',
                '" . $cn->real_escape_string($origen) . "',
                " . ($payloadJson !== null ? "'" . $cn->real_escape_string($payloadJson) . "'" : "NULL") . ",
                '" . $cn->real_escape_string($fechaProgramada) . "',
                'PENDIENTE',
                '" . $cn->real_escape_string($observaciones) . "',
                NOW()
            )
        ";

        $ok = $cn->query($sqlInsert);

        self::log('NOTIFICACION_PENDIENTE_CREAR', [
            'ok' => $ok,
            'id_insertado' => $cn->insert_id,
            'id_cotizacion' => $idCotizacion,
            'tipo' => $tipoNotificacion,
            'error' => $cn->error
        ]);

        // $cn->close();

        return (bool)$ok;
    }

    public static function cancelarPorCotizacionYTipo(
        int $idCotizacion,
        string $tipoNotificacion,
        string $observacion = ''
    ): bool {
        $cn = self::db();

        $sql = "
            UPDATE whatsapp_notificaciones_pendientes
            SET
                estado = 'CANCELADA',
                fecha_procesada = NOW(),
                observaciones = CONCAT(IFNULL(observaciones,''), '\n', '" . $cn->real_escape_string($observacion) . "')
            WHERE id_cotizacion = " . intval($idCotizacion) . "
            AND tipo_notificacion = '" . $cn->real_escape_string($tipoNotificacion) . "'
            AND estado = 'PENDIENTE'
        ";

        $ok = $cn->query($sql);

        self::log('NOTIFICACION_PENDIENTE_CANCELAR', [
            'ok' => $ok,
            'id_cotizacion' => $idCotizacion,
            'tipo' => $tipoNotificacion,
            'affected_rows' => $cn->affected_rows,
            'error' => $cn->error
        ]);

        // $cn->close();

        return (bool)$ok;
    }

    public static function marcarProcesada(int $id): bool
    {
        $cn = self::db();

        $sql = "
            UPDATE whatsapp_notificaciones_pendientes
            SET
                estado = 'PROCESADA',
                fecha_procesada = NOW()
            WHERE id = " . intval($id) . "
            LIMIT 1
        ";

        $ok = $cn->query($sql);

        self::log('NOTIFICACION_PENDIENTE_PROCESADA', [
            'ok' => $ok,
            'id' => $id,
            'affected_rows' => $cn->affected_rows,
            'error' => $cn->error
        ]);

        // $cn->close();

        return (bool)$ok;
    }

    public static function marcarError(int $id, string $error): bool
    {
        $cn = self::db();

        $sql = "
            UPDATE whatsapp_notificaciones_pendientes
            SET
                estado = 'ERROR',
                fecha_procesada = NOW(),
                observaciones = CONCAT(IFNULL(observaciones,''), '\n', '" . $cn->real_escape_string($error) . "')
            WHERE id = " . intval($id) . "
            LIMIT 1
        ";

        $ok = $cn->query($sql);

        self::log('NOTIFICACION_PENDIENTE_ERROR', [
            'ok' => $ok,
            'id' => $id,
            'error_msg' => $error,
            'db_error' => $cn->error
        ]);

        // $cn->close();

        return (bool)$ok;
    }

    public static function cancelarPorAgendaYTipo(
        int $idAgenda,
        string $tipoNotificacion,
        string $observacion = ''
    ): bool
    {
        $cn = self::db();

        $sql = "
            UPDATE whatsapp_notificaciones_pendientes
            SET
                estado = 'CANCELADA',
                fecha_procesada = NOW(),
                observaciones = CONCAT(IFNULL(observaciones,''), '\n', '" . $cn->real_escape_string($observacion) . "')
            WHERE id_agenda = " . intval($idAgenda) . "
            AND tipo_notificacion = '" . $cn->real_escape_string($tipoNotificacion) . "'
            AND estado = 'PENDIENTE'
        ";

        $ok = $cn->query($sql);

        self::log('NOTIFICACION_PENDIENTE_CANCELAR_AGENDA', [
            'ok' => $ok,
            'id_agenda' => $idAgenda,
            'tipo' => $tipoNotificacion,
            'affected_rows' => $cn->affected_rows,
            'error' => $cn->error
        ]);

        return (bool)$ok;
    }

    public static function programarConfirmacionAsistenciaAgenda(
        int $idCotizacion,
        int $idAgenda,
        string $telefono,
        string $fechaAgenda,
        string $horaAgenda,
        string $nombre = '',
        string $auto = '',
        ?string $fechaCreacion = null
    ): bool {
        if ($idAgenda <= 0 || trim($telefono) === '' || trim($fechaAgenda) === '' || trim($horaAgenda) === '') {
            return false;
        }

        $tz = new DateTimeZone('America/Montevideo');
        $agendaDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $fechaAgenda . ' ' . substr($horaAgenda, 0, 5) . ':00', $tz);

        if (!$agendaDt) {
            return false;
        }

        $creacionDt = null;
        if ($fechaCreacion !== null && trim($fechaCreacion) !== '') {
            $creacionDt = new DateTimeImmutable($fechaCreacion, $tz);
        }

        if (!$creacionDt) {
            $creacionDt = new DateTimeImmutable('now', $tz);
        }

        $segundosEntreCreacionYAgenda = $agendaDt->getTimestamp() - $creacionDt->getTimestamp();

        if ($segundosEntreCreacionYAgenda <= 24 * 60 * 60) {
            self::log('NOTIFICACION_AGENDA_CONFIRMACION_NO_PROGRAMADA', [
                'id_cotizacion' => $idCotizacion,
                'id_agenda' => $idAgenda,
                'motivo' => 'Agenda creada dentro de las 24 hs previas'
            ]);
            return true;
        }

        $tipo = self::TIPO_CONFIRMACION_AGENDA_24HS;
        $fechaProgramadaDt = $agendaDt->modify('-24 hours');
        $observaciones = 'Confirmación de asistencia 24 hs antes de la agenda';

        if ($segundosEntreCreacionYAgenda > 72 * 60 * 60) {
            $tipo = self::TIPO_CONFIRMACION_AGENDA_48HS;
            $fechaProgramadaDt = $agendaDt->modify('-48 hours');
            $observaciones = 'Confirmación de asistencia 48 hs antes de la agenda';
        }

        $payload = [
            'id_cotizacion' => $idCotizacion,
            'id_agenda' => $idAgenda,
            'fecha' => $fechaAgenda,
            'hora' => $horaAgenda,
            'nombre' => $nombre,
            'auto' => $auto
        ];

        return self::crear(
            $idCotizacion > 0 ? $idCotizacion : null,
            $idAgenda,
            $telefono,
            $tipo,
            'AGENDA',
            $fechaProgramadaDt->format('Y-m-d H:i:s'),
            $payload,
            $observaciones
        );
    }

    public static function programarAbandonoRecordatorioAgenda10Hs(
        int $idCotizacion,
        int $idAgenda,
        string $telefono,
        array $payload = []
    ): bool {
        if ($idCotizacion <= 0 || $idAgenda <= 0 || trim($telefono) === '') {
            return false;
        }

        $payload['id_cotizacion'] = $idCotizacion;
        $payload['id_agenda'] = $idAgenda;

        return self::crear(
            $idCotizacion,
            $idAgenda,
            $telefono,
            self::TIPO_ABANDONO_RECORDATORIO_AGENDA_10HS,
            'AGENDA',
            date('Y-m-d H:i:s', strtotime('+10 hours')),
            $payload,
            'Control interno: si no responde al recordatorio de agenda en 10 hs, enviar a carrito abandonado'
        );
    }

    public static function cancelar(int $id, string $observacion = ''): bool
    {
        $cn = self::db();

        $sql = "
            UPDATE whatsapp_notificaciones_pendientes
            SET
                estado = 'CANCELADA',
                fecha_procesada = NOW(),
                observaciones = CONCAT(IFNULL(observaciones,''), '\n', '" . $cn->real_escape_string($observacion) . "')
            WHERE id = " . intval($id) . "
            AND estado = 'PENDIENTE'
            LIMIT 1
        ";

        $ok = $cn->query($sql);

        self::log('NOTIFICACION_PENDIENTE_CANCELAR_ID', [
            'ok' => $ok,
            'id' => $id,
            'affected_rows' => $cn->affected_rows,
            'error' => $cn->error
        ]);

        return (bool)$ok;
    }

    public static function cancelarPendientesPorCotizacion(
        int $idCotizacion,
        string $observacion = ''
    ): bool {
        $cn = self::db();

        $sql = "
            UPDATE whatsapp_notificaciones_pendientes
            SET
                estado = 'CANCELADA',
                fecha_procesada = NOW(),
                observaciones = CONCAT(IFNULL(observaciones,''), '\n', '" . $cn->real_escape_string($observacion) . "')
            WHERE id_cotizacion = " . intval($idCotizacion) . "
            AND estado = 'PENDIENTE'
        ";

        $ok = $cn->query($sql);

        self::log('NOTIFICACIONES_PENDIENTES_CANCELAR_COTIZACION', [
            'ok' => $ok,
            'id_cotizacion' => $idCotizacion,
            'affected_rows' => $cn->affected_rows,
            'error' => $cn->error
        ]);

        return (bool)$ok;
    }

    public static function reprogramar(
        int $id,
        string $fechaProgramada,
        string $observacion = ''
    ): bool {
        $cn = self::db();

        $sql = "
            UPDATE whatsapp_notificaciones_pendientes
            SET
                fecha_programada = '" . $cn->real_escape_string($fechaProgramada) . "',
                observaciones = CONCAT(IFNULL(observaciones,''), '\n', '" . $cn->real_escape_string($observacion) . "')
            WHERE id = " . intval($id) . "
            AND estado = 'PENDIENTE'
            LIMIT 1
        ";

        $ok = $cn->query($sql);

        self::log('NOTIFICACION_PENDIENTE_REPROGRAMAR', [
            'ok' => $ok,
            'id' => $id,
            'fecha_programada' => $fechaProgramada,
            'affected_rows' => $cn->affected_rows,
            'error' => $cn->error
        ]);

        return (bool)$ok;
    }
}
