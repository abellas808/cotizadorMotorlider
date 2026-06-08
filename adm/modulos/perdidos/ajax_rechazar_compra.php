<?php

$logFile = __DIR__ . '/rechazar_compra.log';

function perdidos_log($msg, $data = null) {
    global $logFile;

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;

    if ($data !== null) {
        $line .= ' ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
}

perdidos_log('ENTRO AL AJAX');

error_reporting(E_ALL);
ini_set('display_errors', 0);

ob_start();

require_once(__DIR__ . '/../../config/config.inc.php');

if (!isset($config['db_tablePrefix'])) {
    $config['db_tablePrefix'] = '';
}

require_once(__DIR__ . '/../../includes/database.php');
require_once(__DIR__ . '/../../includes/funciones.php');

session_start();

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Montevideo');

global $db;

$logFile = __DIR__ . '/rechazar_compra.log';


function perdidos_json($arr) {
    while (ob_get_level()) {
        ob_end_clean();
    }

    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

function perdidos_normalizar_telefono($telefono) {
    $telefono = trim((string)$telefono);

    if ($telefono === '') {
        return '';
    }

    if (strpos($telefono, 'whatsapp:+') === 0) {
        return $telefono;
    }

    $solo = preg_replace('/[^0-9]/', '', $telefono);

    if ($solo === '') {
        return '';
    }

    if (substr($solo, 0, 3) === '598') {
        return 'whatsapp:+' . $solo;
    }

    if (strlen($solo) === 9 && substr($solo, 0, 1) === '0') {
        return 'whatsapp:+598' . substr($solo, 1);
    }

    if (strlen($solo) === 8) {
        return 'whatsapp:+598' . $solo;
    }

    return 'whatsapp:+' . $solo;
}

function perdidos_enviar_twilio($to, $body) {

    // mismos datos que usa el ajax de cotizaciones
    $accountSid = 'AC4a648c5c55de9d9b1f1f6601b14d4c4d';
    $authToken  = '58f767d26211d9d0c20ea687df00b4c3';
    $from       = 'whatsapp:+59898057857';

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $accountSid . '/Messages.json';

    $post = http_build_query([
        'From' => $from,
        'To'   => $to,
        'Body' => $body
    ]);

    perdidos_log('TWILIO_REQUEST', [
        'url' => $url,
        'from' => $from,
        'to' => $to,
        'body' => $body
    ]);

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_USERPWD => $accountSid . ':' . $authToken,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    $decoded = json_decode((string)$raw, true);

    perdidos_log('TWILIO_RESPONSE', [
        'http' => $http,
        'curl_error' => $curlError,
        'raw' => $raw,
        'decoded' => $decoded
    ]);

    if ($curlError !== '') {
        return [
            'ok' => false,
            'mensaje' => 'Error cURL: ' . $curlError,
            'http' => $http
        ];
    }

    if ($http < 200 || $http >= 300) {
        return [
            'ok' => false,
            'mensaje' => $decoded['message'] ?? ('Twilio HTTP ' . $http),
            'http' => $http,
            'raw' => $raw
        ];
    }

    return [
        'ok' => true,
        'sid' => $decoded['sid'] ?? '',
        'status' => $decoded['status'] ?? '',
        'http' => $http
    ];
}

perdidos_log('INICIO', [
    'post' => $_POST,
    'get' => $_GET
]);

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    perdidos_log('ERROR_ID_INVALIDO', $_POST);
    perdidos_json([
        'ok' => false,
        'mensaje' => 'ID inválido.'
    ]);
}

$elemento = $db->query_first("
    SELECT *
    FROM cotizador_conversaciones_abandonadas
    WHERE id = '" . intval($id) . "'
    LIMIT 1
");

perdidos_log('ELEMENTO', $elemento);

if (!$elemento) {
    perdidos_json([
        'ok' => false,
        'mensaje' => 'Conversación perdida no encontrada.'
    ]);
}

$telefono = perdidos_normalizar_telefono($elemento['telefono'] ?? '');

perdidos_log('TELEFONO_NORMALIZADO', [
    'original' => $elemento['telefono'] ?? '',
    'normalizado' => $telefono
]);

if ($telefono === '') {
    perdidos_json([
        'ok' => false,
        'mensaje' => 'Teléfono inválido.'
    ]);
}

$idConversacion = intval($elemento['id_conversacion'] ?? 0);

if ($idConversacion <= 0) {
    $rowConv = $db->query_first("
        SELECT id_conversacion
        FROM whatsapp_conversacion_mensajes
        WHERE telefono = '" . $db->escape($telefono) . "'
        ORDER BY id DESC
        LIMIT 1
    ");

    $idConversacion = intval($rowConv['id_conversacion'] ?? 0);
}

perdidos_log('ID_CONVERSACION', [
    'id_conversacion' => $idConversacion
]);

$mensaje = "Lamentablemente en este momento no estamos en condiciones de comprar su vehículo ya que tenemos el segmento completo.\n\n"
    . "Quedamos a las órdenes!";

$envio = perdidos_enviar_twilio($telefono, $mensaje);

if (!$envio['ok']) {
    perdidos_log('ERROR_ENVIO_TWILIO', $envio);

    perdidos_json([
        'ok' => false,
        'mensaje' => 'No se pudo enviar WhatsApp: ' . ($envio['mensaje'] ?? 'Error desconocido.')
    ]);
}

$sid = (string)($envio['sid'] ?? '');

if ($idConversacion > 0) {
    $meta = json_encode([
        'origen' => 'backend_rechazo_compra_perdidos',
        'id_conversacion_perdida' => intval($id),
        'accion' => 'RECHAZAR_COMPRA_PERDIDOS'
    ], JSON_UNESCAPED_UNICODE);

    $sqlMsg = "
        INSERT INTO whatsapp_conversacion_mensajes
        (
            id_conversacion,
            telefono,
            direccion,
            emisor,
            mensaje,
            meta_json,
            sid_mensaje,
            fecha
        )
        VALUES
        (
            '" . intval($idConversacion) . "',
            '" . $db->escape($telefono) . "',
            'SALIENTE',
            'BOT',
            '" . $db->escape($mensaje) . "',
            '" . $db->escape($meta) . "',
            '" . $db->escape($sid) . "',
            NOW()
        )
    ";

    $okMsg = $db->query($sqlMsg);

    perdidos_log('INSERT_MENSAJE', [
        'ok' => $okMsg ? 1 : 0,
        'sql' => $sqlMsg
    ]);
} else {
    perdidos_log('SIN_ID_CONVERSACION_NO_INSERTA_MENSAJE');
}

$sqlUpd = "
    UPDATE cotizador_conversaciones_abandonadas
    SET
        estado = 'CERRADO',
        observaciones = CONCAT(
            IFNULL(observaciones, ''),
            '\nMensaje de rechazo enviado desde Conversaciones Perdidas.'
        ),
        fecha_ultima_gestion = NOW()
    WHERE id = '" . intval($id) . "'
    LIMIT 1
";

$okUpd = $db->query($sqlUpd);

perdidos_log('UPDATE_CONVERSACION_PERDIDA', [
    'ok' => $okUpd ? 1 : 0,
    'sql' => $sqlUpd
]);

perdidos_json([
    'ok' => true,
    'mensaje' => 'Mensaje enviado correctamente.',
    'sid' => $sid,
    'id_conversacion' => $idConversacion
]);