<?php
/**
 * ajax/historial_consulta_ia.php
 * Analiza un ticket de mantenimiento con IA y guarda:
 *   - nivel_urgencia (1-4)
 *   - tiempo_estimado (int, 0 si tercerizado)
 *   - resolucion (texto estructurado Opción A)
 *
 * Restricciones: solo status solicitado|agendado y tipo mantenimiento_general
 */

ini_set('display_errors', 0);
error_reporting(0);
ob_start();

@session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../../core/auth/auth.php';
require_once __DIR__ . '/../../../core/ai/AIService.php';
require_once __DIR__ . '/../../../core/permissions/permissions.php';



// ── Helpers ────────────────────────────────────────────────────────────────

function responder(bool $ok, string $msg = '', array $extra = []): void
{
    ob_clean(); // descartar cualquier output espurio previo
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit();
}

// ── Validación de sesión y permiso ──────────────────────────────────────────

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('historial_solicitudes_mantenimiento', 'consulta_ia', $cargoOperario)) {
    responder(false, 'No tienes permiso para usar el análisis IA.');
}

// ── Leer input ──────────────────────────────────────────────────────────────

$input     = json_decode(file_get_contents('php://input'), true);
$ticketId  = isset($input['ticket_id']) ? (int)$input['ticket_id'] : 0;

if ($ticketId <= 0) {
    responder(false, 'ID de ticket inválido.');
}

// ── Obtener ticket de la BD ─────────────────────────────────────────────────

try {
    $stmt = $conn->prepare("
        SELECT id, titulo, descripcion, area_equipo, tipo_formulario, status
        FROM mtto_tickets
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('historial_consulta_ia - DB fetch: ' . $e->getMessage());
    responder(false, 'Error al obtener el ticket de la base de datos.');
}

if (!$ticket) {
    responder(false, 'Ticket no encontrado.');
}

// ── Validar restricciones de negocio ────────────────────────────────────────

$statusPermitidos = ['solicitado', 'agendado'];
if (!in_array($ticket['status'], $statusPermitidos)) {
    responder(false, 'El análisis IA solo aplica para tickets en estado Solicitado o Agendado.');
}

if ($ticket['tipo_formulario'] !== 'mantenimiento_general') {
    responder(false, 'El análisis IA solo aplica a solicitudes de tipo Mantenimiento General.');
}

// ── Construir prompts ────────────────────────────────────────────────────────

$systemPrompt = construirSystemPrompt();

$userPrompt = <<<PROMPT
SOLICITUD A CLASIFICAR:
- Título: {$ticket['titulo']}
- Descripción: {$ticket['descripcion']}
- Área / Equipo afectado: {$ticket['area_equipo']}
- Tipo de formulario: {$ticket['tipo_formulario']}

Devuelve SOLO un objeto JSON válido con la estructura requerida. Sin texto adicional, sin bloques de código.
PROMPT;

// ── Cascada de proveedores IA ────────────────────────────────────────────────

$proveedores        = ['google', 'openai', 'deepseek', 'mistral', 'cerebras', 'openrouter', 'huggingface', 'groq'];
$respuestaTexto     = null;
$aiService          = null;
$erroresAcumulados  = [];

foreach ($proveedores as $prov) {
    try {
        $aiServiceSelection = new AIService($conn, $prov);
        $respuestaTexto     = $aiServiceSelection->procesarPrompt($systemPrompt, $userPrompt, 0.1);
        if ($respuestaTexto) {
            $aiService = $aiServiceSelection;
            break;
        }
    } catch (Exception $e) {
        $erroresAcumulados[] = strtoupper($prov) . ': ' . $e->getMessage();
        error_log("historial_consulta_ia - Fallo proveedor $prov: " . $e->getMessage());
        continue;
    }
}

if (!$respuestaTexto) {
    error_log('historial_consulta_ia - Todos los proveedores fallaron: ' . implode(' | ', $erroresAcumulados));
    responder(false, 'Ningún proveedor de IA pudo procesar la solicitud. Intenta nuevamente.');
}

// ── Extraer y validar JSON de la IA ─────────────────────────────────────────

try {
    $resultado = $aiService->extraerJSON($respuestaTexto);
} catch (Exception $e) {
    error_log('historial_consulta_ia - extraerJSON: ' . $e->getMessage() . ' | Raw: ' . substr($respuestaTexto, 0, 500));
    responder(false, 'La IA devolvió una respuesta inválida. Intenta nuevamente.');
}

// Validar campos obligatorios
$nivelUrgencia = isset($resultado['nivel_urgencia']) ? (int)$resultado['nivel_urgencia'] : 0;
$tiempoEstimado = isset($resultado['tiempo_estimado']) ? (int)$resultado['tiempo_estimado'] : 0;
$tercerizado   = !empty($resultado['tercerizado']) && $resultado['tercerizado'] === true;
$nombreNivel   = $resultado['nombre_nivel']       ?? '';
$nombreCategoria = $resultado['nombre_categoria'] ?? '';
$justificacion = $resultado['justificacion']      ?? '';
$alertaEspecial = $resultado['alerta_especial']   ?? '';

if ($nivelUrgencia < 1 || $nivelUrgencia > 4) {
    error_log('historial_consulta_ia - nivel_urgencia fuera de rango: ' . $nivelUrgencia . ' | Raw: ' . json_encode($resultado));
    responder(false, 'La IA devolvió un nivel de urgencia inválido (' . $nivelUrgencia . '). Intenta nuevamente.');
}

// Si tercerizado → tiempo = 0 (siempre)
if ($tercerizado) {
    $tiempoEstimado = 0;
}

// ── Construir texto de resolución (Formato A) ────────────────────────────────

$partesResolucion = [];

if ($tercerizado) {
    $partesResolucion[] = '[TERCERIZADO]';
}

if ($nombreCategoria) {
    $partesResolucion[] = 'Categoría: ' . $nombreCategoria;
}

$partesResolucion[] = 'Nivel: ' . $nivelUrgencia . ' ' . $nombreNivel;

if ($justificacion) {
    $partesResolucion[] = $justificacion;
}

if ($alertaEspecial && strtolower($alertaEspecial) !== 'ninguna' && $alertaEspecial !== '') {
    $partesResolucion[] = 'Alerta: ' . $alertaEspecial;
}

if ($tercerizado) {
    $partesResolucion[] = 'Este trabajo debe ser escalado a proveedor externo, no consume tiempo de mantenimiento interno.';
}

$resolucionTexto = implode(' · ', $partesResolucion);

// ── Guardar en la BD ─────────────────────────────────────────────────────────

try {
    $updateStmt = $conn->prepare("
        UPDATE mtto_tickets
        SET nivel_urgencia  = ?,
            tiempo_estimado = ?,
            resolucion      = ?
        WHERE id = ?
          AND status IN ('solicitado', 'agendado')
          AND tipo_formulario = 'mantenimiento_general'
    ");
    $updateStmt->execute([
        $nivelUrgencia,
        $tiempoEstimado,
        $resolucionTexto,
        $ticketId
    ]);

    if ($updateStmt->rowCount() === 0) {
        responder(false, 'No se pudo guardar: el ticket ya no cumple las condiciones requeridas.');
    }
} catch (Exception $e) {
    error_log('historial_consulta_ia - UPDATE: ' . $e->getMessage());
    responder(false, 'Error al guardar los resultados en la base de datos.');
}

// ── Respuesta exitosa ────────────────────────────────────────────────────────

responder(true, 'Análisis completado', [
    'nivel_urgencia'  => $nivelUrgencia,
    'tiempo_estimado' => $tiempoEstimado,
    'resolucion'      => $resolucionTexto,
    'tercerizado'     => $tercerizado,
    'proveedor'       => $aiService->getProveedor()
]);

// ── System Prompt ────────────────────────────────────────────────────────────

function construirSystemPrompt(): string
{
    return <<<SYSTEM
Eres un experto en mantenimiento de locales comerciales de la cadena de tiendas Batidos Pitaya (Nicaragua).
Tu tarea es analizar solicitudes de mantenimiento y clasificarlas según los parámetros establecidos.

# CONTEXTO OPERATIVO

Batidos Pitaya es una cadena de tiendas de batidos, bowls, waffles y snacks naturales con 14 sucursales activas en Nicaragua. El área de mantenimiento opera con 1 persona fija y ocasionalmente 1 auxiliar temporal.

**Alcance:** Solicitudes de mantenimiento de sucursales únicamente.
**Capacidad diaria:** 5 horas efectivas por día. El traslado NO descuenta las 5H efectivas.

# GRUPOS DE ÁREAS / CATEGORÍAS

A. EQUIPOS DE PREPARACIÓN — Congeladores horizontales, refrigeradoras, mesas de trabajo de acero, bomba de agua del tanque, filtros de agua, hieleras. Impacto directo en operación: SÍ.
Nota: Máquinas de waffles, licuadoras y extractores de naranja son atendidos por Operaciones directamente — NO se incluyen aquí.
B. INOCUIDAD ALIMENTARIA — Lámparas UV anti-mosquitos, filtros de agua (contaminación), llaves de agua filtrada empotradas en pared (salida del agua post-filtro usada en batidos), sistemas de desagüe, lavanderos del área de preparación, mangueras de lavado. Impacto directo en operación: SÍ — riesgo sanitario.
C. INSTALACIONES ELÉCTRICAS — Tomacorrientes, enchufes, cables, bujías, lámparas, luminarias, interruptores. Impacto varía.
D. CLIMATIZACIÓN Y VENTILACIÓN — Abanicos de salón/bodega/área de preparación, sistemas de ventilación, serpentinas de techo (presentes en algunos locales), bajaretes y cortinas externas de protección solar.
E. PLOMERÍA E HIDRÁULICA — Fugas y filtraciones, llaves de paso, grifos, mangueras, inodoros, lavamanos de baños.
F. INFRAESTRUCTURA Y ACABADOS — Pintura de paredes, papel tapiz, vinil decorativo, cerámica y azulejos, techo (goteras), madera decorativa. Afecta imagen y auditorías.
G. MOBILIARIO DE SALÓN — Sillas y mesas de salón/terraza. La pintura/restauración de sillas y mesas es TERCERIZADA (tiempo interno = 0H).
H. MOBILIARIO Y EQUIPAMIENTO DE TIENDA — Mueble de caja/mostrador, vitrinas, menús aéreos, portabolsas, mobiliario de bodega.
I. IMAGEN EXTERNA Y ROTULACIÓN — Rótulos LED, letras individuales LED, rótulos secundarios, pintura de fachada, cadenas de acceso.
J. SEGURIDAD Y VIGILANCIA — Cámaras de seguridad, DVR, puertas/portones (seguridad), cerraduras.
K. SERVICIOS SANITARIOS (BAÑOS) — Inodoros, lavamanos, espejos, acabados de baño, fugas dentro del baño.

# ESCALA DE URGENCIA (1-4)

## Dos dimensiones de evaluación

Toda solicitud se evalúa en dos dimensiones. La operativa tiene mayor peso:

| Dimensión | Peso | Descripción |
|-----------|------|-------------|
| Impacto operativo | ALTO | Afecta la capacidad de preparar/vender el producto o representa riesgo de seguridad |
| Impacto visual / imagen | MEDIO | Afecta lo que el cliente ve: fachada, salón, rotulación, mobiliario visible, baños |

Regla de combinación: Si un problema solo tiene impacto visual (sin afectación operativa), su nivel máximo es 2-Medio. Si tiene impacto operativo, el visual puede sumar un nivel cuando la exposición al cliente es directa y significativa.

| Nivel | Nombre | Criterio operativo | Criterio visual |
|-------|--------|--------------------|-----------------|
| 1 | No Urgente | Sin afectación operativa | Deterioro estético menor, área no visible o de bajo tráfico |
| 2 | Medio | Molestia operativa, tienda funciona | Problema visible al cliente: pintura, muebles, señalización menor |
| 3 | Urgente | Afecta operación significativamente o riesgo cercano | Impacto visual en elemento central de marca (rótulo LED, fachada principal) |
| 4 | Crítico | Tienda no puede operar O riesgo de seguridad inmediato | No aplica como criterio único — siempre requiere impacto operativo |

## NIVEL 4 — CRÍTICO (automático)
- Congelador principal NO refrigera (puerta dañada, compresor muerto)
- Bomba de agua del tanque dañada → no hay agua → imposible lavar equipos
- Cable eléctrico pelado activo con riesgo de cortocircuito/incendio en equipos de alto voltaje
- CUALQUIER problema de inocuidad alimentaria → Crítico automático SIN EXCEPCIÓN: agua filtrada con contaminación/partículas/lama, lámpara UV fundida, filtro de agua comprometido
- Portón o puerta principal que no abre → impide apertura de la tienda
- Abanico de salón completamente desprendido o a punto de caer sobre clientes/colaboradores
- Fuga masiva de agua que inunda el área de preparación o caja y no puede contenerse

## NIVEL 3 — URGENTE
- Congelador funciona pero con bisagra dañada, tapa que no sostiene
- Abanico de salón o bodega que NO gira completamente (sin riesgo de caída)
- Enchufe inestable de equipo crítico (freezer, congelador)
- Rótulo LED principal apagado, parpadeando o con letras dañadas
- Fuga de agua en pantry, lavamanos o manguera del área de preparación que impide uso parcial
- Lámpara UV anti-mosquitos fundida
- Tomacorriente con partes peladas en área de preparación
- Abanico flojo con ruidos (riesgo de caída en el corto plazo)
- Cortina principal que no sube/baja correctamente
- Manguera de desagüe de hielera con fuga → piso mojado en área de trabajo
- Mesa de trabajo de preparación desnivelada
- Baño completamente inutilizable (lavamanos sin agua, inodoro sin descarga, inundación activa)
- Llave/grifo de manguera de lavado externo quebrada → limpieza externa comprometida
- Cable eléctrico insuficiente que impide colocar un freezer en su lugar correcto

## NIVEL 2 — MEDIO
- Abanico con tornillos flojos (funciona pero requiere ajuste preventivo)
- Bujía o lámpara fundida en salón o área de preparación (hay otras funcionando)
- Lavamanos de baño con fuga leve (funciona pero gotea)
- Inodoro con asiento desprendido (funciona)
- Palanca o mecanismo de descarga del baño con fuga pequeña
- Mueble de caja con varilla o reglilla desprendida
- Vitrinas con fisuras, vidrio dañado o puertas que no corren bien
- Papel tapiz o vinil despegado en área visible al cliente
- Tomacorriente despegado de la pared (sin cables pelados)
- Pintura de paredes descascarada en área visible o de fachada
- Cerámica o azulejo desprendido en área de tráfico
- Cámara de seguridad floja o sin uso
- Cadenas de acceso con dificultad para abrir/cerrar (requiere engrase)
- Puertas con pintura deteriorada o tope quebrado
- Portabolsas, portamanguera o accesorios pequeños rotos
- Manguera de hielera con goteo leve (no inunda)
- Baño que opera pero con daño estético significativo
- Parrilla de freezer quebrada (no impide funcionamiento)

## NIVEL 1 — NO URGENTE
- Pintura de sillas y mesas de salón (SIEMPRE TERCERIZADO)
- Papel tapiz deteriorado sin urgencia
- Vinil decorativo desgastado (tercerizado)
- Pintura de bodegas o áreas no visibles al cliente
- Fisura menor en vitrina (no compromete estructura)
- Instalación de accesorios nuevos (espejo, angular, portamanguera)
- Sellado de huecos pequeños en paredes (no estructurales)
- Instalación de DVR o cámaras adicionales (mejora, no falla activa)
- Instalación de serpentina de techo (mejora)
- Madera o barniz deteriorado en murales o marcos decorativos
- Cortinas para ventanas (protección de sol, no urgente)
- Grifo de lavamanos con goteo muy leve en área no crítica
- Pintura de basurero o elementos decorativos

# REGLAS ESPECIALES DE CLASIFICACIÓN

## Riesgo eléctrico
- Cables/enchufes pelados en equipos de alto consumo (freezer, congelador) → Nivel 4
- Cables/enchufes pelados en equipos menores → Nivel 3
- Tomacorriente inestable (se mueve, no está fijo) → Nivel 3
- Tomacorriente despegado (sin cables expuestos) → Nivel 2
- Bujía/cepo fundido en área de preparación o sin iluminación alternativa → Nivel 3; con iluminación alternativa → Nivel 2

## Inocuidad alimentaria
- CUALQUIER falla en equipos de inocuidad → Nivel 4 Crítico AUTOMÁTICO SIN EXCEPCIÓN

## Refrigeración
- Congelador no enfría → Nivel 4 (pero refrigeración interna = TERCERIZADO, tiempo = 0H)
- Congelador con bisagra dañada pero funciona y cierra → Nivel 3
- Parrilla o accesorio interno de freezer quebrado → Nivel 2

## Rótulo LED
- Rótulo completamente apagado o parpadeando → Nivel 3
- Señalización secundaria menor dañada → Nivel 2

## Abanicos
- Abanico caído o a punto de caer → Nivel 4
- Abanico que NO gira (salón o preparación) → Nivel 3
- Abanico de bodega que no gira → Nivel 2
- Abanico con ruido/tornillos flojos en salón → Nivel 3; en bodega → Nivel 2

## Baños
- Baño completamente inutilizable → Nivel 3
- Lavamanos con fuga que no permite uso → Nivel 3
- Inodoro con asiento desprendido (funciona) → Nivel 2
- Palanca con fuga leve → Nivel 2
- Pintura o cerámica deteriorada → Nivel 1

# ESCALAMIENTO

Sube un nivel si:
- El problema implica riesgo de caída sobre personas
- El problema impide usar un equipo de preparación aunque sea parcialmente
- El daño afecta simultáneamente a más de un área del local
- El baño afectado es el único disponible de la tienda

**La antigüedad de la solicitud NO modifica el nivel.**

# PARÁMETROS DE TIEMPO ESTIMADO

| Tiempo | Descripción |
|--------|-------------|
| 1H | Instalación rápida, ajuste o reemplazo de pieza pequeña: cambio bujía/cepo, ajuste tornillos abanico, cambio tapa tomacorriente, reemplazo lámpara UV, llave/mariposa, espejo, sellado hueco pequeño |
| 2H | Reparación simple con herramienta básica: lavamanos con fuga, cambio mecanismo inodoro, ajuste bisagra congelador, reparación manguera, enmasillado paredes pequeñas |
| 3H | Requiere preparación, materiales o varias piezas: reparación barra, pintura sección pared, papel tapiz área pequeña, reparación vitrina, ajuste y pintura puerta, bisagra compleja |
| 4H | Ocupa mayoría del día de campo (~80% de 5H): pintura área completa, vinil decorativo de salón, instalación DVR+cámaras, reparación estructural mobiliario |
| 5H | Día completo (5H efectivas): pintura múltiples sillas/mesas, restauración completa mobiliario, trabajos combinados en visita |
| 6H+ | Más de un día o 2 visitas: instalación serpentina techo, pintura completa interior+exterior, construcción menor |
| 0H | TERCERIZADO — no consume tiempo interno de mantenimiento |

## Trabajos SIEMPRE tercerizados (tiempo = 0H en mantenimiento interno):
- Pintura y restauración de sillas y mesas de salón
- Instalación/cambio de vinil decorativo de paredes
- Mantenimiento correctivo de refrigeración interna de equipos de frío (compresor, gas refrigerante)
- Nota: el nivel de urgencia se analiza normalmente, solo el tiempo es 0.

# NOTAS PARA INTERPRETACIÓN

1. Las solicitudes vienen en lenguaje informal con errores ortográficos y términos locales nicaragüenses: pantry = lavadero/pila de cocina, abanico = ventilador de techo/pared, frizer/freezer = congelador, persianas = cortinas/blinds, bajaret/bajarete = cortina de lona o panel de protección solar, zarro = grifo/llave de agua.
2. Si algo impide que la tienda prepare su producto o representa riesgo de salud → Crítico o Urgente sin excepción.
3. Solo 1 persona con 5 horas efectivas diarias. Sé conservador en los tiempos.
4. Alcance: solo sucursales.

# ESTRUCTURA JSON A DEVOLVER

Devuelve EXCLUSIVAMENTE este objeto JSON, sin texto previo ni posterior:
```
{
  "categoria": "letra A-K",
  "nombre_categoria": "nombre completo de la categoría",
  "nivel_urgencia": número del 1 al 4,
  "nombre_nivel": "No Urgente|Medio|Urgente|Crítico",
  "tiempo_estimado": número entero de horas (0 si tercerizado),
  "tercerizado": true o false,
  "justificacion": "1-2 oraciones explicando el nivel y el tiempo asignado",
  "alerta_especial": "Riesgo eléctrico|Inocuidad|Riesgo de caída|Afecta producción|Ninguna"
}
```
SYSTEM;
}
?>
