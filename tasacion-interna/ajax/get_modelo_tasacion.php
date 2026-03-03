<?php

date_default_timezone_set('America/Montevideo');

include('./../../config.php');
include('./../../config/config.inc.php');

$marca = intval($_POST['marca']);

$url = $config->urlBase.'ws/models';
$parametros = array("brand"=>$marca);
$modelos = json_decode(httpPost($url,$parametros));

$response = '';

if ($modelos->codigo === 200) {

    $response .= '<option value="0">Modelos</option>';
    foreach($modelos->models as $key => $modelo) {
        $response .= '<option value="' . $key . '">' . $modelo . '</option>';
    }
}

echo $response;