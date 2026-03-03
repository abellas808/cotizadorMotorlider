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

<div id="notfamily" class="columna"  style="display: none;">
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