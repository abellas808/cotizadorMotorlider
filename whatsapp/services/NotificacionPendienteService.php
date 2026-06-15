<?php

class NotificacionPendienteService
{
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
        global $db;

        $payloadJson = !empty($payload)
            ? json_encode($payload, JSON_UNESCAPED_UNICODE)
            : null;

        // Evita duplicar el mismo pendiente
        $existe = $db->query_first("
            SELECT id
            FROM whatsapp_notificaciones_pendientes
            WHERE estado = 'PENDIENTE'
            AND tipo_notificacion = '" . $db->escape($tipoNotificacion) . "'
            AND id_cotizacion " . ($idCotizacion !== null ? "= " . intval($idCotizacion) : "IS NULL") . "
            AND id_agenda " . ($idAgenda !== null ? "= " . intval($idAgenda) : "IS NULL") . "
            LIMIT 1
        ");

        if (!empty($existe)) {
            return true;
        }

        $db->query("
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
                '" . $db->escape($telefono) . "',
                '" . $db->escape($tipoNotificacion) . "',
                '" . $db->escape($origen) . "',
                " . ($payloadJson !== null ? "'" . $db->escape($payloadJson) . "'" : "NULL") . ",
                '" . $db->escape($fechaProgramada) . "',
                'PENDIENTE',
                '" . $db->escape($observaciones) . "',
                NOW()
            )
        ");

        return true;
    }

    public static function cancelarPorCotizacionYTipo(
        int $idCotizacion,
        string $tipoNotificacion,
        string $observacion = ''
    ): bool {
       
        $cn = wa_db();

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

        $cn->close();

        return (bool)$ok;
    }

    public static function marcarProcesada(int $id): bool
    {
        global $db;

        $db->query("
            UPDATE whatsapp_notificaciones_pendientes
            SET
                estado = 'PROCESADA',
                fecha_procesada = NOW()
            WHERE id = " . intval($id) . "
            LIMIT 1
        ");

        return true;
    }

    public static function marcarError(int $id, string $error): bool
    {
        global $db;

        $db->query("
            UPDATE whatsapp_notificaciones_pendientes
            SET
                estado = 'ERROR',
                fecha_procesada = NOW(),
                observaciones = CONCAT(IFNULL(observaciones,''), '\n', '" . $db->escape($error) . "')
            WHERE id = " . intval($id) . "
            LIMIT 1
        ");

        return true;
    }
}