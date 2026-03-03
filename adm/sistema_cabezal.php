<?php
if (!isset($sistema_iniciado))
    exit();
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title><?php echo $config['nombre']; ?> - <?php echo $modulo['nombre']; ?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="">
        <meta name="author" content="">
        <link href="css/bootstrap.min.css" rel="stylesheet">
        <link href='https://fonts.googleapis.com/css?family=Open+Sans:400' rel='stylesheet' type='text/css'>
        <link href="css/estilos.css" rel="stylesheet">
        <link href="css/autocompletar.css" rel="stylesheet">
        <link href="css/pikaday.css" rel="stylesheet">
        <link href="css/select2.css" rel="stylesheet">
        <!--[if lt IE 9]>
              <script src="js/html5shiv.js"></script>
            <![endif]-->
        <script type="text/javascript" src="js/jquery-1.8.3.min.js"></script>
        <link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css">

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
<script type="text/javascript" src="js/moment.min.js"></script>
<script type="text/javascript" src="js/pikaday.min.js"></script>
<script type="text/javascript"  src="js/ckeditor/ckeditor.js"></script>
<script type="text/javascript" src="js/select2.js"></script>

