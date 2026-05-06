<?php
// auditoria_promociones.php
require_once '../auth.php';
require_once '../../../../core/helpers/funciones.php'; // Antes llamaba a ../funciones.php de auditor�a
require_once 'config.php';
require_once '../../../../core/layout/menu_lateral.php';
require_once '../../../../core/layout/header_universal.php';

$db = conectarDB();

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([16, 21]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([16, 21]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
$cargoOperario = $usuario['CodNivelesCargos'];
//******************************Estándar para header, termina******************************

// Obtener sucursales para el select
$sucursales = [];
try {
    $stmt = $db->query("SELECT codigo, nombre FROM sucursales WHERE activa = 1 AND sucursal = 1 ORDER BY nombre");
    $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al obtener sucursales: " . $e->getMessage());
}

// Obtener operarios para autocompletar
$operarios_autocomplete = [];
try {
    $query = "SELECT 
                o.CodOperario, 
                CONCAT(
                    COALESCE(o.Nombre, ''), ' ',
                    COALESCE(o.Nombre2, ''), ' ',
                    COALESCE(o.Apellido, ''), ' ',
                    COALESCE(o.Apellido2, '')
                ) AS nombre_completo
              FROM Operarios o
              WHERE o.Operativo = 1
              ORDER BY nombre_completo";
    $stmt = $db->query($query);
    $operarios_autocomplete = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al obtener operarios: " . $e->getMessage());
}

$operarios_json = json_encode($operarios_autocomplete);

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha = date('Y-m-d H:i:s');
    $sucursal_id = (int)$_POST['sucursal_id'];
    $operario_id = (int)$_POST['operario_id'];
    $usuario_id = $_SESSION['usuario_id'];
    
    // Obtener las respuestas de las preguntas
    $respuesta_1 = trim($_POST['respuesta_1'] ?? '');
    $respuesta_2 = trim($_POST['respuesta_2'] ?? '');
    $respuesta_3 = trim($_POST['respuesta_3'] ?? '');
    $respuesta_4 = trim($_POST['respuesta_4'] ?? '');
    $respuesta_5 = trim($_POST['respuesta_5'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');
    
    // Calcular porcentaje de cumplimiento (respuestas no vacías)
    $total_preguntas = 5;
    $respuestas_completas = 0;
    if (!empty($respuesta_1)) $respuestas_completas++;
    if (!empty($respuesta_2)) $respuestas_completas++;
    if (!empty($respuesta_3)) $respuestas_completas++;
    if (!empty($respuesta_4)) $respuestas_completas++;
    if (!empty($respuesta_5)) $respuestas_completas++;
    
    $porcentaje_cumplimiento = round(($respuestas_completas / $total_preguntas) * 100);
    
    // Verificar que la sucursal existe
    try {
        $stmt = $db->prepare("SELECT codigo, nombre FROM sucursales WHERE codigo = ?");
        $stmt->execute([$sucursal_id]);
        $sucursal = $stmt->fetch();
        
        if (!$sucursal) {
            die("Error: La sucursal seleccionada no existe en la base de datos");
        }
        
        $sucursal_nombre = $sucursal['nombre'];
    } catch (PDOException $e) {
        die("Error al verificar la sucursal: " . $e->getMessage());
    }
    
    // Verificar que el operario existe
    try {
        $stmt = $db->prepare("
            SELECT o.CodOperario, 
                   CONCAT(
                    COALESCE(o.Nombre, ''), ' ',
                    COALESCE(o.Nombre2, ''), ' ',
                    COALESCE(o.Apellido, ''), ' ',
                    COALESCE(o.Apellido2, '')
                   ) AS nombre_completo
            FROM Operarios o
            WHERE o.CodOperario = ?
            LIMIT 1
        ");
        $stmt->execute([$operario_id]);
        $operario = $stmt->fetch();
        
        if (!$operario) {
            die("Error: El colaborador seleccionado no existe");
        }
        
        $operario_nombre = trim($operario['nombre_completo']);
    } catch (PDOException $e) {
        die("Error al verificar el colaborador: " . $e->getMessage());
    }
    
    // Verificar que existe la tabla o crearla
    $check_table = $db->query("SHOW TABLES LIKE 'auditoria_promociones'");
    if ($check_table->rowCount() == 0) {
        // Crear la tabla
        $create_table_sql = "CREATE TABLE auditoria_promociones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fecha DATETIME NOT NULL,
            sucursal_id INT NOT NULL,
            sucursal_nombre VARCHAR(255) NOT NULL,
            operario_id INT NOT NULL,
            operario_nombre VARCHAR(255) NOT NULL,
            respuesta_1 TEXT COMMENT 'Mencione nombres y cantidad de combos o promociones activas',
            respuesta_2 TEXT COMMENT 'Cual es la vigencia de estos combos o promociones',
            respuesta_3 TEXT COMMENT 'Mencionen si hay restricciones',
            respuesta_4 TEXT COMMENT 'Precios de cada combos',
            respuesta_5 TEXT COMMENT 'Detalle en que consiste cada combo o promoción',
            porcentaje_cumplimiento INT DEFAULT 0,
            observaciones TEXT,
            usuario_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sucursal_id) REFERENCES sucursales(codigo),
            FOREIGN KEY (operario_id) REFERENCES Operarios(CodOperario)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($create_table_sql);
    }
    
    // Insertar en la base de datos
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("INSERT INTO auditoria_promociones 
                            (fecha, sucursal_id, sucursal_nombre, operario_id, operario_nombre,
                            respuesta_1, respuesta_2, respuesta_3, respuesta_4, respuesta_5,
                            porcentaje_cumplimiento, observaciones, usuario_id, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        $stmt->execute([
            $fecha,
            $sucursal_id,
            $sucursal_nombre,
            $operario_id,
            $operario_nombre,
            $respuesta_1,
            $respuesta_2,
            $respuesta_3,
            $respuesta_4,
            $respuesta_5,
            $porcentaje_cumplimiento,
            $observaciones,
            $usuario_id
        ]);
        
        $db->commit();
        
        header("Location: auditoria_promociones.php?success=1");
        exit();
        
    } catch (PDOException $e) {
        $db->rollBack();
        die("Error al guardar la auditoría: " . $e->getMessage());
    }
}

// Mostrar mensaje de éxito si viene de redirección
$showSuccess = isset($_GET['success']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría de Promociones Combos Pitaya</title>
    <link rel="icon" href="icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <style>
        * {
            font-family: 'Calibri', sans-serif;
            box-sizing: border-box;
            font-size: clamp(11px, 2vw, 16px) !important;
        }
        
        html, body {
            overflow-x: hidden;
            width: 100%;
            margin: 0;
            padding: 0;
            background-color: #F6F6F6;
        }
        
        .form-group {
            margin-bottom: 20px;
            width: 100%;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 14px;
        }
        
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .pregunta-container {
            background-color: #f9f9f9;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .pregunta-numero {
            display: inline-block;
            background-color: #51B8AC;
            color: white;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            text-align: center;
            line-height: 25px;
            margin-right: 10px;
            font-weight: bold;
            font-size: 13px;
        }
        
        .pregunta-texto {
            font-weight: 600;
            color: #0E544C;
            margin-bottom: 10px;
        }
        
        .btn {
            background-color: #0E544C;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
            margin-bottom: 10px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background-color: #0a3d37;
        }
        
        .btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: <?php echo $showSuccess ? 'block' : 'none'; ?>;
            text-align: center;
        }
        
        .stats-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }
        
        .stats-box h3 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .percentage {
            font-size: 28px;
            font-weight: bold;
            color: #0E544C;
        }
        
        .button-container {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
            justify-content: center;
            width: 100%;
        }
        
        .btn-cancelar {
            background-color: #6c757d !important;
            color: white !important;
        }
        
        .btn-cancelar:hover {
            background-color: #5a6268 !important;
        }
        
        /* Estilos para autocomplete */
        .ui-autocomplete {
            max-height: 200px;
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 1000;
        }
        
        .ui-menu-item {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        
        .ui-menu-item:hover {
            background-color: #0E544C;
            color: white;
        }
        
        .invalid {
            border: 2px solid #dc3545 !important;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 25px;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        
        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
            gap: 10px;
        }
        
        .modal-message {
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .readonly-field {
            background-color: #e9ecef;
            cursor: not-allowed;
        }
        
        @media (min-width: 768px) {
            .btn {
                width: auto;
                margin-bottom: 0;
            }
            
            .button-container {
                flex-direction: row;
            }
        }
        
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                align-items: center;
                gap: 10px;
            }
            
            .buttons-container {
                width: 100%;
                justify-content: center;
            }
            
            .modal-content {
                margin: 30% auto;
                width: 95%;
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
        }
    </style>
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container">
        <div class="contenedor-principal">
            <?php echo renderHeader($usuario, $esAdmin, 'Auditoría de Promociones Combos Pitaya'); ?>
            <div class="success-message" id="successMessage">
                <i class="fas fa-check-circle"></i> ¡La auditoría de promociones se ha guardado correctamente! Serás redirigido...
            </div>
            
            <!-- Modal de confirmación -->
            <div id="confirmModal" class="modal">
                <div class="modal-content">
                    <p id="modalMessage">¿Está seguro que desea guardar esta auditoría?</p>
                    <div class="modal-buttons">
                        <button class="btn btn-cancelar" id="cancelBtn">Cancelar</button>
                        <button class="btn" id="confirmBtn">Guardar</button>
                    </div>
                </div>
            </div>
            
            <form id="auditoriaForm" method="post">
                <div class="form-group">
                    <label for="fecha">Fecha y Hora:</label>
                    <input type="text" id="fecha" name="fecha" value="<?php echo date('d/m/Y H:i'); ?>" readonly class="readonly-field">
                </div>
                
                <div class="form-group">
                    <label for="sucursal_id">Sucursal: *</label>
                    <select id="sucursal_id" name="sucursal_id" required>
                        <option value="">Seleccione una sucursal</option>
                        <?php foreach ($sucursales as $sucursal): ?>
                            <option value="<?php echo htmlspecialchars($sucursal['codigo']); ?>">
                                <?php echo htmlspecialchars($sucursal['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="operario_input">Colaborador Evaluado: *</label>
                    <input type="text" id="operario_input" class="colaborador-input" placeholder="Buscar colaborador..." required>
                    <input type="hidden" id="operario_id" name="operario_id" required>
                    <small style="color: #6c757d; display:none;">Empiece a escribir el nombre del colaborador y seleccione de la lista</small>
                </div>
                
                <hr style="margin: 25px 0; border-color: #eee;">
                
                <h3 style="color: #0E544C; margin-bottom: 20px;"><i class="fas fa-question-circle"></i> Preguntas de Evaluación</h3>
                
                <!-- Pregunta 1 -->
                <div class="pregunta-container">
                    <div class="pregunta-texto">
                        <span class="pregunta-numero">1</span>
                        Mencione nombres y cantidad de combos o promociones activas.
                    </div>
                    <textarea id="respuesta_1" name="respuesta_1" placeholder="Escriba su respuesta aquí..." rows="3"></textarea>
                </div>
                
                <!-- Pregunta 2 -->
                <div class="pregunta-container">
                    <div class="pregunta-texto">
                        <span class="pregunta-numero">2</span>
                        ¿Cuál es la vigencia de estos combos o promociones?
                    </div>
                    <textarea id="respuesta_2" name="respuesta_2" placeholder="Escriba su respuesta aquí..." rows="3"></textarea>
                </div>
                
                <!-- Pregunta 3 -->
                <div class="pregunta-container">
                    <div class="pregunta-texto">
                        <span class="pregunta-numero">3</span>
                        Mencione si hay restricciones.
                    </div>
                    <textarea id="respuesta_3" name="respuesta_3" placeholder="Escriba su respuesta aquí..." rows="3"></textarea>
                </div>
                
                <!-- Pregunta 4 -->
                <div class="pregunta-container">
                    <div class="pregunta-texto">
                        <span class="pregunta-numero">4</span>
                        ¿Precios de cada combo?
                    </div>
                    <textarea id="respuesta_4" name="respuesta_4" placeholder="Escriba su respuesta aquí..." rows="3"></textarea>
                </div>
                
                <!-- Pregunta 5 -->
                <div class="pregunta-container">
                    <div class="pregunta-texto">
                        <span class="pregunta-numero">5</span>
                        Detalle en qué consiste cada combo o promoción.
                    </div>
                    <textarea id="respuesta_5" name="respuesta_5" placeholder="Escriba su respuesta aquí..." rows="4"></textarea>
                </div>
                
                <div id="statsContainer" class="stats-box" style="display: none;">
                    <h3>Preguntas Respondidas:</h3>
                    <div class="percentage" id="porcentajeCumplimiento">0%</div>
                    <p id="preguntasRespondidas">0 de 5 preguntas respondidas</p>
                </div>
                
                <div class="form-group">
                    <label for="observaciones">Observaciones Adicionales:</label>
                    <textarea id="observaciones" name="observaciones" rows="3" placeholder="Observaciones adicionales sobre la evaluación..."></textarea>
                </div>
                
                <div class="button-container">
                    <button type="button" class="btn" id="submitBtn">
                        <i class="fas fa-save"></i> Guardar Auditoría
                    </button>
                    <button type="button" class="btn btn-cancelar" onclick="window.location.href='../index.php'">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const operarios = <?php echo $operarios_json; ?>;
        
        // Preparar datos para autocomplete
        const operariosAutocomplete = operarios.map(operario => ({
            label: operario.nombre_completo,
            value: operario.nombre_completo,
            id: operario.CodOperario
        }));
        
        // Función para calcular preguntas respondidas
        function calcularCumplimiento() {
            const totalPreguntas = 5;
            let preguntasRespondidas = 0;
            
            for (let i = 1; i <= totalPreguntas; i++) {
                const respuesta = document.getElementById('respuesta_' + i).value.trim();
                if (respuesta !== '') {
                    preguntasRespondidas++;
                }
            }
            
            const porcentaje = Math.round((preguntasRespondidas / totalPreguntas) * 100);
            
            // Actualizar display
            const statsContainer = document.getElementById('statsContainer');
            const porcentajeElement = document.getElementById('porcentajeCumplimiento');
            const preguntasElement = document.getElementById('preguntasRespondidas');
            
            porcentajeElement.textContent = porcentaje + '%';
            preguntasElement.textContent = preguntasRespondidas + ' de ' + totalPreguntas + ' preguntas respondidas';
            
            // Mostrar contenedor de estadísticas si hay al menos una respuesta
            if (preguntasRespondidas > 0) {
                statsContainer.style.display = 'block';
            } else {
                statsContainer.style.display = 'none';
            }
            
            return { preguntasRespondidas, porcentaje };
        }
        
        // Función para validar formulario
        function validarFormulario() {
            const sucursalId = document.getElementById('sucursal_id').value;
            const operarioId = document.getElementById('operario_id').value;
            const operarioInput = document.getElementById('operario_input').value;
            
            let valido = true;
            let mensajesError = [];
            
            // Validar sucursal
            if (!sucursalId) {
                valido = false;
                mensajesError.push('Debe seleccionar una sucursal');
                document.getElementById('sucursal_id').classList.add('invalid');
            } else {
                document.getElementById('sucursal_id').classList.remove('invalid');
            }
            
            // Validar colaborador
            if (!operarioId || !operarioInput.trim()) {
                valido = false;
                mensajesError.push('Debe seleccionar un colaborador de la lista');
                document.getElementById('operario_input').classList.add('invalid');
            } else {
                document.getElementById('operario_input').classList.remove('invalid');
            }
            
            // Verificar que al menos una pregunta esté respondida
            const { preguntasRespondidas } = calcularCumplimiento();
            if (preguntasRespondidas === 0) {
                valido = false;
                mensajesError.push('Debe responder al menos una pregunta');
            }
            
            return { valido, mensajesError };
        }
        
        // Función para mostrar modal
        function showModal(message, isConfirm = false) {
            const modal = document.getElementById('confirmModal');
            const modalMessage = document.getElementById('modalMessage');
            
            modalMessage.innerHTML = message.replace(/\n/g, '<br>');
            modal.style.display = 'block';
            
            return new Promise((resolve) => {
                document.getElementById('confirmBtn').onclick = function() {
                    modal.style.display = 'none';
                    resolve(true);
                };
                
                document.getElementById('cancelBtn').onclick = function() {
                    modal.style.display = 'none';
                    resolve(false);
                };
                
                window.onclick = function(event) {
                    if (event.target === modal) {
                        modal.style.display = 'none';
                        resolve(false);
                    }
                };
            });
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Configurar autocomplete para colaborador
            $('#operario_input').autocomplete({
                source: operariosAutocomplete,
                minLength: 2,
                select: function(event, ui) {
                    $(this).val(ui.item.value);
                    $('#operario_id').val(ui.item.id);
                    $(this).removeClass('invalid');
                    return false;
                },
                change: function(event, ui) {
                    if (!ui.item) {
                        const inputValue = $(this).val();
                        const found = operariosAutocomplete.find(op => 
                            op.value.toLowerCase() === inputValue.toLowerCase()
                        );
                        
                        if (found) {
                            $('#operario_id').val(found.id);
                            $(this).removeClass('invalid');
                        } else {
                            $('#operario_id').val('');
                            $(this).addClass('invalid');
                        }
                    }
                }
            });
            
            // Calcular cumplimiento cada vez que cambie una respuesta
            for (let i = 1; i <= 5; i++) {
                document.getElementById('respuesta_' + i).addEventListener('input', calcularCumplimiento);
            }
            
            // Manejar envío del formulario
            document.getElementById('submitBtn').addEventListener('click', async function(e) {
                e.preventDefault();
                
                // Validar formulario
                const { valido, mensajesError } = validarFormulario();
                
                if (!valido) {
                    const mensaje = mensajesError.join('\n');
                    await showModal(mensaje);
                    return;
                }
                
                // Calcular estadísticas para mostrar en confirmación
                const { preguntasRespondidas, porcentaje } = calcularCumplimiento();
                
                // Mostrar confirmación
                const confirmMessage = `
                    ¿Está seguro que desea guardar esta auditoría?
                    
                    <strong>Resumen:</strong>
                    • Preguntas respondidas: ${preguntasRespondidas}/5
                    • Porcentaje: ${porcentaje}%
                    
                    Esta acción no se puede deshacer.
                `;
                
                const confirmado = await showModal(confirmMessage, true);
                
                if (confirmado) {
                    // Deshabilitar botón para evitar doble envío
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
                    
                    // Enviar formulario
                    document.getElementById('auditoriaForm').submit();
                }
            });
            
            // Redirección automática después de éxito
            if (window.location.search.includes('success=1')) {
                setTimeout(function() {
                    window.location.href = '../index.php';
                }, 3000);
                
                // Limpiar parámetro de la URL
                history.replaceState(null, null, window.location.pathname);
            }
            
            // Validación en tiempo real
            document.getElementById('sucursal_id').addEventListener('change', function() {
                if (this.value) {
                    this.classList.remove('invalid');
                }
            });
            
            document.getElementById('operario_input').addEventListener('blur', function() {
                const operarioId = document.getElementById('operario_id').value;
                if (!operarioId && this.value.trim()) {
                    this.classList.add('invalid');
                }
            });
        });
    </script>
</body>
</html>
