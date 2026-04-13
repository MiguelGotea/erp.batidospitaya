# 📊 Guía: Reportes de Consumo — Historial Access → ERP

> **Propósito:** Traducir el historial de ventas de batidos (Access) a consumo
> real de `producto_presentacion` del ERP, usando la lógica del visor de recetas.
> Sirve de base para reportes de consumo, tendencias y proyecciones de compra.

---

## 1. Tablas involucradas

### 1.1 Tablas Access (legado)

| Tabla | Columna clave | Descripción |
|---|---|---|
| `Batidos` | `CodBatido`, `Nombre`, `Vigencia` | Catálogo de productos (batidos, bowls, etc.) |
| `HistorialBatidos` | `CodBatido`, `Fecha`, `Cantidad` | Ventas históricas. `Cantidad` = unidades vendidas en esa fecha |
| `SubReceta` | `CodBatido`, `CodIngrediente`, `Cantidad`, `codporcion`, `ordenreceta` | Ingredientes de cada receta |
| `DBIngredientes` | `CodIngrediente`, `Unidad`, `presentacionpreparacion`, `conversionpreparacion` | Maestro de ingredientes: unidad base y ajustes de preparación |
| `Cotizaciones` | `CodCotizacion`, `CodIngrediente`, `Conversion`, `Prioridad`, `Subproducto`, `Marca` | Cotizaciones de ingredientes. `Conversion=1` = unidad base |

### 1.2 Tablas ERP (nuevo sistema)

| Tabla | Columna clave | Descripción |
|---|---|---|
| `diccionario_productos_legado` | `CodCotizacion`, `id_producto_presentacion` | Puente: cotización Access → presentación ERP |
| `producto_presentacion` | `id`, `Nombre`, `cantidad`, `id_unidad_producto`, `id_producto_maestro`, `Id_receta_producto`, `Activo` | Presentaciones del ERP. `Id_receta_producto IS NOT NULL` = compuesto |
| `producto_maestro` | `id`, `Nombre` | Agrupador de presentaciones |
| `unidad_producto` | `id`, `nombre`, `abreviado`, `nombres_opcionales` | Catálogo de unidades ERP con aliases |
| `conversion_unidad_producto` | `id_unidad_producto_inicio`, `id_unidad_producto_final`, `cantidad` | Factor de conversión entre unidades |
| `receta_producto_global` | `id` | Recetas compuestas (cuando `pp.Id_receta_producto IS NOT NULL`) |
| `componentes_receta_producto` | `id_receta`, `id_presentacion`, `cantidad` | Componentes de una receta compuesta |

---

## 2. Algoritmo de traducción SubReceta → ERP

Para cada fila de `SubReceta` de un `CodBatido` vendido N veces, se ejecutan los pasos siguientes:

### PRE-CHECK: ¿Es Receta Global?

Después de resolver el mapeo en `diccionario_productos_legado`, verificar:

```sql
SELECT pp.Id_receta_producto
FROM producto_presentacion pp
WHERE pp.id = :id_presentacion_mapeado
```

- **`Id_receta_producto IS NOT NULL`** → Producto compuesto. Saltar pasos 3, 4 y 5.
  - `ppCant = 1`, `unidad_erp = 'Unidades'`, `factor = 1`
  - `cantidad_consumida = SubReceta.Cantidad × N_ventas`

- **`Id_receta_producto IS NULL`** → Presentación simple. Continuar con pasos 3-5.

---

### Paso 1: Resolver cotización (P1 / P2 / P3)

#### P1 — Porción directa (`SubReceta.codporcion IS NOT NULL`)

El `codporcion` es directamente un `CodCotizacion` mapeado en el diccionario:

```sql
SELECT
    pp.id               AS id_presentacion,
    pp.cantidad         AS pp_cantidad,
    pp.Id_receta_producto,
    pp.Activo,
    u.nombre            AS unidad_erp,
    pm.id               AS id_maestro
FROM diccionario_productos_legado d
INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
LEFT  JOIN unidad_producto u         ON u.id  = pp.id_unidad_producto
LEFT  JOIN producto_maestro pm       ON pm.id = pp.id_producto_maestro
WHERE d.CodCotizacion = :codporcion   -- SubReceta.codporcion
LIMIT 1;
```

> Con P1, la cantidad en la comanda Access es `SubReceta.Cantidad / Cotizacion.Conversion`.
> **Las porciones P1 son unidades físicas** que solo se manipulan enteras o a la mitad.
> El consumo calculado se redondea al múltiplo de 0.5 más cercano (ver Paso 5).

#### P2 — Cotización base (`codporcion IS NULL`, existe cot. base)

```sql
-- Paso 2a: Obtener CodCotizacion
SELECT c.CodCotizacion
FROM Cotizaciones c
WHERE c.CodIngrediente = :cod_ingrediente
  AND (c.Subproducto IS NULL OR c.Subproducto != 1)
  AND (c.Marca       IS NULL OR c.Marca       != 'Almacen Global')
  AND c.Conversion = 1
  AND c.Prioridad   = 1
LIMIT 1;

-- Paso 2b: Mapear al ERP (mismo query que P1 con el CodCotizacion obtenido)
```

#### P3 — Prioritaria (fallback si no hay P1 ni P2)

```sql
SELECT c.CodCotizacion
FROM Cotizaciones c
WHERE c.CodIngrediente = :cod_ingrediente
  AND (c.Subproducto IS NULL OR c.Subproducto != 1)
  AND (c.Marca       IS NULL OR c.Marca       != 'Almacen Global')
LIMIT 1;
```

---

### Paso 2: Rastreo automático por Maestro + Unidad (AUTO)

Si no hay mapeo en `diccionario_productos_legado` para la cotización resuelta:

```sql
-- Encontrar id_producto_maestro via cualquier cotización mapeada del mismo ingrediente
SELECT pp.id_producto_maestro
FROM Cotizaciones c
INNER JOIN diccionario_productos_legado d ON d.CodCotizacion = c.CodCotizacion
INNER JOIN producto_presentacion pp       ON pp.id = d.id_producto_presentacion
WHERE c.CodIngrediente       = :cod_ingrediente
  AND pp.Id_receta_producto IS NULL
LIMIT 1;
```

Con `id_maestro` obtenido, continuar con Paso 3 y buscar presentación por unidad.

---

### Paso 3: Resolución de unidades Access → ERP (`resolverUnidadERP`)

Entrada: `DBIngredientes.Unidad` (ej: `"gr"`, `"oz"`, `"unid"`)

#### 3.1 Búsqueda primaria (LIMIT 1, prioridad: abreviado > nombre > nombres_opcionales)

```sql
SELECT id, nombre
FROM unidad_producto
WHERE LOWER(abreviado) = :u
   OR LOWER(nombre)    = :u
   OR FIND_IN_SET(:u, LOWER(REPLACE(REPLACE(nombres_opcionales, ', ', ','), ' ,', ','))) > 0
ORDER BY
    CASE
        WHEN LOWER(abreviado) = :u THEN 1
        WHEN LOWER(nombre)    = :u THEN 2
        ELSE 3
    END
LIMIT 1;
-- Resultado: id_unidad_primaria, nombre_primario
```

#### 3.2 Búsquedas secundarias (multi_directos — mismo string en otras unidades)

```sql
SELECT nombre
FROM unidad_producto
WHERE id != :id_unidad_primaria    -- excluir la principal
  AND (
    LOWER(abreviado) = :u
    OR LOWER(nombre) = :u
    OR FIND_IN_SET(:u, LOWER(REPLACE(REPLACE(nombres_opcionales, ', ', ','), ' ,', ','))) > 0
  );
-- Ejemplo: "oz" → primaria = Onzas Peso, secundaria = Onzas Liquidas
-- multi_directos = ['Onzas Peso', 'Onzas Liquidas']
```

#### 3.3 Unidades convertibles (para Nivel 2)

```sql
-- USANDO PARÁMETROS POSICIONALES para evitar PDO HY093
SELECT
    CASE WHEN c.id_unidad_producto_inicio = ? THEN uf.nombre  ELSE ui.nombre  END AS nombre_relacionado,
    CASE WHEN c.id_unidad_producto_inicio = ? THEN c.cantidad ELSE (1/c.cantidad) END AS factor_conversion
FROM conversion_unidad_producto c
JOIN unidad_producto ui ON ui.id = c.id_unidad_producto_inicio
JOIN unidad_producto uf ON uf.id = c.id_unidad_producto_final
WHERE c.id_unidad_producto_inicio = ?
   OR c.id_unidad_producto_final  = ?;
-- Parámetros: [$id_unidad_primaria, $id_unidad_primaria, $id_unidad_primaria, $id_unidad_primaria]
-- Ejemplo: Gramos (id=1) → [{ nombre: 'Onzas Peso', factor: 0.035 }]
```

---

### Paso 4: Buscar presentación del maestro (`buscarPresentacionPorUnidades`)

```sql
SELECT
    pp.id       AS id_presentacion,
    pp.Nombre   AS nombre_erp,
    pp.cantidad AS pp_cantidad,
    u.nombre    AS unidad_erp
FROM producto_presentacion pp
LEFT JOIN unidad_producto u ON u.id = pp.id_unidad_producto
WHERE pp.id_producto_maestro = :id_maestro
  AND u.nombre IN (:lista_unidades)   -- multi_directos, convertibles, o [unidad_presentacion_uso]
  AND pp.Id_receta_producto IS NULL
  AND pp.Activo = 'SI'
ORDER BY
    CASE WHEN pp.cantidad = 1 THEN 0 ELSE 1 END ASC,  -- preferir cantidad=1
    pp.cantidad ASC
LIMIT 1;
```

**Jerarquía de búsqueda:**

| Nivel | Lista de unidades | Factor |
|---|---|---|
| **Nivel 1** | `multi_directos` (ej: `['Gramos']`, `['Onzas Peso', 'Onzas Liquidas']`) | `1` (misma familia) |
| **Nivel 2** | `convertibles` (ej: `['Onzas Peso']` para ingrediente en `gr`) | Factor de conversión de la tabla |
| **Nivel 3** | `[unidad_de_presentacion_uso]` (fallback con la unidad ya resuelta) | Búsqueda directa en tabla de conversiones |

---

### Paso 5: Calcular cantidad consumida en ERP

```
cantidad_erp = (SubReceta.Cantidad × factor_conversion) / pp_cantidad
```

| Caso | Factor | pp_cantidad | Fórmula | Redondeo |
|---|---|---|---|---|
| Misma unidad | `1` | Cantidad de la presentación | `SubReceta.Cantidad / pp_cantidad` | Ninguno (4 decimales) |
| Unidad distinta con conversión | `0.035` (ej: gr→oz) | Cantidad de la presentación | `(SubReceta.Cantidad × 0.035) / pp_cantidad` | Ninguno (4 decimales) |
| **P1 porción directa** | igual a los anteriores | Cantidad de la presentación | `(SubReceta.Cantidad × factor) / pp_cantidad` | **Al 0.5 más cercano** |
| Receta Global | `1` | `1` | `SubReceta.Cantidad` (sin división) | Ninguno |

#### Regla de redondeo P1 — porciones físicas

Las porciones P1 representan unidades que se manipulan físicamente: enteras o a la mitad.
No tiene sentido expresar «1.037 porciones», por lo que el resultado se ajusta al múltiplo
de `0.5` más cercano:

```
consumo_p1_redondeado = round(consumo_crudo × 2) / 2
```

> **Ejemplo real:**
> - Receta en Access: **30 g** de fresa
> - Presentación ERP mapeada vía P1: **1 oz** (= 28.3495 g)
> - Factor de conversión gr→oz: `0.035274`
> - `consumo_crudo = (30 × 0.035274) / 1 = 1.0582`
> - `round(1.0582 × 2) / 2 = round(2.1164) / 2 = 2 / 2 = **1.0**`
>
> Para 100 ventas: consumo crudo = 105.82 porciones → redondeado = **106.0**

En PHP:

```php
// Solo aplica cuando el mapeo provino de P1 (codporcion)
if ($esP1) {
    $consumido = round($consumido * 2) / 2;
}
```

**Consumo total de una presentación ERP en un período:**

```
consumo_total = SUM(HistorialBatidos.Cantidad × cantidad_erp)
```

---

## 3. Query base para reporte de consumo

```sql
-- ============================================================
-- REPORTE DE CONSUMO POR PRESENTACIÓN ERP
-- Período: :fecha_inicio a :fecha_fin
-- Requiere resolver previamente cada SubReceta a su pp.id
-- ============================================================

SELECT
    h.Fecha,
    h.CodBatido,
    b.Nombre                    AS nombre_batido,
    sr.CodIngrediente,
    sr.Cantidad                 AS cantidad_access,
    ing.Unidad                  AS unidad_access,
    pp.id                       AS id_presentacion_erp,
    pp.Nombre                   AS nombre_presentacion_erp,
    pp.cantidad                 AS pp_cantidad,
    u.nombre                    AS unidad_erp,
    -- factor_conversion debe calcularse en aplicación o con la tabla de conversiones
    -- Consumo por unidad de batido = (sr.Cantidad × factor) / pp.cantidad
    h.Cantidad                  AS batidos_vendidos

FROM HistorialBatidos h
INNER JOIN Batidos b         ON b.CodBatido = h.CodBatido
INNER JOIN SubReceta sr      ON sr.CodBatido  = h.CodBatido
INNER JOIN DBIngredientes ing ON ing.CodIngrediente = sr.CodIngrediente

-- Resolver cotización (P2/P3 — ajustar WHERE según prioridad)
INNER JOIN Cotizaciones c ON c.CodIngrediente = sr.CodIngrediente
    AND (c.Subproducto IS NULL OR c.Subproducto != 1)
    AND (c.Marca       IS NULL OR c.Marca       != 'Almacen Global')
    AND c.Conversion = 1

-- Mapeo al ERP
INNER JOIN diccionario_productos_legado d ON d.CodCotizacion =
    COALESCE(sr.codporcion, c.CodCotizacion)   -- P1 tiene prioridad
INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
    AND pp.Id_receta_producto IS NULL           -- excluir recetas compuestas (manejar por separado)
LEFT  JOIN unidad_producto u ON u.id = pp.id_unidad_producto

WHERE h.Fecha BETWEEN :fecha_inicio AND :fecha_fin
  AND b.Vigencia = 1    -- solo batidos activos (ajustar si se quiere histórico completo)

ORDER BY h.Fecha, h.CodBatido, sr.ordenreceta;
```

> ⚠️ **Nota sobre P1 vs P2:** El query anterior prioriza `codporcion` para P1 vía `COALESCE`.
> Para P3 agregar un segundo `LEFT JOIN Cotizaciones` con `OR` y usar `COALESCE(c_p2.CodCotizacion, c_p3.CodCotizacion)`.

---

## 4. Manejo de casos especiales en reportes

### 4.1 Receta Global (producto compuesto)

```sql
-- Detectar items que son recetas globales
SELECT
    h.Fecha,
    h.Cantidad                  AS batidos_vendidos,
    sr.Cantidad                 AS cantidad_access,
    -- Consumo = sr.Cantidad × h.Cantidad (factor=1, ppCant=1)
    (sr.Cantidad * h.Cantidad)  AS consumo_receta_global,
    pp.id                       AS id_receta_global,
    pp.Nombre                   AS nombre_receta_global

FROM HistorialBatidos h
INNER JOIN SubReceta sr ON sr.CodBatido = h.CodBatido
INNER JOIN Cotizaciones c ON c.CodIngrediente = sr.CodIngrediente
    AND c.Conversion = 1
INNER JOIN diccionario_productos_legado d ON d.CodCotizacion =
    COALESCE(sr.codporcion, c.CodCotizacion)
INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
    AND pp.Id_receta_producto IS NOT NULL    -- SOLO recetas globales

WHERE h.Fecha BETWEEN :fecha_inicio AND :fecha_fin;
```

Para desglosar los componentes de la receta global:

```sql
SELECT
    crc.id_presentacion   AS id_componente,
    pp_comp.Nombre        AS componente,
    crc.cantidad          AS cantidad_componente,
    u.nombre              AS unidad_componente,
    -- consumo per batido = crc.cantidad × (sr.Cantidad en Access)
    (crc.cantidad * :consumo_receta_global) AS consumo_total_componente

FROM componentes_receta_producto crc
INNER JOIN receta_producto_global rpg ON rpg.id = crc.id_receta
INNER JOIN producto_presentacion pp_comp ON pp_comp.id = crc.id_presentacion
LEFT  JOIN unidad_producto u ON u.id = pp_comp.id_unidad_producto
WHERE rpg.id = :id_receta_global;
```

### 4.2 Conversión de unidades en SQL (alternativa)

En lugar de calcular el factor en la aplicación, se puede hacer en SQL:

```sql
-- Factor de conversión entre unidad Access y unidad ERP
SELECT
    CASE
        WHEN c.id_unidad_producto_inicio = u_access.id THEN c.cantidad
        ELSE (1 / c.cantidad)
    END AS factor_conversion
FROM unidad_producto u_access
JOIN conversion_unidad_producto c ON (
    c.id_unidad_producto_inicio = u_access.id OR
    c.id_unidad_producto_final  = u_access.id
)
WHERE LOWER(u_access.abreviado) = LOWER(:unidad_access)
  AND (
    -- La unidad ERP target es el otro extremo
    (c.id_unidad_producto_inicio = u_access.id AND c.id_unidad_producto_final  = :id_unidad_erp) OR
    (c.id_unidad_producto_final  = u_access.id AND c.id_unidad_producto_inicio = :id_unidad_erp)
  )
LIMIT 1;
```

### 4.3 Ingredientes sin mapeo

```sql
-- Detectar ingredientes de receta sin mapeo en el diccionario ERP
SELECT DISTINCT
    sr.CodIngrediente,
    ing.Nombre AS nombre_ingrediente,
    ing.Unidad AS unidad_access,
    COUNT(DISTINCT h.CodBatido) AS batidos_afectados

FROM SubReceta sr
INNER JOIN DBIngredientes ing ON ing.CodIngrediente = sr.CodIngrediente
INNER JOIN HistorialBatidos h ON h.CodBatido = sr.CodBatido
LEFT  JOIN Cotizaciones c ON c.CodIngrediente = sr.CodIngrediente
    AND c.Conversion = 1
LEFT  JOIN diccionario_productos_legado d ON d.CodCotizacion =
    COALESCE(sr.codporcion, c.CodCotizacion)
WHERE d.id IS NULL    -- sin mapeo
  AND h.Fecha BETWEEN :fecha_inicio AND :fecha_fin

GROUP BY sr.CodIngrediente, ing.Nombre, ing.Unidad
ORDER BY batidos_afectados DESC;
```

---

## 5. Resumen del flujo completo para reportes

```
HistorialBatidos (CodBatido, Fecha, Cantidad vendida)
    │
    ├─→ SubReceta (CodBatido, CodIngrediente, Cantidad, codporcion)
    │       │
    │       ├─→ [P1] codporcion → diccionario_productos_legado → producto_presentacion
    │       │
    │       ├─→ [P2] Cotizaciones (Conversion=1, Prioridad=1)
    │       │           → diccionario_productos_legado → producto_presentacion
    │       │
    │       └─→ [P3] Cotizaciones (cualquiera)
    │                   → diccionario_productos_legado → producto_presentacion
    │
    ├─→ PRE-CHECK: pp.Id_receta_producto IS NOT NULL?
    │       ├─ SÍ → Receta Global: consumo = SubReceta.Cantidad × N_ventas
    │       └─ NO → Continuar con resolución de unidades
    │
    ├─→ resolverUnidadERP(DBIngredientes.Unidad)
    │       ├─ Primaria: abreviado / nombre / nombres_opcionales
    │       ├─ Secundarias: multi_directos (mismo string en otras unidades)
    │       └─ Convertibles: conversion_unidad_producto (con factor)
    │
    ├─→ buscarPresentacionPorUnidades(id_maestro, [multi_directos|convertibles])
    │       → Nivel 1: multi_directos (factor=1)
    │       → Nivel 2: convertibles   (factor=conversion)
    │       → Nivel 3: unidad de Presentación Uso (factor=búsqueda directa)
    │
    └─→ cantidad_erp = (SubReceta.Cantidad × factor) / pp.cantidad
            × N_ventas (HistorialBatidos.Cantidad)
            = CONSUMO TOTAL de pp.id en el período
```

---

## 6. Consideraciones para proyecciones

- **Tendencia de consumo:** agrupar por semana/mes con `DATE_FORMAT(h.Fecha, '%Y-%m')`.
- **Ingredientes críticos:** ordenar por `consumo_total DESC` para identificar los de mayor rotación.
- **Stock mínimo:** `consumo_promedio_diario × días_de_cobertura_deseados`.
- **Variaciones estacionales:** comparar con el mismo período del año anterior.
- **Ingredientes sin conversión registrada:** flagear los que usen Nivel 3 (fallback) — pueden tener factores incorrectos si no hay conversión en `conversion_unidad_producto`.

---

*Generado: 2026-04-12 | Referencia: `accessantiguo_get_detalle_receta.php` + `accessantiguo_unidades_homologacion.php`*
