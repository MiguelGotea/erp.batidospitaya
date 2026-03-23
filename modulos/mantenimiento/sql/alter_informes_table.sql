-- C:\Users\migue\Desktop\Sistema\Pitaya Web\VisualCode\erp.batidospitaya.com\modulos\mantenimiento\sql\alter_informes_table.sql

-- 1. Añadir columnas de costo por KM y ID de reembolso a los informes diarios
-- Se coloca después de km_final para mantener orden lógico
ALTER TABLE mtto_informes_diarios 
ADD COLUMN costo_km DECIMAL(10,2) DEFAULT 0.00 AFTER km_final,
ADD COLUMN reembolso_id INT NULL AFTER costo_km;

-- 2. Registrar la nueva acción 'reporte_semanal' para la herramienta de mantenimiento
-- Esto permite configurar qué cargos pueden ver este reporte consolidado
INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion)
SELECT id, 'reporte_semanal', 'Ver y generar reporte semanal de KM y costos consolidado'
FROM tools_erp 
WHERE nombre = 'agenda_mantenimiento'
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- 3. Otorgar permiso por defecto a Gerencia General (CodNivelesCargos = 16) 
-- y Jefe de Operaciones (CodNivelesCargos = 11) como ejemplo inicial.
-- El administrador podrá configurar el resto desde el panel de permisos.
INSERT IGNORE INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
SELECT a.id, 16, 'allow'
FROM acciones_tools_erp a
INNER JOIN tools_erp t ON a.tool_erp_id = t.id
WHERE t.nombre = 'agenda_mantenimiento' AND a.nombre_accion = 'reporte_semanal';

INSERT IGNORE INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
SELECT a.id, 11, 'allow'
FROM acciones_tools_erp a
INNER JOIN tools_erp t ON a.tool_erp_id = t.id
WHERE t.nombre = 'agenda_mantenimiento' AND a.nombre_accion = 'reporte_semanal';
