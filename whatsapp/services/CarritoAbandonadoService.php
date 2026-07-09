<?php

class CarritoAbandonadoService
{
    private static function obtenerIdPendientePorCotizacion($cn, int $idCotizacion): int
    {
        if ($idCotizacion <= 0) {
            return 0;
        }

        $sql = "
            SELECT id
            FROM carrito_abandonado
            WHERE id_cotizacion = ?
              AND estado = 'PENDIENTE'
            ORDER BY id DESC
            LIMIT 1
        ";

        $st = $cn->prepare($sql);
        if (!$st) {
            return 0;
        }

        $st->bind_param('i', $idCotizacion);
        $st->execute();
        $rs = $st->get_result();
        $row = $rs ? $rs->fetch_assoc() : null;

        if ($rs) {
            $rs->free();
        }
        $st->close();

        return intval($row['id'] ?? 0);
    }

    private static function cerrarPendientesDuplicados($cn, int $idCotizacion, int $idActivo, string $motivo): void
    {
        if ($idCotizacion <= 0 || $idActivo <= 0) {
            return;
        }

        $sql = "
            UPDATE carrito_abandonado
            SET
                estado = 'CERRADO',
                fecha_ultima_gestion = NOW(),
                usuario_ultima_gestion = 'Alan',
                observaciones = CONCAT(
                    IFNULL(observaciones, ''),
                    IF(IFNULL(observaciones, '') = '', '', '\n'),
                    ?
                )
            WHERE id_cotizacion = ?
              AND id <> ?
              AND estado = 'PENDIENTE'
        ";

        $st = $cn->prepare($sql);
        if (!$st) {
            return;
        }

        $st->bind_param('sii', $motivo, $idCotizacion, $idActivo);
        $st->execute();
        $st->close();
    }

    public static function actualizarMotivoPendiente(
        int $idCotizacion,
        string $origenAbandono,
        array $motivosPendientes,
        string $mensajeCliente,
        string $motivoAbandono
    ): bool {
        if ($idCotizacion <= 0 || empty($motivosPendientes)) {
            return false;
        }

        $cn = wa_db();
        $cn->set_charset("utf8mb4");

        $motivosSql = [];
        foreach ($motivosPendientes as $motivoPendiente) {
            $motivosSql[] = "'" . $cn->real_escape_string((string)$motivoPendiente) . "'";
        }

        $sql = "
            UPDATE carrito_abandonado
            SET
                mensaje_cliente = ?,
                motivo_abandono = ?,
                fecha_respuesta = NOW(),
                observaciones = CONCAT(
                    IFNULL(observaciones, ''),
                    IF(IFNULL(observaciones, '') = '', '', '\n'),
                    'Motivo informado por WhatsApp: ',
                    ?
                )
            WHERE id_cotizacion = ?
              AND origen_abandono = ?
              AND estado = 'PENDIENTE'
              AND motivo_abandono IN (" . implode(',', $motivosSql) . ")
            ORDER BY id DESC
            LIMIT 1
        ";

        $st = $cn->prepare($sql);
        if (!$st) {
            $cn->close();
            return false;
        }

        $st->bind_param(
            'sssis',
            $mensajeCliente,
            $motivoAbandono,
            $motivoAbandono,
            $idCotizacion,
            $origenAbandono
        );

        $ok = $st->execute();
        $actualizados = $st->affected_rows;

        $st->close();
        $cn->close();

        return $ok && $actualizados > 0;
    }

    private static function actualizarMotivoPendienteSinOrigen(
        int $idCotizacion,
        array $motivosPendientes,
        string $mensajeCliente,
        string $motivoAbandono,
        string $origenAbandono
    ): bool {
        if ($idCotizacion <= 0 || empty($motivosPendientes)) {
            return false;
        }

        $cn = wa_db();
        $cn->set_charset("utf8mb4");

        $motivosSql = [];
        foreach ($motivosPendientes as $motivoPendiente) {
            $motivosSql[] = "'" . $cn->real_escape_string((string)$motivoPendiente) . "'";
        }

        $sql = "
            UPDATE carrito_abandonado
            SET
                mensaje_cliente = ?,
                motivo_abandono = ?,
                origen_abandono = ?,
                fecha_respuesta = NOW(),
                observaciones = CONCAT(
                    IFNULL(observaciones, ''),
                    IF(IFNULL(observaciones, '') = '', '', '\n'),
                    'Motivo informado por WhatsApp: ',
                    ?
                )
            WHERE id_cotizacion = ?
              AND estado = 'PENDIENTE'
              AND motivo_abandono IN (" . implode(',', $motivosSql) . ")
            ORDER BY id DESC
            LIMIT 1
        ";

        $st = $cn->prepare($sql);
        if (!$st) {
            $cn->close();
            return false;
        }

        $st->bind_param(
            'ssssi',
            $mensajeCliente,
            $motivoAbandono,
            $origenAbandono,
            $motivoAbandono,
            $idCotizacion
        );

        $ok = $st->execute();
        $actualizados = $st->affected_rows;

        $st->close();

        if ($ok && $actualizados > 0) {
            $idActivo = self::obtenerIdPendientePorCotizacion($cn, $idCotizacion);
            self::cerrarPendientesDuplicados(
                $cn,
                $idCotizacion,
                $idActivo,
                'Cerrado automÃ¡ticamente: la cotizaciÃ³n ya tiene un punto de abandono mÃ¡s reciente.'
            );
        }

        $cn->close();

        return $ok && $actualizados > 0;
    }

    public static function actualizarMotivoCancelacionAgenda(
        int $idCotizacion,
        string $mensajeCliente,
        string $motivoAbandono
    ): bool {
        if ($idCotizacion <= 0) {
            return false;
        }

        return self::actualizarMotivoPendienteSinOrigen(
            $idCotizacion,
            [
                'CANCELO_AGENDA_PENDIENTE_MOTIVO',
                'NO_RESPONDIO_CONFIRMACION_AGENDA',
                'NO_CONFIRMACION_AGENDA',
                'NO_CONFIRMACION_AGENDA_AUTO',
                'NO_CONFIRMA_AGENDA',
                'NO_CONFIRMA_AGENDA_AUTO',
                'NO_ASISTIO_AGENDA'
            ],
            $mensajeCliente,
            $motivoAbandono,
            'AGENDA'
        );
    }

    public static function cerrarPendientePorConfirmacionAgenda(int $idCotizacion): bool
    {
        if ($idCotizacion <= 0) {
            return false;
        }

        $cn = wa_db();
        $cn->set_charset("utf8mb4");

        $motivos = [
            'NO_CONFIRMA_AGENDA_AUTO',
            'NO_CONFIRMACION_AGENDA_AUTO'
        ];

        $motivosSql = [];
        foreach ($motivos as $motivo) {
            $motivosSql[] = "'" . $cn->real_escape_string($motivo) . "'";
        }

        $sql = "
            UPDATE carrito_abandonado
            SET
                estado = 'CERRADO',
                fecha_ultima_gestion = NOW(),
                usuario_ultima_gestion = 'Alan',
                observaciones = CONCAT(
                    IFNULL(observaciones, ''),
                    IF(IFNULL(observaciones, '') = '', '', '\n'),
                    'Cerrado automáticamente: cliente confirmó agenda luego del recordatorio.'
                )
            WHERE id_cotizacion = ?
              AND origen_abandono = 'AGENDA'
              AND estado = 'PENDIENTE'
              AND motivo_abandono IN (" . implode(',', $motivosSql) . ")
        ";

        $st = $cn->prepare($sql);
        if (!$st) {
            $cn->close();
            return false;
        }

        $st->bind_param('i', $idCotizacion);
        $ok = $st->execute();
        $actualizados = $st->affected_rows;

        $st->close();
        $cn->close();

        return $ok && $actualizados > 0;
    }

    public static function existePendiente(
        int $idCotizacion,
        string $motivoAbandono,
        string $origenAbandono = 'AGENDA'
    ): bool {
        if ($idCotizacion <= 0) {
            return false;
        }

        $cn = wa_db();
        $sql = "
            SELECT id
            FROM carrito_abandonado
            WHERE id_cotizacion = ?
              AND motivo_abandono = ?
              AND origen_abandono = ?
              AND estado = 'PENDIENTE'
            LIMIT 1
        ";

        $st = $cn->prepare($sql);
        if (!$st) {
            $cn->close();
            return false;
        }

        $st->bind_param('iss', $idCotizacion, $motivoAbandono, $origenAbandono);
        $st->execute();
        $rs = $st->get_result();
        $existe = $rs && $rs->num_rows > 0;

        if ($rs) {
            $rs->free();
        }
        $st->close();
        $cn->close();

        return $existe;
    }

    public static function registrar(
        int $idCotizacion,
        int $idConversacion,
        string $telefono,
        string $mensajeCliente,
        string $motivoAbandono = 'SIN_DEFINIR',
        string $origenAbandono = 'WHATSAPP_BOT',
        string $usuario = ''
    ): void {

        wa_log('CARRITO_INTENTO', [
            'id_cotizacion' => $idCotizacion,
            'id_conversacion' => $idConversacion,
            'telefono' => $telefono,
            'mensaje_cliente' => $mensajeCliente,
            'motivo_abandono' => $motivoAbandono,
            'origen_abandono' => $origenAbandono
        ]);

        $cn = wa_db();
        $cn->set_charset("utf8mb4");

        $cot = null;

        if ($idCotizacion > 0) {
            $sqlCot = "
                SELECT
                    nombre,
                    email,
                    marca,
                    familia AS modelo,
                    anio,
                    kilometros,
                    tasacion_final
                FROM cotizaciones_generadas
                WHERE id_cotizaciones_generadas = ?
                LIMIT 1
            ";

            $stCot = $cn->prepare($sqlCot);

            if ($stCot) {
                $stCot->bind_param('i', $idCotizacion);
                $stCot->execute();
                $resCot = $stCot->get_result();
                $cot = $resCot ? $resCot->fetch_assoc() : null;
                $stCot->close();
            } else {
                wa_log('CARRITO_COT_PREPARE_ERROR', [
                    'error' => $cn->error,
                    'sql' => $sqlCot
                ]);
            }
        }

        $nombre = (string)($cot['nombre'] ?? '');
        $email = (string)($cot['email'] ?? '');
        $marca = (string)($cot['marca'] ?? '');
        $modelo = (string)($cot['modelo'] ?? '');
        $anio = intval($cot['anio'] ?? 0);
        $kilometros = intval($cot['kilometros'] ?? 0);
        $tasacionFinal = floatval($cot['tasacion_final'] ?? 0);

        $idPendiente = self::obtenerIdPendientePorCotizacion($cn, $idCotizacion);

        if ($idPendiente > 0) {
            $sqlUpdate = "
                UPDATE carrito_abandonado
                SET
                    id_conversacion = ?,
                    telefono = ?,
                    nombre = ?,
                    email = ?,
                    marca = ?,
                    modelo = ?,
                    anio = ?,
                    kilometros = ?,
                    tasacion_final = ?,
                    mensaje_cliente = ?,
                    motivo_abandono = ?,
                    origen_abandono = ?,
                    fecha_respuesta = NOW(),
                    usuario = ?,
                    fecha_alta = NOW(),
                    observaciones = CONCAT(
                        IFNULL(observaciones, ''),
                        IF(IFNULL(observaciones, '') = '', '', '\n'),
                        'Actualizado automÃ¡ticamente: ya existÃ­a un carrito pendiente para esta cotizaciÃ³n.'
                    )
                WHERE id = ?
                  AND estado = 'PENDIENTE'
                LIMIT 1
            ";

            $stUpdate = $cn->prepare($sqlUpdate);

            if (!$stUpdate) {
                wa_log('CARRITO_UPDATE_PREPARE_ERROR', [
                    'error' => $cn->error,
                    'sql' => $sqlUpdate,
                    'id_cotizacion' => $idCotizacion,
                    'id_pendiente' => $idPendiente
                ]);
                $cn->close();
                return;
            }

            $stUpdate->bind_param(
                'isssssiidssssi',
                $idConversacion,
                $telefono,
                $nombre,
                $email,
                $marca,
                $modelo,
                $anio,
                $kilometros,
                $tasacionFinal,
                $mensajeCliente,
                $motivoAbandono,
                $origenAbandono,
                $usuario,
                $idPendiente
            );

            if (!$stUpdate->execute()) {
                wa_log('CARRITO_UPDATE_ERROR', [
                    'error' => $stUpdate->error,
                    'errno' => $stUpdate->errno,
                    'id_cotizacion' => $idCotizacion,
                    'id_pendiente' => $idPendiente,
                    'telefono' => $telefono
                ]);
            } else {
                wa_log('CARRITO_UPDATE_OK', [
                    'id_actualizado' => $idPendiente,
                    'id_cotizacion' => $idCotizacion,
                    'telefono' => $telefono,
                    'motivo_abandono' => $motivoAbandono,
                    'origen_abandono' => $origenAbandono
                ]);
                self::cerrarPendientesDuplicados(
                    $cn,
                    $idCotizacion,
                    $idPendiente,
                    'Cerrado automÃ¡ticamente: la cotizaciÃ³n ya tiene un punto de abandono mÃ¡s reciente.'
                );
            }

            $stUpdate->close();
            $cn->close();
            return;
        }

        $sql = "
            INSERT INTO carrito_abandonado
            (
                id_cotizacion,
                id_conversacion,
                telefono,
                nombre,
                email,
                marca,
                modelo,
                anio,
                kilometros,
                tasacion_final,
                mensaje_cliente,
                motivo_abandono,
                origen_abandono,
                fecha_respuesta,
                usuario,
                estado,
                observaciones,
                fecha_ultima_gestion,
                usuario_ultima_gestion,
                fecha_alta
            )
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, 'PENDIENTE', '', NULL, '', NOW())
        ";

        $st = $cn->prepare($sql);

        if (!$st) {
            wa_log('CARRITO_INSERT_PREPARE_ERROR', [
                'error' => $cn->error,
                'sql' => $sql
            ]);
            $cn->close();
            return;
        }

        $st->bind_param(
            'iisssssiidssss',
            $idCotizacion,
            $idConversacion,
            $telefono,
            $nombre,
            $email,
            $marca,
            $modelo,
            $anio,
            $kilometros,
            $tasacionFinal,
            $mensajeCliente,
            $motivoAbandono,
            $origenAbandono,
            $usuario
        );

        if (!$st->execute()) {
            wa_log('CARRITO_INSERT_ERROR', [
                'error' => $st->error,
                'errno' => $st->errno,
                'id_cotizacion' => $idCotizacion,
                'telefono' => $telefono
            ]);
        } else {
            wa_log('CARRITO_INSERT_OK', [
                'id_insertado' => $st->insert_id,
                'id_cotizacion' => $idCotizacion,
                'telefono' => $telefono
            ]);
        }

        $st->close();
        $cn->close();
    }
}
