<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($sistema_iniciado)) exit();

$id = intval($_GET['i']);

$elemento = $db->query_first('
	SELECT *
	FROM cotizaciones_generadas
	WHERE id_cotizaciones_generadas = "' . $id . '"
	LIMIT 1;
');

if (!$elemento) {
	header('Location: ?m=' . $modulo['prefijo'] . '_l');
	exit();
}

$usuario_cotizo = null;
if (!empty($elemento['id_usuario_cotizo']) && isset($db)) {
	$usuario_cotizo = $db->query_first('
		SELECT id, nombre, email
		FROM admin_usuarios
		WHERE id = "' . intval($elemento['id_usuario_cotizo']) . '"
		LIMIT 1;
	');
}

if (!function_exists('cot_v_money')) {
	function cot_v_money($valor) {
		if ($valor === null || $valor === '' || !is_numeric($valor)) {
			return 'U$S 0';
		}
		return 'U$S ' . number_format((float)$valor, 0, ',', '.');
	}
}

if (!function_exists('cot_v_input_money')) {
	function cot_v_input_money($valor) {
		if ($valor === null || $valor === '' || !is_numeric($valor)) {
			return '';
		}
		return number_format((float)$valor, 0, '', '');
	}
}

if (!function_exists('cot_v_text')) {
	function cot_v_text($valor, $fallback = '-') {
		$valor = trim((string)$valor);
		return $valor !== '' ? $valor : $fallback;
	}
}

$fechaFormateada = '-';
if (!empty($elemento['fecha']) && strtotime($elemento['fecha']) !== false) {
	$fechaFormateada = date('d/m/Y', strtotime($elemento['fecha']));
}

$vehiculoTexto = '';
if (($elemento['familia'] ?? '') == 'otro') {
	$vehiculoTexto = (string)$elemento['auto'];
} elseif (is_numeric($elemento['familia'] ?? null)) {
	$vehiculoTexto = (string)$elemento['auto'];
} else {
	$vehiculoTexto = trim((string)$elemento['auto'] . ' ' . strtoupper((string)$elemento['familia']));
}

?>
<?php require_once('sistema_cabezal.php'); ?>
<?php require_once('sistema_pre_contenido.php'); ?>

<style>
	.modal-cotizacion-overlay {
		display: none;
		position: fixed;
		inset: 0;
		background: rgba(0,0,0,.45);
		z-index: 9998;
	}

	.modal-cotizacion {
		display: none;
		position: fixed;
		top: 8%;
		left: 50%;
		transform: translateX(-50%);
		width: 640px;
		max-width: 92%;
		background: #fff;
		border: 1px solid #d9d9d9;
		border-radius: 8px;
		box-shadow: 0 8px 30px rgba(0,0,0,.18);
		z-index: 9999;
		padding: 18px;
	}

	.modal-cotizacion h4 {
		margin: 0 0 14px 0;
	}

	.modal-cotizacion .fila {
		margin-bottom: 12px;
	}

	.modal-cotizacion input[type="text"],
	.modal-cotizacion textarea {
		width: 100%;
		box-sizing: border-box;
	}

	.modal-cotizacion textarea {
		min-height: 160px;
		resize: vertical;
	}

	.modal-cotizacion .acciones {
		margin-top: 14px;
		display: flex;
		gap: 8px;
		align-items: center;
		flex-wrap: wrap;
	}

	.estado-pendiente { color: #c09853; }
	.estado-finalizada { color: #468847; }
	#envio_feedback { font-weight: bold; }

	.cot-card {
		background: #fff;
		border: 1px solid #ddd;
		border-radius: 8px;
		padding: 18px;
		margin-bottom: 18px;
	}

	.cot-card h4 {
		margin: 0 0 16px 0;
		font-size: 20px;
	}

	.cot-card-destacado {
		background: #f9fbff;
		border: 1px solid #cfdcf1;
	}

	.cot-card-destacado h4 {
		font-size: 22px;
		font-weight: bold;
	}

	.cot-grid-2 {
		display: flex;
		flex-wrap: wrap;
		gap: 18px;
	}

	.cot-grid-2 > .cot-col {
		flex: 1 1 420px;
	}

	.cot-grid-2-sm {
		display: flex;
		flex-wrap: wrap;
		gap: 18px;
	}

	.cot-grid-2-sm > .cot-col {
		flex: 1 1 320px;
	}

	.cot-item {
		display: flex;
		gap: 14px;
		padding: 7px 0;
		border-bottom: 1px solid #f1f1f1;
		align-items: center;
	}

	.cot-item:last-child { border-bottom: 0; }

	.cot-label {
		width: 190px;
		font-weight: bold;
		color: #333;
	}

	.cot-value {
		flex: 1;
		font-weight: bold;
		color: #111;
	}

	.cot-value-big {
		font-size: 22px;
		font-weight: bold;
		color: #1f3d5a;
	}

	.cot-box-resumen {
		background: #fffdf6;
		border: 1px solid #ead9a5;
		border-radius: 8px;
		padding: 16px;
	}

	.cot-box-resumen .cot-item { border-bottom-color: #f1e7c8; }

	.cot-edit-actions {
		margin-top: 14px;
		display: flex;
		gap: 8px;
		align-items: center;
		flex-wrap: wrap;
	}

	.cot-edit-field {
		width: 180px;
		max-width: 100%;
		padding: 7px 10px;
		border: 1px solid #ccc;
		border-radius: 4px;
		font-weight: bold;
		font-size: 18px;
		color: #1f3d5a;
		background: #fff;
	}

	.cot-edit-field[disabled] {
		background: transparent;
		border-color: transparent;
		box-shadow: none;
		padding-left: 0;
	}

	.cot-feedback {
		font-weight: bold;
	}

	@media (max-width: 900px) {
		.cot-label { width: 150px; }
	}

	@media (max-width: 700px) {
		.cot-item {
			flex-direction: column;
			gap: 4px;
			align-items: flex-start;
		}
		.cot-label { width: auto; }
		.cot-edit-field { width: 100%; }
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

<div class="cot-grid-2">
	<div class="cot-col">
		<div class="cot-card">
			<h4>Datos que dio el cliente</h4>

			<div class="cot-item"><div class="cot-label">Código</div><div class="cot-value"><?php echo_s($elemento['id_cotizaciones_generadas']); ?></div></div>
			<div class="cot-item"><div class="cot-label">Fecha</div><div class="cot-value"><?php echo_s($fechaFormateada); ?></div></div>
			<div class="cot-item">
				<div class="cot-label">Estado</div>
				<div class="cot-value <?php echo (($elemento['estado'] ?? '') == 'PENDIENTE') ? 'estado-pendiente' : 'estado-finalizada'; ?>">
					<?php echo_s($elemento['estado']); ?>
				</div>
			</div>

			<?php if (($elemento['estado'] ?? '') == 'PENDIENTE'): ?>
			<div class="cot-item"><div class="cot-label">Motivo</div><div class="cot-value estado-pendiente"><?php echo_s($elemento['detalle_estado']); ?></div></div>
			<?php endif; ?>

			<div class="cot-item"><div class="cot-label">Nombre</div><div class="cot-value"><?php echo_s($elemento['nombre']); ?></div></div>
			<div class="cot-item"><div class="cot-label">Email</div><div class="cot-value"><?php echo_s($elemento['email']); ?></div></div>
			<div class="cot-item"><div class="cot-label">Teléfono</div><div class="cot-value"><?php echo_s($elemento['telefono']); ?></div></div>
			<div class="cot-item"><div class="cot-label">Vehículo</div><div class="cot-value"><?php echo_s(cot_v_text($vehiculoTexto)); ?></div></div>
			<div class="cot-item"><div class="cot-label">Año</div><div class="cot-value"><?php echo_s(cot_v_text($elemento['anio'])); ?></div></div>
			<div class="cot-item"><div class="cot-label">Kilómetros</div><div class="cot-value"><?php echo_s(is_numeric($elemento['kilometros'] ?? null) ? number_format((float)$elemento['kilometros'], 0, ',', '.') : '-'); ?></div></div>
			<div class="cot-item"><div class="cot-label">Ficha en service oficial</div><div class="cot-value"><?php echo_s(cot_v_text($elemento['ficha_tecnica'])); ?></div></div>
			<div class="cot-item"><div class="cot-label">Cantidad de Dueños</div><div class="cot-value"><?php echo_s(cot_v_text($elemento['duenios'])); ?></div></div>
			<div class="cot-item"><div class="cot-label">Tipo de Venta</div><div class="cot-value"><?php echo (($elemento['tipo_venta'] ?? '') == 'Venta') ? 'Venta Contado' : 'Entrega como forma de pago'; ?></div></div>
			<div class="cot-item"><div class="cot-label">Valor Pretendido</div><div class="cot-value"><?php echo_s(cot_v_money($elemento['precio_pretendido'])); ?></div></div>
		</div>
	</div>

	<div class="cot-col">
		<div class="cot-card cot-card-destacado">
			<h4>Tasación interna</h4>

			<div class="cot-box-resumen">
				<div class="cot-item">
					<div class="cot-label">Pre tasación desde</div>
					<div class="cot-value cot-value-big">
						<input type="number" step="1" id="pretasacion_desde" class="cot-edit-field" value="<?php echo htmlspecialchars(cot_v_input_money($elemento['pretasacion_desde'])); ?>" disabled>
					</div>
				</div>

				<div class="cot-item">
					<div class="cot-label">Pre tasación hasta</div>
					<div class="cot-value cot-value-big">
						<input type="number" step="1" id="pretasacion_hasta" class="cot-edit-field" value="<?php echo htmlspecialchars(cot_v_input_money($elemento['pretasacion_hasta'])); ?>" disabled>
					</div>
				</div>

				<div class="cot-item">
					<div class="cot-label">Tasación final</div>
					<div class="cot-value cot-value-big">
						<input type="number" step="1" id="tasacion_final" class="cot-edit-field" value="<?php echo htmlspecialchars(cot_v_input_money($elemento['tasacion_final'])); ?>" disabled>
					</div>
				</div>

				<div class="cot-item">
					<div class="cot-label">Usuario que la hizo</div>
					<div class="cot-value cot-value-big" id="usuario_cotizo_nombre">
						<?php echo_s(($usuario_cotizo && !empty($usuario_cotizo['nombre'])) ? $usuario_cotizo['nombre'] : '-'); ?>
					</div>
				</div>
			</div>

			<div class="cot-edit-actions">
				<button type="button" class="btn btn-small btn-primary" id="btn_editar_tasacion" onclick="habilitarEdicionTasacion();">
					Editar
				</button>
				<button type="button" class="btn btn-small btn-success" id="btn_guardar_tasacion" onclick="guardarTasacionInterna();" style="display:none;">
					Guardar
				</button>
				<button type="button" class="btn btn-small" id="btn_cancelar_tasacion" onclick="cancelarEdicionTasacion();" style="display:none;">
					Cancelar
				</button>
				<span class="cot-feedback" id="tasacion_feedback"></span>
			</div>

			<?php if ($usuario_cotizo && !empty($usuario_cotizo['email'])): ?>
			<div class="cot-item" style="margin-top:12px;">
				<div class="cot-label">Email usuario</div>
				<div class="cot-value" id="usuario_cotizo_email"><?php echo_s($usuario_cotizo['email']); ?></div>
			</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<div class="cot-grid-2-sm">
	<div class="cot-col">
		<div class="cot-card">
			<h4>Valores Motorlider</h4>
			<div class="cot-item"><div class="cot-label">Valor Mínimo Motorlider</div><div class="cot-value"><?php echo_s(cot_v_money($elemento['valor_minimo_autodata'])); ?></div></div>
			<div class="cot-item"><div class="cot-label">Valor Máximo Motorlider</div><div class="cot-value"><?php echo_s(cot_v_money($elemento['valor_maximo_autodata'])); ?></div></div>
			<div class="cot-item"><div class="cot-label">Valor Promedio Motorlider</div><div class="cot-value"><?php echo_s(cot_v_money($elemento['valor_promedio_autodata'])); ?></div></div>
		</div>
	</div>

	<div class="cot-col">
		<div class="cot-card">
			<h4>Valores de Mercado</h4>
			<div class="cot-item"><div class="cot-label">Valor Mínimo de Mercado</div><div class="cot-value"><?php echo_s(cot_v_money($elemento['valor_minimo'])); ?></div></div>
			<div class="cot-item"><div class="cot-label">Valor Máximo de Mercado</div><div class="cot-value"><?php echo_s(cot_v_money($elemento['valor_maximo'])); ?></div></div>
			<div class="cot-item"><div class="cot-label">Valor Promedio de Mercado</div><div class="cot-value"><?php echo_s(cot_v_money($elemento['valor_promedio'])); ?></div></div>
		</div>
	</div>
</div>

<?php if (($elemento['estado'] ?? '') == 'PENDIENTE'): ?>
<div class="row" style="margin-top:15px;">
	<div class="span8">
		<button type="button" class="btn btn-success" onclick="abrirEnviarCotizacion();">Enviar cotización</button>
	</div>
</div>
<?php endif; ?>

<div class="modal-cotizacion-overlay" id="modal_enviar_overlay" onclick="cerrarEnviarCotizacion();"></div>

<div class="modal-cotizacion" id="modal_enviar">
	<h4>Enviar cotización</h4>

	<div class="fila">
		<label for="email_envio"><strong>Email del cliente</strong></label>
		<input type="text" id="email_envio" value="<?php echo htmlspecialchars((string)$elemento['email']); ?>">
	</div>

	<div class="fila">
		<label for="mensaje_envio"><strong>Mensaje a enviar</strong></label>
		<textarea id="mensaje_envio"><?php echo htmlspecialchars(strip_tags((string)$elemento['msg'])); ?></textarea>
	</div>

	<div class="acciones">
		<button type="button" class="btn btn-success" id="btn_confirmar_envio" onclick="enviarCotizacionManual();">Enviar</button>
		<button type="button" class="btn" onclick="cerrarEnviarCotizacion();">Cancelar</button>
		<span id="envio_feedback"></span>
	</div>
</div>

<script>
var pretasacionDesdeOriginal = document.getElementById('pretasacion_desde').value;
var pretasacionHastaOriginal = document.getElementById('pretasacion_hasta').value;
var tasacionFinalOriginal = document.getElementById('tasacion_final').value;

function abrirEnviarCotizacion() {
	document.getElementById('modal_enviar_overlay').style.display = 'block';
	document.getElementById('modal_enviar').style.display = 'block';
	document.getElementById('envio_feedback').innerHTML = '';
}

function cerrarEnviarCotizacion() {
	document.getElementById('modal_enviar_overlay').style.display = 'none';
	document.getElementById('modal_enviar').style.display = 'none';
}

function habilitarEdicionTasacion() {
	document.getElementById('pretasacion_desde').disabled = false;
	document.getElementById('pretasacion_hasta').disabled = false;
	document.getElementById('tasacion_final').disabled = false;

	document.getElementById('btn_editar_tasacion').style.display = 'none';
	document.getElementById('btn_guardar_tasacion').style.display = 'inline-block';
	document.getElementById('btn_cancelar_tasacion').style.display = 'inline-block';

	document.getElementById('tasacion_feedback').innerHTML = '';
}

function cancelarEdicionTasacion() {
	document.getElementById('pretasacion_desde').value = pretasacionDesdeOriginal;
	document.getElementById('pretasacion_hasta').value = pretasacionHastaOriginal;
	document.getElementById('tasacion_final').value = tasacionFinalOriginal;

	document.getElementById('pretasacion_desde').disabled = true;
	document.getElementById('pretasacion_hasta').disabled = true;
	document.getElementById('tasacion_final').disabled = true;

	document.getElementById('btn_editar_tasacion').style.display = 'inline-block';
	document.getElementById('btn_guardar_tasacion').style.display = 'none';
	document.getElementById('btn_cancelar_tasacion').style.display = 'none';

	document.getElementById('tasacion_feedback').innerHTML = '';
}

function guardarTasacionInterna() {
	var pretasacion_desde = document.getElementById('pretasacion_desde').value || '';
	var pretasacion_hasta = document.getElementById('pretasacion_hasta').value || '';
	var tasacion_final = document.getElementById('tasacion_final').value || '';
	var feedback = document.getElementById('tasacion_feedback');
	var btn = document.getElementById('btn_guardar_tasacion');

	if (!pretasacion_desde.trim() || !pretasacion_hasta.trim() || !tasacion_final.trim()) {
		feedback.style.color = '#b94a48';
		feedback.innerHTML = 'Completá todos los campos.';
		return;
	}

	btn.disabled = true;
	feedback.style.color = '#666';
	feedback.innerHTML = 'Guardando...';

	var xhr = new XMLHttpRequest();
	xhr.open('POST', '/adm/modulos/cot/ajax_guardar_tasacion_desde_v.php', true);
	xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');

	xhr.onreadystatechange = function() {
		if (xhr.readyState !== 4) return;

		btn.disabled = false;

		if (xhr.status !== 200) {
			feedback.style.color = '#b94a48';
			feedback.innerHTML = 'Error HTTP ' + xhr.status + '<br><small>' + xhr.responseText + '</small>';
			return;
		}

		try {
			var res = JSON.parse(xhr.responseText);

			if (res.ok) {
				pretasacionDesdeOriginal = pretasacion_desde;
				pretasacionHastaOriginal = pretasacion_hasta;
				tasacionFinalOriginal = tasacion_final;

				document.getElementById('usuario_cotizo_nombre').innerHTML = res.usuario_nombre ? res.usuario_nombre : '-';
				if (document.getElementById('usuario_cotizo_email') && res.usuario_email) {
					document.getElementById('usuario_cotizo_email').innerHTML = res.usuario_email;
				}

				feedback.style.color = '#468847';
				feedback.innerHTML = 'Tasación guardada correctamente.';
				setTimeout(function() {
					window.location.reload();
				}, 700);
			} else {
				feedback.style.color = '#b94a48';
				feedback.innerHTML = res.mensaje ? res.mensaje : 'No se pudo guardar.';
			}
		} catch (e) {
			feedback.style.color = '#b94a48';
			feedback.innerHTML = 'Respuesta inválida del servidor:<br><small>' + xhr.responseText + '</small>';
		}
	};

	xhr.send(
		'id=<?php echo intval($id); ?>' +
		'&pretasacion_desde=' + encodeURIComponent(pretasacion_desde) +
		'&pretasacion_hasta=' + encodeURIComponent(pretasacion_hasta) +
		'&tasacion_final=' + encodeURIComponent(tasacion_final)
	);
}

function enviarCotizacionManual() {
	var email = document.getElementById('email_envio').value || '';
	var mensaje = document.getElementById('mensaje_envio').value || '';
	var btn = document.getElementById('btn_confirmar_envio');
	var feedback = document.getElementById('envio_feedback');

	if (!email.trim()) {
		feedback.style.color = '#b94a48';
		feedback.innerHTML = 'Ingresá un email válido.';
		return;
	}

	btn.disabled = true;
	feedback.style.color = '#666';
	feedback.innerHTML = 'Enviando...';

	var xhr = new XMLHttpRequest();
	xhr.open('POST', '/adm/modulos/cot/ajax_enviar_cotizacion.php', true);
	xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');

	xhr.onreadystatechange = function() {
		if (xhr.readyState !== 4) return;

		btn.disabled = false;

		if (xhr.status !== 200) {
			feedback.style.color = '#b94a48';
			feedback.innerHTML = 'Error HTTP ' + xhr.status + '<br><small>' + xhr.responseText + '</small>';
			return;
		}

		try {
			var res = JSON.parse(xhr.responseText);

			if (res.ok) {
				feedback.style.color = '#468847';
				feedback.innerHTML = 'Cotización enviada correctamente.';
				setTimeout(function() {
					window.location.reload();
				}, 700);
			} else {
				feedback.style.color = '#b94a48';
				feedback.innerHTML = res.mensaje ? res.mensaje : 'No se pudo enviar.';
			}
		} catch (e) {
			feedback.style.color = '#b94a48';
			feedback.innerHTML = 'Respuesta inválida del servidor:<br><small>' + xhr.responseText + '</small>';
		}
	};

	xhr.send(
		'id=<?php echo intval($id); ?>' +
		'&email=' + encodeURIComponent(email) +
		'&mensaje=' + encodeURIComponent(mensaje)
	);
}
</script>

<?php require_once('sistema_post_contenido.php'); ?>
