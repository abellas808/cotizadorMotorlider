<?php

class ConversationService
{
    public static function crearNueva(string $telefono, string $nombre = ''): int
    {
        $cn = wa_db();

        $estado = 'INICIO';
        $modo = 'BOT';
        $fecha = date('Y-m-d H:i:s');

        $datosJson = json_encode([
            'step' => 'inicio',
            'sub_step' => 'nueva_cotizacion'
        ], JSON_UNESCAPED_UNICODE);

        $sql = "
            INSERT INTO whatsapp_conversaciones
            (
                telefono,
                nombre,
                estado,
                step_actual,
                sub_step_actual,
                modo_atencion,
                id_cotizacion,
                datos_json,
                fecha_ultima_interaccion,
                fecha_alta,
                fecha_mod
            )
            VALUES
            (?, ?, ?, 'inicio', 'nueva_cotizacion', ?, NULL, ?, ?, ?, ?)
        ";

        $st = $cn->prepare($sql);

        if (!$st) {
            wa_log('CONVERSATION_SERVICE_CREAR_PREPARE_ERROR', [
                'telefono' => $telefono,
                'error' => $cn->error
            ]);

            $cn->close();
            return 0;
        }

        $st->bind_param(
            'ssssssss',
            $telefono,
            $nombre,
            $estado,
            $modo,
            $datosJson,
            $fecha,
            $fecha,
            $fecha
        );

        $ok = $st->execute();
        $id = $ok ? intval($cn->insert_id) : 0;

        wa_log('CONVERSATION_SERVICE_CREAR_NUEVA', [
            'telefono' => $telefono,
            'nombre' => $nombre,
            'ok' => $ok,
            'id_conversacion' => $id,
            'error' => $st->error
        ]);

        $st->close();
        $cn->close();

        return $id;
    }

    public static function actualizarCampos(string $telefono, array $fields): bool
    {
        if (empty($fields)) {
            return true;
        }

        $idConversacion = intval($fields['id_conversacion'] ?? 0);
        unset($fields['id_conversacion']);

        $cn = wa_db();

        $sets = [];
        $values = [];
        $types = '';

        foreach ($fields as $campo => $valor) {
            $sets[] = $campo . ' = ?';
            $values[] = $valor;
            $types .= 's';
        }

        if ($idConversacion > 0) {
            $sql = "UPDATE whatsapp_conversaciones SET " . implode(', ', $sets) . " WHERE id = ?";
            $types .= 'i';
            $values[] = $idConversacion;
        } else {
            $sql = "UPDATE whatsapp_conversaciones SET " . implode(', ', $sets) . " WHERE telefono = ? ORDER BY ID DESC LIMIT 1";
            $types .= 's';
            $values[] = $telefono;
        }

        $st = $cn->prepare($sql);

        if (!$st) {
            wa_log('CONVERSATION_SERVICE_UPDATE_PREPARE_ERROR', [
                'telefono' => $telefono,
                'id_conversacion' => $idConversacion,
                'error' => $cn->error
            ]);

            $cn->close();
            return false;
        }

        $bind = [];
        $bind[] = &$types;

        foreach ($values as $k => $v) {
            $bind[] = &$values[$k];
        }

        call_user_func_array([$st, 'bind_param'], $bind);

        $ok = $st->execute();

        wa_log('CONVERSATION_SERVICE_UPDATE', [
            'telefono' => $telefono,
            'id_conversacion' => $idConversacion,
            'ok' => $ok,
            'affected_rows' => $st->affected_rows,
            'fields' => array_keys($fields),
            'error' => $st->error
        ]);

        $st->close();
        $cn->close();

        return $ok;
    }

    public static function asociarCotizacion(int $idConversacion, int $idCotizacion): bool
    {
        if ($idConversacion <= 0 || $idCotizacion <= 0) {
            wa_log('CONVERSATION_SERVICE_ASOCIAR_SKIP', [
                'id_conversacion' => $idConversacion,
                'id_cotizacion' => $idCotizacion
            ]);
            return false;
        }

        $cn = wa_db();

        $sql = "
            UPDATE whatsapp_conversaciones
            SET
                id_cotizacion = ?,
                fecha_mod = NOW()
            WHERE id = ?
            LIMIT 1
        ";

        $st = $cn->prepare($sql);

        if (!$st) {
            wa_log('CONVERSATION_SERVICE_ASOCIAR_PREPARE_ERROR', [
                'id_conversacion' => $idConversacion,
                'id_cotizacion' => $idCotizacion,
                'error' => $cn->error
            ]);

            $cn->close();
            return false;
        }

        $st->bind_param('ii', $idCotizacion, $idConversacion);
        $ok = $st->execute();

        wa_log('CONVERSATION_SERVICE_ASOCIAR_COTIZACION', [
            'id_conversacion' => $idConversacion,
            'id_cotizacion' => $idCotizacion,
            'ok' => $ok,
            'affected_rows' => $st->affected_rows,
            'error' => $st->error
        ]);

        $st->close();
        $cn->close();

        return $ok;
    }

    public static function procesarNoQuiereAgendar(
        int $idCotizacion,
        int $idConversacion,
        string $telefono
    ): bool
    {
        // Actualizar estado conversación

        // Enviar template motivos
        TwilioMessageService::enviarTemplateMotivoNoAgendar(
            $telefono,
            $idCotizacion
        );

        return true;
    }
}
