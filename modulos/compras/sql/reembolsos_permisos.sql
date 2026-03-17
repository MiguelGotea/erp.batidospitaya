-- 
-- Registro de Herramienta y Permisos: REEMBOLSOS
-- Ubicación: /modulos/compras/sql/reembolsos_permisos.sql
--

-- 1. Registrar la herramienta en tools_erp
INSERT INTO tools_erp (nombre, titulo, tipo_componente, grupo, descripcion, url_real, url_alias, icono)
VALUES (
    'reembolsos', 
    'Gestión de Reembolsos', 
    'herramienta', 
    'Compras', 
    'Herramienta con IA para transcripción de facturas y gestión de solicitudes de reembolso.', 
    'modulos/compras/reembolsos_historial.php', 
    'reembolsos', 
    'fas fa-file-invoice-dollar'
);

SET @tool_id = LAST_INSERT_ID();

-- 2. Registrar las acciones disponibles
INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion)
VALUES 
    (@tool_id, 'vista', 'Permite ver el historial de reembolsos'),
    (@tool_id, 'nuevo_registro', 'Permite crear nuevas solicitudes de reembolso'),
    (@tool_id, 'editar', 'Permite modificar solicitudes existentes'),
    (@tool_id, 'eliminar', 'Permite eliminar solicitudes');

-- 3. Asignar permisos básicos (Ejemplo: Gerencia Proyectos (49) y Jefe de Compras (9))
-- Nota: Esto es un ejemplo inicial, se deben asignar según necesidad real.

-- Obtener IDs de las acciones recién creadas
SET @accion_vista = (SELECT id FROM acciones_tools_erp WHERE tool_erp_id = @tool_id AND nombre_accion = 'vista');
SET @accion_nuevo = (SELECT id FROM acciones_tools_erp WHERE tool_erp_id = @tool_id AND nombre_accion = 'nuevo_registro');

-- Permisos para Jefe de Compras (9)
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso) VALUES (@accion_vista, 9, 'allow');
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso) VALUES (@accion_nuevo, 9, 'allow');

-- Permisos para Gerencia Proyectos (49)
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso) VALUES (@accion_vista, 49, 'allow');
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso) VALUES (@accion_nuevo, 49, 'allow');

-- Permisos para Gerencia General (16)
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso) VALUES (@accion_vista, 16, 'allow');
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso) VALUES (@accion_nuevo, 16, 'allow');
