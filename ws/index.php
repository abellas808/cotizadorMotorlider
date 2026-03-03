<?php
/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/

	session_start();
	require('../config/config.inc.php');
	header('Content-Type: application/json');
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
	date_default_timezone_set('America/Montevideo');
	set_time_limit(9000);
	ini_set('memory_limit', '-1');
	ini_set('max_input_vars','4000' );


	/**
 * Scraping que reemplaza apiAllDataVersion usando el DOM actual
 * Devuelve array de objetos con id y name
 */
function apiAllDataVersionScrap($brand, $model, $anio) {
    $url = construirUrlML($brand, $model, $anio);
    try {
        $html = obtenerContenidoURLConCurl($url);

        $versiones = extraerVersionesConDOM($html);
        $result = [];
        foreach ($versiones as $v) {
            // Extraer el ID desde el enlace si existe, si no, usar el nombre como id fallback
            $id = null;
            if (isset($v['enlace']) && preg_match('/applied_value_id%3D(\d+)/', $v['enlace'], $m)) {
                $id = $m[1];
            } elseif (isset($v['enlace']) && preg_match('/_SHORT\*VERSION_(\d+)/', $v['enlace'], $m)) {
                $id = $m[1];
            } else {
                $id = md5($v['nombre']);
            }
            $result[] = [
                'id' => $id,
                'name' => $v['nombre']
            ];
        }
        return !empty($result) ? $result : null;
    } catch (Exception $e) {
        return null;
    }
}


/**
 * Función para extraer las versiones de vehículos usando DOMDocument
 */
function extraerVersionesConDOM($html) {
    $versiones = [];
    $debugInfo = "<div style='background:#f5f5f5;padding:15px;margin:15px 0;border:1px solid #ddd;border-radius:5px;'><h3>Información de Depuración</h3>";
    
    // Crear un objeto DOMDocument
    $dom = new DOMDocument();
    
    // Suprimir errores de HTML mal formado
    libxml_use_internal_errors(true);
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    
    // Guardar el HTML para depuración
    $debugFilename = 'debug_html_' . date('Y-m-d_H-i-s') . '.html';
    file_put_contents($debugFilename, $html);
    $debugInfo .= "<p>HTML guardado en: <a href='$debugFilename'>$debugFilename</a></p>";
    
    // Buscar todos los h3 en la página para ver qué títulos existen
    $debugInfo .= "<h4>Títulos H3 encontrados en la página:</h4><ul>";
    $todosH3 = $xpath->query('//h3');
    if ($todosH3->length > 0) {
        foreach ($todosH3 as $h3) {
            $debugInfo .= "<li>Texto: '" . htmlspecialchars($h3->textContent) . "', Clase: '" . htmlspecialchars($h3->getAttribute('class')) . "'</li>";
        }
    } else {
        $debugInfo .= "<li>No se encontraron elementos H3</li>";
    }
    $debugInfo .= "</ul>";
    
    // Método principal: Buscar específicamente H3 con aria-level="3" y clase ui-search-filter-dt-title con texto "Versiones"
    $debugInfo .= "<h4>Método Principal: Buscar H3 con aria-level=\"3\" y clase 'ui-search-filter-dt-title' con texto 'Versiones'</h4>";
    
    // Buscar el elemento H3 específico
    $h3Versiones = $xpath->query('//h3[@aria-level="3" and @class="ui-search-filter-dt-title" and text()="Versiones"]');
    $debugInfo .= "<p>Elementos H3 encontrados: " . $h3Versiones->length . "</p>";
    
    if ($h3Versiones->length > 0) {
        $debugInfo .= "<p>Encontrado el elemento H3 exacto: &lt;h3 aria-level=\"3\" class=\"ui-search-filter-dt-title\"&gt;Versiones&lt;/h3&gt;</p>";
        
        // Obtener el elemento padre
        $padreH3 = $h3Versiones->item(0)->parentNode;
        
        if ($padreH3) {
            $debugInfo .= "<p>Padre del H3 encontrado: " . $padreH3->nodeName . "</p>";
            
            // Buscar el siguiente elemento ul
            $siguienteUl = null;
            $nodoActual = $h3Versiones->item(0);
            
            // Primero intentamos encontrar el siguiente ul como hermano directo
            while ($nodoActual = $nodoActual->nextSibling) {
                if ($nodoActual->nodeType === XML_ELEMENT_NODE) {
                    $debugInfo .= "<p>Elemento hermano encontrado: " . $nodoActual->nodeName . "</p>";
                    
                    if ($nodoActual->nodeName === 'ul') {
                        $siguienteUl = $nodoActual;
                        $debugInfo .= "<p>Encontrado ul hermano directo</p>";
                        break;
                    } else if ($nodoActual->nodeName === 'div') {
                        // Si es un div, buscar ul dentro
                        $ulEnDiv = $xpath->query('.//ul', $nodoActual);
                        if ($ulEnDiv->length > 0) {
                            $siguienteUl = $ulEnDiv->item(0);
                            $debugInfo .= "<p>Encontrado ul dentro de div hermano</p>";
                            break;
                        }
                    }
                }
            }
            
            // Si no encontramos ul como hermano directo, buscamos en todo el documento
            if (!$siguienteUl) {
                $debugInfo .= "<p>Buscando ul en el documento completo...</p>";
                
                // Intentar encontrar el ul que sigue al h3 en la estructura DOM
                $todosUl = $xpath->query('//ul');
                $h3Pos = 0;
                $encontrado = false;
                
                // Encontrar la posición del h3 en el documento
                foreach ($todosH3 as $i => $h3) {
                    if ($h3->isSameNode($h3Versiones->item(0))) {
                        $h3Pos = $i;
                        $encontrado = true;
                        break;
                    }
                }
                
                if ($encontrado) {
                    $debugInfo .= "<p>Posición del H3 en el documento: " . $h3Pos . "</p>";
                    
                    // Buscar el primer ul que aparece después del h3
                    foreach ($todosUl as $ul) {
                        $ulPos = 0;
                        foreach ($todosH3 as $i => $h3) {
                            if ($i >= $h3Pos && $h3->compareDocumentPosition($ul) & 4) { // 4 = DOCUMENT_POSITION_FOLLOWING
                                $siguienteUl = $ul;
                                $debugInfo .= "<p>Encontrado ul que sigue al H3 en el documento</p>";
                                break 2;
                            }
                        }
                    }
                }
            }
            
            // Si encontramos el ul, extraer sus elementos li
            if ($siguienteUl) {
                $nodosVersiones = $xpath->query('./li', $siguienteUl);
                $debugInfo .= "<p>Elementos li encontrados en el ul: " . $nodosVersiones->length . "</p>";
                
                // Si no hay elementos li directos, buscar más profundo
                if ($nodosVersiones->length == 0) {
                    $nodosVersiones = $xpath->query('.//li', $siguienteUl);
                    $debugInfo .= "<p>Elementos li encontrados en profundidad: " . $nodosVersiones->length . "</p>";
                }
            } else {
                $debugInfo .= "<p>No se encontró ningún elemento ul después del H3</p>";
                
                // Último intento: buscar cualquier ul dentro del contenedor padre
                $ulEnPadre = $xpath->query('.//ul', $padreH3);
                if ($ulEnPadre->length > 0) {
                    $siguienteUl = $ulEnPadre->item(0);
                    $nodosVersiones = $xpath->query('./li', $siguienteUl);
                    $debugInfo .= "<p>Elementos li encontrados en ul dentro del padre: " . $nodosVersiones->length . "</p>";
                }
            }
        }
    }
    
    // Método alternativo: búsqueda más flexible si el método principal no encuentra resultados
    if (!isset($nodosVersiones) || $nodosVersiones->length == 0) {
        $debugInfo .= "<h4>Método Alternativo: Búsqueda más flexible</h4>";
        
        // Buscar cualquier h3 que tenga la clase correcta y el texto Versiones
        $h3Versiones = $xpath->query('//h3[contains(@class, "ui-search-filter-dt-title") and contains(text(), "Versiones")]');
        
        if ($h3Versiones->length > 0) {
            $debugInfo .= "<p>Encontrado H3 con clase y texto correctos</p>";
            
            // Buscar el siguiente ul después del h3
            $ulVersiones = $xpath->query('following::ul[1]', $h3Versiones->item(0));
            
            if ($ulVersiones->length > 0) {
                $nodosVersiones = $xpath->query('./li', $ulVersiones->item(0));
                $debugInfo .= "<p>Elementos li encontrados en el siguiente ul: " . $nodosVersiones->length . "</p>";
            } else {
                // Buscar ul en el contenedor padre
                $padreH3 = $h3Versiones->item(0)->parentNode;
                $ulVersiones = $xpath->query('.//ul', $padreH3);
                
                if ($ulVersiones->length > 0) {
                    $nodosVersiones = $xpath->query('./li', $ulVersiones->item(0));
                    $debugInfo .= "<p>Elementos li encontrados en ul dentro del padre: " . $nodosVersiones->length . "</p>";
                }
            }
        }
    }
    
    // Mostrar información sobre los nodos encontrados
    if ($nodosVersiones->length > 0) {
        $debugInfo .= "<h4>Contenido de los nodos encontrados:</h4><ul>";
        foreach ($nodosVersiones as $index => $nodo) {
            $debugInfo .= "<li>Nodo #" . ($index + 1) . ": " . htmlspecialchars($nodo->textContent) . "</li>";
        }
        $debugInfo .= "</ul>";
    }
    
    $debugInfo .= "</div>";
    
    // Guardar y mostrar la información de depuración
    global $mostrarDebug;
    $mostrarDebug = $debugInfo;
    
    // Procesar los nodos encontrados
    if (isset($nodosVersiones) && $nodosVersiones->length > 0) {
        foreach ($nodosVersiones as $nodo) {
            $nombreNodo = $xpath->query('.//span[contains(@class, "ui-search-filter-name")]', $nodo);
            $cantidadNodo = $xpath->query('.//span[contains(@class, "ui-search-filter-results-qty")]', $nodo);
            
            if ($nombreNodo->length > 0) {
                $nombre = trim($nombreNodo->item(0)->textContent);
                $cantidad = "N/A";
                
                if ($cantidadNodo->length > 0) {
                    $cantidad = trim(preg_replace('/[^\d]/', '', $cantidadNodo->item(0)->textContent));
                }
                
                // Obtener el enlace si existe
                $enlaceNodo = $xpath->query('.//a', $nodo);
                $enlace = '';
                if ($enlaceNodo->length > 0) {
                    $enlace = $enlaceNodo->item(0)->getAttribute('href');
                }
                
                $versiones[] = [
                    'nombre' => $nombre,
                    'cantidad' => $cantidad,
                    'enlace' => $enlace
                ];
            }
        }
    }
    
    return $versiones;
}

/**
 * Función para obtener el contenido HTML de una URL usando cURL
 */
function obtenerContenidoURLConCurl($url) {
    $ch = curl_init();
    
    // Configurar opciones de cURL
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Ejecutar la solicitud
    $contenido = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Verificar errores
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("Error cURL: $error");
    }
    
    curl_close($ch);
    
    // Verificar código de respuesta HTTP
    if ($httpCode != 200) {
        throw new Exception("Error HTTP: Código $httpCode");
    }
    
    return $contenido;
}

		/**
	 * Construye la URL de MercadoLibre para un brand, model y año
	 * Adapta los parámetros para formato URL amigable
	 */
	function construirUrlML($brand, $model, $anio = null) {
		// Limpiar parámetros (minúsculas, sin tildes, sin espacios)
		$brand = strtolower(trim($brand));
		$model = strtolower(trim($model));
		$brand = preg_replace('/[^a-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $brand));
		$model = preg_replace('/[^a-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $model));
		
		$url = "https://autos.mercadolibre.com.uy/$brand/$model/";
	
		LogCron("URL construida: " . $url);

		return $url;
	}

	function validarUsuario($bd, $usuario, $contrasena, $entorno){
		return $bd->query("SELECT COUNT(*) AS valido FROM webservices WHERE entorno = '".$entorno."' AND usuario = '".$usuario."' AND contrasena = SHA1('webservices_".$contrasena."') AND activo")->fetch_object()->valido;
	}
	
	/* brands - GET */
	function brands($bd){	
		LogCron("\n\n------- START getBrands -------");

		$bdbrands = $bd->query("SELECT brand.id_marca, brand.nombre FROM act_marcas as brand, act_modelo as model WHERE brand.id_marca = model.id_marca GROUP BY brand.nombre ORDER BY brand.nombre ASC");

		if($bdbrands->num_rows > 0) {
			LogCron("obtuve las marcas correctamente");
			$brands = array();
			while($brand = $bdbrands->fetch_object()){
				$brands[$brand->id_marca] = $brand->nombre;
			}

			return array("codigo"=>200, "mensaje"=> "Obtención de marcas exitosas.","error"=>0,"brands"=>$brands);
		} else {
			LogCron("Error al obtener las marcas " . $bdbrands->error);
			return array("codigo"=>500, "mensaje"=> "No se pudo obtener las marcas.","error"=>500);
		}
	}

	/* models - Parámetros POST: brand */
	function models($bd){	
		LogCron("\n\n------- START getModels -------");

		$bdmodels = $bd->query("SELECT * FROM act_modelo WHERE id_marca = {$_POST['brand']} ORDER BY nombre ASC");

		if($bdmodels->num_rows > 0) {
			LogCron("obtuve los modelos correctamente con la brand " . $_POST['brand']);
			$models = array();
			while($model = $bdmodels->fetch_object()){
				$models[$model->id] = $model->nombre;
			}

			return array("codigo"=>200, "mensaje"=> "Obtención de modelos exitosa.","error"=>0,"models"=>$models);
		} else {
			LogCron("Error al obtener los modelos " . $bdmodels->error);
			return array("codigo"=>500, "mensaje"=> "No se pudo obtener los modelos.","error"=>500);
		}
	}

	/* years - Parámetros POST: brand,model */
	function years($bd){	
		LogCron("\n\n------- START getYears -------");

		$brandmodel = $bd->query("SELECT brand.id_marca, model.id_model FROM act_marcas as brand, act_modelo as model WHERE brand.id_marca = {$_POST['brand']} AND model.id = {$_POST['model']}");

		if($brandmodel->num_rows > 0) {
			LogCron("brand " . $_POST['brand']);
			LogCron("model " . $_POST['model']);
			LogCron("obtuve la marca & modelo voy contra ML");
			$brandmodel = $brandmodel->fetch_all(MYSQLI_ASSOC);

			//Lista de 20 años para atras
			$years = array();
			$yearActual = date("Y");
			$years[(int)$yearActual] = (int)$yearActual;
			$i = 0;
			while($i < 20){
				(int)$yearActual = (int)$yearActual - 1;
				$years[$yearActual] = $yearActual;
				$i++;
			}

			return array("codigo"=>200, "mensaje"=> "Obtención de años exitoso.","error"=>0,"anios"=>$years);
		} else {
			LogCron("Error al obtener marca & modelos " . $brandmodel->error);
			return array("codigo"=>500, "mensaje"=> "No se pudo obtener los años.","error"=>500);
		}
	}

	/* versions - Parámetros POST: brand,model,anio */
	function versions($bd){	
		LogCron("\n\n------- START getVersions -------");

		$brandmodel = $bd->query("SELECT brand.nombre,brand.id_marca, model.id_model,model.nombre as model_nombre FROM act_marcas as brand, act_modelo as model WHERE brand.id_marca = {$_POST['brand']} AND model.id = {$_POST['model']}");

		LogCron("SELECT brand.name,brand.id_marca, model.id_model FROM act_marcas as brand, act_modelo as model WHERE brand.id = {$_POST['brand']} AND model.id = {$_POST['model']}");

		if($brandmodel->num_rows > 0) {
			LogCron("brand " . $_POST['brand']);
			LogCron("model " . $_POST['model']);
			LogCron("obtuve la marca & modelo voy contra ML");
			$brandmodel = $brandmodel->fetch_all(MYSQLI_ASSOC);

			

			$versiones = apiAllDataVersionScrap($brandmodel[0]['nombre'], $brandmodel[0]['model_nombre'], $_POST['anio']);

			if($versiones == null){
				return array("codigo"=>500, "mensaje"=> "No hay versiones para este año.","error"=>501,"versiones"=>null);
			}

			LogCron(print_r($versiones , true));

			$versions = array();
			foreach($versiones as $key => $version) {
				LogCron("version " . $version['name']);
				LogCron("id " . $version['id']);
				$versions[$version['id']] = $version['name'];
			}

			return array("codigo"=>200, "mensaje"=> "Obtención de versiones exitosa.","error"=>0,"versiones"=>$versions);
		} else {
			LogCron("Error al obtener marca & modelos");
			return array("codigo"=>500, "mensaje"=> "No se pudo obtener las versiones.","error"=>500);
		}
	}
	
	/* prices - Parámetros bd,brand,model,anio,version,km */
	function prices($bd, $brand, $model, $anio, $version, $km){	

		$access_token = motorlider_ml_token();

		LogCron("\n\n------- START getPrice -------");

		$brandmodel  = $bd->query("SELECT brand.id_marca, model.id_model FROM act_marcas as brand, act_modelo as model WHERE brand.id = {$brand} AND model.id = {$model}");

		if($brandmodel->num_rows > 0) {
			$brandPadre = $brand;
			$modelPadre = $model;
			$anioPadre = $anio;

			LogCron("brand " . $brandPadre);
			LogCron("model " . $modelPadre);
			LogCron("obtuve la marca & modelo voy contra ML");
			$brandmodel = $brandmodel->fetch_all(MYSQLI_ASSOC);

			$brand = $brandmodel[0]['id_marca'];
			$model = $brandmodel[0]['id_model'];
			$familia = $version;

			LogCron("brand " . $brand);
			LogCron("model " . $model);
			LogCron("anio " . $anioPadre);
			LogCron("familia " . $familia);
			LogCron("km " . $km);

			$nameq = "";
			$checkIsNumeric = checkIsNumeric($familia);
			if($checkIsNumeric){
				$v = '&SHORT_VERSION='.$familia;
				$vName = apiDataNameVersion($v);
				foreach($vName as $key => $version) {
					$nameq = $version->name;
					break;
				}
			} else {
				$nameq = $familia;
			}

			if($checkIsNumeric){
				$search = 'category=MLU1744&BRAND='.$brand.'&MODEL='.$model.'&VEHICLE_YEAR='.$anio.'-'.$anio;
			} else {
				$search = 'q='.$nameq.'&category=MLU1744&BRAND='.$brand.'&MODEL='.$model.'&VEHICLE_YEAR='.$anio.'-'.$anio;
			}

			if($checkIsNumeric){
				$search = $search . '&SHORT_VERSION='.$familia;
			}

			if((int)$km >= 0){
				$kmstart = (int)$km;
				$kmend = (int)$km;
				$search = $search . '&KILOMETERS='.$kmstart.'km-'.$kmend.'km';
			} else {
				LogCron("KM negativos");
				return array("codigo"=>500, "mensaje"=> "No se pudo obtener los precios - KM negativos.","error"=>500);
			}

			LogCron("https://api.mercadolibre.com/sites/MLU/search?" . $search);

			$valorMaximo = $bd->query("SELECT valor FROM ponderador_valor_maximo");
			if($valorMaximo->num_rows > 0) {
				$valorMaximo = $valorMaximo->fetch_all(MYSQLI_ASSOC);
				$valorMaximo = $valorMaximo[0]['valor'];
			}

			LogCron("Tope Maximo: " . $valorMaximo);

			$products_ml = apiData($search);
			LogCron("products_ml results: " . count($products_ml->results));

			//NO tengo resultados con lo parametros pasados
			$products_ml_total = $products_ml->results;
			$count = 0;
			$query = "";
			$query_extra = "";

			LogCron("products_ml_total results: " . count($products_ml_total));

			while (count($products_ml_total) < $valorMaximo) {
				$count = $count + 1;
				LogCron("while count " . $count);
				if ($count === 1) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 5k
				} else if ($count === 2) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 10k
				} else if ($count === 3) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 20k
				} else if ($count === 4) {
					$query = changeYear($search, $anio, $count); //cambio el año restando 1 año
				} else if ($count === 5) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 5k
					$query = changeYear($query, $anio, $count); //cambio el año restando 1 año
				} else if ($count === 6) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 10k
					$query = changeYear($query, $anio, $count); //cambio el año restando 1 año
				} else if ($count === 7) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 20k
					$query = changeYear($query, $anio, $count); //cambio el año restando 1 año
				} else if ($count === 8) {
					$query = changeYear($search, $anio, $count); //cambio el año sumando 1 año
				} else if ($count === 9) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 5k
					$query = changeYear($query, $anio, $count); //cambio el año sumando 1 año
				} else if ($count === 10) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 10k
					$query = changeYear($query, $anio, $count); //cambio el año sumando 1 año
				} else if ($count === 11) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 20k
					$query = changeYear($query, $anio, $count); //cambio el año sumando 1 año
				} else if ($count === 12) {
					$query = changeYear($search, $anio, $count); //cambio el año sumando +/- 1 año
				} else if ($count === 13) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 5k
					$query = changeYear($query, $anio, $count); //cambio el año sumando +/- 1 año
				} else if ($count === 14) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 10k
					$query = changeYear($query, $anio, $count); //cambio el año sumando +/- 1 año
				}  else if ($count === 15) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 20k
					$query = changeYear($query, $anio, $count); //cambio el año sumando +/- 1 año
				} else if ($count === 16) {
					break;
				}

				LogCron("https://api.mercadolibre.com/sites/MLU/search?" . $query);

				$products_ml = apiData($query);
				LogCron("products_ml count " . count($products_ml->results));
				if( (count($products_ml->results) > 0) && (count($products_ml_total) == 0) ){
					$products_ml_total = $products_ml->results;
				} else if( (count($products_ml->results) > 0) && (count($products_ml_total) > 0) ){
					foreach($products_ml->results as $ml){
						$view = false;
						LogCron("MLU " . $ml->id);
						foreach($products_ml_total as $p){
							if($ml->id == $p->id){
								$view = true;
								break;
							}
						}
						if($view == false){
							array_push($products_ml_total, $ml);
						}
					}
				}
			}

			if(count($products_ml_total) < $valorMaximo){
				$kmstart = (int)$km;
				$kmend = (int)$km;
				$anio = $anioPadre;
				$kmend = $kmend + 20000;
				$kmstart = $kmstart - 20000;
				$kmstart = $kmstart < 0 ? 0 : $kmstart;
				$menor_year = (int)$anio - 1;
				$mayor_year = (int)$anio + 1;

				$nameq = str_replace(" ","%0A",$nameq);			
				$query_extra = 'q='.$nameq.'&category=MLU1744&BRAND='.$brand.'&MODEL='.$model.'&VEHICLE_YEAR='.$menor_year.'-'.$mayor_year.'&KILOMETERS='.$kmstart.'km-'.$kmend.'km';
				
				LogCron("query_extra");
				LogCron("https://api.mercadolibre.com/sites/MLU/search?" . $query_extra);

				$ch = curl_init();
                curl_setopt_array($ch, array(
                  CURLOPT_URL => 'https://api.mercadolibre.com/sites/MLU/search?' . $query_extra,
                  CURLOPT_RETURNTRANSFER => true,
                  CURLOPT_TIMEOUT => 0,
                  CURLOPT_FOLLOWLOCATION => true,
                  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                  CURLOPT_CUSTOMREQUEST => 'GET',
				  CURLOPT_HTTPHEADER => array(
					"Authorization: Bearer" . $access_token
				  ),
                ));
				$products_ml = json_decode(curl_exec($ch));

				if(isset($products_ml->results)){
					LogCron("products_ml count extra " . count($products_ml->results));
					if( (count($products_ml->results) > 0) && (count($products_ml_total) == 0) ){
						$products_ml_total = $products_ml->results;
					} else if( (count($products_ml->results) > 0) && (count($products_ml_total) > 0) ){
						foreach($products_ml->results as $ml){
							$view = false;
							LogCron("MLU " . $ml->id);
							foreach($products_ml_total as $p){
								if($ml->id == $p->id){
									$view = true;
									break;
								}
							}
							if($view == false){
								array_push($products_ml_total, $ml);
							}
						}
					}
				} 
			}

			if(count($products_ml_total) > 0){
				LogCron("tengo algun resultado, voy a obtener los precios");
				LogCron("total " . count($products_ml_total));
				$all_price = [];
				$total = 0;
				$dollar = 0;
				foreach($products_ml_total as $vehi){
					$dollar = $vehi->price;
					LogCron("precio " . $dollar);

					if($vehi->currency_id != 'USD'){
						LogCron("precio en $ voy a buscar cotizacion a BD");
						$cotizacion  = $bd->query("SELECT dolar FROM ponderador_valor_dolar WHERE id_valor_dolar = 1");
						if($cotizacion->num_rows > 0) {
							$cotizacion = $cotizacion->fetch_all(MYSQLI_ASSOC);
							$cotizacion = $cotizacion[0]['dolar'];
							LogCron("cotizacion en BD >>> " . $cotizacion);
							$dollar = round($dollar / $cotizacion, 0, PHP_ROUND_HALF_UP);
							LogCron("conversion " . $dollar);
						}
					}
					foreach($vehi->attributes as $filters){
						if($filters->id === 'KILOMETERS'){
							if((int)$filters->value_name == 0){
								LogCron("precio 0KM " . $dollar);
								$total = $total + 1;
								$price = round($dollar / 1.22, 0, PHP_ROUND_HALF_UP);
								$all_price [] = $price;
							} else {
								$all_price [] = $dollar;
							}
						}
					}
				}

				$promedio = 0;
				$count_promedio = 0;
				foreach($all_price as $p){
					$promedio = $promedio + $p;
					$count_promedio = $count_promedio + 1;
				}
				$average = round($promedio / $count_promedio, 0, PHP_ROUND_HALF_UP);
				LogCron("promedio " . $average);

				if($query == ""){
					$query = $search;
				}

				$response = '{"valor_maximo":'.max($all_price).',"valor_minimo":'.min($all_price).',"valor_promedio":'.$average.',"total":'.count($products_ml_total).',"total0km":'.$total.'}';
				return array("codigo"=>200, "mensaje"=> "Obtención de precios exitosa.","error"=>0,"precios"=>json_decode($response));
			} else {
				LogCron("no tengo ningun resultado, solamente " . count($products_ml_total));
				return array("codigo"=>500, "mensaje"=> "No se pudo obtener los precios.","error"=>501);
			}
		} else {
			LogCron("Error al obtener marca & modelos");
			return array("codigo"=>500, "mensaje"=> "No se pudo obtener los precios.","error"=>500);
		}
	}

	/* average - Parámetros bd,promedio */
	function average($bd, $promedio){	
		LogCron("\n\n------- START average -------");

		$average = $promedio;
		LogCron("average " . $average);

		//A,E,I,M,P,T,AA,AE,AI,AM,AP,AT variables porcentuales, llenan por admin
		$valor_venal = $bd->query("SELECT * FROM ponderador_valor_venal");

		$A = 0;
		$E = 0;
		$I = 0;
		$M = 0;
		$P = 0;
		$T = 0;
		$AA = 0;
		$AE = 0;
		$AI = 0;
		$AM = 0;
		$AP = 0;
		$AT = 0;

		if($valor_venal->num_rows > 0) {
			LogCron("obtuve los porcentajes, asigno los valores");
			$vv = $valor_venal->fetch_all(MYSQLI_ASSOC);
			foreach($vv as $v){
				if($v['key'] == 'A'){
					$A = $v['porcentaje'];
				} else if($v['key'] == 'E'){
					$E = $v['porcentaje'];
				} else if($v['key'] == 'I'){
					$I = $v['porcentaje'];
				} else if($v['key'] == 'M'){
					$M = $v['porcentaje'];
				} else if($v['key'] == 'P'){
					$P = $v['porcentaje'];
				} else if($v['key'] == 'T'){
					$T = $v['porcentaje'];
				} else if($v['key'] == 'AA'){
					$AA = $v['porcentaje'];
				} else if($v['key'] == 'AE'){
					$AE = $v['porcentaje'];
				} else if($v['key'] == 'AI'){
					$AI = $v['porcentaje'];
				} else if($v['key'] == 'AM'){
					$AM = $v['porcentaje'];
				} else if($v['key'] == 'AP'){
					$AP = $v['porcentaje'];
				} else if($v['key'] == 'AT'){
					$AT = $v['porcentaje'];
				}
			}
		} else {
			LogCron("Error al ponderador_valor_venal");
			return array("codigo"=>500, "mensaje"=> "No se pudo obtener los porcentajes.","error"=>500);
		}

		if($A == 0 || $E == 0 || $I == 0 || $M == 0 || $P == 0 || $T == 0 || $AA == 0 || $AE == 0 || $AI == 0 || $AM == 0 || $AP == 0 || $AT == 0){
			LogCron("Error al completar los procentajes, uno dio 0");
			LogCron($A);
			LogCron($E);
			LogCron($I);
			LogCron($M);
			LogCron($P);
			LogCron($P);
			LogCron($AA);
			LogCron($AE);
			LogCron($AI);
			LogCron($AM);
			LogCron($AP);
			LogCron($AT);
			return array("codigo"=>500, "mensaje"=> "No se pudo obtener los porcentajes.","error"=>500);
		}

		//C,G,K,Ñ,R,Y,AC,AG,AK,AÑ,AR,AY variables nominales, llenan por admin
		$valor = $bd->query("SELECT * FROM ponderador_valor");

		$C = 0;
		$G = 0;
		$K = 0;
		$Ñ = 0;
		$R = 0;
		$Y = 0;
		$AC = 0;
		$AG = 0;
		$AK = 0;
		$AÑ = 0;
		$AR = 0;
		$AY = 0;

		if($valor->num_rows > 0) {
			LogCron("obtuve los valores, asigno a las letras los valores");
			$vr = $valor->fetch_all(MYSQLI_ASSOC);
			foreach($vr as $v){
				if($v['key'] == 'C'){
					$C = $v['nominal'];
				} else if($v['key'] == 'G'){
					$G = $v['nominal'];
				} else if($v['key'] == 'K'){
					$K = $v['nominal'];
				} else if($v['key'] == 'Ñ'){
					$Ñ = $v['nominal'];
				} else if($v['key'] == 'R'){
					$R = $v['nominal'];
				} else if($v['key'] == 'Y'){
					$Y = $v['nominal'];
				} else if($v['key'] == 'AC'){
					$AC = $v['nominal'];
				} else if($v['key'] == 'AG'){
					$AG = $v['nominal'];
				} else if($v['key'] == 'AK'){
					$AK = $v['nominal'];
				} else if($v['key'] == 'AÑ'){
					$AÑ = $v['nominal'];
				} else if($v['key'] == 'AR'){
					$AR = $v['nominal'];
				} else if($v['key'] == 'AY'){
					$AY = $v['nominal'];
				}
			}
		} else {
			LogCron("Error al ponderador_valor");
			return array("codigo"=>500, "mensaje"=> "No se pudo obtener los valores.","error"=>500);
		}

		if($C == 0 || $G == 0 || $K == 0 || $Ñ == 0 || $R == 0 || $Y == 0 || $AC == 0 || $AG == 0 || $AK == 0 || $AÑ == 0 || $AR == 0 || $AY == 0){
			LogCron("Error al completar los valores, uno dio 0");
			return array("codigo"=>500, "mensaje"=> "No se pudo obtener los valores.","error"=>500);
		}

		$result  = 0;

		if($average < 5000){
			$result = ($average * $A) - $C;
		} else if($average >= 5000 && $average <= 10000){
			$result = ($average * $E) - $G;
		} else if($average >= 10000 && $average <= 15000){
			$result = ($average * $I) - $K;
		} else if($average >= 15000 && $average <= 20000){
			$result = ($average * $M) - $Ñ;
		} else if($average >= 20000 && $average <= 25000){
			$result = ($average * $P) - $R;
		}  else if($average >= 25000 && $average <= 30000){
			$result = ($average * $T) - $Y;
		}  else if($average >= 30000 && $average <= 35000){
			$result = ($average * $AA) - $AC;
		} else if($average >= 35000 && $average <= 40000){
			$result = ($average * $AE) - $AG;
		} else if($average >= 40000 && $average <= 45000){
			$result = ($average * $AI) - $AK;
		} else if($average >= 45000 && $average <= 50000){
			$result = ($average * $AM) - $AÑ;
		} else if($average >= 50000 && $average <= 60000){
			$result = ($average * $AP) - $AR;
		} else if($average >= 60000 && $average <= 70000){
			$result = ($average * $AT) - $AY;
		} else {
			return array("codigo"=>500, "mensaje"=> "Nuestro sistema no pudo estimar en forma automática el valor de tu vehículo, déjanos tus datos y nos comunicaremos a la brevedad","error"=>502);
		}

		$result = round($result, 0, PHP_ROUND_HALF_UP);

		$response = '{"promedio_ml":'.$average.',"promedio_motorlider":'.$result.'}';

		return array("codigo"=>200, "mensaje"=> "Precio promedio","error"=>0,'valores'=>json_decode($response));

	}

	/* publicQuotation - Parámetros POST: marca,modelo,anio,version,promedio,ficha_tecnica,cantidad_duenios,venta_permuta,valor_pretendido */
	function publicQuotation($bd){	
		LogCron("\n\n------- START publicQuotation -------");

		$name = $_POST['name'];
		$email = $_POST['email'];
		$phone = $_POST['phone'];
		$brand = $_POST['brand'];
		$model = $_POST['model'];
		$anio = $_POST['anio'];
		$version = $_POST['version'];
		$km = $_POST['km'];
		$ftecnica = $_POST['ftecnica'];
		$cduenios = $_POST['cduenios'];
		$vpretendido = $_POST['vpretendido'];
		$vpermuta = $_POST['vpermuta'];
		$txtauto = $_POST['txtauto'];

		LogCron("nombre " . $name);
		LogCron("email " . $email);
		LogCron("telefono " . $phone);
		LogCron("marca " . $brand);
		LogCron("modelo " . $model);
		LogCron("anio " . $anio);
		LogCron("version " . $version);
		LogCron("km " . $km);
		LogCron("ficha_tecnica " . $ftecnica);
		LogCron("cantidad_duenios " . $cduenios);
		LogCron("valor_pretendido " . $vpretendido);
		LogCron("venta_permuta " . $vpermuta);
		LogCron("nombre del auto " . $txtauto);

		if(!isset($name) || !isset($email) || !isset($phone) || !isset($brand) || !isset($model) || !isset($anio) || !isset($version) || !isset($km) || !isset($ftecnica) || !isset($cduenios) || !isset($vpretendido) || !isset($vpermuta) || !isset($txtauto)){
			return array("codigo"=>500, "mensaje"=> "No se pudo obtener los valores.","error"=>500);
		}

		$promedio_ponderado = 0;
		$valor_minimo_ml    = 0;
		$valor_maximo_ml    = 0;
		$valor_promedio_ml  = 0;

		//precios ML
		LogCron("Voy contra ML para obtener los precios y el promedio");
		$prices_ml = prices($bd, $brand, $model, $anio, $version, $km);
		if($prices_ml['codigo'] == 200){
			$valor_minimo_ml   = $prices_ml['precios']->valor_minimo;
			$valor_maximo_ml   = $prices_ml['precios']->valor_maximo;
			$valor_promedio_ml = $prices_ml['precios']->valor_promedio;

			LogCron("valor_minimo_ml   " . $valor_minimo_ml);
			LogCron("valor_maximo_ml   " . $valor_maximo_ml);
			LogCron("valor_promedio_ml " . $valor_promedio_ml);

			LogCron("Voy a buscar el promedio final");
			$average = average($bd, $valor_promedio_ml);
			if($average['codigo'] == 200){
				$promedio_ponderado = $average['valores']->promedio_motorlider;
				LogCron("promedio ponderado  " . $promedio_ponderado);
			} else {
				LogCron("Error al obtener el promedio");
				return array("codigo"=>500, "mensaje"=> $average['mensaje'],"error"=>500);
			}
		} else {
			LogCron("Error al obtener los precios");
			return array("codigo"=>500, "mensaje"=> $prices_ml['mensaje'],"error"=>500);
		}

		$BA = 0;
		$ficha_oficial = $bd->query("SELECT porcentaje, operador FROM variables WHERE tipo = 3 AND ficha_oficial = '".$ftecnica."'");
		if($ficha_oficial->num_rows > 0) {
			$ficha_oficial = $ficha_oficial->fetch_all(MYSQLI_ASSOC);
			$fo_porcentaje = $ficha_oficial[0]['porcentaje'];
			$fo_operador = $ficha_oficial[0]['operador'];

			LogCron("ficha_oficial porcentaje " . $fo_porcentaje);
			LogCron("ficha_oficial operador " . $fo_operador);

			$BA = ((float)$fo_porcentaje / 100) * $promedio_ponderado;

			$fo_operador == '-' ? $BA = -$BA : $BA;

			LogCron("BA " . $BA);

		} else {
			return array("codigo"=>500, "mensaje"=> "Error al obtener la Ficha Oficial.","error"=>500);
		}

		$BB = 0;
		$cantidad_duenios = $bd->query("SELECT porcentaje, operador FROM variables WHERE tipo = 4 AND cantidad_duenios = ".(int)$cduenios);
		if($cantidad_duenios->num_rows > 0) {
			$cantidad_duenios = $cantidad_duenios->fetch_all(MYSQLI_ASSOC);
			$cd_porcentaje = $cantidad_duenios[0]['porcentaje'];
			$cd_operador = $cantidad_duenios[0]['operador'];

			LogCron("cantidad_duenios porcentaje " . $cd_porcentaje);
			LogCron("cantidad_duenios operador " . $cd_operador);

			$BB = ((float)$cd_porcentaje / 100) * $promedio_ponderado;

			$cd_operador == '-' ? $BB = -$BB : $BB;

			LogCron("BB " . $BB);

		} else {
			return array("codigo"=>500, "mensaje"=> "Error al obtener la Cantidad de Dueños.","error"=>500);
		}

		$BC = 0;
		$brandmodel  = $bd->query("SELECT brand.nombre as marca, model.nombre as modelo FROM act_marcas as brand, act_modelo as model WHERE brand.id = {$_POST['brand']} AND model.id = {$_POST['model']}");
		if($brandmodel->num_rows > 0) {
			$brandmodel = $brandmodel->fetch_all(MYSQLI_ASSOC);
			$brand = $brandmodel[0]['marca'];
			$model = $brandmodel[0]['modelo'];

			$checkIsNumeric = checkIsNumeric($version);
			if($checkIsNumeric){
				$versiones = apiNameVersion($version);
				$version = $versiones[0]->name;
			} else {
				$version = strtoupper($version);
			}

			LogCron("marca " . $brand);
			LogCron("modelo " . $model);
			LogCron("anio " . $anio);
			LogCron("version " . $version);
			LogCron("kilometros " . $km);

			$kilometros = $bd->query("SELECT kilometros as k FROM ponderador_valor_stock WHERE marca = '".$brand."' AND modelo = '".$model."' AND anio =".$anio." AND version = '".$version."'");
			if($kilometros->num_rows > 0) {
				$kilometros = $kilometros->fetch_object()->k;

				LogCron("kilometros en BD " . $kilometros);

				$max_kilometros = $bd->query("SELECT busqueda FROM ponderador_valor_busqueda")->fetch_object()->busqueda;

				$max_km = $kilometros + $max_kilometros;
				$min_km = $kilometros - $max_kilometros;

				$min_km < 0 ? $min_km = 0 : $min_km;

				LogCron("MAX kilometros " . $max_km);
				LogCron("MIN kilometros " . $min_km);

				if($km >= $min_km && $km <= $max_km) {
					$stock = $bd->query("SELECT stock as total FROM ponderador_valor_stock WHERE marca = '".$brand."' AND modelo = '".$model."' AND anio =".$anio." AND version = '".$version."'")->fetch_object()->total;
					LogCron("stock " . $stock);
	
					$stock > 5 ? $stock = 5 : $stock;
	
					$ponderacion_stock = $bd->query("SELECT porcentaje, operador FROM variables WHERE tipo = 6 AND stock = ".$stock."");
					if($ponderacion_stock->num_rows > 0) {
						$ponderacion_stock = $ponderacion_stock->fetch_all(MYSQLI_ASSOC);
						$ps_porcentaje = $ponderacion_stock[0]['porcentaje'];
						$ps_operador = $ponderacion_stock[0]['operador'];
			
						LogCron("ponderacion_stock porcentaje " . $ps_porcentaje);
						LogCron("ponderacion_stock operador " . $ps_operador);
	
						$BC = ((float)$ps_porcentaje / 100) * $promedio_ponderado;
	
						$ps_operador == '-' ? $BC = -$BC : $BC;
	
						LogCron("BC " . $BC);
	
					}
				} else {
					LogCron("NO estoy en el rango de kilometros por ende tomo % de stock 0");

					$ponderacion_stock = $bd->query("SELECT porcentaje FROM variables WHERE tipo = 6 AND stock = 0")->fetch_object()->porcentaje;

					LogCron("ponderacion stock " . $ponderacion_stock);

					$BC = ((float)$ponderacion_stock / 100) * $promedio_ponderado;

					LogCron("BC " . $BC);
				}
			} else {
				LogCron("NO existe el auto en BD");

				$ponderacion_stock = $bd->query("SELECT porcentaje FROM variables WHERE tipo = 6 AND stock = 0")->fetch_object()->porcentaje;

				LogCron("ponderacion stock " . $ponderacion_stock);

				$BC = ((float)$ponderacion_stock / 100) * $promedio_ponderado;

				LogCron("BC " . $BC);
			}
		} else {
			return array("codigo"=>500, "mensaje"=> "Error al obtener Marca y Modelo.","error"=>500);
		}

		$resultMax = 0;
		$resultMaxPago = 0;

		$resultMin = 0;
		$resultMinPago = 0;

		$resultPromedioPago = 0;
		$resultPromedio = 0;

		$resultMin = round($promedio_ponderado + $BA + $BB + $BC, 0, PHP_ROUND_HALF_UP);

		LogCron("valor_minimo_motorlider " . $resultMin);

		$response = '';
		$bool_vpretendido = false;
		if($vpermuta == 'Entrega'){
			LogCron("Entrega");
			//BE variables porcentuales, llenan por admin
			$BE = $bd->query("SELECT p.porcentaje as total FROM ponderador_valor_venal as p WHERE p.key = 'BE'")->fetch_object()->total;
			LogCron("BE " . $BE);
			
			$resultMinPago = round($resultMin * $BE, 0, PHP_ROUND_HALF_UP);

			//BF variables porcentuales, llenan por admin
			$BF = $bd->query("SELECT p.porcentaje as total FROM ponderador_valor_venal as p WHERE p.key = 'BF'")->fetch_object()->total;
			LogCron("BF " . $BF);

			$resultMaxPago = round($resultMinPago * $BF, 0, PHP_ROUND_HALF_UP);

			$resultPromedioPago = 0;
			$resultPromedioPago = ($resultMinPago + $resultMaxPago) / 2;
			$resultPromedioPago = round($resultPromedioPago, 0, PHP_ROUND_HALF_UP);

			LogCron("valor_maximo_motorlider_pago " . $resultMaxPago);
			LogCron("valor_minimo_motorlider_pago " . $resultMinPago);
			LogCron("valor_promedio_motorlider_pago " . $resultPromedioPago);

			if((int)$vpretendido < $resultMinPago){
				//Valor Pretendido por el Cliente
				LogCron("Valor Pretendido por el Cliente");
				$bool_vpretendido = true;
				$response = '{"vpretendido":'.(int)$vpretendido.'}';
			}else if((int)$vpretendido > $resultMinPago){
				//“Valor Compra Motorlider (mínimo)”
				//“Valor Compra Motorlider (promedio)”
				//“Valor Compra Motorlider (máximo)” 
				LogCron("Valor Motorlider");
				$response = '{"valor_maximo_motorlider":'.$resultMaxPago.',"valor_minimo_motorlider":'.$resultMinPago.',"valor_promedio_motorlider":'.$resultPromedioPago.'}';
			}
		} else {
			LogCron("Venta");
			//BD variables porcentuales, llenan por admin
			$BD = $bd->query("SELECT p.porcentaje as total FROM ponderador_valor_venal as p WHERE p.key = 'BD'")->fetch_object()->total;
			LogCron("BD " . $BD);

			
			$resultMax = round($resultMin * $BD, 0, PHP_ROUND_HALF_UP);

			$resultPromedio = 0;
			$resultPromedio = ($resultMin + $resultMax) / 2;
			$resultPromedio = round($resultPromedio, 0, PHP_ROUND_HALF_UP);

			LogCron("valor_maximo_motorlider_venta " . $resultMax);
			LogCron("valor_minimo_motorlider_venta " . $resultMin);
			LogCron("valor_promedio_motorlider_venta " . $resultPromedio);

			if((int)$vpretendido < $resultMin){
				//Valor Pretendido por el Cliente
				LogCron("Valor Pretendido por el Cliente");
				$bool_vpretendido = true;
				$response = '{"vpretendido":'.(int)$vpretendido.'}';
			}else if((int)$vpretendido > $resultMin){
				//“Valor Compra Motorlider (mínimo)”
				//“Valor Compra Motorlider (promedio)”
				//“Valor Compra Motorlider (máximo)” 
				LogCron("Valor Motorlider");
				$response = '{"valor_maximo_motorlider":'.$resultMax.',"valor_minimo_motorlider":'.$resultMin.',"valor_promedio_motorlider":'.$resultPromedio.'}';
			}
		}

		$date = date('Y-m-d');
		$paplicados = trim($promedio_ponderado,',');
		$brand = $_POST['brand'];
		$model = $_POST['model'];
		$na = 'N/A';
		$uno = 1;
		$msgbd = '';
		$jsonauto = json_encode($response);

		if($vpermuta == 'Entrega'){
			if($bool_vpretendido){
				$valor_pretendido = (int)$vpretendido;
				$valor_minimo_autodata = 0;
				$valor_maximo_autodata = 0;
				$valor_promedio_autodata = 0;
				$msgbd = 'Su Vehículo lo estaríamos comprando en: <strong> U$S ' . number_format($valor_pretendido, 0, ',', '.') . ' </strong>';
			} else {
				$valor_minimo_autodata = $resultMinPago;
				$valor_maximo_autodata = $resultMaxPago;
				$valor_promedio_autodata = $resultPromedioPago;
				$msgbd = 'Su Vehículo se encuentra valorado en el entorno de <strong> U$S ' . number_format($valor_minimo_autodata, 0, ',', '.') . ' </strong> y <strong> USD '. number_format($valor_maximo_autodata, 0, ',', '.') . '</strong>';
			}
		} else {
			if($bool_vpretendido){
				$valor_pretendido = (int)$vpretendido;
				$valor_minimo_autodata = 0;
				$valor_maximo_autodata = 0;
				$valor_promedio_autodata = 0;
				$msgbd = 'Su Vehículo lo estaríamos comprando en: <strong> U$S ' . number_format($valor_pretendido, 0, ',', '.') . ' </strong>';
			} else {
				$valor_minimo_autodata = $resultMin;
				$valor_maximo_autodata = $resultMax;
				$valor_promedio_autodata = $resultPromedio;
				$msgbd = 'Su Vehículo se encuentra valorado en el entorno de <strong> U$S ' . number_format($valor_minimo_autodata, 0, ',', '.') . ' </strong> y <strong> USD '. number_format($valor_maximo_autodata, 0, ',', '.') . '</strong>';
			}
		}

		$stmt = $bd->prepare("INSERT INTO cotizaciones_generadas (`nombre`, `email`, `telefono`, `ci`, `fecha`, `kilometros`, `ficha_tecnica`, `duenios`, `tipo_venta`, `precio_pretendido`, `marca`, `anio`, `familia`, `datos`, `respuesta`, `auto`, `valor_minimo`, `valor_maximo`,`valor_promedio`, `valor_minimo_autodata`, `valor_maximo_autodata`, `valor_promedio_autodata`, `msg`, `porcentajes_aplicados`, `cuenta`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
		$stmt->bind_param("sssssssssssssssssssssssss",$name,$email,$phone,$na,$date,$km,$ftecnica,$cduenios,$vpermuta,$vpretendido,$brand,$anio,$version,$model,$jsonauto,$txtauto,$valor_minimo_ml,$valor_maximo_ml,$valor_promedio_ml,$valor_minimo_autodata,$valor_maximo_autodata,$valor_promedio_autodata,$msgbd,$paplicados,$uno);
		if ($stmt->execute()) {
			$id_cotizacion = $stmt->insert_id;
			return array("codigo"=>200, "mensaje"=> "Valor Compra Motorlider","error"=>0,'cotizacion'=>$id_cotizacion,'valores'=>json_decode($response));
		} else {
			LogCron("Error al insertar " . $stmt->errno . 'MSG: ' . $stmt->error);
			return array("codigo"=>500, "mensaje"=> "Error al insertar la cotizacion.","error"=>500);
		}
	}

	/* pricesData - Parámetros POST: brand,model,anio,version,km */
	function pricesData($bd){	
		LogCron("\n\n------- START getPricesData -------");
		$access_token = motorlider_ml_token();
		$brandmodel  = $bd->query("SELECT brand.id_marca, model.id_model FROM act_marcas as brand, act_modelo as model WHERE brand.id = {$_POST['brand']} AND model.id = {$_POST['model']}");

		if($brandmodel->num_rows > 0) {
			LogCron("brand " . $_POST['brand']);
			LogCron("model " . $_POST['model']);
			LogCron("obtuve la marca & modelo voy contra ML");
			$brandmodel = $brandmodel->fetch_all(MYSQLI_ASSOC);

			$brand = $brandmodel[0]['id_marca'];
			$model = $brandmodel[0]['id_model'];
			$anio = $_POST['anio'];
			$familia = $_POST['version'];
			$km = $_POST['km'];

			LogCron("brand " . $brand);
			LogCron("model " . $model);
			LogCron("anio " . $anio);
			LogCron("familia " . $familia);
			LogCron("km " . $km);

			$nameq = "";
			if(!(int)$familia){
				$nameq = $familia;
			} else {
				$v = '&SHORT_VERSION='.$familia;
				$vName = apiDataNameVersion($v);
				foreach($vName as $key => $version) {
					$nameq = $version->name;
					break;
				}
			}

			if(!(int)$familia){
				$search = 'q='.$nameq.'&category=MLU1744&BRAND='.$brand.'&MODEL='.$model.'&VEHICLE_YEAR='.$anio.'-'.$anio;
			} else {
				$search = 'category=MLU1744&BRAND='.$brand.'&MODEL='.$model.'&VEHICLE_YEAR='.$anio.'-'.$anio;
			}

			if((int)$familia > 0){
				$search = $search . '&SHORT_VERSION='.$familia;
			}

			if((int)$km >= 0){
				$kmstart = (int)$km;
				$kmend = (int)$km;
				$search = $search . '&KILOMETERS='.$kmstart.'km-'.$kmend.'km';
			} else {
				LogCron("KM negativos");
				return array("codigo"=>500, "mensaje"=> "No se pudo obtener los precios - KM negativos.","error"=>500);
			}

			LogCron("https://api.mercadolibre.com/sites/MLU/search?" . $search);

			$hash = hash('md5',$_POST['brand'].$_POST['model'].$anio.date("Ymd G:i:s"));

			$valorMaximo = $bd->query("SELECT valor FROM ponderador_valor_maximo");
			if($valorMaximo->num_rows > 0) {
				$valorMaximo = $valorMaximo->fetch_all(MYSQLI_ASSOC);
				$valorMaximo = $valorMaximo[0]['valor'];
			}

			LogCron("Tope Maximo: " . $valorMaximo);
			LogResult("\n\nTope Máximo de búsqueda: " . $valorMaximo,$_POST['brand'],$_POST['model'],$anio,$hash);

			LogResult("\nPrimera búsqueda",$_POST['brand'],$_POST['model'],$anio,$hash);
			LogResult("https://api.mercadolibre.com/sites/MLU/search?" . $search,$_POST['brand'],$_POST['model'],$anio,$hash);

			$products_ml = apiData($search);
			LogCron("products_ml results: " . count($products_ml->results));

			//NO tengo resultados con lo parametros pasados
			$products_ml_total = $products_ml->results;
			$count = 0;
			$query = "";
			$query_extra = "";

			LogCron("products_ml_total results: " . count($products_ml_total));
			LogResult("Resultados de la búsqueda: " . count($products_ml_total),$_POST['brand'],$_POST['model'],$anio,$hash);

			while (count($products_ml_total) < $valorMaximo) {
				$count = $count + 1;
				LogCron("while count " . $count);
				if ($count === 1) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 5k
				} else if ($count === 2) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 10k
				} else if ($count === 3) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 20k
				} else if ($count === 4) {
					$query = changeYear($search, $anio, $count); //cambio el año restando 1 año
				} else if ($count === 5) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 5k
					$query = changeYear($query, $anio, $count); //cambio el año restando 1 año
				} else if ($count === 6) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 10k
					$query = changeYear($query, $anio, $count); //cambio el año restando 1 año
				} else if ($count === 7) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 20k
					$query = changeYear($query, $anio, $count); //cambio el año restando 1 año
				} else if ($count === 8) {
					$query = changeYear($search, $anio, $count); //cambio el año sumando 1 año
				} else if ($count === 9) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 5k
					$query = changeYear($query, $anio, $count); //cambio el año sumando 1 año
				} else if ($count === 10) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 10k
					$query = changeYear($query, $anio, $count); //cambio el año sumando 1 año
				} else if ($count === 11) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 20k
					$query = changeYear($query, $anio, $count); //cambio el año sumando 1 año
				} else if ($count === 12) {
					$query = changeYear($search, $anio, $count); //cambio el año sumando +/- 1 año
				} else if ($count === 13) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 5k
					$query = changeYear($query, $anio, $count); //cambio el año sumando +/- 1 año
				} else if ($count === 14) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 10k
					$query = changeYear($query, $anio, $count); //cambio el año sumando +/- 1 año
				}  else if ($count === 15) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 20k
					$query = changeYear($query, $anio, $count); //cambio el año sumando +/- 1 año
				} else if ($count === 16) {
					break;
				}

				LogCron("https://api.mercadolibre.com/sites/MLU/search?" . $query);
				LogResult("https://api.mercadolibre.com/sites/MLU/search?" . $query,$_POST['brand'],$_POST['model'],$anio,$hash);

				$products_ml = apiData($query);
				LogCron("products_ml count " . count($products_ml->results));
				LogResult("Resultados de la consulta: " . count($products_ml->results),$_POST['brand'],$_POST['model'],$anio,$hash);
				if( (count($products_ml->results) > 0) && (count($products_ml_total) == 0) ){
					$products_ml_total = $products_ml->results;
				} else if( (count($products_ml->results) > 0) && (count($products_ml_total) > 0) ){
					foreach($products_ml->results as $ml){
						$view = false;
						LogCron("MLU " . $ml->id);
						foreach($products_ml_total as $p){
							if($ml->id == $p->id){
								$view = true;
								break;
							}
						}
						if($view == false){
							array_push($products_ml_total, $ml);
						}
					}
				}
				LogResult("Resultados totales: " . count($products_ml_total),$_POST['brand'],$_POST['model'],$anio,$hash);
			}

			if(count($products_ml_total) < $valorMaximo){
				$kmstart = (int)$km;
				$kmend = (int)$km;
				$anio = $_POST['anio'];
				$kmend = $kmend + 20000;
				$kmstart = $kmstart - 20000;
				$kmstart = $kmstart < 0 ? 0 : $kmstart;
				$menor_year = (int)$anio - 1;
				$mayor_year = (int)$anio + 1;

				$nameq = str_replace(" ","%0A",$nameq);
				$query_extra = 'q='.$nameq.'&category=MLU1744&BRAND='.$brand.'&MODEL='.$model.'&VEHICLE_YEAR='.$menor_year.'-'.$mayor_year.'&KILOMETERS='.$kmstart.'km-'.$kmend.'km';
				
				LogCron("query_extra");
				LogCron("https://api.mercadolibre.com/sites/MLU/search?" . $query_extra);

				LogResult("query_extra",$_POST['brand'],$_POST['model'],$anio,$hash);
				LogResult("https://api.mercadolibre.com/sites/MLU/search?" . $query_extra,$_POST['brand'],$_POST['model'],$anio,$hash);

				$ch = curl_init();
                curl_setopt_array($ch, array(
                  CURLOPT_URL => 'https://api.mercadolibre.com/sites/MLU/search?' . $query_extra,
                  CURLOPT_RETURNTRANSFER => true,
                  CURLOPT_TIMEOUT => 0,
                  CURLOPT_FOLLOWLOCATION => true,
                  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                  CURLOPT_CUSTOMREQUEST => 'GET',
				  CURLOPT_HTTPHEADER => array(
					"Authorization: Bearer" . $access_token
				  ),
                ));
				$products_ml = json_decode(curl_exec($ch));
				
				if(isset($products_ml->results)){
					LogCron("products_ml count extra " . count($products_ml->results));
					LogResult("Resultados extra: " . count($products_ml->results),$_POST['brand'],$_POST['model'],$anio,$hash);
					if( (count($products_ml->results) > 0) && (count($products_ml_total) == 0) ){
						$products_ml_total = $products_ml->results;
					} else if( (count($products_ml->results) > 0) && (count($products_ml_total) > 0) ){
						foreach($products_ml->results as $ml){
							$view = false;
							LogCron("MLU " . $ml->id);
							foreach($products_ml_total as $p){
								if($ml->id == $p->id){
									$view = true;
									break;
								}
							}
							if($view == false){
								array_push($products_ml_total, $ml);
							}
						}
					}
				}
			}

			if(count($products_ml_total) > 0){
				LogCron("tengo algun resultado, voy a obtener los precios");
				LogCron("total " . count($products_ml_total));
				LogResult("Resultados Finales: " . count($products_ml_total),$_POST['brand'],$_POST['model'],$anio,$hash);
				$all_price = [];
				$total = 0;
				$dollar = 0;
				foreach($products_ml_total as $vehi){
					$dollar = $vehi->price;
					LogCron("precio " . $dollar);

					if($vehi->currency_id != 'USD'){
						LogCron("precio en $ voy a buscar cotizacion a BD");
						$cotizacion  = $bd->query("SELECT dolar FROM ponderador_valor_dolar WHERE id_valor_dolar = 1");
						if($cotizacion->num_rows > 0) {
							$cotizacion = $cotizacion->fetch_all(MYSQLI_ASSOC);
							$cotizacion = $cotizacion[0]['dolar'];
							LogCron("cotizacion en BD >>> " . $cotizacion);
							$dollar = round($dollar / $cotizacion, 0, PHP_ROUND_HALF_UP);
							LogCron("conversion " . $dollar);
						}
					}
					foreach($vehi->attributes as $filters){
						if($filters->id === 'KILOMETERS'){
							if((int)$filters->value_name == 0){
								LogCron("precio 0KM " . $dollar);
								$total = $total + 1;
								$price = round($dollar / 1.22, 0, PHP_ROUND_HALF_UP);
								$all_price [] = $price;
							} else {
								$all_price [] = $dollar;
							}
						}
					}
				}

				$promedio = 0;
				$count_promedio = 0;
				foreach($all_price as $p){
					$promedio = $promedio + $p;
					$count_promedio = $count_promedio + 1;
				}
				$average = round($promedio / $count_promedio, 0, PHP_ROUND_HALF_UP);
				LogCron("promedio " . $average);

				if($query == ""){
					$query = $search;
				}

				$response = '{"valor_maximo":'.max($all_price).',"valor_minimo":'.min($all_price).',"valor_promedio":'.$average.',"total":'.count($products_ml_total).',"total0km":'.$total.',"hash":"'.$hash.'"}';
				return array("codigo"=>200, "mensaje"=> "Obtención de precios exitosa.","error"=>0,"precios"=>json_decode($response));
			} else {
				LogCron("no tengo ningun resultado en pricesData, solamente " . count($products_ml_total));
				return array("codigo"=>500, "mensaje"=> "No se pudo obtener los precios.","error"=>501,"hash"=>$hash);
			}
		} else {
			LogCron("Error al obtener marca & modelos pricesData");
			return array("codigo"=>500, "mensaje"=> "No se pudo obtener los precios.","error"=>500,"hash"=>"0");
		}
	}

	/* locations */
	function locations($bd){
		$bdlocations = $bd->query("SELECT * FROM agenda_sucursal");

		if($bdlocations->num_rows > 0) {
			LogCron("obtuve las sucursales correctamente");
			$locations = array();
			while($office = $bdlocations->fetch_object()){
				$locations[] = array(
					"id" => $office->id_sucursal,
					"nombre" => $office->nombre,
					"direccion" => $office->direccion,
					"email" => $office->email,
					"telefono" => $office->telefono,
					"ubicacion" => $office->ubicacion
				);
			}

			return array("codigo"=>200, "mensaje"=> "Obtención de sucrusales exitosas.","error"=>0,"locations"=>$locations);
		} else {
			LogCron("Error al obtener las sucursales " . $bdlocations->error);
			return array("codigo"=>500, "mensaje"=> "No se pudo obtener las sucursales.","error"=>500);
		}
	} 

	/* calendar - Parámetros POST: location, anio, mes */
	function calendar($bd){	
		LogCron("\n\n------- START getCalendar -------");

		$sucursal = $_POST['location'];
		$year = $_POST['anio'];
		$month = $_POST['mes'];

		LogCron("sucursal " . $sucursal);
		LogCron("año " . $year);
		LogCron("mes " . $month);

		if(!isset($sucursal) || !isset($year) || !isset($month)){
			return array("codigo"=>500, "mensaje"=> "No se pudo obtener el calendario.","error"=>500);
		}

		if(!is_numeric($year) || !is_numeric($month)){
			return array("codigo"=>500, "mensaje"=> "No se pudo obtener el calendario.","error"=>500);
		}

		$daysInMonth 	= date("t", mktime(0, 0, 0, $month, 1, $year));
		$firstDay 		= date("w", mktime(0, 0, 0, $month, 1, $year));

		if ($firstDay == 0)
		$firstDay = 7;

		$tempDays 		= $firstDay + $daysInMonth;
		$weeksInMonth 	= ceil($tempDays / 7);

		$calendar = array();

		for ($i = 1; $i <= $daysInMonth + $firstDay; $i++) {
			$calendar[$i] = $i - $firstDay + 1;
		}

		$meses = array("Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre");

		if($year < date('Y')){
			LogCron("Error >> El año es menor al actual");
			LogCron("year " . $year);
			LogCron("dateY " . (date('Y')));
			return array("codigo"=>500, "mensaje"=> "No puede seleccionar horarios con fecha anterior a la de hoy.","error"=>500);
		}

		if ($month < (date('m'))) {
			LogCron("Error >> El mes es menor al actual");
			LogCron("month " . $month);
			LogCron("dateM " . (date('m')));
			return array("codigo"=>500, "mensaje"=> "No puede seleccionar horarios con fecha anterior a la de hoy.","error"=>500);
		}

		LogCron("mes " . $meses[$month - 1]);

		$array_calendar = array();

		$array_calendar['sucursal'] = $sucursal;
		$array_calendar['mes'] = $meses[$month - 1];
		$array_calendar['anio'] = (int)$year;

		$numero_mes = (intval($month) < 10) ? '0' . $month : $month;
		$j = 1;
		$fecha_actual = date("d-m-Y");
		$dia_actual_mas_dos = date("d",strtotime($fecha_actual."+ 2 days"));
		$array_fechas = array();
		for ($w = 0; $w <= $weeksInMonth; $w++) {
			for ($i = 0; $i < 7; $i++) {
				if (isset($calendar[$j])) {
					if ($calendar[$j] > 0 && $calendar[$j] <= $daysInMonth) {
						$clase = '';
						if ((($calendar[$j] < $dia_actual_mas_dos) && ($month == date('m'))) || (($month < date('m')) && ($year == date('y'))) || ($year < date('y'))) {
							//
						} else {
							$now = time();
							$your_date = strtotime($year . "-" . $month . "-" . $calendar[$j]);
							$datediff = $your_date - $now;
							$cantdias = floor($datediff / (60 * 60 * 24));
							if ($cantdias >= 90) {
								//invalido
							} else {
								$fecha_mysql = $year . "-" . $month . "-" . $calendar[$j];
								setlocale(LC_ALL, "es_ES@euro", "es_ES", "esp");
								$date = DateTime::createFromFormat("Y-m-d", $fecha_mysql);
								$dia = strftime("%A", $date->getTimestamp());

								$horario_estable = $bd->query('SELECT * FROM agenda_estables
								INNER JOIN agenda_horas
								ON agenda_estables.id_sucursal = "' . $sucursal . '"
								where agenda_horas.id_estables = agenda_estables.id_estable
								and agenda_estables.dia = "' . utf8_encode($dia) . '"');

								if ($horario_estable) {
									$sql_aux = $bd->query('SELECT COUNT(*) as cantidad FROM agenda_estables
									INNER JOIN agenda_horas
									ON agenda_estables.id_sucursal = "' . $sucursal . '"
									where agenda_horas.id_estables = agenda_estables.id_estable
									and agenda_estables.dia = "' . utf8_encode($dia) . '"');
									$sql_aux = $sql_aux->fetch_array(MYSQLI_ASSOC);
									$cantidad_turnos_disponibles = $sql_aux['cantidad'];

									$sql_aux = $bd->query('SELECT COUNT(*) as cantidad FROM agendas
									WHERE id_sucursal = "' . $sucursal . '"
									and fecha = "' . $fecha_mysql . '"');
									$sql_aux = $sql_aux->fetch_array(MYSQLI_ASSOC);
									$cantidad_turnos_reservados = $sql_aux['cantidad'];

									$cantidad_turnos_cancelados = 0;

									if (($cantidad_turnos_reservados + $cantidad_turnos_cancelados) < $cantidad_turnos_disponibles) {
										$clase = 'verde_central';
									}
								}
							}
						}
						if($clase == 'verde_central'){
							LogCron("dias_disponibles " . $calendar[$j]);
							$array_fechas[] = array(
								"fecha" => $year . "-" . $numero_mes . "-" . $calendar[$j],
								"dia" => $calendar[$j]
							);
						}
					}
				}
				$j++;
			}
		}

		if(count($array_fechas)<=0){
			LogCron("array_fechas vacio no hay dias disponibles");
			return array("codigo"=>500, "mensaje"=> "No puedes reservar horarios con más de 90 días de anticipación.","error"=>500);
		}

		$array_calendar['dias_disponibles'] = $array_fechas;

		return array("codigo"=>200, "mensaje"=> "Obtención del calendario exitoso.","error"=>0,"calendar"=>$array_calendar);
	}

	/* schedules - Parámetros POST: location, fecha */
	function schedules($bd){	
		LogCron("\n\n------- START getSchedules -------");

		$sucursal = $_POST['location'];
		$fecha = $_POST['date'];

		LogCron("sucursal " . $sucursal);
		LogCron("fecha " . $fecha);

		if(!isset($sucursal) || !isset($fecha)){
			return array("codigo"=>500, "mensaje"=> "No se pudo obtener los horarios.","error"=>500);
		}

		setlocale(LC_ALL,"es_ES@euro","es_ES","esp");
		$date = DateTime::createFromFormat("Y-m-d", $fecha);
		$dia = strftime("%A", $date->getTimestamp());

		$year = $date->format('Y');
		if($year < date('Y')){
			LogCron("Error >> El año es menor al actual");
			LogCron("year " . $year);
			LogCron("dateY " . (date('Y')));
			return array("codigo"=>500, "mensaje"=> "No hay horarios para la fecha indicada.","error"=>500);
		}

		$fecha_actual = date("d-m-Y");
		$dia_actual_mas_dos = date("d",strtotime($fecha_actual."+ 2 days"));
		$day = $date->format('d');
		if($day < $dia_actual_mas_dos){
			LogCron("Error >> El dia es menor al permitido");
			LogCron("day " . $day);
			LogCron("dia_actual_mas_dos " . $dia_actual_mas_dos);
			return array("codigo"=>500, "mensaje"=> "No hay horarios para la fecha indicada.","error"=>500);
		}

		$now = time();
		$your_date = strtotime($fecha);
		$datediff = $your_date - $now;
		$cantdias = floor($datediff / (60 * 60 * 24));
		if ($cantdias >= 90) {
			LogCron("Error >> Más de 90 días de anticipación");
			return array("codigo"=>500, "mensaje"=> "No hay horarios con más de 90 días de anticipación.","error"=>500);
		}

		$horarios = $bd->query('SELECT hora_comienzo FROM agenda_particulares
		INNER JOIN agenda_horas_particulares
		ON agenda_particulares.id_particular = agenda_horas_particulares.id_particular
		WHERE agenda_particulares.id_sucursal = "'.$sucursal.'"
		AND agenda_particulares.fecha ="'.$fecha.'"
		AND agenda_particulares.cancelado = 0
		AND agenda_horas_particulares.hora_comienzo
		NOT IN (SELECT hora_comienzo FROM agenda_particulares
		INNER JOIN agenda_horas_particulares
		ON agenda_particulares.id_particular = agenda_horas_particulares.id_horas_particular
		WHERE agenda_particulares.fecha = "'.($fecha).'"
		AND agenda_particulares.cancelado = 1)

		UNION

		SELECT hora_comienzo
		FROM agenda_horas
		INNER JOIN agenda_estables
		ON agenda_horas.id_estables = agenda_estables.id_estable
		WHERE agenda_estables.dia = "'.utf8_encode($dia).'"
		AND agenda_estables.id_sucursal = "'.$sucursal.'"
		AND hora_comienzo NOT IN (SELECT hora_comienzo
		FROM agenda_horas_particulares
		INNER JOIN agenda_particulares
		ON agenda_horas_particulares.id_particular = agenda_particulares.id_particular WHERE
		agenda_particulares.fecha = "'.($fecha).'" AND agenda_particulares.cancelado = 1)

		ORDER BY hora_comienzo asc');

		$array_horarios = array();

		$array_horarios['sucursal'] = $sucursal;
		$array_horarios['fecha'] = strftime('%d/%m/%Y', strtotime($fecha));

		$val_fec = 0;

		if(date('d/m/Y') == strftime('%d/%m/%Y', strtotime($fecha))){
			$val_fec = 1;
		}

		if($horarios->num_rows > 0) {
			LogCron("Hay horarios");
			$array_horas = array();
			while($horario = $horarios->fetch_object()){
				$query_horario_ocupado = $bd->query('SELECT * FROM agendas WHERE fecha = "'.($fecha).'"  AND hora = "'.$horario->hora_comienzo.'"');
				$horario_ocupado = $query_horario_ocupado->fetch_all(MYSQLI_ASSOC);

				date_default_timezone_set ('America/Montevideo');
				$time = time();
				$hora_actual = date("H:i", $time);

				if (count($horario_ocupado) == 0) {
					if(($horario->hora_comienzo <= $hora_actual) && $val_fec == 1) {
						LogCron("horario disabled");
					} else {
						LogCron("horas_disponibles " . $horario->hora_comienzo);
						$array_horas[] = array(
							"hora" => $horario->hora_comienzo
						);
					}

				}
			}

			if(count($array_horas)<=0){
				LogCron("array_horas vacio no hay horas disponibles");
				return array("codigo"=>500, "mensaje"=> "No hay horarios para la fecha indicada.","error"=>500);
			}

			$array_horarios['horas_disponibles'] = $array_horas;

			return array("codigo"=>200, "mensaje"=> "Obtención de horarios exitoso.","error"=>0,"schedules"=>$array_horarios);
		} else {
			LogCron("NO hay horarios");
			return array("codigo"=>500, "mensaje"=> "No hay horarios para la fecha indicada.","error"=>500);
		}
	}

	/* scheduleInspection - Parámetros POST: location,fecha,hora,modelo,marca,anio,familia,auto,nombre,email,telefono,cotizacion */
	function scheduleInspection($bd){	
		include('./../adm/includes/funciones.php');
		include('./../adm/includes/class.phpmailer.php');

		LogCron("\n\n------- START getScheduleInspection -------");

		$sucursal = $_POST['location'];
		$fecha = $_POST['date'];
		$hora = $_POST['hora'];
		$modelo = $_POST['modelo'];
		$marca = $_POST['marca'];
		$anio = $_POST['anio'];
		$familia = $_POST['familia'];
		$auto = $_POST['auto'];
		$nombre = $_POST['nombre'];
		$email = $_POST['email'];
		$telefono = $_POST['telefono'];
		$id_cotizacion = $_POST['id_cotizacion'];

		LogCron("sucursal " . $sucursal);
		LogCron("fecha " . $fecha);
		LogCron("hora " . $hora);
		LogCron("modelo " . $modelo);
		LogCron("marca " . $marca);
		LogCron("anio " . $anio);
		LogCron("familia " . $familia);
		LogCron("auto " . $auto);
		LogCron("nombre " . $nombre);
		LogCron("email " . $email);
		LogCron("telefono " . $telefono);
		LogCron("id_cotizacion " . $id_cotizacion);

		if(!isset($sucursal) || !isset($fecha) || !isset($hora) || !isset($modelo) || !isset($marca) || !isset($anio) || !isset($familia) || !isset($auto) || !isset($nombre) || !isset($email) || !isset($telefono)){
			return array("codigo"=>500, "mensaje"=> "No se pudo obtener los horarios.","error"=>500);
		}

		$query_existe_agenda = $bd->query('SELECT * FROM agendas WHERE id_cotizacion = "' . ($id_cotizacion) . '" AND fecha > NOW() ORDER BY fecha DESC;');
		$existe_agenda = $query_existe_agenda->fetch_array(MYSQLI_ASSOC);

		if (!$existe_agenda) {
			$sucursalesList = 'SELECT * FROM agenda_sucursal WHERE id_sucursal = "' . ($sucursal) . '"';
			$sucursales = $bd->query($sucursalesList);
			$sucursales = $sucursales->fetch_array(MYSQLI_ASSOC);
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

			$cero = 0;
			$na = 'N/A';
			$insertar = $bd->prepare("INSERT INTO agendas (`id_sucursal`, `fecha`, `hora`, `modelo`, `marca`, `anio`, `familia`, `auto`, `nombre`, `ci`, `email`, `telefono`, `rand_string`, `direccion`, `inspeccion_domiciliaria`, `id_cotizacion`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
			$insertar->bind_param("ssssssssssssssss",$sucursal,$fecha,$hora,$modelo,$marca,$anio,$familia,$auto,$nombre,$cero,$email,$telefono,$rand_string,$na,$cero,$id_cotizacion);
			if ($insertar->execute()) {
				LogCron("Se inserto la inspeccion, se procede a enviar eamil");
				$cotizacion = $bd->query('SELECT * FROM cotizaciones_generadas WHERE id_cotizaciones_generadas = ' . $id_cotizacion);
				$cotizacion = $cotizacion->fetch_array(MYSQLI_ASSOC);

				$mailP = new PHPMailer(true);
				$mailP->isHTML(true);
				$mailP->From = "noresponder@motorliderweb.com.uy";
				$mailP->FromName = "MOTORLIDER";
				$mailP->AddAddress($mailSolicitante, $nameSolicitante);
				$mailP->addBCC('info@motorlider.com.uy');
				/*$mailP->addBCC('marcos.ingold@motorlider.com.uy');
				$mailP->addBCC('santiago@sodio.com.uy');
				$mailP->addBCC('daniel@sodio.com.uy');*/
				$mailP->addBCC('gfigueroa.ac@gmail.com');
				$mailP->Subject = "Reserva de Agenda MOTORLIDER";
				$mailP->CharSet = "UTF-8";
				$mailP->AddEmbeddedImage("./../img/logo.png", "my-attach", "logo.png");
				$mailP->AddEmbeddedImage("./../img/mapa.png", "my-attach2", "mapa.png");

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

				$mailP->AddAttachment($name.".ics");

				if ($mailP->send()) {
					unlink($name.".ics");
					return array("codigo"=>200, "mensaje"=> "Se agendo la inspección mecánica con éxito y se mando el email.","error"=>0);
				} else {
					return array("codigo"=>500, "mensaje"=> "Error al enviar email.","error"=>500);
				}
			} else {
				LogCron("Error al insertar " . $insertar->error);
				return array("codigo"=>500, "mensaje"=> "Error de Base de Datos al ingresar la inspeccion.","error"=>500);
			}
		} else {
			LogCron("Ya existe una agenda para la cotizacion:" . $id_cotizacion);
			return array("codigo"=>500, "mensaje"=> "Ya existe una agenda para la cotizacion: " . $id_cotizacion,"error"=>500);
		}
	}

	/* pricesInternal - Parámetros POST: brand,model,anio,version,km */
	function pricesInternal($bd){	
		LogCron("\n\n------- START getPriceInternal -------");
		$access_token = motorlider_ml_token();
		$brandmodel  = $bd->query("SELECT brand.id_marca, model.id_model FROM act_marcas as brand, act_modelo as model WHERE brand.id = {$_POST['brand']} AND model.id = {$_POST['model']}");

		if($brandmodel->num_rows > 0) {
			LogCron("brand " . $_POST['brand']);
			LogCron("model " . $_POST['model']);
			LogCron("obtuve la marca & modelo voy contra ML");
			$brandmodel = $brandmodel->fetch_all(MYSQLI_ASSOC);

			$brand = $brandmodel[0]['id_marca'];
			$model = $brandmodel[0]['id_model'];
			$anio = $_POST['anio'];
			$familia = $_POST['version'];
			$km = $_POST['km'];

			LogCron("brand " . $brand);
			LogCron("model " . $model);
			LogCron("anio " . $anio);
			LogCron("familia " . $familia);
			LogCron("km " . $km);

			$nameq = "";
			if(!(int)$familia){
				$nameq = $familia;
			} else {
				$v = '&SHORT_VERSION='.$familia;
				$vName = apiDataNameVersion($v);
				foreach($vName as $key => $version) {
					$nameq = $version->name;
					break;
				}
			}

			if(!(int)$familia){
				$search = 'q='.$nameq.'&category=MLU1744&BRAND='.$brand.'&MODEL='.$model.'&VEHICLE_YEAR='.$anio.'-'.$anio;
			} else {
				$search = 'category=MLU1744&BRAND='.$brand.'&MODEL='.$model.'&VEHICLE_YEAR='.$anio.'-'.$anio;
			}

			if((int)$familia > 0){
				$search = $search . '&SHORT_VERSION='.$familia;
			}

			if((int)$km >= 0){
				$kmstart = (int)$km;
				$kmend = (int)$km;
				$search = $search . '&KILOMETERS='.$kmstart.'km-'.$kmend.'km';
			} else {
				LogCron("KM negativos");
				return array("codigo"=>500, "mensaje"=> "No se pudo obtener los precios - KM negativos.","error"=>500);
			}

			LogCron("https://api.mercadolibre.com/sites/MLU/search?" . $search);

			$hash = hash('md5',$_POST['brand'].$_POST['model'].$anio.date("Ymd G:i:s"));

			$valorMaximo = $bd->query("SELECT valor FROM ponderador_valor_maximo");
			if($valorMaximo->num_rows > 0) {
				$valorMaximo = $valorMaximo->fetch_all(MYSQLI_ASSOC);
				$valorMaximo = $valorMaximo[0]['valor'];
			}

			LogCron("Tope Maximo: " . $valorMaximo);
			LogResult("\n\nTope Máximo de búsqueda: " . $valorMaximo,$_POST['brand'],$_POST['model'],$anio,$hash);

			LogResult("\nPrimera búsqueda",$_POST['brand'],$_POST['model'],$anio,$hash);
			LogResult("https://api.mercadolibre.com/sites/MLU/search?" . $search,$_POST['brand'],$_POST['model'],$anio,$hash);

			$products_ml = apiData($search);
			LogCron("products_ml results: " . count($products_ml->results));

			//NO tengo resultados con lo parametros pasados
			$products_ml_total = $products_ml->results;
			$count = 0;
			$query = "";
			$query_extra = "";

			LogCron("products_ml_total results: " . count($products_ml_total));
			LogResult("Resultados de la búsqueda: " . count($products_ml_total),$_POST['brand'],$_POST['model'],$anio,$hash);

			while (count($products_ml_total) < $valorMaximo) {
				$count = $count + 1;
				LogCron("while count " . $count);
				if ($count === 1) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 5k
				} else if ($count === 2) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 10k
				} else if ($count === 3) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 20k
				} else if ($count === 4) {
					$query = changeYear($search, $anio, $count); //cambio el año restando 1 año
				} else if ($count === 5) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 5k
					$query = changeYear($query, $anio, $count); //cambio el año restando 1 año
				} else if ($count === 6) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 10k
					$query = changeYear($query, $anio, $count); //cambio el año restando 1 año
				} else if ($count === 7) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 20k
					$query = changeYear($query, $anio, $count); //cambio el año restando 1 año
				} else if ($count === 8) {
					$query = changeYear($search, $anio, $count); //cambio el año sumando 1 año
				} else if ($count === 9) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 5k
					$query = changeYear($query, $anio, $count); //cambio el año sumando 1 año
				} else if ($count === 10) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 10k
					$query = changeYear($query, $anio, $count); //cambio el año sumando 1 año
				} else if ($count === 11) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 20k
					$query = changeYear($query, $anio, $count); //cambio el año sumando 1 año
				} else if ($count === 12) {
					$query = changeYear($search, $anio, $count); //cambio el año sumando +/- 1 año
				} else if ($count === 13) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 5k
					$query = changeYear($query, $anio, $count); //cambio el año sumando +/- 1 año
				} else if ($count === 14) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 10k
					$query = changeYear($query, $anio, $count); //cambio el año sumando +/- 1 año
				}  else if ($count === 15) {
					$query = changeKm($search, $count); //cambio los km sumando +/- 20k
					$query = changeYear($query, $anio, $count); //cambio el año sumando +/- 1 año
				} else if ($count === 16) {
					break;
				}

				LogCron("https://api.mercadolibre.com/sites/MLU/search?" . $query);
				LogResult("https://api.mercadolibre.com/sites/MLU/search?" . $query,$_POST['brand'],$_POST['model'],$anio,$hash);

				$products_ml = apiData($query);
				LogCron("products_ml count " . count($products_ml->results));
				LogResult("Resultados de la consulta: " . count($products_ml->results),$_POST['brand'],$_POST['model'],$anio,$hash);
				if( (count($products_ml->results) > 0) && (count($products_ml_total) == 0) ){
					$products_ml_total = $products_ml->results;
				} else if( (count($products_ml->results) > 0) && (count($products_ml_total) > 0) ){
					foreach($products_ml->results as $ml){
						$view = false;
						LogCron("MLU " . $ml->id);
						foreach($products_ml_total as $p){
							if($ml->id == $p->id){
								$view = true;
								break;
							}
						}
						if($view == false){
							array_push($products_ml_total, $ml);
						}
					}
				}
				LogResult("Resultados totales: " . count($products_ml_total),$_POST['brand'],$_POST['model'],$anio,$hash);
			}

			if(count($products_ml_total) < $valorMaximo){
				$kmstart = (int)$km;
				$kmend = (int)$km;
				$anio = $_POST['anio'];
				$kmend = $kmend + 20000;
				$kmstart = $kmstart - 20000;
				$kmstart = $kmstart < 0 ? 0 : $kmstart;
				$menor_year = (int)$anio - 1;
				$mayor_year = (int)$anio + 1;

				$nameq = str_replace(" ","%0A",$nameq);
				$query_extra = 'q='.$nameq.'&category=MLU1744&BRAND='.$brand.'&MODEL='.$model.'&VEHICLE_YEAR='.$menor_year.'-'.$mayor_year.'&KILOMETERS='.$kmstart.'km-'.$kmend.'km';
				
				LogCron("query_extra");
				LogCron("https://api.mercadolibre.com/sites/MLU/search?" . $query_extra);

				LogResult("query_extra",$_POST['brand'],$_POST['model'],$anio,$hash);
				LogResult("https://api.mercadolibre.com/sites/MLU/search?" . $query_extra,$_POST['brand'],$_POST['model'],$anio,$hash);

				$ch = curl_init();
                curl_setopt_array($ch, array(
                  CURLOPT_URL => 'https://api.mercadolibre.com/sites/MLU/search?' . $query_extra,
                  CURLOPT_RETURNTRANSFER => true,
                  CURLOPT_TIMEOUT => 0,
                  CURLOPT_FOLLOWLOCATION => true,
                  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                  CURLOPT_CUSTOMREQUEST => 'GET',
				  CURLOPT_HTTPHEADER => array(
					"Authorization: Bearer" . $access_token
				  ),
                ));
				$products_ml = json_decode(curl_exec($ch));
				
				if(isset($products_ml->results)){
					LogCron("products_ml count extra " . count($products_ml->results));
					LogResult("Resultados extra: " . count($products_ml->results),$_POST['brand'],$_POST['model'],$anio,$hash);
					if( (count($products_ml->results) > 0) && (count($products_ml_total) == 0) ){
						$products_ml_total = $products_ml->results;
					} else if( (count($products_ml->results) > 0) && (count($products_ml_total) > 0) ){
						foreach($products_ml->results as $ml){
							$view = false;
							LogCron("MLU " . $ml->id);
							foreach($products_ml_total as $p){
								if($ml->id == $p->id){
									$view = true;
									break;
								}
							}
							if($view == false){
								array_push($products_ml_total, $ml);
							}
						}
					}
				}
			}

			if(count($products_ml_total) > 0){
				LogCron("tengo algun resultado, voy a obtener los precios");
				LogCron("total " . count($products_ml_total));
				LogResult("Resultados Finales: " . count($products_ml_total),$_POST['brand'],$_POST['model'],$anio,$hash);
				$all_price = [];
				$total = 0;
				$dollar = 0;
				foreach($products_ml_total as $vehi){
					$dollar = $vehi->price;
					LogCron("precio " . $dollar);

					if($vehi->currency_id != 'USD'){
						LogCron("precio en $ voy a buscar cotizacion a BD");
						$cotizacion  = $bd->query("SELECT dolar FROM ponderador_valor_dolar WHERE id_valor_dolar = 1");
						if($cotizacion->num_rows > 0) {
							$cotizacion = $cotizacion->fetch_all(MYSQLI_ASSOC);
							$cotizacion = $cotizacion[0]['dolar'];
							LogCron("cotizacion en BD >>> " . $cotizacion);
							$dollar = round($dollar / $cotizacion, 0, PHP_ROUND_HALF_UP);
							LogCron("conversion " . $dollar);
						}
					}
					foreach($vehi->attributes as $filters){
						if($filters->id === 'KILOMETERS'){
							if((int)$filters->value_name == 0){
								LogCron("precio 0KM " . $dollar);
								$total = $total + 1;
								$price = round($dollar / 1.22, 0, PHP_ROUND_HALF_UP);
								$all_price [] = $price;
							} else {
								$all_price [] = $dollar;
							}
						}
					}
				}

				$promedio = 0;
				$count_promedio = 0;
				foreach($all_price as $p){
					$promedio = $promedio + $p;
					$count_promedio = $count_promedio + 1;
				}
				$average = round($promedio / $count_promedio, 0, PHP_ROUND_HALF_UP);
				LogCron("promedio " . $average);

				if($query == ""){
					$query = $search;
				}

				$response = '{"valor_maximo":'.max($all_price).',"valor_minimo":'.min($all_price).',"valor_promedio":'.$average.',"total":'.count($products_ml_total).',"total0km":'.$total.',"hash":"'.$hash.'"}';
				return array("codigo"=>200, "mensaje"=> "Obtención de precios exitosa.","error"=>0,"precios"=>json_decode($response));
			} else {
				LogCron("no tengo ningun resultado en pricesInternal, solamente " . count($products_ml_total));
				return array("codigo"=>500, "mensaje"=> "No se pudo obtener los precios.","error"=>501,"hash"=>$hash);
			}
		} else {
			LogCron("Error al obtener marca & modelos pricesInternal");
			return array("codigo"=>500, "mensaje"=> "No se pudo obtener los precios.","error"=>500,"hash"=>"0");
		}
	}

	/* averageInternal - Parámetros POST: valor_minimo,valor_maximo */
	function averageInternal($bd){	
		LogCron("\n\n------- START averageInternal -------");

		$average = $_POST['promedio'];
		LogCron("average " . $average);

		//A,E,I,M,P,T,AA,AE,AI,AM,AP,AT variables porcentuales, llenan por admin
		$valor_venal = $bd->query("SELECT * FROM ponderador_valor_venal");

		$A = 0;
		$E = 0;
		$I = 0;
		$M = 0;
		$P = 0;
		$T = 0;
		$AA = 0;
		$AE = 0;
		$AI = 0;
		$AM = 0;
		$AP = 0;
		$AT = 0;

		if($valor_venal->num_rows > 0) {
			LogCron("obtuve los porcentajes, asigno los valores");
			$vv = $valor_venal->fetch_all(MYSQLI_ASSOC);
			foreach($vv as $v){
				if($v['key'] == 'A'){
					$A = $v['porcentaje'];
				} else if($v['key'] == 'E'){
					$E = $v['porcentaje'];
				} else if($v['key'] == 'I'){
					$I = $v['porcentaje'];
				} else if($v['key'] == 'M'){
					$M = $v['porcentaje'];
				} else if($v['key'] == 'P'){
					$P = $v['porcentaje'];
				} else if($v['key'] == 'T'){
					$T = $v['porcentaje'];
				} else if($v['key'] == 'AA'){
					$AA = $v['porcentaje'];
				} else if($v['key'] == 'AE'){
					$AE = $v['porcentaje'];
				} else if($v['key'] == 'AI'){
					$AI = $v['porcentaje'];
				} else if($v['key'] == 'AM'){
					$AM = $v['porcentaje'];
				} else if($v['key'] == 'AP'){
					$AP = $v['porcentaje'];
				} else if($v['key'] == 'AT'){
					$AT = $v['porcentaje'];
				}
			}
		} else {
			LogCron("Error al ponderador_valor_venal");
			return array("codigo"=>500, "mensaje"=> "No se pudo obtener los porcentajes.","error"=>500);
		}

		if($A == 0 || $E == 0 || $I == 0 || $M == 0 || $P == 0 || $T == 0 || $AA == 0 || $AE == 0 || $AI == 0 || $AM == 0 || $AP == 0 || $AT == 0){
			LogCron("Error al completar los procentajes, uno dio 0");
			LogCron($A);
			LogCron($E);
			LogCron($I);
			LogCron($M);
			LogCron($P);
			LogCron($P);
			LogCron($AA);
			LogCron($AE);
			LogCron($AI);
			LogCron($AM);
			LogCron($AP);
			LogCron($AT);
			return array("codigo"=>500, "mensaje"=> "No se pudo obtener los porcentajes.","error"=>500);
		}

		//C,G,K,Ñ,R,Y,AC,AG,AK,AÑ,AR,AY variables nominales, llenan por admin
		$valor = $bd->query("SELECT * FROM ponderador_valor");

		$C = 0;
		$G = 0;
		$K = 0;
		$Ñ = 0;
		$R = 0;
		$Y = 0;
		$AC = 0;
		$AG = 0;
		$AK = 0;
		$AÑ = 0;
		$AR = 0;
		$AY = 0;

		if($valor->num_rows > 0) {
			LogCron("obtuve los valores, asigno a las letras los valores");
			$vr = $valor->fetch_all(MYSQLI_ASSOC);
			foreach($vr as $v){
				if($v['key'] == 'C'){
					$C = $v['nominal'];
				} else if($v['key'] == 'G'){
					$G = $v['nominal'];
				} else if($v['key'] == 'K'){
					$K = $v['nominal'];
				} else if($v['key'] == 'Ñ'){
					$Ñ = $v['nominal'];
				} else if($v['key'] == 'R'){
					$R = $v['nominal'];
				} else if($v['key'] == 'Y'){
					$Y = $v['nominal'];
				} else if($v['key'] == 'AC'){
					$AC = $v['nominal'];
				} else if($v['key'] == 'AG'){
					$AG = $v['nominal'];
				} else if($v['key'] == 'AK'){
					$AK = $v['nominal'];
				} else if($v['key'] == 'AÑ'){
					$AÑ = $v['nominal'];
				} else if($v['key'] == 'AR'){
					$AR = $v['nominal'];
				} else if($v['key'] == 'AY'){
					$AY = $v['nominal'];
				}
			}
		} else {
			LogCron("Error al ponderador_valor");
			return array("codigo"=>500, "mensaje"=> "No se pudo obtener los valores.","error"=>500);
		}

		if($C == 0 || $G == 0 || $K == 0 || $Ñ == 0 || $R == 0 || $Y == 0 || $AC == 0 || $AG == 0 || $AK == 0 || $AÑ == 0 || $AR == 0 || $AY == 0){
			LogCron("Error al completar los valores, uno dio 0");
			return array("codigo"=>500, "mensaje"=> "No se pudo obtener los valores.","error"=>500);
		}

		$result  = 0;

		if($average < 5000){
			$result = ($average * $A) - $C;
		} else if($average >= 5000 && $average <= 10000){
			$result = ($average * $E) - $G;
		} else if($average >= 10000 && $average <= 15000){
			$result = ($average * $I) - $K;
		} else if($average >= 15000 && $average <= 20000){
			$result = ($average * $M) - $Ñ;
		} else if($average >= 20000 && $average <= 25000){
			$result = ($average * $P) - $R;
		}  else if($average >= 25000 && $average <= 30000){
			$result = ($average * $T) - $Y;
		}  else if($average >= 30000 && $average <= 35000){
			$result = ($average * $AA) - $AC;
		} else if($average >= 35000 && $average <= 40000){
			$result = ($average * $AE) - $AG;
		} else if($average >= 40000 && $average <= 45000){
			$result = ($average * $AI) - $AK;
		} else if($average >= 45000 && $average <= 50000){
			$result = ($average * $AM) - $AÑ;
		} else if($average >= 50000 && $average <= 60000){
			$result = ($average * $AP) - $AR;
		} else if($average >= 60000 && $average <= 70000){
			$result = ($average * $AT) - $AY;
		} else {
			return array("codigo"=>500, "mensaje"=> "Nuestro sistema no pudo estimar en forma automática el valor de tu vehículo, déjanos tus datos y nos comunicaremos a la brevedad","error"=>502);
		}

		$result = round($result, 0, PHP_ROUND_HALF_UP);

		$response = '{"promedio_ml":'.$average.',"promedio_motorlider":'.$result.'}';

		return array("codigo"=>200, "mensaje"=> "Precio promedio","error"=>0,'valores'=>json_decode($response));

	}

	/* internalQuotation - Parámetros POST: marca,modelo,anio,version,promedio,ficha_tecnica,cantidad_duenios,venta_permuta,..............................valor_pretendido */
	function internalQuotation($bd){	
		LogCron("\n\n------- START internalQuotation -------");

		$name = $_POST['name'];
		$email = $_POST['email'];
		$phone = $_POST['phone'];
		$brand = $_POST['brand'];
		$model = $_POST['model'];
		$anio = $_POST['anio'];
		$version = $_POST['version'];
		$km = $_POST['km'];
		$promedio = $_POST['promedio'];
		$ftecnica = $_POST['ftecnica'];
		$cduenios = $_POST['cduenios'];
		$vpermuta = $_POST['vpermuta'];
		$cauto = $_POST['cauto'];
		$choquel = $_POST['choquel'];
		$choqueg = $_POST['choqueg'];
		$estadot = $_POST['estadot'];
		$estadov = $_POST['estadov'];
		$empadronamiento = $_POST['empadronamiento'];
		$servicio = $_POST['servicio'];
		$correa = $_POST['correa'];
		$bateria = $_POST['bateria'];
		$piezasc = $_POST['piezasc'];
		$neumaticos = $_POST['neumaticos'];
		$tazasllantas = $_POST['tazasllantas'];
		$parabrisas = $_POST['parabrisas'];
		$faros = $_POST['faros'];
		$airea = $_POST['airea'];
		$sensore = $_POST['sensore'];
		$camarar = $_POST['camarar'];
		$radio = $_POST['radio'];
		$alarma = $_POST['alarma'];
		$vidriose = $_POST['vidriose'];
		$espejose = $_POST['espejose'];
		$dosllaves = $_POST['dosllaves'];
		$limpiezat = $_POST['limpiezat'];
		$vpretendido = $_POST['vpretendido'];
		$vminimo = $_POST['vminimo'];
		$vmaximo = $_POST['vmaximo'];
		$vpromedio = $_POST['vpromedio'];
		$txtauto = $_POST['txtauto'];

		LogCron("nombre " . $name);
		LogCron("email " . $email);
		LogCron("telefono " . $phone);
		LogCron("marca " . $brand);
		LogCron("modelo " . $model);
		LogCron("anio " . $anio);
		LogCron("version " . $version);
		LogCron("km " . $km);
		LogCron("promedio " . $promedio);
		LogCron("ficha_tecnica " . $ftecnica);
		LogCron("cantidad_duenios " . $cduenios);
		LogCron("venta_permuta " . $vpermuta);
		LogCron("Color del auto " . $cauto);
		LogCron("Sufrió Choque Leve " . $choquel);
		LogCron("Sufrió Choque Grave " . $choqueg);
		LogCron("Estado del Tapizado " . $estadot);
		LogCron("Estado del Volante " . $estadov);
		LogCron("Empadronamiento del vehículo " . $empadronamiento);
		LogCron("Servicio " . $servicio);
		LogCron("Correa de Distribución " . $correa);
		LogCron("Batería " . $bateria);
		LogCron("Piezas para Chapista " . $piezasc);
		LogCron("Neumáticos para cambiar " . $neumaticos);
		LogCron("Tazas o Llantas Para Pintar " . $tazasllantas);
		LogCron("Cambiar parabrisas " . $parabrisas);
		LogCron("Faros para cambiar " . $faros);
		LogCron("Aire Acondicionado " . $airea);
		LogCron("Sensor de Estacionamiento " . $sensore);
		LogCron("Cámara de Reversa " . $camarar);
		LogCron("Radio " . $radio);
		LogCron("Alarma " . $alarma);
		LogCron("Vidrios Eléctricos " . $vidriose);
		LogCron("Espejos Eléctricos " . $espejose);
		LogCron("Dos Juegos Llaves " . $dosllaves);
		LogCron("Limpieza de Tapizado " . $limpiezat);
		LogCron("valor_pretendido " . $vpretendido);
		LogCron("valor_minimo_ml " . $vminimo);
		LogCron("valor_maximo_ml " . $vmaximo);
		LogCron("valor_promedio_ml " . $vpromedio);
		LogCron("Nombre del Auto " . $txtauto);

		if(!isset($name) || !isset($email) || !isset($phone) || !isset($brand) || !isset($model) || !isset($anio) || !isset($version) || !isset($km) || !isset($promedio) || !isset($ftecnica) || !isset($cduenios) || !isset($vpretendido) || !isset($vpermuta)){
			return array("codigo"=>500, "mensaje"=> "No se pudo obtener los valores.","error"=>500);
		}

		if((int)$version){
			$versiones = apiNameVersion($version);
			$version = $versiones[0]->name;
		}

		$json_form = '';
		$json_form = '{"auto":"'.$txtauto.' '.$version.'","anio":'.$anio.',"km":'.$km.',"ficha":"'.$ftecnica.'","duenios":'.$cduenios.',"venta":"'.$vpermuta.'","color":"'.$cauto.'","choquel":"'.$choquel.'","choqueg":"'.$choqueg.'","tapizado":"'.$estadot.'","volante":"'.$estadov.'","empadronamiento":"'.$empadronamiento.'","servicio":"'.$servicio.'","correa":"'.$correa.'","bateria":"'.$bateria.'","piezas":'.$piezasc.',"neumaticos":'.$neumaticos.',"tazasllantas":'.$tazasllantas.',"parabrisas":"'.$parabrisas.'","faros":'.$faros.',"aire":"'.$airea.'","sensor":"'.$sensore.'","camara":"'.$camarar.'","radio":"'.$radio.'","alarma":"'.$alarma.'","vidrios":"'.$vidriose.'","espejos":"'.$espejose.'","dosllaves":"'.$dosllaves.'","limpieza":"'.$limpiezat.'","promedio":'.$promedio.',"vpretendido":'.$vpretendido.',"vminimo":'.$vminimo.',"vmaximo":'.$vmaximo.',"vpromedio":'.$vpromedio.'}';

		$BG = 0;
		$ficha_oficial = $bd->query("SELECT porcentaje, operador FROM variables WHERE tipo = 3 AND ficha_oficial = '".$ftecnica."'");
		if($ficha_oficial->num_rows > 0) {
			$ficha_oficial = $ficha_oficial->fetch_all(MYSQLI_ASSOC);
			$fo_porcentaje = $ficha_oficial[0]['porcentaje'];
			$fo_operador = $ficha_oficial[0]['operador'];

			LogCron("ficha_oficial porcentaje " . $fo_porcentaje);
			LogCron("ficha_oficial operador " . $fo_operador);

			$BG = ((float)$fo_porcentaje / 100) * $promedio;

			$fo_operador == '-' ? $BG = -$BG : $BG;

			LogCron("BG " . $BG);

		} else {
			return array("codigo"=>500, "mensaje"=> "Error al obtener la Ficha Oficial.","error"=>500);
		}

		$BH = 0;
		$cantidad_duenios = $bd->query("SELECT porcentaje, operador FROM variables WHERE tipo = 4 AND cantidad_duenios = ".(int)$cduenios);
		if($cantidad_duenios->num_rows > 0) {
			$cantidad_duenios = $cantidad_duenios->fetch_all(MYSQLI_ASSOC);
			$cd_porcentaje = $cantidad_duenios[0]['porcentaje'];
			$cd_operador = $cantidad_duenios[0]['operador'];

			LogCron("cantidad_duenios porcentaje " . $cd_porcentaje);
			LogCron("cantidad_duenios operador " . $cd_operador);

			$BH = ((float)$cd_porcentaje / 100) * $promedio;

			$cd_operador == '-' ? $BH = -$BH : $BH;

			LogCron("BH " . $BH);

		} else {
			return array("codigo"=>500, "mensaje"=> "Error al obtener la Cantidad de Dueños.","error"=>500);
		}

		$BI = 0;
		$brandmodel  = $bd->query("SELECT brand.nombre as marca, model.nombre as modelo FROM act_marcas as brand, act_modelo as model WHERE brand.id = {$_POST['brand']} AND model.id = {$_POST['model']}");
		if($brandmodel->num_rows > 0) {
			$brandmodel = $brandmodel->fetch_all(MYSQLI_ASSOC);
			$brand = $brandmodel[0]['marca'];
			$model = $brandmodel[0]['modelo'];

			if((int)$version){
				$versiones = apiNameVersion($version);
				$version = $versiones[0]->name;
			}

			LogCron("marca " . $brand);
			LogCron("modelo " . $model);
			LogCron("anio " . $anio);
			LogCron("version " . $version);
			LogCron("kilometros " . $km);

			$kilometros = $bd->query("SELECT kilometros as k FROM ponderador_valor_stock WHERE marca = '".$brand."' AND modelo = '".$model."' AND anio =".$anio." AND version = '".$version."'");
			if($kilometros->num_rows > 0) {
				$kilometros = $kilometros->fetch_object()->k;

				LogCron("kilometros en BD " . $kilometros);

				$max_kilometros = $bd->query("SELECT busqueda FROM ponderador_valor_busqueda")->fetch_object()->busqueda;

				$max_km = $kilometros + $max_kilometros;
				$min_km = $kilometros - $max_kilometros;

				$min_km < 0 ? $min_km = 0 : $min_km;

				LogCron("MAX kilometros " . $max_km);
				LogCron("MIN kilometros " . $min_km);

				if($km >= $min_km && $km <= $max_km) {
					$stock = $bd->query("SELECT stock as total FROM ponderador_valor_stock WHERE marca = '".$brand."' AND modelo = '".$model."' AND anio =".$anio." AND version = '".$version."'")->fetch_object()->total;
					LogCron("stock " . $stock);
	
					$stock > 5 ? $stock = 5 : $stock;
	
					$ponderacion_stock = $bd->query("SELECT porcentaje, operador FROM variables WHERE tipo = 6 AND stock = ".$stock."");
					if($ponderacion_stock->num_rows > 0) {
						$ponderacion_stock = $ponderacion_stock->fetch_all(MYSQLI_ASSOC);
						$ps_porcentaje = $ponderacion_stock[0]['porcentaje'];
						$ps_operador = $ponderacion_stock[0]['operador'];
			
						LogCron("ponderacion_stock porcentaje " . $ps_porcentaje);
						LogCron("ponderacion_stock operador " . $ps_operador);
	
						$BI = ((float)$ps_porcentaje / 100) * $promedio;
	
						$ps_operador == '-' ? $BI = -$BI : $BI;
	
						LogCron("BI " . $BI);
	
					}
				} else {
					LogCron("NO estoy en el rango de kilometros por ende tomo % de stock 0");

					$ponderacion_stock = $bd->query("SELECT porcentaje FROM variables WHERE tipo = 6 AND stock = 0")->fetch_object()->porcentaje;

					LogCron("ponderacion stock " . $ponderacion_stock);

					$BI = ((float)$ponderacion_stock / 100) * $promedio;

					LogCron("BI " . $BI);
				}
			} else {
				LogCron("NO existe el auto en BD");

				$ponderacion_stock = $bd->query("SELECT porcentaje FROM variables WHERE tipo = 6 AND stock = 0")->fetch_object()->porcentaje;

				LogCron("ponderacion stock " . $ponderacion_stock);

				$BI = ((float)$ponderacion_stock / 100) * $promedio;

				LogCron("BI " . $BI);
			}
		} else {
			return array("codigo"=>500, "mensaje"=> "Error al obtener Marca y Modelo.","error"=>500);
		}

		$BJ = 0;
		$color = $bd->query("SELECT porcentaje, operador FROM variables WHERE tipo = 7 AND color = '".$cauto."'");
		if($color->num_rows > 0) {
			$color = $color->fetch_all(MYSQLI_ASSOC);
			$ca_porcentaje = $color[0]['porcentaje'];
			$ca_operador = $color[0]['operador'];

			LogCron("color porcentaje " . $ca_porcentaje);
			LogCron("color operador " . $ca_operador);

			$BJ = ((float)$ca_porcentaje / 100) * $promedio;

			$ca_operador == '-' ? $BJ = -$BJ : $BJ;

			LogCron("BJ " . $BJ);

		} else {
			return array("codigo"=>500, "mensaje"=> "Error al obtener el Color del Auto.","error"=>500);
		}

		$BK = 0;
		$choque_leve = $bd->query("SELECT porcentaje, operador FROM variables WHERE tipo = 8 AND choque_leve = '".$choquel."'");
		if($choque_leve->num_rows > 0) {
			$choque_leve = $choque_leve->fetch_all(MYSQLI_ASSOC);
			$cl_porcentaje = $choque_leve[0]['porcentaje'];
			$cl_operador = $choque_leve[0]['operador'];

			LogCron("choque_leve porcentaje " . $cl_porcentaje);
			LogCron("choque_leve operador " . $cl_operador);

			$BK = ((float)$cl_porcentaje / 100) * $promedio;

			$cl_operador == '-' ? $BK = -$BK : $BK;

			LogCron("BK " . $BK);

		} else {
			return array("codigo"=>500, "mensaje"=> "Error al obtener el Choque Leve.","error"=>500);
		}

		$BL = 0;
		$choque_grave = $bd->query("SELECT porcentaje, operador FROM variables WHERE tipo = 9 AND choque_grave = '".$choqueg."'");
		if($choque_grave->num_rows > 0) {
			$choque_grave = $choque_grave->fetch_all(MYSQLI_ASSOC);
			$cg_porcentaje = $choque_grave[0]['porcentaje'];
			$cg_operador = $choque_grave[0]['operador'];

			LogCron("choque_grave porcentaje " . $cg_porcentaje);
			LogCron("choque_grave operador " . $cg_operador);

			$BL = ((float)$cg_porcentaje / 100) * $promedio;

			$cg_operador == '-' ? $BL = -$BL : $BL;

			LogCron("BL " . $BL);

		} else {
			return array("codigo"=>500, "mensaje"=> "Error al obtener el Choque Grave.","error"=>500);
		}

		$BM = 0;
		$tapizado = $bd->query("SELECT porcentaje, operador FROM variables WHERE tipo = 10 AND tapizado = '".$estadot."'");
		if($tapizado->num_rows > 0) {
			$tapizado = $tapizado->fetch_all(MYSQLI_ASSOC);
			$et_porcentaje = $tapizado[0]['porcentaje'];
			$et_operador = $tapizado[0]['operador'];

			LogCron("tapizado porcentaje " . $et_porcentaje);
			LogCron("tapizado operador " . $et_operador);

			$BM = ((float)$et_porcentaje / 100) * $promedio;

			$et_operador == '-' ? $BM = -$BM : $BM;

			LogCron("BM " . $BM);

		} else {
			return array("codigo"=>500, "mensaje"=> "Error al obtener el Estado del Tapizado.","error"=>500);
		}

		$BN = 0;
		$volante = $bd->query("SELECT porcentaje, operador FROM variables WHERE tipo = 11 AND volante = '".$estadot."'");
		if($volante->num_rows > 0) {
			$volante = $volante->fetch_all(MYSQLI_ASSOC);
			$ev_porcentaje = $volante[0]['porcentaje'];
			$ev_operador = $volante[0]['operador'];

			LogCron("volante porcentaje " . $ev_porcentaje);
			LogCron("volante operador " . $ev_operador);

			$BN = ((float)$ev_porcentaje / 100) * $promedio;

			$ev_operador == '-' ? $BN = -$BN : $BN;

			LogCron("BN " . $BN);

		} else {
			return array("codigo"=>500, "mensaje"=> "Error al obtener el Estado del Volante.","error"=>500);
		}

		$empadronamientov = $bd->query("SELECT usd FROM variables_usd WHERE tipo = 1 AND empadronamiento = '".$empadronamiento."'")->fetch_object()->usd;
		$serviciov = $bd->query("SELECT usd FROM variables_usd WHERE tipo = 2 AND servicio = '".$servicio."'")->fetch_object()->usd;
		$correadv = $bd->query("SELECT usd FROM variables_usd WHERE tipo = 3 AND correa = '".$correa."'")->fetch_object()->usd;
		$bateriav = $bd->query("SELECT usd FROM variables_usd WHERE tipo = 4 AND bateria = '".$bateria."'")->fetch_object()->usd;
		$piezasv = $bd->query("SELECT usd FROM variables_usd WHERE tipo = 5 AND piezas_chapista = ".$piezasc)->fetch_object()->usd;
		$neumaticosv = $bd->query("SELECT usd FROM variables_usd WHERE tipo = 6 AND neumaticos = ".$neumaticos)->fetch_object()->usd;
		$tazasllantasv = $bd->query("SELECT usd FROM variables_usd WHERE tipo = 7 AND tazas_llantas = ".$tazasllantas)->fetch_object()->usd;
		$parabrisasv = $bd->query("SELECT usd FROM variables_usd WHERE tipo = 8 AND parabrisas = '".$parabrisas."'")->fetch_object()->usd;
		$farosv = $bd->query("SELECT usd FROM variables_usd WHERE tipo = 9 AND faros = ".$faros)->fetch_object()->usd;
		$aireav = $bd->query("SELECT usd FROM variables_usd WHERE tipo = 10 AND aire_acondicionado = '".$airea."'")->fetch_object()->usd;
		$sensorev = $bd->query("SELECT usd FROM variables_usd WHERE tipo = 11 AND sensor_estacionamiento = '".$sensore."'")->fetch_object()->usd;
		$camararv = $bd->query("SELECT usd FROM variables_usd WHERE tipo = 12 AND camara_reversa = '".$camarar."'")->fetch_object()->usd;
		$radiov = $bd->query("SELECT usd FROM variables_usd WHERE tipo = 13 AND radio = '".$radio."'")->fetch_object()->usd;
		$alarmav = $bd->query("SELECT usd FROM variables_usd WHERE tipo = 14 AND alarma = '".$alarma."'")->fetch_object()->usd;
		$vidriosev = $bd->query("SELECT usd FROM variables_usd WHERE tipo = 15 AND vidrios = '".$vidriose."'")->fetch_object()->usd;
		$espejosev = $bd->query("SELECT usd FROM variables_usd WHERE tipo = 16 AND espejos = '".$espejose."'")->fetch_object()->usd;
		$dosllavesv = $bd->query("SELECT usd FROM variables_usd WHERE tipo = 17 AND llaves = '".$dosllaves."'")->fetch_object()->usd;
		$tapizadov = $bd->query("SELECT usd FROM variables_usd WHERE tipo = 18 AND tapizado = '".$limpiezat."'")->fetch_object()->usd;

		$resultDefinitivoEntrega = 0;
		$resultDefinitivo = 0;
		$resultDefinitivo = round($promedio + $BG + $BH + $BI + $BJ + $BK + $BL + $BM + $BN - $empadronamientov - $serviciov - $correadv - $bateriav - $piezasv - $neumaticosv - $tazasllantasv - $parabrisasv - $farosv - $aireav - $sensorev - $camararv - $radiov - $alarmav - $vidriosev - $espejosev - $dosllavesv - $tapizadov, 0, PHP_ROUND_HALF_UP);

		LogCron("valor_compra_motorlider_definitivo " . $resultDefinitivo);

		$response = '';
		if($vpermuta == 'Entrega'){
			//BD variables nominal, llenan por admin
			$BD = $bd->query("SELECT p.porcentaje as total FROM ponderador_valor_venal as p WHERE p.key = 'BD'")->fetch_object()->total;
			LogCron("BD " . $BD);

			$resultDefinitivoEntrega = round($resultDefinitivo * $BD, 0, PHP_ROUND_HALF_UP);

			LogCron("valor_entrega_motorlider_definitivo " . $resultDefinitivoEntrega);

			if((int)$vpretendido < $resultDefinitivoEntrega){
				LogCron("Valor Pretendido por el Cliente");
				$response = '{"vpretendido":'.(int)$vpretendido.'}';
			}else if((int)$vpretendido > $resultDefinitivoEntrega){
				$response = '{"valordefinitivo_motorlider":'.$resultDefinitivoEntrega.'}';
			}
		} else {
			if((int)$vpretendido < $resultDefinitivo){
				LogCron("Valor Pretendido por el Cliente");
				$response = '{"vpretendido":'.(int)$vpretendido.'}';
			}else if((int)$vpretendido > $resultDefinitivo){
				$response = '{"valordefinitivo_motorlider":'.$resultDefinitivo.'}';
			}
		}

		$date = date('Y-m-d');
		//$jsonauto = json_encode($json_form);

		$vdefinitivo = 0;
		if($vpermuta == 'Entrega'){
			if((int)$vpretendido < $resultDefinitivoEntrega){
				$vdefinitivo = (int)$vpretendido;
			}else if((int)$vpretendido > $resultDefinitivoEntrega){
				$vdefinitivo = $resultDefinitivoEntrega;
			}
		} else {
			if((int)$vpretendido < $resultDefinitivo){
				$vdefinitivo = (int)$vpretendido;
			}else if((int)$vpretendido > $resultDefinitivo){
				$vdefinitivo = $resultDefinitivo;
			}
		}

		$stmt = $bd->prepare("INSERT INTO cotizaciones_internas (`nombre`, `email`, `telefono`, `fecha`, `valor_definitivo`, `respuesta`) VALUES (?,?,?,?,?,?)");
		$stmt->bind_param("ssssss",$name,$email,$phone,$date,$vdefinitivo,$json_form);
		if ($stmt->execute()) {
			$id_cotizacion = $stmt->insert_id;
			return array("codigo"=>200, "mensaje"=> "Valor Compra Motorlider Interno","error"=>0,'cotizacion'=>$id_cotizacion,'valores'=>json_decode($response));
		} else {
			LogCron("Error al insertar " . $stmt->errno . 'MSG: ' . $stmt->error);
			return array("codigo"=>500, "mensaje"=> "Error al insertar la cotizacion.","error"=>500);
		}
	}

	function apiNameVersion($version){
		$access_token = motorlider_ml_token();
		$search = 'SHORT_VERSION='.$version;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://api.mercadolibre.com/sites/MLU/search?" .$search);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //PARA QUE ANDE LOCAL
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Authorization: Bearer $access_token"));
		$products_ml = json_decode(curl_exec($ch));
		curl_close($ch);
		foreach($products_ml->filters as $key => $filter){
			if($filter->id === 'SHORT_VERSION'){
				return $filter->values;
			}
		}
	}

	function apiAllDataVersion($brand, $model, $anio){
		$access_token = motorlider_ml_token();

		LogCron("apiAllDataVersion");
		LogCron("YEAR " . $anio);

		$search = 'category=MLU1744&BRAND='.$brand.'&MODEL='.$model;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://api.mercadolibre.com/sites/MLU/search?" . $search);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //PARA QUE ANDE LOCAL
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Authorization: Bearer $access_token"));
		$products_ml = json_decode(curl_exec($ch));

		curl_close($ch);
		$years = array();
		foreach($products_ml->available_filters as $key => $filters){
			if($filters->id === 'VEHICLE_YEAR'){
				foreach($filters->values as $key => $year) {
					$years[$year->name] = $year->name;
				}
			}
		}

		$count = 0;
		$start = false;
		$yearstart = 0;
		if(count($years) > 1){
			foreach($years as $y){
				if($anio == $y){
					$start = true;
				}
				if($start){
					$count = $count + 1;
				}
				if($count > 1){
					$yearstart = $y;
					break;
				}
			}
		}

		$anio = $anio + 1;

		LogCron("YEAR SEARCH " . $yearstart . " - " . $anio);

		$search = 'category=MLU1744&BRAND='.$brand.'&MODEL='.$model.'&VEHICLE_YEAR='.$yearstart.'-'.$anio;

		LogCron("SEARCH " . $search);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://api.mercadolibre.com/sites/MLU/search?" . $search);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //PARA QUE ANDE LOCAL
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Authorization: Bearer $access_token"));
		$products_ml = json_decode(curl_exec($ch));

		curl_close($ch);
		foreach($products_ml->available_filters as $key => $filters){
			if($filters->id === 'SHORT_VERSION'){
				return $filters->values;
			}
		}
	}

	function apiDataNameVersion($search){
		$access_token = motorlider_ml_token();

		LogCron("apiDataNameVersion");
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://api.mercadolibre.com/sites/MLU/search?" . $search);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //PARA QUE ANDE LOCAL
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Authorization: Bearer $access_token"));
		$products_ml = json_decode(curl_exec($ch));
		curl_close($ch);
		foreach($products_ml->filters as $key => $filters){
			if($filters->id === 'SHORT_VERSION'){
				return $filters->values;
			}
		}
	}

	function apiData($search){
		$access_token = motorlider_ml_token();
		LogCron("apiData");
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://api.mercadolibre.com/sites/MLU/search?" . $search);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //PARA QUE ANDE LOCAL
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Authorization: Bearer $access_token"));
		$products_ml = json_decode(curl_exec($ch));
		curl_close($ch);
		return $products_ml;
	}
	
	function changeKm($query, $total){
		$new_query = "";
	
		$new_km = explode("&KILOMETERS=", $query);
		$menor_km = explode("km-", $new_km[1]);
		$mayor_km = explode("km", $menor_km[1]);
		$menor_km = (int)$menor_km[0];
		$mayor_km = (int)$mayor_km[0];
	
		if($total === 1 || $total === 5 || $total === 9 || $total === 13){
			$mayor_km = $mayor_km + 5000;
			$menor_km = $menor_km - 5000;
			$menor_km = $menor_km < 0 ? 0 : $menor_km;
		} else if($total === 2 || $total === 6 || $total === 10 || $total === 14){
			$mayor_km = $mayor_km + 10000;
			$menor_km = $menor_km - 10000;
			$menor_km = $menor_km < 0 ? 0 : $menor_km;
		} else if($total === 3 || $total === 7 || $total === 11 || $total === 15){
			$mayor_km = $mayor_km + 20000;
			$menor_km = $menor_km - 20000;
			$menor_km = $menor_km < 0 ? 0 : $menor_km;
		}
	
		$new_query = $new_km[0] . '&KILOMETERS=' . $menor_km . 'km-' . $mayor_km . 'km';
		return $new_query;
	}
	
	function changeYear($query, $anio, $total){
		$new_query = "";
	
		$year = explode("&VEHICLE_YEAR=".$anio."-".$anio, $query);
		$menor_year = (int)$anio;
		$mayor_year = (int)$anio;
	
		if($total === 4 || $total === 5 || $total === 6 || $total === 7){
			$menor_year = (int)$anio - 1;
			$mayor_year = (int)$anio;
		} else if($total === 8 || $total === 9 || $total === 10 || $total === 11){
			$menor_year = (int)$anio;
			$mayor_year = (int)$anio + 1;
		} else if($total === 12 || $total === 13 || $total === 14 || $total === 15){
			$menor_year = (int)$anio - 1;
			$mayor_year = (int)$anio + 1;
		}
	
		$new_query = $year[0] . '&VEHICLE_YEAR=' . $menor_year . '-' . $mayor_year . $year[1];
		return $new_query;
	}

	function checkIsNumeric($input) {
		if (is_numeric($input)) {
			return true;
		} else {
			return false;
		}
	}

	function LogCron($new_data){
		$new_data = date("Ymd G:i:s") . "  >>  " . $new_data;
		$my_file  = dirname(__FILE__) . '/logs/' . 'MiLogT_'.date("Y-m-d").'.log';
		$handle = fopen($my_file, 'a') or die('Cannot open file:  '.$my_file);
		fwrite($handle, $new_data ."\n");
	}

	function LogResult($new_data,$brand,$model,$anio,$hash){
		$my_file  = dirname(__FILE__) . '/logs/' . $brand.'_'.$model.'_'.$anio.'_'.$hash.'.log';
		$handle = fopen($my_file, 'a') or die('Cannot open file:  '.$my_file);
		fwrite($handle, $new_data ."\n");
	}

	if(validarUsuario($bd, $config->wsInfopelUsuario, $config->wsInfopelContrasena, $config->entorno)){
		
		switch($_GET['peticion']){
			case 'brands': $resultado = brands($bd); break;
			case 'models': $resultado = models($bd); break;
			case 'years': $resultado = years($bd); break;
			case 'versions': $resultado = versions($bd); break;
			case 'pricesData': $resultado = pricesData($bd); break; //cotizador interno
			case 'pricesInternal': $resultado = pricesInternal($bd); break; //cotizador interno
			case 'averageInternal': $resultado = averageInternal($bd); break; //cotizador interno
			case 'publicQuotation': $resultado = publicQuotation($bd); break;
			case 'internalQuotation': $resultado = internalQuotation($bd); break;
			case 'locations': $resultado = locations($bd); break;
			case 'calendar': $resultado = calendar($bd); break;
			case 'schedules': $resultado = schedules($bd); break;
			case 'scheduleInspection': $resultado = scheduleInspection($bd); break;
			default: $resultado = array("codigo"=>405, "mensaje"=>"Método incorrecto.");
		}

	}else $resultado = array("codigo"=>403, "mensaje"=>"No tiene permisos para acceder a la API.");

	print_r(json_encode($resultado));



	function motorlider_ml_token(){

		define('ML_APP_ID', '6722426555410846'); 
		define('ML_APP_SECRET', 'aVkGmvga3eaEwpYzxPH6ZTvKqxIgv3Rd'); 
	
		$access_token = "";
		$url = "https://api.mercadolibre.com/oauth/token?grant_type=client_credentials&client_id=" . ML_APP_ID . "&client_secret=" . ML_APP_SECRET . "";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$res = curl_exec($ch);
		$token_info = json_decode($res);
	
		$access_token = $token_info->access_token;
	
		return $access_token;
	}
?>
