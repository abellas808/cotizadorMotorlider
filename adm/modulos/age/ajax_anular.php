<?php
ob_start();

require_once(__DIR__ . '/../../config/config.inc.php');

if (!isset($config['db_tablePrefix'])) {
    $config['db_tablePrefix'] = '';
}

require_once(__DIR__ . '/../../includes/database.php');
require_once(__DIR__ . '/../../includes/funciones.php');
require_once(__DIR__ . '/../../includes/class.phpmailer.php');

session_start();
require_once(__DIR__ . '/../../includes/chk_login.php');

header('Content-Type: application/json; charset=utf-8');

global $db;

$id = intval($_POST['id_agenda'] ?? 0);

if ($id <= 0) {
    echo json_encode(['ok' => false, 'mensaje' => 'ID inválido']);
    exit;
}

$agenda = $db->query_first("
    SELECT a.*, s.nombre AS sucursal_nombre, s.direccion AS sucursal_direccion
    FROM agendas a
    LEFT JOIN agenda_sucursal s ON s.id_sucursal = a.id_sucursal
    WHERE a.id_agenda = '{$id}'
    LIMIT 1
");

if (!$agenda) {
    echo json_encode(['ok' => false, 'mensaje' => 'Agenda no encontrada']);
    exit;
}

if (!empty($agenda['cancelado'])) {
    echo json_encode([
        'ok' => true,
        'mensaje' => 'La agenda ya estaba cancelada',
        'fecha' => $agenda['fecha'],
        'hora' => $agenda['hora']
    ]);
    exit;
}

$ok = $db->query("
    UPDATE agendas
    SET cancelado = 1,
        motivo_cancelacion = 'Anulada manualmente desde admin',
        fecha_cancelacion = NOW()
    WHERE id_agenda = '{$id}'
");

if (!$ok) {
    echo json_encode(['ok' => false, 'mensaje' => 'Error al anular']);
    exit;
}

// mail de cancelación, no rompe flujo
try {
    if (!empty($agenda['email'])) {
        $mail = new PHPMailer(true);
        $mail->isHTML(true);
        $mail->From = "noresponder@motorliderweb.com.uy";
        $mail->FromName = "MOTORLIDER";
        $mail->AddAddress($agenda['email'], $agenda['nombre'] ?? '');
        $mail->Subject = "Cancelación de agenda MOTORLIDER";
        $mail->Body =
            "Tu agenda fue cancelada.<br><br>" .
            "<strong>Fecha:</strong> " . date('d/m/Y', strtotime($agenda['fecha'])) . "<br>" .
            "<strong>Hora:</strong> " . $agenda['hora'] . "<br>" .
            (!empty($agenda['auto']) ? "<strong>Vehículo:</strong> " . $agenda['auto'] . "<br>" : "") .
            (!empty($agenda['sucursal_nombre']) ? "<strong>Sucursal:</strong> " . $agenda['sucursal_nombre'] . "<br>" : "") .
            (!empty($agenda['sucursal_direccion']) ? "<strong>Dirección:</strong> " . $agenda['sucursal_direccion'] . "<br>" : "") .
            "<br>Nos estaremos comunicando contigo para recoordinar si corresponde.";

        $mail->send();
    }
} catch (Exception $e) {
    // no cortar si falla el mail
}

echo json_encode([
    'ok' => true,
    'mensaje' => 'Agenda anulada correctamente',
    'fecha' => $agenda['fecha'],
    'hora' => $agenda['hora']
]);