<?php
if (!isset($sistema_iniciado)) exit();

global $db;

$marcas = [];
$modelosPorMarca = [];

function _pickArr($row, $keys, $default = null) {
	foreach ($keys as $k) {
		if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null) return $row[$k];
	}
	return $default;
}

function findSchemaForTable($db, $tableName) {
	$t = $db->escape($tableName);
	$q = $db->query("
		SELECT TABLE_SCHEMA
		FROM information_schema.TABLES
		WHERE TABLE_NAME = '{$t}'
		ORDER BY TABLE_SCHEMA
		LIMIT 1
	");
	$r = $db->fetch_array($q);
	return $r ? ($r['TABLE_SCHEMA'] ?? null) : null;
}

try {
	// === detectar schema real de tablas ===
	$schemaMarca  = findSchemaForTable($db, 'act_marcas');
	$schemaModelo = findSchemaForTable($db, 'act_modelo');

	$tblMarca  = $schemaMarca  ? "{$schemaMarca}.act_marcas"  : "act_marcas";
	$tblModelo = $schemaModelo ? "{$schemaModelo}.act_modelo" : "act_modelo";

	// Marcas
	$qm = $db->query("SELECT * FROM {$tblMarca} ORDER BY nombre");
	while ($r = $db->fetch_array($qm)) {
		$id  = _pickArr($r, ['id_marca','id','marca_id']);
		$nom = _pickArr($r, ['nombre','name','marca']);
		if ($id === null || $nom === null) continue;
		$marcas[] = ['id' => (string)$id, 'nombre' => (string)$nom];
	}

	// Modelos
	$qo = $db->query("SELECT * FROM {$tblModelo} ORDER BY nombre");
	while ($r = $db->fetch_array($qo)) {
		$idMarca = _pickArr($r, ['id_marca','marca_id']);
		$idMod   = _pickArr($r, ['id_model','id_modelo','id_mdoelo','id','modelo_id']);
		$nomMod  = _pickArr($r, ['nombre','name','modelo']);
		if ($idMarca === null || $idMod === null || $nomMod === null) continue;

		$key = (string)$idMarca;
		if (!isset($modelosPorMarca[$key])) $modelosPorMarca[$key] = [];
		$modelosPorMarca[$key][] = ['id' => (string)$idMod, 'nombre' => (string)$nomMod];
	}
} catch (\Throwable $e) {
	$marcas = [];
	$modelosPorMarca = [];
}
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

<!-- ========================= -->
<!-- BLOQUE NUEVO: COTIZACION -->
<!-- ========================= -->
<div style="margin-top:10px;">
	<strong>Simulación de Cotización</strong>
</div>

<div class="row">
	<div class="span2 tr">Marca</div>
	<div class="span4">
		<select id="cotiza_marca" class="input">
			<option value="">-- Seleccionar --</option>
			<?php foreach ($marcas as $m): ?>
				<option value="<?php echo htmlspecialchars($m['id']); ?>">
					<?php echo htmlspecialchars($m['nombre']); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>
</div>

<div class="row">
	<div class="span2 tr">Modelo</div>
	<div class="span4">
		<select id="cotiza_modelo" class="input" disabled>
			<option value="">-- Seleccionar --</option>
		</select>
	</div>
</div>

<div class="row">
	<div class="span2 tr">Año</div>
	<div class="span2">
		<input type="number" id="cotiza_anio" class="input-mini" placeholder="Ej: 2020" />
	</div>
</div>

<div class="row">
	<div class="span2 tr">Versión</div>
	<div class="span4">
		<input type="text" id="cotiza_version" class="input" placeholder="Ej: Full, GLS, LTZ" />
	</div>
</div>

<div class="row">
	<div class="span2 tr">Tipo de venta</div>
	<div class="span4">
		<select id="cotiza_tipo_venta" class="input">
			<option value="">-- Seleccionar --</option>
			<option value="venta_contado">Venta contado</option>
			<option value="entrega_forma_pago">Entrega como forma de pago</option>
		</select>
	</div>
</div>

<div class="row">
	<div class="span2 tr">¿Posee ficha oficial?</div>
	<div class="span4">
		<select id="cotiza_ficha_oficial" class="input">
			<option value="">-- Seleccionar --</option>
			<option value="si">Sí</option>
			<option value="no">No</option>
		</select>
	</div>
</div>

<div class="row">
	<div class="span2 tr">Kilómetros</div>
	<div class="span2">
		<input type="number" id="cotiza_km" class="input-mini" placeholder="Ej: 85000" />
	</div>
</div>

<div class="row">
	<div class="span2 tr">Valor pretendido</div>
	<div class="span2">
		<input type="number" id="cotiza_valor" class="input-mini" placeholder="Ej: 15000" />
	</div>
</div>

<div class="row">
	<div class="span2 tr">Email</div>
	<div class="span4">
		<input type="email" id="cotiza_email" class="input" placeholder="Ej: cliente@email.com" />
	</div>
</div>

<div class="row" style="margin-top:10px;">
	<div class="span8">
		<button type="button" class="btn btn-primary btn-small" id="btn_cotiza_simular">Cotizar</button>
		<span id="cotiza_estado" style="margin-left:10px; font-weight:bold; color:#444;">Estado: -</span>
		<div id="cotiza_debug" style="margin-top:6px; font-size:12px; color:#666;">-</div>
	</div>
</div>

<div class="row" style="margin-top:10px;">
	<div class="span10">
		<div id="cotiza_resultado" style="display:none; border:1px solid #ddd; background:#fafafa; padding:10px;"></div>
	</div>
</div>

<hr>

<!-- ========================= -->
<!-- BLOQUE ACTUAL: FILTROS/APIFY -->
<!-- ========================= -->
<div class="row">
	<div class="span2 tr">Marca</div>
	<div class="span4">
		<select id="apify_marca" class="input">
			<option value="">-- Seleccionar --</option>
			<?php foreach ($marcas as $m): ?>
				<option value="<?php echo htmlspecialchars($m['id']); ?>">
					<?php echo htmlspecialchars($m['nombre']); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>
</div>

<div class="row">
	<div class="span2 tr">Modelo</div>
	<div class="span4">
		<select id="apify_modelo" class="input" disabled>
			<option value="">-- Seleccionar --</option>
		</select>
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
		<!-- <button type="button" class="btn btn-primary btn-small" id="btn_apify_buscar">Buscar (Apify)</button> -->
		<button type="button" class="btn btn-small" id="btn_apify_filtrar" style="margin-left:6px;">Filtrar</button>
		<button type="button" class="btn btn-small" id="btn_apify_limpiar" style="margin-left:6px;">Limpiar</button>
		<span id="apify_count" style="margin-left:10px; font-weight:bold; color:#444;">0 / 0</span>

		<span style="margin-left:10px; font-weight:bold;" id="apify_estado">Estado: -</span>
		<div style="margin-top:4px; font-size:12px; color:#666;" id="apify_progreso">-</div>
	</div>
</div>

<hr>

<table class="table table-hover" id="tabla_apify_resultados">
	<thead>
		<tr>
			<th>Marca</th>
			<th>Modelo</th>
			<th>Versión</th>
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
		<tr><td colspan="10" style="color:#777;">Sin resultados todavía.</td></tr>
	</tbody>
</table>

<script>
	const APIFY_MODELOS_POR_MARCA = <?php echo json_encode($modelosPorMarca, JSON_UNESCAPED_UNICODE); ?>;

	let apifyCorridaId = null;
	let apifyTimer = null;

	let APIFY_ROWS_ALL = [];
	let APIFY_ROWS_VIEW = [];

	function apifySetEstado(txt, sub) {
		$('#apify_estado').text('Estado: ' + (txt || '-'));
		$('#apify_progreso').text(sub || '-');
	}

	function cotizaSetEstado(txt, sub) {
		$('#cotiza_estado').text('Estado: ' + (txt || '-'));
		$('#cotiza_debug').text(sub || '-');
	}

	function cotizaEscapeHtml(v) {
		return $('<div>').text(v == null ? '' : String(v)).html();
	}

	function cotizaSetResultadoHtml(html) {
		$('#cotiza_resultado').html(html).show();
	}

	function cotizaClearResultado() {
		$('#cotiza_resultado').hide().html('');
	}

	function apifySetCount(show, total) {
		$('#apify_count').text(String(show || 0) + ' / ' + String(total || 0));
	}

	function apifyToIntOrNull(v) {
		if (v === null || v === undefined) return null;
		let s = String(v).replace(/[^\d]/g,'').trim();
		if (!s) return null;
		let n = parseInt(s, 10);
		return Number.isFinite(n) ? n : null;
	}

	function apifyRenderResultados(rows) {
		const $tb = $('#tabla_apify_resultados tbody');
		$tb.empty();

		if (!rows || !rows.length) {
			$tb.append('<tr><td colspan="10" style="color:#777;">Sin resultados.</td></tr>');
			return;
		}

		rows.forEach(r => {
			const marca   = ((r && r.marca)   ? r.marca   : '-').toString();
			const modelo  = ((r && r.modelo)  ? r.modelo  : '-').toString();
			const version = ((r && r.version) ? r.version : '-').toString();

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
					'<td>'+ $('<div>').text(marca).html() +'</td>' +
					'<td>'+ $('<div>').text(modelo).html() +'</td>' +
					'<td>'+ $('<div>').text(version).html() +'</td>' +
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

	function apifyApplyFilters() {
		const marcaId = ($('#apify_marca').val() || '').toString();
		const modeloId = ($('#apify_modelo').val() || '').toString();

		const marcaTxt = ($('#apify_marca option:selected').text() || '').toString().trim();
		let modeloTxt = ($('#apify_modelo option:selected').text() || '').toString().trim();

		const filtraModelo = (modeloId !== '' && modeloTxt !== '' && modeloTxt !== '-- Todos --' && modeloTxt !== '-- Seleccionar --');
		const filtraMarca  = (marcaId !== '' && marcaTxt !== '' && marcaTxt !== '-- Seleccionar --');

		const anioDesde = apifyToIntOrNull($('#apify_anio_desde').val());
		const anioHasta = apifyToIntOrNull($('#apify_anio_hasta').val());
		const kmDesde   = apifyToIntOrNull($('#apify_km_desde').val());
		const kmHasta   = apifyToIntOrNull($('#apify_km_hasta').val());

		const needYear = (anioDesde !== null || anioHasta !== null);
		const needKm   = (kmDesde !== null   || kmHasta !== null);

		APIFY_ROWS_VIEW = (APIFY_ROWS_ALL || []).filter(r => {
			const rMarca = ((r && r.marca) ? String(r.marca) : '').trim();
			const rModelo = ((r && r.modelo) ? String(r.modelo) : '').trim();

			if (filtraMarca) {
				if (!rMarca) return false;
				if (rMarca.toLowerCase() !== marcaTxt.toLowerCase()) return false;
			}

			if (filtraModelo) {
				if (!rModelo) return false;
				if (rModelo.toLowerCase().indexOf(modeloTxt.toLowerCase()) === -1) return false;
			}

			const rAnio = apifyToIntOrNull(r ? r.anio : null);
			const rKm   = apifyToIntOrNull(r ? r.km   : null);

			if (needYear) {
				if (rAnio === null) return false;
				if (anioDesde !== null && rAnio < anioDesde) return false;
				if (anioHasta !== null && rAnio > anioHasta) return false;
			}

			if (needKm) {
				if (rKm === null) return false;
				if (kmDesde !== null && rKm < kmDesde) return false;
				if (kmHasta !== null && rKm > kmHasta) return false;
			}

			return true;
		});

		apifyRenderResultados(APIFY_ROWS_VIEW);
		apifySetCount(APIFY_ROWS_VIEW.length, APIFY_ROWS_ALL.length);
		apifySetEstado('filtrado', 'Mostrando resultados filtrados (local).');
	}

	function apifyClearFilters() {
		$('#apify_anio_desde').val('');
		$('#apify_anio_hasta').val('');
		$('#apify_km_desde').val('');
		$('#apify_km_hasta').val('');

		APIFY_ROWS_VIEW = (APIFY_ROWS_ALL || []).slice(0);
		apifyRenderResultados(APIFY_ROWS_VIEW);
		apifySetCount(APIFY_ROWS_VIEW.length, APIFY_ROWS_ALL.length);
		apifySetEstado('ok', 'Filtros limpiados. Mostrando todo.');
	}

	function cargarModelosEnSelect(idMarca, selectDestino, textoVacio, textoPrimeraOpcion) {
		const $m = $(selectDestino);
		$m.empty();

		if (!idMarca || !APIFY_MODELOS_POR_MARCA[idMarca] || !APIFY_MODELOS_POR_MARCA[idMarca].length) {
			$m.append('<option value="">' + (textoVacio || '-- Seleccionar --') + '</option>');
			$m.prop('disabled', true);
			return;
		}

		$m.append('<option value="">' + (textoPrimeraOpcion || '-- Seleccionar --') + '</option>');
		APIFY_MODELOS_POR_MARCA[idMarca].forEach(x => {
			$m.append('<option value="'+ String(x.id).replace(/"/g,'&quot;') +'">'+ $('<div>').text(x.nombre).html() +'</option>');
		});
		$m.prop('disabled', false);
	}

	function cargarModelosPorMarca(idMarca) {
		cargarModelosEnSelect(idMarca, '#apify_modelo', '-- Seleccionar --', '-- Todos --');
	}

	function cargarModelosCotiza(idMarca) {
		cargarModelosEnSelect(idMarca, '#cotiza_modelo', '-- Seleccionar --', '-- Seleccionar --');
	}

	function cotizaBuildPayload() {
		const marcaId   = ($('#cotiza_marca').val() || '').toString();
		const marcaTxt  = ($('#cotiza_marca option:selected').text() || '').toString().trim();
		const modeloId  = ($('#cotiza_modelo').val() || '').toString();
		const modeloTxt = ($('#cotiza_modelo option:selected').text() || '').toString().trim();
		const anio      = ($('#cotiza_anio').val() || '').toString().trim();
		const version   = ($('#cotiza_version').val() || '').toString().trim();
		const tipoVenta = ($('#cotiza_tipo_venta').val() || '').toString().trim();
		const ficha     = ($('#cotiza_ficha_oficial').val() || '').toString().trim();
		const km        = ($('#cotiza_km').val() || '').toString().trim();
		const valor     = ($('#cotiza_valor').val() || '').toString().trim();
		const email     = ($('#cotiza_email').val() || '').toString().trim();

		const fichaTecnica = (ficha === 'si') ? 1 : 0;
		const ventaPermuta = (tipoVenta === 'entrega_forma_pago') ? 1 : 0;

		let nombreAuto = [marcaTxt, modeloTxt, anio, version].join(' ').replace(/\s+/g, ' ').trim();
		if (!nombreAuto) nombreAuto = 'Vehículo simulado';

		return {
			urlBrand: marcaId,
			payload: {
				marca: marcaId,
				modelo: modeloId,
				anio: anio,
				version: version,
				km: km,
				ficha_tecnica: fichaTecnica,
				cantidad_duenios: 1,
				valor_pretendido: valor,
				venta_permuta: ventaPermuta,
				nombre_auto: nombreAuto,
				nombre: 'Simulación Backend',
				email: email,
				telefono: '000000000'
			},
			meta: {
				marca_txt: marcaTxt,
				modelo_txt: modeloTxt,
				tipo_venta_txt: tipoVenta,
				ficha_txt: ficha
			}
		};
	}

	function cotizaValidar(build) {
		const p = build.payload;

		if (!build.urlBrand) return 'Debes seleccionar una marca.';
		if (!p.modelo) return 'Debes seleccionar un modelo.';
		if (!p.anio) return 'Debes ingresar el año.';
		if (!p.km) return 'Debes ingresar los kilómetros.';
		if (!p.valor_pretendido) return 'Debes ingresar el valor pretendido.';
		if (!p.email) return 'Debes ingresar el email.';
		if (!build.meta.tipo_venta_txt) return 'Debes seleccionar el tipo de venta.';
		if (!build.meta.ficha_txt) return 'Debes indicar si posee ficha oficial.';

		return '';
	}

	function cotizaRenderResponse(res, sentPayload, endpointUrl) {
		const ok = !!(res && res.ok);
		const mensaje = (res && (res.mensaje || res.msg)) ? (res.mensaje || res.msg) : (ok ? 'Cotización procesada.' : 'La API devolvió un error.');

		let idCotizacion = '';
		if (res) {
			idCotizacion = res.id_cotizacion || res.cotizacion_id || (res.data && (res.data.id_cotizacion || res.data.cotizacion_id)) || '';
		}

		let valores = null;
		if (res) {
			valores = res.valores || res.resultado || res.data || null;
		}

		let html = '';
		html += '<div style="font-weight:bold; margin-bottom:8px;">Resultado API cotización</div>';
		html += '<div><strong>Endpoint:</strong> ' + cotizaEscapeHtml(endpointUrl) + '</div>';
		html += '<div><strong>Estado:</strong> ' + (ok ? 'OK' : 'ERROR') + '</div>';
		html += '<div><strong>Mensaje:</strong> ' + cotizaEscapeHtml(mensaje) + '</div>';

		if (idCotizacion) {
			html += '<div><strong>ID Cotización:</strong> ' + cotizaEscapeHtml(idCotizacion) + '</div>';
		}

		if (valores && typeof valores === 'object') {
			const min = (valores.min !== undefined) ? valores.min : ((valores.valores && valores.valores.min !== undefined) ? valores.valores.min : '');
			const max = (valores.max !== undefined) ? valores.max : ((valores.valores && valores.valores.max !== undefined) ? valores.valores.max : '');
			const avg = (valores.avg !== undefined) ? valores.avg : ((valores.promedio !== undefined) ? valores.promedio : '');

			if (min !== '' || max !== '' || avg !== '') {
				html += '<hr style="margin:8px 0;">';
				html += '<div style="font-weight:bold; margin-bottom:4px;">Resumen</div>';
				if (min !== '') html += '<div><strong>Mínimo:</strong> ' + cotizaEscapeHtml(min) + '</div>';
				if (max !== '') html += '<div><strong>Máximo:</strong> ' + cotizaEscapeHtml(max) + '</div>';
				if (avg !== '') html += '<div><strong>Promedio:</strong> ' + cotizaEscapeHtml(avg) + '</div>';
			}
		}

		html += '<hr style="margin:8px 0;">';
		html += '<div style="font-weight:bold; margin-bottom:4px;">Payload enviado</div>';
		html += '<pre style="white-space:pre-wrap; font-size:12px; background:#fff; border:1px solid #eee; padding:8px;">' + cotizaEscapeHtml(JSON.stringify(sentPayload, null, 2)) + '</pre>';

		html += '<div style="font-weight:bold; margin:8px 0 4px 0;">Respuesta cruda</div>';
		html += '<pre style="white-space:pre-wrap; font-size:12px; background:#fff; border:1px solid #eee; padding:8px;">' + cotizaEscapeHtml(JSON.stringify(res, null, 2)) + '</pre>';

		cotizaSetResultadoHtml(html);
	}

	function apifyPoll() {
		if (!apifyCorridaId) return;

		$.getJSON('/adm/modulos/mlapify/apify_estado.php', { corrida_id: apifyCorridaId }, function(res){
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

				$.getJSON('/adm/modulos/mlapify/apify_resultados.php', { corrida_id: apifyCorridaId }, function(r2){
					if (r2 && r2.ok) {
						APIFY_ROWS_ALL = (r2.rows || []);
						APIFY_ROWS_VIEW = APIFY_ROWS_ALL.slice(0);

						apifyRenderResultados(APIFY_ROWS_VIEW);
						apifySetCount(APIFY_ROWS_VIEW.length, APIFY_ROWS_ALL.length);
					}
				});
			}
		}).fail(function(xhr){
			let msg = 'No se pudo contactar run_ml.php (HTTP ' + (xhr ? xhr.status : '') + ')';
			if (xhr && xhr.responseText) {
				try {
					const j = JSON.parse(xhr.responseText);
					if (j && j.mensaje) msg = j.mensaje + ' (HTTP ' + xhr.status + ')';
					else msg = xhr.responseText;
				} catch(e) {
					msg = xhr.responseText;
				}
			}
			apifySetEstado('error', msg);
			$('#btn_apify_buscar').prop('disabled', false);
		});
	}

	$('#apify_marca').on('change', function(){
		cargarModelosPorMarca($(this).val());
	});

	$('#cotiza_marca').on('change', function(){
		cargarModelosCotiza($(this).val());
	});

	$('#btn_apify_filtrar').on('click', function(){
		if (!APIFY_ROWS_ALL || !APIFY_ROWS_ALL.length) {
			apifySetEstado('info', 'No hay resultados cargados para filtrar todavía.');
			return;
		}
		apifyApplyFilters();
	});

	$('#btn_apify_limpiar').on('click', function(){
		apifyClearFilters();
	});

	$('#btn_cotiza_simular').on('click', function(){
	const $btn = $(this);
	const build = cotizaBuildPayload();
	const err = cotizaValidar(build);

	cotizaClearResultado();

	if (err) {
		cotizaSetEstado('validación', err);
		return;
	}

	const endpointUrl = '/apicotizador/cotizadorPublico/' + encodeURIComponent(build.urlBrand);

	$btn.prop('disabled', true);
	cotizaSetEstado('enviando', 'Invocando ' + endpointUrl + ' ...');

		$.ajax({
			url: endpointUrl,
			type: 'POST',
			data: JSON.stringify(build.payload),
			contentType: 'application/json; charset=utf-8',
			dataType: 'json'
		})
		.done(function(res){
			const success = !!(res && (
				res.ok === true ||
				res.error === 0 ||
				res.error === '0' ||
				res.error === false
			));

			if (success) {
				cotizaSetEstado('ok', (res.mensaje || res.msg || 'Cotización obtenida correctamente.'));
			} else {
				cotizaSetEstado('error', (res && (res.mensaje || res.msg)) ? (res.mensaje || res.msg) : 'La API respondió con error.');
			}

			cotizaRenderResponse(res, build.payload, endpointUrl);
		})
		.fail(function(xhr){
			let res = null;
			let msg = 'No se pudo contactar la API de cotización.';

			if (xhr && xhr.responseText) {
				try {
					res = JSON.parse(xhr.responseText);
					if (res && (res.mensaje || res.msg)) {
						msg = res.mensaje || res.msg;
					}
				} catch(e) {
					msg = 'HTTP ' + (xhr.status || '') + ' - ' + xhr.responseText;
				}
			} else if (xhr) {
				msg = 'HTTP ' + (xhr.status || '');
			}

			cotizaSetEstado('error', msg);
			cotizaRenderResponse(
				res || { error: true, msg: msg },
				build.payload,
				endpointUrl
			);
		})
		.always(function(){
			$btn.prop('disabled', false);
		});
	});

	$('#btn_apify_buscar').on('click', function(){
		$('#btn_apify_buscar').prop('disabled', true);

		const data = {
			marca_id: $('#apify_marca').val(),
			marca_txt: $('#apify_marca option:selected').text(),
			modelo_id: $('#apify_modelo').val(),
			modelo_txt: $('#apify_modelo option:selected').text(),
			anio_desde: $('#apify_anio_desde').val(),
			anio_hasta: $('#apify_anio_hasta').val(),
			km_desde: $('#apify_km_desde').val(),
			km_hasta: $('#apify_km_hasta').val()
		};

		apifySetEstado('enviando', 'Lanzando consulta...');
		$('#tabla_apify_resultados tbody').html('<tr><td colspan="10" style="color:#777;">Buscando...</td></tr>');

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

			APIFY_ROWS_ALL = [];
			APIFY_ROWS_VIEW = [];
			apifySetCount(0, 0);

			if (apifyTimer) clearInterval(apifyTimer);
			apifyTimer = setInterval(apifyPoll, 2000);
			apifyPoll();
		}).fail(function(xhr){
			let msg = 'No se pudo contactar run_ml.php (HTTP ' + (xhr ? xhr.status : '') + ')';
			if (xhr && xhr.responseText) {
				try {
					const j = JSON.parse(xhr.responseText);
					if (j && j.mensaje) msg = j.mensaje + ' (HTTP ' + xhr.status + ')';
					else msg = xhr.responseText;
				} catch(e) {
					msg = xhr.responseText;
				}
			}
			apifySetEstado('error', msg);
			$('#btn_apify_buscar').prop('disabled', false);
		});
	});

	$(function(){
		apifySetCount(0, 0);
		cotizaSetEstado('-', '-');

		$.getJSON('/adm/modulos/mlapify/apify_resultados_batch.php?limit=300', function(r){
			if (r && r.ok) {
				APIFY_ROWS_ALL = (r.rows || []);
				APIFY_ROWS_VIEW = APIFY_ROWS_ALL.slice(0);

				apifyRenderResultados(APIFY_ROWS_VIEW);
				apifySetCount(APIFY_ROWS_VIEW.length, APIFY_ROWS_ALL.length);
				apifySetEstado('ok', 'Mostrando resultados recientes del batch.');
			} else {
				apifySetEstado('error', (r && r.mensaje) ? r.mensaje : 'No se pudieron cargar resultados del batch.');
			}
		}).fail(function(xhr){
			let msg = 'No se pudo cargar apify_resultados_batch.php';
			if (xhr && xhr.responseText) {
				try {
					const j = JSON.parse(xhr.responseText);
					if (j && j.mensaje) msg = j.mensaje;
				} catch(e) {}
			}
			apifySetEstado('error', msg);
		});
	});
</script>

<?php require_once('sistema_post_contenido.php'); ?>