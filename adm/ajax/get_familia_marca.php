<?php

require_once("../config/config.inc.php");
require_once("../includes/database.php");
require_once("../includes/funciones.php");

$marca = intval($_POST['marca']);
$anio = intval($_POST['anio']);

$familias = get_familias('AB', $marca, $anio);

$response = '';

if (count($familias) > 0) {

    $response .= '<option value="0">Familia</option>';
    for ($i = 0; $i < count($familias); $i++) {
        $familia = $familias[$i];
        $response .= '<option value="' . $familia->codigo . '">' . $familia->nombre . '</option>';
    }
}


echo $response;
