<?php
// ***************************************************************************************************
// Chequeo que no se llame directamente
// ***************************************************************************************************

/*include('./../config.php');
include('./../config/config.inc.php');

$url = $config->urlBase.'ws/brands';
$marcas = json_decode(httpGet($url));*/

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
		$sql_b .= ' and (porcentaje like "%' . $busqueda_array[$i] . '%")';
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
		$sql_o = 'stock';
		break;
	default:
		$sql_o = 'id_ponderador_valor_stock';
		$orden_campo = 0;
}

$sql_b = trim($sql_b, ' and ');

if ($sql_b != '') $sql_b = ' and ' . $sql_b;

// ***************************************************************************************************
// Consulta
// ***************************************************************************************************
$listado = $db->query('SELECT SQL_CALC_FOUND_ROWS * FROM ponderador_valor_stock where 1=1 ' . $sql_b . ' order by ' . $sql_o . ' ' . $sql_od . ' limit ' . (($pagina - 1) * $config['pagina_cant']) . ', ' . $config['pagina_cant'] . ';');
$qry = $db->query_first('select FOUND_ROWS() as cantidad;');
$total = $qry['cantidad'];

$total_paginas = ceil($total / $config['pagina_cant']);

$listado_busqueda = $db->query('SELECT SQL_CALC_FOUND_ROWS * FROM ponderador_valor_busqueda where 1=1 ' . $sql_b . ';');

?>

<?php require_once('sistema_cabezal.php'); ?>

<?php require_once('sistema_pre_contenido.php'); ?>

<div id="contenido_cabezal">
	<div class="pull-right"></div>
	<h4 class="titulo"><?php echo $modulo['nombre']; ?></h4>
	<!-- <hr> -->
	<?php if ($_SESSION[$config['codigo_unico']]['login_permisos'][$modulo['prefijo']] > 1) { ?>
		<!-- <button type="button" class="btn btn-primary btn-small" onclick="window.location.href='?m=<?php echo $modulo['prefijo']; ?>_m&i=1';">Modificar</button> -->
	<?php } ?>
	<hr class="nb">
</div>

<div class="sep_titulo" style="height: 20px;"></div>

	<div style="margin: 75px 0 0 20px;">	
		<table style="margin-top: 100px;" class="table table-hover" >
			<thead>
				<tr>
					<th width="150"><b>+/- Kilometros de B&uacute;squeda</b></th>
				</tr>
			</thead>
			<tbody>
				<?php while ($entrada = $db->fetch_array($listado_busqueda)) { ?>
					<tr>
						<td>
							<a href="?m=<?php echo $modulo['prefijo']; ?>_m&i=<?php echo $entrada['id_valor_busqueda']; ?>&t=k"><?php echo number_format($entrada['busqueda'] , 0, ',', '.'); ?></a>
						</td>
					<tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
	
	<?php 
	include('./../config/config.inc.php');
	$url = $config->urlBase.'ws/brands';
	$marcas = json_decode(httpGet($url));
	?>
	<div style="margin: 75px 0 0 20px;">
		<select onchange="get_modelo()" id="marca" name="reserva_tasa_marca">
			<option id="0">Marca</option>
			<?php foreach($marcas->brands as $key => $marca) : ?>
				<option value="<?php echo $key; ?>"><?php echo $marca; ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div id="div_modelo" style="margin: 25px 0 0 20px; display: none;">
		<select id="modelo" onchange="get_ano()" name="reserva_tasa_modelo"></select>
	</div>
	<div id="div_ano" style="margin: 25px 0 0 20px; display: none;">
		<select id="ano" onchange="get_familias()" name="reserva_tasa_anio"></select>
	</div>
	<div id="div_familia" style="margin: 25px 0 0 20px; display: none;">
		<select id="familia" name="reserva_tasa_familia">
		</select>
	</div>
	<div id="div_stock" style="margin: 25px 0 0 20px; display: none;">
		<form style="margin: 0px;" id="form_datos" class="form-horizontal" enctype="multipart/form-data" onsubmit="return validar();">
			<div class="control-group txtfamily" style="display:none">
				<div class="controls" style="margin-left: 0px;">
					<input type="text" placeholder="Ingrese su versión" name="txtfamily" id="txtfamily">
				</div>
			</div>		
			<div class="control-group">
				<div class="controls" style="margin-left: 0px;">
					<input type="number" onkeyup="oneDot(this)" placeholder="Ingrese los kilometros" name="km" id="km">
				</div>
			</div>	
			<div class="control-group">
				<div class="controls" style="margin-left: 0px;">
					<input type="number" placeholder="Ingrese el stock" name="stock" id="stock">
				</div>
			</div>
			<div id="contenido_footer">
				<button type="submit" class="btn btn-small btn-primary">Guardar</button>
				<button type="button" class="btn btn-small btn_sep" onclick="window.location.href = '?m=<?php echo $modulo['prefijo'] . '_l'; ?>';">Cancelar</button>
				<hr class="nb">
			</div>
		</form>
		<!-- <input type="number" placeholder="Ingrese el stock" name="stock" id="stock" class="input-login"> -->
	</div>
	<p class="msg" style="margin: 25px 0 0 20px;"></p>

	<form id="form_listado" action="?m=<?php echo $modulo['prefijo'] . '_e'; ?>" method="post">
		<?php require_once("modulos/pds/stock.php"); ?>
	</form>

<?php require_once('sistema_post_contenido.php'); ?>

<script>

	function oneDot(input) {
        var value = input.value,
            value = value.split('.').join('');
        if (value.length > 3) {
            value = value.substring(0, value.length - 3) + '.' + value.substring(value.length - 3, value.length);
        }
        input.value = value;
    }

	$('#familia').bind('change', function(e) {
		$("#div_stock").show();
		let version = document.getElementById("familia");
		let textVersion = version.options[version.selectedIndex].text;
		if(textVersion == 'OTROS'){
			$(".txtfamily").css("display", "block");
		} else {
			$(".txtfamily").css("display", "none");
		}
	});

	function validar() {
        var ret = true;

		let version = document.getElementById("familia");
		let textVersion = version.options[version.selectedIndex].text;
		if(textVersion == 'OTROS'){
			if ($('#txtfamily').val() == '' && ret) {
				alert('Debe ingresar una versión');
				$('#txtfamily').focus();
				ret = false;
			}
		}

		if ($('#km').val() == '' && ret) {
            alert('Debe ingresar los kilometros');
            $('#km').focus();
            ret = false;
        }

        if ($('#stock').val() == '' && ret) {
            alert('Debe ingresar un stock');
            $('#stock').focus();
            ret = false;
        }

        return ret;
    }

	function get_modelo() {
        $("#div_modelo").hide();
        $("#div_ano").hide();
        $("#div_familia").hide();
		$("#div_stock").hide();
		$("#txtfamily").val("");

        let marca = $("#marca").val();

        if (marca > 0) {
            $.ajax({
                type: "POST",
                url: "/../../tasacion/ajax/get_modelo_tasacion.php",
                data: {
                    marca: marca
                },
				beforeSend: function(){
					$(".msg").html("Buscando...");
				},
                success: function(response) {
                    if (response != '') {
                        $("#modelo").html(response);
                        $("#div_modelo").show();
						$(".msg").html('');
                    } else {
                        $("#modelo").empty();
                        $("#div_modelo").hide();
						$(".msg").html('');
                    }

                }
            });
        } else {
            $("#modelo").empty();
            $("#div_modelo").hide();
        }
    }
	function get_ano() {
        $("#div_ano").hide();
		$("#div_stock").hide();
		$("#txtfamily").val("");

        let marca = $("#marca").val();
        let modelo = $("#modelo").val();
        
        if (marca > 0 && modelo > 0) {
            $.ajax({
                type: "POST",
                url: "/../../tasacion/ajax/get_anio_tasacion.php",
                data: {
                    marca: marca,
                    modelo: modelo
                },
				beforeSend: function(){
					$(".msg").html("Buscando...");
				},
                success: function(response) {
                    if (response != '') {
                        $("#ano").html(response);
                        $("#div_ano").show();
						$(".msg").html('');
                    } else {
                        $("#ano").empty();
                        $("#div_ano").hide();
						$(".msg").html('');
                    }

                }
            });
        } else {
            $("#ano").empty();
            $("#div_ano").hide();
        }
    }
	function get_familias() {
        $("#div_familia").hide();
		$("#div_stock").hide();
		$("#txtfamily").val("");

        let marca = $("#marca").val();
        let modelo = $("#modelo").val();
        let anio = $("#ano").val();

        if (marca > 0 && modelo > 0 && anio > 0) {
            $.ajax({
                type: "POST",
				url: "/../../tasacion/ajax/get_familia_tasacion.php",
                data: {
                    marca: marca,
                    modelo: modelo,
                    anio: anio
                },
				beforeSend: function(){
					$(".msg").html("Buscando...");
				},
                success: function(response) {
                    if (response != '') {
                        $("#familia").html(response);
                        $("#div_familia").show();
						$(".msg").html('');
                    } else {
                        $("#familia").empty();
                        $("#div_familia").hide();
						$(".msg").html('');
                    }
                }
            });
        } else {
            $("#familia").empty();
            $("#div_familia").hide();
        }
    }

	$('#form_datos').bind('submit', function(e) {
		e.preventDefault();

		let model1 = document.getElementById("modelo");
		let textModel1 = model1.options[model1.selectedIndex].text;
		let marca1 = document.getElementById("marca");
		let textMarca1 = marca1.options[marca1.selectedIndex].text;
        let anio = $("#ano").val();
		let version = document.getElementById("familia");
		let textVersion = version.options[version.selectedIndex].text;
		let txtfamily = $("#txtfamily").val();
		let km = $("#km").val();
        let stock = $("#stock").val();

		

        if (km >= 0 && stock > 0) {
            $.ajax({
                type: "POST",
                url: "?m=pds_g",
                data: {
                    marca: textMarca1,
					modelo: textModel1,
					anio: anio,
					version: textVersion,
					txtfamily: txtfamily,
					km: km,
					stock: stock,
                },
				beforeSend: function(){
					$(".msg").html("Cargando...");
				},
                success: function(response) {
					$(".msg").html("Se inserto/actualizo el vehiculo. Recargue para ver los cambios.");
                }
            });
        } else {
            //$("#modelo").empty();
            //$("#div_modelo").hide();
        }
    });

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

		window.location.href = '?m=<?php echo $modulo['prefijo']; ?>_l&p=' + $(this).val() + '<?php if ($busqueda != '') {
			echo '&b=' . $busqueda;
		} ?><?php if ($orden_campo != 0) {
			echo '&o=' . $orden_campo;
		} ?><?php if ($orden_dir != 0) {
			echo '&od=' . $orden_dir;
		} ?><?php if ($inactivo != 0) {
			echo '&e=' . $inactivo;
			} ?>';
	});

	function eliminar() {
		if (confirm('¿Esta seguro que desea eliminar los elementos seleccionados?')) {
			$('#form_listado').submit();
		}
	}
</script>