-- =====================================================
-- Script: Configuración de Punto de Reorden Sugerido
-- Descripción: Agrega campos de demanda y cobertura,
--              y elimina el campo pedido_minimo.
-- =====================================================

ALTER TABLE compra_local_configuracion_despacho 
ADD COLUMN base_consumption DECIMAL(10,2) DEFAULT 0.00 AFTER status,
ADD COLUMN lead_time_days INT DEFAULT 0 AFTER base_consumption,
ADD COLUMN shelf_life_days INT DEFAULT 7 AFTER lead_time_days,
ADD COLUMN event_factor DECIMAL(10,2) DEFAULT 1.00 AFTER shelf_life_days;

-- El usuario indicó que pedido_minimo se elimine ya que será calculado
ALTER TABLE compra_local_configuracion_despacho 
DROP COLUMN pedido_minimo;

