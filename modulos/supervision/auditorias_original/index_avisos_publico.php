<?php
$version = "1.0.17";
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../core/layout/menu_lateral.php';
require_once '../../../core/layout/header_universal.php';
require_once '../../../core/permissions/permissions.php';


//******************************Estándar para header******************************
// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
//$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo
//if (!verificarAccesoCargo([42, 26, 2, 5, 43, 8, 11, 13, 16, 21, 22, 27, 28,43, 35]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
//    header('Location: ../../../index.php');
//    exit();
//}
if (!tienePermiso('avisos_internos', 'vista', $cargoOperario)) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Función para formatear fecha en español con corrección horaria (UTC-6)
function formatFechaEspanol($fecha) {
    $meses = [
        1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr',
        5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago',
        9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'
    ];
    
    $date = new DateTime($fecha, new DateTimeZone('UTC'));
    $date->modify('-6 hours'); // Compensar las 6 horas adicionales
    
    return $date->format('d').'-'.$meses[$date->format('n')].'-'.$date->format('y').' '.$date->format('h:i a');
}

// Al inicio del archivo, detecta si esta es la página de auditorías
$pagina_actual = basename($_SERVER['PHP_SELF']);
$es_pagina_auditorias = $pagina_actual == 'index_auditorias_publico.php';
$es_pagina_avisos = $pagina_actual == 'index_avisos_publico.php';
$es_pagina_promedios = ($pagina_actual == 'promedio.php');
$es_pagina_reclamos = ($pagina_actual == 'index_reclamos_publico.php');

// Configuración de paginación
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, $currentPage); // Asegurar que no sea menor a 1
$offset = ($currentPage - 1) * $itemsPerPage;

// Obtener la sucursal del usuario actual
$sucursalUsuario = obtenerSucursalUsuarioActual();

try {
    // Contar el total de avisos (filtrados por sucursal si aplica)
    if ($sucursalUsuario === null) {
        // Admin o usuario sin sucursal asignada - ver todos los avisos
        $countSql = "SELECT COUNT(*) FROM announcements";
        $totalAvisos = $conn->query($countSql)->fetchColumn();
    } else {
        // Usuario con sucursal asignada - solo avisos para su sucursal
        $countSql = "SELECT COUNT(DISTINCT a.id) 
                     FROM announcements a
                     JOIN announcement_branches ab ON a.id = ab.announcement_id
                     WHERE ab.branch_id = ?";
        $stmt = $conn->prepare($countSql);
        $stmt->execute([$sucursalUsuario]);
        $totalAvisos = $stmt->fetchColumn();
    }
    
    $totalPages = ceil($totalAvisos / $itemsPerPage);
    
    // Obtener los avisos paginados (filtrados por sucursal si aplica)
    if ($sucursalUsuario === null) {
        // Admin o usuario sin sucursal asignada - ver todos los avisos
        $sql = "SELECT a.id, a.title, a.content, a.created_at, CONCAT(o.Nombre, ' ', o.Apellido) as author 
                FROM announcements a 
                LEFT JOIN Operarios o ON a.created_by = o.CodOperario
                ORDER BY a.created_at DESC
                LIMIT ? OFFSET ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$itemsPerPage, $offset]);
    } else {
        // Usuario con sucursal asignada - solo avisos para su sucursal
        $sql = "SELECT a.id, a.title, a.content, a.created_at, CONCAT(o.Nombre, ' ', o.Apellido) as author 
                FROM announcements a 
                LEFT JOIN Operarios o ON a.created_by = o.CodOperario
                JOIN announcement_branches ab ON a.id = ab.announcement_id
                WHERE ab.branch_id = ?
                ORDER BY a.created_at DESC
                LIMIT ? OFFSET ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$sucursalUsuario, $itemsPerPage, $offset]);
    }
    
    $avisos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener sucursales para cada aviso (solo las necesarias)
    $avisoIds = array_column($avisos, 'id');
    $branchesByAviso = [];
    
    if (!empty($avisoIds)) {
        $placeholders = implode(',', array_fill(0, count($avisoIds), '?'));
        
        // Si el usuario tiene sucursal asignada, solo obtenemos esa sucursal
        if ($sucursalUsuario !== null) {
            $stmt = $conn->prepare("
                SELECT ab.announcement_id, s.nombre as branch_name 
                FROM announcement_branches ab 
                JOIN sucursales s ON ab.branch_id = s.codigo 
                WHERE ab.announcement_id IN ($placeholders)
                AND ab.branch_id = ?
            ");
            $params = array_merge($avisoIds, [$sucursalUsuario]);
        } else {
            // Admin ve todas las sucursales de cada aviso
            $stmt = $conn->prepare("
                SELECT ab.announcement_id, s.nombre as branch_name 
                FROM announcement_branches ab 
                JOIN sucursales s ON ab.branch_id = s.codigo 
                WHERE ab.announcement_id IN ($placeholders)
            ");
            $params = $avisoIds;
        }
        
        $stmt->execute($params);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $branchesByAviso[$row['announcement_id']][] = $row['branch_name'];
        }
    }

} catch (PDOException $e) {
    error_log("Error en index_avisos_publico.php: " . $e->getMessage());
    die("Ocurrió un error al cargar los avisos. Por favor intente más tarde.");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avisos - Batidos Pitaya</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo $version; ?>"> <!-- contiene main, sub container * y body -->
    
    <style>

        /* Estilos para los avisos */
        .aviso {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 15px;
            overflow: hidden;
            text-align: left;
        }

        .aviso-header {
            padding: 12px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            background-color: #f8f8f8;
        }
        
        /* Nuevos estilos para el contenedor del texto */
        .aviso-header > div:first-child {
            flex: 1;
            min-width: 0; /* Permite que el texto se trunque si es necesario */
            text-align: center;
            padding-right: 15px; /* Espacio para los íconos */
        }
        
        /* Estilos para los íconos */
        .aviso-actions {
            display: flex;
            gap: 10px;
            flex-shrink: 0; /* Evita que se reduzcan */
        }

        .aviso-title {
            font-weight: bold;
            color: #0E544C;
            font-size: 16px;
        }

        .aviso-date {
            color: #666;
            font-size: 13px;
        }

        .aviso-toggle {
            background: none;
            border: none;
            font-size: 16px;
            cursor: pointer;
            color: #51B8AC;
        }

        .aviso-content {
            padding: 0 15px;
            max-height: 0;
            overflow: hidden;
            opacity: 0;
            transition: max-height 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94), 
                        padding 0.4s ease, 
                        opacity 0.3s ease 0.1s;
            will-change: max-height, opacity;
        }

        .aviso-content.active {
            padding: 15px;
            max-height: 2000px; /* Ajusta según necesidad */
            opacity: 1;
        }
        
        .aviso-toggle i {
            transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.27, 1.55);
        }
        
        .aviso-toggle .fa-chevron-up {
            transform: rotate(180deg);
        }
        
        .aviso-text {
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .attachments {
            margin-top: 15px;
        }

        .attachment {
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 10px;
        }

        .attachment a {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #333;
            padding: 5px 10px;
            background-color: #f0f0f0;
            border-radius: 4px;
            font-size: 13px;
        }

        .attachment i {
            margin-right: 5px;
            color: #51B8AC;
        }

        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }

        .gallery-item {
            border-radius: 4px;
            overflow: hidden;
            border: 1px solid #eee;
        }

        .gallery-item img, .gallery-item video {
            width: 100%;
            height: auto;
            display: block;
        }

        .btn-nuevo-aviso {
            background-color: #51B8AC;
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            margin: 15px 0;
            font-weight: bold;
        }

        /* Nuevos estilos para filtros y paginación */
        .filters-container {
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-group label {
            font-weight: bold;
            color: #0E544C;
        }

        .filter-group select, .filter-group input {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
        }

        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }

        .pagination a:hover {
            background-color: #51B8AC;
            color: white;
            border-color: #51B8AC;
        }

        .pagination .current {
            background-color: #0E544C;
            color: white;
            border-color: #0E544C;
        }

        .branches-list {
            margin-top: 8px;
            font-size: 13px;
            color: #555;
        }

        .branches-list i {
            color: #51B8AC;
            margin-right: 5px;
        }

        @media (max-width: 768px) {
            .header-container {
                flex-direction: row;
                align-items: center;
                gap: 10px;
            }
            
            .buttons-container {
                position: static;
                transform: none;
                order: 3;
                width: 100%;
                justify-content: center;
                margin-top: 10px;
            }
            
            .logo-container {
                order: 1;
                margin-right: 0;
            }
            
            .user-info {
                order: 2;
                margin-left: auto;
            }
            
            .btn-agregar {
                padding: 6px 10px;
                font-size: 13px;
            }
    
            .aviso-header {
                flex-wrap: nowrap; /* Evita que los elementos se coloquen en varias líneas */
            }
            
            .aviso-title {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            
            .aviso-date, 
            .branches-list {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .filters-container {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        
        @media (max-width: 480px) {
            .aviso-header {
                padding: 10px 12px;
            }
            
            .aviso-title {
                font-size: 14px;
            }
            
            .aviso-date {
                font-size: 12px;
            }
            
            .branches-list {
                font-size: 12px;
            }
            
            .aviso-toggle, 
            .edit-btn {
                font-size: 14px;
            }
            
            .btn-agregar {
                flex-grow: 1;
                justify-content: center;
                white-space: normal;
                text-align: center;
                padding: 8px 5px;
            }
            
            .user-info {
                flex-direction: column;
                align-items: flex-end;
            }
            
            .btn-agregar i {
                margin-right: 4px;
            }
    
            .gallery {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }

            .pagination {
                flex-wrap: wrap;
            }
        }
        
        .aviso-actions {
            display: flex;
            gap: 10px;
        }
        
        .edit-btn {
            color: #51B8AC;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        
        .edit-btn:hover {
            color: #0E544C;
        }

        /* Estilo para avisos nuevos */
        .new-aviso {
            border-left: 4px solid #51B8AC;
        }
        
        /* Estilos para el modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            overflow: auto;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            max-width: 90%;
            max-height: 90%;
            margin: auto;
            display: block;
        }
        
        .modal-video {
            width: 80%;
            max-width: 800px;
        }
        
        .modal-close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
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
        }
        
        @media (max-width: 768px) {
            .modal-content {
                max-width: 95%;
            }
            
            .modal-video {
                width: 95%;
            }
            
            .modal-close {
                top: 10px;
                right: 20px;
                font-size: 30px;
            }
        }
        
        .gallery-item img, 
        .gallery-item video {
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .gallery-item img:hover, 
        .gallery-item video:hover {
            transform: scale(1.02);
        }
        
        /* Estilos para deshabilitar descargas */
        video::-internal-media-controls-download-button {
            display:none;
        }
        
        video::-webkit-media-controls-enclosure {
            overflow:hidden;
        }
        
        video::-webkit-media-controls-panel {
            width: calc(100% + 30px);
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
        }
        
        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        
        .pagination a:hover {
            background-color: #51B8AC;
            color: white;
            border-color: #51B8AC;
        }
        
        .pagination .current {
            background-color: #0E544C;
            color: white;
            border-color: #0E544C;
        }
    </style>
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Anuncios Internos'); ?>
            
            <?php if (empty($avisos)): ?>
                <div style="background: white; padding: 20px; border-radius: 8px;">
                    No hay avisos actualmente.
                </div>
            <?php else: ?>
                <?php foreach ($avisos as $aviso): 
                    // Determinar si el aviso es nuevo (últimos 3 días)
                    $isNew = (strtotime($aviso['created_at']) > strtotime('-3 days'));
                ?>
                    <div class="aviso <?= $isNew ? 'new-aviso' : '' ?>">
                        <div class="aviso-header">
                            <div>
                                <div class="aviso-title"><?= htmlspecialchars($aviso['title']) ?></div>
                                <div class="aviso-date">
                                    <?= formatFechaEspanol($aviso['created_at']) ?>
                                    <?php if ($aviso['author']): ?>
                                        - Publicado por: <?= htmlspecialchars($aviso['author']) ?>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($branchesByAviso[$aviso['id']])): ?>
                                    <div class="branches-list">
                                        <i class="fas fa-store"></i>
                                        <?= implode(', ', $branchesByAviso[$aviso['id']]) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="aviso-actions">
                                <button class="aviso-toggle">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="aviso-content">
                            <div style="text-align:left !important;" class="aviso-text">
                                <?= nl2br(htmlspecialchars($aviso['content'])) ?>
                            </div>
                            
                            <?php 
                            // Obtener adjuntos para este aviso
                            $stmt = $conn->prepare("SELECT * FROM attachments WHERE announcement_id = ?");
                            $stmt->execute([$aviso['id']]);
                            $adjuntos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if ($adjuntos): 
                                $documentTypes = [
                                    'application/pdf',
                                    'application/msword',
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                    'application/vnd.ms-excel',
                                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                    'application/vnd.ms-powerpoint',
                                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                                    'text/plain',
                                    'application/zip',
                                    'application/x-rar-compressed'
                                ];
                                
                                $multimediaTypes = [
                                    'image/jpeg',
                                    'image/png',
                                    'image/gif',
                                    'image/webp',
                                    'video/mp4',
                                    'video/webm',
                                    'video/quicktime',
                                    'audio/mpeg',
                                    'audio/wav'
                                ];
                                
                                $documentos = array_filter($adjuntos, function($a) use ($documentTypes) {
                                    return in_array($a['file_type'], $documentTypes);
                                });
                                
                                $multimedia = array_filter($adjuntos, function($a) use ($multimediaTypes) {
                                    return in_array($a['file_type'], $multimediaTypes);
                                });
                            ?>
                                <?php if ($documentos): ?>
                                    <div class="attachments">
                                        <h4>Documentos adjuntos:</h4>
                                        <?php foreach ($documentos as $doc): ?>
                                            <div class="attachment">
                                                <?php 
                                                    $prefixedDocPath = $doc['file_path'];
                                                    $icon = 'fa-file';
                                                    if (strpos($doc['file_type'], 'pdf') !== false) $icon = 'fa-file-pdf';
                                                    elseif (strpos($doc['file_type'], 'word') !== false) $icon = 'fa-file-word';
                                                    elseif (strpos($doc['file_type'], 'excel') !== false) $icon = 'fa-file-excel';
                                                    elseif (strpos($doc['file_type'], 'powerpoint') !== false) $icon = 'fa-file-powerpoint';
                                                    elseif (strpos($doc['file_type'], 'zip') !== false || strpos($doc['file_type'], 'rar') !== false) $icon = 'fa-file-archive';
                                                ?>
                                                <a href="<?= htmlspecialchars($prefixedDocPath) ?>" target="_blank">
                                                    <i class="fas <?= $icon ?>"></i>
                                                    <?= htmlspecialchars($doc['file_name']) ?>
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($multimedia): ?>
                                    <div class="gallery">
                                        <?php foreach ($multimedia as $item): ?>
                                            <div class="gallery-item">
                                                <?php 
                                                    $prefixedPath = $item['file_path'];
                                                ?>
                                                <?php if (strpos($item['file_type'], 'image') !== false): ?>
                                                    <img src="<?= htmlspecialchars($prefixedPath) ?>" 
                                                         alt="<?= htmlspecialchars($aviso['title']) ?>"
                                                         loading="lazy">
                                                <?php elseif (strpos($item['file_type'], 'video') !== false): ?>
                                                    <video controls controlsList="nodownload" oncontextmenu="return false;" disablePictureInPicture>
                                                        <source src="<?= htmlspecialchars($prefixedPath) ?>" 
                                                                type="<?= htmlspecialchars($item['file_type']) ?>">
                                                        Tu navegador no soporta el elemento de video.
                                                    </video>
                                                <?php elseif (strpos($item['file_type'], 'audio') !== false): ?>
                                                    <audio controls>
                                                        <source src="<?= htmlspecialchars($prefixedPath) ?>" 
                                                                type="<?= htmlspecialchars($item['file_type']) ?>">
                                                        Tu navegador no soporta el elemento de audio.
                                                    </audio>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Paginación -->
                <div class="pagination" style="margin-bottom:10px;">
                    <?php if ($currentPage > 1): ?>
                        <a href="?page=<?= $currentPage - 1 ?>">&laquo; Anterior</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i == $currentPage): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($currentPage < $totalPages): ?>
                        <a href="?page=<?= $currentPage + 1 ?>">Siguiente &raquo;</a>
                    <?php endif; ?>
                </div>
                
            <?php endif; ?>
        </div>
     </div>

    <script>
        // Toggle para mostrar/ocultar contenido de avisos con mejor transición
        document.addEventListener('DOMContentLoaded', function() {
            const toggleButtons = document.querySelectorAll('.aviso-toggle');
            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const content = this.closest('.aviso-header').nextElementSibling;
                    const icon = this.querySelector('i');
                    
                    if (content.classList.contains('active')) {
                        // Animación de cierre
                        content.style.maxHeight = content.scrollHeight + 'px';
                        void content.offsetHeight; // Reflow
                        content.style.maxHeight = '0';
                        
                        setTimeout(() => {
                            content.classList.remove('active');
                        }, 500);
                        
                        icon.classList.replace('fa-chevron-up', 'fa-chevron-down');
                    } else {
                        // Animación de apertura
                        content.classList.add('active');
                        content.style.maxHeight = '0';
                        void content.offsetHeight; // Reflow
                        content.style.maxHeight = content.scrollHeight + 'px';
                        
                        icon.classList.replace('fa-chevron-down', 'fa-chevron-up');
                        
                        setTimeout(() => {
                            if (content.classList.contains('active')) {
                                content.style.maxHeight = 'none';
                            }
                        }, 500);
                    }
                });
            });
            
            // Lazy loading para imágenes/videos cuando se expande el aviso
            document.querySelectorAll('.aviso-toggle').forEach(button => {
                button.addEventListener('click', function() {
                    const content = this.closest('.aviso-header').nextElementSibling;
                    if (content.classList.contains('active')) {
                        content.querySelectorAll('img[loading="lazy"], video[preload="none"]').forEach(media => {
                            if (media.tagName === 'VIDEO') {
                                media.preload = 'metadata';
                            } else if (media.tagName === 'IMG' && !media.src) {
                                media.src = media.dataset.src;
                            }
                        });
                    }
                });
            });
    
            // Modal para ampliar multimedia
            const modal = document.getElementById('mediaModal');
            const modalImg = document.getElementById('modalImage');
            const modalVideo = document.getElementById('modalVideo');
            const modalCaption = document.getElementById('modalCaption');
            const closeBtn = document.querySelector('.modal-close');
    
            // Función para abrir el modal
            function openModal(src, type, altText) {
                modal.style.display = 'flex';
                modalImg.style.display = 'none';
                modalVideo.style.display = 'none';
                
                if (type === 'image') {
                    modalImg.src = src;
                    modalImg.style.display = 'block';
                    modalCaption.textContent = altText;
                } else if (type === 'video') {
                    modalVideo.src = src;
                    modalVideo.setAttribute('controlsList', 'nodownload');
                    modalVideo.style.display = 'block';
                    modalCaption.textContent = altText;
                    modalVideo.load();
                }
            }
    
            // Cerrar modal al hacer clic en la X
            closeBtn.onclick = function() {
                modal.style.display = 'none';
                if (modalVideo) modalVideo.pause();
            }
    
            // Cerrar modal al hacer clic fuera del contenido
            modal.onclick = function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                    if (modalVideo) modalVideo.pause();
                }
            }
    
            // Asignar eventos a las imágenes y videos
            document.querySelectorAll('.gallery-item img, .gallery-item video').forEach(media => {
                media.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const src = this.tagName === 'IMG' ? this.src : this.querySelector('source').src;
                    const type = this.tagName === 'IMG' ? 'image' : 'video';
                    const altText = this.alt || this.closest('.aviso').querySelector('.aviso-title').textContent;
                    openModal(src, type, altText);
                });
            });
            
            // Bloquear descargas de video
            document.querySelectorAll('video').forEach(video => {
                // Bloquear clic derecho
                video.addEventListener('contextmenu', (e) => {
                    e.preventDefault();
                    return false;
                });
                
                // Bloquear atajos de teclado
                video.addEventListener('keydown', (e) => {
                    if (e.ctrlKey && (e.keyCode === 83 || e.keyCode === 85)) {
                        e.preventDefault();
                        return false;
                    }
                });
            });
        });
    </script>
    
    <!-- Modal para ampliar multimedia -->
    <div id="mediaModal" class="modal">
        <span class="modal-close">&times;</span>
        <div class="modal-content-container">
            <img id="modalImage" class="modal-content" src="">
            <video id="modalVideo" class="modal-content modal-video" controls controlsList="nodownload" oncontextmenu="return false;"></video>
            <div id="modalCaption" class="modal-caption"></div>
        </div>
    </div>
</body>
</html>
