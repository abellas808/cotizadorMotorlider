<?php
header('Content-Type: application/json; charset=utf-8');

$q = urlencode($_GET['q'] ?? 'Audi A1');
$url = "https://api.mercadolibre.com/sites/MLU/search?category=MLU1744&q={$q}&limit=5";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  "Accept: application/json",
  "User-Agent: MotorliderCotizador/1.0"
]);

$raw  = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

file_put_contents(__DIR__ . "/../logs/ml_search.log",
  date('c') . " HTTP=$http URL=$url ERR=$err RAW=" . substr((string)$raw, 0, 500) . PHP_EOL,
  FILE_APPEND
);

echo json_encode([
  "http" => $http,
  "url"  => $url,
  "err"  => $err,
  "raw"  => json_decode($raw, true) ?? $raw
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
