<?php

ini_set('memory_limit', '-1');

/*

Tomó todas las marcas de la BD y las recorro
Creó el excel con el nombre de la marca y la cabecera
Con la marca voy contra la API de ML para obtener todas las publicaciones
ML solo me da de a 50 publicaciones entonces en caso que haya más se le vuelve a pedir a la API hasta juntarlas todas
Una vez tengamos todas las publicaciones las recorremos y obtenemos los datos de cada publicación para agregar al excel creado
Cuando termina una marca pasa al siguiente y todo asi hasta terminar
Cuando termina con una marca pasa a la siguiente marca y se vuelve a repetir el proceso

Si por alguna razon se corta el proceso queda el excel con los datos hasta donde llego

*/

date_default_timezone_set('America/Montevideo');

include('config.php');
include('headerCSV.php');

LogCronExcel(" ------- START ------- ");

$query = "SELECT * FROM act_marcas ORDER BY nombre ASC"; //ORDER BY nombre ASC
$connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$connection->set_charset('utf8');
$all_marcas = $connection->query($query);

$query = "category=MLU1744";

LogCronExcel("AFTER BD");

function encodeCSV(&$value, $key){
    $value = iconv('UTF-8', 'Windows-1252', $value);
}

if($all_marcas->num_rows > 0) {

    global $total_excel;
    
    $array_excel = [];
    
    LogCronExcel("num_rows > 0");
    
    array_walk($header, 'encodeCSV');

    $marcas = $all_marcas->fetch_all(MYSQLI_ASSOC);

    LogCronExcel("TOTAL MARCAS ".count($marcas));

    LogCronExcel("genero access_token");

    $access_token = addwoo_ml_token();

    LogCronExcel("access_token " . $access_token);

	$GLOBALS['total_excel'] = 0;
    foreach($marcas as $key => $marca) {

        $brand = '&BRAND='.$marca["id_marca"];
        
        $GLOBALS['total_excel'] = $GLOBALS['total_excel'] + 1;

        //create folders if not exist
        $filename = __DIR__ . '/excels/'.date("Y").'/'.date("m").'/'.date("d");
        if (!is_dir($filename)) {
            mkdir($filename, 0755, true); // true for recursive create
        }
        
        // open the file for writing
        $file = fopen(__DIR__ . '/excels/'.date("Y").'/'.date("m").'/'.date("d").'/'. $marca['nombre'].'.csv', 'w');

        // save the column headers
        fputcsv($file, $header, ";");

        $new_query = $query . $brand;

        $all_results = [];
        $offset = 0;
        $total  = 0;
        $offset_query = '&offset='.$offset; //PROD
        //$offset_query = '&limit=1&offset='.$offset; //LOCAL

        LogCronExcel("MARCA ".$marca['nombre']);
        LogCronExcel("QUERY ".$new_query);

        $products_ml = apiAllData($new_query, $offset_query, $access_token);

        if(count($products_ml->results) > 0){
            $total = $products_ml->paging->total;
            $offset = $offset + 50;
            $all_results = $products_ml->results;
            
            LogCronExcel("TOTAL RESULTADOS ".$total);

            if($total > 350){
                apiAllDataPorPartes($total, $new_query, $offset, $access_token, $all_results, $marca['nombre'], $file, $header);
            } else {
                while($total > $offset) {
                    $offset_query = '&offset='.$offset;
                    $products_ml = apiAllData($new_query, $offset_query, $access_token);
                    $offset = $offset + 50;
                    $all_results = array_merge($all_results, $products_ml->results);
                }

                LogCronExcel("TOTAL RESULTADOS AFTER WHILE ".count($all_results));

                $body = getAllData($all_results, $marca['nombre'], $header);
                foreach ($body as $row){
                    array_walk($row, 'encodeCSV');
                    fputcsv($file, $row, ";");
                }
            }
        }
        fclose($file);
        $indice = substr($marca['nombre'], 0, 1);
        $url_excel = '<a target="_blank" href="https://carplay.uy/excels/'.date("Y").'/'.date("m").'/'.date("d").'/'. $marca['nombre'].'.csv">'.$marca['nombre'].'</a>';
        LogCronExcel("LISTO CSV ".$marca['nombre']);
        $let_array[$indice][] = $url_excel;
        $array_excel = array_merge($array_excel, $let_array);
    }
    //reviso carpetas para ver si puedo borrar contenido
    check_folders();
    
    //envio email con la informacion
    sendemail($GLOBALS['total_excel'],$array_excel);
}

function apiAllData($query, $offset, $access_token){
    LogCronExcel("apiAllData");

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mercadolibre.com/sites/MLU/search?".$query.''.$offset);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Authorization: Bearer $access_token"));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //PARA QUE ANDE LOCAL
    $products_ml = json_decode(curl_exec($ch));
    curl_close($ch);
    return $products_ml;
}

function apiAllDataPorPartes($total, $new_query, $offset, $access_token, $all_results, $marca_nombre, $file, $header) {
    LogCronExcel("apiAllDataPorPartes");

    $allresults = [];
    $allresults = array_merge($allresults, $all_results);

    while($total > $offset) {
        
        if($offset > $total){
            LogCronExcel("ENTRO AL BREAK");
            break;
        }
        $offset_query = '&offset='.$offset;
        $products_ml = apiAllData($new_query, $offset_query, $access_token);
        $offset = $offset + 50;
        $allresults = array_merge($allresults, $products_ml->results);

        $body = getAllData($allresults, $marca_nombre, $header);
        foreach ($body as $row){
            array_walk($row, 'encodeCSV');
            fputcsv($file, $row, ";");
        }
        $allresults = [];
    }
}

function getAllData($all_results, $marca, $header){
    LogCronExcel("getAllData");

    $all_info_return = [];

    foreach($all_results as $info){
        $all_info = [];
        $all_info['BRAND'] =  $marca;

        $getProductInfo = getProductInfo($info->id);
        $all_info = array_merge($all_info, $getProductInfo);

        $all_info = compareHeader($all_info, $header);
        
        $all_info_return[] = $all_info;
    }

    return $all_info_return;
}

function getProductInfo($mlu){
    LogCronExcel("getProductInfo");

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mercadolibre.com/items/$mlu");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //PARA QUE ANDE LOCAL
    $product_data = json_decode(curl_exec($ch));
    curl_close($ch);

    $productinfo = [];

    $getAttributes = getAttributes($product_data->attributes);
    $productinfo['MODEL'] = $getAttributes['MODEL'];
    $productinfo['VERSION'] = $getAttributes['TRIM'];
    $productinfo['YEAR'] = $getAttributes['VEHICLE_YEAR'];
    $productinfo['KM'] = $getAttributes['KILOMETERS'];

    $productinfo['FILE'] =  '';

    $getSellerInfo = getSellerInfo($product_data->seller_id);
    $productinfo['SELLER'] = $getSellerInfo['SELLER'];

    LogCronExcel("phone " . $product_data->seller_contact->phone);
    LogCronExcel("phone2 " . $product_data->seller_contact->phone2);

    $productinfo['PRICE'] =  $product_data->price;
    $productinfo['MIN'] =  '';
    $productinfo['MAX'] =  '';
    $productinfo['VALUE'] =  '';
    $productinfo['MARGIN'] =  '';
    $productinfo['LOCATION'] =  empty($getSellerInfo['CITY']) ? 'N/A' : $getSellerInfo['CITY'];
    $productinfo['LINK'] =  $product_data->permalink;
    $productinfo['PHONE'] =  $product_data->seller_contact->phone != '' ? $product_data->seller_contact->phone : 'N/A';
    $productinfo['PARTAUT'] =  '';

    $productinfo['TITLE'] = $product_data->title;
    $productinfo['CURRENCY'] = $product_data->currency_id;
    foreach($product_data->sale_terms as $terms){
        if($terms->id == 'IS_FINANCEABLE'){
            $productinfofirst['IS_FINANCEABLE'] = $terms->value_name;
        }
    }
    $productinfo['IS_FINANCEABLE'] = empty($productinfofirst['IS_FINANCEABLE']) ? "N/A" : $productinfofirst['IS_FINANCEABLE'];
    $productinfo['TYPE'] = $product_data->listing_type_id;
    $productinfo['MERCADOPAGO'] = $product_data->accepts_mercadopago ? "true" : "false";

    LogCronExcel("datos en productinfo va para los merge");

    $productinfo = array_merge($productinfo, $getSellerInfo);

    $productinfo = array_merge($productinfo, $getAttributes);

    LogCronExcel("fin merge return");

    return $productinfo;
}

function getSellerInfo($seller_id){
    LogCronExcel("getSellerInfo");

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mercadolibre.com/users/$seller_id");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //PARA QUE ANDE LOCAL
    $seller_data = json_decode(curl_exec($ch));
    curl_close($ch);

    $sellerinfo['SELLER'] =  $seller_data->nickname;
    $registration_date = explode("T", $seller_data->registration_date);
    $sellerinfo['REGISTRATION'] = $registration_date[0];
    $sellerinfo['CITY'] = $seller_data->address->city;
    $sellerinfo['POINTS'] =  $seller_data->points;
    $sellerinfo['PROFILE'] = $seller_data->permalink;
    $sellerinfo['STATUS'] = $seller_data->status->site_status;

    return $sellerinfo;
}

function getAttributes($attributes){
    LogCronExcel("getAttributes");
    LogCronExcel("TOTAL attributes ".count($attributes));
    foreach($attributes as $att){
        $someattributes[$att->id] = $att->value_name;
    }
    return $someattributes;
}

function compareHeader($all_info, $header){
    LogCronExcel("compareHeader");
    foreach($header as $keyhead => $head){
        if($keyhead === 'FILE'){
            $final_return[] = empty($all_info[$keyhead]) ? "" : $all_info[$keyhead];
            continue;
        }
        if($keyhead === 'MIN'){
            $final_return[] = empty($all_info[$keyhead]) ? "" : $all_info[$keyhead];
            continue;
        }
        if($keyhead === 'MAX'){
            $final_return[] = empty($all_info[$keyhead]) ? "" : $all_info[$keyhead];
            continue;
        }
        if($keyhead === 'VALUE'){
            $final_return[] = empty($all_info[$keyhead]) ? "" : $all_info[$keyhead];
            continue;
        }
        if($keyhead === 'MARGIN'){
            $final_return[] = empty($all_info[$keyhead]) ? "" : $all_info[$keyhead];
            continue;
        }
        if($keyhead === 'PARTAUT'){
            $final_return[] = empty($all_info[$keyhead]) ? "" : $all_info[$keyhead];
            continue;
        }

        $final_return[] = empty($all_info[$keyhead]) ? "N/A" : $all_info[$keyhead];
    }
    return $final_return;
}

//reviso si hay carpetas para borrar
function check_folders(){
    LogCronExcel("check_folders");

    $month = date("m");
    $dir_month = (int)$month - 2; 

    if($dir_month < 0){
        LogCronExcel("reviso year anterior y borro todo");
        $years = date("Y");
        $dir_years = (int)$years - 1; 
        $dir_month = 12; 
        while ($dir_month < (int)$month) {
            $filename = __DIR__ . '/excels/'.$dir_years.'/'.$dir_month.'/';
            if (is_dir($filename)) {
                LogCronExcel("carpeta para borrar ".$filename);
                all_rmdir($filename); 
            }
            $dir_month--;
            if($dir_month < 10){
                $dir_month = '0'.$dir_month;
            }
        }
    } else {
        LogCronExcel("reviso year actual");
        if($dir_month < 10){
            $dir_month = '0'.$dir_month;
        }
        $total_folders = 0;
        while ((int)$dir_month < (int)$month) {
            $filename = __DIR__ . '/excels/'.date("Y").'/'.$dir_month.'/';
            if (is_dir($filename)) {
                $total_folders++;
            }
            $dir_month++;
            if($dir_month < 10){
                $dir_month = '0'.$dir_month;
            }
        }
        LogCronExcel("total_folders ".$total_folders);

        if($total_folders === 2 ){
            LogCronExcel("entro a borrar carpeta + contenido");
            $dir_month = (int)$month - 2;
            if($dir_month < 10){
                $dir_month = '0'.$dir_month;
            }
            while ((int)$dir_month < (int)$month) {
                $filename = __DIR__ . '/excels/'.date("Y").'/'.$dir_month.'/';
                LogCronExcel("carpeta para borrar ".$filename);
                all_rmdir($filename); 
                $dir_month++;
                if($dir_month < 10){
                    $dir_month = '0'.$dir_month;
                }
            }
        } else {
            LogCronExcel("NO hay suficientes carpetas para borrar");
        }
    }
}

//borro la carpeta con TODOS sus archivos
function all_rmdir($carpeta) {
    LogCronExcel("all_rmdir");
    foreach(glob($carpeta . "/*") as $archivos_carpeta){
        if (is_dir($archivos_carpeta)){
            all_rmdir($archivos_carpeta);
        } else {
            unlink($archivos_carpeta);
        }
    }
    rmdir($carpeta);
}

function addwoo_ml_token(){

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

//send mail
function sendemail($total_excel,$array_excel){
    $to = 'gfigueroa@actotal.com';
    $subject = 'MotrLider - Excels generados';

    $message = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
    <html lang="en">
    <head>
    
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title></title>

    <style type="text/css">
    </style>    
    </head>
    <body style="margin:0; padding:50px; background-color:#f1f1f1;">
    <center>
        <table width="90%" border="0" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="padding: 20px; border-radius: 4px;">
            <tr>
                <td align="center" valign="top">
                    <table width="100%" style="border-collapse: collapse; margin: 20px 0">
                        <tbody>
                            <tr>
                                <td rowspan="2"><h1 style="font-weight: 300;font-size: 22px;line-height: 1em;margin: 0;">Excels Generados</h1></td>
                            </tr>
                            <tr>
                                <td colspan="2"><h1 style="font-weight: 300;font-size: 22px;line-height: 1em;margin: 0;text-align: right;">'.date("d/m/Y (h:i:s A)").'</h1></td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
            <tr>
                <td align="center" valign="top">
                    <table width="100%" style="border-collapse: collapse;">
                        <tbody>
                            <tr>
                                <td style="border-bottom: 1px solid #f1f1f1;padding: 8px 0;font-size: 13px;font-weight: 500;">Total de Excels Generados:</td>
                                <td style="border-bottom: 1px solid #f1f1f1;padding: 8px 0;font-size: 13px;font-weight: 700; text-align: right;">'.$total_excel.'</td>
                            </tr>
                            <tr>
                                <td><br></td>
                            </tr>
                            <tr>
                                <td><h2 style="font-weight: 300;font-size: 19px;line-height: 1em;">Listado de Excels</h2></td>
                            </tr>
                            <tr>
                                <td>';
                                foreach($array_excel as $indice => $value){
                                    $message .= '#'.$indice.'<br>';
                                    foreach($value as $val){
                                        $message .= $val.'<br>';
                                    }
                                    $message .= '<br>';
                                } $message .=
                                '</td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
    </center>
    </body>
    </html>';

    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=iso-8859-1';
    $headers[] = 'From: MotorLider <info@motorlider.com.uy>';

    mail($to, $subject, $message, implode("\r\n", $headers));
}

//logs
function LogCronExcel($new_data) {
    $new_data = date("Ymd G:i:s") . "  >>  " . $new_data;
    $my_file  = dirname(__FILE__) . '/logs/' . 'LogExcel_'.date("Y-m-d").'.log';
    $handle = fopen($my_file, 'a') or die('Cannot open file:  ' . $my_file);
    fwrite($handle, $new_data . "\n");
}

?>