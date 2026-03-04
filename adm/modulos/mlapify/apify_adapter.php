<?php
// services/apify_adapter.php

// ✅ .env está en /public_html/apicotizador/.env
$envPath = dirname(__DIR__) . '/.env';

if (is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // comentarios / líneas vacías
        if ($line === '' || str_starts_with($line, '#')) continue;

        // debe tener "="
        if (strpos($line, '=') === false) continue;

        [$name, $value] = explode('=', $line, 2);
        $name  = trim($name);
        $value = trim($value);

        // sacar comillas si vienen "TOKEN=xxxxx"
        if ($value !== '' && (
            ($value[0] === '"' && substr($value, -1) === '"') ||
            ($value[0] === "'" && substr($value, -1) === "'")
        )) {
            $value = substr($value, 1, -1);
        }

        // no pisar si ya existe
        if (getenv($name) === false || getenv($name) === '') {
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

function apify_buscar_publicaciones(array $params): array
{
    // IMPORTANTE: Asegurate de tener el include/require de ApiCotizadorApify donde corresponda
    $api = new ApiCotizadorApify();

    $marca  = trim((string)($params['marca'] ?? ''));
    $modelo = trim((string)($params['modelo'] ?? ''));

    if ($marca === '' || $modelo === '') {
        throw new Exception('Falta marca o modelo');
    }

    $res = $api->testRun($marca, $modelo);

    if (!is_array($res)) {
        throw new Exception('Respuesta inválida desde ApiCotizadorApify');
    }

    if (!($res['ok'] ?? false)) {
        $err = $res['error'] ?? 'Error desconocido';
        throw new Exception('Apify: ' . $err);
    }

    $items = $res['items'] ?? [];
    return is_array($items) ? $items : [];
}