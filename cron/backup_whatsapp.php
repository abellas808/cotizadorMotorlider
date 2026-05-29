<?php

ini_set('memory_limit', '512M');
set_time_limit(0);

echo "Iniciando backup...\n";

$fecha = date('Y-m-d_H-i');

$origen = dirname(__DIR__) . '/whatsapp';
$destino = dirname(dirname(__DIR__)) . '/backups';

if (!is_dir($destino)) {
    mkdir($destino, 0777, true);
}

$archivoZip = $destino . "/whatsapp_$fecha.zip";

$zip = new ZipArchive();

if ($zip->open($archivoZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("No se pudo crear ZIP");
}

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($origen, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $file) {

    if (!$file->isDir()) {

        $filePath = $file->getRealPath();

        if (
            strpos($filePath, '/node_modules/') !== false ||
            strpos($filePath, '/cache/') !== false ||
            strpos($filePath, '/tmp/') !== false
        ) {
            continue;
        }

        $relativePath = substr($filePath, strlen($origen) + 1);

        $zip->addFile($filePath, $relativePath);
    }
}

$zip->close();

echo "Backup generado correctamente:\n";
echo $archivoZip . "\n";