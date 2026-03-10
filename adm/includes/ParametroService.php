<?php

require_once __DIR__ . '/../../apicotizador/src/db.php';

class ParametroService
{
    private const DB = 'marcos2022_api';

    private static $cache = [];

    public static function get(string $grupo, string $clave, $default = null)
    {
        $key = $grupo . '.' . $clave;

        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        try {
            $db = Database::getInstance();

            $sql = "
                SELECT valor
                FROM " . self::DB . ".parametros_sistema
                WHERE grupo = :grupo
                  AND clave = :clave
                  AND activo = 1
                LIMIT 1
            ";

            $row = $db->mysqlQuery($sql, [
                ':grupo' => $grupo,
                ':clave' => $clave
            ], true);

            if (!$row || !isset($row['valor'])) {
                self::$cache[$key] = $default;
                return $default;
            }

            self::$cache[$key] = $row['valor'];
            return $row['valor'];

        } catch (\Throwable $e) {
            return $default;
        }
    }

    public static function getBool(string $grupo, string $clave, bool $default = false): bool
    {
        $val = self::get($grupo, $clave, $default ? '1' : '0');

        return in_array(strtolower((string)$val), ['1', 'true', 'yes', 'si', 'on'], true);
    }

    public static function getInt(string $grupo, string $clave, int $default = 0): int
    {
        $val = self::get($grupo, $clave, $default);
        return (int)$val;
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }
}