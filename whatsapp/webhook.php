<?php
declare(strict_types=1);

date_default_timezone_set('America/Montevideo');

/**
 * Webhook WhatsApp Twilio
 * - recibe mensaje entrante
 * - valida firma Twilio
 * - guarda estado simple por usuario en storage.json
 * - flujo guiado completo
 * - valida MARCA contra act_marcas
 * - valida MODELO contra act_modelo
 * - sugiere VERSION pero permite continuar con texto libre
 * - arrastra correctamente id_marca, id_model, id_version
 * - al finalizar llama al cotizador real
 */

// =========================
// CONFIG
// =========================
const TWILIO_AUTH_TOKEN = 'aa4367dc2659286fcf0f8cf0ddc6f487';
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

function wa_storage_file(): string
{
    return __DIR__ . '/storage.json';
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

function normalize_brand_for_url(int $brandId): string
{
    return (string)$brandId;
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
// HELPERS DB / CATÁLOGOS
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
            'id' => (int)$row['id'],
            'id_marca' => (int)$row['id_marca'],
            'nombre' => trim((string)$row['nombre']),
            'cotisa' => (int)$row['cotisa'],
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

        $nombreNorm = wa_normalizar_texto($marca['nombre']);
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
            'id' => $marca['id'],
            'id_marca' => $marca['id_marca'],
            'nombre' => $marca['nombre'],
            'prioridad' => $marca['prioridad'],
            'score' => $score
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
            'id' => (int)$row['id'],
            'id_marca' => (int)$row['id_marca'],
            'id_model' => (int)$row['id_model'],
            'nombre' => trim((string)$row['nombre']),
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
            'id' => $modelo['id'],
            'id_marca' => $modelo['id_marca'],
            'id_model' => $modelo['id_model'],
            'nombre' => $modelo['nombre'],
            'prioridad' => $modelo['prioridad'],
            'score' => $score
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
            'id_marca' => (int)$row['id_marca'],
            'id_modelo' => (int)$row['id_modelo'],
            'nombre' => trim((string)$row['nombre']),
            'activo' => (int)$row['activo']
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
            'id_marca' => $version['id_marca'],
            'id_modelo' => $version['id_modelo'],
            'nombre' => $version['nombre'],
            'score' => $score
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
    array $apiResult,
    array $apiPayload = []
): string {
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

    $count = (int)($resultado['count'] ?? $resultado['total'] ?? 0);

    $valorMinMotorlider = $resultado['valor_minimo_motorlider'] ?? null;
    $valorMaxMotorlider = $resultado['valor_maximo_motorlider'] ?? null;
    $valorPromMotorlider = $resultado['valor_promedio_motorlider'] ?? null;
    $promedioBaseMotorlider = $resultado['promedio_base_motorlider'] ?? null;

    $hayValoresMotorlider =
        $valorMinMotorlider !== null ||
        $valorMaxMotorlider !== null ||
        $valorPromMotorlider !== null ||
        $promedioBaseMotorlider !== null;

    $okApi = (
        (isset($apiResult['ok']) && $apiResult['ok'] === true) ||
        (isset($apiResult['error']) && ($apiResult['error'] === 0 || $apiResult['error'] === false || $apiResult['error'] === '0'))
    );

    $cotizacionValida = ($okApi && ($count > 0 || $hayValoresMotorlider));

    $debugPayload = "";
    if (!empty($apiPayload)) {
        $debugPayload =
            "\n\n--- DEBUG PAYLOAD ---\n" .
            "id_marca: " . (($apiPayload['id_marca'] ?? null) === null ? 'NULL' : (string)$apiPayload['id_marca']) . "\n" .
            "id_model: " . (($apiPayload['id_model'] ?? null) === null ? 'NULL' : (string)$apiPayload['id_model']) . "\n" .
            "id_modelo: " . (($apiPayload['id_modelo'] ?? null) === null ? 'NULL' : (string)$apiPayload['id_modelo']) . "\n" .
            "id_version: " . (($apiPayload['id_version'] ?? null) === null ? 'NULL' : (string)$apiPayload['id_version']) . "\n" .
            "marca: " . (string)($apiPayload['marca'] ?? '') . "\n" .
            "modelo: " . (string)($apiPayload['modelo'] ?? '') . "\n" .
            "version: " . (string)($apiPayload['version'] ?? '');
    }

    if (!$cotizacionValida) {
        $mensaje = $apiResult['mensaje'] ?? $apiResult['msg'] ?? 'No se encontraron valores cotizables para este vehículo.';

        return
            "⚠️ No se pudo generar una cotización con valores\n\n" .
            ($idCotizacion !== '' ? "ID Cotización: {$idCotizacion}\n" : '') .
            "Marca: {$marca}\n" .
            "Modelo: {$modelo}\n" .
            "Año: {$anio}\n" .
            "Kilómetros: {$km}\n" .
            "Versión: {$version}\n" .
            "Ficha oficial: " . strtoupper($ficha) . "\n" .
            "Tipo de venta: " . format_tipo_venta_label($tipoVenta) . "\n" .
            "Valor pretendido: USD {$valor}\n" .
            "Email: {$email}\n\n" .
            "Detalle: {$mensaje}" .
            $debugPayload .
            "\n\nUn asesor revisará el caso manualmente.";
    }

    $min = $resultado['min'] ?? $resultado['valor_minimo'] ?? null;
    $max = $resultado['max'] ?? $resultado['valor_maximo'] ?? null;
    $avg = $resultado['avg'] ?? $resultado['valor_promedio'] ?? null;
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

    $msg .= $debugPayload;
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
// PASO: MARCA
// =========================
if (($userState['step'] ?? '') === 'marca') {
    $marcaIngresada = trim($body);

    if ($marcaIngresada === '') {
        twiml_message("No pude leer la marca. Escribime la MARCA del vehículo.");
    }

    try {
        $marcaExacta = wa_buscar_marca_exacta($marcaIngresada);
    } catch (Throwable $e) {
        wa_log('MARCA_DB_EXCEPTION', ['error' => $e->getMessage()]);
        twiml_message("Ocurrió un problema consultando el catálogo de marcas. Probá nuevamente en unos instantes.");
    }

    if ($marcaExacta !== null) {
        $marcaFinal = $marcaExacta['nombre'];

        set_user_state($from, [
            'step' => 'modelo',
            'marca' => $marcaFinal,
            'id_marca' => $marcaExacta['id_marca']
        ]);

        twiml_message(
            "Perfecto 👍\n\n"
            . "Marca: {$marcaFinal}\n\n"
            . "Ahora escribime el MODELO."
        );
    }

    try {
        $sugerencias = wa_buscar_marcas_similares($marcaIngresada, 5);
    } catch (Throwable $e) {
        wa_log('MARCA_SUGERENCIAS_DB_EXCEPTION', ['error' => $e->getMessage()]);
        twiml_message("Ocurrió un problema consultando el catálogo de marcas. Probá nuevamente en unos instantes.");
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

        set_user_state($from, [
            'step' => 'marca_sugerida',
            'marca_input' => $marcaIngresada,
            'marca_opciones' => $opcionesEstado
        ]);

        twiml_message(
            "No encontré esa marca exacta.\n\n"
            . "Quizás quisiste decir:\n"
            . implode("\n", $opcionesTexto)
            . "\n\nRespondé con el número o con el nombre correcto."
        );
    }

    twiml_message(
        "No encontré esa marca.\n\n"
        . "Probá escribiendo nuevamente el nombre de la marca."
    );
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

        set_user_state($from, [
            'step' => 'modelo',
            'marca' => $marcaFinal,
            'id_marca' => $idMarca
        ]);

        twiml_message(
            "Perfecto 👍\n\n"
            . "Marca: {$marcaFinal}\n\n"
            . "Ahora escribime el MODELO."
        );
    }

    foreach ($opciones as $op) {
        if (wa_normalizar_texto((string)$op['nombre']) === $respuestaNorm) {
            $marcaFinal = (string)$op['nombre'];
            $idMarca = (int)$op['id_marca'];

            set_user_state($from, [
                'step' => 'modelo',
                'marca' => $marcaFinal,
                'id_marca' => $idMarca
            ]);

            twiml_message(
                "Perfecto 👍\n\n"
                . "Marca: {$marcaFinal}\n\n"
                . "Ahora escribime el MODELO."
            );
        }
    }

    $opcionesTexto = [];
    foreach ($opciones as $nro => $op) {
        $opcionesTexto[] = $nro . '. ' . $op['nombre'];
    }

    twiml_message(
        "No entendí la opción elegida.\n\n"
        . "Respondé con el número o con uno de estos nombres:\n"
        . implode("\n", $opcionesTexto)
    );
}

// =========================
// PASO: MODELO
// =========================
if (($userState['step'] ?? '') === 'modelo') {
    $modeloIngresado = trim($body);
    $marca = trim((string)($userState['marca'] ?? ''));
    $idMarca = (int)($userState['id_marca'] ?? 0);

    if ($modeloIngresado === '') {
        twiml_message("No pude leer el modelo. Escribime el MODELO del vehículo.");
    }

    if ($idMarca <= 0) {
        clear_user_state($from);
        twiml_message(
            "Se perdió la referencia de la marca seleccionada.\n\n"
            . "Escribí COTIZAR para comenzar nuevamente."
        );
    }

    try {
        $modeloExacto = wa_buscar_modelo_exacto($idMarca, $modeloIngresado);
    } catch (Throwable $e) {
        wa_log('MODELO_DB_EXCEPTION', ['error' => $e->getMessage(), 'id_marca' => $idMarca]);
        twiml_message("Ocurrió un problema consultando el catálogo de modelos. Probá nuevamente en unos instantes.");
    }

    if ($modeloExacto !== null) {
        $modeloFinal = $modeloExacto['nombre'];

        set_user_state($from, [
            'step' => 'anio',
            'marca' => $marca,
            'id_marca' => $idMarca,
            'modelo' => $modeloFinal,
            'id_model' => $modeloExacto['id_model']
        ]);

        twiml_message(
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
        twiml_message("Ocurrió un problema consultando el catálogo de modelos. Probá nuevamente en unos instantes.");
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

        set_user_state($from, [
            'step' => 'modelo_sugerido',
            'marca' => $marca,
            'id_marca' => $idMarca,
            'modelo_input' => $modeloIngresado,
            'modelo_opciones' => $opcionesEstado
        ]);

        twiml_message(
            "No encontré ese modelo exacto para {$marca}.\n\n"
            . "Quizás quisiste decir:\n"
            . implode("\n", $opcionesTexto)
            . "\n\nRespondé con el número o con el nombre correcto."
        );
    }

    twiml_message(
        "No encontré ese modelo para {$marca}.\n\n"
        . "Probá escribiendo nuevamente el nombre del modelo."
    );
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

        set_user_state($from, [
            'step' => 'anio',
            'marca' => $marca,
            'id_marca' => $idMarca,
            'modelo' => $modeloFinal,
            'id_model' => $idModel
        ]);

        twiml_message(
            "Excelente 👍\n\n"
            . "Marca: {$marca}\n"
            . "Modelo: {$modeloFinal}\n\n"
            . "Ahora escribime el AÑO del vehículo. Ejemplo: 2021"
        );
    }

    foreach ($opciones as $op) {
        if (wa_normalizar_texto((string)$op['nombre']) === $respuestaNorm) {
            $modeloFinal = (string)$op['nombre'];
            $idModel = (int)$op['id_model'];

            set_user_state($from, [
                'step' => 'anio',
                'marca' => $marca,
                'id_marca' => $idMarca,
                'modelo' => $modeloFinal,
                'id_model' => $idModel
            ]);

            twiml_message(
                "Excelente 👍\n\n"
                . "Marca: {$marca}\n"
                . "Modelo: {$modeloFinal}\n\n"
                . "Ahora escribime el AÑO del vehículo. Ejemplo: 2021"
            );
        }
    }

    $opcionesTexto = [];
    foreach ($opciones as $nro => $op) {
        $opcionesTexto[] = $nro . '. ' . $op['nombre'];
    }

    twiml_message(
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
        twiml_message("El año no parece válido. Escribime un año de 4 dígitos. Ejemplo: 2021");
    }

    set_user_state($from, [
        'step' => 'km',
        'marca' => $marca,
        'id_marca' => $userState['id_marca'] ?? null,
        'modelo' => $modelo,
        'id_model' => $userState['id_model'] ?? null,
        'id_version' => $userState['id_version'] ?? null,
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

// =========================
// PASO: KM
// =========================
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
        'id_marca' => $userState['id_marca'] ?? null,
        'modelo' => $modelo,
        'id_model' => $userState['id_model'] ?? null,
        'id_version' => $userState['id_version'] ?? null,
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
        twiml_message("No pude leer la versión. Escribime la VERSIÓN del vehículo.");
    }

    if ($idMarca <= 0 || $idModel <= 0) {
        set_user_state($from, [
            'step' => 'ficha_oficial',
            'marca' => $marca,
            'id_marca' => $userState['id_marca'] ?? null,
            'modelo' => $modelo,
            'id_model' => $userState['id_model'] ?? null,
            'id_version' => $userState['id_version'] ?? null,
            'anio' => $anio,
            'km' => $km,
            'version' => $versionIngresada
        ]);

        twiml_message(
            "Perfecto 👍\n\n"
            . "Versión: {$versionIngresada}\n\n"
            . "¿Posee ficha oficial?\n"
            . "Respondé: SI o NO"
        );
    }

    try {
        $versionExacta = wa_buscar_version_exacta($idMarca, $idModel, $versionIngresada);
    } catch (Throwable $e) {
        wa_log('VERSION_DB_EXCEPTION', ['error' => $e->getMessage(), 'id_marca' => $idMarca, 'id_model' => $idModel]);

        set_user_state($from, [
            'step' => 'ficha_oficial',
            'marca' => $marca,
            'id_marca' => $userState['id_marca'] ?? null,
            'modelo' => $modelo,
            'id_model' => $userState['id_model'] ?? null,
            'id_version' => $userState['id_version'] ?? null,
            'anio' => $anio,
            'km' => $km,
            'version' => $versionIngresada
        ]);

        twiml_message(
            "Perfecto 👍\n\n"
            . "Versión: {$versionIngresada}\n\n"
            . "¿Posee ficha oficial?\n"
            . "Respondé: SI o NO"
        );
    }

    if ($versionExacta !== null) {
        $versionFinal = $versionExacta['nombre'];

        set_user_state($from, [
            'step' => 'ficha_oficial',
            'marca' => $marca,
            'id_marca' => $userState['id_marca'] ?? null,
            'modelo' => $modelo,
            'id_model' => $userState['id_model'] ?? null,
            'id_version' => $versionExacta['id_version'],
            'anio' => $anio,
            'km' => $km,
            'version' => $versionFinal
        ]);

        twiml_message(
            "Perfecto 👍\n\n"
            . "Versión: {$versionFinal}\n\n"
            . "¿Posee ficha oficial?\n"
            . "Respondé: SI o NO"
        );
    }

    try {
        $sugerencias = wa_buscar_versiones_similares($idMarca, $idModel, $versionIngresada, 5);
    } catch (Throwable $e) {
        wa_log('VERSION_SUGERENCIAS_DB_EXCEPTION', ['error' => $e->getMessage(), 'id_marca' => $idMarca, 'id_model' => $idModel]);

        set_user_state($from, [
            'step' => 'ficha_oficial',
            'marca' => $marca,
            'id_marca' => $userState['id_marca'] ?? null,
            'modelo' => $modelo,
            'id_model' => $userState['id_model'] ?? null,
            'id_version' => $userState['id_version'] ?? null,
            'anio' => $anio,
            'km' => $km,
            'version' => $versionIngresada
        ]);

        twiml_message(
            "Perfecto 👍\n\n"
            . "Versión: {$versionIngresada}\n\n"
            . "¿Posee ficha oficial?\n"
            . "Respondé: SI o NO"
        );
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

        set_user_state($from, [
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
        ]);

        twiml_message(
            "No encontré esa versión exacta.\n\n"
            . "Quizás quisiste decir:\n"
            . implode("\n", $opcionesTexto)
            . "\n\nRespondé con el número o con el nombre correcto."
            . "\nSi preferís continuar con la versión que escribiste, respondé SEGUIR."
        );
    }

    set_user_state($from, [
        'step' => 'ficha_oficial',
        'marca' => $marca,
        'id_marca' => $userState['id_marca'] ?? null,
        'modelo' => $modelo,
        'id_model' => $userState['id_model'] ?? null,
        'id_version' => $userState['id_version'] ?? null,
        'anio' => $anio,
        'km' => $km,
        'version' => $versionIngresada
    ]);

    twiml_message(
        "Perfecto 👍\n\n"
        . "Versión: {$versionIngresada}\n\n"
        . "¿Posee ficha oficial?\n"
        . "Respondé: SI o NO"
    );
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
    $versionInput = trim((string)($userState['version_input'] ?? ''));
    $opciones = $userState['version_opciones'] ?? [];

    if (in_array($respuestaNorm, ['seguir', 'continuar', 'omitir'], true)) {
        set_user_state($from, [
            'step' => 'ficha_oficial',
            'marca' => $marca,
            'id_marca' => $userState['id_marca'] ?? null,
            'modelo' => $modelo,
            'id_model' => $userState['id_model'] ?? null,
            'id_version' => $userState['id_version'] ?? null,
            'anio' => $anio,
            'km' => $km,
            'version' => $versionInput
        ]);

        twiml_message(
            "Perfecto 👍\n\n"
            . "Versión: {$versionInput}\n\n"
            . "¿Posee ficha oficial?\n"
            . "Respondé: SI o NO"
        );
    }

    if (isset($opciones[$respuesta])) {
        $versionFinal = $opciones[$respuesta]['nombre'];
        $idVersion = (int)$opciones[$respuesta]['id_version'];

        set_user_state($from, [
            'step' => 'ficha_oficial',
            'marca' => $marca,
            'id_marca' => $userState['id_marca'] ?? null,
            'modelo' => $modelo,
            'id_model' => $userState['id_model'] ?? null,
            'id_version' => $idVersion,
            'anio' => $anio,
            'km' => $km,
            'version' => $versionFinal
        ]);

        twiml_message(
            "Perfecto 👍\n\n"
            . "Versión: {$versionFinal}\n\n"
            . "¿Posee ficha oficial?\n"
            . "Respondé: SI o NO"
        );
    }

    foreach ($opciones as $op) {
        if (wa_normalizar_texto((string)$op['nombre']) === $respuestaNorm) {
            $versionFinal = (string)$op['nombre'];
            $idVersion = (int)$op['id_version'];

            set_user_state($from, [
                'step' => 'ficha_oficial',
                'marca' => $marca,
                'id_marca' => $userState['id_marca'] ?? null,
                'modelo' => $modelo,
                'id_model' => $userState['id_model'] ?? null,
                'id_version' => $idVersion,
                'anio' => $anio,
                'km' => $km,
                'version' => $versionFinal
            ]);

            twiml_message(
                "Perfecto 👍\n\n"
                . "Versión: {$versionFinal}\n\n"
                . "¿Posee ficha oficial?\n"
                . "Respondé: SI o NO"
            );
        }
    }

    $opcionesTexto = [];
    foreach ($opciones as $nro => $op) {
        $opcionesTexto[] = $nro . '. ' . $op['nombre'];
    }

    twiml_message(
        "No entendí la opción elegida.\n\n"
        . implode("\n", $opcionesTexto)
        . "\n\nRespondé con el número o con el nombre correcto."
        . "\nSi preferís continuar con la versión que escribiste, respondé SEGUIR."
    );
}

// =========================
// PASO: FICHA OFICIAL
// =========================
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
        'id_marca' => $userState['id_marca'] ?? null,
        'modelo' => $modelo,
        'id_model' => $userState['id_model'] ?? null,
        'id_version' => $userState['id_version'] ?? null,
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

// =========================
// PASO: TIPO VENTA
// =========================
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
        'id_marca' => $userState['id_marca'] ?? null,
        'modelo' => $modelo,
        'id_model' => $userState['id_model'] ?? null,
        'id_version' => $userState['id_version'] ?? null,
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

// =========================
// PASO: VALOR PRETENDIDO
// =========================
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
    ]);

    twiml_message(
        "Perfecto 👍\n\n"
        . "Valor pretendido: USD {$valor}\n\n"
        . "Por último, escribime tu EMAIL."
    );
}

// =========================
// PASO: EMAIL
// =========================
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

    $idMarca = (int)($userState['id_marca'] ?? 0);
    $idModel = (int)($userState['id_model'] ?? 0);
    $idVersion = (int)($userState['id_version'] ?? 0);

    set_user_state($from, [
        'step' => 'completo',
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
        'email' => $email
    ]);

    wa_log('FLOW_COMPLETED', [
        'from' => $from,
        'data' => get_user_state($from)
    ]);

    if ($idMarca <= 0 || $idModel <= 0) {
        twiml_message(
            "⚠️ No se pudo generar la cotización porque faltan IDs internos.\n\n" .
            "id_marca: " . ($idMarca > 0 ? $idMarca : 'NULL') . "\n" .
            "id_model: " . ($idModel > 0 ? $idModel : 'NULL') . "\n\n" .
            "Escribí COTIZAR para volver a intentarlo."
        );
    }

    // IMPORTANTE:
    // CotizacionService espera:
    // - brand en la URL => id_marca
    // - modelo en el body => id_model (numérico)
    $brandUrl = $marca; // 👈 CLAVE: el endpoint espera nombre, no ID

    $apiPayload = [
        'id_marca' => $idMarca,
        'id_model' => $idModel,
        'id_modelo' => $idModel,
        'id_version' => $idVersion > 0 ? $idVersion : null,

        // CLAVE: el servicio lee $dataIn['modelo']
        'modelo' => $idModel,

        // textos auxiliares
        'marca' => $marca,
        'modelo_nombre' => $modelo,
        'version' => $version,

        // datos de cotización
        'anio' => $anio,
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

    wa_log('PAYLOAD_FINAL_COTIZADOR', [
        'brand_url' => $brandUrl,
        'payload' => $apiPayload
    ]);

    $apiResult = cotizar_api($brandUrl, $apiPayload);

    wa_log('API_RESULT_RESUMEN', [
        'id_cotizacion' => $apiResult['id_cotizacion'] ?? $apiResult['cotizacion'] ?? $apiResult['cotizacion_id'] ?? null,
        'mensaje' => $apiResult['mensaje'] ?? $apiResult['msg'] ?? null,
        'resultado' => $apiResult['resultado'] ?? $apiResult['valores'] ?? null
    ]);

    set_user_state($from, [
        'step' => 'resultado_enviado',
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