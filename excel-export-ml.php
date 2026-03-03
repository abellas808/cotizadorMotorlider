<!DOCTYPE html>
<?php
include('./config.php');
ob_start();

$connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$connection->set_charset('utf8');

$query = "SELECT * FROM act_marcas";
$all_marcas = $connection->query($query);

//Guarda total de modelos y marcas
$modelos = Array();

if($all_marcas->num_rows > 0) {

    $data = $all_marcas->fetch_all(MYSQLI_ASSOC);

	foreach($data as $key => $marca) {
        $urlmodelo = "https://api.mercadolibre.com/sites/MLU/search?category=MLU1744&brand=".$marca['id_marca']."";

        //Guarda total de modelos y marcas
        $modelos = Array();

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
        $totalserach = json_decode($resp, true);
                
        //Table header
        ?>
        <table border="1">
            <thead>
                <tr>                
                    <th>Modelo</th>
                    <th>Titulo</th>
                    <th>Año</th>
                    <th>Km</th>
                    <th>Precio</th>
                    <th>Info. Vendedor</th>
                    <th>Link</th>
                </tr>
            </thead>
        <?php
        
        $busqueda = $totalserach["results"];
        $indice = getPrice($busqueda);
        $indice_anio = getAnio($busqueda);
        die;
        
        
        //titulo
        $title = $totalserach["results"][0]["title"];
        //precio
        $price = $totalserach["results"][$indice]["prices"]["prices"][0]["amount"];
        //modelo
        $model = getModel($totalserach);
        //año
        // $anio = $totalserach["results"][$indice]["prices"]["prices"][0]["amount"];
        

        die(var_dump($title,$price,$model));
        
        
    }
}
ob_end_flush();

function getAnio($busqueda){
    $indice = 0;
    $encontrado = false;
    
    while($indice < count($busqueda) && !$encontrado){
        if (in_array("id", $busqueda[$indice])){
            echo "entra, posicion: ",$indice;
            $encontrado = true;
        }else{
            $indice++;
        }
    }
    $results = $busqueda[$indice]["prices"]["prices"][0]["amount"];

    die(var_dump($results));
    return $indice;
}

function getModel($data){
    
    $busqueda = $data['available_filters'];
    $indice = 0;
    $encontrado = false;
    
    while($indice < count($busqueda) && !$encontrado){
        if (in_array("MODEL", $busqueda[$indice])){
            // echo "entra, posicion: ",$indice;
            $encontrado = true;
        }else{
            $indice++;
        }
    }

    // for ($inicio = 0; $inicio < count(($data['available_filters'][$indice]["values"])); $inicio++) {             
        $serie_name = $data['available_filters'][$indice]["values"][0]["name"]; //Toma el modelo
        // $modelos[$inicio]=$serie_name;
    // }

    return $serie_name;
}

function getPrice($busqueda){
    $indice = 0;
    $encontrado = false;
    while($indice < count($busqueda) && !$encontrado){
        if (in_array("prices", $busqueda[$indice])){
            // echo "entra, posicion: ",$indice;
            $encontrado = true;
        }else{
            $indice++;
        }
    }
    return $indice;
}

?>

