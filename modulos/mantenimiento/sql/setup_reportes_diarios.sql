-- setup_reportes_diarios.sql
-- Estructura para el nuevo sistema de Informes Diarios de Mantenimiento v4

-- 1. Tabla de Informes Diarios
CREATE TABLE IF NOT EXISTS `mtto_informes_diarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cod_operario` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `km_inicial` decimal(10,2) DEFAULT NULL,
  `km_final` decimal(10,2) DEFAULT NULL,
  `km_foto_inicial` varchar(255) DEFAULT NULL,
  `km_foto_final` varchar(255) DEFAULT NULL,
  `monto_caja_chica` decimal(10,2) DEFAULT 0.00,
  `foto_caja_chica` varchar(255) DEFAULT NULL,
  `estado` enum('creado','finalizado') DEFAULT 'creado',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mtto_informes_operario` (`cod_operario`),
  CONSTRAINT `fk_mtto_informes_operario` FOREIGN KEY (`cod_operario`) REFERENCES `Operarios` (`CodOperario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabla de Visitas a Sucursales
CREATE TABLE IF NOT EXISTS `mtto_informe_visitas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `informe_id` int(11) NOT NULL,
  `cod_sucursal` varchar(10) NOT NULL,
  `hora_llegada` time DEFAULT NULL,
  `hora_salida` time DEFAULT NULL,
  `materiales_stock` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mtto_visitas_informe` (`informe_id`),
  CONSTRAINT `fk_mtto_visitas_informe` FOREIGN KEY (`informe_id`) REFERENCES `mtto_informes_diarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabla de Compras/Facturas por Visita
CREATE TABLE IF NOT EXISTS `mtto_informe_compras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `visita_id` int(11) NOT NULL,
  `foto_factura` varchar(255) DEFAULT NULL,
  `monto` decimal(10,2) DEFAULT 0.00,
  `detalle` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mtto_compras_visita` (`visita_id`),
  CONSTRAINT `fk_mtto_compras_visita` FOREIGN KEY (`visita_id`) REFERENCES `mtto_informe_visitas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabla de Tareas vinculadas a la Visita (Tickets)
CREATE TABLE IF NOT EXISTS `mtto_informe_tareas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `visita_id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `completado_100` tinyint(1) NOT NULL DEFAULT 1,
  `trabajo_realizado` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mtto_tareas_visita` (`visita_id`),
  CONSTRAINT `fk_mtto_tareas_visita` FOREIGN KEY (`visita_id`) REFERENCES `mtto_informe_visitas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabla de Fotos de Evidencia por Tarea (Múltiples fotos)
CREATE TABLE IF NOT EXISTS `mtto_informe_tareas_fotos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tarea_id` int(11) NOT NULL,
  `foto` varchar(255) NOT NULL,
  `orden` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mtto_tareas_fotos_tarea` (`tarea_id`),
  CONSTRAINT `fk_mtto_tareas_fotos_tarea` FOREIGN KEY (`tarea_id`) REFERENCES `mtto_informe_tareas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
