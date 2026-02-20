-- =====================================================
-- SETUP: Visor de Recetas Antiguas con Traducción
-- Fecha: 2026-02-20
-- =====================================================

-- =====================================================
-- 1. REGISTRO EN tools_erp
-- =====================================================

INSERT INTO tools_erp (
    nombre,
    titulo,
    tipo_componente,
    grupo,
    descripcion,
    url_real,
    url_alias,
    icono,
    orden,
    activo
)
VALUES (
    'visor_recetas',
    'Visor de Recetas',
    'herramienta',
    'sistemas',
    'Visualización de recetas del sistema antiguo con traducción al nuevo ERP de productos',
    '/modulos/sistemas/visor_recetas.php',
    'visor-recetas',
    'fas fa-blender',
    55,
    1
);

-- =====================================================
-- 2. ACCIONES (solo vista)
-- =====================================================

SET @id_herramienta = (SELECT id FROM tools_erp WHERE nombre = 'visor_recetas');

INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion)
VALUES (
    @id_herramienta,
    'vista',
    'Permiso para visualizar el contenido de las recetas del sistema antiguo con su traducción al nuevo ERP'
);

-- =====================================================
-- 3. PERMISOS POR CARGO (solo vista)
-- =====================================================
-- Cargo 15 = Sistemas
-- Cargo 16 = Gerencia
-- Agrega más cargos duplicando la línea con el CodNivelesCargos que necesites
-- =====================================================

SET @id_accion_vista = (SELECT id FROM acciones_tools_erp WHERE tool_erp_id = @id_herramienta AND nombre_accion = 'vista');

INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
VALUES
    (@id_accion_vista, 15, 'allow'),   -- Sistemas
    (@id_accion_vista, 16, 'allow');   -- Gerencia

-- =====================================================
-- 4. VERIFICACIÓN
-- =====================================================

SELECT
    t.id, t.nombre, t.titulo, t.grupo, t.url_real, t.activo
FROM tools_erp t
WHERE t.nombre = 'visor_recetas';

SELECT
    t.titulo AS herramienta,
    a.nombre_accion,
    p.CodNivelesCargos,
    p.permiso
FROM permisos_tools_erp p
INNER JOIN acciones_tools_erp a ON p.accion_tool_erp_id = a.id
INNER JOIN tools_erp t          ON a.tool_erp_id = t.id
WHERE t.nombre = 'visor_recetas'
ORDER BY a.nombre_accion, p.CodNivelesCargos;

-- =====================================================
-- NOTAS:
-- • Solo se registra acción "vista" (sin edición).
-- • Para agregar acceso a otro cargo, insertar en
--   permisos_tools_erp con el @id_accion_vista y el
--   CodNivelesCargos correspondiente.
-- =====================================================
