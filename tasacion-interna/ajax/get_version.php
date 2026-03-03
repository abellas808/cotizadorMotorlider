<?php

date_default_timezone_set('America/Montevideo');

include('./../../config.php');

$marca = intval($_POST['marca']);
$modelo = intval($_POST['modelo']);
$anio = intval($_POST['anio']);

$query = "SELECT brand.id_marca, model.id_model FROM act_marcas as brand, act_modelo as model WHERE brand.id = $marca AND model.id = $modelo";
$connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$connection->set_charset('utf8');
$all = $connection->query($query);
$brandmodel = $all->fetch_all(MYSQLI_ASSOC);

$versiones = apiAllData($brandmodel[0]['id_marca'], $brandmodel[0]['id_model'], $anio);

$response = '';

if (isset($versiones) && count($versiones) > 0) {

    $response .= '<option value="0">Version</option>';
    foreach($versiones as $key => $version) {
        $response .= '<option value="' . $version->id . '">' . strtoupper($version->name) . '</option>';
    }
}


echo $response;

function apiAllData($brand, $model, $anio){
    $search = 'category=MLU1744&BRAND='.$brand.'&MODEL='.$model.'&VEHICLE_YEAR='.$anio.'-'.$anio;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mercadolibre.com/sites/MLU/search?");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $search);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //PARA QUE ANDE LOCAL
    $products_ml = json_decode(curl_exec($ch));
    curl_close($ch);
    foreach($products_ml->available_filters as $key => $filters){
        if($filters->id === 'SHORT_VERSION'){
            return $filters->values;
        }
    }
}
