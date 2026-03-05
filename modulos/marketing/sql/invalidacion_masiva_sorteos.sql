-- =============================================================================
-- SCRIPT DE INVALIDACIÓN MASIVA - Gestión Sorteos Pitaya Love
-- Fecha: 2026-03-05
-- Descripción: Marca como inválidos (valido=0) los registros que:
--   1. Tienen coincidencia de nombre con un colaborador activo (Finalizado=0)
--      utilizando la misma lógica PHP: al menos 2 palabras significativas
--      en común, excluyendo artículos/preposiciones.
--   2. No fueron validados por la IA (validado_ia=0)
--
-- IMPORTANTE: Ejecutar primero en un entorno de prueba. 
--             Esta operación NO elimina registros, solo cambia valido → 0.
-- =============================================================================

-- ─── OPCIÓN A: Invalidar por IA "Revisar" ───────────────────────────────
-- Todos los registros que el sistema clasifica como "Revisar" por la IA
UPDATE pitaya_love_registros
SET valido = 0
WHERE codigo_sorteo_ia IS NULL 
   OR numero_factura != codigo_sorteo_ia 
   OR puntos_factura != puntos_ia;

-- ─── OPCIÓN B: Invalidar por similitud de nombre con colaborador activo ────
--
-- NOTA: La comparación de palabras exactas (stopwords, normalización de tildes)
-- no es directamente reproducible en SQL puro de manera eficiente.
-- Este script usa una aproximación con REGEXP_LIKE para las palabras más comunes
-- de nombres propios (Apellidos/Nombres de 2+ sílabas).
--
-- La lógica: busca registros cuyo nombre_completo comparte al menos una
-- subcadena de palabra significativa (≥4 chars) con un colaborador activo.
-- Para mayor precisión, usar el script PHP que aplica la lógica completa.
--
-- Colaboradores activos = cod_operario con Finalizado=0 en Contratos

UPDATE pitaya_love_registros plr
SET plr.valido = 0
WHERE EXISTS (
      SELECT 1
      FROM Operarios o
      INNER JOIN Contratos c ON c.cod_operario = o.CodOperario
          AND c.Finalizado = 0
      WHERE (
          -- Al menos 2 palabras significativas en común (≥4 chars)
          (
              o.Nombre IS NOT NULL
              AND LENGTH(o.Nombre) >= 4
              AND LOWER(plr.nombre_completo) LIKE CONCAT('%', LOWER(o.Nombre), '%')
          )
          AND (
              (
                  o.Apellido IS NOT NULL
                  AND LENGTH(o.Apellido) >= 4
                  AND LOWER(plr.nombre_completo) LIKE CONCAT('%', LOWER(o.Apellido), '%')
              )
              OR (
                  o.Apellido2 IS NOT NULL
                  AND LENGTH(o.Apellido2) >= 4
                  AND LOWER(plr.nombre_completo) LIKE CONCAT('%', LOWER(o.Apellido2), '%')
              )
          )
      )
  );

-- ─── Verificación post-ejecución ─────────────────────────────────────────
-- Muestra el conteo de cuántos registros cumplen cada criterio (pueden solaparse)
SELECT 
    COUNT(*) AS total_invalidados_actuales,
    (SELECT COUNT(*) FROM pitaya_love_registros WHERE codigo_sorteo_ia IS NULL OR numero_factura != codigo_sorteo_ia OR puntos_factura != puntos_ia) AS coincide_ia_revisar,
    (SELECT COUNT(*) FROM pitaya_love_registros plr2 WHERE EXISTS (SELECT 1 FROM Operarios o INNER JOIN Contratos c ON c.cod_operario = o.CodOperario AND c.Finalizado = 0 WHERE o.Nombre IS NOT NULL AND LENGTH(o.Nombre) >= 4 AND LOWER(plr2.nombre_completo) LIKE CONCAT('%', LOWER(o.Nombre), '%') AND (o.Apellido IS NOT NULL AND LENGTH(o.Apellido) >= 4 AND LOWER(plr2.nombre_completo) LIKE CONCAT('%', LOWER(o.Apellido), '%')))) AS coincide_colaborador
FROM pitaya_love_registros
WHERE valido = 0;
