-- Parametro Twilio para el template interactivo de pre-cotizacion.
-- Este template se usa cuando un usuario de Motorlider envia la pre-tasacion
-- desde adm/modulos/cot/ajax_enviar_cotizacion.php.
--
-- Template:
--   Template name: envio_pre_tasacion
--   Content SID: HXef735704a2f7425156a7ecd3b3139833
--   Variable {{1}}: texto completo de la pre-cotizacion armado por sistema.
--
-- Botones:
--   Si, agendar      => precotizacion_agendar
--   En otro momento  => precotizacion_no_agendar

UPDATE parametros_sistema
SET
    valor = 'HXef735704a2f7425156a7ecd3b3139833',
    descripcion = 'Twilio Content SID del template envio_pre_tasacion. Se usa al enviar pre-tasacion desde Cotizaciones. Variable {{1}} = texto completo de pre-tasacion. Botones: precotizacion_agendar / precotizacion_no_agendar.',
    activo = 1,
    updated_at = NOW()
WHERE grupo = 'twilio'
  AND clave = 'envio_pre_tasacion';

INSERT INTO parametros_sistema
    (grupo, clave, valor, descripcion, activo, created_at, updated_at)
SELECT
    'twilio',
    'envio_pre_tasacion',
    'HXef735704a2f7425156a7ecd3b3139833',
    'Twilio Content SID del template envio_pre_tasacion. Se usa al enviar pre-tasacion desde Cotizaciones. Variable {{1}} = texto completo de pre-tasacion. Botones: precotizacion_agendar / precotizacion_no_agendar.',
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM parametros_sistema
    WHERE grupo = 'twilio'
      AND clave = 'envio_pre_tasacion'
);
