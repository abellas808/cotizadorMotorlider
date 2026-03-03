<?php 
ini_set('memory_limit', '-1');

date_default_timezone_set('America/Montevideo');

$dir = 'excels/'.date("Y").'/'.date("m").'/'.date("d");
if ($handler = opendir($dir)) {
	$allCSV = array();
    while (false !== ($file = readdir($handler))) {
        if (strpos($file, '.csv') !== false) {
            $allCSV[] = $file;
        }
    }
    closedir($handler);
}

joinFiles($allCSV, __DIR__ .'/excels/'.date("Y").'/'.date("m").'/'.date("d").'/ZZZ.csv');

function joinFiles(array $files, $result) {
    if(!is_array($files)) {
        throw new Exception('`$files` must be an array');
    }

    $wH = fopen($result, "w+");

    $header = false;
    sort($files);
    foreach($files as $file) {
        $csv = file_get_contents((dirname(__FILE__)) . '/excels/'.date("Y").'/'.date("m").'/'.date("d").'/'.$file);
        $lines = explode("\n", $csv);

        $line_number = 0;
        foreach ($lines as $line) {
            if(!$header){
                if($line != ''){
                    fwrite($wH, $line."\n");
                }
                $header = true;
            } else {
                if($line_number == 0){
                    $line_number++;
                    continue;
                } else {
                    $line_number++;
                    if($line != ''){
                        if($line_number == (count($lines)-1)){
                            fwrite($wH, $line."\n\n");
                        } else {
                            fwrite($wH, $line."\n");
                        }
                    }
                }
            }
        }

        fwrite($wH, ""); //usually last line doesn't have a newline
    }
    
    fclose($wH);
    unset($wH);
}

//Rename CSV
$name = __DIR__ .'/excels/'.date("Y").'/'.date("m").'/'.date("d").'/ZZZ.csv';
$rename = __DIR__ .'/excels/'.date("Y").'/'.date("m").'/'.date("d").'/Vehiculos.csv';
rename ($name, $rename);

sendemail();

//send mail
function sendemail(){
    $to = 'ventas@motorlider.com.uy';
    $subject = 'MotrLider - Excels generados a  Motorlider - Excels generados';

    $message = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
    <html lang="en">
    <head>
    
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title></title>

    <style type="text/css">
    </style>    
    </head>
    <body style="margin:0; padding:50px; background-color:#f1f1f1;">
    <center>
        <table width="90%" border="0" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="padding: 20px; border-radius: 4px;">
            <tr>
                <td align="center" valign="top">
                    <table width="100%" style="border-collapse: collapse; margin: 20px 0">
                        <tbody>
                            <tr>
                                <td rowspan="2"><h1 style="font-weight: 300;font-size: 22px;line-height: 1em;margin: 0;">Excels Generados</h1></td>
                            </tr>
                            <tr>
                                <td colspan="2"><h1 style="font-weight: 300;font-size: 22px;line-height: 1em;margin: 0;text-align: right;">'.date("d/m/Y (h:i:s A)").'</h1></td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
            <tr>
                <td align="center" valign="top">
                    <table width="100%" style="border-collapse: collapse;">
                        <tbody>
                            <tr>
                                <td><h2 style="font-weight: 300;font-size: 19px;line-height: 1em;">Excel Completo</h2></td>
                            </tr>
                            <tr>
                                <td>
                                    <a target="_blank" href="https://carplay.uy/excels/'.date("Y").'/'.date("m").'/'.date("d").'/Vehiculos.csv">Vehiculos</a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
            <tr>
                <td align="center" valign="top">
                    <table width="100%" style="border-collapse: collapse;">
                        <tbody>
                            <tr>
                                <td style="text-align: center;padding: 25px 0 10px 0;"><img width="180px" height="auto" src="https://f.fcdn.app/assets/commerce/motorlider.com.uy/d510_2cdb/public/web/img/logo.svg"></td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
    </center>
    </body>
    </html>';

    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=iso-8859-1';
    $headers[] = 'From: MotorLider <info@motorlider.com.uy>';
    $headers[] = 'Bcc: gfigueroa.ac@gmail.com';

    mail($to, $subject, $message, implode("\r\n", $headers));
}