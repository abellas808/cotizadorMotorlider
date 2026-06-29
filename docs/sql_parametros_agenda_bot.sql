-- Parametros para reglas de agenda del bot Motorlider.
-- Grupo: agenda_bot
-- Valores actuales:
-- - horario operativo: 09:00 a 15:30
-- - margen fuera de horario: 6 bloques de 30 minutos = 3 horas

UPDATE parametros_sistema
SET valor = '09:00',
    descripcion = 'Hora de inicio operativa para reglas de agenda del bot',
    activo = 1,
    updated_at = NOW()
WHERE grupo = 'agenda_bot'
  AND clave = 'hora_inicio_operativa';

INSERT INTO parametros_sistema (grupo, clave, valor, descripcion, activo, created_at, updated_at)
SELECT
    'agenda_bot',
    'hora_inicio_operativa',
    '09:00',
    'Hora de inicio operativa para reglas de agenda del bot',
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM parametros_sistema
    WHERE grupo = 'agenda_bot'
      AND clave = 'hora_inicio_operativa'
);

UPDATE parametros_sistema
SET valor = '15:30',
    descripcion = 'Hora de corte operativa para permitir agenda temprana del bot',
    activo = 1,
    updated_at = NOW()
WHERE grupo = 'agenda_bot'
  AND clave = 'hora_corte_agenda_temprana';

INSERT INTO parametros_sistema (grupo, clave, valor, descripcion, activo, created_at, updated_at)
SELECT
    'agenda_bot',
    'hora_corte_agenda_temprana',
    '15:30',
    'Hora de corte operativa para permitir agenda temprana del bot',
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM parametros_sistema
    WHERE grupo = 'agenda_bot'
      AND clave = 'hora_corte_agenda_temprana'
);

UPDATE parametros_sistema
SET valor = '6',
    descripcion = 'Cantidad de bloques a ocultar fuera de horario operativo de agenda',
    activo = 1,
    updated_at = NOW()
WHERE grupo = 'agenda_bot'
  AND clave = 'bloques_margen_fuera_horario';

INSERT INTO parametros_sistema (grupo, clave, valor, descripcion, activo, created_at, updated_at)
SELECT
    'agenda_bot',
    'bloques_margen_fuera_horario',
    '6',
    'Cantidad de bloques a ocultar fuera de horario operativo de agenda',
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM parametros_sistema
    WHERE grupo = 'agenda_bot'
      AND clave = 'bloques_margen_fuera_horario'
);
