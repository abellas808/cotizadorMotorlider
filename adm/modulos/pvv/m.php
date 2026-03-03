<?php
if (!isset($sistema_iniciado))
    exit();

if ($_SESSION[$config['codigo_unico']]['login_permisos'][$modulo['prefijo']] <= 1) {
    header('Location: ?m=' . $modulo['prefijo'] . '_l');
    exit();
}

$id = intval($_GET['i']);

$type = $_GET['t'];

if($type == 'p'){
    $elemento = $db->query_first('select * from ponderador_valor_venal where id_ponderador_valor_venal = "' . $id . '";');
} else if($type == 'n'){
    $elemento = $db->query_first('select * from ponderador_valor where id_ponderador_valor = "' . $id . '";');
} else if($type == 'm'){
    $elemento = $db->query_first('select * from ponderador_valor_maximo where id_valor_maximo = "' . $id . '";');
} else if($type == 'd'){
    $elemento = $db->query_first('select * from ponderador_valor_dolar where id_valor_dolar = "' . $id . '";');
}


if (!$elemento) {
    header('Location: ?m=' . $modulo['prefijo'] . '_l');
    exit();
}

?>
<?php require_once('sistema_cabezal.php'); ?>
<?php require_once('sistema_pre_contenido.php'); ?>

<link rel="stylesheet" type="text/css" href="/css/bootstrap.min.css">

<form id="form_datos" action="?m=<?php echo $modulo['prefijo'] . '_g'; ?>" method="post" class="form-horizontal" enctype="multipart/form-data" onsubmit="return validar();">
    <input type="hidden" name="id" value="<?php echo $id; ?>">
    <input type="hidden" name="type" value="<?php echo $type; ?>">
    <div id="contenido_cabezal">
        <h4 class="titulo">Ponderador Valor MercadoLibre</h4>
        <hr>
        <button type="submit" class="btn btn-small btn-primary">Guardar</button>
        <button type="button" class="btn btn-small btn_sep" onclick="window.location.href = '?m=<?php echo $modulo['prefijo'] . '_l'; ?>';">Cancelar</button>
        <hr class="nb">
    </div>
    <div class="sep_titulo"></div>

    <div class="control-group  ">
        <?php if($type == 'd') { ?>
            <label class="control-label" for="porcentaje">Valor del d&oacute;lar</label>
            <div class="controls">
                <input type="text" name="porcentaje" id="porcentaje" value="<?php echo $elemento['dolar'] ?>"/>
            </div>
        <?php } else if($type == 'm') { ?>
            <label class="control-label" for="porcentaje">Tope de b&uacute;squeda</label>
            <div class="controls">
                <input type="text" name="porcentaje" id="porcentaje" value="<?php echo $elemento['valor'] ?>"/>
            </div>
        <?php } else { ?>
            <label class="control-label" for="porcentaje"><?php echo ($type == 'p') ? 'Porcentaje' : 'Nominal' ?></label>
            <div class="controls">
                <input type="text" name="porcentaje" id="porcentaje" value="<?php echo ($type == 'p') ? $elemento['porcentaje'] : $elemento['nominal'] ?>"/>
            </div>
        <?php } ?>
    </div>

</form>

<script>

    function validar() {
        var ret = true;

        if ($('#porcentaje').val() == '' && ret) {
            alert('Debe ingresar un porcentaje');
            $('#porcentaje').focus();
            ret = false;
        }

        return ret;
    }

</script>
<?php
require_once('sistema_post_contenido.php');
?>