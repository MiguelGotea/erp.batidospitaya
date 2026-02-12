-- =====================================================
-- Script: Crear tabla de configuración de despacho
-- Descripción: Almacena qué días están habilitados para 
--              cada producto y sucursal
-- =====================================================

CREATE TABLE IF NOT EXISTS compra_local_configuracion_despacho (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_producto_presentacion INT NOT NULL COMMENT 'FK a producto_presentacion',
    codigo_sucursal VARCHAR(10) NOT NULL COMMENT 'FK a sucursales',
    dia_entrega TINYINT NOT NULL COMMENT '1=Lun, 2=Mar, 3=Mié, 4=Jue, 5=Vie, 6=Sáb, 7=Dom',
    status ENUM('activo', 'inactivo') DEFAULT 'activo' COMMENT 'Estado de la configuración',
    usuario_creacion INT COMMENT 'Usuario que creó el registro',
    usuario_modificacion INT COMMENT 'Usuario que modificó el registro',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Constraints
    UNIQUE KEY unique_config (id_producto_presentacion, codigo_sucursal, dia_entrega),
    FOREIGN KEY (id_producto_presentacion) REFERENCES producto_presentacion(id) ON DELETE CASCADE,
    FOREIGN KEY (codigo_sucursal) REFERENCES sucursales(codigo) ON DELETE CASCADE,
    FOREIGN KEY (usuario_creacion) REFERENCES Operarios(CodOperario) ON DELETE SET NULL,
    FOREIGN KEY (usuario_modificacion) REFERENCES Operarios(CodOperario) ON DELETE SET NULL,
    
    -- Indexes
    INDEX idx_producto (id_producto_presentacion),
    INDEX idx_sucursal (codigo_sucursal),
    INDEX idx_dia (dia_entrega),
    INDEX idx_status (status),
    INDEX idx_producto_sucursal (id_producto_presentacion, codigo_sucursal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Configuración de días de entrega habilitados por producto y sucursal';
