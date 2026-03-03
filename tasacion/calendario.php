<?php
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Cache-Control: no-cache");
header("Pragma: no-cache");

include('./../config.php');
include('./../config/config.inc.php');
include('./../adm/includes/funciones.php');

$sucursal = $_GET['s'];

$connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$connection->set_charset('utf8');

?>

<link href="css/style_calendario.css?v=z" rel="stylesheet" type="text/css" />
<?php
$sucursal = intval($_GET['s']);
?>
	<script type="text/javascript">

		function cambiarCalendario(mes, anio, sucursal) {
			var sucursal = "<?php echo $sucursal; ?>";
			var oneDay = 24 * 60 * 60 * 1000; // hours*minutes*seconds*milliseconds
			var reservaDate = new Date(anio, mes - 1, 1);
			var actualDate = new Date();
			var diffDays = Math.round(Math.abs((actualDate.getTime() - reservaDate.getTime()) / (oneDay)));

			if (diffDays >= 90) {
				alert('No es posible reservar un horario con más de 90 días de anticipación.');
			} else {
				$('#calendario').load('calendario.php?mes=' + mes + '&anio=' + anio + '&s=' + sucursal);
			}

			return true;
		}

		function mostrar_horarios(fecha, tipo, sucursal) {
			var sucursal = "<?php echo $sucursal; ?>";
			$('.cuadrado').css('border', 'none');
			$('#fecha_' + fecha).css('border', 'thin inset #FFFFFF');
			$('#fecha_reserva').val(fecha);
			$('#horarios').load('mostrar_horarios.php?f=' + fecha + '&t=' + tipo + '&s=' + sucursal);
		}

		function reservar_horario(horario) {
			$('#horario_reserva').val(horario);
			confirm('Confirma que desea reservar para el día ' + $('#fecha_reserva').val() + ' a las ' + $('#horario_reserva').val() + 'horas ?');
		}

		function alertar() {
			alert('Debe elegir días en los que hay horas disponibles,los de color verde.');
		}

		function alertar2() {
			alert('No puede seleccionar horarios con fecha anterior a la de hoy.');
		}
	</script>

	<?php

	$anio = isset($_GET['anio']) ? intval($_GET['anio']) : intval(date('Y'));
	$mes = isset($_GET['mes']) ? intval($_GET['mes']) : intval(date('m'));


	// CREO MATRIZ CALENDARIO:

	$year = $anio;
	$month = $mes;
	$day = date('j');

	$daysInMonth 	= date("t", mktime(0, 0, 0, $month, 1, $year));
	$firstDay 		= date("w", mktime(0, 0, 0, $month, 1, $year));

	if ($firstDay == 0)
		$firstDay = 7;

	$tempDays 		= $firstDay + $daysInMonth;
	$weeksInMonth 	= ceil($tempDays / 7);

	$calendar = array();

	for ($i = 1; $i <= $daysInMonth + $firstDay; $i++) {
		$calendar[$i] = $i - $firstDay + 1;
	}

	$meses = array("Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre");


	?>

	<div id="div_calendario" class="columna">
		<p>Seleccione el día para su visita</p>
		<table border="1" cellpadding="2" cellspacing="2">
			<tr height="35">
				<th colspan='7' height="35" class="mes_ano_1">
					<?php
					$onclick = '';
					if ((($month - 1) >= (date('m'))) || ($year > date('Y'))) {
						if (($month) == 1)
							$onclick = 'cambiarCalendario(12,' . ($year - 1) . ')';
						else
							$onclick = 'cambiarCalendario(' . ($month - 1) . ',' . $year . ')';
					} else
						$onclick = 'alertar2();';
					?>
					<button type="button" class="flechita_izquierda_1" onclick="<?php echo $onclick; ?>">
						< </button> <?php
									echo $meses[$month - 1] . ' ' . $year;
									$onclick = '';
									if (($month) == 12)
										$onclick = 'cambiarCalendario(1,' . ($year + 1) . ')';
									else
										$onclick = 'cambiarCalendario(' . ($month + 1) . ',' . $year . ')';
									?> <button type="button" class="flechita_derecha_1" onclick="<?php echo $onclick; ?>"> >
							</button>
				</th>
			</tr>
			<tr height="35">
				<th class="cuadrado">Lun</th>
				<th class="cuadrado">Mar</th>
				<th class="cuadrado">Mie</th>
				<th class="cuadrado">Jue</th>
				<th class="cuadrado">Vie</th>
				<th class="cuadrado">Sab</th>
				<th class="cuadrado">Dom</th>
			</tr>
			<?php
			$numero_mes = (intval($month) < 10) ? '0' . $month : $month;
			$j = 1;
			$fecha_actual = date("d-m-Y");
			$dia_actual_mas_dos = date("d",strtotime($fecha_actual."+ 2 days"));
			for ($w = 0; $w <= $weeksInMonth; $w++) {
				echo "<tr>";
				for ($i = 0; $i < 7; $i++) {
					if (isset($calendar[$j])) {
						if ($calendar[$j] > 0 && $calendar[$j] <= $daysInMonth) {
							$clase = '';
							$onclick = '';
							
							if ((($calendar[$j] < $dia_actual_mas_dos) && ($month == date('m'))) || (($month < date('m')) && ($year == date('y'))) || ($year < date('y'))) {
								$clase = 'invalido';
								$onclick = " onclick='alertar();'";
							} else {
								$now = time(); // or your date as well
								$your_date = strtotime($year . "-" . $month . "-" . $calendar[$j]);
								$datediff = $your_date - $now;
								$cantdias = floor($datediff / (60 * 60 * 24));
								if ($cantdias >= 90) {

									// ver si lo pinto distinto en caso de que haya consulta
									$clase = 'invalido';
									$onclick = " onclick='alert(\"No puedes reservar horarios con más de 90 días de anticipación.\");'";
								} else {

									$fecha_mysql = $year . "-" . $month . "-" . $calendar[$j];
									setlocale(LC_ALL, "es_ES@euro", "es_ES", "esp");
									$date = DateTime::createFromFormat("Y-m-d", $fecha_mysql);
									$dia = strftime("%A", $date->getTimestamp());

									// $horario_estable = $db->query_first('SELECT * FROM agenda_estables WHERE dia = "'.utf8_encode($dia).'"');

									
									$horario_estable = $connection->query('SELECT * FROM agenda_estables
									INNER JOIN agenda_horas
									ON agenda_estables.id_sucursal = "' . $sucursal . '"
									where agenda_horas.id_estables = agenda_estables.id_estable
									and agenda_estables.dia = "' . utf8_encode($dia) . '"');

									if ($horario_estable) {

										$sql_aux = $connection->query('SELECT COUNT(*) as cantidad FROM agenda_estables
										INNER JOIN agenda_horas
										ON agenda_estables.id_sucursal = "' . $sucursal . '"
										where agenda_horas.id_estables = agenda_estables.id_estable
										and agenda_estables.dia = "' . utf8_encode($dia) . '"');
										$sql_aux = $sql_aux->fetch_array(MYSQLI_ASSOC);
										$cantidad_turnos_disponibles = $sql_aux['cantidad'];

										$sql_aux = $connection->query('SELECT COUNT(*) as cantidad FROM agendas
										WHERE id_sucursal = "' . $sucursal . '"
										and fecha = "' . $fecha_mysql . '"');
										$sql_aux = $sql_aux->fetch_array(MYSQLI_ASSOC);
										$cantidad_turnos_reservados = $sql_aux['cantidad'];


										/*$sql_aux = $db->query_first('SELECT COUNT(*) as cantidad FROM agenda_particulares
										WHERE id_sucursal = "' . $sucursal . '"
										and fecha = "' . $fecha_mysql . '" AND cancelado = 1');
										$cantidad_turnos_cancelados = $sql_aux['cantidad'];*/

										$cantidad_turnos_cancelados = 0;

										if (($cantidad_turnos_reservados + $cantidad_turnos_cancelados) < $cantidad_turnos_disponibles) {

											$clase = 'verde_central';
											$onclick = " onclick='mostrar_horarios(\"" . $year . "-" . $numero_mes . "-" . $calendar[$j] . "\", \"estable\");'";
										} else {
											$clase = 'cuadrado_rojo';
											$onclick = " onclick='alertar();'";
										}
									} else {
										$clase = 'gris';
										$onclick = " onclick='alertar();'";
									}
									/////////////////////////////////////////////////////////////// HORARIOS PARTICULARES
									$horario_particular = $connection->query('SELECT * FROM agenda_particulares WHERE fecha = "' . $fecha_mysql . '" and id_sucursal = "' . $sucursal . '"');
									$hp = $horario_particular->fetch_all(MYSQLI_ASSOC);
									if (count($hp) > 0) {
										$horario_particular = $horario_particular->fetch_array(MYSQLI_ASSOC);
										if ($horario_particular['cancelado'] == 1 && $horario_particular['hora_comienzo'] == '') { // hora_comienzo vacia es para cancelar todo el dia
											$clase = 'gris';
											$onclick = " onclick='alertar();'";
										} else {

											
											$sql_aux = $connection->query('SELECT COUNT(*) as cantidad FROM agenda_particulares WHERE fecha = "' . $fecha_mysql . '" AND cancelado = 0 and id_sucursal = "' . $sucursal . '"');
											$sql_aux = $sql_aux->fetch_array(MYSQLI_ASSOC);
											$cantidad_turnos_disponibles = $sql_aux['cantidad'];

											$sql_aux = $connection->query('SELECT COUNT(*) as cantidad FROM agenda_estables WHERE dia = "' . utf8_encode($dia) . '" and id_sucursal = "' . $sucursal . '"');

											// tengo que considerar horarios estables y  particulares !!
											$sql_aux = $sql_aux->fetch_array(MYSQLI_ASSOC);
											$cantidad_turnos_disponibles += $sql_aux['cantidad'];


											// considerar esta para horaios particulares
											$sql_auxEstables = $connection->query('SELECT COUNT(*) as cantidad FROM agenda_estables
											INNER JOIN agenda_horas
											ON agenda_estables.id_sucursal = "' . $sucursal . '"
											where agenda_horas.id_estables = agenda_estables.id_estable
											and agenda_estables.dia = "' . utf8_encode($dia) . '"');
											$sql_auxEstables = $sql_auxEstables->fetch_array(MYSQLI_ASSOC);
											$cantidad_turnos_estables = $sql_auxEstables['cantidad'];

											// considerar esta para comparar con la primera
											$sql_aux = $connection->query('SELECT COUNT(*) as cantidad FROM agendas WHERE fecha = "' . $fecha_mysql . '"  and id_sucursal = "' . $sucursal . '"');
											$sql_aux = $sql_aux->fetch_array(MYSQLI_ASSOC);
											$cantidad_turnos_reservados = $sql_aux['cantidad'];

											$sql_aux = $connection->query('SELECT COUNT(*) as cantidad FROM agenda_particulares WHERE fecha = "' . $fecha_mysql . '" AND cancelado = 1 and id_sucursal = "' . $sucursal . '" ');
											$sql_aux = $sql_aux->fetch_array(MYSQLI_ASSOC);
											$cantidad_turnos_cancelados = $sql_aux['cantidad'];

											// turnos particulares disponibles
											$sql_auxDISPONIBLES = $connection->query('SELECT COUNT(*) as cantidad FROM agenda_particulares WHERE fecha = "' . $fecha_mysql . '" AND cancelado = 1 and id_sucursal = "' . $sucursal . '" ');
											$sql_auxDISPONIBLES = $sql_auxDISPONIBLES->fetch_array(MYSQLI_ASSOC);
											$cantidad_turnos_particulares_disponibles = $sql_auxDISPONIBLES['cantidad'];

											//SACANDO ID PERTICULAR
											$query_id_particular = $connection->query('SELECT * FROM agenda_particulares where fecha = "' . $fecha_mysql . '" AND cancelado = 0 and id_sucursal = "' . $sucursal . '";');
											$query_id_particular = $query_id_particular->fetch_array(MYSQLI_ASSOC);
											//var_dump($query_id_particular);die;
											/*if ($query_id_particular > 0) {
												$result = $query_id_particular->fetch_array($query_id_particular);
												$id_particular = $result['id_particular'];
											}*/

											//Obteniendo turnos PARTICULARES// turnos particulares disponibles
											$sql_turnos_particulares = $connection->query('SELECT COUNT(*) as cantidad FROM agenda_horas_particulares WHERE id_particular = "' . $id_particular . '" ');
											$sql_turnos_particulares = $sql_turnos_particulares->fetch_array(MYSQLI_ASSOC);
											$cantidad_tpd = $sql_turnos_particulares['cantidad'];

											if (($cantidad_turnos_reservados + $cantidad_turnos_cancelados) < $cantidad_turnos_disponibles) {
												$clase = 'verde_central';
												$onclick = " onclick='mostrar_horarios(\"" . $year . "-" . $numero_mes . "-" . $calendar[$j] . "\", \"particular\");'";
											} else {
												$clase = 'cuadrado_rojo';
												$onclick = " onclick='alertar();'";
											}
											if (($cantidad_turnos_reservados < $cantidad_turnos_estables) || ($cantidad_tpd)) {
												$clase = 'verde_central';
												$onclick = " onclick='mostrar_horarios(\"" . $year . "-" . $numero_mes . "-" . $calendar[$j] . "\", \"particular\");'";
											} else {
												$clase = 'cuadrado_rojo';
												$onclick = " onclick='alertar();'";
											}
											
										}
									}
								}
							}
							echo "<td id='fecha_" . $year . "-" . $numero_mes . "-" . $calendar[$j] . "' align='center' class='cuadrado " . $clase . "' " . $onclick . ">" . $calendar[$j] . "</a></td>";
						} else {
							echo "<td align='center' class='cuadrado'></td>";
						}
					}
					/*} else {
						echo "<td align='center' class='cuadrado'></td>";
					}*/
					$j++;
				}
				echo "</tr>";
			}
			?>
		</table>

		<div id="referencia_colores" style="text-align: initial;">
			<p style="margin-bottom:0px;"><span><img src="/img/c_disp.jpg" width="11" height="11" style="padding-bottom: 3px; margin-right:15px" /></span>Días disponibles</p>
			<p style="margin-bottom:0px;"><span><img src="/img/c_nodisp.jpg" width="11" height="11" style="padding-bottom: 3px; margin-right:15px" /></span>Días no disponibles</p>
			<p style="margin-bottom:0px;"><span><img src="/img/c_comp.jpg" width="11" height="11" style="padding-bottom: 3px; margin-right:15px" /></span>Días completos</p>
		</div>

	</div>

	<div id="horarios" class="columna">

		<h2 class="horarios_2">Horarios:</h2>

		<select name="hora" class="hora_2" disabled class="input">
			<option value="0">Seleccione horario</option>
		</select>

	</div>

	<!--<div class="columna" style="padding-bottom: 50px">
        <button type="button" class="tasar" onclick="agendar()" style="background-color: #38bb38;border: 1px solid #174817;">
            <i class="fab fa-whatsapp" style="color: #fff !important; font-style: normal;"></i>     Agendar</button>
    </div>-->

	<div class="columna hidden-xs hidden-sm visible-md visible-lg" style="height: 80px;">
    </div>

    <div class="columna">
        <button type="button" class="tasar" onclick="continuar_agenda()" id="boton_confirmar" style="display: none;">Continuar</button>
    </div>

	<div style="clear:both;"></div>