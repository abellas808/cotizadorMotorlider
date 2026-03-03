<?php

function get_datos_ML($marca, $modelo, $anio, $familia, $km){

	$query = "SELECT brand.id_marca, model.id_model FROM act_marcas as brand, act_modelo as model WHERE brand.id = $marca AND model.id = $modelo";
	$connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
	$connection->set_charset('utf8');
	$all = $connection->query($query);
	$brandmodel = $all->fetch_all(MYSQLI_ASSOC);

	$brand = $brandmodel[0]['id_marca'];
	$model = $brandmodel[0]['id_model'];

	$search = 'category=MLU1744&BRAND='.$brand.'&MODEL='.$model.'&VEHICLE_YEAR='.$anio.'-'.$anio;

	if((int)$familia > 0){
		$search = $search . '&SHORT_VERSION='.$familia;
	}

	if((int)$km > 0){
		$kmstart = (int)$km;
		$kmend = (int)$km;
		$search = $search . '&KILOMETERS='.$kmstart.'km-'.$kmend.'km';
	}

	$products_ml = apiAllData($search);

	//NO tengo resultados con lo parametros pasados
	$count = 0;
	$query = "";
	while (count($products_ml->results) < 6) {
		$count = $count + 1;
		if ($count === 1) {
			$query = changeKm($search, $count); //cambio los km sumando +/- 5k
		} else if ($count === 2) {
			$query = changeKm($search, $count); //cambio los km sumando +/- 10k
		} else if ($count === 3) {
			$query = changeYear($search, $anio, $count); //cambio el año restando 1 año
		} else if ($count === 4) {
			$query = changeKm($search, $count); //cambio los km sumando +/- 5k
			$query = changeYear($query, $anio, $count); //cambio el año restando 1 año
		} else if ($count === 5) {
			$query = changeKm($search, $count); //cambio los km sumando +/- 10k
			$query = changeYear($query, $anio, $count); //cambio el año restando 1 año
		} else if ($count === 6) {
			$query = changeYear($search, $anio, $count); //cambio el año sumando 1 año
		} else if ($count === 7) {
			$query = changeKm($search, $count); //cambio los km sumando +/- 5k
			$query = changeYear($query, $anio, $count); //cambio el año sumando 1 año
		} else if ($count === 8) {
			$query = changeKm($search, $count); //cambio los km sumando +/- 10k
			$query = changeYear($query, $anio, $count); //cambio el año sumando 1 año
		} else if ($count === 9) {
			$query = changeYear($search, $anio, $count); //cambio el año sumando +/- 1 año
		} else if ($count === 10) {
			$query = changeKm($search, $count); //cambio los km sumando +/- 5k
			$query = changeYear($query, $anio, $count); //cambio el año sumando +/- 1 año
		} else if ($count === 11) {
			$query = changeKm($search, $count); //cambio los km sumando +/- 10k
			$query = changeYear($query, $anio, $count); //cambio el año sumando +/- 1 año
		}  else if ($count === 12) {
			break;
		}
		$products_ml = apiAllData($query);
	}

	if(count($products_ml->results) > 5){
		$all_price = [];
		$total = 0;
		foreach($products_ml->results as $vehi){
			foreach($vehi->attributes as $filters){
				if($filters->id === 'KILOMETERS'){
					if((int)$filters->value_name > 0){
						$total = $total + 1;
						$all_price [] = $vehi->price;
					}
				}
			}
		}
		
		if($query == ""){
			$query = $search;
		}
		$response = '{"valor_maximo":'.max($all_price).',"valor_minimo":'.min($all_price).',"totalR":'.count($products_ml->results).',"total":'.$total.',"query":"'.$query.'"}';
		return json_decode($response);
	} else {
		return null;
	}
}

function apiAllData($search){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mercadolibre.com/sites/MLU/search?");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $search);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //PARA QUE ANDE LOCAL
    $products_ml = json_decode(curl_exec($ch));
    curl_close($ch);
	return $products_ml;
}

function changeKm($query, $total) {
    $new_query = "";

    $new_km = explode("&KILOMETERS=", $query);
    $menor_km = explode("km-", $new_km[1]);
    $mayor_km = explode("km", $menor_km[1]);
    $menor_km = (int)$menor_km[0];
    $mayor_km = (int)$mayor_km[0];

	if($total === 1 || $total === 4 || $total === 7 || $total === 10){
		$mayor_km = $mayor_km + 5000;
    	$menor_km = $menor_km - 5000;
	} else if($total === 2 || $total === 5 || $total === 8 || $total === 11){
		$mayor_km = $mayor_km + 10000;
    	$menor_km = $menor_km - 10000;
	}

    $new_query = $new_km[0] . '&KILOMETERS=' . $menor_km . 'km-' . $mayor_km . 'km';
    return $new_query;
}

function changeYear($query, $anio, $total){
    $new_query = "";

    $year = explode("&VEHICLE_YEAR=".$anio."-".$anio, $query);
	$menor_year = (int)$anio;
    $mayor_year = (int)$anio;

	if($total === 3 || $total === 4 || $total === 5){
		$menor_year = (int)$anio - 1;
    	$mayor_year = (int)$anio;
	} else if($total === 6 || $total === 7 || $total === 8){
		$menor_year = (int)$anio;
    	$mayor_year = (int)$anio + 1;
	} else if($total === 9 || $total === 10 || $total === 11){
		$menor_year = (int)$anio - 1;
    	$mayor_year = (int)$anio + 1;
	}

	$new_query = $year[0] . '&VEHICLE_YEAR=' . $menor_year . '-' . $mayor_year . $year[1];
    return $new_query;
}

//category=MLU1744&BRAND=58955&MODEL=66609&VEHICLE_YEAR=2010-2010&KILOMETERS=90000km-110000km&SHORT_VERSION=2160184