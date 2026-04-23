-- =============================================================
-- SQL: Sistema de Alertas WhatsApp — Pitaya Bot
-- Módulo:  Sistemas
-- Ejecutar en: u839374897_erp
-- Fecha: 2026-04-22
--
-- Pasos:
--   1. Ampliar ENUM tipo_componente en tools_erp
--   2. Crear tabla alertas_wsp_estado (control anti-spam)
--   3. Registrar las 2 alertas en tools_erp
--   4. Registrar acción 'recibir' para cada alerta
-- =============================================================


-- ── 1. Ampliar ENUM de tools_erp para soportar 'alerta' ──────

ALTER TABLE `tools_erp`
MODIFY COLUMN `tipo_componente`
    ENUM('herramienta','indicador','balance','alerta')
    NOT NULL
    DEFAULT 'herramienta'
    COMMENT 'Tipo de componente del sistema';


-- ── 2. Tabla de control anti-spam para alertas enviadas ───────
--
--  key_unica:
--    - Alerta PC:        "{sucursal_codigo}-{pc_nombre}-{ping_at}"
--                        Ej: "S01-ADMIN-PC-2026-04-22 18:00:05"
--                        Al reconectarse y caer de nuevo, ping_at cambia
--                        → nueva key → nueva alerta automática ✅
--    - Alerta Anulación: "{CodAnulacionHost}"
--                        Una sola alerta por solicitud de anulación, nunca se repite

CREATE TABLE IF NOT EXISTS `alertas_wsp_estado` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `tipo_alerta` VARCHAR(100)  NOT NULL COMMENT 'conexion_pc | anulacion_web',
    `key_unica`   VARCHAR(255)  NOT NULL COMMENT 'Identificador único del evento alertado',
    `datos_json`  JSON          NULL     COMMENT 'Contexto extra al momento del envío',
    `enviado_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_alerta` (`tipo_alerta`, `key_unica`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Registro de alertas WSP enviadas — control anti-spam por tipo y key';


-- ── 3. Registrar las 2 alertas en tools_erp ──────────────────

INSERT INTO `tools_erp`
    (`nombre`, `titulo`, `tipo_componente`, `grupo`, `descripcion`, `url_real`, `icono`, `orden`, `activo`)
VALUES
    (
        'alerta_conexion_pc',
        'PC Sin Conexión',
        'alerta',
        'sistemas',
        'Alerta WhatsApp cuando una PC lleva 60 minutos o más sin enviar ping al servidor.',
        'api.batidospitaya.com/api/alertas/alerta_conexion_pc.php',
        'fas fa-desktop',
        10,
        1
    ),
    (
        'alerta_anulacion_web',
        'Anulación Web Pendiente',
        'alerta',
        'sistemas',
        'Alerta WhatsApp cuando hay una solicitud de anulación web del día sin aprobar ni rechazar.',
        'api.batidospitaya.com/api/alertas/alerta_anulacion_web.php',
        'fas fa-ban',
        20,
        1
    )
ON DUPLICATE KEY UPDATE
    `titulo`       = VALUES(`titulo`),
    `descripcion`  = VALUES(`descripcion`),
    `url_real`     = VALUES(`url_real`),
    `icono`        = VALUES(`icono`),
    `activo`       = 1;


-- ── 4. Registrar acción 'recibir' para cada alerta ───────────

SET @id_alerta_pc  = (SELECT `id` FROM `tools_erp` WHERE `nombre` = 'alerta_conexion_pc'   LIMIT 1);
SET @id_alerta_anu = (SELECT `id` FROM `tools_erp` WHERE `nombre` = 'alerta_anulacion_web' LIMIT 1);

INSERT INTO `acciones_tools_erp` (`tool_erp_id`, `nombre_accion`, `descripcion`)
VALUES
    (@id_alerta_pc,  'recibir', 'El cargo recibe por WhatsApp la alerta cuando una PC lleva 60+ min sin conexión.'),
    (@id_alerta_anu, 'recibir', 'El cargo recibe por WhatsApp la alerta cuando hay una anulación web pendiente del día.')
ON DUPLICATE KEY UPDATE
    `descripcion` = VALUES(`descripcion`);


-- =============================================================
-- VERIFICACIÓN (ejecutar por separado para confirmar)
-- =============================================================
-- SELECT
--     t.nombre,
--     t.titulo,
--     t.tipo_componente,
--     a.nombre_accion,
--     a.descripcion
-- FROM tools_erp t
-- JOIN acciones_tools_erp a ON a.tool_erp_id = t.id
-- WHERE t.tipo_componente = 'alerta'
-- ORDER BY t.orden, a.nombre_accion;
--
-- SELECT * FROM alertas_wsp_estado LIMIT 10;
