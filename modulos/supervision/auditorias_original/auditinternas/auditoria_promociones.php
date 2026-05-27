<?php
// auditoria_promociones.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
// Antes llamaba a ../funciones.php de auditora
// require_once 'config.php'; // Comentado por migración al core
require_once '../../../../core/layout/menu_lateral.php';
require_once '../../../../core/layout/header_universal.php';

// $db = conectarDB(); // Comentado por migración al core
$db = $conn;

//******************************Estándar para header******************************

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([16, 21, 49, 52]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([16, 21, 49, 52])) {
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
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="css/auditoria_promociones.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container">
        <div class="contenedor-principal">
            <?php echo renderHeader($usuario, 'Auditoría de Promociones Combos Pitaya'); ?>
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

            return {
                preguntasRespondidas,
                porcentaje
            };
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
            const {
                preguntasRespondidas
            } = calcularCumplimiento();
            if (preguntasRespondidas === 0) {
                valido = false;
                mensajesError.push('Debe responder al menos una pregunta');
            }

            return {
                valido,
                mensajesError
            };
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
                const {
                    valido,
                    mensajesError
                } = validarFormulario();

                if (!valido) {
                    const mensaje = mensajesError.join('\n');
                    await showModal(mensaje);
                    return;
                }

                // Calcular estadísticas para mostrar en confirmación
                const {
                    preguntasRespondidas,
                    porcentaje
                } = calcularCumplimiento();

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