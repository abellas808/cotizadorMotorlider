<?php
require_once(__DIR__ . '/../../config/config.inc.php');
require_once(__DIR__ . '/../../includes/database.php');
require_once(__DIR__ . '/../../includes/funciones.php');

date_default_timezone_set('America/Montevideo');
header('Content-Type: application/json; charset=utf-8');

/**
 * CONFIG TWILIO
 */
const TWILIO_ACCOUNT_SID    = 'AC4a648c5c55de9d9b1f1f6601b14d4c4d';
const TWILIO_AUTH_TOKEN     = '58f767d26211d9d0c20ea687df00b4c3';
const TWILIO_WHATSAPP_FROM  = 'whatsapp:+59898057857';
const TWILIO_API_URL_FORMAT = 'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json';
const TWILIO_TEMPLATE_ASISTENCIA_AGENDA = 'HX4e31eca8dab14ba5842ffc5d78ec93d0';

/**
 * SETTINGS DEL PROCESO
 */
const SEGUNDOS_3_HORAS   = 10800;
const SEGUNDOS_6_HORAS   = 21600;
const SEGUNDOS_12_HORAS  = 43200;
const SEGUNDOS_24_HORAS  = 86400;
const SEGUNDOS_48_HORAS  = 172800;
const SEGUNDOS_72_HORAS  = 259200;

const TIPO_NOTIFICACION_RECORDATORIO_3H = 'recordatorio_3h';
const TIPO_NOTIFICACION_CONFIRMACION_24H = 'confirmacion_24h';
const TIPO_NOTIFICACION_CONFIRMACION_48H = 'confirmacion_48h';
const TIPO_NOTIFICACION_REINTENTO_CONFIRMACION_24H = 'reintento_confirmacion_24h';
const TIPO_NOTIFICACION_REINTENTO_CONFIRMACION_48H = 'reintento_confirmacion_48h';
const TIPO_NOTIFICACION_SIN_RESPUESTA_CONFIRMACION = 'sin_respuesta_confirmacion';

function logMensajeAutomatico(string $msg, array $extra = []): void
{
    $line = date('Y-m-d H:i:s') . ' [MENSAJES_AUTOMATICOS] ' . $msg;
    if (!empty($extra)) {
        $line .= ' ' . json_encode($extra, JSON_UNESCAPED_UNICODE);
    }
    error_log($line . PHP_EOL, 3, __DIR__ . '/mensajes_automaticos.log');
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

function existeColaCentralAgenda(mysqli $db, int $idAgenda, string $tipoAgenda): bool
{
    $tipoCola = '';

    if ($tipoAgenda === TIPO_NOTIFICACION_CONFIRMACION_24H) {
        $tipoCola = 'RECORDATORIO_ASISTENCIA_AGENDA_24HS';
    }

    if ($tipoAgenda === TIPO_NOTIFICACION_CONFIRMACION_48H) {
        $tipoCola = 'RECORDATORIO_ASISTENCIA_AGENDA_48HS';
    }

    if ($tipoCola === '') {
        return false;
    }

    $sql = "SELECT id
            FROM whatsapp_notificaciones_pendientes
            WHERE id_agenda = ?
              AND tipo_notificacion = ?
              AND estado IN ('PENDIENTE', 'PROCESADA', 'REPROGRAMADA')
            LIMIT 1";

    $st = $db->prepare($sql);
    if (!$st) {
        logMensajeAutomatico('ERROR_PREPARE_EXISTE_COLA_CENTRAL', ['error' => $db->error]);
        return false;
    }

    $st->bind_param('is', $idAgenda, $tipoCola);
    $st->execute();
    $rs = $st->get_result();
    $existe = $rs && $rs->num_rows > 0;

    if ($rs) {
        $rs->free();
    }
    $st->close();

    return $existe;
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

function actualizarConfirmacionAsistencia(mysqli $db, int $idAgenda, ?string $estado): bool
{
    $fecha = date('Y-m-d H:i:s');

    $sql = "UPDATE agendas
            SET confirmacion_asistencia = ?,
                fecha_confirmacion_asistencia = ?
            WHERE id_agenda = ?";

    $st = $db->prepare($sql);
    if (!$st) {
        logMensajeAutomatico('ERROR_PREPARE_ACTUALIZAR_CONFIRMACION', ['error' => $db->error]);
        return false;
    }

    $st->bind_param('ssi', $estado, $fecha, $idAgenda);
    $ok = $st->execute();

    if (!$ok) {
        logMensajeAutomatico('ERROR_EXECUTE_ACTUALIZAR_CONFIRMACION', [
            'error' => $st->error,
            'id_agenda' => $idAgenda,
            'estado' => $estado
        ]);
    }

    $st->close();
    return $ok;
}

function obtenerUltimaNotificacion(mysqli $db, int $idAgenda, string $tipo): ?array
{
    $sql = "SELECT id, fecha_envio, estado_envio, tipo_notificacion
            FROM whatsapp_agenda_notificaciones
            WHERE id_agenda = ?
              AND tipo_notificacion = ?
            ORDER BY id DESC
            LIMIT 1";

    $st = $db->prepare($sql);
    if (!$st) {
        logMensajeAutomatico('ERROR_PREPARE_OBTENER_ULTIMA_NOTIFICACION', ['error' => $db->error]);
        return null;
    }

    $st->bind_param('is', $idAgenda, $tipo);
    $st->execute();
    $rs = $st->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;

    if ($rs) {
        $rs->free();
    }
    $st->close();

    return $row ?: null;
}

function notificacionFueEnviadaHaceSegundos(mysqli $db, int $idAgenda, string $tipo, int $segundos): bool
{
    $noti = obtenerUltimaNotificacion($db, $idAgenda, $tipo);
    if (!$noti || empty($noti['fecha_envio'])) {
        return false;
    }

    $ts = strtotime((string)$noti['fecha_envio']);
    if (!$ts) {
        return false;
    }

    return (time() - $ts) >= $segundos;
}

function registrarInfoSinRespuesta(
    mysqli $db,
    int $idAgenda,
    string $telefono,
    string $fechaAgenda,
    string $horaAgenda,
    string $mensaje,
    array $extra = []
): bool {
    return registrarEnvio(
        $db,
        $idAgenda,
        $telefono,
        TIPO_NOTIFICACION_SIN_RESPUESTA_CONFIRMACION,
        $fechaAgenda,
        $horaAgenda,
        $mensaje,
        '',
        json_encode($extra, JSON_UNESCAPED_UNICODE),
        'INFO'
    );
}

function puedeEnviarRecordatorioSegunConfirmacion(array $agenda): bool
{
    $estado = trim((string)($agenda['confirmacion_asistencia'] ?? ''));

    return $estado === '' || $estado === 'CONFIRMADO';
}

function enviarWhatsapp(string $telefono, string $mensaje): array
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
        'raw' => $raw
    ];
}

function enviarWhatsappTemplateAsistenciaAgenda(string $telefono, array $agenda): array
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

    $nombre = trim((string)($agenda['nombre'] ?? ''));
    $auto = trim((string)($agenda['auto'] ?? ''));
    $fechaFmt = formatearFechaEs((string)($agenda['fecha'] ?? ''));
    $hora = substr((string)($agenda['hora'] ?? ''), 0, 5);

    $contentVariables = [
        '1' => $nombre,
        '2' => $auto,
        '3' => $fechaFmt,
        '4' => $hora
    ];

    $url = sprintf(TWILIO_API_URL_FORMAT, TWILIO_ACCOUNT_SID);

    $postFields = [
        'From' => TWILIO_WHATSAPP_FROM,
        'To' => $to,
        'ContentSid' => TWILIO_TEMPLATE_ASISTENCIA_AGENDA,
        'ContentVariables' => json_encode($contentVariables, JSON_UNESCAPED_UNICODE)
    ];

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

    $decoded = json_decode((string)$raw, true);

    logMensajeAutomatico('TWILIO_TEMPLATE_ASISTENCIA_AGENDA_RESPONSE', [
        'to' => $to,
        'http_code' => $httpCode,
        'error' => $curlError,
        'raw' => $raw
    ]);

    if ($curlError !== '') {
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'error' => $curlError,
            'raw' => $raw
        ];
    }

    $ok = ($httpCode >= 200 && $httpCode < 300 && is_array($decoded) && !empty($decoded['sid']));

    return [
        'ok' => $ok,
        'http_code' => $httpCode,
        'sid' => $decoded['sid'] ?? '',
        'status' => $decoded['status'] ?? '',
        'error' => $decoded['message'] ?? '',
        'raw' => $raw
    ];
}

function formatearFechaEs(string $fecha): string
{
    $ts = strtotime($fecha);
    if (!$ts) {
        return $fecha;
    }

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
    return ($dias[$dayName] ?? $dayName) . ' ' . date('d/m/Y', $ts);
}

function resolverDireccionAgenda(array $agenda): string
{
    $direccion = trim((string)($agenda['direccion'] ?? ''));

    if ($direccion === '' || strtolower($direccion) === 'n/a') {
        $direccion = 'Av. de las Américas 7868';
    }

    return $direccion;
}

function construirMensajeRecordatorio(array $agenda): string
{
    $nombre = trim((string)($agenda['nombre'] ?? ''));
    $hora = substr((string)($agenda['hora'] ?? ''), 0, 5);
    $fechaFmt = formatearFechaEs((string)($agenda['fecha'] ?? ''));
    $auto = trim((string)($agenda['auto'] ?? ''));
    $direccion = resolverDireccionAgenda($agenda);

    $saludo = $nombre !== '' ? "Hola {$nombre}," : "Hola,";

    $msg  = $saludo . "\n\n";
    $msg .= "Te recordamos que hoy tenés agendada la inspección de tu vehículo";
    if ($auto !== '') {
        $msg .= " ({$auto})";
    }
    $msg .= ".\n";
    $msg .= "🗓️ Fecha: {$fechaFmt}\n";
    $msg .= "⏰ Hora: {$hora}\n";
    $msg .= "📍 Dirección: {$direccion}  (Frente al Puente de las Américas)\n";
    $msg .= "https://n9.cl/1q4vx \n\n";
    $msg .= "Te esperamos en Motorlider.";

    return $msg;
}

function construirMensajeConfirmacion24h(array $agenda): string
{
    $nombre = trim((string)($agenda['nombre'] ?? ''));
    $hora = substr((string)($agenda['hora'] ?? ''), 0, 5);
    $fechaFmt = formatearFechaEs((string)($agenda['fecha'] ?? ''));
    $auto = trim((string)($agenda['auto'] ?? ''));
    $direccion = resolverDireccionAgenda($agenda);

    $saludo = $nombre !== '' ? "Hola {$nombre}," : "Hola,";

    $msg  = $saludo . "\n\n";
    $msg .= "Te escribimos para confirmar tu asistencia a la agenda de inspección";
    if ($auto !== '') {
        $msg .= " de tu vehículo ({$auto})";
    }
    $msg .= ".\n";
    $msg .= "🗓️ Fecha: {$fechaFmt}\n";
    $msg .= "⏰ Hora: {$hora}\n";
    $msg .= "📍 Dirección: {$direccion} (Frente al Puente de las Américas)\n";
    $msg .= " https://n9.cl/1q4vx \n\n";
    $msg .= "Por favor respondé SI o NO.";

    return $msg;
}

function construirMensajeConfirmacion48h(array $agenda): string
{
    return construirMensajeConfirmacion24h($agenda);
}

function esMismoDia(string $fecha): bool
{
    return $fecha === date('Y-m-d');
}

function segundosDesdeCreacion(?string $fechaCreacion): ?int
{
    if (!$fechaCreacion) {
        return null;
    }

    $ts = strtotime($fechaCreacion);
    if (!$ts) {
        return null;
    }

    return time() - $ts;
}

function agendaCreadaDentroDe24hPrevias(array $agenda): bool
{
    $fechaCreacion = (string)($agenda['fecha_creacion'] ?? '');
    if ($fechaCreacion === '') {
        return false;
    }

    $creacionTs = strtotime($fechaCreacion);
    $agendaTs   = strtotime((string)$agenda['fecha'] . ' ' . (string)$agenda['hora']);

    if (!$creacionTs || !$agendaTs) {
        return false;
    }

    $diff = $agendaTs - $creacionTs;
    return $diff > 0 && $diff <= SEGUNDOS_24_HORAS;
}

function agendaCreadaDentroDe48hPrevias(array $agenda): bool
{
    $fechaCreacion = (string)($agenda['fecha_creacion'] ?? '');
    if ($fechaCreacion === '') {
        return false;
    }

    $creacionTs = strtotime($fechaCreacion);
    $agendaTs   = strtotime((string)$agenda['fecha'] . ' ' . (string)$agenda['hora']);

    if (!$creacionTs || !$agendaTs) {
        return false;
    }

    $diff = $agendaTs - $creacionTs;
    return $diff > SEGUNDOS_24_HORAS && $diff <= SEGUNDOS_48_HORAS;
}

function agendaCreadaDentroDe72hPrevias(array $agenda): bool
{
    $fechaCreacion = (string)($agenda['fecha_creacion'] ?? '');
    if ($fechaCreacion === '') {
        return false;
    }

    $creacionTs = strtotime($fechaCreacion);
    $agendaTs   = strtotime((string)$agenda['fecha'] . ' ' . (string)$agenda['hora']);

    if (!$creacionTs || !$agendaTs) {
        return false;
    }

    $diff = $agendaTs - $creacionTs;
    return $diff > SEGUNDOS_24_HORAS && $diff <= SEGUNDOS_72_HORAS;
}

function agendaCreadaMasDe72hAntes(array $agenda): bool
{
    $fechaCreacion = (string)($agenda['fecha_creacion'] ?? '');
    if ($fechaCreacion === '') {
        return false;
    }

    $creacionTs = strtotime($fechaCreacion);
    $agendaTs   = strtotime((string)$agenda['fecha'] . ' ' . (string)$agenda['hora']);

    if (!$creacionTs || !$agendaTs) {
        return false;
    }

    $diff = $agendaTs - $creacionTs;
    return $diff > SEGUNDOS_72_HORAS;
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
            id_sucursal,
            fecha,
            hora,
            auto,
            nombre,
            email,
            telefono,
            direccion,
            cancelado,
            finalizada,
            fecha_creacion,
            confirmacion_asistencia,
            fecha_confirmacion_asistencia
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
    'agendas_evaluadas' => 0,
    'confirmaciones_48h_enviadas' => 0,
    'confirmaciones_24h_enviadas' => 0,
    'reintentos_48h_enviados' => 0,
    'reintentos_24h_enviados' => 0,
    'sin_respuesta_marcadas' => 0,
    'recordatorios_3h_enviados' => 0,
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
    $confirmacionAsistencia = trim((string)($row['confirmacion_asistencia'] ?? ''));

    $fechaHoraAgenda = strtotime($fecha . ' ' . $hora);
    if ($fechaHoraAgenda === false) {
        logMensajeAutomatico('AGENDA_FECHA_INVALIDA', [
            'id_agenda' => $idAgenda,
            'fecha' => $fecha,
            'hora' => $hora
        ]);
        continue;
    }

    $resumen['agendas_evaluadas']++;
    $faltan = $fechaHoraAgenda - $ahora;

    if ($debug) {
        $resumen['detalles'][] = [
            'id_agenda' => $idAgenda,
            'faltan_segundos' => $faltan,
            'fecha_creacion' => $row['fecha_creacion'] ?? null,
            'confirmacion_asistencia' => $confirmacionAsistencia
        ];
    }

    /**
     * NUEVA REGLA:
     * Si fue creada con más de 72h de anticipación,
     * enviar confirmación 48h antes.
     */
    if (agendaCreadaMasDe72hAntes($row)) {
        if ($faltan > 0 && $faltan <= SEGUNDOS_48_HORAS) {
            $tipo = TIPO_NOTIFICACION_CONFIRMACION_48H;

            if (!existeColaCentralAgenda($db, $idAgenda, $tipo) && !yaFueEnviado($db, $idAgenda, $tipo) && $confirmacionAsistencia === '') {
                $mensaje = construirMensajeConfirmacion48h($row);
                $envio = enviarWhatsappTemplateAsistenciaAgenda($telefono, $row);

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

                    actualizarConfirmacionAsistencia($db, $idAgenda, 'PENDIENTE');

                    $resumen['confirmaciones_48h_enviadas']++;

                    logMensajeAutomatico('CONFIRMACION_48H_ENVIADA', [
                        'id_agenda' => $idAgenda,
                        'telefono' => $telefono,
                        'sid' => $envio['sid'] ?? null
                    ]);
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

                    logMensajeAutomatico('CONFIRMACION_48H_ERROR', [
                        'id_agenda' => $idAgenda,
                        'telefono' => $telefono,
                        'http_code' => $envio['http_code'] ?? null,
                        'error' => $envio['error'] ?? null
                    ]);
                }
            } elseif (existeColaCentralAgenda($db, $idAgenda, $tipo) || yaFueEnviado($db, $idAgenda, $tipo)) {
                $resumen['omitidos_duplicado']++;
            }
        }
    }

    /**
     * REGLA 3:
     * Si fue creada dentro de las 72h previas a la cita,
     * pero no dentro de las 24h previas, enviar confirmación 24h antes.
     */
    if (agendaCreadaDentroDe72hPrevias($row)) {
        if ($faltan > 0 && $faltan <= SEGUNDOS_24_HORAS) {
            $tipo = TIPO_NOTIFICACION_CONFIRMACION_24H;

            if (!existeColaCentralAgenda($db, $idAgenda, $tipo) && !yaFueEnviado($db, $idAgenda, $tipo) && $confirmacionAsistencia === '') {
                $mensaje = construirMensajeConfirmacion24h($row);
                $envio = enviarWhatsappTemplateAsistenciaAgenda($telefono, $row);

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

                    actualizarConfirmacionAsistencia($db, $idAgenda, 'PENDIENTE');

                    $resumen['confirmaciones_24h_enviadas']++;

                    logMensajeAutomatico('CONFIRMACION_24H_ENVIADA', [
                        'id_agenda' => $idAgenda,
                        'telefono' => $telefono,
                        'sid' => $envio['sid'] ?? null
                    ]);
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

                    logMensajeAutomatico('CONFIRMACION_24H_ERROR', [
                        'id_agenda' => $idAgenda,
                        'telefono' => $telefono,
                        'http_code' => $envio['http_code'] ?? null,
                        'error' => $envio['error'] ?? null
                    ]);
                }
            } elseif (existeColaCentralAgenda($db, $idAgenda, $tipo) || yaFueEnviado($db, $idAgenda, $tipo)) {
                $resumen['omitidos_duplicado']++;
            }
        }
    }

    /**
     * REINTENTOS Y SIN RESPUESTA
     * Flujo 48h:
     *   - reintento una vez luego de 24h sin respuesta
     *   - si pasan 12h desde el reintento y sigue pendiente, marcar SIN_RESPUESTA
     *
     * Flujo 24h:
     *   - reintento una vez luego de 12h sin respuesta
     *   - si pasan 6h desde el reintento y sigue pendiente, marcar SIN_RESPUESTA
     */
    if ($confirmacionAsistencia === 'PENDIENTE') {
        if (yaFueEnviado($db, $idAgenda, TIPO_NOTIFICACION_CONFIRMACION_48H)) {
            if (
                !yaFueEnviado($db, $idAgenda, TIPO_NOTIFICACION_REINTENTO_CONFIRMACION_48H)
                && notificacionFueEnviadaHaceSegundos($db, $idAgenda, TIPO_NOTIFICACION_CONFIRMACION_48H, SEGUNDOS_24_HORAS)
                && $faltan > SEGUNDOS_3_HORAS
            ) {
                $mensaje = construirMensajeConfirmacion48h($row);
                $envio = enviarWhatsapp($telefono, $mensaje);

                if (!empty($envio['ok'])) {
                    registrarEnvio(
                        $db,
                        $idAgenda,
                        $telefono,
                        TIPO_NOTIFICACION_REINTENTO_CONFIRMACION_48H,
                        $fecha,
                        $hora,
                        $mensaje,
                        (string)($envio['sid'] ?? ''),
                        json_encode($envio, JSON_UNESCAPED_UNICODE),
                        'ENVIADO'
                    );

                    $resumen['reintentos_48h_enviados']++;

                    logMensajeAutomatico('REINTENTO_CONFIRMACION_48H_ENVIADO', [
                        'id_agenda' => $idAgenda,
                        'telefono' => $telefono,
                        'sid' => $envio['sid'] ?? null
                    ]);
                } else {
                    registrarEnvio(
                        $db,
                        $idAgenda,
                        $telefono,
                        TIPO_NOTIFICACION_REINTENTO_CONFIRMACION_48H,
                        $fecha,
                        $hora,
                        $mensaje,
                        (string)($envio['sid'] ?? ''),
                        json_encode($envio, JSON_UNESCAPED_UNICODE),
                        'ERROR'
                    );

                    $resumen['errores_envio']++;

                    logMensajeAutomatico('REINTENTO_CONFIRMACION_48H_ERROR', [
                        'id_agenda' => $idAgenda,
                        'telefono' => $telefono,
                        'http_code' => $envio['http_code'] ?? null,
                        'error' => $envio['error'] ?? null
                    ]);
                }
            }

            if (
                yaFueEnviado($db, $idAgenda, TIPO_NOTIFICACION_REINTENTO_CONFIRMACION_48H)
                && !yaFueEnviado($db, $idAgenda, TIPO_NOTIFICACION_SIN_RESPUESTA_CONFIRMACION)
                && notificacionFueEnviadaHaceSegundos($db, $idAgenda, TIPO_NOTIFICACION_REINTENTO_CONFIRMACION_48H, SEGUNDOS_12_HORAS)
            ) {
                if (actualizarConfirmacionAsistencia($db, $idAgenda, 'SIN_RESPUESTA')) {
                    registrarInfoSinRespuesta(
                        $db,
                        $idAgenda,
                        $telefono,
                        $fecha,
                        $hora,
                        'Cliente no respondió la confirmación de agenda (flujo 48h).',
                        [
                            'origen' => 'runner_mensajes_automaticos',
                            'flujo' => '48h',
                            'tipo_base' => TIPO_NOTIFICACION_CONFIRMACION_48H
                        ]
                    );

                    $confirmacionAsistencia = 'SIN_RESPUESTA';
                    $row['confirmacion_asistencia'] = 'SIN_RESPUESTA';
                    $resumen['sin_respuesta_marcadas']++;

                    logMensajeAutomatico('AGENDA_SIN_RESPUESTA_48H', [
                        'id_agenda' => $idAgenda,
                        'telefono' => $telefono
                    ]);
                }
            }
        }

        if (yaFueEnviado($db, $idAgenda, TIPO_NOTIFICACION_CONFIRMACION_24H)) {
            if (
                !yaFueEnviado($db, $idAgenda, TIPO_NOTIFICACION_REINTENTO_CONFIRMACION_24H)
                && notificacionFueEnviadaHaceSegundos($db, $idAgenda, TIPO_NOTIFICACION_CONFIRMACION_24H, SEGUNDOS_12_HORAS)
                && $faltan > SEGUNDOS_3_HORAS
            ) {
                $mensaje = construirMensajeConfirmacion24h($row);
                $envio = enviarWhatsapp($telefono, $mensaje);

                if (!empty($envio['ok'])) {
                    registrarEnvio(
                        $db,
                        $idAgenda,
                        $telefono,
                        TIPO_NOTIFICACION_REINTENTO_CONFIRMACION_24H,
                        $fecha,
                        $hora,
                        $mensaje,
                        (string)($envio['sid'] ?? ''),
                        json_encode($envio, JSON_UNESCAPED_UNICODE),
                        'ENVIADO'
                    );

                    $resumen['reintentos_24h_enviados']++;

                    logMensajeAutomatico('REINTENTO_CONFIRMACION_24H_ENVIADO', [
                        'id_agenda' => $idAgenda,
                        'telefono' => $telefono,
                        'sid' => $envio['sid'] ?? null
                    ]);
                } else {
                    registrarEnvio(
                        $db,
                        $idAgenda,
                        $telefono,
                        TIPO_NOTIFICACION_REINTENTO_CONFIRMACION_24H,
                        $fecha,
                        $hora,
                        $mensaje,
                        (string)($envio['sid'] ?? ''),
                        json_encode($envio, JSON_UNESCAPED_UNICODE),
                        'ERROR'
                    );

                    $resumen['errores_envio']++;

                    logMensajeAutomatico('REINTENTO_CONFIRMACION_24H_ERROR', [
                        'id_agenda' => $idAgenda,
                        'telefono' => $telefono,
                        'http_code' => $envio['http_code'] ?? null,
                        'error' => $envio['error'] ?? null
                    ]);
                }
            }

            if (
                yaFueEnviado($db, $idAgenda, TIPO_NOTIFICACION_REINTENTO_CONFIRMACION_24H)
                && !yaFueEnviado($db, $idAgenda, TIPO_NOTIFICACION_SIN_RESPUESTA_CONFIRMACION)
                && notificacionFueEnviadaHaceSegundos($db, $idAgenda, TIPO_NOTIFICACION_REINTENTO_CONFIRMACION_24H, SEGUNDOS_6_HORAS)
            ) {
                if (actualizarConfirmacionAsistencia($db, $idAgenda, 'SIN_RESPUESTA')) {
                    registrarInfoSinRespuesta(
                        $db,
                        $idAgenda,
                        $telefono,
                        $fecha,
                        $hora,
                        'Cliente no respondió la confirmación de agenda (flujo 24h).',
                        [
                            'origen' => 'runner_mensajes_automaticos',
                            'flujo' => '24h',
                            'tipo_base' => TIPO_NOTIFICACION_CONFIRMACION_24H
                        ]
                    );

                    $confirmacionAsistencia = 'SIN_RESPUESTA';
                    $row['confirmacion_asistencia'] = 'SIN_RESPUESTA';
                    $resumen['sin_respuesta_marcadas']++;

                    logMensajeAutomatico('AGENDA_SIN_RESPUESTA_24H', [
                        'id_agenda' => $idAgenda,
                        'telefono' => $telefono
                    ]);
                }
            }
        }
    }

    /**
     * REGLAS 1 y 2:
     * - mismo día => recordatorio 3h antes
     * - creada dentro de las 24h previas => solo recordatorio 3h antes
     * El recordatorio se envía solo si no requiere confirmación previa
     * o si la agenda ya quedó CONFIRMADA.
     */
    if (puedeEnviarRecordatorioSegunConfirmacion($row)) {
        if ($faltan > 0 && $faltan <= SEGUNDOS_3_HORAS) {
            $tipo = TIPO_NOTIFICACION_RECORDATORIO_3H;

            if (!yaFueEnviado($db, $idAgenda, $tipo)) {
                $mensaje = construirMensajeRecordatorio($row);
                $envio = enviarWhatsapp($telefono, $mensaje);

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

                    $resumen['recordatorios_3h_enviados']++;

                    logMensajeAutomatico('RECORDATORIO_3H_ENVIADO', [
                        'id_agenda' => $idAgenda,
                        'telefono' => $telefono,
                        'sid' => $envio['sid'] ?? null
                    ]);
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

                    logMensajeAutomatico('RECORDATORIO_3H_ERROR', [
                        'id_agenda' => $idAgenda,
                        'telefono' => $telefono,
                        'http_code' => $envio['http_code'] ?? null,
                        'error' => $envio['error'] ?? null
                    ]);
                }
            } else {
                $resumen['omitidos_duplicado']++;
            }
        }
    }
}

$q->free();
$db->close();

echo json_encode($resumen, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
