-- SQL para nueva acción de visibilidad consolidada
-- Permite ver solicitudes aprobadas, en proceso y completadas sin depender de cargo 9 harcodeado

-- 1. Insertar la nueva acción "ver_todo_aprobadas"
INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion)
SELECT id, 'ver_todo_aprobadas' 
FROM tools_erp 
WHERE nombre = 'historial_solicitudes_cotizacion'
LIMIT 1;

-- 2. Asignar el permiso 'allow' al cargo de Compras (Cargo 9) por defecto
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
SELECT a.id, 9, 'allow'
FROM acciones_tools_erp a
INNER JOIN tools_erp t ON a.tool_erp_id = t.id
WHERE t.nombre = 'historial_solicitudes_cotizacion' AND a.nombre_accion = 'ver_todo_aprobadas'
LIMIT 1;
