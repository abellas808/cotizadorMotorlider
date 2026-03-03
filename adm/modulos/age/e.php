<?php

    if (!isset($sistema_iniciado)) exit();

	if ($_SESSION[$config['codigo_unico']]['login_permisos'][$modulo['prefijo']] > 1) {

	$eliminados = $_POST['e_sel'];
	for ($i = 0; $i < count($eliminados); $i++){
	
		$id = intval($eliminados[$i]);
	
		if ($id != 0) {
			
			$db->query('delete FROM agendas where id_agenda = "'.$id.'";');
			
		}
	
	}
	
	}
	
	header('Location: index.php?m='.$modulo['prefijo'].'_l');
	
?>