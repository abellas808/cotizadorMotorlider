<?php

// Mostrar todos los errores
error_reporting(E_ALL);

// Habilitar la visualización de errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);


include('config.php');

$access_token = motorlider_ml_token();

$connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$connection->set_charset('utf8');
$query = "SELECT * FROM act_marcas";
$all_marcas = $connection->query($query);

LogImportModelos("-- INICIO IMPORTADOR MODELOS: ".date("Y-m-d H:i:s"));

if($all_marcas->num_rows > 0) {

    LogImportModelos("TOTAL DE MARCAS ".$all_marcas->num_rows);
    LogImportModelos("TENGO MARCAS, BORRO LOS MODELOS");

    //Se fija sino esta vacia la tabla modelo
    $checkdata = "SELECT * FROM act_modelo";
    $result = $connection->query($checkdata);

    if($result->num_rows > 0){
        LogImportModelos("TRUNCATE TABLE");
        $cleartable = "TRUNCATE TABLE act_modelo";
        $connection->query($cleartable);
    }

    $connection->close();
    
    $datafull = $all_marcas->fetch_all(MYSQLI_ASSOC);
    
	foreach($datafull as $key => $marca) {
        $urlmodelo = "https://api.mercadolibre.com/sites/MLU/search?category=MLU1744&brand=".$marca['id_marca']."";
        
        LogImportModelos(" --------------------------------------------------");
        LogImportModelos("COMIENZO INSERT MARCA: ".$marca['nombre']);

        //Guarda total de modelos y marcas
        $modelos = Array();
        
        $curl = curl_init($urlmodelo);
        curl_setopt($curl, CURLOPT_URL, $urlmodelo);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			"Authorization: Bearer $access_token"));


        
        
        $resp = curl_exec($curl);       
        curl_close($curl);
        $data = json_decode($resp, true);
                
        //Inico de busqueda
        $busqueda = $data['available_filters'];
        $indice = 0;
        $encontrado = false;
        
        while($indice < count($busqueda) && !$encontrado){
            if (in_array("MODEL", $busqueda[$indice])){                
                $encontrado = true;
            } else {
                $indice++;
            }
        }

        //Si tiene resultados la marca
        if($indice > 0 ){            
            if(isset($data['available_filters'][$indice]["values"])){
                $url_modelo = $data['available_filters'][$indice]["values"];
                if(count($url_modelo) < 0 ){
                    $index = 1;
                } else {
                    $index = count($url_modelo);
                }
                LogImportModelos("CANT TOTAL A INSERTAR: ".$index);                
                //recorrida y control de marcas                
                $modelos = ctrlModelQuantity($url_modelo,$index,$modelos,$marca,$access_token);          
                DBinsert($marca,$modelos);
            }
        }        
        LogImportModelos("FIN INSERT MARCA: ".$marca['nombre']);
        LogImportModelos(" --------------------------------------------------");
    }
} else {
    LogImportMarcas("NO BORRO NADA, NO HAY MARCAS EN BD");
}

LogImportModelos("-- FIN IMPORTADOR MODELOS: ".date("Y-m-d H:i:s"));

function ctrlModelQuantity($url_modelo,$index,$modelos,$marca,$access_token) {


    for ($inicio = 0; $inicio < $index; $inicio++) {                    
        $serie_name = $url_modelo[$inicio]["name"]; //Toma el modelo
        $serie_id = $url_modelo[$inicio]["id"]; //Toma el id modelo
        
        if(getModelData($marca['id_marca'],$serie_id,$access_token) > 0){                
            $modelos[$inicio]["id"] = $serie_id;
            $modelos[$inicio]["name"] = $serie_name;
        }    
    }       

    //Elimina los modelos duplicados
    $modelos = array_map("unserialize", array_unique(array_map("serialize", $modelos)));

    return $modelos;
}

function getModelData($marca,$serie_id,$access_token){    
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mercadolibre.com/sites/MLU/search?category=MLU1744&brand=$marca&model=$serie_id");
        
    ini_set('max_execution_time', 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Authorization: Bearer $access_token"));
    $res = json_decode(curl_exec($ch));
    curl_close($ch);   
    $restcont = $res->paging->total;        

    return $restcont;
}

function DBinsert($marca,$modelos){
    
    $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $connection->set_charset('utf8');
        
    foreach ($modelos as $value) {
        //SQL INSERT            
        $query = "REPLACE INTO act_modelo (id,id_marca,id_model,nombre) VALUES (0,".$marca["id"].",".$value["id"].",'".$value["name"]."')";
        $res = $connection->query($query);
        if(!$res) {                            
            LogImportModelos("Error Modelo: ".$value['name']);
        } else {
            LogImportModelos("Ingresado Modelo: ".$value['name']);                
        }
    }

    $connection->close();
}

function LogImportModelos($new_data) {
    $new_data = date("Ymd G:i:s") . "  >>  " . $new_data;
    $my_file  = dirname(__FILE__) . '/logs/' . 'LogImportModelos_'.date("Y-m-d").'.log';
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