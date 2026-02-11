-- =====================================================
-- Sistema de Compra Local - Despacho de Productos
-- =====================================================
-- Tabla para gestionar la configuración de despachos 
-- y pedidos de productos a sucursales
-- =====================================================

CREATE TABLE `compra_local_productos_despacho` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_producto_presentacion` INT(11) NOT NULL COMMENT 'FK to producto_presentacion.id',
  `codigo_sucursal` VARCHAR(10) NOT NULL COMMENT 'FK to sucursales.codigo',
  `dia_entrega` TINYINT(1) NOT NULL COMMENT '1=Lunes, 2=Martes, 3=Miércoles, 4=Jueves, 5=Viernes, 6=Sábado, 7=Domingo',
  `cantidad_pedido` INT(11) DEFAULT 0 COMMENT 'Cantidad solicitada para esta fecha',
  `fecha_hora_reportada` DATETIME DEFAULT NULL COMMENT 'Timestamp when order was last modified (only if value actually changed)',
  `status` ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_creacion` INT(11) NOT NULL COMMENT 'FK to Operarios.CodOperario',
  `fecha_modificacion` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `usuario_modificacion` INT(11) DEFAULT NULL COMMENT 'FK to Operarios.CodOperario',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_producto_sucursal_dia` (`id_producto_presentacion`, `codigo_sucursal`, `dia_entrega`),
  KEY `idx_producto` (`id_producto_presentacion`),
  KEY `idx_sucursal` (`codigo_sucursal`),
  KEY `idx_dia_entrega` (`dia_entrega`),
  KEY `idx_status` (`status`),
  KEY `idx_fecha_hora_reportada` (`fecha_hora_reportada`),
  CONSTRAINT `fk_compra_local_producto` FOREIGN KEY (`id_producto_presentacion`) REFERENCES `producto_presentacion` (`id`),
  CONSTRAINT `fk_compra_local_sucursal` FOREIGN KEY (`codigo_sucursal`) REFERENCES `sucursales` (`codigo`),
  CONSTRAINT `fk_compra_local_usuario_creacion` FOREIGN KEY (`usuario_creacion`) REFERENCES `Operarios` (`CodOperario`),
  CONSTRAINT `fk_compra_local_usuario_modificacion` FOREIGN KEY (`usuario_modificacion`) REFERENCES `Operarios` (`CodOperario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuración de despachos y pedidos de productos a sucursales';
