<?php
ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Al inicio del archivo, verificar autenticación y acceso al módulo
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
require_once '../../includes/conexion.php';

// Verificar acceso al módulo 'operaciones'
//verificarAccesoModulo('operaciones');

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo (ajusta los cargos según necesites)
verificarAccesoCargo([11, 13, 16]); // Ejemplo: Jefe Operaciones, RH, Gerencia

// Verificar acceso al módulo
if (!verificarAccesoCargo([11, 13, 16]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Verificar si se envió el formulario de edición
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_edicion'])) {
    $codOperario = $_POST['cod_operario'];
    $campo = $_POST['campo'];
    $valor = $_POST['valor'];
    
    // Obtener el ID del usuario que está modificando
    $cod_operario_actualizacion = $_SESSION['usuario_id'];
    $fecha_actualizacion = date('Y-m-d H:i:s');
    
    // Lista de campos que NO se pueden editar
    $camposNoEditables = [
        'fecha_hora_regsys', 
        'registrado_por', 
        'FechaCreacion', 
        'FechaRegistro',
        'Operativo',
        'CodOperario'
    ];
    
    // Validar que el campo sea editable
    if (in_array($campo, $camposNoEditables)) {
        $_SESSION['error'] = 'Este campo no se puede editar';
        header("Location: Operarios.php?error=1");
        exit();
    }
    
    // Manejar campos de fecha específicos
    if (in_array($campo, ['Inicio', 'Fin', 'Cumpleanos', 'InicioSeguro', 'FinSeguro'])) {
        if (!empty($valor)) {
            $valor = date('Y-m-d H:i:s', strtotime($valor));
        } else {
            $valor = null;
        }
    }
    
    // Manejar campo bit para Operativo (aunque no debería ser editable, por si acaso)
    if ($campo === 'Operativo') {
        $valor = ($valor == '1') ? 1 : 0;
    }
    
    // Preparar la consulta de actualización
    $query = "UPDATE Operarios SET $campo = :valor, fecha_hora_regsys = :fecha_actualizacion WHERE CodOperario = :cod_operario";
    $stmt = $conn->prepare($query);
    
    try {
        $stmt->execute([
            ':valor' => $valor,
            ':fecha_actualizacion' => $fecha_actualizacion,
            ':cod_operario' => $codOperario
        ]);
        
        $_SESSION['success'] = 'Cambio guardado exitosamente';
        header("Location: Operarios.php?success=1");
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error al guardar: ' . $e->getMessage();
        header("Location: Operarios.php?error=1");
        exit();
    }
}

// Obtener solo operarios activos (Operativo = 1)
$query_operarios = "SELECT * FROM Operarios WHERE Operativo = 1 ORDER BY CodOperario DESC";
$stmt_operarios = $conn->prepare($query_operarios);
$stmt_operarios->execute();
$operarios = $stmt_operarios->fetchAll(PDO::FETCH_ASSOC);

// Definir campos no editables
$camposNoEditables = [
    'CodOperario'
];

// COLUMNAS QUE SE MOSTRARÁN (las 8 especificadas)
$columnasMostrar = [
    'CodOperario',
    'Nombre', 
    'Nombre2', 
    'Apellido', 
    'Apellido2', 
    'Celular', 
    'Cedula',
    'Cumpleanos'
];

/*
// COLUMNAS COMPLETAS (COMENTADAS PARA FUTURAS EXPANSIONES)
$columnasCompletas = [
    'CodOperario',
    'Nombre',
    'Nombre2',
    'Apellido',
    'Apellido2',
    'clave',
    'clave_hash',
    'Operativo',
    'Celular',
    'Cedula',
    'Genero',
    'Inicio',
    'Fin',
    'Cumpleanos',
    'Sucursal',
    'Ciudad',
    'CodClub',
    'Cargo',
    'FechaRegistro',
    'usuario',
    'cb_numero',
    'contacto_numero',
    'contacto_nombre',
    'direccion',
    'telefono_casa',
    'telefono_corporativo',
    'email_personal',
    'email_trabajo',
    'foto_perfil',
    'codigo_inss',
    'cb_titular',
    'cb_banco',
    'cb_moneda',
    'segurosocial',
    'InicioSeguro',
    'FinSeguro',
    'registrado_por',
    'fecha_hora_regsys',
    'hospital_riesgo_laboral',
    'FechaCreacion'
];
*/

// Definir nombres amigables para las columnas
$nombresColumnas = [
    'CodOperario' => 'ID Operario',
    'Nombre' => 'Primer Nombre',
    'Nombre2' => 'Segundo Nombre',
    'Apellido' => 'Primer Apellido',
    'Apellido2' => 'Segundo Apellido',
    'Celular' => 'Celular',
    'Cedula' => 'Cédula',
    'Cumpleanos' => 'Fecha Cumpleaños'
];

/*
// NOMBRES AMIGABLES COMPLETOS (COMENTADOS)
$nombresColumnasCompletos = [
    'CodOperario' => 'ID Operario',
    'Nombre' => 'Primer Nombre',
    'Nombre2' => 'Segundo Nombre',
    'Apellido' => 'Primer Apellido',
    'Apellido2' => 'Segundo Apellido',
    'clave' => 'Contraseña',
    'clave_hash' => 'Hash Contraseña',
    'Operativo' => 'Operativo',
    'Celular' => 'Celular',
    'Cedula' => 'Cédula',
    'Genero' => 'Género',
    'Inicio' => 'Fecha Inicio',
    'Fin' => 'Fecha Fin',
    'Cumpleanos' => 'Cumpleaños',
    'Sucursal' => 'Sucursal',
    'Ciudad' => 'Ciudad',
    'CodClub' => 'Código Club',
    'Cargo' => 'Cargo',
    'FechaRegistro' => 'Fecha Registro',
    'usuario' => 'Usuario Sistema',
    'cb_numero' => 'N° Cuenta Bancaria',
    'contacto_numero' => 'N° Contacto Emergencia',
    'contacto_nombre' => 'Nombre Contacto Emergencia',
    'direccion' => 'Dirección',
    'telefono_casa' => 'Teléfono Casa',
    'telefono_corporativo' => 'Teléfono Corporativo',
    'email_personal' => 'Email Personal',
    'email_trabajo' => 'Email Trabajo',
    'foto_perfil' => 'Foto Perfil',
    'codigo_inss' => 'Código INSS',
    'cb_titular' => 'Titular Cuenta',
    'cb_banco' => 'Banco',
    'cb_moneda' => 'Moneda',
    'segurosocial' => 'Seguro Social',
    'InicioSeguro' => 'Inicio Seguro',
    'FinSeguro' => 'Fin Seguro',
    'registrado_por' => 'Registrado Por',
    'fecha_hora_regsys' => 'Fecha Registro Sistema',
    'hospital_riesgo_laboral' => 'Hospital Riesgo Laboral',
    'FechaCreacion' => 'Fecha Creación'
];
*/
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Operarios</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            font-family: 'Calibri', sans-serif;
            text-align: center;
            align-content: center;
            align-items: center;
            justify-content: center;
            font-size: clamp(10px, 1.5vw, 12px);
        }

        body {
            margin-top: 25px;
            background-color: #F6F6F6;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 99%;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
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

        .contenedor-principal {
            width: 100%;
            max-width: 99%;
            margin: 0 auto;
            padding: 0 1px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            font-size: 11px;
        }

        th, td {
            padding: 6px;
            border: 1px solid #ddd;
            white-space: nowrap;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        th {
            background-color: #51B8AC;
            color: white;
            position: sticky;
            top: 0;
            z-index: 10;
            font-weight: bold;
        }

        /* Estilos para celdas editables */
        .editable {
            cursor: pointer;
            transition: background-color 0.3s;
            position: relative;
        }
        
        .editable:hover {
            background-color: #e6f7f5;
        }
        
        .celda-editando {
            background-color: #e6f7f5;
            padding: 0 !important;
        }
        
        .contenedor-edicion {
            display: flex;
            align-items: center;
            position: relative;
            width: 100%;
            height: 100%;
        }
        
        .input-edicion {
            width: calc(100% - 40px);
            padding: 6px 35px 6px 6px !important;
            border: 1px solid #51B8AC;
            border-radius: 4px;
            text-align: center;
            font-size: inherit;
            box-sizing: border-box;
        }
        
        .select-edicion {
            width: calc(100% - 40px);
            padding: 6px 35px 6px 6px !important;
            border: 1px solid #51B8AC;
            border-radius: 4px;
            text-align: center;
            font-size: inherit;
            box-sizing: border-box;
        }
        
        .botones-edicion {
            position: absolute;
            right: 3px;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            gap: 2px;
        }
        
        .btn-editar, .btn-cancelar-edicion {
            width: 18px;
            height: 18px;
            padding: 0;
            border: none;
            border-radius: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 8px;
        }
        
        .btn-editar {
            background-color: #51B8AC;
            color: white;
        }
        
        .btn-cancelar-edicion {
            background-color: #FF6F61;
            color: white;
        }
        
        .no-editable {
            cursor: not-allowed;
            background-color: #f5f5f5;
        }
        
        .success-message {
            background-color: #06D6A0;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .error-message {
            background-color: #FF6F61;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        /* Estilos para tabla responsive */
        .table-container {
            overflow-x: auto;
            width: 100%;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        /* Estilos para tooltip */
        .tooltip-container {
            position: relative;
            display: inline-block;
        }
        
        .tooltip-text {
            visibility: hidden;
            width: 200px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 11px;
        }
        
        .tooltip-text::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #333 transparent transparent transparent;
        }
        
        .tooltip-container:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        /* Estilos para scroll horizontal suave */
        .table-container::-webkit-scrollbar {
            height: 8px;
        }

        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: #51B8AC;
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb:hover {
            background: #0E544C;
        }

        @media (max-width: 768px) {
            * {
                font-size: 9px;
            }
            
            .header-container {
                flex-direction: row;
                align-items: center;
                gap: 8px;
            }
            
            .buttons-container {
                position: static;
                transform: none;
                order: 3;
                width: 100%;
                justify-content: center;
                margin-top: 8px;
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
                padding: 5px 8px;
                font-size: 11px;
            }
            
            table {
                font-size: 9px;
            }
            
            th, td {
                padding: 4px;
                max-width: 120px;
            }

            .search-box input {
                width: 180px;
                font-size: 11px;
            }
        }
        
        @media (max-width: 480px) {
            * {
                font-size: 8px;
            }
            
            .btn-agregar {
                flex-grow: 1;
                justify-content: center;
                white-space: normal;
                text-align: center;
                padding: 6px 4px;
            }
            
            .user-info {
                flex-direction: column;
                align-items: flex-end;
            }
            
            table {
                font-size: 8px;
            }
            
            th, td {
                padding: 3px;
                max-width: 100px;
            }

            .search-box input {
                width: 150px;
                font-size: 10px;
            }

            .filtros-container {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                width: 100%;
                justify-content: center;
            }

            .filtros-right {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <div class="contenedor-principal">
        <header>
            <div class="header-container">
                <div class="logo-container">
                    <img src="../../assets/img/Logo.svg" alt="Batidos Pitaya" class="logo">
                </div>
                
                <div class="buttons-container">
                    <a href="Operarios.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'Operarios.php' ? 'activo' : '' ?>">
                        <i class="fas fa-users"></i> <span class="btn-text">Operarios</span>
                    </a>
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
                    <a href="../../../index.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </header>
        
        <h2>Gestión de Operarios - Información Básica</h2>
        
        <?php if (isset($_GET['success'])): ?>
            <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Éxito',
                    text: 'Los cambios han sido guardados exitosamente.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            </script>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <script>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudieron guardar los cambios.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            </script>
        <?php endif; ?>
        
        <!-- Filtros y búsqueda -->
        <div class="filtros-container">
            <div class="search-box">
                <input type="text" id="buscarOperario" placeholder="Buscar en todos los campos..." onkeyup="filtrarTabla()">
                <button class="btn-filtrar" onclick="filtrarTabla()">
                    <i class="fas fa-search"></i> Buscar
                </button>
            </div>
            
            <div class="filtros-right">
                <button class="btn-filtrar" onclick="mostrarTodos()">
                    <i class="fas fa-sync"></i> Mostrar Todos
                </button>
                <span class="contador" id="contadorResultados">
                    Mostrando <?php echo count($operarios); ?> operarios
                </span>
            </div>
        </div>
        
        <!-- Tabla de operarios con COLUMNAS ESPECÍFICAS -->
        <div class="table-container">
            <table id="tablaOperarios">
                <thead>
                    <tr>
                        <?php foreach ($columnasMostrar as $columna): ?>
                            <?php if (isset($nombresColumnas[$columna])): ?>
                                <th title="<?php echo htmlspecialchars($nombresColumnas[$columna]); ?>">
                                    <?php echo htmlspecialchars($nombresColumnas[$columna]); ?>
                                </th>
                            <?php else: ?>
                                <th><?php echo htmlspecialchars($columna); ?></th>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($operarios as $operario): ?>
                    <tr>
                        <?php foreach ($columnasMostrar as $columna): ?>
                            <td 
                                <?php if (!in_array($columna, $camposNoEditables)): ?>
                                    class="editable"
                                    onclick="iniciarEdicion(this, <?php echo $operario['CodOperario']; ?>, '<?php echo $columna; ?>')"
                                    data-cod-operario="<?php echo $operario['CodOperario']; ?>"
                                    data-campo="<?php echo $columna; ?>"
                                <?php else: ?>
                                    class="no-editable"
                                <?php endif; ?>
                                title="<?php echo htmlspecialchars($operario[$columna] ?? ''); ?>"
                            >
                                <?php 
                                $valor = $operario[$columna] ?? '';
                                
                                // MOSTRAR VALORES NETOS - SIN FORMATEO
                                $valorMostrar = htmlspecialchars($valor);
                                if (strlen($valorMostrar) > 20) {
                                    echo substr($valorMostrar, 0, 20) . '...';
                                } else {
                                    echo $valorMostrar;
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Variable para controlar la celda en edición
        let celdaEditando = null;
        let valorOriginal = null;
    
        // Función para iniciar edición
        function iniciarEdicion(celda, codOperario, campo) {
            // Cerrar edición actual si existe
            if (celdaEditando && celdaEditando !== celda) {
                cancelarEdicion();
            }
            
            // Guardar referencia y valor original
            celdaEditando = celda;
            valorOriginal = celda.textContent.trim();
            
            // Crear campo de edición
            celda.classList.add('celda-editando');
            celda.innerHTML = `
                <div class="contenedor-edicion">
                    <input type="text" class="input-edicion" value="${valorOriginal}" autofocus>
                    <div class="botones-edicion">
                        <button class="btn-editar" onclick="guardarEdicion(${codOperario}, '${campo}')">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn-cancelar-edicion" onclick="cancelarEdicion()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
            
            // Enfocar el input
            const input = celda.querySelector('input');
            if (input) {
                input.focus();
                input.select();
            }
        }
        
        // Función para guardar edición
        function guardarEdicion(codOperario, campo) {
            if (!celdaEditando) return;
            
            const input = celdaEditando.querySelector('input');
            const valor = input.value;
            
            Swal.fire({
                title: '¿Guardar cambios?',
                text: `¿Desea actualizar el campo para el operario ${codOperario}?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#51B8AC',
                cancelButtonColor: '#FF6F61',
                confirmButtonText: 'Guardar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    enviarDatosEdicion(codOperario, campo, valor);
                }
            });
        }
        
        // Función para enviar datos al servidor
        function enviarDatosEdicion(codOperario, campo, valor) {
            const formData = new FormData();
            formData.append('guardar_edicion', true);
            formData.append('cod_operario', codOperario);
            formData.append('campo', campo);
            formData.append('valor', valor);
            
            fetch('Operarios.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    location.reload();
                } else {
                    throw new Error('Error en la respuesta');
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo guardar',
                    confirmButtonColor: '#51B8AC'
                });
            });
        }
        
        // Función para cancelar edición
        function cancelarEdicion() {
            if (!celdaEditando) return;
            
            // Restaurar contenido original
            celdaEditando.classList.remove('celda-editando');
            celdaEditando.innerHTML = valorOriginal;
            
            // Restaurar el evento onclick
            const codOperario = celdaEditando.getAttribute('data-cod-operario');
            const campo = celdaEditando.getAttribute('data-campo');
            celdaEditando.onclick = function() { 
                iniciarEdicion(this, codOperario, campo); 
            };
            
            // Limpiar variables
            celdaEditando = null;
            valorOriginal = null;
        }
        
        // Función para filtrar tabla
        function filtrarTabla() {
            const input = document.getElementById('buscarOperario');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('tablaOperarios');
            const tr = table.getElementsByTagName('tr');
            
            let contador = 0;
            
            // Empezar desde 1 para saltar el header
            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td');
                let mostrar = false;
                
                for (let j = 0; j < td.length; j++) {
                    if (td[j]) {
                        const txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toLowerCase().indexOf(filter) > -1) {
                            mostrar = true;
                            break;
                        }
                    }
                }
                
                if (mostrar) {
                    tr[i].style.display = '';
                    contador++;
                } else {
                    tr[i].style.display = 'none';
                }
            }
            
            document.getElementById('contadorResultados').textContent = `Mostrando ${contador} operarios`;
        }
        
        // Función para mostrar todos los registros
        function mostrarTodos() {
            document.getElementById('buscarOperario').value = '';
            filtrarTabla();
        }
        
        // Manejo de teclado
        document.addEventListener('keydown', function(e) {
            if (!celdaEditando) return;
            
            const input = celdaEditando.querySelector('input');
            if (!input) return;
            
            if (e.key === 'Enter') {
                const codOperario = celdaEditando.getAttribute('data-cod-operario');
                const campo = celdaEditando.getAttribute('data-campo');
                guardarEdicion(codOperario, campo);
            } else if (e.key === 'Escape') {
                cancelarEdicion();
            }
        });

        // Auto-focus en la búsqueda al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('buscarOperario').focus();
        });
    </script>
</body>
</html>