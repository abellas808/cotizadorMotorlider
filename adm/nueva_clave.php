<?php

session_start();

session_unset();


if ($_POST['cmd'] == 'ec') {

	if (!isset($_POST['c'])) {
		die();
	}

	$codigo = $_POST['c'];

	if (!($usuario = $db->query_first('select * from admin_usuarios where codigo_oc = "'.$db->escape($codigo).'" and fecha_oc > NOW()'))) {
		die();
	}
		
	$msg_login = 'ec';

	$clave_salt = uniqid('', true);
	$clave = hash('sha256', $_POST['campo1'].$clave_salt);

	$db->query_update('admin_usuarios', array('clave' => $clave, 'clave_salt' => $clave_salt, 'codigo_oc' => '', 'fecha_oc' => '0000-00-00 00:00:00'), 'id = "'.$usuario['id'].'"');

	
} else {
	
	if (!isset($_GET['c'])) {
		die();
	}
	
	$codigo = $_GET['c'];

	if (!($usuario = $db->query_first('select * from admin_usuarios where codigo_oc = "'.$db->escape($codigo).'" and fecha_oc > NOW()'))) {
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
<script type="text/javascript">

function ec() {
	if ($('campo1').val() != '') {
		$('#fl').attr('action', '?m=l&p=ec');
		$('#fl').submit();
	} else {
		alert('No ingreso su email.');
	}
}

</script>
    
</head>
<body>
<div id="contenedor">
<?php
	if ($msg_login == 'err') {
?>
<div class="box">
    <div class="frmbody">
	<img src="img/logo.svg" width="200">
    </div>
    <div class="frmfooter_e">
    	<br /><br />El link no es correcto o ha caducado.
    </div>
</div>    
<?php
	} else {
?>
<form id="fl" action="?m=nc" method="post" class="box">
<input name="cmd" type="hidden" id="cmd" value="ec" />
<input name="c" type="hidden" id="c" value="<?php echo_s($_GET['c']); ?>" />
<div class="frmbody">
<img src="img/logo.svg" width="200">
	  <label>Nueva clave</label>
	  <input name="campo1" type="password" id="campo1" tabindex="1" />
	  <label>Repetir</label>
	  <input name="campo2" type="password" id="campo2" tabindex="2" />
      </div>
	<div class="frmfooter">
	  <input type="submit" class="btn btn-small" value="Guardar" tabindex="3">
<?php
	if ($msg_login == 'ec') {
		echo '<br /><br />Su clave fue guardada correctamente.';
	}
?>
      <div class="acceso"><a href="?m=l">Ingresar</a></div>
	</div>
</form>
<?php
	} 
?>
</div>
</body>
</html>
