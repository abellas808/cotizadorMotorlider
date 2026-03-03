<?php 

$url = $config->urlBase.'ws/locations';
$sucursales = json_decode(httpGet($url));

?>
<link rel="stylesheet" href="./../adm/css/style_calendario.css">

<div id="div_agendar" style="display: none;">
    <h1>Agendar Inspección Mecánica</h1>
    <div class="subtitulo_pagina subtitulo_seccion">Agende su visita para revisión y cotización final de su usado<br> Indíquenos un lugar, día y horario que más le convenga.</div>
    <div class="columna">
        <select id="sucursal" name="sucursal" onchange="datos_sucursal()">
            <option value=''>Seleccione Sucursal</option>
            <?php 
            foreach ($sucursales->locations as $sucursal ) {
                $array_datos[$sucursal->id] = '
                <p style="text-decoration: none;">
                    <i class="fas fa-map-marker-alt" style="margin-right: 5px;"></i>'.$sucursal->direccion.'
                </p>
                <p>
                    <a href="mailto:'.$sucursal->email.'" style="text-decoration: none;"><i class="fas fa-envelope" style="margin-right: 5px;"></i>'.$sucursal->email.'</a>
                </p>
                <p>
                    <a href="tel:0598'.$sucursal->telefono.'" style="text-decoration: none;"><i class="fas fa-phone-alt" style="margin-right: 5px;"></i><span>'.$sucursal->telefono.'</span></a>
                </p>';
            ?>
            <option value='<?php echo $sucursal->id; ?>'><?php echo $sucursal->nombre; ?></option>
            <?php $suc = $sucursal->id;
                echo $suc;
            } ?>
        </select>
    </div>
    <?php foreach ($array_datos as $id => $datos) : ?>

        <div class="datos_sucursal columna" id="datos_sucursal_<?php echo $id ?>" style="display: none;height: 120px;">
            <div class="pb-4" style="text-decoration: none;">
                <?php echo $datos; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div id="calendario">

    </div>

    <input type="hidden" name="fecha_reserva" id="fecha_reserva">
    <input type="hidden" name="horario_reserva" id="horario_reserva">
    <input type="hidden" name="suc" id="suc">
    <input type="hidden" name="auto" id="auto">
</div>

<script src="js/jquery-3.5.1.min.js"></script>

<script type="text/javascript">

    function datos_sucursal() {
        var sucursal_selec = $("#sucursal").val();
        $(".datos_sucursal").css({
            "display": "none"
        });
        $("#datos_sucursal_" + sucursal_selec).css({
            "display": "block"
        });
    }

    $('#sucursal').change(function() {
        var sucursal = $(this).val();
        if (sucursal != '') {
            $('#suc').val(sucursal);
            $('#calendario').load('calendario.php?s=' + sucursal);
        } else {
            $('#calendario').html('');
            $('#div_siniestro').hide();
        }
    });

    function continuar_agenda(){
        if(!($("#reserva").val() > 0)){
            var model1 = document.getElementById("modelo");
            var textModel1 = model1.options[model1.selectedIndex].text;
            var marca1 = document.getElementById("marca");
            var textMarca1 = marca1.options[marca1.selectedIndex].text;

            var textAuto = textMarca1 + ' ' + textModel1;
            $("#auto").val(textAuto);
        }
        $("#form_cotizar").submit();
    }

</script>