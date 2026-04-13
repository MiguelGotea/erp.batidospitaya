-- ============================================================
-- SQL Setup: Índices compuestos para Dashboard de Consumo
-- modulos/productos/sql/setup_consumo_indices.sql
--
-- PROBLEMA: timeout 504 en dashboard_consumo_auditoria.php
--   al cargar historial de auditoría (ej: semana 536, Estelí).
--
-- CAUSA RAÍZ (confirmado contra u839374897_erp.sql):
--
--   1. VentasGlobalesAccessCSV ya tiene idx_semana(Semana) e
--      idx_codproducto(CodProducto) como índices SIMPLES separados.
--      La query filtra Semana + CodProducto + Anulado + local
--      juntos → MySQL solo usa un índice y escanea el resto.
--
--   2. SubReceta NO TIENE NINGÚN ÍNDICE en el dump.
--      El JOIN SubReceta ON CodBatido = v.CodProducto hace
--      full scan de SubReceta por cada grupo de ventas filtrado.
--
-- SOLUCIÓN: Agregar índices compuestos en ambas tablas.
--
-- EJECUTAR: Una sola vez en producción via phpMyAdmin > SQL
-- ============================================================

-- ── VentasGlobalesAccessCSV ────────────────────────────────

-- Índice compuesto: Semana + CodProducto + Anulado
-- Cubre: WHERE Anulado=0 AND Semana BETWEEN x AND y AND CodProducto IN(...)
-- Permite range scan directo en lugar de usar dos índices simples.
ALTER TABLE `VentasGlobalesAccessCSV`
  ADD KEY `idx_consumo_semana_prod` (`Semana`, `CodProducto`, `Anulado`);

-- Índice compuesto: local + Semana + Anulado
-- Cubre el filtro AND v.local IN (...) cuando se filtra por sucursal
ALTER TABLE `VentasGlobalesAccessCSV`
  ADD KEY `idx_consumo_local_semana` (`local`, `Semana`, `Anulado`);

-- ── SubReceta ──────────────────────────────────────────────

-- SubReceta no tiene ningún índice en producción.
-- El JOIN: INNER JOIN SubReceta ON CodBatido = v.CodProducto
-- hace full scan de SubReceta (~miles de filas) por cada batch de ventas.
-- Este índice convierte el JOIN en un lookup O(log n).
ALTER TABLE `SubReceta`
  ADD KEY `idx_subreceta_codbatido` (`CodBatido`);

-- Índice adicional para filtros por CodIngrediente (PASO A en auditoría)
ALTER TABLE `SubReceta`
  ADD KEY `idx_subreceta_ingrediente` (`CodIngrediente`);

-- ============================================================
-- VERIFICACIÓN: Ejecutar EXPLAIN después de crear los índices
-- ============================================================
-- EXPLAIN
--   SELECT v.Semana, v.local, v.CodProducto, SUM(v.Cantidad)
--   FROM VentasGlobalesAccessCSV v
--   INNER JOIN SubReceta sr ON sr.CodBatido = v.CodProducto
--   WHERE v.Anulado = 0
--     AND v.Semana BETWEEN 536 AND 536
--     AND v.CodProducto IN ('B001','B002')
--     AND v.local = 'EST'
--   GROUP BY v.Semana, v.local, v.CodProducto;
--
-- Resultado esperado:
--   VentasGlobalesAccessCSV → key: idx_consumo_semana_prod
--   SubReceta              → key: idx_subreceta_codbatido
-- ============================================================
