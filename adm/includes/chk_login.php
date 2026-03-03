<?php

   	if(isset($_SESSION[$config['codigo_unico']]['login_usuario_id'])) {
		
		if (($_SESSION[$config['codigo_unico']]['login_super'] == 1) || ($usuario = $db->query_first('select * from admin_usuarios where id = "'.$db->escape($_SESSION[$config['codigo_unico']]['login_usuario_id']).'" ;'))) {

			$usuario_permisos = array();

			$listado = $db->query('SELECT * FROM admin_usuarios_permisos where id_usuario = "'.$usuario['id'].'";');

			while ($entrada = $db->fetch_array($listado)) {

				$usuario_permisos[$entrada['modulo']] = $entrada['permiso'];
				
			}

			$_SESSION[$config['codigo_unico']]['login_permisos'] = $usuario_permisos;
			
		} else {
			
			session_unset;
			header( 'Location: ?m=l' );
			exit();
		}
		
	} else {
		session_unset;
		header( 'Location: ?m=l' );
		exit();
	}
?>