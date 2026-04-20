-- ════════════════════════════════════════════════════════════════
-- Fix campaña #37 — Reintentar destinatarios con error de Frame
-- Error: "Attempted to use detached Frame '028FCD612C453331C8A9EDA1B1A7B03A'"
-- Estos destinatarios quedaron marcados como enviado=1 pero fallaron
-- ════════════════════════════════════════════════════════════════

-- 1. Ver cuántos registros se van a corregir (verificación previa)
SELECT 
    COUNT(*) AS total_a_corregir,
    MIN(id)  AS primer_id,
    MAX(id)  AS ultimo_id
FROM wsp_destinatarios_
WHERE campana_id = 37
  AND enviado = 1
  AND error LIKE '%Attempted to use detached Frame%';

-- 2. Resetear esos destinatarios para que se reenvíen
UPDATE wsp_destinatarios_
SET 
    enviado          = 0,
    error            = NULL,
    fecha_envio      = NULL,
    hora_envio_programada = NOW()   -- enviar de inmediato en el próximo ciclo del VPS
WHERE campana_id = 37
  AND enviado = 1
  AND error LIKE '%Attempted to use detached Frame%';

-- 3. Restar del contador total_errores de la campaña
--    y reactivar estado a 'enviando' para que el VPS la retome
UPDATE wsp_campanas_
SET 
    total_errores   = (
        SELECT COUNT(*) FROM wsp_destinatarios_
        WHERE campana_id = 37
          AND enviado = 1
          AND error IS NOT NULL AND error != ''
    ),
    total_enviados  = (
        SELECT COUNT(*) FROM wsp_destinatarios_
        WHERE campana_id = 37
          AND enviado = 1
          AND (error IS NULL OR error = '')
    ),
    estado          = 'programada'   -- el VPS la retomará en el próximo ciclo
WHERE id = 37;

-- 4. Verificar estado final de la campaña
SELECT 
    id,
    nombre,
    estado,
    total_destinatarios,
    total_enviados,
    total_errores,
    (total_destinatarios - total_enviados - total_errores) AS pendientes_restantes
FROM wsp_campanas_
WHERE id = 37;
