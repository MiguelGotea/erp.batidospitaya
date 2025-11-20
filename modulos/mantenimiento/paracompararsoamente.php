<?php
/**
 * Header Universal para Módulos ERP
 * Incluir este archivo en cada página: require_once '../../includes/header_universal.php';
 * Uso: echo renderHeader($usuario, $esAdmin, 'Título de la Página');
 */

/**
 * Función para renderizar el header universal
 * @param array $usuario - Array con datos del usuario
 * @param bool $esAdmin - Si el usuario es administrador
 * @param string $titulo - Título de la página (opcional)
 * @return string HTML del header
 */
function renderHeader($usuario, $esAdmin = false, $titulo = '') {
    ob_start();
    ?>
    
    <!-- CSS COMPLETO del Header -->
    <style>
        /* ==================== HEADER BASE ==================== */
        .main-header {
            position: relative;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            gap: 20px;
        }
        
        .header-logo {
            height: 45px;
            width: auto;
        }
        
        /* ==================== TÍTULO CENTRAL ==================== */
        .header-title {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            color: #0E544C;
            font-size: 1.2rem !important;
            font-weight: 600;
            margin: 0;
            text-align: center;
            pointer-events: none;
        }
        
        /* ==================== TÍTULO DE BIENVENIDA (ALINEADO A LA IZQUIERDA) ==================== */
        .welcome-title {
            position: static !important;
            transform: none !important;
            left: auto !important;
            text-align: left;
            color: #0E544C;
            margin-right: auto;
        }
        
        /* ==================== USER INFO ==================== */
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 1;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            min-width: 45px;
            border-radius: 50%;
            background-color: #51B8AC;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem !important;
            box-shadow: 0 2px 8px rgba(81, 184, 172, 0.3);
            text-transform: uppercase;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
            gap: 2px;
            text-align: left;
        }
        
        .user-name {
            font-weight: 600;
            color: #0E544C;
            font-size: 0.95rem !important;
            white-space: nowrap;
        }
        
        .user-role {
            color: #0E544C;
            font-size: 0.85rem !important;
            white-space: nowrap;
        }
        
        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 768px) {
            .main-header {
                justify-content: flex-end;
                padding: 15px;
            }
            
            .header-title {
                font-size: 1rem !important;
                max-width: calc(100% - 200px);
            }
            
            .welcome-title {
                margin-right: auto;
                max-width: none;
            }
            
            .user-info {
                gap: 10px;
            }
            
            .user-avatar {
                width: 40px;
                height: 40px;
                min-width: 40px;
                font-size: 1.1rem !important;
            }
            
            .user-details {
                display: flex;
                flex-direction: column;
            }
        }
        
        @media (max-width: 480px) {
            .header-title {
                font-size: 0.9rem !important;
                max-width: calc(100% - 180px);
            }
            
            .welcome-title {
                font-size: 0.9rem !important;
                max-width: none;
            }
            
            .user-name {
                font-size: 0.9rem !important;
            }
            
            .user-role {
                font-size: 0.8rem !important;
            }
            
            .user-avatar {
                width: 38px;
                height: 38px;
                min-width: 38px;
                font-size: 1rem !important;
            }
        }
        
        /* ==================== ANIMACIONES ==================== */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .main-header {
            animation: fadeIn 0.3s ease-out;
        }
    </style>
    
    <!-- Header HTML -->
    <header class="main-header">
        <?php if (!empty($titulo)): ?>
            <h1 class="header-title"><?php echo htmlspecialchars($titulo); ?></h1>
        <?php else: ?>
            <h1 class="header-title welcome-title">
                ¡Bienvenido, <?= $esAdmin ? 
                    htmlspecialchars($usuario['nombre']) : 
                    htmlspecialchars($usuario['Nombre']) ?>!
            </h1>
        <?php endif; ?>
        
        <div class="user-info">
            <div class="user-avatar" title="<?php echo $esAdmin ? htmlspecialchars($usuario['Nombre']) : htmlspecialchars($usuario['Nombre'].' '.$usuario['Apellido']); ?>">
                <?= $esAdmin ? 
                    strtoupper(substr($usuario['nombre'], 0, 1)) : 
                    strtoupper(substr($usuario['Nombre'], 0, 1)) ?>
            </div>
            <div class="user-details">
                <div class="user-name">
                   <?= $esAdmin ? 
                    htmlspecialchars($usuario['nombre']) : 
                    htmlspecialchars($usuario['Nombre'].' '.$usuario['Apellido']) ?>
                </div>
                <small class="user-role">
                    <?= $esAdmin ? 
                        'Administrador' : 
                        htmlspecialchars($usuario['cargo_nombre'] ?? 'Sin cargo definido') ?>
                </small>
            </div>
        </div>
    </header>
    
    <?php
    return ob_get_clean();
}
?>