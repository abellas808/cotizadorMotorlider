<?php

ini_set('memory_limit', '1024M');
set_time_limit(0);

$fecha = date('Y-m-d_H-i');

$origen = __DIR__ . '/../';
$destino = dirname(__DIR__) . '/backups';

if (!is_dir($destino)) {
    mkdir($destino, 0777, true);
}

$archivoZip = $destino . "/public_html_$fecha.zip";

$zip = new ZipArchive();

if ($zip->open($archivoZip, ZipArchive::CREATE) !== TRUE) {
    die("No se pudo crear ZIP");
}

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($origen),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $name => $file) {

    if (!$file->isDir()) {

        $filePath = $file->getRealPath();

        if (
            strpos($filePath, '/backups/') !== false ||
            strpos($filePath, '/node_modules/') !== false ||
            strpos($filePath, '/cache/') !== false ||
            strpos($filePath, '/tmp/') !== false
        ) {
            continue;
        }

        $relativePath = substr($filePath, strlen($origen));

        $zip->addFile($filePath, $relativePath);
    }
}

$zip->close();

echo "Backup generado: " . $archivoZip;