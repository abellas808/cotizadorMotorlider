<?php

	if (!isset($sistema_iniciado)) exit();

	if ($_SESSION[$config['codigo_unico']]['login_permisos']['usa'] <= 1) {
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
    <form id="form_datos" action="?m=<?php echo $modulo['prefijo'].'_g'; ?>" method="post" class="form-horizontal" onsubmit="return validar();">
<div id="contenido_cabezal">
    
    <h4 class="titulo"><?php echo $modulo['nombre']; ?></h4>
      <hr>
      <button type="submit" class="btn btn-small btn-primary">Guardar</button>
      <button type="button" class="btn btn-small btn_sep" onclick="window.location.href='?m=<?php echo $modulo['prefijo'].'_l'; ?>';">Cancelar</button>
      <hr class="nb">
</div>  
<div class="sep_titulo"></div>

<div class="control-group">
    <label class="control-label" for="nombre">Nombre</label>
    <div class="controls">
      <input type="text" id="nombre" name="nombre" placeholder="Nombre">
    </div>
</div>
<div class="control-group">
    <label class="control-label" for="email">Email</label>
    <div class="controls">
      <input type="text" id="email" name="email" placeholder="Email">
    </div>
</div>
<div class="control-group">
    <label class="control-label" for="clave">Clave</label>
    <div class="controls">
      <input type="text" id="clave" name="clave" placeholder="Clave">
    </div>
</div>


<div class="control-group">
    <label class="control-label" for="permisos">Permisos</label>
    <div class="controls">
<?php 

	foreach ($sistema['modulos'] as $modulo_key => $modulo_value) {

		$nombre = $modulo_value['nombre'];

?>  <div class="row row_m">
		<label class="control-label" for="permisos"><?php echo_s($nombre); ?></label>
		<div class="span2">
            <select name="permiso_<?php echo $modulo_key; ?>">
                <option value="0">Sin acceso</option>
<?php
		foreach ($sistema['modulos'][$modulo_key]['permisos'] as $key => $value) {
?>
    	    	<option value="<?php echo $key; ?>"><?php echo_s($value); ?></option>
<?php
		}
?>            
	        </select>
        </div>
	</div>
<?php
	
	}
	
?>
        <div style="clear:both"></div>
    </div>
</div> 
    

</form>
<script>

function validar() {
	
	if ($('#nombre').val() == '') {
		alert('Debe ingresar un nombre');
		$('#nombre').focus();
		return false;
	}
	if ($('#email').val() == '') {
		alert('Debe ingresar un email');
		$('#email').focus();
		return false;
	}
	if ($('#clave').val() == '') {
		alert('Debe ingresar una clave');
		$('#clave').focus();
		return false;
	}
	return true;	
	
}

</script>
<?php

	require_once('sistema_post_contenido.php');

?>