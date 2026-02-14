-- =====================================================
-- Script: Agregar columna pedido_minimo
-- Descripción: Agrega la columna pedido_minimo a la tabla
--              compra_local_configuracion_despacho
-- =====================================================

ALTER TABLE compra_local_configuracion_despacho 
ADD COLUMN pedido_minimo INT DEFAULT 1 COMMENT 'Cantidad mínima de pedido para este producto y sucursal'
AFTER status;
