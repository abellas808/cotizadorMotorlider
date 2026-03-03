<?php

//error_reporting(E_ALL);
//ini_set('display_errors', 1);

if (!isset($sistema_iniciado))
    exit();

if ($_SESSION[$config['codigo_unico']]['login_permisos'][$modulo['prefijo']] <= 1) {
    header('Location: ?m=' . $modulo['prefijo'] . '_l');
    exit();
}


$porcentaje = $_POST['porcentaje'];

// ************************************
// MODIFICAR
// ************************************
if (isset($_POST['id']) && isset($_POST['type'])) {

    $id = $_POST['id'];
    $type = $_POST['type'];

    if($type == 'p'){
        $db->query_update('ponderador_valor_venal', array(
            'porcentaje' => $porcentaje
        ), 'id_ponderador_valor_venal = "' . $id . '"');
    } else if($type == 'n') {
        $db->query_update('ponderador_valor', array(
            'nominal' => $porcentaje
        ), 'id_ponderador_valor = "' . $id . '"');
    } else if($type == 'm') {
        $db->query_update('ponderador_valor_maximo', array(
            'valor' => $porcentaje
        ), 'id_valor_maximo = "' . $id . '"');
    } else if($type == 'd') {
        $db->query_update('ponderador_valor_dolar', array(
            'dolar' => $porcentaje
        ), 'id_valor_dolar = "' . $id . '"');
    }
} else {

    // ************************************
    // CREAR
    // ************************************

    $id = $db->query_insert('ponderador_valor_venal', array(
        'porcentaje' => $porcentaje
    ));
}

header('Location: ?m=' . $modulo['prefijo'] . '_l');
