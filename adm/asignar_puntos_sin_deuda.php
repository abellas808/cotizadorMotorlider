<?php

require_once("config/config.inc.php");
require_once("includes/database.php");
require_once("includes/funciones.php");

session_start();

date_default_timezone_set("America/Montevideo");

$listado = $db->query('SELECT SQL_CALC_FOUND_ROWS * FROM empresas WHERE sin_deuda = 1');

while ($empresa = $db->fetch_array($listado)) {

    $id = $db->query_insert(
        'puntos_extra',
        array(
            'empresa' => $empresa['id_empresa'],
            'puntos' => $config['puntos_sin_deuda'],
            'detalle' => 'Sin deuda',
            'fecha' => date('Y-m-d'),
            'usuario' => 1,
        )
    );

    if($id){
        $db->query_update(
            'empresas',
            array(
                'puntos' => $empresa['puntos'] + $config['puntos_sin_deuda'],
            ),
            'id_empresa = "' . $empresa['id_empresa'] . '"'
        );
    }
    
}
