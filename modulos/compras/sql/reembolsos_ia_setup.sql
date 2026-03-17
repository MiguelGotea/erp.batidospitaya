-- SQL Setup for AI-Powered Reimbursement Tool
-- Module: Compras (Legacy names updated to be generic)

CREATE TABLE IF NOT EXISTS `reembolsos_solicitudes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_proveedor` int(11) DEFAULT NULL,
  `id_cuenta_proveedor` int(11) DEFAULT NULL,
  `concepto` varchar(255) DEFAULT NULL,
  `ceco` varchar(100) DEFAULT NULL,
  `total_cordobas` decimal(12,2) DEFAULT 0.00,
  `estado` enum('pendiente', 'aprobado', 'procesado', 'pagado', 'rechazado') DEFAULT 'pendiente',
  `usuario_registro` int(11) NOT NULL,
  `fecha_solicitud` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_proveedor` (`id_proveedor`),
  KEY `idx_usuario` (`usuario_registro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reembolsos_detalles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_solicitud` int(11) NOT NULL,
  `cantidad` decimal(10,2) DEFAULT NULL,
  `detalle` text DEFAULT NULL,
  `monto_cordobas` decimal(12,2) DEFAULT NULL,
  `foto_factura` varchar(255) DEFAULT NULL,
  `extracted_json` text DEFAULT NULL COMMENT 'Full JSON from AI for traceability',
  `fecha_hora_regsys` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_solicitud` (`id_solicitud`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
