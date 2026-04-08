<?php
ob_start();

require_once(__DIR__ . '/../../config/config.inc.php');

if (!isset($config['db_tablePrefix'])) {
    $config['db_tablePrefix'] = '';
}

require_once(__DIR__ . '/../../includes/database.php');
require_once(__DIR__ . '/../../includes/funciones.php');

session_start();
require_once(__DIR__ . '/../../includes/chk_login.php');

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Montevideo');

global $db;

function age_json($arr) {
	echo json_encode($arr);
	exit;
}

function age_table_columns($db, $table) {
	$tableEsc = $db->escape($table);
	$cols = [];

	$q = $db->query("SHOW COLUMNS FROM `{$tableEsc}`");
	if ($q) {
		while ($r = $db->fetch_array($q)) {
			if (!empty($r['Field'])) {
				$cols[$r['Field']] = true;
			}
		}
	}

	return $cols;
}

$idAgenda         = intval($_POST['id_agenda'] ?? 0);
$pretasacionDesde = trim((string)($_POST['pretasacion_desde'] ?? ''));
$pretasacionHasta = trim((string)($_POST['pretasacion_hasta'] ?? ''));
$tasacionFinal    = trim((string)($_POST['tasacion_final'] ?? ''));

if ($idAgenda <= 0) {
	age_json([
		'ok' => false,
		'mensaje' => 'id_agenda inválido.'
	]);
}

if ($pretasacionDesde === '' || $pretasacionHasta === '' || $tasacionFinal === '') {
	age_json([
		'ok' => false,
		'mensaje' => 'Completá pre tasación desde, pre tasación hasta y tasación final.'
	]);
}

$agenda = $db->query_first("
	SELECT id_agenda, id_cotizacion
	FROM agendas
	WHERE id_agenda = " . intval($idAgenda) . "
	LIMIT 1
");

if (!$agenda) {
	age_json([
		'ok' => false,
		'mensaje' => 'Agenda no encontrada.'
	]);
}

$idCotizacion = intval($agenda['id_cotizacion'] ?? 0);

if ($idCotizacion <= 0) {
	age_json([
		'ok' => false,
		'mensaje' => 'La agenda no tiene id_cotizacion válido.'
	]);
}

$colsCot = age_table_columns($db, 'cotizaciones_generadas');
$sets = [];

if (isset($colsCot['pretasacion_desde'])) {
	$sets[] = "pretasacion_desde = " . floatval($pretasacionDesde);
}

if (isset($colsCot['pretasacion_hasta'])) {
	$sets[] = "pretasacion_hasta = " . floatval($pretasacionHasta);
}

if (isset($colsCot['tasacion_final'])) {
	$sets[] = "tasacion_final = " . floatval($tasacionFinal);
}

$idUsuarioCotizo = intval($_SESSION[$config['codigo_unico']]['login_usuario_id'] ?? 0);

if (isset($colsCot['id_usuario_cotizo']) && $idUsuarioCotizo > 0) {
	$sets[] = "id_usuario_cotizo = " . intval($idUsuarioCotizo);
}

if (empty($sets)) {
	age_json([
		'ok' => false,
		'mensaje' => 'La tabla cotizaciones_generadas no tiene columnas de tasación/usuario todavía.'
	]);
}

$sql = "
	UPDATE cotizaciones_generadas
	SET " . implode(", ", $sets) . "
	WHERE id_cotizaciones_generadas = " . intval($idCotizacion) . "
	LIMIT 1
";

$ok = $db->query($sql);

if (!$ok) {
	age_json([
		'ok' => false,
		'mensaje' => 'No se pudo guardar la tasación manual en cotizaciones_generadas.'
	]);
}

age_json([
	'ok' => true,
	'id_agenda' => $idAgenda,
	'id_cotizacion' => $idCotizacion,
	'pretasacion_desde' => $pretasacionDesde,
	'pretasacion_hasta' => $pretasacionHasta,
	'tasacion_final' => $tasacionFinal,
	'id_usuario_cotizo' => $idUsuarioCotizo,
	'mensaje' => 'Tasación manual guardada correctamente en cotizaciones_generadas.'
]);