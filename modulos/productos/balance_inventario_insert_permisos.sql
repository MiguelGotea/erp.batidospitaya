-- ============================================================
-- Registrar herramienta: balance_inventario_access_host
-- Ejecutar una sola vez en la BD del ERP
-- ============================================================

-- 1. Insertar la herramienta
INSERT IGNORE INTO tools_erp (nombre, descripcion)
VALUES ('balance_inventario_access_host', 'Balance Semanal de Existencias — Kardex Access vs Consumo Teórico');

-- 2. Insertar la acción "vista"
INSERT IGNORE INTO acciones_tools_erp (tool_erp_id, nombre_accion)
SELECT id, 'vista'
FROM tools_erp
WHERE nombre = 'balance_inventario_access_host';

-- 3. Dar permiso al rol administrador (ajustar CodNivelesCargos según tu sistema)
-- Ejemplo: CodNivelesCargos = 1 (Administrador)
INSERT IGNORE INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
SELECT a.id, 1, 'allow'
FROM acciones_tools_erp a
INNER JOIN tools_erp t ON t.id = a.tool_erp_id
WHERE t.nombre = 'balance_inventario_access_host'
  AND a.nombre_accion = 'vista';

-- Consulta de verificación:
-- SELECT t.nombre, a.nombre_accion, p.CodNivelesCargos, p.permiso
-- FROM tools_erp t
-- JOIN acciones_tools_erp a ON a.tool_erp_id = t.id
-- LEFT JOIN permisos_tools_erp p ON p.accion_tool_erp_id = a.id
-- WHERE t.nombre = 'balance_inventario_access_host';
