<?php

/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/

error_reporting(E_ERROR | E_PARSE);

header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Cache-Control: no-cache");
header("Pragma: no-cache");

require_once("config/config.inc.php");
require_once("includes/database.php");
require_once("includes/funciones.php");
require_once("includes/resize-class.php");
require_once("includes/image.php");
require_once("includes/class.phpmailer.php");

session_start();

date_default_timezone_set("America/Montevideo");

if (isset($_GET['m']) && ($_GET['m'] == 'l')) { // MODULO LOGIN
    require_once("login.php");
    exit();
}

if (isset($_GET['m']) && ($_GET['m'] == 'oc')) { // MODULO LOGIN
    require_once("olvido_clave.php");
    exit();
}

if (isset($_GET['m']) && ($_GET['m'] == 'nc')) { // MODULO LOGIN
    require_once("nueva_clave.php");
    exit();
}

require_once("includes/chk_login.php");

// **********************************************************************************

//var_dump($config['modulos']);
//die();

foreach ($config['modulos'] as $modulo) {
    require_once('modulos/' . $modulo . '/config.inc.php');
}




if ($_GET['m'] != '') {
    $get_m = $_GET['m'];
} else {
    $get_m = $config['pagina_defecto'];
}

if ($get_m == 'd') { // MODULO DOWNLOAD
    $sistema_iniciado = true;

    require_once("download.php");
    exit();
} else {

    list($key_modulo, $archivo) = explode('_', $get_m);
    if (isset($sistema['modulos'][$key_modulo]['paginas'][$archivo])) {

        $sistema_iniciado = true;

        $modulo = $sistema['modulos'][$key_modulo];

        if (isset($_SESSION[$config['codigo_unico']]['login_permisos'][$key_modulo]) && ($_SESSION[$config['codigo_unico']]['login_permisos'][$key_modulo] > 0)) {

            require_once('modulos/' . $key_modulo . '/' . $sistema['modulos'][$key_modulo]['paginas'][$archivo]);
        } else {

            if ($get_m != $config['pagina_defecto']) {

                header('Location: ?');
            }
        }

        exit();
    } else {

        exit();
    }
}


exit();
?>