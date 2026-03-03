<?php

if (!isset($sistema_iniciado)) exit();

$id = intval($_GET['i']);

$elemento = $db->query_first('select * from cotizaciones_generadas where id_cotizaciones_generadas = "' . $id . '";');

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
<div class="sep_titulo"></div>

<div class="row">
  <div class="span2 tr">Código</div>
  <div class="span4"><strong><?php echo_s($elemento['id_cotizaciones_generadas']); ?></strong></div>
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
  <?php if($elemento['familia'] == 'otro'): ?>
    <div class="span4"><strong><?php echo_s($elemento['auto']); ?></strong></div>
  <?php elseif(is_numeric($elemento['familia'])): ?>
    <div class="span4"><strong><?php echo_s($elemento['auto']); ?> </strong></div>
  <?php else: ?>
    <div class="span4"><strong><?php echo_s($elemento['auto']); ?> <?php echo_s(strtoupper($elemento['familia'])); ?></strong></div>
  <?php endif; ?>
</div>

<div class="row">
  <div class="span2 tr">Año</div>
  <div class="span4"><strong><?php echo_s($elemento['anio']); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Kilómetros</div>
  <div class="span4"><strong><?php echo_s(number_format($elemento['kilometros'], 0, ',', '.')); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Ficha en service oficial</div>
  <div class="span4"><strong><?php echo_s($elemento['ficha_tecnica']); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Cantidad de Dueños</div>
  <div class="span4"><strong><?php echo_s($elemento['duenios']); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Tipo de Venta</div>
  <?php if($elemento['tipo_venta'] == 'Venta'): ?>
    <div class="span4"><strong>Venta Contado</strong></div>
  <?php else: ?>
    <div class="span4"><strong>Entrega como forma de pago</strong></div>
  <?php endif; ?>
</div>

<div class="row">
  <div class="span2 tr">Valor Pretendido</div>
  <div class="span4"><strong><?php echo_s('U$S ' . number_format($elemento['precio_pretendido'], 0, ',', '.')); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Valor Mínimo Motorlider</div>
  <div class="span4"><strong><?php echo_s('U$S ' . number_format($elemento['valor_minimo_autodata'], 0, ',', '.')); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Valor Máximo Motorlider</div>
  <div class="span4"><strong><?php echo_s('U$S ' . number_format($elemento['valor_maximo_autodata'], 0, ',', '.')); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Valor Promedio Motorlider</div>
  <div class="span4"><strong><?php echo_s('U$S ' . number_format($elemento['valor_promedio_autodata'], 0, ',', '.')); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Valor Mínimo de Mercado</div>
  <div class="span4"><strong><?php echo_s('U$S ' . number_format($elemento['valor_minimo'], 0, ',', '.')); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Valor Máximo de Mercado</div>
  <div class="span4"><strong><?php echo_s('U$S ' . number_format($elemento['valor_maximo'], 0, ',', '.')); ?></strong></div>
</div>

<div class="row">
  <div class="span2 tr">Valor Promedio de Mercado</div>
  <div class="span4"><strong><?php echo_s('U$S ' . number_format($elemento['valor_promedio'], 0, ',', '.')); ?></strong></div>
</div>

<?php

require_once('sistema_post_contenido.php');

?>