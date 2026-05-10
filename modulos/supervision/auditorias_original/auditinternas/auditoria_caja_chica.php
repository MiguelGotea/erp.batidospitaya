<?php
// Incluir configuración y verificar autenticación
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
// Antes llamaba a ../funciones.php de auditora
// require_once 'config.php'; // Comentado por migración al core

// Conexión a la base de datos usando la función de config.php
// $conn = conectarDB(); // Comentado por migración al core (ya viene de conexion.php a través de auth.php)

//******************************Estándar para header******************************

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([16, 21]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([16, 21])) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Procesar formulario (modificado)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar campos de texto
        $error_message = '';
        
        // Validar líder (solo letras y espacios)
        $lider = $_POST['lider'];
        if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $lider)) {
            $error_message = "El campo Líder de Tienda solo puede contener letras y espacios.";
        }
        
        // Validar que se haya subido una foto
        if (!isset($_POST['foto_auditoria']) || empty($_POST['foto_auditoria'])) {
            $error_message = "Debe tomar una foto de la auditoría.";
        }
        
        // Si hay errores de validación, mostrarlos
        if (!empty($error_message)) {
            echo '<script>
            Swal.fire({
                title: "Error de validación",
                text: "'.str_replace("'", "\\'", $error_message).'",
                icon: "error",
                confirmButtonColor: "#0E544C"
            });
            </script>';
        } else {
            $conn->beginTransaction();
            
            // Procesar la foto
            $foto_dir = 'fotos_auditorias_caja_chica/';
            if (!file_exists($foto_dir)) {
                mkdir($foto_dir, 0777, true);
            }
            
            $foto_name = uniqid() . '.png';
            $foto_path = $foto_dir . $foto_name;
            
            $fotoData = str_replace('data:image/png;base64,', '', $_POST['foto_auditoria']);
            $fotoData = str_replace(' ', '+', $fotoData);
            
            $fotoDecodificada = base64_decode($fotoData);
            
            if (!file_put_contents($foto_path, $fotoDecodificada)) {
                throw new Exception("Error al guardar la foto.");
            }
            
            $sucursalId = $_POST['tienda'];
            $stmtSucursal = $conn->prepare("SELECT codigo, nombre FROM sucursales WHERE codigo = ?");
            $stmtSucursal->execute([$sucursalId]);
            $sucursal = $stmtSucursal->fetch(PDO::FETCH_ASSOC);
            
            // Obtener el código del líder desde el campo oculto
            $liderCodigo = $_POST['lider_codigo'] ?? null;
            
            $montoDesignado = $_POST['monto_designado'];
            $totalConteo = $_POST['total_conteo'];
            $diferencia = ($totalConteo < $montoDesignado) ? ($totalConteo - $montoDesignado) : 0;
            
            // CONSULTA DIRECTA PARA OBTENER EL CÓDIGO DE CONTRATO DEL LÍDER
            $cod_contrato_lider = null;
            if (!empty($liderCodigo)) {
                $stmt_contrato = $conn->prepare("
                    SELECT CodContrato 
                    FROM Contratos 
                    WHERE cod_operario = ? 
                    ORDER BY inicio_contrato DESC, CodContrato DESC 
                    LIMIT 1
                ");
            
                if ($stmt_contrato) {
                    $stmt_contrato->execute([$liderCodigo]);
                    $result_contrato = $stmt_contrato->fetch(PDO::FETCH_ASSOC);
                    
                    if ($result_contrato && !empty($result_contrato['CodContrato'])) {
                        $cod_contrato_lider = $result_contrato['CodContrato'];
                        error_log("Contrato encontrado para líder $liderCodigo: $cod_contrato_lider");
                    } else {
                        error_log("No se encontró contrato en consulta directa para líder: $liderCodigo");
                    }
                } else {
                    error_log("Error preparando consulta de contrato para líder: " . $conn->error);
                }
            }
            
            // Insertar datos principales
            $stmt = $conn->prepare("INSERT INTO auditoria_caja_chica 
                          (fecha_hora, sucursal_id, sucursal, lider_tienda, lider_tienda_codigo, monto_designado, total_conteo, faltante_sobrante, comentarios, foto_path, usuario_id, cod_contrato) 
                          VALUES (NOW(), :sucursal_id, :sucursal, :lider, :lider_codigo, :monto, :total, :diferencia, :comentarios, :foto_path, :usuario_id, :cod_contrato)");
            
            $stmt->execute([
                ':sucursal_id' => $sucursal['codigo'],
                ':sucursal' => $sucursal['nombre'],
                ':lider' => $lider,
                ':lider_codigo' => $liderCodigo,
                ':monto' => $montoDesignado,
                ':total' => $totalConteo,
                ':diferencia' => $diferencia,
                ':comentarios' => $_POST['comentarios'],
                ':foto_path' => $foto_path,
                ':usuario_id' => $_SESSION['usuario_id'],
                ':cod_contrato' => $cod_contrato_lider
            ]);
            
            $conn->commit();
            
            // Limpiar el búfer de salida antes de redireccionar
            ob_end_clean();
            header("Location: auditorias_consolidadas.php");
            exit();
        }
        
    } catch(PDOException $e) {
        $conn->rollBack();
        echo '<script>
        Swal.fire({
            title: "Error",
            text: "Ocurrió un error al guardar la auditoría: '.str_replace("'", "\\'", $e->getMessage()).'",
            icon: "error",
            confirmButtonColor: "#0E544C"
        });
        </script>';
        error_log("Error en auditoria_caja_chica.php: " . $e->getMessage());
    } catch(Exception $e) {
        $conn->rollBack();
        echo '<script>
        Swal.fire({
            title: "Error",
            text: "'.str_replace("'", "\\'", $e->getMessage()).'",
            icon: "error",
            confirmButtonColor: "#0E544C"
        });
        </script>';
        error_log("Error en auditoria_caja_chica.php: " . $e->getMessage());
    }
}

// Obtener sucursales y montos designados (se mantiene igual), excluimos la 0 central, 14 ferias, 16 las bisas, 17 rivas
$stmtSucursales = $conn->query("SELECT s.codigo as id, s.nombre as name, cs.monto_designado 
                               FROM sucursales s
                               LEFT JOIN caja_chica_sucursales cs ON s.codigo = cs.sucursal_id AND cs.activo = 1
                               WHERE s.activa = 1 AND s.codigo NOT IN (0, 14)
                               ORDER BY s.nombre");
$sucursales = $stmtSucursales->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Auditoría de Caja Chica</title>
    <link rel="icon" href="icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --color-primario: #0E544C;
            --color-secundario: #51B8AC;
            --color-fondo: #F6F6F6;
            --color-texto: #333;
            --color-borde: #ddd;
            --color-error: #dc3545;
            --color-exito: #28a745;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Calibri', Arial, sans-serif;
            font-size: clamp(11px, 2vw, 16px) !important;
        }
        
        html, body {
            overflow-x: hidden;
            width: 100%;
            margin: 0;
            padding: 0;
            background-color: var(--color-fondo);
        }
        
        .container {
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
            padding: 15px;
            background-color: white;
            min-height: 100vh;
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
        
        .header-container img {
            height: 50px;
            margin-right: 15px;
        }
        
        .header-container h1 {
            color: #000;
            margin: 0;
        }
        
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
            position: relative;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: var(--color-primario);
        }
        
        input[type="text"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--color-borde);
            border-radius: 4px;
            max-width: 100%;
        }
        
        select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 1em;
        }
        
        input[readonly] {
            background-color: #f8f9fa;
        }
        
        .error-input {
            border: 1px solid var(--color-error) !important;
        }
        
        .totals {
            margin-bottom: 20px;
        }
        
        .totals table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .totals th, .totals td {
            border: 1px solid var(--color-borde);
            padding: 10px;
        }
        
        .totals tr:first-child td {
            background-color: transparent;
        }
        
        .totals tr:last-child td {
            background-color: #fff8e1;
        }
        
        .faltante {
            color: red;
            font-weight: normal;
        }
        
        .diferencia-negativa {
            color: red;
            font-weight: bold;
        }
        
        .diferencia-cero {
            color: inherit;
            font-weight: bold;
        }
        
        #total_conteo, #faltante {
            text-align: right;
        }
        
        .btn {
        display: inline-block;
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        font-weight: bold;
        cursor: pointer;
        transition: background-color 0.3s;
        text-align: center;
    }
    
    .btn-special {
        background-color: #0E544C;
        color: white;
    }
    
    .btn-reset {
        background-color: #6c757d;
        color: white;
    }
    
    .btn-camera {
        background-color: #0E544C;
        color: white;
    }
    
    .btn-camera-reset {
        background-color: #dc3545;
        color: white;
    }
    
    .btn:hover {
        opacity: 0.9;
    }
    
    .btn-container {
        display: flex;
        gap: 10px;
        margin-top: 25px;
        flex-wrap: wrap;
        justify-content: center;
    }
        
        /* Estilos para la sección de cámara (se mantienen igual) */
        .camera-section {
            margin-bottom: 20px;
        }
        
        .camera-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        
        #video {
            width: 100%;
            max-width: 320px;
            background-color: #000;
        }
        
        #canvas {
            display: none;
        }
        
        #foto_capturada {
            max-width: 100%;
            max-height: 200px;
            display: none;
            margin-top: 10px;
        }
        
        .camera-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            width: 100%;
            justify-content: center;
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
            
            .container {
                padding: 10px;
            }
            
            .header-container img {
                height: 40px;
                margin-right: 10px;
            }
            
            .btn {
                padding: 8px 15px;
            }
            
            .btn-container {
                flex-direction: row;
                align-items: center;
            }
            
            .btn, .btn-reset {
                width: 100%;
            }
        }
        
        @media (max-width: 480px) {
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
            
            .btn-container {
                flex-direction: row;
            }
            
            .btn-container .btn {
                flex: 1;
                min-width: 120px;
            }
            
            .header-container {
                flex-direction: column;
                text-align: center;
            }
            
            .header-container img {
                margin-right: 0;
                margin-bottom: 10px;
                height: 35px;
            }
            
            .card {
                padding: 15px;
                width: 100%;
                box-sizing: border-box;
            }
            
            select {
                width: 100%;
                max-width: 100%;
                height: 44px;
            }
            
            html, body {
                overflow-x: hidden;
                width: 100%;
                position: relative;
            }
            
            .camera-buttons button {
                width: auto;
                flex: 1;
                min-width: 120px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-container">
                <div class="logo-container">
                    <img src="../Logo.svg" alt="Batidos Pitaya" class="logo">
                </div>
                
                <div class="buttons-container">
                    <a href="auditorias_consolidadas.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'auditorias_consolidadas.php' ? 'activo' : '' ?>">
                        <i class="fas fa-money-bill-wave"></i> <span class="btn-text">Historial</span>
                    </a>
                    
                    <?php if (verificarAccesoCargo([16])): ?>
                        <a href="auditoria_caja_facturacion.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'auditoria_caja_facturacion.php' ? 'activo' : '' ?>"><i class="fas fa-cash-register"></i> Auditoría Caja Facturación</a>
                        <a href="auditoria_caja_chica.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'auditoria_caja_chica.php' ? 'activo' : '' ?>"><i class="fas fa-wallet"></i> Auditoría Caja Chica</a>
                        <a href="auditoria_inventario.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'auditoria_inventario.php' ? 'activo' : '' ?>"><i class="fas fa-boxes"></i> Auditoría Inventario</a>
                        <a href="faltante_inventario.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'faltante_inventario.php' ? 'activo' : '' ?>"><i class="fas fa-exclamation-triangle"></i> Faltante Inventario</a>
                        <a href="faltante_danos.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'faltante_danos.php' ? 'activo' : '' ?>"><i class="fas fa-times-circle"></i> Faltante Daños</a>
                    <?php endif; ?>
                </div>
                
                <div class="user-info">
                    <div class="user-avatar">
                        <?= false ? 
                            strtoupper(substr($usuario['nombre'], 0, 1)) : 
                            strtoupper(substr($usuario['Nombre'], 0, 1)) ?>
                    </div>
                    <div>
                        <div>
                            <?= false ? 
                                htmlspecialchars($usuario['nombre']) : 
                                htmlspecialchars($usuario['Nombre'].' '.$usuario['Apellido']) ?>
                        </div>
                        <small>
                            <?= htmlspecialchars($cargoUsuario) ?>
                        </small>
                    </div>
                    <a href="auditorias_consolidadas.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </header>
        
        <h1 style="text-align:center;">Auditoría Caja Chica</h1>
        
        <div class="card">
            <form id="form-auditoria" method="post">
                <div class="section">
                    <div class="form-group">
                        <label for="fecha_hora">Fecha:</label>
                        <input type="text" id="fecha_hora" value="<?= formatFechaEspanol() ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="tienda">Tienda:</label>
                        <select id="tienda" name="tienda" required style="width:100%;">
                            <option value="">Seleccione una sucursal</option>
                            <?php foreach ($sucursales as $sucursal): ?>
                            <option value="<?= $sucursal['id'] ?>" 
                                    data-monto="<?= number_format($sucursal['monto_designado'] ?? 0, 0, '', '') ?>">
                                <?= htmlspecialchars($sucursal['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="lider">Líder de la Tienda:</label>
                        <input type="text" id="lider" name="lider" required class="texto-validado" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="monto_designado_display">Monto de Caja Chica Designado:</label>
                        <input type="text" id="monto_designado_display" readonly>
                        <input type="hidden" id="monto_designado" name="monto_designado">
                    </div>
                    
                    <div class="form-group">
                        <label for="total_conteo_input">Total Conteo (C$):</label>
                        <input type="number" id="total_conteo_input" name="total_conteo" min="0" step="0.01" required onchange="calcularDiferencia()">
                    </div>
                </div>
                
                <div class="section">
                    <div class="totals">
                        <table>
                            <tr>
                                <td><strong>Monto Designado:</strong></td>
                                <td style="text-align:right;" id="monto_designado_display_table">C$ 0.00</td>
                            </tr>
                            <tr>
                                <td><strong>Total Conteo:</strong></td>
                                <td id="total_conteo">C$ 0.00</td>
                            </tr>
                            <tr>
                                <td><strong>Diferencia:</strong></td>
                                <td id="faltante">C$ 0.00</td>
                            </tr>
                        </table>
                    </div>
                    
                    <input type="hidden" id="faltante_hidden" name="faltante">
                </div>
                
                <div class="section">
                    <h2>Observaciones</h2>
                    <div class="form-group">
                        <textarea id="comentarios" name="comentarios" rows="4" placeholder="Ingrese cualquier observación o comentario sobre la auditoría" required></textarea>
                    </div>
                </div>
                
                <!-- Sección de cámara (se mantiene igual) -->
                <div class="form-group camera-section">
                    <label>Foto de Formato Físico <a style="font-weight:bold; color:red;">*</a></label>
                    <div class="camera-container">
                        <select id="selectorCamara">
                            <option value="">Seleccionar cámara...</option>
                        </select>
                        <video id="video" width="320" height="240" autoplay></video>
                        <canvas id="canvas" width="320" height="240"></canvas>
                        <img id="foto_capturada" alt="Foto capturada">
                        <div class="camera-buttons">
                            <button type="button" id="capturarBtn" class="btn btn-special">Capturar Foto</button>
                            <button type="button" id="reiniciarBtn" class="btn btn-reset">Volver a Tomar</button>
                        </div>
                    </div>
                    <input type="hidden" id="foto_auditoria" name="foto_auditoria">
                </div>
                
                <div class="btn-container">
                    <button type="submit" class="btn btn-special" id="guardarBtn" disabled>Guardar Auditoría</button>
                    <button type="button" class="btn btn-reset" onclick="window.location.href='auditorias_consolidadas.php'">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function validarSoloTexto(texto) {
            return /^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/.test(texto);
        }
        
        function validarCamposTexto() {
            let valido = true;
            const camposTexto = document.querySelectorAll('.texto-validado');
            
            camposTexto.forEach(campo => {
                if (!validarSoloTexto(campo.value)) {
                    campo.classList.add('error-input');
                    valido = false;
                } else {
                    campo.classList.remove('error-input');
                }
            });
            
            return valido;
        }
        
        function calcularDiferencia() {
            const montoDesignado = parseFloat(document.getElementById('monto_designado').value) || 0;
            const totalConteo = parseFloat(document.getElementById('total_conteo_input').value) || 0;
            
            // Actualizar el display del total conteo
            document.getElementById('total_conteo').textContent = 'C$ ' + totalConteo.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            
            // Calcular diferencia (solo muestra cuando el conteo es menor al monto designado)
            const diferencia = (totalConteo < montoDesignado) ? (totalConteo - montoDesignado) : 0;
            document.getElementById('faltante_hidden').value = diferencia;
            
            const diferenciaElement = document.getElementById('faltante');
            
            if (diferencia < 0) {
                // Mostrar valor absoluto (sin signo negativo) pero mantener el estilo de error
                diferenciaElement.textContent = 'C$ ' + Math.abs(diferencia).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                diferenciaElement.className = 'diferencia-negativa';
            } else {
                diferenciaElement.textContent = 'C$ 0.00';
                diferenciaElement.className = 'diferencia-cero';
            }
        }
        
        function actualizarMontoDesignado() {
            const selectTienda = document.getElementById('tienda');
            const montoDesignadoDisplay = document.getElementById('monto_designado_display');
            const montoDesignadoTable = document.getElementById('monto_designado_display_table');
            const montoDesignadoHidden = document.getElementById('monto_designado');
            const selectedOption = selectTienda.options[selectTienda.selectedIndex];
            
            if (selectedOption && selectedOption.value) {
                const montoBruto = parseFloat(selectedOption.getAttribute('data-monto')) || 0;
                montoDesignadoHidden.value = montoBruto;
                
                const montoFormateado = 'C$ ' + montoBruto.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                
                montoDesignadoDisplay.value = montoFormateado;
                montoDesignadoTable.textContent = montoFormateado;
                calcularDiferencia();
            }
        }
        
        function confirmarGuardado(event) {
            event.preventDefault();
            
            if (!validarCamposTexto()) {
                Swal.fire({
                    title: 'Error de validación',
                    text: 'Los campos de texto solo pueden contener letras y espacios',
                    icon: 'error',
                    confirmButtonColor: 'var(--color-primario)'
                });
                return;
            }
            
            // Validar que se haya tomado una foto
            const fotoInput = document.getElementById('foto_auditoria');
            if (!fotoInput.value) {
                Swal.fire({
                    title: 'Error de validación',
                    text: 'Debe tomar una foto de la auditoría',
                    icon: 'error',
                    confirmButtonColor: 'var(--color-primario)'
                });
                return;
            }
            
            const sucursal = document.getElementById('tienda').options[document.getElementById('tienda').selectedIndex].text;
            const montoDesignado = document.getElementById('monto_designado_display').value;
            const totalConteo = document.getElementById('total_conteo').textContent;
            const diferencia = document.getElementById('faltante').textContent;
            const comentarios = document.getElementById('comentarios').value;
            
            Swal.fire({
                title: '¿Confirmar auditoría?',
                html: `<div style="text-align: left;">
                       <p><strong>Sucursal:</strong> ${sucursal}</p>
                       <p><strong>Líder de Tienda:</strong> ${document.getElementById('lider').value}</p>
                       <p><strong>Monto Designado:</strong> ${montoDesignado}</p>
                       <p><strong>Total Conteo:</strong> ${totalConteo}</p>
                       <p><strong>Diferencia:</strong> ${diferencia}</p>
                       ${comentarios ? `<p><strong>Comentarios:</strong> ${comentarios}</p>` : ''}
                       <p><strong>Foto:</strong> Capturada</p>
                       </div>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: 'var(--color-primario)',
                cancelButtonColor: 'var(--color-error)',
                confirmButtonText: 'Sí, guardar',
                cancelButtonText: 'Cancelar',
                width: '80%'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Guardando...',
                        html: 'Por favor espere mientras se guarda la auditoría',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    document.getElementById('form-auditoria').submit();
                }
            });
        }
        
        // Variables para el control de la cámara (se mantienen igual)
        let stream;
        let fotoTomada = false;
        
        async function listarCamaras() {
            try {
                const dispositivos = await navigator.mediaDevices.enumerateDevices();
                const camaras = dispositivos.filter(dispositivo => dispositivo.kind === 'videoinput');
                
                const selectorCamara = document.getElementById('selectorCamara');
                selectorCamara.innerHTML = '<option value="">Seleccionar cámara...</option>';
                
                camaras.forEach((camara, index) => {
                    const option = document.createElement('option');
                    option.value = camara.deviceId;
                    option.text = camara.label || `Cámara ${index + 1}`;
                    selectorCamara.appendChild(option);
                });
            } catch (error) {
                console.error("Error al listar las cámaras: ", error);
                alert("No se pudieron listar las cámaras disponibles.");
            }
        }
        
        async function iniciarCamara(deviceId) {
            try {
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                }
                
                const constraints = {
                    video: {
                        deviceId: deviceId ? { exact: deviceId } : undefined,
                        width: { ideal: 320 },
                        height: { ideal: 240 },
                        facingMode: 'environment'
                    }
                };
                
                stream = await navigator.mediaDevices.getUserMedia(constraints);
                const video = document.getElementById('video');
                video.srcObject = stream;
                video.play();
            } catch (error) {
                console.error("Error al iniciar la cámara: ", error);
                alert("No se pudo acceder a la cámara. Asegúrese de haber concedido los permisos necesarios.");
            }
        }
        
        function capturarFoto() {
            const video = document.getElementById('video');
            const canvas = document.getElementById('canvas');
            const fotoCapturada = document.getElementById('foto_capturada');
            const fotoInput = document.getElementById('foto_auditoria');
            
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            
            const fotoData = canvas.toDataURL('image/png');
            
            fotoCapturada.src = fotoData;
            fotoCapturada.style.display = 'block';
            video.style.display = 'none';
            
            fotoInput.value = fotoData;
            
            fotoTomada = true;
            document.getElementById('guardarBtn').disabled = false;
            
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
        }
        
        function reiniciarCamara() {
            const video = document.getElementById('video');
            const fotoCapturada = document.getElementById('foto_capturada');
            const fotoInput = document.getElementById('foto_auditoria');
            
            fotoCapturada.style.display = 'none';
            video.style.display = 'block';
            
            fotoInput.value = '';
            
            fotoTomada = false;
            document.getElementById('guardarBtn').disabled = true;
            
            const selectorCamara = document.getElementById('selectorCamara');
            iniciarCamara(selectorCamara.value);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('tienda').addEventListener('change', actualizarMontoDesignado);
            document.getElementById('form-auditoria').addEventListener('submit', confirmarGuardado);
            actualizarMontoDesignado();
            
            // Validación en tiempo real para campos de texto
            document.querySelectorAll('.texto-validado').forEach(campo => {
                campo.addEventListener('input', function() {
                    if (!validarSoloTexto(this.value)) {
                        this.classList.add('error-input');
                    } else {
                        this.classList.remove('error-input');
                    }
                });
            });
            
            // Eventos para la cámara
            document.getElementById('selectorCamara').addEventListener('change', function() {
                iniciarCamara(this.value);
            });
            
            document.getElementById('capturarBtn').addEventListener('click', capturarFoto);
            document.getElementById('reiniciarBtn').addEventListener('click', reiniciarCamara);
            
            // Iniciar la cámara por defecto al cargar la página
            (async function() {
                await listarCamaras();
                await iniciarCamara();
            })();
            
            // Ajustar select en móviles
            if (window.innerWidth <= 480) {
                const select = document.getElementById('tienda');
                select.style.width = (window.innerWidth - 30) + 'px';
                
                window.addEventListener('resize', function() {
                    select.style.width = (window.innerWidth - 30) + 'px';
                });
            }
        });
        
        // Agrega esta función al script
        function obtenerLiderSucursal(sucursalId) {
            if (!sucursalId) return;
            
            fetch(`obtener_lider.php?sucursal_id=${sucursalId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.lider) {
                        document.getElementById('lider').value = data.lider;
                        // Agregar campo oculto para el código del líder
                        if (!document.getElementById('lider_codigo')) {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.id = 'lider_codigo';
                            input.name = 'lider_codigo';
                            document.getElementById('form-auditoria').appendChild(input);
                        }
                        document.getElementById('lider_codigo').value = data.codigo || '';
                    }
                })
                .catch(error => console.error('Error al obtener líder:', error));
        }
        
        // Modifica el event listener del select de sucursal para incluir la llamada a obtenerLiderSucursal
        document.getElementById('tienda').addEventListener('change', function() {
            actualizarMontoDesignado();
            obtenerLiderSucursal(this.value);
        });
    </script>
</body>
</html>
