<?php
/**
 * /adm/modulos/cot/detectar_carritos_abandonados.php
 *
 * Detecta automáticamente cotizaciones abandonadas
 * según reglas configurables en carrito_abandonado_reglas.
 *
 * Lógica:
 * 1. Lee reglas activas.
 * 2. Busca cotizaciones en el estado configurado.
 * 3. Busca el último mensaje SALIENTE que coincida con texto_mensaje_referencia.
 * 4. Verifica que hayan pasado X horas desde ese mensaje.
 * 5. Verifica que NO exista mensaje ENTRANTE posterior del cliente.
 * 6. Verifica que no exista ya carrito abandonado con ese motivo.
 * 7. Inserta carrito_abandonado.
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 1);

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
echo " DETECTOR CARRITOS ABANDONADOS\n";
echo "=====================================\n\n";

function esc($valor) {
    global $db;
    return $db->escape((string)$valor);
}

/**
 * =========================================================
 * 1. OBTENER REGLAS ACTIVAS
 * =========================================================
 */
$sqlReglas = "
    SELECT
        id,
        estado_id,
        motivo_abandono,
        origen_abandono,
        horas_espera,
        mensaje_cliente,
        texto_mensaje_referencia
    FROM carrito_abandonado_reglas
    WHERE activo = 1
    ORDER BY id ASC
";

$reglas = $db->query($sqlReglas);

if (!$reglas) {
    echo "Error obteniendo reglas.\n";
    echo "</pre>";
    exit;
}

$totalInsertados = 0;

while ($regla = $db->fetch_array($reglas)) {

    $idRegla = intval($regla['id']);
    $estadoId = intval($regla['estado_id']);
    $motivoAbandono = trim((string)$regla['motivo_abandono']);
    $origenAbandono = trim((string)$regla['origen_abandono']);
    $horasEspera = intval($regla['horas_espera']);
    $mensajeCliente = trim((string)$regla['mensaje_cliente']);
    $textoReferencia = trim((string)$regla['texto_mensaje_referencia']);

    // Normalización de puntos históricos configurados en la tabla de reglas.
    if ($motivoAbandono === 'NO_CONFIRMA_AGENDA' && $horasEspera === 3) {
        $motivoAbandono = 'NO_CONFIRMACION_AGENDA';
    } elseif ($motivoAbandono === 'NO_RESPONDE_RECORDATORIO_AGENDA') {
        $motivoAbandono = 'NO_CONFIRMA_AGENDA';
    } elseif ($motivoAbandono === 'NO_RESPONDE_TASACION_FINAL') {
        $horasEspera = 48;
    }

    echo "-------------------------------------\n";
    echo "Regla ID: {$idRegla}\n";
    echo "Motivo: {$motivoAbandono}\n";
    echo "Estado ID: {$estadoId}\n";
    echo "Horas espera: {$horasEspera}\n";
    echo "Texto referencia: {$textoReferencia}\n";
    echo "-------------------------------------\n";

    if ($estadoId <= 0 || $motivoAbandono === '' || $textoReferencia === '') {
        echo "Regla omitida: faltan datos obligatorios.\n\n";
        continue;
    }

    $likeReferencia = '%' . $textoReferencia . '%';

    /**
     * =========================================================
     * 2. BUSCAR COTIZACIONES CANDIDATAS
     * =========================================================
     *
     * Condiciones:
     * - La cotización está en el estado configurado por la regla.
     * - Existe un mensaje saliente al cliente que coincide con el texto de referencia.
     * - Ese mensaje tiene más horas que la ventana configurada.
     * - No existe mensaje entrante posterior del cliente.
     * - No existe ya un carrito abandonado para esa cotización y motivo.
     */
    $sqlCotizaciones = "
        SELECT
            c.id_cotizaciones_generadas,
            c.telefono,
            c.nombre,
            c.email,
            c.marca,
            c.familia AS modelo,
            c.anio,
            c.kilometros,
            c.tasacion_final,
            c.estado_id,
            msj_ref.id AS id_mensaje_referencia,
            msj_ref.fecha AS fecha_mensaje_referencia
        FROM cotizaciones_generadas c

        INNER JOIN (
            SELECT
                m1.telefono,
                MAX(m1.id) AS id_ultimo_mensaje
            FROM whatsapp_conversacion_mensajes m1
            WHERE m1.direccion = 'SALIENTE'
              AND m1.mensaje LIKE '" . esc($likeReferencia) . "'
            GROUP BY m1.telefono
        ) ult
            ON ult.telefono = c.telefono

        INNER JOIN whatsapp_conversacion_mensajes msj_ref
            ON msj_ref.id = ult.id_ultimo_mensaje

        WHERE c.estado_id = " . intval($estadoId) . "
          AND msj_ref.fecha <= DATE_SUB(NOW(), INTERVAL " . intval($horasEspera) . " HOUR)

          AND NOT EXISTS (
              SELECT 1
              FROM whatsapp_conversacion_mensajes msj_in
              WHERE msj_in.telefono = c.telefono
                AND msj_in.direccion = 'ENTRANTE'
                AND msj_in.fecha > msj_ref.fecha
          )

          AND NOT EXISTS (
              SELECT 1
              FROM carrito_abandonado ca
              WHERE ca.id_cotizacion = c.id_cotizaciones_generadas
                AND ca.estado = 'PENDIENTE'
          )

        ORDER BY c.id_cotizaciones_generadas ASC
    ";

    $cotizaciones = $db->query($sqlCotizaciones);

    if (!$cotizaciones) {
        echo "Error consultando cotizaciones para regla {$idRegla}.\n\n";
        continue;
    }

    $insertadosRegla = 0;

    while ($cot = $db->fetch_array($cotizaciones)) {

        $idCotizacion = intval($cot['id_cotizaciones_generadas']);

        echo "Cotización detectada: {$idCotizacion} | ";
        echo "Último mensaje ref: {$cot['fecha_mensaje_referencia']}\n";

        /**
         * =========================================================
         * 3. INSERTAR CARRITO ABANDONADO
         * =========================================================
         */
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
                0,
                '" . esc($cot['telefono']) . "',
                '" . esc($cot['nombre']) . "',
                '" . esc($cot['email']) . "',
                '" . esc($cot['marca']) . "',
                '" . esc($cot['modelo']) . "',
                " . intval($cot['anio']) . ",
                " . intval($cot['kilometros']) . ",
                " . floatval($cot['tasacion_final']) . ",
                '" . esc($mensajeCliente) . "',
                '" . esc($motivoAbandono) . "',
                '" . esc($origenAbandono) . "',
                NOW(),
                'CRON',
                'PENDIENTE',
                '',
                NULL,
                '',
                NOW()
            )
        ";

        $okInsert = $db->query($sqlInsert);

        if ($okInsert) {
            $totalInsertados++;
            $insertadosRegla++;
            echo "OK INSERT carrito_abandonado\n";
        } else {
            echo "ERROR insertando carrito_abandonado cotización {$idCotizacion}\n";
        }
    }

    echo "Insertados regla {$idRegla}: {$insertadosRegla}\n\n";
}

/**
 * =========================================================
 * 4. DETECTAR AGENDA SIN CONFIRMAR / CANCELAR
 * =========================================================
 */
echo "-------------------------------------\n";
echo "Detectando agendas pendientes sin respuesta...\n";
echo "-------------------------------------\n";

$horasEsperaAgenda = 3; // Configurable: horas que se espera una confirmación de agenda antes de marcar como abandonada

$mensajeCierreAgenda = "Como no recibimos confirmación para la agenda, dejamos cerrada esta solicitud por ahora.\n\n"
    . "Si querés coordinar nuevamente, respondé AGENDAR.";

$sqlAgendaPendiente = "
    SELECT
        wc.id AS id_conversacion,
        wc.telefono,
        wc.nombre,
        wc.email,
        wc.id_cotizacion,
        wc.datos_json,
        wc.fecha_mod,

        c.id_cotizaciones_generadas,
        c.nombre AS cot_nombre,
        c.email AS cot_email,
        c.marca,
        c.familia AS modelo,
        c.anio,
        c.kilometros,
        c.tasacion_final

    FROM whatsapp_conversaciones wc

    LEFT JOIN cotizaciones_generadas c
        ON c.id_cotizaciones_generadas = wc.id_cotizacion

    WHERE wc.datos_json LIKE '%\"step\":\"agenda_confirmar\"%'
      AND wc.fecha_mod <= DATE_SUB(NOW(), INTERVAL " . intval($horasEsperaAgenda) . " HOUR)

      AND NOT EXISTS (
          SELECT 1
          FROM whatsapp_conversacion_mensajes mi
          WHERE mi.telefono = wc.telefono
            AND mi.direccion = 'ENTRANTE'
            AND mi.fecha > wc.fecha_mod
      )

      AND NOT EXISTS (
          SELECT 1
          FROM carrito_abandonado ca
          WHERE ca.id_cotizacion = wc.id_cotizacion
            AND ca.estado = 'PENDIENTE'
      )
";

$rsAgenda = $db->query($sqlAgendaPendiente);

if (!$rsAgenda) {
    echo "Error consultando agendas pendientes.\n";
} else {

    $insertadosAgenda = 0;

    while ($row = $db->fetch_array($rsAgenda)) {

        $idConversacion = intval($row['id_conversacion']);
        $telefono = (string)$row['telefono'];
        $datosJson = json_decode((string)$row['datos_json'], true);
        if (!is_array($datosJson)) {
            $datosJson = [];
        }

        $idCotizacion = intval($row['id_cotizacion'] ?? 0);

        if ($idCotizacion <= 0) {
            echo "Omitido teléfono {$telefono}: no se pudo resolver id_cotizacion.\n";
            continue;
        }

        echo "Agenda abandonada detectada | Cotización {$idCotizacion} | Tel {$telefono}\n";

        $sqlInsertAgenda = "
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
                '" . esc($telefono) . "',
                '" . esc($row['cot_nombre'] ?: $row['nombre']) . "',
                '" . esc($row['cot_email'] ?: $row['email']) . "',
                '" . esc($row['marca']) . "',
                '" . esc($row['modelo']) . "',
                " . intval($row['anio']) . ",
                " . intval($row['kilometros']) . ",
                " . floatval($row['tasacion_final']) . ",
                'Cliente no confirmó ni canceló la agenda',
                'NO_CONFIRMACION_AGENDA',
                'CONFIRMACION_AGENDA_AUTO',
                NOW(),
                'CRON',
                'PENDIENTE',
                '',
                NULL,
                '',
                NOW()
            )
        ";

        $okInsertAgenda = $db->query($sqlInsertAgenda);

        if (!$okInsertAgenda) {
            echo "ERROR insertando carrito agenda cotización {$idCotizacion}\n";
            continue;
        }

        $nuevoJson = $datosJson;
        $nuevoJson['step'] = 'cerrado';
        $nuevoJson['sub_step'] = 'agenda_cerrada_por_inactividad';
        $nuevoJson['id_cotizacion'] = $idCotizacion;

        $sqlUpdateConv = "
            UPDATE whatsapp_conversaciones
            SET
                estado = 'CERRADO',
                modo_atencion = 'BOT',
                datos_json = '" . esc(json_encode($nuevoJson, JSON_UNESCAPED_UNICODE)) . "',
                ultima_respuesta_bot = '" . esc($mensajeCierreAgenda) . "',
                fecha_mod = NOW()
            WHERE id = " . intval($idConversacion) . "
            LIMIT 1
        ";

        $db->query($sqlUpdateConv);

        $sqlInsertMsg = "
            INSERT INTO whatsapp_conversacion_mensajes
            (
                id_conversacion,
                telefono,
                direccion,
                emisor,
                mensaje,
                meta_json,
                sid_mensaje,
                fecha
            )
            VALUES
            (
                " . intval($idConversacion) . ",
                '" . esc($telefono) . "',
                'SALIENTE',
                'BOT',
                '" . esc($mensajeCierreAgenda) . "',
                '" . esc(json_encode(['origen' => 'cron_carrito_abandonado_agenda'], JSON_UNESCAPED_UNICODE)) . "',
                NULL,
                NOW()
            )
        ";

        $db->query($sqlInsertMsg);

        $insertadosAgenda++;
        $totalInsertados++;

        echo "OK agenda enviada a carrito_abandonado\n";
    }

    echo "Insertados agenda automática: {$insertadosAgenda}\n\n";
}

echo "=====================================\n";
echo "TOTAL INSERTADOS: {$totalInsertados}\n";
echo "=====================================\n";
echo "</pre>";

ob_end_flush();
