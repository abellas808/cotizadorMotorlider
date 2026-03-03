<!DOCTYPE html>
<?php
include('./config.php');
ob_start();

?>
    <table border="1">
        <thead>
            <tr>                
                <th>Id.</th>
                <th>Id_marca</th>
                <th>Nombre</th>
            </tr>
        </thead>
<?php

// File name
$filename="EmpData";

$connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$connection->set_charset('utf8');

$query = "SELECT * FROM act_marcas";
$res = $connection->query($query);
$data = $res->fetch_all(MYSQLI_ASSOC);
if($res->num_rows > 0 ) {
    foreach($data as $marca) {
    ?>
        <tr>       
            <td><?php echo $marca['id'];?></td>
            <td><?php echo $marca['id_marca'];?></td>
            <td><?php echo $marca['nombre'];?></td>
        </tr>
    <?php

    // Genrating Execel  filess
    // header("Content-type: application/octet-stream");
    header("Content-type: text/xml");
    header("Content-Disposition: attachment; filename=".$filename."-Report.xls");
    header("Pragma: no-cache");
    header("Expires: 0");    
    }
}
ob_end_flush();
 ?>
</table>