<?php

session_start();

include('./../../config.php');
include('./../../config/config.inc.php');
include('./../../adm/includes/funciones.php');
include('./../../adm/includes/class.phpmailer.php');

$connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$connection->set_charset('utf8');

$modelo = $_POST['modelo'];
$marca = $_POST['marca'];
$anio = $_POST['anio'];
$familia = $_POST['familia'];
$nombre = $_POST['nombre'];
$email = $_POST['email'];
$telefono = $_POST['telefono'];
$auto = $_POST['auto'];
$fecha = $_POST['fecha'];
$hora = $_POST['hora'];
$sucursal = $_POST['sucursal'];
$id_cotizacion = $_POST['id_cotizacion'];

if ($nombre != '' && $fecha != '' && $hora != '') {


	$punto_guion = array(".", "-", " ");
	$existe_agenda = $connection->query('SELECT * FROM agendas WHERE id_cotizacion = "' . ($id_cotizacion) . '" AND fecha > NOW() ORDER BY fecha DESC;');
	$ea = $existe_agenda->fetch_array(MYSQLI_ASSOC);
	$fecha_actual = date("Y-m-d");

	if (!$ea) {
		// Obtengo las variables necesarias segun las entradas recibidas para realizar los inserts
		$sucursalesList = 'SELECT * FROM agenda_sucursal WHERE id_sucursal = "' . ($sucursal) . '"';
		$sucursales = $connection->query($sucursalesList);
		$sucursales = $sucursales->fetch_array(MYSQLI_ASSOC);
		$suc_id = $sucursales['id_sucursal'];
		$suc_name = $sucursales['nombre'];
		$suc_direccion = $sucursales['direccion'];
		$suc_email = $sucursales['email'];
		$suc_telefono = $sucursales['telefono'];
		$suc_ubicacion = $sucursales['ubicacion'];

		$mailSolicitante = $email;
		$nameSolicitante = $nombre;


		$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charsLength = strlen($chars);
		$rand_string = '';
		for ($i = 0; $i < 50; $i++) {
			$rand_string .= $chars[rand(0, $charsLength - 1)];
		}

		//Insertar en tbl agenda_reservaciones
		$cero = 0;
		$na = 'N/A';
		$insertar = $connection->prepare("INSERT INTO agendas (`id_sucursal`, `fecha`, `hora`, `modelo`, `marca`, `anio`, `familia`, `auto`, `nombre`, `ci`, `email`, `telefono`, `rand_string`, `direccion`, `inspeccion_domiciliaria`, `id_cotizacion`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
		$insertar->bind_param("ssssssssssssssss",$sucursal,$fecha,$hora,$modelo,$marca,$anio,$familia,$auto,$nombre,$cero,$email,$telefono,$rand_string,$na,$cero,$id_cotizacion);
		if ($insertar->execute()) { 

			$cotizacion = $connection->query('SELECT * FROM cotizaciones_generadas WHERE id_cotizaciones_generadas = ' . $id_cotizacion);
			$cotizacion = $cotizacion->fetch_array(MYSQLI_ASSOC);

			$mailP = new PHPMailer(true);
			//$mailP->isMail();
			// Configuramos el protocolo SMTP con autenticación	
			//$mailP->IsSMTP(false);
			// $mailP->Host       = "";
			// $mailP->SMTPAuth   = true;
			// $mailP->Username   = "noresponder@sodio.com.uy";
			// $mailP->Password   = "";
			// $mailP->SMTPSecure = "ssl";
			// $mailP->Port 	  = 465;
			// Configuración cabeceras del mensaje	
			$mailP->isHTML(true);
			$mailP->From = "noresponder@motorliderweb.com.uy";
			$mailP->FromName = "MOTORLIDER";
			$mailP->AddAddress($mailSolicitante, $nameSolicitante);
			//$mailP->addBCC('info@motorlider.com.uy');
			$mailP->addBCC('marcos.ingold@motorlider.com.uy');
			$mailP->addBCC('adm.motorlider@gmail.com');
			//$mailP->addBCC('santiago@sodio.com.uy');
			//$mailP->addBCC('daniel@sodio.com.uy');
			//$mailP->addBCC('gfigueroa.ac@gmail.com');
			$mailP->Subject = "Reserva de Agenda MOTORLIDER";
			$mailP->CharSet = "UTF-8";
			$mailP->AddEmbeddedImage("./../../img/logo.png", "my-attach", "logo.png");
			$mailP->AddEmbeddedImage("./../../img/mapa.png", "my-attach2", "mapa.png");
			// Cuerpo del mensaje
			$messageP = '<div class="jumbotron text-center">
			<div style="text-align: left; margin-top: 20px;">
			<img style="height: 50px !important" src="cid:my-attach"> 
			</div>
			<p>Te recordamos que tienes una fecha reservada para presentarte en nuestra sucursal.</p>
			</div>
			<div class="container">
			<div class="row">
			<div class="col-sm-8">
			<h2 style="font-size: 2em; color: #041e42">Detalle de Agenda:</h2>
			<p><strong><font face="Arial">Nombre del cliente:</strong> ' . $nombre . '</font></p>
			<p><strong><font face="Arial">Cotizacion:</strong> ' . $id_cotizacion . '</font></p>
			<p><strong><font face="Arial">Fecha:</strong> ' . strftime('%d/%m/%Y', strtotime($fecha)) . '</font></p>
			<p><strong><font face="Arial">Hora:</strong> ' . $hora . '</font></p>
			<p><strong><font face="Arial">Automóvil:</strong> ' . $auto . '</font></p>
			<p><strong><font face="Arial">Cotización:</strong> ' . $cotizacion['msg'] . '</font></p>
			<p><strong><font face="Arial">Sucursal:</strong> ' . str_s($suc_name) . '</font></p>
			<p><strong><font face="Arial">Dirección Sucursal:</strong> ' . str_s($suc_direccion) . '</font></p>
			<p><a href="'.$suc_ubicacion.'"><img style="height: 120px !important" src="cid:my-attach2"><a></p>
			<p><strong><font face="Arial">Email Sucursal:</strong> ' . str_s($suc_email) . '</font></p>
			<p><strong><font face="Arial">Teléfono Sucursal:</strong> ' . str_s($suc_telefono) . '</font></p>';
			//$messageP .= '<p><strong><font face="Arial"><a href="' . $config['sitio'] . $config['base_url_web'] . 'eliminar_agenda/' . $rand_string . ">Eliminar agenda</a></strong></font></p>
			$messageP .= '</div>
			</div>
			</div>
			</div>';
			$mailP->Body = $messageP;

			$hora_aux = explode(':', $hora);
			$hora_ini = $hora_aux[0] + 3;
			if($hora_aux[1] == '00'){
				$hora_fin = $hora_ini.':30';
			}else{
				$hora_fin = ($hora_ini+1).':00';
			}

			$hora_ini = $hora_ini .':'.$hora_aux[1];


			date_default_timezone_set('America/Montevideo');

		    $name = 'Reserva de Agenda MOTORLIDER';
		    $start = $fecha.' '.$hora_ini.':00';
		    $end = $fecha.' '.$hora_fin.':00';
		    $description = 'Agenda revisión Mecánica';
		    $location = $suc_direccion;
     		$ical = "BEGIN:VCALENDAR\nVERSION:2.0\nMETHOD:PUBLISH\nBEGIN:VEVENT\nORGANIZER;CN=MOTORLIDER:MAILTO:info@motorlider.com.uy\nDTSTART;TZID='America/Montevideo':".date("Ymd\THis\Z",strtotime($start))."\nDTEND;TZID='America/Montevideo':".date("Ymd\THis\Z",strtotime($end))."\nLOCATION:".$location."\nTRANSP: OPAQUE\nSEQUENCE:0\nUID:\nDTSTAMP;TZID='America/Montevideo':".date("Ymd\THis\Z")."\nSUMMARY:".$name."\nDESCRIPTION:".$description."\nPRIORITY:1\nCLASS:PUBLIC\nBEGIN:VALARM\nTRIGGER:-PT10080M\nACTION:DISPLAY\nDESCRIPTION:Reminder\nEND:VALARM\nEND:VEVENT\nEND:VCALENDAR\n";

     		file_put_contents($name.".ics",$ical); 

		    //set correct content-type-header 
		    //header('Content-type: text/calendar; charset=utf-8'); 
		    //header('Content-Disposition: inline; filename=calendar.ics');
		    //echo $ical; 
		    //exit; 

		    $mailP->AddAttachment($name.".ics");

			if ($mailP->send()) {
				unlink($name.".ics");
				echo 1;
			} else {
				echo 0;
			}

		} else {
			echo 3;
		}
	} else {

		echo 2;
	}
}