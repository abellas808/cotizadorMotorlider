<?php

include('config.php');

$access_token = motorlider_ml_token();

$url = "https://api.mercadolibre.com/catalog_domains/MLU-CARS_AND_VANS/attributes/BRAND/top_values";

$curl = curl_init($url);
curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

$headers = array(
   "Accept: application/json",
   "Content-Type: application/json",
   "Authorization: Bearer" . $access_token
);

curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

$resp = curl_exec($curl);
curl_close($curl);
$data = json_decode($resp, true);

LogImportMarcas("-- INICIO IMPORTADOR MARCAS: ".date("Y-m-d H:i:s"));

if(is_array($data)){
    LogImportMarcas("ARRAY TRUE");
    LogImportMarcas("TOTAL " . count($data));

    LogImportMarcas("CREO ARRAY CON TODAS LAS MARCAS");
    $allmarcas = array();
    foreach ($data as $key => $value) {
        LogImportMarcas("MARCA " . $value['id'] . " - " . $value['name']);
        $allmarcas[] = array(
            "id_marca" => $value['id'],
            "nombre" => $value['name']
        );
    }

    if(count($allmarcas) > 0){
        LogImportMarcas("TENGO ARRAY DE MARCAS, BORRO TODO E INSERTO");
        $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $connection->set_charset('utf8');

        //Se fija sino esta vacia
        $checkdata = "SELECT * FROM act_marcas";
        $result = $connection->query($checkdata);

        if($result->num_rows > 0){
            LogImportMarcas("TRUNCATE TABLE");
            $cleartable = "TRUNCATE TABLE act_marcas";
            $connection->query($cleartable);
        }

        foreach ($allmarcas as $key => $value) {
            $query = "INSERT INTO act_marcas (id,id_marca,nombre,cotisa) VALUES (0,".$value['id_marca'].",'".$value['nombre']."',0)";
            $res = $connection->query($query);
            if(!$res) {
                LogImportMarcas("ERROR AL INSERTAR " . $value["id_marca"] . " - " . $value["nombre"]);
            } else {
                LogImportMarcas("INGRESADO " . $value["nombre"]);
            }
        }

        //Setea las marcas activas
        activeMarca();

        $connection->close();
    } else {
        LogImportMarcas("NO PUSO NINGUNA MARCA EN EL ARRAY");
        LogImportMarcas("NO BORRO NADA");
        LogImportMarcas("-- FIN IMPORTADOR MARCAS: ".date("Y-m-d H:i:s"));
    }
} else {
    LogImportMarcas("ARRAY FALSE");
    LogImportMarcas("NO BORRO NADA");
    LogImportMarcas("RESPONSE " . $data);
    LogImportMarcas("-- FIN IMPORTADOR MARCAS: ".date("Y-m-d H:i:s"));
}

function activeMarca(){
    LogImportMarcas("activeMarca");
    $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $connection->set_charset('utf8');
    //Array marcas sin espacios
    $sin_espacios = array();

    //Toma marcas las cuales cotizan en motorlider
    $cotisan = "SELECT nombre FROM marca_auto WHERE id_marca_auto in (SELECT cod_marca FROM filtros_cotizador WHERE anio_desde != 0 OR anio_hasta != 0) ORDER BY `marca_auto`.`nombre` ASC";
    $marca_cotisa = $connection->query($cotisan);
    
    foreach ($marca_cotisa as $value) {        
        $sin_espacios[] = explode(" ",$value["nombre"]);
    }

    for($i=0; $i < count($sin_espacios); $i++ ){
        $activa_marca = "UPDATE act_marcas SET cotisa = 1 WHERE nombre LIKE '%".$sin_espacios[$i][0]."%'";
        $res = $connection->query($activa_marca);
        if($res){
            LogImportMarcas("Se activo: " . $sin_espacios[$i][0]);
        }
    }
}

function LogImportMarcas($new_data) {
    $new_data = date("Ymd G:i:s") . "  >>  " . $new_data;
    $my_file  = dirname(__FILE__) . '/logs/' . 'LogImportMarcas_'.date("Y-m-d").'.log';
    $handle = fopen($my_file, 'a') or die('Cannot open file:  ' . $my_file);
    fwrite($handle, $new_data . "\n");
}

function motorlider_ml_token(){

    define('ML_APP_ID', '6722426555410846'); 
    define('ML_APP_SECRET', 'aVkGmvga3eaEwpYzxPH6ZTvKqxIgv3Rd'); 

    $access_token = "";
    $url = "https://api.mercadolibre.com/oauth/token?grant_type=client_credentials&client_id=" . ML_APP_ID . "&client_secret=" . ML_APP_SECRET . "";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    $token_info = json_decode($res);

    $access_token = $token_info->access_token;

    return $access_token;
}
?>