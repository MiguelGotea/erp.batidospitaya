<?php
$version = "1.3.30";
require_once '../../../core/auth/auth.php';
require_once '../../../core/layout/header_universal.php';
require_once '../../../core/layout/menu_lateral.php';
require_once '../../../core/permissions/permissions.php';


//******************************Estándar para header******************************
// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
//$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo
//if (!verificarAccesoCargo([11, 13, 16, 22, 26, 28, 42, 26]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
//    header('Location: ../../../index.php');
//    exit();
//}
if (!tienePermiso('avisos_sucursales', 'vista', $cargoOperario)) {
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
    
    // Convertir de UTC a UTC-6 (restar 6 horas)
    $date = new DateTime($fecha, new DateTimeZone('UTC'));
    $date->sub(new DateInterval('PT6H'));
    
    // Formatear fecha: 30-abr-25 12:47 pm
    return $date->format('d').'-'.$meses[$date->format('n')].'-'.$date->format('y').' '.$date->format('h:i a');
}

// Configuración de paginación
$itemsPerPage = 15;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, $currentPage); // Asegurar que no sea menor a 1
$offset = ($currentPage - 1) * $itemsPerPage;

// Obtener filtros
$branchFilter = isset($_GET['branch']) ? (int)$_GET['branch'] : null;
$yearFilter = isset($_GET['year']) ? (int)$_GET['year'] : null;
$monthFilter = isset($_GET['month']) ? (int)$_GET['month'] : null;

// Construir condiciones WHERE
$conditions = [];
$params = [];
$joinCondition = '';

if ($branchFilter) {
    $joinCondition = 'JOIN announcement_branches ab ON a.id = ab.announcement_id';
    $conditions[] = 'ab.branch_id = ?';
    $params[] = $branchFilter;
}

// Aplicar corrección horaria para los filtros de fecha (UTC-6)
if ($yearFilter) {
    $conditions[] = 'YEAR(DATE_SUB(a.created_at, INTERVAL 6 HOUR)) = ?';
    $params[] = $yearFilter;
}

if ($monthFilter) {
    $conditions[] = 'MONTH(DATE_SUB(a.created_at, INTERVAL 6 HOUR)) = ?';
    $params[] = $monthFilter;
}

$whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

try {
    // Primero contar el total
    $countSql = "SELECT COUNT(DISTINCT a.id) FROM announcements a $joinCondition $whereClause";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $totalAvisos = $countStmt->fetchColumn();
    
    // Luego obtener los datos paginados
    $sql = "SELECT a.id, a.title, a.content, a.created_at, u.username as author 
            FROM announcements a 
            LEFT JOIN users u ON a.created_by = u.id
            $joinCondition
            $whereClause
            GROUP BY a.id
            ORDER BY a.created_at DESC
            LIMIT ? OFFSET ?";
    
    $params = array_merge($params, [$itemsPerPage, $offset]);
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $avisos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener total de páginas
    $totalPages = ceil($totalAvisos / $itemsPerPage);

    // Obtener sucursales para cada aviso
    $avisoIds = array_column($avisos, 'id');
    $branchesByAviso = [];
    
    if (!empty($avisoIds)) {
        $placeholders = implode(',', array_fill(0, count($avisoIds), '?'));
        $stmt = $conn->prepare("
            SELECT ab.announcement_id, s.nombre as branch_name 
            FROM announcement_branches ab 
            JOIN sucursales s ON ab.branch_id = s.codigo 
            WHERE ab.announcement_id IN ($placeholders)
        ");
        $stmt->execute($avisoIds);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $branchesByAviso[$row['announcement_id']][] = $row['branch_name'];
        }
    }

    // Obtener todas las sucursales para el filtro
    $stmt = $conn->query("SELECT codigo, nombre FROM sucursales ORDER BY nombre");
    $allBranches = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Avisos Públicos - Batidos Pitaya</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo $version; ?>"> <!-- contiene main, sub container * y body -->
    <link rel="icon" href="../../../core/assets/icon12.png" type="image/png">
    <style>
        /* Estilos para los avisos */
        .aviso {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 15px;
            overflow: hidden;
            text-align: left;
            transition: box-shadow 0.3s ease;
        }
        
        .aviso.active {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .aviso-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            background-color: #f8f8f8;
            cursor: pointer;
        }
        
        .aviso-header > div:first-child {
            flex: 1;
            min-width: 0;
            text-align: center;
        }
        
        .aviso-title {
            font-weight: bold;
            color: #0E544C;
        }

        .aviso-date {
            color: #666;
        }

        .aviso-toggle {
            background: none;
            border: none;
            cursor: pointer;
            color: #51B8AC;
        }
        
        .aviso-toggle i {
            transition: transform 0.3s ease;
        }
        
        .aviso-toggle .fa-chevron-up {
            transform: rotate(180deg);
        }
        
        .aviso-content {
            padding: 0 15px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1), padding 0.3s ease;
            will-change: max-height;
            opacity: 0;
            transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1), 
                        padding 0.3s ease, 
                        opacity 0.3s ease 0.1s;
        }
        
        .aviso-content.active {
            padding: 15px;
            max-height: 2000px;
            transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1), padding 0.3s ease;
            opacity: 1;
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

        /* Estilos para filtros y paginación */
        .filters-container {
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            justify-content: center;
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
            margin-bottom: 10px;
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
            color: #555;
        }

        .branches-list i {
            color: #51B8AC;
            margin-right: 5px;
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
            .aviso-header {
                flex-wrap: nowrap;
            }
            
            .aviso-title {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            
            .aviso-date, 
            .branches-list {
                white-space: nowrap;
            }
            
            .filters-container {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .filter-group select {
                flex-grow: 1;
            }

            .gallery {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }

            .pagination {
                flex-wrap: wrap;
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
        
        /* Estilo para indicar filtros activos */
        .filter-active {
            background-color: #0E544C;
            color: white;
        }
        
        .btn-agregar {
            background-color: #51B8AC;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .btn-agregar:hover {
            background-color: #0E544C;
        }
        
        .buttons-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
            flex-grow: 1;
        }
    </style>
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Anuncios'); ?>
            <!-- Filtros -->
            <div class="filters-container">
                <div class="filter-group">
                    <label for="year-filter">Año:</label>
                    <select id="year-filter" name="year" class="<?= $yearFilter ? 'filter-active' : '' ?>">
                        <option value="">Todos</option>
                        <?php 
                        // Generar opciones de años (últimos 5 años y el actual)
                        $currentYear = date('Y');
                        for ($i = $currentYear; $i >= $currentYear - 5; $i--) {
                            $selected = $yearFilter == $i ? 'selected' : '';
                            echo "<option value='$i' $selected>$i</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="month-filter">Mes:</label>
                    <select id="month-filter" name="month" class="<?= $monthFilter ? 'filter-active' : '' ?>">
                        <option value="">Todos</option>
                        <?php 
                        $months = [
                            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
                        ];
                        
                        foreach ($months as $num => $name) {
                            $selected = $monthFilter == $num ? 'selected' : '';
                            echo "<option value='$num' $selected>$name</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="branch-filter">Sucursal:</label>
                    <select id="branch-filter" name="branch" class="<?= $branchFilter ? 'filter-active' : '' ?>">
                        <option value="">Todas</option>
                        <?php foreach ($allBranches as $branch): ?>
                            <option value="<?= $branch['codigo'] ?>" <?= $branchFilter == $branch['codigo'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($branch['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="button" id="apply-filters" class="btn-agregar">
                    <i class="fas fa-filter"></i> Aplicar Filtros
                </button>
                
                <?php if ($yearFilter || $monthFilter || $branchFilter): ?>
                    <a href="index_avisos_publico.php" class="btn-agregar">
                        <i class="fas fa-times"></i> Limpiar Filtros
                    </a>
                <?php endif; ?>
            </div>
    
            
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
                            <button class="aviso-toggle">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>
                        
                        <div class="aviso-content">
                            <div class="aviso-text">
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
                                                <a href="<?= htmlspecialchars('../../supervision/auditorias_original/' . $doc['file_path']) ?>" target="_blank">
                                                    <?php 
                                                    $icon = 'fa-file';
                                                    if (strpos($doc['file_type'], 'pdf') !== false) $icon = 'fa-file-pdf';
                                                    elseif (strpos($doc['file_type'], 'word') !== false) $icon = 'fa-file-word';
                                                    elseif (strpos($doc['file_type'], 'excel') !== false) $icon = 'fa-file-excel';
                                                    elseif (strpos($doc['file_type'], 'powerpoint') !== false) $icon = 'fa-file-powerpoint';
                                                    elseif (strpos($doc['file_type'], 'zip') !== false || strpos($doc['file_type'], 'rar') !== false) $icon = 'fa-file-archive';
                                                    ?>
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
                                                <?php if (strpos($item['file_type'], 'image') !== false): ?>
                                                    <img src="<?= htmlspecialchars('../../supervision/auditorias_original/' . $item['file_path']) ?>" 
                                                         alt="<?= htmlspecialchars($aviso['title']) ?>"
                                                         loading="lazy">
                                                <?php elseif (strpos($item['file_type'], 'video') !== false): ?>
                                                    <video controls preload="none">
                                                        <source src="<?= htmlspecialchars('../../supervision/auditorias_original/' . $item['file_path']) ?>" 
                                                                type="<?= htmlspecialchars($item['file_type']) ?>">
                                                        Tu navegador no soporta el elemento de video.
                                                    </video>
                                                <?php elseif (strpos($item['file_type'], 'audio') !== false): ?>
                                                    <audio controls>
                                                        <source src="<?= htmlspecialchars('../../supervision/auditorias_original/' . $item['file_path']) ?>" 
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
                <?php if ($totalPages > 1): ?>
                    <div class="pagination" style="margin-bottom:10px;">
                        <?php if ($currentPage > 1): ?>
                            <a href="?page=<?= $currentPage - 1 ?><?= $yearFilter ? '&year='.$yearFilter : '' ?><?= $monthFilter ? '&month='.$monthFilter : '' ?><?= $branchFilter ? '&branch='.$branchFilter : '' ?>">&laquo; Anterior</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == $currentPage): ?>
                                <span class="current"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?page=<?= $i ?><?= $yearFilter ? '&year='.$yearFilter : '' ?><?= $monthFilter ? '&month='.$monthFilter : '' ?><?= $branchFilter ? '&branch='.$branchFilter : '' ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                            <a href="?page=<?= $currentPage + 1 ?><?= $yearFilter ? '&year='.$yearFilter : '' ?><?= $monthFilter ? '&month='.$monthFilter : '' ?><?= $branchFilter ? '&branch='.$branchFilter : '' ?>">Siguiente &raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
            <!-- Modal para ampliar multimedia -->
        <div id="mediaModal" class="modal">
            <span class="modal-close">&times;</span>
            <div class="modal-content-container">
                <img id="modalImage" class="modal-content" src="">
                <video id="modalVideo" class="modal-content modal-video" controls></video>
                <div id="modalCaption" class="modal-caption"></div>
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
                    modalVideo.style.display = 'block';
                    modalCaption.textContent = altText;
                    modalVideo.load();
                }
            }
    
            // Cerrar modal al hacer clic en la X
            closeBtn.onclick = function() {
                modal.style.display = 'none';
                modalVideo.pause();
            }
    
            // Cerrar modal al hacer clic fuera del contenido
            modal.onclick = function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                    modalVideo.pause();
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
            
            // Manejar aplicación de filtros
            document.getElementById('apply-filters').addEventListener('click', function() {
                const year = document.getElementById('year-filter').value;
                const month = document.getElementById('month-filter').value;
                const branch = document.getElementById('branch-filter').value;
                
                let url = 'index_avisos_publico.php?';
                if (year) url += `year=${year}&`;
                if (month) url += `month=${month}&`;
                if (branch) url += `branch=${branch}&`;
                
                // Eliminar el último & si existe
                if (url.endsWith('&')) url = url.slice(0, -1);
                
                window.location.href = url;
            });
        });
    </script>
</body>
</html>