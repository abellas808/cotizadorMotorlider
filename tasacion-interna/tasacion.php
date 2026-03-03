<?php

//compruebo si esta la key
if(isset($_GET['key'])){
    if($_GET['key'] != 'aRTMim8Qxl4uGHb'){
        echo '<h2 style="text-align:center;">Acceso no autorizado</h2>';
        echo die;
    }
} else {
    echo '<h2 style="text-align:center;">Acceso no autorizado</h2>';
    echo die;
}

date_default_timezone_set('America/Montevideo');

include('./../config.php');
include('./../config/config.inc.php');

$url = $config->urlBase.'ws/brands';
$marcas = json_decode(httpGet($url));

?>
<link rel="stylesheet" href="./../adm/css/flickity.css">
<link rel="stylesheet" href="./../adm/css/styles.css?v=50">
<link rel="stylesheet" href="./../adm/css/style_calendario.css?v=50">
<link rel="stylesheet" href="./../adm/css/fonts.css">
<link rel="stylesheet" href="//use.fontawesome.com/releases/v5.15.1/css/all.css">

<style>
    .loader{display: none;}
</style>

<section>
    <div class="informacion">
        <h1>Cotizador Interno de Vehículo</h1>
        <form action="#" method="POST">
            <div class="formulario">
                <?php include 'inc_tasacion_home.php'; ?>
                <div style="clear: both"></div>
            </div>
            <div class="formulario fondo" id="div_result_tasacion" style="display: none;">
                <p class="subtitulo subtitulo-resultado" id="result_tasacion">
                </p>
                <div style="clear: both"></div>
            </div>
        </form>
    </div>
</section>

<script src="js/jquery-3.5.1.min.js"></script>

<script>
    function oneDot(input) {
        var value = input.value,
            value = value.split('.').join('');

        if (value.length > 3) {
            value = value.substring(0, value.length - 3) + '.' + value.substring(value.length - 3, value.length);
        }

        input.value = value;
    }

    function get_modelo() {
        $("#result_tasacion").html('');
        $("#div_tasacion").hide();
        $("#div_modelo").hide();
        $("#div_ano").hide();
        $("#div_familia").hide();
        $("#div_resto").hide();
        $("#div_agendar").hide();
        $("#div_tasacion2").hide();
        $("#notfamily").hide();

        var marca = $("#marca").val();

        if (marca > 0) {
            $.ajax({
                type: "POST",
                url: "ajax/get_modelo_tasacion.php",
                data: {
                    marca: marca
                },
                success: function(response) {
                    if (response != '') {
                        $("#modelo").html(response);
                        $("#div_modelo").show();
                    } else {
                        $("#modelo").empty();
                        $("#div_modelo").hide();
                    }

                }
            });
        } else {
            $("#modelo").empty();
            $("#div_modelo").hide();
        }
    }

    function get_ano() {
        $("#div_tasacion").hide();
        $("#div_ano").hide();
        $("#div_familia").hide();
        $("#div_resto").hide();
        $("#div_agendar").hide();
        $("#div_tasacion2").hide();
        $("#notfamily").hide();

        var marca = $("#marca").val();
        var modelo = $("#modelo").val();
        
        if (marca > 0 && modelo > 0) {
            $.ajax({
                type: "POST",
                url: "ajax/get_anio_tasacion.php",
                data: {
                    marca: marca,
                    modelo: modelo
                },
                success: function(response) {
                    if (response != '') {
                        $("#ano").html(response);
                        $("#div_ano").show();
                    } else {
                        $("#ano").empty();
                        $("#div_ano").hide();
                    }

                }
            });
        } else {
            $("#ano").empty();
            $("#div_ano").hide();
        }
    }

    function get_familias() {
        $("#div_tasacion").hide();
        $("#div_familia").hide();
        $("#div_resto").hide();
        $("#div_agendar").hide();
        $("#div_tasacion2").hide();
        $("#notfamily").hide();

        var marca = $("#marca").val();
        var modelo = $("#modelo").val();
        var anio = $("#ano").val();

        if (marca > 0 && modelo > 0 && anio > 0) {
            $.ajax({
                type: "POST",
                url: "ajax/get_familia_tasacion.php",
                data: {
                    marca: marca,
                    modelo: modelo,
                    anio: anio
                },
                success: function(response) {
                    if (response != '') {
                        $("#familia").html(response);
                        $("#div_familia").show();
                    } else {
                        $("#familia").empty();
                        $("#div_familia").hide();
                        $("#div_resto").show();
                        $("#result_tasacion").html('');
                    }
                }
            });
        } else {
            $("#familia").empty();
            $("#div_familia").hide();
        }
    }

    function show_resto() {
        var familia = $("#familia").val();
        if(familia == 'otro'){
            $("#notfamily").show();
        } else {
            $("#notfamily").hide();
        }
        $("#div_resto").show();
    }

    function tasar2() {
        if ($('#atyc').prop('checked')) {
            var modelo = $("#modelo").val();
            var marca = $("#marca").val();
            var anio = $("#ano").val();
            var familia = $("#familia").val();
            var familiaCustom = $("#txtfamily").val();

            var nombre = $("#nombre_cotizacion").val();
            var email = $("#email_cotizacion").val();
            var telefono = $("#telefono_cotizacion").val();

            var kilometros = clean_ci($("#kilometros_cotizacion").val());
            var ficha_tecnica = $("#ficha_tecnica").val();
            var cantidad_duenios = $("#cantidad_duenios").val();
            var venta_permuta = $("#venta_permuta").val();
            var valor_pretendido = clean_ci($("#valor_pretendido").val());

            var color_auto = $("#color_auto").val();
            var choque_leve = $("#choque_leve").val();
            var choque_grave = $("#choque_grave").val();
            var estado_tapizado = $("#estado_tapizado").val();
            var estado_volante = $("#estado_volante").val();
            var empadronamiento = $("#empadronamiento").val();
            var servicio = $("#servicio").val();
            var correa = $("#correa").val();
            var bateria = $("#bateria").val();
            var piezas_chapista = $("#piezas_chapista").val();
            var neumaticos = $("#neumaticos").val();
            var tazas_llantas = $("#tazas_llantas").val();
            var parabrisas = $("#parabrisas").val();
            var faros = $("#faros").val();
            var aire_acondicionado = $("#aire_acondicionado").val();
            var sensor_estacionamiento = $("#sensor_estacionamiento").val();
            var camara_reserva = $("#camara_reserva").val();
            var radio = $("#radio").val();
            var alarma = $("#alarma").val();
            var vidrios = $("#vidrios").val();
            var espejos = $("#espejos").val();
            var llaves = $("#llaves").val();
            var limpieza_tapizado = $("#limpieza_tapizado").val();

            var model1 = document.getElementById("modelo");
            var textModel1 = model1.options[model1.selectedIndex].text;
            var marca1 = document.getElementById("marca");
            var textMarca1 = marca1.options[marca1.selectedIndex].text;

            var errorfamily = true;

            var textVersion = '';
            if (familia > 0) {
                errorfamily = false;
                var version = document.getElementById("familia");
                textVersion  = version.options[version.selectedIndex].text;
            }

            if (familiaCustom != "") {
                errorfamily = false;
            }
            
            var textAuto = textMarca1 + ' ' + textModel1;

            if(isNaN(telefono) || telefono.length === 0){
                $("#result_tasacion").html('Error! Teléfono inválido');
                $("#div_tasacion2").show();
            } else if(isNaN(kilometros)){
                $("#result_tasacion").html('Error! Kilómetros inválidos');
                $("#div_tasacion2").show();
            } else if(ficha_tecnica == 'xx'){
                $("#result_tasacion").html('Error! Ficha técnica inválida');
                $("#div_tasacion2").show();
            } else if(isNaN(cantidad_duenios)){
                $("#result_tasacion").html('Error! Cantidad de dueños');
                $("#div_tasacion2").show();
            } else if(venta_permuta == 'xx'){
                $("#result_tasacion").html('Error! Tipo de venta inválida');
                $("#div_tasacion2").show();
            } else if(color_auto == 'xx'){
                $("#result_tasacion").html('Error! Color del auto inválido');
                $("#div_tasacion2").show();
            } else if(choque_leve == 'xx'){
                $("#result_tasacion").html('Error! Choque leve inválido');
                $("#div_tasacion2").show();
            } else if(choque_grave == 'xx'){
                $("#result_tasacion").html('Error! Choque grave inválido');
                $("#div_tasacion2").show();
            } else if(estado_tapizado == 'xx'){
                $("#result_tasacion").html('Error! Estado del Tapizado inválido');
                $("#div_tasacion2").show();
            } else if(estado_volante == 'xx'){
                $("#result_tasacion").html('Error! Estado del Volante inválido');
                $("#div_tasacion2").show();
            } else if(empadronamiento == 'xx'){
                $("#result_tasacion").html('Error! Empadronamiento del vehículo inválido');
                $("#div_tasacion2").show();
            } else if(servicio == 'xx'){
                $("#result_tasacion").html('Error! Servicio inválido');
                $("#div_tasacion2").show();
            } else if(correa == 'xx'){
                $("#result_tasacion").html('Error! Correa de Distribución inválida');
                $("#div_tasacion2").show();
            } else if(bateria == 'xx'){
                $("#result_tasacion").html('Error! Batería inválida');
                $("#div_tasacion2").show();
            } else if(isNaN(piezas_chapista)){
                $("#result_tasacion").html('Error! Piezas Chapista inválida');
                $("#div_tasacion2").show();
            } else if(isNaN(neumaticos)){
                $("#result_tasacion").html('Error! Neumáticos para cambiar inválida');
                $("#div_tasacion2").show();
            } else if(isNaN(tazas_llantas)){
                $("#result_tasacion").html('Error! Tazas o Llantas Para Pintar inválida');
                $("#div_tasacion2").show();
            } else if(parabrisas == 'xx'){
                $("#result_tasacion").html('Error! Cambiar parabrisas inválida');
                $("#div_tasacion2").show();
            } else if(isNaN(faros)){
                $("#result_tasacion").html('Error! Faros para cambiar inválida');
                $("#div_tasacion2").show();
            } else if(aire_acondicionado == 'xx'){
                $("#result_tasacion").html('Error! Aire Acondicionado inválida');
                $("#div_tasacion2").show();
            } else if(sensor_estacionamiento == 'xx'){
                $("#result_tasacion").html('Error! Sensor de Estacionamiento inválida');
                $("#div_tasacion2").show();
            } else if(camara_reserva == 'xx'){
                $("#result_tasacion").html('Error! Cámara de Reversa inválida');
                $("#div_tasacion2").show();
            } else if(radio == 'xx'){
                $("#result_tasacion").html('Error! Radio inválida');
                $("#div_tasacion2").show();
            } else if(alarma == 'xx'){
                $("#result_tasacion").html('Error! Alarma inválida');
                $("#div_tasacion2").show();
            } else if(vidrios == 'xx'){
                $("#result_tasacion").html('Error! Vidrios Eléctricos inválidos');
                $("#div_tasacion2").show();
            } else if(espejos == 'xx'){
                $("#result_tasacion").html('Error! Espejos Eléctricos inválidos');
                $("#div_tasacion2").show();
            } else if(llaves == 'xx'){
                $("#result_tasacion").html('Error! Dos Juegos Llaves inválida');
                $("#div_tasacion2").show();
            } else if(limpieza_tapizado == 'xx'){
                $("#result_tasacion").html('Error! Limpieza de Tapizado inválida');
                $("#div_tasacion2").show();
            } else if(isNaN(valor_pretendido)){
                $("#result_tasacion").html('Error! Valor pretendido');
                $("#div_tasacion2").show();
            } else if(errorfamily){
                $("#result_tasacion").html('Error! Ingrese una familia');
                $("#div_tasacion2").show();
            } else {
                if(validar_email(email)){
                    //la version/familia no se puede controlar ya que a veces no hay
                    if (marca > 0 && modelo > 0 && anio > 0 && nombre != '' && !isNaN(telefono)) {

                        var miData = {
                            modelo: modelo,marca: marca,
                            anio: anio,familia: familia,
                            familiaCustom: familiaCustom,nombre: nombre,
                            email: email,telefono: telefono,
                            kilometros: kilometros,ficha_tecnica: ficha_tecnica,
                            cantidad_duenios : cantidad_duenios,venta_permuta: venta_permuta,
                            color_auto: color_auto,choque_leve: choque_leve,
                            choque_grave: choque_grave,estado_tapizado: estado_tapizado,
                            estado_volante: estado_volante,empadronamiento: empadronamiento,
                            servicio: servicio,correa: correa,
                            bateria: bateria,piezas_chapista: piezas_chapista,
                            neumaticos: neumaticos,tazas_llantas: tazas_llantas,
                            parabrisas: parabrisas,faros: faros,
                            aire_acondicionado: aire_acondicionado,sensor_estacionamiento: sensor_estacionamiento,
                            camara_reserva: camara_reserva,radio: radio,
                            alarma: alarma,vidrios: vidrios,
                            espejos: espejos,llaves: llaves,
                            limpieza_tapizado: limpieza_tapizado,valor_pretendido : valor_pretendido,
                            txtmarca : textMarca1,txtmodel : textModel1,
                            txtfamilia : textVersion,auto: textAuto,
                            version: textVersion
                        };

                        $.ajax({
                            type: "POST",
                            url: "ajax/get_tasacion_home.php",
                            data: JSON.stringify(miData),
                            beforeSend: function() {
                                $("#div_tasacion2").hide();
                                $(".loader").show();
                            },
                            success: function(response) {
                                $(".loader").hide();
                                var resp = JSON.parse(response);
                                if (resp.msg != '') {
                                    $("#result_tasacion").html(resp.msg);
                                    $("#id_cotizacion").val(resp.id_cotizacion);
                                    $("#div_tasacion").append('<div class="subtitulo" style="font-size:10px">Valoración definitiva al momento de entrega</div>');
                                    $("#div_tasacion2").show();
                                    var precio_tasa = new Intl.NumberFormat("de-DE").format(resp.valor)
                                    $("#precio_usado").html("U$S " + precio_tasa);

                                    $("#id_cotizacion").val(resp.id_cotizacion);
                                    //dejo ver la agenda

                                    if(resp.valor > 0){
                                        $("#div_agendar").show();
                                        $("#div_agendar").focus();
                                    }
                                } else {
                                    $("#result_tasacion").html('Nuestro sistema no pudo estimar en forma automática el valor de tu vehículo, nos comunicaremos contigo a la brevedad.');
                                    $("#div_tasacion2").show();
                                    $("#div_agendar").hide();
                                }
                                //console.log(resp);
                            }
                        });
                    } else {
                        $("#result_tasacion").html('No se pudo realizar la tasación');
                        $("#div_tasacion2").show();
                    }
                } else {
                    $("#result_tasacion").html('Error! email inválido');
                    $("#div_tasacion2").show();
                }
            }
        } else {
            $("#result_tasacion").html('Debe aceptar Términos y condiciones');
            $("#div_tasacion2").show();
        }

        $("#div_result_tasacion").show();
        location.hash = "#div_result_tasacion";

    }

    function clean_ci(ci) {
        return ci.replace(/\D/g, "");
    }

    function validar_email(email) {
        var regex = /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/;
        return regex.test(email) ? true : false;
    }

</script>