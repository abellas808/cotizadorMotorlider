<?php

class Log implements JsonSerializable{

    public $id;
    public $timestamp;
    public $token;
    public $ip;
    public $user_agent;
    public $request_method;
    public $request_uri;
	public $request_header;
	public $request_vars;
	public $request_body;
	public $response_statuscode;
	public $response_header;
	public $response_body;
    
    /* Constructor de clase */
    public function __construct($parametros){
        foreach($parametros as $key => $val){
            $this->$key = $val;
        }
    }

	/* serializador de json */
    public function jsonSerialize(){
        return get_object_vars($this);
    }
    
    /* obtiene los datos de un log a partir de su ID -> return objeto con los datos del log obtenidos de la base de datos */
    public static function get($id){
		$notificacion = Database::getInstance()->mysqlQuery(
            'SELECT * FROM api_logs WHERE ID = ?'
            , array($id), true);

		return new self($notificacion);
    }
    
    /* añade un nuevo registro en la tabla logs ->  return created object */
    public function save(){
        $parametros = get_object_vars($this);
        foreach($parametros as $k => $v){
            if(is_null($v)){
                unset($parametros[$k]);
            }
        }

        $parametros_sql = array_combine(array_map(function($k){
            return ":" . $k;
        }, array_keys($parametros)), array_values($parametros));

        $id = Database::getInstance()->mysqlNonQuery('INSERT INTO api_logs SET  token = :token,
                                                                                ip = :ip,
                                                                                user_agent = :user_agent,
                                                                                request_method = :request_method,
                                                                                request_uri = :request_uri,
                                                                                request_header = :request_header,
                                                                                request_vars = :request_vars,
                                                                                request_body = :request_body,
                                                                                response_statuscode = :response_statuscode,
                                                                                response_header = :response_header,
                                                                                response_body= :response_body', $parametros_sql);

        return self::get($id);
    }
    
}