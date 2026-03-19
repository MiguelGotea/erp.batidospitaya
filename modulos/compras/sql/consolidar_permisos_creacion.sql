-- SQL para consolidar la creación de solicitudes en el historial
-- Elimina la herramienta redundante id 15 (solicitud_cotizacion)

-- 1. Asegurar que la acción "boton_nueva" existe en historial_solicitudes_cotizacion
INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion)
SELECT t.id, 'boton_nueva'
FROM tools_erp t
WHERE t.nombre = 'historial_solicitudes_cotizacion'
AND NOT EXISTS (
    SELECT 1 FROM acciones_tools_erp a 
    WHERE a.tool_erp_id = t.id AND a.nombre_accion = 'boton_nueva'
);

-- 2. Migrar los permisos de la herramienta 15 (vista) a la acción boton_nueva
-- Se utiliza INSERT IGNORE para evitar duplicados si ya existen permisos
INSERT IGNORE INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
SELECT 
    (SELECT a.id FROM acciones_tools_erp a INNER JOIN tools_erp t ON a.tool_erp_id = t.id WHERE t.nombre = 'historial_solicitudes_cotizacion' AND a.nombre_accion = 'boton_nueva' LIMIT 1),
    p.CodNivelesCargos,
    p.permiso
FROM permisos_tools_erp p
INNER JOIN acciones_tools_erp a ON p.accion_tool_erp_id = a.id
WHERE a.tool_erp_id = 15 AND a.nombre_accion = 'vista';

-- 3. Borrar rastro de la herramienta 15 (solicitud_cotizacion)
-- Primero borramos los permisos asociados a sus acciones
DELETE p FROM permisos_tools_erp p
INNER JOIN acciones_tools_erp a ON p.accion_tool_erp_id = a.id
WHERE a.tool_erp_id = 15;

-- Luego borramos las acciones de la herramienta 15
DELETE FROM acciones_tools_erp WHERE tool_erp_id = 15;

-- Finalmente borramos la herramienta 15
DELETE FROM tools_erp WHERE id = 15;
