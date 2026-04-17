-- ============================================================
-- SETUP: Dashboard Accionistas — Batidos Pitaya
-- Ejecutar en la BD del ERP
-- ============================================================

-- 1. Registrar la herramienta en tools_erp
INSERT INTO tools_erp (nombre, titulo, tipo_componente, grupo, descripcion, url_real, url_alias, icono, orden, activo)
VALUES (
    'dashboard_accionistas',
    'Dashboard Accionistas',
    'herramienta',
    'gerencia',
    'Panel ejecutivo estratégico para mesa de socios y accionistas de Batidos Pitaya',
    '/modulos/gerencia/dashboard_accionistas.php',
    'dashboard-accionistas',
    'fas fa-crown',
    1,
    1
)
ON DUPLICATE KEY UPDATE
    titulo      = 'Dashboard Accionistas',
    descripcion = 'Panel ejecutivo estratégico para mesa de socios y accionistas de Batidos Pitaya',
    url_real    = '/modulos/gerencia/dashboard_accionistas.php',
    icono       = 'fas fa-crown',
    activo      = 1;

-- 2. Acción: vista (requerida para acceder)
INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion)
SELECT id, 'vista', 'Ver el dashboard de accionistas'
FROM tools_erp WHERE nombre = 'dashboard_accionistas'
ON DUPLICATE KEY UPDATE descripcion = 'Ver el dashboard de accionistas';

-- 3. Permisos — Asignar a cargos gerenciales
-- (Ajustar CodNivelesCargos según los cargos de los accionistas reales)
-- 16 = Gerencia General, 49 = Gerencia Proyectos, 42 = Gerente Marketing, 11 = Jefe Operaciones, 15 = Lider TI
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
SELECT a.id, c.cod, 'allow'
FROM acciones_tools_erp a
JOIN tools_erp t ON a.tool_erp_id = t.id
CROSS JOIN (
    SELECT 16 AS cod UNION SELECT 49 UNION SELECT 42 UNION SELECT 11 UNION SELECT 15
) c
WHERE t.nombre = 'dashboard_accionistas' AND a.nombre_accion = 'vista'
ON DUPLICATE KEY UPDATE permiso = 'allow';

-- ============================================================
-- VERIFICAR: las tablas usadas deben existir
-- ============================================================
-- VentasGlobalesAccessCSV  ✓ (documentada en esquema)
-- clientesclub             ✓ (documentada en esquema)  
-- sucursales               ✓ (documentada en esquema)
-- ventas_meta              ✓ (usada en ventas_meta.php)
-- SemanasSistema           ✓ (documentada en esquema)
-- ============================================================
