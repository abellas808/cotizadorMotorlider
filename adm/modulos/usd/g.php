<?php

if (!isset($sistema_iniciado)) exit();

if ($_SESSION[$config['codigo_unico']]['login_permisos']['aud'] <= 1) {
	header('Location: ?m=' . $modulo['prefijo'] . '_l');
	exit();
}

$tipo = intval($_POST['tipo']);
$empadronamiento = isset($_POST['empadronamiento']) ? $_POST['empadronamiento'] : '';
$servicio = isset($_POST['servicio']) ? $_POST['servicio'] : '';
$correa = isset($_POST['correa']) ? $_POST['correa'] : '';
$bateria = isset($_POST['bateria']) ? $_POST['bateria'] : '';
$piezas = intval($_POST['piezas']);
$neumaticos = intval($_POST['neumaticos']);
$tazas_llantas = intval($_POST['tazas_llantas']);
$parabrisas = isset($_POST['parabrisas']) ? $_POST['parabrisas'] : '';
$faros = intval($_POST['faros']);
$aire = isset($_POST['aire']) ? $_POST['aire'] : '';
$sensor = isset($_POST['sensor']) ? $_POST['sensor'] : '';
$reserva = isset($_POST['reserva']) ? $_POST['reserva'] : '';
$radio = isset($_POST['radio']) ? $_POST['radio'] : '';
$alarma = isset($_POST['alarma']) ? $_POST['alarma'] : '';
$vidrios = isset($_POST['vidrios']) ? $_POST['vidrios'] : '';
$espejos = isset($_POST['espejos']) ? $_POST['espejos'] : '';
$llaves = isset($_POST['llaves']) ? $_POST['llaves'] : '';
$tapizado = isset($_POST['tapizado']) ? $_POST['tapizado'] : '';
$usd = $_POST['usd'];

// ************************************
// MODIFICAR
// ************************************
if (isset($_POST['id'])) {

	$id = intval($_POST['id']);
	if ($id == 0) exit();

	$db->query_update('variables_usd', array(
		'empadronamiento' => $empadronamiento,
		'servicio' => $servicio,
		'correa' => $correa,
		'bateria' => $bateria,
		'piezas_chapista' => $piezas,
		'neumaticos' => $neumaticos,
		'tazas_llantas' => $tazas_llantas,
		'parabrisas' => $parabrisas,
		'faros' => $faros,
		'aire_acondicionado' => $aire,
		'sensor_estacionamiento' => $sensor,
		'camara_reversa' => $reserva,
		'radio' => $radio,
		'alarma' => $alarma,
		'vidrios' => $vidrios,
		'espejos' => $espejos,
		'llaves' => $llaves,
		'tapizado' => $tapizado,
		'usd' => $usd,
		'tipo' => $tipo
	), 'id = "' . $id . '"');
	// ************************************
	// CREAR
	// ************************************
} else {

	$id = $db->query_insert('variables_usd', array(
		'empadronamiento' => $empadronamiento,
		'servicio' => $servicio,
		'correa' => $correa,
		'bateria' => $bateria,
		'piezas_chapista' => $piezas,
		'neumaticos' => $neumaticos,
		'tazas_llantas' => $tazas_llantas,
		'parabrisas' => $parabrisas,
		'faros' => $faros,
		'aire_acondicionado' => $aire,
		'sensor_estacionamiento' => $sensor,
		'camara_reversa' => $reserva,
		'radio' => $radio,
		'alarma' => $alarma,
		'vidrios' => $vidrios,
		'espejos' => $espejos,
		'llaves' => $llaves,
		'tapizado' => $tapizado,
		'usd' => $usd,
		'tipo' => $tipo
	));

	// *************************************************************************************************************************************


}

header('Location: ?m=' . $modulo['prefijo'] . '_v&i=' . $id);
