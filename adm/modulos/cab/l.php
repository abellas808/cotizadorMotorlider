<?php

if (!isset($sistema_iniciado)) exit();

function ca_tel_formateado($telefono) {
    $telefono = trim((string)$telefono);
    $telefono = str_replace('whatsapp:+598', '0', $telefono);
    $telefono = str_replace('+598', '0', $telefono);
    $telefono = str_replace('whatsapp:', '', $telefono);
    return $telefono;
}

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
    ORDER BY ca.fecha_respuesta DESC, ca.id DESC
");

require_once('sistema_cabezal.php');
require_once('sistema_pre_contenido.php');

?>

<div id="contenido_cabezal">
    <h4 class="titulo"><?php echo_s($modulo['nombre']); ?></h4>
    <hr class="nb">
</div>

<div class="sep_titulo"></div>

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
                    <?php
                    if (!empty($row['motivo_abandono'])) {
                        echo_s($row['motivo_abandono']);
                    } elseif (!empty($row['origen_abandono'])) {
                        echo_s($row['origen_abandono']);
                    } else {
                        echo_s('-');
                    }
                    ?>
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