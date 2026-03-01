-- --------------------------------------------------------
-- Servidor:                     127.0.0.1
-- Creación de tabla para administrar llaves de IA (Groq, OpenAI, etc)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `ia_proveedores_api` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proveedor` varchar(50) NOT NULL COMMENT 'Ej: groq, openai',
  `api_key` varchar(255) NOT NULL,
  `cuenta_correo` varchar(255) DEFAULT NULL COMMENT 'Correo del dueño de la API Key',
  `activa` tinyint(1) DEFAULT 1 COMMENT '1 = Activa, 0 = Inactiva',
  `limite_alcanzado_hoy` tinyint(1) DEFAULT 0 COMMENT 'Se resetea a 0 todos los dias',
  `ultimo_uso` datetime DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Llaves almacenadas para el rotador de APIs de Inteligencia Artificial';

-- Volcando datos para la tabla: ia_proveedores_api
INSERT IGNORE INTO `ia_proveedores_api` (`proveedor`, `api_key`, `cuenta_correo`, `activa`, `limite_alcanzado_hoy`) VALUES
('groq', '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', 'tu_correo_principal@ejemplo.com', 1, 0);
