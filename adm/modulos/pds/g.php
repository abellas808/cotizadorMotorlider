<?php

//error_reporting(E_ALL);
//ini_set('display_errors', 1);

if (!isset($sistema_iniciado))
    exit();

if ($_SESSION[$config['codigo_unico']]['login_permisos'][$modulo['prefijo']] <= 1) {
    header('Location: ?m=' . $modulo['prefijo'] . '_l');
    exit();
}

$stock = $_POST['stock'];

// ************************************
// MODIFICAR
// ************************************
if (isset($_POST['id']) && isset($_POST['type'])) {

    $id = $_POST['id'];
    $type = $_POST['type'];

    if($type == 'p'){
        $km = $_POST['km'];
        $km = str_replace(".", "", $km);
        $db->query_update('ponderador_valor_stock', array(
            'kilometros' => (int)$km,
            'stock' => $stock
        ), 'id_ponderador_valor_stock = "' . $id . '"');
    } else if($type == 'k'){
        $stock = str_replace(".", "", $stock);
        $db->query_update('ponderador_valor_busqueda', array(
            'busqueda' => (int)$stock
        ), 'id_valor_busqueda   = "' . $id . '"');
    }
} else if (isset($_POST['marca']) && isset($_POST['modelo']) && isset($_POST['anio']) && isset($_POST['version']) && isset($_POST['stock'])) {

    if ($_POST['anio'] > 0 && $_POST['km'] > 0 && $_POST['stock'] > 0) {

        // ************************************
        // CREAR
        // ************************************

        $marca = $_POST['marca'];
        $modelo = $_POST['modelo'];
        $anio = $_POST['anio'];
        $version = $_POST['version'];
        if($version == 'OTROS'){
            $version = strtoupper($_POST['txtfamily']);
        }
        $km = $_POST['km'];
        $km = str_replace(".", "", $km);
        $stock = $_POST['stock'];

        $elemento = $db->query_first('select * from ponderador_valor_stock where marca = "'.$marca.'" AND modelo = "'.$modelo.'" AND anio ='.$anio.' AND km ='.$km.' AND version = "'.$version.'"');

        if(isset($elemento)){
            $id = $elemento['id_ponderador_valor_stock'];
            $db->query_update('ponderador_valor_stock', array(
                'stock' => $stock
            ), 'id_ponderador_valor_stock = '.$id.'');
        } else {
            $id = $db->query_replace('ponderador_valor_stock', array(
                'marca' => $marca,
                'modelo' => $modelo,
                'anio' => (int)$anio,
                'version' => $version,
                'kilometros' => (int)$km,
                'stock' => $stock
            ));
        }
    }
    
    header('Location: ?m=' . $modulo['prefijo'] . '_l');
}

header('Location: ?m=' . $modulo['prefijo'] . '_l');
