<?php
ob_start();

require_once(__DIR__ . '/../../config/config.inc.php');

if (!isset($config['db_tablePrefix'])) {
    $config['db_tablePrefix'] = '';
}

require_once(__DIR__ . '/../../includes/database.php');
require_once(__DIR__ . '/../../includes/funciones.php');
require_once(__DIR__ . '/../../../apicotizador/services/MailService.php');

session_start();
require_once(__DIR__ . '/../../includes/chk_login.php');

header('Content-Type: application/json; charset=utf-8');

global $db;

$id      = intval($_POST['id'] ?? 0);
$email   = trim((string)($_POST['email'] ?? ''));
$mensaje = trim((string)($_POST['mensaje'] ?? ''));

if ($id <= 0 || $email === '' || $mensaje === '') {
    echo json_encode([
        'ok' => false,
        'mensaje' => 'Faltan datos.'
    ]);
    exit;
}

$elemento = $db->query_first('
    SELECT *
    FROM cotizaciones_generadas
    WHERE id_cotizaciones_generadas = "' . $id . '"
    LIMIT 1
');

if (!$elemento) {
    echo json_encode([
        'ok' => false,
        'mensaje' => 'Cotización no encontrada.'
    ]);
    exit;
}

$emailEsc   = $db->escape($email);
$mensajeEsc = $db->escape($mensaje);

$okUpdate = $db->query("
    UPDATE cotizaciones_generadas
    SET
        email = '{$emailEsc}',
        msg = '{$mensajeEsc}',
        estado = 'FINALIZADA',
        detalle_estado = ''
    WHERE id_cotizaciones_generadas = '{$id}'
");

if (!$okUpdate) {
    echo json_encode([
        'ok' => false,
        'mensaje' => 'No se pudo actualizar la cotización.'
    ]);
    exit;
}

echo json_encode(['ok' => true, 'mensaje' => 'Llego al ajax']);
exit;

try {
    $mail = new MailService();

    $mail->enviarConfirmacionCotizacion(
        [
            'nombre' => $elemento['nombre'] ?? '',
            'email' => $email,
            'telefono' => $elemento['telefono'] ?? '',
            'nombre_auto' => $elemento['auto'] ?? '',
            'brand' => $elemento['marca'] ?? '',
            'modelo' => $elemento['familia'] ?? '',
            'anio' => $elemento['anio'] ?? '',
            'km' => $elemento['kilometros'] ?? '',
            'valor_pretendido' => $elemento['precio_pretendido'] ?? ''
        ],
        [
            'ok' => true,
            'id_cotizacion' => $id,
            'msg' => $mensaje,
            'msg_cliente' => $mensaje,
            'comparables' => 0,
            'count' => 0,
            'min' => $elemento['valor_minimo'] ?? '',
            'max' => $elemento['valor_maximo'] ?? '',
            'avg' => $elemento['valor_promedio'] ?? '',
            'valor_minimo_motorlider' => $elemento['valor_minimo_autodata'] ?? '',
            'valor_maximo_motorlider' => $elemento['valor_maximo_autodata'] ?? '',
            'valor_promedio_motorlider' => $elemento['valor_promedio_autodata'] ?? '',
            'vpretendido_aplicado' => false,
            'valor_pretendido_cliente' => $elemento['precio_pretendido'] ?? ''
        ]
    );

    echo json_encode([
        'ok' => true,
        'mensaje' => 'Cotización enviada correctamente.'
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'mensaje' => 'Error al enviar: ' . $e->getMessage()
    ]);
}