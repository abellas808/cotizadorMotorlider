<?php

if (!isset($sistema_iniciado)) exit();

function ca_tel_formateado($telefono) {
    $telefono = trim((string)$telefono);
    $telefono = str_replace('whatsapp:+598', '0', $telefono);
    $telefono = str_replace('+598', '0', $telefono);
    $telefono = str_replace('whatsapp:', '', $telefono);
    return $telefono;
}

function ca_badge_punto_abandono($punto) {

    $punto = trim((string)$punto);

    if ($punto === '') {
        return '<span>-</span>';
    }

    $clase = 'ca-badge-default';

    switch ($punto) {

        case 'NO_AGENDA_REVISION':
            $clase = 'ca-badge-fucsia';
            break;

        case 'NO_RESPONDE_PRETASACION':
        case 'NO_RESPONDE_PRETASACION_FINAL':
            $clase = 'ca-badge-amarillo';
            break;

        case 'NO_CONFIRMACION_AGENDA':
            $clase = 'ca-badge-rojo';
            break;

        case 'NO_CONFIRMA_AGENDA':
            $clase = 'ca-badge-turquesa';
            break;

        case 'NO_ASISTIO_AGENDA':
            $clase = 'ca-badge-rojo';
            break;

        case 'TASACION_FINAL_RECHAZADA':
            $clase = 'ca-badge-fucsia';
            break;

        case 'NO_RESPONDE_TASACION_FINAL':
            $clase = 'ca-badge-amarillo';
            break;
    }

    return '<span class="ca-badge ' . $clase . '">' . htmlspecialchars($punto, ENT_QUOTES, 'UTF-8') . '</span>';
}

function ca_normalizar_punto_abandono($punto, $origen = '') {
    $punto = strtoupper(trim((string)$punto));
    $origen = strtoupper(trim((string)$origen));

    $canonicos = [
        'NO_AGENDA_REVISION',
        'NO_RESPONDE_PRETASACION',
        'NO_CONFIRMACION_AGENDA',
        'NO_CONFIRMA_AGENDA',
        'NO_ASISTIO_AGENDA',
        'TASACION_FINAL_RECHAZADA',
        'NO_RESPONDE_TASACION_FINAL'
    ];

    if (in_array($punto, $canonicos, true)) {
        return $punto;
    }

    if ($punto === 'SIN_RESPUESTA_RECORDATORIO_24HS') {
        return 'NO_RESPONDE_PRETASACION';
    }

    if ($punto === 'NO_CONFIRMACION_AGENDA_AUTO') {
        return 'NO_CONFIRMACION_AGENDA';
    }

    if (in_array($punto, [
        'CANCELO_AGENDA_PENDIENTE_MOTIVO',
        'NO_RESPONDIO_CONFIRMACION_AGENDA',
        'NO_RESPONDE_RECORDATORIO_AGENDA'
    ], true)) {
        return 'NO_CONFIRMA_AGENDA';
    }

    if ($origen === 'TASACION_FINAL') {
        return 'TASACION_FINAL_RECHAZADA';
    }

    if ($origen === 'PRETASACION') {
        return 'NO_AGENDA_REVISION';
    }

    if ($origen === 'AGENDA') {
        return 'NO_CONFIRMA_AGENDA';
    }

    return $punto;
}

$buscar = trim((string)($_GET['buscar'] ?? ''));
$puntoAbandono = trim((string)($_GET['punto_abandono'] ?? ''));

$where = "1=1";

if ($buscar !== '') {
    $b = $db->escape($buscar);

    $where .= "
        AND (
            ca.id LIKE '%{$b}%'
            OR ca.id_cotizacion LIKE '%{$b}%'
            OR ca.telefono LIKE '%{$b}%'
            OR ca.nombre LIKE '%{$b}%'
            OR ca.email LIKE '%{$b}%'
            OR ca.marca LIKE '%{$b}%'
            OR ca.modelo LIKE '%{$b}%'
            OR ca.mensaje_cliente LIKE '%{$b}%'
            OR ca.motivo_abandono LIKE '%{$b}%'
            OR ca.origen_abandono LIKE '%{$b}%'
            OR ca.estado LIKE '%{$b}%'
            OR cg.auto LIKE '%{$b}%'
            OR cg.nombre LIKE '%{$b}%'
        )
    ";
}

$puntosAbandono = [
    'NO_AGENDA_REVISION',
    'NO_RESPONDE_PRETASACION',
    'NO_CONFIRMACION_AGENDA',
    'NO_CONFIRMA_AGENDA',
    'NO_ASISTIO_AGENDA',
    'TASACION_FINAL_RECHAZADA',
    'NO_RESPONDE_TASACION_FINAL'
];

$listado = $db->query("
    SELECT
        ca.*,
        cg.auto AS cot_auto,
        cg.nombre AS cot_nombre,
        cg.pretasacion_desde,
        cg.pretasacion_hasta,
        cg.tasacion_final AS cot_tasacion_final
    FROM carrito_abandonado ca
    LEFT JOIN cotizaciones_generadas cg
        ON cg.id_cotizaciones_generadas = ca.id_cotizacion
    WHERE {$where}
    ORDER BY ca.fecha_respuesta DESC, ca.id DESC
");

require_once('sistema_cabezal.php');
require_once('sistema_pre_contenido.php');

?>

<style>
    .ca-badge {
        display: inline-block;
        padding: 5px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: bold;
        line-height: 1.2;
        color: #fff;
        text-align: center;
        white-space: normal;
        max-width: 210px;
        word-break: break-word;
    }

    .ca-badge-fucsia {
        background: #e83e8c;
        color: #fff;
    }

    .ca-badge-amarillo {
        background: #ffc107;
        color: #333;
    }

    .ca-badge-rojo {
        background: #dc3545;
        color: #fff;
    }

    .ca-badge-turquesa {
        background: #20c997;
        color: #fff;
    }

    .ca-badge-default {
        background: #6c757d;
        color: #fff;
    }

    .ca-badge-naranja{
        background:#ff8c00;
        color:#fff;
    }
</style>

<div id="contenido_cabezal">
    <h4 class="titulo"><?php echo_s($modulo['nombre']); ?></h4>
    <hr class="nb">
</div>

<div class="sep_titulo"></div>

<form method="get" style="margin-bottom:20px;">
    <input type="hidden" name="m" value="cab_l">

    <input
        type="text"
        name="buscar"
        placeholder="Buscar ID, cotización, teléfono, cliente, vehículo, respuesta..."
        value="<?php echo_s($buscar); ?>"
        style="width:420px;"
    >

    <select name="punto_abandono" style="width:280px;">
        <option value="">Todos los puntos de abandono</option>

        <?php foreach ($puntosAbandono as $pa) { ?>
                <option
                    value="<?php echo_s($pa); ?>"
                    <?php echo ($puntoAbandono === $pa) ? 'selected' : ''; ?>
                >
                    <?php echo_s($pa); ?>
                </option>
        <?php } ?>
    </select>

    <button type="submit" class="btn">Buscar</button>

    <a href="?m=cab_l" class="btn">Limpiar</a>
</form>

<table class="table table-hover">
    <thead>
        <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>ID Cotización</th>
            <th>Teléfono</th>
            <th>Cliente</th>
            <th>Vehículo</th>
            <th>Pre tasación</th>
            <th>Tasación final</th>
            <th>Respuesta</th>
            <th>Punto abandono</th>
            <th>Estado</th>
            <th></th>
        </tr>
    </thead>
    <tbody>

    <?php if ($listado && $db->num_rows > 0) { ?>

        <?php while ($row = $db->fetch_array($listado)) { ?>

            <?php
            $puntoMostrar = '';

            if (!empty($row['motivo_abandono'])) {
                $puntoMostrar = ca_normalizar_punto_abandono(
                    $row['motivo_abandono'],
                    $row['origen_abandono'] ?? ''
                );
            } elseif (!empty($row['origen_abandono'])) {
                $puntoMostrar = $row['origen_abandono'];
            }

            if ($puntoAbandono !== '' && $puntoMostrar !== $puntoAbandono) {
                continue;
            }
            ?>

            <tr>
                <td><?php echo_s($row['id']); ?></td>
                <td><?php echo_s($row['fecha_respuesta']); ?></td>
                <td><?php echo_s($row['id_cotizacion']); ?></td>
                <td><?php echo_s(ca_tel_formateado($row['telefono'])); ?></td>
                <td><?php echo_s($row['cot_nombre']); ?></td>
                <td><?php echo_s($row['cot_auto']); ?></td>

                <td>
                    <?php
                    $desde = (float)($row['pretasacion_desde'] ?? 0);
                    $hasta = (float)($row['pretasacion_hasta'] ?? 0);

                    if ($desde > 0 || $hasta > 0) {
                        echo_s('USD ' . number_format($desde, 0, ',', '.') . ' a USD ' . number_format($hasta, 0, ',', '.'));
                    } else {
                        echo_s('-');
                    }
                    ?>
                </td>

                <td>
                    <?php
                    if ($row['cot_tasacion_final'] !== null && $row['cot_tasacion_final'] !== '') {
                        echo_s('USD ' . number_format((float)$row['cot_tasacion_final'], 0, ',', '.'));
                    } else {
                        echo_s('-');
                    }
                    ?>
                </td>

                <td><?php echo_s($row['mensaje_cliente']); ?></td>

                <td>
                    <?php echo ca_badge_punto_abandono($puntoMostrar); ?>
                </td>

                <td><?php echo_s($row['estado']); ?></td>

                <td>
                    <?php if (intval($row['id_cotizacion']) > 0) { ?>
                        <a class="btn btn-small" href="?m=cot_v&i=<?php echo intval($row['id_cotizacion']); ?>">
                            Ver cotización
                        </a>
                    <?php } ?>
                </td>
            </tr>

        <?php } ?>

    <?php } else { ?>

        <tr>
            <td colspan="12" class="tc">
                No hay carritos abandonados.
            </td>
        </tr>

    <?php } ?>

    </tbody>
</table>

<?php require_once('sistema_post_contenido.php'); ?>
