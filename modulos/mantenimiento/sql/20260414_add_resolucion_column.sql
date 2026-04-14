-- SQL migration to add 'resolucion' field to mtto_tickets table
-- Created on: 2026-04-14

ALTER TABLE mtto_tickets ADD COLUMN resolucion TEXT NULL;
