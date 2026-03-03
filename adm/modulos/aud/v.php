<?php

	if (!isset($sistema_iniciado)) exit();
	
	$id = intval($_GET['i']);

	$elemento = $db->query_first('select * from variables where id = "' . $id . '";');
  
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
        if ($_SESSION[$config['codigo_unico']]['login_permisos']['aud'] > 1) {
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
  <div class="span2 tr">Tipo</div>
  <div class="span4"><strong>
    <?php 

    if($elemento['tipo'] == 3){
      echo 'Ficha Oficial';
    }elseif($elemento['tipo'] == 4){
      echo 'Cantidad de Dueños';
    }elseif($elemento['tipo'] == 5){
      echo 'Tipo Venta';
    }elseif($elemento['tipo'] == 6){
      echo 'Mismo vehículo en Stock';
    }elseif($elemento['tipo'] == 7){
      echo 'Color del auto';
    }elseif($elemento['tipo'] == 8){
      echo 'Sufrió Choque Leve';
    }elseif($elemento['tipo'] == 9){
      echo 'Sufrió Choque Grave';
    }elseif($elemento['tipo'] == 10){
      echo 'Estado del Tapizado';
    }elseif($elemento['tipo'] == 11){
      echo 'Estado del Volante';
    }

    ?>
    </strong></div>
</div>

<?php if($elemento['tipo'] == 3) : ?>
<div class="row">
  <div class="span2 tr">Ficha Oficia</div>
  <div class="span4"><strong><?php echo_s($elemento['ficha_oficial']); ?></strong></div>
</div>
<?php elseif($elemento['tipo'] == 4) : ?>
<div class="row">
  <div class="span2 tr">Cantidad de Dueños</div>
  <div class="span4"><strong><?php echo_s($elemento['cantidad_duenios']); ?></strong></div>
</div>
<?php elseif($elemento['tipo'] == 5) : ?>
<div class="row">
  <div class="span2 tr">Tipo Venta</div>
  <div class="span4"><strong><?php echo_s($elemento['tipo_venta']); ?></strong></div>
</div>
<?php elseif($elemento['tipo'] == 6) : ?>
<div class="row">
  <div class="span2 tr">Cantidad de Stock</div>
  <div class="span4"><strong><?php $elemento['stock'] == '5' ? echo_s($elemento['stock']." o más") : echo_s($elemento['stock']); ?></strong></div>
</div>
<?php elseif($elemento['tipo'] == 7) : ?>
<div class="row">
  <div class="span2 tr">Color</div>
  <div class="span4"><strong><?php echo_s($elemento['color']); ?></strong></div>
</div>
<?php elseif($elemento['tipo'] == 8) : ?>
<div class="row">
  <div class="span2 tr">Choque</div>
  <div class="span4"><strong><?php echo_s($elemento['choque_leve']); ?></strong></div>
</div>
<?php elseif($elemento['tipo'] == 9) : ?>
<div class="row">
  <div class="span2 tr">Choque</div>
  <div class="span4"><strong><?php echo_s($elemento['choque_grave']); ?></strong></div>
</div>
<?php elseif($elemento['tipo'] == 10) : ?>
<div class="row">
  <div class="span2 tr">Estado</div>
  <div class="span4"><strong><?php echo_s($elemento['tapizado']); ?></strong></div>
</div>
<?php elseif($elemento['tipo'] == 11) : ?>
<div class="row">
  <div class="span2 tr">Estado</div>
  <div class="span4"><strong><?php echo_s($elemento['volante']); ?></strong></div>
</div>
<?php endif; ?>  

<div class="row">
  <div class="span2 tr">Operador</div>
  <div class="span4"><strong><?php echo_s($elemento['operador']); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Porcentaje</div>
  <div class="span4"><strong><?php echo_s($elemento['porcentaje']); ?></strong></div>
</div>

<?php

	require_once('sistema_post_contenido.php');

?>
