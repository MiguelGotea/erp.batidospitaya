-- Migrar todas las solicitudes en proceso a aprobadas
UPDATE solicitudes_cotizacion 
SET estado = 'aprobada' 
WHERE estado = 'en_proceso';

-- Nota: El usuario se encargará de modificar la estructura de la tabla (ENUM) si es necesario.
