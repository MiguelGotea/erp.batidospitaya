<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

// require_once '../../includes/auth.php';
// require_once '../../includes/funciones.php';
require_once '../../core/auth/auth.php'; // Se centralizó el acceso a auth, db y funciones

//******************************Estándar para header******************************

$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo Líderes (CodNivelesCargos 5), 19 es CDS
verificarAccesoCargo([14, 16]);

if (!verificarAccesoCargo([14, 16]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
$cargoUsuariocodigo = obtenerCargoCodigoPrincipalUsuario($_SESSION['usuario_id']);

// Obtener sucursales del usuario si es líder (código 5) o jefe de CDS (código 19)
$sucursalesUsuario = [];
$codOdooUsuario = null;

if ((verificarAccesoCargo([5]) || verificarAccesoCargo([19])) && !$esAdmin) {
    // Para líderes (código 5)
    if (verificarAccesoCargo([5])) {
        $sucursalesUsuario = obtenerSucursalesLider($_SESSION['usuario_id']);
    }
    // Para jefe de CDS (código 19)
    elseif (verificarAccesoCargo([19])) {
        // Obtener la sucursal CDS (código 6)
        global $conn;
        $stmt = $conn->prepare("SELECT codigo, nombre FROM sucursales WHERE codigo = 6");
        $stmt->execute();
        $sucursalesUsuario = $stmt->fetchAll();
    }
    
    // Si tiene sucursales asignadas, obtener el cod_odoo de la primera
    if (!empty($sucursalesUsuario)) {
        $sucursalPrincipal = $sucursalesUsuario[0]['codigo'];
        
        // Obtener el cod_odoo de la sucursal
        global $conn;
        $stmt = $conn->prepare("SELECT cod_odoo FROM sucursales WHERE codigo = ?");
        $stmt->execute([$sucursalPrincipal]);
        $result = $stmt->fetch();
        $codOdooUsuario = $result['cod_odoo'] ?? null;
    }
}

// --- Configuración Odoo ---
$url = "https://pitaya-mantenimiento.odoo.com/jsonrpc";
$db = "pitaya-mantenimiento";
$username = "miguelgotea.3@gmail.com";
$password = "Nihonk03#";

// --- Función para login ---
function odoo_login($url, $db, $username, $password) {
    $payload = json_encode([
        "jsonrpc" => "2.0",
        "method" => "call",
        "params" => [
            "service" => "common",
            "method" => "login",
            "args" => [$db, $username, $password]
        ],
        "id" => 1
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);

    $res = json_decode($response, true);
    return $res['result'];
}

// --- Función para leer tickets ---
function get_tickets($url, $db, $uid, $password, $codOdooFiltro = null, $soloFinalizadas = false) {
    $fields = ["id","access_token","name","stage_id","create_date","partner_id","close_date","team_id","priority","description"];
    
    // Construir filtro según el cod_odoo
    $filtro = [];
    if ($codOdooFiltro) {
        $filtro = [["partner_id", "=", (int)$codOdooFiltro]];
    }
    
    // Si se pide ver solo finalizadas (stage_id >2)
    if ($soloFinalizadas) {
        $filtro[] = ["stage_id", ">", 2];
    } else {
        // Por defecto, excluir las finalizadas
        $filtro[] = ["stage_id", "<", 3];
    }
    
    $payload = json_encode([
        "jsonrpc"=>"2.0",
        "method"=>"call",
        "params"=>[
            "service"=>"object",
            "method"=>"execute_kw",
            "args"=>[$db,$uid,$password,"helpdesk.ticket","search_read",[$filtro],[
                "fields"=>$fields,
                "order"=>"priority desc, stage_id asc"
            ]]
        ],
        "id"=>2
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);

    $res = json_decode($response, true);
    return $res['result']; 
    
}

// --- Login ---
$uid = odoo_login($url,$db,$username,$password);

// Buscar datos de tipo finalizado o No
$soloFinalizadas = isset($_GET['finalizadas']) && $_GET['finalizadas'] == 1;

// Capturamos el valor de la lista depslegable
$filtroSucursal = isset($_GET['sucursal']) ? (int)$_GET['sucursal'] : null;

// --- Obtener tickets con filtro por cod_odoo del usuario ---
if ($cargoUsuariocodigo == 5 || $cargoUsuariocodigo == 19) {
    // Líder (5) o Jefe de CDS (19) → se filtra solo por su sucursal asignada
    $tickets = get_tickets($url, $db, $uid, $password, $codOdooUsuario, $soloFinalizadas);
} else {
    // Otro cargo → se filtra por lo elegido en el select
    $tickets = get_tickets($url, $db, $uid, $password, $filtroSucursal, $soloFinalizadas);
}

// --- Función para traducir estados ---
function traducir_estado($estado) {
    switch($estado) {
        case 'New': return 'Reportado';
        case 'In Progress': return 'Agendado';
        case 'Solved': return 'Finalizado';
        case 'Cancelled': return 'Cancelado';
        default: return $estado;
    }
}

// --- Función para separar description en "Descripción" y "Otra información" ---
function separar_descripciones($html) {
    $desc = '';
    $otra = '';
    if ($html) {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'.$html);
        libxml_clear_errors();

        $h4s = $doc->getElementsByTagName('h4');

        if ($h4s->length == 0) {
            // No hay h4, todo el contenido va a descripción
            $body = $doc->getElementsByTagName('body')->item(0);
            $desc = trim(strip_tags($doc->saveHTML($body)));
        } else {
            foreach ($h4s as $h4) {
                $title = trim($h4->textContent);
                $next = $h4->nextSibling;
                $contenido = '';
                while ($next && $next->nodeName != 'h4') {
                    $contenido .= $doc->saveHTML($next);
                    $next = $next->nextSibling;
                }
                $contenido = trim(strip_tags($contenido));
                if (strcasecmp($title,'Descripción')==0) {
                    $desc = $contenido ?: '';
                }
                if (strcasecmp($title,'Otra información')==0) {
                    // Si hay ":", tomar solo lo que está después
                    $pos = strpos($contenido, ':');
                    if ($pos !== false) {
                        $otra = trim(substr($contenido, $pos + 1));
                    } else {
                        $otra = $contenido ?: '';
                    }
                }
            }
        }
    }
    return [$desc,$otra];
}

// Obtener todas las sucursales para mostrar nombres
global $conn;
$stmt = $conn->prepare("SELECT cod_odoo, nombre FROM sucursales WHERE cod_odoo IS NOT NULL");
$stmt->execute();
$sucursalesOdoo = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitudes de Mantenimiento y Equipos</title>
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
            padding: 5px;
        }
        
        .container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 10px;
        }
        
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding: 0 5px;
            box-sizing: border-box;
            margin: 1px auto;
            flex-wrap: wrap;
        }

        .logo {
            height: 50px;
        }

        .logo-container {
            flex-shrink: 0;
            margin-right: auto;
        }

        .buttons-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            flex-grow: 1;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }

        .btn-agregar {
            background-color: transparent;
            color: #51B8AC;
            border: 1px solid #51B8AC;
            text-decoration: none;
            padding: 6px 10px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            white-space: nowrap;
            font-size: 14px;
            flex-shrink: 0;
        }

        .btn-agregar.activo {
            background-color: #51B8AC;
            color: white;
            font-weight: normal;
        }

        .btn-agregar:hover {
            background-color: #0E544C;
            color: white;
            border-color: #0E544C;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
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
        
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .sucursal-info {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        /* NUEVOS ESTILOS PARA LA TABLA */
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .tickets-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        
        .tickets-table th, 
        .tickets-table td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            vertical-align: middle;
        }
        
        .tickets-table th {
            background-color: #0E544C;
            color: white;
            font-weight: bold;
            position: sticky;
            top: 0;
        }
        
        .tickets-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .tickets-table tr:hover {
            background-color: rgba(81, 184, 172, 0.1) !important;
            transition: background-color 0.3s ease;
        }
        
        .tickets-table .description {
            max-width: 300px;
            word-wrap: break-word;
        }
        
        /* Estilos para estados */
        .estado-reportado {
            background-color: #e7f3ff;
            color: #004085;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
            font-weight: bold;
        }
        
        .estado-agendado {
            background-color: #fff3cd;
            color: #856404;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
            font-weight: bold;
        }
        
        .estado-finalizado {
            background-color: #d4edda;
            color: #155724;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
            font-weight: bold;
        }
        
        .estado-cancelado {
            background-color: #f8d7da;
            color: #721c24;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
            font-weight: bold;
        }
        
        .filtro-info {
            background-color: #e7f3ff;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            border-left: 4px solid #51B8AC;
        }
        
        /* Estilos generales para selects */
        select, input, button {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        /* Estilo uniforme para selects */
        .form-select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #fff;
            font-size: 14px;
            cursor: pointer;
        }
        
        .form-select:focus {
            outline: none;
            border-color: #51B8AC;
            box-shadow: 0 0 4px rgba(81, 184, 172, 0.6);
        }
        
        .acciones {
            text-align: right !important;
        }
        
        .btn-ver {
            background-color: #51B8AC;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }
        
        .btn-ver:hover {
            background-color: #0E544C;
            text-decoration: none;
        }
        
        @media (max-width: 768px) {
            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                width: 100%;
            }
            
            .tickets-table {
                width: auto;
                min-width: 100%;
            }
            
            .tickets-table th, 
            .tickets-table td {
                padding: 8px 5px;
                font-size: 12px;
            }
            
            .btn-ver {
                padding: 4px 6px;
                font-size: 12px;
            }
        }

    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-container">
                <div class="logo-container">
                    <img src="../../assets/img/Logo.svg" alt="Batidos Pitaya" class="logo">
                </div>
                
                <div class="buttons-container">
                    <?php if ($esAdmin || verificarAccesoCargo([5, 11, 16, 19, 21])): ?>
                        <a href="pruebaodoo.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'pruebaodoo.php' && (!isset($_GET['finalizadas']) || $_GET['finalizadas'] != 1) ? 'activo' : '' ?>">
                            <i class="fas fa-sticky-note"></i> <span class="btn-text">Solicitudes Pendientes</span>
                        </a>
                    <?php endif; ?>
                    <?php if ($esAdmin || verificarAccesoCargo([5, 16, 19])): ?>
                        <a href="pruebaodoo_mantenimiento.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'pruebaodoo_mantenimiento.php' ? 'activo' : '' ?>">
                            <i class="fas fa-tools"></i> <span class="btn-text">Mantenimiento</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($esAdmin || verificarAccesoCargo([5, 16, 19])): ?>
                        <a href="pruebaodoo_mobiliario.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'pruebaodoo_mobiliario.php' ? 'activo' : '' ?>">
                            <i class="fas fa-desktop"></i> <span class="btn-text">Equipos</span>
                        </a>
                    <?php endif; ?>
                    <?php if ($esAdmin || verificarAccesoCargo([5, 11, 16, 19, 21])): ?>
                        <a href="pruebaodoo.php?finalizadas=1" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'pruebaodoo.php' && isset($_GET['finalizadas']) && $_GET['finalizadas'] == 1 ? 'activo' : '' ?>">
                            <i class="fas fa-check-circle"></i> <span class="btn-text">Solicitudes Finalizadas</span>
                        </a>
                    <?php endif; ?>
                </div>
                
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
                            <?= htmlspecialchars($cargoUsuario) ?>
                        </small>
                    </div>
                    <a href="index.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </header>
        
        <?php if (isset($_SESSION['exito'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['exito'] ?>
                <?php unset($_SESSION['exito']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?= $_SESSION['error'] ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($sucursalesUsuario) && (verificarAccesoCargo([5]) || verificarAccesoCargo([19]))): ?>
            <div style="text-align:center;" class="sucursal-info">
                Sucursal: <?= htmlspecialchars($sucursalesUsuario[0]['nombre']) ?> 
                <p style="display:none;">(Código: <?= $sucursalesUsuario[0]['codigo'] ?>)</p>
            </div>
        <?php endif; ?>
        
        <?php if ($codOdooUsuario): ?>
            <div style="display:none;" class="filtro-info">
                <strong>Filtro aplicado:</strong> Mostrando tickets solo para la sucursal Odoo ID: <?= $codOdooUsuario ?>
                <?php if (isset($sucursalesOdoo[$codOdooUsuario])): ?>
                    (<?= htmlspecialchars($sucursalesOdoo[$codOdooUsuario]) ?>)
                <?php endif; ?>
            </div>
        <?php elseif ($esAdmin || verificarAccesoCargo([16])): ?>
            <div class="filtro-info">
                <strong>Vista administrativa:</strong> Mostrando todos los tickets (sin filtro por sucursal)
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                No se pudo determinar la sucursal Odoo para filtrar los tickets.
            </div>
        <?php endif; ?>

        <h2 style="display:none;">Solicitud de Mantenimiento y Equipos</h2>


        <?php if ($cargoUsuariocodigo != 5 && $cargoUsuariocodigo != 19): ?>
            <form method="GET" style="margin-bottom:15px; text-align:center;">
                <label for="sucursal">Filtrar por Sucursal: </label>
     
                <select name="sucursal" id="sucursal" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Todas --</option>
                    <?php foreach($sucursalesOdoo as $cod => $nombre): ?>
                        <option value="<?= $cod ?>" <?= ($filtroSucursal == $cod) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($nombre) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php if ($soloFinalizadas): ?>
                    <input type="hidden" name="finalizadas" value="1">
                <?php endif; ?>
            </form>
        <?php endif; ?>

        <div class="table-container">
            <table class="tickets-table">
                <thead>
                    <tr>
                        <th>Solicitud</th>
                        <th style="display:none;">Token</th>
                        <th>Requerimiento</th>
                        <th style="display:none;">Sucursal</th>
                        <th style="display:none;">Sucursal OdooID</th>
                        <th style="display:none;">Tipo</th>
                        <th style="text-align:center;">Urgencia</th>
                        <th>Estado</th>
                        <th>Fecha Entrega</th>
                        <th>Detalle</th>
                        <th style="text-align:center;">Activ</th>
                        <th class="acciones"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($tickets)): ?>
                        <?php foreach($tickets as $t): 
                            list($descripcion,$otra_info) = separar_descripciones($t['description'] ?? '');
                            $sucursalNombre = '';
                            if ($t['partner_id'] && isset($sucursalesOdoo[$t['partner_id'][0]])) {
                                $sucursalNombre = $sucursalesOdoo[$t['partner_id'][0]];
                            }
                            
                            // Determinar clase CSS según el estado
                            $estado_clase = '';
                            $estado_texto = traducir_estado($t['stage_id'][1]);
                            switch($estado_texto) {
                                case 'Reportado': $estado_clase = 'estado-reportado'; break;
                                case 'Agendado': $estado_clase = 'estado-agendado'; break;
                                case 'Finalizado': $estado_clase = 'estado-finalizado'; break;
                                case 'Cancelado': $estado_clase = 'estado-cancelado'; break;
                                default: $estado_clase = '';
                            }
                        ?>
                        <tr>
                            <td><?= $t['id'] ?></td>
                            <td style="display:none;"><?= $t['access_token'] ?></td>
                            <td><?= htmlspecialchars($t['name']) ?></td>
                            <td style="display:none;"><?= $t['partner_id'] ? htmlspecialchars($sucursalNombre ?: $t['partner_id'][1]) : 'No definido' ?></td>
                            <td style="display:none;"><?= $t['partner_id'] ? $t['partner_id'][0] : '-' ?></td>
                            <td style="display:none;"><?= $t['team_id'] ? htmlspecialchars($t['team_id'][1])." (".$t['team_id'][0].")" : '-' ?></td>
                            <td style="text-align:center;">
                                <?php 
                                    $priority = $t['priority'] ?? null;
                                    if ($priority == 1) {
                                        echo '<i class="fas fa-circle" style="color:green;" title="Baja"></i>';
                                    } elseif ($priority == 2) {
                                        echo '<i class="fas fa-circle" style="color:orange;" title="Normal"></i>';
                                    } elseif ($priority == 3) {
                                        echo '<i class="fas fa-circle" style="color:red;" title="Alta"></i>';
                                    } else {
                                        echo '';
                                    }
                                ?>
                            </td>
                           <td><span class="<?= $estado_clase ?>"><?= $estado_texto ?></span></td>
                            <td>
                                <?php 
                                    if (!empty($t['close_date'])) {
                                        $fechax = new DateTime($t['close_date']);
                                        echo $fechax->format("d-M-Y"); // Ejemplo: 02-Sep-2025
                                    } else {
                                        echo "";
                                    }
                                ?>
                            </td>
                            <td class="description"><?= htmlspecialchars($descripcion) ?></td>
                            <td  style="text-align:center;" class="description"><?= htmlspecialchars($otra_info) ?></td>
                            <td class="acciones">
                                <?php if (!empty($t['access_token'])): ?>
                                    <a href="https://pitaya-mantenimiento.odoo.com/my/ticket/<?= $t['id'] ?>?access_token=<?= $t['access_token'] ?>" 
                                       target="_blank" 
                                       class="btn-ver" 
                                       title="Ver Solicitud">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12" style="text-align: center; padding: 20px;">
                                No se encontraron tickets <?= $codOdooUsuario ? 'para esta sucursal' : '' ?>.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>