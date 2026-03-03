<?php

$sistema['modulos']['pvv'] = array('nombre' => 'Ponderador Valor ML',
    'prefijo' => 'pvv',
    'botonera' => 1,
    'paginas' => array('l' => 'l.php', // Listado
        'm' => 'm.php', // Modificar
        'g' => 'g.php', // Guardar
    ),
    'permisos' => array(1 => 'Solo ver',
        2 => 'Total'
    ),
    'principal' => 'l');
?>