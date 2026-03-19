-- Setup permissions for Maintenance Module
-- Tool: historial_solicitudes_mantenimiento
-- Actions: nuevo_registro, vista_todas_sucursales

-- 1. Ensure the tool exists
INSERT IGNORE INTO tools_erp (nombre, descripcion, icono, folder, activo)
VALUES ('historial_solicitudes_mantenimiento', 'Historial y Solicitudes de Mantenimiento', 'bi-tools', 'mantenimiento', 1);

-- 2. Ensure actions exist for this tool
INSERT IGNORE INTO acciones_tools_erp (tool_id, nombre, descripcion)
SELECT id, 'nuevo_registro', 'Crear nuevas solicitudes de mantenimiento o equipos'
FROM tools_erp WHERE nombre = 'historial_solicitudes_mantenimiento';

INSERT IGNORE INTO acciones_tools_erp (tool_id, nombre, descripcion)
SELECT id, 'vista_todas_sucursales', 'Ver y filtrar solicitudes de todas las sucursales'
FROM tools_erp WHERE nombre = 'historial_solicitudes_mantenimiento';

-- 3. Grant permissions to relevant roles
-- Roles to grant 'nuevo_registro': 
-- 5 (Líder), 43 (Líder), 46 (Líder Aux), 12 (Producción), 14 (Mantenimiento), 19 (CDS), 35 (Infraestructura)
INSERT IGNORE INTO permisos_tools_erp (tool_id, accion_id, cod_nivel_cargo)
SELECT t.id, a.id, c.CodNivelesCargos
FROM tools_erp t
JOIN acciones_tools_erp a ON t.id = a.tool_id
CROSS JOIN (SELECT CodNivelesCargos FROM NivelesCargos WHERE CodNivelesCargos IN (5, 43, 46, 12, 14, 19, 35)) c
WHERE t.nombre = 'historial_solicitudes_mantenimiento' 
AND a.nombre = 'nuevo_registro';

-- Roles to grant 'vista_todas_sucursales':
-- 14 (Mantenimiento), 35 (Infraestructura)
INSERT IGNORE INTO permisos_tools_erp (tool_id, accion_id, cod_nivel_cargo)
SELECT t.id, a.id, c.CodNivelesCargos
FROM tools_erp t
JOIN acciones_tools_erp a ON t.id = a.tool_id
CROSS JOIN (SELECT CodNivelesCargos FROM NivelesCargos WHERE CodNivelesCargos IN (14, 35)) c
WHERE t.nombre = 'historial_solicitudes_mantenimiento' 
AND a.nombre = 'vista_todas_sucursales';
