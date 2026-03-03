<?php

$base  = "http://" . $_SERVER['HTTP_HOST'];
$base .= str_replace(basename($_SERVER['SCRIPT_NAME']), "", $_SERVER['SCRIPT_NAME']);
$config['base_url'] = $base;

$config['base_url_web'] = '/';

$config['imagenes_url'] = '../assets/';
$config['imagenes_url_sitio'] = $config['base_url_web'] .'assets/';
$config['url_sitio'] = explode('adm', $base)[0];


$config['nombre'] = "Admin-MotorLider";

$config['codigo_unico'] = "catadm";

//$config['sitio'] = "https://www.motorlider.com.uy";
$config['sitio'] = "https://motorliderapi.z.actotal.net/";
$config['empresa'] = "MotorLider";
$config['mail'] = 'no_responder@com.uy';


// ***********************************************************************************************

/*
$config['db_server'] = "localhost";
$config['db_user'] = "sodiotest";
$config['db_pass'] = "sodio.5Jhen73kaQ.TEST";
$config['db_database'] = "sodiotes_motorlider";
*/

$config['db_server'] = "localhost";
$config['db_user'] = "marcos2022_usr_api";
$config['db_pass'] = "_eT4AjJ79~tX]*h)J5";
$config['db_database'] = "marcos2022_api";


$config['iva'] = 1.22;

/************* SISTARBANC ****************/

$config['url_sistarbanc_test'] = 'https://spftest.sistarbanc.com.uy/spfe/servlet/PagoEmpresa';
$config['url_callback_sistarbanc_test'] = 'https://sodiotest.com/motorlider/callback_sistarbanc';

$config['url_sistarbanc'] = 'https://spf.sistarbanc.com.uy/spfe/servlet/PagoEmpresa';
$config['url_callback_sistarbanc'] = 'https://motorlider.com.uy/callback_sistarbanc';

$config['banco_sistarbanc'] = '009';
$config['organismo_sistarbanc'] = 'MOTOLIDER';
$config['tipo_servicio_sistarbanc'] = 'VTOL';



/**************** MERCADO PAGO ******************/


//////


$config['dir_archivos'] = "archivos/";

// ***********************************************************************************************

$config['pagina_cant'] = 20;

// ***********************************************************************************************

$config['ext_no'] = array("PHP", "PHP3", "PHP4", "PHP5", "HTM", "HTML", "JS", "EXE", "APP", "SH", "PY");

// ***********************************************************************************************

//$config['modulos'][] = 'res';
$config['modulos'][] = 'wel';
$config['modulos'][] = 'cot';
$config['modulos'][] = 'coi';
$config['modulos'][] = 'age';
//$config['modulos'][] = 'dol';
//$config['modulos'][] = 'usu';
//$config['modulos'][] = 'ban';
//$config['modulos'][] = 'mar';
//$config['modulos'][] = 'mod';
//$config['modulos'][] = 'tau';
//$config['modulos'][] = 'aut';
//$config['modulos'][] = 'not';
//$config['modulos'][] = 'tas';
//$config['modulos'][] = 'fin';
$config['modulos'][] = 'pvv';
$config['modulos'][] = 'pds';
$config['modulos'][] = 'aud';
$config['modulos'][] = 'usd';
//$config['modulos'][] = 'fcot';
//$config['modulos'][] = 'nov';

//$config['modulos'][] = 'nos';
//$config['modulos'][] = 'prf';

//$config['modulos'][] = 'tyc';
//$config['modulos'][] = 'pdp';
//$config['modulos'][] = 'lwm';
//$config['modulos'][] = 'pdc';

//$config['modulos'][] = 'suc';

$config['modulos'][] = 'usa';
$config['modulos'][] = 'miu';

// ***********************************************************************************************

$config['pagina_defecto'] = 'miu_m';

$config['imagenes']['productos'] = array(
  'img_width' => 600,
  'img_height' => 656,
  'img_width_th' => 320,
  'img_height_th' => 364,
);

$config['imagenes']['novedades'] = array(
  'img_width' => 1200,
  'img_height' => 800,
  'img_width_th' => 570,
  'img_height_th' => 364,
);

$config['imagenes']['premios'] = array(
  'img_width' => 600,
  'img_height' => 600,
  'img_width_th' => 520,
  'img_height_th' => 580,
);

$config['imagenes']['automoviles'] = array(
  'img_width' => 1920,
  'img_height' => 1080,
  'img_width_mb' => 800,
  'img_height_mb' => 1700,
  'img_width_th' => 570,
  'img_height_th' => 364,
);



$config['iva'] = 22;


/// ************** AUTOS ********************* ///

$config['automoviles']['cambios'] = array(
  1 => 'Manual',
  2 => 'Automático'
);

$config['automoviles']['combustible'] = array(
  1 => 'Nafta',
  2 => 'Gasoil',
  //3 => 'Eléctrico'
);

$config['automoviles']['categoria'] = array(
  1 => 'Nuevo',
  2 => 'Usado'
);


$config['automoviles']['caracteristicas']['seguridad'] = array(
  'Airbag conductor',
  'Airbag para conductor y pasajero',
  'Alarma',
  'Apoya cabeza en asientos traseros',
  'Control de estabilidad',
  'Faros antinieblas delanteros',
  'Frenos ABS',
  'Sistema de bloqueo de encendido',
  'Sistema ISOFIX',
  'Tercera luz de freno led',
  'Repartidor electrónico de fuerza de frenado',
  'Faros con regulación automática'
);

$config['automoviles']['caracteristicas']['confort'] = array(
  'Aire acondicionado',
  'Cierre centralizado de puertas',
  'Alarma de luces encendidas',
  'Apertura remota de puertas',
  'Asiento conductor regulable en altura',
  'Sensor de estacionamiento',
  'Asiento trasero rebatible',
  'Computadora de abordo',
  'Cristales eléctricos',
  'Control eléctrico para los retrovisores',
  'GPS',
  'Regulación de altura del volante',
  'Comando remoto para radio en el volante',
  'Apertura remota de baúl'
);

$config['automoviles']['caracteristicas']['exterior'] = array(
  'Limpia/lava luneta',
  'Llantas de aleación',
  'Paragolpes pintados',
);

$config['automoviles']['caracteristicas']['sonido'] = array(
  'AM/FM',
  'Bluetooth',
  'Entrada auxiliar',
  'Entrada USB',
  'Reproductor de MP3',
  'CD',
  'Tarjeta SD'
);


$config['costo_reserva'] = 15000;


$config['seguros'] = 'Mapfre';