<?php
// Al inicio del archivo, verificar autenticación y acceso al módulo
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../core/layout/header_universal.php';
require_once '../../../core/layout/menu_lateral.php';
require_once '../../../core/permissions/permissions.php';

//******************************Estándar para header******************************

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
// Verificar acceso al módulo
if (!tienePermiso('desempeno_sucursales', 'vista', $cargoOperario)) {
    header('Location: ../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Valores iniciales del filtro (mes y año actual por defecto)
$mes_seleccionado = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('n');
$anio_seleccionado = isset($_GET['anio']) ? (int) $_GET['anio'] : (int) date('Y');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Desempeño Acumulado</title>

    <!-- Bootstrap 5 (requerido para el modal de ayuda) — cargado primero para que CSS propio tenga prioridad -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <!-- CSS propio -->
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="css/desempeno_sucursales_v2.css?v=<?php echo mt_rand(1, 10000); ?>">

    <!-- Icono y librerías externas -->
    <link rel="icon" href="icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Desempeño de Tienda'); ?>

            <div class="contenedor-principal">

                <!-- ── Filtros ──────────────────────────────────────────── -->
                <div class="filtros-container">
                    <form id="dsv2-form" class="filtro">
                        <label for="mes"><i class="fas fa-calendar-alt"></i> Mes:</label>
                        <select name="mes" id="mes">
                            <?php
                            $meses = [
                                1 => 'Enero',
                                2 => 'Febrero',
                                3 => 'Marzo',
                                4 => 'Abril',
                                5 => 'Mayo',
                                6 => 'Junio',
                                7 => 'Julio',
                                8 => 'Agosto',
                                9 => 'Septiembre',
                                10 => 'Octubre',
                                11 => 'Noviembre',
                                12 => 'Diciembre',
                            ];
                            foreach ($meses as $num => $nombre) {
                                $selected = ($num == $mes_seleccionado) ? 'selected' : '';
                                echo "<option value='$num' $selected>$nombre</option>";
                            }
                            ?>
                        </select>

                        <label for="anio"><i class="fas fa-history"></i> Año:</label>
                        <select name="anio" id="anio">
                            <?php
                            $anio_actual = (int) date('Y');
                            for ($i = $anio_actual; $i >= $anio_actual - 5; $i--) {
                                $selected = ($i == $anio_seleccionado) ? 'selected' : '';
                                echo "<option value='$i' $selected>$i</option>";
                            }
                            ?>
                        </select>

                        <button type="submit"><i class="fas fa-filter"></i> Filtrar</button>
                    </form>
                </div>

                <!-- ── Spinner de carga ─────────────────────────────────── -->
                <div id="dsv2-loading" style="display:none;"></div>


                <!-- ── Tabla de resultados ──────────────────────────────── -->
                <table>
                    <thead>
                        <tr>
                            <th rowspan="2" class="header-operativo" style="text-align:center;">Tienda</th>
                            <th colspan="3" class="header-operativo" style="text-align:center;">Auditoria: %</th>
                            <th colspan="3" class="header-operativo" style="text-align:center;">Sistema: %</th>
                            <th rowspan="2" class="header-operativo" style="text-align:center;">Clientes:<br>Reclamos %
                            </th>
                            <th rowspan="2" class="header-operativo" style="text-align:center;">
                                Google: Reseñas*<br>
                                <span class="meta-encabezado">Meta Mensual: 12</span>
                            </th>
                            <th rowspan="2" class="header-resultado" style="text-align:center;">Desempeño de<br>Tienda %
                                (1)</th>
                            <th rowspan="2" class="header-resultado" style="text-align:center;">Cumplimiento
                                de<br>Ventas % (2)*</th>
                            <th rowspan="2" class="header-total-final" style="text-align:center;">Propina<br>Interna % (3)
                                </th>
                        </tr>
                        <tr>
                            <th class="header-operativo" style="text-align:center;">Limpieza</th>
                            <th class="header-operativo" style="text-align:center;">Personal</th>
                            <th class="header-operativo" style="text-align:center;">Servicio</th>
                            <th class="header-operativo" style="text-align:center;">
                                Membresías*<br>
                                <span class="meta-encabezado">Meta Mensual: 64</span>
                            </th>
                            <th class="header-operativo" style="text-align:center;">
                                Tamaño Normal*<br>
                                <span class="meta-encabezado">Meta Mensual: 85%</span>
                            </th>
                            <th class="header-operativo" style="text-align:center;">
                                Mostrador*<br>
                                <span class="meta-encabezado">Meta Mensual: 8%</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="dsv2-tbody">
                        <!-- Filas inyectadas por desempeno_sucursales_v2.js -->
                        <tr>
                            <td colspan="15" style="text-align:center;padding:20px;color:#999;">Cargando...</td>
                        </tr>
                    </tbody>
                </table>

                <!-- ── Nota Aclaratoria ──────────────────────────────── -->
                <div style="margin-top: 15px; margin-bottom: 15px; font-size: 0.85rem; color: #666; font-style: italic;">
                    <i class="fas fa-info-circle" style="color: #51B8AC; margin-right: 5px;"></i>
                    Los datos marcados con (*) se consideran acumulados hasta el día de ayer.
                </div>

                <!-- ── Sección Looker Studio (KPIs adicionales) ────────────── -->
                <div id="looker-section" class="looker-section-container">
                    <!-- Botón de recarga para el reporte (soluciona errores de conexión de Google) -->
                    <button type="button" class="btn-reload-looker" onclick="reloadLookerReport()" title="Recargar Reporte de Looker">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    <div id="looker-iframe-wrapper" class="iframe-wrapper">
                        <!-- El iframe se inyectará dinámicamente vía JS -->
                        <div class="looker-placeholder">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <p>Cargando reporte de Looker Studio...</p>
                        </div>
                    </div>
                </div>

            </div><!-- /.contenedor-principal -->
        </div><!-- /.sub-container -->
    </div><!-- /.main-container -->

    <!-- Bootstrap 5 JS (requerido para bootstrap.Modal) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JS propio -->
    <script src="js/desempeno_sucursales_v2.js?v=<?= filemtime(__DIR__ . '/js/desempeno_sucursales_v2.js') ?>"></script>

    <!-- ══════════════════════════════════════════════════════════════
         MODAL DE AYUDA — abierto desde el botón ⓘ del header
         ══════════════════════════════════════════════════════════════ -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title" id="helpModalLabel">
                        <i class="fas fa-info-circle me-2"></i>
                        Guía del Dashboard de Desempeño
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="guide-flow">

                        <!-- PASO 1: INTRODUCCIÓN -->
                        <div class="guide-card">
                            <div class="guide-card-header">
                                <i class="fas fa-eye"></i>
                                ¿Cómo leer este dashboard?
                            </div>
                            <div class="guide-card-body">
                                <p class="mb-3">Cada celda muestra un <strong>porcentaje de 0 % a 100 %</strong> e
                                    incluye un círculo de color que actúa como semáforo:</p>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="badge"
                                        style="background:#28a745; padding: 10px 14px; font-size: 0.85rem;"><i
                                            class="fas fa-check-circle me-1"></i> Verde: Bien (≥ 70%)</span>
                                    <span class="badge"
                                        style="background:#ffc107; color:#333; padding: 10px 14px; font-size: 0.85rem;"><i
                                            class="fas fa-exclamation-triangle me-1"></i> Amarillo: Regular
                                        (40-69%)</span>
                                    <span class="badge"
                                        style="background:#dc3545; padding: 10px 14px; font-size: 0.85rem;"><i
                                            class="fas fa-times-circle me-1"></i> Rojo: Bajo (< 40%)</span>
                                </div>
                            </div>
                        </div>

                        <div class="flow-arrow"><i class="fas fa-chevron-down"></i></div>

                        <!-- PASO 2: LAS 8 MÉTRICAS -->
                        <div class="text-center mb-4">
                            <h5 class="text-uppercase fw-bold" style="color:#0E544C; letter-spacing: 0.1em;">
                                <i class="fas fa-layer-group me-2"></i> Las 8 Métricas de Desempeño
                            </h5>
                            <p class="text-muted">Desglose detallado de los indicadores operativos</p>
                        </div>

                        <div class="metrics-grid">
                            <!-- 1. Limpieza -->
                            <div class="guide-card">
                                <div class="guide-card-header"><i class="fas fa-broom"></i> 1. Limpieza</div>
                                <div class="guide-card-body">
                                    <div class="card-content">
                                        Promedio de las auditorías de limpieza del mes, convertido de escala 0–5 a
                                        porcentaje.<br>
                                        <small class="text-muted">Ej.: auditoría con 4/5 → 80 %. El número entre
                                            paréntesis
                                            = cantidad de auditorías realizadas.</small>
                                    </div>
                                    <div class="meta-pill">Escala 0-5</div>
                                </div>
                            </div>
                            <!-- 2. Personal -->
                            <div class="guide-card">
                                <div class="guide-card-header"><i class="fas fa-users"></i> 2. Personal</div>
                                <div class="guide-card-body">
                                    <div class="card-content">
                                        Promedio de auditorías de presentación y actitud del equipo en tienda. Escala
                                        0–5 →
                                        porcentaje.<br>
                                        <small class="text-muted">Ej.: promedio 3.5/5 → 70 %.</small>
                                    </div>
                                    <div class="meta-pill">Escala 0-5</div>
                                </div>
                            </div>
                            <!-- 3. Servicio -->
                            <div class="guide-card">
                                <div class="guide-card-header"><i class="fas fa-concierge-bell"></i> 3. Servicio</div>
                                <div class="guide-card-body">
                                    <div class="card-content">
                                        Promedio de auditorías de calidad de atención al cliente. Escala 0–5 →
                                        porcentaje.<br>
                                        <small class="text-muted">Ej.: promedio 4.5/5 → 90 %.</small>
                                    </div>
                                    <div class="meta-pill">Escala 0-5</div>
                                </div>
                            </div>
                            <!-- 4. Membresías -->
                             <div class="guide-card">
                                 <div class="guide-card-header"><i class="fas fa-id-card"></i> 4. Membresías</div>
                                 <div class="guide-card-body">
                                     <div class="card-content">
                                         Tarjetas de membresía vendidas (Grupo 5, con Promoción 5). <strong>Meta mensual: 64
                                             unidades.</strong><br>
                                         <small class="text-muted">El KPI se calcula comparando la cantidad real vs. la <strong>meta acumulada proporcional</strong> según el día del mes (hasta ayer).</small>
                                     </div>
                                     <div class="meta-pill">Meta: 64/mes (ajustada proporcionalmente)</div>
                                 </div>
                             </div>
                            <!-- 5. Tamaño Normal -->
                            <div class="guide-card">
                                <div class="guide-card-header"><i class="fas fa-blender"></i> 5. Tamaño Normal</div>
                                <div class="guide-card-body">
                                    <div class="card-content">
                                        ¿Qué porcentaje de batidos/limonadas (sin PedidosYa) se vendió en tamaño Normal?
                                        <strong>Meta: 85 % Normal = 100 %.</strong><br>
                                        <div class="formula-box">Normal ÷ (Normal + Pequeño) × 100</div>
                                        <small class="text-muted">Ej.: 90 Normals de 110 total = 82 % real → KPI 96
                                            %.</small>
                                    </div>
                                    <div class="meta-pill">Meta: 85% Normal</div>
                                </div>
                            </div>
                            <!-- 6. Mostrador -->
                            <div class="guide-card">
                                <div class="guide-card-header"><i class="fas fa-store"></i> 6. Mostrador</div>
                                <div class="guide-card-body">
                                    <div class="card-content">
                                        ¿Qué porcentaje de las ventas totales (en unidades, sin PedidosYa) son productos
                                        de
                                        mostrador (Grupos 5 y 7)?
                                        <strong>Meta: 8 % = 100 %.</strong><br>
                                        <small class="text-muted">Ej.: 40 unidades mostrador de 400 totales = 10 % real
                                            →
                                            como supera 8 %, KPI = 100 %.</small>
                                    </div>
                                    <div class="meta-pill">Meta: 8 %</div>
                                </div>
                            </div>
                            <!-- 7. Reseñas -->
                             <div class="guide-card">
                                 <div class="guide-card-header"><i class="fas fa-star"></i> 7. Reseñas Google</div>
                                 <div class="guide-card-body">
                                     <div class="card-content">
                                         Reseñas de <strong>5 estrellas</strong> recibidas en Google Business.
                                         <strong>Meta mensual: 12.</strong><br>
                                         <small class="text-muted">El KPI se calcula comparando la cantidad real vs. la <strong>meta acumulada proporcional</strong> según el día del mes (hasta ayer).</small>
                                     </div>
                                     <div class="meta-pill">Meta: 12/mes (ajustada proporcionalmente)</div>
                                 </div>
                             </div>
                            <!-- 8. Reclamos -->
                            <div class="guide-card">
                                <div class="guide-card-header"><i class="fas fa-comment-alt"></i> 8. Reclamos</div>
                                <div class="guide-card-body">
                                    <div class="card-content">
                                        Porcentaje de reclamos que recibieron seguimiento. Formato: <strong>%
                                            (atendidos/totales)</strong>.<br>
                                        <small class="text-muted">Ej.: 80 % (4/5) = 4 reclamos atendidos de 5 recibidos.
                                            La
                                            celda es clickeable para ver el detalle.</small>
                                        <div class="formula-box">Escala: 1=100%, 2=80%, 3=60%, 4=40%, 5=20%</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flow-arrow"><i class="fas fa-chevron-down"></i></div>

                        <!-- PASO 3: CÁLCULOS INTERMEDIOS -->
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="guide-card h-100" style="border-left: 5px solid #0E544C;">
                                    <div class="guide-card-header"><i class="fas fa-store-alt"></i> Desempeño de Tienda
                                    </div>
                                    <div class="guide-card-body">
                                        Es el <strong>promedio simple</strong> de las 8 métricas anteriores. Refleja qué
                                        tan bien está funcionando la tienda en todos los aspectos.
                                        <div class="formula-box">(Métrica 1 + ... + Métrica 8) ÷ 8</div>
                                        <small class="text-muted">Ej.: (80+70+90+75+96+100+75+80) ÷ 8 = <strong>83
                                                %</strong></small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="guide-card h-100" style="border-left: 5px solid #0E544C;">
                                    <div class="guide-card-header"><i class="fas fa-percentage"></i> Cumplimiento de
                                        Ventas %</div>
                                    <div class="guide-card-body">
                                        Cuánto vendió la tienda vs. su meta asignada del mes por la gerencia.
                                        <div class="formula-box">Ventas reales ÷ Meta × 100</div>
                                        <small class="text-muted">Rango válido: 60 % (mínimo) a 130 % (máximo) para
                                            evitar distorsiones finales.</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flow-arrow"><i class="fas fa-chevron-down"></i></div>

                        <!-- PASO 4: RESULTADO FINAL -->
                        <div class="guide-card result-card-gold">
                            <div class="guide-card-header">
                                <i class="fas fa-trophy"></i>
                                Propina Interna (3) % — Puntaje Final
                            </div>
                            <div class="guide-card-body">
                                <p>Combina el Desempeño de Tienda con el Cumplimiento de Ventas en una sola nota final
                                    definitiva.</p>
                                <div class="formula-box" style="background: rgba(255,255,255,0.1); color: white;">
                                    Total % = Desempeño de Tienda × Cumplimiento de Ventas ÷ 100
                                </div>
                                <div class="mt-3 p-2"
                                    style="background:rgba(255,255,255,0.05); border-radius: 6px; font-size: 0.88rem;">
                                    <strong>Ejemplo:</strong> 83% (Operación) × 95% (Ventas) ÷ 100 = <strong>78.85%
                                        Final</strong>
                                </div>
                                <p class="mt-3 mb-0 small" style="opacity: 0.85;">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Una tienda que atiende bien a sus clientes Y cumple sus ventas obtendrá el mejor
                                    puntaje. Ninguno de los dos aspectos puede descuidarse.
                                </p>
                            </div>
                        </div>

                    </div><!-- /.guide-flow --><br>
                    <p class="text-muted small mt-4 mb-0 text-center">
                        <i class="fas fa-info-circle me-1"></i>
                        Los datos se filtran por el <strong>mes y año</strong> seleccionados. Para el mes en curso, solo
                        se consideran días hasta el día de ayer. Las metas de Membresías y Reseñas se ajustan proporcionalmente a los días transcurridos.
                    </p>
                </div><!-- /.modal-body -->

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>

            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /#pageHelpModal -->

</body>

</html>
