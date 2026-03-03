<div class="columna">
    <input type="text" placeholder="Nombre" name="nombre_cotizacion" id="nombre_cotizacion" class="input-login">
</div>
<div class="columna">
    <input type="text" placeholder="Email" name="email_cotizacion" id="email_cotizacion" class="input-login">
</div>
<div class="columna">
    <input type="text" placeholder="Teléfono" name="telefono_cotizacion" id="telefono_cotizacion" class="input-login">
</div>
<div class="columna">
    <select onchange="get_modelo()" id="marca" name="reserva_tasa_marca">
        <option id="0">Marca</option>
        <?php foreach($marcas->brands as $key => $marca) : ?>
            <option value="<?php echo $key; ?>"><?php echo $marca; ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="columna" id="div_modelo" style="display: none;">
    <select id="modelo" onchange="get_ano()" name="reserva_tasa_modelo">
    </select>
</div>

<div class="columna" id="div_ano" style="display: none;">
    <select id="ano" onchange="get_familias()" name="reserva_tasa_anio">
    </select>
</div>

<div class="columna" id="div_familia" style="display: none;">
    <select id="familia" onchange="show_resto()" name="reserva_tasa_familia">
    </select>
</div>

<div class="columna" id="notfamily" style="display: none;">
    <input type="text" placeholder="Ingrese su versión" name="txtfamily" id="txtfamily" class="input-login">
</div>

<div id="div_resto" style="display: none;">
    <div class="columna">
        <input type="text" onkeyup="oneDot(this)" placeholder="Kilómetros" name="kilometros_cotizacion" id="kilometros_cotizacion" class="input-login" onkeyup="$(this).val(format_number($(this).val(),'','.',','));">
    </div>
    <div class="columna">
        <select id="ficha_tecnica" name="ficha_tecnica">
            <option value="xx" selected>Ficha en Service Oficial</option>
            <option value="Si">Si</option>
            <option value="No">No</option>
        </select>
        <label style="font-size: 11px">Todos los Servicios realizados en Service Oficial.</label>
    </div>
    <div class="columna">
        <select id="cantidad_duenios" name="cantidad_duenios">
            <option value="xx" selected>Cantidad de dueños</option>
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
    <div class="columna">
        <select id="venta_permuta" name="venta_permuta">
            <option value="xx" selected>Seleccione tipo Venta</option>
            <option value="Venta">Venta Contado</option>
            <option value="Entrega">Entrega como forma de pago</option>
        </select>
    </div>
    <div class="columna">
        <select id="color_auto" name="color_auto">
            <option value="xx" selected>Color del auto</option>
            <option value="Comerciable">Comerciable</option>
            <option value="Poco comerciable">Poco comerciable</option>
        </select>
    </div>
    <div class="columna">
        <select id="choque_leve" name="choque_leve">
            <option value="xx" selected>Sufrió Choque Leve</option>
            <option value="Si">Si</option>
            <option value="No">No</option>
        </select>
    </div>
    <div class="columna">
        <select id="choque_grave" name="choque_grave">
            <option value="xx" selected>Sufrió Choque Grave</option>
            <option value="Si">Si</option>
            <option value="No">No</option>
        </select>
    </div>
    <div class="columna">
        <select id="estado_tapizado" name="estado_tapizado">
            <option value="xx" selected>Estado del Tapizado</option>
            <option value="Excelente">Excelente</option>
            <option value="Muy bueno">Muy bueno</option>
            <option value="Bueno">Bueno</option>
            <option value="Malo">Malo</option>
        </select>
    </div>
    <div class="columna">
        <select id="estado_volante" name="estado_volante">
            <option value="xx" selected>Estado del Volante</option>
            <option value="Excelente">Excelente</option>
            <option value="Muy bueno">Muy bueno</option>
            <option value="Bueno">Bueno</option>
            <option value="Malo">Malo</option>
        </select>
    </div>
    <div class="columna">
        <select id="empadronamiento" name="empadronamiento">
            <option value="xx" selected>Departamento de empadronamiento del vehículo</option>
            <option value="Montevideo">Montevideo</option>
            <option value="Canelones">Canelones</option>
            <option value="Otro departamento">Otro departamento</option>
        </select>
    </div>
    <div class="columna">
        <select id="servicio" name="servicio">
            <option value="xx" selected>Servicio</option>
            <option value="Ok">Ok</option>
            <option value="Para Cambiar">Para Cambiar</option>
        </select>
    </div>
    <div class="columna">
        <select id="correa" name="correa">
            <option value="xx" selected>Correa de Distribución</option>
            <option value="Ok">Ok</option>
            <option value="Para Cambiar">Para Cambiar</option>
        </select>
    </div>
    <div class="columna">
        <select id="bateria" name="bateria">
            <option value="xx" selected>Batería</option>
            <option value="Ok">Ok</option>
            <option value="Para Cambiar">Para Cambiar</option>
        </select>
    </div>
    <div class="columna">
        <select id="piezas_chapista" name="piezas_chapista">
            <option value="xx" selected>Piezas para Chapista</option>
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
    <div class="columna">
        <select id="neumaticos" name="neumaticos">
            <option value="xx" selected>Neumáticos para cambiar</option>
            <option value="0">0</option>
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
            <option value="4">4</option>
        </select>
    </div>
    <div class="columna">
        <select id="tazas_llantas" name="tazas_llantas">
            <option value="xx" selected>Tazas o Llantas Para Pintar</option>
            <option value="0">0</option>
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
            <option value="4">4</option>
            <option value="5">5</option>
            <option value="6">6</option>
        </select>
    </div>
    <div class="columna">
        <select id="parabrisas" name="parabrisas">
            <option value="xx" selected>Cambiar parabrisas</option>
            <option value="Si">Si</option>
            <option value="No">No</option>
        </select>
    </div>
    <div class="columna">
        <select id="faros" name="faros">
            <option value="xx" selected>Faros para cambiar</option>
            <option value="0">0</option>
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
            <option value="4">4</option>
            <option value="5">5</option>
            <option value="6">6</option>
        </select>
    </div>
    <div class="columna">
        <select id="aire_acondicionado" name="aire_acondicionado">
            <option value="xx" selected>Aire Acondicionado</option>
            <option value="Funciona">Funciona</option>
            <option value="No Funciona">No Funciona</option>
        </select>
    </div>
    <div class="columna">
        <select id="sensor_estacionamiento" name="sensor_estacionamiento">
            <option value="xx" selected>Sensor de Estacionamiento</option>
            <option value="Funciona">Funciona</option>
            <option value="No Funciona">No Funciona</option>
            <option value="No Corresponde">No Corresponde</option>
        </select>
    </div>
    <div class="columna">
        <select id="camara_reserva" name="camara_reserva">
            <option value="xx" selected>Cámara de Reversa</option>
            <option value="Funciona">Funciona</option>
            <option value="No Funciona">No Funciona</option>
            <option value="No Corresponde">No Corresponde</option>
        </select>
    </div>
    <div class="columna">
        <select id="radio" name="radio">
            <option value="xx" selected>Radio</option>
            <option value="Funciona">Funciona</option>
            <option value="No Funciona">No Funciona</option>
        </select>
    </div>
    <div class="columna">
        <select id="alarma" name="alarma">
            <option value="xx" selected>Alarma</option>
            <option value="Funciona">Funciona</option>
            <option value="No Funciona">No Funciona</option>
            <option value="No Corresponde">No Corresponde</option>
        </select>
    </div>
    <div class="columna">
        <select id="vidrios" name="vidrios">
            <option value="xx" selected>Vidrios Eléctricos</option>
            <option value="Funciona">Funciona</option>
            <option value="No Funciona">No Funciona</option>
            <option value="No Corresponde">No Corresponde</option>
        </select>
    </div>
    <div class="columna">
        <select id="espejos" name="espejos">
            <option value="xx" selected>Espejos Eléctricos</option>
            <option value="Funciona">Funciona</option>
            <option value="No Funciona">No Funciona</option>
            <option value="No Corresponde">No Corresponde</option>
        </select>
    </div>
    <div class="columna">
        <select id="llaves" name="llaves">
            <option value="xx" selected>Dos Juegos Llaves</option>
            <option value="Si">Si</option>
            <option value="No">No</option>
        </select>
    </div>
    <div class="columna">
        <select id="limpieza_tapizado" name="limpieza_tapizado">
            <option value="xx" selected>Limpieza de Tapizado</option>
            <option value="Si">Si</option>
            <option value="No">No</option>
        </select>
    </div>
    <div class="columna">
        <input type="text" onkeyup="oneDot(this)" placeholder="Valor pretendido en U$S" name="valor_pretendido" id="valor_pretendido" class="input-login" onkeyup="$(this).val(format_number($(this).val(),'','.',','));">
    </div>

    <div class="columna">
        <a href="#" target="_blank"> Aceptar Términos y condiciones <input type="checkbox" id="atyc" style="width: 15px;height: 13px;-webkit-appearance: checkbox;"></a>
    </div>

    <div class="columna">
        <button type="button" class="tasar" onclick="tasar2()">Tasar</button> 
        <span class="loader"><b>cotizando...</b></span>
    </div>
</div>
<div class="subtitulo subtitulo-resultado" ></div>
<input type="hidden" name="id_cotizacion" id="id_cotizacion" />