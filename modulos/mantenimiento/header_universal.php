<?php
/**
 * Header Universal para Módulos ERP
 * Incluir este archivo en cada página: require_once '../../includes/header_universal.php';
 * Uso: echo renderHeader($usuario, $esAdmin);
 */

/**
 * Función para renderizar el header universal
 * @param array $usuario - Array con datos del usuario
 * @param bool $esAdmin - Si el usuario es administrador
 * @return string HTML del header
 */
function renderHeader($usuario, $esAdmin = false) {
    ob_start();
    ?>
    
    <!-- CSS COMPLETO del Header -->
    <style>
        /* ==================== HEADER BASE ==================== */
        .main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .header-logo {
            height: 45px;
            width: auto;
        }
        
        /* ==================== USER INFO ==================== */
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            min-width: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #51B8AC 0%, #0E544C 100%);
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
        }
        
        .user-name {
            font-weight: 600;
            color: #333;
            font-size: 0.95rem !important;
        }
        
        .user-role {
            color: #666;
            font-size: 0.85rem !important;
        }
        
        /* ==================== BOTÓN LOGOUT ==================== */
        .btn-logout {
            background: linear-gradient(135deg, #51B8AC 0%, #0E544C 100%);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem !important;
        }
        
        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(81, 184, 172, 0.4);
            background: linear-gradient(135deg, #0E544C 0%, #51B8AC 100%);
        }
        
        .btn-logout:active {
            transform: translateY(0);
        }
        
        .btn-logout i {
            font-size: 1rem !important;
        }
        
        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 768px) {
            .main-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
                padding: 15px;
            }
            
            .header-logo {
                height: 40px;
            }
            
            .user-info {
                width: 100%;
                justify-content: center;
            }
            
            .user-avatar {
                width: 40px;
                height: 40px;
                min-width: 40px;
                font-size: 1.1rem !important;
            }
            
            .user-details {
                flex: 1;
                text-align: left;
            }
            
            .btn-logout {
                padding: 8px 15px;
            }
        }
        
        @media (max-width: 480px) {
            .user-name {
                font-size: 0.9rem !important;
            }
            
            .user-role {
                font-size: 0.8rem !important;
            }
        }
        
        /* ==================== ACCESIBILIDAD ==================== */
        .btn-logout:focus {
            outline: 2px solid #51B8AC;
            outline-offset: 2px;
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
        <img src="../../assets/img/Logo.svg" alt="Batidos Pitaya" class="header-logo">
        <div class="user-info">
            <div class="user-avatar" title="<?php echo $esAdmin ? htmlspecialchars($usuario['nombre']) : htmlspecialchars($usuario['Nombre'].' '.$usuario['Apellido']); ?>">
                <?php 
                if ($esAdmin) {
                    echo strtoupper(substr($usuario['nombre'], 0, 1));
                } else {
                    echo strtoupper(substr($usuario['Nombre'], 0, 1));
                }
                ?>
            </div>
            <div class="user-details">
                <div class="user-name">
                    <?php 
                    if ($esAdmin) {
                        echo htmlspecialchars($usuario['nombre']);
                    } else {
                        echo htmlspecialchars($usuario['Nombre'].' '.$usuario['Apellido']);
                    }
                    ?>
                </div>
                <small class="user-role">
                    <?php 
                    if ($esAdmin) {
                        echo 'Administrador';
                    } else {
                        echo htmlspecialchars($usuario['cargo_nombre'] ?? 'Sin cargo definido');
                    }
                    ?>
                </small>
            </div>
            <a href="../../logout.php" class="btn-logout" title="Cerrar sesión" aria-label="Cerrar sesión">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </header>
    
    <?php
    return ob_get_clean();
}

/**
 * Función alternativa para obtener solo el nombre del usuario
 * @param array $usuario - Array con datos del usuario
 * @param bool $esAdmin - Si el usuario es administrador
 * @return string Nombre completo del usuario
 */
function obtenerNombreUsuario($usuario, $esAdmin = false) {
    if ($esAdmin) {
        return htmlspecialchars($usuario['nombre']);
    } else {
        return htmlspecialchars($usuario['Nombre'].' '.$usuario['Apellido']);
    }
}

/**
 * Función alternativa para obtener solo la inicial del usuario
 * @param array $usuario - Array con datos del usuario
 * @param bool $esAdmin - Si el usuario es administrador
 * @return string Primera letra del nombre en mayúscula
 */
function obtenerInicialUsuario($usuario, $esAdmin = false) {
    if ($esAdmin) {
        return strtoupper(substr($usuario['nombre'], 0, 1));
    } else {
        return strtoupper(substr($usuario['Nombre'], 0, 1));
    }
}

/**
 * Función alternativa para obtener solo el rol del usuario
 * @param array $usuario - Array con datos del usuario
 * @param bool $esAdmin - Si el usuario es administrador
 * @return string Rol o cargo del usuario
 */
function obtenerRolUsuario($usuario, $esAdmin = false) {
    if ($esAdmin) {
        return 'Administrador';
    } else {
        return htmlspecialchars($usuario['cargo_nombre'] ?? 'Sin cargo definido');
    }
}
?>