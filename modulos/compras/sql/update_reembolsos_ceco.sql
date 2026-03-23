-- SQL Migration: Update CECO to INT and Migrate Data
-- Date: 2026-03-23

-- 1. Add temporary column
ALTER TABLE reembolsos_solicitudes ADD COLUMN ceco_new INT DEFAULT NULL AFTER ceco;

-- 2. Migrate data matching CodigoTexto from CentroCostos
UPDATE reembolsos_solicitudes rs
JOIN CentroCostos cc ON rs.ceco = cc.CodigoTexto
SET rs.ceco_new = cc.Codigo;

-- 3. Drop old column and rename new one
ALTER TABLE reembolsos_solicitudes DROP COLUMN ceco;
ALTER TABLE reembolsos_solicitudes CHANGE COLUMN ceco_new ceco INT DEFAULT NULL;

-- 4. Re-add index for performance if needed
ALTER TABLE reembolsos_solicitudes ADD INDEX idx_ceco (ceco);
