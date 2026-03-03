<?php

if (!isset($sistema_iniciado)) exit();

?>
</head>

<body>
<div class="box">
  <div id="cabezal">
    <div class="toggle_botonera" onclick="$('#botonera').toggleClass('botonera_abierta');"><img src="img/menu.png" width="24" height="24"></div>
    <img src="img/logo_negativo.svg" class="logo" width="150" >
       <a href="?m=l" class="salir" title="Salir"></a>
      <div class="usuario"><?php echo_s($_SESSION[$config['codigo_unico']]['login_nombre']); ?></div>   
  </div>
  <div id="botonera">
    <div class="contenedor_botones"> 
<?php  
	foreach($sistema['modulos'] as $prefijo => $md) {
//		if (in_array($md['prefijo_corto'],$permisos) || ($_SESSION['login_super'] == 1)){	

		if ($md['botonera'] == 1) {
			if (isset($_SESSION[$config['codigo_unico']]['login_permisos'][$prefijo]) && ($_SESSION[$config['codigo_unico']]['login_permisos'][$prefijo] > 0)) {

?>
  				<a href="?m=<?php echo $prefijo; ?>_<?php echo $md['principal']; ?>" <?php if ($prefijo == $modulo['prefijo']) { echo 'class="activo"'; } ?>><?php echo $md['nombre']; ?></a>
<?php
			}
		}
	}
?>
	</div>
  </div>
  <div id="contenido">