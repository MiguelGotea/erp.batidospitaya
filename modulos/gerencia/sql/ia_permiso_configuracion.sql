-- Registro de la nueva herramienta y permisos para Configuración de APIs de IA
-- Archivo: modulos/gerencia/sql/ia_permiso_configuracion.sql

-- 1. Registrar la herramienta en tools_erp
INSERT IGNORE INTO tools_erp (nombre, titulo, tipo_componente, grupo, descripcion, url_real, icono)
VALUES ('configuracion_ia_provedores', 'Configuración de APIs IA', 'herramienta', 'Gerencia', 'Gestión centralizada de llaves y motores de Inteligencia Artificial', 'modulos/gerencia/ia_config_api.php', 'bi-robot');

-- Obtener el ID de la herramienta insertada
SET @tool_id = (SELECT id FROM tools_erp WHERE nombre = 'configuracion_ia_provedores' LIMIT 1);

-- 2. Registrar la acción obligatoria 'vista'
INSERT IGNORE INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion)
VALUES (@tool_id, 'vista', 'Permite ver y gestionar la configuración de APIs');

-- Obtener el ID de la acción insertada
SET @accion_id = (SELECT id FROM acciones_tools_erp WHERE tool_erp_id = @tool_id AND nombre_accion = 'vista' LIMIT 1);

-- 3. Asignar permiso 'allow' a cargos administrativos por defecto
-- 16: Gerencia General
-- 15: Líder de TI
-- 49: Gerencia Proyectos
INSERT IGNORE INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso) VALUES
(@accion_id, 16, 'allow'),
(@accion_id, 15, 'allow'),
(@accion_id, 49, 'allow');
