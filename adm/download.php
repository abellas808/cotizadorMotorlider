<?php

require_once("config/config.inc.php");
require_once("includes/database.php");

$prefijo = '';
$id = $_GET['i'];
$modulo = $_GET['t'];
//if (!isset($sistema_iniciado)) exit();

foreach ($config['modulos'] as $valor) {

	if ($valor == $modulo) {
		$prefijo = $valor;
	}

}


if ($prefijo != '') {

	if ($prefijo == 'pro' && $elemento = $db->query_first('select * from productos_adjuntos where id_producto_adjunto = "' . intval($id) . '"')) {
		$archivo = $config['imagenes_url'] . 'adjuntos/' . $prefijo . '_' . $elemento['codigo_producto'] . '_' . $elemento['id_producto_adjunto'];
		$nombre_archivo = $elemento['titulo'];
	}else if ($prefijo == 'sub' && $elemento = $db->query_first('select * from sub_categorias_productos_adjuntos where id_sub_categorias_productos_adjuntos = "' . intval($id) . '"')) {
		$archivo = $config['imagenes_url'] . 'adjuntos/' . $prefijo . '_' . $elemento['sub_categoria'] . '_' . $elemento['id_sub_categorias_productos_adjuntos'];
		$nombre_archivo = $elemento['titulo'];
	}else{
		exit;
	}



	header('Content-Description: File Transfer');
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename=' . basename($nombre_archivo));
	header('Content-Transfer-Encoding: binary');
	header('Expires: 0');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public');
	header('Content-Length: ' . filesize($archivo));
	ob_clean();
	flush();
	readfile($archivo);
}


exit;
