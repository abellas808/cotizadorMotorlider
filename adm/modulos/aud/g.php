<?php

if (!isset($sistema_iniciado)) exit();

if ($_SESSION[$config['codigo_unico']]['login_permisos']['aud'] <= 1) {
	header('Location: ?m=' . $modulo['prefijo'] . '_l');
	exit();
}



$tipo = intval($_POST['tipo']);
$ficha = isset($_POST['ficha']) ? $_POST['ficha'] : '';
$cantidad_duenios = intval($_POST['cantidad_duenios']);
$tipo_venta = isset($_POST['tipo_venta']) ? $_POST['tipo_venta'] : '';
$stock = intval($_POST['stock']);
$color = isset($_POST['color']) ? $_POST['color'] : '';
$leve = isset($_POST['leve']) ? $_POST['leve'] : '';
$grave = isset($_POST['grave']) ? $_POST['grave'] : '';
$tapizado = isset($_POST['tapizado']) ? $_POST['tapizado'] : '';
$volante = isset($_POST['volante']) ? $_POST['volante'] : '';

$operador = $_POST['operador'];
$porcentaje = $_POST['porcentaje'];


// ************************************
// MODIFICAR
// ************************************
if (isset($_POST['id'])) {

	$id = intval($_POST['id']);
	if ($id == 0) exit();

	$db->query_update('variables', array(
		'ficha_oficial' => $ficha,
		'cantidad_duenios' => $cantidad_duenios,
		'tipo_venta' => $tipo_venta,
		'stock' => $stock,
		'color' => $color,
		'choque_leve' => $leve,
		'choque_grave' => $grave,
		'tapizado' => $tapizado,
		'volante' => $volante,
		'porcentaje' => $porcentaje,
		'operador' => $operador,
		'tipo' => $tipo
	), 'id = "' . $id . '"');
	// ************************************
	// CREAR
	// ************************************
} else {



	$id = $db->query_insert('variables', array(
		'ficha_oficial' => $ficha,
		'cantidad_duenios' => $cantidad_duenios,
		'tipo_venta' => $tipo_venta,
		'stock' => $stock,
		'color' => $color,
		'choque_leve' => $leve,
		'choque_grave' => $grave,
		'tapizado' => $tapizado,
		'volante' => $volante,
		'porcentaje' => $porcentaje,
		'operador' => $operador,
		'tipo' => $tipo
	));

	// *************************************************************************************************************************************


}

header('Location: ?m=' . $modulo['prefijo'] . '_v&i=' . $id);
