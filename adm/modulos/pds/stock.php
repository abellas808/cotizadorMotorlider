<?php ?>
<h5 style="margin: 65px 0 0 20px;" >Stock</h5>
<table style="margin-left:20px;" class="table table-hover" >
    <thead>
        <tr>
            <?php
            // ***************************************************************************************************
            // Columnas / Cabezales
            // ***************************************************************************************************
            ?>
            <th width="150">
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
            <th width="150">Marca</th>
            <th width="150">Modelo</th>
            <th width="150">Año</th>
            <th width="150">Version</th>
            <th width="150">Kilometros</th>
            <th>
                <?php
                if ($orden_campo == 1) {
                ?>
                    <a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') {
                        echo '&b=' . $busqueda;
                    } ?><?php if ($inactivo != 0) {
                        echo '&e=' . $inactivo;
                    } ?>&o=1&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>"><strong>Stock <?php echo $od_chr; ?></strong></a>
                <?php
                } else {
                ?>
                    <a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') {
                        echo '&b=' . $busqueda;
                    } ?><?php if ($inactivo != 0) {
                        echo '&e=' . $inactivo;
                    } ?>&o=1">Stock</a>
                <?php
                }
                ?>
            </th>
            <th width="30"></th>

            <?php
            ?>
        </tr>
    </thead>
    <tfoot>
        <tr>
            <td height="30" colspan="8" valign="bottom">
            <div class="info_seleccionados"><span id="cantidad_seleccionados"></span>- <input type="button" class="btn btn-danger btn-small" value="Eliminar" onclick="eliminar();" /></div>
            <div class="info_listados">Total: <strong><?php echo $total; ?></strong></div>
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
                        } ?>"> < anterior</a> <?php
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
                        <a href="?m=<?php echo $modulo['prefijo']; ?>_m&i=<?php echo $entrada['id_ponderador_valor_stock']; ?>&t=p"><?php echo echo_s($entrada['id_ponderador_valor_stock']); ?></a>
                    </td>
                    <td>
                        <?php echo $entrada['marca']; ?>
                    </td>
                    <td>
                        <?php echo $entrada['modelo']; ?>
                    </td>
                    <td>
                        <?php echo $entrada['anio']; ?>
                    </td>
                    <td>
                        <?php echo $entrada['version']; ?>
                    </td>
                    <td>
                        <?php echo number_format($entrada['kilometros'] , 0, ',', '.'); ?>
                    </td>
                    <td>
                        <?php echo $entrada['stock']; ?>
                    </td>
                    <?php
                    // ***************************************************************************************************
                    ?>
                    <td><input name="e_sel[]" type="checkbox" value="<?php echo $entrada['id_ponderador_valor_stock']; ?>" /></td>
                </tr>
            <?php
            }
            ?>
    </tbody>
</table>