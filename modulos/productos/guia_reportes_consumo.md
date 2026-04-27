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
| `componentes_receta_producto` | `id_receta_producto_global`, `id_presentacion_producto`, `cantidad`, `orden` | Componentes de una receta compuesta. FK: `id_receta_producto_global` → `receta_producto_global.id`, `id_presentacion_producto` → `producto_presentacion.id` |

### 1.3 Tipos de Presentación (Banderas ERP)

| Tipo | Flag en BD | Propósito | Uso Principal |
|---|---|---|---|
| **Presentación de Receta** | `presentacion_receta = 1` | Unidad optimizada para costeo y cálculo de recetas. | Columna **Insumo Receta** |
| **Presentación de Consumo** | `presentacion_basica_inventario = 1` | Unidad estándar de inventario (unidad de balance). | Columna **Presentación Uso** |
| **Presentación de Despacho** | `presentacion_despacho = 1` | Unidad de embalaje para logística y traslados. | Columna **Presentación Despacho** |

> [!IMPORTANT]
> **Regla de Balance:** Para todo tipo de balance, auditoría de inventario o reporte de consumo real, se utilizará la **Presentación de Consumo** (`presentacion_basica_inventario = 1`) para el cálculo del consumo acumulado. Esto garantiza que el consumo reportado sea semánticamente equivalente a las existencias en el almacén.

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

### Paso 2: Resolución del Diccionario (3 etapas — sistema en cascada)

Esta es la etapa más crítica. Para cada `CodCotizacion` candidato (obtenido vía P1/P2/P3)
se busca la **Presentación de Consumo** (`presentacion_basica_inventario = 1`) en cascada:

#### Paso A — Mapeo directo (caso normal)

La cotización en el diccionario ya apunta directamente a la presentación de inventario:

```sql
SELECT d.CodCotizacion, pp.id AS id_presentacion, pp.cantidad, pp.id_unidad_producto,
       pp.id_producto_maestro, pp.Nombre, u.nombre AS unidad_erp, pm.Nombre AS nombre_maestro
FROM diccionario_productos_legado d
INNER JOIN producto_presentacion pp ON pp.id = d.id_producto_presentacion
LEFT  JOIN unidad_producto u         ON u.id  = pp.id_unidad_producto
LEFT  JOIN producto_maestro pm       ON pm.id = pp.id_producto_maestro
WHERE d.CodCotizacion IN (:lista_cods)
  AND pp.Activo = 'SI'
  AND pp.presentacion_basica_inventario = 1;
```

> **Ejemplo:** Chocolate Líquido oz → su cotización apunta directamente a la pp oz (basica=1). ✅

#### Paso B — Rastreo por maestro vía presentación mapeada

Para `CodCotizacion` no resueltos en Paso A: la cotización en el diccionario apunta a una
presentación **no basica** (ej: presentación de despacho). Se traza al maestro de esa
presentación y se busca la presentación básica del mismo maestro:

```sql
SELECT d.CodCotizacion, pp_base.id AS id_presentacion, pp_base.cantidad, ...
FROM diccionario_productos_legado d
INNER JOIN producto_presentacion pp_orig ON pp_orig.id = d.id_producto_presentacion
INNER JOIN producto_presentacion pp_base
        ON pp_base.id_producto_maestro = pp_orig.id_producto_maestro
       AND pp_base.presentacion_basica_inventario = 1
       AND pp_base.Activo = 'SI'
       AND pp_base.Id_receta_producto IS NULL
WHERE d.CodCotizacion IN (:cods_no_resueltos)
  AND pp_orig.Activo = 'SI'
  AND pp_orig.id_producto_maestro IS NOT NULL  -- requiere FK de maestro asignado
GROUP BY d.CodCotizacion;
```

> **Ejemplo:** Chocolate Líquido — diccionario apunta al pote 1.36kg (despacho, basica=0).
> El Paso B obtiene el maestro del pote → encuentra la presentación oz (basica=1). ✅

> **Limitación:** Si `pp_orig.id_producto_maestro IS NULL` (la presentación mapeada no tiene
> FK de maestro configurado), el Paso B no retorna filas → pasa al Paso C.

#### Paso C — Rastreo vía CodIngrediente en Cotizaciones (fallback completo)

Replica exacta del comportamiento **"AUTO"** del Visor de Recetas. Para `CodCotizacion`
aún sin resolver después del Paso B:

```sql
SELECT c_src.CodCotizacion, pp_base.id AS id_presentacion, pp_base.cantidad, ...
FROM Cotizaciones c_src
-- Trazar: CodCotizacion → CodIngrediente → todas sus cotizaciones
INNER JOIN Cotizaciones c_all ON c_all.CodIngrediente = c_src.CodIngrediente
-- Buscar cualquier cotización del mismo ingrediente que tenga diccionario con maestro
INNER JOIN diccionario_productos_legado d2 ON d2.CodCotizacion = c_all.CodCotizacion
INNER JOIN producto_presentacion pp_any    ON pp_any.id = d2.id_producto_presentacion
                                          AND pp_any.Activo = 'SI'
                                          AND pp_any.id_producto_maestro IS NOT NULL
-- Con ese maestro, buscar la presentación basica
INNER JOIN producto_presentacion pp_base
        ON pp_base.id_producto_maestro = pp_any.id_producto_maestro
       AND pp_base.presentacion_basica_inventario = 1
       AND pp_base.Activo = 'SI'
       AND pp_base.Id_receta_producto IS NULL
WHERE c_src.CodCotizacion IN (:cods_aun_sin_resolver)
GROUP BY c_src.CodCotizacion;
```

> **Ejemplo:** Maní Horneado — diccionario apunta a 1lb (despacho), y la presentación 1lb
> tiene `id_producto_maestro = NULL` (Paso B falla). El Paso C busca el CodIngrediente del
> maní → encuentra otras cotizaciones del mismo ingrediente → alguna lleva al maestro "Maní
> Horneado" → desde ahí encuentra la presentación oz (basica=1). ✅

> [!IMPORTANT]
> **Regla de oro:** Solo la presentación con `presentacion_basica_inventario = 1` es válida
> para consumo e inventario. Los tres pasos (A, B, C) garantizan encontrarla aunque el mapeo
> en el diccionario apunte a una presentación de despacho u otra sin el flag basica.

Con el `id_presentacion` obtenido de cualquiera de los tres pasos, continuar con Paso 3.

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

| Caso | Tipo mapeo | Factor | pp_cantidad | Fórmula | Redondeo |
|---|---|---|---|---|---|
| Porción directa | **P1** | igual a los anteriores | Cantidad de la presentación | `(SubReceta.Cantidad × factor) / pp_cantidad` | **Al 0.5 más cercano** |
| Cotización base (`Conversion=1`, `Prioridad=1`) | **P2** | `1` o conversión | Cantidad de la presentación | `(SubReceta.Cantidad × factor) / pp_cantidad` | **4 decimales** |
| Fallback (cualquier cotización disponible) | **P3** | `1` o conversión | Cantidad de la presentación | `(SubReceta.Cantidad × factor) / pp_cantidad` | **4 decimales** |
| Receta Global | — | `1` | `1` | `SubReceta.Cantidad` (sin división) | — |

#### Tipos de mapeo y sus reglas de redondeo

| Tipo | Origen del mapeo | Regla de redondeo | Color en auditoría |
|---|---|---|---|
| **P1** | `SubReceta.codporcion` apunta directamente a un `CodCotizacion` mapeado | `round(x × 2) / 2` → múltiplo de 0.5 | 🟢 Verde |
| **P2** | Cotización con `Conversion=1` y `Prioridad=1` para el ingrediente | `round(x, 4)` → 4 decimales | 🔵 Azul |
| **P3** | Primera cotización disponible para el ingrediente (fallback) | `round(x, 4)` → 4 decimales | 🟠 Naranja |

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

#### Regla de redondeo P2 / P3 — ingredientes volumétricos o gravimétricos

Los ingredientes mapeados por cotización base (P2) o fallback (P3) pueden consumirse
en fracciones de gramo, mililitro u otra unidad continua. El resultado se expresa con
**4 decimales** para preservar precisión en el consumo total acumulado.

> **Ejemplo:**
> - Receta en Access: **15 g** de proteína (P2, sin codporcion)
> - Presentación ERP: **500 g** (pp_cantidad = 500)
> - `consumo_crudo = 15 / 500 = 0.03`
> - `consumo_final = round(0.03, 4) = **0.0300**`
>
> Para 1 000 ventas: consumo total = **30.0000** unidades de 500 g

En PHP:

```php
// Redondeo según tipo de mapeo
if ($esP1) {
    $consumoFinal = round($consumoCrudo * 2) / 2;   // múltiplo de 0.5
} else {
    $consumoFinal = round($consumoCrudo, 4);          // P2 y P3: 4 decimales
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
    crc.id_presentacion_producto   AS id_componente,
    pp_comp.Nombre                 AS componente,
    crc.cantidad                   AS cantidad_componente,
    u.nombre                       AS unidad_componente,
    -- consumo per batido = crc.cantidad × (sr.Cantidad en Access)
    (crc.cantidad * :consumo_receta_global) AS consumo_total_componente

FROM componentes_receta_producto crc
INNER JOIN receta_producto_global rpg ON rpg.id = crc.id_receta_producto_global
INNER JOIN producto_presentacion pp_comp ON pp_comp.id = crc.id_presentacion_producto
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

### 4.4 Resolución de Presentación Despacho (`presentacion_despacho = 1`)

Analogía directa de los Pasos A/B/C pero orientada a encontrar la **unidad de embalaje/logística**
en lugar de la unidad de inventario. Se ejecuta **después** de resolver la Presentación Uso.

#### Equivalencia de pasos

| Paso basica (Pasos A/B/C) | Equivalente despacho | Descripción |
|---|---|---|
| **Paso A** — mapeo directo `basica=1` | **Nivel 1** — misma unidad + `despacho=1` | Misma unidad Access directa en el mismo maestro |
| **Paso A** (unidades convertibles) | **Nivel 2** — unidad convertible + `despacho=1` | Unidades homologadas del mismo grupo de medida |
| **Paso B** — cualquier `basica=1` del maestro | **Fallback 1** — cualquier `despacho=1` del maestro | Sin restricción de unidad, mismo `id_producto_maestro` |
| **Paso C** — rastreo vía CodIngrediente | **Fallback 2** — receta de 1 componente = Presentación Uso | Independiente del maestro. Cubre paquetes con maestro diferente **y también** productos de uso que son recetas sin maestro |

> [!IMPORTANT]
> **El Fallback 2 se ejecuta siempre**, incluso cuando `id_producto_maestro = NULL` en la Presentación Uso.
> Esto cubre el caso de ingredientes que son recetas compuestas de producción sin maestro asignado
> (ej: Mix de Waffle = receta de Masa Waffle gr + Cocoa + Avena) cuyo paquete de despacho
> (Paquete Mix Waffle 10u) los contiene como único componente.

#### Queries en cascada

```sql
-- ── NIVEL 1: mismo maestro + unidad directa + despacho=1 ────────────────
SELECT pp.id, pp.SKU, pp.Nombre, pp.cantidad, u.nombre AS unidad
FROM producto_presentacion pp
LEFT JOIN producto_maestro pm ON pm.id = pp.id_producto_maestro  -- LEFT, no INNER
LEFT JOIN unidad_producto   u  ON u.id  = pp.id_unidad_producto
WHERE pp.id_producto_maestro = :id_maestro_resolucion   -- maestro de la Presentación Uso
  AND u.nombre IN (:unidades_directas_access)            -- unidades directas de la unidad Access
  AND pp.presentacion_despacho = 1
  AND pp.Activo = 'SI'
LIMIT 1;

-- ── NIVEL 2: mismo maestro + unidad convertible + despacho=1 ────────────
-- (igual pero con unidades de conversion_unidad_producto)

-- ── FALLBACK 1: mismo maestro + cualquier unidad + despacho=1 ───────────
SELECT pp.id, pp.SKU, pp.Nombre, pp.cantidad, u.nombre AS unidad
FROM producto_presentacion pp
LEFT JOIN producto_maestro pm ON pm.id = pp.id_producto_maestro
LEFT JOIN unidad_producto   u  ON u.id  = pp.id_unidad_producto
WHERE pp.id_producto_maestro = :id_maestro_resolucion
  AND pp.presentacion_despacho = 1
  AND pp.Activo = 'SI'
LIMIT 1;

-- ── FALLBACK 2: receta de 1 componente = Presentación Uso ───────────────
-- Cubre paquetes con maestro DIFERENTE al de la Presentación Uso
SELECT pp.id, pp.SKU, pp.Nombre, pp.cantidad, u.nombre AS unidad
FROM producto_presentacion pp
LEFT JOIN producto_maestro pm ON pm.id = pp.id_producto_maestro   -- LEFT: puede ser NULL
LEFT JOIN unidad_producto   u  ON u.id  = pp.id_unidad_producto
WHERE pp.Id_receta_producto IS NOT NULL
  AND pp.Activo = 'SI'
  AND pp.presentacion_despacho = 1
  AND (
      SELECT COUNT(DISTINCT crp.id_presentacion_producto)
      FROM componentes_receta_producto crp
      WHERE crp.id_receta_producto_global = pp.Id_receta_producto
  ) = 1
  AND EXISTS (
      SELECT 1
      FROM componentes_receta_producto crp2
      WHERE crp2.id_receta_producto_global = pp.Id_receta_producto
        AND crp2.id_presentacion_producto = :id_presentacion_uso  -- id de la Presentación Uso
  )
LIMIT 1;
```

> [!IMPORTANT]
> **LEFT JOIN en lugar de INNER JOIN:** Todos los queries de resolución de despacho usan
> `LEFT JOIN producto_maestro`. Los productos compuestos de embalaje (ristras, bolsas, cajillas)
> pueden tener `id_producto_maestro = NULL` porque son presentaciones de producción,
> no presentaciones simples de un ingrediente. Un `INNER JOIN` los excluiría silenciosamente.

#### Cuándo se activa el Fallback 2

**Caso 1 — Paquete con maestro diferente al producto de uso:**
```
nuevo_producto (Presentación Uso, id=35, maestro=21 "Vaso Plástico 16oz")
    │
    └─→ Niveles 1 y 2 fallan (sin conversiones para "Unidades")
    └─→ Fallback 1 falla (Ristra tiene maestro diferente o NULL)
    └─→ Fallback 2 (corre fuera del bloque if-maestro):
            pp.Id_receta_producto IS NOT NULL    (Ristra es una receta)
            COUNT(componentes) = 1              (solo un ingrediente: Vaso 16oz Unid)
            componente.id_presentacion = 35     (= id de la Presentación Uso)
            → Encuentra: PQ-VASO16OZ-01 "Ristra 25u" ✅
```

**Caso 2 — Presentación Uso es en sí una receta sin maestro:**
```
nuevo_producto (Presentación Uso, id=139, maestro=NULL "Mix de Waffle")
    │                   ← Es una receta de producción sin id_producto_maestro
    └─→ if ($idMaestroResolucion) → FALSE → Niveles 1, 2 y Fallback 1 no se ejecutan
    └─→ Fallback 2 (corre SIEMPRE, fuera del bloque if-maestro):
            pp.Id_receta_producto IS NOT NULL    (Paquete Mix Waffle es una receta)
            COUNT(componentes) = 1              (solo un ingrediente: Mix de Waffle)
            componente.id_presentacion = 139    (= id de la Presentación Uso)
            → Encuentra: id=140 "Paquete Mix Waffle 10u" ✅
```

> [!NOTE]
> **Regla de implementación:** El Fallback 2 debe vivir **fuera** del `if ($idMaestroResolucion)`,
> después del cierre del bloque de maestro. Solo requiere que `nuevo_producto['id_presentacion']`
> esté definido. Ver `accessantiguo_get_detalle_receta.php` líneas ~335–385.

---


```
HistorialBatidos (CodBatido, Fecha, Cantidad vendida)
    │
    ├─→ SubReceta (CodBatido, CodIngrediente, Cantidad, codporcion)
    │       │
    │       ├─→ [P1] codporcion → RESOLUCIÓN DICCIONARIO (3 etapas)
    │       ├─→ [P2] Cotizaciones (Conversion=1, Prioridad=1) → RESOLUCIÓN DICCIONARIO
    │       └─→ [P3] Cotizaciones (cualquiera) → RESOLUCIÓN DICCIONARIO
    │
    ├─→ RESOLUCIÓN DICCIONARIO (en cascada hasta encontrar basica_inventario=1):
    │       ├─ [Paso A] CodCotizacion → diccionario → pp (basica=1)          ← caso normal
    │       ├─ [Paso B] CodCotizacion → diccionario → pp_orig.maestro → pp_base (basica=1)
    │       │           └─ Requiere: pp_orig.id_producto_maestro IS NOT NULL
    │       └─ [Paso C] CodCotizacion → Cotizaciones.CodIngrediente
    │                   → todas cotizaciones del ingrediente → diccionario
    │                   → cualquier pp con maestro → pp_base (basica=1)      ← AUTO completo
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

*Generado: 2026-04-12 | Actualizado: 2026-04-27 — Sección 4.4: Resolución de Presentación Despacho en 4 niveles. Fallback 2 ahora se ejecuta **siempre** (fuera del bloque `if id_producto_maestro`), cubriendo dos casos: (a) paquete de despacho con maestro diferente al producto de uso, y (b) producto de uso que es una receta de producción sin maestro asignado (ej: Mix de Waffle id=139 → Paquete Mix Waffle 10u id=140). Condición del Fallback 2: `pp.Id_receta_producto IS NOT NULL` + `COUNT(componentes)=1` + `componente.id_presentacion = id_presentacion_uso`. Todos los queries de despacho usan `LEFT JOIN producto_maestro` para no excluir paquetes sin maestro. | Referencia: `accessantiguo_get_detalle_receta.php` + `accessantiguo_unidades_homologacion.php` + `dashboard_consumo_get_datos.php` + `balance_inventario_get_datos.php`*
