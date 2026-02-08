-- Script de configuración de permisos para Gestión de Sorteos
-- Ejecutar este script para configurar los permisos de la herramienta

-- 1. Registrar la herramienta en tools_erp
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
    icono = 'fas fa-gift',
    orden = 10,
    activo = 1;

-- 2. Crear acciones para la herramienta
INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion)
SELECT id, 'vista', 'Ver registros de sorteos'
FROM tools_erp WHERE nombre = 'gestion_sorteos'
ON DUPLICATE KEY UPDATE descripcion = 'Ver registros de sorteos';

INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion)
SELECT id, 'edicion', 'Eliminar registros de sorteos'
FROM tools_erp WHERE nombre = 'gestion_sorteos'
ON DUPLICATE KEY UPDATE descripcion = 'Eliminar registros de sorteos';

-- 3. Asignar permisos de VISTA a todos los cargos
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
SELECT ate.id, nc.CodNivelesCargos, 'allow'
FROM acciones_tools_erp ate
JOIN tools_erp te ON ate.tool_erp_id = te.id
CROSS JOIN NivelesCargos nc
WHERE te.nombre = 'gestion_sorteos'
  AND ate.nombre_accion = 'vista'
ON DUPLICATE KEY UPDATE permiso = 'allow';

-- 4. Asignar permisos de EDICIÓN solo a gerencia y administración
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
SELECT ate.id, nc.CodNivelesCargos, 'allow'
FROM acciones_tools_erp ate
JOIN tools_erp te ON ate.tool_erp_id = te.id
JOIN NivelesCargos nc ON nc.Nombre IN ('Gerencia', 'Administracion', 'Administración')
WHERE te.nombre = 'gestion_sorteos'
  AND ate.nombre_accion = 'edicion'
ON DUPLICATE KEY UPDATE permiso = 'allow';

-- 5. Verificar configuración
SELECT 
    te.nombre,
    te.titulo,
    te.grupo,
    te.url_real,
    ate.nombre_accion,
    nc.Nombre as cargo,
    pte.permiso
FROM tools_erp te
JOIN acciones_tools_erp ate ON ate.tool_erp_id = te.id
JOIN permisos_tools_erp pte ON pte.accion_tool_erp_id = ate.id
JOIN NivelesCargos nc ON nc.CodNivelesCargos = pte.CodNivelesCargos
WHERE te.nombre = 'gestion_sorteos'
ORDER BY ate.nombre_accion, nc.Nombre;
