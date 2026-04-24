<?php

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

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

$convMensajes = array();

if (isset($db)) {

	$telefonoCot = trim((string)($elemento['telefono'] ?? ''));

	if ($telefonoCot !== '') {

		$telefonosBuscar = array();

		// Teléfono tal cual viene en la cotización
		$telefonosBuscar[] = $telefonoCot;

		// Solo números
		$soloNumeros = preg_replace('/[^0-9]/', '', $telefonoCot);

		if ($soloNumeros !== '') {
			$telefonosBuscar[] = $soloNumeros;
			$telefonosBuscar[] = '+' . $soloNumeros;
			$telefonosBuscar[] = 'whatsapp:+' . $soloNumeros;

			// Si está guardado como 098..., armar formato Uruguay
			if (strlen($soloNumeros) == 9 && substr($soloNumeros, 0, 1) == '0') {
				$uy = '598' . substr($soloNumeros, 1);
				$telefonosBuscar[] = $uy;
				$telefonosBuscar[] = '+' . $uy;
				$telefonosBuscar[] = 'whatsapp:+' . $uy;
			}
		}

		$telefonosBuscar = array_unique(array_filter($telefonosBuscar));

		$whereTelefonos = array();
		foreach ($telefonosBuscar as $tel) {
			$whereTelefonos[] = 'telefono = "' . addslashes($tel) . '"';
		}

		if (!empty($whereTelefonos)) {

			$sqlMensajes = '
				SELECT id, id_conversacion, telefono, direccion, emisor, mensaje, meta_json, sid_mensaje, fecha
				FROM whatsapp_conversacion_mensajes
				WHERE (' . implode(' OR ', $whereTelefonos) . ')
				ORDER BY fecha ASC, id ASC
			';

			$qMensajes = $db->query($sqlMensajes);

			if ($qMensajes) {
				while ($rowMsg = $db->fetch_array($qMensajes)) {
					$convMensajes[] = $rowMsg;
				}
			}
		}
	}
}

if (!function_exists('cot_v_hora_chat')) {
	function cot_v_hora_chat($valor) {
		if (!isset($valor) || trim((string)$valor) === '') return '';
		$ts = strtotime($valor);
		if ($ts === false) return trim((string)$valor);
		return date('d/m/Y H:i', $ts);
	}
}

?>
<?php require_once('sistema_cabezal.php'); ?>
<?php require_once('sistema_pre_contenido.php'); ?>

<style>
	.estado-pendiente { color: #c09853; }
	.estado-finalizada { color: #468847; }

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

	.btn[disabled],
	button[disabled] {
		opacity: .55;
		cursor: not-allowed !important;
	}

	@media (max-width: 900px) {
		.cot-label { width: 150px; }
	}

	.cot-chat-card {
		background: #fff;
		border: 1px solid #ddd;
		border-radius: 8px;
		padding: 18px;
		margin-top: 18px;
	}

	.cot-chat-box {
		max-height: 520px;
		overflow-y: auto;
		background: #efeae2;
		border: 1px solid #ddd;
		border-radius: 8px;
		padding: 16px;
	}

	.cot-chat-empty {
		color: #666;
		font-weight: bold;
		padding: 12px;
		background: #fff;
		border-radius: 8px;
	}

	.cot-msg-row {
		display: flex;
		margin-bottom: 12px;
	}

	.cot-msg-row.in { justify-content: flex-start; }
	.cot-msg-row.out { justify-content: flex-end; }

	.cot-msg-bubble {
		max-width: 72%;
		padding: 10px 12px 8px 12px;
		border-radius: 10px;
		box-shadow: 0 1px 1px rgba(0,0,0,.08);
		line-height: 1.35;
		word-break: break-word;
	}

	.cot-msg-bubble.bot {
		background: #fff;
		border-top-left-radius: 2px;
	}

	.cot-msg-bubble.humano,
	.cot-msg-bubble.cliente {
		background: #d9fdd3;
		border-top-right-radius: 2px;
	}

	.cot-msg-meta {
		font-size: 11px;
		color: #666;
		margin-bottom: 4px;
		font-weight: bold;
	}

	.cot-msg-text {
		white-space: pre-wrap;
		color: #111;
	}

	.cot-msg-time {
		font-size: 10px;
		color: #777;
		text-align: right;
		margin-top: 6px;
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
			<div class="cot-item">
				<div class="cot-label">Ficha en service oficial</div>
				<div class="cot-value">
					<?= ((int)$elemento['ficha_tecnica'] === 1) ? 'Sí' : 'No'; ?>
				</div>
			</div>
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

			<div class="cot-edit-actions" style="margin-top:10px;">
				<button type="button" class="btn btn-success" id="btn_enviar_pre_whatsapp" onclick="enviarRespuestaWhatsapp('pre');">
					Enviar pre tasación
				</button>
				<button type="button" class="btn btn-success" id="btn_enviar_final_whatsapp" onclick="enviarRespuestaFinal('final');">
					Enviar tasación final
				</button>
				<span class="cot-feedback" id="whatsapp_feedback"></span>
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

<div class="cot-chat-card">
	<h4>Conversación WhatsApp</h4>
	<?php if (!empty($convMensajes)) { ?>
		<div class="cot-chat-box" id="cot_chat_box">
			<?php foreach ($convMensajes as $msg) { ?>
				<?php
				$direccion = strtoupper(trim((string)($msg['direccion'] ?? '')));
				$emisor = strtoupper(trim((string)($msg['emisor'] ?? '')));
				$rowClass = ($direccion === 'ENTRANTE') ? 'out' : 'in';
				$bubbleClass = 'bot';
				$emisorLabel = 'Bot';

				if ($emisor === 'CLIENTE') {
					$bubbleClass = 'cliente';
					$emisorLabel = 'Cliente';
				} elseif ($emisor === 'HUMANO') {
					$bubbleClass = 'humano';
					$emisorLabel = 'Asesor';
				} elseif ($emisor === 'BOT') {
					$bubbleClass = 'bot';
					$emisorLabel = 'Bot';
				}
				?>
				<div class="cot-msg-row <?php echo $rowClass; ?>">
					<div class="cot-msg-bubble <?php echo $bubbleClass; ?>">
						<div class="cot-msg-meta"><?php echo_s($emisorLabel); ?></div>
						<div class="cot-msg-text"><?php echo nl2br(htmlspecialchars((string)($msg['mensaje'] ?? ''))); ?></div>
						<div class="cot-msg-time"><?php echo_s(cot_v_hora_chat($msg['fecha'] ?? '')); ?></div>
					</div>
				</div>
			<?php } ?>
		</div>
	<?php } else { ?>
		<div class="cot-chat-empty">No hay conversación registrada para esta cotización.</div>
	<?php } ?>
</div>

<script>
var pretasacionDesdeOriginal = document.getElementById('pretasacion_desde').value;
var pretasacionHastaOriginal = document.getElementById('pretasacion_hasta').value;
var tasacionFinalOriginal = document.getElementById('tasacion_final').value;

function tieneValor(valor) {
	return String(valor || '').trim() !== '';
}

function actualizarBotonesEnvio() {
	var pretasacion_desde = document.getElementById('pretasacion_desde').value || '';
	var pretasacion_hasta = document.getElementById('pretasacion_hasta').value || '';
	var tasacion_final = document.getElementById('tasacion_final').value || '';

	var btnPre = document.getElementById('btn_enviar_pre_whatsapp');
	var btnFinal = document.getElementById('btn_enviar_final_whatsapp');

	if (btnPre) {
		btnPre.disabled = !(tieneValor(pretasacion_desde) && tieneValor(pretasacion_hasta));
	}

	if (btnFinal) {
		btnFinal.disabled = !tieneValor(tasacion_final);
	}
}

function habilitarEdicionTasacion() {
	document.getElementById('pretasacion_desde').disabled = false;
	document.getElementById('pretasacion_hasta').disabled = false;
	document.getElementById('tasacion_final').disabled = false;

	document.getElementById('btn_editar_tasacion').style.display = 'none';
	document.getElementById('btn_guardar_tasacion').style.display = 'inline-block';
	document.getElementById('btn_cancelar_tasacion').style.display = 'inline-block';

	document.getElementById('tasacion_feedback').innerHTML = '';
	actualizarBotonesEnvio();
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
	actualizarBotonesEnvio();
}

function guardarTasacionInterna() {
	var pretasacion_desde = document.getElementById('pretasacion_desde').value || '';
	var pretasacion_hasta = document.getElementById('pretasacion_hasta').value || '';
	var tasacion_final = document.getElementById('tasacion_final').value || '';
	var feedback = document.getElementById('tasacion_feedback');
	var btn = document.getElementById('btn_guardar_tasacion');

	if (!pretasacion_desde.trim() || !pretasacion_hasta.trim()) {
		feedback.style.color = '#b94a48';
		feedback.innerHTML = 'Completá pre tasación desde y hasta.';
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
				document.getElementById('pretasacion_desde').disabled = true;
				document.getElementById('pretasacion_hasta').disabled = true;
				document.getElementById('tasacion_final').disabled = true;
				document.getElementById('btn_editar_tasacion').style.display = 'inline-block';
				document.getElementById('btn_guardar_tasacion').style.display = 'none';
				document.getElementById('btn_cancelar_tasacion').style.display = 'none';
				actualizarBotonesEnvio();

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

function enviarRespuestaWhatsapp(modoEnvio) {
	var pretasacion_desde = document.getElementById('pretasacion_desde').value || '';
	var pretasacion_hasta = document.getElementById('pretasacion_hasta').value || '';
	var tasacion_final = document.getElementById('tasacion_final').value || '';
	var feedback = document.getElementById('whatsapp_feedback');
	var btnPre = document.getElementById('btn_enviar_pre_whatsapp');
	var btnFinal = document.getElementById('btn_enviar_final_whatsapp');

	if (modoEnvio === 'pre') {
		if (!pretasacion_desde.trim() || !pretasacion_hasta.trim()) {
			feedback.style.color = '#b94a48';
			feedback.innerHTML = 'Para enviar la pre tasación, completá desde y hasta.';
			return;
		}
	}

	if (modoEnvio === 'final') {
		if (!tasacion_final.trim()) {
			feedback.style.color = '#b94a48';
			feedback.innerHTML = 'Para enviar la tasación final, completá la tasación final.';
			return;
		}
	}

	btnPre.disabled = true;
	btnFinal.disabled = true;
	feedback.style.color = '#666';
	feedback.innerHTML = (modoEnvio === 'final') ? 'Enviando tasación final por WhatsApp...' : 'Enviando pre tasación por WhatsApp...';

	var xhr = new XMLHttpRequest();
	xhr.open('POST', '/adm/modulos/cot/ajax_enviar_cotizacion.php', true);
	xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');

	xhr.onreadystatechange = function() {
		if (xhr.readyState !== 4) return;

		actualizarBotonesEnvio();

		if (xhr.status !== 200) {
			feedback.style.color = '#b94a48';
			feedback.innerHTML = 'Error HTTP ' + xhr.status + '<br><small>' + xhr.responseText + '</small>';
			return;
		}

		try {
			var res = JSON.parse(xhr.responseText);

			if (res.ok) {
				feedback.style.color = '#468847';
				feedback.innerHTML = res.mensaje ? res.mensaje : 'Respuesta enviada correctamente.';
				setTimeout(function() {
					window.location.reload();
				}, 900);
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
		'&modo_envio=' + encodeURIComponent(modoEnvio) +
		'&pretasacion_desde=' + encodeURIComponent(pretasacion_desde) +
		'&pretasacion_hasta=' + encodeURIComponent(pretasacion_hasta) +
		'&tasacion_final=' + encodeURIComponent(tasacion_final)
	);
}

function enviarRespuestaFinal(modoEnvio) {
	var pretasacion_desde = document.getElementById('pretasacion_desde').value || '';
	var pretasacion_hasta = document.getElementById('pretasacion_hasta').value || '';
	var tasacion_final = document.getElementById('tasacion_final').value || '';
	var feedback = document.getElementById('whatsapp_feedback');
	var btnPre = document.getElementById('btn_enviar_pre_whatsapp');
	var btnFinal = document.getElementById('btn_enviar_final_whatsapp');

	if (modoEnvio === 'pre') {
		if (!pretasacion_desde.trim() || !pretasacion_hasta.trim()) {
			feedback.style.color = '#b94a48';
			feedback.innerHTML = 'Para enviar la pre tasación, completá desde y hasta.';
			return;
		}
	}

	if (modoEnvio === 'final') {
		if (!tasacion_final.trim()) {
			feedback.style.color = '#b94a48';
			feedback.innerHTML = 'Para enviar la tasación final, completá la tasación final.';
			return;
		}
	}

	btnPre.disabled = true;
	btnFinal.disabled = true;
	feedback.style.color = '#666';
	feedback.innerHTML = (modoEnvio === 'final') ? 'Enviando tasación final ...' : 'Enviando pre tasación por WhatsApp...';

	var xhr = new XMLHttpRequest();
	xhr.open('POST', '/adm/modulos/cot/ajax_guardar_tasacion_desde_v.php', true);
	xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');

	xhr.onreadystatechange = function() {
		if (xhr.readyState !== 4) return;

		actualizarBotonesEnvio();

		if (xhr.status !== 200) {
			feedback.style.color = '#b94a48';
			feedback.innerHTML = 'Error HTTP ' + xhr.status + '<br><small>' + xhr.responseText + '</small>';
			return;
		}

		try {
			var res = JSON.parse(xhr.responseText);

			if (res.ok) {
				feedback.style.color = '#468847';
				feedback.innerHTML = res.mensaje ? res.mensaje : 'Respuesta enviada correctamente.';
				setTimeout(function() {
					window.location.reload();
				}, 900);
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
		'&modo_envio=' + encodeURIComponent(modoEnvio) +
		'&pretasacion_desde=' + encodeURIComponent(pretasacion_desde) +
		'&pretasacion_hasta=' + encodeURIComponent(pretasacion_hasta) +
		'&tasacion_final=' + encodeURIComponent(tasacion_final)
	);
}

window.addEventListener('load', function () {
	var chatBox = document.getElementById('cot_chat_box');
	if (chatBox) {
		chatBox.scrollTop = chatBox.scrollHeight;
	}

	actualizarBotonesEnvio();

	var campos = ['pretasacion_desde', 'pretasacion_hasta', 'tasacion_final'];
	for (var i = 0; i < campos.length; i++) {
		var el = document.getElementById(campos[i]);
		if (el) {
			el.addEventListener('input', actualizarBotonesEnvio);
			el.addEventListener('change', actualizarBotonesEnvio);
		}
	}
});
</script>

<?php require_once('sistema_post_contenido.php'); ?>
