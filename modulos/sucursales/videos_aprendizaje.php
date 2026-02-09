<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

// Verificar acceso al módulo
verificarAccesoCargo([27]);

$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Videos de Aprendizaje - Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Calibri', sans-serif;
            font-size: clamp(12px, 2vw, 16px) !important;
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
            font-size: 1.8rem !important;
        }
        
        .btn-volver {
            background: #51B8AC;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
        }
        
        .btn-volver:hover {
            background: #0E544C;
        }

        /* Estilos para el buscador */
        .search-container {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .search-box {
            position: relative;
            max-width: 600px;
            margin: 0 auto;
        }

        .search-input {
            width: 100%;
            padding: 12px 50px 12px 20px;
            border: 2px solid #51B8AC;
            border-radius: 25px;
            font-size: 1rem !important;
            outline: none;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            border-color: #0E544C;
            box-shadow: 0 0 10px rgba(81, 184, 172, 0.3);
        }

        .search-button {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: #51B8AC;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            color: white;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .search-button:hover {
            background: #0E544C;
        }

        .search-results-info {
            text-align: center;
            margin-top: 15px;
            color: #666;
            font-size: 0.9rem !important;
        }

        .search-highlight {
            background-color: #fff3cd;
            padding: 2px 4px;
            border-radius: 3px;
            font-weight: bold;
        }

        .no-results {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .no-results i {
            font-size: 3rem !important;
            color: #ccc;
            margin-bottom: 15px;
        }

        /* Estilos para acordeones */
        .categoria-videos {
            margin-bottom: 20px;
        }

        .categoria-header {
            background: white;
            border-radius: 8px;
            padding: 15px 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-left: 4px solid #51B8AC;
        }

        .categoria-header:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .categoria-header.active {
            background: #51B8AC;
            color: white;
        }

        .categoria-titulo {
            font-size: 1.4rem !important;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .categoria-toggle {
            transition: transform 0.3s ease;
        }

        .categoria-toggle.active {
            transform: rotate(180deg);
        }

        .videos-container {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            background: white;
            border-radius: 0 0 8px 8px;
            margin-top: 2px;
        }

        .videos-container.active {
            max-height: 5000px;
        }

        .video-item {
            padding: 20px;
            border-bottom: 1px solid #eee;
            transition: background 0.3s ease;
        }

        .video-item:hover {
            background: #f8f8f8;
        }

        .video-item:last-child {
            border-bottom: none;
        }

        .video-titulo {
            font-size: 1.2rem !important;
            font-weight: bold;
            color: #0E544C;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .video-player-container {
            position: relative;
            width: 100%;
            max-width: 600px;
            margin: 15px 0;
        }

        .video-iframe {
            width: 100%;
            height: 337px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .video-info {
            margin-top: 10px;
            color: #666;
        }

        .video-descripcion {
            margin-top: 10px;
            line-height: 1.5;
        }

        /* Estilos para galería de imágenes */
        .imagen-item {
            padding: 20px;
            border-bottom: 1px solid #eee;
            transition: background 0.3s ease;
        }

        .imagen-item:hover {
            background: #f8f8f8;
        }

        .imagen-item:last-child {
            border-bottom: none;
        }

        .imagen-titulo {
            font-size: 1.2rem !important;
            font-weight: bold;
            color: #0E544C;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .imagen-container {
            position: relative;
            width: 100%;
            max-width: 500px;
            margin: 15px 0;
        }

        .imagen-content {
            width: 100%;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .imagen-content:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .imagen-info {
            margin-top: 10px;
            color: #666;
        }

        .imagen-descripcion {
            margin-top: 10px;
            line-height: 1.5;
        }

        /* Modal para imágenes */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            overflow: auto;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            max-width: 90%;
            max-height: 90%;
            margin: auto;
            display: block;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        
        .modal-close {
            position: absolute;
            top: 20px;
            right: 35px;
            color: #f1f1f1;
            font-size: 2rem !important;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
            z-index: 1001;
        }
        
        .modal-close:hover {
            color: #51B8AC;
        }
        
        .modal-caption {
            margin: auto;
            display: block;
            width: 80%;
            max-width: 700px;
            text-align: center;
            color: #ccc;
            padding: 10px 0;
            font-size: 1.1rem !important;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .video-iframe {
                height: 250px;
            }
            
            .categoria-titulo {
                font-size: 1.2rem !important;
            }
            
            .video-titulo, .imagen-titulo {
                font-size: 1.1rem !important;
            }
            
            header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .modal-content {
                max-width: 95%;
            }
            
            .modal-close {
                top: 10px;
                right: 20px;
                font-size: 1.5rem !important;
            }
        }

        @media (max-width: 480px) {
            .video-iframe {
                height: 200px;
            }
            
            .video-item, .imagen-item {
                padding: 15px;
            }
            
            .imagen-content {
                max-width: 100%;
            }
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 3rem !important;
            color: #51B8AC;
            margin-bottom: 15px;
        }

        /* Estilos para resultados de búsqueda */
        .search-match {
            background-color: #fff3cd;
            border-radius: 3px;
            padding: 1px 4px;
            font-weight: bold;
        }

        .hidden {
            display: none !important;
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
                <a href="../../index.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </header>
        
        <div class="module-header">
            <h1 class="module-title-page">Guía de Procedimientos y Buenas Prácticas - Batidos Pitaya</h1>
        </div>
        
        <div class="search-container">
            <div class="search-results-info" id="searchResultsInfo" style="text-align:left !important;">
                Esta Guia Operativa reúne de manera clara y ordenada todos los procedimientos, estándares, responsabilidades y lineamientos que rigen el funcionamiento diario de nuestras estaciones de trabajo: caja, batidos, bowls y waffles. Además, define cómo deben estar organizados los equipos, utensilios, áreas frías y secas, a fin de asegurar un flujo de trabajo eficiente, limpio y consistente en todas las sucursales.
            </div>
        </div>

        <!-- Buscador -->
        <div class="search-container">
            <div class="search-box">
                <input type="text" id="searchInput" class="search-input" placeholder="Buscar videos, imágenes, procedimientos...">
                <button class="search-button" onclick="realizarBusqueda()">
                    <i class="fas fa-search"></i>
                </button>
            </div>
            <div class="search-results-info" id="searchResultsInfo">
                Escribe en el buscador para encontrar contenido específico
            </div>
        </div>
        
        <div class="videos-content" id="videosContent">
            <?php
            // Array de contenido organizado por categorías
            $categoriasContenido = [
                'menajes_equipos' => [
                    'titulo' => 'Menajes y Equipos',
                    'icono' => 'fas fa-workstation',
                    'tipo' => 'imagenes',
                    'items' => [
                        [
                            'titulo' => '',
                            'imagen' => '../../assets/img/estaciones/menaje1.jpg',
                            'descripcion' => ''
                        ],
                        [
                            'titulo' => '',
                            'imagen' => '../../assets/img/estaciones/menaje2.jpg',
                            'descripcion' => ''
                        ],
                        [
                            'titulo' => '',
                            'imagen' => '../../assets/img/estaciones/menaje3.jpg',
                            'descripcion' => ''
                        ],
                        [
                            'titulo' => '',
                            'imagen' => '../../assets/img/estaciones/menaje4.jpg',
                            'descripcion' => ''
                        ],
                        [
                            'titulo' => '',
                            'imagen' => '../../assets/img/estaciones/menaje5.jpg',
                            'descripcion' => ''
                        ]
                    ]
                ],
                'fichas_recetas' => [
                    'titulo' => 'Recetario',
                    'icono' => 'fas fa-workstation',
                    'tipo' => 'imagenes',
                    'items' => [
                        [
                            'titulo' => '',
                            'imagen' => '',
                            'descripcion' => ''
                        ]
                    ]
                ],
                'limpieza_equipos' => [
                    'titulo' => 'Limpieza de Equipos',
                    'icono' => 'fas fa-spray-can',
                    'tipo' => 'videos',
                    'items' => [
                        [
                            'titulo' => 'Limpieza de waflera después de cada uso',
                            'url' => 'https://www.youtube.com/embed/zESew-_D0wc',
                            'descripcion' => 'Procedimiento correcto para la limpieza y mantenimiento de la waflera después de cada uso.'
                        ],
                        [
                            'titulo' => 'Limpieza de refrigeradora',
                            'url' => 'https://www.youtube.com/embed/A0fZ4i0nEzg',
                            'descripcion' => 'Método adecuado para la limpieza interna y externa de la refrigeradora.'
                        ],
                        [
                            'titulo' => 'Limpieza de Organizadora',
                            'url' => 'https://www.youtube.com/embed/uCevWt6yuCo',
                            'descripcion' => 'Cómo mantener organizada y limpia la estación de trabajo.'
                        ],
                        [
                            'titulo' => 'Limpieza de Luz UV',
                            'url' => 'https://www.youtube.com/embed/Ekc7gFE8M0M',
                            'descripcion' => 'Procedimiento de limpieza y mantenimiento de las luces UV.'
                        ],
                        [
                            'titulo' => 'Limpieza de estación de trabajo',
                            'url' => 'https://www.youtube.com/embed/EILmxSTEc2U',
                            'descripcion' => 'Limpieza completa de la estación de trabajo diaria.'
                        ],
                        [
                            'titulo' => 'Limpieza de ventiladores de techo',
                            'url' => 'https://www.youtube.com/embed/1RsJ3yY9ha0',
                            'descripcion' => 'Mantenimiento y limpieza de ventiladores de techo.'
                        ],
                        [
                            'titulo' => 'Limpieza de Mesas y Sillas',
                            'url' => 'https://www.youtube.com/embed/qpfX02yrVrQ',
                            'descripcion' => 'Procedimiento para la limpieza de mobiliario del área de clientes.'
                        ]
                    ]
                ],
                'proceso_elaboracion' => [
                    'titulo' => 'Proceso de Elaboración',
                    'icono' => 'fas fa-blender',
                    'tipo' => 'videos',
                    'items' => [
                        [
                            'titulo' => 'Elaboración de Waffle',
                            'url' => 'https://www.youtube.com/embed/h3X-teeKG1c',
                            'descripcion' => 'Proceso completo para la elaboración de waffles perfectos.'
                        ],
                        [
                            'titulo' => 'Elaboración de Bowl',
                            'url' => 'https://www.youtube.com/embed/efx472JomOQ',
                            'descripcion' => 'Técnicas para preparar bowls atractivos y deliciosos.'
                        ],
                        [
                            'titulo' => 'Elaboración de Batido',
                            'url' => 'https://www.youtube.com/embed/Lv50I2xV5o0',
                            'descripcion' => 'Método estándar para la preparación de batidos consistentes.'
                        ]
                    ]
                ],
                'limpieza_infraestructura' => [
                    'titulo' => 'Limpieza de Infraestructura',
                    'icono' => 'fas fa-building',
                    'tipo' => 'videos',
                    'items' => [
                        [
                            'titulo' => 'Limpieza de bodega',
                            'url' => 'https://www.youtube.com/embed/mDQAKSzCpQE',
                            'descripcion' => 'Organización y limpieza del área de bodega y almacenamiento.'
                        ]
                    ]
                ],
                'estaciones_trabajo' => [
                    'titulo' => 'Estaciones de Trabajo',
                    'icono' => 'fas fa-workstation',
                    'tipo' => 'imagenes',
                    'items' => [
                        [
                            'titulo' => 'Estación de Batidos - Configuración Ideal',
                            'imagen' => '../../assets/img/estaciones/organizador_acrilico.jpg',
                            'descripcion' => 'Configuración óptima de la estación de batidos con todos los ingredientes organizados y equipos listos para uso.'
                        ],
                        [
                            'titulo' => 'Estación de Waffles - Organización Correcta',
                            'imagen' => '../../assets/img/estaciones/organizacion_freezer.jpg',
                            'descripcion' => 'Distribución adecuada de la estación de waffles con materias primas y utensilios en su lugar correspondiente.'
                        ],
                        [
                            'titulo' => 'Estación de Toppings - Presentación Estándar',
                            'imagen' => '../../assets/img/estaciones/organizacion_refrigeradora.jpg',
                            'descripcion' => 'Presentación estándar de la estación de toppings con todos los ingredientes frescos y visibles para el cliente.'
                        ],
                        [
                            'titulo' => 'Estación de Batidos - Vista Completa',
                            'imagen' => '../../assets/img/estaciones/estacion_batidos.jpg',
                            'descripcion' => 'Vista completa de la estación de batidos mostrando la disposición ideal de equipos e ingredientes.'
                        ],
                        [
                            'titulo' => 'Estación de Caja - Organización',
                            'imagen' => '../../assets/img/estaciones/estacion_caja.jpg',
                            'descripcion' => 'Organización correcta de la estación de caja y atención al cliente.'
                        ],
                        [
                            'titulo' => 'Estación de Waffles y Bowls',
                            'imagen' => '../../assets/img/estaciones/estacion_waffles_bowls.jpg',
                            'descripcion' => 'Configuración estándar para la preparación de waffles y bowls.'
                        ]
                    ]
                ]
            ];

            // Mostrar categorías
            foreach ($categoriasContenido as $categoriaKey => $categoria) {
                echo '
                <div class="categoria-videos" data-categoria="' . $categoriaKey . '">
                    <div class="categoria-header" onclick="toggleCategoria(\'' . $categoriaKey . '\')">
                        <div class="categoria-titulo">
                            <i class="' . $categoria['icono'] . '"></i>
                            ' . $categoria['titulo'] . '
                            <span class="item-count" style="font-size: 0.9rem; background: rgba(0,0,0,0.1); padding: 2px 8px; border-radius: 12px; margin-left: 10px;">
                                ' . count($categoria['items']) . ' ' . ($categoria['tipo'] === 'videos' ? 'videos' : 'imágenes') . '
                            </span>
                        </div>
                        <div class="categoria-toggle">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                    <div class="videos-container" id="videos-' . $categoriaKey . '">
                ';
                
                foreach ($categoria['items'] as $index => $item) {
                    if ($categoria['tipo'] === 'videos') {
                        // Mostrar video
                        echo '
                            <div class="video-item" data-titulo="' . htmlspecialchars(strtolower($item['titulo'])) . '" data-descripcion="' . htmlspecialchars(strtolower($item['descripcion'])) . '">
                                <div class="video-titulo">
                                    <i class="fas fa-play-circle"></i>
                                    <span class="titulo-texto">' . $item['titulo'] . '</span>
                                </div>
                                <div class="video-player-container">
                                    <iframe 
                                        class="video-iframe"
                                        src="' . $item['url'] . '" 
                                        frameborder="0" 
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                        allowfullscreen>
                                    </iframe>
                                </div>
                                <div class="video-info">
                                    <div class="video-descripcion">
                                        <span class="descripcion-texto">' . $item['descripcion'] . '</span>
                                    </div>
                                </div>
                            </div>
                        ';
                    } else {
                        // Mostrar imagen
                        echo '
                            <div class="imagen-item" data-titulo="' . htmlspecialchars(strtolower($item['titulo'])) . '" data-descripcion="' . htmlspecialchars(strtolower($item['descripcion'])) . '">
                                <div class="imagen-titulo">
                                    <i class="fas fa-image"></i>
                                    <span class="titulo-texto">' . $item['titulo'] . '</span>
                                </div>
                                <div class="imagen-container">
                                    <img 
                                        src="' . $item['imagen'] . '" 
                                        alt="' . htmlspecialchars($item['titulo']) . '"
                                        class="imagen-content"
                                        onclick="abrirModal(\'' . $item['imagen'] . '\', \'' . htmlspecialchars($item['titulo']) . '\')"
                                    >
                                </div>
                                <div class="imagen-info">
                                    <div class="imagen-descripcion">
                                        <span class="descripcion-texto">' . $item['descripcion'] . '</span>
                                    </div>
                                </div>
                            </div>
                        ';
                    }
                }
                
                echo '
                    </div>
                </div>
                ';
            }
            ?>
        </div>

        <!-- Mensaje cuando no hay resultados -->
        <div id="noResults" class="no-results hidden">
            <i class="fas fa-search"></i>
            <h3>No se encontraron resultados</h3>
            <p>Intenta con otros términos de búsqueda</p>
        </div>
    </div>

    <!-- Modal para imágenes -->
    <div id="imageModal" class="modal">
        <span class="modal-close" onclick="cerrarModal()">&times;</span>
        <img class="modal-content" id="modalImage">
        <div id="modalCaption" class="modal-caption"></div>
    </div>

    <script>
        // Variable para llevar el control de la categoría activa
        let categoriaActiva = null;
        let terminoBusquedaActual = '';

        // Función para realizar búsqueda
        function realizarBusqueda() {
            const searchInput = document.getElementById('searchInput');
            const searchTerm = searchInput.value.trim().toLowerCase();
            const resultadosInfo = document.getElementById('searchResultsInfo');
            const noResults = document.getElementById('noResults');
            const videosContent = document.getElementById('videosContent');
            
            terminoBusquedaActual = searchTerm;
            
            if (searchTerm === '') {
                // Si no hay término de búsqueda, mostrar todo
                mostrarTodo();
                resultadosInfo.innerHTML = 'Escribe en el buscador para encontrar contenido específico';
                noResults.classList.add('hidden');
                return;
            }
            
            let totalResultados = 0;
            let categoriasConResultados = 0;
            
            // Buscar en todas las categorías
            document.querySelectorAll('.categoria-videos').forEach(categoria => {
                const categoriaKey = categoria.getAttribute('data-categoria');
                const categoriaHeader = categoria.querySelector('.categoria-header');
                const videosContainer = categoria.querySelector('.videos-container');
                const itemCount = categoria.querySelector('.item-count');
                let resultadosEnCategoria = 0;
                
                // Buscar en items de la categoría
                categoria.querySelectorAll('.video-item, .imagen-item').forEach(item => {
                    const titulo = item.getAttribute('data-titulo');
                    const descripcion = item.getAttribute('data-descripcion');
                    
                    const coincideTitulo = titulo.includes(searchTerm);
                    const coincideDescripcion = descripcion.includes(searchTerm);
                    
                    if (coincideTitulo || coincideDescripcion) {
                        item.classList.remove('hidden');
                        resultadosEnCategoria++;
                        totalResultados++;
                        
                        // Resaltar texto coincidente
                        resaltarTextoCoincidente(item, searchTerm);
                    } else {
                        item.classList.add('hidden');
                    }
                });
                
                // Mostrar/ocultar categoría según si tiene resultados
                if (resultadosEnCategoria > 0) {
                    categoria.classList.remove('hidden');
                    categoriasConResultados++;
                    
                    // Actualizar contador de la categoría
                    itemCount.textContent = resultadosEnCategoria + ' ' + 
                        (categoria.querySelector('.video-item') ? 'videos' : 'imágenes') + ' encontrados';
                    
                    // Abrir categoría automáticamente si tiene resultados
                    if (!videosContainer.classList.contains('active')) {
                        videosContainer.classList.add('active');
                        categoriaHeader.classList.add('active');
                        categoriaHeader.querySelector('.categoria-toggle i').classList.remove('fa-chevron-down');
                        categoriaHeader.querySelector('.categoria-toggle i').classList.add('fa-chevron-up');
                    }
                } else {
                    categoria.classList.add('hidden');
                }
            });
            
            // Mostrar información de resultados
            if (totalResultados > 0) {
                resultadosInfo.innerHTML = `Se encontraron <strong>${totalResultados}</strong> resultados en <strong>${categoriasConResultados}</strong> categorías para "<span class="search-highlight">${searchTerm}</span>"`;
                noResults.classList.add('hidden');
                videosContent.classList.remove('hidden');
            } else {
                resultadosInfo.innerHTML = `No se encontraron resultados para "<span class="search-highlight">${searchTerm}</span>"`;
                noResults.classList.remove('hidden');
                videosContent.classList.add('hidden');
            }
        }
        
        // Función para mostrar todo el contenido (sin filtros)
        function mostrarTodo() {
            document.querySelectorAll('.categoria-videos').forEach(categoria => {
                categoria.classList.remove('hidden');
                const itemCount = categoria.querySelector('.item-count');
                const categoriaOriginal = categoriasOriginales.find(cat => cat.key === categoria.getAttribute('data-categoria'));
                
                if (categoriaOriginal) {
                    itemCount.textContent = categoriaOriginal.count + ' ' + categoriaOriginal.tipo;
                }
                
                categoria.querySelectorAll('.video-item, .imagen-item').forEach(item => {
                    item.classList.remove('hidden');
                    // Quitar resaltado
                    quitarResaltado(item);
                });
            });
            
            document.getElementById('videosContent').classList.remove('hidden');
        }
        
        // Función para resaltar texto coincidente
        function resaltarTextoCoincidente(item, searchTerm) {
            const tituloElement = item.querySelector('.titulo-texto');
            const descripcionElement = item.querySelector('.descripcion-texto');
            
            if (tituloElement) {
                const tituloOriginal = tituloElement.getAttribute('data-original') || tituloElement.textContent;
                tituloElement.setAttribute('data-original', tituloOriginal);
                const tituloResaltado = resaltarTexto(tituloOriginal, searchTerm);
                tituloElement.innerHTML = tituloResaltado;
            }
            
            if (descripcionElement) {
                const descripcionOriginal = descripcionElement.getAttribute('data-original') || descripcionElement.textContent;
                descripcionElement.setAttribute('data-original', descripcionOriginal);
                const descripcionResaltada = resaltarTexto(descripcionOriginal, searchTerm);
                descripcionElement.innerHTML = descripcionResaltada;
            }
        }
        
        // Función para quitar resaltado
        function quitarResaltado(item) {
            const tituloElement = item.querySelector('.titulo-texto');
            const descripcionElement = item.querySelector('.descripcion-texto');
            
            if (tituloElement && tituloElement.getAttribute('data-original')) {
                tituloElement.textContent = tituloElement.getAttribute('data-original');
                tituloElement.removeAttribute('data-original');
            }
            
            if (descripcionElement && descripcionElement.getAttribute('data-original')) {
                descripcionElement.textContent = descripcionElement.getAttribute('data-original');
                descripcionElement.removeAttribute('data-original');
            }
        }
        
        // Función para resaltar texto en un string
        function resaltarTexto(texto, termino) {
            const regex = new RegExp(`(${termino.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
            return texto.replace(regex, '<span class="search-match">$1</span>');
        }
        
        // Búsqueda en tiempo real
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                realizarBusqueda();
            }, 300);
        });
        
        // Búsqueda al presionar Enter
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                realizarBusqueda();
            }
        });
        
        // Limpiar búsqueda
        document.getElementById('searchInput').addEventListener('search', function() {
            if (this.value === '') {
                realizarBusqueda();
            }
        });

        // Almacenar información original de las categorías
        const categoriasOriginales = [];
        document.querySelectorAll('.categoria-videos').forEach(categoria => {
            const key = categoria.getAttribute('data-categoria');
            const count = categoria.querySelectorAll('.video-item, .imagen-item').length;
            const tipo = categoria.querySelector('.video-item') ? 'videos' : 'imágenes';
            categoriasOriginales.push({ key, count, tipo });
        });

        // Funciones existentes para acordeones y modal
        function toggleCategoria(categoriaKey) {
            const videosContainer = document.getElementById('videos-' + categoriaKey);
            const categoriaHeader = videosContainer.previousElementSibling;
            const toggleIcon = categoriaHeader.querySelector('.categoria-toggle i');
            
            if (categoriaActiva === categoriaKey) {
                videosContainer.classList.remove('active');
                categoriaHeader.classList.remove('active');
                toggleIcon.classList.remove('fa-chevron-up');
                toggleIcon.classList.add('fa-chevron-down');
                categoriaActiva = null;
            } else {
                if (categoriaActiva) {
                    const anteriorContainer = document.getElementById('videos-' + categoriaActiva);
                    const anteriorHeader = anteriorContainer.previousElementSibling;
                    const anteriorToggle = anteriorHeader.querySelector('.categoria-toggle i');
                    
                    anteriorContainer.classList.remove('active');
                    anteriorHeader.classList.remove('active');
                    anteriorToggle.classList.remove('fa-chevron-up');
                    anteriorToggle.classList.add('fa-chevron-down');
                }
                
                videosContainer.classList.add('active');
                categoriaHeader.classList.add('active');
                toggleIcon.classList.remove('fa-chevron-down');
                toggleIcon.classList.add('fa-chevron-up');
                categoriaActiva = categoriaKey;
            }
        }

        function abrirModal(src, titulo) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            const caption = document.getElementById('modalCaption');
            
            modal.style.display = 'flex';
            modalImg.src = src;
            caption.innerHTML = titulo;
            document.body.style.overflow = 'hidden';
        }

        function cerrarModal() {
            const modal = document.getElementById('imageModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        document.getElementById('imageModal').addEventListener('click', function(event) {
            if (event.target === this) {
                cerrarModal();
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                cerrarModal();
            }
        });
    </script>
</body>
</html>