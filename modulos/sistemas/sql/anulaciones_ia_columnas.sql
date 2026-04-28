-- ================================================================
-- Migración: Agregar columnas de veredicto IA a AnulacionPedidosHost
-- Ejecutar una sola vez en el servidor MySQL del host ERP.
-- ================================================================

ALTER TABLE `AnulacionPedidosHost`
    ADD COLUMN `ia_decision`  VARCHAR(10)  NULL DEFAULT NULL
        COMMENT 'Veredicto IA: aprobar | rechazar | revisar'
        AFTER `ComentarioAprobacion`,
    ADD COLUMN `ia_resultado` TEXT         NULL DEFAULT NULL
        COMMENT 'JSON completo con análisis IA: decision, confianza, comentario, puntos, proveedor, fecha'
        AFTER `ia_decision`;

-- Índice para filtrar rápido por veredicto IA
CREATE INDEX IF NOT EXISTS idx_ia_decision
    ON `AnulacionPedidosHost` (`ia_decision`);
