<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

//header('Access-Control-Allow-Origin: *');
//> comentar la siguiente línea para salir de modo testing y afectar la plataforma Recargas >
$modoTesting = true;
$urlBase = '';
// ^ ^ ^
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \OAuth2\GrantType\RefreshToken;
use \OAuth2\GrantType\ClientCredentials;
use \OAuth2\Request as Oauth2Request;
use \OAuth2\Storage\Pdo as PdoStorage;
use \Chadicus\Slim\OAuth2\Middleware\Authorization;
header('Access-Control-Allow-Headers: authorization,content-type');
//ini_set('display_errors', 1);
//error_reporting(E_ALL);

loadEnv(__DIR__ . '/.env');
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/bshaffer/oauth2-server-php/src/OAuth2/Autoloader.php';
require __DIR__ . '/src/db.php';
require __DIR__ . '/src/classes.php';
require_once __DIR__ . '/services/ApiCotizadorApify.php';
require_once __DIR__ . '/services/MeliScraperService.php';
require_once __DIR__ . '/services/MailService.php';
require_once __DIR__ . '/services/CotizacionService.php';


// config defaults
$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = false;
$app = new \Slim\App(["settings" => $config]);
// Oauth

// ------- CUIDADO con urlBase y entorno hay que cambiar para que no afecte la subida a PROD ------- //

global $urlBase;
if($_SERVER['HTTP_HOST'] == 'apicotizador.local'){//Dominio Virtual local
	$pdoStoragetSettings = [
		'dsn' => 'mysql:dbname=api_cotizador;host=localhost',
		'username' => 'root',
		'password' => ''
	];
	$urlBase = "http://localhost/apiml/";
}else{//Infopel Server
	$pdoStoragetSettings = [
		'dsn' => 'mysql:dbname=marcos2022_api_cotizador;host=localhost',
		'username' => 'marcos2022_usr_api',
		'password' => '_eT4AjJ79~tX]*h)J5'
	];
	$entorno = 't';
	$urlBase = "https://carplay.uy/";
}

$appServerDefaultSettings = [
    'allow_implicit' => true,
	'always_issue_new_refresh_token' => true,
    'refresh_token_lifetime' => 2592000,
    'access_lifetime' => 2592000
];
$appClientCredentialsSettings = [
    'always_issue_new_refresh_token' => true,
];
\OAuth2\Autoloader::register();
$storage = new PdoStorage($pdoStoragetSettings);
$server = new \OAuth2\Server($storage, $appServerDefaultSettings);
$server2 = new \OAuth2\Server($storage, $appServerDefaultSettings, [new ClientCredentials($storage, $appClientCredentialsSettings)]);
$authorization = new Authorization($server2, $app->getContainer());
// api homepage
$app->get('/', function (Request $request, Response $response) {
	global $modoTesting;
    try{
		if($modoTesting){
			$return = $response->withJson(['msg'=>'[Modo Testing] Bienvenido a la portada de la API Recargas. Refierase a la documentacion para conectar con la API en las URI correspondientes.'],200);
		}else{
			$return = $response->withJson(['msg'=>'Bienvenido a la portada de la API Recargas. Refierase a la documentacion para conectar con la API en las URI correspondientes.'],200);
		}
    }catch (Exception $e){
        $return = $response->withJson(['error'=>$e->getMessage()],500);
	}
    
	// log this call!
	$log_data = array();
	$log_data['token'] = 'nontokenaccess';
	$log_data['ip'] = $_SERVER['REMOTE_ADDR'];
	$log_data['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
	$log_data['request_method'] = $request->getMethod();
	$log_data['request_uri'] = $request->getUri();
	$log_data['request_header'] = print_r($request->getHeaders(),1);
	$log_data['request_vars'] = str_replace("(    ","(",str_replace("\n","",print_r($data,1)));
	$log_data['request_body'] = $request->getBody();
	$log_data['response_statuscode'] = $response->getStatusCode();
	$log_data['response_header'] = print_r($response->getHeaders(),1);
	$log_data['response_body'] = $return;
	$log = new Log($log_data);
	$log->save();
    return $return;
});

$app->get('/marcas', function (Request $request, Response $response) {
	global $modoTesting;
	global $urlBase;

    try{
        $token = obtenerTokenRequest($request);

		if($modoTesting){
			$token = "[Modo Testing] ".$token;

        	$urlRecarga = $urlBase."ws/brands";
	        
	        $curl = curl_init($urlRecarga);
	        curl_setopt($curl, CURLOPT_URL, $urlRecarga);
	        curl_setopt($curl, CURLOPT_POST, true);
	        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); 
	        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

	        $respCotizadorRaw = curl_exec($curl);
	        $curlError = curl_error($curl);

	        curl_close($curl);

	        if ($curlError) {
				$return = $response->withJson([
					'msg' => 'Error CURL: '.$curlError,
					'error' => 500
				], 500);
				return $return;
	        }

	        $respCotizador = json_decode($respCotizadorRaw);

	        if (!$respCotizador) {
				$return = $response->withJson([
					'msg' => 'Respuesta inválida del WS /brands',
					'error' => 500,
					'raw' => $respCotizadorRaw
				], 500);
				return $return;
	        }

			if($respCotizador->codigo === 500){
				$return = $response->withJson([
					'msg'=>$respCotizador->mensaje,
					'error'=>$respCotizador->error
				],500);
			} else {
				$return = $response->withJson([
					'msg'=>$respCotizador->mensaje,
					'error'=>0,
					'brands'=>$respCotizador->brands
				],200);
			}

		} else {
			$return = $response->withJson([
				'msg'=>'Modo productivo sin implementar',
				'error'=>1
			],500);
		}
    } catch (Exception $e) {
        $return = $response->withJson(['error'=>$e->getMessage()],500);
	}

    return $return;
});

// MODELOS
$app->post('/modelos/{brand}', function (Request $request, Response $response) {
	global $modoTesting;
	global $urlBase;
    
    
    // Capturamos body UNA vez
    $rawBody = (string)$request->getBody();
    $brand = $args['brand'] ?? $request->getAttribute('brand') ?? '';

     // 🔵 LOG DE ENTRADA (para confirmar que entra)
    registrarApiLog($request, null, [
        'rawBody' => $rawBody,
        'tag' => 'MODELOS - ENTRO',
        'response_statuscode' => 0,
        'response_body' => ''
    ]);


    try{
		$brand = $request->getAttribute('brand');
        $token = obtenerTokenRequest($request);
		
		if($modoTesting){
			$token = "[Modo Testing] ".$token;
        	//mail('gfigueroa.ac@gmail.com','[API Recargas] Nueva solicitud de /recargachip/'.$mid,"Saldo: \"".$saldo."\" para el MID: ".($mid).".\nPDV: ".$pdv."\nVendedor: ".$vendedor."\nReferencia/datos extra: ".$referencia."\n\n\nRecibido el: ".date('d-m-Y H:i'));

			$data = array(
        		'brand'=>$brand
            );   
        	
			$urlRecarga = $urlBase."ws/models";
	        
	        $curl = curl_init($urlRecarga);
	        curl_setopt($curl, CURLOPT_URL, $urlRecarga);
	        curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));
	        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); 
	        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

	        $respCotizador = json_decode(curl_exec($curl));

	        curl_close($curl);

			if($respCotizador->codigo === 500){
				$return = $response->withJson(['msg'=>$respCotizador->mensaje,"error"=>$respCotizador->error],500);
			} else {
				$return = $response->withJson(['msg'=>$respCotizador->mensaje,"error"=>0,"models"=>$respCotizador->models],200);
			}

		} else {
			$notificacion_data = array();
			$notificacion_data['tipo'] = 'recarga-exitosa';
			//$notificacion_data['titulo'] = 'Recarga solicitada para : '.($mid);
			//$notificacion_data['contenido'] = 'Se ha solicitado una recarga para '.($mid).' por parte de '.($pdv);
			//$notificacion_data['referencia'] = $referencia;
			$notificacion_data['url'] = '';
			
			$notificacion_data['estado'] = 0; // 0 = pendiente, 1 = visto, -1 = eliminada
			$notificacion = new Notificacion($notificacion_data);
			$notificacion->save();
		}
    } catch (Exception $e) {
        $return = $response->withJson(['error'=>$e->getMessage()],500);
	}
    
    // 🔵 LOG DE SALIDA REAL
    registrarApiLog($request, $return, [
        'rawBody' => $rawBody,
        'tag' => 'MODELOS - SALIDA'
    ]);

    return $return;
});

// AÑO
$app->post('/anios/{brand}', function (Request $request, Response $response) {
	global $modoTesting;
	global $urlBase;

    try{
		$brand = $request->getAttribute('brand');
        $token = obtenerTokenRequest($request);

		$data = get_object_vars(json_decode($request->getBody()));

		$check_data = true;

		$modelo = isset($data['modelo']) ? $data['modelo'] : $check_data=false;

		if(!$check_data){
        	return $response->withJson(['error'=>'Falta enviar campos, revise la documentacion.'],500);
        }
		
		if($modoTesting){
			$token = "[Modo Testing] ".$token;
        	//mail('gfigueroa.ac@gmail.com','[API Recargas] Nueva solicitud de /recargachip/'.$mid,"Saldo: \"".$saldo."\" para el MID: ".($mid).".\nPDV: ".$pdv."\nVendedor: ".$vendedor."\nReferencia/datos extra: ".$referencia."\n\n\nRecibido el: ".date('d-m-Y H:i'));

			$data = array(
        		'brand'=>$brand,
        		'model'=>$modelo
            );   
        	
			$urlRecarga = $urlBase."ws/years";
	        
	        $curl = curl_init($urlRecarga);
	        curl_setopt($curl, CURLOPT_URL, $urlRecarga);
	        curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));
	        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); 
	        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

	        $respCotizador = json_decode(curl_exec($curl));

	        curl_close($curl);

			if($respCotizador->codigo === 500){
				$return = $response->withJson(['msg'=>$respCotizador->mensaje,"error"=>$respCotizador->error],500);
			} else {
				$return = $response->withJson(['msg'=>$respCotizador->mensaje,"error"=>0,"anios"=>$respCotizador->anios],200);
			}

		} else {
			$notificacion_data = array();
			$notificacion_data['tipo'] = 'recarga-exitosa';
			//$notificacion_data['titulo'] = 'Recarga solicitada para : '.($mid);
			//$notificacion_data['contenido'] = 'Se ha solicitado una recarga para '.($mid).' por parte de '.($pdv);
			//$notificacion_data['referencia'] = $referencia;
			$notificacion_data['url'] = '';
			
			$notificacion_data['estado'] = 0; // 0 = pendiente, 1 = visto, -1 = eliminada
			$notificacion = new Notificacion($notificacion_data);
			$notificacion->save();
		}
    } catch (Exception $e) {
        $return = $response->withJson(['error'=>$e->getMessage()],500);
	}
    return $return;
});

// VERSION
$app->post('/versiones/{brand}', function (Request $request, Response $response) {
	global $modoTesting;
	global $urlBase;

    try{
		$brand = $request->getAttribute('brand');
        $token = obtenerTokenRequest($request);

		$data = get_object_vars(json_decode($request->getBody()));

		$check_data = true;

		$modelo = isset($data['modelo']) ? $data['modelo'] : $check_data=false;
		$anio = isset($data['anio']) ? $data['anio'] : $check_data=false;

		if(!$check_data){
        	return $response->withJson(['error'=>'Falta enviar campos, revise la documentacion.'],500);
        }
		
		if($modoTesting){
			$token = "[Modo Testing] ".$token;
        	//mail('gfigueroa.ac@gmail.com','[API Recargas] Nueva solicitud de /recargachip/'.$mid,"Saldo: \"".$saldo."\" para el MID: ".($mid).".\nPDV: ".$pdv."\nVendedor: ".$vendedor."\nReferencia/datos extra: ".$referencia."\n\n\nRecibido el: ".date('d-m-Y H:i'));

			$data = array(
        		'brand'=>$brand,
        		'model'=>$modelo,
				'anio'=>$anio
            );   
        	
			$urlRecarga = $urlBase."ws/versions";
	        
	        $curl = curl_init($urlRecarga);
	        curl_setopt($curl, CURLOPT_URL, $urlRecarga);
	        curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));
	        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); 
	        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

	        $respCotizador = json_decode(curl_exec($curl));

	        curl_close($curl);

			if($respCotizador->codigo === 500){
				$return = $response->withJson(['msg'=>$respCotizador->mensaje,"error"=>$respCotizador->error],500);
			} else {
				$return = $response->withJson(['msg'=>$respCotizador->mensaje,"error"=>0,"versiones"=>$respCotizador->versiones],200);
			}

		} else {
			$notificacion_data = array();
			$notificacion_data['tipo'] = 'recarga-exitosa';
			//$notificacion_data['titulo'] = 'Recarga solicitada para : '.($mid);
			//$notificacion_data['contenido'] = 'Se ha solicitado una recarga para '.($mid).' por parte de '.($pdv);
			//$notificacion_data['referencia'] = $referencia;
			$notificacion_data['url'] = '';
			
			$notificacion_data['estado'] = 0; // 0 = pendiente, 1 = visto, -1 = eliminada
			$notificacion = new Notificacion($notificacion_data);
			$notificacion->save();
		}
    } catch (Exception $e) {
        $return = $response->withJson(['error'=>$e->getMessage()],500);
	}
 
    return $return;
});

// // COTIZADOR PUBLICO MIN/MAX/PROMEDIO
// $app->post('/cotizadorPublico/{brand}', function (Request $request, Response $response, array $args = []) {
//     global $modoTesting;
//     global $urlBase;

//      // Capturamos body UNA vez
//     $rawBody = (string)$request->getBody();
//     $brand = $args['brand'] ?? $request->getAttribute('brand') ?? '';

//      // 🔵 LOG DE ENTRADA (para confirmar que entra)
//     registrarApiLog($request, null, [
//         'rawBody' => $rawBody,
//         'tag' => 'Cotizador Inicio',
//         'response_statuscode' => 0,
//         'response_body' => ''
//     ]);

//     $headers = $request->getHeaders();
//     $token = '';
//     if (isset($headers['HTTP_AUTHORIZATION'][0])) {
//         $token = $headers['HTTP_AUTHORIZATION'][0];
//     } elseif (isset($headers['Authorization'][0])) {
//         $token = $headers['Authorization'][0];
//     }

//     try {
//         $dataObj = json_decode($rawBody);
//         $data = is_object($dataObj) ? get_object_vars($dataObj) : [];

//         $check_data = true;

//         $nombre = isset($data['nombre']) ? $data['nombre'] : $check_data=false;
//         $email = isset($data['email']) ? $data['email'] : $check_data=false;
//         $telefono = isset($data['telefono']) ? $data['telefono'] : $check_data=false;
//         $modelo = isset($data['modelo']) ? $data['modelo'] : $check_data=false;
//         $anio = isset($data['anio']) ? $data['anio'] : $check_data=false;
//         $version = isset($data['version']) ? $data['version'] : $check_data=false;
//         $km = isset($data['km']) ? $data['km'] : $check_data=false;
//         $ftecnica = isset($data['ficha_tecnica']) ? $data['ficha_tecnica'] : $check_data=false;
//         $cduenios = isset($data['cantidad_duenios']) ? $data['cantidad_duenios'] : $check_data=false;
//         $vpretendido = isset($data['valor_pretendido']) ? $data['valor_pretendido'] : $check_data=false;
//         $vpermuta = isset($data['venta_permuta']) ? $data['venta_permuta'] : $check_data=false;
//         $txtauto = isset($data['nombre_auto']) ? $data['nombre_auto'] : $check_data=false;

//         if (!$check_data) {
//             $return = $response->withJson(['error'=>'Falta enviar campos, revise la documentacion.'], 500);
//             return $return;
//         }

//         if ($modoTesting) {
//             $token = "[Modo Testing] " . $token;

//             $dataPost = array(
//                 'name'       => $nombre,
//                 'email'      => $email,
//                 'phone'      => $telefono,
//                 'brand'      => $brand,
//                 'model'      => $modelo,
//                 'anio'       => $anio,
//                 'version'    => $version,
//                 'km'         => $km,
//                 'ftecnica'   => $ftecnica,
//                 'cduenios'   => $cduenios,
//                 'vpretendido'=> $vpretendido,
//                 'vpermuta'   => $vpermuta,
//                 'txtauto'    => $txtauto
//             );

//             $urlRecarga = $urlBase . "ws/publicQuotation";

//             $curl = curl_init($urlRecarga);
//             curl_setopt($curl, CURLOPT_URL, $urlRecarga);
//             curl_setopt($curl, CURLOPT_POST, true);
//             curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($dataPost, '', '&'));
//             curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
//             curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
//             curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

//         // Capturamos body UNA vez
//             $rawBody = (string)$request->getBody();
//             $brand = $args['brand'] ?? $request->getAttribute('brand') ?? '';

//             registrarApiLog($request, null, [
//             'rawBody' => json_encode([
//                 'url' => $urlRecarga,
//                 'payload' => $dataPost
//             ]),
//             'tag' => 'Cotizador antes de ejecutar el Curl',
//             'response_statuscode' => 0,
//             'response_body' => ''
//             ]);


//             $respCotizadorRaw = curl_exec($curl);
//             $respCotizador = json_decode($respCotizadorRaw);

//             curl_close($curl);

//             if (isset($respCotizador->codigo) && $respCotizador->codigo === 500) {
//                 $return = $response->withJson(['msg'=>$respCotizador->mensaje, "error"=>$respCotizador->error], 500);
//             } else {
//                 $return = $response->withJson([
//                     'msg'         => $respCotizador->mensaje ?? '',
//                     "error"       => 0,
//                     "id_cotizacion"=> $respCotizador->cotizacion ?? null,
//                     'valores'     => $respCotizador->valores ?? []
//                 ], 200);
//             }

//              // 🔵 LOG DE ENTRADA (para confirmar que entra)
//             $respCotizadorRaw = curl_exec($curl);
//             $curlErr = curl_error($curl);
//             $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

//             registrarApiLog($request, null, [
//             'rawBody' => json_encode([
//                 'http_code' => $httpCode,
//                 'curl_error' => $curlErr,
//                 'raw' => $respCotizadorRaw
//             ]),
//             'tag' => 'Cotizador CURL RESULT'
//             ]);


//         } else {
//             $notificacion_data = array();
//             $notificacion_data['tipo'] = 'recarga-exitosa';
//             $notificacion_data['url'] = '';
//             $notificacion_data['estado'] = 0;

//             $notificacion = new Notificacion($notificacion_data);
//             $notificacion->save();

//             // Si en modo NO testing tenés otra lógica real, ponela acá.
//             $return = $response->withJson(["error"=>false, "codigo"=>0, "mensaje"=>"", "data"=>[]], 200);
//         }

//     } catch (Exception $e) {
//         $return = $response->withJson(['error'=>$e->getMessage()], 500);
//     }

//     return $return;

// })->add($authorization);

$app->post('/cotizadorPublico/{brand}', function (Request $request, Response $response, array $args = []) {

    $rawBody = (string)$request->getBody();
    $brandRoute = $args['brand'] ?? $request->getAttribute('brand') ?? '';

    registrarApiLog($request, null, [
        'rawBody' => $rawBody,
        'tag' => 'Cotizador Entrada'
    ]);

    try {

        $data = json_decode($rawBody, true);

        if (!is_array($data)) {
            $return = $response->withJson([
                "error" => true,
                "msg"   => "JSON inválido"
            ], 400);

            registrarApiLog($request, $return, ['tag'=>'Cotizador JSON invalido']);
            return $return;
        }

        /*----------------------------------
        MODO SISTEMA
        ----------------------------------*/

        $modoSistema = obtenerParametroSistema('COTIZADOR_MODO','COTIZADOR');
        $modoSistema = strtoupper(trim((string)$modoSistema));
        $modoTest = ($modoSistema === 'TEST');

        /*----------------------------------
        MARCA
        ----------------------------------*/

        $brandId = isset($data['marca']) && trim($data['marca']) !== ''
            ? trim($data['marca'])
            : trim($brandRoute);

        if (!$brandId) {

            $return = $response->withJson([
                "error" => true,
                "msg"   => "Falta parametro marca"
            ],400);

            registrarApiLog($request,$return,['tag'=>'Cotizador falta marca']);
            return $return;
        }

        /*----------------------------------
        CAMPOS REQUERIDOS
        ----------------------------------*/

        $required = [
            'nombre','email','telefono','modelo','anio','km',
            'ficha_tecnica','cantidad_duenios','valor_pretendido',
            'venta_permuta','nombre_auto'
        ];

        foreach ($required as $campo) {

            if (!isset($data[$campo]) || trim((string)$data[$campo]) === '') {

                $return = $response->withJson([
                    "error"=>true,
                    "msg"=>"Falta parametro {$campo}"
                ],400);

                registrarApiLog($request,$return,['tag'=>"Cotizador falta {$campo}"]);
                return $return;
            }
        }

        /*----------------------------------
        DEFAULT VERSIONES
        ----------------------------------*/

        $data['version']       = $data['version'] ?? '';
        $data['version_name']  = $data['version_name'] ?? '';
        $data['version_other'] = $data['version_other'] ?? '';

        /*----------------------------------
        LLAMAR SERVICIO
        ----------------------------------*/

        registrarApiLog($request,null,['tag'=>'Cotizador Antes Servicio']);

        $svc = new CotizacionService();

        $svcRes = $svc->procesarCotizacionPublica($data,$brandId);

        registrarApiLog($request,null,[
            'rawBody'=>json_encode($svcRes),
            'tag'=>'Cotizador Resultado Servicio'
        ]);

        $resultado       = $svcRes['resultado'] ?? null;
        $msg             = $svcRes['msg'] ?? 'OK';
        $idCotizacion    = $svcRes['id_cotizacion'] ?? null;
        $postCotizacion  = $svcRes['post_cotizacion'] ?? null;

        if ($modoTest) {
            $msg = "[TEST] ".$msg;
        }

        /*----------------------------------
        SIN RESULTADO
        ----------------------------------*/

        if (!$resultado) {

            $out = [
                "error" => false,
                "msg" => $msg,
                "id_cotizacion" => $idCotizacion,
                "valores" => [
                    "count" => 0,
                    "min" => null,
                    "max" => null,
                    "avg" => null
                ],
                "resultado" => null,
                "post_cotizacion" => $postCotizacion
            ];

            $return = $response->withJson($out,200);

            registrarApiLog($request,$return,['tag'=>'Cotizador SIN RESULTADO']);

            return $return;
        }

        /*----------------------------------
        CON RESULTADO
        ----------------------------------*/

        $out = [
            "error" => false,
            "msg" => $msg,
            "id_cotizacion" => $idCotizacion,
            "valores" => [
                "count" => $resultado['count'] ?? null,
                "min" => $resultado['min'] ?? null,
                "max" => $resultado['max'] ?? null,
                "avg" => $resultado['avg'] ?? null
            ],
            "url" => $resultado['url'] ?? null,
            "resultado" => $resultado,
            "post_cotizacion" => $postCotizacion
        ];

        $return = $response->withJson($out,200);

        registrarApiLog($request,$return,['tag'=>'Cotizador OK']);

        return $return;

    } catch(\Throwable $e){

        registrarApiLog($request,null,[
            'rawBody'=>json_encode([
                'msg'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]),
            'tag'=>'Cotizador Exception'
        ]);

        $return = $response->withJson([
            "error"=>true,
            "msg"=>"Error interno del cotizador"
        ],500);

        return $return;
    }

});

// SUCURSALES
$app->get('/sucursales', function (Request $request, Response $response) {
	global $modoTesting;
	global $urlBase;

    try{
        $token = obtenerTokenRequest($request);
		
		if($modoTesting){
			$token = "[Modo Testing] ".$token;
        	//mail('gfigueroa.ac@gmail.com','[API Recargas] Nueva solicitud de /recargachip/'.$mid,"Saldo: \"".$saldo."\" para el MID: ".($mid).".\nPDV: ".$pdv."\nVendedor: ".$vendedor."\nReferencia/datos extra: ".$referencia."\n\n\nRecibido el: ".date('d-m-Y H:i'));
 
        	$urlRecarga = $urlBase."ws/locations";
	        
	        $curl = curl_init($urlRecarga);
	        curl_setopt($curl, CURLOPT_URL, $urlRecarga);
	        curl_setopt($curl, CURLOPT_POST, true);
	        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); 
	        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

	        $respCotizador = json_decode(curl_exec($curl));

	        curl_close($curl);

			if($respCotizador->codigo === 500){
				$return = $response->withJson(['msg'=>$respCotizador->mensaje,"error"=>$respCotizador->error],500);
			} else {
				$return = $response->withJson(['msg'=>$respCotizador->mensaje,"error"=>0,"locations"=>$respCotizador->locations],200);
			}

		} else {
			$notificacion_data = array();
			$notificacion_data['tipo'] = 'recarga-exitosa';
			//$notificacion_data['titulo'] = 'Se pidieron todas las marcas';
			//$notificacion_data['contenido'] = 'Se ha solicitado obtener todas las marcas';
			//$notificacion_data['referencia'] = $referencia;
			$notificacion_data['url'] = '';
			
			$notificacion_data['estado'] = 0; // 0 = pendiente, 1 = visto, -1 = eliminada
			$notificacion = new Notificacion($notificacion_data);
			$notificacion->save();
		}
    } catch (Exception $e) {
        $return = $response->withJson(['error'=>$e->getMessage()],500);
	}
   
    return $return;
});

$app->post('/calendario[/{location}]', function (Request $request, Response $response) {
	global $modoTesting;
	global $urlBase;

    try{
		$location = $request->getAttribute('location');
        $token = obtenerTokenRequest($request);

		$rawBody = (string)$request->getBody();
		$parsed = json_decode($rawBody, true);
		$data = is_array($parsed) ? $parsed : [];

		if (!$location && !empty($data['sucursal'])) {
			$location = trim((string)$data['sucursal']);
		}
		if (!$location && !empty($data['location'])) {
			$location = trim((string)$data['location']);
		}

		$anio = $data['anio'] ?? $data['anio_agenda'] ?? null;
		$mes  = $data['mes']  ?? $data['mes_agenda']  ?? null;

		if (!$location) {
        	return $response->withJson([
				"error" => true,
				"codigo" => 0,
				"mensaje" => "Falta parametro sucursal",
				"data" => null
			], 400);
        }

		if (!$anio || !$mes) {
        	return $response->withJson([
				"error" => true,
				"codigo" => 0,
				"mensaje" => "Falta enviar campos, revise la documentacion.",
				"data" => null
			], 400);
        }

		$postData = array(
			'location' => $location,
			'anio' => $anio,
			'mes' => $mes
		);

		$urlRecarga = $urlBase."ws/calendar";

		$curl = curl_init($urlRecarga);
		curl_setopt($curl, CURLOPT_URL, $urlRecarga);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postData, '', '&'));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

		$respRaw = curl_exec($curl);
		$curlError = curl_error($curl);
		curl_close($curl);

		if ($curlError) {
			return $response->withJson([
				"error" => true,
				"codigo" => 0,
				"mensaje" => "Error CURL: ".$curlError,
				"data" => null
			], 500);
		}

		$respCotizador = json_decode($respRaw);

		if (!$respCotizador) {
			return $response->withJson([
				"error" => true,
				"codigo" => 0,
				"mensaje" => "Respuesta inválida del WS calendar",
				"data" => null
			], 500);
		}

		if (isset($respCotizador->codigo) && (int)$respCotizador->codigo === 500) {
			return $response->withJson([
				"error" => true,
				"codigo" => 0,
				"mensaje" => $respCotizador->mensaje,
				"data" => null
			], 500);
		}

		return $response->withJson([
			"error" => false,
			"codigo" => 0,
			"mensaje" => $respCotizador->mensaje ?? '',
			"data" => $respCotizador->calendar ?? null
		], 200);

    } catch (Exception $e) {
        return $response->withJson([
			"error" => true,
			"codigo" => 0,
			"mensaje" => $e->getMessage(),
			"data" => null
		], 500);
	}
});

// HORARIOS
$app->post('/horarios[/{location}]', function (Request $request, Response $response) {
	global $modoTesting;
	global $urlBase;

    try{
		$location = $request->getAttribute('location');
        $token = obtenerTokenRequest($request);

		$rawBody = (string)$request->getBody();
		$parsed = json_decode($rawBody, true);
		$data = is_array($parsed) ? $parsed : [];

		// compatibilidad: si no vino en URL, tomarlo del body
		if (!$location && !empty($data['sucursal'])) {
			$location = trim((string)$data['sucursal']);
		}
		if (!$location && !empty($data['location'])) {
			$location = trim((string)$data['location']);
		}

		$check_data = true;
		$fecha = isset($data['fecha']) ? $data['fecha'] : $check_data = false;

		if (!$location) {
        	return $response->withJson([
				"error" => true,
				"codigo" => 0,
				"mensaje" => "Falta parametro sucursal"
			], 400);
        }

		if(!$check_data){
        	return $response->withJson([
				"error" => true,
				"codigo" => 0,
				"mensaje" => "Falta enviar campos, revise la documentacion."
			], 400);
        }
		
		if($modoTesting){
			$token = $token !== '' ? "[Modo Testing] ".$token : "[Modo Testing] SIN TOKEN";

			$postData = array(
        		'location' => $location,
        		'date' => $fecha
            );   
        	
			$urlRecarga = $urlBase."ws/schedules";
	        
	        $curl = curl_init($urlRecarga);
	        curl_setopt($curl, CURLOPT_URL, $urlRecarga);
	        curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postData, '', '&'));
	        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); 
	        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

	        $respRaw = curl_exec($curl);
	        $curlError = curl_error($curl);

	        curl_close($curl);

	        if ($curlError) {
				return $response->withJson([
					"error" => true,
					"codigo" => 0,
					"mensaje" => "Error CURL: ".$curlError
				], 500);
	        }

	        $respCotizador = json_decode($respRaw);

	        if (!$respCotizador) {
				return $response->withJson([
					"error" => true,
					"codigo" => 0,
					"mensaje" => "Respuesta inválida del WS schedules",
					"raw" => $respRaw
				], 500);
	        }

			if (isset($respCotizador->codigo) && (int)$respCotizador->codigo === 500) {
				return $response->withJson([
					"error" => true,
					"codigo" => 0,
					"mensaje" => $respCotizador->mensaje
				], 500);
			}

			return $response->withJson([
				"error" => false,
				"codigo" => 0,
				"mensaje" => $respCotizador->mensaje ?? '',
				"schedules" => $respCotizador->schedules ?? null
			], 200);

		} else {
			return $response->withJson([
				"error" => true,
				"codigo" => 0,
				"mensaje" => "Modo productivo sin implementar"
			], 500);
		}
    } catch (Exception $e) {
        return $response->withJson([
			"error" => true,
			"codigo" => 0,
			"mensaje" => $e->getMessage()
		], 500);
	}
});

$app->post('/agendarInspeccion[/{location}]', function (Request $request, Response $response) {
	global $modoTesting;
	global $urlBase;

    try{
		$location = $request->getAttribute('location');
        $token = obtenerTokenRequest($request);

		$rawBody = (string)$request->getBody();
		$parsed = json_decode($rawBody, true);
		$data = is_array($parsed) ? $parsed : [];

		// compatibilidad
		if (!$location && !empty($data['sucursal'])) {
			$location = $data['sucursal'];
		}
		if (!$location && !empty($data['location'])) {
			$location = $data['location'];
		}

		$check_data = true;

		$fecha = $data['fecha'] ?? $check_data = false;
		$hora = $data['hora'] ?? $check_data = false;
		$modelo = $data['modelo'] ?? $check_data = false;
		$marca = $data['marca'] ?? $check_data = false;
		$anio = $data['anio'] ?? $check_data = false;
		$familia = $data['version'] ?? $check_data = false;
		$auto = $data['nombre_auto'] ?? $check_data = false;
		$nombre = $data['nombre'] ?? $check_data = false;
		$email = $data['email'] ?? $check_data = false;
		$telefono = $data['telefono'] ?? $check_data = false;
		$id_cotizacion = $data['id_cotizacion'] ?? $check_data = false;

		if (!$location) {
			return $response->withJson([
				"error" => true,
				"mensaje" => "Falta parametro sucursal"
			], 400);
		}

		if(!$check_data){
        	return $response->withJson([
				"error" => true,
				"mensaje" => "Falta enviar campos, revise la documentacion."
			], 400);
        }
		
		if($modoTesting){
			$postData = array(
        		'location'=>$location,
        		'date'=>$fecha,
        		'hora'=>$hora,
        		'modelo'=>$modelo,
        		'marca'=>$marca,
        		'anio'=>$anio,
        		'familia'=>$familia,
        		'auto'=>$auto,
        		'nombre'=>$nombre,
        		'email'=>$email,
        		'telefono'=>$telefono,
        		'id_cotizacion'=>$id_cotizacion
            );   
        	
			$urlRecarga = $urlBase."ws/scheduleInspection";
	        
	        $curl = curl_init($urlRecarga);
	        curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postData));
	        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); 
	        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

	        $respRaw = curl_exec($curl);
	        curl_close($curl);

	        $respCotizador = json_decode($respRaw);

			return $response->withJson([
				"error" => 0,
				"msg" => $respCotizador->mensaje ?? "OK"
			], 200);
		}

		return $response->withJson([
			"error" => 1,
			"msg" => "Modo productivo no implementado"
		], 500);

    } catch (Exception $e) {
        return $response->withJson(['error'=>$e->getMessage()],500);
	}
});

$app->post('/availability[/{location}]', function (Request $request, Response $response) {
	global $modoTesting;
	global $urlBase;

    try{
		$location = $request->getAttribute('location');
        $token = obtenerTokenRequest($request);

		$rawBody = (string)$request->getBody();
		$parsed = json_decode($rawBody, true);
		$data = is_array($parsed) ? $parsed : [];

		if (!$location && !empty($data['sucursal'])) {
			$location = $data['sucursal'];
		}
		if (!$location && !empty($data['location'])) {
			$location = $data['location'];
		}

		if (!$location) {
			return $response->withJson([
				"error" => true,
				"mensaje" => "Falta parametro sucursal"
			], 400);
		}

		if($modoTesting){
			$postData = ['location'=>$location];

			$urlRecarga = $urlBase."ws/availability";
	        
	        $curl = curl_init($urlRecarga);
	        curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postData));
	        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); 
	        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

	        $respRaw = curl_exec($curl);
	        curl_close($curl);

	        $respCotizador = json_decode($respRaw);

			return $response->withJson([
				"error"=>0,
				"msg"=>$respCotizador->mensaje ?? '',
				"availability"=>$respCotizador->availability ?? null
			],200);
		}

		return $response->withJson([
			"error"=>1,
			"msg"=>"Modo productivo no implementado"
		],500);

    } catch (Exception $e) {
        return $response->withJson(['error'=>$e->getMessage()],500);
	}
});

$g = function($req, $res, $next)
{
    $res->write('In1');
    $res = $next($req, $res);
    $res->write('Out1');
    return $res;
};

$app->post('/auth', function(Request $request, Response $response) use($server, $storage)
{
    $appRefreshTokenSettings = [
        'always_issue_new_refresh_token' => true,
    ];
    $appClientCredentialsSettings = [
        'always_issue_new_refresh_token' => true,
    ];
    $server->addGrantType(new RefreshToken($storage, $appRefreshTokenSettings));
    $server->addGrantType(new ClientCredentials($storage, $appClientCredentialsSettings));
    $server->handleTokenRequest(Oauth2Request::createFromGlobals())->send();
});

$app->get('/test-apify', function (Request $request, Response $response) {

    try {
        require_once __DIR__ . '/services/ApiCotizadorApify.php';

        $api = new ApiCotizadorApify();

        // 1) Ejecuta el actor
        $run = $api->testRun("Chevrolet", "Onix");

        // 2) Sacar datasetId del run (esto existe según tu JSON)
        $datasetId = $run['data']['defaultDatasetId'] ?? null;

        if (!$datasetId) {
            return $response->withJson([
                'ok' => false,
                'mensaje' => 'El run no trajo defaultDatasetId',
                'run' => $run
            ], 500);
        }

        // 3) Leer items del dataset
        $itemsResp = $api->getDatasetItems($datasetId, 50);

        return $response->withJson([
            'ok' => true,
            'run_status' => $run['data']['status'] ?? null,
            'dataset_id' => $datasetId,
            'items_ok' => $itemsResp['ok'] ?? false,
            'items_count' => isset($itemsResp['items']) ? count($itemsResp['items']) : 0,
            'items' => $itemsResp['items'],
        ], 200);

    } catch (Throwable $e) {
        return $response->withJson([
            "ok" => false,
            "mensaje" => $e->getMessage()
        ], 500);
    }

});

// ===== CARGAR .ENV =====
function loadEnv($path)
{
    if (!file_exists($path)) return;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        $_ENV[$name] = $value;
        putenv("$name=$value");
    }
}

function obtenerTokenRequest(Request $request): string
{
    try {
        $headers = $request->getHeaders();

        if (isset($headers['HTTP_AUTHORIZATION'][0]) && trim((string)$headers['HTTP_AUTHORIZATION'][0]) !== '') {
            return (string)$headers['HTTP_AUTHORIZATION'][0];
        }

        if (isset($headers['Authorization'][0]) && trim((string)$headers['Authorization'][0]) !== '') {
            return (string)$headers['Authorization'][0];
        }

        return '';
    } catch (\Throwable $e) {
        return '';
    }
}

function registrarApiLog(Request $request, $return = null, array $extra = []): void
{
    try {
        // Token (soporta ambos headers)
        $token = obtenerTokenRequest($request);
        
        // Body request: leer como string (y si ya fue consumido, podés pasarlo por $extra['rawBody'])
        $rawBody = $extra['rawBody'] ?? (string)$request->getBody();

        // Status/headers/body de la respuesta real ($return)
        $status = 0;
        $respHeaders = '';
        $respBody = '';

        if ($return && is_object($return)) {
            if (method_exists($return, 'getStatusCode')) $status = $return->getStatusCode();
            if (method_exists($return, 'getHeaders')) $respHeaders = print_r($return->getHeaders(), 1);

            if (method_exists($return, 'getBody')) {
                $respBody = (string)$return->getBody();
                // si quedó el puntero al final, intentamos rewind
                $bodyStream = $return->getBody();
                if ($respBody === '' && is_object($bodyStream) && method_exists($bodyStream, 'rewind')) {
                    $bodyStream->rewind();
                    $respBody = (string)$bodyStream;
                }
            }
        }

        // Permite sobrescribir response_body con un texto/tag (útil para "ENTRO AL ENDPOINT")
        if (isset($extra['response_body'])) {
            $respBody = (string)$extra['response_body'];
        }

        $log_data = array();
        $log_data['token'] = $extra['token'] ?? $token;
        $log_data['ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $log_data['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $log_data['request_method'] = $request->getMethod();
        $log_data['request_uri'] = (string)$request->getUri();
        $log_data['request_header'] = print_r($request->getHeaders(), 1);
        $log_data['request_body'] = $rawBody;

        $log_data['response_statuscode'] = $extra['response_statuscode'] ?? $status;
        $log_data['response_header'] = $extra['response_header'] ?? $respHeaders;
        $log_data['response_body'] = $respBody;

        // Tag opcional
        if (isset($extra['tag'])) {
            $log_data['response_body'] = '[' . $extra['tag'] . '] ' . $log_data['response_body'];
        }

        $log = new Log($log_data);
        $log->save();

    } catch (\Throwable $e) {
        // Nunca romper el endpoint por log
    }
}

function obtenerParametroSistema($clave, $grupo = null)
{
    global $db;

    if (!isset($db) || !$db) {
        return null;
    }

    $clave = addslashes(trim((string)$clave));
    $grupoSql = '';

    if ($grupo !== null && trim((string)$grupo) !== '') {
        $grupo = addslashes(trim((string)$grupo));
        $grupoSql = " AND grupo = '{$grupo}' ";
    }

    $sql = "
        SELECT valor
        FROM parametros_sistema
        WHERE clave = '{$clave}'
          {$grupoSql}
          AND activo = 1
        ORDER BY id DESC
        LIMIT 1
    ";

    $q = $db->query($sql);

    if ($q && $row = $db->fetch_array($q)) {
        return trim((string)$row['valor']);
    }

    return null;
}

$app->run();

function validateDate($date, $format = 'Y-m-d'){
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

