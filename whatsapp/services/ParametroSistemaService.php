<?php

class ParametroSistemaService
{
    public static function obtener(string $grupo, string $clave, string $default = ''): string
    {
        $cn = wa_db();

        $sql = "
            SELECT valor
            FROM parametros_sistema
            WHERE grupo = ?
              AND clave = ?
              AND activo = 1
            LIMIT 1
        ";

        $st = $cn->prepare($sql);

        if (!$st) {
            wa_log('PARAMETRO_SERVICE_PREPARE_ERROR', [
                'grupo' => $grupo,
                'clave' => $clave,
                'error' => $cn->error
            ]);

            $cn->close();
            return $default;
        }

        $st->bind_param('ss', $grupo, $clave);
        $st->execute();

        $rs = $st->get_result();
        $row = $rs ? $rs->fetch_assoc() : null;

        $st->close();
        $cn->close();

        if (!$row || trim((string)$row['valor']) === '') {
            return $default;
        }

        return (string)$row['valor'];
    }
}