<?php

require_once("../config/config.inc.php");
require_once("../includes/database.php");
require_once("../includes/funciones.php");

$marca = intval($_POST['marca']);
$anio = intval($_POST['anio']);
$familia = intval($_POST['familia']);

$modelos = get_modelos('AB', $marca, $anio, $familia);

$response = '';

if (count($modelos) > 0) {

    $response .= '<option value="0">Modelos</option>';
    for ($i = 0; $i < count($modelos); $i++) {
        $modelo = $modelos[$i];
        $response .= '<option value="' . $modelo->codigo . '">' . $modelo->nombre . '</option>';
    }
}


echo $response;
