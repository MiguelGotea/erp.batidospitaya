-- =====================================================
-- SQL para Registro de Herramientas y Permisos
-- Sistema de Compra Local - Despacho de Productos
-- =====================================================

-- =====================================================
-- 1. REGISTRO DE HERRAMIENTAS EN tools_erp
-- =====================================================

-- Herramienta 1: Configuración de Plan de Despacho (CDS)
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
    'compra_local_configuracion_despacho',
    'Configuración de Plan de Despacho',
    'herramienta',
    'cds',
    'Configuración de días de entrega de productos a sucursales',
    '/modulos/cds/compra_local_configuracion_despacho.php',
    'configuracion-despacho',
    'fas fa-calendar-alt',
    10,
    1
);

-- Herramienta 2: Registro de Pedidos de Insumos (Sucursales)
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
    'compra_local_registro_pedidos',
    'Registro de Pedidos de Insumos',
    'herramienta',
    'sucursales',
    'Registro de pedidos de productos según plan de despacho',
    '/modulos/sucursales/compra_local_registro_pedidos.php',
    'registro-pedidos',
    'fas fa-clipboard-list',
    20,
    1
);

-- Herramienta 3: Consolidado de Pedidos (CDS)
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
    'compra_local_consolidado_pedidos',
    'Consolidado de Pedidos',
    'herramienta',
    'cds',
    'Vista consolidada de todos los pedidos de sucursales',
    '/modulos/cds/compra_local_consolidado_pedidos.php',
    'consolidado-pedidos',
    'fas fa-chart-bar',
    30,
    1
);

-- =====================================================
-- 2. REGISTRO DE ACCIONES EN acciones_tools_erp
-- =====================================================

-- Obtener los IDs de las herramientas recién creadas
SET @id_config_despacho = (SELECT id FROM tools_erp WHERE nombre = 'compra_local_configuracion_despacho');
SET @id_registro_pedidos = (SELECT id FROM tools_erp WHERE nombre = 'compra_local_registro_pedidos');
SET @id_consolidado = (SELECT id FROM tools_erp WHERE nombre = 'compra_local_consolidado_pedidos');

-- =====================================================
-- Acciones para: Configuración de Plan de Despacho
-- =====================================================

-- Acción: Vista
INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion)
VALUES (
    @id_config_despacho,
    'vista',
    'Permiso para ver la configuración de plan de despacho'
);

-- Acción: Edición
INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion)
VALUES (
    @id_config_despacho,
    'edicion',
    'Permiso para editar la configuración de plan de despacho'
);

-- =====================================================
-- Acciones para: Registro de Pedidos de Insumos
-- =====================================================

-- Acción: Vista
INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion)
VALUES (
    @id_registro_pedidos,
    'vista',
    'Permiso para ver el registro de pedidos'
);

-- Acción: Edición
INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion)
VALUES (
    @id_registro_pedidos,
    'edicion',
    'Permiso para registrar y editar pedidos'
);

-- =====================================================
-- Acciones para: Consolidado de Pedidos
-- =====================================================

-- Acción: Vista
INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion)
VALUES (
    @id_consolidado,
    'vista',
    'Permiso para ver el consolidado de pedidos'
);

-- Acción: Edición (opcional para consolidado)
INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion)
VALUES (
    @id_consolidado,
    'edicion',
    'Permiso para editar datos del consolidado'
);

-- =====================================================
-- 3. PERMISOS RECOMENDADOS POR CARGO
-- =====================================================
-- NOTA: Estos son ejemplos. Debes ajustar según los códigos
-- de cargo específicos de tu sistema.
-- =====================================================

-- Ejemplo de asignación de permisos usando permisos_tools_erp
-- Reemplaza los códigos de cargo según tu sistema

/*
-- Obtener IDs de acciones
SET @id_accion_config_vista = (SELECT id FROM acciones_tools_erp WHERE tool_erp_id = @id_config_despacho AND nombre_accion = 'vista');
SET @id_accion_config_edicion = (SELECT id FROM acciones_tools_erp WHERE tool_erp_id = @id_config_despacho AND nombre_accion = 'edicion');
SET @id_accion_registro_vista = (SELECT id FROM acciones_tools_erp WHERE tool_erp_id = @id_registro_pedidos AND nombre_accion = 'vista');
SET @id_accion_registro_edicion = (SELECT id FROM acciones_tools_erp WHERE tool_erp_id = @id_registro_pedidos AND nombre_accion = 'edicion');
SET @id_accion_consolidado_vista = (SELECT id FROM acciones_tools_erp WHERE tool_erp_id = @id_consolidado AND nombre_accion = 'vista');
SET @id_accion_consolidado_edicion = (SELECT id FROM acciones_tools_erp WHERE tool_erp_id = @id_consolidado AND nombre_accion = 'edicion');

-- Jefe de CDS (CodNivelesCargos = 19) - Configuración y Consolidado (vista y edición)
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
VALUES 
    (@id_accion_config_vista, 19, 'allow'),
    (@id_accion_config_edicion, 19, 'allow'),
    (@id_accion_consolidado_vista, 19, 'allow'),
    (@id_accion_consolidado_edicion, 19, 'allow');

-- Líderes de Sucursal (CodNivelesCargos = 5) - Registro de Pedidos (vista y edición)
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
VALUES 
    (@id_accion_registro_vista, 5, 'allow'),
    (@id_accion_registro_edicion, 5, 'allow');

-- Vendedores (CodNivelesCargos = 2) - Solo vista de Registro de Pedidos
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
VALUES 
    (@id_accion_registro_vista, 2, 'allow');
*/

-- =====================================================
-- 4. VERIFICACIÓN
-- =====================================================

-- Verificar herramientas creadas
SELECT 
    id,
    nombre,
    titulo,
    tipo_componente,
    grupo,
    descripcion,
    url_real,
    icono,
    orden,
    activo
FROM tools_erp
WHERE nombre LIKE 'compra_local_%'
ORDER BY orden;

-- Verificar acciones creadas
SELECT 
    t.nombre as herramienta,
    t.titulo,
    a.id as accion_id,
    a.nombre_accion,
    a.descripcion
FROM acciones_tools_erp a
INNER JOIN tools_erp t ON a.tool_erp_id = t.id
WHERE t.nombre LIKE 'compra_local_%'
ORDER BY t.orden, a.nombre_accion;

-- Verificar permisos asignados (si se descomentó la sección 3)
/*
SELECT 
    t.titulo as herramienta,
    a.nombre_accion,
    nc.Nombre as cargo,
    p.CodNivelesCargos,
    p.permiso
FROM permisos_tools_erp p
INNER JOIN acciones_tools_erp a ON p.accion_tool_erp_id = a.id
INNER JOIN tools_erp t ON a.tool_erp_id = t.id
LEFT JOIN NivelesCargos nc ON p.CodNivelesCargos = nc.CodNivelesCargos
WHERE t.nombre LIKE 'compra_local_%'
ORDER BY t.orden, a.nombre_accion, nc.Nombre;
*/

-- =====================================================
-- NOTAS IMPORTANTES:
-- =====================================================
-- 1. Ejecuta este script en tu base de datos
-- 2. Las tablas utilizadas son:
--    - tools_erp (herramientas)
--      * nombre: identificador único (snake_case)
--      * titulo: título mostrado en UI
--      * tipo_componente: 'herramienta', 'indicador', 'balance'
--      * grupo: módulo del sistema
--      * url_real: ruta del archivo PHP
--      * icono: clase Font Awesome
--      * orden: orden de visualización
--    - acciones_tools_erp (acciones por herramienta)
--      * tool_erp_id: FK a tools_erp
--      * nombre_accion: 'vista', 'edicion', etc.
--    - permisos_tools_erp (permisos por cargo)
--      * accion_tool_erp_id: FK a acciones_tools_erp
--      * CodNivelesCargos: FK a NivelesCargos
--      * permiso: 'allow' o 'deny'
-- 3. Configura los permisos por cargo manualmente según
--    tu estructura organizacional (descomenta sección 3)
-- 4. Las 3 herramientas quedan registradas con sus
--    acciones de 'vista' y 'edicion'
-- 5. Los campos created_at y updated_at se llenan
--    automáticamente por la base de datos
-- =====================================================


