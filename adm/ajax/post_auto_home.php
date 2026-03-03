<?php

require_once("../config/config.inc.php");
require_once("../includes/database.php");
require_once("../includes/funciones.php");

$id = intval($_POST['auto']);
$home = intval($_POST['home']);

$auto = $db->query_first('SELECT * FROM automoviles WHERE id_automoviles = "' . $id . '"');

/*$sql = 'SELECT SQL_CALC_FOUND_ROWS * FROM automoviles WHERE home = 1 and categoria = "' . $auto['categoria'] . '"';
$listado = $db->query($sql);

$qry = $db->query_first('select FOUND_ROWS() as cantidad;');
$total = $qry['cantidad'];*/

if ($home == 1) {
    $db->query_update('automoviles', array(
        'home' => $home,
    ), 'id_automoviles = "' . $id . '"');

    echo 1;
} else {
    $db->query_update('automoviles', array(
        'home' => 0,
    ), 'id_automoviles = "' . $id . '"');
    echo 0;
}
