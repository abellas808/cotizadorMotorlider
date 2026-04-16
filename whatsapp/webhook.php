<?php
declare(strict_types=1);

date_default_timezone_set('America/Montevideo');

/**
 * Webhook WhatsApp Twilio - Motorlider
 *
 * Flujo:
 * 1. BOT pide datos
 * 2. BOT cotiza
 * 3. BOT envía mensaje final y pasa a HUMANO
 * 4. HUMANO responde tasación desde/hasta
 * 5. Si cliente escribe AGENDAR o responde SI
 * 6. vuelve BOT y sigue agenda
 */

// =========================
// CONFIG
// =========================
const TWILIO_AUTH_TOKEN = '58f767d26211d9d0c20ea687df00b4c3';
const COTIZADOR_BASE_URL = 'https://carplay.uy/apicotizador/cotizadorPublico/';

const DB_HOST = 'localhost';
const DB_NAME = 'marcos2022_api';
const DB_USER = 'marcos2022_usr_api';
const DB_PASS = '_eT4AjJ79~tX]*h)J5';

// =========================
// PATHS
// =========================
function wa_log_file(): string
{
    return __DIR__ . '/logs/whatsapp_webhook_' . date('Y-m-d') . '.log';
}

// =========================
// HELPERS GENERALES
// =========================
function wa_log(string $tag, array $data = []): void
{
    $line = date('Y-m-d H:i:s') . ' [' . $tag . '] ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    @file_put_contents(wa_log_file(), $line . PHP_EOL, FILE_APPEND);
}

function get_request_headers_lower(): array
{
    $headers = [];

    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0) {
            $name = strtolower(str_replace('_', '-', substr($key, 5)));
            $headers[$name] = $value;
        }
    }

    return $headers;
}

function validate_twilio_signature(string $authToken): bool
{
    $headers = get_request_headers_lower();
    $twilioSignature = $headers['x-twilio-signature'] ?? '';
    $accountSid = (string)($_POST['AccountSid'] ?? '');
    $url = 'https://carplay.uy/whatsapp/webhook.php';

    if ($authToken === '') {
        wa_log('SIGNATURE_FAIL', [
            'reason' => 'auth token vacío',
            'account_sid' => $accountSid,
            'url' => $url
        ]);
        return false;
    }

    if ($twilioSignature === '') {
        wa_log('SIGNATURE_FAIL', [
            'reason' => 'header ausente',
            'account_sid' => $accountSid,
            'url' => $url
        ]);
        return false;
    }

    $params = $_POST;
    ksort($params);

    $data = $url;
    foreach ($params as $key => $value) {
        $data .= $key . $value;
    }

    $hash = base64_encode(hash_hmac('sha1', $data, $authToken, true));
    $ok = hash_equals($hash, $twilioSignature);

    wa_log('SIGNATURE_CHECK', [
        'ok' => $ok,
        'account_sid' => $accountSid,
        'url' => $url,
        'expected' => $hash,
        'received' => $twilioSignature
    ]);

    return $ok;
}

function twiml_message(string $message): void
{
    header('Content-Type: text/xml; charset=UTF-8');

    $safe = htmlspecialchars($message, ENT_XML1 | ENT_QUOTES, 'UTF-8');

    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<Response>';
    echo '<Message>' . $safe . '</Message>';
    echo '</Response>';
    exit;
}

function twiml_empty(): void
{
    header('Content-Type: text/xml; charset=UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<Response></Response>';
    exit;
}

function body_to_lower(string $text): string
{
    return function_exists('mb_strtolower')
        ? mb_strtolower($text, 'UTF-8')
        : strtolower($text);
}

function wa_normalizar_texto(string $txt): string
{
    $txt = trim($txt);
    $txt = function_exists('mb_strtolower') ? mb_strtolower($txt, 'UTF-8') : strtolower($txt);

    $reemplazos = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n'
    ];

    $txt = strtr($txt, $reemplazos);
    $txt = preg_replace('/[^a-z0-9\s]/u', ' ', $txt);
    $txt = preg_replace('/\s+/', ' ', (string)$txt);

    return trim((string)$txt);
}

function normalize_yes_no(string $text): ?string
{
    $v = body_to_lower(trim($text));

    if (in_array($v, ['si', 'sí', 's', '1'], true)) {
        return 'si';
    }

    if (in_array($v, ['no', 'n', '0'], true)) {
        return 'no';
    }

    return null;
}

function normalize_tipo_venta(string $text): ?string
{
    $v = body_to_lower(trim($text));

    if (in_array($v, ['1', 'venta', 'venta contado', 'contado'], true)) {
        return 'venta_contado';
    }

    if (in_array($v, ['2', 'entrega', 'permuta', 'entrega como forma de pago'], true)) {
        return 'entrega_forma_pago';
    }

    return null;
}

function format_tipo_venta_label(string $tipo): string
{
    if ($tipo === 'venta_contado') {
        return 'Venta contado';
    }

    if ($tipo === 'entrega_forma_pago') {
        return 'Entrega como forma de pago';
    }

    return $tipo;
}

function is_valid_email_simple(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function wa_version_es_ninguna(string $texto): bool
{
    $v = wa_normalizar_texto($texto);

    return in_array($v, [
        'ninguna',
        'ninguno',
        'no se',
        'nose',
        'ns',
        'sin version',
        'sin versión',
        'no la se',
        'no la sé'
    ], true);
}

function wa_es_agendar(string $texto): bool
{
    $v = wa_normalizar_texto($texto);

    return in_array($v, [
        'agendar',
        'quiero agendar',
        'deseo agendar',
        'coordinar inspeccion',
        'coordinar inspección'
    ], true);
}

function wa_respuesta_es_si(string $texto): bool
{
    $v = wa_normalizar_texto($texto);

    return in_array($v, [
        'si', 'sí', 's', 'quiero agendar', 'agendar', 'si quiero', 'dale', 'ok', 'bueno'
    ], true);
}

function wa_respuesta_es_no(string $texto): bool
{
    $v = wa_normalizar_texto($texto);

    return in_array($v, [
        'no', 'n', 'no gracias', 'ahora no', 'despues', 'después'
    ], true);
}

function wa_es_atras(string $texto): bool
{
    $v = wa_normalizar_texto($texto);
    return in_array($v, ['atras', 'atras menu', 'volver', 'volver atras'], true);
}

function wa_es_cancelar_agenda(string $texto): bool
{
    $v = wa_normalizar_texto($texto);
    return in_array($v, ['cancelar', 'salir', 'menu', 'inicio'], true);
}

function wa_redondear_motorlider($valor): int
{
    $n = (float)$valor;
    $entero = floor($n);
    $decimal = $n - $entero;

    if ($decimal <= 0.50) {
        return (int)$entero;
    }

    return (int)ceil($n);
}

function wa_money($value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    if (!is_numeric((string)$value)) {
        return (string)$value;
    }

    return number_format((float)wa_redondear_motorlider((float)$value), 0, ',', '.');
}

// =========================
// DB
// =========================
function wa_db(): mysqli
{
    mysqli_report(MYSQLI_REPORT_OFF);

    $cn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($cn->connect_errno) {
        throw new RuntimeException('Error conexión MySQL: ' . $cn->connect_error);
    }

    if (!$cn->set_charset('utf8')) {
        throw new RuntimeException('Error charset MySQL: ' . $cn->error);
    }

    return $cn;
}

function wa_registrar_input_no_match(array $data): void
{
    $cn = wa_db();

    $sql = "INSERT INTO whatsapp_inputs_no_match
            (
                telefono,
                nombre_cliente,
                tipo_input,
                valor_input,
                marca_input,
                modelo_input,
                version_input,
                id_marca_ref,
                id_modelo_ref,
                step_origen,
                mensaje_cliente,
                message_sid,
                procesado,
                observaciones,
                fecha_alta
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NULL, NOW())";

    $st = $cn->prepare($sql);

    if (!$st) {
        throw new RuntimeException('Error prepare wa_registrar_input_no_match: ' . $cn->error);
    }

    $telefono      = (string)($data['telefono'] ?? '');
    $nombreCliente = (string)($data['nombre_cliente'] ?? '');
    $tipoInput     = (string)($data['tipo_input'] ?? '');
    $valorInput    = (string)($data['valor_input'] ?? '');
    $marcaInput    = (string)($data['marca_input'] ?? '');
    $modeloInput   = (string)($data['modelo_input'] ?? '');
    $versionInput  = (string)($data['version_input'] ?? '');
    $idMarcaRef    = isset($data['id_marca_ref']) ? (int)$data['id_marca_ref'] : null;
    $idModeloRef   = isset($data['id_modelo_ref']) ? (int)$data['id_modelo_ref'] : null;
    $stepOrigen    = (string)($data['step_origen'] ?? '');
    $mensaje       = (string)($data['mensaje_cliente'] ?? '');
    $messageSid    = (string)($data['message_sid'] ?? '');

    $st->bind_param(
        'sssssssiisss',
        $telefono,
        $nombreCliente,
        $tipoInput,
        $valorInput,
        $marcaInput,
        $modeloInput,
        $versionInput,
        $idMarcaRef,
        $idModeloRef,
        $stepOrigen,
        $mensaje,
        $messageSid
    );

    $st->execute();
    $st->close();
    $cn->close();

    wa_log('INPUT_NO_MATCH_GUARDADO', [
        'telefono' => $telefono,
        'tipo_input' => $tipoInput,
        'valor_input' => $valorInput,
        'marca_input' => $marcaInput,
        'modelo_input' => $modeloInput,
        'version_input' => $versionInput,
        'id_marca_ref' => $idMarcaRef,
        'id_modelo_ref' => $idModeloRef,
        'step_origen' => $stepOrigen
    ]);
}

function wa_registrar_input_no_match_safe(array $data): void
{
    static $registrados = [];

    $key = md5(json_encode([
        $data['telefono'] ?? '',
        $data['tipo_input'] ?? '',
        $data['valor_input'] ?? '',
        $data['marca_input'] ?? '',
        $data['modelo_input'] ?? '',
        $data['version_input'] ?? '',
        $data['step_origen'] ?? '',
        $data['message_sid'] ?? ''
    ], JSON_UNESCAPED_UNICODE));

    if (isset($registrados[$key])) {
        return;
    }

    $registrados[$key] = true;

    try {
        wa_registrar_input_no_match($data);
    } catch (Throwable $e) {
        wa_log('INPUT_NO_MATCH_ERROR', [
            'error' => $e->getMessage(),
            'data' => $data
        ]);
    }
}


function wa_finalizar_cotizacion_desde_estado(string $from, string $profileName, array $userState, string $valor): void
{
    $marca = trim((string)($userState['marca'] ?? ''));
    $modelo = trim((string)($userState['modelo'] ?? ''));
    $anio = trim((string)($userState['anio'] ?? ''));
    $km = trim((string)($userState['km'] ?? ''));
    $version = trim((string)($userState['version'] ?? ''));
    $ficha = trim((string)($userState['ficha_oficial'] ?? ''));
    $tipoVenta = trim((string)($userState['tipo_venta'] ?? ''));

    $idMarca = (int)($userState['id_marca'] ?? 0);
    $idModel = (int)($userState['id_model'] ?? 0);
    $idVersion = (int)($userState['id_version'] ?? 0);

    $emailSistema = 'abella.motorlider@gmail.com';

    $estadoFinalData = [
        'step' => 'pendiente_humano',
        'marca' => $marca,
        'id_marca' => $idMarca > 0 ? $idMarca : null,
        'modelo' => $modelo,
        'id_model' => $idModel > 0 ? $idModel : null,
        'id_version' => $idVersion > 0 ? $idVersion : null,
        'anio' => $anio,
        'km' => $km,
        'version' => $version,
        'ficha_oficial' => $ficha,
        'tipo_venta' => $tipoVenta,
        'valor_pretendido' => $valor,
        'email' => $emailSistema
    ];

    wa_log('FLOW_COMPLETED_SIN_EMAIL', [
        'from' => $from,
        'data' => $estadoFinalData
    ]);

    if ($idMarca > 0 && $idModel > 0) {
        $brandUrl = (string)$idMarca;

        $apiPayload = [
            'id_marca' => $idMarca,
            'id_model' => $idModel,
            'id_modelo' => $idModel,
            'id_version' => $idVersion > 0 ? $idVersion : null,
            'modelo' => $idModel,
            'marca' => $marca,
            'modelo_nombre' => $modelo,
            'version' => $version,
            'anio' => $anio,
            'km' => $km,
            'ficha_tecnica' => ($ficha === 'si') ? 1 : 0,
            'cantidad_duenios' => 1,
            'valor_pretendido' => $valor,
            'venta_permuta' => ($tipoVenta === 'entrega_forma_pago') ? 1 : 0,
            'nombre_auto' => trim($marca . ' ' . $modelo . ' ' . $anio . ' ' . $version),
            'nombre' => $profileName !== '' ? $profileName : 'Cliente WhatsApp',
            'email' => $emailSistema,
            'telefono' => $from
        ];

        wa_log('PAYLOAD_FINAL_COTIZADOR', [
            'brand_url' => $brandUrl,
            'payload' => $apiPayload
        ]);

        $apiResult = cotizar_api($brandUrl, $apiPayload);

        $estadoFinalData['api_result'] = $apiResult;
        $estadoFinalData['step'] = 'pendiente_humano';

        $idCotizacion = $apiResult['id_cotizacion']
            ?? $apiResult['cotizacion']
            ?? $apiResult['cotizacion_id']
            ?? null;

        wa_set_user_state(
            $from,
            $estadoFinalData,
            'PENDIENTE_RESPUESTA_HUMANA',
            'HUMANO',
            $profileName !== '' ? $profileName : null,
            $emailSistema,
            $idCotizacion
        );

        twiml_message_and_save($from, wa_build_mensaje_post_email($idCotizacion));
    }

    wa_set_user_state(
        $from,
        $estadoFinalData,
        'PENDIENTE_RESPUESTA_HUMANA',
        'HUMANO',
        $profileName !== '' ? $profileName : null,
        $emailSistema,
        null
    );

    twiml_message_and_save($from, wa_build_mensaje_post_email(null));
}


// =========================
// CONVERSACIONES DB
// =========================
function wa_get_conversation(string $telefono): ?array
{
    $cn = wa_db();

    $sql = "SELECT * FROM whatsapp_conversaciones WHERE telefono = ? ORDER BY id DESC LIMIT 1";
    $st = $cn->prepare($sql);

    if (!$st) {
        throw new RuntimeException('Error prepare wa_get_conversation: ' . $cn->error);
    }

    $st->bind_param('s', $telefono);
    $st->execute();
    $rs = $st->get_result();

    $row = $rs ? $rs->fetch_assoc() : null;

    if ($rs) {
        $rs->free();
    }
    $st->close();
    $cn->close();

    return $row ?: null;
}

function wa_create_conversation_if_not_exists(string $telefono, string $nombre = ''): array
{
    $conv = wa_get_conversation($telefono);
    if ($conv !== null) {
        return $conv;
    }

    $cn = wa_db();

    $estado = 'INICIO';
    $modo = 'BOT';
    $fecha = date('Y-m-d H:i:s');
    $datosJson = json_encode(['step' => null], JSON_UNESCAPED_UNICODE);

    $sql = "INSERT INTO whatsapp_conversaciones
            (telefono, nombre, estado, modo_atencion, datos_json, fecha_ultima_interaccion, fecha_alta, fecha_mod)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $st = $cn->prepare($sql);

    if (!$st) {
        throw new RuntimeException('Error prepare wa_create_conversation_if_not_exists: ' . $cn->error);
    }

    $st->bind_param('ssssssss', $telefono, $nombre, $estado, $modo, $datosJson, $fecha, $fecha, $fecha);
    $st->execute();
    $st->close();
    $cn->close();

    return wa_get_conversation($telefono) ?? [];
}

function wa_update_conversation_fields(string $telefono, array $fields): void
{
    if (empty($fields)) {
        return;
    }

    $cn = wa_db();

    $sets = [];
    $values = [];
    $types = '';

    foreach ($fields as $campo => $valor) {
        $sets[] = $campo . ' = ?';
        $values[] = $valor;
        $types .= 's';
    }

    $sql = "UPDATE whatsapp_conversaciones SET " . implode(', ', $sets) . " WHERE telefono = ?";
    $st = $cn->prepare($sql);

    if (!$st) {
        throw new RuntimeException('Error prepare wa_update_conversation_fields: ' . $cn->error);
    }

    $types .= 's';
    $values[] = $telefono;

    $bind = [];
    $bind[] = &$types;
    foreach ($values as $k => $v) {
        $bind[] = &$values[$k];
    }

    call_user_func_array([$st, 'bind_param'], $bind);
    $st->execute();
    $st->close();
    $cn->close();
}

function wa_get_user_data(string $telefono): array
{
    $conv = wa_get_conversation($telefono);
    if (!$conv) {
        return [];
    }

    $json = (string)($conv['datos_json'] ?? '');
    if ($json === '') {
        return [];
    }

    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function wa_step_to_estado(string $step): string
{
    $map = [
        'marca'                       => 'ESPERANDO_MARCA',
        'marca_sugerida'             => 'ESPERANDO_MARCA',
        'modelo'                     => 'ESPERANDO_MODELO',
        'modelo_sugerido'            => 'ESPERANDO_MODELO',
        'anio'                       => 'ESPERANDO_ANIO',
        'km'                         => 'ESPERANDO_KM',
        'version'                    => 'ESPERANDO_VERSION',
        'version_sugerida'           => 'ESPERANDO_VERSION',
        'ficha_oficial'              => 'ESPERANDO_FICHA_OFICIAL',
        'tipo_venta'                 => 'ESPERANDO_TIPO_VENTA',
        'valor_pretendido'           => 'ESPERANDO_VALOR_PRETENDIDO',

        'agenda_dia'                 => 'ESPERANDO_FECHA',
        'agenda_hora'                => 'ESPERANDO_HORA',
        'agenda_confirmar'           => 'ESPERANDO_HORA',

        'agenda_confirmacion_humana' => 'PENDIENTE_RESPUESTA_HUMANA',
        'pendiente_humano'           => 'PENDIENTE_RESPUESTA_HUMANA',
        'resultado_enviado'          => 'PENDIENTE_RESPUESTA_HUMANA',
        'agendado'                   => 'AGENDADO',
        'cerrado'                    => 'CERRADO',
    ];

    return $map[$step] ?? 'INICIO';
}

function wa_set_user_state(
    string $telefono,
    array $data,
    ?string $estado = null,
    ?string $modoAtencion = null,
    ?string $nombre = null,
    ?string $email = null,
    $idCotizacion = null
): void {
    $step = (string)($data['step'] ?? '');
    $estadoFinal = $estado ?? wa_step_to_estado($step);
    $modoFinal = $modoAtencion ?? (($estadoFinal === 'PENDIENTE_RESPUESTA_HUMANA' || $estadoFinal === 'HUMANO_EN_CONVERSACION') ? 'HUMANO' : 'BOT');
    $fecha = date('Y-m-d H:i:s');

    $campos = [
        'estado' => $estadoFinal,
        'modo_atencion' => $modoFinal,
        'datos_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
        'fecha_ultima_interaccion' => $fecha,
        'fecha_mod' => $fecha
    ];

    if ($nombre !== null) {
        $campos['nombre'] = $nombre;
    }

    if ($email !== null) {
        $campos['email'] = $email;
    }

    if ($idCotizacion !== null) {
        $campos['id_cotizacion'] = (string)$idCotizacion;
    }

    wa_update_conversation_fields($telefono, $campos);

    wa_log('STATE_SET_DB', [
        'telefono' => $telefono,
        'estado'   => $estadoFinal,
        'modo'     => $modoFinal,
        'step'     => $step,
        'data'     => $data
    ]);
}

function wa_touch_incoming_message(string $telefono, string $mensaje): void
{
    $fecha = date('Y-m-d H:i:s');

    wa_update_conversation_fields($telefono, [
        'ultimo_mensaje_cliente' => $mensaje,
        'fecha_ultima_interaccion' => $fecha,
        'fecha_mod' => $fecha
    ]);
}

function wa_save_last_bot_message(string $telefono, string $mensaje): void
{
    $fecha = date('Y-m-d H:i:s');

    wa_update_conversation_fields($telefono, [
        'ultima_respuesta_bot' => $mensaje,
        'fecha_mod' => $fecha
    ]);
}

function twiml_message_and_save(string $telefono, string $mensaje): void
{
    wa_save_last_bot_message($telefono, $mensaje);
    twiml_message($mensaje);
}

function wa_get_parametro_sistema(string $grupo, string $clave): ?string
{
    $cn = wa_db();

    $sql = "SELECT valor
            FROM parametros_sistema
            WHERE grupo = ?
              AND clave = ?
              AND activo = 1
            ORDER BY id DESC
            LIMIT 1";

    $st = $cn->prepare($sql);
    if (!$st) {
        throw new RuntimeException('Error prepare wa_get_parametro_sistema: ' . $cn->error);
    }

    $st->bind_param('ss', $grupo, $clave);
    $st->execute();
    $rs = $st->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;

    if ($rs) $rs->free();
    $st->close();
    $cn->close();

    return $row ? (string)$row['valor'] : null;
}



function wa_obtener_agenda_pendiente_confirmacion(string $telefono): ?array
{
    $cn = wa_db();

    $sql = "SELECT *
            FROM agendas
            WHERE telefono = ?
              AND cancelado = 0
              AND finalizada = 0
              AND confirmacion_asistencia = 'PENDIENTE'
              AND CONCAT(fecha, ' ', hora) >= NOW()
            ORDER BY fecha ASC, hora ASC
            LIMIT 1";

    $st = $cn->prepare($sql);
    if (!$st) {
        throw new RuntimeException('Error prepare wa_obtener_agenda_pendiente_confirmacion: ' . $cn->error);
    }

    $st->bind_param('s', $telefono);
    $st->execute();
    $rs = $st->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;

    if ($rs) {
        $rs->free();
    }
    $st->close();
    $cn->close();

    return $row ?: null;
}

function wa_marcar_confirmacion_agenda(int $idAgenda, string $estado, ?string $motivoCancelacion = null): bool
{
    $cn = wa_db();
    $fecha = date('Y-m-d H:i:s');
    $estado = strtoupper(trim($estado));

    if ($estado === 'SI') {
        $estadoDb = 'CONFIRMADO';

        $sql = "UPDATE agendas
                SET confirmacion_asistencia = ?,
                    fecha_confirmacion_asistencia = ?
                WHERE id_agenda = ?";
        $st = $cn->prepare($sql);

        if (!$st) {
            throw new RuntimeException('Error prepare wa_marcar_confirmacion_agenda(SI): ' . $cn->error);
        }

        $st->bind_param('ssi', $estadoDb, $fecha, $idAgenda);
    } elseif ($estado === 'NO') {
        $estadoDb = 'CANCELADO';

        $sql = "UPDATE agendas
                SET confirmacion_asistencia = ?,
                    fecha_confirmacion_asistencia = ?,
                    cancelado = 1,
                    motivo_cancelacion = ?,
                    fecha_cancelacion = ?
                WHERE id_agenda = ?";
        $st = $cn->prepare($sql);

        if (!$st) {
            throw new RuntimeException('Error prepare wa_marcar_confirmacion_agenda(NO): ' . $cn->error);
        }

        $motivo = $motivoCancelacion ?? 'Cancelada por cliente vía WhatsApp';
        $st->bind_param('ssssi', $estadoDb, $fecha, $motivo, $fecha, $idAgenda);
    } else {
        throw new RuntimeException('Estado de confirmación inválido: ' . $estado);
    }

    $ok = $st->execute();

    if (!$ok) {
        $err = $st->error;
        $st->close();
        $cn->close();
        throw new RuntimeException('Error execute wa_marcar_confirmacion_agenda: ' . $err);
    }

    $st->close();
    $cn->close();

    return true;
}

function wa_registrar_notificacion_agenda(
    int $idAgenda,
    string $telefono,
    string $tipo,
    string $fechaAgenda,
    string $horaAgenda,
    string $mensajeEnviado = '',
    string $sidMensaje = '',
    string $respuestaApi = '',
    string $estado = 'INFO'
): bool {
    $cn = wa_db();

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

    $st = $cn->prepare($sql);
    if (!$st) {
        throw new RuntimeException('Error prepare wa_registrar_notificacion_agenda: ' . $cn->error);
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
        $err = $st->error;
        $st->close();
        $cn->close();
        throw new RuntimeException('Error execute wa_registrar_notificacion_agenda: ' . $err);
    }

    $st->close();
    $cn->close();

    return true;
}

// =========================
// WS AGENDA
// =========================
function wa_ws_post(string $peticion, array $data): array
{
    $url = 'https://carplay.uy/ws/index.php?peticion=' . rawurlencode($peticion);

    wa_log('WS_REQUEST', [
        'peticion' => $peticion,
        'url' => $url,
        'data' => $data
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded'
        ],
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err !== '') {
        wa_log('WS_ERROR', [
            'peticion' => $peticion,
            'error' => $err,
            'http' => $http
        ]);

        return [
            'codigo' => 500,
            'mensaje' => 'Error de comunicación con agenda',
            'error' => 500
        ];
    }

    $decoded = json_decode((string)$raw, true);

    wa_log('WS_RESPONSE', [
        'peticion' => $peticion,
        'http' => $http,
        'raw' => $raw,
        'decoded' => $decoded
    ]);

    if (!is_array($decoded)) {
        return [
            'codigo' => 500,
            'mensaje' => 'Respuesta inválida del sistema de agenda',
            'error' => 500
        ];
    }

    return $decoded;
}

function wa_agenda_location_id(): int
{
    return 1;
}

function wa_formatear_fecha_chat(string $fechaYmd): string
{
    $ts = strtotime($fechaYmd);
    if (!$ts) {
        return $fechaYmd;
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
    $dayEs = $dias[$dayName] ?? $dayName;

    return $dayEs . ' ' . date('d/m/Y', $ts);
}

function wa_obtener_disponibilidad_agenda(int $location): array
{
    return wa_ws_post('availability', [
        'location' => $location
    ]);
}

function wa_obtener_horarios_agenda(int $location, string $fecha): array
{
    return wa_ws_post('schedules', [
        'location' => $location,
        'date' => $fecha
    ]);
}

function wa_agendar_inspeccion(array $payload): array
{
    return wa_ws_post('scheduleInspection', $payload);
}

// =========================
// MARCAS
// =========================
function wa_obtener_marcas_catalogo(): array
{
    $cn = wa_db();

    $sql = "
        SELECT id, id_marca, nombre, cotisa, prioridad
        FROM act_marcas
        WHERE nombre IS NOT NULL
          AND nombre <> ''
        ORDER BY prioridad DESC, nombre ASC
    ";

    $rs = $cn->query($sql);

    if (!$rs) {
        throw new RuntimeException('Error query act_marcas: ' . $cn->error);
    }

    $rows = [];

    while ($row = $rs->fetch_assoc()) {
        $rows[] = [
            'id'        => (int)$row['id'],
            'id_marca'  => (int)$row['id_marca'],
            'nombre'    => trim((string)$row['nombre']),
            'cotisa'    => (int)$row['cotisa'],
            'prioridad' => (int)$row['prioridad']
        ];
    }

    $rs->free();
    $cn->close();

    return $rows;
}

function wa_buscar_marca_exacta(string $texto): ?array
{
    $textoNorm = wa_normalizar_texto($texto);
    $marcas = wa_obtener_marcas_catalogo();

    foreach ($marcas as $marca) {
        if ($marca['cotisa'] !== 1) {
            continue;
        }

        $nombreNorm = wa_normalizar_texto($marca['nombre']);
        if ($nombreNorm === $textoNorm) {
            return $marca;
        }
    }

    return null;
}

function wa_buscar_marcas_similares(string $texto, int $limite = 5): array
{
    $textoNorm = wa_normalizar_texto($texto);
    $marcas = wa_obtener_marcas_catalogo();
    $resultados = [];

    foreach ($marcas as $marca) {
        if ($marca['cotisa'] !== 1) {
            continue;
        }

        $nombre = $marca['nombre'];
        $nombreNorm = wa_normalizar_texto($nombre);

        if ($nombreNorm === '') {
            continue;
        }

        $score = 9999;

        if (strpos($nombreNorm, $textoNorm) === 0) {
            $score = 0;
        } elseif (strpos($nombreNorm, $textoNorm) !== false) {
            $score = 1;
        } else {
            similar_text($textoNorm, $nombreNorm, $porcentaje);

            $distancia = function_exists('levenshtein')
                ? levenshtein($textoNorm, $nombreNorm)
                : 999;

            if ($porcentaje >= 50 || $distancia <= 4) {
                $score = 10 + $distancia;
            } else {
                continue;
            }
        }

        $resultados[] = [
            'id'        => $marca['id'],
            'id_marca'  => $marca['id_marca'],
            'nombre'    => $nombre,
            'prioridad' => $marca['prioridad'],
            'score'     => $score
        ];
    }

    usort($resultados, function ($a, $b) {
        if ($a['score'] === $b['score']) {
            if ($a['prioridad'] === $b['prioridad']) {
                return strcmp($a['nombre'], $b['nombre']);
            }
            return $b['prioridad'] <=> $a['prioridad'];
        }

        return $a['score'] <=> $b['score'];
    });

    return array_slice($resultados, 0, $limite);
}

// =========================
// MODELOS
// =========================
function wa_obtener_modelos_catalogo_por_marca(int $idMarca): array
{
    $cn = wa_db();

    $sql = "
        SELECT id, id_marca, id_model, nombre, prioridad
        FROM act_modelo
        WHERE id_marca = " . (int)$idMarca . "
          AND nombre IS NOT NULL
          AND nombre <> ''
        ORDER BY prioridad DESC, nombre ASC
    ";

    $rs = $cn->query($sql);

    if (!$rs) {
        throw new RuntimeException('Error query act_modelo: ' . $cn->error);
    }

    $rows = [];

    while ($row = $rs->fetch_assoc()) {
        $rows[] = [
            'id'        => (int)$row['id'],
            'id_marca'  => (int)$row['id_marca'],
            'id_model'  => (int)$row['id_model'],
            'nombre'    => trim((string)$row['nombre']),
            'prioridad' => (int)$row['prioridad']
        ];
    }

    $rs->free();
    $cn->close();

    return $rows;
}

function wa_buscar_modelo_exacto(int $idMarca, string $texto): ?array
{
    $textoNorm = wa_normalizar_texto($texto);
    $modelos = wa_obtener_modelos_catalogo_por_marca($idMarca);

    foreach ($modelos as $modelo) {
        $nombreNorm = wa_normalizar_texto($modelo['nombre']);
        if ($nombreNorm === $textoNorm) {
            return $modelo;
        }
    }

    return null;
}

function wa_buscar_modelos_similares(int $idMarca, string $texto, int $limite = 5): array
{
    $textoNorm = wa_normalizar_texto($texto);
    $modelos = wa_obtener_modelos_catalogo_por_marca($idMarca);
    $resultados = [];

    foreach ($modelos as $modelo) {
        $nombreNorm = wa_normalizar_texto($modelo['nombre']);

        if ($nombreNorm === '') {
            continue;
        }

        $score = 9999;
        $porcentaje = 0.0;
        $distancia = 9999;

        if ($textoNorm !== '' && (strpos($nombreNorm, $textoNorm) !== false || strpos($textoNorm, $nombreNorm) !== false)) {
            $score = 1;
        } else {
            similar_text($textoNorm, $nombreNorm, $porcentaje);

            if (function_exists('levenshtein')) {
                $distancia = levenshtein($textoNorm, $nombreNorm);
            }

            if ($porcentaje >= 55 || $distancia <= 4) {
                $score = $distancia;
            } else {
                continue;
            }
        }

        $resultados[] = [
            'id'        => $modelo['id'],
            'id_marca'  => $modelo['id_marca'],
            'id_model'  => $modelo['id_model'],
            'nombre'    => $modelo['nombre'],
            'prioridad' => $modelo['prioridad'],
            'score'     => $score
        ];
    }

    usort($resultados, function (array $a, array $b): int {
        if ($a['score'] === $b['score']) {
            if ($a['prioridad'] === $b['prioridad']) {
                return strcmp($a['nombre'], $b['nombre']);
            }
            return $b['prioridad'] <=> $a['prioridad'];
        }

        return $a['score'] <=> $b['score'];
    });

    return array_slice($resultados, 0, $limite);
}

// =========================
// VERSIONES
// =========================
function wa_obtener_versiones_catalogo(int $idMarca, int $idModelo): array
{
    $cn = wa_db();

    $sql = "
        SELECT id_version, id_marca, id_modelo, nombre, activo
        FROM act_version
        WHERE id_marca = " . (int)$idMarca . "
          AND id_modelo = " . (int)$idModelo . "
          AND activo = 1
          AND nombre IS NOT NULL
          AND nombre <> ''
        ORDER BY nombre ASC
    ";

    $rs = $cn->query($sql);

    if (!$rs) {
        throw new RuntimeException('Error query act_version: ' . $cn->error);
    }

    $rows = [];

    while ($row = $rs->fetch_assoc()) {
        $rows[] = [
            'id_version' => (int)$row['id_version'],
            'id_marca'   => (int)$row['id_marca'],
            'id_modelo'  => (int)$row['id_modelo'],
            'nombre'     => trim((string)$row['nombre']),
            'activo'     => (int)$row['activo']
        ];
    }

    $rs->free();
    $cn->close();

    return $rows;
}

function wa_buscar_version_exacta(int $idMarca, int $idModelo, string $texto): ?array
{
    $textoNorm = wa_normalizar_texto($texto);
    $versiones = wa_obtener_versiones_catalogo($idMarca, $idModelo);

    foreach ($versiones as $version) {
        $nombreNorm = wa_normalizar_texto($version['nombre']);
        if ($nombreNorm === $textoNorm) {
            return $version;
        }
    }

    return null;
}

function wa_buscar_versiones_similares(int $idMarca, int $idModelo, string $texto, int $limite = 5): array
{
    $textoNorm = wa_normalizar_texto($texto);
    $versiones = wa_obtener_versiones_catalogo($idMarca, $idModelo);
    $resultados = [];

    foreach ($versiones as $version) {
        $nombreNorm = wa_normalizar_texto($version['nombre']);

        if ($nombreNorm === '') {
            continue;
        }

        $score = 9999;
        $porcentaje = 0.0;
        $distancia = 9999;

        if ($textoNorm !== '' && (strpos($nombreNorm, $textoNorm) !== false || strpos($textoNorm, $nombreNorm) !== false)) {
            $score = 1;
        } else {
            similar_text($textoNorm, $nombreNorm, $porcentaje);

            if (function_exists('levenshtein')) {
                $distancia = levenshtein($textoNorm, $nombreNorm);
            }

            if ($porcentaje >= 55 || $distancia <= 4) {
                $score = $distancia;
            } else {
                continue;
            }
        }

        $resultados[] = [
            'id_version' => $version['id_version'],
            'id_marca'   => $version['id_marca'],
            'id_modelo'  => $version['id_modelo'],
            'nombre'     => $version['nombre'],
            'score'      => $score
        ];
    }

    usort($resultados, function (array $a, array $b): int {
        if ($a['score'] === $b['score']) {
            return strcmp($a['nombre'], $b['nombre']);
        }

        return $a['score'] <=> $b['score'];
    });

    return array_slice($resultados, 0, $limite);
}

// =========================
// COTIZADOR API
// =========================
function cotizar_api(string $brand, array $payload): array
{
    $url = COTIZADOR_BASE_URL . rawurlencode($brand);
    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

    wa_log('API_REQUEST', [
        'url' => $url,
        'payload' => $payload
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonPayload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json; charset=utf-8',
            'Content-Length: ' . strlen((string)$jsonPayload)
        ],
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError !== '') {
        wa_log('API_CURL_ERROR', [
            'url' => $url,
            'error' => $curlError
        ]);

        return [
            'ok' => false,
            'mensaje' => 'Error de comunicación con el cotizador',
            'curl_error' => $curlError,
            'http_code' => $httpCode
        ];
    }

    $decoded = json_decode((string)$response, true);

    wa_log('API_RESPONSE', [
        'http_code' => $httpCode,
        'raw' => $response,
        'decoded' => $decoded
    ]);

    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'mensaje' => 'Respuesta inválida del cotizador',
            'raw' => $response,
            'http_code' => $httpCode
        ];
    }

    $decoded['_http_code'] = $httpCode;
    return $decoded;
}

// =========================
// MENSAJES
// =========================
function wa_build_mensaje_post_email($idCotizacion = null): string
{
    $msg = "Excelente! Recibimos correctamente sus datos.\n\n";
 
    $msg .= "Le estaremos enviando la cotización de su vehículo en unos minutos ⏱️";

    return $msg;
}

// =========================
// MAIN
// =========================
wa_log('INCOMING_RAW', [
    'post' => $_POST,
    'server' => [
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? null,
        'REQUEST_URI'    => $_SERVER['REQUEST_URI'] ?? null,
        'HTTP_HOST'      => $_SERVER['HTTP_HOST'] ?? null,
    ]
]);

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    twiml_message('Método no permitido.');
}

if (!validate_twilio_signature(TWILIO_AUTH_TOKEN)) {
    http_response_code(403);
    twiml_message('Firma no válida.');
}

$from        = trim((string)($_POST['From'] ?? ''));
$to          = trim((string)($_POST['To'] ?? ''));
$body        = trim((string)($_POST['Body'] ?? ''));
$messageSid  = trim((string)($_POST['MessageSid'] ?? ''));
$profileName = trim((string)($_POST['ProfileName'] ?? ''));

wa_log('INCOMING_PARSED', [
    'from'         => $from,
    'to'           => $to,
    'body'         => $body,
    'message_sid'  => $messageSid,
    'profile_name' => $profileName
]);

if ($from === '') {
    twiml_empty();
}

try {
    wa_create_conversation_if_not_exists($from, $profileName);
    wa_touch_incoming_message($from, $body);
} catch (Throwable $e) {
    wa_log('CONV_INIT_ERROR', ['error' => $e->getMessage()]);
    twiml_message('Ocurrió un problema inicializando la conversación.');
}

$bodyNorm = wa_normalizar_texto($body);
$userState = wa_get_user_data($from);
$currentConv = wa_get_conversation($from);
$currentEstado = (string)($currentConv['estado'] ?? 'INICIO');

// =========================
// CONFIRMACION DE AGENDA PENDIENTE (GLOBAL)
// =========================
try {
    $agendaPendienteConfirmacionGlobal = wa_obtener_agenda_pendiente_confirmacion($from);
} catch (Throwable $e) {
    wa_log('AGENDA_CONFIRMACION_LOOKUP_ERROR_GLOBAL', [
        'from' => $from,
        'error' => $e->getMessage()
    ]);
    $agendaPendienteConfirmacionGlobal = null;
}

if ($agendaPendienteConfirmacionGlobal !== null) {
    if (wa_respuesta_es_si($body)) {
        try {
            wa_marcar_confirmacion_agenda((int)$agendaPendienteConfirmacionGlobal['id_agenda'], 'SI');
        } catch (Throwable $e) {
            wa_log('AGENDA_CONFIRMACION_UPDATE_ERROR_GLOBAL', [
                'from' => $from,
                'id_agenda' => (int)$agendaPendienteConfirmacionGlobal['id_agenda'],
                'estado' => 'SI',
                'error' => $e->getMessage()
            ]);
            twiml_message_and_save(
                $from,
                "Ocurrió un problema confirmando tu agenda. Un asesor lo revisará a la brevedad."
            );
        }

        wa_log('AGENDA_CONFIRMADA_POR_CLIENTE_GLOBAL', [
            'from' => $from,
            'id_agenda' => (int)$agendaPendienteConfirmacionGlobal['id_agenda']
        ]);

        twiml_message_and_save(
            $from,
            "Perfecto 👍

Tu agenda quedó confirmada para el "
            . wa_formatear_fecha_chat((string)$agendaPendienteConfirmacionGlobal['fecha'])
            . " a las " . substr((string)$agendaPendienteConfirmacionGlobal['hora'], 0, 5)
            . ".

Te estaremos enviando un recordatorio antes de la inspección."
        );
    }

    if (wa_respuesta_es_no($body) || wa_es_cancelar_agenda($body)) {
        try {
            wa_marcar_confirmacion_agenda(
                (int)$agendaPendienteConfirmacionGlobal['id_agenda'],
                'NO',
                'Cancelada por cliente vía WhatsApp'
            );

            wa_registrar_notificacion_agenda(
                (int)$agendaPendienteConfirmacionGlobal['id_agenda'],
                (string)$agendaPendienteConfirmacionGlobal['telefono'],
                'cancelacion_cliente',
                (string)$agendaPendienteConfirmacionGlobal['fecha'],
                (string)$agendaPendienteConfirmacionGlobal['hora'],
                'Cliente canceló la agenda respondiendo NO por WhatsApp',
                '',
                json_encode([
                    'origen' => 'whatsapp',
                    'respuesta_cliente' => $body
                ], JSON_UNESCAPED_UNICODE),
                'INFO'
            );
        } catch (Throwable $e) {
            wa_log('AGENDA_CANCELACION_UPDATE_ERROR_GLOBAL', [
                'from' => $from,
                'id_agenda' => (int)$agendaPendienteConfirmacionGlobal['id_agenda'],
                'estado' => 'NO',
                'error' => $e->getMessage()
            ]);

            twiml_message_and_save(
                $from,
                "Ocurrió un problema cancelando tu agenda. Un asesor lo revisará a la brevedad."
            );
        }

        wa_log('AGENDA_CANCELADA_POR_CLIENTE_GLOBAL', [
            'from' => $from,
            'id_agenda' => (int)$agendaPendienteConfirmacionGlobal['id_agenda']
        ]);

        twiml_message_and_save(
            $from,
            "Perfecto. Tu agenda del "
            . wa_formatear_fecha_chat((string)$agendaPendienteConfirmacionGlobal['fecha'])
            . " a las " . substr((string)$agendaPendienteConfirmacionGlobal['hora'], 0, 5)
            . " fue cancelada.

Si querés coordinar una nueva fecha, respondé AGENDAR."
        );
    }
}

// =========================
// COMANDOS GLOBALES
// =========================
if (in_array($bodyNorm, ['hola', 'hi', 'menu', 'inicio'], true)) {
    wa_set_user_state(
        $from,
        ['step' => null],
        'INICIO',
        'BOT',
        $profileName !== '' ? $profileName : null
    );

    twiml_message_and_save(
        $from,
        "¡Hola" . ($profileName !== '' ? " {$profileName}" : "") . "! "
        . "Bienvenido al cotizador de vehículos de Motorlider.\n\n"
        . "Escribí COTIZAR para comenzar."
    );
}

if (in_array($bodyNorm, ['cancelar', 'salir'], true)) {
    wa_set_user_state(
        $from,
        ['step' => null],
        'INICIO',
        'BOT',
        $profileName !== '' ? $profileName : null
    );

    twiml_message_and_save(
        $from,
        "Perfecto. Cancelé el flujo actual.\n\n"
        . "Cuando quieras volver a empezar, escribí COTIZAR."
    );
}

if ($bodyNorm === 'cotizar') {
    wa_set_user_state(
        $from,
        ['step' => 'marca'],
        'ESPERANDO_MARCA',
        'BOT',
        $profileName !== '' ? $profileName : null
    );

    twiml_message_and_save(
        $from,
        "Perfecto. Vamos a comenzar la cotización.\n\n"
        . "Primer dato: escribime la MARCA del vehículo."
    );
}

// =========================
// MODO HUMANO / HÍBRIDO
// =========================
if (in_array($currentEstado, ['PENDIENTE_RESPUESTA_HUMANA', 'HUMANO_EN_CONVERSACION'], true)) {

    try {
        $agendaPendienteConfirmacion = wa_obtener_agenda_pendiente_confirmacion($from);
    } catch (Throwable $e) {
        wa_log('AGENDA_CONFIRMACION_LOOKUP_ERROR', [
            'from' => $from,
            'error' => $e->getMessage()
        ]);
        $agendaPendienteConfirmacion = null;
    }

    if ($agendaPendienteConfirmacion !== null) {
        if (wa_respuesta_es_si($body)) {
            try {
                wa_marcar_confirmacion_agenda((int)$agendaPendienteConfirmacion['id_agenda'], 'SI');
            } catch (Throwable $e) {
                wa_log('AGENDA_CONFIRMACION_UPDATE_ERROR', [
                    'from' => $from,
                    'id_agenda' => (int)$agendaPendienteConfirmacion['id_agenda'],
                    'estado' => 'SI',
                    'error' => $e->getMessage()
                ]);
                twiml_message_and_save(
                    $from,
                    "Ocurrió un problema confirmando tu agenda. Un asesor lo revisará a la brevedad."
                );
            }

            wa_log('AGENDA_CONFIRMADA_POR_CLIENTE', [
                'from' => $from,
                'id_agenda' => (int)$agendaPendienteConfirmacion['id_agenda']
            ]);

            twiml_message_and_save(
                $from,
                "Perfecto 👍\n\nTu agenda quedó confirmada para el "
                . wa_formatear_fecha_chat((string)$agendaPendienteConfirmacion['fecha'])
                . " a las " . substr((string)$agendaPendienteConfirmacion['hora'], 0, 5)
                . ".\n\nTe estaremos enviando un recordatorio antes de la inspección."
            );
        }

        if (wa_respuesta_es_no($body) || wa_es_cancelar_agenda($body)) {
            try {
                wa_marcar_confirmacion_agenda(
                    (int)$agendaPendienteConfirmacion['id_agenda'],
                    'NO',
                    'Cancelada por cliente vía WhatsApp'
                );
            } catch (Throwable $e) {
                wa_log('AGENDA_CANCELACION_UPDATE_ERROR', [
                    'from' => $from,
                    'id_agenda' => (int)$agendaPendienteConfirmacion['id_agenda'],
                    'estado' => 'NO',
                    'error' => $e->getMessage()
                ]);
                twiml_message_and_save(
                    $from,
                    "Ocurrió un problema cancelando tu agenda. Un asesor lo revisará a la brevedad."
                );
            }

            wa_log('AGENDA_CANCELADA_POR_CLIENTE', [
                'from' => $from,
                'id_agenda' => (int)$agendaPendienteConfirmacion['id_agenda']
            ]);

            twiml_message_and_save(
                $from,
                "Perfecto. Tu agenda del "
                . wa_formatear_fecha_chat((string)$agendaPendienteConfirmacion['fecha'])
                . " a las " . substr((string)$agendaPendienteConfirmacion['hora'], 0, 5)
                . " fue cancelada.\n\nSi querés coordinar una nueva fecha, respondé AGENDAR."
            );
        }
    }

    if (wa_es_agendar($body) || wa_respuesta_es_si($body)) {
        $location = wa_agenda_location_id();
        $disp = wa_obtener_disponibilidad_agenda($location);

        if (($disp['codigo'] ?? 500) != 200 || empty($disp['availability']) || !is_array($disp['availability'])) {
            twiml_message_and_save(
                $from,
                "En este momento no encontré días disponibles para agenda.\n\n"
                . "Un asesor lo estará coordinando a la brevedad."
            );
        }

        $opciones = [];
        $lineas = [];
        $max = min(7, count($disp['availability']));

        for ($i = 0; $i < $max; $i++) {
            $nro = (string)($i + 1);
            $fecha = (string)$disp['availability'][$i]['fecha'];
            $opciones[$nro] = [
                'fecha' => $fecha
            ];
            $lineas[] = $nro . ' = ' . wa_formatear_fecha_chat($fecha);
        }

        $nuevoEstado = $userState;
        $nuevoEstado['step'] = 'agenda_dia';
        $nuevoEstado['agenda_location'] = $location;
        $nuevoEstado['agenda_dias_opciones'] = $opciones;
        unset($nuevoEstado['agenda_fecha'], $nuevoEstado['agenda_hora'], $nuevoEstado['agenda_horas_opciones']);

        wa_set_user_state(
            $from,
            $nuevoEstado,
            'ESPERANDO_FECHA',
            'BOT',
            $profileName !== '' ? $profileName : null
        );

        twiml_message_and_save(
            $from,
            "Perfecto 👍\n\n"
            . "Estos son los próximos días disponibles para la inspección:\n"
            . implode("\n", $lineas)
            . "\n\nRespondé con el número del día.\n"
            . "También podés escribir ATRAS o CANCELAR."
        );
    }

    if (wa_respuesta_es_no($body)) {
        $msgCierre = wa_get_parametro_sistema('whatsapp_cotizador', 'respuesta_cierre_no_agenda');
        if ($msgCierre === null || trim($msgCierre) === '') {
            $msgCierre = 'Gracias por comunicarte con Motorlider. Quedamos a las órdenes.';
        }

        $nuevoEstado = $userState;
        $nuevoEstado['step'] = 'cerrado';

        wa_set_user_state(
            $from,
            $nuevoEstado,
            'CERRADO',
            'HUMANO',
            $profileName !== '' ? $profileName : null
        );

        twiml_message_and_save($from, $msgCierre);
    }

    wa_log('HUMAN_MODE_NO_AUTO_REPLY', [
        'from' => $from,
        'estado' => $currentEstado,
        'body' => $body
    ]);

    twiml_empty();
}

// refrescar por si algún comando global cambió estado
$userState = wa_get_user_data($from);

// =========================
// PASO: MARCA
// =========================
if (($userState['step'] ?? '') === 'marca') {
    $marcaIngresada = trim($body);

    if ($marcaIngresada === '') {
        twiml_message_and_save($from, "No pude leer la marca. Escribime la MARCA del vehículo.");
    }

    try {
        $marcaExacta = wa_buscar_marca_exacta($marcaIngresada);
    } catch (Throwable $e) {
        wa_log('MARCA_DB_EXCEPTION', ['error' => $e->getMessage()]);
        twiml_message_and_save($from, "Ocurrió un problema consultando el catálogo de marcas. Probá nuevamente en unos instantes.");
    }

    if ($marcaExacta !== null) {
        $marcaFinal = $marcaExacta['nombre'];

        wa_set_user_state($from, [
            'step' => 'modelo',
            'marca' => $marcaFinal,
            'id_marca' => $marcaExacta['id_marca']
        ], 'ESPERANDO_MODELO', 'BOT', $profileName !== '' ? $profileName : null);

        twiml_message_and_save(
            $from,
            "Perfecto 👍\n\n"
            . "Marca: {$marcaFinal}\n\n"
            . "Ahora escribime el MODELO."
        );
    }

    try {
        $sugerencias = wa_buscar_marcas_similares($marcaIngresada, 5);
    } catch (Throwable $e) {
        wa_log('MARCA_SUGERENCIAS_DB_EXCEPTION', ['error' => $e->getMessage()]);
        twiml_message_and_save($from, "Ocurrió un problema consultando el catálogo de marcas. Probá nuevamente en unos instantes.");
    }

    if (!empty($sugerencias)) {
        $opcionesTexto = [];
        $opcionesEstado = [];

        foreach ($sugerencias as $i => $op) {
            $nro = $i + 1;
            $opcionesTexto[] = $nro . '. ' . $op['nombre'];
            $opcionesEstado[(string)$nro] = [
                'id' => $op['id'],
                'id_marca' => $op['id_marca'],
                'nombre' => $op['nombre']
            ];
        }

        wa_set_user_state($from, [
            'step' => 'marca_sugerida',
            'marca_input' => $marcaIngresada,
            'marca_opciones' => $opcionesEstado
        ], 'ESPERANDO_MARCA', 'BOT', $profileName !== '' ? $profileName : null);

        twiml_message_and_save(
            $from,
            "No encontré esa marca exacta.\n\n"
            . "Quizás quisiste decir:\n"
            . implode("\n", $opcionesTexto)
            . "\n\nRespondé con el número o con el nombre correcto."
        );
    }

    wa_registrar_input_no_match_safe([
        'telefono' => $from,
        'nombre_cliente' => $profileName,
        'tipo_input' => 'marca',
        'valor_input' => $marcaIngresada,
        'marca_input' => $marcaIngresada,
        'modelo_input' => '',
        'version_input' => '',
        'id_marca_ref' => null,
        'id_modelo_ref' => null,
        'step_origen' => 'marca',
        'mensaje_cliente' => $body,
        'message_sid' => $messageSid
    ]);

    twiml_message_and_save($from, "No encontré esa marca.\n\nProbá escribiendo nuevamente el nombre de la marca.");
}

// =========================
// PASO: MARCA SUGERIDA
// =========================
if (($userState['step'] ?? '') === 'marca_sugerida') {
    $respuesta = trim($body);
    $respuestaNorm = wa_normalizar_texto($respuesta);
    $opciones = $userState['marca_opciones'] ?? [];

    if (isset($opciones[$respuesta])) {
        $marcaFinal = $opciones[$respuesta]['nombre'];
        $idMarca = (int)$opciones[$respuesta]['id_marca'];

        wa_set_user_state($from, [
            'step' => 'modelo',
            'marca' => $marcaFinal,
            'id_marca' => $idMarca
        ], 'ESPERANDO_MODELO', 'BOT', $profileName !== '' ? $profileName : null);

        twiml_message_and_save($from, "Perfecto 👍\n\nMarca: {$marcaFinal}\n\nAhora escribime el MODELO.");
    }

    foreach ($opciones as $op) {
        if (wa_normalizar_texto((string)$op['nombre']) === $respuestaNorm) {
            $marcaFinal = (string)$op['nombre'];
            $idMarca = (int)$op['id_marca'];

            wa_set_user_state($from, [
                'step' => 'modelo',
                'marca' => $marcaFinal,
                'id_marca' => $idMarca
            ], 'ESPERANDO_MODELO', 'BOT', $profileName !== '' ? $profileName : null);

            twiml_message_and_save($from, "Perfecto 👍\n\nMarca: {$marcaFinal}\n\nAhora escribime el MODELO.");
        }
    }

    try {
        $marcaExacta = wa_buscar_marca_exacta($respuesta);
    } catch (Throwable $e) {
        wa_log('MARCA_REBUSQUEDA_DB_EXCEPTION', [
            'error' => $e->getMessage(),
            'respuesta' => $respuesta
        ]);
        twiml_message_and_save($from, "Ocurrió un problema consultando el catálogo de marcas. Probá nuevamente en unos instantes.");
    }

    if ($marcaExacta !== null) {
        $marcaFinal = $marcaExacta['nombre'];

        wa_set_user_state($from, [
            'step' => 'modelo',
            'marca' => $marcaFinal,
            'id_marca' => $marcaExacta['id_marca']
        ], 'ESPERANDO_MODELO', 'BOT', $profileName !== '' ? $profileName : null);

        twiml_message_and_save($from, "Perfecto 👍\n\nMarca: {$marcaFinal}\n\nAhora escribime el MODELO.");
    }

    try {
        $nuevasSugerencias = wa_buscar_marcas_similares($respuesta, 5);
    } catch (Throwable $e) {
        wa_log('MARCA_REBUSQUEDA_SUGERENCIAS_DB_EXCEPTION', [
            'error' => $e->getMessage(),
            'respuesta' => $respuesta
        ]);
        twiml_message_and_save($from, "Ocurrió un problema consultando el catálogo de marcas. Probá nuevamente en unos instantes.");
    }

    if (!empty($nuevasSugerencias)) {
        $opcionesTexto = [];
        $opcionesEstado = [];

        foreach ($nuevasSugerencias as $i => $op) {
            $nro = $i + 1;
            $opcionesTexto[] = $nro . '. ' . $op['nombre'];
            $opcionesEstado[(string)$nro] = [
                'id' => $op['id'],
                'id_marca' => $op['id_marca'],
                'nombre' => $op['nombre']
            ];
        }

        wa_set_user_state($from, [
            'step' => 'marca_sugerida',
            'marca_input' => $respuesta,
            'marca_opciones' => $opcionesEstado
        ], 'ESPERANDO_MARCA', 'BOT', $profileName !== '' ? $profileName : null);

        twiml_message_and_save(
            $from,
            "No encontré esa marca exacta.\n\n"
            . "Quizás quisiste decir:\n"
            . implode("\n", $opcionesTexto)
            . "\n\nRespondé con el número o con el nombre correcto."
        );
    }

    wa_set_user_state($from, ['step' => 'marca'], 'ESPERANDO_MARCA', 'BOT', $profileName !== '' ? $profileName : null);
    twiml_message_and_save($from, "No encontré esa marca.\n\nProbá escribiendo nuevamente la marca del vehículo.");
}

// =========================
// PASO: MODELO
// =========================
if (($userState['step'] ?? '') === 'modelo') {
    $modeloIngresado = trim($body);
    $marca = trim((string)($userState['marca'] ?? ''));
    $idMarca = (int)($userState['id_marca'] ?? 0);

    if ($modeloIngresado === '') {
        twiml_message_and_save($from, "No pude leer el modelo. Escribime el MODELO del vehículo.");
    }

    if ($idMarca <= 0) {
        wa_set_user_state($from, ['step' => null], 'INICIO', 'BOT', $profileName !== '' ? $profileName : null);
        twiml_message_and_save($from, "Se perdió la referencia de la marca seleccionada.\n\nEscribí COTIZAR para comenzar nuevamente.");
    }

    try {
        $modeloExacto = wa_buscar_modelo_exacto($idMarca, $modeloIngresado);
    } catch (Throwable $e) {
        wa_log('MODELO_DB_EXCEPTION', ['error' => $e->getMessage(), 'id_marca' => $idMarca]);
        twiml_message_and_save($from, "Ocurrió un problema consultando el catálogo de modelos. Probá nuevamente en unos instantes.");
    }

    if ($modeloExacto !== null) {
        $modeloFinal = $modeloExacto['nombre'];

        wa_set_user_state($from, [
            'step' => 'anio',
            'marca' => $marca,
            'id_marca' => $idMarca,
            'modelo' => $modeloFinal,
            'id_model' => $modeloExacto['id_model']
        ], 'ESPERANDO_ANIO', 'BOT', $profileName !== '' ? $profileName : null);

        twiml_message_and_save(
            $from,
            "Excelente 👍\n\n"
            . "Marca: {$marca}\n"
            . "Modelo: {$modeloFinal}\n\n"
            . "Ahora escribime el AÑO del vehículo. Ejemplo: 2021"
        );
    }

    try {
        $sugerencias = wa_buscar_modelos_similares($idMarca, $modeloIngresado, 5);
    } catch (Throwable $e) {
        wa_log('MODELO_SUGERENCIAS_DB_EXCEPTION', ['error' => $e->getMessage(), 'id_marca' => $idMarca]);
        twiml_message_and_save($from, "Ocurrió un problema consultando el catálogo de modelos. Probá nuevamente en unos instantes.");
    }

    if (!empty($sugerencias)) {
        $opcionesTexto = [];
        $opcionesEstado = [];

        foreach ($sugerencias as $i => $op) {
            $nro = $i + 1;
            $opcionesTexto[] = $nro . '. ' . $op['nombre'];
            $opcionesEstado[(string)$nro] = [
                'id' => $op['id'],
                'id_marca' => $op['id_marca'],
                'id_model' => $op['id_model'],
                'nombre' => $op['nombre']
            ];
        }

        wa_set_user_state($from, [
            'step' => 'modelo_sugerido',
            'marca' => $marca,
            'id_marca' => $idMarca,
            'modelo_input' => $modeloIngresado,
            'modelo_opciones' => $opcionesEstado
        ], 'ESPERANDO_MODELO', 'BOT', $profileName !== '' ? $profileName : null);

        twiml_message_and_save(
            $from,
            "No encontré ese modelo exacto para {$marca}.\n\n"
            . "Quizás quisiste decir:\n"
            . implode("\n", $opcionesTexto)
            . "\n\nRespondé con el número o con el nombre correcto."
        );
    }

    wa_registrar_input_no_match_safe([
        'telefono' => $from,
        'nombre_cliente' => $profileName,
        'tipo_input' => 'modelo',
        'valor_input' => $modeloIngresado,
        'marca_input' => $marca,
        'modelo_input' => $modeloIngresado,
        'version_input' => '',
        'id_marca_ref' => $idMarca > 0 ? $idMarca : null,
        'id_modelo_ref' => null,
        'step_origen' => 'modelo',
        'mensaje_cliente' => $body,
        'message_sid' => $messageSid
    ]);

    twiml_message_and_save($from, "No encontré ese modelo para {$marca}.\n\nProbá escribiendo nuevamente el nombre del modelo.");
}

// =========================
// PASO: MODELO SUGERIDO
// =========================
if (($userState['step'] ?? '') === 'modelo_sugerido') {
    $respuesta = trim($body);
    $respuestaNorm = wa_normalizar_texto($respuesta);
    $marca = trim((string)($userState['marca'] ?? ''));
    $idMarca = (int)($userState['id_marca'] ?? 0);
    $opciones = $userState['modelo_opciones'] ?? [];

    if (isset($opciones[$respuesta])) {
        $modeloFinal = $opciones[$respuesta]['nombre'];
        $idModel = (int)$opciones[$respuesta]['id_model'];

        wa_set_user_state($from, [
            'step' => 'anio',
            'marca' => $marca,
            'id_marca' => $idMarca,
            'modelo' => $modeloFinal,
            'id_model' => $idModel
        ], 'ESPERANDO_ANIO', 'BOT', $profileName !== '' ? $profileName : null);

        twiml_message_and_save($from, "Excelente 👍\n\nMarca: {$marca}\nModelo: {$modeloFinal}\n\nAhora escribime el AÑO del vehículo. Ejemplo: 2021");
    }

    foreach ($opciones as $op) {
        if (wa_normalizar_texto((string)$op['nombre']) === $respuestaNorm) {
            $modeloFinal = (string)$op['nombre'];
            $idModel = (int)$op['id_model'];

            wa_set_user_state($from, [
                'step' => 'anio',
                'marca' => $marca,
                'id_marca' => $idMarca,
                'modelo' => $modeloFinal,
                'id_model' => $idModel
            ], 'ESPERANDO_ANIO', 'BOT', $profileName !== '' ? $profileName : null);

            twiml_message_and_save($from, "Excelente 👍\n\nMarca: {$marca}\nModelo: {$modeloFinal}\n\nAhora escribime el AÑO del vehículo. Ejemplo: 2021");
        }
    }

    $opcionesTexto = [];
    foreach ($opciones as $nro => $op) {
        $opcionesTexto[] = $nro . '. ' . $op['nombre'];
    }

    wa_registrar_input_no_match_safe([
        'telefono' => $from,
        'nombre_cliente' => $profileName,
        'tipo_input' => 'modelo',
        'valor_input' => $respuesta,
        'marca_input' => $marca,
        'modelo_input' => $respuesta,
        'version_input' => '',
        'id_marca_ref' => $idMarca > 0 ? $idMarca : null,
        'id_modelo_ref' => null,
        'step_origen' => 'modelo_sugerido',
        'mensaje_cliente' => $body,
        'message_sid' => $messageSid
    ]);

    twiml_message_and_save(
        $from,
        "No entendí la opción elegida.\n\n"
        . "Respondé con el número o con uno de estos nombres:\n"
        . implode("\n", $opcionesTexto)
    );
}

// =========================
// PASO: AÑO
// =========================
if (($userState['step'] ?? '') === 'anio') {
    $anio = preg_replace('/[^0-9]/', '', $body);
    $marca = trim((string)($userState['marca'] ?? ''));
    $modelo = trim((string)($userState['modelo'] ?? ''));

    if ($anio === '' || strlen($anio) !== 4) {
        twiml_message_and_save($from, "El año no parece válido. Escribime un año de 4 dígitos. Ejemplo: 2021");
    }

    wa_set_user_state($from, [
        'step' => 'km',
        'marca' => $marca,
        'id_marca' => $userState['id_marca'] ?? null,
        'modelo' => $modelo,
        'id_model' => $userState['id_model'] ?? null,
        'id_version' => $userState['id_version'] ?? null,
        'anio' => $anio
    ], 'ESPERANDO_KM', 'BOT', $profileName !== '' ? $profileName : null);

    twiml_message_and_save(
        $from,
        "Perfecto 👍\n\n"
        . "Marca: {$marca}\n"
        . "Modelo: {$modelo}\n"
        . "Año: {$anio}\n\n"
        . "Ahora escribime los KILÓMETROS. Ejemplo: 85000"
    );
}

// =========================
// PASO: KM
// =========================
if (($userState['step'] ?? '') === 'km') {
    $km = preg_replace('/[^0-9]/', '', $body);
    $marca = trim((string)($userState['marca'] ?? ''));
    $modelo = trim((string)($userState['modelo'] ?? ''));
    $anio = trim((string)($userState['anio'] ?? ''));

    if ($km === '') {
        twiml_message_and_save($from, "Los kilómetros no parecen válidos. Escribime solo números. Ejemplo: 85000");
    }

    wa_set_user_state($from, [
        'step' => 'version',
        'marca' => $marca,
        'id_marca' => $userState['id_marca'] ?? null,
        'modelo' => $modelo,
        'id_model' => $userState['id_model'] ?? null,
        'id_version' => $userState['id_version'] ?? null,
        'anio' => $anio,
        'km' => $km
    ], 'ESPERANDO_VERSION', 'BOT', $profileName !== '' ? $profileName : null);

    twiml_message_and_save(
        $from,
        "Perfecto 👍\n\n"
        . "Marca: {$marca}\n"
        . "Modelo: {$modelo}\n"
        . "Año: {$anio}\n"
        . "Kilómetros: {$km}\n\n"
        . "Ahora escribime la VERSIÓN.\n"
        . "Ejemplo: Full, GLS, LTZ, GS"
    );
}

// =========================
// PASO: VERSION
// =========================
if (($userState['step'] ?? '') === 'version') {
    $versionIngresada = trim($body);
    $marca = trim((string)($userState['marca'] ?? ''));
    $modelo = trim((string)($userState['modelo'] ?? ''));
    $anio = trim((string)($userState['anio'] ?? ''));
    $km = trim((string)($userState['km'] ?? ''));
    $idMarca = (int)($userState['id_marca'] ?? 0);
    $idModel = (int)($userState['id_model'] ?? 0);

    if ($versionIngresada === '') {
        twiml_message_and_save($from, "No pude leer la versión. Escribime la VERSIÓN del vehículo o respondé NINGUNA.");
    }

    if (wa_version_es_ninguna($versionIngresada)) {
        wa_set_user_state($from, [
            'step' => 'ficha_oficial',
            'marca' => $marca,
            'id_marca' => $userState['id_marca'] ?? null,
            'modelo' => $modelo,
            'id_model' => $userState['id_model'] ?? null,
            'id_version' => null,
            'anio' => $anio,
            'km' => $km,
            'version' => ''
        ], 'ESPERANDO_FICHA_OFICIAL', 'BOT', $profileName !== '' ? $profileName : null);

        twiml_message_and_save($from, "Perfecto 👍\n\nVersión: sin especificar\n\n¿Posee ficha oficial?\nRespondé: SI o NO");
    }

    if ($idMarca <= 0 || $idModel <= 0) {
        wa_set_user_state($from, [
            'step' => 'ficha_oficial',
            'marca' => $marca,
            'id_marca' => $userState['id_marca'] ?? null,
            'modelo' => $modelo,
            'id_model' => $userState['id_model'] ?? null,
            'id_version' => $userState['id_version'] ?? null,
            'anio' => $anio,
            'km' => $km,
            'version' => $versionIngresada
        ], 'ESPERANDO_FICHA_OFICIAL', 'BOT', $profileName !== '' ? $profileName : null);

        twiml_message_and_save($from, "Perfecto 👍\n\nVersión: {$versionIngresada}\n\n¿Posee ficha oficial?\nRespondé: SI o NO");
    }

    try {
        $versionExacta = wa_buscar_version_exacta($idMarca, $idModel, $versionIngresada);
    } catch (Throwable $e) {
        wa_log('VERSION_DB_EXCEPTION', [
            'error' => $e->getMessage(),
            'id_marca' => $idMarca,
            'id_model' => $idModel
        ]);

        wa_set_user_state($from, [
            'step' => 'ficha_oficial',
            'marca' => $marca,
            'id_marca' => $userState['id_marca'] ?? null,
            'modelo' => $modelo,
            'id_model' => $userState['id_model'] ?? null,
            'id_version' => $userState['id_version'] ?? null,
            'anio' => $anio,
            'km' => $km,
            'version' => $versionIngresada
        ], 'ESPERANDO_FICHA_OFICIAL', 'BOT', $profileName !== '' ? $profileName : null);

        twiml_message_and_save($from, "Perfecto 👍\n\nVersión: {$versionIngresada}\n\n¿Posee ficha oficial?\nRespondé: SI o NO");
    }

    if ($versionExacta !== null) {
        $versionFinal = $versionExacta['nombre'];

        wa_set_user_state($from, [
            'step' => 'ficha_oficial',
            'marca' => $marca,
            'id_marca' => $userState['id_marca'] ?? null,
            'modelo' => $modelo,
            'id_model' => $userState['id_model'] ?? null,
            'id_version' => $versionExacta['id_version'],
            'anio' => $anio,
            'km' => $km,
            'version' => $versionFinal
        ], 'ESPERANDO_FICHA_OFICIAL', 'BOT', $profileName !== '' ? $profileName : null);

        twiml_message_and_save($from, "Perfecto 👍\n\nVersión: {$versionFinal}\n\n¿Posee ficha oficial?\nRespondé: SI o NO");
    }

    try {
        $sugerencias = wa_buscar_versiones_similares($idMarca, $idModel, $versionIngresada, 5);
    } catch (Throwable $e) {
        wa_log('VERSION_SUGERENCIAS_DB_EXCEPTION', [
            'error' => $e->getMessage(),
            'id_marca' => $idMarca,
            'id_model' => $idModel
        ]);

        wa_set_user_state($from, [
            'step' => 'ficha_oficial',
            'marca' => $marca,
            'id_marca' => $userState['id_marca'] ?? null,
            'modelo' => $modelo,
            'id_model' => $userState['id_model'] ?? null,
            'id_version' => $userState['id_version'] ?? null,
            'anio' => $anio,
            'km' => $km,
            'version' => $versionIngresada
        ], 'ESPERANDO_FICHA_OFICIAL', 'BOT', $profileName !== '' ? $profileName : null);

        twiml_message_and_save($from, "Perfecto 👍\n\nVersión: {$versionIngresada}\n\n¿Posee ficha oficial?\nRespondé: SI o NO");
    }

    if (!empty($sugerencias)) {
        $opcionesTexto = [];
        $opcionesEstado = [];

        foreach ($sugerencias as $i => $op) {
            $nro = $i + 1;
            $opcionesTexto[] = $nro . '. ' . $op['nombre'];
            $opcionesEstado[(string)$nro] = [
                'id_version' => $op['id_version'],
                'nombre' => $op['nombre']
            ];
        }

        wa_set_user_state($from, [
            'step' => 'version_sugerida',
            'marca' => $marca,
            'id_marca' => $userState['id_marca'] ?? null,
            'modelo' => $modelo,
            'id_model' => $userState['id_model'] ?? null,
            'id_version' => $userState['id_version'] ?? null,
            'anio' => $anio,
            'km' => $km,
            'version_input' => $versionIngresada,
            'version_opciones' => $opcionesEstado
        ], 'ESPERANDO_VERSION', 'BOT', $profileName !== '' ? $profileName : null);

        wa_registrar_input_no_match_safe([
            'telefono' => $from,
            'nombre_cliente' => $profileName,
            'tipo_input' => 'version',
            'valor_input' => $versionIngresada,
            'marca_input' => $marca,
            'modelo_input' => $modelo,
            'version_input' => $versionIngresada,
            'id_marca_ref' => $idMarca > 0 ? $idMarca : null,
            'id_modelo_ref' => $idModel > 0 ? $idModel : null,
            'step_origen' => 'version',
            'mensaje_cliente' => $body,
            'message_sid' => $messageSid
        ]);

        twiml_message_and_save(
            $from,
            "No encontré esa versión exacta.\n\n"
            . "Quizás quisiste decir:\n"
            . implode("\n", $opcionesTexto)
            . "\n\nRespondé con el número o con el nombre correcto."
            . "\nSi preferís continuar con la versión que escribiste, respondé SEGUIR."
            . "\nSi no sabés la versión, respondé NINGUNA."
        );
    }

    wa_set_user_state($from, [
        'step' => 'ficha_oficial',
        'marca' => $marca,
        'id_marca' => $userState['id_marca'] ?? null,
        'modelo' => $modelo,
        'id_model' => $userState['id_model'] ?? null,
        'id_version' => $userState['id_version'] ?? null,
        'anio' => $anio,
        'km' => $km,
        'version' => $versionIngresada
    ], 'ESPERANDO_FICHA_OFICIAL', 'BOT', $profileName !== '' ? $profileName : null);

    twiml_message_and_save($from, "Perfecto 👍\n\nVersión: {$versionIngresada}\n\n¿Posee ficha oficial?\nRespondé: SI o NO");
}

// =========================
// PASO: VERSION SUGERIDA
// =========================
if (($userState['step'] ?? '') === 'version_sugerida') {
    $respuesta = trim($body);
    $respuestaNorm = wa_normalizar_texto($respuesta);

    $marca = trim((string)($userState['marca'] ?? ''));
    $modelo = trim((string)($userState['modelo'] ?? ''));
    $anio = trim((string)($userState['anio'] ?? ''));
    $km = trim((string)($userState['km'] ?? ''));
    $idMarca = (int)($userState['id_marca'] ?? 0);
    $idModel = (int)($userState['id_model'] ?? 0);
    $versionInput = trim((string)($userState['version_input'] ?? ''));
    $opciones = $userState['version_opciones'] ?? [];

    if (wa_version_es_ninguna($respuesta)) {
        wa_set_user_state($from, [
            'step' => 'ficha_oficial',
            'marca' => $marca,
            'id_marca' => $userState['id_marca'] ?? null,
            'modelo' => $modelo,
            'id_model' => $userState['id_model'] ?? null,
            'id_version' => null,
            'anio' => $anio,
            'km' => $km,
            'version' => ''
        ], 'ESPERANDO_FICHA_OFICIAL', 'BOT', $profileName !== '' ? $profileName : null);

        twiml_message_and_save($from, "Perfecto 👍\n\nVersión: sin especificar\n\n¿Posee ficha oficial?\nRespondé: SI o NO");
    }

    if (in_array($respuestaNorm, ['seguir', 'continuar', 'omitir'], true)) {
        wa_set_user_state($from, [
            'step' => 'ficha_oficial',
            'marca' => $marca,
            'id_marca' => $userState['id_marca'] ?? null,
            'modelo' => $modelo,
            'id_model' => $userState['id_model'] ?? null,
            'id_version' => $userState['id_version'] ?? null,
            'anio' => $anio,
            'km' => $km,
            'version' => $versionInput
        ], 'ESPERANDO_FICHA_OFICIAL', 'BOT', $profileName !== '' ? $profileName : null);

        twiml_message_and_save($from, "Perfecto 👍\n\nVersión: {$versionInput}\n\n¿Posee ficha oficial?\nRespondé: SI o NO");
    }

    if (isset($opciones[$respuesta])) {
        $versionFinal = $opciones[$respuesta]['nombre'];
        $idVersion = (int)$opciones[$respuesta]['id_version'];

        wa_set_user_state($from, [
            'step' => 'ficha_oficial',
            'marca' => $marca,
            'id_marca' => $userState['id_marca'] ?? null,
            'modelo' => $modelo,
            'id_model' => $userState['id_model'] ?? null,
            'id_version' => $idVersion,
            'anio' => $anio,
            'km' => $km,
            'version' => $versionFinal
        ], 'ESPERANDO_FICHA_OFICIAL', 'BOT', $profileName !== '' ? $profileName : null);

        twiml_message_and_save($from, "Perfecto 👍\n\nVersión: {$versionFinal}\n\n¿Posee ficha oficial?\nRespondé: SI o NO");
    }

    foreach ($opciones as $op) {
        if (wa_normalizar_texto((string)$op['nombre']) === $respuestaNorm) {
            $versionFinal = (string)$op['nombre'];
            $idVersion = (int)$op['id_version'];

            wa_set_user_state($from, [
                'step' => 'ficha_oficial',
                'marca' => $marca,
                'id_marca' => $userState['id_marca'] ?? null,
                'modelo' => $modelo,
                'id_model' => $userState['id_model'] ?? null,
                'id_version' => $idVersion,
                'anio' => $anio,
                'km' => $km,
                'version' => $versionFinal
            ], 'ESPERANDO_FICHA_OFICIAL', 'BOT', $profileName !== '' ? $profileName : null);

            twiml_message_and_save($from, "Perfecto 👍\n\nVersión: {$versionFinal}\n\n¿Posee ficha oficial?\nRespondé: SI o NO");
        }
    }

    try {
        $versionExacta = wa_buscar_version_exacta($idMarca, $idModel, $respuesta);
    } catch (Throwable $e) {
        wa_log('VERSION_REBUSQUEDA_DB_EXCEPTION', [
            'error' => $e->getMessage(),
            'id_marca' => $idMarca,
            'id_model' => $idModel,
            'respuesta' => $respuesta
        ]);
        $versionExacta = null;
    }

    if ($versionExacta !== null) {
        $versionFinal = $versionExacta['nombre'];

        wa_set_user_state($from, [
            'step' => 'ficha_oficial',
            'marca' => $marca,
            'id_marca' => $userState['id_marca'] ?? null,
            'modelo' => $modelo,
            'id_model' => $userState['id_model'] ?? null,
            'id_version' => $versionExacta['id_version'],
            'anio' => $anio,
            'km' => $km,
            'version' => $versionFinal
        ], 'ESPERANDO_FICHA_OFICIAL', 'BOT', $profileName !== '' ? $profileName : null);

        twiml_message_and_save($from, "Perfecto 👍\n\nVersión: {$versionFinal}\n\n¿Posee ficha oficial?\nRespondé: SI o NO");
    }

    try {
        $nuevasSugerencias = wa_buscar_versiones_similares($idMarca, $idModel, $respuesta, 5);
    } catch (Throwable $e) {
        wa_log('VERSION_REBUSQUEDA_SUGERENCIAS_DB_EXCEPTION', [
            'error' => $e->getMessage(),
            'id_marca' => $idMarca,
            'id_model' => $idModel,
            'respuesta' => $respuesta
        ]);
        $nuevasSugerencias = [];
    }

    if (!empty($nuevasSugerencias)) {
        $opcionesTexto = [];
        $opcionesEstado = [];

        foreach ($nuevasSugerencias as $i => $op) {
            $nro = $i + 1;
            $opcionesTexto[] = $nro . '. ' . $op['nombre'];
            $opcionesEstado[(string)$nro] = [
                'id_version' => $op['id_version'],
                'nombre' => $op['nombre']
            ];
        }

        wa_registrar_input_no_match_safe([
            'telefono' => $from,
            'nombre_cliente' => $profileName,
            'tipo_input' => 'version',
            'valor_input' => $respuesta,
            'marca_input' => $marca,
            'modelo_input' => $modelo,
            'version_input' => $respuesta,
            'id_marca_ref' => $idMarca > 0 ? $idMarca : null,
            'id_modelo_ref' => $idModel > 0 ? $idModel : null,
            'step_origen' => 'version_sugerida',
            'mensaje_cliente' => $body,
            'message_sid' => $messageSid
        ]);

        wa_set_user_state($from, [
            'step' => 'version_sugerida',
            'marca' => $marca,
            'id_marca' => $userState['id_marca'] ?? null,
            'modelo' => $modelo,
            'id_model' => $userState['id_model'] ?? null,
            'id_version' => $userState['id_version'] ?? null,
            'anio' => $anio,
            'km' => $km,
            'version_input' => $respuesta,
            'version_opciones' => $opcionesEstado
        ], 'ESPERANDO_VERSION', 'BOT', $profileName !== '' ? $profileName : null);

        twiml_message_and_save(
            $from,
            "No encontré esa versión exacta.\n\n"
            . "Quizás quisiste decir:\n"
            . implode("\n", $opcionesTexto)
            . "\n\nRespondé con el número o con el nombre correcto."
            . "\nSi preferís continuar con la versión que escribiste, respondé SEGUIR."
            . "\nSi no sabés la versión, respondé NINGUNA."
        );
    }

    wa_set_user_state($from, [
        'step' => 'ficha_oficial',
        'marca' => $marca,
        'id_marca' => $userState['id_marca'] ?? null,
        'modelo' => $modelo,
        'id_model' => $userState['id_model'] ?? null,
        'id_version' => $userState['id_version'] ?? null,
        'anio' => $anio,
        'km' => $km,
        'version' => $respuesta
    ], 'ESPERANDO_FICHA_OFICIAL', 'BOT', $profileName !== '' ? $profileName : null);

    twiml_message_and_save($from, "Perfecto 👍\n\nVersión: {$respuesta}\n\n¿Posee ficha oficial?\nRespondé: SI o NO");
}

// =========================
// PASO: FICHA OFICIAL
// =========================
if (($userState['step'] ?? '') === 'ficha_oficial') {
    $ficha = normalize_yes_no($body);

    if ($ficha === null) {
        twiml_message_and_save($from, "No entendí la respuesta. Respondé solamente: SI o NO");
    }

    $marca = trim((string)($userState['marca'] ?? ''));
    $modelo = trim((string)($userState['modelo'] ?? ''));
    $anio = trim((string)($userState['anio'] ?? ''));
    $km = trim((string)($userState['km'] ?? ''));
    $version = trim((string)($userState['version'] ?? ''));

    wa_set_user_state($from, [
        'step' => 'tipo_venta',
        'marca' => $marca,
        'id_marca' => $userState['id_marca'] ?? null,
        'modelo' => $modelo,
        'id_model' => $userState['id_model'] ?? null,
        'id_version' => $userState['id_version'] ?? null,
        'anio' => $anio,
        'km' => $km,
        'version' => $version,
        'ficha_oficial' => $ficha
    ], 'ESPERANDO_TIPO_VENTA', 'BOT', $profileName !== '' ? $profileName : null);

    twiml_message_and_save(
        $from,
        "Perfecto 👍\n\n"
        . "Ficha oficial: " . strtoupper($ficha) . "\n\n"
        . "Ahora indicame el TIPO DE VENTA:\n"
        . "1 = Venta contado\n"
        . "2 = Entrega como forma de pago"
    );
}

// =========================
// PASO: TIPO VENTA
// =========================
if (($userState['step'] ?? '') === 'tipo_venta') {
    $tipoVenta = normalize_tipo_venta($body);

    if ($tipoVenta === null) {
        twiml_message_and_save(
            $from,
            "No entendí el tipo de venta.\n\n"
            . "Respondé:\n"
            . "1 = Venta contado\n"
            . "2 = Entrega como forma de pago"
        );
    }

    $marca = trim((string)($userState['marca'] ?? ''));
    $modelo = trim((string)($userState['modelo'] ?? ''));
    $anio = trim((string)($userState['anio'] ?? ''));
    $km = trim((string)($userState['km'] ?? ''));
    $version = trim((string)($userState['version'] ?? ''));
    $ficha = trim((string)($userState['ficha_oficial'] ?? ''));

    wa_set_user_state($from, [
        'step' => 'valor_pretendido',
        'marca' => $marca,
        'id_marca' => $userState['id_marca'] ?? null,
        'modelo' => $modelo,
        'id_model' => $userState['id_model'] ?? null,
        'id_version' => $userState['id_version'] ?? null,
        'anio' => $anio,
        'km' => $km,
        'version' => $version,
        'ficha_oficial' => $ficha,
        'tipo_venta' => $tipoVenta
    ], 'ESPERANDO_VALOR_PRETENDIDO', 'BOT', $profileName !== '' ? $profileName : null);

    twiml_message_and_save(
        $from,
        "Perfecto 👍\n\n"
        . "Tipo de venta: " . format_tipo_venta_label($tipoVenta) . "\n\n"
        . "Ahora escribime el VALOR PRETENDIDO.\n"
        . "Ejemplo: 20000"
    );
}

// =========================
// PASO: VALOR PRETENDIDO
// =========================
if (($userState['step'] ?? '') === 'valor_pretendido') {
    $valor = preg_replace('/[^0-9]/', '', $body);

    if ($valor === '') {
        twiml_message_and_save($from, "El valor pretendido no parece válido. Escribime solo números. Ejemplo: 20000");
    }

    $marca = trim((string)($userState['marca'] ?? ''));
    $modelo = trim((string)($userState['modelo'] ?? ''));
    $anio = trim((string)($userState['anio'] ?? ''));
    $km = trim((string)($userState['km'] ?? ''));
    $version = trim((string)($userState['version'] ?? ''));
    $ficha = trim((string)($userState['ficha_oficial'] ?? ''));
    $tipoVenta = trim((string)($userState['tipo_venta'] ?? ''));

    $nuevoEstado = [
        'step' => 'pendiente_humano',
        'marca' => $marca,
        'id_marca' => $userState['id_marca'] ?? null,
        'modelo' => $modelo,
        'id_model' => $userState['id_model'] ?? null,
        'id_version' => $userState['id_version'] ?? null,
        'anio' => $anio,
        'km' => $km,
        'version' => $version,
        'ficha_oficial' => $ficha,
        'tipo_venta' => $tipoVenta,
        'valor_pretendido' => $valor
    ];

    wa_set_user_state(
        $from,
        $nuevoEstado,
        'PENDIENTE_RESPUESTA_HUMANA',
        'HUMANO',
        $profileName !== '' ? $profileName : null
    );

    wa_finalizar_cotizacion_desde_estado($from, $profileName, $nuevoEstado, $valor);
}

// =========================
// PASO: EMAIL (compatibilidad conversaciones viejas)
// =========================
if (($userState['step'] ?? '') === 'email') {
    $valor = trim((string)($userState['valor_pretendido'] ?? ''));

    if ($valor === '') {
        wa_set_user_state(
            $from,
            ['step' => null],
            'INICIO',
            'BOT',
            $profileName !== '' ? $profileName : null
        );

        twiml_message_and_save(
            $from,
            "Se perdió el estado de la cotización.\n\nEscribí COTIZAR para comenzar nuevamente."
        );
    }

    wa_finalizar_cotizacion_desde_estado($from, $profileName, $userState, $valor);
}

// =========================
// AGENDA - DIA
// =========================
if (($userState['step'] ?? '') === 'agenda_dia') {
    $respuesta = trim($body);

    if (wa_es_cancelar_agenda($body)) {
        $nuevoEstado = $userState;
        $nuevoEstado['step'] = 'cerrado';

        wa_set_user_state(
            $from,
            $nuevoEstado,
            'CERRADO',
            'BOT',
            $profileName !== '' ? $profileName : null
        );

        twiml_message_and_save($from, "Perfecto. Cancelé la agenda.\n\nGracias por comunicarte con Motorlider.");
    }

    if (wa_es_atras($body)) {
        wa_set_user_state(
            $from,
            $userState,
            'HUMANO_EN_CONVERSACION',
            'HUMANO',
            $profileName !== '' ? $profileName : null
        );

        twiml_message_and_save(
            $from,
            "Perfecto.\n\nVolvimos un paso atrás.\n"
            . "Si querés coordinar la inspección, respondé AGENDAR."
        );
    }

    $opciones = $userState['agenda_dias_opciones'] ?? [];
    if (!isset($opciones[$respuesta])) {
        twiml_message_and_save(
            $from,
            "No entendí el día elegido.\n\n"
            . "Respondé con el número del día disponible.\n"
            . "También podés escribir ATRAS o CANCELAR."
        );
    }

    $fechaElegida = (string)$opciones[$respuesta]['fecha'];
    $location = (int)($userState['agenda_location'] ?? wa_agenda_location_id());

    $respHorarios = wa_obtener_horarios_agenda($location, $fechaElegida);

    if (($respHorarios['codigo'] ?? 500) != 200 || empty($respHorarios['schedules']['horas_disponibles'])) {
        twiml_message_and_save(
            $from,
            "No encontré horarios disponibles para " . wa_formatear_fecha_chat($fechaElegida) . ".\n\n"
            . "Respondé ATRAS para elegir otro día."
        );
    }

    $horasOpciones = [];
    $lineas = [];
    $horas = $respHorarios['schedules']['horas_disponibles'];
    $max = min(8, count($horas));

    for ($i = 0; $i < $max; $i++) {
        $nro = (string)($i + 1);
        $hora = (string)$horas[$i]['hora'];
        $horasOpciones[$nro] = [
            'hora' => $hora
        ];
        $lineas[] = $nro . ' = ' . substr($hora, 0, 5);
    }

    $nuevoEstado = $userState;
    $nuevoEstado['step'] = 'agenda_hora';
    $nuevoEstado['agenda_fecha'] = $fechaElegida;
    $nuevoEstado['agenda_horas_opciones'] = $horasOpciones;

    wa_set_user_state(
        $from,
        $nuevoEstado,
        'ESPERANDO_HORA',
        'BOT',
        $profileName !== '' ? $profileName : null
    );

    twiml_message_and_save(
        $from,
        "Perfecto 👍\n\n"
        . "Día elegido: " . wa_formatear_fecha_chat($fechaElegida) . "\n\n"
        . "Estos son los horarios disponibles:\n"
        . implode("\n", $lineas)
        . "\n\nRespondé con el número de la hora.\n"
        . "También podés escribir ATRAS o CANCELAR."
    );
}

// =========================
// AGENDA - HORA
// =========================
if (($userState['step'] ?? '') === 'agenda_hora') {
    $respuesta = trim($body);

    if (wa_es_cancelar_agenda($body)) {
        $nuevoEstado = $userState;
        $nuevoEstado['step'] = 'cerrado';

        wa_set_user_state(
            $from,
            $nuevoEstado,
            'CERRADO',
            'BOT',
            $profileName !== '' ? $profileName : null
        );

        twiml_message_and_save($from, "Perfecto. Cancelé la agenda.\n\nGracias por comunicarte con Motorlider.");
    }

    if (wa_es_atras($body)) {
        $location = (int)($userState['agenda_location'] ?? wa_agenda_location_id());
        $disp = wa_obtener_disponibilidad_agenda($location);

        if (($disp['codigo'] ?? 500) != 200 || empty($disp['availability']) || !is_array($disp['availability'])) {
            twiml_message_and_save(
                $from,
                "No pude volver a cargar los días disponibles.\n\nIntentá nuevamente respondiendo AGENDAR."
            );
        }

        $opciones = [];
        $lineas = [];
        $max = min(7, count($disp['availability']));

        for ($i = 0; $i < $max; $i++) {
            $nro = (string)($i + 1);
            $fecha = (string)$disp['availability'][$i]['fecha'];
            $opciones[$nro] = ['fecha' => $fecha];
            $lineas[] = $nro . ' = ' . wa_formatear_fecha_chat($fecha);
        }

        $nuevoEstado = $userState;
        $nuevoEstado['step'] = 'agenda_dia';
        $nuevoEstado['agenda_dias_opciones'] = $opciones;
        unset($nuevoEstado['agenda_fecha'], $nuevoEstado['agenda_hora'], $nuevoEstado['agenda_horas_opciones']);

        wa_set_user_state(
            $from,
            $nuevoEstado,
            'ESPERANDO_FECHA',
            'BOT',
            $profileName !== '' ? $profileName : null
        );

        twiml_message_and_save(
            $from,
            "Perfecto 👍\n\n"
            . "Volvemos a elegir el día.\n\n"
            . implode("\n", $lineas)
            . "\n\nRespondé con el número del día."
        );
    }

    $opciones = $userState['agenda_horas_opciones'] ?? [];
    if (!isset($opciones[$respuesta])) {
        twiml_message_and_save(
            $from,
            "No entendí la hora elegida.\n\n"
            . "Respondé con el número del horario.\n"
            . "También podés escribir ATRAS o CANCELAR."
        );
    }

    $horaElegida = (string)$opciones[$respuesta]['hora'];

    $nuevoEstado = $userState;
    $nuevoEstado['step'] = 'agenda_confirmar';
    $nuevoEstado['agenda_hora'] = $horaElegida;

    wa_set_user_state(
        $from,
        $nuevoEstado,
        'ESPERANDO_HORA',
        'BOT',
        $profileName !== '' ? $profileName : null
    );

    twiml_message_and_save(
        $from,
        "Perfecto 👍\n\n"
        . "Reserva solicitada:\n"
        . "Fecha: " . wa_formatear_fecha_chat((string)$nuevoEstado['agenda_fecha']) . "\n"
        . "Hora: " . substr($horaElegida, 0, 5) . "\n\n"
        . "Respondé CONFIRMAR para agendar.\n"
        . "O escribí ATRAS / CANCELAR."
    );
}

// =========================
// AGENDA - CONFIRMAR
// =========================
if (($userState['step'] ?? '') === 'agenda_confirmar') {
    $respuestaNorm = wa_normalizar_texto($body);

    if (wa_es_cancelar_agenda($body)) {
        $nuevoEstado = $userState;
        $nuevoEstado['step'] = 'cerrado';

        wa_set_user_state(
            $from,
            $nuevoEstado,
            'CERRADO',
            'BOT',
            $profileName !== '' ? $profileName : null
        );

        twiml_message_and_save($from, "Perfecto. Cancelé la agenda.\n\nGracias por comunicarte con Motorlider.");
    }

    if (wa_es_atras($body)) {
        $nuevoEstado = $userState;
        $nuevoEstado['step'] = 'agenda_hora';
        unset($nuevoEstado['agenda_hora']);

        wa_set_user_state(
            $from,
            $nuevoEstado,
            'ESPERANDO_HORA',
            'BOT',
            $profileName !== '' ? $profileName : null
        );

        $horasOpciones = $nuevoEstado['agenda_horas_opciones'] ?? [];
        $lineas = [];

        foreach ($horasOpciones as $nro => $item) {
            $lineas[] = $nro . ' = ' . substr((string)$item['hora'], 0, 5);
        }

        twiml_message_and_save(
            $from,
            "Perfecto 👍\n\n"
            . "Volvemos a elegir el horario.\n\n"
            . implode("\n", $lineas)
            . "\n\nRespondé con el número del horario disponible."
        );
    }

    if (!in_array($respuestaNorm, ['confirmar', 'confirmo', 'si', 'ok'], true)) {
        twiml_message_and_save(
            $from,
            "Para agendar, respondé CONFIRMAR.\n"
            . "También podés escribir ATRAS o CANCELAR."
        );
    }

    $location = (int)($userState['agenda_location'] ?? wa_agenda_location_id());
    $fecha = (string)($userState['agenda_fecha'] ?? '');
    $hora = (string)($userState['agenda_hora'] ?? '');

    $conv = wa_get_conversation($from);
    $idCotizacion = (int)($conv['id_cotizacion'] ?? 0);

    $payload = [
        'location' => $location,
        'date' => $fecha,
        'hora' => $hora,
        'modelo' => (string)($userState['id_model'] ?? ''),
        'marca' => (string)($userState['id_marca'] ?? ''),
        'anio' => (string)($userState['anio'] ?? ''),
        'familia' => (string)($userState['version'] ?? ''),
        'auto' => trim(
            (string)($userState['marca'] ?? '') . ' ' .
            (string)($userState['modelo'] ?? '') . ' ' .
            (string)($userState['anio'] ?? '') . ' ' .
            (string)($userState['version'] ?? '')
        ),
        'nombre' => (string)($conv['nombre'] ?? ($profileName !== '' ? $profileName : 'Cliente WhatsApp')),
        'email' => (string)($conv['email'] ?? ''),
        'telefono' => $from,
        'id_cotizacion' => $idCotizacion
    ];

    $respAgenda = wa_agendar_inspeccion($payload);

    if (($respAgenda['codigo'] ?? 500) != 200) {
        twiml_message_and_save(
            $from,
            "No pude confirmar la agenda en este momento.\n\n"
            . "Probá nuevamente o un asesor lo estará coordinando."
        );
    }

    $nuevoEstado = $userState;
    $nuevoEstado['step'] = 'agendado';

    wa_set_user_state(
        $from,
        $nuevoEstado,
        'AGENDADO',
        'BOT',
        $profileName !== '' ? $profileName : null,
        (string)($conv['email'] ?? ''),
        $idCotizacion > 0 ? $idCotizacion : null
    );

    twiml_message_and_save(
        $from,
        "¡Agenda confirmada! ✅\n\n"
        . "Fecha: " . wa_formatear_fecha_chat($fecha) . "\n"
        . "Hora: " . substr($hora, 0, 5) . "\n\n"
        . "Te esperamos en Av. de las Américas 7868."
    );
}

// =========================
// DEFAULT
// =========================
twiml_message_and_save(
    $from,
    "Recibí tu mensaje: {$body}\n\n"
    . "Escribí COTIZAR para iniciar el flujo."
);