<?php

if (!isset($sistema_iniciado)) exit();

if ($_SESSION[$config['codigo_unico']]['login_permisos']['aud'] <= 1) {
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
<form id="form_datos" action="?m=<?php echo $modulo['prefijo'] . '_g'; ?>" method="post" class="form-horizontal">
  <div id="contenido_cabezal">
    <h4 class="titulo"><?php echo $modulo['nombre']; ?></h4>
    <hr>
    <button type="submit" class="btn btn-small btn-primary">Guardar</button>
    <button type="button" class="btn btn-small btn_sep" onclick="window.location.href='?m=<?php echo $modulo['prefijo'] . '_l'; ?>';">Cancelar</button>
    <hr class="nb">
  </div>
  <div class="sep_titulo"></div>

  <div class="control-group">
    <label class="control-label" for="nombre">Tipo</label>
    <div class="controls">
      <select name="tipo" id="tipo" class="select-search" onchange="get_tipo()">
        <option value="xx" selected disabled>Seleccione un Tipo</option>
        <option value="3">Ficha Técnica</option>
        <option value="4">Cantidad de dueños</option>
        <option value="5">Tipo Venta</option>
        <option value="6">Mismo veh&iacute;culo en Stock</option>
        <option value="7">Color del auto</option>
        <option value="8">Sufrió Choque Leve</option>
        <option value="9">Sufrió Choque Grave</option>
        <option value="10">Estado del Tapizado</option>
        <option value="11">Estado del Volante</option>
      </select>
    </div>
  </div>

  <div id="div_ficha" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Ficha Técnica</label>
      <div class="controls">
        <select name="ficha" id="ficha" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Si">Si</option>
          <option value="No">No</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_cantidad_duenios" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="cantidad_duenios">Cantidad de Dueños</label>
      <div class="controls">
        <select name="cantidad_duenios" id="cantidad_duenios" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="1">1</option>
          <option value="2">2</option>
          <option value="3">3</option>
          <option value="4">4</option>
          <option value="5">5</option>
          <option value="6">6</option>
          <option value="7">7</option>
          <option value="8">8</option>
          <option value="9">9</option>
          <option value="10">10</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_tipo_venta" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Tipo Venta</label>
      <div class="controls">
        <select name="tipo_venta" id="tipo_venta" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Venta">Venta Contado</option>
          <option value="Entrega">Entrega en forma de pago</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_stock" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Cantidad de Stock</label>
      <div class="controls">
        <select name="stock" id="stock" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="0">0</option>
          <option value="1">1</option>
          <option value="2">2</option>
          <option value="3">3</option>
          <option value="4">4</option>
          <option value="5">5 o más</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_color" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Color del auto</label>
      <div class="controls">
        <select name="color" id="color" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Comerciable">Comerciable</option>
          <option value="Poco comerciable">Poco comerciable</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_leve" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Sufrió Choque Leve</label>
      <div class="controls">
        <select name="leve" id="leve" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Si">Si</option>
          <option value="No">No</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_grave" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Sufrió Choque Grave</label>
      <div class="controls">
        <select name="grave" id="grave" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Si">Si</option>
          <option value="No">No</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_tapizado" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Estado del Tapizado</label>
      <div class="controls">
        <select name="tapizado" id="tapizado" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Excelente">Excelente</option>
          <option value="Muy bueno">Muy bueno</option>
          <option value="Bueno">Bueno</option>
          <option value="Malo">Malo</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_volante" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Estado del Volante</label>
      <div class="controls">
        <select name="volante" id="volante" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Excelente">Excelente</option>
          <option value="Muy bueno">Muy bueno</option>
          <option value="Bueno">Bueno</option>
          <option value="Malo">Malo</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_porcentaje" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Operador</label>
      <div class="controls">
        <select name="operador" id="operador" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="+">+</option>
          <option value="-">-</option>
        </select>
      </div>
    </div>
    <div class="control-group">
      <label class="control-label" for="nombre">Porcentaje</label>
      <div class="controls">
        <input type="number" id="porcentaje" name="porcentaje" step="0.01" min="0.1" max="100">
      </div>
    </div>
  </div>



</form>
<script>
  //$('.select-search').select2({});

  function get_tipo(){
    var tipo = $("#tipo").val();

    $("#div_ficha").hide();
    $("#div_cantidad_duenios").hide();
    $("#div_tipo_venta").hide();
    $("#div_stock").hide();
    $("#div_color").hide();
    $("#div_leve").hide();
    $("#div_grave").hide();
    $("#div_tapizado").hide();
    $("#div_volante").hide();
    $("#div_porcentaje").hide();

    if(tipo == 3) $("#div_ficha").show(); $("#div_porcentaje").show();
    if(tipo == 4) $("#div_cantidad_duenios").show(); $("#div_porcentaje").show();
    if(tipo == 5) $("#div_tipo_venta").show(); $("#div_porcentaje").show();
    if(tipo == 6) $("#div_stock").show(); $("#div_porcentaje").show();
    if(tipo == 7) $("#div_color").show(); $("#div_porcentaje").show();
    if(tipo == 8) $("#div_leve").show(); $("#div_porcentaje").show();
    if(tipo == 9) $("#div_grave").show(); $("#div_porcentaje").show();
    if(tipo == 10) $("#div_tapizado").show(); $("#div_porcentaje").show();
    if(tipo == 11) $("#div_volante").show(); $("#div_porcentaje").show();
    
  }

</script>
<?php

require_once('sistema_post_contenido.php');

?>