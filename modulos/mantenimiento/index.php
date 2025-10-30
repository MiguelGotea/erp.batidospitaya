<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

//verificarAccesoModulo('sistema'); Esto ya no se usa

$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

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
        }
    </style>
</head>
<body>
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
        </div>
        
          <!-- Contenedor para indicadores -->
        <div class="indicadores-container">
            <div class="pendientes-container" style="margin-bottom: 30px;">
                <div class="pendientes-card faltas-indicador <?= $faltasPendientesOperaciones['color'] ?>" onclick="mostrarModalFaltasOperaciones()" style="cursor: pointer;">
                    <div class="pendientes-content">
                        <div class="pendientes-count"><?= $faltasPendientesOperaciones['total'] ?></div>
                        <div class="pendientes-info">
                            <div class="pendientes-fecha" id="faltasFechaOperaciones">
                                <?php 
                                $diasRestantes = $faltasPendientesOperaciones['dias_restantes'];
                                if ($faltasPendientesOperaciones['total'] == 0) {
                                    echo 'Al día';
                                } elseif ($diasRestantes < 0) {
                                    echo 'Vencido hace ' . abs($diasRestantes) . ' días';
                                } elseif ($diasRestantes === 0) {
                                    echo 'Vence hoy';
                                } else {
                                    echo $diasRestantes . ' días restantes';
                                }
                                ?>
                            </div>
                            <div class="pendientes-titulo">
                                Faltas Tiendas
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