<?php
require_once(__DIR__ . '/../../config/config.inc.php');
require_once(__DIR__ . '/../../includes/database.php');
require_once(__DIR__ . '/../../includes/funciones.php');

date_default_timezone_set('America/Montevideo');
header('Content-Type: application/json; charset=utf-8');

/**
 * CONFIG TWILIO
 * Ajustar si cambia la cuenta o sender.
 */
const TWILIO_ACCOUNT_SID    = 'AC4a648c5c55de9d9b1f1f6601b14d4c4d';
const TWILIO_AUTH_TOKEN     = '58f767d26211d9d0c20ea687df00b4c3';
const TWILIO_WHATSAPP_FROM  = 'whatsapp:+59898057857';
const TWILIO_API_URL_FORMAT = 'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json';

/**
 * SETTINGS DEL PROCESO
 */
const RECORDATORIO_SEGUNDOS_ANTES = 10800; // 3 horas
const TIPO_NOTIFICACION_RECORDATORIO_3H = 'recordatorio_3h';

function logMensajeAutomatico(string $msg, array $extra = []): void
{
    $line = date('Y-m-d H:i:s') . ' [MENSAJES_AUTOMATICOS] ' . $msg;
    if (!empty($extra)) {
        $line .= ' ' . json_encode($extra, JSON_UNESCAPED_UNICODE);
    }
    error_log($line . PHP_EOL, 3, __DIR__ . '/mensajes_automaticos.log');
}

function yaFueEnviado(mysqli $db, int $idAgenda, string $tipo): bool
{
    $sql = "SELECT id
            FROM whatsapp_agenda_notificaciones
            WHERE id_agenda = ?
              AND tipo_notificacion = ?
            LIMIT 1";

    $st = $db->prepare($sql);
    if (!$st) {
        logMensajeAutomatico('ERROR_PREPARE_YA_FUE_ENVIADO', ['error' => $db->error]);
        return false;
    }

    $st->bind_param('is', $idAgenda, $tipo);
    $st->execute();
    $rs = $st->get_result();
    $ok = $rs && $rs->num_rows > 0;

    if ($rs) {
        $rs->free();
    }
    $st->close();

    return $ok;
}

function registrarEnvio(
    mysqli $db,
    int $idAgenda,
    string $telefono,
    string $tipo,
    string $fechaAgenda,
    string $horaAgenda,
    string $mensajeEnviado = '',
    string $sidMensaje = '',
    string $respuestaApi = '',
    string $estado = 'ENVIADO'
): bool {
    $sql = "INSERT INTO whatsapp_agenda_notificaciones
            (
                id_agenda,
                telefono,
                tipo_notificacion,
                fecha_agenda,
                hora_agenda,
                fecha_envio,
                estado_envio,
                mensaje_enviado,
                sid_mensaje,
                respuesta_api
            )
            VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)";

    $st = $db->prepare($sql);
    if (!$st) {
        logMensajeAutomatico('ERROR_PREPARE_REGISTRAR_ENVIO', ['error' => $db->error]);
        return false;
    }

    $st->bind_param(
        'issssssss',
        $idAgenda,
        $telefono,
        $tipo,
        $fechaAgenda,
        $horaAgenda,
        $estado,
        $mensajeEnviado,
        $sidMensaje,
        $respuestaApi
    );

    $ok = $st->execute();
    if (!$ok) {
        logMensajeAutomatico('ERROR_EXECUTE_REGISTRAR_ENVIO', ['error' => $st->error]);
    }

    $st->close();
    return $ok;
}

function normalizarTelefonoWhatsapp(string $telefono): string
{
    $telefono = trim($telefono);

    if ($telefono === '') {
        return '';
    }

    if (stripos($telefono, 'whatsapp:') === 0) {
        return $telefono;
    }

    if ($telefono[0] !== '+') {
        $telefono = '+' . $telefono;
    }

    return 'whatsapp:' . $telefono;
}

function enviarWhatsappRecordatorio(string $telefono, string $mensaje): array
{
    $to = normalizarTelefonoWhatsapp($telefono);

    if ($to === '') {
        return [
            'ok' => false,
            'http_code' => 0,
            'error' => 'Teléfono vacío',
            'raw' => ''
        ];
    }

    $url = sprintf(TWILIO_API_URL_FORMAT, TWILIO_ACCOUNT_SID);

    $postFields = [
        'From' => TWILIO_WHATSAPP_FROM,
        'To'   => $to,
        'Body' => $mensaje
    ];

    logMensajeAutomatico('TWILIO_REQUEST', [
        'url' => $url,
        'to' => $to,
        'from' => TWILIO_WHATSAPP_FROM,
        'body' => $mensaje
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postFields),
        CURLOPT_USERPWD => TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError !== '') {
        logMensajeAutomatico('TWILIO_CURL_ERROR', [
            'to' => $to,
            'error' => $curlError,
            'http_code' => $httpCode
        ]);

        return [
            'ok' => false,
            'http_code' => $httpCode,
            'error' => $curlError,
            'raw' => ''
        ];
    }

    $decoded = json_decode((string)$raw, true);

    logMensajeAutomatico('TWILIO_RESPONSE', [
        'to' => $to,
        'http_code' => $httpCode,
        'raw' => $raw
    ]);

    $ok = ($httpCode >= 200 && $httpCode < 300 && is_array($decoded) && !empty($decoded['sid']));

    if ($ok) {
        return [
            'ok' => true,
            'http_code' => $httpCode,
            'sid' => $decoded['sid'],
            'status' => $decoded['status'] ?? '',
            'raw' => $raw
        ];
    }

    return [
        'ok' => false,
        'http_code' => $httpCode,
        'error' => $decoded['message'] ?? 'Twilio no devolvió éxito',
        'raw' => $raw,
        'sid' => $decoded['sid'] ?? ''
    ];
}

function construirMensajeRecordatorio(array $agenda): string
{
    $nombre = trim((string)($agenda['nombre'] ?? ''));
    $hora = substr((string)($agenda['hora'] ?? ''), 0, 5);
    $fecha = (string)($agenda['fecha'] ?? '');
    $auto = trim((string)($agenda['auto'] ?? ''));
    $direccion = trim((string)($agenda['direccion'] ?? ''));

    if ($direccion === '' || strtolower($direccion) === 'n/a') {
        $direccion = 'Av. de las Américas 7868';
    }

    $fechaFmt = $fecha;
    if ($fecha !== '') {
        $ts = strtotime($fecha);
        if ($ts) {
            $dias = [
                'Sunday' => 'Domingo',
                'Monday' => 'Lunes',
                'Tuesday' => 'Martes',
                'Wednesday' => 'Miércoles',
                'Thursday' => 'Jueves',
                'Friday' => 'Viernes',
                'Saturday' => 'Sábado',
            ];
            $dayName = date('l', $ts);
            $fechaFmt = ($dias[$dayName] ?? $dayName) . ' ' . date('d/m/Y', $ts);
        }
    }

    $saludo = $nombre !== '' ? "Hola {$nombre}," : "Hola,";

    $msg  = $saludo . "\n\n";
    $msg .= "Te recordamos que hoy tenés agendada la inspección de tu vehículo";
    if ($auto !== '') {
        $msg .= " ({$auto})";
    }
    $msg .= ".\n";
    $msg .= "Fecha: {$fechaFmt}\n";
    $msg .= "Hora: {$hora}\n";
    $msg .= "Dirección: {$direccion}\n\n";
    $msg .= "Te esperamos en Av. de las Américas 7868.";

    return $msg;
}

$debug = isset($_GET['debug']) && $_GET['debug'] == '1';

$db = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_database']);
if ($db->connect_errno) {
    echo json_encode([
        'ok' => false,
        'error' => 'Error de conexión DB: ' . $db->connect_error
    ]);
    exit;
}
$db->set_charset('utf8');

$ahora = time();

$sql = "SELECT
            id_agenda,
            fecha,
            hora,
            nombre,
            telefono,
            auto,
            direccion,
            cancelado,
            finalizada
        FROM agendas
        WHERE cancelado = 0
          AND finalizada = 0
          AND CONCAT(fecha, ' ', hora) >= NOW()
        ORDER BY fecha ASC, hora ASC";

$q = $db->query($sql);

if (!$q) {
    echo json_encode([
        'ok' => false,
        'error' => 'Error consultando agendas: ' . $db->error
    ]);
    exit;
}

$resumen = [
    'ok' => true,
    'debug' => $debug,
    'agendas_encontradas' => 0,
    'agendas_en_ventana' => 0,
    'enviados' => 0,
    'omitidos_duplicado' => 0,
    'errores_envio' => 0,
    'detalles' => []
];

while ($row = $q->fetch_assoc()) {
    $resumen['agendas_encontradas']++;

    $idAgenda = (int)$row['id_agenda'];
    $telefono = trim((string)$row['telefono']);
    $fecha = (string)$row['fecha'];
    $hora = (string)$row['hora'];

    $fechaHoraAgenda = strtotime($fecha . ' ' . $hora);
    if ($fechaHoraAgenda === false) {
        logMensajeAutomatico('AGENDA_FECHA_INVALIDA', [
            'id_agenda' => $idAgenda,
            'fecha' => $fecha,
            'hora' => $hora
        ]);
        continue;
    }

    $faltan = $fechaHoraAgenda - $ahora;

    if ($faltan <= 0 || $faltan > RECORDATORIO_SEGUNDOS_ANTES) {
        if ($debug) {
            $resumen['detalles'][] = [
                'id_agenda' => $idAgenda,
                'accion' => 'fuera_de_ventana',
                'faltan_segundos' => $faltan
            ];
        }
        continue;
    }

    $resumen['agendas_en_ventana']++;

    $tipo = TIPO_NOTIFICACION_RECORDATORIO_3H;

    if (yaFueEnviado($db, $idAgenda, $tipo)) {
        $resumen['omitidos_duplicado']++;

        logMensajeAutomatico('OMITIDO_DUPLICADO', [
            'id_agenda' => $idAgenda,
            'telefono' => $telefono,
            'tipo' => $tipo
        ]);

        if ($debug) {
            $resumen['detalles'][] = [
                'id_agenda' => $idAgenda,
                'accion' => 'omitido_duplicado'
            ];
        }
        continue;
    }

    $mensaje = construirMensajeRecordatorio($row);
    $envio = enviarWhatsappRecordatorio($telefono, $mensaje);

    if (!empty($envio['ok'])) {
        registrarEnvio(
            $db,
            $idAgenda,
            $telefono,
            $tipo,
            $fecha,
            $hora,
            $mensaje,
            (string)($envio['sid'] ?? ''),
            json_encode($envio, JSON_UNESCAPED_UNICODE),
            'ENVIADO'
        );

        $resumen['enviados']++;

        logMensajeAutomatico('ENVIADO_OK', [
            'id_agenda' => $idAgenda,
            'telefono' => $telefono,
            'sid' => $envio['sid'] ?? null,
            'status' => $envio['status'] ?? null
        ]);

        if ($debug) {
            $resumen['detalles'][] = [
                'id_agenda' => $idAgenda,
                'accion' => 'enviado',
                'sid' => $envio['sid'] ?? null
            ];
        }
    } else {
        registrarEnvio(
            $db,
            $idAgenda,
            $telefono,
            $tipo,
            $fecha,
            $hora,
            $mensaje,
            (string)($envio['sid'] ?? ''),
            json_encode($envio, JSON_UNESCAPED_UNICODE),
            'ERROR'
        );

        $resumen['errores_envio']++;

        logMensajeAutomatico('ENVIO_ERROR', [
            'id_agenda' => $idAgenda,
            'telefono' => $telefono,
            'http_code' => $envio['http_code'] ?? null,
            'error' => $envio['error'] ?? null,
            'raw' => $envio['raw'] ?? null
        ]);

        if ($debug) {
            $resumen['detalles'][] = [
                'id_agenda' => $idAgenda,
                'accion' => 'error_envio',
                'http_code' => $envio['http_code'] ?? null,
                'error' => $envio['error'] ?? null
            ];
        }
    }
}

$q->free();
$db->close();

echo json_encode($resumen, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
