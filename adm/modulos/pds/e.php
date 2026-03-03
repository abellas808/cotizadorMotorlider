<?php

if (!isset($sistema_iniciado)) exit();

if ($_SESSION[$config['codigo_unico']]['login_permisos']['pds'] > 1) {

	$eliminados = $_POST['e_sel'];
	for ($i = 0; $i < count($eliminados); $i++) {

		$id = intval($eliminados[$i]);

		if ($id != 0) {

			$db->query('delete FROM ponderador_valor_stock where id_ponderador_valor_stock = '.$id.';');
		}
	}
}

header('Location: index.php?m=' . $modulo['prefijo'] . '_l');
