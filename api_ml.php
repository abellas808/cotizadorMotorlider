<?php

include('./config.php');

/**
 * ------- NOTAS -------
 * Se va a buscar el ID de marca a la BD
 * Se va a buscar el ID de modelo a la BD
 *  - SINO hay marca&modelo --> return no hay resultados 
 * Si hay marca&modelo sigo
 * Se toman las primeras 2 palabras del modelo sin importar que traiga y se pasa como &q
 * Al año que venga se aplica +2/-2
 * A los km que vengan se aplica +10.000/-10.000
 * Tiene que tener 5 o mas resultados para ser tenido en cuenta
 * Sino encuentra de una con la &q se borra y se vuelve a consultar
 * Si por segunda vez no hay resultados se va a bajar año + km
 * Si los km da negativo o 0 se pone 1
 * Se dan 3 vueltas (equivale a bajar 6 años)
 *  - Si NO hay resultados se consulta si hay KM
 *      - NO KM - return no hay resultados
 *      - SI KM - hacemos una ultima query cambiando SOLAMENTE los KM poniendo de 1km-100000km
 *          - Si luego de esto no hay resultados return no hay resultados
 * Se toman precios < 1.000.000 && > 5.000
 */

/*
 BYD AUTO      - EXISTE BYD
 GREENWHEEL    - NO EXISTE
 KIA MOTORS    - EXISTE KIA
 MERCEDES BENZ - EXISTE MERCEDES-BENZ 
 */

$vehiculo[] = [
    'marca' => $_GET['marca'] ,
    'anio' => $_GET['anio'],
    'familia' => $_GET['familia'],
    'modelo' => $_GET['modelo'],
    'km' => $_GET['km']
];


$encode_vehiculo = json_encode($vehiculo);

$decode_vehiculo = json_decode($encode_vehiculo, true);

echo APIML($decode_vehiculo);

function APIML($decode_vehiculo)
{
    //Parametros
    $marca   = $decode_vehiculo[0]['marca'];
    $anio    = $decode_vehiculo[0]['anio'];
    $familia = $decode_vehiculo[0]['familia'];
    $modelo  = $decode_vehiculo[0]['modelo'];
    $km      = $decode_vehiculo[0]['km'];

    $query = "category=MLU1744";

    //GET MARCA
    $marca = verifyBrand($marca);
    $brand = getMarca($marca);

    echo "<pre>";
    var_dump(  $decode_vehiculo );
    echo "</pre>";
    echo "<br/>";
    
    
    if (count($brand) > 0) {
        $query .= '&BRAND=' . $brand[0]["id_marca"];

       

        //EXPLODE Modelo de Motorlider para quedarme con los 2 primeros
        $modeloMotor = $modelo;
        $modeloMotor = explode(" ", $modeloMotor);
        $modeloMotor = $modeloMotor[0] . ' ' . $modeloMotor[1];

        //GET MODELO
        $model = getModelo($brand[0]["id"], $familia);
        if (count($model) > 0) {
            $query .= '&MODEL=' . $model[0]["id_model"];;
            $query .= '&q=' . $modeloMotor;

            //AÑO
            if ($anio != '') {
                $menor_year = $anio - 1;
                $mayor_year = $anio + 1;
                $yearToday =  date("Y") + 1;
                if ($mayor_year > $yearToday) {
                    $mayor_year = $yearToday;
                }
                $query .= '&VEHICLE_YEAR=' . $menor_year . '-' . $mayor_year;
            }

            //KILOMETERS
            if ($km != '') {
                //20.000 -10/+10
                $menor_km = $km - 10000;
                if ($menor_km <= 0) {
                    $menor_km = 1;
                }
                $mayor_km = $km + 10000;
                $query .= '&KILOMETERS=' . $menor_km . 'km-' . $mayor_km . 'km';
            }

            $all_results = [];
            $offset = 0;
            $total  = 0;
            $offset_query = '&offset=' . $offset;

           // var_dump("FIRST QUERY " . $query);
            //echo "<br>";

            //Tengo la QUERY voy contra la API para obtener resultados
            $products_ml = apiAllData($query, $offset_query);

            //NO tengo resultados con lo parametros pasados
            $count = 0;
            while (count($products_ml->results) < 2) {
                $count = $count + 1;
                if ($count == 1) {
                    $query = deletQuery($query); //borro la &q
                } else if ($count <= 3) {
                    $query = changeYear($query); //cambio el año
                    if ($km != '') {
                        $query = changeKm($query); //cambio los km
                    }
                } else if ($count > 3) {
                    if ($km != '') {
                       // $query = changeMaxMinKm($query); //ultima chance pasando 1km-100000km
                        if ($count > 4) {
                            var_dump("ultima chance " . $query);
                            $query = "No hay resultados";
                            break;
                        }
                    } else {
                        $query = "No hay resultados";
                        break;
                    }
                }
                $products_ml = apiAllData($query, $offset_query);
            }

            var_dump("FINAL QUERY " . $query);
            echo "<br>";
            echo "<br>";
            echo "<br>";
            
            if ($query != "No hay resultados") {
                //Tengo resultados
                $total = $products_ml->paging->total;
                $offset = $offset + 50;
                $all_results = $products_ml->results;

                //Reviso si hay paginado para volver a armar la consulta 
                //Pasao offset (que es el que cambia de pagina) y asi juntar todos los productos
                while ($total > $offset) {
                    $offset_query = '&offset=' . $offset;
                    $products_ml = apiAllData($query, $offset_query);
                    $offset = $offset + 50;
                    $all_results = array_merge($all_results, $products_ml->results);
                }

                //Tengo TODOS los resultados en $all_results ya sean 5/50/200/500/800
                $all_price = [];
                foreach ($all_results as $vehi) {
                    if ($vehi->price < 100000 && $vehi->price > 5000) {
                        echo "<a target='_blank' href=" . $vehi->permalink ." /> Precio : ".  $vehi->price . " - ".  $vehi->title . " </a><br>";
                        
                        $all_price[] = $vehi->price;
                    }
                }
                //var_dump(count($all_price));
                echo "<br>";
                echo "Precio MAXIMO: " . max($all_price);
                echo "<br>";
                echo "Precio MINIMO: " . min($all_price);
                echo "<br><br>";
                echo "Resultados : " . count($all_price);
            } else {
                echo "NO HAY RESULTADOS :(";
            }
        } else {
            var_dump($marca);
            var_dump($brand);
            var_dump($familia);
            var_dump($model);
            echo "ERROR MODELO->NO HAY RESULTADOS :(";
        }
    } else {
        var_dump($marca);
        echo "ERROR MARCA->NO HAY RESULTADOS :(";
    }
}

//Obtengo ID de Marca
function getMarca($brand)
{
    //va contra la BD y retorna el ID de la marca
    $sql = "SELECT id,id_marca FROM act_marcas WHERE nombre='" . $brand . "'";
    $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $connection->set_charset('utf8');
    $res = $connection->query($sql);
    $brand = $res->fetch_all(MYSQLI_ASSOC);
    return $brand;
}

function getModelo($marca, $model)
{
    //va contra la BD y retorna el ID del modelo
    //$sql = "SELECT id_model FROM act_modelo WHERE id_marca =".$marca." AND nombre LIKE '%".$model."%'";
    $sql = "SELECT id_model FROM act_modelo WHERE id_marca =" . $marca . " AND nombre = '" . $model . "'";
    //var_dump($sql);
    $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $connection->set_charset('utf8');
    $res = $connection->query($sql);
    $model = $res->fetch_all(MYSQLI_ASSOC);
    return $model;
}

function apiAllData($query, $offset)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mercadolibre.com/sites/MLU/search?");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $query . '' . $offset);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //PARA QUE ANDE LOCAL
    $products_ml = json_decode(curl_exec($ch));
    curl_close($ch);
    return $products_ml;
}

function changeYear($query)
{
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
        if ($mayor_year > $yearToday) {
            $mayor_year = $yearToday;
        }
        $new_query = $year[0] . '&VEHICLE_YEAR=' . $menor_year . '-' . $mayor_year . '&' . $km[1];
    } else {
        $years = explode("-", $year[1]);
        $menor_year = (int)$years[0] - 2;
        $mayor_year = (int)$years[1] + 2;
        $yearToday =  date("Y") + 1;
        if ($mayor_year > $yearToday) {
            $mayor_year = $yearToday;
        }
        $new_query = $year[0] . '&VEHICLE_YEAR=' . $menor_year . '-' . $mayor_year;
    }

    return $new_query;
}

function changeKm($query)
{
    $new_query = "";

    $new_km = explode("&KILOMETERS=", $query);
    $menor_km = explode("km-", $new_km[1]);
    $mayor_km = explode("km", $menor_km[1]);
    $menor_km = (int)$menor_km[0];
    $mayor_km = (int)$mayor_km[0];
    $mayor_km = $mayor_km + 10000;
    $menor_km = $menor_km - 10000;
    if ($menor_km <= 0) {
        $menor_km = 1;
    }
    $new_query = $new_km[0] . '&KILOMETERS=' . $menor_km . 'km-' . $mayor_km . 'km';
    return $new_query;
}

function deletQuery($query)
{
    $new_query = "";
    $query = explode("&q=", $query);
    $yearkm = explode("&VEHICLE_YEAR=", $query[1]);
    $new_query = $query[0] . '&VEHICLE_YEAR=' . $yearkm[1];
    return $new_query;
}

function changeMaxMinKm($query)
{
    $new_query = "";
    $query = explode("&KILOMETERS=", $query);
    $new_query = $query[0] . '&KILOMETERS=1km-100000km';
    return $new_query;
}

function verifyBrand($marca)
{
    if ($marca == "BYD AUTO") {
        $marca = "BYD";
    }

    if ($marca == "KIA MOTORS") {
        $marca = "KIA";
    }

    if ($marca == "MERCEDES BENZ") {
        $marca = "MERCEDES-BENZ";
    }

    return $marca;
}
