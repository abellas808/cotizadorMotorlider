<?php
date_default_timezone_set('America/Montevideo');

include('./../config.php');
include('./../config/config.inc.php');

$negativo = true;

$connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$connection->set_charset('utf8');

if(isset($_POST['id_cotizacion']) && intval($_POST['id_cotizacion']) > 0){
	$cotizacion = $connection->query("SELECT * FROM cotizaciones_generadas WHERE id_cotizaciones_generadas = " . $_POST['id_cotizacion']);
	$cotizacion = $cotizacion->fetch_array(MYSQLI_ASSOC);

	$sucursalesList = 'SELECT * FROM agenda_sucursal WHERE id_sucursal = "' . ($_POST['sucursal']) . '"';
	$sucursales = $connection->query($sucursalesList);
	$sucursales = $sucursales->fetch_array(MYSQLI_ASSOC);
	$suc_name = $sucursales['nombre'];
	$suc_direccion = $sucursales['direccion'];
	$suc_email = $sucursales['email'];
	$suc_telefono = $sucursales['telefono'];
}else{
	header("Location: " . $config['base_url_web'] . "tasacion");
}

?>
<link href="css/style_calendario.css?v=z" rel="stylesheet" type="text/css" />
<style type="text/css">
	
	.text-continuar{
		display: block;
    	text-align: left;
	}
</style>
<div class="scroll-container">

    <section>
        <div class="informacion">
            <h1>Confirmar Agenda</h1>
            <div class="subtitulo_pagina subtitulo_seccion"></div>
            <form action="/tasacion/continuar_agenda.php" method="POST" id="form_cotizar">

            	<input type="hidden" name="modelo" id="modelo" value="<?php echo $_POST['reserva_tasa_modelo']; ?>" />
            	<input type="hidden" name="marca" id="marca" value="<?php echo $_POST['reserva_tasa_marca']; ?>" />
            	<input type="hidden" name="ano" id="ano" value="<?php echo $_POST['reserva_tasa_anio']; ?>" />
            	<input type="hidden" name="familia" id="familia" value="<?php echo $_POST['reserva_tasa_familia']; ?>" />
            	<input type="hidden" name="txtfamily" id="txtfamily" value="<?php echo $_POST['txtfamily']; ?>" />
            	<input type="hidden" name="nombre" id="nombre" value="<?php echo $_POST['nombre_cotizacion']; ?>" />
            	<input type="hidden" name="email" id="email" value="<?php echo $_POST['email_cotizacion']; ?>" />
            	<input type="hidden" name="telefono" id="telefono" value="<?php echo $_POST['telefono_cotizacion']; ?>" />
            	<input type="hidden" name="fecha" id="fecha" value="<?php echo $_POST['fecha_reserva']; ?>" />
            	<input type="hidden" name="hora" id="hora" value="<?php echo $_POST['horario_reserva']; ?>" />
            	<input type="hidden" name="suc" id="suc" value="<?php echo $_POST['sucursal']; ?>" />
            	<input type="hidden" name="suc_nombre" id="suc_nombre" value="<?php echo $suc_name; ?>" />
            	<input type="hidden" name="suc_direccion" id="suc_direccion" value="<?php echo $suc_direccion; ?>" />
            	<input type="hidden" name="suc_email" id="suc_email" value="<?php echo $suc_email; ?>" />
            	<input type="hidden" name="suc_telefono" id="suc_telefono" value="<?php echo $suc_telefono; ?>" />
            	<input type="hidden" name="auto" id="auto" value="<?php echo $_POST['auto']; ?>" />
            	<input type="hidden" name="id_cotizacion" id="id_cotizacion" value="<?php echo $_POST['id_cotizacion']; ?>" />
            	
            
                <div class="formulario">
                    <div class="columna">
					    <strong class="text-continuar">Nombre:</strong> <label class="text-continuar"> <?php echo $_POST['nombre_cotizacion']; ?></label>
					</div>
					<div class="columna">
					    <strong class="text-continuar">Email:</strong> <label class="text-continuar"> <?php echo $_POST['email_cotizacion']; ?></label>
					</div>
					<div class="columna">
					    <strong class="text-continuar">Teléfono:</strong> <label class="text-continuar"> <?php echo $_POST['telefono_cotizacion']; ?></label>
					</div>
					<div class="columna">
					    <strong class="text-continuar">Automóvil:</strong> <label class="text-continuar"> <?php echo $_POST['auto']; ?></label>
					</div>
					<div class="columna">
					    <strong class="text-continuar">Año Automóvil:</strong> <label class="text-continuar"> <?php echo $_POST['reserva_tasa_anio']; ?></label>
					</div>
					<div class="columna">
					    <strong class="text-continuar">Cotización:</strong> <label class="text-continuar"> <?php echo $cotizacion['msg']; ?></label>
					</div>
					<div class="columna">
					    <strong class="text-continuar">Kilómetros Automóvil:</strong> <label class="text-continuar"> <?php echo $_POST['kilometros_cotizacion']; ?></label>
					</div>
					<div class="columna">
					    <strong class="text-continuar">Ficha Técnica:</strong> <label class="text-continuar"> <?php echo $_POST['ficha_tecnica']; ?></label>
					</div>
					<div class="columna">
					    <strong class="text-continuar">Cantidad de Dueños:</strong> <label class="text-continuar"> <?php echo $_POST['cantidad_duenios']; ?></label>
					</div>
					<div class="columna">
					    <strong class="text-continuar">Tipo de Venta:</strong> <label class="text-continuar"> <?php echo $_POST['venta_permuta']; ?></label>
					</div>
					<div class="columna">
					    <strong class="text-continuar">Valor Pretendido:</strong> <label class="text-continuar"> <?php echo $_POST['valor_pretendido']; ?></label>
					</div>
					<div class="columna">
					    <strong class="text-continuar">Sucursal:</strong> <label class="text-continuar"> <?php echo $suc_name; ?></label>
					</div>
					<div class="columna">
					    <strong class="text-continuar">Sucursal Dirección:</strong> <label class="text-continuar"> <?php echo $suc_direccion; ?></label>
					</div>
					<div class="columna">
					    <strong class="text-continuar">Sucursal Email:</strong> <label class="text-continuar"> <?php echo $suc_email; ?></label>
					</div>
					<div class="columna">
					    <strong class="text-continuar">Sucursal Teléfono:</strong> <label class="text-continuar"> <?php echo $suc_telefono; ?></label>
					</div>
					<div class="columna">
					    <strong class="text-continuar">Fecha de Agenda:</strong> <label class="text-continuar"> <?php echo $_POST['fecha_reserva']; ?></label>
					</div>
					<div class="columna">
					    <strong class="text-continuar">Hora de Agenda:</strong> <label class="text-continuar"> <?php echo $_POST['horario_reserva']; ?></label>
					</div>
					<div class="columna columna_tasar visible-xs hidden-sm hidden-md hidden-lg">
						<input type="hidden" id="es_mobile" value="1">
				        <button type="button" class="tasar tasar_whatsapp" onclick="agendar(true)">
				            <i class="fab fa-whatsapp" style="color: #fff !important; font-style: normal;"></i>&nbsp;&nbsp;&nbsp;Agendar WApp</button>
				    </div>
				    <div class="columna columna_tasar hidden-xs visible-sm visible-md visible-lg">
				        <button type="button" class="tasar" onclick="agendar(false)"> Agendar</button>
				    </div>
				    <div class="columna columna_tasar" id="resultado_agenda" style="width: 60%;">
					    
					</div>
                </div>
            </form>
        </div>
    </section>

</div>

<?php include 'whatsapp.php'; ?>

<script src="js/jquery-3.5.1.min.js"></script>

<script>
	function agendar(enviar_wapp) {
        var modelo = $("#modelo").val();
            var marca = $("#marca").val();
            var anio = $("#ano").val();
            var familia = $("#familia").val();
			if(familia == 'otro'){
				familia = $("#txtfamily").val();
			}

            var nombre = $("#nombre").val();
            var email = $("#email").val();
            var telefono = $("#telefono").val();

            var textAuto = $("#auto").val();

            var fecha = $("#fecha").val();
            var hora = $("#hora").val();
            var sucursal = $("#suc").val();
            var suc_name = $("#suc_nombre").val();
            var suc_direccion = $("#suc_direccion").val();
            var suc_telefono = $("#suc_telefono").val();
            var suc_email = $("#suc_email").val();

            var id_cotizacion = $("#id_cotizacion").val();

            $.ajax({
	                type: "POST",
	                url: "/tasacion/ajax/realizar_agenda.php",
	                data: {
	                modelo: modelo,
	                marca: marca,
	                anio: anio,
	                familia: familia,
	                nombre: nombre,
	                email: email,
	                telefono: telefono,
	                auto: textAuto,
	                fecha: fecha,
	                hora: hora,
	                sucursal: sucursal,
	                id_cotizacion:id_cotizacion
	            },
	            success: function(response) {

	                if(response == 1){
//*ejemplo* --> negrita
//%0A --> salto de linea	

						var mensaje = "*Detalle de Agenda:*%0A%0A";
						mensaje += "*Nombre del cliente:* " + nombre + "%0A%0A";
						mensaje += "*Fecha:* " + fecha + "%0A%0A";
						mensaje += "*Hora:* " + hora + "%0A%0A";
						mensaje += "*Sucursal:* " + suc_name + "%0A%0A";
						mensaje += "*Sucursal Direccion:* " + suc_direccion + "%0A%0A";
						mensaje += "*Sucursal Email:* " + suc_email + "%0A%0A";
						mensaje += "*Sucursal Teléfono:* " + suc_telefono + "%0A%0A";
						mensaje += "*Automóvil:* " + textAuto + "%0A%0A";


	                    //window.location = "https://api.whatsapp.com/send?phone=+59892194512&text=" + mensaje;

	                    $("#resultado_agenda").html("Se ha agendado la visita con éxito, le llegará un mail a la dirección de correo ingresada con los datos.");

	                    if(enviar_wapp){
	                    	window.open("https://wa.me/59892194512?text=" + mensaje, '_blank');
	                    }
	                   
	                    //window.location = "https://sodiotest.com/motorlider/";
	                }

	                if(response == 2){ alert("Ya existe una agenda para la cotizacion: " + id_cotizacion)}
	               
	               //var = "*Detalle de Agenda:*%0A*Nombre del cliente:* Santiago Palermo%0A*Documento del cliente:* 46448486%0A*Fecha:* 12/06/2021%0A*Hora:* 10:00%0A*Sucursal:* Motorlider%0A*Automóvil:* Hyundai i10 5p full";

	                /*var resp = JSON.parse(response);
	                if (resp.msg != '') {
	                    $("#result_tasacion").html(resp.msg);
	                    $("#div_tasacion").append('<div class="subtitulo" style="font-size:10px">Valoración definitiva al momento de entrega</div>');
	                    $("#div_tasacion2").show();
	                    var precio_tasa = new Intl.NumberFormat("de-DE").format(resp.valor)
	                    $("#precio_usado").html("U$S " + precio_tasa);
	                } else {
	                    $("#result_tasacion").html('No se pudo realizar la tasación');
	                    $("#div_tasacion2").show();
	                }*/
	            }
    		});

    }
</script>