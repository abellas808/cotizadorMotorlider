<?php
// ***************************************************************************************************
// Chequeo que no se llame directamente
// ***************************************************************************************************

	if (!isset($sistema_iniciado)) exit();
	$id = $_SESSION[$config['codigo_unico']]['login_usuario_id'];

	$elemento = $db->query_first('select * from admin_usuarios where id = "'.$id.'";');

	if (!$elemento || ($_SESSION[$config['codigo_unico']]['login_permisos']['wel'] < 1)) {
		header('Location: ?');
		exit();
	}

?>

<?php require_once('sistema_cabezal.php'); ?>

<?php require_once('sistema_pre_contenido.php'); ?>

<div id="contenido_cabezal">
    <h4 class="titulo"><?php echo $modulo['nombre']; ?>: <?php echo_s($elemento['nombre']); ?></h4>
    <hr class="nb">
</div>  

<div class="sep_titulo"></div>

<?php require_once('sistema_post_contenido.php'); ?>