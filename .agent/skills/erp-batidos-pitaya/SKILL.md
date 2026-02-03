---
name: ERP Batidos Pitaya Development
description: Comprehensive skill for developing modules and tools in the Batidos Pitaya ERP system following established standards and architecture
---

# ERP Batidos Pitaya Development Skill

Esta skill te guía en el desarrollo de módulos y herramientas para el Sistema ERP de Batidos Pitaya, siguiendo los estándares establecidos en la documentación del proyecto.

## 🎯 Contexto del Proyecto

**Sistema**: ERP modular para Batidos Pitaya  
**Stack**: PHP + MySQL  
**Arquitectura**: Componentes globales compartidos con estructura estandarizada  
**Ritmo**: ~1 herramienta completa por día  

## 📋 Antes de Empezar

Cuando el usuario solicite crear una nueva herramienta, **SIEMPRE pregunta**:

1. **Nombre del módulo** (ej: cupones, auditorías, vacaciones)
2. **Área/Carpeta** (marketing, rrhh, operaciones, gerencia, etc.)
3. **Funcionalidades específicas** requeridas
4. **Permisos necesarios** (además del obligatorio `vista`)
5. **Sufijo para archivos y tablas** (ej: `cupones_`, `vacaciones_`)

## 🏗️ Arquitectura Obligatoria

### Estructura de Archivos por Módulo

```
📁 modulos/{area}/
├── {herramienta}.php              # Archivo principal
├── uploads/                        # Archivos subidos (max 10MB)
├── css/
│   └── {herramienta}.css          # CSS personalizado
├── js/
│   └── {herramienta}.js           # JavaScript personalizado
└── ajax/
    ├── {herramienta}_guardar.php
    ├── {herramienta}_get_datos.php
    ├── {herramienta}_get_opciones_filtro.php
    ├── {herramienta}_get_{item}.php
    └── {herramienta}_eliminar.php
```

### Áreas del Sistema

- `ventas/` - Historial de ventas, cupones
- `rh/` - Recursos humanos
- `operaciones/` - Gestión de sucursales
- `marketing/` - Cupones, promociones
- `supervision/` - Auditorías y control
- `sucursales/` - Herramientas de punto de venta
- `sistemas/` - Control de permisos
- `mantenimiento/` - Gestión de activos
- `gerencia/` - Dirección general
- `compras/` - Gestión de OC, facturas
- `contabilidad/` - Descarga de datos
- Y más... (ver docs/00_Instrucciones_Generales.md líneas 31-54)

## 🎨 Identidad Visual

### Colores Corporativos

```css
/* Color principal */
--color-principal: #51B8AC;

/* Encabezado de tablas */
--color-header-tabla: #0E544C;

/* Botones de acción */
--btn-nuevo: #218838;
--btn-nuevo-hover: #1d6f42;
--btn-principal: #51B8AC;
```

### Tipografía

```css
font-family: 'Calibri', sans-serif;
font-size: clamp(12px, 2vw, 18px);
```

### Principios de Diseño

- ❌ **NO usar degradados**
- ✅ **Estilo minimalista y limpio**
- ✅ **Mobile-first responsive**

## 🔐 Sistema de Permisos

### Implementación Obligatoria en Archivo Principal

```php
<?php
require_once '../../core/auth/auth.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso (SIEMPRE debe existir permiso 'vista')
if (!tienePermiso('nombre_herramienta', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}
?>
```

### Función de Permisos

```php
tienePermiso($nombreHerramienta, $nombreAccion, $codNivelCargo)
```

### Permisos Típicos por Módulo

- `vista` - **OBLIGATORIO** para todas las herramientas
- `nuevo_registro` - Crear registros
- `editar` - Modificar registros
- `eliminar` - Eliminar registros
- `shortcut` - Acceso rápido en index del módulo
- `aprobar_gerencia` - Aprobaciones de nivel gerencial
- `exportar_{modulo}` - Exportar a Excel

### Cargos Frecuentes

| CodNivelesCargos | Nombre |
|------------------|--------|
| 2 | Vendedor |
| 5 | Líder de Tienda |
| 8 | Jefe de Contabilidad |
| 11 | Jefe de Operaciones |
| 13 | Gerente de Desarrollo Humano |
| 15 | Líder de TI |
| 16 | Gerencia General |
| 49 | Gerencia Proyectos |

(Ver lista completa en docs/00_Instrucciones_Generales.md líneas 82-127)

## 📦 Componentes Globales

### Includes Obligatorios

```php
<?php
// SIEMPRE en este orden
require_once '../../core/auth/auth.php';                    // Incluye funciones.php y conexion.php
require_once '../../core/layout/menu_lateral.php';          // Menú lateral
require_once '../../core/layout/header_universal.php';      // Header universal
require_once '../../core/permissions/permissions.php';      // Sistema de permisos
?>
```

### Servicios Disponibles

#### Conexión a Base de Datos
```php
require_once '../../core/database/conexion.php';
// Variable $conn disponible globalmente
// Charset: UTF-8
// Zona horaria: America/Managua
```

#### Funciones de Usuarios
```php
require_once '../../core/helpers/funciones.php';

// Funciones disponibles:
obtenerNombreCompleto($id_empleado)
obtenerCargo($id_empleado)
obtenerSucursal($id_empleado)
verificarPermiso($permiso)
```

#### Envío de Correos
```php
require_once '../../core/email/EmailService.php';

// Funciones disponibles:
obtenerEmailPorCargo($codNivelCargo)
enviarCorreo($remitenteId, $destinatarios, $asunto, $cuerpoHtml, $archivos = [])
obtenerCredencialesUsuario($codOperario)
```

## 📝 Reglas de Codificación

### PHP

- ✅ **SIEMPRE** usar `prepared statements` para SQL
- ✅ Validar y sanitizar **TODOS** los inputs
- ✅ Usar `try-catch` para operaciones críticas
- ✅ Comentar código complejo
- ✅ Nombres de variables en español descriptivos

### JavaScript

- ✅ Funciones con nombres descriptivos en español
- ✅ Usar `async/await` para AJAX
- ✅ Validar formularios antes de enviar
- ✅ Mostrar loaders durante operaciones
- ✅ Mensajes claros con SweetAlert2

### CSS

- ✅ Mobile-first responsive
- ✅ Usar variables CSS para colores
- ✅ Clases descriptivas con prefijo del módulo
- ✅ Consistencia con estilos globales

### SQL

- ✅ Nombres de tablas: `{herramienta}_`
- ✅ Campos de auditoría: `fecha_creacion`, `usuario_creacion`
- ✅ IDs auto-increment
- ✅ Foreign keys con ON DELETE/UPDATE apropiados

## 🎨 Estructura HTML Estándar

### Head Section

```html
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nombre de la Herramienta</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/{herramienta}.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>
```

### Body Structure

```html
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Título de la Herramienta'); ?>
            
            <div class="container-fluid p-3">
                <!-- Contenido aquí -->
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/{herramienta}.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>
```

## 📘 Modal de Ayuda Universal (OBLIGATORIO)

### Implementación Requerida

**TODAS las herramientas deben incluir un modal de ayuda** con ID estándar `pageHelpModal`. El header universal incluye un botón de ayuda (ícono "i" turquesa) que detecta automáticamente este modal.

### Estructura del Modal

Agregar **antes del cierre de `</body>`**:

```html
<!-- Modal de Ayuda -->
<div class="modal fade" id="pageHelpModal" tabindex="-1" 
     aria-labelledby="pageHelpModalLabel" aria-hidden="true" 
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="pageHelpModalLabel">
                    <i class="fas fa-info-circle me-2"></i>
                    Guía de {Nombre de la Herramienta}
                </h5>
                <button type="button" class="btn-close btn-close-white" 
                        data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- CONTENIDO PERSONALIZADO -->
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card h-100 border-0 bg-light">
                            <div class="card-body">
                                <h6 class="text-primary border-bottom pb-2 fw-bold">
                                    <i class="fas fa-check me-2"></i> Sección 1
                                </h6>
                                <p class="small text-muted mb-0">
                                    Descripción de funcionalidad...
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Z-index para evitar que backdrop cubra el modal */
    #pageHelpModal {
        z-index: 1060 !important;
    }
    .modal-backdrop {
        z-index: 1050 !important;
    }
</style>
```

### Contenido del Modal

El modal debe documentar:

1. **Funcionalidades principales** - Qué hace la herramienta
2. **Permisos y roles** - Quién puede hacer qué
3. **Flujo de trabajo** - Cómo usar la herramienta
4. **Reglas de negocio** - Validaciones y restricciones
5. **Casos especiales** - Situaciones importantes a considerar

### Elementos Recomendados

```html
<!-- Cards con iconos de colores -->
<div class="card border-0 bg-light">
    <div class="card-body">
        <h6 class="text-warning border-bottom pb-2 fw-bold">
            <i class="fas fa-exclamation-triangle me-2"></i> Importante
        </h6>
        <p class="small text-muted mb-0">Información crítica...</p>
    </div>
</div>

<!-- Alertas informativas -->
<div class="alert alert-info py-2 px-3 small">
    <strong><i class="fas fa-info-circle me-1"></i> Nota:</strong>
    <br>
    Información adicional relevante.
</div>
```

### Reglas Obligatorias

- ✅ **ID**: Siempre `id="pageHelpModal"`
- ✅ **Backdrop**: Incluir `data-bs-backdrop="static"`
- ✅ **Z-index**: Incluir CSS de z-index
- ✅ **Tamaño**: Mínimo `modal-lg`
- ✅ **Contenido**: Documentación útil y completa
- ❌ **No**: Modales vacíos o sin información relevante


## 📊 Sistema de Filtros para Tablas

### Tipos de Filtro

1. **Texto Libre** (`data-type="text"`)
   - Para textos grandes o códigos autogenerados
   - Input de búsqueda libre

2. **Número con Rango** (`data-type="number"`)
   - Para cantidades
   - Inputs min y max

3. **Rango de Fechas** (`data-type="daterange"`)
   - Un calendario: desde/hasta
   - Formato visual de calendario

4. **Lista Definida** (`data-type="list"`)
   - Para datos con enum o consultas limitadas
   - Checkboxes con búsqueda
   - Aplica para: sucursales, cargos, tipos, estados, etc.

### Estructura de Encabezado de Tabla

```html
<thead>
    <tr>
        <th data-column="nombre_columna" data-type="text">
            Nombre Columna
            <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
        </th>
        <th data-column="monto" data-type="number">
            Monto
            <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
        </th>
        <th data-column="fecha" data-type="daterange">
            Fecha
            <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
        </th>
        <th data-column="estado" data-type="list">
            Estado
            <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
        </th>
        <th style="width: 150px;">Acciones</th>
    </tr>
</thead>
```

## 🎯 Columnas Estándar en Tablas

### Columna de Estado (Badges)

```html
<td>
    <span class="badge bg-success">Activo</span>
    <span class="badge bg-warning text-dark">Pendiente</span>
    <span class="badge bg-danger">Inactivo</span>
</td>
```

### Columna de Acciones

```html
<td>
    <?php if (tienePermiso('herramienta', 'editar', $cargoOperario)): ?>
    <button class="btn-accion btn-editar" onclick="editar(id)" title="Editar">
        <i class="bi bi-pencil"></i>
    </button>
    <?php endif; ?>
    
    <?php if (tienePermiso('herramienta', 'eliminar', $cargoOperario)): ?>
    <button class="btn-accion btn-eliminar" onclick="eliminar(id)" title="Eliminar">
        <i class="bi bi-trash"></i>
    </button>
    <?php endif; ?>
</td>
```

## 📄 Paginación Estándar

### HTML

```html
<div class="d-flex justify-content-between align-items-center mt-3">
    <div class="d-flex align-items-center gap-2">
        <label class="mb-0">Mostrar:</label>
        <select class="form-select form-select-sm" id="registrosPorPagina" 
                style="width: auto;" onchange="cambiarRegistrosPorPagina()">
            <option value="25" selected>25</option>
            <option value="50">50</option>
            <option value="100">100</option>
        </select>
        <span class="mb-0">registros</span>
    </div>
    <div id="paginacion"></div>
</div>
```

### JavaScript

```javascript
const elementosPorPagina = [10, 25, 50, 100, 500];
let paginaActual = 1;
let registrosPorPagina = 25;
```

## 📚 Librerías Disponibles

- **jQuery 3.x** - DOM manipulation
- **Bootstrap 5.x** - UI framework
- **SweetAlert2** - Alertas bonitas
- **DataTables** (opcional) - Tablas avanzadas
- **Select2** (opcional) - Dropdowns mejorados
- **Chart.js** (opcional) - Gráficas

## 🗄️ Esquema de Base de Datos

### Tablas Principales del Sistema

#### Operarios
```sql
-- Todos los colaboradores del sistema
-- Campos clave: CodOperario, Nombre, Apellido, email_trabajo, CodNivelesCargos
```

#### NivelesCargos
```sql
-- Todos los cargos con permisos
-- Campos clave: CodNivelesCargos, Nombre, Area, Peso
```

#### AsignacionNivelesCargos
```sql
-- Asignación histórica de cargos a operarios
-- Lógica: Fin IS NULL OR Fin >= CURDATE() AND Fecha <= CURDATE()
```

#### Sucursales
```sql
-- Información de sucursales
-- Campos clave: id, codigo, nombre, activa, sucursal (boolean)
```

#### tools_erp
```sql
-- Registro de herramientas del sistema
-- Campos: id, nombre, titulo, tipo_componente ('herramienta','indicador','balance'), grupo, descripcion, url_real, url_alias(para mascara de url), icono(icono relacionado a herramienta)
```

#### acciones_tools_erp
```sql
-- Acciones disponibles por herramienta
-- Campos: id, tool_erp_id, nombre_accion, descripcion
```

#### permisos_tools_erp
```sql
-- Permisos por cargo para cada acción
-- Campos: id, accion_tool_erp_id, CodNivelesCargos, permiso (allow/deny)
```

(Ver esquema completo en docs/03_Esquema_BaseDatos.md)

## 🚀 Proceso de Generación de Nueva Herramienta

### 1. Recopilar Información

Preguntar al usuario:
- Nombre del módulo
- Área (carpeta)
- Funcionalidades específicas
- Permisos requeridos
- Sufijo de archivos/tablas

### 2. Generar Estructura Completa

- ✅ Archivo PHP principal
- ✅ CSS específico
- ✅ JavaScript con todas las funciones
- ✅ Archivos AJAX necesarios
- ✅ SQL para crear tablas
- ✅ Carpeta uploads (si aplica, max 10MB)

### 3. Incluir Documentación

- ✅ Lista de herramientas (nombre/código) a crear manualmente
- ✅ Lista de permisos a crear manualmente
- ✅ Instrucciones de implementación
- ✅ Consideraciones especiales

### 4. Validar Contra Patrones

- ✅ ¿Usa header_universal?
- ✅ ¿Implementa permisos?
- ✅ ¿Sigue estructura de carpetas?
- ✅ ¿Colores corporativos correctos?
- ✅ ¿AJAX devuelve JSON?

## 📦 Entregables Esperados

Para cada módulo nuevo:

1. ✅ Todos los archivos de código
2. ✅ Script SQL completo y probado
3. ✅ Lista de herramientas a crear en `tools_erp`
4. ✅ Lista de permisos necesarios
5. ✅ Instrucciones de implementación
6. ✅ Notas sobre configuración especial

**Formato**: Archivos separados listos para copiar/pegar

## 💡 Recordatorios Importantes

- Siempre seguir herramienta de ejemplo como referencia (docs/04_Plantilla_Modulo_Referencia.md)
- Mantener consistencia con módulos existentes
- Código limpio, comentado y profesional
- Pensar en escalabilidad y mantenimiento
- Validar en frontend **Y** backend
- Responsive design obligatorio
- Usar permisos granulares
- Formato de fechas: `dia-mes-año` (01-Ene-25)

## 📖 Referencias

- **Instrucciones Generales**: `docs/00_Instrucciones_Generales.md`
- **Estándares UI/UX**: `docs/01_Estandares_UI_UX.md`
- **Core Global**: `docs/02_Core_Global_Docs.md`
- **Esquema BD**: `docs/03_Esquema_BaseDatos.md`
- **Plantilla Referencia**: `docs/04_Plantilla_Modulo_Referencia.md`

---

## 🎯 Uso de Esta Skill

Cuando trabajes en el ERP de Batidos Pitaya:

1. **Lee esta skill** antes de comenzar cualquier desarrollo
2. **Sigue los estándares** establecidos aquí
3. **Consulta las referencias** para detalles específicos
4. **Valida tu código** contra los patrones definidos
5. **Genera documentación completa** para cada entregable
