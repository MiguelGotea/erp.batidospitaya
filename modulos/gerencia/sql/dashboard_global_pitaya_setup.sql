-- ============================================================
-- SETUP: Dashboard Global Pitaya
-- Ejecutar en la BD del ERP
-- ============================================================

-- 1. Registrar la herramienta en tools_erp
INSERT INTO tools_erp (nombre, titulo, tipo_componente, grupo, descripcion, url_real, url_alias, icono, orden, activo)
VALUES (
    'dashboard_global_pitaya',
    'Dashboard Global Pitaya',
    'herramienta',
    'gerencia',
    'Centro de inteligencia global: ventas, club, expansión y KPIs estratégicos de Batidos Pitaya',
    '/modulos/gerencia/dashboard_global_pitaya.php',
    'dashboard-global',
    'fas fa-chart-network',
    1,
    1
)
ON DUPLICATE KEY UPDATE
    titulo      = 'Dashboard Global Pitaya',
    descripcion = 'Centro de inteligencia global: ventas, club, expansión y KPIs estratégicos de Batidos Pitaya',
    url_real    = '/modulos/gerencia/dashboard_global_pitaya.php',
    icono       = 'fas fa-chart-network',
    activo      = 1;

-- 2. Acción: vista
INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion)
SELECT id, 'vista', 'Ver el dashboard global de Pitaya'
FROM tools_erp WHERE nombre = 'dashboard_global_pitaya'
ON DUPLICATE KEY UPDATE descripcion = 'Ver el dashboard global de Pitaya';

-- 3. Permisos por cargo (ajustar CodNivelesCargos según roles reales)
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
SELECT a.id, c.cod, 'allow'
FROM acciones_tools_erp a
JOIN tools_erp t ON a.tool_erp_id = t.id
CROSS JOIN (
    SELECT 16 AS cod UNION SELECT 49 UNION SELECT 42 UNION SELECT 11 UNION SELECT 15
) c
WHERE t.nombre = 'dashboard_global_pitaya' AND a.nombre_accion = 'vista'
ON DUPLICATE KEY UPDATE permiso = 'allow';

-- 4. Verificar columna Fecha_Apertura en sucursales
-- (Debe existir; si no, crearla)
-- ALTER TABLE sucursales ADD COLUMN Fecha_Apertura DATE NULL AFTER activa;

-- 5. Tablas requeridas
-- VentasGlobalesAccessCSV   ✓
-- clientesclub              ✓
-- sucursales (Fecha_Apertura) ✓
-- ventas_meta               ✓
-- ============================================================
