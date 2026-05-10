<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';

// Verificar acceso al módulo RH (Código 13 para Jefe de RH)
//verificarAccesoModulo('contabilidad');
verificarAccesoCargo([8, 16]);

$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso al módulo (cargos con permiso para ver marcaciones)
if (!$esAdmin && !verificarAccesoCargo([8, 16])) {
    header('Location: ../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contabilidad - Batidos Pitaya</title>
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
            margin: 0;
            padding: 0;
        }
        
        .modules {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(135px, 135px));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .module-card {
            background: white;
            border-radius: 8px;
            padding: 7px;
            width: auto;
            max-width: 135px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center !important;
            justify-content: center !important;
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
            text-align: center;
        }
        
        /* Estilos para las tarjetas de pendientes */
        .pendientes-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
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
        
        .pendiente-count {
            font-size: 1.8rem;
            font-weight: bold;
            margin: 5px 0;
        }
        
        .pendiente-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .pendiente-alert {
            font-size: 0.8rem;
            margin-top: 5px;
        }
        
        /* Colores específicos para cada tipo */
        .viaticos-pendientes {
            border-left: 5px solid #17a2b8;
        }
        
        .viaticos-pendientes .pendiente-count {
            color: #17a2b8;
        }
        
        .viaticos-pendientes .pendiente-alert {
            color: #17a2b8;
        }
        
        .deducciones-pendientes {
            border-left: 5px solid #6f42c1;
        }
        
        .deducciones-pendientes .pendiente-count {
            color: #6f42c1;
        }
        
        .deducciones-pendientes .pendiente-alert {
            color: #6f42c1;
        }
        
        @media (max-width: 768px) {
            .modules {
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
            }
            
            .module-card {
                padding: 10px 5px;
                max-width: 100%;
                height: 100%;
            }
            
            .module-icon {
                font-size: 1.8rem !important;
                margin-bottom: 5px;
            }
            
            .module-title {
                font-size: 0.9rem !important;
                margin-bottom: 5px;
            }
            
            header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .pendientes-container {
                flex-direction: column;
                align-items: center;
            }
            
            .pendiente-card {
                width: 100%;
                max-width: 300px;
            }
        }
    </style>
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    
    <div class="main-container">
        <div class="contenedor-principal">
            <?php echo renderHeader($usuario, $esAdmin, ''); ?>
            
            <div style="display:none;" class="module-header">
                <h1 class="module-title-page">Área de Contabilidad</h1>
            </div>
            
            <!-- Grupo 1 -->
            <h2 class="category-title">Recursos Humanos</h2>
            <div class="modules">
                <a href="../supervision/ver_horarios_compactos.php" class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3 class="module-title">Control de Asistencia</h3>
                </a>
                
                <a href="../operaciones/viaticos.php" class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <h3 class="module-title">Gestión de RRHH</h3>
                </a>
            </div>
            
            <!-- Grupo 2 -->
            <h2 class="category-title">Supervisión</h2>
            <div class="modules">
                <a href="../supervision/auditorias_original/auditinternas/deducciones_total.php" class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-search-dollar"></i>
                    </div>
                    <h3 class="module-title">Auditorías de Efectivo</h3>
                </a>
            </div>
            
            <!-- Grupo 3 -->
            <h2 class="category-title">Comunicación Interna</h2>
            <div class="modules">
                <a href="../supervision/auditorias_original/index_avisos_publico.php" class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h3 class="module-title">Vista Pública</h3>
                </a>
            </div>
        </div>
    </div>
</body>
</html>