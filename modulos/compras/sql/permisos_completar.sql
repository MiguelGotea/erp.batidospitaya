-- SQL para registrar la nueva acción "completar" y asignar permiso al cargo de Compras (Cargo 9)
-- Herramienta: historial_solicitudes_cotizacion

-- 1. Insertar la nueva acción (se utiliza subconsulta para obtener el id de la herramienta)
INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion)
SELECT id, 'completar' 
FROM tools_erp 
WHERE nombre = 'historial_solicitudes_cotizacion'
LIMIT 1;

-- 2. Asignar el permiso 'allow' al cargo de Compras (Cargo 9)
-- Usamos LAST_INSERT_ID() asumiendo que el insert anterior fue exitoso
-- O podemos usar una subconsulta más robusta para el id de la acción recién creada
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
SELECT a.id, 9, 'allow'
FROM acciones_tools_erp a
INNER JOIN tools_erp t ON a.tool_erp_id = t.id
WHERE t.nombre = 'historial_solicitudes_cotizacion' AND a.nombre_accion = 'completar'
LIMIT 1;
