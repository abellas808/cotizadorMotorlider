<?php
// ***************************************************************************************************
// Chequeo que no se llame directamente
// ***************************************************************************************************
if (!isset($sistema_iniciado)) exit();

// ***************************************************************************************************
// Paginado
// ***************************************************************************************************
$pagina = isset($_GET['p']) ? intval($_GET['p']) : 1;
if ($pagina <= 0) {
	$pagina = 1;
}

// ***************************************************************************************************
// Busqueda
// ***************************************************************************************************
$busqueda = isset($_GET['b']) ? substr(trim($_GET['b']), 0, 30) : '';
$sql_b = '';

if ($busqueda != '') {
	$busqueda_array = explode(' ', $busqueda);

	for ($i = 0; $i < count($busqueda_array); $i++) {
		$term = trim($busqueda_array[$i]);
		if ($term == '') continue;

		$term = addslashes($term);

		$sql_b .= ' and (
			c.id_cotizaciones_generadas like "%' . $term . '%"
			or c.auto like "%' . $term . '%"
			or c.marca like "%' . $term . '%"
			or c.familia like "%' . $term . '%"
			or c.anio like "%' . $term . '%"
			or c.kilometros like "%' . $term . '%"
			or c.nombre like "%' . $term . '%"
			or c.telefono like "%' . $term . '%"
			or c.email like "%' . $term . '%"
			or c.fecha like "%' . $term . '%"
			or c.estado like "%' . $term . '%"
		)';
	}
}

$sql_b = trim($sql_b, ' and ');
if ($sql_b != '') $sql_b = ' and ' . $sql_b;

// ***************************************************************************************************
// Ordenado
// ***************************************************************************************************
$orden_campo = isset($_GET['o']) ? intval($_GET['o']) : 0;
$orden_dir = isset($_GET['od']) ? intval($_GET['od']) : 0;

switch ($orden_dir) {
	case 1:
		$sql_od = 'asc';
		$od_chr = '▲';
		break;
	default:
		$sql_od = 'desc';
		$od_chr = '▼';
}

switch ($orden_campo) {
	case 1:
		$sql_o = 'c.auto';
		break;
	case 2:
		$sql_o = 'c.anio';
		break;
	case 3:
		$sql_o = 'c.kilometros';
		break;
	case 4:
		$sql_o = 'c.nombre';
		break;
	case 5:
		$sql_o = 'c.telefono';
		break;
	case 6:
		$sql_o = 'c.email';
		break;
	case 7:
		$sql_o = 'c.fecha';
		break;
	case 8:
		$sql_o = 'c.estado';
		break;
	default:
		$sql_o = 'c.id_cotizaciones_generadas';
		$orden_campo = 0;
}

// ***************************************************************************************************
// Filtros fecha
// ***************************************************************************************************
$mes_get = isset($_GET['mes']) ? intval($_GET['mes']) : date('m');
$anio_get = isset($_GET['anio']) ? intval($_GET['anio']) : date('Y');

// ***************************************************************************************************
// Consulta
// ***************************************************************************************************
$sql_from = '
	FROM cotizaciones_generadas c
	WHERE MONTH(c.fecha) = ' . $mes_get . '
	  AND YEAR(c.fecha) = ' . $anio_get . '
	  ' . $sql_b . '
';

$listado = $db->query('
	SELECT SQL_CALC_FOUND_ROWS
		c.*
	' . $sql_from . '
	ORDER BY ' . $sql_o . ' ' . $sql_od . '
	LIMIT ' . (($pagina - 1) * $config['pagina_cant']) . ', ' . $config['pagina_cant'] . ';
');

$qry = $db->query_first('SELECT FOUND_ROWS() AS cantidad;');
$total = intval($qry['cantidad']);

$total_paginas = ceil($total / $config['pagina_cant']);

$cant_cotizaciones = $db->query_first('
	SELECT COUNT(*) AS cant
	' . $sql_from . '
');
?>
<?php require_once('sistema_cabezal.php'); ?>
<?php require_once('sistema_pre_contenido.php'); ?>

<div id="contenido_cabezal">

	<div class="pull-right">
		<input
			type="text"
			id="b"
			onkeypress="if (event.keyCode == 13) { window.location.href='?m=<?php echo $modulo['prefijo'] . '_l'; ?>&mes='+$('#mes').val()+'&anio='+$('#anio').val()<?php if ($orden_campo != 0) { echo '&o=' . $orden_campo; } ?><?php if ($orden_dir != 0) { echo '&od=' . $orden_dir; } ?><?php if (isset($inactivo) && $inactivo != 0) { echo '&e=' . $inactivo; } ?>&b='+$('#b').val(); }"
			value="<?php echo_s($busqueda); ?>"
			maxlength="30"
		/>

		<?php if ($busqueda != '') { ?>
			<button
				type="button"
				class="btn btn-default btn-small btn_cerrar"
				onclick="window.location.href='?m=<?php echo $modulo['prefijo'] . '_l'; ?>&mes='+$('#mes').val()+'&anio='+$('#anio').val()<?php if ($orden_campo != 0) { echo '&o=' . $orden_campo; } ?><?php if ($orden_dir != 0) { echo '&od=' . $orden_dir; } ?><?php if (isset($inactivo) && $inactivo != 0) { echo '&e=' . $inactivo; } ?>';"
			>X</button>
		<?php } ?>

		<button
			type="button"
			class="btn btn-default btn-small"
			onclick="window.location.href='?m=<?php echo $modulo['prefijo'] . '_l'; ?>&mes='+$('#mes').val()+'&anio='+$('#anio').val()<?php if ($orden_campo != 0) { echo '&o=' . $orden_campo; } ?><?php if ($orden_dir != 0) { echo '&od=' . $orden_dir; } ?><?php if (isset($inactivo) && $inactivo != 0) { echo '&e=' . $inactivo; } ?>&b='+$('#b').val();"
		>Buscar</button>
	</div>

	<h4 class="titulo"><?php echo $modulo['nombre']; ?></h4>
	<hr>

	<div style="margin-bottom: 10px; display: inline;">Mes:
		<select name="mes" id="mes" style="width: 110px" onchange="window.location.href='?m=<?php echo $modulo['prefijo']; ?>_l&mes='+$(this).val()+'&anio='+$('#anio').val()<?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?><?php if ($orden_campo != 0) { echo '&o=' . $orden_campo; } ?><?php if ($orden_dir != 0) { echo '&od=' . $orden_dir; } ?>'">
			<option value="1" <?php echo $mes_get == 1 ? 'selected' : ''; ?>>Enero</option>
			<option value="2" <?php echo $mes_get == 2 ? 'selected' : ''; ?>>Febrero</option>
			<option value="3" <?php echo $mes_get == 3 ? 'selected' : ''; ?>>Marzo</option>
			<option value="4" <?php echo $mes_get == 4 ? 'selected' : ''; ?>>Abril</option>
			<option value="5" <?php echo $mes_get == 5 ? 'selected' : ''; ?>>Mayo</option>
			<option value="6" <?php echo $mes_get == 6 ? 'selected' : ''; ?>>Junio</option>
			<option value="7" <?php echo $mes_get == 7 ? 'selected' : ''; ?>>Julio</option>
			<option value="8" <?php echo $mes_get == 8 ? 'selected' : ''; ?>>Agosto</option>
			<option value="9" <?php echo $mes_get == 9 ? 'selected' : ''; ?>>Setiembre</option>
			<option value="10" <?php echo $mes_get == 10 ? 'selected' : ''; ?>>Octubre</option>
			<option value="11" <?php echo $mes_get == 11 ? 'selected' : ''; ?>>Noviembre</option>
			<option value="12" <?php echo $mes_get == 12 ? 'selected' : ''; ?>>Diciembre</option>
		</select>
	</div>

	<div style="display: inline; margin-left: 10px;">Año:
		<select id="anio" name="anio" style="width: 110px" onchange="window.location.href='?m=<?php echo $modulo['prefijo']; ?>_l&mes='+$('#mes').val()+'&anio='+$(this).val()<?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?><?php if ($orden_campo != 0) { echo '&o=' . $orden_campo; } ?><?php if ($orden_dir != 0) { echo '&od=' . $orden_dir; } ?>'">
			<?php for ($i = 2021; $i <= date('Y'); $i++) : ?>
				<option value="<?php echo $i; ?>" <?php echo $i == $anio_get ? 'selected' : ''; ?>><?php echo $i; ?></option>
			<?php endfor; ?>
		</select>
	</div>

	<div style="margin-top: 10px;">
		<strong><?php echo intval($cant_cotizaciones['cant']); ?> Cotizaciones</strong>
	</div>

	<hr class="nb">
</div>

<div class="sep_titulo"></div>

<?php if ($total > 0) { ?>

	<?php if ($_SESSION[$config['codigo_unico']]['login_permisos']['mod'] > 1) { ?>
		<form id="form_listado" action="?m=<?php echo $modulo['prefijo'] . '_e'; ?>" method="post">
	<?php } ?>

	<table class="table table-hover">
		<thead>
			<tr>
				<th>
					<?php if ($orden_campo == 0) { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l&mes=<?php echo $mes_get; ?>&anio=<?php echo $anio_get; ?><?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?><?php if (isset($inactivo) && $inactivo != 0) { echo '&e=' . $inactivo; } ?>&o=0&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>"><strong>Codigo <?php echo $od_chr; ?></strong></a>
					<?php } else { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l&mes=<?php echo $mes_get; ?>&anio=<?php echo $anio_get; ?><?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?><?php if (isset($inactivo) && $inactivo != 0) { echo '&e=' . $inactivo; } ?>&o=0">Codigo</a>
					<?php } ?>
				</th>

				<th>
					<?php if ($orden_campo == 1) { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l&mes=<?php echo $mes_get; ?>&anio=<?php echo $anio_get; ?><?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?>&o=1&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>"><strong>Auto <?php echo $od_chr; ?></strong></a>
					<?php } else { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l&mes=<?php echo $mes_get; ?>&anio=<?php echo $anio_get; ?><?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?>&o=1">Auto</a>
					<?php } ?>
				</th>

				<th>
					<?php if ($orden_campo == 2) { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l&mes=<?php echo $mes_get; ?>&anio=<?php echo $anio_get; ?><?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?>&o=2&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>"><strong>Año <?php echo $od_chr; ?></strong></a>
					<?php } else { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l&mes=<?php echo $mes_get; ?>&anio=<?php echo $anio_get; ?><?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?>&o=2">Año</a>
					<?php } ?>
				</th>

				<th>
					<?php if ($orden_campo == 3) { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l&mes=<?php echo $mes_get; ?>&anio=<?php echo $anio_get; ?><?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?>&o=3&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>"><strong>Km <?php echo $od_chr; ?></strong></a>
					<?php } else { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l&mes=<?php echo $mes_get; ?>&anio=<?php echo $anio_get; ?><?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?>&o=3">Km</a>
					<?php } ?>
				</th>

				<th>
					<?php if ($orden_campo == 4) { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l&mes=<?php echo $mes_get; ?>&anio=<?php echo $anio_get; ?><?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?>&o=4&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>"><strong>Nombre <?php echo $od_chr; ?></strong></a>
					<?php } else { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l&mes=<?php echo $mes_get; ?>&anio=<?php echo $anio_get; ?><?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?>&o=4">Nombre</a>
					<?php } ?>
				</th>

				<th>
					<?php if ($orden_campo == 5) { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l&mes=<?php echo $mes_get; ?>&anio=<?php echo $anio_get; ?><?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?>&o=5&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>"><strong>Telefono <?php echo $od_chr; ?></strong></a>
					<?php } else { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l&mes=<?php echo $mes_get; ?>&anio=<?php echo $anio_get; ?><?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?>&o=5">Telefono</a>
					<?php } ?>
				</th>

				<th>
					<?php if ($orden_campo == 6) { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l&mes=<?php echo $mes_get; ?>&anio=<?php echo $anio_get; ?><?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?>&o=6&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>"><strong>Email <?php echo $od_chr; ?></strong></a>
					<?php } else { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l&mes=<?php echo $mes_get; ?>&anio=<?php echo $anio_get; ?><?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?>&o=6">Email</a>
					<?php } ?>
				</th>

				<th>
					<?php if ($orden_campo == 7) { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l&mes=<?php echo $mes_get; ?>&anio=<?php echo $anio_get; ?><?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?>&o=7&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>"><strong>Fecha <?php echo $od_chr; ?></strong></a>
					<?php } else { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l&mes=<?php echo $mes_get; ?>&anio=<?php echo $anio_get; ?><?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?>&o=7">Fecha</a>
					<?php } ?>
				</th>

				<th>
					<?php if ($orden_campo == 8) { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l&mes=<?php echo $mes_get; ?>&anio=<?php echo $anio_get; ?><?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?>&o=8&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>"><strong>Estado <?php echo $od_chr; ?></strong></a>
					<?php } else { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l&mes=<?php echo $mes_get; ?>&anio=<?php echo $anio_get; ?><?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?>&o=8">Estado</a>
					<?php } ?>
				</th>
			</tr>
		</thead>

		<tfoot>
			<tr>
				<td height="30" colspan="9" valign="bottom">
					<div class="info_seleccionados">
						<span id="cantidad_seleccionados"></span>
						<?php if ($_SESSION[$config['codigo_unico']]['login_permisos']['mod'] > 1) { ?>
							- <input type="button" class="btn btn-danger btn-small" value="Eliminar" onclick="eliminar();" />
						<?php } ?>
					</div>

					<div class="info_listados">Total: <strong><?php echo $total; ?></strong></div>

					<?php if ($total_paginas > 1) { ?>
						<div class="paginas">
							<?php if ($pagina > 1) { ?>
								<a href="?m=<?php echo $modulo['prefijo']; ?>_l&p=<?php echo $pagina - 1; ?>&mes=<?php echo $mes_get; ?>&anio=<?php echo $anio_get; ?><?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?><?php if ($orden_campo != 0) { echo '&o=' . $orden_campo; } ?><?php if ($orden_dir != 0) { echo '&od=' . $orden_dir; } ?><?php if (isset($inactivo) && $inactivo != 0) { echo '&e=' . $inactivo; } ?>">< anterior</a>
							<?php } ?>

							<select id="select_pagina" class="input-mini">
								<?php for ($i = 1; $i <= $total_paginas; $i++) { ?>
									<option value="<?php echo $i; ?>" <?php if ($i == $pagina) echo 'selected="selected"'; ?>><?php echo $i; ?></option>
								<?php } ?>
							</select> / <?php echo $total_paginas; ?>

							<?php if ($pagina < $total_paginas) { ?>
								<a href="?m=<?php echo $modulo['prefijo']; ?>_l&p=<?php echo $pagina + 1; ?>&mes=<?php echo $mes_get; ?>&anio=<?php echo $anio_get; ?><?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?><?php if ($orden_campo != 0) { echo '&o=' . $orden_campo; } ?><?php if ($orden_dir != 0) { echo '&od=' . $orden_dir; } ?><?php if (isset($inactivo) && $inactivo != 0) { echo '&e=' . $inactivo; } ?>">siguiente ></a>
							<?php } ?>
						</div>
					<?php } ?>
				</td>
			</tr>
		</tfoot>

		<tbody>
			<?php while ($entrada = $db->fetch_array($listado)) { ?>
				<?php
				$estado = trim((string)($entrada['estado'] ?? ''));
				$estado_color = '#666';
				$estado_bg = '#f2f2f2';

				if ($estado === 'FINALIZADA') {
					$estado_color = '#155724';
					$estado_bg = '#d4edda';
				} elseif ($estado === 'PENDIENTE') {
					$estado_color = '#856404';
					$estado_bg = '#fff3cd';
				}

				$autoMostrar = trim((string)($entrada['auto'] ?? ''));
				if ($autoMostrar === '') $autoMostrar = '-';
				?>
				<tr>
					<td>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_v&i=<?php echo $entrada['id_cotizaciones_generadas']; ?>">
							<?php echo_s($entrada['id_cotizaciones_generadas']); ?>
						</a>
					</td>

					<td><?php echo_s($autoMostrar); ?></td>
					<td><?php echo_s($entrada['anio']); ?></td>
					<td><?php echo_s($entrada['kilometros']); ?></td>
					<td><?php echo_s($entrada['nombre']); ?></td>
					<td><?php echo_s($entrada['telefono']); ?></td>
					<td><?php echo_s($entrada['email']); ?></td>
					<td><?php echo_s(strftime('%d/%m/%Y', strtotime($entrada['fecha']))); ?></td>
					<td>
						<span style="display:inline-block; padding:3px 8px; border-radius:10px; font-size:11px; font-weight:bold; color:<?php echo $estado_color; ?>; background:<?php echo $estado_bg; ?>;">
							<?php echo_s($estado != '' ? $estado : 'SIN ESTADO'); ?>
						</span>
					</td>
				</tr>
			<?php } ?>
		</tbody>
	</table>

	<?php if ($_SESSION[$config['codigo_unico']]['login_permisos']['mod'] > 1) { ?>
		</form>
	<?php } ?>

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
			window.location.href = '?m=<?php echo $modulo['prefijo']; ?>_l&p=' + $(this).val() +
				'&mes=<?php echo $mes_get; ?>&anio=<?php echo $anio_get; ?>' +
				'<?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?>' +
				'<?php if ($orden_campo != 0) { echo '&o=' . $orden_campo; } ?>' +
				'<?php if ($orden_dir != 0) { echo '&od=' . $orden_dir; } ?>' +
				'<?php if (isset($inactivo) && $inactivo != 0) { echo '&e=' . $inactivo; } ?>';
		});

		function eliminar() {
			if (confirm('¿Esta seguro que desea eliminar los elementos seleccionados?')) {
				$('#form_listado').submit();
			}
		}
	</script>

<?php } else { ?>

	<?php if ($busqueda != '') { ?>
		<div class="info_resultado">
			<div class="tc">No se encontraron elementos con <strong>"<?php echo_s($busqueda); ?>"</strong>.</div>
			<div class="tc">
				<a href="?m=<?php echo $modulo['prefijo']; ?>_l&mes=<?php echo $mes_get; ?>&anio=<?php echo $anio_get; ?><?php if ($orden_campo != 0) { echo '&o=' . $orden_campo; } ?><?php if ($orden_dir != 0) { echo '&od=' . $orden_dir; } ?><?php if (isset($inactivo) && $inactivo != 0) { echo '&e=' . $inactivo; } ?>">Ver todos</a>
			</div>
		</div>
	<?php } else { ?>
		<div class="info_resultado">
			<div class="tc">No hay elementos para listar.</div>
		</div>
	<?php } ?>

<?php } ?>

<?php require_once('sistema_post_contenido.php'); ?>