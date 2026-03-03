<?php
// ***************************************************************************************************
// Chequeo que no se llame directamente
// ***************************************************************************************************

if (!isset($sistema_iniciado)) exit();

// ***************************************************************************************************
// Paginado
// ***************************************************************************************************
$pagina = intval($_GET['p']);
if ($pagina == 0) {
	$pagina = 1;
}

// ***************************************************************************************************
// Busqueda
// ***************************************************************************************************
$sql_b = '';
if ($_GET['b'] != '') {
	$busqueda = substr($_GET['b'], 0, 30);
	$busqueda_array = explode(' ', $busqueda);
	for ($i = 0; $i <= count($busqueda_array); $i++) {
		$sql_b .= ' and (marca like "%' . $busqueda_array[$i] . '%" or modelo like "%' . $busqueda_array[$i] . '%" or anio_desde like "%' . $busqueda_array[$i] . '%" or anio_hasta like "%' . $busqueda_array[$i] . '%")';
	}
}


// ***************************************************************************************************
// Ordenado
// ***************************************************************************************************
$orden_campo = intval($_GET['o']);
$orden_dir = intval($_GET['od']);

switch ($orden_dir) {
	case 1:
		$sql_od = 'desc';
		$od_chr = '▼';
		break;
	default:
		$sql_od = 'asc';
		$od_chr = '▲';
}

switch ($orden_campo) {
	case 1:
		$sql_o = 'marca';
		break;
	case 2:
		$sql_o = 'modelo';
		break;
	case 3:
		$sql_o = 'anio_desde';
		break;
	case 4:
		$sql_o = 'anio_hasta';
		break;
	default:
		$sql_o = 'id';
		$orden_campo = 0;
}

$sql_b = trim($sql_b, ' and ');

if ($sql_b != '') $sql_b = ' and ' . $sql_b;

// ***************************************************************************************************
// Consulta
// ***************************************************************************************************
$listado = $db->query('SELECT SQL_CALC_FOUND_ROWS * FROM variables_usd' . $sql_b . ' order by ' . $sql_o . ' ' . $sql_od . ' limit ' . (($pagina - 1) * $config['pagina_cant']) . ', ' . $config['pagina_cant'] . ';');

$qry = $db->query_first('select FOUND_ROWS() as cantidad;');
$total = $qry['cantidad'];

$total_paginas = ceil($total / $config['pagina_cant']);

?>
<?php

require_once('sistema_cabezal.php');

?>
<?php

require_once('sistema_pre_contenido.php');

?>
<div id="contenido_cabezal">

	<div class="pull-right">
</div>
	<h4 class="titulo"><?php echo $modulo['nombre']; ?></h4>
	<hr>
	<div class="pull-right">
	</div>
	<?php
	if ($_SESSION[$config['codigo_unico']]['login_permisos']['fcot'] > 1) {
	?>

		<button type="button" class="btn btn-primary btn-small" onclick="window.location.href='?m=<?php echo $modulo['prefijo']; ?>_c';">Nuevo</button>
	<?php
	}
	?>
	<hr class="nb">
</div>
<div class="sep_titulo"></div>
<?php
if ($total > 0) {
?>
	
	<form id="form_listado" action="?m=<?php echo $modulo['prefijo'] . '_e'; ?>" method="post">
		<?php require_once("modulos/usd/nomianles/empadronamiento.php"); ?>
		<?php require_once("modulos/usd/nomianles/servicio.php"); ?>
		<?php require_once("modulos/usd/nomianles/correa.php"); ?>
		<?php require_once("modulos/usd/nomianles/bateria.php"); ?>
		<?php require_once("modulos/usd/nomianles/piezas.php"); ?>
		<?php require_once("modulos/usd/nomianles/neumaticos.php"); ?>
		<?php require_once("modulos/usd/nomianles/tazas_llantas.php"); ?>
		<?php require_once("modulos/usd/nomianles/parabrisas.php"); ?>
		<?php require_once("modulos/usd/nomianles/faros.php"); ?>
		<?php require_once("modulos/usd/nomianles/aire.php"); ?>
		<?php require_once("modulos/usd/nomianles/sensor.php"); ?>
		<?php require_once("modulos/usd/nomianles/reserva.php"); ?>
		<?php require_once("modulos/usd/nomianles/radio.php"); ?>
		<?php require_once("modulos/usd/nomianles/alarma.php"); ?>
		<?php require_once("modulos/usd/nomianles/vidrios.php"); ?>
		<?php require_once("modulos/usd/nomianles/espejos.php"); ?>
		<?php require_once("modulos/usd/nomianles/llaves.php"); ?>
		<?php require_once("modulos/usd/nomianles/tapizado.php"); ?>
	</form>
	<script>
		$('input[name="e_sel[]"]').bind('click', function(e) {

			$(this).closest('tr').toggleClass('info');
			var t = $('tr.info').length;
			if (t > 0) {
				$('.info_seleccionados').show();
				t == 1 ? $('#cantidad_seleccionados').html('1 elemento seleccionado') : $('#cantidad_seleccionados').html(t + ' elementos seleccionados');
			} else {
				$('.info_seleccionados').hide();
			}

		});

		$('#select_pagina').bind('change', function(e) {

			window.location.href = '?m=<?php echo $modulo['prefijo']; ?>_l&p=' + $(this).val() + '<?php if ($busqueda != '') {
																										echo '&b=' . $busqueda;
																									} ?><?php if ($orden_campo != 0) {
																											echo '&o=' . $orden_campo;
																										} ?><?php if ($orden_dir != 0) {
																																									echo '&od=' . $orden_dir;
																																								} ?><?php if ($inactivo != 0) {
																																																								echo '&e=' . $inactivo;
																																																							} ?>';

		});

		function eliminar() {

			if (confirm('¿Esta seguro que desea eliminar los elementos seleccionados?')) {
				$('#form_listado').submit();
			}

		}
	</script>
	<?php
} else {
	if ($busqueda != '') {
	?>
		<div class="info_resultado">
			<div class="tc">No se encontraron elementos con <strong>"<?php echo_s($busqueda); ?>"</strong>.</div>
			<div class="tc"><a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($orden_campo != 0) {
																				echo '&o=' . $orden_campo;
																			} ?><?php if ($orden_dir != 0) {
																					echo '&od=' . $orden_dir;
																				} ?><?php if ($inactivo != 0) {
																																				echo '&e=' . $inactivo;
																																			} ?>">Ver todos</a></div>
		</div>
	<?php
	} else {
	?>
		<div class="info_resultado">
			<div class="tc">No hay elementos para listar.</div>
			<div class="tc"><a href="?m=<?php echo $modulo['prefijo']; ?>_c">Nuevo</a></div>
		</div>
<?php
	}
}
?>
<?php

require_once('sistema_post_contenido.php');

?>