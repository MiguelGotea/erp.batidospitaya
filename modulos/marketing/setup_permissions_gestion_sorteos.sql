-- Script SQL para configurar permisos de gestion_sorteos
-- Ejecutar en la base de datos después de crear el módulo

-- 1. Insertar la herramienta en tools_erp con TODOS los campos requeridos
INSERT INTO tools_erp (
    nombre,
    titulo,
    tipo_componente,
    grupo,
    descripcion,
    url_real,
    url_alias,
    icono,
    orden,
    activo
)
VALUES (
    'gestion_sorteos',
    'Gestión Sorteos',
    'herramienta',
    'marketing',
    'Gestión de registros del sorteo Pitaya Love',
    '/modulos/marketing/gestion_sorteos.php',
    'gestion-sorteos',
    'fas fa-gift',
    10,
    1
)
ON DUPLICATE KEY UPDATE 
    titulo = 'Gestión Sorteos',
    descripcion = 'Gestión de registros del sorteo Pitaya Love',
    url_real = '/modulos/marketing/gestion_sorteos.php',
    url_alias = 'gestion-sorteos',
    icono = 'fas fa-gift';

-- 2. Obtener el ID de la herramienta
SET @tool_id = (SELECT id FROM tools_erp WHERE nombre = 'gestion_sorteos' LIMIT 1);

-- 3. Insertar las acciones para esta herramienta
INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion)
VALUES 
    (@tool_id, 'vista', 'Ver registros de sorteos'),
    (@tool_id, 'edicion', 'Eliminar registros de sorteos')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- 4. Obtener IDs de las acciones
SET @accion_vista_id = (SELECT id FROM acciones_tools_erp WHERE tool_erp_id = @tool_id AND nombre_accion = 'vista' LIMIT 1);
SET @accion_edicion_id = (SELECT id FROM acciones_tools_erp WHERE tool_erp_id = @tool_id AND nombre_accion = 'edicion' LIMIT 1);

-- 5. Configurar permisos para TODOS los cargos (vista)
-- Permitir vista a todos los cargos (1-100)
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
SELECT @accion_vista_id, CodNivelesCargos, 'allow'
FROM NivelesCargos
WHERE CodNivelesCargos BETWEEN 1 AND 100
ON DUPLICATE KEY UPDATE permiso = 'allow';

-- 6. Configurar permisos de EDICIÓN solo para gerencia/admin (cargos 1-10)
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
SELECT @accion_edicion_id, CodNivelesCargos, 'allow'
FROM NivelesCargos
WHERE CodNivelesCargos BETWEEN 1 AND 10
ON DUPLICATE KEY UPDATE permiso = 'allow';

-- Verificar que se crearon correctamente
SELECT 
    t.nombre as herramienta,
    t.titulo,
    t.grupo,
    t.url_real,
    a.nombre_accion as accion,
    COUNT(p.id) as cargos_con_permiso
FROM tools_erp t
INNER JOIN acciones_tools_erp a ON t.id = a.tool_erp_id
LEFT JOIN permisos_tools_erp p ON a.id = p.accion_tool_erp_id AND p.permiso = 'allow'
WHERE t.nombre = 'gestion_sorteos'
GROUP BY t.nombre, t.titulo, t.grupo, t.url_real, a.nombre_accion;
