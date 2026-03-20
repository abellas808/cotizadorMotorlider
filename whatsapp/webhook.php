<?php
declare(strict_types=1);

date_default_timezone_set('America/Montevideo');

/**
 * Webhook básico WhatsApp Twilio - Fase 2
 * - recibe mensaje entrante
 * - valida firma Twilio
 * - guarda estado simple por usuario en storage.json
 * - flujo guiado inicial:
 *   hola -> cotizar -> marca -> modelo
 */

// =========================
// CONFIG
// =========================
const TWILIO_AUTH_TOKEN = '58f767d26211d9d0c20ea687df00b4c3';

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

    // 🔥 IMPORTANTE: refrescar estado
    $userState = get_user_state($from);

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

    $userState = get_user_state($from); // 🔥

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

    $userState = get_user_state($from); // 🔥

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

    $userState = get_user_state($from); // 🔥

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
        'step' => 'fin',
        'marca' => $marca,
        'modelo' => $modelo,
        'anio' => $anio,
        'km' => $km
    ]);

    $userState = get_user_state($from); // 🔥

    twiml_message(
        "🔥 Datos recibidos correctamente\n\n"
        . "Marca: {$marca}\n"
        . "Modelo: {$modelo}\n"
        . "Año: {$anio}\n"
        . "Kilómetros: {$km}\n\n"
        . "Fase siguiente: pedir versión, ficha oficial, tipo de venta y valor pretendido.\n\n"
        . "Si querés reiniciar, escribí COTIZAR."
    );
}

// =========================
// DEFAULT
// =========================
twiml_message(
    "Recibí tu mensaje: {$body}\n\n"
    . "Escribí COTIZAR para iniciar el flujo."
);