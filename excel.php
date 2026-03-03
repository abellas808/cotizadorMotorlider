<?php

/*
YA tenemos en BD una tabla marcas y otra modelos+marca
Recorrer tabla marcas y tomar ID
Tengo el primer ID hago una consulta sobre la tabla modelo y me devuelve todos los modelos de esa marca
Recorro tabla modelo para tomer el ID (foreach doble)
Con id_marca && id_modelo consulto la API
Junto todos los resultados y por cada resultado traigo los datos

------- UNA OPCION -------
Tendria un excel por cada marca-modelo osea
chevrolet_agile.excel
chevrolet_apache.excel
chevrolet_astra.excel
...

------- OTRA OPCION -------
Tendria un excel por marca
Chevrolet.excel
Volkswagen.excel
Fiat.excel
...

*/

date_default_timezone_set('America/Montevideo');

include('./config.php');

$query = "SELECT * FROM act_marcas";
$connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$connection->set_charset('utf8');
$all_marcas = $connection->query($query);

$query = "category=MLU1744";

if($all_marcas->num_rows > 0) {

    $marcas = $all_marcas->fetch_all(MYSQLI_ASSOC);

	foreach($marcas as $key => $marca) {

        $query .= '&BRAND='.$marca["id_marca"];

        $sql = "SELECT id_model,nombre FROM act_modelo WHERE id_marca = ".$marca['id']." ORDER BY nombre ASC";
        $all_modelos = $connection->query($sql);
        $modelos = $all_modelos->fetch_all(MYSQLI_ASSOC);

        foreach($modelos as $key => $modelo) {
            $new_query = $query . '&MODEL='.$modelo["id_model"];

            $all_results = [];
            $offset = 0;
            $total  = 0;
            //$offset_query = '&offset='.$offset;
            $offset_query = '&limit=3&offset='.$offset;

            var_dump($new_query);

            $products_ml = apiAllData($new_query, $offset_query);

            if(count($products_ml->results) > 0){
                //Tengo resultados
                $total = $products_ml->paging->total;
                $offset = $offset + 50;
                $all_results = $products_ml->results;

                //Reviso si hay paginado para volver a armar la consulta 
                /*while($total > $offset) {
                    $offset_query = '&offset='.$offset;
                    $products_ml = apiAllData($new_query, $offset_query);
                    $offset = $offset + 50;
                    $all_results = array_merge($all_results, $products_ml->results);
                }*/

                // var_dump(count($all_results));
                // var_dump("MARCA ".$marca['nombre']);
                // var_dump("MODEL ".$modelo['nombre']);
                // getAllData($all_results, $marca['nombre'], $modelo['nombre']);
                Excel($all_results, $marca['nombre'], $modelo['nombre']);                
            }
        }
        die;
    }
}

function apiAllData($query, $offset){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mercadolibre.com/sites/MLU/search?");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $query.''.$offset);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //PARA QUE ANDE LOCAL
    $products_ml = json_decode(curl_exec($ch));
    curl_close($ch);
    return $products_ml;
}

function getAllData($all_results, $marca, $modelo){

    $all_info = "";
    foreach($all_results as $info){
        LogCron('marca '.$marca, $marca);
        LogCron('modelo '.$modelo, $marca);
        LogCron('title '.$info->title, $marca);
        LogCron('price '.$info->price, $marca);
        LogCron('currency '.$info->currency_id, $marca);
        LogCron('permalink '.$info->permalink, $marca);
        LogCron('buying_mode '.$info->buying_mode, $marca);
        LogCron('type '.$info->listing_type_id, $marca);
        LogCron('condition '.$info->condition, $marca);
        $mercadopago = $info->accepts_mercadopago ? 'true' : 'false';
        LogCron('accepts_mercadopago '.$mercadopago, $marca);
        LogCron('address_name '.$info->address->state_name, $marca);
        LogCron('address_city '.$info->address->city_name, $marca);
        getSellerInfo($info->seller->id, $marca);
        LogCron('---', $marca);

        //https://api.mercadolibre.com/items/MLU602587413/description - descripcion
    }
    
    return $all_info;
}

function getSellerInfo($seller_id, $marca){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mercadolibre.com/users/$seller_id");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //PARA QUE ANDE LOCAL
    $seller_data = json_decode(curl_exec($ch));
    curl_close($ch);

    LogCron('seller_id '.$seller_data->id, $marca);
    LogCron('seller_nickname '.$seller_data->nickname, $marca);
    LogCron('seller_country '.$seller_data->country_id, $marca);
    LogCron('seller_city '.$seller_data->address->city, $marca);
    LogCron('seller_points '.$seller_data->points, $marca);
    LogCron('seller_permalink '.$seller_data->permalink, $marca);
    LogCron('seller_status '.$seller_data->status->site_status, $marca);
}

/*function getDescriptionInfo($seller_id){
    
}*/

// LogCron('marca '.$marca, $marca);
//         LogCron('modelo '.$modelo, $marca);
//         LogCron('title '.$info->title, $marca);
//         LogCron('price '.$info->price, $marca);
//         LogCron('currency '.$info->currency_id, $marca);
//         LogCron('permalink '.$info->permalink, $marca);
//         LogCron('buying_mode '.$info->buying_mode, $marca);
//         LogCron('type '.$info->listing_type_id, $marca);
//         LogCron('condition '.$info->condition, $marca);
//         $mercadopago = $info->accepts_mercadopago ? 'true' : 'false';
//         LogCron('accepts_mercadopago '.$mercadopago, $marca);
//         LogCron('address_name '.$info->address->state_name, $marca);
//         LogCron('address_city '.$info->address->city_name, $marca);
//         getSellerInfo($info->seller->id, $marca);
//         LogCron('---', $marca);

function Excel($all_results, $marca, $modelo){
    $filename="EmpData";
    //Table header
    ?>
    <table border="1">
        <thead>
            <tr>                
                <th>Modelo</th>
                <th>Marca</th>
                <th>Año</th>
                <th>Km</th>
                <th>Precio</th>
                <th>Info. Vendedor</th>
                <th>Link</th>
            </tr>
        </thead>
    <?php
    
    foreach($all_results as $info){        
        ?>
            <tr>       
                <td><?php echo $marca; ?></td>
                <td><?php echo $modelo; ?></td>                
            </tr>
        <?php
        die(var_dump($marca));
    
        // Genrating Execel  filess
        // header("Content-type: application/octet-stream");
        header("Content-type: text/xml");
        header("Content-Disposition: attachment; filename=".$filename."-Report.xls");
        header("Pragma: no-cache");
        header("Expires: 0");    
        }
}

function LogCron($new_data, $name) {
    $new_data = date("Ymd G:i:s") . "  >>  " . $new_data;
    $my_file  = dirname(__FILE__) . '/logs/' . $name.'_'.date("Y-m-d").'.log';
    $handle = fopen($my_file, 'a') or die('Cannot open file:  ' . $my_file);
    fwrite($handle, $new_data . "\n");
}



?>