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

-- ─── OPCIÓN A: Invalidar por IA no validada ───────────────────────────────
-- Todos los registros donde la IA no pudo validar la factura se marcan inválidos.
UPDATE pitaya_love_registros
SET valido = 0
WHERE validado_ia = 0
  AND valido = 1; -- Solo actualiza los que aún están marcados como válidos

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
WHERE plr.valido = 1
  AND EXISTS (
      SELECT 1
      FROM Operarios o
      INNER JOIN Contratos c ON c.cod_operario = o.CodOperario
          AND c.Finalizado = 0
      WHERE (
          -- Al menos 2 palabras significativas en común (≥4 chars)
          -- Aproximación SQL: verifica que el nombre del concursante contiene
          -- al menos 2 tokens del nombre del colaborador
          (
              LENGTH(
                  CONCAT(
                      COALESCE(o.Nombre, ''), ' ',
                      COALESCE(o.Nombre2, ''), ' ',
                      COALESCE(o.Apellido, ''), ' ',
                      COALESCE(o.Apellido2, '')
                  )
              ) > 0
          )
          AND (
              -- Primera palabra sustantiva coincide (Nombre)
              (
                  o.Nombre IS NOT NULL
                  AND LENGTH(o.Nombre) >= 4
                  AND LOWER(plr.nombre_completo) LIKE CONCAT('%', LOWER(o.Nombre), '%')
              )
              AND (
                  -- Segunda palabra sustantiva: Apellido
                  (
                      o.Apellido IS NOT NULL
                      AND LENGTH(o.Apellido) >= 4
                      AND LOWER(plr.nombre_completo) LIKE CONCAT('%', LOWER(o.Apellido), '%')
                  )
                  OR (
                      -- O Apellido2
                      o.Apellido2 IS NOT NULL
                      AND LENGTH(o.Apellido2) >= 4
                      AND LOWER(plr.nombre_completo) LIKE CONCAT('%', LOWER(o.Apellido2), '%')
                  )
                  OR (
                      -- O Nombre2
                      o.Nombre2 IS NOT NULL
                      AND LENGTH(o.Nombre2) >= 4
                      AND LOWER(plr.nombre_completo) LIKE CONCAT('%', LOWER(o.Nombre2), '%')
                  )
              )
          )
      )
      GROUP BY o.CodOperario
  );

-- ─── Verificación post-ejecución ─────────────────────────────────────────
SELECT 
    COUNT(*) AS total_invalidados,
    SUM(CASE WHEN validado_ia = 0 THEN 1 ELSE 0 END) AS por_ia_no_validada,
    SUM(CASE WHEN validado_ia = 1 THEN 1 ELSE 0 END) AS por_coincidencia_colaborador
FROM pitaya_love_registros
WHERE valido = 0;
