<?php

function echo_s($s, $nl2br = 0, $html = 0)
{
	if ($html == 1) {
		echo strip_tags($s, '<br /><p><a><ul><li><b><strong>');
	} else if ($nl2br == 1) {
		echo nl2br(htmlentities($s, ENT_QUOTES, "UTF-8"));
	} else {
		echo htmlentities($s, ENT_QUOTES, "UTF-8");
	}
}

function str_s($s, $nl2br = 0, $html = 0)
{
	if ($html == 1) {
		return strip_tags($s, '<br /><p><a><ul><li><b><strong>');
	} else if ($nl2br == 1) {
		return nl2br(htmlentities($s, ENT_QUOTES, "UTF-8"));
	} else {
		return htmlentities($s, ENT_QUOTES, "UTF-8");
	}
}

function chk_campo($c)
{

	global $modulo;

	return isset($modulo['campos'][$c]) && $modulo['campos'][$c];
}

function sistema_cabezal()
{
	require_once('sistema_cabezal.php');
}

function sistema_pre_contenido()
{
	require_once('sistema_pre_contenido.php');
}

function sistema_post_contenido()
{
	require_once('sistema_post_contenido.php');
}

function simple_crypt($key, $string, $action = 'encrypt')
{
	$res = '';
	if ($action !== 'encrypt') {
		$string = base64_decode($string);
	}
	for ($i = 0; $i < strlen($string); $i++) {
		$c = ord(substr($string, $i));
		if ($action == 'encrypt') {
			$c += ord(substr($key, (($i + 1) % strlen($key))));
			$res .= chr($c & 0xFF);
		} else {
			$c -= ord(substr($key, (($i + 1) % strlen($key))));
			$res .= chr(abs($c) & 0xFF);
		}
	}
	if ($action == 'encrypt') {
		$res = base64_encode($res);
	}
	return $res;
}


function numero($numero, $decimales = 0)
{
	return number_format($numero, $decimales, ',', '.');
}

function cod_url($codigo)
{ // CODIFICA CODIGO PARA ENVIAR EN LA URL
	return str_replace(array(' ', '-', '/'), array('_', '.', '#'), $codigo);
}

function dec_cod_url($codigo)
{ // DECODIFICA CODIGOS RECIBIDOS EN LA URL
	return str_replace(array('_', '.', '#'), array(' ', '-', '/'), $codigo);
}

function format_fecha_db($fecha)
{
	$arr_fecha = explode('/', $fecha);

	return $arr_fecha[2] . '-' . $arr_fecha[1] . '-' . $arr_fecha[0];
}

function numero_compra($id, $comprador)
{

	global $db;

	$usuario = $db->query_first('select * from usuarios where id_usuario = "' . $comprador . '";');

	return strtoupper($usuario['razon_social'][0]) . '-0000-' . $id;
}



function tiene_caracteristica($caracteristica, $automovil)
{

	global $db;

	$caut = $db->query_first('SELECT * FROM automoviles_caracteristicas WHERE id_automoviles = "' . $automovil . '" AND caracteristica = "' . $caracteristica . '"');

	return $caut;
}

function get_name_caracteristica($caracteristica)
{

	$cara_min = strtolower($caracteristica);

	$caract = str_replace(' ', '_', $cara_min);

	return $caract;
}

//  ********* FUNCIONES **************** //


function get_porcentaje_reservas($auto_precio, $valor_tasacion, $porcentaje_reserva)
{
	$precio_final = $auto_precio - $valor_tasacion;
	$monto_reserva = ($precio_final * $porcentaje_reserva) / 100;

	return $monto_reserva;
}

function get_valor_patente_nuevo($auto_precio, $iva)
{
	$valor_sin_iva = $auto_precio - (($auto_precio * $iva) / 100);
	$patente = ($valor_sin_iva * 5) / 100;
	$patente = ($patente * 80) / 100;

	return $patente;
}

function format_titulo_auto($titulo)
{
	$arr_titulo = explode(',', $titulo);

	return $arr_titulo[0];
}

function existe_rango($marca, $p_desde, $p_hasta)
{

	global $db;

	if (intval($p_desde) > 0 && intval($p_hasta) > 0) $sql_precio = ' AND precio >= ' . intval($p_desde) . ' AND precio <= ' . intval($p_hasta);
	else if (intval($p_hasta) == 0)  $sql_precio = ' AND precio > ' . intval($p_desde);

	$sql_marca = intval($marca) > 0 ? ' AND marca = "' . intval($marca) . '"' : '';;

	$db->query('select * from automoviles where activo = 1' . $sql_precio . $sql_marca);
	$result = $db->num_rows > 0 ? true : false;

	return $result;
}

function calcular_tasacion($marca, $modelo, $anio)
{
	global $db;

	$valor = $db->query_first('SELECT * from modelos_valor WHERE id_marca = "' . intval($marca) . '" and id_modelo = "' . intval($modelo) . '" and anio = "' . intval($anio) . '" order by fecha desc');

	return $valor;
}

function get_valor_dolar()
{
	global $db;
	$valor_dolar = $db->query_first('select * from cotizacion where moneda = "USD";')['valor'];

	return $valor_dolar;
}

function get_tasa_financiacion($clearing, $edad, $ingresos, $cuota, $antiguedad, $publico, $porcentaje_a_financiar, $monto_total_automovil)
{
	global $db;

	$porcentaje_calculo = 100;
	$porcentaje_BCU = 40;

	$porcentaje_calculo -= $porcentaje_BCU;

	$config_montos_finanaciacion = $db->query_first('select * from financiacion where id_financiacion = 1');

	//CLEARING
	if (!$clearing) $porcentaje_calculo -= 10;

	//TRABAJO PÚBLICO
	if ($publico) $porcentaje_calculo -= 10;

	//EDAD
	if ($edad > 17 && $edad <= 30) {
		$porcentaje_calculo -= 4;
	} elseif ($edad > 30 && $edad <= 65) {
		$porcentaje_calculo -= 10;
	} elseif ($edad > 65) {
		$porcentaje_calculo -= 4;
	}

	//ANTIGÜEDAD
	if ($antiguedad == 2) $porcentaje_calculo -= 5; // entre 7 y 24 meses
	if ($antiguedad == 3) $porcentaje_calculo -= 10; //más de 24 meses

	//PORCENTAJE A FINANCIAR
	if ($porcentaje_a_financiar <= 15) {
		$porcentaje_calculo -= 10;
	} elseif ($porcentaje_a_financiar > 15 && $porcentaje_a_financiar <= 35) {
		$porcentaje_calculo -= 5;
	}

	//RELACION INGRESO CUOTAS
	//le sumo la tasa techo para ver al relación de la cuota con el ingreso
	//$monto = $monto_total_automovil * (($config_montos_finanaciacion['tasa_techo'] * 100) + 1);
	$monto = $monto_total_automovil + (($monto_total_automovil * $config_montos_finanaciacion['tasa_techo']) / 100);
	$monto_aplicado_procentaje = ($monto * $porcentaje_a_financiar) / 100;
	$monto_cuota = $monto_aplicado_procentaje / $cuota;

	$cotizacion_dolar = get_cotizacion();

	$porcentaje_relacion_cuota_ingresos = ($monto_cuota * 100) / ($ingresos / $cotizacion_dolar);

	if ($porcentaje_relacion_cuota_ingresos <= 15) $porcentaje_calculo -= 10;
	if ($porcentaje_relacion_cuota_ingresos > 15 && $porcentaje_relacion_cuota_ingresos <= 25) $porcentaje_calculo -= 6;
	if ($porcentaje_relacion_cuota_ingresos > 25 && $porcentaje_relacion_cuota_ingresos <= 35) $porcentaje_calculo -= 2;


	$rango = $config_montos_finanaciacion['tasa_techo'] - $config_montos_finanaciacion['tasa_piso'];

	$resta_tope = ($porcentaje_calculo * $rango) / 100;

	$porcentaje_final = $config_montos_finanaciacion['tasa_techo'] - $resta_tope;

	return $porcentaje_final;
}

function get_cotizacion()
{
	global $db;

	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, 'https://sodio.com.uy/cotizaciones.php');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$cotizaciones = curl_exec($ch);

	curl_close($ch);

	$cotizaciones = json_decode($cotizaciones);

	$USD_compra = $cotizaciones->USD[0];
	$USD_venta = $cotizaciones->USD[1];
	$cotizacion_admin = $db->query_first('select * from cotizacion where id_cotizacion = 1')['valor'];

	$promedio = ($USD_compra + $USD_venta) / 2;

	$dolar_sitio = $promedio + $cotizacion_admin;

	return $dolar_sitio;
}

function format_titulo_link($titulo)
{

	return str_replace(' ', '_', $titulo);
}

function des_format_titulo_link($titulo)
{

	return str_replace('_', ' ', $titulo);
}

function get_cotizaciones_generadas($ci){

	global $db;

	$cotizacion_admin = $db->query_first('select count(*) cantidad from cotizaciones_generadas where ci = "'. $ci .'" and cuenta = 1 AND MONTH(fecha) = '. date('m') .' AND YEAR(fecha) = ' . date('Y'));

	return $cotizacion_admin['cantidad'];
}

function calcular_ponderaciones_cotizacion($valor_minimo_autodata,$valor_maximo_autodata,$kilometros_cotizacion,$anio,$ficha_tecnica,$cantidad_duenios,$venta_permuta,$valor_pretendido){

	global $db;

	$porcentaje_km = $db->query_first('select * from autodata where tipo = 1 and km_desde <= "' . $kilometros_cotizacion . '" and km_hasta >= "' . $kilometros_cotizacion . '";');
	$anio_porcentaje = $db->query_first('select * from autodata where tipo = 2 and anio_desde <= "' . $anio . '" and anio_hasta >= "' . $anio . '";');
	$ficha_porcentaje = $db->query_first('select * from autodata where tipo = 3 and ficha_oficial = "' . $ficha_tecnica . '";');
	$cantidad_duenios_porcentaje = $db->query_first('select * from autodata where tipo = 4 and cantidad_duenios = "' . $cantidad_duenios . '";');
	$tipo_venta_porcentaje = $db->query_first('select * from autodata where tipo = 5 and tipo_venta = "' . $venta_permuta . '";');


	$valor_final_minimo = $valor_minimo_autodata;
		$valor_final_maximo = $valor_maximo_autodata;

		$porcentajes_aplicados="";

		//RESTO EL PORCENTAJE AL VALOR MAXIMO
		$ponderador_valor_venal = $db->query_first('select * from ponderador_valor_venal where id_ponderador_valor_venal = 1');
		if($ponderador_valor_venal){
			$valor_final_maximo -= (($ponderador_valor_venal['porcentaje'] * $valor_maximo_autodata) / 100);
			$porcentajes_aplicados .=',valor_venal:-'.$ponderador_valor_venal['porcentaje'];
		}

		//Ponderacion según valor cantidad de kms -- tipo 1
		if($porcentaje_km){

			if($porcentaje_km['operador'] == '+'){
				$valor_final_minimo += (($porcentaje_km['porcentaje'] * $valor_minimo_autodata) / 100);
				$porcentajes_aplicados .=',km:+'.$porcentaje_km['porcentaje'];
			}else{
				$valor_final_minimo -= (($porcentaje_km['porcentaje'] * $valor_minimo_autodata) / 100);
				$porcentajes_aplicados .=',km:-'.$porcentaje_km['porcentaje'];
			}
		}

		//Ponderacion según año del vehículo -- tipo 2
		if($anio_porcentaje){

			if($anio_porcentaje['operador'] == '+'){
				$valor_final_minimo += (($anio_porcentaje['porcentaje'] * $valor_minimo_autodata) / 100);
				$porcentajes_aplicados .=',anios:+'.$anio_porcentaje['porcentaje'];
			}else{
				$valor_final_minimo -= (($anio_porcentaje['porcentaje'] * $valor_minimo_autodata) / 100);
				$porcentajes_aplicados .=',anios:-'.$anio_porcentaje['porcentaje'];
			}
		}

		//Ponderación según si tiene o no ficha -- tipo 3
		if($ficha_porcentaje){

			if($ficha_porcentaje['operador'] == '+'){
				$valor_final_minimo += (($ficha_porcentaje['porcentaje'] * $valor_minimo_autodata) / 100);
				$porcentajes_aplicados .=',ficha:+'.$ficha_porcentaje['porcentaje'];
			}else{
				$valor_final_minimo -= (($ficha_porcentaje['porcentaje'] * $valor_minimo_autodata) / 100);
				$porcentajes_aplicados .=',ficha:-'.$ficha_porcentaje['porcentaje'];
			}
		}

		//Ponderación según si cantidad de dueños -- tipo 4
		if($cantidad_duenios_porcentaje){

			if($cantidad_duenios_porcentaje['operador'] == '+'){
				$valor_final_minimo += (($cantidad_duenios_porcentaje['porcentaje'] * $valor_minimo_autodata) / 100);
				$porcentajes_aplicados .=',cantidad_duenios:+'.$cantidad_duenios_porcentaje['porcentaje'];
			}else{
				$valor_final_minimo -= (($cantidad_duenios_porcentaje['porcentaje'] * $valor_minimo_autodata) / 100);
				$porcentajes_aplicados .=',cantidad_duenios:-'.$cantidad_duenios_porcentaje['porcentaje'];
			}
		}

		//Ponderación según tipo de venta -- tipo 5
		if($tipo_venta_porcentaje){

			//Si es tipo entrega como parte de pago aplica para el monto mayor y menor
			if($tipo_venta_porcentaje['tipo_venta'] == 'Entrega'){

				if($tipo_venta_porcentaje['operador'] == '+'){
					$valor_final_minimo += (($tipo_venta_porcentaje['porcentaje'] * $valor_minimo_autodata) / 100);
					//$valor_final_maximo += (($tipo_venta_porcentaje['porcentaje'] * $valor_maximo_autodata) / 100);
					$porcentajes_aplicados .=',tipo_venta:+'.$tipo_venta_porcentaje['porcentaje'];
					
				}else{
					$valor_final_minimo -= (($tipo_venta_porcentaje['porcentaje'] * $valor_minimo_autodata) / 100);
					//$valor_final_maximo -= (($tipo_venta_porcentaje['porcentaje'] * $valor_maximo_autodata) / 100);
					$porcentajes_aplicados .=',tipo_venta:-'.$tipo_venta_porcentaje['porcentaje'];
				}
				
			}else{
				if($tipo_venta_porcentaje['operador'] == '+'){
					$valor_final_minimo += (($tipo_venta_porcentaje['porcentaje'] * $valor_minimo_autodata) / 100);
					$porcentajes_aplicados .=',tipo_venta:+'.$tipo_venta_porcentaje['porcentaje'];
				}else{
					$valor_final_minimo -= (($tipo_venta_porcentaje['porcentaje'] * $valor_minimo_autodata) / 100);
					$porcentajes_aplicados .=',tipo_venta:-'.$tipo_venta_porcentaje['porcentaje'];
				}
			}
			
		}


		//Si  el valor minimo de autodata es manor al de motorlider tomamos el de autodata
		if($valor_pretendido < $valor_final_minimo){
			$valor_final_minimo = $valor_pretendido;
		}

		
		return array('valor_final_minimo' => $valor_final_minimo,'valor_final_maximo' => $valor_final_maximo,'porcentajes_aplicados' => $porcentajes_aplicados);	

		/*echo '<pre>';
		var_dump($porcentajes_aplicados);
		echo '</pre>';
		die();*/

		//,valor_venal:-5.00,cantidad_duenios:+1,

}