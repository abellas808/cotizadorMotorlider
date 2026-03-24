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
		max-height: 700px;
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

	.mlapify-post-card {
		background: #fffef6;
		border: 1px solid #eadfa8;
		border-radius: 8px;
		padding: 14px;
		margin-top: 16px;
	}

	.mlapify-post-card h5 {
		margin: 0 0 10px 0;
		font-size: 16px;
	}

	.mlapify-post-grid {
		display: flex;
		flex-wrap: wrap;
		gap: 14px;
	}

	.mlapify-post-col {
		flex: 1 1 260px;
		background: #fff;
		border: 1px solid #eee;
		border-radius: 6px;
		padding: 12px;
	}

	.mlapify-post-actions {
		margin-top: 12px;
		display: flex;
		flex-wrap: wrap;
		gap: 8px;
	}

	.mlapify-mini {
		font-size: 12px;
		color: #666;
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
				<span class="mlapify-counter" id="apify_count_filtros">0 / 0</span>
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

	let COTIZA_POST = null;
	let COTIZA_ID = null;

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
		$('#apify_count_filtros').text(String(show || 0) + ' / ' + String(total || 0));
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

	function cotizaFormatNumber(val) {
		let n = parseFloat(val);
		if (!isFinite(n)) return '-';

		const decimal = n - Math.floor(n);

		if (decimal <= 0.50) {
			n = Math.floor(n);
		} else {
			n = Math.ceil(n);
		}

		return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
	}

	function cotizaFillTemplate(template, location) {
		if (!template) return '';
		return String(template).replace('{location}', encodeURIComponent(location));
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
		let url = '/adm/modulos/mlapify/apify_resultados_batch.php?limit=10000';

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

	function cotizaRenderPostCotizacion(post, idCotizacion) {
		if (!post || !post.agenda_habilitada) {
			return '';
		}

		const cliente = post.cliente || {};
		const vehiculo = post.vehiculo || {};
		const endpoints = post.endpoints || {};

		return `
			<div class="mlapify-post-card">
				<h5>Post-cotización / Agenda</h5>

				<div class="mlapify-post-grid">
					<div class="mlapify-post-col">
						<div><strong>ID Cotización:</strong> ${cotizaEscapeHtml(idCotizacion || post.id_cotizacion || '')}</div>
						<div><strong>Cliente:</strong> ${cotizaEscapeHtml(cliente.nombre || '')}</div>
						<div><strong>Email:</strong> ${cotizaEscapeHtml(cliente.email || '')}</div>
						<div><strong>Teléfono:</strong> ${cotizaEscapeHtml(cliente.telefono || '')}</div>
					</div>

					<div class="mlapify-post-col">
						<div><strong>Marca:</strong> ${cotizaEscapeHtml(vehiculo.marca || '')}</div>
						<div><strong>Modelo:</strong> ${cotizaEscapeHtml(vehiculo.modelo || '')}</div>
						<div><strong>Año:</strong> ${cotizaEscapeHtml(vehiculo.anio || '')}</div>
						<div><strong>Versión:</strong> ${cotizaEscapeHtml(vehiculo.version || '')}</div>
						<div><strong>Familia:</strong> ${cotizaEscapeHtml(vehiculo.familia || '')}</div>
					</div>

					<div class="mlapify-post-col">
						<div><strong>Calendario:</strong></div>
						<div class="mlapify-mini">${cotizaEscapeHtml(endpoints.calendar_template || '')}</div>
						<div style="margin-top:8px;"><strong>Horarios:</strong></div>
						<div class="mlapify-mini">${cotizaEscapeHtml(endpoints.schedules_template || '')}</div>
						<div style="margin-top:8px;"><strong>Agendar:</strong></div>
						<div class="mlapify-mini">${cotizaEscapeHtml(endpoints.schedule_inspection_template || '')}</div>
					</div>
				</div>

				<div class="mlapify-post-actions">
					<button type="button" class="btn btn-small" onclick="cotizaIniciarAgenda(${idCotizacion})">
						Continuar con agenda
					</button>
				</div>
			</div>
		`;
	}

	function cotizaIniciarAgenda(idCotizacion) {
		console.log('Iniciando agenda para cotización:', idCotizacion);

		const url = '/ws/index.php?peticion=locations';
		const $box = $('#agenda_container');

		if ($box.length) {
			$box.html('Cargando sucursales...');
		} else {
			$('#cotiza_resultado').append('<div id="agenda_container" style="margin-top:15px;">Cargando sucursales...</div>');
		}

		$.ajax({
			url: url,
			type: 'POST',
			dataType: 'json',
			data: {}
		})
		.done(function(res){
			console.log('RESP SUCURSALES', res);

			const rows =
				(res && Array.isArray(res.data) && res.data) ||
				(res && Array.isArray(res.locations) && res.locations) ||
				(res && Array.isArray(res.sucursales) && res.sucursales) ||
				(res && Array.isArray(res.rows) && res.rows) ||
				[];

			if (!rows.length) {
				$('#agenda_container').html(
					'<div style="color:red;">No hay sucursales disponibles</div>' +
					'<div style="margin-top:6px; color:#666;"><small>Respuesta: ' + $('<div>').text(JSON.stringify(res)).html() + '</small></div>'
				);
				return;
			}

			let html = '<div style="margin-top:10px;"><strong>Seleccionar sucursal:</strong></div>';
			html += '<select id="agenda_sucursal" style="margin-top:5px; min-width:260px;">';

			rows.forEach(function(s){
				const id =
					s.id ??
					s.id_sucursal ??
					s.location ??
					s.codigo ??
					'';

				const nombre =
					s.nombre ??
					s.name ??
					s.sucursal ??
					s.descripcion ??
					('Sucursal ' + id);

				html += '<option value="' + String(id).replace(/"/g, '&quot;') + '">' +
					$('<div>').text(nombre).html() +
					'</option>';
			});

			html += '</select>';
			html += '<div style="margin-top:10px;"><button class="btn btn-small" onclick="cotizaCargarCalendario()">Continuar</button></div>';
			html += '<div id="agenda_calendario" style="margin-top:15px;"></div>';
			html += '<div id="agenda_horarios" style="margin-top:15px;"></div>';

			$('#agenda_container').html(html);
		})
		.fail(function(xhr){
			let txt = 'Error cargando sucursales';
			if (xhr && xhr.responseText) {
				txt += '<br><small>' + $('<div>').text(xhr.responseText).html() + '</small>';
			}
			$('#agenda_container').html('<div style="color:red;">' + txt + '</div>');
		});
	}

	function cotizaCargarCalendario() {
		const sucursal = $('#agenda_sucursal').val();
		if (!sucursal) {
			alert('Seleccioná una sucursal');
			return;
		}

		const url = '/ws/index.php?peticion=availability';

		$('#agenda_calendario').html('Cargando disponibilidad...');
		$('#agenda_horarios').html('');

		$.ajax({
			url: url,
			type: 'POST',
			dataType: 'json',
			data: {
				location: sucursal
			}
		})
		.done(function(res){
			console.log('RESP AVAILABILITY', res);

			const rows = (res && res.availability) ? res.availability : [];

			if (!rows.length) {
				$('#agenda_calendario').html('<div style="color:red;">No hay disponibilidad</div>');
				return;
			}

			window.AVAILABILITY_DATA = rows;

			let html = '<div><strong>Seleccionar día:</strong></div><div style="margin-top:8px;">';

			rows.forEach(function(d){
				const fecha = d.fecha;
				if (!fecha) return;

				html += '<button type="button" class="btn btn-small" style="margin:4px;" onclick="cotizaMostrarHorariosAvailability(\'' +
					String(fecha).replace(/'/g, "\\'") +
					'\')">' + cotizaEscapeHtml(fecha) + '</button>';
			});

			html += '</div>';

			$('#agenda_calendario').html(html);
		})
		.fail(function(xhr){
			let txt = 'Error cargando disponibilidad';
			if (xhr && xhr.responseText) {
				txt += '<br><small>' + $('<div>').text(xhr.responseText).html() + '</small>';
			}
			$('#agenda_calendario').html('<div style="color:red;">' + txt + '</div>');
		});
	}

	function cotizaMostrarHorariosAvailability(fecha) {
		const data = window.AVAILABILITY_DATA || [];
		const dia = data.find(x => x.fecha === fecha);

		if (!dia || !dia.horas || !dia.horas.length) {
			$('#agenda_horarios').html('<div style="color:red;">Sin horarios</div>');
			return;
		}

		let html = '<div><strong>Horarios para ' + cotizaEscapeHtml(fecha) + ':</strong></div><div style="margin-top:8px;">';

		dia.horas.forEach(function(h){
			html += '<button type="button" class="btn btn-small" style="margin:4px;" onclick="cotizaConfirmarAgenda(\'' +
				String(fecha).replace(/'/g, "\\'") + '\', \'' +
				String(h).replace(/'/g, "\\'") + '\')">' +
				cotizaEscapeHtml(h) + '</button>';
		});

		html += '</div>';

		$('#agenda_horarios').html(html);
	}

	function cotizaConfirmarAgenda(fecha, hora) {

		if (window.AGENDA_EN_PROCESO) {
			return;
		}

		if (!confirm('¿Confirmar turno para ' + fecha + ' ' + hora + '?')) {
			return;
		}

		const sucursal = $('#agenda_sucursal').val();
		if (!sucursal) {
			alert('Falta seleccionar sucursal.');
			return;
		}

		if (!COTIZA_ID) {
			alert('No se encontró el id de cotización.');
			return;
		}

		if (!COTIZA_POST) {
			alert('No se encontró la información de post-cotización.');
			return;
		}

		window.AGENDA_EN_PROCESO = true;

		$('#agenda_horarios button').prop('disabled', true);
		$('#agenda_guardando').remove();
		$('#agenda_horarios').append('<div id="agenda_guardando" style="margin-top:10px; color:#666;">Guardando agenda...</div>');

		const cliente = COTIZA_POST.cliente || {};
		const vehiculo = COTIZA_POST.vehiculo || {};

		const payload = {
			location: sucursal,
			date: fecha,
			hora: hora,
			modelo: vehiculo.modelo || '',
			marca: vehiculo.marca || '',
			anio: vehiculo.anio || '',
			familia: vehiculo.familia || vehiculo.version || '',
			auto: vehiculo.auto || vehiculo.nombre_auto || '',
			nombre: cliente.nombre || '',
			email: cliente.email || '',
			telefono: cliente.telefono || '',
			id_cotizacion: COTIZA_ID
		};

		console.log('AGENDAR payload', payload);

		$.ajax({
			url: '/ws/index.php?peticion=scheduleInspection',
			type: 'POST',
			dataType: 'json',
			data: payload
		})
		.done(function(res){
			console.log('RESP AGENDAR', res);

			if (res && (res.codigo == 200 || res.error === 0 || res.error === '0')) {
				$('#agenda_guardando').html('<div style="color:green; font-weight:bold;">Agenda confirmada correctamente.</div>');
				alert(res.mensaje || 'Agenda confirmada correctamente.');
				return;
			}

			let msg = (res && (res.mensaje || res.msg)) ? (res.mensaje || res.msg) : 'No se pudo confirmar la agenda.';
			$('#agenda_guardando').html('<div style="color:red;">' + $('<div>').text(msg).html() + '</div>');
			alert(msg);

			window.AGENDA_EN_PROCESO = false;
			$('#agenda_horarios button').prop('disabled', false);
		})
		.fail(function(xhr){
			let msg = 'Error al guardar la agenda.';
			if (xhr && xhr.responseText) {
				try {
					const j = JSON.parse(xhr.responseText);
					if (j && (j.mensaje || j.msg)) {
						msg = j.mensaje || j.msg;
					} else {
						msg = xhr.responseText;
					}
				} catch(e) {
					msg = xhr.responseText;
				}
			}

			$('#agenda_guardando').html('<div style="color:red;">' + $('<div>').text(msg).html() + '</div>');
			alert(msg);

			window.AGENDA_EN_PROCESO = false;
			$('#agenda_horarios button').prop('disabled', false);
		});
	}

	function cotizaRenderResponse(res, sentPayload, endpointUrl) {
		console.log('RESP COTIZACION', res);

		const r = res?.resultado || res?.data || res?.valores || null;
		const post = res?.post_cotizacion || null;

		const ok =
			(res?.ok === true) ||
			(res?.estado === 'ok') ||
			(res?.estado === 'OK') ||
			(res?.mensaje === 'OK') ||
			(res?.msg === 'OK') ||
			!!r;

		let idCotizacion =
			res?.id_cotizacion ||
			res?.cotizacion_id ||
			res?.id ||
			res?.resultado?.id_cotizacion ||
			res?.resultado?.cotizacion_id ||
			post?.id_cotizacion ||
			'';

		COTIZA_ID = idCotizacion || null;
		COTIZA_POST = post || null;

		if (!ok || !r) {
			cotizaSetResultadoHtml(`
				<div style="color:#b30000; font-weight:bold;">
					Error generando la cotización
				</div>
				<div style="margin-top:6px; color:#666;">
					${cotizaEscapeHtml(res?.mensaje || res?.msg || 'No vino un resultado válido desde la API.')}
				</div>
			`);
			return;
		}

		const min = r.min || r.valor_minimo || 0;
		const max = r.max || r.valor_maximo || 0;
		const avg = r.avg || r.valor_promedio || 0;
		const count = r.count || r.total || 0;

		const valorMinMotorlider = r.valor_minimo_motorlider || 0;
		const valorMaxMotorlider = r.valor_maximo_motorlider || 0;
		const valorPromMotorlider = r.valor_promedio_motorlider || 0;
		const promedioBaseMotorlider = r.promedio_base_motorlider || 0;
		const vpretendidoAplicado = !!r.vpretendido_aplicado;
		const valorPretendidoCliente = r.valor_pretendido_cliente || sentPayload.valor_pretendido || '';

		const nombre =
			(sentPayload.nombre || '') +
			(sentPayload.apellido ? ' ' + sentPayload.apellido : '');

		const email = sentPayload.email || '';
		const marca = $('#cotiza_marca option:selected').text();
		const modelo = $('#cotiza_modelo option:selected').text();
		const anio = sentPayload.anio || '';
		const km = sentPayload.km || '';
		const valor = sentPayload.valor_pretendido || '';

		let html = '';

		html += `
			<div style="font-size:18px; font-weight:bold; color:#2d7a2d; margin-bottom:12px;">
				✔ Cotización OK
			</div>
		`;

		if (vpretendidoAplicado) {
			html += `
				<div style="
					background:#fff8e6;
					border:1px solid #eed58f;
					border-radius:8px;
					padding:12px 14px;
					margin-bottom:15px;
					color:#7a5a00;
				">
					<strong>Observación:</strong> se tomó el valor pretendido del cliente porque es menor al valor mínimo calculado por Motorlider.
				</div>
			`;
		}

		html += `
			<div style="display:flex; flex-wrap:wrap; gap:16px; margin-bottom:16px;">
				<div style="flex:1 1 320px; background:#fff; border:1px solid #ddd; border-radius:8px; padding:14px;">
					<div style="font-size:16px; font-weight:bold; margin-bottom:10px;">Datos de la solicitud</div>
					<div style="line-height:1.8;">
						<div><strong>ID Cotización:</strong> ${cotizaEscapeHtml(String(idCotizacion || ''))}</div>
						<div><strong>Solicitante:</strong> ${cotizaEscapeHtml(nombre)}</div>
						<div><strong>Email:</strong> ${cotizaEscapeHtml(email)}</div>
						<div><strong>Vehículo:</strong> ${cotizaEscapeHtml(marca)} ${cotizaEscapeHtml(modelo)}</div>
						<div><strong>Año:</strong> ${cotizaEscapeHtml(anio)}</div>
						<div><strong>Kilómetros:</strong> ${cotizaEscapeHtml(km)}</div>
						<div><strong>Valor pretendido:</strong> USD ${cotizaFormatNumber(valor)}</div>
					</div>
				</div>

				<div style="flex:1 1 320px; background:#f4f8ff; border:1px solid #d0def5; border-radius:8px; padding:14px;">
					<div style="font-size:16px; font-weight:bold; margin-bottom:10px;">Resultado de mercado</div>
					<div style="line-height:1.8;">
						<div><strong>Comparables usados:</strong> ${cotizaEscapeHtml(count)}</div>
						<div><strong>Valor mínimo:</strong> USD ${cotizaFormatNumber(min)}</div>
						<div><strong>Valor máximo:</strong> USD ${cotizaFormatNumber(max)}</div>
						<div><strong>Promedio mercado:</strong> USD ${cotizaFormatNumber(avg)}</div>
					</div>
				</div>

				<div style="flex:1 1 320px; background:#f6fff5; border:1px solid #cfe6cc; border-radius:8px; padding:14px;">
					<div style="font-size:16px; font-weight:bold; margin-bottom:10px;">Resultado Motorlider</div>
					<div style="line-height:1.8;">
						<div><strong>Promedio base Motorlider:</strong> USD ${cotizaFormatNumber(promedioBaseMotorlider)}</div>
						<div><strong>Valor mínimo Motorlider:</strong> USD ${cotizaFormatNumber(valorMinMotorlider)}</div>
						<div><strong>Valor máximo Motorlider:</strong> USD ${cotizaFormatNumber(valorMaxMotorlider)}</div>
						<div><strong>Valor promedio Motorlider:</strong> USD ${cotizaFormatNumber(valorPromMotorlider)}</div>
						<div><strong>Valor pretendido aplicado:</strong> ${vpretendidoAplicado ? 'Sí' : 'No'}</div>
						<div><strong>Valor pretendido cliente:</strong> USD ${cotizaFormatNumber(valorPretendidoCliente)}</div>
					</div>
				</div>
			</div>
		`;

		if (idCotizacion) {
			html += `
				<div style="margin-top:10px;">
					<div style="font-size:16px; font-weight:bold; margin-bottom:10px;">
						Publicaciones utilizadas
					</div>
					<div id="tabla_comparables">Cargando...</div>
				</div>
			`;
		}

		html += cotizaRenderPostCotizacion(post, idCotizacion);

		cotizaSetResultadoHtml(html);

		if (idCotizacion) {
			const itemsUrl = window.location.origin + '/adm/modulos/mlapify/cotizacion_items.php?id=' + encodeURIComponent(idCotizacion) + '&_ts=' + Date.now();

			setTimeout(function () {
				$.getJSON(itemsUrl, function (resp) {
					console.log('RESP ITEMS', resp);

					if (!resp || !resp.ok || !resp.rows || !resp.rows.length) {
						$('#tabla_comparables').html('<div style="color:#666;">Sin publicaciones.</div>');
						return;
					}

					let t = '<table class="table table-bordered table-striped">';
					t += '<thead><tr>';
					t += '<th>Marca</th>';
					t += '<th>Modelo</th>';
					t += '<th>Título</th>';
					t += '<th>Link</th>';
					t += '</tr></thead><tbody>';

					resp.rows.forEach(function(it){
						t += '<tr>';
						t += '<td>' + cotizaEscapeHtml(it.brand || '') + '</td>';
						t += '<td>' + cotizaEscapeHtml(it.modelo || '') + '</td>';
						t += '<td>' + cotizaEscapeHtml(it.title || '') + '</td>';
						t += '<td>';
						if (it.item_url) {
							t += '<a href="' + it.item_url + '" target="_blank" rel="noopener noreferrer">Ver publicación</a>';
						} else {
							t += '-';
						}
						t += '</td>';
						t += '</tr>';
					});

					t += '</tbody></table>';

					$('#tabla_comparables').html(t);
				}).fail(function (xhr) {
					let txt = 'No se pudieron cargar las publicaciones.';
					if (xhr && xhr.responseText) {
						txt += '<br><small>' + cotizaEscapeHtml(xhr.responseText) + '</small>';
					}
					$('#tabla_comparables').html('<div style="color:#b30000;">' + txt + '</div>');
				});
			}, 300);
		}
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