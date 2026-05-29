<?php

/**
 * Detecta conversaciones abandonadas ANTES de generar cotización.
 *
 * Debe registrar solo chats donde el cliente abandonó en pasos previos:
 * inicio, marca, modelo, año, versión, ficha, kilómetros, tipo venta, valor pretendido.
 *
 * NO debe registrar:
 * - conversaciones que ya tienen cotización
 * - mensajes enviados desde cot/v
 * - casos PENDIENTE_RESPUESTA_HUMANA
 * - casos donde ya se generó una cotización
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
ini_set('display_errors', 0);

ob_start();

require_once(__DIR__ . '/../../config/config.inc.php');

if (!isset($config['db_tablePrefix'])) {
    $config['db_tablePrefix'] = '';
}

require_once(__DIR__ . '/../../includes/database.php');
require_once(__DIR__ . '/../../includes/funciones.php');

date_default_timezone_set('America/Montevideo');

global $db;

echo "<pre>";
echo "=====================================\n";
echo " DETECTOR CONVERSACIONES ABANDONADAS\n";
echo "=====================================\n\n";

$HORAS_ESPERA = 24;

/**
 * SOLO estos pasos pueden ir a conversaciones perdidas.
 * Todo lo demás queda afuera.
 */
$stepsAbandono = [
    'inicio',
    'marca',
    'esperando_marca',
    'modelo',
    'esperando_modelo',
    'modelo_sugerido',
    'anio',
    'año',
    'esperando_anio',
    'esperando_año',
    'version',
    'versión',
    'esperando_version',
    'esperando_versión',
    'ficha_tecnica',
    'ficha',
    'esperando_ficha_tecnica',
    'kilometros',
    'kilómetros',
    'esperando_kilometros',
    'esperando_kilómetros',
    'duenios',
    'dueños',
    'tipo_venta',
    'esperando_tipo_venta',
    'precio_pretendido',
    'valor_pretendido',
    'pretendido',
    'esperando_precio_pretendido',
    'esperando_valor_pretendido'
];

$stepsSql = [];
foreach ($stepsAbandono as $s) {
    $stepsSql[] = "'" . $db->escape($s) . "'";
}

/**
 * Query:
 * 1) Último mensaje real del teléfono debe ser SALIENTE del bot.
 * 2) Deben haber pasado X horas.
 * 3) El step debe estar dentro de los pasos previos a cotizar.
 * 4) No debe tener id_cotizacion.
 * 5) No debe existir cotización generada para ese teléfono después del inicio de la conversación.
 * 6) No debe existir ya como pendiente/en gestión en conversaciones abandonadas.
 */
$sql = "
    SELECT
        wc.*,

        LOWER(TRIM(
            COALESCE(
                NULLIF(wc.step_actual, ''),
                JSON_UNQUOTE(JSON_EXTRACT(wc.datos_json, '$.step')),
                ''
            )
        )) AS step_real,

        ult.fecha AS fecha_ultimo_mensaje_real,
        ult.mensaje AS ultimo_mensaje_real,
        ult.direccion AS direccion_ultimo_mensaje,
        ult.emisor AS emisor_ultimo_mensaje

    FROM whatsapp_conversaciones wc

    INNER JOIN (
        SELECT m1.*
        FROM whatsapp_conversacion_mensajes m1
        INNER JOIN (
            SELECT 
                telefono,
                MAX(id) AS max_id
            FROM whatsapp_conversacion_mensajes
            GROUP BY telefono
        ) x
            ON x.telefono = m1.telefono
           AND x.max_id = m1.id
    ) ult
        ON ult.telefono = wc.telefono

    WHERE (wc.id_cotizacion IS NULL OR wc.id_cotizacion = 0)

      AND ult.direccion = 'SALIENTE'

      AND ult.fecha <= DATE_SUB(NOW(), INTERVAL " . intval($HORAS_ESPERA) . " HOUR)

      AND LOWER(TRIM(
            COALESCE(
                NULLIF(wc.step_actual, ''),
                JSON_UNQUOTE(JSON_EXTRACT(wc.datos_json, '$.step')),
                ''
            )
          )) IN (" . implode(',', $stepsSql) . ")

      AND NOT EXISTS (
          SELECT 1
          FROM cotizaciones_generadas cg
          WHERE cg.telefono = wc.telefono
            AND cg.fecha >= DATE(wc.fecha_alta)
      )

      AND NOT EXISTS (
          SELECT 1
          FROM cotizador_conversaciones_abandonadas cca
          WHERE cca.id_conversacion = wc.id
            AND cca.estado IN ('PENDIENTE', 'EN_GESTION')
      )

    ORDER BY ult.fecha ASC
";

$res = $db->query($sql);

if (!$res) {
    echo "ERROR consultando conversaciones.\n";
    echo "</pre>";
    exit;
}

$totalInsertados = 0;

while ($conv = $db->fetch_array($res)) {

    $datos = [];

    if (!empty($conv['datos_json'])) {
        $tmp = json_decode($conv['datos_json'], true);
        if (is_array($tmp)) {
            $datos = $tmp;
        }
    }

    $marca = trim((string)($datos['marca'] ?? ''));
    $modelo = trim((string)($datos['modelo'] ?? ''));
    $anio = intval($datos['anio'] ?? 0);
    $kilometros = intval($datos['kilometros'] ?? 0);
    $fichaTecnica = trim((string)($datos['ficha_tecnica'] ?? ''));
    $duenios = intval($datos['duenios'] ?? 0);
    $tipoVenta = trim((string)($datos['tipo_venta'] ?? ''));

    echo "Conversación candidata: " . intval($conv['id']) .
         " | Step real: " . $conv['step_real'] .
         " | Último mensaje BOT: " . $conv['ultimo_mensaje_real'] .
         " | Fecha: " . $conv['fecha_ultimo_mensaje_real'] . "\n";

    $sqlInsert = "
        INSERT INTO cotizador_conversaciones_abandonadas
        (
            id_conversacion,
            telefono,
            nombre,
            email,

            estado_conversacion,
            step_actual,
            sub_step_actual,

            ultimo_mensaje_cliente,
            ultimo_payload,
            ultimo_tipo_input,
            ultima_respuesta_bot,

            marca,
            modelo,
            anio,
            kilometros,
            ficha_tecnica,
            duenios,
            tipo_venta,

            datos_json,

            motivo_abandono,
            origen_abandono,

            fecha_inicio,
            fecha_ultima_interaccion,
            fecha_detectado,

            estado
        )
        VALUES
        (
            " . intval($conv['id']) . ",
            '" . $db->escape($conv['telefono']) . "',
            '" . $db->escape($conv['nombre']) . "',
            '" . $db->escape($conv['email']) . "',

            '" . $db->escape($conv['estado']) . "',
            '" . $db->escape($conv['step_real']) . "',
            '" . $db->escape($conv['sub_step_actual']) . "',

            '" . $db->escape($conv['ultimo_mensaje_cliente']) . "',
            '" . $db->escape($conv['ultimo_payload']) . "',
            '" . $db->escape($conv['ultimo_tipo_input']) . "',
            '" . $db->escape($conv['ultima_respuesta_bot']) . "',

            '" . $db->escape($marca) . "',
            '" . $db->escape($modelo) . "',
            " . intval($anio) . ",
            " . intval($kilometros) . ",
            '" . $db->escape($fichaTecnica) . "',
            " . intval($duenios) . ",
            '" . $db->escape($tipoVenta) . "',

            '" . $db->escape($conv['datos_json']) . "',

            'NO_COMPLETA_COTIZACION',
            'WHATSAPP_BOT',

            '" . $db->escape($conv['fecha_alta']) . "',
            '" . $db->escape($conv['fecha_ultimo_mensaje_real']) . "',
            NOW(),

            'PENDIENTE'
        )
    ";

    $ok = $db->query($sqlInsert);

    if ($ok) {
        $totalInsertados++;
        echo "OK INSERT conversación abandonada ID conversación: " . intval($conv['id']) . "\n";
    } else {
        echo "ERROR insertando conversación ID: " . intval($conv['id']) . "\n";
    }
}

echo "\n=====================================\n";
echo "TOTAL INSERTADOS: {$totalInsertados}\n";
echo "\n=====================================\n";
echo "</pre>";