<?php
// ***************************************************************************************************
// Chequeo que no se llame directamente
// ***************************************************************************************************

	if (!isset($sistema_iniciado)) exit();

// ***************************************************************************************************
// Paginado
// ***************************************************************************************************
	$pagina = intval($_GET['p']);
	if ($pagina == 0) {
		$pagina = 1;
	}

// ***************************************************************************************************
// Busqueda
// ***************************************************************************************************
	$sql_b = '';
	if ($_GET['b'] != '') {
		$busqueda = substr($_GET['b'], 0, 30);
		$busqueda_array = explode(' ', $busqueda); 
		for ($i = 0; $i <= count($busqueda_array); $i++) {
			$sql_b .= ' and (email like "%'.$busqueda_array[$i].'%" or nombre like "%'.$busqueda_array[$i].'%")';
		}
	}

// ***************************************************************************************************
// Ordenado
// ***************************************************************************************************
	$orden_campo = intval($_GET['o']);
	$orden_dir = intval($_GET['od']);
	
	switch ($orden_dir) {
		case 1:
			$sql_od = 'desc';
			$od_chr = '▼';
			break;
		default:			
			$sql_od = 'asc';
			$od_chr = '▲';
	}

	switch ($orden_campo) {
		case 1:
			$sql_o = 'email';
			break;
		default:
			$sql_o = 'nombre';
			$orden_campo = 0;
	}

	$sql_b = trim($sql_b, ' and ');

	if ($sql_b != '') $sql_b = ' where '.$sql_b;

// ***************************************************************************************************
// Consulta
// ***************************************************************************************************
	$listado = $db->query('SELECT SQL_CALC_FOUND_ROWS * FROM admin_usuarios '.$sql_b.' order by '.$sql_o.' '.$sql_od.' limit '.(($pagina-1)*$config['pagina_cant']).', '.$config['pagina_cant'].';');

	$qry = $db->query_first('select FOUND_ROWS() as cantidad;');
	$total = $qry['cantidad'];

	$total_paginas = ceil($total / $config['pagina_cant']);
	
?>
<?php

	require_once('sistema_cabezal.php');

?>
<?php

	require_once('sistema_pre_contenido.php');

?>
<div id="contenido_cabezal">
    
    <div class="pull-right">
        <input type="text" id="b" onkeypress="if (event.keyCode == 13) { window.location.href='?m=<?php echo $modulo['prefijo'].'_l'; ?><?php if ($orden_campo != 0) { echo '&o='.$orden_campo; } ?><?php if ($orden_dir != 0) { echo '&od='.$orden_dir; } ?>&b='+$('#b').val(); }" value="<?php echo_s($busqueda); ?>" maxlength="30" /> 
    <?php
        if ($busqueda != '') {
    ?>
        <button type="button" class="btn btn-default btn-small btn_cerrar" onclick="window.location.href='?m=<?php echo $modulo['prefijo'].'_l'; ?><?php if ($orden_campo != 0) { echo '&o='.$orden_campo; } ?><?php if ($orden_dir != 0) { echo '&od='.$orden_dir; } ?>';">X</button> 
    <?php
        }
    ?>
        <button type="button" class="btn btn-default btn-small" onclick="window.location.href='?m=<?php echo $modulo['prefijo'].'_l'; ?><?php if ($orden_campo != 0) { echo '&o='.$orden_campo; } ?><?php if ($orden_dir != 0) { echo '&od='.$orden_dir; } ?>&b='+$('#b').val();">Buscar</button> 
    </div>
    <h4 class="titulo"><?php echo $modulo['nombre']; ?></h4>
    <?php
        if ($_SESSION[$config['codigo_unico']]['login_permisos']['usa'] > 1) {
    ?>
    <hr>
    <button type="button" class="btn btn-primary btn-small" onclick="window.location.href='?m=<?php echo $modulo['prefijo']; ?>_c';">Nuevo</button> 
    <?php
        }
    ?>
    <hr class="nb">
</div>    
<div class="sep_titulo"></div>
<?php
	if ($total > 0) {
?>
<?php
	if ($_SESSION[$config['codigo_unico']]['login_permisos']['usa'] > 1) {
?>
<form id="form_listado" action="?m=<?php echo $modulo['prefijo'].'_e'; ?>" method="post">
<?php
	}
?>
<table class="table table-hover">
<thead>
<tr>
<?php
// ***************************************************************************************************
// Columnas / Cabezales
// ***************************************************************************************************
?>
<th width="300">
<?php 
		if ($orden_campo == 0) {
?>		
<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') { echo '&b='.$busqueda; } ?>&o=0&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>"><strong>Nombre <?php echo $od_chr; ?></strong></a>
<?php
		} else {
?>
<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') { echo '&b='.$busqueda; } ?>&o=0">Nombre</a>
<?php
		}
?>
</th>
<th>
<?php 
		if ($orden_campo == 1) {
?>		
<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') { echo '&b='.$busqueda; } ?>&o=1&od=<?php echo $orden_dir == 0 ? 1 : 0; ?>"><strong>Email <?php echo $od_chr; ?></strong></a>
<?php
		} else {
?>
<a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($busqueda != '') { echo '&b='.$busqueda; } ?>&o=1">Email</a>
<?php
		}
?>
</th>
<th>
	Depósitos 
</th>
<?php
// ***************************************************************************************************
?>
<th width="30"></th>
</tr>

</thead>
<tfoot>
        <tr>
          <td height="30" colspan="4" valign="bottom"><div class="info_seleccionados"><span id="cantidad_seleccionados"></span><?php if ($_SESSION[$config['codigo_unico']]['login_permisos']['usa'] > 1) { ?> - <input type="button" class="btn btn-danger btn-small" value="Eliminar" onclick="eliminar();" /><?php } ?></div>
            <div class="info_listados">Total: <strong><?php echo $total; ?></strong></div>
<?php
	if ($total_paginas > 1) {
?>            
            <div class="paginas">
<?php
	if ($pagina > 1) {
?>            
            <a href="?m=<?php echo $modulo['prefijo']; ?>&p=<?php echo $pagina - 1; ?><?php if ($busqueda != '') { echo '&b='.$busqueda; } ?><?php if ($orden_campo != 0) { echo '&o='.$orden_campo; } ?><?php if ($orden_dir != 0) { echo '&od='.$orden_dir; } ?>">< anterior</a>
<?php
	}
?>
<select id="select_pagina" class="input-mini">
<?php 
	for ($i = 1; $i <= $total_paginas; $i++) {
?>
  <option value="<?php echo $i; ?>" <?php if ($i == $pagina) { echo 'selected="selected"'; } ?>><?php echo $i; ?></option>
<?php
	}
?>	
</select> / <?php echo $total_paginas; ?>
<?php
	if ($pagina < $total_paginas) {
?>            
            <a href="?m=<?php echo $modulo['prefijo']; ?>_l&p=<?php echo $pagina + 1; ?><?php if ($busqueda != '') { echo '&b='.$busqueda; } ?><?php if ($orden_campo != 0) { echo '&o='.$orden_campo; } ?><?php if ($orden_dir != 0) { echo '&od='.$orden_dir; } ?>">siguiente ></a>
<?php
	}
?>
          </div>
<?php
	}
?>
          </td>
        </tr>
      </tfoot>
<tbody>
<?php
	while ($entrada = $db->fetch_array($listado)) {
?>
<tr>
<?php
// ***************************************************************************************************
// Columnas / Datos
// ***************************************************************************************************
?>
<td>
	<a href="?m=<?php echo $modulo['prefijo']; ?>_v&i=<?php echo $entrada['id']; ?>"><?php echo echo_s($entrada['nombre']); ?></a>
</td>
<td>
	<a href="?m=<?php echo $modulo['prefijo']; ?>_v&i=<?php echo $entrada['id']; ?>"><?php echo_s($entrada['email']); ?></a>
</td>
<td>
	<?php echo_s($entrada['sucursales']); ?>
</td>

<?php
// ***************************************************************************************************
?>
<td><input name="e_sel[]" type="checkbox" value="<?php echo $entrada['id']; ?>" /></td>
</tr>
<?php
	}
?>
</tbody>
</table>
<?php
	if ($_SESSION[$config['codigo_unico']]['login_permisos']['usa'] > 1) {
?>
</form>
<?php
	}
?>
<script>
$('input[name="e_sel[]"]').bind('click', function(e) {
		
	$(this).closest('tr').toggleClass('info');
	var t = $('tr.info').length;
	if (t > 0) {
		$('.info_seleccionados').show();
		t == 1 ? $('#cantidad_seleccionados').html('1 elemento seleccionado') : $('#cantidad_seleccionados').html(t + ' elementos seleccionados');
	} else {
		$('.info_seleccionados').hide();
	}

});
	
$('#select_pagina').bind('change', function(e) {

	window.location.href = '?m=<?php echo $modulo['prefijo']; ?>_l&p='+$(this).val()+'<?php if ($busqueda != '') { echo '&b='.$busqueda; } ?><?php if ($orden_campo != 0) { echo '&o='.$orden_campo; } ?><?php if ($orden_dir != 0) { echo '&od='.$orden_dir; } ?>';
		
});	

function eliminar() {
	
	if (confirm('¿Esta seguro que desea eliminar los elementos seleccionados?')) {
		$('#form_listado').submit();
	}
	
}
</script>
<?php
	} else {
		if ($busqueda != '') {
?>
<div class="info_resultado">
    <div class="tc">No se encontraron elementos con <strong>"<?php echo_s($busqueda); ?>"</strong>.</div>
    <div class="tc"><a href="?m=<?php echo $modulo['prefijo']; ?>_l<?php if ($orden_campo != 0) { echo '&o='.$orden_campo; } ?><?php if ($orden_dir != 0) { echo '&od='.$orden_dir; } ?>">Ver todos</a></div>
</div>
<?php
		} else {
?>
<div class="info_resultado">
    <div class="tc">No hay elementos para listar.</div>
    <div class="tc"><a href="?m=<?php echo $modulo['prefijo']; ?>_c">Nuevo</a></div>
</div>
<?php
		}
	}
?>
<?php

	require_once('sistema_post_contenido.php');

?>