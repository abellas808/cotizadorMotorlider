<?php

class Database
{
    private $PDO;
    private static $__instance = null;

    private static $host  = 'localhost';
    private static $user  = 'marcos2022_usr_api';
    private static $pass  = '_eT4AjJ79~tX]*h)J5';
    private static $dbase = 'marcos2022_api_cotizador';

    private function __construct()
    {
        $dsn = 'mysql:host=' . self::$host . ';dbname=' . self::$dbase . ';charset=utf8mb4';

        $this->PDO = new PDO($dsn, self::$user, self::$pass);
        $this->PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->PDO->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    public static function getInstance()
    {
        if (is_null(self::$__instance)) {
            self::$__instance = new self();
        }

        return self::$__instance;
    }

    public function mysqlQuery($query, $parametros = array(), $isSingle = false)
    {
        $query = trim($query);

        if (substr(strtoupper($query), 0, 6) !== 'SELECT') {
            throw new Exception('Bad Action');
        }

        if (empty($parametros)) {
            $stmt = $this->PDO->query($query);
        } else {
            $stmt = $this->PDO->prepare($query);
            $stmt->execute($parametros);
        }

        if ($isSingle) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function mysqlNonQuery($query, $parametros = array())
    {
        $query = trim($query);
        $action = substr(strtoupper($query), 0, 6);

        if (!in_array($action, ['INSERT', 'UPDATE', 'DELETE'])) {
            throw new Exception('Bad Action');
        }

        $stmt = $this->PDO->prepare($query);
        $stmt->execute($parametros);

        if ($action === 'INSERT') {
            return $this->PDO->lastInsertId();
        }

        return $stmt->rowCount();
    }

    public function mysqlCountQuery($query, $parametros = array())
    {
        $query = trim($query);

        if (substr(strtoupper($query), 0, 6) !== 'SELECT') {
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