<?php
// Incluir configuración y verificar autenticación
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
// Antes llamaba a ../funciones.php de auditora
// require_once 'config.php'; // Comentado por migración al core

// Verificar acceso al módulo 'supervision'
//verificarAccesoModulo('supervision');

//******************************Estándar para header******************************

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([8, 16]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([8, 16])) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

date_default_timezone_set('America/Managua');

try {
    // $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); // Comentado por migración al core
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    // Verificar y crear tablas si no existen
    $check_tables = $conn->query("SHOW TABLES LIKE 'faltante_caja'");
    if ($check_tables->num_rows == 0) {
        // Las tablas se crearán automáticamente con el script SQL proporcionado
        // Por seguridad, aquí solo verificamos
        die("Las tablas para faltante de caja no existen. Ejecute el script SQL primero.");
    }
} catch (Exception $e) {
    die("Error al conectar con la base de datos: " . $e->getMessage());
}

// Procesar formulario si se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $registrador_id = $_SESSION['usuario_id'];
    
    // DEBUG: Verificar datos recibidos
    error_log("Datos POST recibidos: " . print_r($_POST, true));
    
    $conn->begin_transaction();
    
    try {
        $registros_guardados = 0;
        
        foreach ($_POST['colaboradores'] as $index => $colaborador) {
            // Validar y sanitizar datos
            $fecha = !empty($colaborador['fecha']) ? $colaborador['fecha'] : date('Y-m-d');
            $sucursal_id = !empty($colaborador['sucursal_id']) ? intval($colaborador['sucursal_id']) : 0;
            $operario_id = !empty($colaborador['operario_id']) ? intval($colaborador['operario_id']) : 0;
            $operario_nombre = !empty($colaborador['nombre_completo']) ? trim($colaborador['nombre_completo']) : '';
            $monto = !empty($colaborador['monto']) ? floatval($colaborador['monto']) : 0;
            $comentarios = !empty($colaborador['comentarios']) ? trim($colaborador['comentarios']) : null;
            
            // Validaciones críticas
            if (empty($fecha) || $fecha == '0000-00-00') {
                throw new Exception("Fecha inválida en fila " . ($index + 1));
            }
            
            if ($sucursal_id <= 0) {
                throw new Exception("Sucursal no seleccionada en fila " . ($index + 1));
            }
            
            if ($operario_id <= 0) {
                throw new Exception("Colaborador no seleccionado correctamente en fila " . ($index + 1));
            }
            
            if ($monto <= 0) {
                throw new Exception("Monto debe ser mayor a 0 en fila " . ($index + 1));
            }
            
            // Obtener nombre de la sucursal
            $sucursal_nombre = '';
            $query = "SELECT nombre FROM sucursales WHERE codigo = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $sucursal_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $sucursal_nombre = $row['nombre'];
            } else {
                throw new Exception("Sucursal no encontrada: " . $sucursal_id);
            }
            $stmt->close();
            
            // Obtener código de contrato del operario involucrado - CONSULTA DIRECTA
            $cod_contrato_operario = null;
            $stmt_contrato = $conn->prepare("
                SELECT CodContrato 
                FROM Contratos 
                WHERE cod_operario = ? 
                ORDER BY inicio_contrato DESC, CodContrato DESC 
                LIMIT 1
            ");
            
            if ($stmt_contrato) {
                $stmt_contrato->bind_param("i", $operario_id);
                $stmt_contrato->execute();
                $result_contrato = $stmt_contrato->get_result();
                
                if ($row_contrato = $result_contrato->fetch_assoc()) {
                    $cod_contrato_operario = $row_contrato['CodContrato'];
                    error_log("Contrato encontrado directamente: " . $cod_contrato_operario);
                } else {
                    error_log("No se encontró contrato en consulta directa para: " . $operario_id);
                }
                $stmt_contrato->close();
            } else {
                error_log("Error preparando consulta de contrato: " . $conn->error);
            }
            
            // DEBUG: Log de datos antes de insertar
            error_log("Insertando: fecha=$fecha, sucursal_id=$sucursal_id, operario_id=$operario_id, monto=$monto, cod_contrato=" . ($cod_contrato_operario ?? 'NULL'));
            
            // Insertar cada registro individualmente
            $stmt = $conn->prepare("INSERT INTO faltante_caja 
                                   (fecha, sucursal_id, sucursal, operario_id, operario_nombre, monto, comentarios, registrador_id, cod_contrato) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if (!$stmt) {
                throw new Exception("Error preparando consulta: " . $conn->error);
            }
            
            $stmt->bind_param("sisisdsii", 
                $fecha,
                $sucursal_id,
                $sucursal_nombre,
                $operario_id,
                $operario_nombre,
                $monto,
                $comentarios,
                $registrador_id,
                $cod_contrato_operario
            );
            
            if ($stmt->execute()) {
                $registros_guardados++;
            } else {
                throw new Exception("Error ejecutando consulta: " . $stmt->error);
            }
            
            $stmt->close();
        }
        
        $conn->commit();
        $_SESSION['success_message'] = "Se guardaron {$registros_guardados} registros de faltante de caja correctamente.";
        header('Location: faltante_caja.php');
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error al guardar los registros: " . $e->getMessage();
        error_log("Error en faltante_caja: " . $e->getMessage());
    }
}

// Obtener sucursales
$sucursales = [];
try {
    $query = "SELECT codigo, nombre FROM sucursales WHERE activa = 1 AND sucursal = 1 ORDER BY nombre";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $sucursales[] = $row;
        }
    }
} catch (Exception $e) {
    die("Error al obtener sucursales: " . $e->getMessage());
}

// Obtener operarios para autocompletar
$operarios_autocomplete = [];
$query = "SELECT 
            o.CodOperario, 
            CONCAT(
                COALESCE(o.Nombre, ''), ' ',
                COALESCE(o.Nombre2, ''), ' ',
                COALESCE(o.Apellido, ''), ' ',
                COALESCE(o.Apellido2, '')
            ) AS nombre_completo,
            s.nombre as sucursal_nombre
          FROM Operarios o
          LEFT JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
          LEFT JOIN sucursales s ON anc.Sucursal = s.codigo
          WHERE o.Operativo = 1
          AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
          GROUP BY o.CodOperario
          ORDER BY nombre_completo";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Limpiar espacios extra en el nombre completo
        $row['nombre_completo'] = preg_replace('/\s+/', ' ', trim($row['nombre_completo']));
        $operarios_autocomplete[] = $row;
    }
}

$operarios_json = json_encode($operarios_autocomplete);
$sucursales_json = json_encode($sucursales);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Registro de Faltante de Caja</title>
    <link href="https://fonts.googleapis.com/css2?family=Calibri&display=swap" rel="stylesheet">
    <link rel="icon" href="icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="css/faltante_caja.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>
<body>
    <div class="container">
        <div class="header">
            <header>
                <div class="header-container">
                    <div class="logo-container">
                        <img src="../Logo.svg" alt="Batidos Pitaya" class="logo">
                    </div>
                    
                    <div class="buttons-container">
                        <a href="deducciones_total.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'deducciones_total.php' ? 'activo' : '' ?>">
                            <i class="fas fa-money-bill-wave"></i> <span class="btn-text">Deducciones</span>
                        </a>
                        <a href="faltante_caja.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'faltante_caja.php' ? 'activo' : '' ?>">
                            <i class="fas fa-money-bill-wave"></i> <span class="btn-text">Faltante de Caja</span>
                        </a>
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
            
            <h1 style="text-align:center;">Registro de Faltante de Caja</h1>
        </div>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="message success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <form id="faltanteForm" method="post" action="">
            <div class="form-group">
                <label>Registros de Faltante de Caja:</label>
                <div class="add-row-container">
                    <button type="button" class="btn add-row" id="addColaborador">Agregar Colaborador</button>
                </div>
                <div class="table-responsive">
                    <table id="colaboradoresTable">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Sucursal</th>
                                <th>Colaborador</th>
                                <th>Monto (C$)</th>
                                <th>Comentarios</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="colaboradoresBody">
                            <tr>
                                <td>
                                    <input type="date" name="colaboradores[0][fecha]" class="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                                </td>
                                <td>
                                    <select name="colaboradores[0][sucursal_id]" class="sucursal-select" required>
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($sucursales as $sucursal): ?>
                                            <option value="<?php echo htmlspecialchars($sucursal['codigo']); ?>">
                                                <?php echo htmlspecialchars($sucursal['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="colaboradores[0][nombre_completo]" class="colaborador-input" required>
                                    <input type="hidden" name="colaboradores[0][operario_id]" class="operario-id">
                                </td>
                                <td><input type="number" name="colaboradores[0][monto]" class="monto" min="1" step="1" required></td>
                                <td><textarea name="colaboradores[0][comentarios]" class="comentarios-fila" rows="2" placeholder="Comentarios específicos..."></textarea></td>
                                <td><button type="button" class="remove-row"><i class="fas fa-times"></i></button></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="3">Total General:</td>
                                <td id="totalFaltante">0.00</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <div class="button-container">
                <button type="submit" class="btn" id="guardarBtn">Guardar Todos los Faltantes</button>
                <button type="button" class="btn btn-cancelar" onclick="window.location.href='auditorias_consolidadas.php'">Cancelar</button>
            </div>
        </form>
    </div>

    <!-- Modal para mensajes -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-message" id="modalMessage"></div>
            <div class="modal-buttons">
                <button type="button" class="btn btn-cancelar" id="modalCancelBtn">Cancelar</button>
                <button type="button" class="btn" id="modalConfirmBtn">Aceptar</button>
            </div>
        </div>
    </div>

    <script>
        const operarios = <?php echo $operarios_json; ?>;
        const sucursales = <?php echo $sucursales_json; ?>;
        
        const operariosAutocomplete = operarios.map(operario => ({
            label: operario.nombre_completo + (operario.sucursal_nombre ? ' - ' + operario.sucursal_nombre : ''),
            value: operario.nombre_completo,
            id: operario.CodOperario
        }));
        
        document.addEventListener('DOMContentLoaded', function() {
            // Elementos del modal
            const modal = document.getElementById('modal');
            const modalMessage = document.getElementById('modalMessage');
            const modalConfirmBtn = document.getElementById('modalConfirmBtn');
            const modalCancelBtn = document.getElementById('modalCancelBtn');
            
            // Función para mostrar modal
            function showModal(message, isConfirm = false, callback = null) {
                modalMessage.textContent = message;
                modal.style.display = 'block';
                
                if (isConfirm) {
                    modalCancelBtn.style.display = 'inline-block';
                    modalConfirmBtn.style.display = 'inline-block';
                    
                    modalConfirmBtn.onclick = function() {
                        modal.style.display = 'none';
                        if (callback) callback(true);
                    };
                    
                    modalCancelBtn.onclick = function() {
                        modal.style.display = 'none';
                        if (callback) callback(false);
                    };
                } else {
                    modalCancelBtn.style.display = 'none';
                    modalConfirmBtn.style.display = 'inline-block';
                    modalConfirmBtn.textContent = 'Aceptar';
                    
                    modalConfirmBtn.onclick = function() {
                        modal.style.display = 'none';
                    };
                }
            }
            
            // Agregar fila de colaborador
            document.getElementById('addColaborador').addEventListener('click', function() {
                const tbody = document.getElementById('colaboradoresBody');
                const rowCount = tbody.querySelectorAll('tr').length;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <input type="date" name="colaboradores[${rowCount}][fecha]" class="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                    </td>
                    <td>
                        <select name="colaboradores[${rowCount}][sucursal_id]" class="sucursal-select" required>
                            <option value="">Seleccione...</option>
                            ${sucursales.map(s => `<option value="${s.codigo}">${s.nombre}</option>`).join('')}
                        </select>
                    </td>
                    <td>
                        <input type="text" name="colaboradores[${rowCount}][nombre_completo]" class="colaborador-input" required>
                        <input type="hidden" name="colaboradores[${rowCount}][operario_id]" class="operario-id">
                    </td>
                    <td><input type="number" name="colaboradores[${rowCount}][monto]" class="monto" min="1" step="1" required></td>
                    <td><textarea name="colaboradores[${rowCount}][comentarios]" class="comentarios-fila" rows="2" placeholder="Comentarios específicos..."></textarea></td>
                    <td><button type="button" class="remove-row"><i class="fas fa-times"></i></button></td>
                `;
                tbody.appendChild(tr);
                
                setupAutocomplete(tr);
                addRowEvents(tr);
                
                tr.querySelector('.remove-row').addEventListener('click', function() {
                    if (document.querySelectorAll('#colaboradoresBody tr').length > 1) {
                        tbody.removeChild(tr);
                        calcularTotales();
                    } else {
                        showModal('Debe haber al menos un colaborador');
                    }
                });
            });
            
            function setupAutocomplete(row) {
                const colaboradorInput = row.querySelector('.colaborador-input');
                const operarioIdInput = row.querySelector('.operario-id');
                
                $(colaboradorInput).autocomplete({
                    source: operariosAutocomplete,
                    minLength: 2,
                    select: function(event, ui) {
                        $(this).val(ui.item.value);
                        operarioIdInput.value = ui.item.id;
                        $(this).removeClass('invalid');
                        return false;
                    }
                });
            }
            
            function addRowEvents(row) {
                const montoInput = row.querySelector('.monto');
                
                function calcularTotal() {
                    calcularTotales();
                }
                
                montoInput.addEventListener('input', calcularTotal);
            }
            
            function calcularTotales() {
                let totalFaltante = 0;
                document.querySelectorAll('.monto').forEach(input => {
                    totalFaltante += parseFloat(input.value) || 0;
                });
                document.getElementById('totalFaltante').textContent = totalFaltante.toFixed(2);
            }
            
            // Configurar eventos para la fila inicial
            document.querySelectorAll('#colaboradoresBody tr').forEach(row => {
                setupAutocomplete(row);
                addRowEvents(row);
                
                row.querySelector('.remove-row').addEventListener('click', function() {
                    if (document.querySelectorAll('#colaboradoresBody tr').length > 1) {
                        document.getElementById('colaboradoresBody').removeChild(row);
                        calcularTotales();
                    } else {
                        showModal('Debe haber al menos un colaborador');
                    }
                });
            });
            
            // Validar formulario antes de enviar - VERSIÓN MEJORADA
            document.getElementById('faltanteForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                let valid = true;
                let mensajesError = [];
                
                // Validar cada fila
                const filas = document.querySelectorAll('#colaboradoresBody tr');
                filas.forEach((fila, index) => {
                    const erroresFila = validarFila(fila, index);
                    if (erroresFila.length > 0) {
                        valid = false;
                        mensajesError = mensajesError.concat(erroresFila);
                    }
                });
                
                if (!valid) {
                    const mensajeUnico = [...new Set(mensajesError)].join('\n');
                    showModal(mensajeUnico);
                    return;
                }
                
                // Confirmación final
                const totalFilas = filas.length;
                showModal(`¿Está seguro que desea guardar ${totalFilas} registro(s) de faltante de caja?`, true, function(confirmed) {
                    if (confirmed) {
                        // Deshabilitar botón para evitar doble envío
                        const guardarBtn = document.getElementById('guardarBtn');
                        guardarBtn.disabled = true;
                        guardarBtn.textContent = 'Guardando...';
                        
                        document.getElementById('faltanteForm').submit();
                    }
                });
            });
        });
        
        // Función mejorada para validar filas antes de enviar
        function validarFila(fila, index) {
            const fecha = fila.querySelector('.fecha').value;
            const sucursalSelect = fila.querySelector('.sucursal-select');
            const sucursalId = sucursalSelect ? sucursalSelect.value : '';
            const colaboradorInput = fila.querySelector('.colaborador-input');
            const operarioIdInput = fila.querySelector('.operario-id');
            const operarioId = operarioIdInput ? operarioIdInput.value : '';
            const monto = fila.querySelector('.monto').value;
            
            let errores = [];
            
            // Validar fecha
            if (!fecha || fecha === '0000-00-00') {
                errores.push(`Fila ${index + 1}: Fecha es requerida`);
                fila.querySelector('.fecha').classList.add('invalid');
            } else {
                fila.querySelector('.fecha').classList.remove('invalid');
            }
            
            // Validar sucursal
            if (!sucursalId) {
                errores.push(`Fila ${index + 1}: Sucursal es requerida`);
                if (sucursalSelect) sucursalSelect.classList.add('invalid');
            } else {
                if (sucursalSelect) sucursalSelect.classList.remove('invalid');
            }
            
            // Validar colaborador
            if (!operarioId || !colaboradorInput.value.trim()) {
                errores.push(`Fila ${index + 1}: Debe seleccionar un colaborador válido de la lista`);
                colaboradorInput.classList.add('invalid');
            } else {
                colaboradorInput.classList.remove('invalid');
            }
            
            // Validar monto
            if (!monto || parseFloat(monto) <= 0) {
                errores.push(`Fila ${index + 1}: Monto debe ser mayor a 0`);
                fila.querySelector('.monto').classList.add('invalid');
            } else {
                fila.querySelector('.monto').classList.remove('invalid');
            }
            
            return errores;
        }
    </script>
</body>
</html>
