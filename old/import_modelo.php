<?php

include('./config.php');

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
        
        echo "<br>";
        var_dump($marca["id"],$marca["id_marca"]);
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

        $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $connection->set_charset('utf8');

        for ($inicio = 0; $inicio < $totalserach; $inicio++) {  
            $busqueda = $data['results'][$inicio]["attributes"];

            for ($i = 0; $i < count($busqueda); $i++) {

                if (in_array("MODEL", $busqueda[$i])) {
                    // echo "MODEL posicion: ".$i."";
                    // echo "<br>";
                    $serie_name = $data['results'][$inicio]["attributes"][$i]["value_name"]; //Toma el modelo
                    $modelos[$inicio]["name"] = $serie_name;
                    // echo $serie_name;
                    // echo "<br>";
                }

                if (in_array("Modelo", $busqueda[$i])) {
                    // echo "Modelo posicion: ".$i."";
                    // echo "<br>";
                    $id = $data['results'][$inicio]["attributes"][$i]["value_id"]; //Toma el modelo
                    if($id == NULL){
                        $modelos[$inicio]["id"] = 0;
                    }else{
                        $modelos[$inicio]["id"] = $id;
                    }
                    // echo $id;
                    // echo "<br>";
                }
            }
        }

        //Elimina los modelos duplicados
        $modelos = array_map("unserialize", array_unique(array_map("serialize", $modelos)));
        
        echo "<br>";
        echo "Marca: ".$marca['nombre']."";
        echo "<br>";
        echo "Total de modelos: ".count($modelos)."";
        echo "<br>";

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
}

?>