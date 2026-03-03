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
		$sql_b .= ' and (porcentaje like "%' . $busqueda_array[$i] . '%")';
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
		$sql_o = 'porcentaje';
		$sql_v = 'valor';
		break;
	default:
		$sql_o = 'id_ponderador_valor_venal';
		$sql_v = 'id_ponderador_valor';
		$orden_campo = 0;
}

$sql_b = trim($sql_b, ' and ');

if ($sql_b != '') $sql_b = ' and ' . $sql_b;

// ***************************************************************************************************
// Consulta
// ***************************************************************************************************
$listado = $db->query('SELECT SQL_CALC_FOUND_ROWS * FROM ponderador_valor_venal where 1=1 ' . $sql_b . ' order by ' . $sql_o . ' ' . $sql_od . ' limit ' . (($pagina - 1) * $config['pagina_cant']) . ', ' . $config['pagina_cant'] . ';');

$qry = $db->query_first('select FOUND_ROWS() as cantidad;');
$total = $qry['cantidad'];

$total_paginas = ceil($total / $config['pagina_cant']);

//NUEVO
$listado_valor = $db->query('SELECT SQL_CALC_FOUND_ROWS * FROM ponderador_valor where 1=1 ' . $sql_b . ' order by ' . $sql_v . ' ' . $sql_od . ' limit ' . (($pagina - 1) * $config['pagina_cant']) . ', ' . $config['pagina_cant'] . ';');
$qry_valor = $db->query_first('select FOUND_ROWS() as cantidad;');
$total_valor = $qry_valor['cantidad'];

$total_paginas_valor = ceil($total_valor / $config['pagina_cant']);

$listado_maximo = $db->query('SELECT SQL_CALC_FOUND_ROWS * FROM ponderador_valor_maximo where 1=1 ' . $sql_b . ';');

$listado_dolar = $db->query('SELECT SQL_CALC_FOUND_ROWS * FROM ponderador_valor_dolar where 1=1 ' . $sql_b . ';');

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
	<h4 class="titulo">Ponderador Valor MercadoLibre</h4>
	<!-- <hr> -->
	<?php
	if ($_SESSION[$config['codigo_unico']]['login_permisos'][$modulo['prefijo']] > 1) {



	?>
		<!-- <button type="button" class="btn btn-primary btn-small" onclick="window.location.href='?m=<?php echo $modulo['prefijo']; ?>_m&i=1';">Modificar</button> -->

	<?php
	}





	?>

	<hr class="nb">
</div>
<div class="sep_titulo" style="height: 20px;"></div>
<?php
if ($total > 0 && $total_valor > 0) {
?>
	<?php
	if ($_SESSION[$config['codigo_unico']]['login_permisos'][$modulo['prefijo']] > 1) {
	?>
		<form id="form_listado" action="?m=<?php echo $modulo['prefijo'] . '_e'; ?>" method="post">
			<input type="hidden" name='tipo_e' id='tipo_e' value='' />
		<?php
	}
		?>

		<table style="margin-top: 100px;" class="table table-hover" >
			<thead>
				<tr>
					<th width="150"><b>Valor del D&oacute;lar</b></th>
				</tr>
			</thead>
			<tbody>
				<?php while ($entrada = $db->fetch_array($listado_dolar)) { ?>
					<tr>
						<td>
							<a href="?m=<?php echo $modulo['prefijo']; ?>_m&i=<?php echo $entrada['id_valor_dolar']; ?>&t=d"><?php echo echo_s($entrada['dolar']); ?></a>
						</td>
					<tr>
				<?php } ?>
			</tbody>
		</table>

		<table style="margin-top: 65px;" class="table table-hover" >
			<thead>
				<tr>
					<th width="150"><b>Tope m&aacute;ximo de b&uacute;squeda</b></th>
				</tr>
			</thead>
			<tbody>
				<?php while ($entrada = $db->fetch_array($listado_maximo)) { ?>
					<tr>
						<td>
							<a href="?m=<?php echo $modulo['prefijo']; ?>_m&i=<?php echo $entrada['id_valor_maximo']; ?>&t=m"><?php echo echo_s($entrada['valor']); ?> resultados</a>
						</td>
					<tr>
				<?php } ?>
			</tbody>
		</table>

		<h5 style="margin-top: 65px;" >Porcentuales</h5>
		<table class="table table-hover" >
			<thead>
				<tr>
					<?php
					// ***************************************************************************************************
					// Columnas / Cabezales
					// ***************************************************************************************************
					?>
					<th width="150">
						<?php
						if ($orden_campo == 0) {
						?>
							<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') {
																				echo '&b=' . $busqueda;
																			} ?><?php if ($inactivo != 0) {
																					echo '&e=' . $inactivo;
																				} ?>&o=0&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>"><strong>Código <?php echo $od_chr; ?></strong></a>
						<?php
						} else {
						?>
							<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') {
																				echo '&b=' . $busqueda;
																			} ?><?php if ($inactivo != 0) {
																					echo '&e=' . $inactivo;
																				} ?>&o=0">Código</a>
						<?php
						}
						?>
					</th>
					<th width="150">Key</th>
					<th>Nominal</th>

					<?php
					// ***************************************************************************************************
					?>
					<!--<th width="30"></th>-->
				</tr>

			</thead>
			<tfoot>
				<tr>
					<td height="30" colspan="4" valign="bottom">
						<div class="info_seleccionados"><span id="cantidad_seleccionados"></span><?php if ($_SESSION[$config['codigo_unico']]['login_permisos'][$modulo['prefijo']] > 1) { ?> - <input type="button" class="btn btn-danger btn-small" value="Eliminar" onclick="eliminar();" /><?php } ?></div>
						<div class="info_seleccionados_des" hidden><span id="cantidad_seleccionados_des"></span><?php if ($_SESSION[$config['codigo_unico']]['login_permisos'][$modulo['prefijo']] > 1) { ?> - <input type="button" class="btn btn-success btn-small" value="Destacar" onclick="destacar();" /><?php } ?></div>
						<div class="info_listados">Total: <strong><?php echo $total; ?></strong></div>
						<?php
						if ($total_paginas > 1) {
						?>
							<div class="paginas">
								<?php
								if ($pagina > 1) {
								?>
									<a href="?m=<?php echo $modulo['prefijo']; ?>_l&p=<?php echo $pagina - 1; ?><?php if ($busqueda != '') {
																													echo '&b=' . $busqueda;
																												} ?><?php if ($orden_campo != 0) {
																														echo '&o=' . $orden_campo;
																													} ?><?php if ($orden_dir != 0) {
																															echo '&od=' . $orden_dir;
																														} ?><?php if ($inactivo != 0) {
																																echo '&e=' . $inactivo;
																															} ?>">
										< anterior</a> <?php
													}
														?> <select id="select_pagina" class="input-mini">
											<?php
											for ($i = 1; $i <= $total_paginas; $i++) {
											?>
												<option value="<?php echo $i; ?>" <?php if ($i == $pagina) {
																						echo 'selected="selected"';
																					} ?>><?php echo $i; ?></option>
											<?php
											}
											?>
											</select> / <?php echo $total_paginas; ?>
											<?php
											if ($pagina < $total_paginas) {
											?>
												<a href="?m=<?php echo $modulo['prefijo']; ?>_l&p=<?php echo $pagina + 1; ?><?php if ($busqueda != '') {
																																echo '&b=' . $busqueda;
																															} ?><?php if ($orden_campo != 0) {
																																	echo '&o=' . $orden_campo;
																																} ?><?php if ($orden_dir != 0) {
																																		echo '&od=' . $orden_dir;
																																	} ?><?php if ($inactivo != 0) {
																																			echo '&e=' . $inactivo;
																																		} ?>">siguiente ></a>
											<?php
											}
											?>
							</div>
						<?php
						}
						?>
					</td>
				</tr>
			</tfoot>
			<tbody>
				<?php
				while ($entrada = $db->fetch_array($listado)) {
				?>
					<tr>
						<?php
						// ***************************************************************************************************
						// Columnas / Datos
						// ***************************************************************************************************
						?>
						<td>
							<a href="?m=<?php echo $modulo['prefijo']; ?>_m&i=<?php echo $entrada['id_ponderador_valor_venal']; ?>&t=p"><?php echo echo_s($entrada['id_ponderador_valor_venal']); ?></a>
						</td>
						<td>
							<?php echo $entrada['key']; ?>
						</td>
						<td>
							<?php echo $entrada['porcentaje']; ?>
						</td>
						<?php
						// ***************************************************************************************************
						?>
						<!--<td><input name="e_sel[]" type="checkbox" value="<?php echo $entrada['id_financiacion']; ?>" /></td>-->
					</tr>
				<?php
				}
				?>
			</tbody>
		</table>

		<h5 style="margin-top: 30px;" >Nominales</h5>
		<table class="table table-hover" >
			<thead>
				<tr>
					<?php
					// ***************************************************************************************************
					// Columnas / Cabezales
					// ***************************************************************************************************
					?>
					<th width="150">
						<?php
						if ($orden_campo == 0) {
						?>
							<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') {
																				echo '&b=' . $busqueda;
																			} ?><?php if ($inactivo != 0) {
																					echo '&e=' . $inactivo;
																				} ?>&o=0&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>"><strong>Código <?php echo $od_chr; ?></strong></a>
						<?php
						} else {
						?>
							<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') {
																				echo '&b=' . $busqueda;
																			} ?><?php if ($inactivo != 0) {
																					echo '&e=' . $inactivo;
																				} ?>&o=0">Código</a>
						<?php
						}
						?>
					</th>
					<th width="150">Key</th>
					<th>Nominal</th>

					<?php
					// ***************************************************************************************************
					?>
					<!--<th width="30"></th>-->
				</tr>

			</thead>
			<tfoot>
				<tr>
					<td height="30" colspan="4" valign="bottom">
						<div class="info_seleccionados"><span id="cantidad_seleccionados"></span><?php if ($_SESSION[$config['codigo_unico']]['login_permisos'][$modulo['prefijo']] > 1) { ?> - <input type="button" class="btn btn-danger btn-small" value="Eliminar" onclick="eliminar();" /><?php } ?></div>
						<div class="info_seleccionados_des" hidden><span id="cantidad_seleccionados_des"></span><?php if ($_SESSION[$config['codigo_unico']]['login_permisos'][$modulo['prefijo']] > 1) { ?> - <input type="button" class="btn btn-success btn-small" value="Destacar" onclick="destacar();" /><?php } ?></div>
						<div class="info_listados">Total: <strong><?php echo $total; ?></strong></div>
						<?php
						if ($total_paginas_valor > 1) {
						?>
							<div class="paginas">
								<?php
								if ($pagina > 1) {
								?>
									<a href="?m=<?php echo $modulo['prefijo']; ?>_l&p=<?php echo $pagina - 1; ?><?php if ($busqueda != '') {
																													echo '&b=' . $busqueda;
																												} ?><?php if ($orden_campo != 0) {
																														echo '&o=' . $orden_campo;
																													} ?><?php if ($orden_dir != 0) {
																															echo '&od=' . $orden_dir;
																														} ?><?php if ($inactivo != 0) {
																																echo '&e=' . $inactivo;
																															} ?>">
										< anterior</a> <?php
													}
														?> <select id="select_pagina" class="input-mini">
											<?php
											for ($i = 1; $i <= $total_paginas_valor; $i++) {
											?>
												<option value="<?php echo $i; ?>" <?php if ($i == $pagina) {
																						echo 'selected="selected"';
																					} ?>><?php echo $i; ?></option>
											<?php
											}
											?>
											</select> / <?php echo $total_paginas_valor; ?>
											<?php
											if ($pagina < $total_paginas_valor) {
											?>
												<a href="?m=<?php echo $modulo['prefijo']; ?>_l&p=<?php echo $pagina + 1; ?><?php if ($busqueda != '') {
																																echo '&b=' . $busqueda;
																															} ?><?php if ($orden_campo != 0) {
																																	echo '&o=' . $orden_campo;
																																} ?><?php if ($orden_dir != 0) {
																																		echo '&od=' . $orden_dir;
																																	} ?><?php if ($inactivo != 0) {
																																			echo '&e=' . $inactivo;
																																		} ?>">siguiente ></a>
											<?php
											}
											?>
							</div>
						<?php
						}
						?>
					</td>
				</tr>
			</tfoot>
			<tbody>
				<?php
				while ($entrada = $db->fetch_array($listado_valor)) {
				?>
					<tr>
						<?php
						// ***************************************************************************************************
						// Columnas / Datos
						// ***************************************************************************************************
						?>
						<td>
							<a href="?m=<?php echo $modulo['prefijo']; ?>_m&i=<?php echo $entrada['id_ponderador_valor']; ?>&t=n"><?php echo echo_s($entrada['id_ponderador_valor']); ?></a>
						</td>
						<td>
							<?php echo $entrada['key']; ?>
						</td>
						<td>
							<?php echo $entrada['nominal']; ?>
						</td>
						<?php
						// ***************************************************************************************************
						?>
						<!--<td><input name="e_sel[]" type="checkbox" value="<?php echo $entrada['id_financiacion']; ?>" /></td>-->
					</tr>
				<?php
				}
				?>
			</tbody>
		</table>
		<?php
		if ($_SESSION[$config['codigo_unico']]['login_permisos'][$modulo['prefijo']] > 1) {
		?>
		</form>
	<?php
		}
	?>
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
			$("#tipo_e").val('eliminar');
			if (confirm('¿Esta seguro que desea eliminar los elementos seleccionados?')) {

				$('#form_listado').submit();
			}

		}

		function destacar() {
			$("#tipo_e").val('destacar');
			if (confirm('¿Esta seguro que desea destacar los elementos seleccionados?')) {

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
		<div class="info_resultado" style="margin-top: 130px;">
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

<script>
	function descargar_excel() {
		window.location.href = '?m=<?php echo $modulo['prefijo']; ?>_ex';
	}
</script>