-- SQL para registrar la nueva herramienta: Monitor de Conexión
-- Tabla: tools_erp

INSERT INTO tools_erp (nombre, titulo, grupo, descripcion, url_real, icono, orden, tipo_componente, activo)
VALUES ('conexion_monitor', 'Monitor de Conexión', 'Sistemas', 'Monitor en tiempo real de la conexión de los equipos de sucursales.', 'modulos/sistemas/conexion_monitor.php', 'fas fa-satellite-dish', 10, 'herramienta', 1);

-- Obtener el ID insertado (en MySQL sería LAST_INSERT_ID())
SET @tool_id = LAST_INSERT_ID();

-- Registrar la acción 'vista' para la herramienta
INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion)
VALUES (@tool_id, 'vista', 'Ver el monitor de conexión');

SET @accion_id = LAST_INSERT_ID();

-- Asignar permiso de 'vista' a roles administrativos
-- Nota: Ajustar CodNivelesCargos según la base de datos (ej: 1 para Administrador, 2 para Sistemas)
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
SELECT @accion_id, CodNivelesCargos, 'allow'
FROM NivelesCargos
WHERE Nombre LIKE '%Administrador%' OR Nombre LIKE '%Sistemas%';
