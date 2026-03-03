<?php

date_default_timezone_set('America/Montevideo');

include('./../../config.php');
include('./../../config/config.inc.php');
include('./../../adm/includes/funciones.php');

$request = file_get_contents("php://input"); // gets the raw data
$params = json_decode($request,true); // true for return as array

$modelo = $params['modelo'];
$marca = $params['marca'];
$anio = $params['anio'];
$familia = $params['familia'];
$familiaCustom = $params['familiaCustom'];

$nombre = $params['nombre'];
$telefono = $params['telefono'];
$email = $params['email'];

$kilometros_cotizacion = $params['kilometros'];
$ficha_tecnica = $params['ficha_tecnica']; //ficha en service oficial
$cantidad_duenios = $params['cantidad_duenios']; //cantidad de due;os
$venta_permuta = $params['venta_permuta'];// seleccione tipo venta
$color_auto = $params['color_auto'];// color del auto
$choque_leve = $params['choque_leve'];// sufrió choque Leve
$choque_grave = $params['choque_grave'];// sufrió choque Grave 
$estado_tapizado = $params['estado_tapizado'];// estado del tapizado
$estado_volante = $params['estado_volante'];// estado del Volante
$empadronamiento = $params['empadronamiento'];// empadronamiento del vehículo 
$servicio = $params['servicio'];// servicio
$correa = $params['correa'];// correa de distribución
$bateria = $params['bateria'];// batería
$piezas_chapista = $params['piezas_chapista'];// piezas para chapista
$neumaticos = $params['neumaticos'];// neumáticos para cambiar 
$tazas_llantas = $params['tazas_llantas'];// tazas o llantas para pintar
$parabrisas = $params['parabrisas'];// cambiar parabrisas 
$faros = $params['faros'];// faros para cambiar 
$aire_acondicionado = $params['aire_acondicionado'];// aire acondicionado
$sensor_estacionamiento = $params['sensor_estacionamiento'];// sensor de estacionamiento
$camara_reserva = $params['camara_reserva'];// cámara de reversa 
$radio = $params['radio'];// radio
$alarma = $params['alarma'];// alarma
$vidrios = $params['vidrios'];// vidrios eléctricos
$espejos = $params['espejos'];// espejos eléctricos
$llaves = $params['llaves'];// dos juegos llaves 
$limpieza_tapizado = $params['limpieza_tapizado'];// limpieza de tapizado 
$valor_pretendido = $params['valor_pretendido'];// valor pretendido

$textAuto = $params['auto'];// Nombre del auto EJ: Chevrolet Corsa
$textVersion = $params['version'];// Nombre de la version EJ: SPORT/GL

$textBrand = $params['txtmarca'];
$textModel = $params['txtmodel'];
$textFamiliy = $params['txtfamilia'];

if($kilometros_cotizacion == ''){
	$valor_final = 0;
	$id_cotizacion = 0;
	$msg = 'Error, no se puede realizar la cotización, debes ingresar los kilómetros del automovil';

	$_SESSION['cotizacion_realizada'] = null; 
} elseif($ficha_tecnica == 'xx' || $ficha_tecnica == ''){
	$valor_final = 0;
	$id_cotizacion = 0;
	$msg = 'Error, no se puede realizar la cotización, debes ingresar si tiene ficha técnica';
	$_SESSION['cotizacion_realizada'] = null; 

} elseif($cantidad_duenios == 'xx' || $cantidad_duenios == ''){
	$valor_final = 0;
	$id_cotizacion = 0;
	$msg = 'Error, no se puede realizar la cotización, debes ingresar la cantidad de dueños';
	$_SESSION['cotizacion_realizada'] = null; 

} elseif($venta_permuta == 'xx' || $venta_permuta == ''){
	$valor_final = 0;
	$id_cotizacion = 0;
	$msg = 'Error, no se puede realizar la cotización, debes ingresar el tipo de venta';
	$_SESSION['cotizacion_realizada'] = null; 

} elseif($valor_pretendido < 0 || $valor_pretendido == ''){
	$valor_final = 0;
	$id_cotizacion = 0;
	$msg = 'Error, no se puede realizar la cotización, debes ingresar un valor pretendido';
	$_SESSION['cotizacion_realizada'] = null; 

} else {

	$family = "";
	if($familia == "otro"){
		$family = $familiaCustom;
	} else {
		$family = $familia;
	}

	$url = $config->urlBase.'ws/pricesInternal';
	$parametros = array("brand"=>$marca,"model"=>$modelo,"anio"=>$anio,"version"=>$family,"km"=>$kilometros_cotizacion);
	$auto = json_decode(httpPost($url,$parametros));
	
	if($auto->codigo === 200){
		
		$valor_minimo_autodata = $auto->precios->valor_minimo;
		$valor_maximo_autodata = $auto->precios->valor_maximo;
		$valor_promedio = $auto->precios->valor_promedio;

		if(isset($valor_minimo_autodata) && isset($valor_maximo_autodata)){

			$url = $config->urlBase.'ws/averageInternal';
			$parametros = array("promedio"=>$valor_promedio);
			$promedio = json_decode(httpPost($url,$parametros));

			$url = $config->urlBase.'ws/internalQuotation';
			$parametros = array(
				"name"=>$nombre,
				"email"=>$email,
				"phone"=>$telefono,
				"brand"=>$marca,
				"model"=>$modelo,
				"anio"=>$anio,
				"version"=>$family,
				"km"=>$kilometros_cotizacion,
				"promedio"=>$promedio->valores->promedio_motorlider,
				"ftecnica"=>$ficha_tecnica,
				"cduenios"=>$cantidad_duenios,
				"vpermuta"=>$venta_permuta,
				"cauto"=>$color_auto,
				"choquel"=>$choque_leve,
				"choqueg"=>$choque_grave,
				"estadot"=>$estado_tapizado,
				"estadov"=>$estado_volante,
				"empadronamiento"=>$empadronamiento,
				"servicio"=>$servicio,
				"correa"=>$correa,
				"bateria"=>$bateria,
				"piezasc"=>$piezas_chapista,
				"neumaticos"=>$neumaticos,
				"tazasllantas"=>$tazas_llantas,
				"parabrisas"=>$parabrisas,
				"faros"=>$faros,
				"airea"=>$aire_acondicionado,
				"sensore"=>$sensor_estacionamiento,
				"camarar"=>$camara_reserva,
				"radio"=>$radio,
				"alarma"=>$alarma,
				"vidriose"=>$vidrios,
				"espejose"=>$espejos,
				"dosllaves"=>$llaves,
				"limpiezat"=>$limpieza_tapizado,
				"vpretendido"=>$valor_pretendido,
				"vminimo" => $valor_minimo_autodata,
				"vmaximo" => $valor_maximo_autodata,
				"vpromedio" => $valor_promedio,
				"txtauto" => $textAuto
			);
			$valorMinMaxProMotorlider = json_decode(httpPost($url,$parametros));

			$msg = '';

			$valor_final_minimo = $valor_minimo_autodata;
			$valor_final_maximo = $valor_maximo_autodata;
			$porcentajes_aplicados = $promedio->valores->promedio_motorlider;

			if (intval($valor_final_minimo) > 0 && intval($valor_final_maximo) > 0) {

				$valor_final = $valor_final_minimo;

				if($venta_permuta == 'Entrega'){
					$msg .= 'Valor de mercado entre: <strong> USD '. number_format($valor_final_minimo, 0, ',', '.') .'</strong> y <strong>USD '. number_format($valor_final_maximo, 0, ',', '.').'</strong>';
					$msg .= '<br>Valor promedio de mercado: <strong> USD '.number_format($promedio->valores->promedio_ml, 0, ',', '.').'</strong>';
					$msg .= '<br><br>Promedio Motorlider: <strong> USD '.number_format($promedio->valores->promedio_motorlider, 0, ',', '.').'</strong>';
					if(isset($valorMinMaxProMotorlider->valores->vpretendido)){
						$valor_pretendido = $valorMinMaxProMotorlider->valores->vpretendido;
						$msg .= '<br><br>Su vehículo lo estaríamos tomando como forma de pago en: <strong> USD '. number_format($valor_pretendido, 0, ',', '.') .'</strong>';
					} else {
						$valor_definitivo = $valorMinMaxProMotorlider->valores->valordefinitivo_motorlider;
						$msg .= '<br><br>Su vehículo lo estaríamos tomando como forma de pago en: <strong> USD '. number_format($valor_definitivo, 0, ',', '.') .'</strong>';
					}
					$msg .= '<br><br>Nombre: '.$nombre;
					$msg .= '<br>Email: '.$email;
					$msg .= '<br>Teléfono: '.$telefono;
					$msg .= '<br>Vehículo: '.$textAuto;
					$msg .= '<br>Año: '.$anio;
					if($familia == "otro"){
						$msg .= '<br>Versión: '.$familiaCustom;
					} else {
						$msg .= '<br>Versión: '.$textVersion;
					}
					$msg .= '<br>Kilómetros: '.number_format($kilometros_cotizacion, 0, ',', '.');
					$msg .= '<br>Ficha en service oficial: '.$ficha_tecnica;
					$msg .= '<br>Cantidad de dueños: '.$cantidad_duenios;
					$msg .= '<br>Tipo de Venta: '.$venta_permuta;
					$msg .= '<br>Valor pretendido: '.number_format($valor_pretendido, 0, ',', '.');
					$msg .= '<br>Código: '.$valorMinMaxProMotorlider->cotizacion;
					$msg .= '<br><br>Total de vehículos usados encontrados: '.$auto->precios->total;
					$msg .= '<br>Total de vehículos 0km encontrados: '.$auto->precios->total0km;
				} else {
					$msg .= 'Valor de mercado entre: <strong> USD '. number_format($valor_final_minimo, 0, ',', '.') .'</strong> y <strong>USD '. number_format($valor_final_maximo, 0, ',', '.').'</strong>';
					$msg .= '<br>Valor promedio de mercado: <strong> USD '.number_format($promedio->valores->promedio_ml, 0, ',', '.').'</strong>';
					$msg .= '<br><br>Promedio Motorlider: <strong> USD '.number_format($promedio->valores->promedio_motorlider, 0, ',', '.').'</strong>';
					if(isset($valorMinMaxProMotorlider->valores->vpretendido)){
						$valor_pretendido = $valorMinMaxProMotorlider->valores->vpretendido;
						$msg .= '<br><br>Su vehículo lo estaríamos comprando en: <strong> USD '. number_format($valor_pretendido, 0, ',', '.') .'</strong>';
					} else {
						$valor_definitivo = $valorMinMaxProMotorlider->valores->valordefinitivo_motorlider;
						$msg .= '<br><br>Su vehículo lo estaríamos comprando en: <strong> USD '. number_format($valor_definitivo, 0, ',', '.') .'</strong>';
					}
					$msg .= '<br><br>Nombre: '.$nombre;
					$msg .= '<br>Email: '.$email;
					$msg .= '<br>Teléfono: '.$telefono;
					$msg .= '<br>Vehículo: '.$textAuto;
					$msg .= '<br>Año: '.$anio;
					if($familia == "otro"){
						$msg .= '<br>Versión: '.$familiaCustom;
					} else {
						$msg .= '<br>Versión: '.$textVersion;
					}
					$msg .= '<br>Kilómetros: '.number_format($kilometros_cotizacion, 0, ',', '.');
					$msg .= '<br>Ficha en service oficial: '.$ficha_tecnica;
					$msg .= '<br>Cantidad de dueños: '.$cantidad_duenios;
					$msg .= '<br>Tipo de Venta: '.$venta_permuta;
					$msg .= '<br>Valor pretendido: '.number_format($valor_pretendido, 0, ',', '.');
					$msg .= '<br>Código: '.$valorMinMaxProMotorlider->cotizacion;
					$msg .= '<br><br>Total de vehículos usados encontrados: '.$auto->precios->total;
					$msg .= '<br>Total de vehículos 0km encontrados: '.$auto->precios->total0km;
				}

				$log = file_get_contents(dirname(dirname(dirname(__FILE__))) . '/ws/logs/'.$marca.'_'.$modelo.'_'.$anio.'_'.$auto->precios->hash.'.log');
				$exp = explode("\n",$log);
				foreach($exp as $e){
					$msg .= $e;
					$msg .= '<br>';
				}

				$id_cotizacion = $valorMinMaxProMotorlider->cotizacion;

				if($id_cotizacion > 0){
					$_SESSION['cotizacion_realizada'] = array(
						'id_cotizacion' => $id_cotizacion
					); 
				} else {
					$valor_final = 0;
					$msg = 'Error, no se puede realizar la cotización, intente nuevamente';
					$_SESSION['cotizacion_realizada'] = null; 
				}
			} else {
				$valor_final = 0;
				$msg = 'Error, no se puede realizar la cotización, intente nuevamente';
				$_SESSION['cotizacion_realizada'] = null; 
			}
		}
	} else {
		$valor_final = 0;
		$msg = 'Error, no se puede realizar la cotización, intente nuevamente';
		$_SESSION['cotizacion_realizada'] = null; 
	}
}

$response = array('msg' => $msg, 'valor' => $valor_final,'id_cotizacion' => $id_cotizacion);

echo json_encode($response);