-- ============================================================
--  NUEVO PERMISO: recetario_access_traducido
--  Herramienta: Consulta de Recetas (Visor Light)
--  Módulo: productos
--  Acción: vista
--  Fecha: 2026-04-12
-- ============================================================

-- ────────────────────────────────────────────────────────────
-- PASO 1: Registrar la herramienta en tools_erp
-- ────────────────────────────────────────────────────────────
INSERT INTO tools_erp
    (nombre, titulo, descripcion, grupo, url_real, icono, tipo_componente, activo, orden)
VALUES
    (
        'recetario_access_traducido',
        'Consulta de Recetas',
        'Visor compacto de recetas de Access con traducción al nuevo ERP. Solo lectura — muestra ingredientes del Nuevo Sistema con columnas Orden y Tipo.',
        'Productos',
        '/modulos/productos/visor_recetas_light.php',
        'fas fa-blender',
        'herramienta',
        1,
        99
    );

-- ────────────────────────────────────────────────────────────
-- PASO 2: Registrar la acción "vista" en acciones_tools_erp
--         Referenciando el ID recién insertado via subconsulta
-- ────────────────────────────────────────────────────────────
INSERT INTO acciones_tools_erp
    (tool_erp_id, nombre_accion, descripcion, created_at, updated_at)
VALUES
    (
        (SELECT id FROM tools_erp WHERE nombre = 'recetario_access_traducido' LIMIT 1),
        'vista',
        'Permite acceder y visualizar el recetario con traducción al ERP. Sin capacidad de edición.',
        CURRENT_TIMESTAMP,
        CURRENT_TIMESTAMP
    );

-- ────────────────────────────────────────────────────────────
-- PASO 3: Otorgar permiso "allow" en permisos_tools_erp
--         Ajusta los CodNivelesCargos según los cargos de tu sistema.
--         Ejemplo: cargo 1 = Administrador, cargo 2 = Supervisor, etc.
--         Puedes consultar tus cargos con:
--         SELECT CodNivelesCargos, Descripcion FROM NivelesCargos ORDER BY 1;
-- ────────────────────────────────────────────────────────────

-- Plantilla: reemplaza (1), (2), (3) con los CodNivelesCargos que apliquen
INSERT INTO permisos_tools_erp
    (accion_tool_erp_id, CodNivelesCargos, permiso, created_at, updated_at)
SELECT
    a.id,
    c.CodNivelesCargos,
    'allow',
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
FROM acciones_tools_erp a
INNER JOIN tools_erp t ON t.id = a.tool_erp_id
CROSS JOIN (
    -- ► Agrega aquí los CodNivelesCargos que tendrán acceso
    SELECT 1 AS CodNivelesCargos
    UNION ALL SELECT 2
    -- UNION ALL SELECT 3  ← descomenta y agrega más según necesites
) c
WHERE t.nombre    = 'recetario_access_traducido'
  AND a.nombre_accion = 'vista';

-- ────────────────────────────────────────────────────────────
-- VERIFICACIÓN: Confirmar que quedó todo correcto
-- ────────────────────────────────────────────────────────────
SELECT
    t.id            AS tool_id,
    t.nombre        AS herramienta,
    t.titulo,
    t.grupo,
    a.id            AS accion_id,
    a.nombre_accion,
    p.CodNivelesCargos,
    p.permiso
FROM tools_erp t
INNER JOIN acciones_tools_erp  a ON t.id = a.tool_erp_id
LEFT  JOIN permisos_tools_erp  p ON a.id = p.accion_tool_erp_id
WHERE t.nombre = 'recetario_access_traducido'
ORDER BY p.CodNivelesCargos;
