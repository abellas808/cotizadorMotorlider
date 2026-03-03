<?php

$sistema['modulos']['cot'] = array(	'nombre' => 'Cotizaciones',
									'prefijo' => 'cot',  
								    'botonera' => 1,
									'paginas' => array(	'l' => 'l.php', // Listado
														'v' => 'v.php', // Ver
													   ),
									'permisos' => array(1 => 'Solo ver',
														2 => 'Total'
													   ),
									'principal' => 'l');

?>