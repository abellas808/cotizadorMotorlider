<?php

ini_set('memory_limit', '1024M');
set_time_limit(0);

date_default_timezone_set('America/Montevideo');

$fecha = date('Y-m-d_H-i-s');

/**
 * Este archivo debería estar en:
 * /public_html/cron/backup_public_html.php
 *
 * Por eso:
 * __DIR__ = /homeX/marcos2022/public_html/cron
 * dirname(__DIR__) = /homeX/marcos2022/public_html
 */
$origen = dirname(__DIR__);
$origenReal = realpath($origen);

$destino = dirname(__DIR__, 2) . '/backups';

$lockFile = $destino . '/backup_public_html.lock';
$logFile  = $destino . '/backup_public_html.log';

function backup_log($msg) {
    global $logFile;

    file_put_contents(
        $logFile,
        '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL,
        FILE_APPEND
    );
}

if ($origenReal === false || !is_dir($origenReal)) {
    exit("ERROR: No se encontró carpeta origen public_html.\n");
}

if (!is_dir($destino)) {
    mkdir($destino, 0777, true);
}

if (file_exists($lockFile)) {
    $lockTime = filemtime($lockFile);
    $minutos = round((time() - $lockTime) / 60);

    if ($minutos < 180) {
        backup_log("Proceso cancelado: ya hay un backup ejecutándose. Lock activo hace {$minutos} minutos.");
        exit("Ya hay un backup ejecutándose.\n");
    }

    backup_log("Lock viejo detectado. Se elimina lock de {$minutos} minutos.");
    unlink($lockFile);
}

file_put_contents($lockFile, date('Y-m-d H:i:s'));

backup_log("Inicio backup public_html");
backup_log("Origen: {$origenReal}");
backup_log("Destino: {$destino}");

$archivoZip = $destino . "/public_html_$fecha.zip";

$zip = new ZipArchive();

if ($zip->open($archivoZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    backup_log("ERROR: No se pudo crear ZIP: {$archivoZip}");

    if (file_exists($lockFile)) {
        unlink($lockFile);
    }

    exit("No se pudo crear ZIP.\n");
}

$totalArchivos = 0;

try {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($origenReal, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $file) {
        if ($file->isDir()) {
            continue;
        }

        $filePath = $file->getRealPath();

        if ($filePath === false) {
            continue;
        }

        /**
         * Exclusiones
         */
        if (
            strpos($filePath, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR) !== false ||
            strpos($filePath, DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR) !== false ||
            strpos($filePath, DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR) !== false ||
            strpos($filePath, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR) !== false
        ) {
            continue;
        }

        /**
         * Ruta relativa correcta dentro del ZIP.
         * Esto evita que se corten mal los nombres de carpetas.
         */
        $relativePath = ltrim(
            str_replace($origenReal, '', $filePath),
            DIRECTORY_SEPARATOR
        );

        /**
         * Guardamos todo dentro de una carpeta raíz public_html/
         * para que al descomprimir quede igual a cPanel.
         */
        $zipPath = 'public_html/' . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

        if ($zip->addFile($filePath, $zipPath)) {
            $totalArchivos++;
        } else {
            backup_log("No se pudo agregar archivo: {$filePath}");
        }
    }

    $zip->close();

    $pesoMb = file_exists($archivoZip)
        ? round(filesize($archivoZip) / 1024 / 1024, 2)
        : 0;

    backup_log("Backup generado OK: {$archivoZip} | Archivos: {$totalArchivos} | Peso: {$pesoMb} MB");

    /**
     * Mantener solo los últimos 5 backups de public_html.
     */
    $backups = glob($destino . '/public_html_*.zip');

    if ($backups && count($backups) > 5) {
        usort($backups, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });

        while (count($backups) > 5) {
            $viejo = array_shift($backups);

            if (is_file($viejo)) {
                unlink($viejo);
                backup_log("Backup viejo eliminado: {$viejo}");
            }
        }
    }

} catch (Throwable $e) {
    backup_log("ERROR: " . $e->getMessage());

    if ($zip instanceof ZipArchive) {
        $zip->close();
    }
}

if (file_exists($lockFile)) {
    unlink($lockFile);
}

backup_log("Fin backup public_html");

echo "Backup finalizado.\n";
echo $archivoZip . "\n";