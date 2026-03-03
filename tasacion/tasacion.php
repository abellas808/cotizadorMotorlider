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
<link rel="stylesheet" href="./../adm/css/fonts.css">
<link rel="stylesheet" href="//use.fontawesome.com/releases/v5.15.1/css/all.css">

<style>
    .loader{display: none;}
    .subtitulo_pagina{margin-bottom: 20px;}
</style>

<section>
    <div class="informacion">
        <h1>Cotizar mi Vehículo Online</h1>
        <div class="subtitulo_pagina subtitulo_seccion">Oferta no vinculante.<br>Esta oferta vence en 3 días.</div>
        <form action="/tasacion/continuar_agenda.php" method="POST" id="form_cotizar">
            <div class="formulario">
                <?php include 'inc_tasacion_home.php'; ?>
                <div style="clear: both"></div>
            </div>
            <div class="formulario fondo" id="div_result_tasacion" style="display: none;">
                <p class="subtitulo subtitulo-resultado" id="result_tasacion">
                </p>
                <div style="clear: both"></div>
            </div>
            <div class="formulario">
                <?php include 'inc_agenda.php'; ?>
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
            } else if(venta_permuta == 'xx'){
                $("#result_tasacion").html('Error! Tipo de venta inválida');
                $("#div_tasacion2").show();
            } else if(isNaN(cantidad_duenios)){
                $("#result_tasacion").html('Error! Cantidad de dueños');
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
                        $.ajax({
                            type: "POST",
                            url: "ajax/get_tasacion_home.php",
                            data: {
                                modelo: modelo,
                                marca: marca,
                                anio: anio,
                                familia: familia,
                                familiaCustom: familiaCustom,
                                nombre: nombre,
                                email: email,
                                telefono: telefono,
                                kilometros: kilometros,
                                ficha_tecnica: ficha_tecnica,
                                cantidad_duenios : cantidad_duenios,
                                venta_permuta: venta_permuta,
                                valor_pretendido : valor_pretendido,
                                txtmarca : textMarca1,
                                txtmodel : textModel1,
                                txtfamilia : textVersion,
                                auto: textAuto,
                                version: textVersion
                            },
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