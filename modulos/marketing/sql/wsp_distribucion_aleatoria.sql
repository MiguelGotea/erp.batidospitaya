-- ════════════════════════════════════════════════════════════════
-- Distribución aleatoria de mensajes en campañas WhatsApp
-- Agrega columna hora_envio_programada a wsp_destinatarios_
-- Cada destinatario tiene su propio horario aleatorio (08:00-21:00)
-- ════════════════════════════════════════════════════════════════

-- 1. Agregar columna de hora individual por destinatario
ALTER TABLE wsp_destinatarios_
    ADD COLUMN hora_envio_programada DATETIME NULL DEFAULT NULL
    COMMENT 'Hora aleatoria asignada para enviar este mensaje (08:00-21:00 del día de campaña)';

-- 2. Índice para que pendientes.php filtre eficientemente
CREATE INDEX idx_dest_hora_prog
    ON wsp_destinatarios_ (campana_id, enviado, hora_envio_programada);

-- ────────────────────────────────────────────────────────────────
-- Nota: Los destinatarios sin hora_envio_programada (NULL)
-- corresponden a campañas antiguas y se envían de inmediato
-- (comportamiento legado compatible).
-- ════════════════════════════════════════════════════════════════
