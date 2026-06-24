<?php

/**
 * detectar_recordatorios_agenda_sin_respuesta.php
 *
 * Detecta agendas donde el último recordatorio automático enviado
 * no tuvo respuesta luego de X horas.
 *
 * Ubicación:
 * /public_html/adm/modulos/cot/detectar_recordatorios_agenda_sin_respuesta.php
 *
 * URL prueba:
 * https://carplay.uy/adm/modulos/cot/detectar_recordatorios_agenda_sin_respuesta.php
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 1);
set_time_limit(0);
ob_start();

require_once(__DIR__ . '/../../config/config.inc.php');
require_once(__DIR__ . '/../../includes/database.php');
require_once(__DIR__ . '/../../includes/funciones.php');

date_default_timezone_set('America/Montevideo');

global $db;

echo "<pre>";
echo "====================================================\n";
echo " DETECTOR RECORDATORIOS AGENDA SIN RESPUESTA\n";
echo "====================================================\n\n";

function cron_escape($valor) {
    global $db;
    return $db->escape((string)$valor);
}

$horasEspera = 10;

$tiposRecordatorio = array(
    'confirmacion_24h',
    'reintento_confirmacion_24h',
    'confirmacion_48h',
    'reintento_confirmacion_48h',
    'recordatorio_24h',
    'recordatorio_48h',
    'sin_respuesta_confirmacion'
);

$tiposEscapados = array();


foreach ($tiposRecordatorio as $tipo) {
    $tiposEscapados[] = "'" . cron_escape($tipo) . "'";
}

$tiposSql = implode(',', $tiposEscapados);

/**
 * Esta query:
 * 1. Busca el último recordatorio por agenda.
 * 2. Valida que hayan pasado 10 horas desde ese último recordatorio.
 * 3. Verifica que la agenda no esté cancelada ni finalizada.
 * 4. Verifica que la asistencia siga pendiente.
 * 5. Verifica que no exista una respuesta posterior en whatsapp_agenda_notificaciones.
 * 6. Verifica que no exista ya carrito abandonado para esa cotización/motivo.
 */
$sql = "
    SELECT
        n.id AS id_notificacion,
        n.id_agenda,
        n.tipo_notificacion,
        n.estado_envio,
        n.mensaje_enviado,
        n.fecha_envio AS fecha_recordatorio,

        a.id_cotizacion,
        a.telefono,
        a.fecha AS agenda_fecha,
        a.hora AS agenda_hora,
        a.cancelado,
        a.finalizada,
        a.confirmacion_asistencia,

        c.id_cotizaciones_generadas,
        c.nombre,
        c.email,
        c.marca,
        c.familia AS modelo,
        c.anio,
        c.kilometros,
        c.tasacion_final,
        c.auto,

        wc.id AS id_conversacion

    FROM whatsapp_agenda_notificaciones n

    INNER JOIN (
        SELECT
            id_agenda,
            MAX(id) AS id_ultima_notificacion
        FROM whatsapp_agenda_notificaciones
        WHERE tipo_notificacion IN ({$tiposSql})
        GROUP BY id_agenda
    ) ult
        ON ult.id_ultima_notificacion = n.id

    INNER JOIN agendas a
        ON a.id_agenda = n.id_agenda

    INNER JOIN cotizaciones_generadas c
        ON c.id_cotizaciones_generadas = a.id_cotizacion

    LEFT JOIN whatsapp_conversaciones wc
        ON wc.telefono = a.telefono
       AND wc.id_cotizacion = a.id_cotizacion

    WHERE n.tipo_notificacion IN ({$tiposSql})

      AND n.fecha_envio <= DATE_SUB(NOW(), INTERVAL " . intval($horasEspera) . " HOUR)

      AND IFNULL(a.cancelado, 0) = 0
      AND IFNULL(a.finalizada, 0) = 0

        AND IFNULL(a.confirmacion_asistencia, '') IN (
            '',
            'PENDIENTE',
            'PTE RESP.',
            'PTE_RESP',
            'SIN_RESPUESTA'
        )

      AND NOT EXISTS (
          SELECT 1
          FROM whatsapp_agenda_notificaciones r
          WHERE r.id_agenda = n.id_agenda
            AND r.id > n.id
            AND r.tipo_notificacion IN (
                'respuesta_cliente',
                'respuesta_si',
                'respuesta_no',
                'confirmacion_cliente',
                'cancelacion_cliente',
                'cliente_responde_si',
                'cliente_responde_no'
            )
      )

      AND NOT EXISTS (
          SELECT 1
          FROM carrito_abandonado ca
          WHERE ca.id_cotizacion = a.id_cotizacion
            AND ca.motivo_abandono = 'NO_RESPONDE_RECORDATORIO_AGENDA'
      )

    ORDER BY n.fecha_envio ASC
";

$rs = $db->query($sql);

if (!$rs) {
    echo "ERROR consultando recordatorios.\n";
    echo "SQL:\n" . $sql . "\n\n";
    echo "</pre>";
    ob_end_flush();
    exit;
}

$totalCandidatos = 0;
$totalInsertados = 0;
$totalOmitidos = 0;

while ($row = $db->fetch_array($rs)) {

    $totalCandidatos++;

    $idAgenda = intval($row['id_agenda']);
    $idCotizacion = intval($row['id_cotizacion']);
    $idConversacion = intval($row['id_conversacion']);
    $telefono = trim((string)$row['telefono']);

    echo "Candidato detectado:\n";
    echo " - Agenda: {$idAgenda}\n";
    echo " - Cotización: {$idCotizacion}\n";
    echo " - Conversación: {$idConversacion}\n";
    echo " - Teléfono: {$telefono}\n";
    echo " - Recordatorio: " . $row['tipo_notificacion'] . "\n";
    echo " - Fecha recordatorio: " . $row['fecha_recordatorio'] . "\n";
    echo " - Auto: " . $row['auto'] . "\n";

    if ($idAgenda <= 0 || $idCotizacion <= 0 || $telefono === '') {
        $totalOmitidos++;
        echo " - OMITIDO: faltan datos mínimos.\n\n";
        continue;
    }

    /**
     * Seguridad extra anti-duplicado.
     */
    $yaExiste = $db->query_first("
        SELECT id
        FROM carrito_abandonado
        WHERE id_cotizacion = " . intval($idCotizacion) . "
          AND motivo_abandono = 'NO_RESPONDE_RECORDATORIO_AGENDA'
        LIMIT 1
    ");

    if ($yaExiste) {
        $totalOmitidos++;
        echo " - OMITIDO: ya existe carrito abandonado ID " . intval($yaExiste['id']) . "\n\n";
        continue;
    }

    $sqlInsert = "
        INSERT INTO carrito_abandonado
        (
            id_cotizacion,
            id_conversacion,
            telefono,
            nombre,
            email,
            marca,
            modelo,
            anio,
            kilometros,
            tasacion_final,
            mensaje_cliente,
            motivo_abandono,
            origen_abandono,
            fecha_respuesta,
            usuario,
            estado,
            observaciones,
            fecha_ultima_gestion,
            usuario_ultima_gestion,
            fecha_alta
        )
        VALUES
        (
            " . intval($idCotizacion) . ",
            " . intval($idConversacion) . ",
            '" . cron_escape($telefono) . "',
            '" . cron_escape($row['nombre']) . "',
            '" . cron_escape($row['email']) . "',
            '" . cron_escape($row['marca']) . "',
            '" . cron_escape($row['modelo']) . "',
            " . intval($row['anio']) . ",
            " . intval($row['kilometros']) . ",
            " . floatval($row['tasacion_final']) . ",
            'Sin respuesta luego de recordatorio automático de agenda',
            'NO_RESPONDE_RECORDATORIO_AGENDA',
            'RECORDATORIO_AGENDA_AUTO',
            NOW(),
            'CRON',
            'PENDIENTE',
            '',
            NULL,
            '',
            NOW()
        )
    ";

    $ok = $db->query($sqlInsert);

    if ($ok) {
        $totalInsertados++;
        echo " - OK: enviado a carrito_abandonado.\n\n";
    } else {
        $totalOmitidos++;
        echo " - ERROR: no se pudo insertar carrito_abandonado.\n\n";
    }
}

echo "====================================================\n";
echo "TOTAL CANDIDATOS: {$totalCandidatos}\n";
echo "TOTAL INSERTADOS: {$totalInsertados}\n";
echo "TOTAL OMITIDOS: {$totalOmitidos}\n";
echo "====================================================\n";
echo "</pre>";

ob_end_flush();
