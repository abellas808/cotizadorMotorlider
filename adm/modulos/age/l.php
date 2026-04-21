<?php
if (!isset($sistema_iniciado)) exit();

date_default_timezone_set('America/Montevideo');

global $db;

// ===============================
// HELPERS
// ===============================
if (!function_exists('age_pick_arr')) {
    function age_pick_arr($row, $keys, $default = null) {
        foreach ($keys as $k) {
            if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null) return $row[$k];
        }
        return $default;
    }
}

if (!function_exists('age_find_schema_for_table')) {
    function age_find_schema_for_table($db, $tableName) {
        $t = $db->escape($tableName);
        $q = $db->query("\n            SELECT TABLE_SCHEMA\n            FROM information_schema.TABLES\n            WHERE TABLE_NAME = '{$t}'\n            ORDER BY TABLE_SCHEMA\n            LIMIT 1\n        ");
        $r = $q ? $db->fetch_array($q) : null;
        return $r ? ($r['TABLE_SCHEMA'] ?? null) : null;
    }
}

if (!function_exists('age_table_columns')) {
    function age_table_columns($table) {
        global $db;
        static $cache = array();
        if (isset($cache[$table])) return $cache[$table];
        $cols = array();
        $q = $db->query("SHOW COLUMNS FROM `" . addslashes($table) . "`");
        if ($q) {
            while ($r = $db->fetch_array($q)) {
                if (isset($r['Field'])) $cols[$r['Field']] = true;
            }
        }
        $cache[$table] = $cols;
        return $cols;
    }
}

if (!function_exists('age_sql_first_existing')) {
    function age_sql_first_existing($aliasTabla, $table, $candidatas, $defaultSql = "''") {
        $cols = age_table_columns($table);
        foreach ($candidatas as $col) {
            if (isset($cols[$col])) {
                return $aliasTabla . '.`' . $col . '`';
            }
        }
        return $defaultSql;
    }
}

if (!function_exists('age_format_fecha')) {
    function age_format_fecha($valor, $default = '-') {
        if (!isset($valor) || trim((string)$valor) === '' || $valor === '0000-00-00' || $valor === '0000-00-00 00:00:00') return $default;
        $ts = strtotime($valor);
        if ($ts === false) return $default;
        return strftime('%d/%m/%Y', $ts);
    }
}

if (!function_exists('age_format_hora')) {
    function age_format_hora($valor, $default = '-') {
        if (!isset($valor) || trim((string)$valor) === '') return $default;
        $valor = trim((string)$valor);
        $ts = strtotime($valor);
        if ($ts !== false) return date('H:i', $ts);
        if (strlen($valor) >= 5) return substr($valor, 0, 5);
        return $valor;
    }
}

if (!function_exists('age_format_tasacion')) {
    function age_format_tasacion($valor, $default = '-') {
        if (!isset($valor) || trim((string)$valor) === '') return $default;
        if (!is_numeric($valor)) return $valor;
        return '$ ' . number_format((float)$valor, 0, ',', '.');
    }
}

if (!function_exists('age_badge')) {
    function age_badge($texto, $tipo = 'gris') {
        $colores = array(
            'gris'      => array('fg' => '#5f6368', 'bg' => '#eef0f2'),
            'verde'     => array('fg' => '#155724', 'bg' => '#d4edda'),
            'amarillo'  => array('fg' => '#856404', 'bg' => '#fff3cd'),
            'rojo'      => array('fg' => '#721c24', 'bg' => '#f8d7da'),
            'azul'      => array('fg' => '#0c5460', 'bg' => '#d1ecf1')
        );
        if (!isset($colores[$tipo])) $tipo = 'gris';
        $c = $colores[$tipo];
        return '<span title="' . htmlspecialchars($texto) . '" style="display:inline-block;padding:2px 6px;border-radius:9px;font-size:10px;font-weight:bold;line-height:1.15;color:' . $c['fg'] . ';background:' . $c['bg'] . ';white-space:nowrap;">' . htmlspecialchars($texto) . '</span>';
    }
}

if (!function_exists('age_estado_agenda_badge')) {
    function age_estado_agenda_badge($entrada) {
        $tsAgenda = strtotime(($entrada['fecha'] ?? '') . ' ' . substr((string)($entrada['hora'] ?? ''), 0, 5));
        $yaPaso = ($tsAgenda !== false && $tsAgenda < time());

        if (!empty($entrada['cancelado'])) {
            return age_badge('CANCELADA', 'rojo');
        } elseif (!empty($entrada['finalizada']) || $yaPaso) {
            return age_badge('FINALIZADA', 'verde');
        }
        return age_badge('ACTIVA', 'azul');
    }
}

if (!function_exists('age_estado_cot_badge')) {
    function age_estado_cot_badge($estado) {
        $estado = trim((string)$estado);
        $upper = strtoupper($estado);
        if ($upper === 'FINALIZADA') return age_badge('FINALIZADA', 'verde');
        if ($upper === 'PENDIENTE') return age_badge('PENDIENTE', 'amarillo');
        if ($upper === 'CANCELADA') return age_badge('CANCELADA', 'rojo');
        if ($estado === '') return age_badge('SIN COT.', 'gris');
        return age_badge($estado, 'gris');
    }
}

if (!function_exists('age_qs_base')) {
    function age_qs_base($extra = array()) {
        global $modulo, $busqueda, $fecha_desde, $fecha_hasta, $estado_cot, $estado_age, $orden_campo, $orden_dir, $inactivo;
        $params = array('m' => $modulo['prefijo'] . '_l');
        if ($busqueda !== '') $params['b'] = $busqueda;
        if ($fecha_desde !== '') $params['fd'] = $fecha_desde;
        if ($fecha_hasta !== '') $params['fh'] = $fecha_hasta;
        if ($estado_cot !== '') $params['ecot'] = $estado_cot;
        if ($estado_age !== '') $params['eage'] = $estado_age;
        if (isset($inactivo) && $inactivo != 0) $params['e'] = $inactivo;
        foreach ($extra as $k => $v) {
            if ($v === null || $v === '') continue;
            $params[$k] = $v;
        }
        return '?' . http_build_query($params);
    }
}

if (!function_exists('age_sort_link')) {
    function age_sort_link($campo, $label) {
        global $orden_campo, $orden_dir, $od_chr;
        if ($orden_campo == $campo) {
            return '<a href="' . age_qs_base(array('o' => $campo, 'od' => $orden_dir == 0 ? 1 : 0)) . '"><strong>' . $label . ' ' . $od_chr . '</strong></a>';
        }
        return '<a href="' . age_qs_base(array('o' => $campo)) . '">' . $label . ' ▼</a>';
    }
}

// ===============================
// DATOS PARA POPUP DE COTIZACIÓN
// ===============================
$marcas = [];
$modelosPorMarca = [];

try {
    $schemaMarca  = age_find_schema_for_table($db, 'act_marcas');
    $schemaModelo = age_find_schema_for_table($db, 'act_modelo');

    $tblMarca  = $schemaMarca  ? "{$schemaMarca}.act_marcas"  : "act_marcas";
    $tblModelo = $schemaModelo ? "{$schemaModelo}.act_modelo" : "act_modelo";

    $qm = $db->query("SELECT * FROM {$tblMarca} ORDER BY nombre");
    if ($qm) {
        while ($r = $db->fetch_array($qm)) {
            $id  = age_pick_arr($r, ['id_marca','id','marca_id']);
            $nom = age_pick_arr($r, ['nombre','name','marca']);
            if ($id === null || $nom === null) continue;
            $marcas[] = ['id' => (string)$id, 'nombre' => (string)$nom];
        }
    }

    $qo = $db->query("SELECT * FROM {$tblModelo} ORDER BY nombre");
    if ($qo) {
        while ($r = $db->fetch_array($qo)) {
            $idMarca = age_pick_arr($r, ['id_marca','marca_id']);
            $idMod   = age_pick_arr($r, ['id_model','id_modelo','id_mdoelo','id','modelo_id']);
            $nomMod  = age_pick_arr($r, ['nombre','name','modelo']);
            if ($idMarca === null || $idMod === null || $nomMod === null) continue;

            $key = (string)$idMarca;
            if (!isset($modelosPorMarca[$key])) $modelosPorMarca[$key] = [];
            $modelosPorMarca[$key][] = ['id' => (string)$idMod, 'nombre' => (string)$nomMod];
        }
    }
} catch (Throwable $e) {
    $marcas = [];
    $modelosPorMarca = [];
}

// ===============================
// AUTO FINALIZAR AGENDAS PASADAS
// ===============================
$db->query("\n    UPDATE agendas\n    SET\n        finalizada = 1,\n        detalle_estado = CASE\n            WHEN detalle_estado IS NULL OR TRIM(detalle_estado) = ''\n                THEN 'Finalizada automáticamente por fecha/hora'\n            ELSE detalle_estado\n        END\n    WHERE IFNULL(cancelado, 0) = 0\n      AND IFNULL(finalizada, 0) = 0\n      AND STR_TO_DATE(CONCAT(fecha, ' ', LEFT(hora, 5)), '%Y-%m-%d %H:%i') < NOW()\n");

$pagina = intval($_GET['p'] ?? 0);
if ($pagina <= 0) $pagina = 1;

$busqueda = '';
$sql_b = '';

if (!empty($_GET['b'])) {
    $busqueda = substr(trim($_GET['b']), 0, 30);
    $busqueda_array = array_filter(explode(' ', $busqueda));

    foreach ($busqueda_array as $term) {
        $term = trim($term);
        if ($term === '') continue;
        $term = addslashes($term);

        $sql_b .= ' and (
            a.nombre like "%' . $term . '%"
            or a.ci like "%' . $term . '%"
            or a.auto like "%' . $term . '%"
            or a.hora like "%' . $term . '%"
            or a.fecha like "%' . $term . '%"
            or a.id_agenda like "%' . $term . '%"
            or c.id_cotizaciones_generadas like "%' . $term . '%"
            or c.auto like "%' . $term . '%"
            or c.estado like "%' . $term . '%"
        )';
    }
}

$fecha_desde = trim($_GET['fd'] ?? date('Y-m-01'));
$fecha_hasta = trim($_GET['fh'] ?? date('Y-m-d', strtotime('+30 days')));

$estado_cot = trim($_GET['ecot'] ?? '');
$estado_age = trim($_GET['eage'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde)) $fecha_desde = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta)) $fecha_hasta = date('Y-m-d');
if ($fecha_desde > $fecha_hasta) {
    $tmp = $fecha_desde;
    $fecha_desde = $fecha_hasta;
    $fecha_hasta = $tmp;
}

$orden_campo = intval($_GET['o'] ?? 0);
$orden_dir = isset($_GET['od']) ? intval($_GET['od']) : 0;

switch ($orden_dir) {
    case 1:
        $sql_od = 'asc';
        $od_chr = '▲';
        break;
    default:
        $sql_od = 'desc';
        $od_chr = '▼';
        $orden_dir = 0;
}

switch ($orden_campo) {
    case 1: $sql_o = 'a.id_agenda'; break;
    case 2: $sql_o = 'a.fecha'; break;
    case 3: $sql_o = "STR_TO_DATE(LEFT(a.hora, 5), '%H:%i')"; break;
    case 4: $sql_o = 'a.auto'; break;
    case 5: $sql_o = 'a.nombre'; break;
    case 6: $sql_o = 'a.cancelado'; break;
    case 7: $sql_o = 'a.detalle_estado'; break;
    case 8: $sql_o = 'c.id_cotizaciones_generadas'; break;
    case 9: $sql_o = 'c.auto'; break;
    case 10: $sql_o = 'pretasacion_desde_orden'; break;
    case 11: $sql_o = 'pretasacion_hasta_orden'; break;
    case 12: $sql_o = 'pretasacion_final_orden'; break;
    case 13: $sql_o = 'c.estado'; break;
    default:
        $sql_o = '';
        $orden_campo = 0;
        break;
}

$sql_b = trim($sql_b);
if ($sql_b != '') $sql_b = ' ' . $sql_b;

$inactivo = intval($_GET['e'] ?? 0);

$return_url = '?m=' . $modulo['prefijo'] . '_l'
    . '&p=' . $pagina
    . ($busqueda != '' ? '&b=' . urlencode($busqueda) : '')
    . '&fd=' . urlencode($fecha_desde)
    . '&fh=' . urlencode($fecha_hasta)
    . ($estado_cot != '' ? '&ecot=' . urlencode($estado_cot) : '')
    . ($estado_age != '' ? '&eage=' . urlencode($estado_age) : '')
    . '&o=' . $orden_campo
    . '&od=' . $orden_dir
    . ($inactivo != 0 ? '&e=' . $inactivo : '');

if ($orden_campo == 0) {
    $sql_order_by = "
        STR_TO_DATE(a.fecha, '%Y-%m-%d') DESC,
        STR_TO_DATE(LEFT(a.hora, 5), '%H:%i') ASC,
        a.id_agenda DESC
    ";
} else {
    $sql_order_by = $sql_o . ' ' . $sql_od . ', a.id_agenda ' . $sql_od;
}

$expr_pre_desde = age_sql_first_existing(
    'c',
    'cotizaciones_generadas',
    array(
        'pretasacion_desde',
        'pre_tasacion_desde',
        'tasacion_desde'
    ),
    'NULL'
);

$expr_pre_hasta = age_sql_first_existing(
    'c',
    'cotizaciones_generadas',
    array(
        'pretasacion_hasta',
        'pre_tasacion_hasta',
        'tasacion_hasta'
    ),
    'NULL'
);

$expr_pre_final = age_sql_first_existing(
    'c',
    'cotizaciones_generadas',
    array(
        'tasacion_final',
        'pretasacion_final',
        'valor_final',
        'precio_final',
        'cotizacion_final'
    ),
    'NULL'
);

$sql_filtros = '';
$sql_filtros .= " AND DATE(a.fecha) >= '" . addslashes($fecha_desde) . "'";
$sql_filtros .= " AND DATE(a.fecha) <= '" . addslashes($fecha_hasta) . "'";
if ($estado_cot !== '') {
    $sql_filtros .= " AND c.estado = '" . addslashes($estado_cot) . "'";
}
if ($estado_age !== '') {
    switch ($estado_age) {
        case 'ACTIVA':
            $sql_filtros .= " AND a.cancelado = 0 AND a.finalizada = 0";
            break;
        case 'FINALIZADA':
            $sql_filtros .= " AND a.finalizada = 1";
            break;
        case 'CANCELADA':
            $sql_filtros .= " AND a.cancelado = 1";
            break;
    }
}

$sql_from = '
    FROM agendas a
    LEFT JOIN cotizaciones_generadas c ON c.id_cotizaciones_generadas = a.id_cotizacion
    WHERE 1=1 ' . $sql_filtros . $sql_b;

$listado = $db->query(
    'SELECT SQL_CALC_FOUND_ROWS
        a.*, 
        c.id_cotizaciones_generadas AS cot_id,
        c.auto AS cot_auto,
        c.estado AS cot_estado,
        ' . $expr_pre_desde . ' AS pretasacion_desde,
        ' . $expr_pre_hasta . ' AS pretasacion_hasta,
        ' . $expr_pre_final . ' AS pretasacion_final,
        COALESCE(' . $expr_pre_desde . ', 0) AS pretasacion_desde_orden,
        COALESCE(' . $expr_pre_hasta . ', 0) AS pretasacion_hasta_orden,
        COALESCE(' . $expr_pre_final . ', 0) AS pretasacion_final_orden
     ' . $sql_from . '
     ORDER BY ' . $sql_order_by . '
     LIMIT ' . (($pagina - 1) * $config['pagina_cant']) . ', ' . $config['pagina_cant'] . ';'
);

$qry = $db->query_first('SELECT FOUND_ROWS() as cantidad;');
$total = intval($qry['cantidad'] ?? 0);

$total_paginas = ($config['pagina_cant'] > 0) ? ceil($total / $config['pagina_cant']) : 1;
if ($total_paginas <= 0) $total_paginas = 1;
?>
<?php require_once('sistema_cabezal.php'); ?>
<?php require_once('sistema_pre_contenido.php'); ?>

<style>
    #contenido_cabezal { clear:both; display:block; position:relative; background:#fff; z-index:20; margin:0 0 16px 0; padding:0; }
    #contenido_cabezal .titulo { clear:both; display:block; margin:0 0 12px 0; padding:0; position:relative; z-index:21; background:#fff; }
    .age-toolbar-wrap { clear:both; display:block; position:relative; z-index:21; background:#fff; margin:0 0 14px 0; padding:0; }
    .age-bloque-calendario { margin-top: 30px; margin-bottom: 20px; }
    .age-filtros-bar { clear:both; display:flex; flex-wrap:wrap; gap:10px 12px; align-items:flex-end; margin:0 0 10px 0; padding:12px; background:#f9f9f9; border:1px solid #e5e5e5; border-radius:4px; position:relative; z-index:22; }
    .age-filtro-item label { display:block; font-size:11px; color:#666; margin-bottom:2px; }
    .age-filtro-item input, .age-filtro-item select { height:30px; padding:4px 6px; font-size:12px; min-width:120px; }
    .age-panel { border: 1px solid #ddd; background: #fff; padding: 15px; border-radius: 4px; min-height: 340px; }
    .age-panel h5 { margin-top: 0; margin-bottom: 12px; }
    .age-cal-toolbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
    .age-cal-title { font-size:18px; font-weight:bold; }
    .age-calendar-grid { display:grid; grid-template-columns: repeat(7, 1fr); gap:6px; }
    .age-calendar-head { text-align:center; font-size:12px; font-weight:bold; color:#666; padding:6px 0; }
    .age-calendar-day { min-height:46px; border:1px solid #ddd; background:#f7f7f7; display:flex; align-items:center; justify-content:center; cursor:pointer; border-radius:4px; font-size:13px; }
    .age-calendar-day:hover { background:#e9f2ff; border-color:#a7c6f2; }
    .age-calendar-day-empty { background:transparent; border:0; cursor:default; }
    .age-calendar-day-past { background:#e5e5e5; color:#999; cursor:not-allowed; }
    .age-calendar-day-blocked { background:#f2dede; border-color:#dca7a7; color:#a94442; }
    .age-calendar-day-selected { background:#d9edf7; border-color:#7bb6d9; }
    .age-detalle-dia h4 { margin-top: 0; margin-bottom: 14px; }
    .age-acciones-bloqueo { margin-top: 14px; }
    .age-feedback { margin-top: 10px; font-weight: bold; }
    .age-help { color:#777; font-size:12px; margin-top:8px; }
    .age-slots-grid { display: flex; flex-wrap: wrap; gap: 8px; }
    .age-slot-card { width: 118px; min-height: 132px; border: 1px solid #d9d9d9; border-radius: 4px; padding: 8px; text-align: center; background: #f8f8f8; box-sizing: border-box; display: flex; flex-direction: column; justify-content: flex-start; }
    .age-slot-card-hora { font-weight: bold; font-size: 18px; margin-bottom: 8px; line-height: 1.2; }
    .age-slot-card-actions .btn { display: block; width: 100%; margin-bottom: 6px; box-sizing: border-box; }
    .age-slot-disponible { background: #fcfcfc; }
    .age-slot-ocupada { background: #f8f8f8; }
    .age-slot-bloqueada { background: #f2dede; border-color: #dca7a7; }
    .age-slot-pasada { background: #eef3f7; border-color: #cfdbe5; color: #5c6b77; }
    .age-btn-bloquear { background: #f0ad4e; color: #fff; border: 1px solid #eea236; }
    .age-btn-anular { background: #d9534f; color: #fff; border: 1px solid #d43f3a; }
    .age-btn-desbloquear { background: #d9534f; color: #fff; border: 1px solid #d43f3a; }
    .age-row-cancelada { background: #f2dede !important; color: #a94442; }
    .age-row-finalizada { background: #eef3f7 !important; color: #5c6b77; }
    .age-modal-bg { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9998; }
    .modal-grande { display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); width:900px; max-width:95%; max-height:90vh; overflow:auto; background:#fff; border:1px solid #ccc; border-radius:8px; padding:20px; z-index:9999; box-sizing:border-box; }
    .modal-content h3 { margin-top:0; margin-bottom:15px; }
    .age-modal-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:12px 16px; }
    .age-modal-field label { display:block; font-weight:bold; margin-bottom:4px; }
    .age-modal-field input, .age-modal-field select { width:100%; box-sizing:border-box; height:34px; padding:6px 10px; border:1px solid #cfcfcf; border-radius:4px; background:#fff; }
    .age-modal-actions { margin-top:16px; display:flex; gap:8px; flex-wrap:wrap; }
    .age-modal-estado { margin-top:10px; font-weight:bold; }
    .age-modal-resultado { margin-top:15px; }
    .age-box-ok { background:#f6fff5; border:1px solid #cfe6cc; border-radius:8px; padding:12px 14px; margin-top:12px; }
    .age-box-grid { display:flex; flex-wrap:wrap; gap:16px; margin-top:14px; }
    .age-box-col { flex:1 1 240px; background:#fff; border:1px solid #ddd; border-radius:8px; padding:12px; }

    .age-grid-wrap { overflow-x: hidden; margin-top: 60px !important; clear: both; position: relative; z-index: 1; }
    .age-grid-compact { width:100%; table-layout:fixed; font-size:10px; margin-bottom:0; }
    .age-grid-compact th, .age-grid-compact td { padding:3px 4px !important; vertical-align:middle !important; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; line-height:1.1; }
    .age-grid-compact thead tr.group-row th { font-size:11px; text-transform:uppercase; letter-spacing:.35px; padding-top:6px !important; padding-bottom:6px !important; border-bottom:1px solid #d8dde3; }
    .age-grid-compact thead tr.group-row th.group-age { background:#eef5ff; color:#355b8c; }
    .age-grid-compact thead tr.group-row th.group-cot { background:#eef8f1; color:#2f6d45; border-left:3px solid #d6e9dc; }
    .age-grid-compact thead tr.cols-row th { font-size:10px; color:#666; background:#fafafa; }
    .age-grid-compact thead tr.cols-row th a { color:#3d6f99; text-decoration:none; display:inline-block; width:100%; }
    .age-grid-compact thead tr.cols-row th a:hover { text-decoration:underline; }
    .age-grid-compact td.sep-cot, .age-grid-compact th.sep-cot { border-left:3px solid #dde6dd !important; }
    .age-col-cod { width:48px; }
    .age-col-fecha { width:76px; text-align:center; }
    .age-col-hora { width:48px; text-align:center; }
    .age-col-auto { width:120px; }
    .age-col-nombre { width:96px; }
    .age-col-estado { width:90px; text-align:center; }
    .age-col-detalle { width:170px; }
    .age-col-money { width:70px; text-align:right; }
    .age-col-check { width:26px; text-align:center; }
    @media (max-width: 768px) { .age-modal-grid { grid-template-columns: 1fr; } }
</style>

<div id="contenido_cabezal">
    <div class="pull-right" style="position:relative; z-index:25;">
        <input type="text" id="b" onkeypress="if (event.keyCode == 13) { buscarListado(); }" value="<?php echo_s($busqueda); ?>" maxlength="30" />
        <?php if ($busqueda != '') { ?>
            <button type="button" class="btn btn-default btn-small btn_cerrar" onclick="window.location.href='?m=<?php echo $modulo['prefijo']; ?>_l&fd='+encodeURIComponent($('#fd').val())+'&fh='+encodeURIComponent($('#fh').val())+'&ecot='+encodeURIComponent($('#ecot').val())+'&eage='+encodeURIComponent($('#eage').val())+'<?php if ($orden_campo != 0) { echo '&o=' . $orden_campo; } ?>&od=<?php echo $orden_dir; ?><?php if ($inactivo != 0) { echo '&e=' . $inactivo; } ?>';">X</button>
        <?php } ?>
        <button type="button" class="btn btn-default btn-small" onclick="buscarListado();">Buscar</button>
    </div>

    <h4 class="titulo"><?php echo $modulo['nombre']; ?></h4>

    <div class="age-toolbar-wrap">
    <div class="age-filtros-bar">
        <div class="age-filtro-item">
            <label for="fd">Fecha desde</label>
            <input type="date" id="fd" value="<?php echo_s($fecha_desde); ?>" />
        </div>
        <div class="age-filtro-item">
            <label for="fh">Fecha hasta</label>
            <input type="date" id="fh" value="<?php echo_s($fecha_hasta); ?>" />
        </div>
        <div class="age-filtro-item">
            <label for="ecot">Estado cotización</label>
            <select id="ecot">
                <option value="">Todos</option>
                <option value="PENDIENTE" <?php echo $estado_cot === 'PENDIENTE' ? 'selected' : ''; ?>>PENDIENTE</option>
                <option value="FINALIZADA" <?php echo $estado_cot === 'FINALIZADA' ? 'selected' : ''; ?>>FINALIZADA</option>
                <option value="CANCELADA" <?php echo $estado_cot === 'CANCELADA' ? 'selected' : ''; ?>>CANCELADA</option>
            </select>
        </div>
        <div class="age-filtro-item">
            <label for="eage">Estado agenda</label>
            <select id="eage">
                <option value="">Todos</option>
                <option value="ACTIVA" <?php echo $estado_age === 'ACTIVA' ? 'selected' : ''; ?>>ACTIVA</option>
                <option value="FINALIZADA" <?php echo $estado_age === 'FINALIZADA' ? 'selected' : ''; ?>>FINALIZADA</option>
                <option value="CANCELADA" <?php echo $estado_age === 'CANCELADA' ? 'selected' : ''; ?>>CANCELADA</option>
            </select>
        </div>
        <div class="age-filtro-item">
            <button type="button" class="btn btn-default btn-small" onclick="buscarListado();">Aplicar</button>
            <button type="button" class="btn btn-default btn-small" onclick="window.location.href='?m=<?php echo $modulo['prefijo']; ?>_l';">Limpiar</button>
        </div>
    </div>
    </div>
</div>

<div class="sep_titulo" style="height:8px; clear:both;"></div>

<div id="modal_agendar_bg" class="age-modal-bg"></div>
<div id="modal_agenda_cotizador" class="modal-grande">
    <div class="modal-content">
        <h3>Agendar inspección</h3>
        <div id="modal_cotizador_container"></div>
    </div>
</div>

<?php if ($total > 0) { ?>
<?php if ($_SESSION[$config['codigo_unico']]['login_permisos']['mod'] > 1) { ?>
<form id="form_listado" action="?m=age_e" method="post">
    <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($return_url); ?>" />
<?php } ?>

<div class="age-grid-wrap">
<table class="table table-hover age-grid-compact">
    <thead>
        <tr class="group-row">
            <th colspan="7" class="group-age">Datos de la agenda</th>
            <th colspan="5" class="group-cot sep-cot">Datos de la cotización</th>
            <th colspan="1"></th>
        </tr>
        <tr class="cols-row">
            <th class="age-col-cod"><?php echo age_sort_link(1, 'Codigo'); ?></th>
            <th class="age-col-fecha"><?php echo age_sort_link(2, 'Fecha'); ?></th>
            <th class="age-col-hora"><?php echo age_sort_link(3, 'Hora'); ?></th>
            <th class="age-col-auto"><?php echo age_sort_link(4, 'Automovil'); ?></th>
            <th class="age-col-nombre"><?php echo age_sort_link(5, 'Nombre'); ?></th>
            <th class="age-col-estado"><?php echo age_sort_link(6, 'Estado'); ?></th>
            <th class="age-col-detalle"><?php echo age_sort_link(7, 'Detalle'); ?></th>
            <th class="age-col-cod sep-cot"><?php echo age_sort_link(8, 'Cod.'); ?></th>
            <th class="age-col-auto"><?php echo age_sort_link(9, 'Auto'); ?></th>
            <th class="age-col-money"><?php echo age_sort_link(10, 'Desde'); ?></th>
            <th class="age-col-money"><?php echo age_sort_link(11, 'Hasta'); ?></th>
            <th class="age-col-money"><?php echo age_sort_link(12, 'Final'); ?></th>
            <th class="age-col-estado"><?php echo age_sort_link(13, 'Estado'); ?></th>
            <th class="age-col-check"></th>
        </tr>
    </thead>
    <tfoot>
        <tr>
            <td height="30" colspan="14" valign="bottom">
                <div class="info_seleccionados" style="display:none;">
                    <span id="cantidad_seleccionados"></span>
                    <?php if ($_SESSION[$config['codigo_unico']]['login_permisos']['mod'] > 1) { ?>
                        - <input type="button" class="btn btn-danger btn-small" value="Eliminar" onclick="eliminar();" />
                    <?php } ?>
                </div>

                <div class="info_listados">Total: <strong><?php echo $total; ?></strong></div>

                <?php if ($total_paginas > 1) { ?>
                    <div class="paginas">
                        <?php if ($pagina > 1) { ?>
                            <a href="<?php echo age_qs_base(array('p' => $pagina - 1, 'o' => $orden_campo != 0 ? $orden_campo : null, 'od' => $orden_dir != 0 ? $orden_dir : null)); ?>">&lt; anterior</a>
                        <?php } ?>

                        <select id="select_pagina" class="input-mini">
                            <?php for ($i = 1; $i <= $total_paginas; $i++) { ?>
                                <option value="<?php echo $i; ?>" <?php if ($i == $pagina) echo 'selected="selected"'; ?>><?php echo $i; ?></option>
                            <?php } ?>
                        </select>

                        / <?php echo $total_paginas; ?>

                        <?php if ($pagina < $total_paginas) { ?>
                            <a href="<?php echo age_qs_base(array('p' => $pagina + 1, 'o' => $orden_campo != 0 ? $orden_campo : null, 'od' => $orden_dir != 0 ? $orden_dir : null)); ?>">siguiente &gt;</a>
                        <?php } ?>
                    </div>
                <?php } ?>
            </td>
        </tr>
    </tfoot>
    <tbody>
        <?php while ($entrada = $db->fetch_array($listado)) { ?>
            <?php
            $tsAgenda = strtotime($entrada['fecha'] . ' ' . substr((string)$entrada['hora'], 0, 5));
            $yaPaso = ($tsAgenda !== false && $tsAgenda < time());
            $claseFila = '';
            if (!empty($entrada['cancelado'])) $claseFila = 'age-row-cancelada';
            elseif (!empty($entrada['finalizada']) || $yaPaso) $claseFila = 'age-row-finalizada';

            $detalleAgenda = '';
            if (!empty($entrada['cancelado'])) {
                $detalleAgenda = trim((string)($entrada['motivo_cancelacion'] ?? ''));
            } elseif (!empty($entrada['finalizada']) || $yaPaso) {
                $detalleAgenda = trim((string)($entrada['detalle_estado'] ?? ''));
                if ($detalleAgenda === '') $detalleAgenda = 'Finalizada automáticamente por fecha/hora';
            }
            ?>
            <tr class="<?php echo $claseFila; ?>">
                <td class="age-col-cod"><a href="?m=<?php echo $modulo['prefijo']; ?>_v&i=<?php echo $entrada['id_agenda']; ?>"><?php echo_s($entrada['id_agenda']); ?></a></td>
                <td class="age-col-fecha"><?php echo_s(age_format_fecha($entrada['fecha'])); ?></td>
                <td class="age-col-hora"><?php echo_s(age_format_hora($entrada['hora'])); ?></td>
                <td class="age-col-auto" title="<?php echo htmlspecialchars((string)$entrada['auto']); ?>"><?php echo_s($entrada['auto']); ?></td>
                <td class="age-col-nombre" title="<?php echo htmlspecialchars((string)$entrada['nombre']); ?>"><?php echo_s($entrada['nombre']); ?></td>
                <td class="age-col-estado"><?php echo age_estado_agenda_badge($entrada); ?></td>
                <td class="age-col-detalle" title="<?php echo htmlspecialchars($detalleAgenda); ?>"><?php echo_s($detalleAgenda); ?></td>
                <td class="age-col-cod sep-cot">
                    <?php if (intval($entrada['cot_id']) > 0) { ?>
                        <a href="?m=cot_v&i=<?php echo intval($entrada['cot_id']); ?>"><?php echo intval($entrada['cot_id']); ?></a>
                    <?php } else { echo '-'; } ?>
                </td>
                <td class="age-col-auto" title="<?php echo htmlspecialchars((string)($entrada['cot_auto'] ?? '')); ?>"><?php echo_s($entrada['cot_auto'] ?? '-'); ?></td>
                <td class="age-col-money"><?php echo_s(age_format_tasacion($entrada['pretasacion_desde'] ?? '')); ?></td>
                <td class="age-col-money"><?php echo_s(age_format_tasacion($entrada['pretasacion_hasta'] ?? '')); ?></td>
                <td class="age-col-money" style="font-weight:bold;"><?php echo_s(age_format_tasacion($entrada['pretasacion_final'] ?? '')); ?></td>
                <td class="age-col-estado"><?php echo age_estado_cot_badge($entrada['cot_estado'] ?? ''); ?></td>
                <td class="age-col-check"><input name="e_sel[]" type="checkbox" value="<?php echo $entrada['id_agenda']; ?>" /></td>
            </tr>
        <?php } ?>
    </tbody>
</table>
</div>

<?php if ($_SESSION[$config['codigo_unico']]['login_permisos']['mod'] > 1) { ?></form><?php } ?>

<div class="row age-bloque-calendario">
    <div class="span6">
        <div class="age-panel">
            <div class="age-cal-toolbar">
                <button type="button" class="btn btn-small" onclick="cambiarMes(-1)">&lt;</button>
                <div class="age-cal-title" id="cal_titulo"></div>
                <button type="button" class="btn btn-small" onclick="cambiarMes(1)">&gt;</button>
            </div>
            <div id="calendario"></div>
            <div class="age-help">Gris: día pasado | Rojo: día bloqueado completo</div>
        </div>
    </div>

    <div class="span6">
        <div class="age-panel age-detalle-dia">
            <h5>Detalle del día</h5>
            <div id="detalle_dia">Seleccioná un día</div>
        </div>
    </div>
</div>


<script>
const AGE_MARCAS = <?php echo json_encode($marcas, JSON_UNESCAPED_UNICODE); ?>;
const AGE_MODELOS_POR_MARCA = <?php echo json_encode($modelosPorMarca, JSON_UNESCAPED_UNICODE); ?>;

let fechaSeleccionada = null;
let agendaHoraSeleccionada = '';
let agendaFechaSeleccionada = '';
let calYear = (new Date()).getFullYear();
let calMonth = (new Date()).getMonth();
const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

function actualizarSeleccionados() {
    var t = $('input[name="e_sel[]"]:checked').length;
    $('input[name="e_sel[]"]').each(function() { $(this).closest('tr').toggleClass('info', $(this).is(':checked')); });
    if (t > 0) { $('.info_seleccionados').show(); $('#cantidad_seleccionados').html(t === 1 ? '1 elemento seleccionado' : t + ' elementos seleccionados'); }
    else { $('.info_seleccionados').hide(); $('#cantidad_seleccionados').html(''); }
}

$('input[name="e_sel[]"]').on('click', actualizarSeleccionados);

$('#select_pagina').on('change', function() {
    window.location.href = '<?php echo age_qs_base(array('p' => 'REEMPLAZAR', 'o' => $orden_campo != 0 ? $orden_campo : null, 'od' => $orden_dir != 0 ? $orden_dir : null)); ?>'.replace('REEMPLAZAR', $(this).val());
});

function buscarListado() {
    window.location.href = '?m=<?php echo $modulo['prefijo']; ?>_l'
        + '&fd=' + encodeURIComponent($('#fd').val())
        + '&fh=' + encodeURIComponent($('#fh').val())
        + '&ecot=' + encodeURIComponent($('#ecot').val())
        + '&eage=' + encodeURIComponent($('#eage').val())
        + '<?php if ($orden_campo != 0) echo '&o=' . $orden_campo; ?>'
        + '&od=<?php echo $orden_dir; ?>'
        + '<?php if ($inactivo != 0) echo '&e=' . $inactivo; ?>'
        + '&b=' + encodeURIComponent($('#b').val());
}

function eliminar() {
    var seleccionados = $('input[name="e_sel[]"]:checked').length;
    if (seleccionados <= 0) { alert('Seleccioná al menos una agenda.'); return; }
    if (confirm('¿Esta seguro que desea eliminar los elementos seleccionados?')) { $('#form_listado').submit(); }
}

function cambiarMes(delta) {
    calMonth += delta;
    if (calMonth < 0) { calMonth = 11; calYear--; }
    if (calMonth > 11) { calMonth = 0; calYear++; }
    cargarCalendario();
}

function cargarCalendario() {
    $('#cal_titulo').html(meses[calMonth] + ' ' + calYear);

    $.post('/adm/modulos/age/ajax_dia.php', { accion: 'calendario', year: calYear, month: calMonth + 1 }, function(resp) {
        if (!resp || !resp.ok) { $('#calendario').html('<p style="color:red;">No se pudo cargar el calendario.</p>'); return; }

        const today = new Date();
        const firstDay = new Date(calYear, calMonth, 1);
        const lastDay = new Date(calYear, calMonth + 1, 0);

        let html = '<div class="age-calendar-grid">';
        const heads = ['L','M','M','J','V','S','D'];
        for (let i = 0; i < heads.length; i++) html += '<div class="age-calendar-head">' + heads[i] + '</div>';

        let startDay = firstDay.getDay();
        startDay = (startDay === 0) ? 6 : startDay - 1;

        for (let i = 0; i < startDay; i++) html += '<div class="age-calendar-day age-calendar-day-empty"></div>';

        for (let d = 1; d <= lastDay.getDate(); d++) {
            let fecha = calYear + '-' + String(calMonth + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
            let clases = 'age-calendar-day';
            let puedeClick = true;

            let current = new Date(calYear, calMonth, d, 0, 0, 0, 0);
            let hoy = new Date(today.getFullYear(), today.getMonth(), today.getDate(), 0, 0, 0, 0);

            if (current < hoy) { clases += ' age-calendar-day-past'; puedeClick = false; }
            if (resp.bloqueados_completos.indexOf(fecha) !== -1) clases += ' age-calendar-day-blocked';
            if (fechaSeleccionada === fecha) clases += ' age-calendar-day-selected';

            if (puedeClick) html += '<div class="' + clases + '" onclick="seleccionarDia(\'' + fecha + '\')">' + d + '</div>';
            else html += '<div class="' + clases + '">' + d + '</div>';
        }

        html += '</div>';
        $('#calendario').html(html);

    }, 'json').fail(function() {
        $('#calendario').html('<p style="color:red;">Error cargando el calendario.</p>');
    });
}

function renderSlotCard(h) {
    let html = '';
    let cardClass = 'age-slot-card';

    if (h.estado === 'bloqueada') cardClass += ' age-slot-bloqueada';
    else if (h.estado === 'ocupada') cardClass += ' age-slot-ocupada';
    else if (h.estado === 'pasada') cardClass += ' age-slot-pasada';
    else cardClass += ' age-slot-disponible';

    html += '<div class="' + cardClass + '">';
    html += '<div class="age-slot-card-hora">' + h.hora + '</div>';
    html += '<div class="age-slot-card-actions">';

    if (h.estado === 'pasada') html += '<div style="margin-top:6px;color:#5c6b77;font-weight:bold;">Finalizada</div>';
    else if (h.estado === 'bloqueada') html += '<button class="btn btn-small age-btn-desbloquear" onclick="desbloquearHora(\'' + h.hora + '\')">Desbloquear</button>';
    else if (h.estado === 'ocupada') {
        html += '<a class="btn btn-small" href="?m=age_v&i=' + h.id_agenda + '">Ver</a>';
        html += '<button class="btn btn-small age-btn-anular" onclick="anularAgenda(' + h.id_agenda + ')">Anular</button>';
    } else {
        html += '<button class="btn btn-small" onclick="abrirModalAgenda(\'' + h.hora + '\')">Agendar</button>';
        html += '<button class="btn btn-small age-btn-bloquear" onclick="bloquearHora(\'' + h.hora + '\')">Bloquear</button>';
    }

    html += '</div></div>';
    return html;
}

function seleccionarDia(fecha) {
    fechaSeleccionada = fecha;
    $('#detalle_dia').html('Cargando...');

    $.post('/adm/modulos/age/ajax_dia.php', { accion: 'detalle', fecha: fecha }, function(resp) {
        if (!resp || !resp.ok) {
            $('#detalle_dia').html('<p style="color:red;">No se pudo cargar el detalle del día.</p>');
            return;
        }

        let html = '<h4>' + fecha + '</h4>';

        if (resp.dia_bloqueado) {
            html += '<p style="color:#a94442;"><strong>Día bloqueado completo</strong></p>';
            html += '<button class="btn btn-small age-btn-desbloquear" onclick="desbloquearDia()">Desbloquear día completo</button>';
        } else {
            if (resp.horas.length <= 0) html += '<p style="color:red;">No hay horas configuradas para ese día.</p>';
            else {
                html += '<div class="age-slots-grid">';
                resp.horas.forEach(function(h) { html += renderSlotCard(h); });
                html += '</div>';
            }
            html += '<div class="age-acciones-bloqueo"><button class="btn btn-warning btn-small" onclick="bloquearDia()">Bloquear día completo</button></div>';
        }

        html += '<div class="age-feedback" id="bloqueo_feedback"></div>';
        $('#detalle_dia').html(html);
        cargarCalendario();

    }, 'json').fail(function(xhr) {
        $('#detalle_dia').html('<p style="color:red;">Error cargando horarios del día.</p><pre style="white-space:pre-wrap;">' + (xhr.responseText || 'sin respuesta') + '</pre>');
    });
}

function anularAgenda(id) {
    if (!confirm('¿Seguro que querés anular esta agenda?')) return;

    $.post('/adm/modulos/age/ajax_anular.php', { id_agenda: id }, function(resp) {
        if (resp.ok) {
            alert('Agenda anulada correctamente');
            seleccionarDia(fechaSeleccionada);
            setTimeout(function() { window.location.reload(); }, 300);
        } else alert(resp.mensaje || 'Error al anular');
    }, 'json').fail(function() {
        alert('Error al anular la agenda');
    });
}

function bloquearDia() {
    if (!fechaSeleccionada) return;
    if (!confirm("¿Bloquear todo el día " + fechaSeleccionada + "?")) return;

    $.post('/adm/modulos/age/ajax_bloquear.php', { accion: 'bloquear', fecha: fechaSeleccionada, hora: '' }, function(resp) {
        if (resp && resp.ok) {
            $('#bloqueo_feedback').html('Día bloqueado correctamente. Agendas afectadas: ' + (resp.afectadas || 0));
            seleccionarDia(fechaSeleccionada);
        } else $('#bloqueo_feedback').html(resp && resp.mensaje ? resp.mensaje : 'No se pudo bloquear el día.');
    }, 'json').fail(function() {
        $('#bloqueo_feedback').html('Error al bloquear el día.');
    });
}

function desbloquearDia() {
    if (!fechaSeleccionada) return;
    if (!confirm("¿Desbloquear todo el día " + fechaSeleccionada + "?")) return;

    $.post('/adm/modulos/age/ajax_bloquear.php', { accion: 'desbloquear', fecha: fechaSeleccionada, hora: '' }, function(resp) {
        if (resp && resp.ok) {
            $('#bloqueo_feedback').html('Día desbloqueado correctamente.');
            seleccionarDia(fechaSeleccionada);
        } else $('#bloqueo_feedback').html(resp && resp.mensaje ? resp.mensaje : 'No se pudo desbloquear el día.');
    }, 'json').fail(function() {
        $('#bloqueo_feedback').html('Error al desbloquear el día.');
    });
}

function bloquearHora(hora) {
    if (!fechaSeleccionada) return;
    if (!confirm("¿Bloquear la hora " + hora + " del día " + fechaSeleccionada + "?")) return;

    $.post('/adm/modulos/age/ajax_bloquear.php', { accion: 'bloquear', fecha: fechaSeleccionada, hora: hora }, function(resp) {
        if (resp && resp.ok) {
            $('#bloqueo_feedback').html('Hora bloqueada correctamente. Agendas afectadas: ' + (resp.afectadas || 0));
            seleccionarDia(fechaSeleccionada);
        } else $('#bloqueo_feedback').html(resp && resp.mensaje ? resp.mensaje : 'No se pudo bloquear la hora.');
    }, 'json').fail(function() {
        $('#bloqueo_feedback').html('Error al bloquear la hora.');
    });
}

function desbloquearHora(hora) {
    if (!fechaSeleccionada) return;
    if (!confirm("¿Desbloquear la hora " + hora + " del día " + fechaSeleccionada + "?")) return;

    $.post('/adm/modulos/age/ajax_bloquear.php', { accion: 'desbloquear', fecha: fechaSeleccionada, hora: hora }, function(resp) {
        if (resp && resp.ok) {
            $('#bloqueo_feedback').html('Hora desbloqueada correctamente.');
            seleccionarDia(fechaSeleccionada);
        } else $('#bloqueo_feedback').html(resp && resp.mensaje ? resp.mensaje : 'No se pudo desbloquear la hora.');
    }, 'json').fail(function() {
        $('#bloqueo_feedback').html('Error al desbloquear la hora.');
    });
}

function abrirModalAgenda(hora) {
    agendaHoraSeleccionada = hora;
    agendaFechaSeleccionada = fechaSeleccionada;

    let html = '';
    html += '<div class="age-modal-grid">';
    html += '<div class="age-modal-field"><label>Fecha</label><input type="text" id="cotiza_fecha_agenda" value="' + agendaFechaSeleccionada + '" readonly></div>';
    html += '<div class="age-modal-field"><label>Hora</label><input type="text" id="cotiza_hora_agenda" value="' + agendaHoraSeleccionada + '" readonly></div>';
    html += '<div class="age-modal-field"><label>Nombre</label><input type="text" id="cotiza_nombre" placeholder="Nombre"></div>';
    html += '<div class="age-modal-field"><label>Teléfono</label><input type="text" id="cotiza_telefono" placeholder="Teléfono"></div>';
    html += '<div class="age-modal-field"><label>Email</label><input type="email" id="cotiza_email" placeholder="cliente@email.com"></div>';
    html += '<div class="age-modal-field"><label>Marca</label><select id="cotiza_marca"><option value="">-- Seleccionar --</option></select></div>';
    html += '<div class="age-modal-field"><label>Modelo</label><select id="cotiza_modelo" disabled><option value="">-- Seleccionar --</option></select></div>';
    html += '<div class="age-modal-field"><label>Año</label><input type="number" id="cotiza_anio" placeholder="Ej: 2022"></div>';
    html += '<div class="age-modal-field"><label>Versión</label><input type="text" id="cotiza_version" placeholder="Ej: Full"></div>';
    html += '<div class="age-modal-field"><label>Tipo de venta</label><select id="cotiza_tipo_venta"><option value="">-- Seleccionar --</option><option value="venta_contado">Venta contado</option><option value="entrega_forma_pago">Entrega como forma de pago</option></select></div>';
    html += '<div class="age-modal-field"><label>¿Posee ficha oficial?</label><select id="cotiza_ficha_oficial"><option value="">-- Seleccionar --</option><option value="si">Sí</option><option value="no">No</option></select></div>';
    html += '<div class="age-modal-field"><label>Kilómetros</label><input type="number" id="cotiza_km" placeholder="Ej: 85000"></div>';
    html += '<div class="age-modal-field"><label>Valor pretendido</label><input type="number" id="cotiza_valor" placeholder="Ej: 15000"></div>';
    html += '</div>';
    html += '<div class="age-modal-actions"><button type="button" class="btn btn-primary btn-small" id="btn_cotiza_simular_popup">Cotizar y agendar</button><button type="button" class="btn btn-small" onclick="cerrarModalCotizador()">Cancelar</button></div>';
    html += '<div id="cotiza_estado_popup" class="age-modal-estado"></div>';
    html += '<div id="cotiza_resultado_popup" class="age-modal-resultado"></div>';
    html += '<div id="tasacion_manual_box" class="age-box-ok" style="display:none;">';
    html += '  <h4 style="margin-top:0;">Tasación manual</h4>';
    html += '  <div class="age-modal-grid">';
    html += '    <div class="age-modal-field"><label>Pre tasación desde</label><input type="number" id="pretasacion_desde" placeholder="Ej: 12000"></div>';
    html += '    <div class="age-modal-field"><label>Pre tasación hasta</label><input type="number" id="pretasacion_hasta" placeholder="Ej: 14500"></div>';
    html += '    <div class="age-modal-field"><label>Tasación final</label><input type="number" id="tasacion_final" placeholder="Ej: 13800"></div>';
    html += '  </div>';
    html += '  <div class="age-modal-actions">';
    html += '    <button type="button" class="btn btn-success btn-small" id="btn_guardar_tasacion_manual" disabled>Guardar tasación</button>';
    html += '  </div>';
    html += '  <div id="tasacion_manual_estado" class="age-modal-estado"></div>';
    html += '</div>';

    $('#modal_cotizador_container').html(html);

    let options = '<option value="">-- Seleccionar --</option>';
    AGE_MARCAS.forEach(function(m) {
        options += '<option value="' + String(m.id).replace(/"/g, '&quot;') + '">' + $('<div>').text(m.nombre).html() + '</option>';
    });
    $('#cotiza_marca').html(options);

    $('#cotiza_marca').off('change').on('change', function() { cargarModelosCotiza($(this).val()); });
    $('#btn_cotiza_simular_popup').off('click').on('click', function() { cotizarYAgendarPopup(); });

    $('#modal_agendar_bg').show();
    $('#modal_agenda_cotizador').show();
}

function cerrarModalCotizador() {
    $('#modal_agendar_bg').hide();
    $('#modal_agenda_cotizador').hide();
    $('#modal_cotizador_container').html('');
}

function cargarModelosCotiza(idMarca) {
    const $m = $('#cotiza_modelo');
    $m.empty();

    if (!idMarca || !AGE_MODELOS_POR_MARCA[idMarca] || !AGE_MODELOS_POR_MARCA[idMarca].length) {
        $m.append('<option value="">-- Seleccionar --</option>');
        $m.prop('disabled', true);
        return;
    }

    $m.append('<option value="">-- Seleccionar --</option>');
    AGE_MODELOS_POR_MARCA[idMarca].forEach(function(x) {
        $m.append('<option value="' + x.id + '">' + $('<div>').text(x.nombre).html() + '</option>');
    });
    $m.prop('disabled', false);
}

function cotizaFormatNumber(val) {
    let n = parseFloat(val);
    if (!isFinite(n)) return '-';
    const decimal = n - Math.floor(n);
    if (decimal <= 0.50) n = Math.floor(n); else n = Math.ceil(n);
    return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

function cotizarYAgendarPopup() {
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
    const nombre    = ($('#cotiza_nombre').val() || '').toString().trim();
    const telefono  = ($('#cotiza_telefono').val() || '').toString().trim();

    if (!marcaId || !modeloId || !anio || !km || !valor || !email || !nombre || !telefono || !tipoVenta || !ficha) {
        $('#cotiza_estado_popup').css('color', '#a94442').text('Completá todos los datos obligatorios de la cotización.');
        return;
    }

    const fichaTecnica = (ficha === 'si') ? 1 : 0;
    const ventaPermuta = (tipoVenta === 'entrega_forma_pago') ? 1 : 0;
    const nombreAuto = [marcaTxt, modeloTxt, anio, version].join(' ').replace(/\s+/g, ' ').trim();
    const endpointUrl = '/apicotizador/cotizadorPublico/' + encodeURIComponent(marcaId);

    const payload = {
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
        nombre: nombre,
        email: email,
        telefono: telefono
    };

    $('#cotiza_estado_popup').css('color', '#333').text('Cotizando...');

    $.ajax({
        url: endpointUrl,
        type: 'POST',
        data: JSON.stringify(payload),
        contentType: 'application/json; charset=utf-8',
        dataType: 'json'
    })
    .done(function(res) {
        if (!(res && (res.ok === true || res.error === 0 || res.error === '0' || res.error === false))) {
            $('#cotiza_estado_popup').css('color', '#a94442').text(res?.mensaje || res?.msg || 'Error al cotizar.');
            return;
        }

        const resultado = res?.resultado || {};
        const idCotizacion = res?.id_cotizacion || res?.cotizacion_id || res?.id || resultado?.id_cotizacion || resultado?.cotizacion_id || 0;

        let html = '';
        html += '<div class="age-box-ok"><strong>Cotización OK.</strong> Guardando agenda...</div>';
        html += '<div class="age-box-grid">';
        html += '<div class="age-box-col"><div><strong>Vehículo:</strong> ' + $('<div>').text(nombreAuto).html() + '</div><div><strong>Año:</strong> ' + $('<div>').text(anio).html() + '</div><div><strong>Kilómetros:</strong> ' + $('<div>').text(km).html() + '</div><div><strong>Valor pretendido:</strong> USD ' + cotizaFormatNumber(valor) + '</div></div>';
        html += '<div class="age-box-col"><div><strong>ID Cotización:</strong> ' + $('<div>').text(idCotizacion).html() + '</div><div><strong>Valor mínimo:</strong> USD ' + cotizaFormatNumber(resultado?.min || resultado?.valor_minimo || 0) + '</div><div><strong>Valor máximo:</strong> USD ' + cotizaFormatNumber(resultado?.max || resultado?.valor_maximo || 0) + '</div><div><strong>Promedio:</strong> USD ' + cotizaFormatNumber(resultado?.avg || resultado?.valor_promedio || 0) + '</div></div>';
        html += '</div>';
        $('#cotiza_resultado_popup').html(html);
        $('#cotiza_estado_popup').css('color', '#468847').text('Cotización OK. Guardando agenda...');

        $.post('/adm/modulos/age/ajax_agendar.php', {
            fecha: agendaFechaSeleccionada,
            hora: agendaHoraSeleccionada,
            nombre: nombre,
            email: email,
            telefono: telefono,
            auto: nombreAuto,
            marca: marcaTxt,
            modelo: modeloTxt,
            anio: anio,
            familia: version,
            id_cotizacion: idCotizacion
        }, function(resp) {
            if (resp && resp.ok) {
                $('#cotiza_estado_popup').css('color', '#468847').text('Agenda creada correctamente. Ahora completá la tasación manual y guardala.');
                $('#cotiza_resultado_popup').append('<div class="age-box-ok"><strong>Agenda generada.</strong> Código agenda: ' + $('<div>').text(resp.id_agenda || '').html() + ' | ID cotización: ' + $('<div>').text(resp.id_cotizacion || '').html() + ' | Usuario: ' + $('<div>').text(resp.id_usuario_cotizo || '').html() + '</div>');
                $('#tasacion_manual_box').show();
                $('#btn_guardar_tasacion_manual').prop('disabled', false).off('click').on('click', function(){
                    guardarTasacionManual(resp.id_agenda || 0);
                });
                seleccionarDia(fechaSeleccionada);
            } else {
                $('#cotiza_estado_popup').css('color', '#a94442').text(resp?.mensaje || 'No se pudo guardar la agenda.');
            }
        }, 'json').fail(function() {
            $('#cotiza_estado_popup').css('color', '#a94442').text('Error al guardar la agenda.');
        });
    })
    .fail(function(xhr) {
        let msg = 'No se pudo cotizar.';
        if (xhr && xhr.responseText) {
            try {
                const j = JSON.parse(xhr.responseText);
                if (j && (j.mensaje || j.msg)) msg = j.mensaje || j.msg;
            } catch(e) {}
        }
        $('#cotiza_estado_popup').css('color', '#a94442').text(msg);
    });
}

function guardarTasacionManual(idAgenda) {
    const pretasacionDesde = ($('#pretasacion_desde').val() || '').toString().trim();
    const pretasacionHasta = ($('#pretasacion_hasta').val() || '').toString().trim();
    const tasacionFinal = ($('#tasacion_final').val() || '').toString().trim();

    if (!idAgenda || idAgenda <= 0) {
        $('#tasacion_manual_estado').css('color', '#a94442').text('No se encontró la agenda para guardar la tasación.');
        return;
    }
    if (!pretasacionDesde || !pretasacionHasta || !tasacionFinal) {
        $('#tasacion_manual_estado').css('color', '#a94442').text('Completá pre tasación desde, pre tasación hasta y tasación final.');
        return;
    }

    $('#tasacion_manual_estado').css('color', '#333').text('Guardando tasación...');

    $.post('/adm/modulos/age/ajax_guardar_tasacion.php', {
        id_agenda: idAgenda,
        pretasacion_desde: pretasacionDesde,
        pretasacion_hasta: pretasacionHasta,
        tasacion_final: tasacionFinal
    }, function(resp) {
        if (resp && resp.ok) {
            $('#tasacion_manual_estado').css('color', '#468847').text('Tasación manual guardada correctamente.');
        } else {
            $('#tasacion_manual_estado').css('color', '#a94442').text(resp?.mensaje || 'No se pudo guardar la tasación manual.');
        }
    }, 'json').fail(function() {
        $('#tasacion_manual_estado').css('color', '#a94442').text('Error al guardar la tasación manual.');
    });
}

$(function() {
    actualizarSeleccionados();
    cargarCalendario();
    $('#modal_agendar_bg').on('click', cerrarModalCotizador);
});

function calcularEstadoCotizacion($row) {

    $desde = isset($row['tasacion_desde']) ? $row['tasacion_desde'] : null;
    $hasta = isset($row['tasacion_hasta']) ? $row['tasacion_hasta'] : null;
    $final = isset($row['tasacion_final']) ? $row['tasacion_final'] : null;

    // Detectar comparables (ajustar según tu campo real)
    $tieneComparables = true;
    if (isset($row['cantidad_comparables'])) {
        $tieneComparables = intval($row['cantidad_comparables']) > 0;
    }

    // 1. No cotizó
    if (!$tieneComparables) {
        return 'NO_COTIZO';
    }

    // 2. Finalizada
    if (!empty($final) && floatval($final) > 0) {
        return 'FINALIZADA';
    }

    // 3. Preliminar
    if (!empty($desde) && !empty($hasta)) {
        return 'COTIZADO_PRELIMINAR';
    }

    return 'NO_COTIZO';
}
</script>

<?php } else { ?>
    <?php if ($busqueda != '') { ?>
        <div class="info_resultado">
            <div class="tc">No se encontraron elementos con <strong>"<?php echo_s($busqueda); ?>"</strong>.</div>
            <div class="tc"><a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($orden_campo != 0) echo '&o=' . $orden_campo; ?>&od=<?php echo $orden_dir; ?><?php if ($inactivo != 0) echo '&e=' . $inactivo; ?>">Ver todos</a></div>
        </div>
    <?php } else { ?>
        <div class="info_resultado"><div class="tc">No hay elementos para listar.</div></div>
    <?php } ?>
<?php } ?>

<?php require_once('sistema_post_contenido.php'); ?>
