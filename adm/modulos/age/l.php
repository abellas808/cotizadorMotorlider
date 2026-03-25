<?php
// ***************************************************************************************************
// Chequeo que no se llame directamente
// ***************************************************************************************************
if (!isset($sistema_iniciado)) exit();

// ***************************************************************************************************
// Paginado
// ***************************************************************************************************
$pagina = intval($_GET['p'] ?? 0);
if ($pagina <= 0) {
	$pagina = 1;
}

// ***************************************************************************************************
// Búsqueda
// ***************************************************************************************************
$busqueda = '';
$sql_b = '';

if (!empty($_GET['b'])) {
	$busqueda = substr(trim($_GET['b']), 0, 30);
	$busqueda_array = array_filter(explode(' ', $busqueda));

	foreach ($busqueda_array as $term) {
		$term = trim($term);
		if ($term === '') continue;

		$sql_b .= ' and (
			nombre like "%' . $term . '%"
			or ci like "%' . $term . '%"
			or auto like "%' . $term . '%"
			or email like "%' . $term . '%"
			or hora like "%' . $term . '%"
			or fecha like "%' . $term . '%"
		)';
	}
}

// ***************************************************************************************************
// Ordenado
// ***************************************************************************************************
$orden_campo = intval($_GET['o'] ?? 0);

// por defecto DESC
$orden_dir = isset($_GET['od']) ? intval($_GET['od']) : 0;

switch ($orden_dir) {
	case 1:
		$sql_od = 'asc';
		$od_chr = '▲';
		break;
	default:
		$sql_od = 'desc';
		$od_chr = '▼';
		$orden_dir = 0;
}

switch ($orden_campo) {
	case 1:
		$sql_o = 'id_agenda';
		break;
	case 2:
		$sql_o = 'hora';
		break;
	case 3:
		$sql_o = 'auto';
		break;
	case 4:
		$sql_o = 'nombre';
		break;
	case 5:
		$sql_o = 'ci';
		break;
	case 6:
		$sql_o = 'email';
		break;
	default:
		$sql_o = 'fecha';
		$orden_campo = 0;
}

$sql_b = trim($sql_b);
if ($sql_b != '') $sql_b = ' ' . $sql_b;

// por si existe en la plantilla general
$inactivo = intval($_GET['e'] ?? 0);

// URL de retorno al listado actual
$return_url = '?m=' . $modulo['prefijo'] . '_l'
	. '&p=' . $pagina
	. ($busqueda != '' ? '&b=' . urlencode($busqueda) : '')
	. '&o=' . $orden_campo
	. '&od=' . $orden_dir
	. ($inactivo != 0 ? '&e=' . $inactivo : '');

// ***************************************************************************************************
// Consulta
// ***************************************************************************************************
$listado = $db->query(
	'SELECT SQL_CALC_FOUND_ROWS * 
	 FROM agendas 
	 WHERE 1=1 ' . $sql_b . '
	 ORDER BY ' . $sql_o . ' ' . $sql_od . '
	 LIMIT ' . (($pagina - 1) * $config['pagina_cant']) . ', ' . $config['pagina_cant'] . ';'
);

$qry = $db->query_first('SELECT FOUND_ROWS() as cantidad;');
$total = intval($qry['cantidad'] ?? 0);

$total_paginas = ($config['pagina_cant'] > 0) ? ceil($total / $config['pagina_cant']) : 1;
if ($total_paginas <= 0) $total_paginas = 1;
?>
<?php require_once('sistema_cabezal.php'); ?>
<?php require_once('sistema_pre_contenido.php'); ?>

<div id="contenido_cabezal">

	<div class="pull-right">
		<input
			type="text"
			id="b"
			onkeypress="if (event.keyCode == 13) { buscarListado(); }"
			value="<?php echo_s($busqueda); ?>"
			maxlength="30"
		/>
		<?php if ($busqueda != '') { ?>
			<button
				type="button"
				class="btn btn-default btn-small btn_cerrar"
				onclick="window.location.href='?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($orden_campo != 0) { echo '&o=' . $orden_campo; } ?><?php echo '&od=' . $orden_dir; ?><?php if ($inactivo != 0) { echo '&e=' . $inactivo; } ?>';"
			>X</button>
		<?php } ?>
		<button type="button" class="btn btn-default btn-small" onclick="buscarListado();">Buscar</button>
	</div>

	<h4 class="titulo"><?php echo $modulo['nombre']; ?></h4>
	<hr>
	<hr class="nb">
</div>

<div class="sep_titulo"></div>

<?php if ($total > 0) { ?>
	<?php if ($_SESSION[$config['codigo_unico']]['login_permisos']['mod'] > 1) { ?>
		<form id="form_listado" action="?m=age_e" method="post">
			<input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($return_url); ?>" />
	<?php } ?>

		<table class="table table-hover">
			<thead>
				<tr>
					<th>
						<?php if ($orden_campo == 1) { ?>
							<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?><?php if ($inactivo != 0) { echo '&e=' . $inactivo; } ?>&o=1&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>">
								<strong>Codigo <?php echo $od_chr; ?></strong>
							</a>
						<?php } else { ?>
							<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?><?php if ($inactivo != 0) { echo '&e=' . $inactivo; } ?>&o=1&od=0">Codigo</a>
						<?php } ?>
					</th>

					<th>
						<?php if ($orden_campo == 0) { ?>
							<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?><?php if ($inactivo != 0) { echo '&e=' . $inactivo; } ?>&o=0&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>">
								<strong>Fecha <?php echo $od_chr; ?></strong>
							</a>
						<?php } else { ?>
							<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?><?php if ($inactivo != 0) { echo '&e=' . $inactivo; } ?>&o=0&od=0">Fecha</a>
						<?php } ?>
					</th>

					<th>
						<?php if ($orden_campo == 2) { ?>
							<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?><?php if ($inactivo != 0) { echo '&e=' . $inactivo; } ?>&o=2&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>">
								<strong>Hora <?php echo $od_chr; ?></strong>
							</a>
						<?php } else { ?>
							<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?><?php if ($inactivo != 0) { echo '&e=' . $inactivo; } ?>&o=2&od=0">Hora</a>
						<?php } ?>
					</th>

					<th>
						<?php if ($orden_campo == 3) { ?>
							<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?><?php if ($inactivo != 0) { echo '&e=' . $inactivo; } ?>&o=3&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>">
								<strong>Automovil <?php echo $od_chr; ?></strong>
							</a>
						<?php } else { ?>
							<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?><?php if ($inactivo != 0) { echo '&e=' . $inactivo; } ?>&o=3&od=0">Automovil</a>
						<?php } ?>
					</th>

					<th>
						<?php if ($orden_campo == 4) { ?>
							<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?><?php if ($inactivo != 0) { echo '&e=' . $inactivo; } ?>&o=4&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>">
								<strong>Nombre <?php echo $od_chr; ?></strong>
							</a>
						<?php } else { ?>
							<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?><?php if ($inactivo != 0) { echo '&e=' . $inactivo; } ?>&o=4&od=0">Nombre</a>
						<?php } ?>
					</th>

					<th>
						<?php if ($orden_campo == 6) { ?>
							<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?><?php if ($inactivo != 0) { echo '&e=' . $inactivo; } ?>&o=6&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>">
								<strong>Email <?php echo $od_chr; ?></strong>
							</a>
						<?php } else { ?>
							<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?><?php if ($inactivo != 0) { echo '&e=' . $inactivo; } ?>&o=6&od=0">Email</a>
						<?php } ?>
					</th>

					<th></th>
				</tr>
			</thead>

			<tfoot>
				<tr>
					<td height="30" colspan="10" valign="bottom">
						<div class="info_seleccionados" style="display:none;">
							<span id="cantidad_seleccionados"></span>
							<?php if ($_SESSION[$config['codigo_unico']]['login_permisos']['mod'] > 1) { ?>
								- <input type="button" class="btn btn-danger btn-small" value="Eliminar" onclick="eliminar();" />
							<?php } ?>
						</div>

						<div class="info_listados">Total: <strong><?php echo $total; ?></strong></div>

						<?php if ($total_paginas > 1) { ?>
							<div class="paginas">
								<?php if ($pagina > 1) { ?>
									<a href="?m=<?php echo $modulo['prefijo']; ?>_l&p=<?php echo $pagina - 1; ?><?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?><?php if ($orden_campo != 0) { echo '&o=' . $orden_campo; } ?>&od=<?php echo $orden_dir; ?><?php if ($inactivo != 0) { echo '&e=' . $inactivo; } ?>">&lt; anterior</a>
								<?php } ?>

								<select id="select_pagina" class="input-mini">
									<?php for ($i = 1; $i <= $total_paginas; $i++) { ?>
										<option value="<?php echo $i; ?>" <?php if ($i == $pagina) echo 'selected="selected"'; ?>>
											<?php echo $i; ?>
										</option>
									<?php } ?>
								</select>

								/ <?php echo $total_paginas; ?>

								<?php if ($pagina < $total_paginas) { ?>
									<a href="?m=<?php echo $modulo['prefijo']; ?>_l&p=<?php echo $pagina + 1; ?><?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?><?php if ($orden_campo != 0) { echo '&o=' . $orden_campo; } ?>&od=<?php echo $orden_dir; ?><?php if ($inactivo != 0) { echo '&e=' . $inactivo; } ?>">siguiente &gt;</a>
								<?php } ?>
							</div>
						<?php } ?>
					</td>
				</tr>
			</tfoot>

			<tbody>
				<?php while ($entrada = $db->fetch_array($listado)) { ?>
					<tr>
						<td>
							<a href="?m=<?php echo $modulo['prefijo']; ?>_v&i=<?php echo $entrada['id_agenda']; ?>">
								<?php echo_s($entrada['id_agenda']); ?>
							</a>
						</td>
						<td><?php echo_s(strftime('%d/%m/%Y', strtotime($entrada['fecha']))); ?></td>
						<td><?php echo_s($entrada['hora']); ?></td>
						<td><?php echo_s($entrada['auto']); ?></td>
						<td><?php echo_s($entrada['nombre']); ?></td>
						<td><?php echo_s($entrada['email']); ?></td>
						<td><input name="e_sel[]" type="checkbox" value="<?php echo $entrada['id_agenda']; ?>" /></td>
					</tr>
				<?php } ?>
			</tbody>
		</table>

	<?php if ($_SESSION[$config['codigo_unico']]['login_permisos']['mod'] > 1) { ?>
		</form>
	<?php } ?>

	<script>
		function actualizarSeleccionados() {
			var t = $('input[name="e_sel[]"]:checked').length;

			$('input[name="e_sel[]"]').each(function() {
				$(this).closest('tr').toggleClass('info', $(this).is(':checked'));
			});

			if (t > 0) {
				$('.info_seleccionados').show();
				$('#cantidad_seleccionados').html(t === 1 ? '1 elemento seleccionado' : t + ' elementos seleccionados');
			} else {
				$('.info_seleccionados').hide();
				$('#cantidad_seleccionados').html('');
			}
		}

		$('input[name="e_sel[]"]').on('click', function() {
			actualizarSeleccionados();
		});

		$('#select_pagina').on('change', function() {
			window.location.href = '?m=<?php echo $modulo['prefijo']; ?>_l&p=' + $(this).val()
				+ '<?php if ($busqueda != '') { echo '&b=' . urlencode($busqueda); } ?>'
				+ '<?php if ($orden_campo != 0) { echo '&o=' . $orden_campo; } ?>'
				+ '&od=<?php echo $orden_dir; ?>'
				+ '<?php if ($inactivo != 0) { echo '&e=' . $inactivo; } ?>';
		});

		function buscarListado() {
			window.location.href = '?m=<?php echo $modulo['prefijo']; ?>_l'
				+ '<?php if ($orden_campo != 0) { echo '&o=' . $orden_campo; } ?>'
				+ '&od=<?php echo $orden_dir; ?>'
				+ '<?php if ($inactivo != 0) { echo '&e=' . $inactivo; } ?>'
				+ '&b=' + encodeURIComponent($('#b').val());
		}

		function eliminar() {
			var seleccionados = $('input[name="e_sel[]"]:checked').length;

			if (seleccionados <= 0) {
				alert('Seleccioná al menos una agenda.');
				return;
			}

			if (confirm('¿Esta seguro que desea eliminar los elementos seleccionados?')) {
				$('#form_listado').submit();
			}
		}

		$(function() {
			actualizarSeleccionados();
		});
	</script>

<?php } else { ?>

	<?php if ($busqueda != '') { ?>
		<div class="info_resultado">
			<div class="tc">No se encontraron elementos con <strong>"<?php echo_s($busqueda); ?>"</strong>.</div>
			<div class="tc">
				<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($orden_campo != 0) { echo '&o=' . $orden_campo; } ?>&od=<?php echo $orden_dir; ?><?php if ($inactivo != 0) { echo '&e=' . $inactivo; } ?>">Ver todos</a>
			</div>
		</div>
	<?php } else { ?>
		<div class="info_resultado">
			<div class="tc">No hay elementos para listar.</div>
		</div>
	<?php } ?>

<?php } ?>

<?php require_once('sistema_post_contenido.php'); ?>