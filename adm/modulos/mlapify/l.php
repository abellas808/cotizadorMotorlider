<?php
// ***************************************************************************************************
// Chequeo que no se llame directamente
// ***************************************************************************************************
if (!isset($sistema_iniciado)) exit();
?>

<?php require_once('sistema_cabezal.php'); ?>
<?php require_once('sistema_pre_contenido.php'); ?>

<div id="contenido_cabezal">
	<h4 class="titulo"><?php echo $modulo['nombre']; ?></h4>
	<hr>
	<div style="margin-top: 10px;">
		<strong>Recolección de publicaciones públicas (ML via Apify)</strong>
	</div>
	<hr class="nb">
</div>

<div class="sep_titulo"></div>

<!-- Filtros (estilo admin: row + span) -->
<div class="row">
	<div class="span2 tr">Marca</div>
	<div class="span4">
		<input type="text" id="apify_marca" class="input" placeholder="Ej: Chevrolet" />
	</div>
</div>

<div class="row">
	<div class="span2 tr">Modelo</div>
	<div class="span4">
		<input type="text" id="apify_modelo" class="input" placeholder="Ej: Onix" />
	</div>
</div>

<div class="row">
	<div class="span2 tr">Año</div>
	<div class="span2">
		<input type="number" id="apify_anio_desde" class="input-mini" placeholder="Desde" />
	</div>
	<div class="span1 tr">a</div>
	<div class="span2">
		<input type="number" id="apify_anio_hasta" class="input-mini" placeholder="Hasta" />
	</div>
</div>

<div class="row">
	<div class="span2 tr">Kilómetros</div>
	<div class="span2">
		<input type="number" id="apify_km_desde" class="input-mini" placeholder="Desde" />
	</div>
	<div class="span1 tr">a</div>
	<div class="span2">
		<input type="number" id="apify_km_hasta" class="input-mini" placeholder="Hasta" />
	</div>
</div>

<div class="row" style="margin-top:10px;">
	<div class="span6">
		<button type="button" class="btn btn-primary btn-small" id="btn_apify_buscar">Buscar (Apify)</button>
		<span style="margin-left:10px; font-weight:bold;" id="apify_estado">Estado: -</span>
		<div style="margin-top:4px; font-size:12px; color:#666;" id="apify_progreso">-</div>
	</div>
</div>

<hr>

<!-- Grilla estilo admin -->
<table class="table table-hover" id="tabla_apify_resultados">
	<thead>
		<tr>
			<th>Título</th>
			<th style="width:110px;">Precio</th>
			<th style="width:70px;">Moneda</th>
			<th style="width:70px;">Año</th>
			<th style="width:90px;">Km</th>
			<th style="width:160px;">Ubicación</th>
			<th style="width:80px;">Link</th>
		</tr>
	</thead>
	<tbody>
		<tr><td colspan="7" style="color:#777;">Sin resultados todavía.</td></tr>
	</tbody>
</table>

<script>
	let apifyCorridaId = null;
	let apifyTimer = null;

	function apifySetEstado(txt, sub) {
		$('#apify_estado').text('Estado: ' + (txt || '-'));
		$('#apify_progreso').text(sub || '-');
	}

	function apifyRenderResultados(rows) {
		const $tb = $('#tabla_apify_resultados tbody');
		$tb.empty();

		if (!rows || !rows.length) {
			$tb.append('<tr><td colspan="7" style="color:#777;">Sin resultados.</td></tr>');
			return;
		}

		rows.forEach(r => {
			const titulo = (r.titulo || '').toString();
			const precio = (r.precio ?? '').toString();
			const moneda = (r.moneda || '').toString();
			const anio = (r.anio ?? '').toString();
			const km = (r.km ?? '').toString();
			const ubic = (r.ubicacion || '').toString();
			const url = (r.url || '').toString();
			const link = url ? '<a href="'+url+'" target="_blank">Ver</a>' : '-';

			$tb.append(
				'<tr>' +
					'<td>'+ $('<div>').text(titulo).html() +'</td>' +
					'<td>'+ $('<div>').text(precio).html() +'</td>' +
					'<td>'+ $('<div>').text(moneda).html() +'</td>' +
					'<td>'+ $('<div>').text(anio).html() +'</td>' +
					'<td>'+ $('<div>').text(km).html() +'</td>' +
					'<td>'+ $('<div>').text(ubic).html() +'</td>' +
					'<td>'+ link +'</td>' +
				'</tr>'
			);
		});
	}

	function apifyPoll() {
		if (!apifyCorridaId) return;

		$.getJSON('modulos/mlapify/apify_estado.php', { corrida_id: apifyCorridaId }, function(res){
			if (!res || !res.ok) {
				apifySetEstado('error', (res && res.mensaje) ? res.mensaje : 'No se pudo leer el estado');
				if (apifyTimer) clearInterval(apifyTimer);
				apifyTimer = null;
				$('#btn_apify_buscar').prop('disabled', false);
				return;
			}

			const sub = 'Items: ' + (res.total_items ?? '-') + ' | Guardados: ' + (res.items_guardados ?? '-') + (res.mensaje ? (' | ' + res.mensaje) : '');
			apifySetEstado(res.estado, sub);

			if (res.estado === 'ok' || res.estado === 'error') {
				if (apifyTimer) clearInterval(apifyTimer);
				apifyTimer = null;
				$('#btn_apify_buscar').prop('disabled', false);

				$.getJSON('modulos/mlapify/apify_resultados.php', { corrida_id: apifyCorridaId }, function(r2){
					if (r2 && r2.ok) apifyRenderResultados(r2.rows);
				});
			}
		}).fail(function(){
			apifySetEstado('error', 'No se pudo contactar apify_estado.php');
			if (apifyTimer) clearInterval(apifyTimer);
			apifyTimer = null;
			$('#btn_apify_buscar').prop('disabled', false);
		});
	}

	$('#btn_apify_buscar').on('click', function(){
		$('#btn_apify_buscar').prop('disabled', true);

		const data = {
			marca: $('#apify_marca').val(),
			modelo: $('#apify_modelo').val(),
			anio_desde: $('#apify_anio_desde').val(),
			anio_hasta: $('#apify_anio_hasta').val(),
			km_desde: $('#apify_km_desde').val(),
			km_hasta: $('#apify_km_hasta').val()
		};

		apifySetEstado('enviando', 'Lanzando consulta...');
		$('#tabla_apify_resultados tbody').html('<tr><td colspan="7" style="color:#777;">Buscando...</td></tr>');

		$.post('/adm/modulos/mlapify/run_ml.php', data, function(raw){
			let res = null;
			try { res = (typeof raw === 'string') ? JSON.parse(raw) : raw; } catch(e) {}

			if (!res || !res.ok) {
				apifySetEstado('error', (res && res.mensaje) ? res.mensaje : 'No se pudo iniciar la corrida');
				$('#btn_apify_buscar').prop('disabled', false);
				return;
			}

			apifyCorridaId = res.corrida_id;
			apifySetEstado('corriendo', 'Corrida #' + apifyCorridaId);

			if (apifyTimer) clearInterval(apifyTimer);
			apifyTimer = setInterval(apifyPoll, 2000);
			apifyPoll();
		}).fail(function(xhr){
			const status = xhr ? xhr.status : '';
			const text = (xhr && xhr.responseText) ? xhr.responseText : '';
			apifySetEstado('error', 'HTTP ' + status + ' ' + (text ? text : 'Sin body'));
			$('#btn_apify_buscar').prop('disabled', false);
			});
	});
</script>

<?php require_once('sistema_post_contenido.php'); ?>