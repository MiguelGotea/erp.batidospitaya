<?php
// Incluir configuración y verificar autenticación
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/layout/menu_lateral.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/layout/header_universal.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/permissions/permissions.php';
// Antes llamaba a ../funciones.php de auditora
// require_once 'config.php'; // Comentado por migración al core

// Conexión a la base de datos usando la función de config.php
// $conn = conectarDB(); // Comentado por migración al core (ya viene de conexion.php a través de auth.php)

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar permisos
$puede_ver         = tienePermiso('auditoria_efectivo', 'vista',      $cargoOperario);
$puede_nuevo       = tienePermiso('auditoria_efectivo', 'nuevo',      $cargoOperario);
$puede_nuevo_todos = tienePermiso('auditoria_efectivo', 'nuevo_todos', $cargoOperario);
$puede_editar      = tienePermiso('auditoria_efectivo', 'editar',     $cargoOperario);

if (!$puede_ver) {
    header('Location: /index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);

// Procesar formulario (modificado)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$puede_nuevo) {
        header('Location: auditorias_consolidadas.php');
        exit();
    }
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

// Obtener sucursales (filtradas por supervisor_asignado si no tiene nuevo_todos)
// Excluimos la 0 central, 14 ferias
if ($puede_nuevo_todos) {
    $stmtSucursales = $conn->query("SELECT s.codigo as id, s.nombre as name, cs.monto_designado 
                                   FROM sucursales s
                                   LEFT JOIN caja_chica_sucursales cs ON s.codigo = cs.sucursal_id AND cs.activo = 1
                                   WHERE s.activa = 1 AND s.codigo NOT IN (0, 14)
                                   ORDER BY s.nombre");
} else {
    $stmtSucursales = $conn->prepare("SELECT s.codigo as id, s.nombre as name, cs.monto_designado 
                                     FROM sucursales s
                                     LEFT JOIN caja_chica_sucursales cs ON s.codigo = cs.sucursal_id AND cs.activo = 1
                                     WHERE s.activa = 1 AND s.codigo NOT IN (0, 14) AND JSON_VALID(COALESCE(NULLIF(s.supervisor_asignado, ''), '[]')) = 1 AND JSON_CONTAINS(COALESCE(NULLIF(s.supervisor_asignado, ''), '[]'), CAST(? AS JSON))
                                     ORDER BY s.nombre");
    $stmtSucursales->execute([$_SESSION['usuario_id']]);
}
$sucursales = $stmtSucursales->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Auditoría de Caja Chica</title>
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="css/auditoria_caja_chica.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Auditoría Caja Chica'); ?>

            <div class="container-fluid p-3">
        <div class="container">
        
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
                    <?php if ($puede_nuevo): ?>
                    <button type="submit" class="btn btn-special" id="guardarBtn" disabled>Guardar Auditoría</button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-reset" onclick="window.location.href='auditorias_consolidadas.php'">Cancelar</button>
                </div>
            </form>
        </div><!-- /.card -->
        </div><!-- /.container -->
            </div><!-- /.container-fluid -->
        </div><!-- /.sub-container -->
    </div><!-- /.main-container -->

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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
