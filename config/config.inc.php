<?php
	$bd = new MySQLi("localhost", "marcos2022_usr_api", "_eT4AjJ79~tX]*h)J5", "marcos2022_api");
	$bd->query("SET NAMES 'utf8'");

	$config = new stdClass();

	$config->correoAdministrador = "actotaldev@gmail.com";
	$config->maxIntentos = 10;
	$config->entorno = 't';
	$config->table_schema = 'apiml';
	$config->wsInfopelUsuario = 'motorlider';
	$config->wsInfopelContrasena = 'Pnuesma-Uveuo-Iaxeo';

	if($_SERVER['HTTP_HOST'] == 'localhost') $config->urlBase = "http://localhost/apiml/";
	else $config->urlBase = "https://carplay.uy/";

	function httpPost($url, $parametros){
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($parametros));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$respuesta = curl_exec($curl);
		curl_close($curl);
		return $respuesta;
	}

	function httpGet($url){
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$respuesta = curl_exec($curl);
		curl_close($curl);
		return $respuesta;
	}

	function codigo($lon){
		$chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		$code = "";
		for($x=0; $x<$lon; $x++){
			$r = rand(0, strlen($chars) - 1);
			$code .= substr($chars, $r, 1);
		}
		return $code;
	}

	function codigoExtendido($lon){
		$chars = "aAbBcCdDeEfFgGhHiIjJkKlLmMnNoOpPqQrRsStTuUvVwWxXyYzZ0123456789";
		$code = "";
		for($x=0; $x<$lon; $x++){
			$r = rand(0, strlen($chars) - 1);
			$code .= substr($chars, $r, 1);
		}
		return $code;
	}

	function validarDigito($ci){
		$a = 0;
		$i;
		if(strlen($ci) <= 6){
			for($i=strlen($ci); $i<7; $i++){
				$ci = "0".$ci;
			}
		}
		for($i=0; $i<7; $i++){
			$x = intval(substr("2987634", $i, 1));
			$y = intval(substr($ci, $i, 1));
			$a += ($x * $y) % 10;
		}
		if($a % 10 == 0){
			return 0;
		}else{
			return 10 - $a % 10;
		}
	}

	function validarCedula($ci){
		if(empty($ci)) return false;
		$dig = intval(substr($ci, -1));
		$ci = substr($ci, 0, strlen($ci) - 1);
		return ($dig == validarDigito($ci));
	}

	function formatoCedula($ci){
		if(strlen($ci)==1){return $ci;}
		return number_format(substr($ci, 0, -1), 0, ' ', '.').'-'.substr($ci, -1, 1);
	}

	function formatoCelular($str){
		return '<a class="enlace text-info" href="tel:+598'.substr($str, 1).'">'.substr($str, 0, 3).' '.substr($str, 3, 3).' '.substr($str, 6).'</a>';
	}

	function formatoTelefono($str){
		return '<a class="enlace text-info" href="tel:+598'.$str.'">'.substr($str, 0, 4).' '.substr($str, 4).'</a>';
	}

	function formatoCorreo($str){
		return '<a class="enlace text-info" href="mailto:'.$str.'">'.$str.'</a>';
	}

	function separarDe3En3($str){
		return number_format($str, 0, ' ', ' ');
	}

	function formatoActivo($valor){
		return '<i class="fa fa-toggle-'.(($valor) ? 'on' : 'off').' morado"></i>';
	}

	function formatoNumero($numero, $decimales){
		return number_format($numero, $decimales, ',', '.');
	}

	function formatoFecha($fecha){
		if(empty($fecha)) return '';
		$f = explode('-', substr($fecha, 0, 10));
		return ((intval($f[2]) < 10)?('0'.intval($f[2])):$f[2]).'/'.((intval($f[1]) < 10)?('0'.intval($f[1])):$f[1]).'/'.$f[0];
	}

	function formatoFechaBD($fecha){
		if(empty($fecha)) return '';
		$f = explode('/', $fecha);
		return $f[2].'-'.(($f[1] < 10)?('0'.$f[1]):$f[1]).'-'.(($f[0] < 10)?('0'.$f[0]):$f[0]);
	}

	function celularNuevo($id){
		return substr('09000000', 0, 9-strlen($id)).$id;
	}

	function correoNuevo($str){
		return $str.'@correo.com';
	}

	function selector($str){
		return strtolower(str_replace(' ', '', quitarCaracteresEspeciales($str)));
	}

	function nombreArchivo($str){
		return strtolower(str_replace('/', '-', str_replace(' ', '_', quitarCaracteresEspeciales(trim($str)))));
	}

	function codigoDistribuidor($id){
		if(strlen($id) > 3) return 'D'.$id;
		return substr('D0000', 0, 5-strlen($id)).$id;
	}

	function siguienteId($bd, $table_schema, $tabla){
		$inf = $bd->query("SELECT AUTO_INCREMENT AS id FROM information_schema.TABLES WHERE table_schema = '".$table_schema."' AND TABLE_NAME = '".$tabla."'")->fetch_object();
		return is_null($inf) ? 1 : ($inf->id);
	}

	function quitarCaracteresEspeciales($str){
		return str_replace('Á', 'a', str_replace('É', 'e', str_replace('Í', 'i', str_replace('Ó', 'o', str_replace('Ú', 'u', str_replace('Ñ', 'n', str_replace('á', 'a', str_replace('é', 'e', str_replace('í', 'i', str_replace('ó', 'o', str_replace('ú', 'u', str_replace('ñ', 'n', $str))))))))))));
	}
?>