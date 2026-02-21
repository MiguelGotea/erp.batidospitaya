-- ============================================================
-- SQL: Sistema de Campañas WhatsApp — ERP Batidos Pitaya
-- Ejecutar en la base de datos del ERP
-- ============================================================

-- Tabla 1: Campañas
CREATE TABLE IF NOT EXISTS wsp_campanas_ (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    nombre              VARCHAR(255)    NOT NULL,
    mensaje             TEXT            NOT NULL COMMENT 'Soporta variables: {{nombre}}, {{sucursal}}',
    imagen_url          VARCHAR(500)    NULL,
    fecha_envio         DATETIME        NOT NULL,
    estado              ENUM('borrador','programada','enviando','completada','fallida','cancelada')
                        DEFAULT 'borrador',
    total_destinatarios INT             DEFAULT 0,
    total_enviados      INT             DEFAULT 0,
    total_errores       INT             DEFAULT 0,
    filtro_sucursal     VARCHAR(500)    NULL COMMENT 'JSON con IDs de sucursales filtradas',
    usuario_creacion    INT             NOT NULL,
    fecha_creacion      DATETIME        DEFAULT (CONVERT_TZ(NOW(),'+00:00','-06:00')),
    INDEX idx_estado (estado),
    INDEX idx_fecha_envio (fecha_envio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 2: Destinatarios por campaña
CREATE TABLE IF NOT EXISTS wsp_destinatarios_ (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    campana_id      INT             NOT NULL,
    id_cliente      INT             NOT NULL COMMENT 'clientesclub.id_clienteclub',
    nombre          VARCHAR(200),
    telefono        VARCHAR(20)     NOT NULL COMMENT 'Formato +50588887777',
    sucursal        VARCHAR(100)    NULL,
    enviado         TINYINT(1)      DEFAULT 0,
    error           VARCHAR(500)    NULL,
    fecha_envio     DATETIME        NULL,
    FOREIGN KEY (campana_id) REFERENCES wsp_campanas_(id) ON DELETE CASCADE,
    INDEX idx_campana_pendientes (campana_id, enviado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 3: Log de actividad
CREATE TABLE IF NOT EXISTS wsp_logs_ (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    campana_id      INT             NULL,
    destinatario_id INT             NULL,
    tipo            ENUM('info','exito','error','sesion') NOT NULL,
    detalle         TEXT,
    fecha           DATETIME        DEFAULT (CONVERT_TZ(NOW(),'+00:00','-06:00')),
    INDEX idx_campana (campana_id),
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 4: Estado del servicio VPS (siempre 1 fila)
CREATE TABLE IF NOT EXISTS wsp_sesion_vps_ (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    estado      ENUM('desconectado','qr_pendiente','conectado') DEFAULT 'desconectado',
    qr_base64   MEDIUMTEXT  NULL,
    ultimo_ping DATETIME    NULL,
    ip_vps      VARCHAR(50) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fila única de sesión VPS
INSERT IGNORE INTO wsp_sesion_vps_ (id, estado) VALUES (1, 'desconectado');

-- ============================================================
-- Registrar herramienta en tools_erp (insertar manualmente)
-- ============================================================
-- INSERT INTO tools_erp (nombre, titulo, tipo_componente, grupo, descripcion, url_real, icono)
-- VALUES (
--   'campanas_wsp',
--   'Campañas WhatsApp',
--   'herramienta',
--   'marketing',
--   'Gestión de campañas de mensajería masiva por WhatsApp',
--   '/modulos/marketing/campanas_wsp.php',
--   'bi bi-whatsapp'
-- );

-- ============================================================
-- Registrar acciones en acciones_tools_erp (insertar manualmente)
-- Reemplazar {TOOL_ID} con el ID generado arriba
-- ============================================================
-- INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion) VALUES
--   ({TOOL_ID}, 'vista',           'Ver listado de campañas'),
--   ({TOOL_ID}, 'nueva_campana',   'Crear nuevas campañas'),
--   ({TOOL_ID}, 'eliminar_campana','Eliminar campañas en borrador');
