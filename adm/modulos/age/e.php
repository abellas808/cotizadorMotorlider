<?php

if (!isset($sistema_iniciado)) exit();

if ($_SESSION[$config['codigo_unico']]['login_permisos']['age'] > 1) {

    $eliminados = $_POST['e_sel'] ?? [];

    if (is_array($eliminados) && count($eliminados) > 0) {
        foreach ($eliminados as $item) {
            $id = intval($item);

            if ($id > 0) {

                $logFile = __DIR__ . '/age_eliminar_agenda.log';

                $agenda = $db->query_first('
                    SELECT id_cotizacion
                    FROM agendas
                    WHERE id_agenda = "' . intval($id) . '"
                    LIMIT 1
                ');

                file_put_contents(
                    $logFile,
                    date('Y-m-d H:i:s') . " Agenda {$id}: " . print_r($agenda, true) . "\n",
                    FILE_APPEND
                );

                if ($agenda && intval($agenda['id_cotizacion']) > 0) {

                    $idCotizacion = intval($agenda['id_cotizacion']);

                    $sqlUpdate = "
                        UPDATE cotizaciones_generadas
                        SET
                            estado_id = 3, 
                            estado = 'COTIZADO_PRELIMINAR',
                            detalle_estado = 'Agenda eliminada desde módulo de agendas',
                            fecha_mod = NOW()
                        WHERE id_cotizaciones_generadas = {$idCotizacion}
                        LIMIT 1
                    ";

                    $ok = $db->query($sqlUpdate);

                    file_put_contents(
                        $logFile,
                        date('Y-m-d H:i:s') . " Cotización {$idCotizacion}: " . ($ok ? "OK" : "ERROR") . "\n{$sqlUpdate}\n",
                        FILE_APPEND
                    );
                }

                $db->query('DELETE FROM agendas WHERE id_agenda = "' . intval($id) . '";');
            }
        }
    }
}

$redirect = trim((string)($_POST['redirect_to'] ?? ''));

if ($redirect === '' || strpos($redirect, '?m=') !== 0) {
    $redirect = '?m=age_l';
}

// salida segura sin depender de header()
echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') . '">';
echo '<script>window.location.replace(' . json_encode($redirect) . ');</script>';
echo '</head><body></body></html>';
exit;