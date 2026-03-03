<?php

require_once("../config/config.inc.php");
require_once("../includes/database.php");
require_once("../includes/funciones.php");

$marca = intval($_POST['marca']);

$modelos = $db->query('SELECT * from modelo_marca WHERE marca = "' . $marca . '" and activo = 1 order by nombre asc');

$response = '';

if ($db->num_rows > 0) {

    while ($modelo = $db->fetch_array($modelos)) {

        $response .= '<option value="'.$modelo['id_modelo_marca'].'">'.$modelo['nombre'].'</option>';

    }
}


echo $response;
