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

if (!function_exists('age_v_obtener_notificaciones')) {
	function age_v_obtener_notificaciones($db, $idAgenda) {
		$rows = [];
		$sql = '
			SELECT id, tipo_notificacion, estado_envio, mensaje_enviado, respuesta_api, fecha_envio
			FROM whatsapp_agenda_notificaciones
			WHERE id_agenda = "' . intval($idAgenda) . '"
			ORDER BY fecha_envio ASC, id ASC
		';

		try {
			if (!method_exists($db, 'query')) {
				return $rows;
			}

			$rs = $db->query($sql);
			if (!$rs) {
				return $rows;
			}

			if (is_object($rs) && method_exists($rs, 'fetch_assoc')) {
				while ($row = $rs->fetch_assoc()) {
					$rows[] = $row;
				}
				return $rows;
			}

			if (method_exists($db, 'fetch_assoc')) {
				while ($row = $db->fetch_assoc($rs)) {
					$rows[] = $row;
				}
				return $rows;
			}

			if (is_array($rs)) {
				foreach ($rs as $row) {
					if (is_array($row)) {
						$rows[] = $row;
					}
				}
			}
		} catch (Throwable $e) {
			// evitar romper la vista por historial
		}

		return $rows;
	}
}

$notificaciones = age_v_obtener_notificaciones($db, $id);

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

if (!function_exists('age_v_fecha_hora')) {
	function age_v_fecha_hora($valor, $fallback = '-') {
		$valor = trim((string)$valor);
		if ($valor === '') {
			return $fallback;
		}
		$ts = strtotime($valor);
		if ($ts === false) {
			return $valor;
		}
		return date('d/m/Y H:i', $ts);
	}
}


if (!function_exists('age_v_notificacion_es_cliente')) {
	function age_v_notificacion_es_cliente($tipo) {
		$tipo = trim((string)$tipo);
		return in_array($tipo, ['respuesta_cliente', 'cancelacion_cliente'], true);
	}
}

if (!function_exists('age_v_notificacion_titulo_chat')) {
	function age_v_notificacion_titulo_chat($tipo) {
		$tipo = trim((string)$tipo);
		if (in_array($tipo, ['respuesta_cliente', 'cancelacion_cliente'], true)) {
			return 'Respuesta del cliente';
		}
		return 'Mensaje enviado Motorlider';
	}
}

if (!function_exists('age_v_tipo_notificacion_label')) {
	function age_v_tipo_notificacion_label($tipo) {
		$tipo = trim((string)$tipo);
		$map = [
			'confirmacion_48h' => 'Mensaje enviado Motorlider',
			'confirmacion_24h' => 'Mensaje enviado Motorlider',
			'reintento_confirmacion_48h' => 'Mensaje enviado Motorlider',
			'reintento_confirmacion_24h' => 'Mensaje enviado Motorlider',
			'recordatorio_3h' => 'Mensaje enviado Motorlider',
			'sin_respuesta_confirmacion' => 'Sin respuesta',
			'cancelacion_cliente' => 'Respuesta del cliente',
			'respuesta_cliente' => 'Respuesta del cliente',
		];
		return isset($map[$tipo]) ? $map[$tipo] : ucfirst(str_replace('_', ' ', $tipo));
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
	if ((isset($cotizacion['familia']) ? $cotizacion['familia'] : '') == 'otro') {
		$vehiculoTexto = (string)$cotizacion['auto'];
	} elseif (is_numeric(isset($cotizacion['familia']) ? $cotizacion['familia'] : null)) {
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

	.age-chat-wrap {
		max-height: 720px;
		overflow-y: auto;
		padding-right: 6px;
	}
	.age-chat-wrap::-webkit-scrollbar {
		width: 8px;
	}
	.age-chat-wrap::-webkit-scrollbar-thumb {
		background: #d6d6d6;
		border-radius: 8px;
	}
	.age-chat {
		display: flex;
		flex-direction: column;
		gap: 14px;
		padding-bottom: 4px;
	}
	.age-chat-row {
		display: flex;
		width: 100%;
	}
	.age-chat-row.motorlider {
		justify-content: flex-start;
	}
	.age-chat-row.cliente {
		justify-content: flex-end;
	}
	.age-chat-bubble {
		max-width: 78%;
		border-radius: 12px;
		padding: 12px 14px;
		border: 1px solid #e5e5e5;
		box-shadow: 0 1px 2px rgba(0,0,0,0.04);
	}
	.age-chat-bubble.motorlider {
		background: #f4f8ff;
		border-color: #d7e4fb;
	}
	.age-chat-bubble.cliente {
		background: #f7f3ff;
		border-color: #ddd2fa;
	}
	.age-chat-header {
		display: flex;
		justify-content: space-between;
		gap: 10px;
		align-items: baseline;
		margin-bottom: 6px;
	}
	.age-chat-titulo {
		font-weight: bold;
		color: #1f3d5a;
	}
	.age-chat-row.cliente .age-chat-titulo {
		color: #5a3d91;
	}
	.age-chat-fecha {
		font-size: 12px;
		color: #666;
		white-space: nowrap;
	}
	.age-chat-mensaje {
		white-space: pre-line;
		color: #222;
		line-height: 1.45;
	}
	.age-historial-empty {
		color: #777;
		font-style: italic;
	}

	@media (max-width: 900px) {
		.age-label { width: 150px; }
	}

	@media (max-width: 700px) {
		.age-chat-bubble { max-width: 100%; }
		.age-chat-header { flex-direction: column; gap: 2px; }
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
				<div class="age-value"><?php echo_s(age_v_text(isset($sucursal['nombre']) ? $sucursal['nombre'] : '')); ?></div>
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
					<div class="age-value age-value-big"><?php echo_s(age_v_text(isset($cotizacion['id_cotizaciones_generadas']) ? $cotizacion['id_cotizaciones_generadas'] : '')); ?></div>
				</div>

				<div class="age-item">
					<div class="age-label">Pre tasación</div>
					<div class="age-value age-value-big">
						<?php
							$desde = isset($cotizacion['precio_desde']) ? $cotizacion['precio_desde'] : null;
							$hasta = isset($cotizacion['precio_hasta']) ? $cotizacion['precio_hasta'] : null;
							echo_s(age_v_money($desde) . ' a ' . age_v_money($hasta));
						?>
					</div>
				</div>

				<div class="age-item">
					<div class="age-label">Tasación final</div>
					<div class="age-value age-value-big"><?php echo_s(age_v_money(isset($cotizacion['precio_final']) ? $cotizacion['precio_final'] : null)); ?></div>
				</div>

				<div class="age-item">
					<div class="age-label">Usuario que la hizo</div>
					<div class="age-value age-value-big"><?php echo_s(age_v_text(isset($usuario_cotizo['nombre']) ? $usuario_cotizo['nombre'] : '')); ?></div>
				</div>
			</div>

			<div class="age-item" style="margin-top:12px;">
				<div class="age-label">Email usuario</div>
				<div class="age-value"><?php echo_s(age_v_text(isset($usuario_cotizo['email']) ? $usuario_cotizo['email'] : '')); ?></div>
			</div>
		</div>
	</div>
</div>

<div class="age-grid-2-sm">
	<div class="age-col">
		<div class="age-card">
			<h4>Datos que dio el cliente</h4>

			<div class="age-item">
				<div class="age-label">Fecha cotización</div>
				<div class="age-value"><?php echo_s($fechaCotizacion); ?></div>
			</div>

			<div class="age-item">
				<div class="age-label">Estado cotización</div>
				<div class="age-value <?php
					$estadoCot = trim((string)(isset($cotizacion['estado']) ? $cotizacion['estado'] : ''));
					if ($estadoCot == 'PENDIENTE') echo 'estado-pendiente';
					elseif ($estadoCot == 'FINALIZADA') echo 'estado-finalizada';
					elseif ($estadoCot == 'CANCELADA') echo 'estado-cancelada';
				?>"><?php echo_s(age_v_text(isset($cotizacion['estado']) ? $cotizacion['estado'] : '')); ?></div>
			</div>

			<div class="age-item"><div class="age-label">Nombre</div><div class="age-value"><?php echo_s(age_v_text(isset($cotizacion['nombre']) ? $cotizacion['nombre'] : '')); ?></div></div>
			<div class="age-item"><div class="age-label">Email</div><div class="age-value"><?php echo_s(age_v_text(isset($cotizacion['email']) ? $cotizacion['email'] : '')); ?></div></div>
			<div class="age-item"><div class="age-label">Teléfono</div><div class="age-value"><?php echo_s(age_v_text(isset($cotizacion['telefono']) ? $cotizacion['telefono'] : '')); ?></div></div>
			<div class="age-item"><div class="age-label">Vehículo</div><div class="age-value"><?php echo_s(age_v_text($vehiculoTexto)); ?></div></div>
			<div class="age-item"><div class="age-label">Año</div><div class="age-value"><?php echo_s(age_v_text(isset($cotizacion['anio']) ? $cotizacion['anio'] : '')); ?></div></div>
			<div class="age-item"><div class="age-label">Kilómetros</div><div class="age-value"><?php echo_s(age_v_text(isset($cotizacion['km']) ? number_format((float)$cotizacion['km'], 0, ',', '.') : '')); ?></div></div>
			<div class="age-item"><div class="age-label">Ficha en service oficial</div><div class="age-value"><?php echo_s(age_v_text(isset($cotizacion['ficha_tecnica']) ? $cotizacion['ficha_tecnica'] : '')); ?></div></div>
		</div>
	</div>

	<div class="age-col">
		<div class="age-card">
			<h4>Histórico de notificaciones y respuestas</h4>

			<?php if (!empty($notificaciones)): ?>
				<div class="age-chat-wrap" id="age-chat-wrap">
				<div class="age-chat">
				<?php foreach ($notificaciones as $noti): ?>
					<?php
					$tipoNoti = isset($noti['tipo_notificacion']) ? $noti['tipo_notificacion'] : '';
					$esCliente = age_v_notificacion_es_cliente($tipoNoti);
					$ladoClass = $esCliente ? 'cliente' : 'motorlider';
					$mensajeMostrar = trim((string)(isset($noti['mensaje_enviado']) ? $noti['mensaje_enviado'] : ''));
					if ($mensajeMostrar === '' && !empty($noti['respuesta_api'])) {
						$mensajeMostrar = (string)$noti['respuesta_api'];
					}
					?>
					<div class="age-chat-row <?php echo $ladoClass; ?>">
						<div class="age-chat-bubble <?php echo $ladoClass; ?>">
							<div class="age-chat-header">
								<div class="age-chat-titulo"><?php echo_s(age_v_notificacion_titulo_chat($tipoNoti)); ?></div>
								<div class="age-chat-fecha"><?php echo_s(age_v_fecha_hora(isset($noti['fecha_envio']) ? $noti['fecha_envio'] : '')); ?></div>
							</div>
							<div class="age-chat-mensaje"><?php echo_s(age_v_text($mensajeMostrar)); ?></div>
						</div>
					</div>
				<?php endforeach; ?>
				</div>
				</div>
			<?php else: ?>
				<div class="age-historial-empty">No hay notificaciones ni respuestas registradas para esta agenda.</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<script>
(function(){
  var wrap = document.getElementById('age-chat-wrap');
  if (wrap) {
    wrap.scrollTop = wrap.scrollHeight;
  }
})();
</script>

<?php require_once('sistema_post_contenido.php'); ?>
