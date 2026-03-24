<?php

require_once __DIR__ . '/../src/cotizacion_generada.php';
require_once __DIR__ . '/MailService.php';
require_once __DIR__ . '/../src/log.php';
require_once __DIR__ . '/../src/db.php';

class CotizacionService
{
    private const DB_BATCH = 'marcos2022_api';
    private const DB_COTIZADOR = 'marcos2022_api_cotizador';
    private const MAX_COMPARABLES = 6;

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
            // nunca romper flujo por log
        }
    }

    private function persistirItemCotizacion(array $row): void
    {
        try {
            $db = Database::getInstance();

            $sql = "INSERT INTO " . self::DB_COTIZADOR . ".cotizacion_items
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
                ':cotizacion_id'     => $row['cotizacion_id'] ?? null,
                ':run_id'            => $row['run_id'] ?? null,
                ':brand'             => $row['brand'] ?? null,
                ':modelo'            => $row['modelo'] ?? null,
                ':source'            => $row['source'] ?? 'apify_publicaciones',
                ':item_url'          => $row['item_url'] ?? null,
                ':item_id'           => $row['item_id'] ?? null,
                ':title'             => $row['title'] ?? null,
                ':seller'            => $row['seller'] ?? null,
                ':is_official_store' => (int)($row['is_official_store'] ?? 0),
                ':price_value'       => $row['price_value'] ?? null,
                ':price_currency'    => $row['price_currency'] ?? null,
                ':item_year'         => $row['item_year'] ?? null,
                ':item_km'           => $row['item_km'] ?? null,
                ':location_txt'      => $row['location_txt'] ?? null,
                ':passes_filters'    => (int)($row['passes_filters'] ?? 0),
                ':reject_reason'     => $row['reject_reason'] ?? null,
                ':raw_json'          => $row['raw_json'] ?? null,
            ];

            $db->mysqlNonQuery(trim($sql), $params);

            $this->logInterno('ITEM_INSERT_OK', [
                'cotizacion_id' => $row['cotizacion_id'] ?? null,
                'item_id' => $row['item_id'] ?? null,
                'title' => $row['title'] ?? null
            ]);
        } catch (\Throwable $e) {
            $this->logInterno('PERSIST_ITEM_FAIL', [
                'cotizacion_id' => $row['cotizacion_id'] ?? null,
                'item_id' => $row['item_id'] ?? null,
                'title' => $row['title'] ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function actualizarEstadoCotizacion(int $idCotizacion, string $estado, string $detalleEstado, int $mailEnviado = 0, ?string $fechaMailSql = null): void
    {
        try {
            $db = Database::getInstance();

            $sql = "
                UPDATE " . self::DB_BATCH . ".cotizaciones_generadas
                SET estado = :estado,
                    detalle_estado = :detalle_estado,
                    mail_enviado = :mail_enviado,
                    fecha_mail = :fecha_mail
                WHERE id_cotizaciones_generadas = :id
            ";

            $params = [
                ':estado' => $estado,
                ':detalle_estado' => $detalleEstado,
                ':mail_enviado' => $mailEnviado,
                ':fecha_mail' => $fechaMailSql,
                ':id' => $idCotizacion,
            ];

            $db->mysqlNonQuery($sql, $params);
        } catch (\Throwable $e) {
            $this->logInterno('UPDATE_ESTADO_FAIL', [
                'cotizacion_id' => $idCotizacion,
                'estado' => $estado,
                'detalle_estado' => $detalleEstado,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function procesarCotizacionPublica(array $dataIn, string $brand): array
    {
        $this->logInterno('INICIO', [
            'brand_in'      => $brand,
            'modelo_in'     => $dataIn['modelo'] ?? null,
            'anio'          => $dataIn['anio'] ?? null,
            'km'            => $dataIn['km'] ?? null,
            'nombre_auto'   => $dataIn['nombre_auto'] ?? null,
            'version'       => $dataIn['version'] ?? null,
            'version_other' => $dataIn['version_other'] ?? null,
            'version_name'  => $dataIn['version_name'] ?? null,
        ]);

        $brandIn      = trim((string)$brand);
        $modeloIn     = trim((string)($dataIn['modelo'] ?? ''));
        $anioIn       = $this->toIntOrNull($dataIn['anio'] ?? null);
        $kmIn         = $this->toIntOrNull($dataIn['km'] ?? null);
        $version      = trim((string)($dataIn['version'] ?? ''));
        $versionOther = trim((string)($dataIn['version_other'] ?? ''));
        $versionName  = trim((string)($dataIn['version_name'] ?? ''));
        $nombreAuto   = trim((string)($dataIn['nombre_auto'] ?? ''));
        $ver          = trim((string)($versionOther ?: ($versionName ?: $version)));

        $brandId      = is_numeric($brandIn) ? (int)$brandIn : null;
        $modelInputId = is_numeric($modeloIn) ? (int)$modeloIn : null;
        $modelId      = null;

        $brandTxt  = $brandIn;
        $modeloTxt = $modeloIn;

        if ($brandId && $modelInputId) {
            try {
                $resolvedModel = $this->resolveModelIdLocal($brandId, $modelInputId);

                if ($resolvedModel && !empty($resolvedModel['resolved_id'])) {
                    $modelId = (int)$resolvedModel['resolved_id'];

                    if (!empty($resolvedModel['model_name'])) {
                        $modeloTxt = trim((string)$resolvedModel['model_name']);
                    }

                    $this->logInterno('MODEL_ID_MAP_OK', [
                        'brand_id'       => $brandId,
                        'model_input_id' => $modelInputId,
                        'model_id_final' => $modelId,
                        'source'         => $resolvedModel['source'] ?? null,
                        'row_brand_id'   => $resolvedModel['row_brand_id'] ?? null,
                        'model_name'     => $resolvedModel['model_name'] ?? null,
                    ]);
                } else {
                    $modelId = $modelInputId;

                    $this->logInterno('MODEL_ID_MAP_FALLBACK', [
                        'brand_id'       => $brandId,
                        'model_input_id' => $modelInputId,
                        'model_id_final' => $modelId
                    ]);
                }
            } catch (\Throwable $e) {
                $modelId = $modelInputId;

                $this->logInterno('MODEL_ID_MAP_FAIL', [
                    'brand_id'       => $brandId,
                    'model_input_id' => $modelInputId,
                    'model_id_final' => $modelId,
                    'error'          => $e->getMessage()
                ]);
            }
        }

        if ($brandId && $modelId) {
            try {
                $resolved = $this->resolveBrandAndModelNamesLocal($brandId, $modelId);

                if (!empty($resolved['brand_name'])) {
                    $brandTxt = trim((string)$resolved['brand_name']);
                }
                if (!empty($resolved['model_name'])) {
                    $modeloTxt = trim((string)$resolved['model_name']);
                }

                $this->logInterno('RESOLVE_IDS_OK', [
                    'brand_id'   => $brandId,
                    'model_id'   => $modelId,
                    'brand_name' => $brandTxt,
                    'model_name' => $modeloTxt,
                ]);
            } catch (\Throwable $e) {
                $this->logInterno('RESOLVE_IDS_FAIL', [
                    'error'    => $e->getMessage(),
                    'brand_id' => $brandId,
                    'model_id' => $modelId
                ]);
            }
        }

        if (!$brandId || !$modelId) {
            $this->logInterno('IDS_FALTANTES', [
                'brand_in'       => $brandIn,
                'modelo_in'      => $modeloIn,
                'model_input_id' => $modelInputId,
                'model_id_final' => $modelId,
                'nombre_auto'    => $nombreAuto
            ]);

            return [
                'msg' => 'Marca o modelo inválidos (faltan IDs).',
                'resultado' => null,
                'id_cotizacion' => null,
                'post_cotizacion' => null
            ];
        }

        if (($brandId === null && $brandTxt === '') || ($modelId === null && $modeloTxt === '')) {
            return [
                'msg' => 'Marca o modelo inválidos (no se pudo resolver).',
                'resultado' => null,
                'id_cotizacion' => null,
                'post_cotizacion' => null
            ];
        }

        $this->logInterno('BUSCANDO_ULTIMA_CORRIDA', [
            'brand_id' => $brandId,
            'model_id' => $modelId
        ]);

        try {
            $ultimaCorrida = $this->obtenerUltimaCorridaValida($brandId, $modelId);
        } catch (\Throwable $e) {
            $this->logInterno('EXCEPTION_ULTIMA_CORRIDA', [
                'error'    => $e->getMessage(),
                'file'     => $e->getFile(),
                'line'     => $e->getLine(),
                'brand_id' => $brandId,
                'model_id' => $modelId,
                'brandTxt' => $brandTxt,
                'modeloTxt'=> $modeloTxt
            ]);

            return [
                'msg' => 'Error buscando última corrida válida: ' . $e->getMessage(),
                'resultado' => null,
                'id_cotizacion' => null,
                'post_cotizacion' => null
            ];
        }

        if (!$ultimaCorrida || empty($ultimaCorrida['corrida_id'])) {
            $this->logInterno('SIN_CORRIDA_VALIDA', [
                'brand_id'  => $brandId,
                'model_id'  => $modelId,
                'brandTxt'  => $brandTxt,
                'modeloTxt' => $modeloTxt
            ]);

            return [
                'msg' => 'No existe una corrida válida reciente para esa marca/modelo.',
                'resultado' => null,
                'id_cotizacion' => null,
                'post_cotizacion' => null
            ];
        }

        $corridaId = (string)$ultimaCorrida['corrida_id'];

        $this->logInterno('ULTIMA_CORRIDA_VALIDA', [
            'corrida_id' => $corridaId,
            'estado'     => $ultimaCorrida['estado'] ?? null,
            'updated_at' => $ultimaCorrida['updated_at'] ?? null,
        ]);

        $this->logInterno('BUSCANDO_PUBLICACIONES_CORRIDA', [
            'corrida_id' => $corridaId,
            'brand_id'   => $brandId,
            'model_id'   => $modelId,
            'brandTxt'   => $brandTxt,
            'modeloTxt'  => $modeloTxt
        ]);

        try {
            $publicaciones = $this->obtenerPublicacionesCorrida($corridaId, $brandId, $modelId);
        } catch (\Throwable $e) {
            $this->logInterno('EXCEPTION_PUBLICACIONES_CORRIDA', [
                'error'     => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'corrida_id'=> $corridaId,
                'brand_id'  => $brandId,
                'model_id'  => $modelId
            ]);

            return [
                'msg' => 'Error buscando publicaciones de corrida: ' . $e->getMessage(),
                'resultado' => null,
                'id_cotizacion' => null,
                'post_cotizacion' => null
            ];
        }

        $this->logInterno('PUBLICACIONES_CORRIDA', [
            'corrida_id' => $corridaId,
            'count'      => count($publicaciones),
        ]);

        if (count($publicaciones) === 0) {
            return [
                'msg' => 'No se encontraron publicaciones para la última corrida válida.',
                'resultado' => null,
                'id_cotizacion' => null,
                'post_cotizacion' => null
            ];
        }

        $comparables = [];

        foreach ($publicaciones as $pub) {
            $precio = $this->toFloatOrNull($pub['precio'] ?? null);
            $itemYear = $this->toIntOrNull($pub['anio'] ?? null);
            $itemKm   = $this->toIntOrNull($pub['km'] ?? null);

            $pubVersion = trim((string)($pub['version'] ?? ''));
            $titulo     = trim((string)($pub['titulo'] ?? ''));
            $moneda     = trim((string)($pub['moneda'] ?? ''));
            $url        = trim((string)($pub['url'] ?? ''));
            $vendedor   = trim((string)($pub['vendedor'] ?? ''));
            $ubicacion  = trim((string)($pub['ubicacion'] ?? ''));
            $mlId       = trim((string)($pub['ml_id'] ?? ''));
            $esOficial  = (int)($pub['es_oficial'] ?? 0);

            $this->logInterno('PUB_ANALISIS', [
                'corrida_id'   => $corridaId,
                'ml_id'        => $mlId,
                'titulo'       => $titulo,
                'pub_version'  => $pubVersion,
                'anio_pub'     => $itemYear,
                'km_pub'       => $itemKm,
                'modelo_id'    => $pub['modelo_id'] ?? null,
                'modelo_txt'   => $pub['modelo_txt'] ?? null,
                'version_in'   => $ver,
                'anio_in'      => $anioIn,
                'km_in'        => $kmIn
            ]);

            $passes = true;
            $reason = null;

            if ($precio === null || $precio <= 0) {
                $passes = false;
                $reason = 'no_price';
            }

            if ($passes && $anioIn !== null) {
                if ($itemYear === null) {
                    $passes = false;
                    $reason = 'year_missing';
                } elseif (abs($itemYear - $anioIn) > 1) {
                    $passes = false;
                    $reason = 'year_out_of_range';
                }
            }

            if ($passes && $kmIn !== null) {
                if ($itemKm === null) {
                    $passes = false;
                    $reason = 'km_missing';
                } elseif (abs($itemKm - $kmIn) > 20000) {
                    $passes = false;
                    $reason = 'km_out_of_range';
                }
            }

            if ($passes && $ver !== '') {
                $versionMatch = $this->versionParecida($ver, $pubVersion, $titulo);
                if (!$versionMatch) {
                    $passes = false;
                    $reason = 'version_mismatch';
                }
            }

            if (!$passes) {
                $this->logInterno('PUB_REJECT', [
                    'corrida_id'   => $corridaId,
                    'ml_id'        => $mlId,
                    'titulo'       => $titulo,
                    'pub_version'  => $pubVersion,
                    'anio_pub'     => $itemYear,
                    'km_pub'       => $itemKm,
                    'modelo_id'    => $pub['modelo_id'] ?? null,
                    'modelo_txt'   => $pub['modelo_txt'] ?? null,
                    'version_in'   => $ver,
                    'anio_in'      => $anioIn,
                    'km_in'        => $kmIn,
                    'reason'       => $reason
                ]);
                continue;
            }

            $comparables[] = [
                'precio' => $precio,
                'row' => [
                    'cotizacion_id'     => null,
                    'run_id'            => $corridaId,
                    'brand'             => $brandTxt,
                    'modelo'            => $modeloTxt,
                    'source'            => 'apify_publicaciones',
                    'item_url'          => $url ?: null,
                    'item_id'           => $mlId ?: null,
                    'title'             => $titulo ?: null,
                    'seller'            => $vendedor ?: null,
                    'is_official_store' => $esOficial ? 1 : 0,
                    'price_value'       => $precio,
                    'price_currency'    => $moneda ?: null,
                    'item_year'         => $itemYear,
                    'item_km'           => $itemKm,
                    'location_txt'      => $ubicacion ?: null,
                    'passes_filters'    => 1,
                    'reject_reason'     => null,
                    'raw_json'          => $pub['raw_json'] ?? json_encode($pub, JSON_UNESCAPED_UNICODE),
                ]
            ];
        }

        $this->logInterno('FILTER_RESULT', [
            'corrida_id'          => $corridaId,
            'publicaciones_total' => count($publicaciones),
            'comparables_ok'      => count($comparables),
            'version_filtro'      => $ver,
            'anio_in'             => $anioIn,
            'km_in'               => $kmIn
        ]);

        if (count($comparables) === 0) {
            return [
                'msg' => 'No se encontraron publicaciones comparables en la última corrida válida.',
                'resultado' => null,
                'id_cotizacion' => null,
                'post_cotizacion' => null,
                'debug' => [
                    'corrida_id' => $corridaId,
                    'publicaciones_total' => count($publicaciones),
                    'version' => $ver,
                    'anio' => $anioIn,
                    'km' => $kmIn
                ]
            ];
        }

        usort($comparables, function (array $a, array $b) {
            return $a['precio'] <=> $b['precio'];
        });

        $comparablesSeleccionados = array_slice($comparables, 0, self::MAX_COMPARABLES);
        $itemsParaPersistir = [];
        $prices = [];

        foreach ($comparablesSeleccionados as $cmp) {
            $prices[] = (float)$cmp['precio'];
            $itemsParaPersistir[] = $cmp['row'];
        }

        sort($prices);

        $minRaw = (float)$prices[0];
        $maxRaw = (float)$prices[count($prices) - 1];
        $avgRaw = (float)(array_sum($prices) / count($prices));

        $min = $this->redondearMotorlider($minRaw);
        $max = $this->redondearMotorlider($maxRaw);
        $avg = $this->redondearMotorlider($avgRaw);

        $this->logInterno('TOP_COMPARABLES_SELECTED', [
            'corrida_id'                => $corridaId,
            'comparables_filtrados'     => count($comparables),
            'comparables_seleccionados' => count($comparablesSeleccionados),
            'prices'                    => $prices
        ]);

        $motorliderCalc = $this->calcularValoresMotorlider(
            $brandTxt,
            $modeloTxt,
            $anioIn,
            $kmIn,
            $ver,
            $avg,
            $dataIn
        );

        $valorPretendidoCliente = $this->toFloatOrNull($dataIn['valor_pretendido'] ?? null);
        $valorPretendidoClienteRedondeado = $valorPretendidoCliente !== null
            ? $this->redondearMotorlider($valorPretendidoCliente)
            : null;

        $vpretendidoAplicado = false;
        if (
            $valorPretendidoClienteRedondeado !== null &&
            $motorliderCalc['valor_minimo_motorlider'] > 0 &&
            $valorPretendidoClienteRedondeado < $motorliderCalc['valor_minimo_motorlider']
        ) {
            $vpretendidoAplicado = true;
        }

        $motorliderCalc['porcentajes_aplicados']['vpretendido_aplicado'] = $vpretendidoAplicado;
        $motorliderCalc['porcentajes_aplicados']['valor_pretendido_cliente'] = $valorPretendidoClienteRedondeado;

        $resultado = [
            'count'                     => count($prices),
            'count_filtrados'           => count($comparables),
            'count_total_publicaciones' => count($publicaciones),
            'min'                       => $min,
            'max'                       => $max,
            'avg'                       => $avg,
            'corrida_id'                => $corridaId,
            'brand'                     => $brandTxt,
            'modelo'                    => $modeloTxt,
            'version'                   => $ver,
            'comparables_limit'         => self::MAX_COMPARABLES,
            'valor_minimo_motorlider'   => $motorliderCalc['valor_minimo_motorlider'],
            'valor_maximo_motorlider'   => $motorliderCalc['valor_maximo_motorlider'],
            'valor_promedio_motorlider' => $motorliderCalc['valor_promedio_motorlider'],
            'promedio_mercado_6'        => $avg,
            'promedio_base_motorlider'  => $motorliderCalc['promedio_base_motorlider'],
            'vpretendido_aplicado'      => $vpretendidoAplicado,
            'valor_pretendido_cliente'  => $valorPretendidoClienteRedondeado,
            'porcentajes_aplicados'     => $motorliderCalc['porcentajes_aplicados'],
        ];

        $this->logInterno('RESULTADO_OK', $resultado);

        $id = null;
        $cg_data = [];

        try {
            $cg_data['nombre']            = $dataIn['nombre'] ?? null;
            $cg_data['email']             = $dataIn['email'] ?? null;
            $cg_data['telefono']          = $dataIn['telefono'] ?? null;
            $cg_data['ci']                = $dataIn['ci'] ?? null;
            $cg_data['fecha']             = date('Y-m-d');
            $cg_data['kilometros']        = $kmIn;
            $cg_data['ficha_tecnica']     = $dataIn['ficha_tecnica'] ?? null;
            $cg_data['duenios']           = $dataIn['cantidad_duenios'] ?? null;
            $cg_data['tipo_venta']        = $this->mapTipoVentaTexto($dataIn['venta_permuta'] ?? null);
            $cg_data['precio_pretendido'] = $valorPretendidoClienteRedondeado;
            $cg_data['marca']             = $brandTxt;
            $cg_data['anio']              = $anioIn;
            $cg_data['familia']           = $modelId;
            $cg_data['auto']              = $dataIn['nombre_auto'] ?? trim($brandTxt . ' ' . $modeloTxt);

            $cg_data['valor_minimo']   = $min;
            $cg_data['valor_maximo']   = $max;
            $cg_data['valor_promedio'] = $avg;

            $cg_data['valor_minimo_autodata']   = $motorliderCalc['valor_minimo_motorlider'];
            $cg_data['valor_maximo_autodata']   = $motorliderCalc['valor_maximo_motorlider'];
            $cg_data['valor_promedio_autodata'] = $motorliderCalc['valor_promedio_motorlider'];

            $cg_data['datos'] = json_encode([
                'corrida_id'            => $corridaId,
                'brand_id'              => $brandId,
                'model_input_id'        => $modelInputId,
                'model_id'              => $modelId,
                'brand_final'           => $brandTxt,
                'modelo_final'          => $modeloTxt,
                'anio'                  => $anioIn,
                'version'               => $ver,
                'publicaciones_total'   => count($publicaciones),
                'comparables_filtrados' => count($comparables),
                'comparables_usados'    => count($prices),
                'comparables_limit'     => self::MAX_COMPARABLES,
                'fuente'                => 'apify_publicaciones',
                'comparables_prices'    => $prices
            ], JSON_UNESCAPED_UNICODE);

            $cg_data['respuesta'] = json_encode($resultado, JSON_UNESCAPED_UNICODE);
            $cg_data['msg'] = 'OK';
            $cg_data['porcentajes_aplicados'] = json_encode($motorliderCalc['porcentajes_aplicados'], JSON_UNESCAPED_UNICODE);
            $cg_data['cuenta'] = null;

            $cg_data['estado'] = 'PENDIENTE';
            $cg_data['detalle_estado'] = 'Cotización generada pendiente de envío de mail';
            $cg_data['mail_enviado'] = 0;
            $cg_data['fecha_mail'] = null;

            $cg = new CotizacionGenerada($cg_data);
            $created = $cg->save();
            $id = isset($created->id_cotizaciones_generadas) ? (int)$created->id_cotizaciones_generadas : null;

            $this->logInterno('PERSIST_OK', ['id_cotizaciones_generadas' => $id]);
        } catch (\Throwable $e) {
            $this->logInterno('PERSIST_FAIL', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine()
            ]);
        }

        if ($id) {
            $okItems = 0;
            foreach ($itemsParaPersistir as $row) {
                $row['cotizacion_id'] = $id;
                $this->persistirItemCotizacion($row);
                $okItems++;
            }

            $this->logInterno('ITEMS_PERSIST_OK', [
                'cotizacion_id' => $id,
                'items_inserted'=> $okItems,
                'corrida_id'    => $corridaId
            ]);

            try {
                $mail = new MailService();

                $mail->enviarConfirmacionCotizacion(
                    [
                        'nombre' => $cg_data['nombre'],
                        'email' => $cg_data['email'],
                        'nombre_auto' => $cg_data['auto'],
                        'brand' => $brandTxt,
                        'modelo' => $modeloTxt,
                        'anio' => $anioIn,
                        'km' => $kmIn
                    ],
                    [
                        'ok' => true,
                        'comparables' => $resultado['count'],
                        'min' => $resultado['min'],
                        'max' => $resultado['max'],
                        'avg' => $resultado['avg'],
                        'valor_minimo_motorlider' => $resultado['valor_minimo_motorlider'],
                        'valor_maximo_motorlider' => $resultado['valor_maximo_motorlider'],
                        'valor_promedio_motorlider' => $resultado['valor_promedio_motorlider']
                    ]
                );

                $this->actualizarEstadoCotizacion(
                    (int)$id,
                    'FINALIZADA',
                    'Cotización generada y mail enviado',
                    1,
                    date('Y-m-d H:i:s')
                );

                $this->logInterno('MAIL_OK', [
                    'cotizacion_id' => $id,
                    'email' => $cg_data['email'] ?? null
                ]);
            } catch (\Throwable $e) {
                $this->actualizarEstadoCotizacion(
                    (int)$id,
                    'PENDIENTE',
                    'Cotización generada pero falló envío de mail: ' . $e->getMessage(),
                    0,
                    null
                );

                $this->logInterno('MAIL_FAIL', [
                    'cotizacion_id' => $id,
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            $this->logInterno('ITEMS_PERSIST_SKIP_NO_ID', [
                'items_count' => count($itemsParaPersistir),
                'corrida_id'  => $corridaId
            ]);
        }

        $postCotizacion = $this->buildPostCotizacionPayload(
            $id,
            $brandTxt,
            $modeloTxt,
            $modelId,
            $anioIn,
            $kmIn,
            $ver,
            $dataIn
        );

        return [
            'msg' => 'OK',
            'resultado' => $resultado,
            'id_cotizacion' => $id,
            'post_cotizacion' => $postCotizacion
        ];
    }

    private function buildPostCotizacionPayload(
        ?int $idCotizacion,
        string $brandTxt,
        string $modeloTxt,
        ?int $modelId,
        ?int $anioIn,
        ?int $kmIn,
        string $version,
        array $dataIn
    ): array {
        $auto = trim((string)($dataIn['nombre_auto'] ?? trim($brandTxt . ' ' . $modeloTxt)));

        return [
            'agenda_habilitada' => $idCotizacion ? true : false,
            'id_cotizacion' => $idCotizacion,
            'cliente' => [
                'nombre' => $dataIn['nombre'] ?? '',
                'email' => $dataIn['email'] ?? '',
                'telefono' => $dataIn['telefono'] ?? '',
            ],
            'vehiculo' => [
                'marca' => $brandTxt,
                'modelo' => $modeloTxt,
                'anio' => $anioIn,
                'familia' => $modelId,
                'version' => $version,
                'auto' => $auto,
                'km' => $kmIn,
            ],
            'endpoints' => [
                'calendar_template' => '/ws/index.php?peticion=calendar&location={location}',
                'schedules_template' => '/ws/index.php?peticion=schedules&location={location}',
                'schedule_inspection_template' => '/ws/index.php?peticion=scheduleInspection&location={location}',
            ]
        ];
    }

    private function calcularValoresMotorlider(
        string $brandTxt,
        string $modeloTxt,
        ?int $anioIn,
        ?int $kmIn,
        string $version,
        float $promedioMercado,
        array $dataIn
    ): array {
        $porcentajesAplicados = [
            'comparables_limit' => self::MAX_COMPARABLES,
            'promedio_mercado_6' => $promedioMercado,
            'tramo' => null,
            'porcentaje_tramo' => null,
            'nominal_tramo' => null,
            'ajuste_ficha_tecnica' => 0,
            'ajuste_duenios' => 0,
            'ajuste_stock' => 0,
            'tipo_operacion' => $dataIn['venta_permuta'] ?? null,
            'factor_operacion_min' => null,
            'factor_operacion_max' => null,
        ];

        $promedioBaseMotorlider = $promedioMercado;
        $valorMinMotorlider = $promedioMercado;
        $valorMaxMotorlider = $promedioMercado;
        $valorPromMotorlider = $promedioMercado;

        try {
            $promedioBaseData = $this->calcularPromedioBaseMotorlider($promedioMercado);
            $promedioBaseMotorlider = $promedioBaseData['valor'];
            $porcentajesAplicados['tramo'] = $promedioBaseData['tramo'];
            $porcentajesAplicados['porcentaje_tramo'] = $promedioBaseData['porcentaje'];
            $porcentajesAplicados['nominal_tramo'] = $promedioBaseData['nominal'];

            $fichaValor = $this->normalizarFichaTecnica($dataIn['ficha_tecnica'] ?? null);
            $dueniosValor = $this->toIntOrNull($dataIn['cantidad_duenios'] ?? null);

            $ajusteFicha = $this->calcularAjusteVariablePorPorcentaje(
                3,
                'ficha_oficial',
                $fichaValor,
                $promedioBaseMotorlider
            );

            $ajusteDuenios = $this->calcularAjusteVariablePorPorcentaje(
                4,
                'cantidad_duenios',
                $dueniosValor,
                $promedioBaseMotorlider
            );

            $ajusteStock = $this->calcularAjusteStock(
                $brandTxt,
                $modeloTxt,
                $anioIn,
                $kmIn,
                $version,
                $promedioBaseMotorlider
            );

            $porcentajesAplicados['ajuste_ficha_tecnica'] = $ajusteFicha;
            $porcentajesAplicados['ajuste_duenios'] = $ajusteDuenios;
            $porcentajesAplicados['ajuste_stock'] = $ajusteStock;

            $valorMinMotorlider = round($promedioBaseMotorlider + $ajusteFicha + $ajusteDuenios + $ajusteStock, 2);

            $esEntrega = $this->esEntregaComoFormaDePago($dataIn['venta_permuta'] ?? null);

            if ($esEntrega) {
                $be = $this->obtenerPonderadorVenalPorKey('BE');
                $bf = $this->obtenerPonderadorVenalPorKey('BF');

                $porcentajesAplicados['factor_operacion_min'] = $be;
                $porcentajesAplicados['factor_operacion_max'] = $bf;

                $valorMinMotorlider = round($valorMinMotorlider * $be, 2);
                $valorMaxMotorlider = round($valorMinMotorlider * $bf, 2);
                $valorPromMotorlider = round(($valorMinMotorlider + $valorMaxMotorlider) / 2, 2);
            } else {
                $bd = $this->obtenerPonderadorVenalPorKey('BD');

                $porcentajesAplicados['factor_operacion_min'] = 1;
                $porcentajesAplicados['factor_operacion_max'] = $bd;

                $valorMaxMotorlider = round($valorMinMotorlider * $bd, 2);
                $valorPromMotorlider = round(($valorMinMotorlider + $valorMaxMotorlider) / 2, 2);
            }
        } catch (\Throwable $e) {
            $this->logInterno('CALCULO_MOTORLIDER_FAIL', [
                'error' => $e->getMessage(),
                'brand' => $brandTxt,
                'modelo' => $modeloTxt,
                'anio' => $anioIn,
                'km' => $kmIn,
                'version' => $version,
                'promedio_mercado' => $promedioMercado
            ]);
        }

        $promedioBaseMotorlider = $this->redondearMotorlider($promedioBaseMotorlider);
        $valorMinMotorlider = $this->redondearMotorlider($valorMinMotorlider);
        $valorMaxMotorlider = $this->redondearMotorlider($valorMaxMotorlider);
        $valorPromMotorlider = $this->redondearMotorlider($valorPromMotorlider);

        return [
            'promedio_base_motorlider' => $promedioBaseMotorlider,
            'valor_minimo_motorlider' => $valorMinMotorlider,
            'valor_maximo_motorlider' => $valorMaxMotorlider,
            'valor_promedio_motorlider' => $valorPromMotorlider,
            'porcentajes_aplicados' => $porcentajesAplicados
        ];
    }

    private function calcularPromedioBaseMotorlider(float $average): array
    {
        $db = Database::getInstance();

        $rowsVenal = $this->dbFetchAll($db, "SELECT `key`, porcentaje FROM " . self::DB_BATCH . ".ponderador_valor_venal");
        $rowsNominal = $this->dbFetchAll($db, "SELECT `key`, nominal FROM " . self::DB_BATCH . ".ponderador_valor");

        $mapPct = [];
        foreach ($rowsVenal as $r) {
            $mapPct[(string)$r['key']] = (float)$r['porcentaje'];
        }

        $mapNom = [];
        foreach ($rowsNominal as $r) {
            $mapNom[(string)$r['key']] = (float)$r['nominal'];
        }

        $tramos = [
            ['name' => 'A/C',   'min' => 0,     'max' => 5000,  'pct' => 'A',  'nom' => 'C'],
            ['name' => 'E/G',   'min' => 5000,  'max' => 10000, 'pct' => 'E',  'nom' => 'G'],
            ['name' => 'I/K',   'min' => 10000, 'max' => 15000, 'pct' => 'I',  'nom' => 'K'],
            ['name' => 'M/Ñ',   'min' => 15000, 'max' => 20000, 'pct' => 'M',  'nom' => 'Ñ'],
            ['name' => 'P/R',   'min' => 20000, 'max' => 25000, 'pct' => 'P',  'nom' => 'R'],
            ['name' => 'T/Y',   'min' => 25000, 'max' => 30000, 'pct' => 'T',  'nom' => 'Y'],
            ['name' => 'AA/AC', 'min' => 30000, 'max' => 35000, 'pct' => 'AA', 'nom' => 'AC'],
            ['name' => 'AE/AG', 'min' => 35000, 'max' => 40000, 'pct' => 'AE', 'nom' => 'AG'],
            ['name' => 'AI/AK', 'min' => 40000, 'max' => 45000, 'pct' => 'AI', 'nom' => 'AK'],
            ['name' => 'AM/AÑ', 'min' => 45000, 'max' => 50000, 'pct' => 'AM', 'nom' => 'AÑ'],
            ['name' => 'AP/AR', 'min' => 50000, 'max' => 60000, 'pct' => 'AP', 'nom' => 'AR'],
            ['name' => 'AT/AY', 'min' => 60000, 'max' => 70000, 'pct' => 'AT', 'nom' => 'AY'],
        ];

        foreach ($tramos as $t) {
            $ok = false;

            if ($t['min'] == 0) {
                $ok = ($average < $t['max']);
            } else {
                $ok = ($average >= $t['min'] && $average <= $t['max']);
            }

            if ($ok) {
                $pct = isset($mapPct[$t['pct']]) ? (float)$mapPct[$t['pct']] : 1.0;
                $nom = isset($mapNom[$t['nom']]) ? (float)$mapNom[$t['nom']] : 0.0;

                return [
                    'valor' => round(($average * $pct) - $nom, 2),
                    'tramo' => $t['name'],
                    'porcentaje' => $pct,
                    'nominal' => $nom
                ];
            }
        }

        return [
            'valor' => round($average, 2),
            'tramo' => 'SIN_TRAMO',
            'porcentaje' => 1,
            'nominal' => 0
        ];
    }

    private function calcularAjusteVariablePorPorcentaje(int $tipo, string $campo, $valorBuscado, float $base): float
    {
        if ($valorBuscado === null || $valorBuscado === '') {
            return 0.0;
        }

        $db = Database::getInstance();

        if (is_numeric($valorBuscado)) {
            $sql = "
                SELECT porcentaje, operador
                FROM " . self::DB_BATCH . ".variables
                WHERE tipo = " . (int)$tipo . "
                  AND {$campo} = " . (int)$valorBuscado . "
                LIMIT 1
            ";
        } else {
            $sql = "
                SELECT porcentaje, operador
                FROM " . self::DB_BATCH . ".variables
                WHERE tipo = " . (int)$tipo . "
                  AND {$campo} = " . $this->sqlQuote((string)$valorBuscado) . "
                LIMIT 1
            ";
        }

        $row = $this->dbFetchOne($db, $sql);
        if (!$row) {
            return 0.0;
        }

        $porcentaje = (float)($row['porcentaje'] ?? 0);
        $operador = trim((string)($row['operador'] ?? '+'));
        $valor = round(($porcentaje / 100) * $base, 2);

        return ($operador === '-') ? -$valor : $valor;
    }

    private function calcularAjusteStock(
        string $brandTxt,
        string $modeloTxt,
        ?int $anioIn,
        ?int $kmIn,
        string $version,
        float $base
    ): float {
        if ($anioIn === null || $kmIn === null) {
            return $this->calcularAjusteStockDefault($base);
        }

        $db = Database::getInstance();

        $versionBuscar = trim((string)$version);
        $sqlVersion = $versionBuscar !== ''
            ? " AND version = " . $this->sqlQuote($versionBuscar) . " "
            : " ";

        $sql = "
            SELECT kilometros, stock
            FROM " . self::DB_BATCH . ".ponderador_valor_stock
            WHERE marca = " . $this->sqlQuote($brandTxt) . "
              AND modelo = " . $this->sqlQuote($modeloTxt) . "
              AND anio = " . (int)$anioIn . "
              {$sqlVersion}
            LIMIT 1
        ";

        $row = $this->dbFetchOne($db, $sql);

        if (!$row) {
            return $this->calcularAjusteStockDefault($base);
        }

        $kmRef = $this->toIntOrNull($row['kilometros'] ?? null);
        $stock = $this->toIntOrNull($row['stock'] ?? null);

        if ($kmRef === null || $stock === null) {
            return $this->calcularAjusteStockDefault($base);
        }

        $rowBusqueda = $this->dbFetchOne($db, "
            SELECT busqueda
            FROM " . self::DB_BATCH . ".ponderador_valor_busqueda
            LIMIT 1
        ");

        $busqueda = $this->toIntOrNull($rowBusqueda['busqueda'] ?? null);
        if ($busqueda === null) {
            $busqueda = 0;
        }

        $maxKm = $kmRef + $busqueda;
        $minKm = max(0, $kmRef - $busqueda);

        if ($kmIn < $minKm || $kmIn > $maxKm) {
            return $this->calcularAjusteStockPorNumero(0, $base);
        }

        if ($stock > 5) {
            $stock = 5;
        }

        return $this->calcularAjusteStockPorNumero($stock, $base);
    }

    private function calcularAjusteStockDefault(float $base): float
    {
        return $this->calcularAjusteStockPorNumero(0, $base);
    }

    private function calcularAjusteStockPorNumero(int $stock, float $base): float
    {
        $db = Database::getInstance();

        $row = $this->dbFetchOne($db, "
            SELECT porcentaje, operador
            FROM " . self::DB_BATCH . ".variables
            WHERE tipo = 6
              AND stock = " . (int)$stock . "
            LIMIT 1
        ");

        if (!$row) {
            return 0.0;
        }

        $porcentaje = (float)($row['porcentaje'] ?? 0);
        $operador = trim((string)($row['operador'] ?? '+'));
        $valor = round(($porcentaje / 100) * $base, 2);

        return ($operador === '-') ? -$valor : $valor;
    }

    private function obtenerPonderadorVenalPorKey(string $key): float
    {
        $db = Database::getInstance();

        $row = $this->dbFetchOne($db, "
            SELECT porcentaje
            FROM " . self::DB_BATCH . ".ponderador_valor_venal
            WHERE `key` = " . $this->sqlQuote($key) . "
            LIMIT 1
        ");

        if (!$row) {
            return 1.0;
        }

        return (float)($row['porcentaje'] ?? 1);
    }

    private function obtenerUltimaCorridaValida(int $brandId, int $modelId): ?array
    {
        $db = Database::getInstance();

        $sql = "
            SELECT
                c.corrida_id,
                c.estado,
                c.updated_at,
                c.created_at,
                c.id
            FROM " . self::DB_BATCH . ".apify_corridas c
            WHERE c.estado = 'ok'
              AND EXISTS (
                  SELECT 1
                  FROM " . self::DB_BATCH . ".apify_publicaciones p
                  WHERE p.corrida_id = c.corrida_id
                    AND p.marca_id = " . (int)$brandId . "
                    AND p.modelo_id = " . (int)$modelId . "
              )
            ORDER BY COALESCE(c.updated_at, c.created_at) DESC, c.id DESC
            LIMIT 1
        ";

        $rows = $this->dbFetchAll($db, $sql);
        return $rows[0] ?? null;
    }

    private function obtenerPublicacionesCorrida(string $corridaId, int $brandId, int $modelId): array
    {
        $db = Database::getInstance();

        $sql = "
            SELECT
                p.id,
                p.corrida_id,
                p.marca_id,
                p.modelo_id,
                p.marca_txt,
                p.modelo_txt,
                p.fuente,
                p.ml_id,
                p.titulo,
                p.precio,
                p.moneda,
                p.anio,
                p.km,
                p.ubicacion,
                p.url,
                p.vendedor,
                p.es_oficial,
                p.raw_json,
                p.created_at,
                p.version_id,
                p.version
            FROM " . self::DB_BATCH . ".apify_publicaciones p
            WHERE p.corrida_id = " . $this->sqlQuote($corridaId) . "
              AND p.marca_id = " . (int)$brandId . "
              AND p.modelo_id = " . (int)$modelId . "
              AND p.precio IS NOT NULL
              AND p.precio > 0
            ORDER BY p.created_at DESC, p.id DESC
        ";

        return $this->dbFetchAll($db, $sql);
    }

    private function versionParecida(string $inputVersion, string $pubVersion, string $titulo): bool
    {
        $needle = $this->normalizarTexto($inputVersion);
        if ($needle === '') {
            return true;
        }

        $haystacks = [
            $this->normalizarTexto($pubVersion),
            $this->normalizarTexto($titulo),
        ];

        foreach ($haystacks as $h) {
            if ($h !== '' && strpos($h, $needle) !== false) {
                return true;
            }
        }

        $tokens = array_values(array_filter(explode(' ', $needle)));
        if (count($tokens) > 0) {
            foreach ($haystacks as $h) {
                if ($h === '') {
                    continue;
                }

                $allFound = true;
                foreach ($tokens as $tk) {
                    if (strlen($tk) < 2) {
                        continue;
                    }
                    if (strpos($h, $tk) === false) {
                        $allFound = false;
                        break;
                    }
                }
                if ($allFound) {
                    return true;
                }
            }
        }

        return false;
    }

    private function normalizarTexto(string $txt): string
    {
        $txt = mb_strtolower(trim($txt), 'UTF-8');
        $txt = str_replace(
            ['á','é','í','ó','ú','ä','ë','ï','ö','ü','ñ'],
            ['a','e','i','o','u','a','e','i','o','u','n'],
            $txt
        );
        $txt = preg_replace('/[^a-z0-9]+/u', ' ', $txt);
        $txt = preg_replace('/\s+/', ' ', $txt);
        return trim($txt);
    }

    private function redondearMotorlider($valor): int
    {
        $n = (float)$valor;
        $entero = floor($n);
        $decimal = $n - $entero;

        if ($decimal <= 0.50) {
            return (int)$entero;
        }

        return (int)ceil($n);
    }

    private function toIntOrNull($v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_int($v)) {
            return $v;
        }

        $s = trim((string)$v);
        if ($s === '') {
            return null;
        }

        $s = preg_replace('/[^\d\-]/', '', $s);
        if ($s === '' || $s === '-') {
            return null;
        }

        return (int)$s;
    }

    private function toFloatOrNull($v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_float($v) || is_int($v)) {
            return (float)$v;
        }

        $s = trim((string)$v);
        if ($s === '') {
            return null;
        }

        $s = preg_replace('/[^0-9,\.\-]/', '', $s);
        if ($s === '' || $s === '-' || $s === '.' || $s === ',') {
            return null;
        }

        if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
            $lastComma = strrpos($s, ',');
            $lastDot   = strrpos($s, '.');

            if ($lastComma > $lastDot) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                $s = str_replace(',', '', $s);
            }
        } else {
            if (strpos($s, ',') !== false) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            }
        }

        return is_numeric($s) ? (float)$s : null;
    }

    private function sqlQuote(string $value): string
    {
        return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
    }

    private function dbFetchAll($db, string $sql): array
    {
        $sql = trim($sql);

        if (method_exists($db, 'query') && method_exists($db, 'fetch_array')) {
            $res = $db->query($sql);
            $rows = [];
            while ($r = $db->fetch_array($res)) {
                $rows[] = $r;
            }
            return $rows;
        }

        if (method_exists($db, 'mysqlQuery')) {
            $res = $db->mysqlQuery($sql);

            if (is_array($res)) {
                return $res;
            }

            if (method_exists($db, 'fetch_array')) {
                $rows = [];
                while ($r = $db->fetch_array($res)) {
                    $rows[] = $r;
                }
                return $rows;
            }
        }

        throw new \Exception('No se pudo ejecutar dbFetchAll: interfaz DB no soportada.');
    }

    private function dbFetchOne($db, string $sql): ?array
    {
        $rows = $this->dbFetchAll($db, $sql);
        return $rows[0] ?? null;
    }

    private function resolveBrandAndModelNamesLocal(int $brandId, int $modelId): array
    {
        $db = Database::getInstance();

        $tblMarca  = self::DB_BATCH . ".act_marcas";
        $tblModelo = self::DB_BATCH . ".act_modelo";

        $brandRows = $this->dbFetchAll($db, "
            SELECT *
            FROM {$tblMarca}
            WHERE id_marca = " . (int)$brandId . "
            LIMIT 1
        ");

        $modelRows = $this->dbFetchAll($db, "
            SELECT *
            FROM {$tblModelo}
            WHERE id_model = " . (int)$modelId . "
            LIMIT 1
        ");

        if (empty($brandRows)) {
            throw new \Exception("No se pudo resolver nombre de marca local (ID {$brandId})");
        }

        if (empty($modelRows)) {
            throw new \Exception("No se pudo resolver nombre de modelo local (ID_MODEL {$modelId})");
        }

        $brandRow = $brandRows[0];
        $modelRow = $modelRows[0];

        $brandName = $this->pickArr($brandRow, ['nombre', 'name', 'marca']);
        $modelName = $this->pickArr($modelRow, ['nombre', 'name', 'modelo']);

        if (!$brandName) {
            throw new \Exception("La marca local no tiene nombre resoluble (ID {$brandId})");
        }

        if (!$modelName) {
            throw new \Exception("El modelo local no tiene nombre resoluble (ID_MODEL {$modelId})");
        }

        return [
            'brand_name' => $brandName,
            'model_name' => $modelName
        ];
    }

    private function pickArr(array $row, array $keys, $default = null)
    {
        foreach ($keys as $k) {
            if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null) {
                return $row[$k];
            }
        }
        return $default;
    }

    private function resolveModelIdLocal(int $brandId, int $modelInputId): ?array
    {
        $db = Database::getInstance();
        $tblModelo = self::DB_BATCH . ".act_modelo";

        $rows = $this->dbFetchAll($db, "
            SELECT *
            FROM {$tblModelo}
            WHERE id = " . (int)$modelInputId . "
            LIMIT 1
        ");

        if (!empty($rows)) {
            $row = $rows[0];

            $realModelId = $this->pickArr($row, ['id_model', 'id_modelo', 'modelo_id']);
            $modelName   = $this->pickArr($row, ['nombre', 'name', 'modelo']);
            $rowBrandId  = $this->pickArr($row, ['id_marca', 'marca_id']);

            if ($realModelId !== null) {
                return [
                    'input_id'      => $modelInputId,
                    'resolved_id'   => (int)$realModelId,
                    'model_name'    => $modelName,
                    'row_brand_id'  => $rowBrandId !== null ? (int)$rowBrandId : null,
                    'source'        => 'act_modelo.id'
                ];
            }
        }

        $rows2 = $this->dbFetchAll($db, "
            SELECT *
            FROM {$tblModelo}
            WHERE id_model = " . (int)$modelInputId . "
            LIMIT 1
        ");

        if (!empty($rows2)) {
            $row = $rows2[0];

            $realModelId = $this->pickArr($row, ['id_model', 'id_modelo', 'modelo_id']);
            $modelName   = $this->pickArr($row, ['nombre', 'name', 'modelo']);
            $rowBrandId  = $this->pickArr($row, ['id_marca', 'marca_id']);

            if ($realModelId !== null) {
                return [
                    'input_id'      => $modelInputId,
                    'resolved_id'   => (int)$realModelId,
                    'model_name'    => $modelName,
                    'row_brand_id'  => $rowBrandId !== null ? (int)$rowBrandId : null,
                    'source'        => 'act_modelo.id_model'
                ];
            }
        }

        return null;
    }

    private function normalizarFichaTecnica($valor): string
    {
        $v = trim((string)$valor);

        if ($v === '1' || strcasecmp($v, 'si') === 0 || strcasecmp($v, 'sí') === 0) {
            return 'Si';
        }

        if ($v === '0' || strcasecmp($v, 'no') === 0) {
            return 'No';
        }

        return $v !== '' ? ucfirst(mb_strtolower($v, 'UTF-8')) : '';
    }

    private function esEntregaComoFormaDePago($valor): bool
    {
        $v = trim((string)$valor);

        if ($v === '1') {
            return true;
        }

        return strcasecmp($v, 'Entrega') === 0
            || strcasecmp($v, 'entrega_forma_pago') === 0
            || strcasecmp($v, 'Entrega como forma de pago') === 0;
    }

    private function mapTipoVentaTexto($valor): string
    {
        return $this->esEntregaComoFormaDePago($valor) ? 'Entrega' : 'Venta';
    }
}