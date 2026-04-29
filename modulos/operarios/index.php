<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
require_once '../../includes/menu_lateral.php';
require_once '../../includes/header_universal.php';

$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso al módulo (cargos con permiso para ver marcaciones)
if (!verificarAccesoCargo([2,44,45,46,47])) {
    header('Location: ../index.php');
    exit();
}

/**
 * Obtiene información relevante para el operario
 */
function obtenerInfoOperario($codOperario) {
    global $conn;
    
    // Aquí puedes agregar funciones específicas para operarios si es necesario
    // Por ejemplo: horas trabajadas, próximos turnos, etc.
    
    return [
        'mensaje_bienvenida' => 'Bienvenido a tu área de trabajo',
        'ultimo_acceso' => date('d/m/Y H:i'),
        // Agregar más datos según sea necesario
    ];
}

// Obtener información del operario
$infoOperario = obtenerInfoOperario($_SESSION['usuario_id']);

// Verificar si el usuario está de cumpleaños
$cumpleanosInfo = verificarCumpleanosUsuario($_SESSION['usuario_id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operarios - Batidos Pitaya</title>
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
            text-align: center;
        }
        
        /* Estilos para el indicador de bienvenida/información */
        .info-container {
            max-width: 1200px;
            margin: 0 auto 30px auto;
        }
        
        .info-card {
            background: linear-gradient(135deg, #51B8AC 0%, #0E544C 100%);
            border-radius: 12px;
            padding: 25px;
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
        }
        
        .info-card:hover {
            transform: translateY(-2px);
        }
        
        .info-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            font-size: 1.4rem !important;
        }
        
        .info-title i {
            font-size: 1.6rem;
        }
        
        .info-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .info-icon {
            font-size: 4rem !important;
            opacity: 0.8;
            min-width: 100px;
            text-align: center;
        }
        
        .info-text {
            flex: 1;
            text-align: left;
            padding-left: 20px;
        }
        
        .info-message {
            margin-bottom: 10px;
            font-size: 1.1rem;
        }
        
        .info-details {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .btn-action {
            background: rgba(255,255,255,0.2);
            border: 2px solid white;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-action:hover {
            background: white;
            color: #333;
            text-decoration: none;
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
            
            .info-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .info-text {
                text-align: center;
                padding-left: 0;
            }
            
            .info-icon {
                font-size: 3rem !important;
            }
        }
        
        /* Estilos para el modal de información */
        .modal-info {
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
        
        .modal-content-info {
            background-color: white;
            margin: 5% auto;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header-info {
            background: linear-gradient(135deg, #51B8AC 0%, #0E544C 100%);
            color: white;
            padding: 20px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header-info h3 {
            margin: 0;
            font-size: 1.4rem !important;
        }
        
        .close-modal {
            font-size: 2rem;
            cursor: pointer;
            transition: color 0.3s ease;
            line-height: 1;
        }
        
        .close-modal:hover {
            color: #ffeb3b;
        }
        
        .modal-body-info {
            padding: 20px;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        /* Responsive para el modal */
        @media (max-width: 768px) {
            .modal-content-info {
                margin: 10% auto;
                width: 95%;
            }
            
            .modal-body-info {
                padding: 15px;
            }
        }
        
        /* Estilos para la tarjeta de cumpleaños */
.cumpleanos-container {
    max-width: 1200px;
    margin: 0 auto 30px auto;
}

.cumpleanos-card {
    background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 50%, #FF6B6B 100%);
    border-radius: 20px;
    padding: 30px;
    color: white;
    box-shadow: 0 10px 30px rgba(255, 107, 107, 0.3);
    position: relative;
    overflow: hidden;
    border: 3px solid rgba(255, 255, 255, 0.2);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.02); }
    100% { transform: scale(1); }
}

.cumpleanos-card::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
    transform: rotate(45deg);
    animation: shine 3s infinite;
}

@keyframes shine {
    0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
    100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
}

.cumpleanos-content {
    display: flex;
    align-items: center;
    gap: 25px;
    position: relative;
    z-index: 2;
}

.cumpleanos-icon {
    font-size: 4rem !important;
    animation: bounce 2s infinite;
    min-width: 80px;
    text-align: center;
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.cumpleanos-text {
    flex: 1;
}

.cumpleanos-title {
    font-size: 2rem !important;
    margin-bottom: 15px;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
}

.cumpleanos-message {
    font-size: 1.1rem !important;
    margin-bottom: 15px;
    line-height: 1.6;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
}

.cumpleanos-details {
    font-size: 1rem !important;
    opacity: 0.9;
    line-height: 1.5;
}

.cumpleanos-confetti {
    position: absolute;
    top: 20px;
    right: 30px;
    font-size: 3rem !important;
    animation: spin 4s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Efectos de confeti adicionales */
.cumpleanos-card::after {
    content: '🎉🎊🥳';
    position: absolute;
    bottom: 10px;
    right: 20px;
    font-size: 1.5rem;
    opacity: 0.7;
}

/* Responsive */
@media (max-width: 768px) {
    .cumpleanos-content {
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }
    
    .cumpleanos-title {
        font-size: 1.5rem !important;
    }
    
    .cumpleanos-message {
        font-size: 1rem !important;
    }
    
    .cumpleanos-icon {
        font-size: 3rem !important;
    }
    
    .cumpleanos-confetti {
        position: relative;
        top: auto;
        right: auto;
        margin-top: 10px;
    }
}
    </style>
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    
    <div class="main-container">
        <div class="contenedor-principal">
            <div class="module-header" style="display:none;">
                <h1 class="module-title-page">Área de Colaboradores</h1>
            </div>
            
            <?php echo renderHeader($usuario, $esAdmin, ''); ?>
            
            <!-- Tarjeta de Información/Bienvenida -->
            <div class="info-container" style="display:none;">
                <div class="info-card" onclick="mostrarModalInfo()" style="cursor: pointer;">
                    <h2 class="info-title">
                        <i class="fas fa-user-check"></i>
                        Información del Colaborador(a)
                    </h2>
                    <div class="info-content">
                        <div class="info-icon">
                            <i class="fas fa-id-card"></i>
                        </div>
                        <div class="info-text">
                            <div class="info-message">
                                ¡Bienvenido <?= htmlspecialchars($esAdmin ? $usuario['nombre'] : $usuario['Nombre']) ?>!
                            </div>
                            <div class="info-details">
                                <strong>Cargo:</strong> <?= htmlspecialchars($usuario['cargo_nombre'] ?? 'Operario') ?><br>
                                <strong>Último acceso:</strong> <?= date('d/m/Y H:i') ?><br>
                                <strong>Estado:</strong> <span style="color: #90EE90;">Activo</span>
                            </div>
                        </div>
                        <button class="btn-action" onclick="event.stopPropagation();">
                            <i class="fas fa-info-circle"></i> Ver Detalles
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Tarjeta de Feliz Cumpleaños -->
            <?php if ($cumpleanosInfo): ?>
            <div class="cumpleanos-container" style="max-width: 1200px; margin: 0 auto 30px auto;">
                <div class="cumpleanos-card">
                    <div class="cumpleanos-content">
                        <div class="cumpleanos-icon">
                            <i class="fas fa-birthday-cake"></i>
                        </div>
                        <div class="cumpleanos-text">
                            <h2 class="cumpleanos-title">
                                ¡Feliz Cumpleaños <?= htmlspecialchars($cumpleanosInfo['nombre']) ?>! 🎉🎂
                            </h2>
                            <p class="cumpleanos-message">
                                Hoy celebramos tu día especial 🥳 y queremos agradecerte por ser parte de nuestra familia 
                                en Batidos Pitaya 🍓. <?= $cumpleanosInfo['edad'] ? "¡Felicidades por tus {$cumpleanosInfo['edad']} años! " : "" ?>
                                Que este nuevo ciclo de vida esté lleno de éxitos, alegrías y momentos inolvidables.
                            </p>
                            <p class="cumpleanos-details">
                                Te invitamos a pasar por tu batido de cortesía en cualquier sucursal si tienes membresía de Club Pitaya 🥤<br>
                                <strong>Con nuestros mejores deseos,<br>El equipo de Batidos Pitaya 💜✨</strong>
                            </p>
                        </div>
                        <div class="cumpleanos-confetti">🎊</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Módulos de Acceso Rápido -->
            <div class="modules">
                <?php if ($esAdmin || verificarAccesoCargo([5])): ?>
                    <a href="../lideres/index.php" class="module-card">
                        <div class="module-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h3 class="module-title">Módulo de Líderes</h3>
                    </a>
                <?php endif; ?>
                
                <?php if ($esAdmin || verificarAccesoCargo([22])): ?>
                    <a href="../atencioncliente/index.php" class="module-card">
                        <div class="module-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h3 class="module-title">Módulo de Atención al Cliente</h3>
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Grupo: Comunicación Interna -->
            <h2 class="category-title">Comunicación Interna</h2>
            <div class="modules">
                <a href="../supervision/auditorias_original/index_avisos_publico.php" class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h3 class="module-title">Avisos</h3>
                </a>
            </div>
            
            <!-- Grupo: Planilla -->
            <h2 class="category-title">Planilla</h2>
            <div class="modules">
                <a href="../supervision/auditorias_original/auditinternas/deducciones_total.php" class="module-card" style="display:none;">
                    <div class="module-icon">
                        <i class="fas fa-money-check-alt"></i>
                    </div>
                    <h3 class="module-title">Historial de Deducciones</h3>
                </a>
                
                <a href="../operarios/historial_marcacion_individual.php" class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-money-check-alt"></i>
                    </div>
                    <h3 class="module-title">Marcaciones / Deducciones</h3>
                </a>
                
                <a href="../contabilidad/boleta_pago.php" class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-file-invoice-dollar"></i> <!-- Icono sugerido para boleta de pago -->
                    </div>
                    <h3 class="module-title">Boleta de Pago</h3>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Modal de Información del Operario -->
    <div id="modalInfo" class="modal-info">
        <div class="modal-content-info">
            <div class="modal-header-info">
                <h3><i class="fas fa-id-card"></i> Información Detallada del Operario</h3>
                <span class="close-modal" onclick="cerrarModalInfo()">&times;</span>
            </div>
            <div class="modal-body-info">
                <div style="text-align: center; margin-bottom: 20px;">
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #51B8AC 0%, #0E544C 100%); display: inline-flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: bold; margin-bottom: 15px;">
                        <?= $esAdmin ? 
                            strtoupper(substr($usuario['nombre'], 0, 1)) : 
                            strtoupper(substr($usuario['Nombre'], 0, 1)) ?>
                    </div>
                    <h4 style="color: #0E544C; margin-bottom: 5px;">
                        <?= $esAdmin ? 
                            htmlspecialchars($usuario['nombre']) : 
                            htmlspecialchars($usuario['Nombre'].' '.$usuario['Apellido']) ?>
                    </h4>
                    <p style="color: #666; margin-bottom: 20px;">
                        <?= $esAdmin ? 
                            'Administrador' : 
                            htmlspecialchars($usuario['cargo_nombre'] ?? 'Operario') ?>
                    </p>
                </div>
                
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <h5 style="color: #0E544C; margin-bottom: 15px; border-bottom: 1px solid #ddd; padding-bottom: 8px;">
                        <i class="fas fa-info-circle"></i> Información General
                    </h5>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <strong>Usuario ID:</strong><br>
                            <span style="color: #666;"><?= $_SESSION['usuario_id'] ?></span>
                        </div>
                        <div>
                            <strong>Último acceso:</strong><br>
                            <span style="color: #666;"><?= date('d/m/Y H:i') ?></span>
                        </div>
                        <div>
                            <strong>Estado:</strong><br>
                            <span style="color: #28a745; font-weight: bold;">Activo</span>
                        </div>
                        <div>
                            <strong>Módulo:</strong><br>
                            <span style="color: #666;">Área de Operarios</span>
                        </div>
                    </div>
                </div>
                
                <div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">
                    <h5 style="color: #856404; margin-bottom: 10px;">
                        <i class="fas fa-lightbulb"></i> Recordatorio Importante
                    </h5>
                    <p style="color: #856404; margin: 0; font-size: 0.9rem;">
                        Recuerda que cualquier incidencia con tu horario o asistencia debe ser reportada 
                        inmediatamente a tu líder de sucursal o al departamento de Recursos Humanos.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Funciones para el modal de información
        function mostrarModalInfo() {
            document.getElementById('modalInfo').style.display = 'block';
        }
        
        function cerrarModalInfo() {
            document.getElementById('modalInfo').style.display = 'none';
        }
        
        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modalInfo = document.getElementById('modalInfo');
            if (event.target === modalInfo) {
                cerrarModalInfo();
            }
        }
        
        // Cerrar modal con tecla ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                cerrarModalInfo();
            }
        });
        
        // Mostrar mensaje de bienvenida
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Área de Operarios cargada correctamente');
        });
    </script>
</body>
</html>