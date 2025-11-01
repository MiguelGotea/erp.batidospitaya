<?php
require_once 'models/Ticket.php';
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
// Incluir el menú lateral
require_once '../../includes/menu_lateral.php';

verificarAutenticacion();
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';
$cargoUsuariocodigo = $usuario['CodNivelesCargos'];

// Verificar acceso al módulo (cargos con permiso para ver marcaciones)
if (!verificarAccesoCargo(14)) {
    header('Location: ../index.php');
    exit();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantenimiento - Batidos Pitaya</title>
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
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 10px;
        }
        
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
            margin-bottom: 30px;
        }
        
        .logo {
            height: 50px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #51B8AC;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .modules {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(135px, 135px)); /*Espacio entre las cartas del módulo*/
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .module-card {
            background: white;
            border-radius: 8px;
            padding: 7px; /*Espacio de las cartas del módulo*/
            width: auto;
            max-width: 135px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            text-align: center; /*Texto centrado*/
            display: flex;
            flex-direction: column;
            align-items: center !important;     /* Centrado horizontal */
            justify-content: center !important; /* Centrado vertical */
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }
        
        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .module-icon {
            font-size: 2.5rem;
            color: #51B8AC;
            margin-bottom: 12px;
        }
        
        .module-title {
            margin: 0;
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: #0E544C;
        }
        
        .module-desc {
            color: #666;
            font-size: 0.9rem;
        }
        
        .btn-logout {
            background: #51B8AC;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-logout:hover {
            background: #0E544C;
        }
        
        .module-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .module-title-page {
            color: #51B8AC;
            font-size: 1.8rem;
        }
        
        .category-title {
            color: #0E544C;
            font-size: 1.5rem;
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #51B8AC;
            text-align: center; /*Texto de categorías al centro*/
        }
        
        
        @media (max-width: 768px) {
            .modules {
                grid-template-columns: repeat(3, 1fr); /* 3 columnas en móvil */
                gap: 10px; /* Reducir espacio entre tarjetas */
            }
            
            .module-card {
                padding: 10px 5px;  /* Ajustar espaciado interno */
                max-width: 100%;    /* Ocupar todo el ancho disponible */
                height: 100%;       /* Asegurar altura consistente */
            }
            
            .module-icon {
                font-size: 1.8rem !important; /* Reducir tamaño de icono */
                margin-bottom: 5px; /* Menos espacio entre icono y texto */
            }
            
            .module-title {
                font-size: 0.9rem !important; /* Reducir tamaño de texto */
                margin-bottom: 5px;
            }
            
            header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .modal-content-pendientes {
                margin: 10% auto;
                width: 95%;
            }
            
            .item-tardanza {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .btn-justificar {
                margin-left: 0;
                width: 100%;
                text-align: center;
            }
            
            .pendientes-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .pendientes-info {
                text-align: center;
            }
            
            .pendientes-fecha {
                font-size: 0.7rem !important;
            }
            
            .indicadores-container {
                flex-direction: column;
                align-items: center;
            }
            
            .pendientes-container {
                min-width: 100%;
                max-width: 100%;
            }
        }
        
        /* Estilos para el contenedor de indicadores */
        .indicadores-container {
            display: flex;
            flex-direction: row; /* En una sola fila */
            gap: 15px;
            margin-bottom: 30px;
            max-width: 1200px;
            margin: 0 auto 30px auto;
            flex-wrap: wrap;
            justify-content: center;
        }

        .pendientes-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            flex: 1;
        }

        .pendiente-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            transition: transform 0.3s, box-shadow 0.3s;
            min-width: 200px;
            max-width: 250px;
        }
        
        .pendiente-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .pendientes-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 5px;
        }

        .pendientes-count {
            font-size: 2.5rem !important;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            min-width: 80px;
        }

        .pendientes-fecha {
            font-size: 0.8rem !important;
            opacity: 0.9;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .pendientes-titulo {
            font-size: 0.9rem !important;
            font-weight: 600;
            margin-top: 5px;
        }

        .pendientes-info {
            text-align: center;
            margin-top: 5px;
        }

        .pendientes-detalle {
            margin-bottom: 10px;
            font-size: 0.6rem;
            opacity: 0.9;
        }

        .pendientes-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 20px;
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
            cursor: pointer;
        }



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

    <div class="container">
        <header>
            <img src="../../assets/img/Logo.svg" alt="Batidos Pitaya" class="logo">
            <div class="user-info">
                <div class="user-avatar">
                    <?= $esAdmin ? 
                        strtoupper(substr($usuario['nombre'], 0, 1)) : 
                        strtoupper(substr($usuario['Nombre'], 0, 1)) ?>
                </div>
                <div>
                    <div>
                        <?= $esAdmin ? 
                            htmlspecialchars($usuario['nombre']) : 
                            htmlspecialchars($usuario['Nombre'].' '.$usuario['Apellido']) ?>
                    </div>
                    <small>
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
        
        <div class="module-header">
            <h1 class="module-title-page">Área de Mantenimiento</h1>
            <?= htmlspecialchars($cargoUsuariocodigo) ?>
        </div>

        <?php
            $ticket = new Ticket();
            $tickets = $ticket->getAll();
            // Obtener estadísticas
            $stats = [
                'total' => count($tickets),
                'solicitado' => count(array_filter($tickets, fn($t) => $t['status'] === 'solicitado')),
                'agendado' => count(array_filter($tickets, fn($t) => $t['status'] === 'agendado')),
                'finalizado' => count(array_filter($tickets, fn($t) => $t['status'] === 'finalizado'))
            ];
        ?>

          <!-- Contenedor para indicadores -->
        <div class="indicadores-container">
            <div class="pendientes-container" style="margin-bottom: 30px;">
                <div class="pendientes-card" onclick="" style="cursor: pointer;">
                    <div class="pendientes-content">
                        <div class="pendientes-count">
                            <?= $stats['total']-$stats['agendado']-$stats['finalizado'] ?>
                        </div>
                        <div class="pendientes-info">
                            <div class="pendientes-fecha">
                                Solicitudes Pendientes por Agendar
                            </div>
                            <div class="pendientes-titulo">
                                --
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pendientes-container" style="margin-bottom: 30px;">
                <div class="pendientes-card" onclick="" style="cursor: pointer;">
                    <div class="pendientes-content">
                        <div class="pendientes-count">
                            <?= $stats['finalizado'] ?>
                        </div>
                        <div class="pendientes-info">
                            <div class="pendientes-fecha">
                                Solicitudes Concluidas
                            </div>
                            <div class="pendientes-titulo">
                                --
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pendientes-container" style="margin-bottom: 30px;">
                <div class="pendientes-card" onclick="" style="cursor: pointer;">
                    <div class="pendientes-content">
                        <div class="pendientes-count">
                            <?= $stats['total'] ?>
                        </div>
                        <div class="pendientes-info">
                            <div class="pendientes-fecha">
                                Solicitudes Totales
                            </div>
                            <div class="pendientes-titulo">
                                --
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Grupo 1: Vista Pública -->
        <!--
        <h2 class="category-title">Comunicación Interna</h2>
        <div class="modules">
            <a href="../supervision/auditorias_original/index_auditorias_publico.php" class="module-card">
                <div class="module-icon">
                    <i class="fas fa-eye"></i>
                </div>
                <h3 class="module-title">Vista Pública</h3>
            </a>
        </div>
        -->
        
        <!-- Grupo 2: Mantenimiento -->
        <h2 class="category-title">Gestión de Mantenimiento</h2>
        <div class="modules">
            <a href="dashboard_mantenimiento.php" class="module-card">
                <div class="module-icon">
                    <i class="fas fa-tools"></i>
                </div>
                <h3 class="module-title">Solicitudes</h3>
            </a>
            
            <!-- Puedes agregar más módulos de mantenimiento aquí -->
            <!--
            <a href="otro_modulo_mantenimiento.php" class="module-card">
                <div class="module-icon">
                    <i class="fas fa-wrench"></i>
                </div>
                <h3 class="module-title">Otro Módulo</h3>
            </a>
            -->
        </div>
        
        <!-- Grupo 3: Reportes (opcional) -->
        <!--
        <h2 class="category-title">Reportes y Estadísticas</h2>
        <div class="modules">
            <a href="reportes_mantenimiento.php" class="module-card">
                <div class="module-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <h3 class="module-title">Reportes</h3>
            </a>
        </div>
        -->
    </div>
</body>
</html>