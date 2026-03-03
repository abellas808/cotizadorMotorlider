<?php

	if (!isset($sistema_iniciado)) exit();

	if ($_SESSION[$config['codigo_unico']]['login_permisos']['usa'] <= 1) {
		header('Location: ?m='.$modulo['prefijo'].'_l');
		exit();
	}
	
// ************************************
// MODIFICAR
// ************************************
if (isset($_POST['id'])) {

	$id = intval($_POST['id']);
	if ($id == 0) exit();

	if (($_POST['nombre'] == '') || ($_POST['email'] == '')) exit();
	
	$db->query_update('admin_usuarios', array('nombre' => $_POST['nombre'], 'email' => $_POST['email']), 'id = "'.$id.'"');

	if ($_POST['clave'] != '') {
		
		$clave_salt = uniqid('', true);
		$clave = hash('sha256', $_POST['clave'].$clave_salt);

		$db->query_update('admin_usuarios', array('clave' => $clave, 'clave_salt' => $clave_salt), 'id = "'.$id.'"');
		
	}

// *************************************************************************************************************************************

	foreach ($sistema['modulos'] as $modulo_key => $modulo_value) {
	
		$permiso = intval($_POST['permiso_'.$modulo_key]);
		if ($permiso == 0) {
			
			$db->query('delete FROM admin_usuarios_permisos where id_usuario = "'.$id.'" and modulo = "'.$modulo_key.'";');
			
		} else {
			
			$db->query('INSERT INTO admin_usuarios_permisos (id_usuario, modulo, permiso) VALUES ("'.$id.'", "'.$modulo_key.'", "'.$permiso.'") ON DUPLICATE KEY UPDATE permiso="'.$permiso.'"');
			
		}
	
	}

// *************************************************************************************************************************************
	

// ************************************
// CREAR
// ************************************
} else {
	
	if (($_POST['nombre'] == '') || ($_POST['email'] == '') || ($_POST['clave'] == '')) exit();
	
	$clave_salt = uniqid('', true);
	$clave = hash('sha256', $_POST['clave'].$clave_salt);


	$id = $db->query_insert('admin_usuarios', array('nombre' => $_POST['nombre'], 'email' => $_POST['email'], 'clave' => $clave, 'clave_salt' => $clave_salt));

// *************************************************************************************************************************************

	foreach ($sistema['modulos'] as $modulo_key => $modulo_value) {
	
		$permiso = intval($_POST['permiso_'.$modulo_key]);
		if ($permiso != 0) {
			
			$db->query('INSERT INTO admin_usuarios_permisos (id_usuario, modulo, permiso) VALUES ("'.$id.'", "'.$modulo_key.'", "'.$permiso.'")');
			
		}
	
	}

// *************************************************************************************************************************************

	
}

header('Location: ?m='.$modulo['prefijo'].'_v&i='.$id);	
	
?>
