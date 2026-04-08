<?php

if (!isset($sistema_iniciado)) exit();

$id = intval($_GET['i']);

$elemento = $db->query_first('
	SELECT *
	FROM agendas
	WHERE id_agenda = "' . $id . '"
	LIMIT 1;
');

if (!$elemento) {
	header('Location: ?m=' . $modulo['prefijo'] . '_l');
	exit();
}

$cotizacion = null;
if (!empty($elemento['id_cotizacion'])) {
	$cotizacion = $db->query_first('
		SELECT *
		FROM cotizaciones_generadas
		WHERE id_cotizaciones_generadas = "' . intval($elemento['id_cotizacion']) . '"
		LIMIT 1;
	');
}

$sucursal = null;
if (!empty($elemento['id_sucursal'])) {
	$sucursal = $db->query_first('
		SELECT *
		FROM agenda_sucursal
		WHERE id_sucursal = "' . intval($elemento['id_sucursal']) . '"
		LIMIT 1;
	');
}

$usuario_cotizo = null;
if ($cotizacion && !empty($cotizacion['id_usuario_cotizo'])) {
	$usuario_cotizo = $db->query_first('
		SELECT id, nombre, email
		FROM admin_usuarios
		WHERE id = "' . intval($cotizacion['id_usuario_cotizo']) . '"
		LIMIT 1;
	');
}

if (!function_exists('age_v_money')) {
	function age_v_money($valor) {
		if ($valor === null || $valor === '' || !is_numeric($valor)) {
			return 'U$S 0';
		}
		return 'U$S ' . number_format((float)$valor, 0, ',', '.');
	}
}

if (!function_exists('age_v_text')) {
	function age_v_text($valor, $fallback = '-') {
		$valor = trim((string)$valor);
		return $valor !== '' ? $valor : $fallback;
	}
}

$fechaAgenda = '-';
if (!empty($elemento['fecha']) && strtotime($elemento['fecha']) !== false) {
	$fechaAgenda = date('d/m/Y', strtotime($elemento['fecha']));
}

$horaAgenda = !empty($elemento['hora']) ? substr((string)$elemento['hora'], 0, 5) : '-';

$fechaCotizacion = '-';
if ($cotizacion && !empty($cotizacion['fecha']) && strtotime($cotizacion['fecha']) !== false) {
	$fechaCotizacion = date('d/m/Y', strtotime($cotizacion['fecha']));
}

$vehiculoTexto = '-';
if ($cotizacion) {
	if (($cotizacion['familia'] ?? '') == 'otro') {
		$vehiculoTexto = (string)$cotizacion['auto'];
	} elseif (is_numeric($cotizacion['familia'] ?? null)) {
		$vehiculoTexto = (string)$cotizacion['auto'];
	} else {
		$vehiculoTexto = trim((string)$cotizacion['auto'] . ' ' . strtoupper((string)$cotizacion['familia']));
	}
}

$estadoAgenda = 'ACTIVA';
if (!empty($elemento['cancelado'])) {
	$estadoAgenda = 'CANCELADA';
} elseif (!empty($elemento['finalizada'])) {
	$estadoAgenda = 'FINALIZADA';
}

?>
<?php require_once('sistema_cabezal.php'); ?>
<?php require_once('sistema_pre_contenido.php'); ?>

<style>
	.age-card {
		background: #fff;
		border: 1px solid #ddd;
		border-radius: 8px;
		padding: 18px;
		margin-bottom: 18px;
	}

	.age-card h4 {
		margin: 0 0 16px 0;
		font-size: 20px;
	}

	.age-card-destacado {
		background: #f9fbff;
		border: 1px solid #cfdcf1;
	}

	.age-card-destacado h4 {
		font-size: 22px;
		font-weight: bold;
	}

	.age-grid-2 {
		display: flex;
		flex-wrap: wrap;
		gap: 18px;
	}

	.age-grid-2 > .age-col {
		flex: 1 1 420px;
	}

	.age-grid-2-sm {
		display: flex;
		flex-wrap: wrap;
		gap: 18px;
	}

	.age-grid-2-sm > .age-col {
		flex: 1 1 320px;
	}

	.age-item {
		display: flex;
		gap: 14px;
		padding: 7px 0;
		border-bottom: 1px solid #f1f1f1;
		align-items: center;
	}

	.age-item:last-child {
		border-bottom: 0;
	}

	.age-label {
		width: 190px;
		font-weight: bold;
		color: #333;
	}

	.age-value {
		flex: 1;
		font-weight: bold;
		color: #111;
	}

	.age-value-big {
		font-size: 22px;
		font-weight: bold;
		color: #1f3d5a;
	}

	.age-box-resumen {
		background: #fffdf6;
		border: 1px solid #ead9a5;
		border-radius: 8px;
		padding: 16px;
	}

	.age-box-resumen .age-item {
		border-bottom-color: #f1e7c8;
	}

	.estado-pendiente { color: #c09853; }
	.estado-finalizada { color: #468847; }
	.estado-cancelada { color: #b94a48; }

	@media (max-width: 900px) {
		.age-label { width: 150px; }
	}

	@media (max-width: 700px) {
		.age-item {
			flex-direction: column;
			gap: 4px;
			align-items: flex-start;
		}
		.age-label { width: auto; }
	}
</style>

<div id="contenido_cabezal">
	<h4 class="titulo"><?php echo $modulo['nombre']; ?></h4>
	<hr>
	<?php if ($_SESSION[$config['codigo_unico']]['login_permisos']['res'] > 1) { ?>
		<button type="button" class="btn btn-small btn_sep" onclick="window.location.href='?m=<?php echo $modulo['prefijo']; ?>_l';">Volver</button>
	<?php } else { ?>
		<button type="button" class="btn btn-small" onclick="window.location.href='?m=<?php echo $modulo['prefijo']; ?>_l';">Volver</button>
	<?php } ?>
	<hr class="nb">
</div>

<div class="sep_titulo"></div>

<div class="age-grid-2">
	<div class="age-col">
		<div class="age-card">
			<h4>Datos de agenda</h4>

			<div class="age-item">
				<div class="age-label">Código agenda</div>
				<div class="age-value"><?php echo_s($elemento['id_agenda']); ?></div>
			</div>

			<div class="age-item">
				<div class="age-label">Sucursal</div>
				<div class="age-value"><?php echo_s(age_v_text($sucursal['nombre'] ?? '')); ?></div>
			</div>

			<div class="age-item">
				<div class="age-label">Fecha de agenda</div>
				<div class="age-value"><?php echo_s($fechaAgenda); ?></div>
			</div>

			<div class="age-item">
				<div class="age-label">Hora de agenda</div>
				<div class="age-value"><?php echo_s($horaAgenda); ?></div>
			</div>

			<div class="age-item">
				<div class="age-label">Estado agenda</div>
				<div class="age-value <?php
					if ($estadoAgenda == 'CANCELADA') echo 'estado-cancelada';
					elseif ($estadoAgenda == 'FINALIZADA') echo 'estado-finalizada';
				?>">
					<?php echo_s($estadoAgenda); ?>
				</div>
			</div>

			<?php if (!empty($elemento['motivo_cancelacion'])): ?>
			<div class="age-item">
				<div class="age-label">Motivo cancelación</div>
				<div class="age-value estado-cancelada"><?php echo_s($elemento['motivo_cancelacion']); ?></div>
			</div>
			<?php endif; ?>

			<?php if (!empty($elemento['detalle_estado'])): ?>
			<div class="age-item">
				<div class="age-label">Detalle estado</div>
				<div class="age-value"><?php echo_s($elemento['detalle_estado']); ?></div>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="age-col">
		<div class="age-card age-card-destacado">
			<h4>Tasación interna</h4>

			<div class="age-box-resumen">
				<div class="age-item">
        <div class="age-label">Código cotización</div>
        <div class="age-value age-value-big">
          <?php if (!empty($elemento['id_cotizacion'])): ?>
            <a href="?m=cot_v&i=<?php echo intval($elemento['id_cotizacion']); ?>" 
              style="text-decoration:none; color:#1f3d5a;">
              <?php echo_s($elemento['id_cotizacion']); ?>
            </a>
          <?php else: ?>
            -
          <?php endif; ?>
        </div>
      </div>

				<div class="age-item">
					<div class="age-label">Pre tasación</div>
					<div class="age-value age-value-big">
						<?php echo_s(age_v_money($cotizacion['pretasacion_desde'] ?? null)); ?>
						&nbsp;a&nbsp;
						<?php echo_s(age_v_money($cotizacion['pretasacion_hasta'] ?? null)); ?>
					</div>
				</div>

				<div class="age-item">
					<div class="age-label">Tasación final</div>
					<div class="age-value age-value-big"><?php echo_s(age_v_money($cotizacion['tasacion_final'] ?? null)); ?></div>
				</div>

				<div class="age-item">
					<div class="age-label">Usuario que la hizo</div>
					<div class="age-value age-value-big">
						<?php echo_s(($usuario_cotizo && !empty($usuario_cotizo['nombre'])) ? $usuario_cotizo['nombre'] : '-'); ?>
					</div>
				</div>
			</div>

			<?php if ($usuario_cotizo && !empty($usuario_cotizo['email'])): ?>
			<div class="age-item" style="margin-top:12px;">
				<div class="age-label">Email usuario</div>
				<div class="age-value"><?php echo_s($usuario_cotizo['email']); ?></div>
			</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<div class="age-grid-2">
	<div class="age-col">
		<div class="age-card">
			<h4>Datos que dio el cliente</h4>

			<div class="age-item">
				<div class="age-label">Fecha cotización</div>
				<div class="age-value"><?php echo_s($fechaCotizacion); ?></div>
			</div>

			<div class="age-item">
				<div class="age-label">Estado cotización</div>
				<div class="age-value <?php echo (($cotizacion['estado'] ?? '') == 'PENDIENTE') ? 'estado-pendiente' : 'estado-finalizada'; ?>">
					<?php echo_s(age_v_text($cotizacion['estado'] ?? '')); ?>
				</div>
			</div>

			<?php if (($cotizacion['estado'] ?? '') == 'PENDIENTE'): ?>
			<div class="age-item">
				<div class="age-label">Motivo</div>
				<div class="age-value estado-pendiente"><?php echo_s(age_v_text($cotizacion['detalle_estado'] ?? '')); ?></div>
			</div>
			<?php endif; ?>

			<div class="age-item">
				<div class="age-label">Nombre</div>
				<div class="age-value"><?php echo_s(age_v_text($elemento['nombre'] ?? '')); ?></div>
			</div>

			<div class="age-item">
				<div class="age-label">Email</div>
				<div class="age-value"><?php echo_s(age_v_text($elemento['email'] ?? '')); ?></div>
			</div>

			<div class="age-item">
				<div class="age-label">Teléfono</div>
				<div class="age-value"><?php echo_s(age_v_text($elemento['telefono'] ?? '')); ?></div>
			</div>

			<div class="age-item">
				<div class="age-label">Vehículo</div>
				<div class="age-value"><?php echo_s($vehiculoTexto); ?></div>
			</div>

			<div class="age-item">
				<div class="age-label">Año</div>
				<div class="age-value"><?php echo_s(age_v_text($cotizacion['anio'] ?? '')); ?></div>
			</div>

			<div class="age-item">
				<div class="age-label">Kilómetros</div>
				<div class="age-value">
					<?php echo_s(is_numeric($cotizacion['kilometros'] ?? null) ? number_format((float)$cotizacion['kilometros'], 0, ',', '.') : '-'); ?>
				</div>
			</div>

			<div class="age-item">
				<div class="age-label">Ficha en service oficial</div>
				<div class="age-value"><?php echo_s(age_v_text($cotizacion['ficha_tecnica'] ?? '')); ?></div>
			</div>

			<div class="age-item">
				<div class="age-label">Cantidad de Dueños</div>
				<div class="age-value"><?php echo_s(age_v_text($cotizacion['duenios'] ?? '')); ?></div>
			</div>

			<div class="age-item">
				<div class="age-label">Tipo de Venta</div>
				<div class="age-value">
					<?php
					if (($cotizacion['tipo_venta'] ?? '') == 'Venta') {
						echo 'Venta Contado';
					} else {
						echo 'Entrega como forma de pago';
					}
					?>
				</div>
			</div>

			<div class="age-item">
				<div class="age-label">Valor Pretendido</div>
				<div class="age-value"><?php echo_s(age_v_money($cotizacion['precio_pretendido'] ?? null)); ?></div>
			</div>
		</div>
	</div>
</div>

<div class="age-grid-2-sm">
	<div class="age-col">
		<div class="age-card">
			<h4>Valores Motorlider</h4>

			<div class="age-item">
				<div class="age-label">Valor Mínimo Motorlider</div>
				<div class="age-value"><?php echo_s(age_v_money($cotizacion['valor_minimo_autodata'] ?? null)); ?></div>
			</div>

			<div class="age-item">
				<div class="age-label">Valor Máximo Motorlider</div>
				<div class="age-value"><?php echo_s(age_v_money($cotizacion['valor_maximo_autodata'] ?? null)); ?></div>
			</div>

			<div class="age-item">
				<div class="age-label">Valor Promedio Motorlider</div>
				<div class="age-value"><?php echo_s(age_v_money($cotizacion['valor_promedio_autodata'] ?? null)); ?></div>
			</div>
		</div>
	</div>

	<div class="age-col">
		<div class="age-card">
			<h4>Valores de Mercado</h4>

			<div class="age-item">
				<div class="age-label">Valor Mínimo de Mercado</div>
				<div class="age-value"><?php echo_s(age_v_money($cotizacion['valor_minimo'] ?? null)); ?></div>
			</div>

			<div class="age-item">
				<div class="age-label">Valor Máximo de Mercado</div>
				<div class="age-value"><?php echo_s(age_v_money($cotizacion['valor_maximo'] ?? null)); ?></div>
			</div>

			<div class="age-item">
				<div class="age-label">Valor Promedio de Mercado</div>
				<div class="age-value"><?php echo_s(age_v_money($cotizacion['valor_promedio'] ?? null)); ?></div>
			</div>
		</div>
	</div>
</div>

<?php if ($cotizacion && ($cotizacion['estado'] ?? '') == 'PENDIENTE'): ?>
<div class="row" style="margin-top:15px;">
	<div class="span8">
		<button type="button" class="btn btn-success" onclick="window.location.href='?m=cot_v&i=<?php echo intval($elemento['id_cotizacion']); ?>';">
			Ir a cotización
		</button>
	</div>
</div>
<?php endif; ?>

<?php require_once('sistema_post_contenido.php'); ?>