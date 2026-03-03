<?php

if (!isset($sistema_iniciado)) exit();

$id = intval($_GET['i']);

$elemento = $db->query_first('select * from agendas where id_agenda = "' . $id . '";');

if (!$elemento) {
  header('Location: ?m=' . $modulo['prefijo'] . '_l');
  exit();
}

$cotizacion = $db->query_first('select * from cotizaciones_generadas where id_cotizaciones_generadas = "' . $elemento['id_cotizacion'] . '";');

$sucursal = $db->query_first('select * from agenda_sucursal where id_sucursal = "' . $elemento['id_sucursal'] . '";');


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
<div class="sep_titulo"></div>

<div class="row">
  <div class="span2 tr">Sucursal</div>
  <div class="span4"><strong><?php echo_s($sucursal['nombre']); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Fecha de agenda</div>
  <div class="span4"><strong><?php echo_s(strftime('%d/%m/%Y', strtotime($elemento['fecha']))); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Hora de agenda</div>
  <div class="span4"><strong><?php echo_s($elemento['hora']); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Código</div>
  <div class="span4"><strong><?php echo_s($elemento['id_cotizacion']); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Fecha</div>
  <div class="span4"><strong><?php echo_s(strftime('%d/%m/%Y', strtotime($cotizacion['fecha']))); ?></strong></div>
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
  <?php if($cotizacion['familia'] == 'otro'): ?>
    <div class="span4"><strong><?php echo_s($cotizacion['auto']); ?></strong></div>
  <?php elseif(is_numeric($cotizacion['familia'])): ?>
    <div class="span4"><strong><?php echo_s($cotizacion['auto']); ?></strong></div>
  <?php else: ?>
    <div class="span4"><strong><?php echo_s($cotizacion['auto']); ?> <?php echo_s(strtoupper($cotizacion['familia'])); ?></strong></div>
  <?php endif; ?>
</div>

<div class="row">
  <div class="span2 tr">Año</div>
  <div class="span4"><strong><?php echo $cotizacion['anio']; ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Kilómetros</div>
  <div class="span4"><strong><?php echo number_format($cotizacion['kilometros'], 0, ',', '.'); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Ficha en service oficial</div>
  <div class="span4"><strong><?php echo $cotizacion['ficha_tecnica']; ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Cantidad de Dueños</div>
  <div class="span4"><strong><?php echo $cotizacion['duenios']; ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Tipo de Venta</div>
  <?php if($cotizacion['tipo_venta'] == 'Venta'): ?>
    <div class="span4"><strong>Venta Contado</strong></div>
  <?php else: ?>
    <div class="span4"><strong>Entrega como forma de pago</strong></div>
  <?php endif; ?>
</div>

<div class="row">
  <div class="span2 tr">Valor Pretendido</div>
  <div class="span4"><strong><?php echo_s('U$S ' . number_format($cotizacion['precio_pretendido'], 0, ',', '.')); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Valor Mínimo Motorlider</div>
  <div class="span4"><strong><?php echo_s('U$S ' . number_format($cotizacion['valor_minimo_autodata'], 0, ',', '.')); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Valor Máximo Motorlider</div>
  <div class="span4"><strong><?php echo_s('U$S ' . number_format($cotizacion['valor_maximo_autodata'], 0, ',', '.')); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Valor Promedio Motorlider</div>
  <div class="span4"><strong><?php echo_s('U$S ' . number_format($cotizacion['valor_promedio_autodata'], 0, ',', '.')); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Valor Mínimo de Mercado</div>
  <div class="span4"><strong><?php echo_s('U$S ' . number_format($cotizacion['valor_minimo'], 0, ',', '.')); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Valor Máximo de Mercado</div>
  <div class="span4"><strong><?php echo_s('U$S ' . number_format($cotizacion['valor_maximo'], 0, ',', '.')); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Valor Promedio de Mercado</div>
  <div class="span4"><strong><?php echo_s('U$S ' . number_format($cotizacion['valor_promedio'], 0, ',', '.')); ?></strong></div>
</div>

<?php

require_once('sistema_post_contenido.php');

?>