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
	// OJO: si tu tabla real es act_marca (sin s), cambiá acá.
	$schemaMarca  = findSchemaForTable($db, 'act_marcas');
	$schemaModelo = findSchemaForTable($db, 'act_modelo');

	$tblMarca  = $schemaMarca  ? "{$schemaMarca}.act_marcas"   : "act_marcas";
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
		$idMod   = _pickArr($r, ['id_modelo','id_mdoelo','id','modelo_id']); // por si viene con typo
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
		<button type="button" class="btn btn-primary btn-small" id="btn_apify_buscar">Buscar (Apify)</button>

		<!-- ⭐ NUEVO: botones para filtrar localmente la grilla -->
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

	// ⭐ NUEVO: datasets para filtrar localmente
	let APIFY_ROWS_ALL = [];
	let APIFY_ROWS_VIEW = [];

	function apifySetEstado(txt, sub) {
		$('#apify_estado').text('Estado: ' + (txt || '-'));
		$('#apify_progreso').text(sub || '-');
	}

	// ⭐ NUEVO: contador tipo "mostrando / total"
	function apifySetCount(show, total) {
		$('#apify_count').text(String(show || 0) + ' / ' + String(total || 0));
	}

	// ⭐ helper parse int seguro
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
			// ✅ ahora SI: r existe acá adentro
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

	// ⭐ NUEVO: aplica filtros a APIFY_ROWS_ALL y renderiza
	function apifyApplyFilters() {
		const marcaId = ($('#apify_marca').val() || '').toString();
		const modeloId = ($('#apify_modelo').val() || '').toString();

		const marcaTxt = ($('#apify_marca option:selected').text() || '').toString().trim();
		let modeloTxt = ($('#apify_modelo option:selected').text() || '').toString().trim();

		// Si el select está en "-- Todos --", no filtramos por modelo
		const filtraModelo = (modeloId !== '' && modeloTxt !== '' && modeloTxt !== '-- Todos --' && modeloTxt !== '-- Seleccionar --');
		const filtraMarca  = (marcaId !== '' && marcaTxt !== '' && marcaTxt !== '-- Seleccionar --');

		const anioDesde = apifyToIntOrNull($('#apify_anio_desde').val());
		const anioHasta = apifyToIntOrNull($('#apify_anio_hasta').val());
		const kmDesde   = apifyToIntOrNull($('#apify_km_desde').val());
		const kmHasta   = apifyToIntOrNull($('#apify_km_hasta').val());

		const needYear = (anioDesde !== null || anioHasta !== null);
		const needKm   = (kmDesde !== null   || kmHasta !== null);

		APIFY_ROWS_VIEW = (APIFY_ROWS_ALL || []).filter(r => {
			// Texto (marca/modelo)
			const rMarca = ((r && r.marca) ? String(r.marca) : '').trim();
			const rModelo = ((r && r.modelo) ? String(r.modelo) : '').trim();

			if (filtraMarca) {
				if (!rMarca) return false;
				if (rMarca.toLowerCase() !== marcaTxt.toLowerCase()) return false;
			}

			if (filtraModelo) {
				if (!rModelo) return false;
				// contiene (más flexible)
				if (rModelo.toLowerCase().indexOf(modeloTxt.toLowerCase()) === -1) return false;
			}

			// Año / KM (num)
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

	// ⭐ NUEVO: limpia filtros (inputs) y vuelve a mostrar todo
	function apifyClearFilters() {
		$('#apify_anio_desde').val('');
		$('#apify_anio_hasta').val('');
		$('#apify_km_desde').val('');
		$('#apify_km_hasta').val('');

		// OJO: no reseteo marca/modelo para no romper tu flujo de selección
		APIFY_ROWS_VIEW = (APIFY_ROWS_ALL || []).slice(0);
		apifyRenderResultados(APIFY_ROWS_VIEW);
		apifySetCount(APIFY_ROWS_VIEW.length, APIFY_ROWS_ALL.length);
		apifySetEstado('ok', 'Filtros limpiados. Mostrando todo.');
	}

	function cargarModelosPorMarca(idMarca) {
		const $m = $('#apify_modelo');
		$m.empty();

		if (!idMarca || !APIFY_MODELOS_POR_MARCA[idMarca] || !APIFY_MODELOS_POR_MARCA[idMarca].length) {
			$m.append('<option value="">-- Seleccionar --</option>');
			$m.prop('disabled', true);
			return;
		}

		$m.append('<option value="">-- Todos --</option>');
		APIFY_MODELOS_POR_MARCA[idMarca].forEach(x => {
			$m.append('<option value="'+ String(x.id).replace(/"/g,'&quot;') +'">'+ $('<div>').text(x.nombre).html() +'</option>');
		});
		$m.prop('disabled', false);
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
						// ⭐ NUEVO: guardo dataset completo
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
				// intento mostrar JSON de error si vino
				try {
				const j = JSON.parse(xhr.responseText);
				if (j && j.mensaje) msg = j.mensaje + ' (HTTP ' + xhr.status + ')';
				else msg = xhr.responseText;
				} catch(e) {
				msg = xhr.responseText; // texto plano
				}
			}
			apifySetEstado('error', msg);
			$('#btn_apify_buscar').prop('disabled', false);
			});
	}

	$('#apify_marca').on('change', function(){
		cargarModelosPorMarca($(this).val());

		// ⭐ opcional: si ya hay datos cargados, re-aplico filtros al cambiar marca
		// apifyApplyFilters();
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

			// ⭐ NUEVO: al iniciar nueva corrida, reseteo datasets
			APIFY_ROWS_ALL = [];
			APIFY_ROWS_VIEW = [];
			apifySetCount(0, 0);

			if (apifyTimer) clearInterval(apifyTimer);
			apifyTimer = setInterval(apifyPoll, 2000);
			apifyPoll();
		}).fail(function(xhr){
			let msg = 'No se pudo contactar run_ml.php (HTTP ' + (xhr ? xhr.status : '') + ')';
			if (xhr && xhr.responseText) {
				// intento mostrar JSON de error si vino
				try {
				const j = JSON.parse(xhr.responseText);
				if (j && j.mensaje) msg = j.mensaje + ' (HTTP ' + xhr.status + ')';
				else msg = xhr.responseText;
				} catch(e) {
				msg = xhr.responseText; // texto plano
				}
			}
			apifySetEstado('error', msg);
			$('#btn_apify_buscar').prop('disabled', false);
			});
	});

	// ✅ Auto-cargar última corrida al entrar
	$(function(){
		apifySetCount(0, 0);

		$.getJSON('/adm/modulos/mlapify/apify_ultima_corrida.php', function(r){
			if (r && r.ok && r.corrida_id) {
				apifyCorridaId = r.corrida_id;
				apifySetEstado(r.estado || '-', 'Items: ' + (r.total_items ?? '-') + ' | Guardados: ' + (r.items_guardados ?? '-') + (r.mensaje ? (' | ' + r.mensaje) : ''));

				$.getJSON('/adm/modulos/mlapify/apify_resultados.php', { corrida_id: apifyCorridaId }, function(r2){
					if (r2 && r2.ok) {
						// ⭐ NUEVO: guardo dataset completo
						APIFY_ROWS_ALL = (r2.rows || []);
						APIFY_ROWS_VIEW = APIFY_ROWS_ALL.slice(0);

						apifyRenderResultados(APIFY_ROWS_VIEW);
						apifySetCount(APIFY_ROWS_VIEW.length, APIFY_ROWS_ALL.length);
					}
				});
			}
		});
	});
</script>

<?php require_once('sistema_post_contenido.php'); ?>