<?php

$sistema['modulos']['pds'] = array('nombre' => 'Ponderacion de Stock',
    'prefijo' => 'pds',
    'botonera' => 1,
    'paginas' => array('l' => 'l.php', // Listado
        'm' => 'm.php', // Modificar
        'g' => 'g.php', // Guardar
        'e' => 'e.php' // Eliminar
    ),
    'permisos' => array(1 => 'Solo ver',
        2 => 'Total'
    ),
    'principal' => 'l');
?>