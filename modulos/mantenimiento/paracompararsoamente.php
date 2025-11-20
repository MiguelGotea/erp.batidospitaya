<?php
/**
 * Menú Lateral Universal para Módulos ERP - Sistema de Permisos
 * Sidebar colapsable con acordeón vertical
 * Incluir este archivo en cada index: require_once '../../includes/menu_lateral.php';
 * Uso: renderMenuLateral($cargoOperario, 'index.php');
 */

// Configuración global del menú basado en permisos por cargo
$menuGlobal = [
    [
        'nombre' => 'Inicio',
        'icon' => 'fas fa-home',
        'cargos_permitidos' => [11, 5, 14, 16, 21, 35],
        'url' => 'index.php', // Añade esta línea
        'items' => [] // Vacía el array de items
    ],
    [
        'nombre' => 'Recursos Humanos',
        'icon' => 'fas fa-users',
        'cargos_permitidos' => [11, 5, 16, 21],
        'items' => [
            [
                'nombre' => 'Tardanzas', 
                'url' => 'operaciones/tardanzas_manual.php',
                'cargos_permitidos' => [11, 5, 16, 21]
            ],
            [
                'nombre' => 'Control de Asistencia', 
                'url' => 'supervision/ver_horarios_compactos.php',
                'cargos_permitidos' => [11, 16, 21]
            ],
            [
                'nombre' => 'Reportes de Personal', 
                'url' => 'rrhh/reportes.php',
                'cargos_permitidos' => [16, 21]
            ],
            [
                'nombre' => 'Faltas/Ausencias', 
                'url' => 'lideres/faltas_manual.php',
                'cargos_permitidos' => [11, 5]
            ],
            [
                'nombre' => 'Generar Horarios', 
                'url' => 'lideres/programar_horarios_lider.php',
                'cargos_permitidos' => [11, 5]
            ],
            [
                'nombre' => 'Horarios Programados', 
                'url' => 'supervision/ver_horarios_compactos.php',
                'cargos_permitidos' => [11, 5]
            ],
            [
                'nombre' => 'Marcaciones', 
                'url' => 'supervision/ver_horarios_compactos.php',
                'cargos_permitidos' => [13, 5, 8, 11, 21, 22]
            ]
        ]
    ],
    [
        'nombre' => 'Supervisión',
        'icon' => 'fas fa-eye',
        'cargos_permitidos' => [11, 21, 16],
        'items' => [
            [
                'nombre' => 'Auditorías de Efectivo', 
                'url' => 'auditorias_original/auditinternas/auditorias_consolidadas.php',
                'cargos_permitidos' => [11, 21]
            ],
            [
                'nombre' => 'Control de Inventario', 
                'url' => 'supervision/inventario.php',
                'cargos_permitidos' => [21]
            ]
        ]
    ],
    [
        'nombre' => 'Comunicación Interna',
        'icon' => 'fas fa-comments',
        'cargos_permitidos' => [11, 5, 14, 16, 21],
        'items' => [
            [
                'nombre' => 'Avisos', 
                'url' => 'supervision/auditorias_original/index_avisos_publico.php',
                'cargos_permitidos' => [11, 5, 14, 16, 21]
            ],
            [
                'nombre' => 'Auditorías', 
                'url' => 'supervision/auditorias_original/index_auditorias_publico.php',
                'cargos_permitidos' => [11, 5, 14, 16, 21]
            ],
            [
                'nombre' => 'Promedios', 
                'url' => 'supervision/auditorias_original/promedio.php',
                'cargos_permitidos' => [11, 5, 14, 16, 21]
            ],
            [
                'nombre' => 'KPI Sucursales', 
                'url' => 'sucursales/kpi_sucursales.php',
                'cargos_permitidos' => [11, 5]
            ],
            [
                'nombre' => 'Reclamos', 
                'url' => 'supervision/auditorias_original/index_reclamos_publico.php',
                'cargos_permitidos' => [11, 5, 14, 16, 21]
            ],
            [
                'nombre' => 'Gestión de Comunicación', 
                'url' => 'supervision/auditorias_original/index_avisos.php',
                'cargos_permitidos' => [11, 21]
            ]
        ]
    ],
    [
        'nombre' => 'Mantenimiento',
        'icon' => 'fas fa-tools',
        'cargos_permitidos' => [11, 14, 21, 5, 35],
        'items' => [
            [
                'nombre' => 'Solicitudes', 
                'url' => 'mantenimiento/dashboard_sucursales.php',
                'cargos_permitidos' => [11, 16, 5]
            ],
            [
                'nombre' => 'Solicitudes', 
                'url' => 'mantenimiento/dashboard_mantenimiento.php',
                'cargos_permitidos' => [35, 14, 11, 16]
            ],
            [
                'nombre' => 'Agenda Diaria', 
                'url' => 'mantenimiento/agenda_colaborador.php',
                'cargos_permitidos' => [11, 14, 16, 35]
            ],
            [
                'nombre' => 'Calendario', 
                'url' => 'mantenimiento/calendario.php',
                'cargos_permitidos' => [21, 5, 11, 16, 35]
            ],
            [
                'nombre' => 'Mantenimiento', 
                'url' => 'mantenimiento/formulario_mantenimiento.php',
                'cargos_permitidos' => [11, 5, 35, 16]
            ],
            [
                'nombre' => 'Equipo', 
                'url' => 'mantenimiento/formulario_equipos.php',
                'cargos_permitidos' => [11, 5, 16, 35]
            ]
        ]
    ],
    [
        'nombre' => 'Reportes y Análisis',
        'icon' => 'fas fa-chart-line',
        'cargos_permitidos' => [11, 21],
        'items' => [
            [
                'nombre' => 'Dashboard Ejecutivo', 
                'url' => 'reportes/ejecutivo.php',
                'cargos_permitidos' => [11, 21]
            ],
            [
                'nombre' => 'Reportes Operativos', 
                'url' => 'reportes/operativos.php',
                'cargos_permitidos' => [11, 21]
            ]
        ]
    ],
    
    [
        'nombre' => 'Cerrar Sesion',
        'icon' => 'fas fa-sign-out-alt',
        'cargos_permitidos' => [11, 5, 14, 16, 21, 35],
        'url' => 'logout.php', // Añade esta línea
        'items' => [] // Vacía el array de items
    ],
];

/**
 * Detecta la ruta base automáticamente basado en la estructura de módulos
 */
function detectarRutaBase() {
    // Obtener la ruta del script actual
    $scriptActual = $_SERVER['SCRIPT_FILENAME'];
    $documentRoot = $_SERVER['DOCUMENT_ROOT'];
    
    // Convertir a ruta relativa desde el document root
    $rutaRelativa = str_replace($documentRoot, '', $scriptActual);
    
    // Buscar la posición de '/modulos/' en la ruta
    $posModulos = strpos($rutaRelativa, '/modulos/');
    
    if ($posModulos !== false) {
        // Extraer la parte de la ruta hasta /modulos/
        $rutaHastaModulos = substr($rutaRelativa, 0, $posModulos + 9); // +9 para incluir '/modulos/'
        
        // Contar cuántos directorios hay después de /modulos/
        $rutaDespuesModulos = substr($rutaRelativa, $posModulos + 9);
        $nivelesProfundidad = substr_count($rutaDespuesModulos, '/');
        
        // Generar la ruta base (../../ etc.)
        if ($nivelesProfundidad === 0) {
            return './';
        } else {
            return str_repeat('../', $nivelesProfundidad);
        }
    }
    
    // Si no se encuentra /modulos/, asumir que estamos en la raíz
    return './';
}

/**
 * Genera la URL correcta para cualquier archivo en la estructura de módulos
 */
function generarUrlModulo($rutaDestino) {
    $rutaBase = detectarRutaBase();
    
    // Si el destino es solo "index.php", apuntar al index del módulo actual
    if ($rutaDestino === 'index.php') {
        return $rutaBase . 'index.php';
    }
    
    // Caso especial para logout.php - usar ruta absoluta desde la raíz del dominio
    if ($rutaDestino === 'logout.php') {
        return '/logout.php';
    }
    
    // Para otras rutas, construir la ruta completa
    return $rutaBase . $rutaDestino;
}

/**
 * Detecta el módulo actual basado en la ruta
 */
function detectarModuloActual() {
    $scriptActual = $_SERVER['SCRIPT_FILENAME'];
    $documentRoot = $_SERVER['DOCUMENT_ROOT'];
    
    $rutaRelativa = str_replace($documentRoot, '', $scriptActual);
    $posModulos = strpos($rutaRelativa, '/modulos/');
    
    if ($posModulos !== false) {
        $rutaDespuesModulos = substr($rutaRelativa, $posModulos + 9); // +9 para saltar '/modulos/'
        $partes = explode('/', $rutaDespuesModulos);
        return $partes[0] ?? 'desconocido';
    }
    
    return 'raiz';
}


/**
 * Función para verificar si un cargo tiene acceso a un elemento
 */
function tieneAcceso($cargoOperario, $cargosPermitidos) {
    if (empty($cargosPermitidos)) {
        return true;
    }
    return in_array($cargoOperario, $cargosPermitidos);
}

/**
 * Función para filtrar el menú según los permisos del cargo
 */
function filtrarMenuPorPermisos($menu, $cargoOperario) {
    $menuFiltrado = [];
    
    foreach ($menu as $grupo) {
        if (tieneAcceso($cargoOperario, $grupo['cargos_permitidos'])) {
            $grupoFiltrado = $grupo;
            
            // Si el grupo tiene items, filtrarlos
            if (!empty($grupo['items'])) {
                $itemsFiltrados = [];
                
                foreach ($grupo['items'] as $item) {
                    if (tieneAcceso($cargoOperario, $item['cargos_permitidos'])) {
                        $itemsFiltrados[] = $item;
                    }
                }
                
                // Solo incluir el grupo si tiene items filtrados o si tiene URL directa
                if (!empty($itemsFiltrados)) {
                    $grupoFiltrado['items'] = $itemsFiltrados;
                    $menuFiltrado[] = $grupoFiltrado;
                }
            } else {
                // Grupo sin items pero con URL directa (como Inicio)
                $menuFiltrado[] = $grupoFiltrado;
            }
        }
    }
    
    return $menuFiltrado;
}

/**
 * Función principal para renderizar el menú lateral
 * @param int $cargoOperario - Código del cargo del usuario
 * @param string $paginaActual - Nombre del archivo actual para marcar como activo
 * @param string $basePath - Ruta base desde la raíz del proyecto (ej: '../../')
 * @return string HTML del menú lateral
 */
function renderMenuLateral($cargoOperario, $paginaActual = '') {
    global $menuGlobal;
    
    if (!$cargoOperario) {
        return '';
    }
    
    $menuFiltrado = filtrarMenuPorPermisos($menuGlobal, $cargoOperario);
    
    if (empty($menuFiltrado)) {
        return '';
    }
    
    // Detectar módulo actual para el Dashboard
    $moduloActual = detectarModuloActual();
    
    ob_start();
    ?>
    
    <!-- CSS COMPLETO del Menú Lateral -->
    <style>
        /* ==================== SIDEBAR BASE ==================== */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 70px;
            height: 100vh;
            background: white;
            box-shadow: 2px 0 15px rgba(0,0,0,0.1);
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 1000;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .sidebar:hover {
            width: 260px;
        }
        
        /* ==================== HEADER ==================== */
        .sidebar-header {
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 2px solid #e0e0e0;
            padding: 0 15px;
            overflow: hidden;
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }
        
        .sidebar-header .logo {
            height: 40px;
            width: auto;
            opacity: 1;
            transition: all 0.3s ease 0.15s;
        }
        
        /* ==================== GRUPOS ==================== */
        .menu-group {
            position: relative;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .menu-group-title {
            height: 60px;
            padding: 0;
            color: #0E544C;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            background: white;
        }
        
        .menu-group-title:hover {
            background: #f8f9fa;
        }
        
        .menu-group-title.active {
            background: #e8f5f3;
            border-right: 4px solid #51B8AC;
        }
        
        .menu-icon-wrapper {
            width: 70px;
            min-width: 70px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem !important;
            color: #51B8AC;
            transition: transform 0.3s ease;
        }
        
        .menu-group-title:hover .menu-icon-wrapper {
            transform: scale(1.1);
        }
        
        .menu-group-title.active .menu-icon-wrapper {
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }
        
        .menu-group-name {
            white-space: nowrap;
            opacity: 0;
            transform: translateX(-10px);
            transition: all 0.3s ease 0.1s;
            font-weight: 600;
            font-size: 0.95rem !important;
            flex: 1;
            text-align: left; /* Alinea el texto a la izquierda */
        }
        
        .sidebar:hover .menu-group-name {
            opacity: 1;
            transform: translateX(0);
        }
        
        .chevron-icon {
            margin-right: 15px;
            opacity: 0;
            transition: all 0.3s ease 0.1s;
            font-size: 0.8rem !important;
            color: #666;
        }
        
        .sidebar:hover .chevron-icon {
            opacity: 1;
        }
        
        .menu-group.active .chevron-icon {
            transform: rotate(90deg);
        }
        
        /* ==================== SUBGRUPOS (ACORDEÓN) ==================== */
        .menu-items {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: #fafafa;
        }
        
        /* Solo mostrar subgrupos cuando el sidebar está expandido Y el grupo está activo */
        .sidebar:hover .menu-group.active .menu-items {
            max-height: 600px;
        }
        
        .menu-item {
            padding: 12px 20px 12px 70px;
            color: #666;
            text-decoration: none;
            display: block;
            transition: all 0.2s ease;
            font-size: 0.9rem !important;
            border-left: 3px solid transparent;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-align: left; /* Alinea el texto a la izquierda */
        }
        
        .sidebar:hover .menu-item {
            padding-left: 80px;
        }
        
        .menu-item:hover {
            background: #f0f0f0;
            color: #51B8AC;
            border-left-color: #51B8AC;
            padding-left: 85px;
        }
        
        .menu-item.active {
            background: #e8f5f3;
            color: #0E544C;
            border-left-color: #51B8AC;
            font-weight: 600;
        }
        
        /* ==================== TOOLTIP ==================== */
        .menu-group-title::before {
            content: attr(data-tooltip);
            position: absolute;
            left: 80px;
            background: #333;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.85rem !important;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            pointer-events: none;
            z-index: 1001;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .menu-group-title::after {
            content: '';
            position: absolute;
            left: 70px;
            border: 5px solid transparent;
            border-right-color: #333;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            pointer-events: none;
        }
        
        .sidebar:not(:hover) .menu-group-title:hover::before,
        .sidebar:not(:hover) .menu-group-title:hover::after {
            opacity: 0.95;
            visibility: visible;
        }
        
        .sidebar:hover .menu-group-title::before,
        .sidebar:hover .menu-group-title::after {
            display: none;
        }
        
        /* ==================== BOTÓN TOGGLE MÓVIL ==================== */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1002;
            background: #51B8AC;
            color: white;
            border: none;
            padding: 12px 16px;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        
        .menu-toggle:hover {
            background: #0E544C;
            transform: scale(1.05);
        }
        
        .menu-toggle:active {
            transform: scale(0.95);
        }
        
        .menu-toggle i {
            font-size: 1.2rem !important;
        }
        
        /* ==================== OVERLAY MÓVIL ==================== */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .sidebar-overlay.show {
            display: block;
            opacity: 1;
        }
        
        /* ==================== CONTENEDOR PRINCIPAL ==================== */
        .main-container {
            margin-left: 70px;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 100vh;
        }
        
                
        .contenedor-principal {
            width: 100%;
            margin: 0 auto;
            padding: 20px; /* Cambiar de 0 1px a 20px */
        }
        
        /* ==================== SCROLLBAR PERSONALIZADA ==================== */
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: #51B8AC;
            border-radius: 3px;
        }
        
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: #0E544C;
        }
        
        /* Añade este CSS para los enlaces directos */
        .menu-group-title.direct-link {
            text-decoration: none;
            cursor: pointer;
        }
        
        .menu-group-title.direct-link:hover {
            background: #f8f9fa;
        }
        
        .menu-group-title.direct-link.active {
            background: #e8f5f3;
            border-right: 4px solid #51B8AC;
        }
        
        /* ==================== RESPONSIVE - MÓVIL ==================== */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-70px);
                width: 70px;
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), 
                            width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            
            .sidebar.show {
                transform: translateX(0);
                width: 260px;
            }
            
            /* Forzar expansión en móvil cuando está abierto */
            .sidebar.show .sidebar-header .logo {
                opacity: 1;
                transform: translateX(0);
            }
            
            .sidebar.show .menu-group-name {
                opacity: 1;
                transform: translateX(0);
            }
            
            .sidebar.show .chevron-icon {
                opacity: 1;
            }
            
            .sidebar.show .menu-item {
                padding-left: 80px;
            }
            
            /* Deshabilitar hover en móvil */
            .sidebar:hover {
                width: 70px;
            }
            
            .sidebar.show:hover {
                width: 260px;
            }
            
            /* Tooltips deshabilitados en móvil */
            .menu-group-title::before,
            .menu-group-title::after {
                display: none !important;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .main-container {
                margin-left: 0;
            }
            
        }
        
        /* ==================== ANIMACIONES ADICIONALES ==================== */
        @keyframes slideInFromLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .menu-item {
            animation: slideInFromLeft 0.3s ease-out;
        }
        
        /* ==================== ESTADOS DE CARGA ==================== */
        .sidebar.loading {
            pointer-events: none;
            opacity: 0.6;
        }
        
        /* ==================== MEJORAS VISUALES ==================== */
        .menu-group:last-child {
            border-bottom: none;
        }
        
        .menu-items:empty {
            display: none;
        }
        
        /* Efecto de resaltado al hacer click */
        .menu-item:active {
            background: #daf3f0;
            transform: scale(0.98);
        }
        
        /* ==================== ACCESIBILIDAD ==================== */
        .menu-group-title:focus,
        .menu-item:focus {
            outline: 2px solid #51B8AC;
            outline-offset: -2px;
        }
        
        /* ==================== SOPORTE PARA NAVEGADORES ==================== */
        @supports not (backdrop-filter: blur(10px)) {
            .sidebar-overlay {
                background: rgba(0,0,0,0.7);
            }
        }
    </style>
    
    <!-- Toggle del menú (móvil) -->
    <button class="menu-toggle" onclick="toggleSidebarMobile()" aria-label="Abrir menú">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Overlay para cerrar menú en móvil -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebarMobile()"></div>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="<?php echo generarUrlModulo('../../assets/img/icon12.png'); ?>" alt="Batidos Pitaya" class="logo">
        </div>
        
        <?php foreach ($menuFiltrado as $index => $grupo): ?>
            <div class="menu-group" id="grupo-<?php echo $index; ?>">
                <?php if (!empty($grupo['items'])): ?>
                    <!-- Grupo con subitems (acordeón) -->
                    <div class="menu-group-title" 
                         onclick="toggleMenuGroup(<?php echo $index; ?>)"
                         data-tooltip="<?php echo htmlspecialchars($grupo['nombre']); ?>"
                         role="button"
                         aria-expanded="false"
                         aria-controls="items-<?php echo $index; ?>">
                        <div class="menu-icon-wrapper">
                            <i class="<?php echo $grupo['icon']; ?>"></i>
                        </div>
                        <span class="menu-group-name"><?php echo htmlspecialchars($grupo['nombre']); ?></span>
                        <i class="fas fa-chevron-right chevron-icon"></i>
                    </div>
                    <div class="menu-items" id="items-<?php echo $index; ?>">
                        <?php foreach ($grupo['items'] as $item): ?>
                            <?php 
                            $isActive = '';
                            if ($paginaActual) {
                                $urlFile = basename($item['url']);
                                if ($urlFile === $paginaActual) {
                                    $isActive = 'active';
                                }
                            }
                            ?>
                            <a href="<?php echo htmlspecialchars(generarUrlModulo($item['url'])); ?>" 
                               class="menu-item <?php echo $isActive; ?>"
                               title="<?php echo htmlspecialchars($item['nombre']); ?>">
                                <?php echo htmlspecialchars($item['nombre']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <!-- Grupo sin subitems (enlace directo) -->
                    <?php 
                    $isActiveInicio = '';
                    if ($paginaActual && $grupo['url'] && basename($grupo['url']) === $paginaActual) {
                        $isActiveInicio = 'active';
                    }
                    ?>
                    <a href="<?php echo htmlspecialchars(generarUrlModulo($grupo['url'])); ?>" 
                       class="menu-group-title direct-link <?php echo $isActiveInicio; ?>"
                       data-tooltip="<?php echo htmlspecialchars($grupo['nombre']); ?>"
                       title="<?php echo htmlspecialchars($grupo['nombre']); ?>">
                        <div class="menu-icon-wrapper">
                            <i class="<?php echo $grupo['icon']; ?>"></i>
                        </div>
                        <span class="menu-group-name"><?php echo htmlspecialchars($grupo['nombre']); ?></span>
                    </a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- JavaScript del menú -->
    <script>
        (function() {
            'use strict';
            
            let activeGroupIndex = null;
            
            // Función para toggle de grupo (acordeón)
            window.toggleMenuGroup = function(index) {
                const grupo = document.getElementById('grupo-' + index);
                const allGroups = document.querySelectorAll('.menu-group');
                const titulo = grupo.querySelector('.menu-group-title');
                
                // Cerrar otros grupos
                allGroups.forEach((g, i) => {
                    if (i !== index) {
                        g.classList.remove('active');
                        const t = g.querySelector('.menu-group-title');
                        if (t) t.setAttribute('aria-expanded', 'false');
                    }
                });
                
                // Toggle del grupo actual
                const isActive = grupo.classList.toggle('active');
                titulo.setAttribute('aria-expanded', isActive);
                activeGroupIndex = isActive ? index : null;
            };
            
            // Función para abrir sidebar en móvil
            window.toggleSidebarMobile = function() {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
                
                // Actualizar aria-label
                const toggle = document.querySelector('.menu-toggle');
                if (sidebar.classList.contains('show')) {
                    toggle.setAttribute('aria-label', 'Cerrar menú');
                } else {
                    toggle.setAttribute('aria-label', 'Abrir menú');
                }
            };
            
            // Función para cerrar sidebar en móvil
            window.closeSidebarMobile = function() {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
                
                // Restaurar aria-label
                const toggle = document.querySelector('.menu-toggle');
                toggle.setAttribute('aria-label', 'Abrir menú');
                
                // Cerrar todos los grupos
                document.querySelectorAll('.menu-group').forEach(g => {
                    g.classList.remove('active');
                    const t = g.querySelector('.menu-group-title');
                    if (t) t.setAttribute('aria-expanded', 'false');
                });
                activeGroupIndex = null;
            };
            
            // Cerrar menú en móvil al hacer clic en un enlace
            document.querySelectorAll('.menu-item').forEach(item => {
                item.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        closeSidebarMobile();
                    }
                });
            });
            
            // Marcar grupo activo si hay una página activa
            document.addEventListener('DOMContentLoaded', function() {
                const activeItem = document.querySelector('.menu-item.active');
                if (activeItem) {
                    const parentGroup = activeItem.closest('.menu-group');
                    if (parentGroup) {
                        parentGroup.classList.add('active');
                        const titulo = parentGroup.querySelector('.menu-group-title');
                        if (titulo) {
                            titulo.classList.add('active');
                            titulo.setAttribute('aria-expanded', 'true');
                        }
                    }
                }
                
                // Marcar "Inicio" como activo si estamos en index.php
                const currentPage = window.location.pathname.split('/').pop();
                if (currentPage === 'index.php') {
                    const inicioLinks = document.querySelectorAll('.menu-group-title.direct-link');
                    inicioLinks.forEach(link => {
                        if (link.getAttribute('href') && link.getAttribute('href').includes('index.php')) {
                            link.classList.add('active');
                            link.closest('.menu-group').classList.add('active');
                        }
                    });
                }
            });
            
            // Prevenir scroll del body cuando el menú está abierto en móvil
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.attributeName === 'class') {
                        if (sidebar.classList.contains('show')) {
                            document.body.style.overflow = 'hidden';
                        } else {
                            document.body.style.overflow = '';
                        }
                    }
                });
            });
            
            observer.observe(sidebar, { attributes: true });
            
            // Soporte para teclado (accesibilidad)
            document.addEventListener('keydown', function(e) {
                // ESC para cerrar menú en móvil
                if (e.key === 'Escape' && window.innerWidth <= 768) {
                    closeSidebarMobile();
                }
            });
            
        })();
    </script>
    
    <?php
    return ob_get_clean();
}

