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

$bloqueo_dia_completo = false;
$horas_bloqueadas = array();

$bloqueos = $connection->query('
	SELECT hora
	FROM agenda_bloqueos
	WHERE id_sucursal = "'.$sucursal.'"
	AND fecha = "'.$fecha.'"
	AND activo = 1
');

if ($bloqueos) {
	while ($bloqueo = $bloqueos->fetch_assoc()) {
		if ($bloqueo['hora'] === null || $bloqueo['hora'] === '' || $bloqueo['hora'] == '00:00:00') {
			$bloqueo_dia_completo = true;
			break;
		}

		$horas_bloqueadas[] = substr($bloqueo['hora'], 0, 5);
	}
}

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

		if ($bloqueo_dia_completo || in_array(substr($horario['hora_comienzo'], 0, 5), $horas_bloqueadas)) {
			continue;
		}

		$horario_ocupado = $connection->query('
			SELECT id_agenda
			FROM agendas
			WHERE id_sucursal = "'.$sucursal.'"
			AND fecha = "'.($fecha).'"
			AND LEFT(hora, 5) = "'.substr($horario['hora_comienzo'], 0, 5).'"
			AND (cancelado = 0 OR cancelado IS NULL)
			LIMIT 1
		');
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
