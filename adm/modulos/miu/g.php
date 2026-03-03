<?php

	if (!isset($sistema_iniciado)) exit();

	if ($_SESSION[$config['codigo_unico']]['login_permisos']['miu'] < 1) {
		header('Location: ?');
		exit();
	}
	
	if (($_POST['clave'] == $_POST['rclave']) && ($_POST['clave'] != '')) {
	
		$clave_salt = uniqid('', true);
		$clave = hash('sha256', $_POST['clave'].$clave_salt);

		$id = $_SESSION[$config['codigo_unico']]['login_usuario_id'];

		$db->query_update('admin_usuarios', array('clave' => $clave, 'clave_salt' => $clave_salt), 'id = "'.$id.'"');
		
		$_SESSION[$config['codigo_unico']]['mensaje_cambio_clave'] = 1;
		
	}

header('Location: ?m='.$modulo['prefijo'].'_m');	
	
?>
