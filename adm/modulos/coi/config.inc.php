<?php

$sistema['modulos']['coi'] = array(	'nombre' => 'Cotizaciones Internas',
									'prefijo' => 'coi',  
								    'botonera' => 1,
									'paginas' => array(	'l' => 'l.php', // Listado
														'v' => 'v.php', // Ver
													   ),
									'permisos' => array(1 => 'Solo ver',
														2 => 'Total'
													   ),
									'principal' => 'l');

?>