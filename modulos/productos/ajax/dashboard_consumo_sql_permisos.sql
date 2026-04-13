-- ============================================================
-- SQL: Registro de herramienta y permisos
-- Dashboard Consumo de Insumos
--
-- Estructura real de las tablas:
--   tools_erp          : id, nombre, titulo, tipo_componente, grupo,
--                        descripcion, url_real, url_alias, icono, orden, activo
--   acciones_tools_erp : id, tool_erp_id, nombre_accion, descripcion
--   permisos_tools_erp : id, accion_tool_erp_id, CodNivelesCargos, permiso
-- ============================================================

-- ── 1. Insertar la herramienta en tools_erp ───────────────
INSERT INTO `tools_erp` (
    `nombre`,
    `titulo`,
    `tipo_componente`,
    `grupo`,
    `descripcion`,
    `url_real`,
    `url_alias`,
    `icono`,
    `orden`,
    `activo`
) VALUES (
    'dashboard_consumo_insumos',                           -- nombre (clave interna usada en tienePermiso())
    'Análisis de Consumo de Insumos',                      -- titulo visible en el menú
    'herramienta',                                         -- tipo_componente
    'productos',                                           -- grupo / módulo
    'Dashboard de consumo histórico, proyección y planificación de insumos. Traduce ventas Access al ERP.',
    '/modulos/productos/dashboard_consumo.php',            -- url_real
    NULL,                                                  -- url_alias
    'fas fa-chart-bar',                                    -- icono FontAwesome
    0,                                                     -- orden
    1                                                      -- activo
);

-- ── 2. Insertar acción: vista ─────────────────────────────
INSERT INTO `acciones_tools_erp` (`tool_erp_id`, `nombre_accion`, `descripcion`)
SELECT id, 'vista', 'Acceso general al dashboard de consumo de insumos'
FROM `tools_erp`
WHERE `nombre` = 'dashboard_consumo_insumos'
LIMIT 1;

-- ── 3. Insertar acción: exportar_consumo ─────────────────
INSERT INTO `acciones_tools_erp` (`tool_erp_id`, `nombre_accion`, `descripcion`)
SELECT id, 'exportar_consumo', 'Permite exportar el análisis de consumo e insumos a CSV'
FROM `tools_erp`
WHERE `nombre` = 'dashboard_consumo_insumos'
LIMIT 1;

-- ── 4. Asignar permisos por cargo ─────────────────────────
--  permit='allow'  a los cargos que deben ver / exportar.
--  Ajustar CodNivelesCargos según los cargos reales del sistema.
--
--  Referencia de cargos frecuentes:
--    9  = Analista de Compras
--   10  = Jefe de Logística
--   12  = Jefe de Producción
--   15  = Líder de TI
--   16  = Gerencia General
--   17  = Jefe de Almacén
--   19  = Jefe de CDS

-- Permiso: VISTA (accion_tool_erp_id = id del insert de 'vista')
INSERT INTO `permisos_tools_erp` (`accion_tool_erp_id`, `CodNivelesCargos`, `permiso`)
SELECT
    a.id,
    c.CodNivelesCargos,
    'allow'
FROM `acciones_tools_erp` a
CROSS JOIN (
    SELECT 9  AS CodNivelesCargos UNION ALL
    SELECT 10 UNION ALL
    SELECT 12 UNION ALL
    SELECT 15 UNION ALL
    SELECT 16 UNION ALL
    SELECT 17 UNION ALL
    SELECT 19
) c
WHERE a.nombre_accion = 'vista'
  AND a.tool_erp_id = (SELECT id FROM tools_erp WHERE nombre = 'dashboard_consumo_insumos' LIMIT 1);

-- Permiso: EXPORTAR_CONSUMO (solo para cargos con gestión de compras/logística/gerencia)
INSERT INTO `permisos_tools_erp` (`accion_tool_erp_id`, `CodNivelesCargos`, `permiso`)
SELECT
    a.id,
    c.CodNivelesCargos,
    'allow'
FROM `acciones_tools_erp` a
CROSS JOIN (
    SELECT 9  AS CodNivelesCargos UNION ALL
    SELECT 10 UNION ALL
    SELECT 16 UNION ALL
    SELECT 17 UNION ALL
    SELECT 19
) c
WHERE a.nombre_accion = 'exportar_consumo'
  AND a.tool_erp_id = (SELECT id FROM tools_erp WHERE nombre = 'dashboard_consumo_insumos' LIMIT 1);

-- ── 5. Verificación ───────────────────────────────────────
SELECT
    t.id                AS tool_id,
    t.nombre            AS nombre_herramienta,
    t.titulo,
    t.grupo,
    a.id                AS accion_id,
    a.nombre_accion,
    COUNT(p.id)         AS num_permisos_asignados
FROM tools_erp t
INNER JOIN acciones_tools_erp a ON a.tool_erp_id = t.id
LEFT  JOIN permisos_tools_erp p ON p.accion_tool_erp_id = a.id
WHERE t.nombre = 'dashboard_consumo_insumos'
GROUP BY t.id, t.nombre, t.titulo, t.grupo, a.id, a.nombre_accion;
