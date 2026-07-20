-- Parametro Twilio para el template recordatorio_pretasacion_24
-- Content SID: HX26f04d48e3753488a3271cd6772340b8

INSERT INTO parametros_sistema
    (grupo, clave, valor, descripcion, activo, created_at, updated_at)
SELECT
    'twilio',
    'template_recordatorio_pretasacion_24',
    'HX26f04d48e3753488a3271cd6772340b8',
    'Template Twilio para recordatorio 24 hs de pre tasacion',
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM parametros_sistema
    WHERE grupo = 'twilio'
      AND clave = 'template_recordatorio_pretasacion_24'
);

UPDATE parametros_sistema
SET valor = 'HX26f04d48e3753488a3271cd6772340b8',
    descripcion = 'Template Twilio para recordatorio 24 hs de pre tasacion',
    activo = 1,
    updated_at = NOW()
WHERE grupo = 'twilio'
  AND clave = 'template_recordatorio_pretasacion_24';
