<?php
ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Al inicio del archivo, verificar autenticación y acceso al módulo
require_once '../../core/auth/auth.php';

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
    $codContrato = $_POST['cod_contrato'];
    $campo = $_POST['campo'];
    $valor = $_POST['valor'];
    
    // Obtener el ID del usuario que está modificando
    $usuario_ultima_modificacion = $_SESSION['usuario_id'];
    $fecha_actualizacion = date('Y-m-d H:i:s');
    
    // Lista de campos que NO se pueden editar
    $camposNoEditables = [
        'CodContrato', 
        'fecha_hora_reg_sys',
        'cod_usuario_creador',
        'fecha_ultima_modificacion',
        'usuario_ultima_modificacion'
    ];
    
    // Validar que el campo sea editable
    if (in_array($campo, $camposNoEditables)) {
        $_SESSION['error'] = 'Este campo no se puede editar';
        header("Location: Contratos.php?error=1");
        exit();
    }
    
    // Manejar campos de fecha específicos
    if (in_array($campo, ['inicio_contrato', 'fin_contrato', 'fecha_salida', 'fecha_liquidacion'])) {
        if (!empty($valor)) {
            $valor = date('Y-m-d', strtotime($valor));
        } else {
            $valor = null;
        }
    }
    
    // Manejar campos decimales
    if (in_array($campo, ['monto_contrato', 'salario_inicial', 'monto_indemnizacion'])) {
        if (!empty($valor)) {
            $valor = floatval(str_replace(',', '', $valor));
        } else {
            $valor = null;
        }
    }
    
    // Manejar campos enteros
    if (in_array($campo, ['cod_tipo_contrato', 'cod_operario', 'CodSalario', 'cod_tipo_salida', 'dias_trabajados', 'numero_planilla', 'CodAsignacionNivelesCargos'])) {
        if (!empty($valor)) {
            $valor = intval($valor);
        } else {
            $valor = null;
        }
    }
    
    // Manejar campo bit para devolucion_herramientas_trabajo
    if ($campo === 'devolucion_herramientas_trabajo') {
        $valor = ($valor == '1' || $valor == 'true' || $valor == 'on') ? 1 : 0;
    }
    
    // Preparar la consulta de actualización
    $query = "UPDATE Contratos SET $campo = :valor, fecha_ultima_modificacion = :fecha_actualizacion, usuario_ultima_modificacion = :usuario_ultima_modificacion WHERE CodContrato = :cod_contrato";
    $stmt = $conn->prepare($query);
    
    try {
        $stmt->execute([
            ':valor' => $valor,
            ':fecha_actualizacion' => $fecha_actualizacion,
            ':usuario_ultima_modificacion' => $usuario_ultima_modificacion,
            ':cod_contrato' => $codContrato
        ]);
        
        $_SESSION['success'] = 'Cambio guardado exitosamente';
        header("Location: Contratos.php?success=1");
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error al guardar: ' . $e->getMessage();
        header("Location: Contratos.php?error=1");
        exit();
    }
}

// Obtener contratos activos (sin fecha de salida)
$query_contratos = "SELECT 
                    c.CodContrato,
                    c.cod_tipo_contrato,
                    c.codigo_manual_contrato,
                    c.cod_operario,
                    c.inicio_contrato,
                    c.fin_contrato,
                    c.ciudad,
                    c.salario_inicial,
                    c.frecuencia_pago,
                    CONCAT(
                        IFNULL(o.Nombre, ''), 
                        IF(o.Nombre2 IS NOT NULL AND o.Nombre2 != '', CONCAT(' ', o.Nombre2), ''), 
                        IFNULL(CONCAT(' ', o.Apellido), ''), 
                        IF(o.Apellido2 IS NOT NULL AND o.Apellido2 != '', CONCAT(' ', o.Apellido2), '')
                    ) as nombre_completo,
                    tc.nombre as tipo_contrato_nombre
                    /* 
                    COLUMNAS COMENTADAS PARA FUTURAS IMPLEMENTACIONES:
                    c.foto,
                    c.CodSalario,
                    c.monto_contrato,
                    c.fecha_salida,
                    c.fecha_liquidacion,
                    c.motivo,
                    c.cod_tipo_salida,
                    c.observaciones,
                    c.cod_sucursal_contrato,
                    c.cod_usuario_creador,
                    c.CodAsignacionNivelesCargos,
                    c.foto_solicitud_renuncia,
                    c.devolucion_herramientas_trabajo,
                    c.persona_recibe_herramientas_trabajo,
                    c.fecha_hora_reg_sys,
                    c.dias_trabajados,
                    c.monto_indemnizacion,
                    c.numero_planilla,
                    c.hospital_inss,
                    c.fecha_ultima_modificacion,
                    c.usuario_ultima_modificacion,
                    ts.nombre as tipo_salida_nombre,
                    s.nombre as sucursal_nombre,
                    ucreador.Nombre as usuario_creador_nombre,
                    umodificador.Nombre as usuario_modificador_nombre
                    */
                FROM Contratos c
                LEFT JOIN Operarios o ON c.cod_operario = o.CodOperario
                LEFT JOIN TipoContrato tc ON c.cod_tipo_contrato = tc.CodTipoContrato
                /* 
                TABLAS COMENTADAS PARA FUTURAS IMPLEMENTACIONES:
                LEFT JOIN TipoSalida ts ON c.cod_tipo_salida = ts.CodTipoSalida
                LEFT JOIN sucursales s ON c.cod_sucursal_contrato = s.codigo
                LEFT JOIN Operarios ucreador ON c.cod_usuario_creador = ucreador.CodOperario
                LEFT JOIN Operarios umodificador ON c.usuario_ultima_modificacion = umodificador.CodOperario
                */
                WHERE c.fecha_salida IS NULL OR c.fecha_salida = ''
                ORDER BY c.CodContrato DESC";
$stmt_contratos = $conn->prepare($query_contratos);
$stmt_contratos->execute();
$contratos = $stmt_contratos->fetchAll(PDO::FETCH_ASSOC);

// Definir campos no editables
$camposNoEditables = [
    'CodContrato', 
    'fecha_hora_reg_sys',
    'cod_usuario_creador',
    'fecha_ultima_modificacion',
    'usuario_ultima_modificacion'
];

// Definir nombres amigables para las columnas
$nombresColumnas = [
    'CodContrato' => 'ID Contrato',
    'nombre_completo' => 'Nombre',
    'tipo_contrato_nombre' => 'Tipo Contrato',
    'codigo_manual_contrato' => 'Código Manual',
    'inicio_contrato' => 'Fecha Inicio',
    'fin_contrato' => 'Fecha Fin',
    'ciudad' => 'Ciudad',
    'salario_inicial' => 'Salario Inicial',
    'frecuencia_pago' => 'Frecuencia Pago'
];

// Definir el orden específico de columnas que deseas mostrar
$columnasPrincipales = [
    'CodContrato',
    'nombre_completo',
    'tipo_contrato_nombre',
    'codigo_manual_contrato',
    'inicio_contrato',
    'fin_contrato',
    'ciudad',
    'salario_inicial',
    'frecuencia_pago'
];

// Filtrar solo las columnas que existen en los resultados
$columnasMostrar = [];
foreach ($columnasPrincipales as $columna) {
    if (isset($contratos[0][$columna])) {
        $columnasMostrar[] = $columna;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Contratos</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
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

        /* Estilos para búsqueda y filtros */
        .filtros-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
            padding: 8px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-box input {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 250px;
            font-size: 12px;
        }

        .filtros-right {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .btn-filtrar {
            background-color: #51B8AC;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
            font-size: 12px;
        }

        .btn-filtrar:hover {
            background-color: #0E544C;
        }

        .contador {
            color: #666;
            font-size: 12px;
            white-space: nowrap;
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
        
        /* Estilo específico para la columna de nombre completo */
        td[data-campo="nombre_completo"] {
            white-space: normal !important;
            min-width: 200px;
            max-width: none !important;
        }
    </style>
</head>
<body>
    <div class="contenedor-principal">
        <header>
            <div class="header-container">
                <div class="logo-container">
                    <img src="../../core/assets/img/Logo.svg" alt="Batidos Pitaya" class="logo">
                </div>
                
                <div class="buttons-container">
                    <a href="Contratos.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'Contratos.php' ? 'activo' : '' ?>">
                        <i class="fas fa-file-contract"></i> <span class="btn-text">Contratos</span>
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
        
        <h2>Gestión de Contratos Activos - Sin Fecha de Salida</h2>
        
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
                <input type="text" id="buscarContrato" placeholder="Buscar en todos los campos..." onkeyup="filtrarTabla()">
                <button class="btn-filtrar" onclick="filtrarTabla()">
                    <i class="fas fa-search"></i> Buscar
                </button>
            </div>
            
            <div class="filtros-right">
                <button class="btn-filtrar" onclick="mostrarTodos()">
                    <i class="fas fa-sync"></i> Mostrar Todos
                </button>
                <span class="contador" id="contadorResultados">
                    Mostrando <?php echo count($contratos); ?> contratos
                </span>
            </div>
        </div>
        
        <!-- Tabla de contratos con columnas específicas -->
        <div class="table-container">
            <table id="tablaContratos">
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
    <?php foreach ($contratos as $contrato): ?>
    <tr>
        <?php foreach ($columnasMostrar as $columna): ?>
            <td 
                <?php if (!in_array($columna, $camposNoEditables) && $columna !== 'CodContrato' && $columna !== 'nombre_completo' && $columna !== 'tipo_contrato_nombre'): ?>
                    class="editable"
                    onclick="iniciarEdicion(this, <?php echo $contrato['CodContrato']; ?>, '<?php echo $columna; ?>')"
                    data-cod-contrato="<?php echo $contrato['CodContrato']; ?>"
                    data-campo="<?php echo $columna; ?>"
                <?php else: ?>
                    class="no-editable"
                <?php endif; ?>
                title="<?php echo htmlspecialchars($contrato[$columna] ?? ''); ?>"
                <?php if ($columna === 'nombre_completo'): ?>
                    style="white-space: normal; min-width: 200px; text-align: left;"
                <?php endif; ?>
            >
                <?php 
                $valor = $contrato[$columna] ?? '';
                
                // MOSTRAR VALORES NETOS - SIN FORMATEO ESPECIAL
                // Solo mostrar el valor tal cual viene de la base de datos
                echo htmlspecialchars($valor);
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
        function iniciarEdicion(celda, codContrato, campo) {
            // Cerrar edición actual si existe
            if (celdaEditando && celdaEditando !== celda) {
                cancelarEdicion();
            }
            
            // Guardar referencia y valor original
            celdaEditando = celda;
            valorOriginal = celda.textContent.trim();
            
            // Determinar el tipo de campo para el input adecuado
            const esFecha = ['inicio_contrato', 'fin_contrato'].includes(campo);
            const esFrecuenciaPago = campo === 'frecuencia_pago';
            
            let inputHTML = '';
            
            if (esFrecuenciaPago) {
                // Select para frecuencia de pago
                inputHTML = `
                    <select class="select-edicion" autofocus>
                        <option value="quincenal" ${valorOriginal === 'quincenal' ? 'selected' : ''}>Quincenal</option>
                        <option value="mensual" ${valorOriginal === 'mensual' ? 'selected' : ''}>Mensual</option>
                        <option value="semanal" ${valorOriginal === 'semanal' ? 'selected' : ''}>Semanal</option>
                    </select>
                `;
            } else if (esFecha) {
                // Input de fecha
                let fechaValor = '';
                if (valorOriginal && valorOriginal !== '') {
                    // Convertir formato Y-m-d a Y-m-d para el input date (ya viene neto de BD)
                    fechaValor = valorOriginal;
                }
                inputHTML = `<input type="date" class="input-edicion" value="${fechaValor}" autofocus>`;
            } else {
                // Input de texto normal - VALOR NETO
                inputHTML = `<input type="text" class="input-edicion" value="${valorOriginal}" autofocus>`;
            }
            
            // Crear campo de edición
            celda.classList.add('celda-editando');
            celda.innerHTML = `
                <div class="contenedor-edicion">
                    ${inputHTML}
                    <div class="botones-edicion">
                        <button class="btn-editar" onclick="guardarEdicion(${codContrato}, '${campo}')">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn-cancelar-edicion" onclick="cancelarEdicion()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
            
            // Enfocar el input/select
            const input = celda.querySelector('input, select');
            if (input) {
                input.focus();
                if (input.type !== 'date') {
                    input.select();
                }
            }
        }
        
        // Función para guardar edición
        function guardarEdicion(codContrato, campo) {
            if (!celdaEditando) return;
            
            const input = celdaEditando.querySelector('input, select');
            let valor = input.value;
            
            // Validaciones específicas por tipo de campo
            const esFecha = ['inicio_contrato', 'fin_contrato'].includes(campo);
            
            if (esFecha && valor) {
                // Validar formato de fecha
                const fecha = new Date(valor);
                if (isNaN(fecha.getTime())) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Formato de fecha inválido',
                        confirmButtonColor: '#51B8AC'
                    });
                    input.focus();
                    return;
                }
            }
            
            Swal.fire({
                title: '¿Guardar cambios?',
                text: `¿Desea actualizar el campo ${campo} para el contrato ${codContrato}?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#51B8AC',
                cancelButtonColor: '#FF6F61',
                confirmButtonText: 'Guardar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    enviarDatosEdicion(codContrato, campo, valor);
                }
            });
        }
        
        // Función para enviar datos al servidor
        function enviarDatosEdicion(codContrato, campo, valor) {
            const formData = new FormData();
            formData.append('guardar_edicion', true);
            formData.append('cod_contrato', codContrato);
            formData.append('campo', campo);
            formData.append('valor', valor);
            
            fetch('Contratos.php', {
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
            const codContrato = celdaEditando.getAttribute('data-cod-contrato');
            const campo = celdaEditando.getAttribute('data-campo');
            celdaEditando.onclick = function() { 
                iniciarEdicion(this, codContrato, campo); 
            };
            
            // Limpiar variables
            celdaEditando = null;
            valorOriginal = null;
        }
        
        // Función para filtrar tabla
        function filtrarTabla() {
            const input = document.getElementById('buscarContrato');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('tablaContratos');
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
            
            document.getElementById('contadorResultados').textContent = `Mostrando ${contador} contratos`;
        }
        
        // Función para mostrar todos los registros
        function mostrarTodos() {
            document.getElementById('buscarContrato').value = '';
            filtrarTabla();
        }
        
        // Manejo de teclado
        document.addEventListener('keydown', function(e) {
            if (!celdaEditando) return;
            
            const input = celdaEditando.querySelector('input, select');
            if (!input) return;
            
            if (e.key === 'Enter') {
                const codContrato = celdaEditando.getAttribute('data-cod-contrato');
                const campo = celdaEditando.getAttribute('data-campo');
                guardarEdicion(codContrato, campo);
            } else if (e.key === 'Escape') {
                cancelarEdicion();
            }
        });

        // Auto-focus en la búsqueda al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('buscarContrato').focus();
        });
    </script>
</body>
</html>