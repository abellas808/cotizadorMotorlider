<?php

class MeliScraperService
{
    private $actorId;
    private $token;

    public function __construct($actorId = null, $token = null)
    {
        // NO tirar exception acá, para que no rompa al incluir.
        $this->actorId = $actorId;
        $this->token   = $token;
    }

   private function ensureConfig()
    {
        if (!$this->token) {
            $this->token = getenv('APIFY_TOKEN') ?: ($_ENV['APIFY_TOKEN'] ?? ($_SERVER['APIFY_TOKEN'] ?? null));
        }
        if (!$this->actorId) {
            $this->actorId = getenv('APIFY_ACTOR_ID') ?: ($_ENV['APIFY_ACTOR_ID'] ?? ($_SERVER['APIFY_ACTOR_ID'] ?? null));
        }

        if (!$this->token) {
            throw new Exception("Falta APIFY_TOKEN en .env");
        }
        if (!$this->actorId) {
            throw new Exception("Falta APIFY_ACTOR_ID en .env");
        }
    }


    private function normalizeActorId($actorId)
    {
        // Apify espera: username~actorName (no con /)
        // Si te llega "ecomscrape/mercadolibre-product-search-scraper" -> lo pasamos a "ecomscrape~mercadolibre-product-search-scraper"
        $actorId = trim((string)$actorId);
        if (strpos($actorId, '/') !== false) {
            $actorId = str_replace('/', '~', $actorId);
        }
        return $actorId;
    }

    public function runMercadoLibreSearch($input)
    {
        $this->ensureConfig();

        $actor = $this->normalizeActorId($this->actorId);

        $url = "https://api.apify.com/v2/acts/" . rawurlencode($actor) . "/runs?waitForFinish=120&token=" . rawurlencode($this->token);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($input));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 140); // waitForFinish=120, dejá margen


        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $raw === null) {
            throw new Exception("cURL error Apify: " . $err);
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            throw new Exception("Apify devolvió no-JSON. HTTP=$http RAW=" . substr($raw, 0, 300));
        }

        if ($http < 200 || $http >= 300) {
            $msg = isset($json['error']['message']) ? $json['error']['message'] : 'HTTP ' . $http;
            throw new Exception("Apify error: " . $msg);
        }

        return $json;
    }
}
