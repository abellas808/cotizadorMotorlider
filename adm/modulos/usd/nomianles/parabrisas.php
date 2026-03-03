<?php

// ***************************************************************************************************
// ***********************************   ÁLBUMES DEL ARTISTAS  ***********************************
// ***************************************************************************************************



// ***************************************************************************************************
// Consulta
// ***************************************************************************************************
$listado = $db->query('SELECT SQL_CALC_FOUND_ROWS * FROM variables_usd WHERE tipo = 8'. $sql_b . ' order by ' . $sql_o . ' ' . $sql_od . ';');

$qry = $db->query_first('select FOUND_ROWS() as cantidad;');
$total = $qry['cantidad'];

$total_paginas = ceil($total / $config['pagina_cant']);

?>

<div class="row">

    <h4 style="margin: 20px">Cambiar parabrisas</h4>
    <hr>
    <table class="table table-hover" style="margin: 20px;width: 97%;">
        <thead>
            <tr>
               <?php
                    // ***************************************************************************************************
                    // Columnas / Cabezales
                    // ***************************************************************************************************
                    ?>
                    <th>
                        <?php
                        if ($orden_campo == 0) {
                        ?>
                            <a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') {
                                                                                echo '&b=' . $busqueda;
                                                                            } ?><?php if ($inactivo != 0) {
                                                                                    echo '&e=' . $inactivo;
                                                                                } ?>&o=0&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>"><strong>Código <?php echo $od_chr; ?></strong></a>
                        <?php
                        } else {
                        ?>
                            <a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') {
                                                                                echo '&b=' . $busqueda;
                                                                            } ?><?php if ($inactivo != 0) {
                                                                                    echo '&e=' . $inactivo;
                                                                                } ?>&o=0">Código</a>
                        <?php
                        }
                        ?>
                    </th>
                    <th>
                        Parabrisas
                    </th>
                    <th>
                        USD
                    </th>
                    <?php
                    // ***************************************************************************************************
                    ?>
                <th width="30"></th>
            </tr>

        </thead>
        <tfoot>
            <tr>
                <td height="30" colspan="5" valign="bottom">
                     
                </td>
            </tr>
        </tfoot>
        <tbody>
            <?php
            while ($entrada = $db->fetch_array($listado)) {
            ?>
                <tr>
                        <?php
                        // ***************************************************************************************************
                        // Columnas / Datos
                        // ***************************************************************************************************
                        ?>
                        <td>
                            <a href="?m=<?php echo $modulo['prefijo']; ?>_v&i=<?php echo $entrada['id']; ?>"><?php echo_s($entrada['id']); ?></a>
                        </td>
                        <td>
                            <?php echo_s($entrada['parabrisas']); ?>
                        </td>
                        <td>
                            <?php echo_s($entrada['usd']); ?>
                        </td>
                        <?php
                        // ***************************************************************************************************
                        ?>
                        <td><input name="e_sel[]" type="checkbox" value="<?php echo $entrada['id']; ?>" /></td>
                    </tr>
            <?php
            }
            ?>
        </tbody>
    </table>
</div>