-- =====================================================
-- Script: Migración de datos
-- Descripción: Migra datos de compra_local_productos_despacho
--              a las nuevas tablas separadas
-- =====================================================

-- PASO 1: Crear las nuevas tablas
-- Ejecutar primero:
-- - compra_local_configuracion_despacho.sql
-- - compra_local_pedidos_historico.sql

-- =====================================================
-- PASO 2: Migrar configuración (días habilitados)
-- =====================================================

INSERT INTO compra_local_configuracion_despacho 
    (id_producto_presentacion, codigo_sucursal, dia_entrega, status, usuario_creacion, fecha_creacion)
SELECT DISTINCT 
    id_producto_presentacion,
    codigo_sucursal,
    dia_entrega,
    status,
    usuario_creacion,
    fecha_creacion
FROM compra_local_productos_despacho
ON DUPLICATE KEY UPDATE
    status = VALUES(status),
    usuario_modificacion = VALUES(usuario_creacion),
    fecha_modificacion = CURRENT_TIMESTAMP;

-- =====================================================
-- PASO 3: Migrar pedidos históricos
-- =====================================================

-- Nota: Este paso requiere calcular fechas específicas a partir de día_entrega
-- Se migrarán solo los registros con cantidad > 0

-- Para cada registro con pedido, necesitamos calcular la fecha de entrega más reciente
-- basándonos en el día de la semana (dia_entrega) y fecha_hora_reportada

INSERT INTO compra_local_pedidos_historico
    (id_producto_presentacion, codigo_sucursal, fecha_entrega, cantidad_pedido, usuario_registro, fecha_hora_reportada)
SELECT 
    id_producto_presentacion,
    codigo_sucursal,
    -- Calcular la fecha de entrega más cercana al día reportado
    DATE_ADD(
        DATE(fecha_hora_reportada),
        INTERVAL (
            CASE 
                WHEN DAYOFWEEK(fecha_hora_reportada) <= dia_entrega 
                THEN dia_entrega - DAYOFWEEK(fecha_hora_reportada)
                ELSE 7 - DAYOFWEEK(fecha_hora_reportada) + dia_entrega
            END
        ) DAY
    ) as fecha_entrega,
    cantidad_pedido,
    usuario_creacion,
    fecha_hora_reportada
FROM compra_local_productos_despacho
WHERE cantidad_pedido > 0
  AND fecha_hora_reportada IS NOT NULL
ON DUPLICATE KEY UPDATE
    cantidad_pedido = VALUES(cantidad_pedido),
    fecha_hora_reportada = VALUES(fecha_hora_reportada);

-- =====================================================
-- PASO 4: Verificación de migración
-- =====================================================

-- Verificar conteo de configuraciones
SELECT 
    'Configuraciones migradas' as tipo,
    COUNT(*) as total
FROM compra_local_configuracion_despacho;

-- Verificar conteo de pedidos históricos
SELECT 
    'Pedidos históricos migrados' as tipo,
    COUNT(*) as total
FROM compra_local_pedidos_historico;

-- Verificar registros originales con pedidos
SELECT 
    'Registros originales con pedidos' as tipo,
    COUNT(*) as total
FROM compra_local_productos_despacho
WHERE cantidad_pedido > 0;

-- =====================================================
-- PASO 5: Renombrar tabla antigua (BACKUP)
-- =====================================================

-- NO EJECUTAR hasta verificar que todo funciona correctamente
-- RENAME TABLE compra_local_productos_despacho TO compra_local_productos_despacho_backup;

-- =====================================================
-- PASO 6: Eliminar tabla antigua (SOLO DESPUÉS DE VERIFICAR)
-- =====================================================

-- NO EJECUTAR hasta estar 100% seguro
-- DROP TABLE compra_local_productos_despacho_backup;
