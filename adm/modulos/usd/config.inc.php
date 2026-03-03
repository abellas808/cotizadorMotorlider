<?php

$sistema['modulos']['usd'] = array(	'nombre' => 'Variables USD',
									'prefijo' => 'usd',  
								    'botonera' => 1,
									'paginas' => array(	'l' => 'l.php', // Listado
														'v' => 'v.php', // Ver
														'c' => 'c.php', // Crear
														'm' => 'm.php', // Modificar
														'g' => 'g.php', // Guardar
														'e' => 'e.php' // Eliminar
													   ),
									'permisos' => array(1 => 'Solo ver',
														2 => 'Total'
													   ),
									'principal' => 'l');

?>