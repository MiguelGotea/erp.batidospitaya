-- ============================================================
-- Registrar herramienta: balance_inventario_access_host
-- Ejecutar una sola vez en la BD del ERP
-- Estructura real de tools_erp:
--   nombre, titulo, tipo_componente, class_name, config_json,
--   grupo, descripcion, url_real, url_alias, icono, orden, activo
-- ============================================================

-- 1. Insertar la herramienta con todos los campos requeridos
INSERT IGNORE INTO tools_erp
    (nombre, titulo, tipo_componente, grupo, descripcion, url_real, icono, orden, activo)
VALUES (
    'balance_inventario_access_host',
    'Balance de Inventario',
    'herramienta',
    'Productos',
    'Balance semanal de existencias: Kardex Access vs Consumo Teórico por sucursal',
    '/modulos/productos/balance_inventario_access_host.php',
    'fas fa-balance-scale',
    100,
    1
);

-- 2. Insertar la acción "vista"
INSERT IGNORE INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion)
SELECT id, 'vista', 'Ver el balance semanal de inventario'
FROM tools_erp
WHERE nombre = 'balance_inventario_access_host';

-- 3. Dar permisos por cargo (ajustar CodNivelesCargos según los cargos de tu sistema)
-- Consulta primero los cargos con: SELECT CodNivelesCargos, NombreNivel FROM NivelesCargos;
-- Ejemplo: dar acceso a todos los cargos que necesiten verlo
INSERT IGNORE INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
SELECT a.id, nc.CodNivelesCargos, 'allow'
FROM acciones_tools_erp a
INNER JOIN tools_erp t ON t.id = a.tool_erp_id
CROSS JOIN NivelesCargos nc          -- Ajustar si quieres filtrar por cargo específico
WHERE t.nombre = 'balance_inventario_access_host'
  AND a.nombre_accion = 'vista';
  -- Agrega: AND nc.CodNivelesCargos IN (1, 2, 5)  para cargos específicos

-- ─────────────────────────────────────────────
-- Consulta de verificación:
-- ─────────────────────────────────────────────
-- SELECT t.id, t.nombre, t.titulo, t.grupo, t.url_real,
--        a.nombre_accion, p.CodNivelesCargos, p.permiso
-- FROM tools_erp t
-- JOIN acciones_tools_erp a ON a.tool_erp_id = t.id
-- LEFT JOIN permisos_tools_erp p ON p.accion_tool_erp_id = a.id
-- WHERE t.nombre = 'balance_inventario_access_host'
-- ORDER BY p.CodNivelesCargos;

