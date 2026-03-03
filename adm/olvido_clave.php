<?php

session_start();

session_unset();

if ($_POST['cmd'] == 'ec') {

	$msg_login = 'ec';

	if ($usuario = $db->query_first('select * from admin_usuarios where email = "' . $db->escape($_POST['campo1']) . '"')) {

		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		$codigo = substr(str_shuffle($chars), 0, 8) . $usuario['id'];

		$db->query_update('admin_usuarios', array('codigo_oc' => $codigo, 'fecha_oc' => date("Y-m-d H:i:s", strtotime('+1 day'))), 'id = "' . $usuario['id'] . '"');



		$html_mail = '
<!doctype html>
<html>

<head>
  <meta name="viewport" content="width=device-width">
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <title><?php echo get_empresa(); ?></title>
  <style>
    @media only screen and (max-width: 620px) {
      table[class=body] h1 {
        font-size: 28px !important;
        margin-bottom: 10px !important;
      }

      table[class=body] p,
      table[class=body] ul,
      table[class=body] ol,
      table[class=body] td,
      table[class=body] span,
      table[class=body] a {
        font-size: 16px !important;
      }

      table[class=body] .wrapper,
      table[class=body] .article {
        padding: 10px !important;
      }

      table[class=body] .content {
        padding: 0 !important;
      }

      table[class=body] .container {
        padding: 0 !important;
        width: 100% !important;
      }

      table[class=body] .main {
        border-left-width: 0 !important;
        border-radius: 0 !important;
        border-right-width: 0 !important;
      }

      table[class=body] .btn table {
        width: 100% !important;
      }

      table[class=body] .btn a {
        width: 100% !important;
      }

      table[class=body] .img-responsive {
        height: auto !important;
        max-width: 100% !important;
        width: auto !important;
      }
    }

    @media all {
      .ExternalClass {
        width: 100%;
      }

      .ExternalClass,
      .ExternalClass p,
      .ExternalClass span,
      .ExternalClass font,
      .ExternalClass td,
      .ExternalClass div {
        line-height: 100%;
      }

      .apple-link a {
        color: inherit !important;
        font-family: inherit !important;
        font-size: inherit !important;
        font-weight: inherit !important;
        line-height: inherit !important;
        text-decoration: none !important;
      }

      .btn-primary table td:hover {
        background-color: #34495e !important;
      }

      .btn-primary a:hover {
        background-color: #34495e !important;
        border-color: #34495e !important;
      }
    }
  </style>
</head>

<body class="" style="background-color: #fff; font-family: arial; -webkit-font-smoothing: antialiased; font-size: 14px; line-height: 1.4; margin: 0; padding: 0; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%;">
  <table border="0" cellpadding="0" cellspacing="0" class="body" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #fff; width: 100%;" width="100%" bgcolor="#fff">
    <tr>
      <td style="font-family: arial; font-size: 14px; vertical-align: top;" valign="top">&nbsp;</td>
      <td class="container" style="font-family: arial; font-size: 14px; vertical-align: top; display: block; max-width: 580px; padding: 0 10px 10px 10px; width: 580px; Margin: 0 auto;" width="580" valign="top">
        <div class="content" style="box-sizing: border-box; display: block; margin: 0 auto; max-width: 580px; padding: 0 10px 10px 10px;">

          <!-- START CENTERED WHITE CONTAINER -->
          <div class="logo" style="height: 150px;"><img src="' . $config['url_sitio'] . 'assets/img/logo/logo.png" style="border: none; -ms-interpolation-mode: bicubic; max-width: 65%;"></div>
          <table class="main" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; background: #fff; border-radius: 3px; width: 100%;" width="100%">

            <!-- START MAIN CONTENT AREA -->
            <tr>
              <td class="wrapper" style="font-family: arial; font-size: 14px; vertical-align: top; box-sizing: border-box; padding: 20px;" valign="top">
                <table border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 100%;" width="100%">
                  <tr>
                    <td style="font-family: arial; font-size: 14px; vertical-align: top;" valign="top">
                      <p style="font-family: arial; font-size: 14px; font-weight: normal; margin: 0; Margin-bottom: 15px;">Cambio de contraseña</p>
                    </td>
				  </tr>
				  <tr>
				  	<td style="font-family: arial; font-size: 14px; vertical-align: top; border-radius: 5px; text-align: center; background-color: #3498db;" valign="top" align="center" bgcolor="#3498db"> <a href="' . $config['url_sitio'] . 'administrador_shell/?m=nc&c=' . $codigo . '"" target="_blank" style="border-radius: 5px; box-sizing: border-box; cursor: pointer; display: inline-block; font-size: 14px; font-weight: bold; margin: 0; padding: 15px 12px; text-decoration: none; text-transform: capitalize; background-color: #e52830; color: #ffffff;">Cambie la contraseña</a> </td>
				  </tr>
				  <tr>
				  	<td style="font-family: arial; font-size: 14px; vertical-align: top;" valign="top">
                      <p style="font-family: arial; font-size: 14px; font-weight: normal; margin: 0; Margin-bottom: 15px;">Si no pidió para cambiar su clave ignore este email.</p>
                    </td>
				  </tr>
                </table>
              </td>
            </tr>
            <hr>
            <!-- END MAIN CONTENT AREA  -->
          </table>
          <!-- START FOOTER -->
          <div class="footer" style="clear: both; Margin-top: 10px; text-align: center; width: 100%;">
            <table border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 100%;" width="100%">
              <tr>
                <td class="content-block" style="font-family: arial; vertical-align: top; padding-bottom: 10px; padding-top: 10px; color: #999999; font-size: 12px; text-align: center;" valign="top" align="center">
                  <span class="apple-link" style="color: #999999; font-size: 12px; text-align: center;">' . $config['empresa'] . '</span>
                  <br><a href="https://<?php echo get_sitio(); ?>/" style="text-decoration: underline; color: #999999; font-size: 12px; text-align: center;">' . $config['sitio'] . '</a>
                </td>
              </tr>
            </table>
          </div>
          <!-- END FOOTER -->

          <!-- END CENTERED WHITE CONTAINER -->
        </div>
      </td>
      <td style="font-family: arial; font-size: 14px; vertical-align: top;" valign="top">&nbsp;</td>
    </tr>
  </table>
</body>

</html>';

		$mail = new PHPMailer(true);
		//Recipients
		$mail->Port = 25;
		$mail->SMTPSecure = 'ssl';
		$mail->setFrom('no-responder@sodio.com.uy', $config['empresa']);
		$mail->addAddress($usuario['email']);
		// Content
		$mail->isHTML(true);
		$mail->Subject = utf8_decode('Olvidé mi clave - ' . $config['empresa']);
		$mail->Body = $html_mail;
		$mail->send();
	}
}

?>
<!DOCTYPE html>
<html>

<head>
	<meta charset="utf-8">
	<title><?php echo $config['nombre']; ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="description" content="">
	<meta name="author" content="">
	<link href="css/bootstrap.min.css" rel="stylesheet">
	<!--[if lt IE 9]>
      <script src="js/html5shiv.js"></script>
    <![endif]-->
	<style>
		body {
			background-color: #fff;
		}

		.box {
			color: #222;
			height: 300px;
			width: 350px;
			position: absolute;
			left: 50%;
			top: 50%;
			margin: -150px 0 0 -166px;
		}

		.frmbody {
			padding: 10px 56px;

		}

		.frmfooter {
			margin-left: -11px;
			padding: 7px 26px;
			height: 50px;
			text-align: center;
		}

		.box a {
			color: #222;
		}

		.box a:hover,
		.box a:focus {
			text-decoration: underline;
		}

		.box a:active {
			color: #f84747;
		}

		#contenedor {

			left: 0;
			top: 0;
			right: 0;
			bottom: 0;
			min-height: 500px;
			min-width: 900px;
		}

		.acceso {
			cursor: pointer;
			text-decoration: underline;
			color: #222;
			margin-top: 20px;
			font-size: 12px;
		}

		label {
			margin-top: 20px;
		}
	</style>
	<script type="text/javascript" src="js/jquery-1.8.3.min.js"></script>
	<script type="text/javascript">
		function ec() {
			if ($('campo1').val() != '') {
				$('#fl').attr('action', '?m=l&p=ec');
				$('#fl').submit();
			} else {
				alert('No ingreso su email.');
			}
		}
	</script>

</head>

<body>
	<div id="contenedor">
		<form id="fl" action="?m=oc" method="post" class="box">
			<input name="cmd" type="hidden" id="cmd" value="ec" />
			<div class="frmbody">
				<img src="img/logo.svg" width="200">
				<label>Email</label>
				<input name="campo1" type="text" id="campo1" tabindex="1" value="<?php echo_s($_COOKIE[$config['codigo_unico'] . '_' . 'login_email']); ?>" />
			</div>
			<div class="frmfooter">
				<input type="submit" class="btn btn-small" value="Enviar email" tabindex="3">
				<?php
				if ($msg_login == 'ec') {
					echo '<br /><br />Si esta registrado<br />recibirá un email con un link para ingresar una nueva clave.';
				}
				?>
				<div class="acceso"><a href="?m=l">Ingresar</a></div>
			</div>
		</form>
	</div>
</body>

</html>