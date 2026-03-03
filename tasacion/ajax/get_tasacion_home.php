<?php
/*ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);*/
date_default_timezone_set('America/Montevideo');

include('./../../config.php');
include('./../../config/config.inc.php');
include('./../adm/includes/funciones.php');

$modelo = $_POST['modelo'];
$marca = $_POST['marca'];
$anio = $_POST['anio'];
$familia = $_POST['familia'];
$familiaCustom = $_POST['familiaCustom'];

$nombre = $_POST['nombre'];
$telefono = $_POST['telefono'];
$email = $_POST['email'];

$kilometros_cotizacion = $_POST['kilometros'];
$ficha_tecnica = $_POST['ficha_tecnica']; //ficha en service oficial
$cantidad_duenios = $_POST['cantidad_duenios']; //cantidad de due;os
$venta_permuta = $_POST['venta_permuta'];// seleccione tipo venta
$valor_pretendido = $_POST['valor_pretendido'];// valor pretendido

$textAuto = $_POST['auto'];// Nombre del auto EJ: Chevrolet Corsa
$textVersion = $_POST['version'];// Nombre de la version EJ: SPORT/GL

$textBrand = $_POST['txtmarca'];
$textModel = $_POST['txtmodel'];
$textFamiliy = $_POST['txtfamilia'];

$valor_final = 0;

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

	$url = $config->urlBase.'ws/publicQuotation';
	$parametros = array(
		"name"=>$nombre,
		"email"=>$email,
		"phone"=>$telefono,
		"brand"=>$marca,
		"model"=>$modelo,
		"anio"=>$anio,
		"version"=>$family,
		"km"=>$kilometros_cotizacion,
		"ftecnica"=>$ficha_tecnica,
		"cduenios"=>$cantidad_duenios,
		"vpretendido"=>$valor_pretendido,
		"vpermuta"=>$venta_permuta,
		"txtauto"=>$textAuto
	);
	$valoresMotorlider = json_decode(httpPost($url,$parametros));

	$url = $config->urlBase.'ws/pricesData';
	$parametros = array("brand"=>$marca,"model"=>$modelo,"anio"=>$anio,"version"=>$family,"km"=>$kilometros_cotizacion);
	$auto = json_decode(httpPost($url,$parametros));

	if($valoresMotorlider->codigo === 200 && $auto->codigo === 200){
		$valor_final_minimo   = $auto->precios->valor_minimo;
		$valor_final_maximo   = $auto->precios->valor_maximo;
		$valor_final_promedio = $auto->precios->valor_promedio;

		$msg = '';

		if($venta_permuta == 'Entrega'){
			$msg .= 'Valor de Mercado entre: <strong> USD '. number_format($valor_final_minimo, 0, ',', '.') .'</strong> y <strong>USD '. number_format($valor_final_maximo, 0, ',', '.').'</strong>';
			$msg .= '<br>Valor Promedio de Mercado: <strong> USD '.number_format($valor_final_promedio, 0, ',', '.').'</strong>';
			if(isset($valoresMotorlider->valores->vpretendido)){
				$valor_pretendido = $valoresMotorlider->valores->vpretendido;
				$valor_final = $valor_pretendido;
				$msg .= '<br><br>Su vehículo lo estaríamos tomando como forma de pago en: <strong> USD '. number_format($valor_pretendido, 0, ',', '.') .'</strong>';
			} else {
				$valor_minimo_motorlider   = $valoresMotorlider->valores->valor_minimo_motorlider;
				$valor_maximo_motorlider   = $valoresMotorlider->valores->valor_maximo_motorlider;
				$valor_promedio_motorlider = $valoresMotorlider->valores->valor_promedio_motorlider;
				$valor_final = $valor_promedio_motorlider;
				$msg .= '<br><br>Su vehículo lo estaríamos tomando como forma de pago entre: <strong> USD '. number_format($valor_minimo_motorlider, 0, ',', '.') .'</strong> y <strong> USD '. number_format($valor_maximo_motorlider, 0, ',', '.').'</strong>';
				$msg .= '<br>Valor de toma promedio: <strong> USD '.number_format($valor_promedio_motorlider, 0, ',', '.').'</strong>';
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
			$msg .= '<br>Valor Pretendido: '.number_format($valor_pretendido, 0, ',', '.');
			$msg .= '<br>Código: '.$valoresMotorlider->cotizacion;
			$msg .= '<br><br>Total de vehículos usados encontrados: '.$auto->precios->total;
			$msg .= '<br>Total de vehículos 0km encontrados: '.$auto->precios->total0km;
		} else {
			$msg .= 'Valor de Mercado entre: <strong> USD '. number_format($valor_final_minimo, 0, ',', '.') .'</strong> y <strong>USD '. number_format($valor_final_maximo, 0, ',', '.').'</strong>';
			$msg .= '<br>Valor Promedio de Mercado: <strong> USD '.number_format($valor_final_promedio, 0, ',', '.').'</strong>';
			if(isset($valoresMotorlider->valores->vpretendido)){
				$valor_pretendido = $valoresMotorlider->valores->vpretendido;
				$valor_final = $valor_pretendido;
						$msg .= '<br><br>Su vehículo lo estaríamos comprando en: <strong> USD '. number_format($valor_pretendido, 0, ',', '.') .'</strong>';
			} else {
				$valor_minimo_motorlider   = $valoresMotorlider->valores->valor_minimo_motorlider;
				$valor_maximo_motorlider   = $valoresMotorlider->valores->valor_maximo_motorlider;
				$valor_promedio_motorlider = $valoresMotorlider->valores->valor_promedio_motorlider;
				$valor_final = $valor_promedio_motorlider;
				$msg .= '<br><br>Su vehículo lo estaríamos comprando entre: <strong> USD '. number_format($valor_minimo_motorlider, 0, ',', '.') .'</strong> y <strong> USD '. number_format($valor_maximo_motorlider, 0, ',', '.').'</strong>';
				$msg .= '<br>Valor de compra promedio: <strong> USD '.number_format($valor_promedio_motorlider, 0, ',', '.').'</strong>';
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
			$msg .= '<br>Valor Pretendido: '.number_format($valor_pretendido, 0, ',', '.');
			$msg .= '<br>Código: '.$valoresMotorlider->cotizacion;
			$msg .= '<br><br>Total de vehículos usados encontrados: '.$auto->precios->total;
			$msg .= '<br>Total de vehículos 0km encontrados: '.$auto->precios->total0km;
		}

		$log = file_get_contents(dirname(dirname(dirname(__FILE__))) . '/ws/logs/'.$marca.'_'.$modelo.'_'.$anio.'_'.$auto->precios->hash.'.log');
		$exp = explode("\n",$log);
		foreach($exp as $e){
			$msg .= $e;
			$msg .= '<br>';
		}
				
		$id_cotizacion = $valoresMotorlider->cotizacion;

		if($id_cotizacion > 0){
			$_SESSION['cotizacion_realizada'] = array(
				'id_cotizacion' => $id_cotizacion
			); 
		} else {
			$valor_final = 0;
			$msg = 'Error, id_cotizacion no encontrado';
			$_SESSION['cotizacion_realizada'] = null; 
		}
	} else {
		$valor_final = 0;
		$msg = 'Error, no se puede realizar la cotización, intente nuevamente';
		$_SESSION['cotizacion_realizada'] = null; 
	}
}

$response = array('msg' => $msg, 'valor' => $valor_final,'id_cotizacion' => $id_cotizacion);

echo json_encode($response);