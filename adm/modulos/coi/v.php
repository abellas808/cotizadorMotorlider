<?php

if (!isset($sistema_iniciado)) exit();

$id = intval($_GET['i']);

$elemento = $db->query_first('select * from cotizaciones_internas where id_cotizaciones_internas = "' . $id . '";');

if (!$elemento) {
  header('Location: ?m=' . $modulo['prefijo'] . '_l');
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
  if ($_SESSION[$config['codigo_unico']]['login_permisos']['res'] > 1) {
  ?>
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

<?php 

$html_decode = html_entity_decode($elemento['respuesta']);
$json = json_decode($html_decode);

?>

<div class="sep_titulo"></div>

<div class="row">
  <div class="span2 tr">Código</div>
  <div class="span4"><strong><?php echo_s($elemento['id_cotizaciones_internas']); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Fecha</div>
  <div class="span4"><strong><?php echo_s(strftime('%d/%m/%Y', strtotime($elemento['fecha']))); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Nombre</div>
  <div class="span4"><strong><?php echo_s($elemento['nombre']); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Email</div>
  <div class="span4"><strong><?php echo_s($elemento['email']); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Teléfono</div>
  <div class="span4"><strong><?php echo_s($elemento['telefono']); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Vehículo</div>
  <div class="span4"><strong><?php echo_s($json->auto); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Año</div>
  <div class="span4"><strong><?php echo_s($json->anio); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Kilómetros</div>
  <div class="span4"><strong><?php echo_s(number_format($json->km, 0, ',', '.')); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Ficha en service oficial</div>
  <div class="span4"><strong><?php echo_s($json->ficha); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Cantidad de Dueños</div>
  <div class="span4"><strong><?php echo_s($json->duenios); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Tipo de Venta</div>
  <?php if($json->venta == 'Venta'): ?>
    <div class="span4"><strong>Venta Contado</strong></div>
  <?php else: ?>
    <div class="span4"><strong>Entrega como forma de pago</strong></div>
  <?php endif; ?>
</div>

<div class="row">
  <div class="span2 tr">Color Automóvil</div>
  <div class="span4"><strong><?php echo_s($json->color); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Sufrió Choque Leve</div>
  <div class="span4"><strong><?php echo_s($json->choquel); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Sufrió Choque Grave</div>
  <div class="span4"><strong><?php echo_s($json->choqueg); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Estado del Tapizado</div>
  <div class="span4"><strong><?php echo_s($json->tapizado); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Estado del Volante</div>
  <div class="span4"><strong><?php echo_s($json->volante); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Departamento de empadronamiento del vehículo</div>
  <div class="span4"><strong><?php echo_s($json->empadronamiento); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Servicio</div>
  <div class="span4"><strong><?php echo_s($json->servicio); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Correa de Distribución</div>
  <div class="span4"><strong><?php echo_s($json->correa); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Batería</div>
  <div class="span4"><strong><?php echo_s($json->bateria); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Piezas para Chapista</div>
  <div class="span4"><strong><?php echo_s($json->piezas); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Neumáticos para cambiar</div>
  <div class="span4"><strong><?php echo_s($json->neumaticos); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Tazas o Llantas Para Pintar</div>
  <div class="span4"><strong><?php echo_s($json->tazasllantas); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Cambiar parabrisas</div>
  <div class="span4"><strong><?php echo_s($json->parabrisas); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Faros para cambiar</div>
  <div class="span4"><strong><?php echo_s($json->faros); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Aire Acondicionado</div>
  <div class="span4"><strong><?php echo_s($json->aire); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Sensor de Estacionamiento</div>
  <div class="span4"><strong><?php echo_s($json->sensor); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Cámara de Reversa</div>
  <div class="span4"><strong><?php echo_s($json->camara); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Radio</div>
  <div class="span4"><strong><?php echo_s($json->radio); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Alarma</div>
  <div class="span4"><strong><?php echo_s($json->alarma); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Vidrios Eléctricos</div>
  <div class="span4"><strong><?php echo_s($json->vidrios); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Espejos Eléctricos</div>
  <div class="span4"><strong><?php echo_s($json->espejos); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Dos Juegos Llaves</div>
  <div class="span4"><strong><?php echo_s($json->dosllaves); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Limpieza de Tapizado</div>
  <div class="span4"><strong><?php echo_s($json->limpieza); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Valor Pretendido</div>
  <div class="span4"><strong><?php echo_s('U$S '.number_format($json->vpretendido, 0, ',', '.')); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Valor Definitivo</div>
  <div class="span4"><strong><?php echo_s('U$S '.number_format($elemento['valor_definitivo'], 0, ',', '.')); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Promedio Motorlider</div>
  <div class="span4"><strong><?php echo_s('U$S '.number_format($json->promedio, 0, ',', '.')); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Valor Mínimo de Mercado</div>
  <div class="span4"><strong><?php echo_s('U$S '.number_format($json->vminimo, 0, ',', '.')); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Valor Máximo de Mercado</div>
  <div class="span4"><strong><?php echo_s('U$S '.number_format($json->vmaximo, 0, ',', '.')); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Valor Promedio de Mercado</div>
  <div class="span4"><strong><?php echo_s('U$S '.number_format($json->vpromedio, 0, ',', '.')); ?></strong></div>
</div>

<?php

require_once('sistema_post_contenido.php');

?>