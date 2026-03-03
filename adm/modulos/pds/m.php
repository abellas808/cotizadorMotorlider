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
    $elemento = $db->query_first('select * from ponderador_valor_stock where id_ponderador_valor_stock = ' . $id . ';');
} else if($type == 'k'){
    $elemento = $db->query_first('select * from ponderador_valor_busqueda where id_valor_busqueda = "' . $id . '";');
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
        <h4 class="titulo"><?php echo $modulo['nombre']; ?></h4>
        <hr>
        <button type="submit" class="btn btn-small btn-primary">Guardar</button>
        <button type="button" class="btn btn-small btn_sep" onclick="window.location.href = '?m=<?php echo $modulo['prefijo'] . '_l'; ?>';">Cancelar</button>
        <hr class="nb">
    </div>
    <div class="sep_titulo"></div>

    <div class="control-group  ">
        <?php if($type == 'p') : ?>
            <p style="width:130px;"><b>MARCA </b><?php echo $elemento['marca']; ?></p>
            <p style="width:130px;"><b>MODELO </b><?php echo $elemento['modelo']; ?></p>
            <p style="width:130px;"><b>AÑO </b><?php echo $elemento['anio']; ?></p>
            <p style="width:130px;"><b>VERSION </b><?php echo $elemento['version']; ?></p>
            <p><span style="margin-right: 20px;width: 130px;text-align: right;float: left;">KM</span>
                <input type="text" name="km" id="km" onkeyup="oneDot(this)" value="<?php echo number_format($elemento['kilometros'] , 0, ',', '.'); ?>"/>
            </p>
            <label class="control-label" for="stock">Stock</label>
            <div class="controls">
                <input type="text" name="stock" id="stock" value="<?php echo $elemento['stock'] ?>"/>
            </div>
        <?php elseif($type == 'k'): ?>
            <label class="control-label" for="stock">Kilometros</label>
            <div class="controls">
                <input type="text" name="stock" id="stock" onkeyup="oneDot(this)" value="<?php echo number_format($elemento['busqueda'] , 0, ',', '.'); ?>"/>
            </div>
        <?php endif; ?>
    </div>

</form>

<script>

    function validar() {
        var ret = true;

        if ($('#stock').val() == '' && ret) {
            alert('Debe ingresar un stock');
            $('#stock').focus();
            ret = false;
        }

        return ret;
    }

    function oneDot(input) {
        var value = input.value,
            value = value.split('.').join('');
        if (value.length > 3) {
            value = value.substring(0, value.length - 3) + '.' + value.substring(value.length - 3, value.length);
        }
        input.value = value;
    }

</script>
<?php
require_once('sistema_post_contenido.php');
?>