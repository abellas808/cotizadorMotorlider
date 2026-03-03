<?php

header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Cache-Control: no-cache");
header("Pragma: no-cache");

require_once("config/config.inc.php");
require_once("includes/database.php");
require_once("includes/funciones.php");


$listado = $db->query('SELECT * FROM compras where finalizada = 1 and con_error = 0 and estado in (0,1)');

while ($entrada = $db->fetch_array($listado)) {

    $like = '%"PedOc":"'.$entrada['id_compra'].'"%';
    $log_bhv = $db->query_first("select * from logs_bhv where request like '".$like."'");

    if ($log_bhv) {

        $db->query_update('logs_bhv', array(
            'id_compra' => $entrada['id_compra']
        ), 'id = "' . $log_bhv['id'] . '"');

        var_dump("cambio bhv " . $log_bhv['id'] . " -  id_compra - " . $entrada['id_compra']);
    }
}
