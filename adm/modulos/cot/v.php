<?php

if (!isset($sistema_iniciado)) exit();

$id = intval($_GET['i']);

$elemento = $db->query_first('select * from cotizaciones_generadas where id_cotizaciones_generadas = "' . $id . '";');

if (!$elemento) {
  header('Location: ?m=' . $modulo['prefijo'] . '_l');
  exit();
}

?>
<?php require_once('sistema_cabezal.php'); ?>
<?php require_once('sistema_pre_contenido.php'); ?>

<style>
  .modal-cotizacion-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 9998;
  }

  .modal-cotizacion {
    display: none;
    position: fixed;
    top: 8%;
    left: 50%;
    transform: translateX(-50%);
    width: 640px;
    max-width: 92%;
    background: #fff;
    border: 1px solid #d9d9d9;
    border-radius: 8px;
    box-shadow: 0 8px 30px rgba(0,0,0,.18);
    z-index: 9999;
    padding: 18px;
  }

  .modal-cotizacion h4 {
    margin: 0 0 14px 0;
  }

  .modal-cotizacion .fila {
    margin-bottom: 12px;
  }

  .modal-cotizacion input[type="text"],
  .modal-cotizacion textarea {
    width: 100%;
    box-sizing: border-box;
  }

  .modal-cotizacion textarea {
    min-height: 160px;
    resize: vertical;
  }

  .modal-cotizacion .acciones {
    margin-top: 14px;
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
  }

  .estado-pendiente {
    color: #c09853;
  }

  .estado-finalizada {
    color: #468847;
  }

  #envio_feedback {
    font-weight: bold;
  }
</style>

<div id="contenido_cabezal">
  <h4 class="titulo"><?php echo $modulo['nombre']; ?></h4>
  <hr>
  <?php if ($_SESSION[$config['codigo_unico']]['login_permisos']['res'] > 1) { ?>
    <button type="button" class="btn btn-small btn_sep" onclick="window.location.href='?m=<?php echo $modulo['prefijo']; ?>_l';">Volver</button>
  <?php } else { ?>
    <button type="button" class="btn btn-small" onclick="window.location.href='?m=<?php echo $modulo['prefijo']; ?>_l';">Volver</button>
  <?php } ?>
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
  <div class="span2 tr">Estado</div>
  <div class="span4">
    <strong class="<?php echo ($elemento['estado'] == 'PENDIENTE') ? 'estado-pendiente' : 'estado-finalizada'; ?>">
      <?php echo_s($elemento['estado']); ?>
    </strong>
  </div>
</div>

<?php if ($elemento['estado'] == 'PENDIENTE'): ?>
<div class="row">
  <div class="span2 tr">Motivo</div>
  <div class="span6">
    <strong class="estado-pendiente">
      <?php echo_s($elemento['detalle_estado']); ?>
    </strong>
  </div>
</div>
<?php endif; ?>

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
    <div class="span4"><strong><?php echo_s($elemento['auto']); ?></strong></div>
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

<?php if ($elemento['estado'] == 'PENDIENTE'): ?>
<div class="row" style="margin-top:15px;">
  <div class="span8">
    <button type="button" class="btn btn-success" onclick="abrirEnviarCotizacion();">
      Enviar cotización
    </button>
  </div>
</div>
<?php endif; ?>

<div class="modal-cotizacion-overlay" id="modal_enviar_overlay" onclick="cerrarEnviarCotizacion();"></div>

<div class="modal-cotizacion" id="modal_enviar">
  <h4>Enviar cotización</h4>

  <div class="fila">
    <label for="email_envio"><strong>Email del cliente</strong></label>
    <input type="text" id="email_envio" value="<?php echo htmlspecialchars($elemento['email']); ?>">
  </div>

  <div class="fila">
    <label for="mensaje_envio"><strong>Mensaje a enviar</strong></label>
    <textarea id="mensaje_envio"><?php echo htmlspecialchars(strip_tags((string)$elemento['msg'])); ?></textarea>
  </div>

  <div class="acciones">
    <button type="button" class="btn btn-success" id="btn_confirmar_envio" onclick="enviarCotizacionManual();">
      Enviar
    </button>
    <button type="button" class="btn" onclick="cerrarEnviarCotizacion();">
      Cancelar
    </button>
    <span id="envio_feedback"></span>
  </div>
</div>

<script>
function abrirEnviarCotizacion() {
  document.getElementById('modal_enviar_overlay').style.display = 'block';
  document.getElementById('modal_enviar').style.display = 'block';
  document.getElementById('envio_feedback').innerHTML = '';
}

function cerrarEnviarCotizacion() {
  document.getElementById('modal_enviar_overlay').style.display = 'none';
  document.getElementById('modal_enviar').style.display = 'none';
}

function enviarCotizacionManual() {
  var email = document.getElementById('email_envio').value || '';
  var mensaje = document.getElementById('mensaje_envio').value || '';
  var btn = document.getElementById('btn_confirmar_envio');
  var feedback = document.getElementById('envio_feedback');

  if (!email.trim()) {
    feedback.style.color = '#b94a48';
    feedback.innerHTML = 'Ingresá un email válido.';
    return;
  }

  btn.disabled = true;
  feedback.style.color = '#666';
  feedback.innerHTML = 'Enviando...';

  var xhr = new XMLHttpRequest();
  xhr.open('POST', '/adm/modulos/cot/ajax_enviar_cotizacion.php', true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');

  xhr.onreadystatechange = function() {
    if (xhr.readyState !== 4) return;

    btn.disabled = false;

    if (xhr.status !== 200) {
      feedback.style.color = '#b94a48';
      feedback.innerHTML = 'Error HTTP ' + xhr.status + '<br><small>' + xhr.responseText + '</small>';
      return;
    }

    try {
      var res = JSON.parse(xhr.responseText);

      if (res.ok) {
        feedback.style.color = '#468847';
        feedback.innerHTML = 'Cotización enviada correctamente.';
        setTimeout(function() {
          window.location.reload();
        }, 700);
      } else {
        feedback.style.color = '#b94a48';
        feedback.innerHTML = res.mensaje ? res.mensaje : 'No se pudo enviar.';
      }
    } catch (e) {
      feedback.style.color = '#b94a48';
      feedback.innerHTML = 'Respuesta inválida del servidor:<br><small>' + xhr.responseText + '</small>';
    }
  };

  xhr.send(
    'id=<?php echo intval($id); ?>' +
    '&email=' + encodeURIComponent(email) +
    '&mensaje=' + encodeURIComponent(mensaje)
  );
}
</script>

<?php require_once('sistema_post_contenido.php'); ?>