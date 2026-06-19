<?php

class CarritoAbandonadoService
{
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
                    'Motivo informado por WhatsApp'
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
            'ssis',
            $mensajeCliente,
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

    public static function actualizarMotivoCancelacionAgenda(
        int $idCotizacion,
        string $mensajeCliente,
        string $motivoAbandono
    ): bool {
        if ($idCotizacion <= 0) {
            return false;
        }

        return self::actualizarMotivoPendiente(
            $idCotizacion,
            'AGENDA',
            [
                'CANCELO_AGENDA_PENDIENTE_MOTIVO',
                'NO_RESPONDIO_CONFIRMACION_AGENDA',
                'NO_ASISTIO_AGENDA'
            ],
            $mensajeCliente,
            $motivoAbandono
        );
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
