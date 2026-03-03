<?php

require "log.php"; // clases y métodos para guardar logs de todas las llamadas a la API
require "notificacion.php"; // clases y métodos para generar notificaciones en la plataforma CeNaFRE
require_once __DIR__ . "/cotizacion_generada.php";


//functions
function striped($string){
	return preg_replace('/[^a-zA-Z0-9\-_]/','', $string);
}

function base64_to_jpeg($base64_string, $output_file){
    $ifp = fopen($output_file, "wb");
	
    $data = explode(',', $base64_string);

    fwrite($ifp, base64_decode($data[1]));
    fclose($ifp);

    return $output_file;
}

function formatoCI($ci){
	$CIarray = str_split($ci);
	
	if(count($CIarray) == 8){
		$ci = $CIarray[0].'.'.$CIarray[1].$CIarray[2].$CIarray[3].'.'.$CIarray[4].$CIarray[5].$CIarray[6].'-'.$CIarray[7];
	}elseif(count($CIarray) == 7){
		$ci = $CIarray[0].$CIarray[1].$CIarray[2].'.'.$CIarray[3].$CIarray[4].$CIarray[5].'-'.$CIarray[6];
	}else{
		//devuelve lo que entra
	}

	return $ci;
}
