-- =============================================================
-- SQL: Herramienta aprobacion_pedidos_access_host
-- Módulo:  Sistemas
-- Archivo: modulos/sistemas/gestion_anulaciones.php
-- Ejecutar en: u839374897_erp
-- =============================================================

-- ── 1. Registrar la herramienta en tools_erp ─────────────────
INSERT INTO `tools_erp`
    (`nombre`, `titulo`, `tipo_componente`, `grupo`, `descripcion`, `url_real`, `url_alias`, `icono`)
VALUES
    ('aprobacion_pedidos_access_host',
     'Aprobación de Anulaciones',
     'herramienta',
     'sistemas',
     'Gestión y aprobación de solicitudes de anulación de pedidos sincronizadas desde Access.',
     'modulos/sistemas/gestion_anulaciones.php',
     'sistemas/anulaciones',
     'bi bi-ban');

-- ── 2. Registrar las acciones de la herramienta ──────────────
-- (Obtener el id de la herramienta recién insertada con LAST_INSERT_ID())

SET @tool_id = LAST_INSERT_ID();

INSERT INTO `acciones_tools_erp` (`tool_erp_id`, `nombre_accion`, `descripcion`) VALUES
    (@tool_id, 'vista',   'Permite ver la lista de solicitudes de anulación y sus detalles.'),
    (@tool_id, 'aprobar', 'Permite aprobar, rechazar solicitudes y crear anulaciones directamente desde el ERP web.');

-- ── 3. Asignar permisos por cargo ────────────────────────────
-- Obtener los IDs de las acciones recién insertadas

SET @accion_vista   = (SELECT id FROM acciones_tools_erp WHERE tool_erp_id = @tool_id AND nombre_accion = 'vista'   LIMIT 1);
SET @accion_aprobar = (SELECT id FROM acciones_tools_erp WHERE tool_erp_id = @tool_id AND nombre_accion = 'aprobar' LIMIT 1);

-- VISTA: Gerencia Proyectos (49) y Líder TI (15) pueden ver
INSERT INTO `permisos_tools_erp` (`accion_tool_erp_id`, `CodNivelesCargos`, `permiso`) VALUES
    (@accion_vista, 49, 'allow'),   -- Gerencia Proyectos
    (@accion_vista, 15, 'allow'),   -- Líder de TI
    (@accion_vista, 16, 'allow'),   -- Gerencia General
    (@accion_vista, 11, 'allow');   -- Jefe de Operaciones

-- APROBAR: Solo Gerencia Proyectos (49) y Gerencia General (16)
INSERT INTO `permisos_tools_erp` (`accion_tool_erp_id`, `CodNivelesCargos`, `permiso`) VALUES
    (@accion_aprobar, 49, 'allow'),  -- Gerencia Proyectos
    (@accion_aprobar, 16, 'allow');  -- Gerencia General

-- =============================================================
-- VERIFICACIÓN (opcional, ejecutar por separado para confirmar)
-- =============================================================
-- SELECT t.nombre, t.titulo, a.nombre_accion, p.CodNivelesCargos, p.permiso
-- FROM tools_erp t
-- JOIN acciones_tools_erp a ON a.tool_erp_id = t.id
-- JOIN permisos_tools_erp p ON p.accion_tool_erp_id = a.id
-- WHERE t.nombre = 'aprobacion_pedidos_access_host'
-- ORDER BY a.nombre_accion, p.CodNivelesCargos;
