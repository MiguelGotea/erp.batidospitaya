<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

// Verificar acceso al módulo
verificarAccesoCargo([27]);

$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Definir las playlists segmentadas
$playlists = [
    '1' => [
        'id' => '35zSIM3xgGJKkPBrvKETdb',
        'nombre' => 'Volumen 1 (Canciones 1-100)',
        'descripcion' => 'Primera parte de la playlist oficial'
    ],
    '2' => [
        'id' => 'TU_SEGUNDA_PLAYLIST_ID_AQUI',
        'nombre' => 'Volumen 2 (Canciones 101-200)',
        'descripcion' => 'Segunda parte de la playlist oficial'
    ],
    '3' => [
        'id' => 'TU_TERCERA_PLAYLIST_ID_AQUI',
        'nombre' => 'Volumen 3 (Canciones 201-300)',
        'descripcion' => 'Tercera parte de la playlist oficial'
    ],
    '4' => [
        'id' => 'TU_CUARTA_PLAYLIST_ID_AQUI',
        'nombre' => 'Volumen 4 (Canciones 301-400+)',
        'descripcion' => 'Cuarta parte de la playlist oficial'
    ],
    '5' => [
        'id' => 'TU_QUINTA_PLAYLIST_ID_AQUI',
        'nombre' => 'Volumen 5 (Canciones 401-500+)',
        'descripcion' => 'Quinta parte de la playlist oficial'
    ]
];

// Playlist por defecto
$playlistActual = $_GET['playlist'] ?? '1';
$playlistData = $playlists[$playlistActual] ?? $playlists['1'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Música para Sucursales - Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Calibri', sans-serif;
        }
        
        body {
            background-color: #F6F6F6;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
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
        
        .btn-back {
            background: #51B8AC;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
        }
        
        .btn-back:hover {
            background: #0E544C;
        }
        
        .page-title {
            color: #51B8AC;
            font-size: 2rem;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .page-subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }
        
        .spotify-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .spotify-player {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            margin-bottom: 20px;
        }
        
        .playlist-selector {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            justify-content: center;
        }
        
        .playlist-btn {
            background: #f8f9fa;
            border: 2px solid #51B8AC;
            color: #51B8AC;
            padding: 10px 15px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            font-weight: bold;
        }
        
        .playlist-btn:hover {
            background: #51B8AC;
            color: white;
        }
        
        .playlist-btn.active {
            background: #51B8AC;
            color: white;
        }
        
        .current-playlist-info {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .current-playlist-info h3 {
            color: #51B8AC;
            margin-bottom: 5px;
        }
        
        .instructions {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .instructions h3 {
            color: #51B8AC;
            margin-bottom: 15px;
        }
        
        .instructions ul {
            list-style-type: none;
            padding-left: 0;
        }
        
        .instructions li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .instructions li:last-child {
            border-bottom: none;
        }
        
        .instructions i {
            color: #51B8AC;
            width: 20px;
        }
        
        .spotify-limitation {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
            color: #856404;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
            
            .spotify-container {
                padding: 15px;
            }
            
            .playlist-selector {
                flex-direction: column;
                align-items: center;
            }
            
            .playlist-btn {
                width: 100%;
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
                <a href="../../index.php" class="btn-back">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </header>
        
        <h1 class="page-title" style="display:none;">🎵 Música para Sucursales</h1>
        <p class="page-subtitle" style="display:none;">Playlist oficial de Batidos Pitaya - Ambiente musical para todas las sucursales</p>
        
        <div class="spotify-container">
            <!-- Selector de Playlists -->
            <div class="playlist-selector" style="display:none;">
                <?php foreach ($playlists as $key => $playlist): ?>
                    <a href="?playlist=<?= $key ?>" 
                       class="playlist-btn <?= $playlistActual == $key ? 'active' : '' ?>">
                        <?= $playlist['nombre'] ?>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <!-- Información de la playlist actual -->
            <div class="current-playlist-info" style="display:none;">
                <h3><?= $playlistData['nombre'] ?></h3>
                <p><?= $playlistData['descripcion'] ?></p>
            </div>
            
            <!-- Aviso de limitación -->
            <div class="spotify-limitation" style="display:none;">
                <i class="fas fa-info-circle"></i>
                <strong>Nota:</strong> Por limitaciones técnicas de Spotify, cada playlist muestra máximo 100 canciones. 
                Para acceder a todas las canciones, usa los diferentes volúmenes disponibles.
            </div>
            
            <!-- Player de Spotify -->
            <div class="spotify-player">
                <iframe style="border-radius:12px" 
                    src="https://open.spotify.com/embed/playlist/35zSIM3xgGJKkPBrvKETdb?utm_source=generator" 
                    width="100%" 
                    height="380" 
                    frameBorder="0" 
                    allowfullscreen="" 
                    allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture">
            </iframe>
            </div>
            
            <div class="instructions">
                <h3><i class="fas fa-info-circle"></i> Instrucciones de Uso</h3>
                <ul>
                    <li>
                        <i class="fas fa-volume-up"></i>
                        <strong>Reproducción:</strong> Haz clic en el botón de reproducción para iniciar la música
                    </li>
                    <li>
                        <i class="fas fa-music"></i>
                        <strong>Playlist oficial:</strong> Esta es la única playlist autorizada para uso en sucursales
                    </li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>