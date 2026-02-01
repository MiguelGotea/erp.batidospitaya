-- Registro de Herramientas de Feriados en tools_erp con todos los campos obligatorios
INSERT INTO tools_erp (nombre, titulo, grupo, descripcion, url_real, url_alias, icono, orden, tipo_componente, activo) VALUES 
(
    'gestion_feriados', 
    'Gestión de Feriados', 
    'rh', 
    'Gestión y edición de feriados del sistema para cálculos de nómina y asistencia', 
    'modulos/rh/editar_feriados.php', 
    'gestion-feriados', 
    'fas fa-calendar-times', 
    10, 
    'herramienta', 
    1
),
(
    'plan_feriados_anual', 
    'Plan Anual de Feriados', 
    'rh', 
    'Visualización anual de feriados nacionales y departamentales', 
    'modulos/rh/plan_feriados_anual.php', 
    'plan-feriados-anual', 
    'fas fa-calendar-alt', 
    11, 
    'herramienta', 
    1
)
ON DUPLICATE KEY UPDATE 
    titulo = VALUES(titulo),
    grupo = VALUES(grupo),
    descripcion = VALUES(descripcion),
    url_real = VALUES(url_real),
    url_alias = VALUES(url_alias),
    icono = VALUES(icono),
    orden = VALUES(orden),
    tipo_componente = VALUES(tipo_componente),
    activo = VALUES(activo);

-- Registro de Acciones para las Herramientas
INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion)
SELECT id, 'vista', 'Permiso para visualizar la herramienta' FROM tools_erp WHERE nombre = 'gestion_feriados'
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion)
SELECT id, 'vista', 'Permiso para visualizar la herramienta' FROM tools_erp WHERE nombre = 'plan_feriados_anual'
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- Asignación de Permisos (allow) para cargos relevantes
-- Cargos: 13 (Jefe RH), 39 (RH), 30 (RH), 37 (RH), 28 (RH), 16 (Gerencia)

-- Permisos para gestion_feriados (Vista)
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
SELECT a.id, c.cod, 'allow'
FROM acciones_tools_erp a
CROSS JOIN (SELECT 13 as cod UNION SELECT 39 UNION SELECT 30 UNION SELECT 37 UNION SELECT 28 UNION SELECT 16) c
INNER JOIN tools_erp t ON a.tool_erp_id = t.id
WHERE t.nombre = 'gestion_feriados' AND a.nombre_accion = 'vista'
ON DUPLICATE KEY UPDATE permiso = 'allow';

-- Permisos para plan_feriados_anual (Vista)
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
SELECT a.id, c.cod, 'allow'
FROM acciones_tools_erp a
CROSS JOIN (SELECT 13 as cod UNION SELECT 39 UNION SELECT 30 UNION SELECT 37 UNION SELECT 28 UNION SELECT 16) c
INNER JOIN tools_erp t ON a.tool_erp_id = t.id
WHERE t.nombre = 'plan_feriados_anual' AND a.nombre_accion = 'vista'
ON DUPLICATE KEY UPDATE permiso = 'allow';
