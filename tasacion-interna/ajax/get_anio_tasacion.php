<?php

date_default_timezone_set('America/Montevideo');

include('./../../config.php');
include('./../../config/config.inc.php');

$marca = intval($_POST['marca']);
$modelo = intval($_POST['modelo']);

$url = $config->urlBase.'ws/years';
$parametros = array("brand"=>$marca,"model"=>$modelo);
$anios = json_decode(httpPost($url,$parametros));

$response = '';

if ($anios->codigo === 200) {

    $response .= '<option value="0">Año</option>';
    foreach($anios->anios as $key => $anio) {
        $response .= '<option value="' . $key . '">' . $anio . '</option>';
    }
}

echo $response;