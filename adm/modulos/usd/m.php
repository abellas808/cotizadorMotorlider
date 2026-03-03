<?php

if (!isset($sistema_iniciado)) exit();

$id = intval($_GET['i']);

  $elemento = $db->query_first('select * from variables_usd where id = "' . $id . '";');

if (!$elemento || ($_SESSION[$config['codigo_unico']]['login_permisos']['aud'] <= 1)) {
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
<form id="form_datos" action="?m=<?php echo $modulo['prefijo'] . '_g'; ?>" method="post" class="form-horizontal" enctype="multipart/form-data">
    <div id="contenido_cabezal">
      <input type="hidden" id="id" name="id" value="<?php echo $id; ?>">
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
        <option value="1" <?php echo $elemento['tipo'] == 1 ? 'selected' : '' ?>>Departamento de empadronamiento del vehículo</option>
        <option value="2" <?php echo $elemento['tipo'] == 2 ? 'selected' : '' ?>>Servicio</option>
        <option value="3" <?php echo $elemento['tipo'] == 3 ? 'selected' : '' ?>>Correa de Distribución</option>
        <option value="4" <?php echo $elemento['tipo'] == 4 ? 'selected' : '' ?>>Batería</option>
        <option value="5" <?php echo $elemento['tipo'] == 5 ? 'selected' : '' ?>>Piezas para Chapista</option>
        <option value="6" <?php echo $elemento['tipo'] == 6 ? 'selected' : '' ?>>Neumáticos para cambiar</option>
        <option value="7" <?php echo $elemento['tipo'] == 7 ? 'selected' : '' ?>>Tazas o Llantas Para Pintar</option>
        <option value="8" <?php echo $elemento['tipo'] == 8 ? 'selected' : '' ?>>Cambiar parabrisas</option>
        <option value="9" <?php echo $elemento['tipo'] == 9 ? 'selected' : '' ?>>Faros para cambiar</option>
        <option value="10" <?php echo $elemento['tipo'] == 10 ? 'selected' : '' ?>>Aire Acondicionado </option>
        <option value="11" <?php echo $elemento['tipo'] == 11 ? 'selected' : '' ?>>Sensor de Estacionamiento</option>
        <option value="12" <?php echo $elemento['tipo'] == 12 ? 'selected' : '' ?>>Cámara de Reversa</option>
        <option value="13" <?php echo $elemento['tipo'] == 13 ? 'selected' : '' ?>>Radio</option>
        <option value="14" <?php echo $elemento['tipo'] == 14 ? 'selected' : '' ?>>Alarma</option>
        <option value="15" <?php echo $elemento['tipo'] == 15 ? 'selected' : '' ?>>Vidrios Eléctricos</option>
        <option value="16" <?php echo $elemento['tipo'] == 16 ? 'selected' : '' ?>>Espejos Eléctricos</option>
        <option value="17" <?php echo $elemento['tipo'] == 17 ? 'selected' : '' ?>>Dos Juegos Llaves</option>
        <option value="18" <?php echo $elemento['tipo'] == 18 ? 'selected' : '' ?>>Limpieza de Tapizado</option>
      </select>
    </div>
  </div>

  <div id="div_empadronamiento" <?php echo $elemento['tipo'] == 1 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Empadronamiento del vehículo</label>
      <div class="controls">
        <select name="empadronamiento" id="empadronamiento" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Montevideo" <?php echo $elemento['tipo'] == 1 && $elemento['empadronamiento'] == 'Montevideo' ? 'selected' : '' ?>>Montevideo</option>
          <option value="Canelones" <?php echo $elemento['tipo'] == 1 && $elemento['empadronamiento'] == 'Canelones' ? 'selected' : '' ?>>Canelones</option>
          <option value="Otro departamento" <?php echo $elemento['tipo'] == 1 && $elemento['empadronamiento'] == 'Otro departamento' ? 'selected' : '' ?>>Otro departamento</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_servicio" <?php echo $elemento['tipo'] == 2 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Servicio</label>
      <div class="controls">
        <select name="servicio" id="servicio" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Ok" <?php echo $elemento['tipo'] == 2 && $elemento['servicio'] == 'Ok' ? 'selected' : '' ?>>Ok</option>
          <option value="Para Cambiar" <?php echo $elemento['tipo'] == 2 && $elemento['servicio'] == 'Para Cambiar' ? 'selected' : '' ?>>Para Cambiar</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_correa" <?php echo $elemento['tipo'] == 3 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Correa</label>
      <div class="controls">
        <select name="correa" id="correa" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Ok" <?php echo $elemento['tipo'] == 3 && $elemento['correa'] == 'Ok' ? 'selected' : '' ?>>Ok</option>
          <option value="Para Cambiar" <?php echo $elemento['tipo'] == 3 && $elemento['correa'] == 'Para Cambiar' ? 'selected' : '' ?>>Para Cambiar</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_bateria" <?php echo $elemento['tipo'] == 4 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Batería</label>
      <div class="controls">
        <select name="bateria" id="bateria" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Ok" <?php echo $elemento['tipo'] == 4 && $elemento['bateria'] == 'Ok' ? 'selected' : '' ?>>Ok</option>
          <option value="Para Cambiar" <?php echo $elemento['tipo'] == 4 && $elemento['bateria'] == 'Para Cambiar' ? 'selected' : '' ?>>Para Cambiar</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_piezas" <?php echo $elemento['tipo'] == 5 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Piezas</label>
      <div class="controls">
        <select name="piezas" id="piezas" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="0" <?php echo $elemento['tipo'] == 5 && $elemento['piezas_chapista'] == '0' ? 'selected' : '' ?>>0</option>
          <option value="1" <?php echo $elemento['tipo'] == 5 && $elemento['piezas_chapista'] == '1' ? 'selected' : '' ?>>1</option>
          <option value="2" <?php echo $elemento['tipo'] == 5 && $elemento['piezas_chapista'] == '2' ? 'selected' : '' ?>>2</option>
          <option value="3" <?php echo $elemento['tipo'] == 5 && $elemento['piezas_chapista'] == '3' ? 'selected' : '' ?>>3</option>
          <option value="4" <?php echo $elemento['tipo'] == 5 && $elemento['piezas_chapista'] == '4' ? 'selected' : '' ?>>4</option>
          <option value="5" <?php echo $elemento['tipo'] == 5 && $elemento['piezas_chapista'] == '5' ? 'selected' : '' ?>>5</option>
          <option value="6" <?php echo $elemento['tipo'] == 5 && $elemento['piezas_chapista'] == '6' ? 'selected' : '' ?>>6</option>
          <option value="7" <?php echo $elemento['tipo'] == 5 && $elemento['piezas_chapista'] == '7' ? 'selected' : '' ?>>7</option>
          <option value="8" <?php echo $elemento['tipo'] == 5 && $elemento['piezas_chapista'] == '8' ? 'selected' : '' ?>>8</option>
          <option value="9" <?php echo $elemento['tipo'] == 5 && $elemento['piezas_chapista'] == '9' ? 'selected' : '' ?>>9 o más</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_neumaticos" <?php echo $elemento['tipo'] == 6 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Neumáticos</label>
      <div class="controls">
        <select name="neumaticos" id="neumaticos" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="0" <?php echo $elemento['tipo'] == 6 && $elemento['neumaticos'] == '0' ? 'selected' : '' ?>>0</option>
          <option value="1" <?php echo $elemento['tipo'] == 6 && $elemento['neumaticos'] == '1' ? 'selected' : '' ?>>1</option>
          <option value="2" <?php echo $elemento['tipo'] == 6 && $elemento['neumaticos'] == '2' ? 'selected' : '' ?>>2</option>
          <option value="3" <?php echo $elemento['tipo'] == 6 && $elemento['neumaticos'] == '3' ? 'selected' : '' ?>>3</option>
          <option value="4" <?php echo $elemento['tipo'] == 6 && $elemento['neumaticos'] == '4' ? 'selected' : '' ?>>4</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_tazas_llantas" <?php echo $elemento['tipo'] == 7 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Tazas o Llantas</label>
      <div class="controls">
        <select name="tazas_llantas" id="tazas_llantas" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="0" <?php echo $elemento['tipo'] == 7 && $elemento['tazas_llantas'] == '0' ? 'selected' : '' ?>>0</option>
          <option value="1" <?php echo $elemento['tipo'] == 7 && $elemento['tazas_llantas'] == '1' ? 'selected' : '' ?>>1</option>
          <option value="2" <?php echo $elemento['tipo'] == 7 && $elemento['tazas_llantas'] == '2' ? 'selected' : '' ?>>2</option>
          <option value="3" <?php echo $elemento['tipo'] == 7 && $elemento['tazas_llantas'] == '3' ? 'selected' : '' ?>>3</option>
          <option value="4" <?php echo $elemento['tipo'] == 7 && $elemento['tazas_llantas'] == '4' ? 'selected' : '' ?>>4</option>
          <option value="5" <?php echo $elemento['tipo'] == 7 && $elemento['tazas_llantas'] == '5' ? 'selected' : '' ?>>5</option>
          <option value="6" <?php echo $elemento['tipo'] == 7 && $elemento['tazas_llantas'] == '6' ? 'selected' : '' ?>>6</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_parabrisas" <?php echo $elemento['tipo'] == 8 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Parabrisas</label>
      <div class="controls">
        <select name="parabrisas" id="parabrisas" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Si" <?php echo $elemento['tipo'] == 8 && $elemento['parabrisas'] == 'Si' ? 'selected' : '' ?>>Si</option>
          <option value="No" <?php echo $elemento['tipo'] == 8 && $elemento['parabrisas'] == 'No' ? 'selected' : '' ?>>No</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_faros" <?php echo $elemento['tipo'] == 9 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Faros</label>
      <div class="controls">
        <select name="faros" id="faros" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="0" <?php echo $elemento['tipo'] == 9 && $elemento['faros'] == '0' ? 'selected' : '' ?>>0</option>
          <option value="1" <?php echo $elemento['tipo'] == 9 && $elemento['faros'] == '1' ? 'selected' : '' ?>>1</option>
          <option value="2" <?php echo $elemento['tipo'] == 9 && $elemento['faros'] == '2' ? 'selected' : '' ?>>2</option>
          <option value="3" <?php echo $elemento['tipo'] == 9 && $elemento['faros'] == '3' ? 'selected' : '' ?>>3</option>
          <option value="4" <?php echo $elemento['tipo'] == 9 && $elemento['faros'] == '4' ? 'selected' : '' ?>>4</option>
          <option value="5" <?php echo $elemento['tipo'] == 9 && $elemento['faros'] == '5' ? 'selected' : '' ?>>5</option>
          <option value="6" <?php echo $elemento['tipo'] == 9 && $elemento['faros'] == '6' ? 'selected' : '' ?>>6</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_aire" <?php echo $elemento['tipo'] == 10 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Aire</label>
      <div class="controls">
        <select name="aire" id="aire" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Funciona" <?php echo $elemento['tipo'] == 10 && $elemento['aire_acondicionado'] == 'Funciona' ? 'selected' : '' ?>>Funciona</option>
          <option value="No Funciona" <?php echo $elemento['tipo'] == 10 && $elemento['aire_acondicionado'] == 'No Funciona' ? 'selected' : '' ?>>No Funciona</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_sensor" <?php echo $elemento['tipo'] == 11 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Sensor</label>
      <div class="controls">
        <select name="sensor" id="sensor" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Funciona" <?php echo $elemento['tipo'] == 11 && $elemento['sensor_estacionamiento'] == 'Funciona' ? 'selected' : '' ?>>Funciona</option>
          <option value="No Funciona" <?php echo $elemento['tipo'] == 11 && $elemento['sensor_estacionamiento'] == 'No Funciona' ? 'selected' : '' ?>>No Funciona</option>
          <option value="No Corresponde" <?php echo $elemento['tipo'] == 11 && $elemento['sensor_estacionamiento'] == 'No Corresponde' ? 'selected' : '' ?>>No Corresponde</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_reserva" <?php echo $elemento['tipo'] == 12 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Cámara</label>
      <div class="controls">
        <select name="reserva" id="reserva" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Funciona" <?php echo $elemento['tipo'] == 12 && $elemento['camara_reversa'] == 'Funciona' ? 'selected' : '' ?>>Funciona</option>
          <option value="No Funciona" <?php echo $elemento['tipo'] == 12 && $elemento['camara_reversa'] == 'No Funciona' ? 'selected' : '' ?>>No Funciona</option>
          <option value="No Corresponde" <?php echo $elemento['tipo'] == 12 && $elemento['camara_reversa'] == 'No Corresponde' ? 'selected' : '' ?>>No Corresponde</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_radio" <?php echo $elemento['tipo'] == 13 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Radio</label>
      <div class="controls">
        <select name="radio" id="radio" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Funciona" <?php echo $elemento['tipo'] == 13 && $elemento['radio'] == 'Funciona' ? 'selected' : '' ?>>Funciona</option>
          <option value="No Funciona" <?php echo $elemento['tipo'] == 13 && $elemento['radio'] == 'No Funciona' ? 'selected' : '' ?>>No Funciona</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_alarma" <?php echo $elemento['tipo'] == 14 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Alarma</label>
      <div class="controls">
        <select name="alarma" id="alarma" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Funciona" <?php echo $elemento['tipo'] == 14 && $elemento['alarma'] == 'Funciona' ? 'selected' : '' ?>>Funciona</option>
          <option value="No Funciona" <?php echo $elemento['tipo'] == 14 && $elemento['alarma'] == 'No Funciona' ? 'selected' : '' ?>>No Funciona</option>
          <option value="No Corresponde" <?php echo $elemento['tipo'] == 14 && $elemento['alarma'] == 'No Corresponde' ? 'selected' : '' ?>>No Corresponde</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_vidrios" <?php echo $elemento['tipo'] == 15 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Vidrios</label>
      <div class="controls">
        <select name="vidrios" id="vidrios" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Funciona" <?php echo $elemento['tipo'] == 15 && $elemento['vidrios'] == 'Funciona' ? 'selected' : '' ?>>Funciona</option>
          <option value="No Funciona" <?php echo $elemento['tipo'] == 15 && $elemento['vidrios'] == 'No Funciona' ? 'selected' : '' ?>>No Funciona</option>
          <option value="No Corresponde" <?php echo $elemento['tipo'] == 15 && $elemento['vidrios'] == 'No Corresponde' ? 'selected' : '' ?>>No Corresponde</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_espejos" <?php echo $elemento['tipo'] == 16 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Espejos</label>
      <div class="controls">
        <select name="espejos" id="espejos" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Funciona" <?php echo $elemento['tipo'] == 16 && $elemento['espejos'] == 'Funciona' ? 'selected' : '' ?>>Funciona</option>
          <option value="No Funciona" <?php echo $elemento['tipo'] == 16 && $elemento['espejos'] == 'No Funciona' ? 'selected' : '' ?>>No Funciona</option>
          <option value="No Corresponde" <?php echo $elemento['tipo'] == 16 && $elemento['espejos'] == 'No Corresponde' ? 'selected' : '' ?>>No Corresponde</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_llaves" <?php echo $elemento['tipo'] == 17 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Llaves</label>
      <div class="controls">
        <select name="llaves" id="llaves" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Si" <?php echo $elemento['tipo'] == 17 && $elemento['llaves'] == 'Si' ? 'selected' : '' ?>>Si</option>
          <option value="No" <?php echo $elemento['tipo'] == 17 && $elemento['llaves'] == 'No' ? 'selected' : '' ?>>No</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_tapizado" <?php echo $elemento['tipo'] == 18 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Tapizado</label>
      <div class="controls">
        <select name="tapizado" id="tapizado" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Si" <?php echo $elemento['tipo'] == 18 && $elemento['tapizado'] == 'Si' ? 'selected' : '' ?>>Si</option>
          <option value="No" <?php echo $elemento['tipo'] == 18 && $elemento['tapizado'] == 'No' ? 'selected' : '' ?>>No</option>
        </select>
      </div>
    </div>
  </div>
  
  <div id="div_usd">
    <div class="control-group">
      <label class="control-label" for="nombre">USD</label>
      <div class="controls">
        <input type="number" id="usd" name="usd" step="0.01" min="0.1" max="100" value="<?php echo $elemento['usd']; ?>">
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