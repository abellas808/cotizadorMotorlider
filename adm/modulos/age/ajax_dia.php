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

global $db;

// por ahora fijo
$id_sucursal = 1;

$accion = trim((string)($_POST['accion'] ?? ''));
$fecha  = trim((string)($_POST['fecha'] ?? ''));
$year   = intval($_POST['year'] ?? 0);
$month  = intval($_POST['month'] ?? 0);

if ($accion === 'calendario') {

    if ($year <= 0 || $month <= 0 || $month > 12) {
        echo json_encode(['ok' => false, 'mensaje' => 'Parámetros inválidos']);
        exit;
    }

    $desde = sprintf('%04d-%02d-01', $year, $month);
    $hasta = date('Y-m-t', strtotime($desde));

    $bloqueados_completos = [];

    $q = $db->query("
        SELECT fecha
        FROM agenda_bloqueos
        WHERE id_sucursal = '{$id_sucursal}'
          AND activo = 1
          AND hora IS NULL
          AND fecha BETWEEN '{$desde}' AND '{$hasta}'
    ");

    if ($q) {
        while ($r = $db->fetch_array($q)) {
            $bloqueados_completos[] = $r['fecha'];
        }
    }

    echo json_encode([
        'ok' => true,
        'bloqueados_completos' => $bloqueados_completos
    ]);
    exit;
}

if ($accion === 'detalle') {

    if ($fecha === '') {
        echo json_encode(['ok' => false, 'mensaje' => 'Falta fecha']);
        exit;
    }

    setlocale(LC_ALL,"es_ES@euro","es_ES","esp");
    $date = DateTime::createFromFormat("Y-m-d", $fecha);
    $dia = strftime("%A", $date->getTimestamp());

    $dia_bloqueado = false;
    $horas_bloqueadas = [];

    $qBloqueos = $db->query("
        SELECT hora
        FROM agenda_bloqueos
        WHERE id_sucursal = '{$id_sucursal}'
          AND fecha = '".$db->escape($fecha)."'
          AND activo = 1
    ");

    if ($qBloqueos) {
        while ($b = $db->fetch_array($qBloqueos)) {
            if ($b['hora'] === null || $b['hora'] === '') {
                $dia_bloqueado = true;
            } else {
                $horas_bloqueadas[] = substr($b['hora'], 0, 5);
            }
        }
    }

    $horas_base = [];

    $qHoras = $db->query('
        SELECT hora_comienzo FROM agenda_particulares
        INNER JOIN agenda_horas_particulares
            ON agenda_particulares.id_particular = agenda_horas_particulares.id_particular
        WHERE agenda_particulares.id_sucursal = "'.$id_sucursal.'"
          AND agenda_particulares.fecha ="'.$db->escape($fecha).'"
          AND agenda_particulares.cancelado = 0

        UNION

        SELECT hora_comienzo
        FROM agenda_horas
        INNER JOIN agenda_estables
            ON agenda_horas.id_estables = agenda_estables.id_estable
        WHERE agenda_estables.dia = "'.utf8_encode($dia).'"
          AND agenda_estables.id_sucursal = "'.$id_sucursal.'"

        ORDER BY hora_comienzo ASC
    ');

    if ($qHoras) {
        while ($h = $db->fetch_array($qHoras)) {
            $hora = substr($h['hora_comienzo'], 0, 5);
            if (!in_array($hora, $horas_base)) {
                $horas_base[] = $hora;
            }
        }
    }

    $horas = [];

    foreach ($horas_base as $hora) {

        $qOcupada = $db->query("
            SELECT id_agenda
            FROM agendas
            WHERE id_sucursal = '{$id_sucursal}'
              AND fecha = '".$db->escape($fecha)."'
              AND hora = '".$db->escape($hora)."'
              AND (cancelado = 0 OR cancelado IS NULL)
            LIMIT 1
        ");
        $ocupada = $qOcupada ? $db->fetch_array($qOcupada) : null;

        $estado = 'disponible';

        if (in_array($hora, $horas_bloqueadas)) {
            $estado = 'bloqueada';
        } elseif ($ocupada) {
            $estado = 'ocupada';
        }

        $horas[] = [
            'hora' => $hora,
            'estado' => $estado,
            'id_agenda' => ($ocupada && isset($ocupada['id_agenda'])) ? $ocupada['id_agenda'] : null
        ];
    }

    echo json_encode([
        'ok' => true,
        'dia_bloqueado' => $dia_bloqueado,
        'horas' => $horas
    ]);
    exit;
}

echo json_encode(['ok' => false, 'mensaje' => 'Acción inválida']);