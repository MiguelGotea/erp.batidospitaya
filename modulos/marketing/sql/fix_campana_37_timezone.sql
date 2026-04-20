-- ════════════════════════════════════════════════════════════════
-- Fix timezone: hora_envio_programada quedó en UTC, debe ser Managua
-- Campaña 37 — corregir para que el VPS la tome de inmediato
-- ════════════════════════════════════════════════════════════════

-- Corregir la hora a Managua (UTC-6) para que ya esté vencida
UPDATE wsp_destinatarios_
SET hora_envio_programada = CONVERT_TZ(NOW(), '+00:00', '-06:00')
WHERE campana_id = 37
  AND enviado = 0
  AND (error IS NULL OR error = '');

-- Verificar: el resultado debe ser una hora tipo 2026-04-19 22:xx:xx
SELECT 
    COUNT(*) AS pendientes,
    MIN(hora_envio_programada) AS hora_min,
    MAX(hora_envio_programada) AS hora_max
FROM wsp_destinatarios_
WHERE campana_id = 37
  AND enviado = 0;
