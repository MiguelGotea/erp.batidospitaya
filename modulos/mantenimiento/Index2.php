<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

verificarAutenticacion();

$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

verificarAccesoCargo([11, 16]);

// Obtener cargo del operario para el menú
$cargoUsuariocodigo = obtenerCargoCodigoPrincipalUsuario($_SESSION['usuario_id']);
// Obtener todas las sucursales
$sucursales = obtenerTodasSucursales();

// [MANTENER TODAS LAS FUNCIONES EXISTENTES DE CÁLCULO DE INDICADORES]
// ... (todas las funciones que ya tienes)

// Calcular indicadores
$tardanzasPendientesOperaciones = obtenerTardanzasPendientesOperaciones();
$faltasPendientesOperaciones = obtenerFaltasPendientesOperaciones();
$cantidadAnunciosNoLeidos = obtenerCantidadAnunciosNoLeidos($_SESSION['usuario_id']);

// Incluir el menú lateral
require_once 'includes/menu_lateral.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Operaciones - Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Calibri', sans-serif;
            font-size: clamp(12px, 2vw, 18px) !important;
        }
        
        body {
            background-color: #F6F6F6;
            color: #333;
        }
        
        .main-container {
            margin-left: 260px;
            transition: margin-left 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .main-container {
                margin-left: 0;
            }
        }
        
        .content-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 30px;
            background: white;
            border-radius: 8px;
            padding: 15px 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .logo {
            height: 45px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #51B8AC 0%, #0E544C 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem !important;
            box-shadow: 0 2px 8px rgba(81, 184, 172, 0.3);
        }
        
        .btn-logout {
            background: linear-gradient(135deg, #51B8AC 0%, #0E544C 100%);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(81, 184, 172, 0.4);
        }
        
        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        /* Sección de título */
        .section-title {
            color: #0E544C;
            font-size: 1.5rem !important;
            margin: 30px 0 20px 0;
            padding-left: 15px;
            border-left: 5px solid #51B8AC;
            font-weight: 600;
        }
        
        /* Cards de indicadores mejoradas */
        .indicator-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .indicator-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #51B8AC 0%, #0E544C 100%);
        }
        
        .indicator-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .indicator-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .indicator-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem !important;
            background: linear-gradient(135deg, #51B8AC20 0%, #0E544C20 100%);
            color: #0E544C;
        }
        
        .indicator-value {
            font-size: 2.5rem !important;
            font-weight: bold;
            color: #0E544C;
            margin: 10px 0;
        }
        
        .indicator-label {
            color: #666;
            font-size: 0.95rem !important;
            font-weight: 500;
        }
        
        .indicator-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }
        
        .indicator-status {
            font-size: 0.85rem !important;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .status-verde {
            background: #d4edda;
            color: #155724;
        }
        
        .status-amarillo {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-rojo {
            background: #f8d7da;
            color: #721c24;
        }
        
        .indicator-action {
            color: #51B8AC;
            font-size: 0.85rem !important;
            font-weight: 600;
        }
        
        /* Accesos Rápidos */
        .quick-access-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .quick-access-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 140px;
        }
        
        .quick-access-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(81, 184, 172, 0.2);
        }
        
        .quick-access-icon {
            font-size: 2rem !important;
            color: #51B8AC;
            margin-bottom: 10px;
        }
        
        .quick-access-title {
            font-size: 0.9rem !important;
            font-weight: 600;
            color: #0E544C;
        }
        
        /* Modales (mantener estilos existentes) */
        .modal-pendientes {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }
        
        .modal-content-pendientes {
            background-color: white;
            margin: 5% auto;
            border-radius: 12px;
            width: 90%;
            max-width: 1000px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .modal-header-pendientes {
            background: linear-gradient(135deg, #51B8AC 0%, #0E544C 100%);
            color: white;
            padding: 20px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .close-modal {
            font-size: 2rem !important;
            cursor: pointer;
            transition: color 0.3s ease;
            line-height: 1;
        }
        
        .close-modal:hover {
            color: #ffeb3b;
        }
        
        .modal-body-pendientes {
            padding: 20px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-access-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Incluir menú lateral -->
    <?php echo renderMenuLateral($cargoUsuariocodigo, 'operaciones', 'index.php'); ?>
    
    <!-- Contenido principal -->
    <div class="main-container">
        <div class="content-wrapper">
            <!-- Header -->
            <header>
                <img src="../../assets/img/Logo.svg" alt="Batidos Pitaya" class="logo">
                <div class="user-info">
                    <div class="user-avatar">
                        <?= $esAdmin ? 
                            strtoupper(substr($usuario['nombre'], 0, 1)) : 
                            strtoupper(substr($usuario['Nombre'], 0, 1)) ?>
                    </div>
                    <div>
                        <div style="font-weight: 600;">
                            <?= $esAdmin ? 
                                htmlspecialchars($usuario['nombre']) : 
                                htmlspecialchars($usuario['Nombre'].' '.$usuario['Apellido']) ?>
                        </div>
                        <small style="color: #666;">
                            <?= $esAdmin ? 
                                'Administrador' : 
                                htmlspecialchars($usuario['cargo_nombre'] ?? 'Sin cargo definido') ?>
                        </small>
                    </div>
                    <a href="../../logout.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </header>
            
            <!-- Sección: Indicadores de Gestión -->
            <h2 class="section-title">
                <i class="fas fa-chart-line"></i> Indicadores de Gestión
            </h2>
            
            <div class="dashboard-grid">
                <!-- Indicador: Anuncios Nuevos -->
                <div class="indicator-card" id="cardAnuncios" onclick="irAAnuncios()">
                    <div class="indicator-header">
                        <div class="indicator-icon">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                    </div>
                    <div class="indicator-value" id="anunciosCount">
                        <?= $cantidadAnunciosNoLeidos ?>
                    </div>
                    <div class="indicator-label">Anuncios Nuevos</div>
                    <div class="indicator-meta">
                        <span class="indicator-status <?= $cantidadAnunciosNoLeidos > 0 ? 'status-rojo' : 'status-verde' ?>">
                            <?= $cantidadAnunciosNoLeidos > 0 ? 'Pendientes' : 'Al día' ?>
                        </span>
                        <span class="indicator-action">
                            <i class="fas fa-arrow-right"></i>
                        </span>
                    </div>
                </div>
                
                <!-- Indicador: Tardanzas Pendientes -->
                <div class="indicator-card" onclick="mostrarModalTardanzasOperaciones()">
                    <div class="indicator-header">
                        <div class="indicator-icon">
                            <i class="fas fa-user-clock"></i>
                        </div>
                    </div>
                    <div class="indicator-value">
                        <?= $tardanzasPendientesOperaciones['total'] ?>
                    </div>
                    <div class="indicator-label">Tardanzas Tiendas</div>
                    <div class="indicator-meta">
                        <span class="indicator-status status-<?= $tardanzasPendientesOperaciones['color'] ?>">
                            <?php 
                            $dias = $tardanzasPendientesOperaciones['dias_restantes'];
                            if ($tardanzasPendientesOperaciones['total'] == 0) {
                                echo 'Al día';
                            } elseif ($dias <= 0) {
                                echo 'Vencido';
                            } else {
                                echo $dias . ' días';
                            }
                            ?>
                        </span>
                        <span class="indicator-action">
                            <i class="fas fa-arrow-right"></i>
                        </span>
                    </div>
                </div>
                
                <!-- Indicador: Faltas Pendientes -->
                <div class="indicator-card" onclick="mostrarModalFaltasOperaciones()">
                    <div class="indicator-header">
                        <div class="indicator-icon">
                            <i class="fas fa-calendar-times"></i>
                        </div>
                    </div>
                    <div class="indicator-value">
                        <?= $faltasPendientesOperaciones['total'] ?>
                    </div>
                    <div class="indicator-label">Faltas Tiendas</div>
                    <div class="indicator-meta">
                        <span class="indicator-status status-<?= $faltasPendientesOperaciones['color'] ?>">
                            <?php 
                            $dias = $faltasPendientesOperaciones['dias_restantes'];
                            if ($faltasPendientesOperaciones['total'] == 0) {
                                echo 'Al día';
                            } elseif ($dias <= 0) {
                                echo 'Vencido';
                            } else {
                                echo $dias . ' días';
                            }
                            ?>
                        </span>
                        <span class="indicator-action">
                            <i class="fas fa-arrow-right"></i>
                        </span>
                    </div>
                </div>
                
                <!-- Indicador: KPI (placeholder por ahora) -->
                <div class="indicator-card" style="opacity: 0.6; cursor: default;">
                    <div class="indicator-header">
                        <div class="indicator-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                    </div>
                    <div class="indicator-value">0%</div>
                    <div class="indicator-label">KPI del Mes</div>
                    <div class="indicator-meta">
                        <span class="indicator-status" style="background: #e9ecef; color: #6c757d;">
                            Próximamente
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Sección: Accesos Rápidos -->
            <h2 class="section-title">
                <i class="fas fa-bolt"></i> Accesos Rápidos
            </h2>
            
            <div class="quick-access-grid">
                <a href="tardanzas_manual.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div class="quick-access-title">Gestión de RRHH</div>
                </a>
                
                <a href="../supervision/ver_horarios_compactos.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="quick-access-title">Control de Asistencia</div>
                </a>
                
                <a href="../supervision/auditorias_original/auditinternas/auditorias_consolidadas.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-search-dollar"></i>
                    </div>
                    <div class="quick-access-title">Auditorías</div>
                </a>
                
                <a href="../supervision/auditorias_original/index_avisos.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div class="quick-access-title">Comunicación Interna</div>
                </a>
                
                <a href="../mantenimiento/dashboard_mantenimiento.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="quick-access-title">Mantenimiento</div>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Modales (mantener los existentes) -->
    <!-- Modal Tardanzas Operaciones -->
    <div id="modalTardanzasOperaciones" class="modal-pendientes">
        <!-- [MANTENER CONTENIDO EXISTENTE DEL MODAL] -->
    </div>
    
    <!-- Modal Faltas Operaciones -->
    <div id="modalFaltasOperaciones" class="modal-pendientes">
        <!-- [MANTENER CONTENIDO EXISTENTE DEL MODAL] -->
    </div>
    
    <!-- Scripts -->
    <script>
        // [MANTENER TODOS LOS SCRIPTS EXISTENTES]
        
        function irAAnuncios() {
            // Código existente
        }
        
        function mostrarModalTardanzasOperaciones() {
            document.getElementById('modalTardanzasOperaciones').style.display = 'block';
        }
        
        function cerrarModalTardanzasOperaciones() {
            document.getElementById('modalTardanzasOperaciones').style.display = 'none';
        }
        
        function mostrarModalFaltasOperaciones() {
            document.getElementById('modalFaltasOperaciones').style.display = 'block';
        }
        
        function cerrarModalFaltasOperaciones() {
            document.getElementById('modalFaltasOperaciones').style.display = 'none';
        }
        
        // Cerrar modales con ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                cerrarModalTardanzasOperaciones();
                cerrarModalFaltasOperaciones();
            }
        });
    </script>
</body>
</html>