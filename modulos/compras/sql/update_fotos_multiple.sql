-- Script de actualización para múltiples fotos en solicitudes de cotización
-- Fecha: 2026-03-18

CREATE TABLE IF NOT EXISTS `solicitudes_cotizacion_fotos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `producto_id` INT NOT NULL,
    `foto_nombre` VARCHAR(255) NOT NULL,
    `fecha_subida` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_foto_producto` FOREIGN KEY (`producto_id`) 
        REFERENCES `solicitudes_cotizacion_productos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Nota: La columna foto_referencia en solicitudes_cotizacion_productos 
-- puede mantenerse para compatibilidad o como 'foto principal', 
-- pero el sistema ahora priorizará esta tabla.
