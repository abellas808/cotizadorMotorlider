<?php

if (!isset($sistema_iniciado)) exit();

if ($_SESSION[$config['codigo_unico']]['login_permisos']['aud'] > 1) {

	$eliminados = $_POST['e_sel'];
	for ($i = 0; $i < count($eliminados); $i++) {

		$id = intval($eliminados[$i]);

		if ($id != 0) {

			$db->query('delete FROM variables_usd where id = "'.$id.'";');
		}
	}
}

header('Location: index.php?m=' . $modulo['prefijo'] . '_l');
