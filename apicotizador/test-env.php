<?php
require __DIR__ . '/src/classes.php'; // o donde tengas loadEnv()
loadEnv(); // o como sea
header('Content-Type: application/json');
echo json_encode([
  "getenv" => getenv('APIFY_TOKEN') ? "OK" : "VACIO",
  "_ENV" => isset($_ENV['APIFY_TOKEN']) ? "OK" : "VACIO",
  "_SERVER" => isset($_SERVER['APIFY_TOKEN']) ? "OK" : "VACIO",
]);
