<?php
// Solo iniciar sesión si no está ya activa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'models/Ticket.php';


verificarAutenticacion();

if (!isset($_GET['id'])) {
    header('Location: dashboard_sucursales.php');
    exit();
}

$ticket = new Ticket();
$ticketData = $ticket->getById($_GET['id']);

if (!$ticketData) {
    die("Ticket no encontrado");
}

$fotos = $ticketData['fotos'] ?? [];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?= htmlspecialchars($ticketData['codigo']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Calibri', sans-serif;
        }
        
        .ticket-container {
            max-width: 900px;
            margin: 30px auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        .ticket-header {
            border-bottom: 3px solid #51B8AC;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .ticket-code {
            color: #0E544C;
            font-size: 1.8rem;
            font-weight: bold;
        }
        
        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            display: inline-block;
        }
        
        .status-solicitado {
            background-color: #ffc107;
            color: #000;
        }
        
        .status-clasificado {
            background-color: #17a2b8;
            color: white;
        }
        
        .status-agendado {
            background-color: #007bff;
            color: white;
        }
        
        .status-finalizado {
            background-color: #28a745;
            color: white;
        }
        
        .info-section {
            margin-bottom: 25px;
        }
        
        .info-label {
            font-weight: bold;
            color: #0E544C;
            margin-bottom: 5px;
        }
        
        .info-value {
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 6px;
        }
        
        /* Galería de fotos */
        .photos-section {
            margin-top: 30px;
        }
        
        .photos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .photo-card {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .photo-card:hover {
            transform: scale(1.05);
        }
        
        .photo-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .photo-number {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(81, 184, 172, 0.9);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        /* Modal para vista de foto completa */
        .photo-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
        }
        
        .photo-modal-content {
            position: relative;
            margin: auto;
            padding: 20px;
            width: 90%;
            max-width: 1200px;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .photo-modal img {
            max-width: 100%;
            max-height: 85vh;
            object-fit: contain;
        }
        
        .modal-close {
            position: absolute;
            top: 20px;
            right: 40px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .modal-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(81, 184, 172, 0.8);
            color: white;
            font-size: 30px;
            padding: 15px 20px;
            cursor: pointer;
            border-radius: 5px;
            user-select: none;
        }
        
        .modal-prev {
            left: 20px;
        }
        
        .modal-next {
            right: 20px;
        }
        
        .modal-counter {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(81, 184, 172, 0.9);
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .no-photos {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        @media (max-width: 768px) {
            .photos-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
            
            .photo-card img {
                height: 150px;
            }
            
            .modal-nav {
                font-size: 20px;
                padding: 10px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="ticket-container">
        <div class="ticket-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <div class="ticket-code">
                        <i class="fas fa-ticket-alt me-2"></i>
                        <?= htmlspecialchars($ticketData['codigo']) ?>
                    </div>
                    <span class="status-badge status-<?= $ticketData['status'] ?> mt-2">
                        <?= strtoupper($ticketData['status']) ?>
                    </span>
                </div>
                <button onclick="window.history.back()" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Volver
                </button>
            </div>
        </div>
        
        <div class="info-section">
            <div class="info-label"><i class="fas fa-heading me-2"></i>Título</div>
            <div class="info-value"><?= htmlspecialchars($ticketData['titulo']) ?></div>
        </div>
        
        <div class="info-section">
            <div class="info-label"><i class="fas fa-align-left me-2"></i>Descripción</div>
            <div class="info-value"><?= nl2br(htmlspecialchars($ticketData['descripcion'])) ?></div>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="info-label"><i class="fas fa-store me-2"></i>Sucursal</div>
                <div class="info-value"><?= htmlspecialchars($ticketData['nombre_sucursal']) ?></div>
            </div>
            
            <div class="col-md-6 mb-3">
                <div class="info-label"><i class="fas fa-map-marker-alt me-2"></i>Área/Equipo</div>
                <div class="info-value"><?= htmlspecialchars($ticketData['area_equipo']) ?></div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="info-label"><i class="fas fa-user me-2"></i>Solicitado por</div>
                <div class="info-value"><?= htmlspecialchars($ticketData['nombre_operario']) ?></div>
            </div>
            
            <div class="col-md-6 mb-3">
                <div class="info-label"><i class="fas fa-calendar me-2"></i>Fecha de solicitud</div>
                <div class="info-value"><?= date('d/m/Y H:i', strtotime($ticketData['created_at'])) ?></div>
            </div>
        </div>
        
        <?php if (!empty($fotos)): ?>
        <div class="photos-section">
            <div class="info-label">
                <i class="fas fa-images me-2"></i>Fotografías 
                <span class="badge bg-success"><?= count($fotos) ?></span>
            </div>
            
            <div class="photos-grid">
                <?php foreach ($fotos as $index => $foto): ?>
                <div class="photo-card" onclick="openPhotoModal(<?= $index ?>)">
                    <img src="uploads/tickets/<?= htmlspecialchars($foto['foto']) ?>" alt="Foto <?= $index + 1 ?>">
                    <span class="photo-number"><?= $index + 1 ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="photos-section">
            <div class="info-label"><i class="fas fa-images me-2"></i>Fotografías</div>
            <div class="no-photos">
                <i class="fas fa-image fa-3x mb-3"></i>
                <p>No se adjuntaron fotografías a este ticket</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal para ver fotos en grande -->
    <div id="photoModal" class="photo-modal" onclick="closePhotoModal(event)">
        <div class="photo-modal-content">
            <span class="modal-close" onclick="closePhotoModal(event)">&times;</span>
            <span class="modal-nav modal-prev" onclick="changePhoto(-1, event)">&#10094;</span>
            <img id="modalImage" src="" alt="Foto ampliada">
            <span class="modal-nav modal-next" onclick="changePhoto(1, event)">&#10095;</span>
            <div class="modal-counter" id="modalCounter"></div>
        </div>
    </div>

    <script>
        const fotos = <?= json_encode(array_map(function($f) { return $f['foto']; }, $fotos)) ?>;
        let currentPhotoIndex = 0;
        
        function openPhotoModal(index) {
            currentPhotoIndex = index;
            updateModalPhoto();
            document.getElementById('photoModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closePhotoModal(event) {
            if (event.target.id === 'photoModal' || event.target.className === 'modal-close') {
                document.getElementById('photoModal').style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        
        function changePhoto(direction, event) {
            event.stopPropagation();
            currentPhotoIndex += direction;
            
            if (currentPhotoIndex < 0) {
                currentPhotoIndex = fotos.length - 1;
            } else if (currentPhotoIndex >= fotos.length) {
                currentPhotoIndex = 0;
            }
            
            updateModalPhoto();
        }
        
        function updateModalPhoto() {
            const img = document.getElementById('modalImage');
            img.src = 'uploads/tickets/' + fotos[currentPhotoIndex];
            document.getElementById('modalCounter').textContent = 
                (currentPhotoIndex + 1) + ' / ' + fotos.length;
        }
        
        // Navegación con teclado
        document.addEventListener('keydown', function(event) {
            const modal = document.getElementById('photoModal');
            if (modal.style.display === 'block') {
                if (event.key === 'ArrowLeft') {
                    changePhoto(-1, event);
                } else if (event.key === 'ArrowRight') {
                    changePhoto(1, event);
                } else if (event.key === 'Escape') {
                    closePhotoModal(event);
                }
            }
        });
    </script>
</body>
</html>