<?php

/**
 * Detecta conversaciones abandonadas ANTES de generar cotización.
 *
 * Debe registrar solo chats donde el cliente abandonó en pasos previos:
 * inicio, marca, modelo, año, versión, ficha, kilómetros, tipo venta, valor pretendido.
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

function obtener_parametro_sistema($grupo, $clave, $default)
{
    global $db;

    $row = $db->query_first("
        SELECT valor
        FROM parametros_sistema
        WHERE grupo = '" . $db->escape($grupo) . "'
          AND clave = '" . $db->escape($clave) . "'
          AND activo = 1
        LIMIT 1
    ");

    if (!$row || trim((string)$row['valor']) === '') {
        return $default;
    }

    return $row['valor'];
}

echo "<pre>";
echo "=====================================\n";
echo " DETECTOR CONVERSACIONES ABANDONADAS\n";
echo "=====================================\n\n";

$HORAS_ESPERA = intval(obtener_parametro_sistema(
    'whatsapp',
    'horas_conversacion_abandonada',
    12
));

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
 * IMPORTANTE:
 * Primero se toma datos_json.step.
 * Si no existe, recién ahí se usa wc.step_actual.
 *
 * Esto evita que step_actual = resultado_enviado tape el step real del flujo,
 * por ejemplo datos_json = {"step":"marca"}.
 */
$stepRealSql = "
    LOWER(TRIM(
        COALESCE(
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(wc.datos_json, '$.step')), ''),
            NULLIF(wc.step_actual, ''),
            ''
        )
    ))
";

$sql = "
    SELECT
        wc.*,

        {$stepRealSql} AS step_real,

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
                id_conversacion,
                MAX(id) AS max_id
            FROM whatsapp_conversacion_mensajes
            GROUP BY id_conversacion
        ) x
            ON x.id_conversacion = m1.id_conversacion
        AND x.max_id = m1.id
    ) ult
        ON ult.id_conversacion = wc.id

    WHERE ult.direccion = 'SALIENTE'

      AND ult.fecha <= DATE_SUB(NOW(), INTERVAL " . intval($HORAS_ESPERA) . " HOUR)

      AND {$stepRealSql} IN (" . implode(',', $stepsSql) . ")

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

        $idCotizacion = intval($conv['id_cotizacion'] ?? 0);

        if ($idCotizacion > 0) {

            $agenda = $db->query_first("
                SELECT id_agenda
                FROM agendas
                WHERE id_cotizacion = '" . intval($idCotizacion) . "'
                AND cancelado = 0
                AND finalizada = 0
                ORDER BY id_agenda DESC
                LIMIT 1
            ");

            $idAgenda = intval($agenda['id_agenda'] ?? 0);

            if ($idAgenda > 0) {

                wa_cancelar_agenda_automatica($idAgenda);

                wa_marcar_cotizacion_preliminar_por_cancelacion_agenda($idCotizacion);

                echo "Agenda cancelada automáticamente: {$idAgenda}\n";
                echo "Cotización vuelta a preliminar: {$idCotizacion}\n";

            } else {
                echo "No se encontró agenda activa para cotización: {$idCotizacion}\n";
            }

        } else {
            echo "Conversación sin id_cotizacion asociado, no se cancela agenda.\n";
        }

    } else {
        echo "ERROR insertando conversación ID: " . intval($conv['id']) . "\n";
    }
}

function wa_marcar_cotizacion_preliminar_por_cancelacion_agenda(int $idCotizacion): bool
{
    if ($idCotizacion <= 0) {
        return false;
    }

    $cn = wa_db();

    $estadoId = 3;
    $estado = 'COTIZADO_PRELIMINAR';
    $detalle = 'Agenda cancelada automáticamente por falta de respuesta al recordatorio.';

    $sql = "
        UPDATE cotizaciones_generadas
        SET estado_id = ?,
            estado = ?,
            detalle_estado = ?,
            fecha_mod = NOW()
        WHERE id_cotizaciones_generadas = ?
        LIMIT 1
    ";

    $st = $cn->prepare($sql);

    if (!$st) {
        wa_log('COTIZACION_PRELIMINAR_PREPARE_ERROR', [
            'id_cotizacion' => $idCotizacion,
            'error' => $cn->error
        ]);

        $cn->close();
        return false;
    }

    $st->bind_param('issi', $estadoId, $estado, $detalle, $idCotizacion);
    $ok = $st->execute();

    wa_log('COTIZACION_PRELIMINAR_UPDATE', [
        'id_cotizacion' => $idCotizacion,
        'ok' => $ok,
        'affected_rows' => $st->affected_rows,
        'error' => $st->error
    ]);

    $st->close();
    $cn->close();

    return $ok;
}

function wa_cancelar_agenda_automatica(int $idAgenda): bool
{
    if ($idAgenda <= 0) {
        return false;
    }

    $cn = wa_db();

    $sql = "
        UPDATE agendas
        SET cancelado = 1,
            motivo_cancelacion = 'Cancelada automáticamente por falta de respuesta al recordatorio de agenda',
            fecha_cancelacion = NOW()
        WHERE id_agenda = ?
          AND cancelado = 0
          AND finalizada = 0
        LIMIT 1
    ";

    $st = $cn->prepare($sql);

    if (!$st) {
        wa_log('AGENDA_CANCEL_AUTO_PREPARE_ERROR', [
            'id_agenda' => $idAgenda,
            'error' => $cn->error
        ]);

        $cn->close();
        return false;
    }

    $st->bind_param('i', $idAgenda);
    $ok = $st->execute();

    wa_log('AGENDA_CANCEL_AUTO_UPDATE', [
        'id_agenda' => $idAgenda,
        'ok' => $ok,
        'affected_rows' => $st->affected_rows,
        'error' => $st->error
    ]);

    $st->close();
    $cn->close();

    return $ok;
}

echo "\n=====================================\n";
echo "TOTAL INSERTADOS: {$totalInsertados}\n";
echo "=====================================\n";
echo "</pre>";