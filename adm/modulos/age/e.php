<?php

if (!isset($sistema_iniciado)) exit();

if ($_SESSION[$config['codigo_unico']]['login_permisos']['age'] > 1) {

    $eliminados = $_POST['e_sel'] ?? [];

    if (is_array($eliminados) && count($eliminados) > 0) {
        foreach ($eliminados as $item) {
            $id = intval($item);

            if ($id > 0) {
                $db->query('DELETE FROM agendas WHERE id_agenda = "' . $id . '";');
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