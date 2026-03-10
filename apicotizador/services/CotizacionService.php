<?php

require_once __DIR__ . '/../src/cotizacion_generada.php';
require_once __DIR__ . '/MailService.php';
require_once __DIR__ . '/../src/log.php';
require_once __DIR__ . '/../src/db.php';

class CotizacionService
{
    private const DB_BATCH = 'marcos2022_api';
    private const DB_COTIZADOR = 'marcos2022_api_cotizador';

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

            $db->mysqlNonQuery($sql, $params);
        } catch (\Throwable $e) {
            $this->logInterno('PERSIST_ITEM_FAIL', ['error' => $e->getMessage()]);
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

        $brandId = is_numeric($brandIn) ? (int)$brandIn : null;
        $modelId = is_numeric($modeloIn) ? (int)$modeloIn : null;

        $brandTxt  = $brandIn;
        $modeloTxt = $modeloIn;

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
                'brand_in'    => $brandIn,
                'modelo_in'   => $modeloIn,
                'nombre_auto' => $nombreAuto
            ]);

            return [
                'msg' => 'Marca o modelo inválidos (faltan IDs).',
                'resultado' => null,
                'id_cotizacion' => null
            ];
        }

        if (($brandId === null && $brandTxt === '') || ($modelId === null && $modeloTxt === '')) {
            return [
                'msg' => 'Marca o modelo inválidos (no se pudo resolver).',
                'resultado' => null,
                'id_cotizacion' => null
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
                'id_cotizacion' => null
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
                'id_cotizacion' => null
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
                'id_cotizacion' => null
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
                'id_cotizacion' => null
            ];
        }

        $itemsParaPersistir = [];
        $prices = [];

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
                continue;
            }

            $prices[] = $precio;

            $itemsParaPersistir[] = [
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
            ];
        }

        $this->logInterno('FILTER_RESULT', [
            'corrida_id'          => $corridaId,
            'publicaciones_total' => count($publicaciones),
            'comparables_ok'      => count($prices),
            'version_filtro'      => $ver,
            'anio_in'             => $anioIn,
            'km_in'               => $kmIn
        ]);

        if (count($prices) === 0) {
            return [
                'msg' => 'No se encontraron publicaciones comparables en la última corrida válida.',
                'resultado' => null,
                'id_cotizacion' => null,
                'debug' => [
                    'corrida_id' => $corridaId,
                    'publicaciones_total' => count($publicaciones),
                    'version' => $ver,
                    'anio' => $anioIn,
                    'km' => $kmIn
                ]
            ];
        }

        sort($prices);
        $min = $prices[0];
        $max = $prices[count($prices) - 1];
        $avg = round(array_sum($prices) / count($prices), 2);

        $resultado = [
            'count'      => count($prices),
            'min'        => $min,
            'max'        => $max,
            'avg'        => $avg,
            'corrida_id' => $corridaId,
            'brand'      => $brandTxt,
            'modelo'     => $modeloTxt,
            'version'    => $ver,
        ];

        $this->logInterno('RESULTADO_OK', $resultado);

        $id = null;

        try {
            $cg_data = [];

            $cg_data['nombre']            = $dataIn['nombre'] ?? null;
            $cg_data['email']             = $dataIn['email'] ?? null;
            $cg_data['telefono']          = $dataIn['telefono'] ?? null;
            $cg_data['ci']                = $dataIn['ci'] ?? null;
            $cg_data['fecha']             = date('Y-m-d');
            $cg_data['kilometros']        = $kmIn;
            $cg_data['ficha_tecnica']     = $dataIn['ficha_tecnica'] ?? null;
            $cg_data['duenios']           = $dataIn['cantidad_duenios'] ?? null;
            $cg_data['tipo_venta']        = $dataIn['venta_permuta'] ?? null;
            $cg_data['precio_pretendido'] = $dataIn['valor_pretendido'] ?? null;
            $cg_data['marca']             = $brandTxt;
            $cg_data['anio']              = $anioIn;
            $cg_data['familia']           = $modeloTxt;
            $cg_data['auto']              = $dataIn['nombre_auto'] ?? trim($brandTxt . ' ' . $modeloTxt);

            $cg_data['valor_minimo']   = $min;
            $cg_data['valor_maximo']   = $max;
            $cg_data['valor_promedio'] = $avg;

            $cg_data['valor_minimo_autodata']   = null;
            $cg_data['valor_maximo_autodata']   = null;
            $cg_data['valor_promedio_autodata'] = null;

            $cg_data['datos'] = json_encode([
                'corrida_id'          => $corridaId,
                'brand_id'            => $brandId,
                'model_id'            => $modelId,
                'brand_final'         => $brandTxt,
                'modelo_final'        => $modeloTxt,
                'anio'                => $anioIn,
                'version'             => $ver,
                'publicaciones_total' => count($publicaciones),
                'items_ok'            => count($prices),
                'fuente'              => 'apify_publicaciones'
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
        } else {
            $this->logInterno('ITEMS_PERSIST_SKIP_NO_ID', [
                'items_count' => count($itemsParaPersistir),
                'corrida_id'  => $corridaId
            ]);
        }

        return [
            'msg' => 'OK',
            'resultado' => $resultado,
            'id_cotizacion' => $id
        ];
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
                if ($h === '') continue;
                $allFound = true;
                foreach ($tokens as $tk) {
                    if (strlen($tk) < 2) continue;
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

    private function toIntOrNull($v): ?int
    {
        if ($v === null || $v === '') return null;
        if (is_int($v)) return $v;

        $s = trim((string)$v);
        if ($s === '') return null;

        $s = preg_replace('/[^\d\-]/', '', $s);
        if ($s === '' || $s === '-') return null;

        return (int)$s;
    }

    private function toFloatOrNull($v): ?float
    {
        if ($v === null || $v === '') return null;
        if (is_float($v) || is_int($v)) return (float)$v;

        $s = trim((string)$v);
        if ($s === '') return null;

        $s = preg_replace('/[^0-9,\.\-]/', '', $s);
        if ($s === '' || $s === '-' || $s === '.' || $s === ',') return null;

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
            WHERE (
                id_model = " . (int)$modelId . "
                OR id_modelo = " . (int)$modelId . "
                OR id = " . (int)$modelId . "
                OR modelo_id = " . (int)$modelId . "
            )
            LIMIT 1
        ");

        if (empty($brandRows)) {
            throw new \Exception("No se pudo resolver nombre de marca local (ID {$brandId})");
        }

        if (empty($modelRows)) {
            throw new \Exception("No se pudo resolver nombre de modelo local (ID {$modelId})");
        }

        $brandRow = $brandRows[0];
        $modelRow = $modelRows[0];

        $brandName = $this->pickArr($brandRow, ['nombre','name','marca']);
        $modelName = $this->pickArr($modelRow, ['nombre','name','modelo']);

        if (!$brandName) {
            throw new \Exception("La marca local no tiene nombre resoluble (ID {$brandId})");
        }

        if (!$modelName) {
            throw new \Exception("El modelo local no tiene nombre resoluble (ID {$modelId})");
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

    private function makeRunId(): string
    {
        try {
            if (function_exists('random_bytes')) {
                return 'local_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
            }
        } catch (\Throwable $e) {}

        if (function_exists('openssl_random_pseudo_bytes')) {
            return 'local_' . date('Ymd_His') . '_' . bin2hex(openssl_random_pseudo_bytes(4));
        }

        return 'local_' . date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 8);
    }
}