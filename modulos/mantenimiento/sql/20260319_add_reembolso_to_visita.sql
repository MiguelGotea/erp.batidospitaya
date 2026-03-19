-- Migración: Integración de Agenda de Mantenimiento con Reembolsos
-- Fecha: 2026-03-19

-- 1. Agregar columna para rastrear el reembolso en cada visita
ALTER TABLE mtto_informe_visitas 
ADD COLUMN reembolso_id INT NULL DEFAULT NULL 
AFTER fecha_hora_regsys;

-- 2. Registrar la nueva acción 'generar_reembolso' para la herramienta 'agenda_mantenimiento'
INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion)
SELECT id, 'generar_reembolso', 'Permite generar reembolsos por visita desde informes finalizados'
FROM tools_erp 
WHERE nombre = 'agenda_mantenimiento'
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- 3. Otorgar permiso automáticamente al cargo con ID 1 (Administrador/Gerencia)
-- Se usa un subquery para encontrar el ID de la acción recién creada
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
SELECT a.id, 1, 'allow'
FROM acciones_tools_erp a
JOIN tools_erp t ON a.tool_erp_id = t.id
WHERE t.nombre = 'agenda_mantenimiento' AND a.nombre_accion = 'generar_reembolso'
ON DUPLICATE KEY UPDATE permiso = 'allow';
