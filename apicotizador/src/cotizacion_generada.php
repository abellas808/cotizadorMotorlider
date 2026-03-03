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

    public function __construct(array $parametros)
    {
        foreach ($parametros as $key => $val) {
            $this->$key = $val;
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

        return new self($row);
    }

    public function save()
    {
        // Tomo todas las props del objeto
        $parametros = get_object_vars($this);

        // IMPORTANTÍSIMO: el ID es autoincremental, no se inserta ni se envía como parámetro
        unset($parametros['id_cotizaciones_generadas']);

        // Normalizar nulls (tu tabla es NOT NULL en casi todo)
        foreach ($parametros as $k => $v) {
            if ($v === null) {
                $parametros[$k] = '';
            }
        }

        // Defaults mínimos útiles
        if (($parametros['fecha'] ?? '') === '') {
            // fecha es DATE en tu tabla
            $parametros['fecha'] = date('Y-m-d');
        }
        if (!isset($parametros['anio']) || $parametros['anio'] === '') {
            $parametros['anio'] = 0;
        }

        // Armar placeholders SOLO de estos campos (que coinciden con el SQL)
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
            cuenta = :cuenta';

        $id = Database::getInstance()->mysqlNonQuery($sql, $parametros_sql);

        return self::get($id);
    }
}
