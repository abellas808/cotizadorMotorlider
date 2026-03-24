<?php
declare(strict_types=1);

date_default_timezone_set('America/Montevideo');

/**
 * Webhook WhatsApp Twilio - Fase 4
 * - recibe mensaje entrante
 * - valida firma Twilio
 * - guarda estado simple por usuario en storage.json
 * - flujo guiado completo
 * - al finalizar llama al cotizador real
 */

// =========================
// CONFIG
// =========================
const TWILIO_AUTH_TOKEN = '58f767d26211d9d0c20ea687df00b4c3';
const COTIZADOR_BASE_URL = 'https://carplay.uy/apicotizador/cotizadorPublico/';

function wa_log_file(): string
{
    return __DIR__ . '/logs/whatsapp_webhook_' . date('Y-m-d') . '.log';
}

function wa_storage_file(): string
{
    return __DIR__ . '/storage.json';
}

// =========================
// HELPERS
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

function build_current_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    return $scheme . '://' . $host . $uri;
}

function validate_twilio_signature(string $authToken): bool
{
    if ($authToken === '') {
        wa_log('SIGNATURE_SKIPPED', ['reason' => 'auth token vacío']);
        return true;
    }

    $headers = get_request_headers_lower();
    $twilioSignature = $headers['x-twilio-signature'] ?? '';

    if ($twilioSignature === '') {
        wa_log('SIGNATURE_FAIL', ['reason' => 'header ausente']);
        return false;
    }

    $url = build_current_url();

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

function load_state(): array
{
    $file = wa_storage_file();

    if (!file_exists($file)) {
        return [];
    }

    $json = @file_get_contents($file);
    if ($json === false || trim($json) === '') {
        return [];
    }

    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function save_state(array $data): void
{
    @file_put_contents(
        wa_storage_file(),
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

function get_user_state(string $from): array
{
    $all = load_state();
    return (isset($all[$from]) && is_array($all[$from])) ? $all[$from] : [];
}

function set_user_state(string $from, array $state): void
{
    $all = load_state();
    $all[$from] = $state;
    save_state($all);

    wa_log('STATE_SET', [
        'from' => $from,
        'state' => $state
    ]);
}

function clear_user_state(string $from): void
{
    $all = load_state();
    if (isset($all[$from])) {
        unset($all[$from]);
        save_state($all);
    }

    wa_log('STATE_CLEAR', ['from' => $from]);
}

function body_to_lower(string $text): string
{
    return function_exists('mb_strtolower')
        ? mb_strtolower($text, 'UTF-8')
        : strtolower($text);
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

function normalize_brand_for_url(string $brand): string
{
    return trim($brand);
}

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

function wa_number($value, int $decimals = 0): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    if (!is_numeric((string)$value)) {
        return (string)$value;
    }

    if ($decimals <= 0) {
        return (string)wa_redondear_motorlider((float)$value);
    }

    return number_format((float)$value, $decimals, '.', '');
}

function build_whatsapp_result_message(
    string $marca,
    string $modelo,
    string $anio,
    string $km,
    string $version,
    string $ficha,
    string $tipoVenta,
    string $valor,
    string $email,
    array $apiResult
): string {
    $ok = (
        (isset($apiResult['ok']) && $apiResult['ok'] === true) ||
        (isset($apiResult['error']) && ($apiResult['error'] === 0 || $apiResult['error'] === false || $apiResult['error'] === '0'))
    );

    $resultado = [];
    if (isset($apiResult['resultado']) && is_array($apiResult['resultado'])) {
        $resultado = $apiResult['resultado'];
    } elseif (isset($apiResult['valores']) && is_array($apiResult['valores'])) {
        $resultado = $apiResult['valores'];
    }

    $idCotizacion = $apiResult['id_cotizacion']
        ?? $apiResult['cotizacion']
        ?? $apiResult['cotizacion_id']
        ?? $resultado['id_cotizacion']
        ?? '';

    if (!$ok || empty($resultado)) {
        $mensaje = $apiResult['mensaje'] ?? $apiResult['msg'] ?? 'No se pudo obtener una cotización válida.';

        return
            "⚠️ No se pudo completar la cotización\n\n" .
            "Marca: {$marca}\n" .
            "Modelo: {$modelo}\n" .
            "Año: {$anio}\n" .
            "Kilómetros: {$km}\n" .
            "Versión: {$version}\n" .
            "Ficha oficial: " . strtoupper($ficha) . "\n" .
            "Tipo de venta: " . format_tipo_venta_label($tipoVenta) . "\n" .
            "Valor pretendido: USD {$valor}\n" .
            "Email: {$email}\n\n" .
            "Detalle: {$mensaje}\n\n" .
            "Podés escribir COTIZAR para volver a intentarlo.";
    }

    $min = $resultado['min'] ?? $resultado['valor_minimo'] ?? null;
    $max = $resultado['max'] ?? $resultado['valor_maximo'] ?? null;
    $avg = $resultado['avg'] ?? $resultado['valor_promedio'] ?? null;
    $count = $resultado['count'] ?? $resultado['total'] ?? null;

    $valorMinMotorlider = $resultado['valor_minimo_motorlider'] ?? null;
    $valorMaxMotorlider = $resultado['valor_maximo_motorlider'] ?? null;
    $valorPromMotorlider = $resultado['valor_promedio_motorlider'] ?? null;
    $promedioBaseMotorlider = $resultado['promedio_base_motorlider'] ?? null;
    $vpretendidoAplicado = !empty($resultado['vpretendido_aplicado']);

    $msg =
        "✅ Cotización generada correctamente\n\n" .
        ($idCotizacion !== '' ? "ID Cotización: {$idCotizacion}\n" : '') .
        "Vehículo: {$marca} {$modelo}\n" .
        "Año: {$anio}\n" .
        "Kilómetros: {$km}\n" .
        "Versión: {$version}\n" .
        "Ficha oficial: " . strtoupper($ficha) . "\n" .
        "Tipo de venta: " . format_tipo_venta_label($tipoVenta) . "\n" .
        "Valor pretendido: USD {$valor}\n\n" .
        "📊 Mercado\n" .
        "- Comparables usados: " . wa_money($count) . "\n" .
        "- Mínimo: USD " . wa_money($min) . "\n" .
        "- Máximo: USD " . wa_money($max) . "\n" .
        "- Promedio: USD " . wa_money($avg) . "\n\n" .
        "🏷️ Motorlider\n" .
        "- Base: USD " . wa_money($promedioBaseMotorlider) . "\n" .
        "- Mínimo: USD " . wa_money($valorMinMotorlider) . "\n" .
        "- Máximo: USD " . wa_money($valorMaxMotorlider) . "\n" .
        "- Promedio: USD " . wa_money($valorPromMotorlider);

    if ($vpretendidoAplicado) {
        $msg .= "\n\n⚠️ Se aplicó el valor pretendido del cliente.";
    }

    $msg .= "\n\nSi querés hacer otra cotización, escribí COTIZAR.";

    return $msg;
}

// =========================
// MAIN
// =========================
wa_log('INCOMING_RAW', [
    'post' => $_POST,
    'server' => [
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? null,
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null,
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? null,
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

$from = trim((string)($_POST['From'] ?? ''));
$to = trim((string)($_POST['To'] ?? ''));
$body = trim((string)($_POST['Body'] ?? ''));
$messageSid = trim((string)($_POST['MessageSid'] ?? ''));
$profileName = trim((string)($_POST['ProfileName'] ?? ''));

wa_log('INCOMING_PARSED', [
    'from' => $from,
    'to' => $to,
    'body' => $body,
    'message_sid' => $messageSid,
    'profile_name' => $profileName
]);

$bodyLower = body_to_lower($body);
$userState = get_user_state($from);

// =========================
// COMANDOS GENERALES
// =========================
if ($bodyLower === 'hola' || $bodyLower === 'hi' || $bodyLower === 'menu') {
    clear_user_state($from);

    twiml_message(
        "¡Hola" . ($profileName !== '' ? " {$profileName}" : "") . "! "
        . "Bienvenido al cotizador de vehículos de Motorlider.\n\n"
        . "Escribí COTIZAR para comenzar."
    );
}

if ($bodyLower === 'cancelar' || $bodyLower === 'salir') {
    clear_user_state($from);

    twiml_message(
        "Perfecto. Cancelé el flujo actual.\n\n"
        . "Cuando quieras volver a empezar, escribí COTIZAR."
    );
}

if ($bodyLower === 'cotizar') {
    set_user_state($from, [
        'step' => 'marca'
    ]);

    twiml_message(
        "Perfecto. Vamos a comenzar la cotización.\n\n"
        . "Primer dato: escribime la MARCA del vehículo."
    );
}

// =========================
// FLUJO PASO A PASO
// =========================

// Paso: MARCA
if (($userState['step'] ?? '') === 'marca') {
    $marca = trim($body);

    if ($marca === '') {
        twiml_message("No pude leer la marca. Escribime la MARCA del vehículo.");
    }

    set_user_state($from, [
        'step' => 'modelo',
        'marca' => $marca
    ]);

    twiml_message(
        "Perfecto 👍\n\n"
        . "Marca: {$marca}\n\n"
        . "Ahora escribime el MODELO."
    );
}

// Paso: MODELO
if (($userState['step'] ?? '') === 'modelo') {
    $modelo = trim($body);
    $marca = trim((string)($userState['marca'] ?? ''));

    if ($modelo === '') {
        twiml_message("No pude leer el modelo. Escribime el MODELO del vehículo.");
    }

    set_user_state($from, [
        'step' => 'anio',
        'marca' => $marca,
        'modelo' => $modelo
    ]);

    twiml_message(
        "Excelente 👍\n\n"
        . "Marca: {$marca}\n"
        . "Modelo: {$modelo}\n\n"
        . "Ahora escribime el AÑO del vehículo. Ejemplo: 2021"
    );
}

// Paso: AÑO
if (($userState['step'] ?? '') === 'anio') {
    $anio = preg_replace('/[^0-9]/', '', $body);
    $marca = trim((string)($userState['marca'] ?? ''));
    $modelo = trim((string)($userState['modelo'] ?? ''));

    if ($anio === '' || strlen($anio) !== 4) {
        twiml_message("El año no parece válido. Escribime un año de 4 dígitos. Ejemplo: 2021");
    }

    set_user_state($from, [
        'step' => 'km',
        'marca' => $marca,
        'modelo' => $modelo,
        'anio' => $anio
    ]);

    twiml_message(
        "Perfecto 👍\n\n"
        . "Marca: {$marca}\n"
        . "Modelo: {$modelo}\n"
        . "Año: {$anio}\n\n"
        . "Ahora escribime los KILÓMETROS. Ejemplo: 85000"
    );
}

// Paso: KM
if (($userState['step'] ?? '') === 'km') {
    $km = preg_replace('/[^0-9]/', '', $body);
    $marca = trim((string)($userState['marca'] ?? ''));
    $modelo = trim((string)($userState['modelo'] ?? ''));
    $anio = trim((string)($userState['anio'] ?? ''));

    if ($km === '') {
        twiml_message("Los kilómetros no parecen válidos. Escribime solo números. Ejemplo: 85000");
    }

    set_user_state($from, [
        'step' => 'version',
        'marca' => $marca,
        'modelo' => $modelo,
        'anio' => $anio,
        'km' => $km
    ]);

    twiml_message(
        "Perfecto 👍\n\n"
        . "Marca: {$marca}\n"
        . "Modelo: {$modelo}\n"
        . "Año: {$anio}\n"
        . "Kilómetros: {$km}\n\n"
        . "Ahora escribime la VERSIÓN.\n"
        . "Ejemplo: Full, GLS, LTZ, GS"
    );
}

// Paso: VERSION
if (($userState['step'] ?? '') === 'version') {
    $version = trim($body);
    $marca = trim((string)($userState['marca'] ?? ''));
    $modelo = trim((string)($userState['modelo'] ?? ''));
    $anio = trim((string)($userState['anio'] ?? ''));
    $km = trim((string)($userState['km'] ?? ''));

    if ($version === '') {
        twiml_message("No pude leer la versión. Escribime la VERSIÓN del vehículo.");
    }

    set_user_state($from, [
        'step' => 'ficha_oficial',
        'marca' => $marca,
        'modelo' => $modelo,
        'anio' => $anio,
        'km' => $km,
        'version' => $version
    ]);

    twiml_message(
        "Perfecto 👍\n\n"
        . "Versión: {$version}\n\n"
        . "¿Posee ficha oficial?\n"
        . "Respondé: SI o NO"
    );
}

// Paso: FICHA OFICIAL
if (($userState['step'] ?? '') === 'ficha_oficial') {
    $ficha = normalize_yes_no($body);

    if ($ficha === null) {
        twiml_message("No entendí la respuesta. Respondé solamente: SI o NO");
    }

    $marca = trim((string)($userState['marca'] ?? ''));
    $modelo = trim((string)($userState['modelo'] ?? ''));
    $anio = trim((string)($userState['anio'] ?? ''));
    $km = trim((string)($userState['km'] ?? ''));
    $version = trim((string)($userState['version'] ?? ''));

    set_user_state($from, [
        'step' => 'tipo_venta',
        'marca' => $marca,
        'modelo' => $modelo,
        'anio' => $anio,
        'km' => $km,
        'version' => $version,
        'ficha_oficial' => $ficha
    ]);

    twiml_message(
        "Perfecto 👍\n\n"
        . "Ficha oficial: " . strtoupper($ficha) . "\n\n"
        . "Ahora indicame el TIPO DE VENTA:\n"
        . "1 = Venta contado\n"
        . "2 = Entrega como forma de pago"
    );
}

// Paso: TIPO VENTA
if (($userState['step'] ?? '') === 'tipo_venta') {
    $tipoVenta = normalize_tipo_venta($body);

    if ($tipoVenta === null) {
        twiml_message(
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

    set_user_state($from, [
        'step' => 'valor_pretendido',
        'marca' => $marca,
        'modelo' => $modelo,
        'anio' => $anio,
        'km' => $km,
        'version' => $version,
        'ficha_oficial' => $ficha,
        'tipo_venta' => $tipoVenta
    ]);

    twiml_message(
        "Perfecto 👍\n\n"
        . "Tipo de venta: " . format_tipo_venta_label($tipoVenta) . "\n\n"
        . "Ahora escribime el VALOR PRETENDIDO.\n"
        . "Ejemplo: 20000"
    );
}

// Paso: VALOR PRETENDIDO
if (($userState['step'] ?? '') === 'valor_pretendido') {
    $valor = preg_replace('/[^0-9]/', '', $body);

    if ($valor === '') {
        twiml_message("El valor pretendido no parece válido. Escribime solo números. Ejemplo: 20000");
    }

    $marca = trim((string)($userState['marca'] ?? ''));
    $modelo = trim((string)($userState['modelo'] ?? ''));
    $anio = trim((string)($userState['anio'] ?? ''));
    $km = trim((string)($userState['km'] ?? ''));
    $version = trim((string)($userState['version'] ?? ''));
    $ficha = trim((string)($userState['ficha_oficial'] ?? ''));
    $tipoVenta = trim((string)($userState['tipo_venta'] ?? ''));

    set_user_state($from, [
        'step' => 'email',
        'marca' => $marca,
        'modelo' => $modelo,
        'anio' => $anio,
        'km' => $km,
        'version' => $version,
        'ficha_oficial' => $ficha,
        'tipo_venta' => $tipoVenta,
        'valor_pretendido' => $valor
    ]);

    twiml_message(
        "Perfecto 👍\n\n"
        . "Valor pretendido: USD {$valor}\n\n"
        . "Por último, escribime tu EMAIL."
    );
}

// Paso: EMAIL
if (($userState['step'] ?? '') === 'email') {
    $email = trim($body);

    if (!is_valid_email_simple($email)) {
        twiml_message("El email no parece válido. Escribilo nuevamente. Ejemplo: cliente@email.com");
    }

    $marca = trim((string)($userState['marca'] ?? ''));
    $modelo = trim((string)($userState['modelo'] ?? ''));
    $anio = trim((string)($userState['anio'] ?? ''));
    $km = trim((string)($userState['km'] ?? ''));
    $version = trim((string)($userState['version'] ?? ''));
    $ficha = trim((string)($userState['ficha_oficial'] ?? ''));
    $tipoVenta = trim((string)($userState['tipo_venta'] ?? ''));
    $valor = trim((string)($userState['valor_pretendido'] ?? ''));

    set_user_state($from, [
        'step' => 'completo',
        'marca' => $marca,
        'modelo' => $modelo,
        'anio' => $anio,
        'km' => $km,
        'version' => $version,
        'ficha_oficial' => $ficha,
        'tipo_venta' => $tipoVenta,
        'valor_pretendido' => $valor,
        'email' => $email
    ]);

    wa_log('FLOW_COMPLETED', [
        'from' => $from,
        'data' => get_user_state($from)
    ]);

    $brandUrl = normalize_brand_for_url($marca);

    $apiPayload = [
        'marca' => $marca,
        'modelo' => $modelo,
        'anio' => $anio,
        'version' => $version,
        'km' => $km,
        'ficha_tecnica' => ($ficha === 'si') ? 1 : 0,
        'cantidad_duenios' => 1,
        'valor_pretendido' => $valor,
        'venta_permuta' => ($tipoVenta === 'entrega_forma_pago') ? 1 : 0,
        'nombre_auto' => trim($marca . ' ' . $modelo . ' ' . $anio . ' ' . $version),
        'nombre' => $profileName !== '' ? $profileName : 'Cliente WhatsApp',
        'email' => $email,
        'telefono' => $from
    ];

    $apiResult = cotizar_api($brandUrl, $apiPayload);

    set_user_state($from, [
        'step' => 'resultado_enviado',
        'marca' => $marca,
        'modelo' => $modelo,
        'anio' => $anio,
        'km' => $km,
        'version' => $version,
        'ficha_oficial' => $ficha,
        'tipo_venta' => $tipoVenta,
        'valor_pretendido' => $valor,
        'email' => $email,
        'api_result' => $apiResult
    ]);

    $message = build_whatsapp_result_message(
        $marca,
        $modelo,
        $anio,
        $km,
        $version,
        $ficha,
        $tipoVenta,
        $valor,
        $email,
        $apiResult
    );

    twiml_message($message);
}

// =========================
// DEFAULT
// =========================
twiml_message(
    "Recibí tu mensaje: {$body}\n\n"
    . "Escribí COTIZAR para iniciar el flujo."
);