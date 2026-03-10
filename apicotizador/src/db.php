<?php
class Database
{
    private $pdo;
    private static $__instance;
    private static $host = 'localhost';
    private static $user = 'marcos2022_usr_api';
    private static $pass = '_eT4AjJ79~tX]*h)J5';
    private static $dbase = 'marcos2022_api_cotizador';
    /* Constructor de clase */
    private function __construct()
    {
        $db = new PDO('mysql:host=' . $this::$host . ';dbname=' . $this::$dbase . ';charset=utf8mb4', $this::$user, $this::$pass);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $this->PDO = $db;
    }
    /* Metodo público para tener una instancia de conexión, si no existe la crea */
    public static function getInstance()
    {
        if (is_null(self::$__instance)) {
            self::$__instance = new self();
        }
        return self::$__instance;
    }
    /* Ejecuta las queries SELECT y devuelve los resultados en array assoc */
    public function mysqlQuery($query, $parametros = array(), $isSingle = false)
    {
        if (!in_array(substr(strtoupper($query), 0, 6), ['SELECT'])) {
            throw new Exception('Bad Action');
        }
        if (empty($parametros)) {
            $stmt = $this->PDO->query($query);
        } else {
            $stmt = $this->PDO->prepare($query);
            $stmt->execute($parametros);
        }
        if ($isSingle) {
            $return = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $return = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $return;
    }
    /* Ejecuta las queries no SELECT */
    public function mysqlNonQuery($query, $parametros = array())
    {
        if (!in_array(substr(strtoupper($query), 0, 6), ['INSERT', 'UPDATE', 'DELETE'])) {
            throw new Exception('Bad Action');
        }
        $stmt = $this->PDO->prepare($query);
        $stmt->execute($parametros);
        return (substr(strtoupper($query), 0, 6) == "INSERT") ? $this->PDO->lastInsertId() : $stmt->rowCount();
    }
    /* QueryCount */
    public function mysqlCountQuery($query, $parametros)
    {
        if (!in_array(substr(strtoupper($query), 0, 6), ['SELECT'])) {
            throw new Exception('Bad Action');
        }
        if (empty($parametros)) {
            $stmt = $this->PDO->query($query);
        } else {
            $stmt = $this->PDO->prepare($query);
            $stmt->execute($parametros);
        }
        return $stmt->rowCount();
    }
}