<?php

	if (!isset($sistema_iniciado)) exit();
	
	$id = $_SESSION[$config['codigo_unico']]['login_usuario_id'];

	$elemento = $db->query_first('select * from admin_usuarios where id = "'.$id.'";');

	if (!$elemento || ($_SESSION[$config['codigo_unico']]['login_permisos']['miu'] < 1)) {
		header('Location: ?');
		exit();
	}
	
?>
<?php

	require_once('sistema_cabezal.php');

?>
<?php

	require_once('sistema_pre_contenido.php');

?>
    <form id="form_datos" action="?m=<?php echo $modulo['prefijo'].'_g'; ?>" method="post" class="form-horizontal" onsubmit="return validar();">
<div id="contenido_cabezal">
    
    <h4 class="titulo"><?php echo $modulo['nombre']; ?></h4>
      <hr>
      
      <button type="submit" class="btn btn-small btn-primary">Guardar</button>
      
      <hr class="nb">
</div>  
<div class="sep_titulo"></div>
<?php
	if ($_SESSION[$config['codigo_unico']]['mensaje_cambio_clave'] == 1) {
?>
<div id="div_msg" style="margin-bottom:20px">
La clave fue modificada con éxito.
</div>
<script>
	setTimeout(function() { $('#div_msg').fadeOut(); }, 5000);
</script>
<?php
		unset($_SESSION[$config['codigo_unico']]['mensaje_cambio_clave']);
	}
?>
<div class="control-group">
    <label class="control-label" for="nombre">Nombre</label>
    <div class="controls">
      <?php echo_s($elemento['nombre']); ?>
    </div>
</div>
<div class="control-group">
    <label class="control-label" for="email">Email</label>
    <div class="controls">
      <?php echo_s($elemento['email']); ?>
    </div>
</div>
<div class="control-group">
    <label class="control-label" for="clave">Nueva clave</label>
    <div class="controls">
      <input type="text" id="clave" name="clave" placeholder="Clave">
    </div>
</div>
<div class="control-group">
    <label class="control-label" for="rclave">Repetir clave</label>
    <div class="controls">
      <input type="text" id="rclave" name="rclave" placeholder="Repetir clave">
    </div>
</div>
  
</form>
<script>

function validar() {
	
	if ($('#clave').val() != $('#rclave').val())  {
		alert('Las claves no coinciden');
		$('#clave').focus();
		return false;
	}
	return true;	
	
}

</script>
<?php

	require_once('sistema_post_contenido.php');

?>
