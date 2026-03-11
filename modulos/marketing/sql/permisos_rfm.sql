-- Registro de la herramienta Dashboard RFM en el sistema
-- Este script registra la página y el permiso base de 'vista'

-- 1. Registro en tools_erp
INSERT INTO tools_erp (nombre, titulo, tipo_componente, grupo, descripcion, url_real, url_alias, icono, orden, activo)
VALUES (
    'dashboard_rfm',
    'Dashboard RFM',
    'indicador',
    'marketing',
    'Análisis de segmentación RFM y hábitos de clientes club',
    '/modulos/marketing/dashboard_rfm.php',
    'dashboard-rfm',
    'fas fa-chart-pie',
    15,
    1
)
ON DUPLICATE KEY UPDATE 
    titulo = VALUES(titulo),
    descripcion = VALUES(descripcion),
    url_real = VALUES(url_real);

-- 2. Registro de la acción 'vista'
INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion)
SELECT id, 'vista', 'Permitir ver el dashboard' FROM tools_erp WHERE nombre = 'dashboard_rfm'
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- 3. Asignación de permisos (Por defecto a Gerencia General CodNivelesCargos = 16)
-- Puedes añadir más cargos repitiendo el INSERT con otros IDs de CodNivelesCargos
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
SELECT a.id, 16, 'allow' 
FROM acciones_tools_erp a
JOIN tools_erp t ON a.tool_erp_id = t.id
WHERE t.nombre = 'dashboard_rfm' AND a.nombre_accion = 'vista'
ON DUPLICATE KEY UPDATE permiso = 'allow';

-- Otros cargos sugeridos (Marketing = 49, Operaciones = 11, etc.)
-- INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
-- SELECT a.id, 11, 'allow' FROM acciones_tools_erp a JOIN tools_erp t ON a.tool_erp_id = t.id WHERE t.nombre = 'dashboard_rfm' AND a.nombre_accion = 'vista';
