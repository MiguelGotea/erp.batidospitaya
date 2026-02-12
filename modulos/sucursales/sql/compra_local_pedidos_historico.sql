-- =====================================================
-- Script: Crear tabla de pedidos históricos
-- Descripción: Almacena el historial completo de pedidos
--              con fechas específicas de entrega
-- =====================================================

CREATE TABLE IF NOT EXISTS compra_local_pedidos_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_producto_presentacion INT NOT NULL COMMENT 'FK a producto_presentacion',
    codigo_sucursal VARCHAR(10) NOT NULL COMMENT 'FK a sucursales',
    fecha_entrega DATE NOT NULL COMMENT 'Fecha específica de entrega del pedido',
    cantidad_pedido DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'Cantidad solicitada',
    usuario_registro INT COMMENT 'Usuario que registró el pedido',
    fecha_hora_reportada TIMESTAMP NULL COMMENT 'Última modificación del pedido',
    notas TEXT COMMENT 'Notas adicionales sobre el pedido',
    
    -- Constraints
    UNIQUE KEY unique_pedido (id_producto_presentacion, codigo_sucursal, fecha_entrega),
    FOREIGN KEY (id_producto_presentacion) REFERENCES producto_presentacion(id) ON DELETE CASCADE,
    FOREIGN KEY (codigo_sucursal) REFERENCES sucursales(codigo) ON DELETE CASCADE,
    FOREIGN KEY (usuario_registro) REFERENCES Operarios(CodOperario) ON DELETE SET NULL,
    
    -- Indexes
    INDEX idx_producto (id_producto_presentacion),
    INDEX idx_sucursal (codigo_sucursal),
    INDEX idx_fecha_entrega (fecha_entrega),
    INDEX idx_fecha_reportada (fecha_hora_reportada),
    INDEX idx_producto_sucursal (id_producto_presentacion, codigo_sucursal),
    INDEX idx_sucursal_fecha (codigo_sucursal, fecha_entrega),
    INDEX idx_producto_fecha (id_producto_presentacion, fecha_entrega)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Historial completo de pedidos con fechas específicas de entrega';
