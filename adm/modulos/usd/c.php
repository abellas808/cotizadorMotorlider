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
        <option value="1">Departamento de empadronamiento del vehículo</option>
        <option value="2">Servicio</option>
        <option value="3">Correa de Distribución</option>
        <option value="4">Batería</option>
        <option value="5">Piezas para Chapista </option>
        <option value="6">Neumáticos para cambiar</option>
        <option value="7">Tazas o Llantas Para Pintar</option>
        <option value="8">Cambiar parabrisas</option>
        <option value="9">Faros para cambiar</option>
        <option value="10">Aire Acondicionado</option>
        <option value="11">Sensor de Estacionamiento</option>
        <option value="12">Cámara de Reversa</option>
        <option value="13">Radio</option>
        <option value="14">Alarma</option>
        <option value="15">Vidrios Eléctricos</option>
        <option value="16">Espejos Eléctricos</option>
        <option value="17">Dos Juegos Llaves</option>
        <option value="18">Limpieza de Tapizado</option>
      </select>
    </div>
  </div>

  <div id="div_empadronamiento" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Empadronamiento del vehículo</label>
      <div class="controls">
        <select name="empadronamiento" id="empadronamiento" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Montevideo">Montevideo</option>
          <option value="Canelones">Canelones</option>
          <option value="Otro departamento">Otro departamento</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_servicio" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Servicio</label>
      <div class="controls">
        <select name="servicio" id="servicio" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Ok">Ok</option>
          <option value="Para Cambiar">Para Cambiar</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_correa" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Correa</label>
      <div class="controls">
        <select name="correa" id="correa" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Ok">Ok</option>
          <option value="Para Cambiar">Para Cambiar</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_bateria" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Batería</label>
      <div class="controls">
        <select name="bateria" id="bateria" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Ok">Ok</option>
          <option value="Para Cambiar">Para Cambiar</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_piezas" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Piezas</label>
      <div class="controls">
        <select name="piezas" id="piezas" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="0">0</option>
          <option value="1">1</option>
          <option value="2">2</option>
          <option value="3">3</option>
          <option value="4">4</option>
          <option value="5">5</option>
          <option value="6">6</option>
          <option value="7">7</option>
          <option value="8">8</option>
          <option value="9">9 o más</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_neumaticos" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Neumáticos</label>
      <div class="controls">
        <select name="neumaticos" id="neumaticos" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="0">0</option>
          <option value="1">1</option>
          <option value="2">2</option>
          <option value="3">3</option>
          <option value="4">4</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_tazas_llantas" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Tazas o Llantas</label>
      <div class="controls">
        <select name="tazas_llantas" id="tazas_llantas" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="0">0</option>
          <option value="1">1</option>
          <option value="2">2</option>
          <option value="3">3</option>
          <option value="4">4</option>
          <option value="5">5</option>
          <option value="6">6</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_parabrisas" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Parabrisas</label>
      <div class="controls">
        <select name="parabrisas" id="parabrisas" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Si">Si</option>
          <option value="No">No</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_faros" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Faros</label>
      <div class="controls">
        <select name="faros" id="faros" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="0">0</option>
          <option value="1">1</option>
          <option value="2">2</option>
          <option value="3">3</option>
          <option value="4">4</option>
          <option value="5">5</option>
          <option value="6">6</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_aire" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Aire Acondicionado</label>
      <div class="controls">
        <select name="aire" id="aire" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Funciona">Funciona</option>
          <option value="No Funciona">No Funciona</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_sensor" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Sensor</label>
      <div class="controls">
        <select name="sensor" id="sensor" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Funciona">Funciona</option>
          <option value="No Funciona">No Funciona</option>
          <option value="No Corresponde">No Corresponde</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_reserva" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Reserva</label>
      <div class="controls">
        <select name="reserva" id="reserva" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Funciona">Funciona</option>
          <option value="No Funciona">No Funciona</option>
          <option value="No Corresponde">No Corresponde</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_radio" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Radio</label>
      <div class="controls">
        <select name="radio" id="radio" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Funciona">Funciona</option>
          <option value="No Funciona">No Funciona</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_alarma" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Alarma</label>
      <div class="controls">
        <select name="alarma" id="alarma" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Funciona">Funciona</option>
          <option value="No Funciona">No Funciona</option>
          <option value="No Corresponde">No Corresponde</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_vidrios" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Vidrios</label>
      <div class="controls">
        <select name="vidrios" id="vidrios" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Funciona">Funciona</option>
          <option value="No Funciona">No Funciona</option>
          <option value="No Corresponde">No Corresponde</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_espejos" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Espejos</label>
      <div class="controls">
        <select name="espejos" id="espejos" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Funciona">Funciona</option>
          <option value="No Funciona">No Funciona</option>
          <option value="No Corresponde">No Corresponde</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_llaves" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Llaves</label>
      <div class="controls">
        <select name="llaves" id="llaves" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Si">Si</option>
          <option value="No">No</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_tapizado" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">Limpieza</label>
      <div class="controls">
        <select name="tapizado" id="tapizado" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Si">Si</option>
          <option value="No">No</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_usd" style="display: none;">
    <div class="control-group">
      <label class="control-label" for="nombre">USD</label>
      <div class="controls">
        <input type="number" id="usd" name="usd">
      </div>
    </div>
  </div>



</form>
<script>
  //$('.select-search').select2({});

  function get_tipo(){
    var tipo = $("#tipo").val();

    $("#div_empadronamiento").hide();
    $("#div_servicio").hide();
    $("#div_correa").hide();
    $("#div_bateria").hide();
    $("#div_piezas").hide();
    $("#div_neumaticos").hide();
    $("#div_tazas_llantas").hide();
    $("#div_parabrisas").hide();
    $("#div_faros").hide();
    $("#div_aire").hide();
    $("#div_sensor").hide();
    $("#div_reserva").hide();
    $("#div_radio").hide();
    $("#div_alarma").hide();
    $("#div_vidrios").hide();
    $("#div_espejos").hide();
    $("#div_llaves").hide();
    $("#div_tapizado").hide();
    $("#div_usd").hide();

    if(tipo == 1) $("#div_empadronamiento").show(); $("#div_usd").show();
    if(tipo == 2) $("#div_servicio").show(); $("#div_usd").show();
    if(tipo == 3) $("#div_correa").show(); $("#div_usd").show();
    if(tipo == 4) $("#div_bateria").show(); $("#div_usd").show();
    if(tipo == 5) $("#div_piezas").show(); $("#div_usd").show();
    if(tipo == 6) $("#div_neumaticos").show(); $("#div_usd").show();
    if(tipo == 7) $("#div_tazas_llantas").show(); $("#div_usd").show();
    if(tipo == 8) $("#div_parabrisas").show(); $("#div_usd").show();
    if(tipo == 9) $("#div_faros").show(); $("#div_usd").show();
    if(tipo == 10) $("#div_aire").show(); $("#div_usd").show();
    if(tipo == 11) $("#div_sensor").show(); $("#div_usd").show();
    if(tipo == 12) $("#div_reserva").show(); $("#div_usd").show();
    if(tipo == 13) $("#div_radio").show(); $("#div_usd").show();
    if(tipo == 14) $("#div_alarma").show(); $("#div_usd").show();
    if(tipo == 15) $("#div_vidrios").show(); $("#div_usd").show();
    if(tipo == 16) $("#div_espejos").show(); $("#div_usd").show();
    if(tipo == 17) $("#div_llaves").show(); $("#div_usd").show();
    if(tipo == 18) $("#div_tapizado").show(); $("#div_usd").show();
    
  }

</script>
<?php

require_once('sistema_post_contenido.php');

?>