<?php

if (!isset($sistema_iniciado)) exit();

$listado = $db->query("
    SELECT
        ca.*,
        cg.auto AS cot_auto,
        cg.nombre AS cot_nombre,
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
            <th>Tasación final</th>
            <th>Respuesta</th>
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
                <td><?php echo_s($row['telefono']); ?></td>
                <td><?php echo_s($row['cot_nombre']); ?></td>
                <td><?php echo_s($row['cot_auto']); ?></td>
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
            <td colspan="10" class="tc">
                No hay carritos abandonados.
            </td>
        </tr>

    <?php } ?>

    </tbody>
</table>

<?php require_once('sistema_post_contenido.php'); ?>