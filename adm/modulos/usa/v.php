<?php

	if (!isset($sistema_iniciado)) exit();
	
	$id = intval($_GET['i']);

	$elemento = $db->query_first('select * from admin_usuarios where id = "'.$id.'";');

	if (!$elemento) {
		header('Location: ?m='.$modulo['prefijo'].'_l');
		exit();
	}

	
?>
<?php

	require_once('sistema_cabezal.php');

?>
<?php

	require_once('sistema_pre_contenido.php');

?>
<div id="contenido_cabezal">
    <h4 class="titulo"><?php echo $modulo['nombre']; ?></h4>
      <hr>
    <?php
        if ($_SESSION[$config['codigo_unico']]['login_permisos']['usa'] > 1) {
    ?>
      <button type="button" class="btn btn-small btn-primary" onclick="window.location.href='?m=<?php echo $modulo['prefijo']; ?>_m&i=<?php echo $id; ?>';">Modificar</button>
      <button type="button" class="btn btn-small btn_sep" onclick="window.location.href='?m=<?php echo $modulo['prefijo']; ?>_l';">Volver</button>
    <?php
        } else {
    ?>  
      <button type="button" class="btn btn-small" onclick="window.location.href='?m=<?php echo $modulo['prefijo']; ?>_l';">Volver</button>
    <?php
        }
    ?>  
      
      <hr class="nb">
</div>  
<div class="sep_titulo"></div>


<div class="row">
  <div class="span2 tr">Nombre</div>
  <div class="span4"><strong><?php echo_s($elemento['nombre']); ?></strong></div>
</div>
<div class="row">
  <div class="span2 tr">Email</div>
  <div class="span4"><strong><?php echo_s($elemento['email']); ?></strong></div>
</div>
<?php 

	$listado = $db->query('SELECT * FROM admin_usuarios_permisos where id_usuario = "'.$elemento['id'].'";');

	if ($db->num_rows > 0) {

?>
<div class="row">
  <div class="span2 tr">Permisos</div>
  <div class="span4">
  
<?php 

		while ($entrada = $db->fetch_array($listado)) {
	
			if ($entrada['permiso'] > 0) {
	
				$nombre = $sistema['modulos'][$entrada['modulo']]['nombre'];
			
				$permiso = $sistema['modulos'][$entrada['modulo']]['permisos'][$entrada['permiso']];		

?>  
  	<div class="row">
        <div class="span2"><strong><?php echo_s($nombre); ?></strong></div>
        <div class="span2"><strong><?php echo_s($permiso); ?></strong></div>
	</div>
<?php
	
			}
		}
	
?>
  </div>
<?php
	}
?>  
</div>
 
<?php

	require_once('sistema_post_contenido.php');

?>
 
