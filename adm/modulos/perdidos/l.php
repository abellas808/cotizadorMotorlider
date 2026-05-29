<?php

require_once('sistema_cabezal.php');
require_once('sistema_pre_contenido.php');

function perdidos_tel($telefono) {
    $telefono = trim((string)$telefono);
    $telefono = str_replace('whatsapp:+598', '0', $telefono);
    $telefono = str_replace('+598', '0', $telefono);
    $telefono = str_replace('whatsapp:', '', $telefono);
    return $telefono;
}

$where = "1=1";

if (!empty($_GET['estado'])) {
    $where .= " AND cca.estado = '" . $db->escape($_GET['estado']) . "'";
}

if (!empty($_GET['buscar'])) {
    $buscar = $db->escape(trim($_GET['buscar']));

    $where .= "
        AND (
            cca.telefono LIKE '%{$buscar}%'
            OR cca.nombre LIKE '%{$buscar}%'
            OR cca.marca LIKE '%{$buscar}%'
            OR cca.modelo LIKE '%{$buscar}%'
            OR cca.step_actual LIKE '%{$buscar}%'
            OR cca.ultimo_mensaje_cliente LIKE '%{$buscar}%'
            OR cca.ultima_respuesta_bot LIKE '%{$buscar}%'
        )
    ";
}

$listado = $db->query("
    SELECT
        cca.*,

        cli.mensaje AS ultimo_mensaje_cliente_real,
        cli.fecha AS fecha_ultimo_mensaje_cliente,

        bot.mensaje AS ultimo_mensaje_bot_real,
        bot.fecha AS fecha_ultimo_mensaje_bot

    FROM cotizador_conversaciones_abandonadas cca

    LEFT JOIN (
        SELECT m1.*
        FROM whatsapp_conversacion_mensajes m1
        INNER JOIN (
            SELECT id_conversacion, MAX(id) AS max_id
            FROM whatsapp_conversacion_mensajes
            WHERE direccion = 'ENTRANTE'
            GROUP BY id_conversacion
        ) x
            ON x.id_conversacion = m1.id_conversacion
           AND x.max_id = m1.id
    ) cli
        ON cli.id_conversacion = cca.id_conversacion

    LEFT JOIN (
        SELECT m2.*
        FROM whatsapp_conversacion_mensajes m2
        INNER JOIN (
            SELECT id_conversacion, MAX(id) AS max_id
            FROM whatsapp_conversacion_mensajes
            WHERE direccion = 'SALIENTE'
            GROUP BY id_conversacion
        ) y
            ON y.id_conversacion = m2.id_conversacion
           AND y.max_id = m2.id
    ) bot
        ON bot.id_conversacion = cca.id_conversacion

    WHERE {$where}
    ORDER BY cca.fecha_detectado DESC, cca.id DESC
");

?>

<div id="contenido_cabezal">
    <h4 class="titulo">Conversaciones perdidas</h4>
    <hr class="nb">
</div>

<div class="sep_titulo"></div>

<form method="get" style="margin-bottom:20px;">
    <input type="hidden" name="m" value="perdidos_l">

    <input
        type="text"
        name="buscar"
        placeholder="Buscar nombre, teléfono, marca, modelo..."
        value="<?php echo_s($_GET['buscar'] ?? ''); ?>"
        style="width:320px;"
    >

    <select name="estado">
        <option value="">Todos los estados</option>
        <option value="PENDIENTE" <?php echo (($_GET['estado'] ?? '') == 'PENDIENTE') ? 'selected' : ''; ?>>Pendiente</option>
        <option value="EN_GESTION" <?php echo (($_GET['estado'] ?? '') == 'EN_GESTION') ? 'selected' : ''; ?>>En gestión</option>
        <option value="CERRADO" <?php echo (($_GET['estado'] ?? '') == 'CERRADO') ? 'selected' : ''; ?>>Cerrado</option>
    </select>

    <button type="submit" class="btn">Buscar</button>
</form>

<div class="table-responsive">
<table class="table table-hover">
    <thead>
        <tr>
            <th>Nombre</th>
            <th>Vehículo</th>
            <th>Teléfono</th>
            <th>Último mensaje bot</th>
            <th>Último mensaje persona</th>
            <th>Estado conversación</th>
            <th>Gestión</th>
            <th></th>
        </tr>
    </thead>

    <tbody>
        <?php if ($listado && $db->num_rows > 0) { ?>

            <?php while ($row = $db->fetch_array($listado)) { ?>

                <?php
                $vehiculoPartes = [];

                if (!empty($row['marca'])) $vehiculoPartes[] = $row['marca'];
                if (!empty($row['modelo'])) $vehiculoPartes[] = $row['modelo'];
                if (!empty($row['anio'])) $vehiculoPartes[] = $row['anio'];
                if (!empty($row['kilometros']) && intval($row['kilometros']) > 0) {
                    $vehiculoPartes[] = number_format((int)$row['kilometros'], 0, ',', '.') . ' km';
                }

                $vehiculo = trim(implode(' ', $vehiculoPartes));

                $msgCliente = $row['ultimo_mensaje_cliente_real'] ?: $row['ultimo_mensaje_cliente'];
                $fechaCliente = $row['fecha_ultimo_mensaje_cliente'] ?: $row['fecha_ultima_interaccion'];

                $msgBot = $row['ultimo_mensaje_bot_real'] ?: $row['ultima_respuesta_bot'];
                $fechaBot = $row['fecha_ultimo_mensaje_bot'] ?: '';
                ?>

                <tr>
                    <td>
                <?php echo_s($row['nombre'] ?: '-'); ?>
                    </td>

                    <td>
                        <?php echo_s($vehiculo !== '' ? $vehiculo : '-'); ?>
                    </td>

                    <td>
                        <?php echo_s(perdidos_tel($row['telefono'])); ?>
                    </td>

                    <td style="max-width:320px;">
                        <strong>
                            <?php echo nl2br(htmlspecialchars((string)$msgBot)); ?>
                        </strong>

                        <?php if (!empty($fechaBot)) { ?>
                            <br>
                            <small style="font-size:11px;color:#777;">
                                <?php echo_s($fechaBot); ?>
                            </small>
                        <?php } ?>
                    </td>

                    <td style="max-width:260px;">
                        <strong>
                            <?php echo nl2br(htmlspecialchars((string)$msgCliente)); ?>
                        </strong>

                        <?php if (!empty($fechaCliente)) { ?>
                            <br>
                            <small style="font-size:11px;color:#777;">
                                <?php echo_s($fechaCliente); ?>
                            </small>
                        <?php } ?>
                    </td>

                    <td>
                        <strong>
                            <?php echo_s($row['step_actual'] ?: '-'); ?>
                        </strong>

                        <?php if (!empty($row['estado_conversacion'])) { ?>
                            <br>
                            <small style="font-size:11px;color:#777;">
                                <?php echo_s($row['estado_conversacion']); ?>
                            </small>
                        <?php } ?>
                    </td>

                    <td>
                        <?php echo_s($row['estado']); ?>
                    </td>

                    <td class="tc">
                        <a
                            href="?m=perdidos_v&i=<?php echo intval($row['id']); ?>"
                            class="btn btn-default"
                        >
                            Ver conversación
                        </a>
                    </td>
                </tr>

            <?php } ?>

        <?php } else { ?>
            <tr>
                <td colspan="8" class="tc">
                    No hay conversaciones perdidas registradas.
                </td>
            </tr>
        <?php } ?>
    </tbody>
</table>
</div>

<?php require_once('sistema_post_contenido.php'); ?>