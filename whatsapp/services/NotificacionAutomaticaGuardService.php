<?php

class NotificacionAutomaticaGuardService
{
    private const HORA_INICIO = '08:01:00';
    private const HORA_FIN = '21:00:00';

    private const TIPOS_CON_ENVIO = [
        'RECORDATORIO_PRECOTIZACION_24HS',
        'RECORDATORIO_CONFIRMACION_AGENDA_3HS',
        'RECORDATORIO_CONFIRMACION_AGENDA_10HS',
        'RECORDATORIO_ASISTENCIA_AGENDA_24HS',
        'RECORDATORIO_ASISTENCIA_AGENDA_48HS',
        'NOTIFICACION_NO_ASISTIO_AGENDA',
        'RECORDATORIO_TASACION_FINAL_24HS'
    ];

    private static function db(): mysqli
    {
        if (function_exists('wa_db')) {
            return wa_db();
        }

        throw new RuntimeException('No se pudo obtener conexión MySQL para el guardián de notificaciones');
    }

    public static function evaluar(array $notificacion): array
    {
        $id = intval($notificacion['id'] ?? 0);
        $idCotizacion = intval($notificacion['id_cotizacion'] ?? 0);
        $telefono = trim((string)($notificacion['telefono'] ?? ''));
        $tipo = trim((string)($notificacion['tipo_notificacion'] ?? ''));
        $creada = trim((string)($notificacion['created_at'] ?? ''));

        if ($id <= 0 || !self::siguePendiente($id)) {
            return self::resultado('OMITIR', 'La notificación ya no está pendiente');
        }

        if ($idCotizacion > 0) {
            $estadoCotizacion = self::estadoCotizacion($idCotizacion);

            if (
                intval($estadoCotizacion['estado_id'] ?? 0) === 5
                || strtoupper(trim((string)($estadoCotizacion['estado'] ?? ''))) === 'RECHAZADO'
            ) {
                return self::resultado(
                    'CANCELAR_TODAS',
                    'Kill switch: la cotización está RECHAZADA'
                );
            }

            if (
                !self::esRecordatorioAsistenciaAgenda($tipo)
                &&
                $creada !== ''
                && !empty($estadoCotizacion['fecha_mod'])
                && strtotime((string)$estadoCotizacion['fecha_mod']) > strtotime($creada)
            ) {
                return self::resultado(
                    'CANCELAR_TODAS',
                    'Kill switch: la cotización cambió de estado o fue modificada luego de programar el envío'
                );
            }
        }

        if (
            !self::esRecordatorioAsistenciaAgenda($tipo)
            && !self::permiteInteraccionLibreSinCancelar($tipo)
            && $telefono !== ''
            && $creada !== ''
            && self::huboInteraccionPosterior($telefono, $creada)
        ) {
            return self::resultado(
                $idCotizacion > 0 ? 'CANCELAR_TODAS' : 'CANCELAR',
                'Kill switch: hubo una respuesta del cliente o una intervención humana posterior'
            );
        }

        if (in_array($tipo, self::TIPOS_CON_ENVIO, true)) {
            $nuevaFecha = self::proximaFechaPermitida();

            if ($nuevaFecha !== null) {
                return [
                    'accion' => 'REPROGRAMAR',
                    'motivo' => 'Kill switch: envío fuera del horario comercial',
                    'fecha_programada' => $nuevaFecha
                ];
            }
        }

        return self::resultado('CONTINUAR', 'Controles de seguridad aprobados');
    }

    private static function siguePendiente(int $id): bool
    {
        $cn = self::db();
        $rs = $cn->query("
            SELECT estado
            FROM whatsapp_notificaciones_pendientes
            WHERE id = " . intval($id) . "
            LIMIT 1
        ");
        $row = $rs ? $rs->fetch_assoc() : null;
        $cn->close();

        return strtoupper(trim((string)($row['estado'] ?? ''))) === 'PENDIENTE';
    }

    private static function esRecordatorioAsistenciaAgenda(string $tipo): bool
    {
        return in_array($tipo, [
            'RECORDATORIO_ASISTENCIA_AGENDA_24HS',
            'RECORDATORIO_ASISTENCIA_AGENDA_48HS'
        ], true);
    }

    private static function permiteInteraccionLibreSinCancelar(string $tipo): bool
    {
        return in_array($tipo, [
            'RECORDATORIO_PRECOTIZACION_24HS'
        ], true);
    }

    private static function estadoCotizacion(int $idCotizacion): array
    {
        $cn = self::db();
        $rs = $cn->query("
            SELECT estado, estado_id, fecha_mod
            FROM cotizaciones_generadas
            WHERE id_cotizaciones_generadas = " . intval($idCotizacion) . "
            LIMIT 1
        ");
        $row = $rs ? $rs->fetch_assoc() : [];
        $cn->close();

        return is_array($row) ? $row : [];
    }

    private static function huboInteraccionPosterior(string $telefono, string $creada): bool
    {
        $cn = self::db();
        $telefonoSql = $cn->real_escape_string($telefono);
        $creadaSql = $cn->real_escape_string($creada);

        $rs = $cn->query("
            SELECT id
            FROM whatsapp_conversacion_mensajes
            WHERE telefono = '" . $telefonoSql . "'
            AND fecha > '" . $creadaSql . "'
            AND (
                direccion = 'ENTRANTE'
                OR (
                    direccion = 'SALIENTE'
                    AND (
                        emisor = 'HUMANO'
                        OR id_usuario IS NOT NULL
                    )
                )
            )
            LIMIT 1
        ");

        $huboInteraccion = $rs && $rs->num_rows > 0;
        $cn->close();

        return $huboInteraccion;
    }

    private static function proximaFechaPermitida(?DateTimeImmutable $ahora = null): ?string
    {
        $ahora = $ahora ?? new DateTimeImmutable('now', new DateTimeZone('America/Montevideo'));
        $hora = $ahora->format('H:i:s');

        if ($hora >= self::HORA_INICIO && $hora < self::HORA_FIN) {
            return null;
        }

        if ($hora < self::HORA_INICIO) {
            return $ahora->format('Y-m-d') . ' ' . self::HORA_INICIO;
        }

        return $ahora->modify('+1 day')->format('Y-m-d') . ' ' . self::HORA_INICIO;
    }

    private static function resultado(string $accion, string $motivo): array
    {
        return [
            'accion' => $accion,
            'motivo' => $motivo
        ];
    }
}
