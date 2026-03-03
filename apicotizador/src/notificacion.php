<?php

class Notificacion implements JsonSerializable{

    public $id;
    public $fecha;
    public $titulo;
    public $contenido;
    public $tipo;
    public $referencia;
    public $url;
    public $info_extra;
	public $estado;
    
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

    
    /* obtiene los datos de una notificación a partir de la ID -> return objeto con los datos del registro obtenidos de la base de datos */
    public static function get($id){
		$notificacion = Database::getInstance()->mysqlQuery(
            'SELECT * FROM api_notificaciones WHERE ID = ?'
            , array($id), true);

		return new self($notificacion);
    }

    /* añade un nuevo registro en la tabla logs -> return created object */
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

        if (!key_exists('info_extra', $parametros_sql)){
            $parametros_sql['info_extra'] = "";
        }

        $id = Database::getInstance()->mysqlNonQuery('INSERT INTO api_notificaciones SET    titulo = :titulo,
                                                                                            contenido = :contenido,
                                                                                            tipo = :tipo,
                                                                                            referencia = :referencia,
                                                                                            url = :url,
                                                                                            info_extra = :info_extra,
                                                                                            estado = :estado', $parametros_sql);

        return self::get($id);
    }
}