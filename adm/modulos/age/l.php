<?php
if (!isset($sistema_iniciado)) exit();

$pagina = intval($_GET['p'] ?? 0);
if ($pagina <= 0) $pagina = 1;

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

$orden_campo = intval($_GET['o'] ?? 0);
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
	case 1: $sql_o = 'id_agenda'; break;
	case 2: $sql_o = 'hora'; break;
	case 3: $sql_o = 'auto'; break;
	case 4: $sql_o = 'nombre'; break;
	case 5: $sql_o = 'ci'; break;
	case 6: $sql_o = 'email'; break;
	default:
		$sql_o = 'fecha';
		$orden_campo = 0;
}

$sql_b = trim($sql_b);
if ($sql_b != '') $sql_b = ' ' . $sql_b;

$inactivo = intval($_GET['e'] ?? 0);

$return_url = '?m=' . $modulo['prefijo'] . '_l'
	. '&p=' . $pagina
	. ($busqueda != '' ? '&b=' . urlencode($busqueda) : '')
	. '&o=' . $orden_campo
	. '&od=' . $orden_dir
	. ($inactivo != 0 ? '&e=' . $inactivo : '');

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

<style>
	.age-bloque-calendario { margin-bottom: 20px; }

	.age-panel {
		border: 1px solid #ddd;
		background: #fff;
		padding: 15px;
		border-radius: 4px;
		min-height: 340px;
	}

	.age-panel h5 {
		margin-top: 0;
		margin-bottom: 12px;
	}

	.age-cal-toolbar {
		display:flex;
		align-items:center;
		justify-content:space-between;
		margin-bottom:10px;
	}

	.age-cal-title {
		font-size:18px;
		font-weight:bold;
	}

	.age-calendar-grid {
		display:grid;
		grid-template-columns: repeat(7, 1fr);
		gap:6px;
	}

	.age-calendar-head {
		text-align:center;
		font-size:12px;
		font-weight:bold;
		color:#666;
		padding:6px 0;
	}

	.age-calendar-day {
		min-height:46px;
		border:1px solid #ddd;
		background:#f7f7f7;
		display:flex;
		align-items:center;
		justify-content:center;
		cursor:pointer;
		border-radius:4px;
		font-size:13px;
	}

	.age-calendar-day:hover { background:#e9f2ff; border-color:#a7c6f2; }
	.age-calendar-day-empty { background:transparent; border:0; cursor:default; }
	.age-calendar-day-past { background:#e5e5e5; color:#999; cursor:not-allowed; }
	.age-calendar-day-blocked { background:#f2dede; border-color:#dca7a7; color:#a94442; }
	.age-calendar-day-selected { background:#d9edf7; border-color:#7bb6d9; }

	.age-detalle-dia h4 {
		margin-top: 0;
		margin-bottom: 14px;
	}

	.age-acciones-bloqueo {
		margin-top: 14px;
	}

	.age-feedback {
		margin-top: 10px;
		font-weight: bold;
	}

	.age-help {
		color:#777;
		font-size:12px;
		margin-top:8px;
	}

	.age-slots-grid {
		display: flex;
		flex-wrap: wrap;
		gap: 8px;
	}

	.age-slot-card {
		width: 118px;
		min-height: 132px;
		border: 1px solid #d9d9d9;
		border-radius: 4px;
		padding: 8px;
		text-align: center;
		background: #f8f8f8;
		box-sizing: border-box;
		display: flex;
		flex-direction: column;
		justify-content: flex-start;
	}

	.age-slot-card-hora {
		font-weight: bold;
		font-size: 18px;
		margin-bottom: 8px;
		line-height: 1.2;
	}

	.age-slot-card-actions .btn {
		display: block;
		width: 100%;
		margin-bottom: 6px;
		box-sizing: border-box;
	}

	.age-slot-disponible {
		background: #fcfcfc;
	}

	.age-slot-ocupada {
		background: #f8f8f8;
	}

	.age-slot-bloqueada {
		background: #f2dede;
		border-color: #dca7a7;
	}

	.age-btn-bloquear {
		background: #f0ad4e;
		color: #fff;
		border: 1px solid #eea236;
	}

	.age-btn-anular {
		background: #d9534f;
		color: #fff;
		border: 1px solid #d43f3a;
	}

	.age-btn-desbloquear {
		background: #d9534f;
		color: #fff;
		border: 1px solid #d43f3a;
	}

	.age-row-cancelada {
		background: #f2dede !important;
		color: #a94442;
	}

	.age-row-finalizada {
	background: #eef3f7 !important;
	color: #5c6b77;
	}

	.age-estado-finalizada {
		color: #5c6b77;
		font-weight: bold;
	}

	.age-estado-activa {
		color: #468847;
		font-weight: bold;
	}

	.age-estado-cancelada {
		color: #a94442;
		font-weight: bold;
	}

	.age-modal-bg {
		display:none;
		position:fixed;
		inset:0;
		background:rgba(0,0,0,.45);
		z-index:9998;
	}

	.age-modal {
		display:none;
		position:fixed;
		top:50%;
		left:50%;
		transform:translate(-50%,-50%);
		background:#fff;
		border:1px solid #ccc;
		border-radius:6px;
		padding:18px;
		width:560px;
		max-width:95%;
		z-index:9999;
		box-sizing:border-box;
	}

	.age-modal h4 {
		margin-top:0;
		margin-bottom:12px;
	}

	.age-modal .row-fluid {
		margin-bottom:10px;
	}

	.age-modal input {
		width:95%;
		box-sizing:border-box;
	}

	.age-modal-feedback {
		margin-top:10px;
		font-weight:bold;
	}
</style>

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
				onclick="window.location.href='?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($orden_campo != 0) { echo '&o=' . $orden_campo; } ?>&od=<?php echo $orden_dir; ?><?php if ($inactivo != 0) { echo '&e=' . $inactivo; } ?>';"
			>X</button>
		<?php } ?>
		<button type="button" class="btn btn-default btn-small" onclick="buscarListado();">Buscar</button>
	</div>

	<h4 class="titulo"><?php echo $modulo['nombre']; ?></h4>
	<hr>
	<hr class="nb">
</div>

<div class="sep_titulo"></div>

<div class="row age-bloque-calendario">
	<div class="span6">
		<div class="age-panel">
			<div class="age-cal-toolbar">
				<button type="button" class="btn btn-small" onclick="cambiarMes(-1)">&lt;</button>
				<div class="age-cal-title" id="cal_titulo"></div>
				<button type="button" class="btn btn-small" onclick="cambiarMes(1)">&gt;</button>
			</div>
			<div id="calendario"></div>
			<div class="age-help">
				Gris: día pasado | Rojo: día bloqueado completo
			</div>
		</div>
	</div>

	<div class="span6">
		<div class="age-panel age-detalle-dia">
			<h5>Detalle del día</h5>
			<div id="detalle_dia">Seleccioná un día</div>
		</div>
	</div>
</div>

<div id="modal_agendar_bg" class="age-modal-bg"></div>

<div id="modal_agendar" class="age-modal">
	<h4>Agendar manualmente</h4>

	<div class="row-fluid">
		<div class="span6">
			<label>Fecha</label>
			<input type="text" id="ag_fecha" readonly>
		</div>
		<div class="span6">
			<label>Hora</label>
			<input type="text" id="ag_hora" readonly>
		</div>
	</div>

	<div class="row-fluid">
		<div class="span6">
			<label>Nombre</label>
			<input type="text" id="ag_nombre">
		</div>
		<div class="span6">
			<label>Email</label>
			<input type="text" id="ag_email">
		</div>
	</div>

	<div class="row-fluid">
		<div class="span6">
			<label>Teléfono</label>
			<input type="text" id="ag_telefono">
		</div>
		<div class="span6">
			<label>Auto</label>
			<input type="text" id="ag_auto">
		</div>
	</div>

	<div class="row-fluid">
		<div class="span6">
			<label>Marca</label>
			<input type="text" id="ag_marca">
		</div>
		<div class="span6">
			<label>Modelo</label>
			<input type="text" id="ag_modelo">
		</div>
	</div>

	<div class="row-fluid">
		<div class="span6">
			<label>Año</label>
			<input type="text" id="ag_anio">
		</div>
		<div class="span6">
			<label>Familia</label>
			<input type="text" id="ag_familia">
		</div>
	</div>

	<div style="margin-top:14px;">
		<button type="button" class="btn btn-primary" onclick="guardarAgendaManual()">Guardar agenda</button>
		<button type="button" class="btn" onclick="cerrarModalAgenda()">Cancelar</button>
	</div>

	<div id="ag_feedback" class="age-modal-feedback"></div>
</div>

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
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') echo '&b=' . urlencode($busqueda); ?><?php if ($inactivo != 0) echo '&e=' . $inactivo; ?>&o=1&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>">
							<strong>Codigo <?php echo $od_chr; ?></strong>
						</a>
					<?php } else { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') echo '&b=' . urlencode($busqueda); ?><?php if ($inactivo != 0) echo '&e=' . $inactivo; ?>&o=1&od=0">Codigo</a>
					<?php } ?>
				</th>

				<th>
					<?php if ($orden_campo == 0) { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') echo '&b=' . urlencode($busqueda); ?><?php if ($inactivo != 0) echo '&e=' . $inactivo; ?>&o=0&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>">
							<strong>Fecha <?php echo $od_chr; ?></strong>
						</a>
					<?php } else { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') echo '&b=' . urlencode($busqueda); ?><?php if ($inactivo != 0) echo '&e=' . $inactivo; ?>&o=0&od=0">Fecha</a>
					<?php } ?>
				</th>

				<th>
					<?php if ($orden_campo == 2) { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') echo '&b=' . urlencode($busqueda); ?><?php if ($inactivo != 0) echo '&e=' . $inactivo; ?>&o=2&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>">
							<strong>Hora <?php echo $od_chr; ?></strong>
						</a>
					<?php } else { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') echo '&b=' . urlencode($busqueda); ?><?php if ($inactivo != 0) echo '&e=' . $inactivo; ?>&o=2&od=0">Hora</a>
					<?php } ?>
				</th>

				<th>
					<?php if ($orden_campo == 3) { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') echo '&b=' . urlencode($busqueda); ?><?php if ($inactivo != 0) echo '&e=' . $inactivo; ?>&o=3&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>">
							<strong>Automovil <?php echo $od_chr; ?></strong>
						</a>
					<?php } else { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') echo '&b=' . urlencode($busqueda); ?><?php if ($inactivo != 0) echo '&e=' . $inactivo; ?>&o=3&od=0">Automovil</a>
					<?php } ?>
				</th>

				<th>
					<?php if ($orden_campo == 4) { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') echo '&b=' . urlencode($busqueda); ?><?php if ($inactivo != 0) echo '&e=' . $inactivo; ?>&o=4&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>">
							<strong>Nombre <?php echo $od_chr; ?></strong>
						</a>
					<?php } else { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') echo '&b=' . urlencode($busqueda); ?><?php if ($inactivo != 0) echo '&e=' . $inactivo; ?>&o=4&od=0">Nombre</a>
					<?php } ?>
				</th>

				<th>
					<?php if ($orden_campo == 6) { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') echo '&b=' . urlencode($busqueda); ?><?php if ($inactivo != 0) echo '&e=' . $inactivo; ?>&o=6&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>">
							<strong>Email <?php echo $od_chr; ?></strong>
						</a>
					<?php } else { ?>
						<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') echo '&b=' . urlencode($busqueda); ?><?php if ($inactivo != 0) echo '&e=' . $inactivo; ?>&o=6&od=0">Email</a>
					<?php } ?>
				</th>

				<th>Estado</th>
				<th>Detalle</th>
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
								<a href="?m=<?php echo $modulo['prefijo']; ?>_l&p=<?php echo $pagina - 1; ?><?php if ($busqueda != '') echo '&b=' . urlencode($busqueda); ?><?php if ($orden_campo != 0) echo '&o=' . $orden_campo; ?>&od=<?php echo $orden_dir; ?><?php if ($inactivo != 0) echo '&e=' . $inactivo; ?>">&lt; anterior</a>
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
								<a href="?m=<?php echo $modulo['prefijo']; ?>_l&p=<?php echo $pagina + 1; ?><?php if ($busqueda != '') echo '&b=' . urlencode($busqueda); ?><?php if ($orden_campo != 0) echo '&o=' . $orden_campo; ?>&od=<?php echo $orden_dir; ?><?php if ($inactivo != 0) echo '&e=' . $inactivo; ?>">siguiente &gt;</a>
							<?php } ?>
						</div>
					<?php } ?>
				</td>
			</tr>
		</tfoot>

		<tbody>
			<?php while ($entrada = $db->fetch_array($listado)) { ?>
				<tr class="<?php
					if (!empty($entrada['cancelado'])) {
						echo 'age-row-cancelada';
					} elseif (!empty($entrada['finalizada'])) {
						echo 'age-row-finalizada';
					}
				?>">
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
					<td>
						<?php if (!empty($entrada['cancelado'])) { ?>
							<span class="age-estado-cancelada">CANCELADA</span>
						<?php } elseif (!empty($entrada['finalizada'])) { ?>
							<span class="age-estado-finalizada">FINALIZADA</span>
						<?php } else { ?>
							<span class="age-estado-activa">ACTIVA</span>
						<?php } ?>
					</td>
					<td>
						<?php
						if (!empty($entrada['cancelado'])) {
							echo_s($entrada['motivo_cancelacion'] ?? '');
						} elseif (!empty($entrada['finalizada'])) {
							echo_s($entrada['detalle_estado'] ?? '');
						} else {
							echo '';
						}
						?>
					</td>
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
				+ '<?php if ($busqueda != '') echo '&b=' . urlencode($busqueda); ?>'
				+ '<?php if ($orden_campo != 0) echo '&o=' . $orden_campo; ?>'
				+ '&od=<?php echo $orden_dir; ?>'
				+ '<?php if ($inactivo != 0) echo '&e=' . $inactivo; ?>';
		});

		function buscarListado() {
			window.location.href = '?m=<?php echo $modulo['prefijo']; ?>_l'
				+ '<?php if ($orden_campo != 0) echo '&o=' . $orden_campo; ?>'
				+ '&od=<?php echo $orden_dir; ?>'
				+ '<?php if ($inactivo != 0) echo '&e=' . $inactivo; ?>'
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

		let fechaSeleccionada = null;
		let calYear = (new Date()).getFullYear();
		let calMonth = (new Date()).getMonth();

		const meses = [
			'Enero','Febrero','Marzo','Abril','Mayo','Junio',
			'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'
		];

		function cambiarMes(delta) {
			calMonth += delta;
			if (calMonth < 0) {
				calMonth = 11;
				calYear--;
			}
			if (calMonth > 11) {
				calMonth = 0;
				calYear++;
			}
			cargarCalendario();
		}

		function cargarCalendario() {
			$('#cal_titulo').html(meses[calMonth] + ' ' + calYear);

			$.post('/adm/modulos/age/ajax_dia.php', {
				accion: 'calendario',
				year: calYear,
				month: calMonth + 1
			}, function(resp) {

				if (!resp || !resp.ok) {
					$('#calendario').html('<p style="color:red;">No se pudo cargar el calendario.</p>');
					return;
				}

				const today = new Date();
				const firstDay = new Date(calYear, calMonth, 1);
				const lastDay = new Date(calYear, calMonth + 1, 0);

				let html = '<div class="age-calendar-grid">';
				const heads = ['L','M','M','J','V','S','D'];
				for (let i = 0; i < heads.length; i++) {
					html += '<div class="age-calendar-head">' + heads[i] + '</div>';
				}

				let startDay = firstDay.getDay();
				startDay = (startDay === 0) ? 6 : startDay - 1;

				for (let i = 0; i < startDay; i++) {
					html += '<div class="age-calendar-day age-calendar-day-empty"></div>';
				}

				for (let d = 1; d <= lastDay.getDate(); d++) {
					let fecha = calYear + '-' + String(calMonth + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
					let clases = 'age-calendar-day';
					let puedeClick = true;

					let current = new Date(calYear, calMonth, d, 0, 0, 0, 0);
					let hoy = new Date(today.getFullYear(), today.getMonth(), today.getDate(), 0, 0, 0, 0);

					if (current < hoy) {
						clases += ' age-calendar-day-past';
						puedeClick = false;
					}

					if (resp.bloqueados_completos.indexOf(fecha) !== -1) {
						clases += ' age-calendar-day-blocked';
					}

					if (fechaSeleccionada === fecha) {
						clases += ' age-calendar-day-selected';
					}

					if (puedeClick) {
						html += '<div class="' + clases + '" onclick="seleccionarDia(\'' + fecha + '\')">' + d + '</div>';
					} else {
						html += '<div class="' + clases + '">' + d + '</div>';
					}
				}

				html += '</div>';
				$('#calendario').html(html);

			}, 'json').fail(function(){
				$('#calendario').html('<p style="color:red;">Error cargando el calendario.</p>');
			});
		}

		function renderSlotCard(h) {
			let html = '';
			let cardClass = 'age-slot-card';

			if (h.estado === 'bloqueada') {
				cardClass += ' age-slot-bloqueada';
			} else if (h.estado === 'ocupada') {
				cardClass += ' age-slot-ocupada';
			} else {
				cardClass += ' age-slot-disponible';
			}

			html += '<div class="' + cardClass + '">';
			html += '<div class="age-slot-card-hora">' + h.hora + '</div>';
			html += '<div class="age-slot-card-actions">';

			if (h.estado === 'bloqueada') {
				html += '<button class="btn btn-small age-btn-desbloquear" onclick="desbloquearHora(\'' + h.hora + '\')">Desbloquear</button>';
			} else if (h.estado === 'ocupada') {
				html += '<a class="btn btn-small" href="?m=age_v&i=' + h.id_agenda + '">Ver</a>';
				html += '<button class="btn btn-small age-btn-anular" onclick="anularAgenda(' + h.id_agenda + ')">Anular</button>';
			} else {
				html += '<button class="btn btn-small" onclick="abrirModalAgenda(\'' + h.hora + '\')">Agendar</button>';
				html += '<button class="btn btn-small age-btn-bloquear" onclick="bloquearHora(\'' + h.hora + '\')">Bloquear</button>';
			}

			html += '</div>';
			html += '</div>';

			return html;
		}

		function seleccionarDia(fecha) {
			fechaSeleccionada = fecha;
			$('#detalle_dia').html('Cargando...');

			$.post('/adm/modulos/age/ajax_dia.php', {
				accion: 'detalle',
				fecha: fecha
			}, function(resp) {

				if (!resp || !resp.ok) {
					$('#detalle_dia').html('<p style="color:red;">No se pudo cargar el detalle del día.</p>');
					return;
				}

				let html = '<h4>' + fecha + '</h4>';

				if (resp.dia_bloqueado) {
					html += '<p style="color:#a94442;"><strong>Día bloqueado completo</strong></p>';
					html += '<button class="btn btn-small age-btn-desbloquear" onclick="desbloquearDia()">Desbloquear día completo</button>';
				} else {
					if (resp.horas.length <= 0) {
						html += '<p style="color:red;">No hay horas configuradas para ese día.</p>';
					} else {
						html += '<div class="age-slots-grid">';

						resp.horas.forEach(function(h) {
							html += renderSlotCard(h);
						});

						html += '</div>';
					}

					html += '<div class="age-acciones-bloqueo"><button class="btn btn-warning btn-small" onclick="bloquearDia()">Bloquear día completo</button></div>';
				}

				html += '<div class="age-feedback" id="bloqueo_feedback"></div>';
				$('#detalle_dia').html(html);
				cargarCalendario();

			}, 'json').fail(function(xhr){
				$('#detalle_dia').html(
					'<p style="color:red;">Error cargando horarios del día.</p><pre style="white-space:pre-wrap;">'
					+ (xhr.responseText || 'sin respuesta') +
					'</pre>'
				);
			});
		}

		function anularAgenda(id) {
			if (!confirm('¿Seguro que querés anular esta agenda?')) return;

			$.post('/adm/modulos/age/ajax_anular.php', {
				id_agenda: id
			}, function(resp){
				if (resp.ok) {
					alert('Agenda anulada correctamente');
					seleccionarDia(fechaSeleccionada);
					setTimeout(function() {
						window.location.reload();
					}, 300);
				} else {
					alert(resp.mensaje || 'Error al anular');
				}
			}, 'json').fail(function(){
				alert('Error al anular la agenda');
			});
		}

		function bloquearDia() {
			if (!fechaSeleccionada) return;
			if (!confirm("¿Bloquear todo el día " + fechaSeleccionada + "?")) return;

			$.post('/adm/modulos/age/ajax_bloquear.php', {
				accion: 'bloquear',
				fecha: fechaSeleccionada,
				hora: ''
			}, function(resp) {
				if (resp && resp.ok) {
					$('#bloqueo_feedback').html('Día bloqueado correctamente. Agendas afectadas: ' + (resp.afectadas || 0));
					seleccionarDia(fechaSeleccionada);
				} else {
					$('#bloqueo_feedback').html(resp && resp.mensaje ? resp.mensaje : 'No se pudo bloquear el día.');
				}
			}, 'json').fail(function(){
				$('#bloqueo_feedback').html('Error al bloquear el día.');
			});
		}

		function desbloquearDia() {
			if (!fechaSeleccionada) return;
			if (!confirm("¿Desbloquear todo el día " + fechaSeleccionada + "?")) return;

			$.post('/adm/modulos/age/ajax_bloquear.php', {
				accion: 'desbloquear',
				fecha: fechaSeleccionada,
				hora: ''
			}, function(resp) {
				if (resp && resp.ok) {
					$('#bloqueo_feedback').html('Día desbloqueado correctamente.');
					seleccionarDia(fechaSeleccionada);
				} else {
					$('#bloqueo_feedback').html(resp && resp.mensaje ? resp.mensaje : 'No se pudo desbloquear el día.');
				}
			}, 'json').fail(function(){
				$('#bloqueo_feedback').html('Error al desbloquear el día.');
			});
		}

		function bloquearHora(hora) {
			if (!fechaSeleccionada) return;
			if (!confirm("¿Bloquear la hora " + hora + " del día " + fechaSeleccionada + "?")) return;

			$.post('/adm/modulos/age/ajax_bloquear.php', {
				accion: 'bloquear',
				fecha: fechaSeleccionada,
				hora: hora
			}, function(resp) {
				if (resp && resp.ok) {
					$('#bloqueo_feedback').html('Hora bloqueada correctamente. Agendas afectadas: ' + (resp.afectadas || 0));
					seleccionarDia(fechaSeleccionada);
				} else {
					$('#bloqueo_feedback').html(resp && resp.mensaje ? resp.mensaje : 'No se pudo bloquear la hora.');
				}
			}, 'json').fail(function(){
				$('#bloqueo_feedback').html('Error al bloquear la hora.');
			});
		}

		function desbloquearHora(hora) {
			if (!fechaSeleccionada) return;
			if (!confirm("¿Desbloquear la hora " + hora + " del día " + fechaSeleccionada + "?")) return;

			$.post('/adm/modulos/age/ajax_bloquear.php', {
				accion: 'desbloquear',
				fecha: fechaSeleccionada,
				hora: hora
			}, function(resp) {
				if (resp && resp.ok) {
					$('#bloqueo_feedback').html('Hora desbloqueada correctamente.');
					seleccionarDia(fechaSeleccionada);
				} else {
					$('#bloqueo_feedback').html(resp && resp.mensaje ? resp.mensaje : 'No se pudo desbloquear la hora.');
				}
			}, 'json').fail(function(){
				$('#bloqueo_feedback').html('Error al desbloquear la hora.');
			});
		}

		function abrirModalAgenda(hora) {
			$('#ag_fecha').val(fechaSeleccionada);
			$('#ag_hora').val(hora);
			$('#ag_nombre').val('');
			$('#ag_email').val('');
			$('#ag_telefono').val('');
			$('#ag_auto').val('');
			$('#ag_marca').val('');
			$('#ag_modelo').val('');
			$('#ag_anio').val('');
			$('#ag_familia').val('');
			$('#ag_feedback').html('');

			$('#modal_agendar_bg').show();
			$('#modal_agendar').show();
		}

		function cerrarModalAgenda() {
			$('#modal_agendar_bg').hide();
			$('#modal_agendar').hide();
		}

		function guardarAgendaManual() {
			let data = {
				fecha: $('#ag_fecha').val(),
				hora: $('#ag_hora').val(),
				nombre: $('#ag_nombre').val(),
				email: $('#ag_email').val(),
				telefono: $('#ag_telefono').val(),
				auto: $('#ag_auto').val(),
				marca: $('#ag_marca').val(),
				modelo: $('#ag_modelo').val(),
				anio: $('#ag_anio').val(),
				familia: $('#ag_familia').val()
			};

			if (!data.fecha || !data.hora || !data.nombre || !data.email) {
				$('#ag_feedback').css('color', '#a94442').html('Completá al menos fecha, hora, nombre y email.');
				return;
			}

			$.post('/adm/modulos/age/ajax_agendar.php', data, function(resp){
				if (resp.ok) {
					$('#ag_feedback').css('color', '#468847').html('Agenda creada correctamente.');
					setTimeout(function(){
						cerrarModalAgenda();
						seleccionarDia(fechaSeleccionada);
						window.location.reload();
					}, 500);
				} else {
					$('#ag_feedback').css('color', '#a94442').html(resp.mensaje || 'No se pudo guardar.');
				}
			}, 'json').fail(function(xhr){
				$('#ag_feedback').css('color', '#a94442').html(
					'Error al guardar la agenda.<br>' +
					'HTTP: ' + xhr.status + '<br>' +
					'<pre style="white-space:pre-wrap;">' +
					(xhr.responseText || 'sin respuesta') +
					'</pre>'
				);
			});
		}

		$(function() {
			actualizarSeleccionados();
			cargarCalendario();

			$('#modal_agendar_bg').on('click', function(){
				cerrarModalAgenda();
			});
		});
	</script>

<?php } else { ?>

	<?php if ($busqueda != '') { ?>
		<div class="info_resultado">
			<div class="tc">No se encontraron elementos con <strong>"<?php echo_s($busqueda); ?>"</strong>.</div>
			<div class="tc">
				<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($orden_campo != 0) echo '&o=' . $orden_campo; ?>&od=<?php echo $orden_dir; ?><?php if ($inactivo != 0) echo '&e=' . $inactivo; ?>">Ver todos</a>
			</div>
		</div>
	<?php } else { ?>
		<div class="info_resultado">
			<div class="tc">No hay elementos para listar.</div>
		</div>
	<?php } ?>

<?php } ?>

<?php require_once('sistema_post_contenido.php'); ?>