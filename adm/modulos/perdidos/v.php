
<?php

require_once('sistema_cabezal.php');
require_once('sistema_pre_contenido.php');

$id = intval($_GET['i'] ?? 0);

if ($id <= 0) {
    echo '<div class="alert alert-danger">ID inválido.</div>';
    require_once('sistema_post_contenido.php');
    exit;
}

$perdida = $db->query_first("
    SELECT *
    FROM cotizador_conversaciones_abandonadas
    WHERE id = " . intval($id) . "
    LIMIT 1
");

if (!$perdida) {
    echo '<div class="alert alert-danger">No se encontró la conversación perdida.</div>';
    require_once('sistema_post_contenido.php');
    exit;
}

$idConversacion = intval($perdida['id_conversacion']);

$mensajes = $db->query("
    SELECT *
    FROM whatsapp_conversacion_mensajes
    WHERE id_conversacion = " . intval($idConversacion) . "
    ORDER BY fecha ASC, id ASC
");

function perdidos_tel_v($telefono) {
    $telefono = trim((string)$telefono);
    $telefono = str_replace('whatsapp:+598', '0', $telefono);
    $telefono = str_replace('+598', '0', $telefono);
    $telefono = str_replace('whatsapp:', '', $telefono);
    return $telefono;
}

?>

<div id="contenido_cabezal">
    <h4 class="titulo">Conversación perdida</h4>
    <hr class="nb">
</div>

<div class="sep_titulo"></div>

<p>
    <a href="?m=perdidos_l" class="btn">Volver</a>
</p>

<div class="row">
    <div class="col-md-5">
        <div class="panel panel-default">
            <div class="panel-heading">
                <strong>Datos de la conversación</strong>
            </div>

            <div class="panel-body">
                <table class="table">
                    <tr>
                        <th>Nombre</th>
                        <td><?php echo_s($perdida['nombre'] ?: '-'); ?></td>
                    </tr>
                    <tr>
                        <th>Teléfono</th>
                        <td><?php echo_s(perdidos_tel_v($perdida['telefono'])); ?></td>
                    </tr>
                    <tr>
                        <th>Vehículo</th>
                        <td>
                            <?php
                            $vehiculo = trim(
                                (string)$perdida['marca'] . ' ' .
                                (string)$perdida['modelo'] . ' ' .
                                (string)$perdida['anio']
                            );
                            echo_s($vehiculo !== '' ? $vehiculo : '-');
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Kilómetros</th>
                        <td>
                            <?php
                            echo intval($perdida['kilometros']) > 0
                                ? number_format((int)$perdida['kilometros'], 0, ',', '.')
                                : '-';
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Estado conversación</th>
                        <td><?php echo_s($perdida['estado_conversacion'] ?: '-'); ?></td>
                    </tr>
                    <tr>
                        <th>Step</th>
                        <td><?php echo_s($perdida['step_actual'] ?: '-'); ?></td>
                    </tr>
                    <tr>
                        <th>Fecha detectado</th>
                        <td><?php echo_s($perdida['fecha_detectado']); ?></td>
                    </tr>
                    <tr>
                        <th>Estado gestión</th>
                        <td><?php echo_s($perdida['estado']); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="panel panel-default">
            <div class="panel-heading">
                <strong>Chat completo</strong>
            </div>

            <div class="panel-body" style="background:#f7f7f7; max-height:650px; overflow-y:auto;">

                <?php if ($mensajes && $db->num_rows > 0) { ?>

                    <?php while ($msg = $db->fetch_array($mensajes)) { ?>

                        <?php
                        $esCliente = strtoupper((string)$msg['direccion']) === 'ENTRANTE';
                        $align = $esCliente ? 'left' : 'right';
                        $bg = $esCliente ? '#ffffff' : '#dcf8c6';
                        $label = $esCliente ? 'Cliente' : ($msg['emisor'] ?: 'Bot');
                        ?>

                        <div style="text-align:<?php echo $align; ?>; margin-bottom:12px;">
                            <div style="
                                display:inline-block;
                                max-width:78%;
                                background:<?php echo $bg; ?>;
                                border:1px solid #ddd;
                                border-radius:8px;
                                padding:10px 12px;
                                text-align:left;
                                box-shadow:0 1px 2px rgba(0,0,0,0.08);
                            ">
                                <div style="font-size:12px; color:#555; margin-bottom:4px;">
                                    <strong><?php echo_s($label); ?></strong>
                                </div>

                                <div style="white-space:pre-wrap;">
                                    <?php echo htmlspecialchars((string)$msg['mensaje']); ?>
                                </div>

                                <div style="font-size:11px; color:#777; text-align:right; margin-top:6px;">
                                    <?php echo_s($msg['fecha']); ?>
                                </div>
                            </div>
                        </div>

                    <?php } ?>

                <?php } else { ?>

                    <div class="alert alert-info">
                        No hay mensajes registrados para esta conversación.
                    </div>

                <?php } ?>

            </div>
        </div>
    </div>
</div>

<?php require_once('sistema_post_contenido.php'); ?>