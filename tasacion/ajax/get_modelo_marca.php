<?php

date_default_timezone_set('America/Montevideo');

include('./../../config.php');

$marca = intval($_POST['marca']);

$query_modelos = "SELECT * FROM `act_modelo` WHERE id_marca = $marca";
$connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$connection->set_charset('utf8');
$all_modelos = $connection->query($query_modelos);
$modelos = $all_modelos->fetch_all(MYSQLI_ASSOC);

$response = '';

if (count($modelos) > 0) {

    $response .= '<option value="0">Modelos</option>';
    foreach($modelos as $key => $modelo) {
        $response .= '<option value="' . $modelo['id'] . '">' . $modelo['nombre'] . '</option>';
    }
}


echo $response;
