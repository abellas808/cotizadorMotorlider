<?php

require_once __DIR__ . '/MeliScraperService.php';
require_once __DIR__ . '/../src/log.php';

class ApiCotizadorApify
{
    private $scraper;

    public function __construct()
    {
        $this->scraper = new MeliScraperService();
    }

    private function logInterno(string $tag, array $payload = []): void
    {
        try {
            $log_data = [];
            $log_data['token'] = '';
            $log_data['ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
            $log_data['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $log_data['request_method'] = 'INTERNAL';
            $log_data['request_uri'] = 'ApiCotizadorApify';
            $log_data['request_header'] = '';
            $log_data['request_vars'] = '';
            $log_data['request_body'] = json_encode(['tag' => $tag, 'payload' => $payload], JSON_UNESCAPED_UNICODE);
            $log_data['response_statuscode'] = 0;
            $log_data['response_header'] = '';
            $log_data['response_body'] = '';
            $log = new Log($log_data);
            $log->save();
        } catch (\Throwable $e) {
            // no romper
        }
    }

    private function getToken(): string
    {
        $token = getenv('APIFY_TOKEN');
        if (!$token) $token = $_ENV['APIFY_TOKEN'] ?? null;
        if (!$token) $token = $_SERVER['APIFY_TOKEN'] ?? null;
        return trim((string)$token);
    }

    /**
     * Slug safe para MercadoLibre URL paths
     * - baja a minúsculas
     * - reemplaza espacios por guiones
     * - elimina caracteres raros
     */
    private function slugify(string $txt): string
    {
        $txt = trim($txt);
        $txt = strtolower($txt);

        // quitar acentos (si está disponible iconv)
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
        if ($converted !== false) {
            $txt = $converted;
        }

        $txt = preg_replace('/\s+/', '-', $txt);
        $txt = preg_replace('/[^a-z0-9\-]/', '', $txt);
        $txt = preg_replace('/-+/', '-', $txt);
        $txt = trim($txt, '-');

        return $txt;
    }

    /**
     * ✅ NUEVO: arma URL de AUTOS (no listado genérico)
     * https://autos.mercadolibre.com.uy/{marca}/{modelo}/
     *
     * Importante: si te llega marca/modelo numéricos, acá NO adivinamos:
     * devolvemos igual la url "numérica" pero lo logueamos para detectar
     * y en CotizacionService hay que resolver a texto antes de llamar.
     */
    public function buildInputFromCotizador($marca, $modelo)
    {
        $marca  = trim((string)$marca);
        $modelo = trim((string)$modelo);

        $slugMarca  = $this->slugify($marca);
        $slugModelo = $this->slugify($modelo);

        // si vino numérico, lo dejamos visible en logs para arreglarlo arriba (CotizacionService)
        if ($slugMarca !== '' && ctype_digit($slugMarca)) {
            $this->logInterno('WARN_NUMERIC_BRAND', ['marca_in' => $marca, 'slugMarca' => $slugMarca]);
        }
        if ($slugModelo !== '' && ctype_digit($slugModelo)) {
            $this->logInterno('WARN_NUMERIC_MODEL', ['modelo_in' => $modelo, 'slugModelo' => $slugModelo]);
        }

        // URL de autos (NUEVA)
        $url = "https://autos.mercadolibre.com.uy/{$slugMarca}/{$slugModelo}/";

        return array(
            "country_code" => "uy",
            "ignore_url_failures" => true,
            "max_items_per_url" => 50,
            "max_retries_per_url" => 2,
            "proxy" => array(
                "useApifyProxy" => true,
                "apifyProxyGroups" => array("RESIDENTIAL"),
                "apifyProxyCountry" => "US"
            ),
            "urls" => array($url)
        );
    }

    public function run(array $input, int $limit = 50): array
    {
        $token = $this->getToken();
        if ($token === '') {
            $this->logInterno('RUN_FAIL_NO_TOKEN');
            return ['ok' => false, 'error' => 'Falta APIFY_TOKEN en .env', 'items' => []];
        }

        $this->logInterno('RUN_START', [
            'actor_id' => getenv('APIFY_ACTOR_ID') ?: null,
            'urls' => $input['urls'] ?? null,
            'max_items_per_url' => $input['max_items_per_url'] ?? null
        ]);

        $res = $this->scraper->runMercadoLibreSearch($input);

        if (is_object($res)) $res = json_decode(json_encode($res), true);
        if (!is_array($res)) {
            $this->logInterno('RUN_BAD_RESPONSE_TYPE', ['type' => gettype($res)]);
            return ['ok' => false, 'error' => 'Respuesta inválida del scraper', 'items' => []];
        }

        if (isset($res['items']) && is_array($res['items'])) {
            $this->logInterno('RUN_OK_ITEMS', ['items_count' => count($res['items'])]);
            return ['ok' => true, 'items' => $res['items']];
        }

        $dataNode = $res['data'] ?? $res;

        $datasetId = $dataNode['defaultDatasetId']
            ?? $dataNode['datasetId']
            ?? $res['defaultDatasetId']
            ?? $res['datasetId']
            ?? null;

        if ($datasetId) {
            $this->logInterno('RUN_DATASET_FETCH', ['datasetId' => $datasetId, 'limit' => $limit]);

            $itemsRes = $this->getDatasetItems($datasetId, $limit);
            if (!($itemsRes['ok'] ?? false)) {
                $this->logInterno('RUN_DATASET_FETCH_FAIL', ['datasetId' => $datasetId, 'error' => $itemsRes['error'] ?? null]);
                return ['ok' => false, 'error' => $itemsRes['error'] ?? 'Error obteniendo dataset', 'items' => []];
            }

            $items = $itemsRes['items'] ?? [];
            $this->logInterno('RUN_OK_DATASET', ['items_count' => is_array($items) ? count($items) : 0]);

            return ['ok' => true, 'items' => is_array($items) ? $items : []];
        }

        $this->logInterno('RUN_NO_ITEMS_NO_DATASET', ['keys' => array_keys($res)]);
        return ['ok' => false, 'error' => 'El scraper no devolvió items ni datasetId', 'items' => []];
    }

    public function testRun($marca, $modelo)
    {
        $input = $this->buildInputFromCotizador($marca, $modelo);
        return $this->run($input, 50);
    }

    public function getDatasetItems($datasetId, $limit = 20)
    {
        $token = $this->getToken();
        if ($token === '') {
            return ['ok' => false, 'error' => 'Falta APIFY_TOKEN en .env'];
        }

        $url = "https://api.apify.com/v2/datasets/{$datasetId}/items?token={$token}&clean=true&limit={$limit}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['ok' => false, 'error' => $err];
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            return ['ok' => false, 'error' => "HTTP {$httpCode} al obtener dataset items"];
        }

        $items = json_decode($result, true);
        if (!is_array($items)) {
            return ['ok' => false, 'error' => 'Dataset items devolvió JSON inválido'];
        }

        return ['ok' => true, 'items' => $items];
    }
}