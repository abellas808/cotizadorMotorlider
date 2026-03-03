<?php

if (!isset($sistema_iniciado)) exit();

$id = intval($_GET['i']);

  $elemento = $db->query_first('select * from variables where id = "' . $id . '";');

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
        <option value="3" <?php echo $elemento['tipo'] == 3 ? 'selected' : '' ?>>Ficha Técnica</option>
        <option value="4" <?php echo $elemento['tipo'] == 4 ? 'selected' : '' ?>>Cantidad de Dueños</option>
        <option value="5" <?php echo $elemento['tipo'] == 5 ? 'selected' : '' ?>>Tipo Venta</option>
        <option value="6" <?php echo $elemento['tipo'] == 6 ? 'selected' : '' ?>>Mismo veh&iacute;culo en Stock</option>
        <option value="7" <?php echo $elemento['tipo'] == 7 ? 'selected' : '' ?>>Color del auto</option>
        <option value="8" <?php echo $elemento['tipo'] == 8 ? 'selected' : '' ?>>Sufrió Choque Leve</option>
        <option value="9" <?php echo $elemento['tipo'] == 9 ? 'selected' : '' ?>>Sufrió Choque Grave</option>
        <option value="10" <?php echo $elemento['tipo'] == 10 ? 'selected' : '' ?>>Estado del Tapizado</option>
        <option value="11" <?php echo $elemento['tipo'] == 11 ? 'selected' : '' ?>>Estado del Volante</option>
      </select>
    </div>
  </div>

  <div id="div_ficha" <?php echo $elemento['tipo'] == 3 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Ficha Técnica</label>
      <div class="controls">
        <select name="ficha" id="ficha" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Si" <?php echo $elemento['tipo'] == 3 && $elemento['ficha_oficial'] == 'Si' ? 'selected' : '' ?>>Si</option>
          <option value="No" <?php echo $elemento['tipo'] == 3 && $elemento['ficha_oficial'] == 'No' ? 'selected' : '' ?>>No</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_cantidad_duenios" <?php echo $elemento['tipo'] == 4 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="cantidad_duenios">Cantidad de Dueños</label>
      <div class="controls">
        <select name="cantidad_duenios" id="cantidad_duenios" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="1" <?php echo $elemento['tipo'] == 4 && $elemento['cantidad_duenios'] == '1' ? 'selected' : '' ?>>1</option>
          <option value="2" <?php echo $elemento['tipo'] == 4 && $elemento['cantidad_duenios'] == '2' ? 'selected' : '' ?>>2</option>
          <option value="3" <?php echo $elemento['tipo'] == 4 && $elemento['cantidad_duenios'] == '3' ? 'selected' : '' ?>>3</option>
          <option value="4" <?php echo $elemento['tipo'] == 4 && $elemento['cantidad_duenios'] == '4' ? 'selected' : '' ?>>4</option>
          <option value="5" <?php echo $elemento['tipo'] == 4 && $elemento['cantidad_duenios'] == '5' ? 'selected' : '' ?>>5</option>
          <option value="6" <?php echo $elemento['tipo'] == 4 && $elemento['cantidad_duenios'] == '6' ? 'selected' : '' ?>>6</option>
          <option value="7" <?php echo $elemento['tipo'] == 4 && $elemento['cantidad_duenios'] == '7' ? 'selected' : '' ?>>7</option>
          <option value="8" <?php echo $elemento['tipo'] == 4 && $elemento['cantidad_duenios'] == '8' ? 'selected' : '' ?>>8</option>
          <option value="9" <?php echo $elemento['tipo'] == 4 && $elemento['cantidad_duenios'] == '9' ? 'selected' : '' ?>>9</option>
          <option value="10" <?php echo $elemento['tipo'] == 4 && $elemento['cantidad_duenios'] == '10' ? 'selected' : '' ?>>10</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_ficha" <?php echo $elemento['tipo'] == 5 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Tipo Venta</label>
      <div class="controls">
        <select name="tipo_venta" id="tipo_venta" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Venta" <?php echo $elemento['tipo'] == 5 && $elemento['tipo_venta'] == 'Venta' ? 'selected' : '' ?>>Venta Contado</option>
          <option value="Entrega" <?php echo $elemento['tipo'] == 5 && $elemento['tipo_venta'] == 'Entrega' ? 'selected' : '' ?>>Entrega en forma de pago</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_stock" <?php echo $elemento['tipo'] == 6 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Cantidad de Stock</label>
      <div class="controls">
        <select name="stock" id="stock" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="1" <?php echo $elemento['tipo'] == 6 && $elemento['stock'] == '1' ? 'selected' : '' ?>>1</option>
          <option value="2" <?php echo $elemento['tipo'] == 6 && $elemento['stock'] == '2' ? 'selected' : '' ?>>2</option>
          <option value="3" <?php echo $elemento['tipo'] == 6 && $elemento['stock'] == '3' ? 'selected' : '' ?>>3</option>
          <option value="4" <?php echo $elemento['tipo'] == 6 && $elemento['stock'] == '4' ? 'selected' : '' ?>>4</option>
          <option value="5" <?php echo $elemento['tipo'] == 6 && $elemento['stock'] == '5' ? 'selected' : '' ?>>5 o más</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_color" <?php echo $elemento['tipo'] == 7 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Color del auto</label>
      <div class="controls">
        <select name="color" id="color" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Comerciable" <?php echo $elemento['tipo'] == 7 && $elemento['color'] == 'Comerciable' ? 'selected' : '' ?>>Comerciable</option>
          <option value="Poco comerciable" <?php echo $elemento['tipo'] == 7 && $elemento['color'] == 'Poco comerciable' ? 'selected' : '' ?>>Poco comerciable</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_leve" <?php echo $elemento['tipo'] == 8 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Choque</label>
      <div class="controls">
        <select name="leve" id="leve" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Si" <?php echo $elemento['tipo'] == 8 && $elemento['choque_leve'] == 'Si' ? 'selected' : '' ?>>Si</option>
          <option value="No" <?php echo $elemento['tipo'] == 8 && $elemento['choque_leve'] == 'No' ? 'selected' : '' ?>>No</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_grave" <?php echo $elemento['tipo'] == 9 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Choque</label>
      <div class="controls">
        <select name="grave" id="grave" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Si" <?php echo $elemento['tipo'] == 9 && $elemento['choque_grave'] == 'Si' ? 'selected' : '' ?>>Si</option>
          <option value="No" <?php echo $elemento['tipo'] == 9 && $elemento['choque_grave'] == 'No' ? 'selected' : '' ?>>No</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_tapizado" <?php echo $elemento['tipo'] == 10 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Estado</label>
      <div class="controls">
        <select name="tapizado" id="tapizado" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Excelente" <?php echo $elemento['tipo'] == 10 && $elemento['tapizado'] == 'Excelente' ? 'selected' : '' ?>>Excelente</option>
          <option value="Muy bueno" <?php echo $elemento['tipo'] == 10 && $elemento['tapizado'] == 'Muy bueno' ? 'selected' : '' ?>>Muy bueno</option>
          <option value="Bueno" <?php echo $elemento['tipo'] == 10 && $elemento['tapizado'] == 'Bueno' ? 'selected' : '' ?>>Bueno</option>
          <option value="Malo" <?php echo $elemento['tipo'] == 10 && $elemento['tapizado'] == 'Malo' ? 'selected' : '' ?>>Malo</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_volante" <?php echo $elemento['tipo'] == 11 ? '' : 'style="display: none;"' ?>>
    <div class="control-group">
      <label class="control-label" for="nombre">Estado</label>
      <div class="controls">
        <select name="volante" id="volante" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="Excelente" <?php echo $elemento['tipo'] == 11 && $elemento['volante'] == 'Excelente' ? 'selected' : '' ?>>Excelente</option>
          <option value="Muy bueno" <?php echo $elemento['tipo'] == 11 && $elemento['volante'] == 'Muy bueno' ? 'selected' : '' ?>>Muy bueno</option>
          <option value="Bueno" <?php echo $elemento['tipo'] == 11 && $elemento['volante'] == 'Bueno' ? 'selected' : '' ?>>Bueno</option>
          <option value="Malo" <?php echo $elemento['tipo'] == 11 && $elemento['volante'] == 'Malo' ? 'selected' : '' ?>>Malo</option>
        </select>
      </div>
    </div>
  </div>

  <div id="div_porcentaje">
    <div class="control-group">
      <label class="control-label" for="nombre">Operador</label>
      <div class="controls">
        <select name="operador" id="operador" class="select-search">
          <option value="xx" selected disabled>Seleccione</option>
          <option value="+" <?php echo $elemento['operador'] == '+' ? 'selected' : '' ?>>+</option>
          <option value="-" <?php echo $elemento['operador'] == '-' ? 'selected' : '' ?>>-</option>
        </select>
      </div>
    </div>
    <div class="control-group">
      <label class="control-label" for="nombre">Porcentaje</label>
      <div class="controls">
        <input type="number" id="porcentaje" name="porcentaje" step="0.01" min="0.1" max="100" value="<?php echo $elemento['porcentaje']; ?>">
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