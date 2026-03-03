<?php

date_default_timezone_set('America/Montevideo');

include('./../../config.php');
include('./../../config/config.inc.php');

$marca = intval($_POST['marca']);
$modelo = intval($_POST['modelo']);
$anio = intval($_POST['anio']);

$url = $config->urlBase.'ws/versions';
$parametros = array("brand"=>$marca,"model"=>$modelo,"anio"=>$anio);
$versiones = json_decode(httpPost($url,$parametros));

$response = '';

if ($versiones->codigo === 200) {

    $response .= '<option value="0">Version</option>';
    $versionArrayObject = new ArrayObject($versiones->versiones);
    $versionArrayObject->asort();
    foreach($versionArrayObject as $key => $version) {
        $response .= '<option value="' . $key . '">' . strtoupper($version) . '</option>';
    }
    $response .= '<option value="otro">OTROS</option>';
} else {
    $response .= '<option value="0">Version</option>';
    $response .= '<option value="otro">OTROS</option>';
}

echo $response;