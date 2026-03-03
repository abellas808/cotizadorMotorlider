<?php

$sistema['modulos']['age'] = array(	'nombre' => 'Agendas',
									'prefijo' => 'age',  
								    'botonera' => 1,
									'paginas' => array(	'l' => 'l.php', // Listado
														'v' => 'v.php', // Ver
														'e' => 'e.php', // eliminar
													   ),
									'permisos' => array(1 => 'Solo ver',
														2 => 'Total'
													   ),
									'principal' => 'l');

?>