<?php

if (!isset($sistema_iniciado)) exit();

$usuario_colaborador = array(
	'id' => isset($_SESSION[$config['codigo_unico']]['login_usuario_id']) ? $_SESSION[$config['codigo_unico']]['login_usuario_id'] : '',
	'nombre' => isset($_SESSION[$config['codigo_unico']]['login_nombre']) ? $_SESSION[$config['codigo_unico']]['login_nombre'] : '',
	'email' => '',
	'ultimo_login' => '',
);

if (!empty($usuario_colaborador['id'])) {
	$usuario_actual = $db->query_first('select id, nombre, email, ultimo_login from admin_usuarios where id = "'.$db->escape($usuario_colaborador['id']).'" ;');

	if ($usuario_actual) {
		$usuario_colaborador['nombre'] = $usuario_actual['nombre'];
		$usuario_colaborador['email'] = $usuario_actual['email'];
		$usuario_colaborador['ultimo_login'] = $usuario_actual['ultimo_login'];
	}
}

$usuario_colaborador_ultimo = '';

if (!empty($_SESSION[$config['codigo_unico']]['login_ultimo'])) {
	$usuario_colaborador_ultimo = date('d/m/Y H:i', $_SESSION[$config['codigo_unico']]['login_ultimo']);
} else if (!empty($usuario_colaborador['ultimo_login'])) {
	$usuario_colaborador_ultimo = date('d/m/Y H:i', strtotime($usuario_colaborador['ultimo_login']));
}

?>
</head>

<body>
<div class="box">
  <div id="cabezal">
    <div class="toggle_botonera" onclick="$('#botonera').toggleClass('botonera_abierta');"><img src="img/menu.png" width="24" height="24"></div>
    <img src="img/logo_negativo.svg" class="logo" width="150" >
       <a href="?m=l" class="salir" title="Salir"></a>
      <div class="usuario_box">
        <button type="button" class="usuario" title="Datos del colaborador" aria-label="Datos del colaborador"></button>
        <div class="usuario_popover">
          <div class="usuario_popover_titulo">Colaborador</div>
          <div class="usuario_popover_nombre"><?php echo_s($usuario_colaborador['nombre']); ?></div>
          <div class="usuario_popover_fila">
            <span>Email</span>
            <strong><?php echo_s(!empty($usuario_colaborador['email']) ? $usuario_colaborador['email'] : '-'); ?></strong>
          </div>
          <div class="usuario_popover_fila">
            <span>ID</span>
            <strong><?php echo_s(!empty($usuario_colaborador['id']) ? $usuario_colaborador['id'] : '-'); ?></strong>
          </div>
          <div class="usuario_popover_fila">
            <span>Último ingreso</span>
            <strong><?php echo_s(!empty($usuario_colaborador_ultimo) ? $usuario_colaborador_ultimo : '-'); ?></strong>
          </div>
        </div>
      </div>
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
