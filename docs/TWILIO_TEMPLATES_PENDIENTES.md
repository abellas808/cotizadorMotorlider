# Templates Twilio pendientes

## Cancelación de agenda

Clave:

`template_motivo_cancelacion_agenda`

Texto:

`Gracias por tu respuesta, nos gustaría saber el motivo de tu decisión:`

Botones:

- `Me quiero agendar en otro momento.` → `motivo_no_agenda_otro_momento`
- `Recibí otra oferta.` → `motivo_no_agenda_recibi_otra_oferta`
- `Ya vendí el auto.` → `motivo_no_agenda_ya_vendi_auto`
- `Motivos personales.` → `motivo_no_agenda_personales`

## Recordatorio de confirmación de agenda

Clave:

`template_recordatorio_confirmacion_agenda_10hs`

Texto:

`¡Hola! ⏰ Noté que no llegaste a confirmar tu agenda para la revisión. ¿Querés continuar con ella?`

Botones:

- `Confirmar` → `recordatorio_agenda_confirmar_pendiente`
- `Buscar otro día` → `recordatorio_agenda_buscar_otro_dia`
- `Cancelar` → `recordatorio_agenda_cancelar`

## No asistió a la agenda

Clave:

`template_no_asistio_agenda`

Texto:

`¡Hola! Noté que no asististe a tu agenda para la revisión. Nos gustaría saber si querés coordinar una nueva agenda.`

Botones:

- `Confirmar` → `no_asistio_recoordinar_confirmar`
- `Cancelar` → `no_asistio_recoordinar_cancelar`

## Motivo de rechazo de tasación final

Clave:

`template_motivo_rechazo_tasacion_final`

Texto:

`Gracias por tu respuesta, nos gustaría saber el motivo de tu decisión:`

Botones:

- `Esperaba otro valor.` → `motivo_tasacion_final_otro_valor`
- `Voy a vender más adelante.` → `motivo_tasacion_final_vender_mas_adelante`
- `Motivos personales.` → `motivo_tasacion_final_personales`

## Recordatorio de tasación final

Clave:

`template_recordatorio_tasacion_final_24hs`

Texto:

`¡Hola {{1}}! 👋 Ayer te envié la tasación final de tu {{2}}. ¿Pudiste evaluarla? Avisame si querés avanzar.`

Variables:

- `{{1}}`: nombre del cliente.
- `{{2}}`: marca y modelo.

Botones:

- `Quiero avanzar` → `recordatorio_tasacion_final_avanzar`
- `Por ahora no` → `recordatorio_tasacion_final_por_ahora_no`

## Alta en `parametros_sistema`

Después de que Twilio apruebe cada plantilla, registrar su SID `HX...`:

```sql
INSERT INTO parametros_sistema
    (grupo, clave, valor, descripcion, activo)
VALUES
    ('twilio', 'CLAVE_TEMPLATE', 'HX_SID_REAL', 'Template Twilio', 1);
```
