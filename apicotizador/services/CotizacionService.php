<?php

require_once __DIR__ . '/../src/cotizacion_generada.php';
require_once __DIR__ . '/MailService.php';
require_once __DIR__ . '/ApiCotizadorApify.php';
require_once __DIR__ . '/../src/log.php';
require_once __DIR__ . '/../src/db.php'; // si ya lo tenés cargado global no molesta, pero es seguro

class CotizacionService
{
    private function logInterno(string $tag, array $payload = []): void
    {
        try {
            $log_data = [];
            $log_data['token'] = '';
            $log_data['ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
            $log_data['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $log_data['request_method'] = 'INTERNAL';
            $log_data['request_uri'] = 'CotizacionService::procesarCotizacionPublica';
            $log_data['request_header'] = '';
            $log_data['request_vars'] = '';
            $log_data['request_body'] = json_encode([
                'tag' => $tag,
                'payload' => $payload
            ], JSON_UNESCAPED_UNICODE);
            $log_data['response_statuscode'] = 0;
            $log_data['response_header'] = '';
            $log_data['response_body'] = '';
            $log = new Log($log_data);
            $log->save();
        } catch (\Throwable $e) {
            // nunca romper el flujo por log
        }
    }

    private function persistirItemCotizacion(array $row): void
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO marcos2022_api_cotizador.cotizacion_items
                (cotizacion_id, run_id, brand, modelo, source, item_url, item_id,
                 title, seller, is_official_store,
                 price_value, price_currency, item_year, item_km, location_txt,
                 passes_filters, reject_reason, raw_json)
                VALUES
                (:cotizacion_id, :run_id, :brand, :modelo, :source, :item_url, :item_id,
                 :title, :seller, :is_official_store,
                 :price_value, :price_currency, :item_year, :item_km, :location_txt,
                 :passes_filters, :reject_reason, :raw_json)";

            $params = [
                ':cotizacion_id' => $row['cotizacion_id'] ?? null,
                ':run_id' => $row['run_id'] ?? null,
                ':brand' => $row['brand'] ?? null,
                ':modelo' => $row['modelo'] ?? null,
                ':source' => $row['source'] ?? 'apify',
                ':item_url' => $row['item_url'] ?? null,
                ':item_id' => $row['item_id'] ?? null,

                ':title' => $row['title'] ?? null,
                ':seller' => $row['seller'] ?? null,
                ':is_official_store' => (int)($row['is_official_store'] ?? 0),

                ':price_value' => $row['price_value'] ?? null,
                ':price_currency' => $row['price_currency'] ?? null,

                ':item_year' => $row['item_year'] ?? null,
                ':item_km' => $row['item_km'] ?? null,
                ':location_txt' => $row['location_txt'] ?? null,

                ':passes_filters' => (int)($row['passes_filters'] ?? 0),
                ':reject_reason' => $row['reject_reason'] ?? null,

                ':raw_json' => $row['raw_json'] ?? null,
            ];

            // tu DB actual (rollback) ya debería tener mysqlNonQuery
            $db->mysqlNonQuery($sql, $params);

        } catch (\Throwable $e) {
            $this->logInterno('PERSIST_ITEM_FAIL', ['error' => $e->getMessage()]);
        }
    }

    public function procesarCotizacionPublica(array $dataIn, string $brand): array
    {
        $this->logInterno('INICIO', [
            'brand_in' => $brand,
            'modelo_in' => $dataIn['modelo'] ?? null,
            'anio' => $dataIn['anio'] ?? null,
            'km' => $dataIn['km'] ?? null,
            'nombre_auto' => $dataIn['nombre_auto'] ?? null,
            'version' => $dataIn['version'] ?? null,
            'version_other' => $dataIn['version_other'] ?? null,
            'version_name' => $dataIn['version_name'] ?? null,
        ]);

        // --- Valores input ---
        $brandTxt = trim((string)$brand);
        $modeloTxt = trim((string)($dataIn['modelo'] ?? ''));
        $anio = trim((string)($dataIn['anio'] ?? ''));
        $km = trim((string)($dataIn['km'] ?? ''));
        $version = trim((string)($dataIn['version'] ?? ''));
        $versionOther = trim((string)($dataIn['version_other'] ?? ''));
        $versionName = trim((string)($dataIn['version_name'] ?? ''));
        $nombreAuto = trim((string)($dataIn['nombre_auto'] ?? ''));

        // ==========================================================
        // 1) Resolver IDs contra carplay (si ambos numéricos)
        // ==========================================================
        if (is_numeric($brandTxt) && is_numeric($modeloTxt)) {
            try {
                $this->logInterno('RESOLVE_IDS_START', [
                    'brand_id' => $brandTxt,
                    'model_id' => $modeloTxt
                ]);

                $resolved = $this->resolveBrandAndModelNames($brandTxt, $modeloTxt);
                $brandTxt = trim((string)($resolved['brand_name'] ?? $brandTxt));
                $modeloTxt = trim((string)($resolved['model_name'] ?? $modeloTxt));

                $this->logInterno('RESOLVE_IDS_OK', [
                    'brand_name' => $brandTxt,
                    'model_name' => $modeloTxt
                ]);
            } catch (\Throwable $e) {
                $this->logInterno('RESOLVE_IDS_FAIL', [
                    'error' => $e->getMessage(),
                    'brand_id' => $brandTxt,
                    'model_id' => $modeloTxt,
                    'nombre_auto' => $nombreAuto
                ]);
            }
        } else {
            $this->logInterno('RESOLVE_IDS_SKIP', [
                'brandTxt' => $brandTxt,
                'modeloTxt' => $modeloTxt
            ]);
        }

        // ==========================================================
        // 2) Fallback FINAL: si sigue numérico o vacío, usar nombre_auto
        // ==========================================================
        if ($nombreAuto !== '' && (is_numeric($brandTxt) || is_numeric($modeloTxt) || $brandTxt === '' || $modeloTxt === '')) {
            $parts = preg_split('/\s+/', $nombreAuto, 2);
            if (count($parts) >= 2) {
                $brandTxt = $parts[0];
                $modeloTxt = $parts[1];
            } else {
                $brandTxt = $nombreAuto;
                $modeloTxt = '';
            }

            $this->logInterno('FALLBACK_NOMBRE_AUTO', [
                'brandTxt' => $brandTxt,
                'modeloTxt' => $modeloTxt
            ]);
        }

        // si sigue inválido, cortar
        if ($brandTxt === '' || $modeloTxt === '') {
            return [
                'msg' => 'Marca o modelo inválidos (no se pudo resolver).',
                'resultado' => null,
                'id_cotizacion' => null
            ];
        }

        // ==========================================================
        // 3) Construcción de input Apify CENTRALIZADA (autos/)
        // ==========================================================
        $api = new ApiCotizadorApify();
        $input = $api->buildInputFromCotizador($brandTxt, $modeloTxt);

        $maxItems = (int)(getenv('APIFY_MAX_ITEMS_PER_URL') ?: 20);

        $this->logInterno('APIFY_INPUT', [
            'url' => $input['urls'][0] ?? null,
            'brand_final' => $brandTxt,
            'modelo_final' => $modeloTxt
        ]);

        try {
            $res = $api->run($input, $maxItems);

            if (!($res['ok'] ?? false)) {
                $this->logInterno('APIFY_FAIL', [
                    'error' => $res['error'] ?? 'unknown',
                    'url' => $input['urls'][0] ?? null
                ]);

                return [
                    'msg' => 'No se pudo obtener los precios.',
                    'resultado' => null,
                    'id_cotizacion' => null,
                    'debug' => [
                        'error' => $res['error'] ?? 'unknown',
                        'url' => $input['urls'][0] ?? null
                    ]
                ];
            }

        } catch (\Throwable $e) {
            $this->logInterno('APIFY_EXCEPTION', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }

        $items = $res['items'] ?? [];
        $itemsCount = is_array($items) ? count($items) : 0;

        $this->logInterno('APIFY_RESULT', [
            'items_count' => $itemsCount,
            'sample_item' => ($itemsCount > 0) ? $items[0] : null
        ]);

        $this->logInterno('STEP_4_BEFORE_RUNID');
        // ==========================================================
        // 4) Parseo + acumulación de items para persistir luego
        // ==========================================================
        $runId = $res['runId'] ?? ($res['data']['id'] ?? ($res['id'] ?? null));
        if (!$runId) {
            $runId = $this->makeRunId();
        }

        $this->logInterno('STEP_4_AFTER_RUNID', ['runId' => $runId]);

        $ver = trim((string)($versionOther ?: ($versionName ?: $version)));

        $prices = [];
        $itemsParaPersistir = [];

        foreach (($items ?? []) as $idx => $it) {

            $price = null;
            $currency = null;
            $itemYear = null;
            $itemKm = null;
            $titleTxt = '';
            $sellerTxt = '';
            $isOfficial = false;
            $locationTxt = '';

            $itemUrl = $it['url'] ?? $it['item_url'] ?? null;
            $itemId  = $it['id'] ?? null;

            // Bloques: components o array directo
            if (isset($it['components']) && is_array($it['components'])) {
                $blocks = $it['components'];
            } elseif (is_array($it) && isset($it[0]) && is_array($it[0]) && isset($it[0]['type'])) {
                $blocks = $it;
            } else {
                // si no tiene estructura esperada, igual lo guardamos como rechazado
                $blocks = [];
            }

            foreach ($blocks as $comp) {
                $type = $comp['type'] ?? '';

                if ($type === 'price') {
                    $price = $comp['price']['current_price']['value'] ?? null;
                    $currency = $comp['price']['current_price']['currency'] ?? null;
                }

                if ($type === 'title') {
                    $titleTxt = (string)($comp['title']['text'] ?? '');
                }

                if ($type === 'seller') {
                    $sellerTxt = (string)($comp['seller']['text'] ?? '');
                    foreach (($comp['seller']['values'] ?? []) as $v) {
                        $alt = $v['icon']['alt_text'] ?? '';
                        if (stripos($alt, 'Tienda oficial') !== false) {
                            $isOfficial = true;
                            break;
                        }
                    }
                }

                if ($type === 'attributes_list') {
                    foreach (($comp['attributes_list']['texts'] ?? []) as $t) {
                        $t = trim((string)$t);

                        if (preg_match('/^\d{4}$/', $t)) {
                            $itemYear = (int)$t;
                        }

                        if (stripos($t, 'km') !== false) {
                            $num = preg_replace('/[^\d]/', '', $t);
                            $itemKm = ($num === '') ? null : (int)$num;
                            if (stripos($t, '0 km') !== false) $itemKm = 0;
                        }
                    }
                }

                if ($type === 'location') {
                    $locationTxt = (string)($comp['location']['text'] ?? '');
                }
            }

            // ✅ SIN FILTROS (por ahora): solo validar que haya precio numérico
            $passes = true;
            $reason = null;

            if ($price === null || !is_numeric($price)) {
                $passes = false;
                $reason = 'no_price';
            }

            // Guardar item para persistencia (igual guardamos todo)
            $itemsParaPersistir[] = [
                'cotizacion_id' => null,
                'run_id' => $runId,
                'brand' => $brandTxt,
                'modelo' => $modeloTxt,
                'source' => 'apify',
                'item_url' => $itemUrl,
                'item_id' => $itemId,
                'title' => $titleTxt,
                'seller' => $sellerTxt,
                'is_official_store' => $isOfficial ? 1 : 0,
                'price_value' => $price !== null && is_numeric($price) ? (float)$price : null,
                'price_currency' => $currency,
                'item_year' => $itemYear,
                'item_km' => $itemKm,
                'location_txt' => $locationTxt,
                'passes_filters' => $passes ? 1 : 0,
                'reject_reason' => $reason,
                'raw_json' => json_encode($it, JSON_UNESCAPED_UNICODE),
            ];

            // Si no hay precio, no lo usamos para min/max/avg
            if (!$passes) {
                continue;
            }

            // ✅ Acumular precio
            $prices[] = (float)$price;
        }

        $this->logInterno('PRICES_COUNT', [
            'prices_found' => count($prices),
            'items_total' => count($itemsParaPersistir)
        ]);

        if (count($prices) === 0) {
            // Persistimos igual items? (opcional) => por ahora NO
            return [
                'msg' => 'No se encontraron publicaciones comparables.',
                'resultado' => null,
                'id_cotizacion' => null,
                'debug' => [
                    'url' => $input['urls'][0] ?? null,
                    'items_count' => $itemsCount
                ]
            ];
        }

        sort($prices);

        $min = $prices[0];
        $max = $prices[count($prices) - 1];
        $avg = array_sum($prices) / count($prices);

        $resultado = [
            'count' => count($prices),
            'min' => $min,
            'max' => $max,
            'avg' => round($avg, 2),
            'url' => $input['urls'][0] ?? null
        ];

        $this->logInterno('RESULTADO_OK', $resultado);

        // ==========================================================
        // 5) Persistir cabecera (cotizaciones_generadas)
        // ==========================================================
        $id = null;

        try {
            $cg_data = [];

            $cg_data['nombre']   = $dataIn['nombre'] ?? null;
            $cg_data['email']    = $dataIn['email'] ?? null;
            $cg_data['telefono'] = $dataIn['telefono'] ?? null;
            $cg_data['ci']       = $dataIn['ci'] ?? null;

            // tu tabla dice DATE en comentarios previos, pero hoy estabas guardando datetime
            // si querés evitar lío: guardo date('Y-m-d')
            $cg_data['fecha']         = date('Y-m-d');
            $cg_data['kilometros']    = isset($dataIn['km']) ? (int)$dataIn['km'] : null;
            $cg_data['ficha_tecnica'] = $dataIn['ficha_tecnica'] ?? null;
            $cg_data['duenios']       = $dataIn['cantidad_duenios'] ?? null;
            $cg_data['tipo_venta']    = $dataIn['venta_permuta'] ?? null;
            $cg_data['precio_pretendido'] = $dataIn['valor_pretendido'] ?? null;

            $cg_data['marca'] = $brandTxt;
            $cg_data['anio']  = $anio !== '' ? (int)$anio : null;
            $cg_data['familia'] = $modeloTxt;
            $cg_data['auto'] = $dataIn['nombre_auto'] ?? ($brandTxt . ' ' . $modeloTxt);

            $cg_data['valor_minimo']   = $min;
            $cg_data['valor_maximo']   = $max;
            $cg_data['valor_promedio'] = round($avg, 2);

            $cg_data['valor_minimo_autodata']   = null;
            $cg_data['valor_maximo_autodata']   = null;
            $cg_data['valor_promedio_autodata'] = null;

            $cg_data['datos'] = json_encode([
                'url' => $resultado['url'],
                'brand_final' => $brandTxt,
                'modelo_final' => $modeloTxt,
                'anio' => $anio,
                'version' => $ver,
                'run_id' => $runId,
                'items_total' => count($itemsParaPersistir),
                'items_ok' => count($prices),
            ], JSON_UNESCAPED_UNICODE);

            $cg_data['respuesta'] = json_encode($resultado, JSON_UNESCAPED_UNICODE);

            $cg_data['msg'] = 'OK';
            $cg_data['porcentajes_aplicados'] = null;
            $cg_data['cuenta'] = null;

            $cg = new CotizacionGenerada($cg_data);
            $created = $cg->save();
            $id = $created->id_cotizaciones_generadas ?? null;

            $this->logInterno('PERSIST_OK', ['id_cotizaciones_generadas' => $id]);

        } catch (\Throwable $e) {
            $this->logInterno('PERSIST_FAIL', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }

        // ==========================================================
        // 6) Persistir items con cotizacion_id real
        // ==========================================================
        if ($id) {
            $okItems = 0;
            foreach ($itemsParaPersistir as $row) {
                $row['cotizacion_id'] = $id;
                $this->persistirItemCotizacion($row);
                $okItems++;
            }
            $this->logInterno('ITEMS_PERSIST_OK', [
                'cotizacion_id' => $id,
                'items_inserted' => $okItems,
                'run_id' => $runId
            ]);
        } else {
            $this->logInterno('ITEMS_PERSIST_SKIP_NO_ID', [
                'items_count' => count($itemsParaPersistir),
                'run_id' => $runId
            ]);
        }

        return [
            'msg' => 'OK',
            'resultado' => $resultado,
            'id_cotizacion' => $id
        ];
    }

    private function resolveBrandAndModelNames(string $brandId, string $modelId): array
    {
        $urlBase = "https://carplay.uy/";

        $brandsJson = @file_get_contents($urlBase . "ws/brands");
        if (!$brandsJson) {
            throw new Exception("No se pudo consultar ws/brands");
        }

        $brands = json_decode($brandsJson, true);
        if (!is_array($brands)) {
            throw new Exception("ws/brands devolvió JSON inválido");
        }

        $brandName = null;
        foreach ($brands['brands'] ?? [] as $b) {
            if ((string)($b['id'] ?? '') === (string)$brandId) {
                $brandName = $b['name'] ?? null;
                break;
            }
        }
        if (!$brandName) {
            throw new Exception("No se pudo resolver nombre de marca (ID {$brandId})");
        }

        $postData = http_build_query(['brand' => $brandId]);

        $opts = [
            'http' => [
                'method'  => 'POST',
                'header'  => 'Content-type: application/x-www-form-urlencoded',
                'content' => $postData,
                'timeout' => 15
            ]
        ];

        $context = stream_context_create($opts);
        $modelsJson = @file_get_contents($urlBase . "ws/models", false, $context);

        if (!$modelsJson) {
            throw new Exception("No se pudo consultar ws/models");
        }

        $models = json_decode($modelsJson, true);
        if (!is_array($models)) {
            throw new Exception("ws/models devolvió JSON inválido");
        }

        $modelName = null;
        foreach ($models['models'] ?? [] as $m) {
            if ((string)($m['id'] ?? '') === (string)$modelId) {
                $modelName = $m['name'] ?? null;
                break;
            }
        }
        if (!$modelName) {
            throw new Exception("No se pudo resolver nombre de modelo (ID {$modelId})");
        }

        return [
            'brand_name' => $brandName,
            'model_name' => $modelName
        ];
    }

    private function makeRunId(): string
    {
        // PHP 7+ suele tener random_bytes; si no, fallback
        try {
            if (function_exists('random_bytes')) {
                return 'local_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
            }
        } catch (\Throwable $e) {}

        if (function_exists('openssl_random_pseudo_bytes')) {
            return 'local_' . date('Ymd_His') . '_' . bin2hex(openssl_random_pseudo_bytes(4));
        }

        // último recurso
        return 'local_' . date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 8);
    }
}