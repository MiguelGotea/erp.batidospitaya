-- ============================================================
-- SQL: Registro de herramienta y permisos
-- Herramienta: dashboard_consumo_insumos
-- Módulo: productos
-- ============================================================

-- 1. Insertar la herramienta en tools_erp
INSERT INTO `tools_erp` (`nombre_herramienta`, `descripcion`, `ruta`, `modulo`, `icono`, `activo`)
VALUES (
    'dashboard_consumo_insumos',
    'Dashboard profesional de análisis de consumo histórico, proyección y planificación de insumos. Traduce ventas Access al sistema ERP.',
    '/modulos/productos/dashboard_consumo.php',
    'productos',
    'fas fa-chart-bar',
    1
);

-- 2. Insertar las acciones para la herramienta
-- Acción: vista
INSERT INTO `acciones_tools_erp` (`tool_erp_id`, `nombre_accion`, `descripcion`)
SELECT id, 'vista', 'Acceso general al dashboard de consumo de insumos'
FROM `tools_erp` WHERE `nombre_herramienta` = 'dashboard_consumo_insumos';

-- Acción: exportar_consumo
INSERT INTO `acciones_tools_erp` (`tool_erp_id`, `nombre_accion`, `descripcion`)
SELECT id, 'exportar_consumo', 'Permite exportar los datos de consumo e insumos a CSV'
FROM `tools_erp` WHERE `nombre_herramienta` = 'dashboard_consumo_insumos';

-- ============================================================
-- VERIFICACIÓN
-- ============================================================
SELECT t.id, t.nombre_herramienta, a.nombre_accion, a.descripcion
FROM tools_erp t
INNER JOIN acciones_tools_erp a ON a.tool_erp_id = t.id
WHERE t.nombre_herramienta = 'dashboard_consumo_insumos';
