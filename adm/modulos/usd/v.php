<?php

	if (!isset($sistema_iniciado)) exit();
	
	$id = intval($_GET['i']);

	$elemento = $db->query_first('select * from variables_usd where id = "' . $id . '";');
  
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

    if($elemento['tipo'] == 1){
      echo 'Departamento de empadronamiento del vehículo';
    }elseif($elemento['tipo'] == 2){
      echo 'Servicio';
    }elseif($elemento['tipo'] == 3){
      echo 'Correa de Distribución';
    }elseif($elemento['tipo'] == 4){
      echo 'Batería';
    }elseif($elemento['tipo'] == 5){
      echo 'Piezas para Chapista';
    }elseif($elemento['tipo'] == 6){
      echo 'Neumáticos para cambiar';
    }elseif($elemento['tipo'] == 7){
      echo 'Tazas o Llantas Para Pintar';
    }elseif($elemento['tipo'] == 8){
      echo 'Cambiar parabrisas';
    }elseif($elemento['tipo'] == 9){
      echo 'Faros para cambiar';
    }elseif($elemento['tipo'] == 10){
      echo 'Aire Acondicionado';
    }elseif($elemento['tipo'] == 11){
      echo 'Sensor de Estacionamiento';
    }elseif($elemento['tipo'] == 12){
      echo 'Cámara de Reversa';
    }elseif($elemento['tipo'] == 13){
      echo 'Radio';
    }elseif($elemento['tipo'] == 14){
      echo 'Alarma';
    }elseif($elemento['tipo'] == 15){
      echo 'Vidrios Eléctricos';
    }elseif($elemento['tipo'] == 16){
      echo 'Espejos Eléctricos';
    }elseif($elemento['tipo'] == 17){
      echo 'Dos Juegos Llaves';
    }elseif($elemento['tipo'] == 18){
      echo 'Limpieza de Tapizado';
    }

    ?>
    </strong></div>
</div>

<?php if($elemento['tipo'] == 1) : ?>
<div class="row">
  <div class="span2 tr">Empadronamiento</div>
  <div class="span4"><strong><?php echo_s($elemento['empadronamiento']); ?></strong></div>
</div>
<?php elseif($elemento['tipo'] == 2) : ?>
<div class="row">
  <div class="span2 tr">Servicio</div>
  <div class="span4"><strong><?php echo_s($elemento['servicio']); ?></strong></div>
</div>
<?php elseif($elemento['tipo'] == 3) : ?>
<div class="row">
  <div class="span2 tr">Correa</div>
  <div class="span4"><strong><?php echo_s($elemento['correa']); ?></strong></div>
</div>
<?php elseif($elemento['tipo'] == 4) : ?>
<div class="row">
  <div class="span2 tr">Batería</div>
  <div class="span4"><strong><?php echo_s($elemento['bateria']); ?></strong></div>
</div>
<?php elseif($elemento['tipo'] == 5) : ?>
<div class="row">
  <div class="span2 tr">Piezas</div>
  <div class="span4"><strong><?php $elemento['piezas_chapista'] == '9' ? echo_s($elemento['piezas_chapista']." o más") : echo_s($elemento['piezas_chapista']); ?></strong></div>
</div>
<?php elseif($elemento['tipo'] == 6) : ?>
<div class="row">
  <div class="span2 tr">Neumaticos</div>
  <div class="span4"><strong><?php echo_s($elemento['neumaticos']); ?></strong></div>
</div>
<?php elseif($elemento['tipo'] == 7) : ?>
<div class="row">
  <div class="span2 tr">Tazas o Llantas</div>
  <div class="span4"><strong><?php echo_s($elemento['tazas_llantas']); ?></strong></div>
</div>
<?php elseif($elemento['tipo'] == 8) : ?>
<div class="row">
  <div class="span2 tr">Parabrisas</div>
  <div class="span4"><strong><?php echo_s($elemento['parabrisas']); ?></strong></div>
</div>
<?php elseif($elemento['tipo'] == 9) : ?>
<div class="row">
  <div class="span2 tr">Faros</div>
  <div class="span4"><strong><?php echo_s($elemento['faros']); ?></strong></div>
</div>
<?php elseif($elemento['tipo'] == 10) : ?>
<div class="row">
  <div class="span2 tr">Aire</div>
  <div class="span4"><strong><?php echo_s($elemento['aire_acondicionado']); ?></strong></div>
</div>
<?php elseif($elemento['tipo'] == 11) : ?>
<div class="row">
  <div class="span2 tr">Sensor</div>
  <div class="span4"><strong><?php echo_s($elemento['sensor_estacionamiento']); ?></strong></div>
</div>
<?php elseif($elemento['tipo'] == 12) : ?>
<div class="row">
  <div class="span2 tr">Cámara</div>
  <div class="span4"><strong><?php echo_s($elemento['camara_reversa']); ?></strong></div>
</div>
<?php elseif($elemento['tipo'] == 13) : ?>
<div class="row">
  <div class="span2 tr">Radio</div>
  <div class="span4"><strong><?php echo_s($elemento['radio']); ?></strong></div>
</div>
<?php elseif($elemento['tipo'] == 14) : ?>
<div class="row">
  <div class="span2 tr">Alarma</div>
  <div class="span4"><strong><?php echo_s($elemento['alarma']); ?></strong></div>
</div>
<?php elseif($elemento['tipo'] == 15) : ?>
<div class="row">
  <div class="span2 tr">Vidrios</div>
  <div class="span4"><strong><?php echo_s($elemento['vidrios']); ?></strong></div>
</div>
<?php elseif($elemento['tipo'] == 16) : ?>
<div class="row">
  <div class="span2 tr">Espejos</div>
  <div class="span4"><strong><?php echo_s($elemento['espejos']); ?></strong></div>
</div>
<?php elseif($elemento['tipo'] == 17) : ?>
<div class="row">
  <div class="span2 tr">Llaves</div>
  <div class="span4"><strong><?php echo_s($elemento['llaves']); ?></strong></div>
</div>
<?php elseif($elemento['tipo'] == 18) : ?>
<div class="row">
  <div class="span2 tr">Limpieza</div>
  <div class="span4"><strong><?php echo_s($elemento['tapizado']); ?></strong></div>
</div>
<?php endif; ?>  

<div class="row">
  <div class="span2 tr">USD</div>
  <div class="span4"><strong><?php echo_s($elemento['usd']); ?></strong></div>
</div>

<?php

	require_once('sistema_post_contenido.php');

?>
