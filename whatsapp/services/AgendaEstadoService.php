<?php

class AgendaEstadoService
{
    public static function registrar(
        mysqli $conn,
        int $idAgenda,
        int $idEstado,
        string $observacion = '',
        string $usuario = 'Sistema'
    ): bool {
        $sql = "
            INSERT INTO agenda_historial_estados
            (id_agenda, id_estado, fecha, observacion, usuario)
            VALUES (?, ?, NOW(), ?, ?)
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log('AgendaEstadoService::registrar prepare error: ' . $conn->error);
            return false;
        }

        $stmt->bind_param(
            "iiss",
            $idAgenda,
            $idEstado,
            $observacion,
            $usuario
        );

        $ok = $stmt->execute();

        if (!$ok) {
            error_log('AgendaEstadoService::registrar execute error: ' . $stmt->error);
        }

        $stmt->close();

        return $ok;
    }
}