-- SQL Migration: Add currency to Reembolsos
-- Date: 2026-03-23

-- Add moneda column to reembolsos_solicitudes
ALTER TABLE reembolsos_solicitudes ADD COLUMN moneda VARCHAR(15) DEFAULT 'Cordobas' AFTER total_cordobas;

-- Update existing records to default 'Cordobas'
UPDATE reembolsos_solicitudes SET moneda = 'Cordobas' WHERE moneda IS NULL;

-- If you want to use symbols:
-- UPDATE reembolsos_solicitudes SET moneda = 'C$' WHERE moneda = 'Cordobas';
-- UPDATE reembolsos_solicitudes SET moneda = 'US$' WHERE moneda = 'Dolares';
-- But for now we stick to names 'Cordobas' / 'Dolares' or 'C$' / 'US$'.
-- User asked "cambiar el tipo de moneda", I will use 'Cordobas' and 'Dolares'.
