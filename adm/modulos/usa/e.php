<?php

	if (!isset($sistema_iniciado)) exit();

	if ($_SESSION[$config['codigo_unico']]['login_permisos']['usa'] > 1) {

	$eliminados = $_POST['e_sel'];
	for ($i = 0; $i < count($eliminados); $i++){
	
		$id = intval($eliminados[$i]);
	
		if ($id != 0) {
			
			$db->query('delete FROM admin_usuarios where id = "'.$id.'";');
			$db->query('delete FROM admin_usuarios_permisos where id_usuario = "'.$id.'";');
			
		}
	
	}
	
	}
	
	header('Location: index.php?m='.$modulo['prefijo'].'_l');
	
?>