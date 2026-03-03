<?php

$sistema['modulos']['miu'] = array(	'nombre' => 'Mi usuario',
									'prefijo' => 'miu',  
								    'botonera' => 1,
									'paginas' => array(	'm' => 'm.php', // Modificar
														'g' => 'g.php' // Guardar
													   ),
									'permisos' => array(1 => 'Total'
													   ),
									'principal' => 'm');

?>