<?php

// ***************************************************************************************************
// ***********************************   ÁLBUMES DEL ARTISTAS  ***********************************
// ***************************************************************************************************



// ***************************************************************************************************
// Consulta
// ***************************************************************************************************
$listado = $db->query('SELECT SQL_CALC_FOUND_ROWS * FROM variables_usd WHERE tipo = 18'. $sql_b . ' order by ' . $sql_o . ' ' . $sql_od . ';');

$qry = $db->query_first('select FOUND_ROWS() as cantidad;');
$total = $qry['cantidad'];

$total_paginas = ceil($total / $config['pagina_cant']);

?>

<div class="row">

    <h4 style="margin: 20px">Limpieza de Tapizado</h4>
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
                        Limpieza 
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
                    <div class="info_seleccionados"><span id="cantidad_seleccionados"></span><?php if ($_SESSION[$config['codigo_unico']]['login_permisos'][$modulo['prefijo']] > 1) { ?> - <input type="button" class="btn btn-danger btn-small" value="Eliminar" onclick="eliminar();" /><?php } ?></div>
                    <div class="info_seleccionados_des" hidden><span id="cantidad_seleccionados_des"></span><?php if ($_SESSION[$config['codigo_unico']]['login_permisos'][$modulo['prefijo']] > 1) { ?> - <input type="button" class="btn btn-success btn-small" value="Destacar" onclick="destacar();" /><?php } ?></div>
                    <?php
                    if ($total_paginas > 1) {
                    ?>
                        <div class="paginas">
                            <?php
                            if ($pagina > 1) {
                            ?>
                                <a href="?m=<?php echo $modulo['prefijo']; ?>_l&p=<?php echo $pagina - 1; ?><?php if ($busqueda != '') {
                                                                                                                echo '&b=' . $busqueda;
                                                                                                            } ?><?php if ($orden_campo != 0) {
                                                                                                                    echo '&o=' . $orden_campo;
                                                                                                                } ?><?php if ($orden_dir != 0) {
                                                                                                                        echo '&od=' . $orden_dir;
                                                                                                                    } ?><?php if ($inactivo != 0) {
                                                                                                                            echo '&e=' . $inactivo;
                                                                                                                        } ?>">
                                    < anterior</a> <?php
                                                }
                                                    ?> <select id="select_pagina" class="input-mini">
                                        <?php
                                        for ($i = 1; $i <= $total_paginas; $i++) {
                                        ?>
                                            <option value="<?php echo $i; ?>" <?php if ($i == $pagina) {
                                                                                    echo 'selected="selected"';
                                                                                } ?>><?php echo $i; ?></option>
                                        <?php
                                        }
                                        ?>
                                    </select> / <?php echo $total_paginas; ?>
                                    <?php
                                    if ($pagina < $total_paginas) {
                                    ?>
                                        <a href="?m=<?php echo $modulo['prefijo']; ?>_l&p=<?php echo $pagina + 1; ?><?php if ($busqueda != '') {
                                                                                                                        echo '&b=' . $busqueda;
                                                                                                                    } ?><?php if ($orden_campo != 0) {
                                                                                                                            echo '&o=' . $orden_campo;
                                                                                                                        } ?><?php if ($orden_dir != 0) {
                                                                                                                                echo '&od=' . $orden_dir;
                                                                                                                            } ?><?php if ($inactivo != 0) {
                                                                                                                                    echo '&e=' . $inactivo;
                                                                                                                                } ?>">siguiente ></a>
                                    <?php
                                    }
                                    ?>
                        </div>
                    <?php
                    }
                    ?>
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
                            <?php echo_s($entrada['tapizado']); ?>
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