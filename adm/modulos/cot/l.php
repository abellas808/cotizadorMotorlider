<?php
// ***************************************************************************************************
// Chequeo que no se llame directamente
// ***************************************************************************************************
if (!isset($sistema_iniciado)) exit();

// ***************************************************************************************************
// Helpers
// ***************************************************************************************************
if (!function_exists('cot_pick_first')) {
    function cot_pick_first($row, $keys, $default = '') {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
                return $row[$key];
            }
        }
        return $default;
    }
}

if (!function_exists('cot_format_fecha')) {
    function cot_format_fecha($valor, $default = '-') {
        if (!isset($valor) || trim((string)$valor) === '' || $valor === '0000-00-00' || $valor === '0000-00-00 00:00:00') return $default;
        $ts = strtotime($valor);
        if ($ts === false) return $default;
        return strftime('%d/%m/%Y', $ts);
    }
}

if (!function_exists('cot_format_hora')) {
    function cot_format_hora($valor, $default = '-') {
        if (!isset($valor) || trim((string)$valor) === '') return $default;
        $valor = trim((string)$valor);
        $ts = strtotime($valor);
        if ($ts !== false) return date('H:i', $ts);
        if (strlen($valor) >= 5) return substr($valor, 0, 5);
        return $valor;
    }
}

if (!function_exists('cot_format_numero')) {
    function cot_format_numero($valor, $default = '-') {
        if (!isset($valor) || trim((string)$valor) === '') return $default;
        if (!is_numeric($valor)) return $valor;
        return number_format((float)$valor, 0, ',', '.');
    }
}

if (!function_exists('cot_format_tasacion')) {
    function cot_format_tasacion($valor, $default = '-') {
        if (!isset($valor) || trim((string)$valor) === '') return $default;
        if (!is_numeric($valor)) return $valor;
        return '$ ' . number_format((float)$valor, 0, ',', '.');
    }
}

if (!function_exists('cot_badge')) {
    function cot_badge($texto, $tipo = 'gris') {
        $colores = array(
            'gris'     => array('fg' => '#5f6368', 'bg' => '#eef0f2'),
            'verde'    => array('fg' => '#155724', 'bg' => '#d4edda'),
            'amarillo' => array('fg' => '#856404', 'bg' => '#fff3cd'),
            'rojo'     => array('fg' => '#721c24', 'bg' => '#f8d7da'),
            'azul'     => array('fg' => '#0c5460', 'bg' => '#d1ecf1'),
            'naranja'  => array('fg' => '#8a4b08', 'bg' => '#fde7cf')
        );

        if (!isset($colores[$tipo])) $tipo = 'gris';
        $c = $colores[$tipo];

        return '<span title="' . htmlspecialchars($texto) . '" 
        style="
        display:inline-block;
        padding:2px 6px;
        border-radius:4px;
        font-size:10px;
        font-weight:bold;
        line-height:1.2;
        color:' . $c['fg'] . ';
        background:' . $c['bg'] . ';
        white-space:normal;
        word-break:normal;
        overflow-wrap:normal;
        max-width:none;
        min-width:72px;
        text-align:center;
        ">
        ' . htmlspecialchars($texto) . '
        </span>';
    }
}

if (!function_exists('cot_estado_badge_db')) {
    function cot_estado_badge_db($entrada) {
        $texto = trim((string)($entrada['estado_nombre'] ?? ''));
        $color = trim((string)($entrada['estado_color'] ?? ''));

        if ($texto === '') {
            $texto = cot_estado_cotizacion_badge($entrada);
        }

        if ($color === '') {
            $color = '#eef0f2';
        }

        return '<span title="' . htmlspecialchars($texto) . '" style="
            display:inline-block;
            padding:3px 7px;
            border-radius:4px;
            font-size:10px;
            font-weight:bold;
            line-height:1.2;
            color:#fff;
            background:' . htmlspecialchars($color) . ';
            white-space:normal;
            word-break:normal;
            overflow-wrap:normal;
            max-width:none;
            min-width:76px;
            text-align:center;
        ">' . htmlspecialchars($texto) . '</span>';
    }
}

if (!function_exists('cot_estado_cotizacion_badge')) {
    function cot_estado_cotizacion_badge($entrada) {
        // Lógica corregida basada en estado_id
        $estado_id = isset($entrada['estado_id']) ? intval($entrada['estado_id']) : 1;

        switch ($estado_id) {
			case 1:
				return 'NO COTIZÓ';
			case 2:
				return 'PENDIENTE';
			case 3:
				return 'COT. PRELIMINAR';
			case 4:
				return 'FINALIZADO';
			case 5:
				return 'RECHAZADO';
			case 6:
				return 'COMUNICARSE CON CLIENTE';
			case 7:
                return 'CLIENTE AVANZÓ';
			case 8:
                return 'AGENDADO';
			case 9:
                return 'AVANZÓ';
			case 10:
                return 'INSPECIÓN REALIZADA';
			case 11:
                return 'COTIZACIÓN FINAL';
			default:
				return 'NO COTIZÓ';
		}
    }
}

if (!function_exists('cot_json_extraer_texto')) {
    function cot_json_extraer_texto($json, $clave) {
        if (!is_string($json) || trim($json) === '') return '';
        $data = @json_decode($json, true);
        if (is_array($data) && isset($data[$clave])) {
            return trim((string)$data[$clave]);
        }
        return '';
    }
}

if (!function_exists('cot_limpiar_texto')) {
    function cot_limpiar_texto($texto) {
        $texto = trim((string)$texto);
        if ($texto === '') return '';
        $texto = str_replace(array("\r", "\n", "\t"), ' ', $texto);
        $texto = preg_replace('/\s+/', ' ', $texto);
        return trim($texto);
    }
}

if (!function_exists('cot_detectar_respuesta_cliente')) {
    function cot_detectar_respuesta_cliente($entrada) {
        $partes = array();
        $partes[] = cot_pick_first($entrada, array('wa_ult_respuesta_mensaje'), '');
        $partes[] = cot_pick_first($entrada, array('wa_ult_respuesta_observaciones'), '');
        $partes[] = cot_pick_first($entrada, array('wa_ult_respuesta_api'), '');
        $partes[] = cot_json_extraer_texto(cot_pick_first($entrada, array('wa_ult_respuesta_api'), ''), 'mensaje_cliente');

        $texto = strtoupper(cot_limpiar_texto(implode(' | ', $partes)));
        if ($texto === '') return '';

        $texto = str_replace('SÍ', 'SI', $texto);
        $texto = str_replace('CANCELACIÓN', 'CANCELACION', $texto);

        if (strpos($texto, 'CANCELACION_CLIENTE') !== false) return 'CANCELADO';
        if (strpos($texto, 'CLIENTE CANCELO') !== false) return 'CANCELADO';
        if (strpos($texto, 'RESPONDIENDO NO') !== false) return 'NO';
        if (preg_match('/\bCANCEL(O|Ó)\b/', $texto)) return 'CANCELADO';
        if (preg_match('/\bNO\b/', $texto) && strpos($texto, 'NO RESPONDE') === false) return 'NO';
        if (preg_match('/\bSI\b/', $texto)) return 'SI';

        return '';
    }
}

if (!function_exists('cot_estado_agenda_badge')) {
    function cot_estado_agenda_badge($entrada) {
        if (intval(cot_pick_first($entrada, array('agenda_id_agenda'), 0)) <= 0) {
            return cot_badge('SIN AGENDA', 'gris');
        }

        $cancelado = intval(cot_pick_first($entrada, array('agenda_cancelado'), 0));
        $finalizada = intval(cot_pick_first($entrada, array('agenda_finalizada'), 0));
        $respuestaTipo = strtoupper(trim((string)cot_pick_first($entrada, array('wa_ult_respuesta_tipo'), '')));
        $respuestaCliente = cot_detectar_respuesta_cliente($entrada);

        if ($respuestaTipo === 'CANCELACION_CLIENTE' || $respuestaCliente === 'CANCELADO') {
            return cot_badge('CANC. CLIENTE', 'rojo');
        }
        if ($cancelado === 1) {
            return cot_badge('CANCELADA', 'rojo');
        }
        if ($finalizada === 1) {
            return cot_badge('FINALIZADA', 'verde');
        }
        return cot_badge('ACTIVA', 'azul');
    }
}

if (!function_exists('cot_confirmacion_badge')) {
    function cot_confirmacion_badge($entrada) {
        if (intval(cot_pick_first($entrada, array('agenda_id_agenda'), 0)) <= 0) {
            return '-';
        }

        $cancelado = intval(cot_pick_first($entrada, array('agenda_cancelado'), 0));
        $finalizada = intval(cot_pick_first($entrada, array('agenda_finalizada'), 0));
        $ultConfirmTipo = strtoupper(trim((string)cot_pick_first($entrada, array('wa_ult_confirm_tipo'), '')));
        $ultConfirmEstado = strtoupper(trim((string)cot_pick_first($entrada, array('wa_ult_confirm_estado'), '')));
        $ultRespuestaTipo = strtoupper(trim((string)cot_pick_first($entrada, array('wa_ult_respuesta_tipo'), '')));
        $respuestaCliente = cot_detectar_respuesta_cliente($entrada);

        if ($ultRespuestaTipo === 'CANCELACION_CLIENTE' || $respuestaCliente === 'CANCELADO') {
            return cot_badge('CANCELADO', 'rojo');
        }
        if ($cancelado === 0 && $finalizada === 0 && $ultConfirmTipo !== '' && $respuestaCliente === 'SI') {
            return cot_badge('CONFIRMADO', 'verde');
        }
        if ($respuestaCliente === 'NO') {
            return cot_badge('RESP. NO', 'naranja');
        }
        if ($ultConfirmTipo !== '') {
            if ($ultConfirmEstado === 'ENVIADO' || $ultConfirmEstado === 'RECIBIDO' || $ultConfirmEstado === 'INFO') {
                return cot_badge('PTE RESP.', 'amarillo');
            }
            return cot_badge($ultConfirmEstado !== '' ? $ultConfirmEstado : 'EN PROCESO', 'amarillo');
        }
        if ($cancelado === 0 && $finalizada === 0) {
            return cot_badge('SIN ENVÍO', 'gris');
        }
        return '-';
    }
}

if (!function_exists('cot_table_columns')) {
    function cot_table_columns($table) {
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

if (!function_exists('cot_sql_first_existing')) {
    function cot_sql_first_existing($aliasTabla, $table, $candidatas, $defaultSql = "''") {
        $cols = cot_table_columns($table);
        foreach ($candidatas as $col) {
            if (isset($cols[$col])) {
                return $aliasTabla . '.`' . $col . '`';
            }
        }
        return $defaultSql;
    }
}

if (!function_exists('cot_qs_base')) {
    function cot_qs_base($extra = array()) {
        global $modulo, $busqueda, $fecha_desde, $fecha_hasta, $estado_cot, $estado_age, $orden_campo, $orden_dir, $inactivo, $idcot, $telefono_filtro;
        $params = array(
            'm' => $modulo['prefijo'] . '_l'
        );
        if (!empty($idcot)) $params['idcot'] = $idcot;
        if (!empty($telefono_filtro)) $params['telcot'] = $telefono_filtro;
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

if (!function_exists('cot_sort_link')) {
    function cot_sort_link($campo, $label) {
        global $orden_campo, $orden_dir, $od_chr;
        if ($orden_campo == $campo) {
            return '<a href="' . cot_qs_base(array('o' => $campo, 'od' => $orden_dir == 0 ? 1 : 0)) . '"><strong>' . $label . ' ' . $od_chr . '</strong></a>';
        }
        return '<a href="' . cot_qs_base(array('o' => $campo)) . '">' . $label . ' ▼</a>';
    }
}

// ***************************************************************************************************
// Paginado
// ***************************************************************************************************
$pagina = isset($_GET['p']) ? intval($_GET['p']) : 1;
if ($pagina <= 0) $pagina = 1;

// ***************************************************************************************************
// Busqueda y filtros
// ***************************************************************************************************
$busqueda = isset($_GET['b']) ? substr(trim($_GET['b']), 0, 50) : '';
$fecha_desde = isset($_GET['fd']) ? trim($_GET['fd']) : date('Y-m-01');
$fecha_hasta = isset($_GET['fh']) ? trim($_GET['fh']) : date('Y-m-d', strtotime('+30 days'));
$estado_cot = isset($_GET['ecot']) ? trim($_GET['ecot']) : '';
$estado_age = isset($_GET['eage']) ? trim($_GET['eage']) : '';
$idcot = isset($_GET['idcot']) ? intval($_GET['idcot']) : 0;
$telefono_filtro = isset($_GET['telcot']) ? trim($_GET['telcot']) : '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde)) $fecha_desde = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta)) $fecha_hasta = date('Y-m-d');
if ($fecha_desde > $fecha_hasta) {
    $tmp = $fecha_desde;
    $fecha_desde = $fecha_hasta;
    $fecha_hasta = $tmp;
}

$sql_b = '';
if ($busqueda != '') {
    $busqueda_array = explode(' ', $busqueda);
    for ($i = 0; $i < count($busqueda_array); $i++) {
        $term = trim($busqueda_array[$i]);
        if ($term == '') continue;
        $term = addslashes($term);
        $sql_b .= ' and (
            c.id_cotizaciones_generadas like "%' . $term . '%"
            or c.auto like "%' . $term . '%"
            or c.marca like "%' . $term . '%"
            or c.familia like "%' . $term . '%"
            or c.anio like "%' . $term . '%"
            or c.kilometros like "%' . $term . '%"
            or c.nombre like "%' . $term . '%"
            or c.telefono like "%' . $term . '%"
            or c.fecha like "%' . $term . '%"
            or c.estado like "%' . $term . '%"
            or a.id_agenda like "%' . $term . '%"
            or a.fecha like "%' . $term . '%"
            or a.hora like "%' . $term . '%"
        )';
    }
}

$sql_b = trim($sql_b, ' and ');
if ($sql_b != '') $sql_b = ' and ' . $sql_b;

$sql_filtros = '';
$sql_filtros .= " and DATE(c.fecha) >= '" . addslashes($fecha_desde) . "'";
$sql_filtros .= " and DATE(c.fecha) <= '" . addslashes($fecha_hasta) . "'";
if ($estado_cot !== '') {
    $sql_filtros .= " and c.estado = '" . addslashes($estado_cot) . "'";
}
if ($idcot > 0) {
    $sql_filtros .= " and c.id_cotizaciones_generadas = " . intval($idcot);
}
if ($telefono_filtro !== '') {
    $tel = preg_replace('/[^0-9]/', '', $telefono_filtro);

    if ($tel !== '') {
        $sql_filtros .= " and REPLACE(REPLACE(REPLACE(c.telefono, 'whatsapp:', ''), '+', ''), ' ', '') LIKE '%" . addslashes($tel) . "%'";
    }
}
if ($estado_age !== '') {
    switch ($estado_age) {
        case 'SIN_AGENDA':
            $sql_filtros .= ' and a.id_agenda is null';
            break;
        case 'ACTIVA':
            $sql_filtros .= " and a.id_agenda is not null and a.cancelado = 0 and a.finalizada = 0 and not exists (select 1 from whatsapp_agenda_notificaciones wx where wx.id_agenda = a.id_agenda and wx.tipo_notificacion = 'cancelacion_cliente')";
            break;
        case 'FINALIZADA':
            $sql_filtros .= ' and a.id_agenda is not null and a.finalizada = 1';
            break;
        case 'CANCELADA':
            $sql_filtros .= " and a.id_agenda is not null and a.cancelado = 1 and not exists (select 1 from whatsapp_agenda_notificaciones wx where wx.id_agenda = a.id_agenda and wx.tipo_notificacion = 'cancelacion_cliente')";
            break;
        case 'CANCELADA_CLIENTE':
            $sql_filtros .= " and a.id_agenda is not null and exists (select 1 from whatsapp_agenda_notificaciones wx where wx.id_agenda = a.id_agenda and wx.tipo_notificacion = 'cancelacion_cliente')";
            break;
    }
}

// ***************************************************************************************************
// Ordenado
// ***************************************************************************************************
$orden_campo = isset($_GET['o']) ? intval($_GET['o']) : 0;
$orden_dir = isset($_GET['od']) ? intval($_GET['od']) : 0;

switch ($orden_dir) {
    case 1:
        $sql_od = 'asc';
        $od_chr = '▲';
        break;
    default:
        $sql_od = 'desc';
        $od_chr = '▼';
}

switch ($orden_campo) {
    case 1:  $sql_o = 'c.auto'; break;
    case 2:  $sql_o = 'c.anio'; break;
    case 3:  $sql_o = 'c.kilometros'; break;
    case 4:  $sql_o = 'c.nombre'; break;
    case 5:  $sql_o = 'c.telefono'; break;
    case 6:  $sql_o = 'tasacion_desde_orden'; break;
    case 7:  $sql_o = 'tasacion_hasta_orden'; break;
    case 8:  $sql_o = 'tasacion_final_orden'; break;
    case 9:  $sql_o = 'c.fecha'; break;
    case 10: $sql_o = 'c.estado'; break;
    case 11: $sql_o = 'agenda_id_agenda'; break;
    case 12: $sql_o = 'agenda_fecha'; break;
    case 13: $sql_o = 'agenda_hora'; break;
    case 14: $sql_o = 'agenda_cancelado'; break;
    case 15: $sql_o = 'wa_ult_respuesta_fecha'; break;
    default:
        $sql_o = 'c.id_cotizaciones_generadas';
        $orden_campo = 0;
}

// ***************************************************************************************************
// Consulta
// ***************************************************************************************************
$expr_tas_desde = cot_sql_first_existing(
    'c',
    'cotizaciones_generadas',
    array(
        'pretasacion_desde',
        'pre_tasacion_desde',
        'tasacion_desde'
    ),
    'NULL'
);

$expr_tas_hasta = cot_sql_first_existing(
    'c',
    'cotizaciones_generadas',
    array(
        'pretasacion_hasta',
        'pre_tasacion_hasta',
        'tasacion_hasta'
    ),
    'NULL'
);

$expr_tas_final = cot_sql_first_existing('c', 'cotizaciones_generadas', array('tasacion_final', 'valor_final', 'precio_final', 'valor_publicado', 'valor_cotizado', 'cotizacion_final', 'promedio_ponderado'), 'NULL');

$sql_from = '
    FROM cotizaciones_generadas c
    LEFT JOIN cotizaciones_estados ce ON ce.id_estado = c.estado_id
    LEFT JOIN agendas a ON a.id_agenda = (
        SELECT ag.id_agenda
        FROM agendas ag
        WHERE ag.id_cotizacion = c.id_cotizaciones_generadas
        ORDER BY ag.id_agenda DESC
        LIMIT 1
    )
    WHERE 1=1
    ' . $sql_filtros . '
    ' . $sql_b . '
';

$sql_select = "
    SELECT SQL_CALC_FOUND_ROWS
        c.*,
        ce.nombre_estado AS estado_nombre,
        ce.color_label AS estado_color,
        {$expr_tas_desde} AS tasacion_desde,
        {$expr_tas_hasta} AS tasacion_hasta,
        {$expr_tas_final} AS tasacion_final,
        COALESCE({$expr_tas_desde}, 0) AS tasacion_desde_orden,
        COALESCE({$expr_tas_hasta}, 0) AS tasacion_hasta_orden,
        COALESCE({$expr_tas_final}, 0) AS tasacion_final_orden,
";

$listado = $db->query($sql_select . '
        a.id_agenda AS agenda_id_agenda,
        a.fecha AS agenda_fecha,
        a.hora AS agenda_hora,
        a.cancelado AS agenda_cancelado,
        a.finalizada AS agenda_finalizada,
        (
            SELECT w1.tipo_notificacion
            FROM whatsapp_agenda_notificaciones w1
            WHERE w1.id_agenda = a.id_agenda
              AND w1.tipo_notificacion IN ("confirmacion_24h", "confirmacion_48h")
            ORDER BY w1.id DESC
            LIMIT 1
        ) AS wa_ult_confirm_tipo,
        (
            SELECT w1.estado_envio
            FROM whatsapp_agenda_notificaciones w1
            WHERE w1.id_agenda = a.id_agenda
              AND w1.tipo_notificacion IN ("confirmacion_24h", "confirmacion_48h")
            ORDER BY w1.id DESC
            LIMIT 1
        ) AS wa_ult_confirm_estado,
        (
            SELECT w1.fecha_envio
            FROM whatsapp_agenda_notificaciones w1
            WHERE w1.id_agenda = a.id_agenda
              AND w1.tipo_notificacion IN ("confirmacion_24h", "confirmacion_48h")
            ORDER BY w1.id DESC
            LIMIT 1
        ) AS wa_ult_confirm_fecha,
        (
            SELECT w2.tipo_notificacion
            FROM whatsapp_agenda_notificaciones w2
            WHERE w2.id_agenda = a.id_agenda
              AND w2.tipo_notificacion IN ("respuesta_cliente", "cancelacion_cliente")
            ORDER BY w2.id DESC
            LIMIT 1
        ) AS wa_ult_respuesta_tipo,
        (
            SELECT w2.estado_envio
            FROM whatsapp_agenda_notificaciones w2
            WHERE w2.id_agenda = a.id_agenda
              AND w2.tipo_notificacion IN ("respuesta_cliente", "cancelacion_cliente")
            ORDER BY w2.id DESC
            LIMIT 1
        ) AS wa_ult_respuesta_estado,
        (
            SELECT w2.fecha_envio
            FROM whatsapp_agenda_notificaciones w2
            WHERE w2.id_agenda = a.id_agenda
              AND w2.tipo_notificacion IN ("respuesta_cliente", "cancelacion_cliente")
            ORDER BY w2.id DESC
            LIMIT 1
        ) AS wa_ult_respuesta_fecha,
        (
            SELECT w2.mensaje_enviado
            FROM whatsapp_agenda_notificaciones w2
            WHERE w2.id_agenda = a.id_agenda
              AND w2.tipo_notificacion IN ("respuesta_cliente", "cancelacion_cliente")
            ORDER BY w2.id DESC
            LIMIT 1
        ) AS wa_ult_respuesta_mensaje,
        (
            SELECT w2.observaciones
            FROM whatsapp_agenda_notificaciones w2
            WHERE w2.id_agenda = a.id_agenda
              AND w2.tipo_notificacion IN ("respuesta_cliente", "cancelacion_cliente")
            ORDER BY w2.id DESC
            LIMIT 1
        ) AS wa_ult_respuesta_observaciones,
        (
            SELECT w2.respuesta_api
            FROM whatsapp_agenda_notificaciones w2
            WHERE w2.id_agenda = a.id_agenda
              AND w2.tipo_notificacion IN ("respuesta_cliente", "cancelacion_cliente")
            ORDER BY w2.id DESC
            LIMIT 1
        ) AS wa_ult_respuesta_api
    ' . $sql_from . '
    ORDER BY ' . $sql_o . ' ' . $sql_od . '
    LIMIT ' . (($pagina - 1) * $config['pagina_cant']) . ', ' . $config['pagina_cant'] . ';
');

$qry = $db->query_first('SELECT FOUND_ROWS() AS cantidad;');
$total = intval($qry['cantidad']);
$total_paginas = ceil($total / $config['pagina_cant']);
$cant_cotizaciones = $db->query_first('SELECT COUNT(*) AS cant ' . $sql_from);
?>
<?php require_once('sistema_cabezal.php'); ?>
<?php require_once('sistema_pre_contenido.php'); ?>

<style>
    #contenido_cabezal {
        clear: both;
        display: block;
        position: relative;
        margin: 0 0 18px 0;
        padding: 0;
        z-index: 5;
        background: #fff;
    }

    .cot-top-search {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 6px;
        margin: 0 0 14px 0;
        clear: both;
        position: relative;
        z-index: 6;
        background: #fff;
    }

    #contenido_cabezal .titulo {
        clear: both;
        display: block;
        position: relative;
        margin: 0 0 14px 0;
        padding: 0;
        background: #fff;
        z-index: 6;
    }

    .cot-toolbar-wrap {
        clear: both;
        display: block;
        position: relative;
        background: #fff;
        padding: 0;
        margin: 0 0 14px 0;
        min-height: auto;
        z-index: 6;
    }

    .cot-filtros-bar {
        clear: both;
        display: flex;
        flex-wrap: wrap;
        gap: 8px 10px;
        align-items: flex-end;
        margin: 0 0 10px 0;
        padding: 12px;
        background: #f9f9f9;
        border: 1px solid #e5e5e5;
        border-radius: 4px;
    }

    .cot-filtro-item label {
        display: block;
        font-size: 11px;
        color: #666;
        margin-bottom: 2px;
    }

    .cot-filtro-item input,
    .cot-filtro-item select {
        height: 30px;
        padding: 4px 6px;
        font-size: 12px;
    }

    .cot-grid-wrap {
        overflow-x: visible;
        overflow-y: visible;
        margin-top: 18px !important;
        position: relative;
        z-index: 1;
        clear: both;
        max-width: 100%;
        padding-bottom: 6px;
    }

    .cot-grid-compact {
        width: 100%;
        table-layout: fixed;
        font-size: 10px;
    }

    .cot-grid-compact th,
    .cot-grid-compact td {
        padding: 5px 5px !important;
        vertical-align: middle !important;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.18;
    }

    .cot-grid-compact thead tr.group-row th {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .35px;
        padding-top: 6px !important;
        padding-bottom: 6px !important;
        border-bottom: 1px solid #d8dde3;
    }

    .cot-grid-compact thead tr.group-row th.group-cot {
        background: #eef5ff;
        color: #355b8c;
    }

    .cot-grid-compact thead tr.group-row th.group-age {
        background: #eef8f1;
        color: #2f6d45;
        border-left: 3px solid #d6e9dc;
    }

    .cot-grid-compact thead tr.cols-row th {
        font-size: 10px;
        color: #666;
        background: #fafafa;
    }

    .cot-grid-compact thead tr.cols-row th a {
        color: #3d6f99;
        text-decoration: none;
        display: inline-block;
        width: 100%;
    }

    .cot-grid-compact thead tr.cols-row th a:hover {
        text-decoration: underline;
    }

    .cot-grid-compact td.sep-age,
    .cot-grid-compact th.sep-age {
        border-left: 3px solid #dde6dd !important;
    }

    .cot-grid-compact tbody tr:hover td {
        background: #fcfcfc;
    }

    .cot-grid-compact td.cot-col-estado {
        white-space: normal !important;
        overflow: hidden !important;
        text-overflow: unset !important;
        line-height: 1.2;
    }

    .cot-grid-compact td.cot-col-auto {
        white-space: normal !important;
        overflow: visible !important;
        text-overflow: unset !important;
        line-height: 1.25;
    }

    .cot-grid-compact td.cot-col-estado span,
    .cot-grid-compact td.cot-col-conf span {
        display: block !important;
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
        box-sizing: border-box;
        word-break: normal !important;
        overflow-wrap: normal !important;
        white-space: normal !important;
    }

    .cot-col-cod { width: 42px; }
    .cot-col-anio { width: 36px; }
    .cot-col-km { width: 50px; }
    .cot-col-auto { width: 150px; }
    .cot-col-nombre { width: 72px; }
    .cot-col-tel { width: 64px; }
    .cot-col-money { width: 54px; }
    .cot-col-fecha { width: 56px; }
    .cot-col-estado { width: 112px; }
    .cot-col-agecod { width: 40px; }
    .cot-col-agehora { width: 48px; }
    .cot-col-conf { width: 82px; }
    .cot-col-money-strong { font-weight: 700; }

    .cot-filtros-bar {
        max-width: 100%;
        overflow: hidden;
    }

    .cot-filtro-item input,
    .cot-filtro-item select {
        max-width: 180px;
    }

    @media (max-width: 1200px) {
        .cot-top-search {
            justify-content: flex-start;
        }

        .cot-filtro-item input,
        .cot-filtro-item select {
            max-width: 155px;
        }
    }
</style>

<script>
    function cotIrListado(pagina){
        var url = '?m=<?php echo $modulo['prefijo']; ?>_l';
        if (pagina && pagina > 0) url += '&p=' + pagina;
        url += '&idcot=' + encodeURIComponent($('#idcot').val());
        url += '&telcot=' + encodeURIComponent($('#telcot').val());
        url += '&fd=' + encodeURIComponent($('#fd').val());
        url += '&fh=' + encodeURIComponent($('#fh').val());
        url += '&ecot=' + encodeURIComponent($('#ecot').val());
        url += '&eage=' + encodeURIComponent($('#eage').val());
        url += '&b=' + encodeURIComponent($('#b').val());
        <?php if ($orden_campo != 0) { ?>url += '&o=<?php echo $orden_campo; ?>';<?php } ?>
        <?php if ($orden_dir != 0) { ?>url += '&od=<?php echo $orden_dir; ?>';<?php } ?>
        <?php if (isset($inactivo) && $inactivo != 0) { ?>url += '&e=<?php echo $inactivo; ?>';<?php } ?>
        window.location.href = url;
    }
</script>

<div id="contenido_cabezal">
    <div class="cot-top-search">
        <input type="text" id="b" onkeypress="if (event.keyCode == 13) { cotIrListado(1); }" value="<?php echo_s($busqueda); ?>" maxlength="50" />
        <?php if ($busqueda != '') { ?>
            <button type="button" class="btn btn-default btn-small btn_cerrar" onclick="$('#b').val(''); cotIrListado(1);">X</button>
        <?php } ?>
        <button type="button" class="btn btn-default btn-small" onclick="cotIrListado(1);">Buscar</button>
    </div>

    <h4 class="titulo"><?php echo $modulo['nombre']; ?></h4>

    <div class="cot-toolbar-wrap">
        <div class="cot-filtros-bar">
            <div class="cot-filtro-item">
                <label for="idcot">ID cotización</label>
                <input type="number" id="idcot" value="<?php echo isset($_GET['idcot']) ? intval($_GET['idcot']) : ''; ?>">
            </div>
            <div class="cot-filtro-item">
                <label for="telcot">Teléfono</label>
                <input type="text" id="telcot" value="<?php echo isset($_GET['telcot']) ? htmlspecialchars($_GET['telcot']) : ''; ?>">
            </div>
            <div class="cot-filtro-item">
                <label for="fd">Fecha desde</label>
                <input type="date" id="fd" value="<?php echo_s($fecha_desde); ?>">
            </div>
            <div class="cot-filtro-item">
                <label for="fh">Fecha hasta</label>
                <input type="date" id="fh" value="<?php echo_s($fecha_hasta); ?>">
            </div>
            <div class="cot-filtro-item">
                <label for="ecot">Estado cotización</label>
                <select id="ecot">
                    <option value="">Todos</option>
                    <option value="NO_COTIZO" <?php echo $estado_cot === 'NO_COTIZO' ? 'selected' : ''; ?>>NO COTIZÓ</option>
                    <option value="PENDIENTE" <?php echo $estado_cot === 'PENDIENTE' ? 'selected' : ''; ?>>PENDIENTE</option>
                    <option value="COT_PRELIMINAR" <?php echo $estado_cot === 'COT_PRELIMINAR' ? 'selected' : ''; ?>>COT. PRELIMINAR</option>
                    <option value="FINALIZADO" <?php echo $estado_cot === 'FINALIZADO' ? 'selected' : ''; ?>>FINALIZADO</option>
                </select>
            </div>
            <div class="cot-filtro-item">
                <label for="eage">Estado agenda</label>
                <select id="eage">
                    <option value="">Todos</option>
                    <option value="SIN_AGENDA" <?php echo $estado_age === 'SIN_AGENDA' ? 'selected' : ''; ?>>Sin agenda</option>
                    <option value="ACTIVA" <?php echo $estado_age === 'ACTIVA' ? 'selected' : ''; ?>>Activa</option>
                    <option value="FINALIZADA" <?php echo $estado_age === 'FINALIZADA' ? 'selected' : ''; ?>>Finalizada</option>
                    <option value="CANCELADA" <?php echo $estado_age === 'CANCELADA' ? 'selected' : ''; ?>>Cancelada</option>
                    <option value="CANCELADA_CLIENTE" <?php echo $estado_age === 'CANCELADA_CLIENTE' ? 'selected' : ''; ?>>Cancelada cliente</option>
                </select>
            </div>
            <div class="cot-filtro-item">
                <button type="button" class="btn btn-default btn-small" onclick="cotIrListado(1);">Aplicar</button>
                <button type="button" class="btn btn-default btn-small" onclick="window.location.href='?m=<?php echo $modulo['prefijo']; ?>_l';">Limpiar</button>
            </div>
        </div>

        <div style="margin:6px 0 0 0; clear:both; display:block;">
            <strong><?php echo intval($cant_cotizaciones['cant']); ?> Cotizaciones</strong>
        </div>
    </div>
</div>

<div class="sep_titulo" style="clear:both;height:10px;"></div>

<?php if ($total > 0) { ?>
    <?php if ($_SESSION[$config['codigo_unico']]['login_permisos']['mod'] > 1) { ?>
        <form id="form_listado" action="?m=<?php echo $modulo['prefijo'] . '_e'; ?>" method="post">
    <?php } ?>

    <div class="cot-grid-wrap">
        <table class="table table-hover cot-grid-compact">
            <thead>
                <tr class="group-row">
                    <th colspan="11" class="group-cot">Datos de la cotización</th>
                    <th colspan="5" class="group-age sep-age">Datos de la agenda</th>
                </tr>
                <tr class="cols-row">
                    <th class="cot-col-cod"><?php echo cot_sort_link(0, 'Cod'); ?></th>
                    <th class="cot-col-auto"><?php echo cot_sort_link(1, 'Auto'); ?></th>
                    <th class="cot-col-anio"><?php echo cot_sort_link(2, 'Año'); ?></th>
                    <th class="cot-col-km"><?php echo cot_sort_link(3, 'Km'); ?></th>
                    <th class="cot-col-nombre"><?php echo cot_sort_link(4, 'Nombre'); ?></th>
                    <th class="cot-col-tel"><?php echo cot_sort_link(5, 'Tel'); ?></th>
                    <th class="cot-col-money"><?php echo cot_sort_link(6, 'Desde'); ?></th>
                    <th class="cot-col-money"><?php echo cot_sort_link(7, 'Hasta'); ?></th>
                    <th class="cot-col-money"><?php echo cot_sort_link(8, 'Final'); ?></th>
                    <th class="cot-col-fecha"><?php echo cot_sort_link(9, 'Fecha'); ?></th>
                    <th class="cot-col-estado"><?php echo cot_sort_link(10, 'Estado'); ?></th>
                    <th class="cot-col-agecod sep-age"><?php echo cot_sort_link(11, 'Ag.'); ?></th>
                    <th class="cot-col-fecha"><?php echo cot_sort_link(12, 'Fec.'); ?></th>
                    <th class="cot-col-agehora"><?php echo cot_sort_link(13, 'Hora'); ?></th>
                    <th class="cot-col-estado"><?php echo cot_sort_link(14, 'Est.'); ?></th>
                    <th class="cot-col-conf"><?php echo cot_sort_link(15, 'Conf.'); ?></th>
                </tr>
            </thead>

            <tfoot>
                <tr>
                    <td height="30" colspan="16" valign="bottom">
                        <div class="info_seleccionados">
                            <span id="cantidad_seleccionados"></span>
                            <?php if ($_SESSION[$config['codigo_unico']]['login_permisos']['mod'] > 1) { ?>
                                - <input type="button" class="btn btn-danger btn-small" value="Eliminar" onclick="eliminar();" />
                            <?php } ?>
                        </div>

                        <div class="info_listados">Total: <strong><?php echo $total; ?></strong></div>

                        <?php if ($total_paginas > 1) { ?>
                            <div class="paginas">
                                <?php if ($pagina > 1) { ?>
                                    <a href="<?php echo cot_qs_base(array('p' => $pagina - 1, 'o' => $orden_campo != 0 ? $orden_campo : null, 'od' => $orden_dir != 0 ? $orden_dir : null)); ?>">< anterior</a>
                                <?php } ?>

                                <select id="select_pagina" class="input-mini">
                                    <?php for ($i = 1; $i <= $total_paginas; $i++) { ?>
                                        <option value="<?php echo $i; ?>" <?php if ($i == $pagina) echo 'selected="selected"'; ?>><?php echo $i; ?></option>
                                    <?php } ?>
                                </select> / <?php echo $total_paginas; ?>

                                <?php if ($pagina < $total_paginas) { ?>
                                    <a href="<?php echo cot_qs_base(array('p' => $pagina + 1, 'o' => $orden_campo != 0 ? $orden_campo : null, 'od' => $orden_dir != 0 ? $orden_dir : null)); ?>">siguiente ></a>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </td>
                </tr>
            </tfoot>

            <tbody>
                <?php while ($entrada = $db->fetch_array($listado)) { ?>
                    <?php
                    $autoMostrar = trim((string)($entrada['auto'] ?? ''));
                    if ($autoMostrar === '') $autoMostrar = '-';
                    $tasDesde = isset($entrada['tasacion_desde']) ? $entrada['tasacion_desde'] : '';
                    $tasHasta = isset($entrada['tasacion_hasta']) ? $entrada['tasacion_hasta'] : '';
                    $tasFinal = isset($entrada['tasacion_final']) ? $entrada['tasacion_final'] : '';
                    ?>
                    <tr>
                        <td class="cot-col-cod"><a href="?m=<?php echo $modulo['prefijo']; ?>_v&i=<?php echo $entrada['id_cotizaciones_generadas']; ?>"><?php echo_s($entrada['id_cotizaciones_generadas']); ?></a></td>
                        <td class="cot-col-auto" title="<?php echo htmlspecialchars($autoMostrar); ?>"><?php echo_s($autoMostrar); ?></td>
                        <td class="cot-col-anio"><?php echo_s($entrada['anio']); ?></td>
                        <td class="cot-col-km"><?php echo_s(cot_format_numero($entrada['kilometros'])); ?></td>
                        <td class="cot-col-nombre" title="<?php echo htmlspecialchars((string)$entrada['nombre']); ?>"><?php echo_s($entrada['nombre']); ?></td>
                        <td class="cot-col-tel">
                            <?php
                            $telefono = trim((string)$entrada['telefono']);

                            $telefono = preg_replace('/^whatsapp:\+/', '', $telefono);

                            if (strpos($telefono, '598') === 0) {
                                $telefono = '0' . substr($telefono, 3);
                            }

                            echo_s($telefono);
                            ?>
                        </td>
                        <td class="cot-col-money cot-col-money-strong"><?php echo_s(cot_format_tasacion($tasDesde)); ?></td>
                        <td class="cot-col-money cot-col-money-strong"><?php echo_s(cot_format_tasacion($tasHasta)); ?></td>
                        <td class="cot-col-money" style="font-weight:bold;"><?php echo_s(cot_format_tasacion($tasFinal)); ?></td>
                        <td class="cot-col-fecha"><?php echo_s(cot_format_fecha($entrada['fecha'])); ?></td>
                        <td class="cot-col-estado"><?php echo cot_estado_badge_db($entrada); ?></td>
                        <td class="cot-col-agecod sep-age">
                            <?php if (intval(cot_pick_first($entrada, array('agenda_id_agenda'), 0)) > 0) { ?>
                                <a href="?m=age_v&i=<?php echo intval($entrada['agenda_id_agenda']); ?>"><?php echo intval($entrada['agenda_id_agenda']); ?></a>
                            <?php } else { echo '-'; } ?>
                        </td>
                        <td class="cot-col-fecha"><?php echo_s(cot_format_fecha(cot_pick_first($entrada, array('agenda_fecha'), ''))); ?></td>
                        <td class="cot-col-agehora"><?php echo_s(cot_format_hora(cot_pick_first($entrada, array('agenda_hora'), ''))); ?></td>
                        <td class="cot-col-estado" title="<?php echo strip_tags(cot_estado_agenda_badge($entrada)); ?>"><?php echo cot_estado_agenda_badge($entrada); ?></td>
                        <td class="cot-col-conf" title="<?php echo strip_tags(cot_confirmacion_badge($entrada)); ?>"><?php echo cot_confirmacion_badge($entrada); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <?php if ($_SESSION[$config['codigo_unico']]['login_permisos']['mod'] > 1) { ?>
        </form>
    <?php } ?>

    <script>
        $('input[name="e_sel[]"]').bind('click', function(e) {
            $(this).closest('tr').toggleClass('info');
            var t = $('tr.info').length;
            if (t > 0) {
                $('.info_seleccionados').show();
                t == 1 ? $('#cantidad_seleccionados').html('1 elemento seleccionado') : $('#cantidad_seleccionados').html(t + ' elementos seleccionados');
            } else {
                $('.info_seleccionados').hide();
            }
        });

        $('#select_pagina').bind('change', function(e) {
            window.location.href = '<?php echo cot_qs_base(array('p' => 'REEMPLAZAR', 'o' => $orden_campo != 0 ? $orden_campo : null, 'od' => $orden_dir != 0 ? $orden_dir : null)); ?>'.replace('REEMPLAZAR', $(this).val());
        });

        function eliminar() {
            if (confirm('¿Esta seguro que desea eliminar los elementos seleccionados?')) {
                $('#form_listado').submit();
            }
        }
</script>

<?php } else { ?>
    <?php if ($busqueda != '') { ?>
        <div class="info_resultado">
            <div class="tc">No se encontraron elementos con <strong>"<?php echo_s($busqueda); ?>"</strong>.</div>
            <div class="tc"><a href="<?php echo cot_qs_base(); ?>">Ver todos</a></div>
        </div>
    <?php } else { ?>
        <div class="info_resultado"><div class="tc">No hay elementos para listar.</div></div>
    <?php } ?>
<?php } ?>

<?php require_once('sistema_post_contenido.php'); ?>
