-- ============================================================
-- campanas_wsp_reset_sesion.sql
-- Agrega columna reset_solicitado a wsp_sesion_vps_
-- y registra el permiso de resetear sesión en tools_erp
-- ============================================================

-- 1. Columna para el flag de reset (se lee y limpia en pendientes.php)
ALTER TABLE `wsp_sesion_vps_`
    ADD COLUMN `reset_solicitado` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = el ERP solicitó reiniciar la sesión WhatsApp (cambio de número)';

-- 2. Permiso de resetear sesión en la tabla de tools del ERP
--    Ajusta el valor de CodNivelesCargos al cargo del usuario que debe tenerlo
--    (el mismo que usa los otros permisos de campanas_wsp)
INSERT INTO `tools_erp` (`nombre_tool`, `accion`, `CodNivelesCargos`, `permitido`)
VALUES ('campanas_wsp', 'resetear_sesion', 1, 1)
ON DUPLICATE KEY UPDATE `permitido` = 1;

-- NOTA: Cambia el valor 1 de CodNivelesCargos por el código del cargo del
--       usuario específico que debe tener este permiso.
--       Puedes consultar los cargos en:
--       SELECT CodNivelesCargos, Descripcion FROM NivelesCargos;
