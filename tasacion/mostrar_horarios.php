<?php

header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Cache-Control: no-cache");
header("Pragma: no-cache");

include('./../config.php');
include('./../config/config.inc.php');
include('./../adm/includes/funciones.php');

$connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$connection->set_charset('utf8'); 

$fecha =$_GET['f'];
$tipo = $_GET['t'];
$sucursal = intval($_GET['s']);

setlocale(LC_ALL,"es_ES@euro","es_ES","esp");
$date = DateTime::createFromFormat("Y-m-d", $fecha);
$dia = strftime("%A", $date->getTimestamp());

$horarios = $connection->query('SELECT hora_comienzo FROM agenda_particulares
INNER JOIN agenda_horas_particulares
ON agenda_particulares.id_particular = agenda_horas_particulares.id_particular
WHERE agenda_particulares.id_sucursal = "'.$sucursal.'"
AND agenda_particulares.fecha ="'.$fecha.'"
AND agenda_particulares.cancelado = 0
AND agenda_horas_particulares.hora_comienzo
NOT IN (SELECT hora_comienzo FROM agenda_particulares
INNER JOIN agenda_horas_particulares
ON agenda_particulares.id_particular = agenda_horas_particulares.id_horas_particular
WHERE agenda_particulares.fecha = "'.($fecha).'"
AND agenda_particulares.cancelado = 1)

UNION

SELECT hora_comienzo
FROM agenda_horas
INNER JOIN agenda_estables
ON agenda_horas.id_estables = agenda_estables.id_estable
WHERE agenda_estables.dia = "'.utf8_encode($dia).'"
AND agenda_estables.id_sucursal = "'.$sucursal.'"
AND hora_comienzo NOT IN (SELECT hora_comienzo
FROM agenda_horas_particulares
INNER JOIN agenda_particulares
ON agenda_horas_particulares.id_particular = agenda_particulares.id_particular WHERE
agenda_particulares.fecha = "'.($fecha).'" AND agenda_particulares.cancelado = 1)

ORDER BY hora_comienzo asc');

?>
<h2 class="horarios_1">Horarios: <?php echo strftime('%d/%m/%Y', strtotime($fecha)); ?></h2>


<?php if(date('d/m/Y') == strftime('%d/%m/%Y', strtotime($fecha))){
	$val_fec = 1;
}

?>
<select name="hora" class="hora_1" onchange="if ($(this).val() != 0) { $('#boton_confirmar').show(); $('#horario_reserva').val($(this).val()) };" class="input">
<!-- <select name="hora" style="width:307px; display:block" onchange="if ($(this).val() != 0) { $('#boton_confirmar').show(); $('#horario_reserva').val($(this).val()) };" class="input"> -->
	<option value="0">Seleccione horario</option>
	
	<?php $array_horario = $horarios->fetch_all(MYSQLI_ASSOC);
	foreach($array_horario as $horario) {

		$horario_ocupado = $connection->query('SELECT * FROM agendas WHERE fecha = "'.($fecha).'"  AND hora = "'.$horario['hora_comienzo'].'"');
		$ho = $horario_ocupado->fetch_all(MYSQLI_ASSOC);

		date_default_timezone_set ('America/Montevideo');
		$time = time();
		$hora_actual = date("H:i", $time);

		if (count($ho) == 0) {
			 if(($horario['hora_comienzo'] <= $hora_actual) && $val_fec == 1) {
				?>
				<option value="<?php echo $horario['hora_comienzo'];?>" disabled style="color:#c3ceda" ><?php echo $horario['hora_comienzo']; ?></option>
			<?php

			 }else{
				?>
				<option value="<?php echo $horario['hora_comienzo']; ?>"><?php echo $horario['hora_comienzo']; ?></option>
			<?php
			 }

		}
	}
	?>
</select>
