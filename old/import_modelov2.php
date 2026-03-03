<?php

include('./config.php');

$connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$connection->set_charset('utf8');

$query = "SELECT * FROM act_marcas";
$all_marcas = $connection->query($query);

// //Guarda total de modelos y marcas
// $modelos = Array();

if($all_marcas->num_rows > 0) {

    $data = $all_marcas->fetch_all(MYSQLI_ASSOC);

	foreach($data as $key => $marca) {
        $urlmodelo = "https://api.mercadolibre.com/sites/MLU/search?category=MLU1744&brand=".$marca['id_marca']."";
        // $urlmodelo = "https://api.mercadolibre.com/sites/MLU/search?category=MLU1744&brand=60249";
        // 60249
        
        //Guarda total de modelos y marcas
        $modelos = Array();
        echo "<br>";
        echo $marca["id"].$marca["id_marca"];
        echo "<br>";
        
        $curl = curl_init($urlmodelo);
        curl_setopt($curl, CURLOPT_URL, $urlmodelo);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $headers = array(
        "Accept: application/json",
        "Content-Type: application/json",
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $resp = curl_exec($curl);
        curl_close($curl);
        $data = json_decode($resp, true);        
        $totalserach = count($data['results']);

        echo "Total de resultados: ".$totalserach."";
        echo "<br>";
                
        //Inico de busqueda
        $busqueda = $data['available_filters'];
        $indice = 0;
        $encontrado = false;
        
        while($indice < count($busqueda) && !$encontrado){
            if (in_array("MODEL", $busqueda[$indice])){
                // echo "entra, posicion: ",$indice;
                // echo "<br>";
                $encontrado = true;
            }else{
                $indice++;
            }
        }
        // Total de modelos por marca: count($data['available_filters'][$indice]["values"]));        

        for ($inicio = 0; $inicio < count(($data['available_filters'][$indice]["values"])); $inicio++) {        
            // echo $inicio."<br>";
            $serie_name = $data['available_filters'][$indice]["values"][$inicio]["name"]; //Toma el modelo
            $serie_id = $data['available_filters'][$indice]["values"][$inicio]["id"]; //Toma el id modelo
            // echo $serie_name,$serie_id;
            // echo "<br>";
            if(getModelData($marca['id_marca'],$serie_id) > 0){
            // if(getModelData(60249,$serie_id) > 0){
                echo "<br>";
                echo "<br>";
                echo "cantidad obtenida:".getModelData($marca['id_marca'],$serie_id);
                echo "entra".$serie_name.$serie_id;
                echo "<br>";
                // die;
                $modelos[$inicio]["id"] = $serie_id;
                $modelos[$inicio]["name"] = $serie_name;
            }else{
                echo "No entra".$serie_name.$serie_id;
                echo "<br>";
            }
            
        }        
        
        //Elimina los modelos duplicados
        $modelos = array_map("unserialize", array_unique(array_map("serialize", $modelos)));  
        DBinsert($marca,$modelos);
        
        echo "<br>";
        echo "Marca: ".$marca['nombre']."";
        echo "<br>";
        echo "Total de modelos: ".count($modelos)."";
        echo "<br>";

        // die(var_dump($modelos));
        
        
        // die;
    }
}

function getModelData($marca,$serie_id){    
    // $marca = 60249;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mercadolibre.com/sites/MLU/search?category=MLU1744&brand=$marca&model=$serie_id");
    // curl_setopt($ch, CURLOPT_URL, "https://api.mercadolibre.com/sites/MLU/search?category=MLU1744&brand=60249&model=$serie_id");
    
    ini_set('max_execution_time', 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = json_decode(curl_exec($ch));
    curl_close($ch);   

    $restcont = $res->paging->total;
    // echo $restcont;

    return $restcont;
}

function DBinsert($marca,$modelos){
    
    $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $connection->set_charset('utf8');

    foreach ($modelos as $value) {
            //SQL INSERT
            $query = "INSERT INTO act_modelo (id,id_marca,id_model,nombre) VALUES (0,".$marca["id"].",".$value["id"].",'".$value["name"]."')";
            $res = $connection->query($query);
            if(!$res) {
                var_dump("Error",$value["id"],$value["name"]);
            }else{
                var_dump("Ingresado",$value["id"],$value["name"]);
                echo "<br>";
            }
        }

        $connection->close();
}

?>