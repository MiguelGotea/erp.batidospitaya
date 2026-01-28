<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('ia_graficos_ventas', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualización de Ventas con IA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/ia_graficos_ventas.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Visualización de Ventas con IA'); ?>
            
            <div class="container-fluid p-3">
                <!-- Sección de Favoritos (Colapsable) -->
                <div class="card mb-4 favoritos-section">
                    <div class="card-header favoritos-header" onclick="toggleFavoritos()">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>
                                <i class="bi bi-star-fill text-warning"></i> 
                                <strong>Mis Consultas Favoritas</strong>
                                <span class="badge bg-secondary ms-2" id="favoritosBadge">0</span>
                            </span>
                            <i class="bi bi-chevron-down" id="favoritosChevron"></i>
                        </div>
                    </div>
                    <div class="card-body favoritos-body d-none" id="favoritosBody">
                        <div id="listaFavoritos">
                            <!-- Se llenará dinámicamente -->
                        </div>
                        <div class="text-center text-muted py-4 d-none" id="noFavoritos">
                            <i class="bi bi-star" style="font-size: 3rem;"></i>
                            <p class="mt-2">No tienes consultas favoritas guardadas</p>
                            <small>Genera un gráfico y guárdalo como favorito</small>
                        </div>
                    </div>
                </div>

                <!-- Sección de ayuda -->
                <div class="card mb-4 help-section">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-lightbulb text-warning"></i> ¿Cómo usar esta herramienta?
                        </h5>
                        <p class="mb-2">Escribe tu consulta en lenguaje natural. Por ejemplo:</p>
                        <div class="examples-grid">
                            <span class="example-badge" onclick="usarEjemplo(this)">
                                "gráfico lineal de ventas diarias de las últimas dos semanas"
                            </span>
                            <span class="example-badge" onclick="usarEjemplo(this)">
                                "ventas por sucursal del último mes en gráfico de barras"
                            </span>
                            <span class="example-badge" onclick="usarEjemplo(this)">
                                "cantidad de pedidos por hora hoy"
                            </span>
                            <span class="example-badge" onclick="usarEjemplo(this)">
                                "productos más vendidos esta semana"
                            </span>
                            <span class="example-badge" onclick="usarEjemplo(this)">
                                "comparar ventas con membresía vs sin membresía este mes"
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Panel de consulta -->
                <div class="card query-panel mb-4">
                    <div class="card-body">
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-chat-dots text-primary"></i>
                            </span>
                            <textarea 
                                class="form-control" 
                                id="promptInput" 
                                rows="3" 
                                placeholder="Escribe tu consulta aquí... Ejemplo: 'gráfico lineal de ventas diarias del último mes'"
                            ></textarea>
                            <button class="btn btn-primary btn-generate" onclick="generarGrafico()" id="btnGenerar">
                                <i class="bi bi-graph-up"></i> Generar Gráfico
                            </button>
                        </div>
                        <div class="mt-2 text-muted small">
                            <i class="bi bi-info-circle"></i> 
                            Puedes mencionar: tipo de gráfico, métrica (ventas, cantidad, promedio), período de tiempo, y filtros (sucursal, membresía, producto, etc.)
                        </div>
                    </div>
                </div>

                <!-- Loader -->
                <div class="text-center my-4 d-none" id="loader">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Procesando...</span>
                    </div>
                    <p class="mt-2 text-muted">Analizando tu consulta con IA...</p>
                </div>

                <!-- Panel de interpretación -->
                <div class="card interpretation-panel mb-4 d-none" id="interpretationPanel">
                    <div class="card-header bg-info text-white">
                        <i class="bi bi-robot"></i> Interpretación de la IA
                    </div>
                    <div class="card-body" id="interpretationContent">
                        <!-- Se llenará dinámicamente -->
                    </div>
                </div>

                <!-- Panel de resultado -->
                <div class="card result-panel d-none" id="resultPanel">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-graph-up-arrow"></i> Resultado</span>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-light" onclick="guardarFavorito()" title="Guardar como favorito">
                                <i class="bi bi-star"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-light" onclick="descargarExcel()" title="Descargar Excel">
                                <i class="bi bi-file-earmark-excel"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-light" onclick="descargarGrafico()" title="Descargar imagen">
                                <i class="bi bi-download"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-light" onclick="limpiarResultado()" title="Limpiar">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartCanvas"></canvas>
                        </div>
                        
                        <!-- Explicación del gráfico -->
                        <div class="explanation-box mt-3" id="explanationBox">
                            <!-- Se llenará dinámicamente -->
                        </div>

                        <!-- Estadísticas adicionales -->
                        <div class="stats-grid mt-3" id="statsGrid">
                            <!-- Se llenará dinámicamente -->
                        </div>
                    </div>
                </div>

                <!-- Panel de errores -->
                <div class="alert alert-danger d-none" id="errorPanel">
                    <h5><i class="bi bi-exclamation-triangle"></i> Error</h5>
                    <p id="errorMessage"></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para guardar favorito -->
    <div class="modal fade" id="modalGuardarFavorito" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-star text-warning"></i> Guardar como Favorito
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre del Favorito *</label>
                        <input type="text" class="form-control" id="nombreFavorito" 
                               placeholder="Ej: Ventas mensuales por sucursal" maxlength="200">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción (opcional)</label>
                        <textarea class="form-control" id="descripcionFavorito" rows="2" 
                                  placeholder="Agrega notas adicionales sobre esta consulta"></textarea>
                    </div>
                    <div class="alert alert-info small mb-0">
                        <i class="bi bi-info-circle"></i> 
                        Este favorito se guardará con la consulta actual y podrás usarlo nuevamente cuando lo necesites.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="confirmarGuardarFavorito()">
                        <i class="bi bi-star-fill"></i> Guardar Favorito
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="js/ia_graficos_ventas.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>
</html>