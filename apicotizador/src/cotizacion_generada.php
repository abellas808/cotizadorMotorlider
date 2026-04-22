<?php

class CotizacionGenerada implements JsonSerializable
{
    public $id_cotizaciones_generadas;

    public $nombre;
    public $email;
    public $telefono;
    public $ci;
    public $fecha;
    public $kilometros;
    public $ficha_tecnica;
    public $duenios;
    public $tipo_venta;
    public $precio_pretendido;
    public $marca;
    public $anio;
    public $familia;
    public $datos;
    public $respuesta;
    public $auto;

    public $valor_minimo;
    public $valor_maximo;
    public $valor_promedio;

    public $valor_minimo_autodata;
    public $valor_maximo_autodata;
    public $valor_promedio_autodata;

    public $msg;
    public $porcentajes_aplicados;
    public $cuenta;

    public $estado;
    public $detalle_estado;
    public $mail_enviado;
    public $fecha_mail;
    public $cotizado_exitoso; // Variable agregada
    public $estado_id; // Variable agregada


    public function __construct(array $parametros)
    {
        foreach ($parametros as $key => $val) {
            if (property_exists($this, $key)) {
                $this->$key = $val;
            }
        }
    }

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    public static function get($id)
    {
        $row = Database::getInstance()->mysqlQuery(
            'SELECT * FROM marcos2022_api.cotizaciones_generadas WHERE id_cotizaciones_generadas = ?',
            [$id],
            true
        );

        return $row ? new self($row) : null;
    }

    public function save()
    {
        $parametros = get_object_vars($this);

        unset($parametros['id_cotizaciones_generadas']);

        // Normalizar nulls
        foreach ($parametros as $k => $v) {
            if ($v === null) {
                $parametros[$k] = '';
            }
        }

        if (($parametros['fecha'] ?? '') === '') {
            $parametros['fecha'] = date('Y-m-d');
        }
        
        // Armar placeholders
        $parametros_sql = [];
        foreach ($parametros as $k => $v) {
            $parametros_sql[":" . $k] = $v;
        }

        $sql = 'INSERT INTO marcos2022_api.cotizaciones_generadas SET
            nombre = :nombre,
            email = :email,
            telefono = :telefono,
            ci = :ci,
            fecha = :fecha,
            kilometros = :kilometros,
            ficha_tecnica = :ficha_tecnica,
            duenios = :duenios,
            tipo_venta = :tipo_venta,
            precio_pretendido = :precio_pretendido,
            marca = :marca,
            anio = :anio,
            familia = :familia,
            datos = :datos,
            respuesta = :respuesta,
            auto = :auto,
            valor_minimo = :valor_minimo,
            valor_maximo = :valor_maximo,
            valor_promedio = :valor_promedio,
            valor_minimo_autodata = :valor_minimo_autodata,
            valor_maximo_autodata = :valor_maximo_autodata,
            valor_promedio_autodata = :valor_promedio_autodata,
            msg = :msg,
            porcentajes_aplicados = :porcentajes_aplicados,
            cuenta = :cuenta,
            estado = :estado,
            detalle_estado = :detalle_estado,
            mail_enviado = :mail_enviado,
            fecha_mail = :fecha_mail,
            cotizado_exitoso = :cotizado_exitoso,
            estado_id = :estado_id '; // Agregado al INSERT

        $id = Database::getInstance()->mysqlNonQuery($sql, $parametros_sql);

        return self::get($id);
    }

    /**
     * Método para actualizar registros existentes (útil para el método actualizarEstadoCotizacion)
     */
    public function update()
    {
        if (!$this->id_cotizaciones_generadas) return false;

        $parametros = get_object_vars($this);
        $id = $this->id_cotizaciones_generadas;
        unset($parametros['id_cotizaciones_generadas']);

        $set_parts = [];
        $parametros_sql = [':id' => $id];

        foreach ($parametros as $k => $v) {
            $set_parts[] = "$k = :$k";
            $parametros_sql[":$k"] = ($v === null) ? '' : $v;
        }

        $sql = 'UPDATE marcos2022_api.cotizaciones_generadas SET ' . implode(', ', $set_parts) . ' 
                WHERE id_cotizaciones_generadas = :id LIMIT 1';

        return Database::getInstance()->mysqlNonQuery($sql, $parametros_sql);
    }
}