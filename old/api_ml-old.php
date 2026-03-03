<?php

include('./config.php');
// orden query completo
// category,marca,modelo,q,anio,km

$vehiculo [] = [
    'marca' => 'CHEVROLET',
    'anio' => 2020,
    'familia' => 'JOY',
    'modelo' => 'Nuevo Joy Plus 1.0 Full, 2Abag, ABS, radio, Btooth 4p.',
    'km' => 1
];

$encode_vehiculo = json_encode($vehiculo);

$decode_vehiculo = json_decode($encode_vehiculo, true);

echo APIML($decode_vehiculo);

function APIML($decode_vehiculo){
    //Parametros
    $marca   = $decode_vehiculo[0]['marca'];
    $anio    = $decode_vehiculo[0]['anio'];
    $familia = $decode_vehiculo[0]['familia'];
    $modelo  = $decode_vehiculo[0]['modelo'];
    $km      = $decode_vehiculo[0]['km'];
    $query   = "category=MLU1744";    
        
    //GET MARCA (obl)
    //$decode_vehiculo[0]['marca'];
    $brand = getMarca($marca);
    if($brand != 0){
        $query .= '&BRAND='.$brand[0]["id_marca"];
    }else{
        $query = "No hay resultados";
        die(var_dump("ERROR: marca no encontrado"));
    }
    
    //EXPLODE Modelo de Motorlider
    //Ejecuta el filtro de string
    $modeloMotor = stringFilter($modelo);

    //GET MODELO (obl)
    $model = getModelo($brand[0]["id"],$familia);
        
    if($model != 0){
        $query .= '&MODEL='.$model;
        $query .= '&q='.$modeloMotor[0].'%20'.$modeloMotor[1].'%20'.$modeloMotor[2];
    } else {
        $query = "No hay resultados";
        die(var_dump("ERROR: modelo no encontrado"));
        // $query .= '&q='.$familia.' '.$modeloMotor[0]; //sino hay modelo se pasa modelo y familia
    }
    
    //AÑO (NO PUEDE VENIR VACIO)
    if($decode_vehiculo[0]['anio'] != ''){
        $menor_year = $decode_vehiculo[0]['anio'] - 1;
        $mayor_year = $decode_vehiculo[0]['anio'] + 1;
        $yearToday =  date("Y") + 1;
        if($mayor_year > $yearToday){
            $mayor_year = $yearToday;
        }
        $query .= '&VEHICLE_YEAR='.$menor_year.'-'.$mayor_year;
    }

    //KILOMETERS
    //$decode_vehiculo[0]['km'];
    if($decode_vehiculo[0]['km'] != ''){
        //20.000 -10/+10
        $menor_km = $decode_vehiculo[0]['km'] - 10000;
        if($menor_km <= 0){
            $menor_km = 1;
        }
        $mayor_km = $decode_vehiculo[0]['km'] + 10000;
        $query .= '&KILOMETERS='.$menor_km.'km-'.$mayor_km.'km';
    }
   
    $all_results = [];
    $offset = 0;
    $total  = 0;
    $count = 0;
    $offset_query = '&offset='.$offset;

    $products_ml = apiAllData($query, $offset_query);

    var_dump($query);
    echo "<br>";

    while(count($products_ml->results) < 5) {
        $count = $count + 1;

        if($count == 1){
            $query = deletQuery($query); //borro la query
        } else if($count <= 3){
            $query = changeYear($query,$anio);
            if($km != ''){ //Si existen km especificados
                $query = changeKm($query); // modifica km
            }
        } else if($count > 3){
            if($km != ''){                
                //cambiar query para poner 1-100.000
                $query = changeKmPlus($query);
                if($count > 4){
                    $query = "No hay resultados";
                    break;
                }
            } else {
                $query = "No hay resultados";
                break;
            }
        }
        $products_ml = apiAllData($query, $offset_query);
        var_dump($query);
        echo "Vuelta:".$count."<br>";
        echo "<br>";
    }

    if( $query != "No hay resultados"){
        //Tengo resultados
        $total = $products_ml->paging->total;
        $offset = $offset + 50;
        $all_results = $products_ml->results;

        //Reviso si hay paginado para volver a armar la consulta 
        //Pasao offset (que es el que cambia de pagina) y asi juntar todos los productos
        while($total > $offset) {
            $offset_query = '&offset='.$offset;
            $products_ml = apiAllData($query, $offset_query);
            $offset = $offset + 50;
            $all_results = array_merge($all_results, $products_ml->results);
        }

        //Tengo TODOS los resultados en $all_results ya sean 5/50/200/500/800
        $all_price = [];
        foreach($all_results as $vehi){
            if($vehi->price < 1000000 && $vehi->price > 5000){
                $all_price [] = $vehi->price;
            }
        }
        var_dump(count($all_price));
        echo "Precio MAXIMO: ".max($all_price);echo "<br>";
        echo "Precio MINIMO: ".min($all_price);echo "<br><br>";
    } else {
        echo "NO HAY RESULTADOS :(";
    }
    
}

function getMarca($brand){
    //va contra la BD y retorna el ID de la marca
    //sino hay retorna 0
    $sql = "SELECT id,id_marca FROM act_marcas WHERE nombre='".$brand."'";
    $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $connection->set_charset('utf8');
    $res = $connection->query($sql);
    $brand = $res->fetch_all(MYSQLI_ASSOC);
    return $brand; //retornar SOLO el ID que pertenezca a la MARCA enviada EJ:Nissan=60505
}

function getModelo($marca,$model){
    //va contra la BD y retorna el ID del modelo
    //sino hay retornar 0
    //ver varios casos ya que en Motorlider a veces el nombre es F 150 y ML es F-150
    //a veces no existe como con las BMW que 310/320 para ML es serie 3 en este caso retornaria 0 y pasamos el 310 por query
    $sql = "SELECT id_model FROM act_modelo WHERE id_marca =".$marca." AND nombre LIKE '".$model."' ";
    $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $connection->set_charset('utf8');
    $res = $connection->query($sql);
    $model = $res->fetch_all(MYSQLI_ASSOC);
    $model = (int)$model[0]["id_model"]; //convierta a int
    return $model; //retornar SOLO el ID que pertenezca al MODELO enviado EJ:Tiida=61186
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

//la primera vez restamos 1 y sumamos 1
//sino hay resultados ya resto 2 y sumo 2
//nunca dejo que pase mas de 1a;o del actual osea estamos en 2021 le sumo 1 y si el resultado es 2023 uso el 2022
//hasta que a;o restamos? 2001 llega motorlider
function changeYear($query){
    $new_query = "";

    //saco el año
    $year = explode("&VEHICLE_YEAR=", $query);

    //consulto si hay kilometros
    if (strpos($year[1], 'KILOMETERS') !== false) {
        $km = explode("&", $year[1]);
        //saco los años que estan y resto 2
        $years = explode("-", $km[0]);
        $menor_year = (int)$years[0] - 2;
        $mayor_year = (int)$years[1] + 2;
        $yearToday =  date("Y") + 1;
        if($mayor_year > $yearToday){
            $mayor_year = $yearToday;
        }
        $new_query = $year[0].'&VEHICLE_YEAR='.$menor_year.'-'.$mayor_year.'&'.$km[1];
    } else {
        $years = explode("-", $year[1]);
        $menor_year = (int)$years[0] - 2;
        $mayor_year = (int)$years[1] + 2;
        $yearToday =  date("Y") + 1;
        if($mayor_year > $yearToday){
            $mayor_year = $yearToday;
        }
        $new_query = $year[0].'&VEHICLE_YEAR='.$menor_year.'-'.$mayor_year;
    }

    return $new_query;
}

function deletQuery($query){
    $new_query = "";
    $query = explode("&q=", $query);
    $yearkm = explode("&VEHICLE_YEAR=", $query[1]);
    $new_query = $query[0].'&VEHICLE_YEAR='.$yearkm[1];
    return $new_query;
}

function changeKm($query){
    //&KILOMETERS
    $new_query = "";
        
    //saco kms
    $new_km = explode("&KILOMETERS=", $query);
    $menor_km = explode("km-", $new_km[1]);
    $mayor_km = explode("km", $menor_km[1]);
    $menor_km = (int)$menor_km[0];
    $mayor_km = (int)$mayor_km[0];    
    //Le sumamos kms
    $mayor_km = $mayor_km + 10000;
    $menor_km = $menor_km - 10000;    
    if($menor_km <= 0){        
        $menor_km = 1;
    }    
    $new_query = $new_km[0].'&KILOMETERS='.$menor_km.'km-'.$mayor_km.'km';
    return $new_query;
}

function changeKmPlus($query){
    //&KILOMETERS
    $new_query = "";        
    //saco kms
    $new_km = explode("&KILOMETERS=", $query);
    $menor_km = explode("km-", $new_km[1]);
    $mayor_km = explode("km", $menor_km[1]);
    $menor_km = (int)$menor_km[0];
    $mayor_km = (int)$mayor_km[0];    
    
    //Le sumamos kms 1 - 100000
    $mayor_km = $mayor_km + 100000;
    $menor_km = 1;

    $new_query = $new_km[0].'&KILOMETERS='.$menor_km.'km-'.$mayor_km.'km';
    return $new_query;
}

function stringFilter ($modelo) {
    // Ejemplo base
    // 118i 1.5T 140 HP Urban Extra Full 5p. Aut. (F40)
    // New QQ 1.0 Standard dir, a/a, vid, 2Abag, ABS 5p. (CHI)
    
    $new_q = array();
    $keywords = array('Nuevo','Nueva','New','Extra','Full','Nuevo,','Nueva,','New,','Extra,','Full,');    
    $string = '';
    $string = explode(" ", $modelo);
    $result = array_diff($string,$keywords);
    
    //Reordena el arreglo
    $i = 0;
    foreach($result as $value){
        $new_q[$i] = $value;
        $i++;
    }    
    // output:  [1]=> string(2) "QQ" [2]=> string(3) "1.0" [3]=> string(8) "Standard" [4]=> string(4) "dir," [5]=> string(4) "a/a," [6]=> string(4) "vid," [7]=> string(6) "2Abag," [8]=> string(3) "ABS" [9]=> string(3) "5p." [10]=> string(5) "(CHI)" }    
    return $new_q;
}

//https://api.mercadolibre.com/sites/MLU/search?
//q=nissan%20tiida
//&category=MLU1744
//&BRAND=60505
//&MODEL=61186
//&VEHICLE_YEAR=2016-2018
//&KILOMETERS=0km-90000km
