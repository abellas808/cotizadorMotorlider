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
	$schemaMarca  = findSchemaForTable($db, 'act_marcas');
	$schemaModelo = findSchemaForTable($db, 'act_modelo');

	$tblMarca  = $schemaMarca  ? "{$schemaMarca}.act_marcas"  : "act_marcas";
	$tblModelo = $schemaModelo ? "{$schemaModelo}.act_modelo" : "act_modelo";

	$qm = $db->query("SELECT * FROM {$tblMarca} ORDER BY nombre");
	while ($r = $db->fetch_array($qm)) {
		$id  = _pickArr($r, ['id_marca','id','marca_id']);
		$nom = _pickArr($r, ['nombre','name','marca']);
		if ($id === null || $nom === null) continue;
		$marcas[] = ['id' => (string)$id, 'nombre' => (string)$nom];
	}

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

<style>
	.mlapify-page {
		padding: 0 0 20px 0;
	}

	.mlapify-subtitle {
		margin: 10px 0 18px 0;
		font-size: 15px;
		color: #444;
	}

	.mlapify-grid-top {
		display: flex;
		gap: 20px;
		align-items: flex-start;
		flex-wrap: wrap;
		margin-bottom: 20px;
	}

	.mlapify-card {
		flex: 1 1 460px;
		background: #fff;
		border: 1px solid #ddd;
		border-radius: 8px;
		box-shadow: 0 1px 4px rgba(0,0,0,.05);
		padding: 18px 18px 14px 18px;
	}

	.mlapify-card-title {
		font-size: 20px;
		font-weight: 700;
		color: #222;
		margin: 0 0 14px 0;
		padding-bottom: 10px;
		border-bottom: 1px solid #eee;
	}

	.mlapify-form-row {
		display: flex;
		align-items: center;
		margin-bottom: 12px;
		gap: 12px;
	}

	.mlapify-form-label {
		width: 150px;
		min-width: 150px;
		text-align: right;
		font-weight: 600;
		color: #333;
		padding-top: 2px;
	}

	.mlapify-form-field {
		flex: 1;
	}

	.mlapify-form-field input[type="text"],
	.mlapify-form-field input[type="email"],
	.mlapify-form-field input[type="number"],
	.mlapify-form-field select {
		width: 100%;
		max-width: 100%;
		box-sizing: border-box;
		height: 34px;
		padding: 6px 10px;
		border: 1px solid #cfcfcf;
		border-radius: 4px;
		background: #fff;
		margin-bottom: 0;
	}

	.mlapify-range {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	.mlapify-range input[type="number"] {
		flex: 1;
		min-width: 0;
	}

	.mlapify-range-sep {
		color: #666;
		font-weight: 600;
	}

	.mlapify-actions {
		display: flex;
		align-items: center;
		flex-wrap: wrap;
		gap: 8px;
		margin-top: 16px;
	}

	.mlapify-status-inline {
		margin-left: 6px;
		font-weight: 700;
		color: #333;
	}

	.mlapify-help {
		margin-top: 8px;
		font-size: 12px;
		color: #666;
	}

	.mlapify-result-box {
		display: none;
		margin-top: 16px;
		border: 1px solid #d8d8d8;
		background: #fcfcfc;
		border-radius: 6px;
		padding: 14px;
		max-height: 520px;
		overflow: auto;
	}

	.mlapify-table-card {
		background: #fff;
		border: 1px solid #ddd;
		border-radius: 8px;
		box-shadow: 0 1px 4px rgba(0,0,0,.05);
		padding: 16px;
	}

	.mlapify-table-head {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 12px;
		flex-wrap: wrap;
		gap: 10px;
	}

	.mlapify-table-title {
		font-size: 18px;
		font-weight: 700;
		color: #222;
		margin: 0;
	}

	.mlapify-counter {
		font-weight: 700;
		color: #444;
	}

	.mlapify-table-controls {
		display: flex;
		gap: 10px;
		align-items: center;
		flex-wrap: wrap;
	}

	.mlapify-page-size {
		display: flex;
		align-items: center;
		gap: 6px;
		font-weight: 600;
		color: #444;
		margin: 0;
	}

	.mlapify-page-size select {
		width: auto;
		min-width: 70px;
		height: 32px;
		margin-bottom: 0;
	}

	.mlapify-pagination {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-top: 12px;
		flex-wrap: wrap;
		gap: 10px;
	}

	.mlapify-page-info {
		color: #555;
		font-weight: 600;
	}

	.mlapify-pagination-buttons {
		display: flex;
		gap: 8px;
		align-items: center;
	}

	#tabla_apify_resultados {
		margin-bottom: 0;
		background: #fff;
	}

	#tabla_apify_resultados thead th {
		background: #f5f5f5;
		border-bottom: 1px solid #ddd;
		color: #333;
		font-size: 13px;
	}

	#tabla_apify_resultados tbody td {
		vertical-align: middle;
		font-size: 13px;
	}

	.mlapify-result-box pre {
		margin: 6px 0 0 0;
		background: #fff;
		border: 1px solid #e8e8e8;
		padding: 10px;
		border-radius: 4px;
		font-size: 12px;
	}

	@media (max-width: 980px) {
		.mlapify-grid-top {
			display: block;
		}

		.mlapify-card {
			margin-bottom: 16px;
		}

		.mlapify-form-row {
			display: block;
		}

		.mlapify-form-label {
			width: auto;
			min-width: 0;
			text-align: left;
			margin-bottom: 6px;
			padding-top: 0;
		}

		.mlapify-pagination {
			display: block;
		}

		.mlapify-pagination-buttons {
			margin-top: 8px;
		}
	}
</style>

<div id="contenido_cabezal">
	<h4 class="titulo"><?php echo $modulo['nombre']; ?></h4>
	<hr>
	<div class="mlapify-subtitle">
		<strong>Recolección de publicaciones públicas (ML vía Apify)</strong>
	</div>
	<hr class="nb">
</div>

<div class="sep_titulo"></div>

<div class="mlapify-page">

	<div class="mlapify-grid-top">

		<div class="mlapify-card">
			<div class="mlapify-card-title">Filtros de resultados</div>

			<div class="mlapify-form-row">
				<div class="mlapify-form-label">Marca</div>
				<div class="mlapify-form-field">
					<select id="apify_marca">
						<option value="">-- Seleccionar --</option>
						<?php foreach ($marcas as $m): ?>
							<option value="<?php echo htmlspecialchars($m['id']); ?>">
								<?php echo htmlspecialchars($m['nombre']); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="mlapify-form-row">
				<div class="mlapify-form-label">Modelo</div>
				<div class="mlapify-form-field">
					<select id="apify_modelo" disabled>
						<option value="">-- Seleccionar --</option>
					</select>
				</div>
			</div>

			<div class="mlapify-form-row">
				<div class="mlapify-form-label">Año</div>
				<div class="mlapify-form-field">
					<div class="mlapify-range">
						<input type="number" id="apify_anio_desde" placeholder="Desde" />
						<span class="mlapify-range-sep">a</span>
						<input type="number" id="apify_anio_hasta" placeholder="Hasta" />
					</div>
				</div>
			</div>

			<div class="mlapify-form-row">
				<div class="mlapify-form-label">Kilómetros</div>
				<div class="mlapify-form-field">
					<div class="mlapify-range">
						<input type="number" id="apify_km_desde" placeholder="Desde" />
						<span class="mlapify-range-sep">a</span>
						<input type="number" id="apify_km_hasta" placeholder="Hasta" />
					</div>
				</div>
			</div>

			<div class="mlapify-actions">
				<button type="button" class="btn btn-small" id="btn_apify_filtrar">Filtrar</button>
				<button type="button" class="btn btn-small" id="btn_apify_limpiar">Limpiar</button>
				<span class="mlapify-counter" id="apify_count">0 / 0</span>
			</div>

			<div class="mlapify-help">
				<span id="apify_estado" class="mlapify-status-inline">Estado: -</span>
			</div>
			<div class="mlapify-help" id="apify_progreso">-</div>
		</div>

		<div class="mlapify-card">
			<div class="mlapify-card-title">Simular Cotización</div>

			<div class="mlapify-form-row">
				<div class="mlapify-form-label">Marca</div>
				<div class="mlapify-form-field">
					<select id="cotiza_marca">
						<option value="">-- Seleccionar --</option>
						<?php foreach ($marcas as $m): ?>
							<option value="<?php echo htmlspecialchars($m['id']); ?>">
								<?php echo htmlspecialchars($m['nombre']); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="mlapify-form-row">
				<div class="mlapify-form-label">Modelo</div>
				<div class="mlapify-form-field">
					<select id="cotiza_modelo" disabled>
						<option value="">-- Seleccionar --</option>
					</select>
				</div>
			</div>

			<div class="mlapify-form-row">
				<div class="mlapify-form-label">Año</div>
				<div class="mlapify-form-field">
					<input type="number" id="cotiza_anio" placeholder="Ej: 2020" />
				</div>
			</div>

			<div class="mlapify-form-row">
				<div class="mlapify-form-label">Versión</div>
				<div class="mlapify-form-field">
					<input type="text" id="cotiza_version" placeholder="Ej: Full, GLS, LTZ" />
				</div>
			</div>

			<div class="mlapify-form-row">
				<div class="mlapify-form-label">Tipo de venta</div>
				<div class="mlapify-form-field">
					<select id="cotiza_tipo_venta">
						<option value="">-- Seleccionar --</option>
						<option value="venta_contado">Venta contado</option>
						<option value="entrega_forma_pago">Entrega como forma de pago</option>
					</select>
				</div>
			</div>

			<div class="mlapify-form-row">
				<div class="mlapify-form-label">¿Posee ficha oficial?</div>
				<div class="mlapify-form-field">
					<select id="cotiza_ficha_oficial">
						<option value="">-- Seleccionar --</option>
						<option value="si">Sí</option>
						<option value="no">No</option>
					</select>
				</div>
			</div>

			<div class="mlapify-form-row">
				<div class="mlapify-form-label">Kilómetros</div>
				<div class="mlapify-form-field">
					<input type="number" id="cotiza_km" placeholder="Ej: 85000" />
				</div>
			</div>

			<div class="mlapify-form-row">
				<div class="mlapify-form-label">Valor pretendido</div>
				<div class="mlapify-form-field">
					<input type="number" id="cotiza_valor" placeholder="Ej: 15000" />
				</div>
			</div>

			<div class="mlapify-form-row">
				<div class="mlapify-form-label">Email</div>
				<div class="mlapify-form-field">
					<input type="email" id="cotiza_email" placeholder="Ej: cliente@email.com" />
				</div>
			</div>

			<div class="mlapify-actions">
				<button type="button" class="btn btn-primary btn-small" id="btn_cotiza_simular">Cotizar</button>
				<span id="cotiza_estado" class="mlapify-status-inline">Estado: -</span>
			</div>

			<div class="mlapify-help" id="cotiza_debug">-</div>
			<div id="cotiza_resultado" class="mlapify-result-box"></div>
		</div>

	</div>

	<div class="mlapify-table-card">
		<div class="mlapify-table-head">
			<div class="mlapify-table-title">Resultados de publicaciones</div>

			<div class="mlapify-table-controls">
				<label class="mlapify-page-size">
					Mostrar
					<select id="apify_page_size">
						<option value="10">10</option>
						<option value="20" selected>20</option>
						<option value="50">50</option>
						<option value="100">100</option>
					</select>
					registros
				</label>

				<span class="mlapify-counter" id="apify_count">0 / 0</span>
			</div>
		</div>

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

		<div class="mlapify-pagination" id="apify_pagination">
			<div class="mlapify-page-info" id="apify_page_info">Página 1 de 1</div>
			<div class="mlapify-pagination-buttons">
				<button type="button" class="btn btn-small" id="apify_prev_page">Anterior</button>
				<button type="button" class="btn btn-small" id="apify_next_page">Siguiente</button>
			</div>
		</div>
	</div>
</div>

<script>
	const APIFY_MODELOS_POR_MARCA = <?php echo json_encode($modelosPorMarca, JSON_UNESCAPED_UNICODE); ?>;

	let apifyCorridaId = null;
	let apifyTimer = null;

	let APIFY_ROWS_ALL = [];
	let APIFY_ROWS_VIEW = [];

	let APIFY_PAGE = 1;
	let APIFY_PAGE_SIZE = 20;

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

	function apifyNormalizeText(v) {
		return (v == null ? '' : String(v)).trim().toLowerCase();
	}

	function apifySortRows(rows) {
		return (rows || []).slice().sort(function(a, b){
			const marcaA = apifyNormalizeText(a && a.marca ? a.marca : '');
			const marcaB = apifyNormalizeText(b && b.marca ? b.marca : '');

			if (marcaA < marcaB) return -1;
			if (marcaA > marcaB) return 1;

			const modeloA = apifyNormalizeText(a && a.modelo ? a.modelo : '');
			const modeloB = apifyNormalizeText(b && b.modelo ? b.modelo : '');

			if (modeloA < modeloB) return -1;
			if (modeloA > modeloB) return 1;

			const tituloA = apifyNormalizeText(a && a.titulo ? a.titulo : '');
			const tituloB = apifyNormalizeText(b && b.titulo ? b.titulo : '');

			if (tituloA < tituloB) return -1;
			if (tituloA > tituloB) return 1;

			return 0;
		});
	}

	function apifyGetPageCount() {
		if (!APIFY_ROWS_VIEW || !APIFY_ROWS_VIEW.length) return 1;
		return Math.max(1, Math.ceil(APIFY_ROWS_VIEW.length / APIFY_PAGE_SIZE));
	}

	function apifyClampPage() {
		const totalPages = apifyGetPageCount();
		if (APIFY_PAGE < 1) APIFY_PAGE = 1;
		if (APIFY_PAGE > totalPages) APIFY_PAGE = totalPages;
	}

	function apifyRenderPagination() {
		apifyClampPage();

		const totalPages = apifyGetPageCount();
		const totalRows = APIFY_ROWS_VIEW.length;

		$('#apify_page_info').text('Página ' + APIFY_PAGE + ' de ' + totalPages + ' (' + totalRows + ' registros filtrados)');
		$('#apify_prev_page').prop('disabled', APIFY_PAGE <= 1);
		$('#apify_next_page').prop('disabled', APIFY_PAGE >= totalPages);
	}

	function apifyRenderResultados(rows) {
		const $tb = $('#tabla_apify_resultados tbody');
		$tb.empty();

		const sortedRows = apifySortRows(rows || []);
		APIFY_ROWS_VIEW = sortedRows;

		apifyClampPage();
		apifyRenderPagination();

		if (!sortedRows.length) {
			$tb.append('<tr><td colspan="10" style="color:#777;">Sin resultados.</td></tr>');
			return;
		}

		const start = (APIFY_PAGE - 1) * APIFY_PAGE_SIZE;
		const end = start + APIFY_PAGE_SIZE;
		const pageRows = sortedRows.slice(start, end);

		pageRows.forEach(r => {
			const marca   = ((r && r.marca) ? r.marca : '-').toString();
			const modelo  = ((r && r.modelo) ? r.modelo : '-').toString();
			const version = ((r && r.version) ? r.version : '-').toString();
			const titulo  = ((r && r.titulo) ? r.titulo : '').toString();
			const precio  = (r && r.precio != null) ? String(r.precio) : '';
			const moneda  = ((r && r.moneda) ? r.moneda : '').toString();
			const anio    = (r && r.anio != null) ? String(r.anio) : '';
			const km      = (r && r.km != null) ? String(r.km) : '';
			const ubic    = ((r && r.ubicacion) ? r.ubicacion : '').toString();
			const url     = ((r && r.url) ? r.url : '').toString();

			const link = url ? '<a href="' + url + '" target="_blank">Ver</a>' : '-';

			$tb.append(
				'<tr>' +
					'<td>' + $('<div>').text(marca).html() + '</td>' +
					'<td>' + $('<div>').text(modelo).html() + '</td>' +
					'<td>' + $('<div>').text(version).html() + '</td>' +
					'<td>' + $('<div>').text(titulo).html() + '</td>' +
					'<td>' + $('<div>').text(precio).html() + '</td>' +
					'<td>' + $('<div>').text(moneda).html() + '</td>' +
					'<td>' + $('<div>').text(anio).html() + '</td>' +
					'<td>' + $('<div>').text(km).html() + '</td>' +
					'<td>' + $('<div>').text(ubic).html() + '</td>' +
					'<td>' + link + '</td>' +
				'</tr>'
			);
		});
	}

	function apifyRefreshTable() {
		apifyRenderResultados(APIFY_ROWS_VIEW);
		apifySetCount(APIFY_ROWS_VIEW.length, APIFY_ROWS_ALL.length);
	}

	function apifyBuildBatchUrl() {
		let url = '/adm/modulos/mlapify/apify_resultados_batch.php?limit=3000';

		const marcaId = ($('#apify_marca').val() || '').toString().trim();
		const modeloId = ($('#apify_modelo').val() || '').toString().trim();

		if (marcaId !== '') {
			url += '&marca_id=' + encodeURIComponent(marcaId);
		}
		if (modeloId !== '') {
			url += '&modelo_id=' + encodeURIComponent(modeloId);
		}

		return url;
	}

	function apifyFetchBatch(estadoTexto) {
		const url = apifyBuildBatchUrl();

		apifySetEstado('cargando', 'Consultando última corrida...');
		$('#tabla_apify_resultados tbody').html('<tr><td colspan="10" style="color:#777;">Cargando...</td></tr>');

		$.getJSON(url, function(r){
			if (r && r.ok) {
				apifyLoadRows((r.rows || []), estadoTexto || 'Mostrando resultados de la última corrida.');
			} else {
				apifySetEstado('error', (r && r.mensaje) ? r.mensaje : 'No se pudieron cargar resultados.');
				APIFY_ROWS_ALL = [];
				APIFY_ROWS_VIEW = [];
				APIFY_PAGE = 1;
				apifyRefreshTable();
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
			APIFY_ROWS_ALL = [];
			APIFY_ROWS_VIEW = [];
			APIFY_PAGE = 1;
			apifyRefreshTable();
		});
	}

	function apifyApplyFiltersLocal() {
		const anioDesde = apifyToIntOrNull($('#apify_anio_desde').val());
		const anioHasta = apifyToIntOrNull($('#apify_anio_hasta').val());
		const kmDesde   = apifyToIntOrNull($('#apify_km_desde').val());
		const kmHasta   = apifyToIntOrNull($('#apify_km_hasta').val());

		const needYear = (anioDesde !== null || anioHasta !== null);
		const needKm   = (kmDesde !== null || kmHasta !== null);

		APIFY_ROWS_VIEW = (APIFY_ROWS_ALL || []).filter(r => {
			const rAnio = apifyToIntOrNull(r ? r.anio : null);
			const rKm   = apifyToIntOrNull(r ? r.km : null);

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

		APIFY_PAGE = 1;
		apifyRefreshTable();
		apifySetEstado('ok', 'Mostrando resultados de la última corrida filtrados localmente.');
	}

	function apifyClearFilters() {
		$('#apify_marca').val('');
		$('#apify_modelo').html('<option value="">-- Seleccionar --</option>').prop('disabled', true);
		$('#apify_anio_desde').val('');
		$('#apify_anio_hasta').val('');
		$('#apify_km_desde').val('');
		$('#apify_km_hasta').val('');

		APIFY_ROWS_ALL = [];
		APIFY_ROWS_VIEW = [];
		APIFY_PAGE = 1;
		apifySetCount(0, 0);

		apifyFetchBatch('Mostrando resultados de la última corrida global.');
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
			$m.append('<option value="' + String(x.id).replace(/"/g,'&quot;') + '">' + $('<div>').text(x.nombre).html() + '</option>');
		});
		$m.prop('disabled', false);
	}

	function cargarModelosPorMarca(idMarca) {
		cargarModelosEnSelect(idMarca, '#apify_modelo', '-- Seleccionar --', '-- Seleccionar --');
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
		html += '<div style="font-weight:bold; font-size:16px; margin-bottom:10px;">Resultado API cotización</div>';
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
				html += '<hr style="margin:10px 0;">';
				html += '<div style="font-weight:bold; margin-bottom:6px;">Resumen</div>';
				if (min !== '') html += '<div><strong>Mínimo:</strong> ' + cotizaEscapeHtml(min) + '</div>';
				if (max !== '') html += '<div><strong>Máximo:</strong> ' + cotizaEscapeHtml(max) + '</div>';
				if (avg !== '') html += '<div><strong>Promedio:</strong> ' + cotizaEscapeHtml(avg) + '</div>';
			}
		}

		html += '<hr style="margin:10px 0;">';
		html += '<div style="font-weight:bold; margin-bottom:4px;">Payload enviado</div>';
		html += '<pre style="white-space:pre-wrap;">' + cotizaEscapeHtml(JSON.stringify(sentPayload, null, 2)) + '</pre>';

		html += '<div style="font-weight:bold; margin:10px 0 4px 0;">Respuesta cruda</div>';
		html += '<pre style="white-space:pre-wrap;">' + cotizaEscapeHtml(JSON.stringify(res, null, 2)) + '</pre>';

		cotizaSetResultadoHtml(html);
	}

	function apifyLoadRows(rows, estadoTexto) {
		APIFY_ROWS_ALL = apifySortRows(rows || []);
		APIFY_ROWS_VIEW = APIFY_ROWS_ALL.slice(0);
		APIFY_PAGE = 1;
		apifyRefreshTable();
		apifySetEstado('ok', estadoTexto || 'Mostrando resultados.');
	}

	function apifyPoll() {
		if (!apifyCorridaId) return;

		$.getJSON('/adm/modulos/mlapify/apify_estado.php', { corrida_id: apifyCorridaId }, function(res){
			if (!res || !res.ok) {
				apifySetEstado('error', (res && res.mensaje) ? res.mensaje : 'No se pudo leer el estado');
				if (apifyTimer) clearInterval(apifyTimer);
				apifyTimer = null;
				return;
			}

			const sub = 'Items: ' + (res.total_items ?? '-') + ' | Guardados: ' + (res.items_guardados ?? '-') + (res.mensaje ? (' | ' + res.mensaje) : '');
			apifySetEstado(res.estado, sub);

			if (res.estado === 'ok' || res.estado === 'error') {
				if (apifyTimer) clearInterval(apifyTimer);
				apifyTimer = null;

				$.getJSON('/adm/modulos/mlapify/apify_resultados.php', { corrida_id: apifyCorridaId }, function(r2){
					if (r2 && r2.ok) {
						apifyLoadRows((r2.rows || []), 'Mostrando resultados de la corrida.');
					}
				});
			}
		}).fail(function(xhr){
			let msg = 'No se pudo contactar apify_estado.php (HTTP ' + (xhr ? xhr.status : '') + ')';
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
		});
	}

	$('#apify_marca').on('change', function(){
		cargarModelosPorMarca($(this).val());
		APIFY_ROWS_ALL = [];
		APIFY_ROWS_VIEW = [];
		APIFY_PAGE = 1;
		apifySetCount(0, 0);
		$('#tabla_apify_resultados tbody').html('<tr><td colspan="10" style="color:#777;">Seleccioná un modelo y presioná "Filtrar".</td></tr>');
		apifySetEstado('-', 'Marca cambiada. Elegí modelo y presioná Filtrar.');
	});

	$('#apify_modelo').on('change', function(){
		APIFY_ROWS_ALL = [];
		APIFY_ROWS_VIEW = [];
		APIFY_PAGE = 1;
		apifySetCount(0, 0);
		$('#tabla_apify_resultados tbody').html('<tr><td colspan="10" style="color:#777;">Presioná "Filtrar" para ver la última corrida del filtro seleccionado.</td></tr>');
		apifySetEstado('-', 'Modelo cambiado. Presioná Filtrar.');
	});

	$('#cotiza_marca').on('change', function(){
		cargarModelosCotiza($(this).val());
	});

	$('#btn_apify_filtrar').on('click', function(){
		const marcaId = ($('#apify_marca').val() || '').toString().trim();
		const modeloId = ($('#apify_modelo').val() || '').toString().trim();

		if (marcaId !== '' || modeloId !== '') {
			apifyFetchBatch('Mostrando resultados de la última corrida del filtro seleccionado.');
			return;
		}

		if (!APIFY_ROWS_ALL || !APIFY_ROWS_ALL.length) {
			apifyFetchBatch('Mostrando resultados de la última corrida global.');
			return;
		}

		apifyApplyFiltersLocal();
	});

	$('#btn_apify_limpiar').on('click', function(){
		apifyClearFilters();
	});

	$('#apify_page_size').on('change', function(){
		const v = parseInt($(this).val(), 10);
		APIFY_PAGE_SIZE = (Number.isFinite(v) && v > 0) ? v : 20;
		APIFY_PAGE = 1;
		apifyRefreshTable();
	});

	$('#apify_prev_page').on('click', function(){
		if (APIFY_PAGE > 1) {
			APIFY_PAGE--;
			apifyRefreshTable();
		}
	});

	$('#apify_next_page').on('click', function(){
		const totalPages = apifyGetPageCount();
		if (APIFY_PAGE < totalPages) {
			APIFY_PAGE++;
			apifyRefreshTable();
		}
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

	$(function(){
		apifySetCount(0, 0);
		cotizaSetEstado('-', '-');
		$('#apify_page_size').val(String(APIFY_PAGE_SIZE));
		apifyFetchBatch('Mostrando resultados de la última corrida global.');
	});
</script>

<?php require_once('sistema_post_contenido.php'); ?>