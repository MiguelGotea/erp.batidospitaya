-- =====================================================
-- SETUP: Diccionario de Traducción de Productos
-- Sistema Antiguo (Access/Cotizaciones) → Nuevo ERP
-- Fecha: 2026-02-20
-- =====================================================

-- =====================================================
-- 1. TABLA DE MAPEO
-- =====================================================

CREATE TABLE IF NOT EXISTS `diccionario_productos_legado` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `CodIngrediente` varchar(100) NOT NULL COMMENT 'Código del ingrediente en el sistema antiguo (DBIngredientes)',
  `CodCotizacion` int(11) NOT NULL COMMENT 'ID de la presentación en el sistema antiguo (Cotizaciones)',
  `id_producto_presentacion` int(11) NOT NULL COMMENT 'FK a producto_presentacion.id en el nuevo ERP',
  `notas` varchar(255) DEFAULT NULL COMMENT 'Observaciones opcionales del mapeo',
  `fecha_mapeo` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha en que se realizó el mapeo',
  `usuario_mapeo` int(11) NOT NULL COMMENT 'FK a Operarios - quién realizó el mapeo',
  `fecha_modificacion` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'Última modificación',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mapeo_cotizacion` (`CodCotizacion`) COMMENT 'Una cotización solo puede tener un producto nuevo',
  KEY `idx_ingrediente` (`CodIngrediente`),
  KEY `idx_presentacion` (`id_producto_presentacion`),
  KEY `idx_usuario` (`usuario_mapeo`),
  CONSTRAINT `fk_dic_presentacion` FOREIGN KEY (`id_producto_presentacion`) REFERENCES `producto_presentacion` (`id`),
  CONSTRAINT `fk_dic_usuario` FOREIGN KEY (`usuario_mapeo`) REFERENCES `Operarios` (`CodOperario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Diccionario de traducción: presentación antigua (Cotizaciones) → nuevo ERP (producto_presentacion)';

-- =====================================================
-- 2. REGISTRO EN tools_erp
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
    'diccionario_productos',
    'Diccionario de Productos',
    'herramienta',
    'sistemas',
    'Herramienta para mapear productos del sistema antiguo (Access) con productos del nuevo ERP',
    '/modulos/sistemas/diccionario_productos.php',
    'diccionario-productos',
    'fas fa-exchange-alt',
    50,
    1
);

-- =====================================================
-- 3. ACCIONES DE LA HERRAMIENTA
-- =====================================================

SET @id_herramienta = (SELECT id FROM tools_erp WHERE nombre = 'diccionario_productos');

-- Acción: Vista (ver el diccionario)
INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion)
VALUES (
    @id_herramienta,
    'vista',
    'Permiso para ver el diccionario de traducción de productos'
);

-- Acción: Edición (crear/actualizar mapeos)
INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion)
VALUES (
    @id_herramienta,
    'edicion',
    'Permiso para crear y editar mapeos de productos (antiguo → nuevo)'
);

-- =====================================================
-- 4. PERMISOS POR CARGO
-- =====================================================
-- CodNivelesCargos 15 = Sistemas (acceso total)
-- CodNivelesCargos 16 = Gerencia (solo vista)
-- =====================================================

SET @id_accion_vista   = (SELECT id FROM acciones_tools_erp WHERE tool_erp_id = @id_herramienta AND nombre_accion = 'vista');
SET @id_accion_edicion = (SELECT id FROM acciones_tools_erp WHERE tool_erp_id = @id_herramienta AND nombre_accion = 'edicion');

-- Sistemas (15): vista + edición
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
VALUES
    (@id_accion_vista,   15, 'allow'),
    (@id_accion_edicion, 15, 'allow');

-- Gerencia (16): solo vista
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
VALUES
    (@id_accion_vista, 16, 'allow');

-- =====================================================
-- 5. VERIFICACIÓN
-- =====================================================

SELECT
    t.id,
    t.nombre,
    t.titulo,
    t.grupo,
    t.url_real,
    t.activo
FROM tools_erp t
WHERE t.nombre = 'diccionario_productos';

SELECT
    t.titulo AS herramienta,
    a.nombre_accion,
    p.CodNivelesCargos,
    p.permiso
FROM permisos_tools_erp p
INNER JOIN acciones_tools_erp a ON p.accion_tool_erp_id = a.id
INNER JOIN tools_erp t ON a.tool_erp_id = t.id
WHERE t.nombre = 'diccionario_productos'
ORDER BY a.nombre_accion, p.CodNivelesCargos;

SELECT * FROM diccionario_productos_legado LIMIT 5;

-- =====================================================
-- NOTAS:
-- • Cargo 15 = Sistemas → vista + edición
-- • Cargo 16 = Gerencia → solo vista
-- • Para agregar más cargos: insertar en permisos_tools_erp
--   con el id_accion correspondiente y CodNivelesCargos deseado
-- =====================================================
