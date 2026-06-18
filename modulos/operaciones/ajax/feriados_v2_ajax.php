<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'] ?? null;

// Verificar permiso general
if (!tienePermiso('feriados_v2', 'vista', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['error' => 'No tiene permisos para acceder a esta herramienta']);
    exit;
}


$puedeAprobar = tienePermiso('feriados_v2', 'aprobar', $cargoOperario);
$puedeCrear = tienePermiso('feriados_v2', 'crear', $cargoOperario);
$puedeImprimir = tienePermiso('feriados_v2', 'imprimir', $cargoOperario);

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'obtener_feriados_sucursal':
            $codSucursal = $_GET['sucursal'] ?? '';
            if (empty($codSucursal)) {
                throw new Exception('Debe especificar una sucursal');
            }


            // Obtener el cod_departamento de la sucursal
            $stmtSuc = $conn->prepare("SELECT cod_departamento FROM sucursales WHERE codigo = ? LIMIT 1");
            $stmtSuc->execute([$codSucursal]);
            $sucRow = $stmtSuc->fetch(PDO::FETCH_ASSOC);
            if (!$sucRow) {
                throw new Exception('Sucursal no encontrada');
            }
            $codDepartamento = $sucRow['cod_departamento'];

            // Obtener feriados: nacionales + los del departamento de la sucursal
            // Ordenados por fecha desc para mostrar los más recientes primero
            $stmtFeriados = $conn->prepare("
                SELECT f.id, f.fecha, f.nombre, f.tipo,
                       d.nombre AS departamento_nombre
                FROM feriadosnic f
                LEFT JOIN departamentos d ON f.departamento_codigo = d.codigo
                WHERE (f.departamento_codigo IS NULL OR f.departamento_codigo = ?)
                ORDER BY f.fecha DESC
            ");
            $stmtFeriados->execute([$codDepartamento]);
            $feriados = $stmtFeriados->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($feriados);
            break;

        case 'obtener_operarios':
            $codSucursal = $_GET['sucursal'] ?? '';
            if (empty($codSucursal)) {
                throw new Exception('Debe especificar una sucursal');
            }

            $fechaReferencia = $_GET['fecha'] ?? date('Y-m-d');
            if (empty($fechaReferencia)) {
                $fechaReferencia = date('Y-m-d');
            }

            // Traer colaboradores activos de la sucursal (excluyendo cargo 27 e inactivos/liquidados)
            // Usamos CONCAT_WS + NULLIF para el nombre completo
            $stmt = $conn->prepare("
                SELECT DISTINCT o.CodOperario, 
                       CONCAT_WS(' ',
                           TRIM(o.Nombre),
                           NULLIF(TRIM(o.Nombre2), ''),
                           TRIM(o.Apellido),
                           NULLIF(TRIM(o.Apellido2), '')
                       ) as nombre_completo
                FROM Operarios o
                INNER JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
                LEFT JOIN (
                    -- Obtener el último contrato de cada operario
                    SELECT c1.cod_operario, c1.fecha_liquidacion
                    FROM Contratos c1
                    INNER JOIN (
                        SELECT cod_operario, MAX(CodContrato) as max_contrato
                        FROM Contratos
                        GROUP BY cod_operario
                    ) c2 ON c1.cod_operario = c2.cod_operario AND c1.CodContrato = c2.max_contrato
                ) c ON o.CodOperario = c.cod_operario
                WHERE anc.Sucursal = ?
                AND o.Operativo = 1
                AND anc.Fecha <= ?
                AND (anc.Fin IS NULL OR anc.Fin = '0000-00-00' OR anc.Fin >= ?)
                AND o.CodOperario NOT IN (
                    SELECT DISTINCT anc2.CodOperario 
                    FROM AsignacionNivelesCargos anc2
                    WHERE anc2.CodNivelesCargos = 27
                    AND anc2.Fecha <= ?
                    AND (anc2.Fin IS NULL OR anc2.Fin = '0000-00-00' OR anc2.Fin >= ?)
                )
                -- Filtrar liquidados
                AND (
                    c.fecha_liquidacion IS NULL 
                    OR c.fecha_liquidacion = '0000-00-00'
                    OR c.fecha_liquidacion > ?
                )
                ORDER BY o.Nombre, o.Apellido
            ");
            $stmt->execute([$codSucursal, $fechaReferencia, $fechaReferencia, $fechaReferencia, $fechaReferencia, $fechaReferencia]);
            $operarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($operarios);
            break;

        case 'guardar_solicitud':
            if (!$puedeCrear) {
                throw new Exception('No tiene permisos para crear solicitudes de feriados');
            }
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido');
            }

            $codOperario = (int) $_POST['cod_operario'];
            $fechaFeriado = $_POST['fecha_feriado'];
            $observaciones = $_POST['observaciones'] ?? '';

            if (!$codOperario || !$fechaFeriado) {
                throw new Exception('Todos los campos son obligatorios');
            }

            // Validar que no exista ya un registro para este operario y fecha
            $stmt_dup = $conn->prepare("SELECT id FROM FeriadosStatus WHERE cod_operario = ? AND fecha_feriado = ?");
            $stmt_dup->execute([$codOperario, $fechaFeriado]);
            if ($stmt_dup->fetch()) {
                throw new Exception('Ya existe una solicitud o registro de feriado para este colaborador en esta fecha');
            }

            // Obtener el último contrato activo
            $codContrato = null;
            $stmt_contrato = $conn->prepare("
                SELECT CodContrato 
                FROM Contratos 
                WHERE cod_operario = ? 
                ORDER BY inicio_contrato DESC, CodContrato DESC 
                LIMIT 1
            ");
            $stmt_contrato->execute([$codOperario]);
            $contrato = $stmt_contrato->fetch(PDO::FETCH_ASSOC);
            if ($contrato) {
                $codContrato = $contrato['CodContrato'];
            }

            // Buscar si hay marcación de reloj para esa fecha y operario
            $stmt_marcacion = $conn->prepare("
                SELECT id, hora_ingreso, hora_salida 
                FROM marcaciones 
                WHERE CodOperario = ? AND fecha = ?
                LIMIT 1
            ");
            $stmt_marcacion->execute([$codOperario, $fechaFeriado]);
            $marcacion = $stmt_marcacion->fetch(PDO::FETCH_ASSOC);

            $idMarcacion = null;
            $horasTrabajadas = 0;

            if ($marcacion) {
                $idMarcacion = $marcacion['id'];
                if (!empty($marcacion['hora_ingreso']) && !empty($marcacion['hora_salida'])) {
                    $entrada = new DateTime($marcacion['hora_ingreso']);
                    $salida = new DateTime($marcacion['hora_salida']);
                    $diferencia = $salida->diff($entrada);
                    $horasTrabajadas = $diferencia->h + ($diferencia->i / 60);
                }
            }

            // Registrar solicitud en estado 'Pendiente'
            $stmt_insert = $conn->prepare("
                INSERT INTO FeriadosStatus (
                    id_marcacion, cod_operario, fecha_feriado, horas_trabajadas, 
                    cod_contrato, estado, observaciones, creado_por, fecha_creacion
                ) VALUES (?, ?, ?, ?, ?, 'Pendiente', ?, ?, NOW())
            ");

            $ok = $stmt_insert->execute([
                $idMarcacion,
                $codOperario,
                $fechaFeriado,
                $horasTrabajadas,
                $codContrato,
                $observaciones,
                $_SESSION['usuario_id']
            ]);

            if ($ok) {
                echo json_encode(['success' => true, 'message' => 'Solicitud de feriado registrada en estado Pendiente']);
            } else {
                throw new Exception('Error al registrar la solicitud en la base de datos');
            }
            break;

        case 'editar_aprobar':
            if (!$puedeAprobar) {
                throw new Exception('No tiene permisos para aprobar o editar solicitudes');
            }
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido');
            }

            $id = (int) $_POST['id'];
            $estado = $_POST['estado'];
            $observaciones = $_POST['observaciones'] ?? '';

            if (!$id || !$estado) {
                throw new Exception('Parámetros incompletos');
            }

            $estadosPermitidos = ['Pendiente', 'Pagado', 'Descansado'];
            if (!in_array($estado, $estadosPermitidos)) {
                throw new Exception('Estado no válido');
            }

            $stmt_upd = $conn->prepare("
                UPDATE FeriadosStatus 
                SET estado = ?, 
                    observaciones = ?,
                    actualizado_por = ?,
                    fecha_actualizacion = NOW()
                WHERE id = ?
            ");

            $ok = $stmt_upd->execute([
                $estado,
                $observaciones,
                $_SESSION['usuario_id'],
                $id
            ]);

            if ($ok) {
                echo json_encode(['success' => true, 'message' => 'Solicitud actualizada correctamente']);
            } else {
                throw new Exception('Error al actualizar el registro en la base de datos');
            }
            break;

        case 'eliminar_rechazar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido');
            }

            $id = (int) $_POST['id'];
            if (!$id) {
                throw new Exception('ID no especificado');
            }

            // Cargar registro
            $stmt = $conn->prepare("SELECT creado_por, estado FROM FeriadosStatus WHERE id = ?");
            $stmt->execute([$id]);
            $registro = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$registro) {
                throw new Exception('Registro no encontrado');
            }

            // Líderes solo pueden eliminar sus propias solicitudes en estado 'Pendiente'
            if (!$puedeAprobar) {
                if ($registro['creado_por'] != $_SESSION['usuario_id'] || $registro['estado'] !== 'Pendiente') {
                    throw new Exception('No tiene permisos para eliminar este registro');
                }
            }

            // Eliminar
            $stmt_del = $conn->prepare("DELETE FROM FeriadosStatus WHERE id = ?");
            $stmt_del->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'Solicitud eliminada/rechazada correctamente']);
            break;

        case 'listar_para_imprimir':
            // Solo quien tiene permiso 'imprimir' puede listar para imprimir
            if (!$puedeImprimir) {
                throw new Exception('No tiene permisos para imprimir fichas de feriados');
            }

            $anio = isset($_GET['anio']) ? intval($_GET['anio']) : (int) date('Y');
            if ($anio < 2020 || $anio > 2100) {
                throw new Exception('Año inválido');
            }

            $puedeVerTodasSucursales = tienePermiso('feriados_v2', 'ver_todas_sucursales', $cargoOperario);

            $sqlImpr = "
                SELECT fs.id, fs.fecha_feriado, fs.horas_trabajadas, fs.estado,
                       CONCAT_WS(' ',
                           TRIM(o.Nombre),
                           NULLIF(TRIM(o.Nombre2), ''),
                           TRIM(o.Apellido),
                           NULLIF(TRIM(o.Apellido2), '')
                       ) as nombre_completo,
                       COALESCE(s.nombre, s_actual.nombre, 'Sin sucursal') as sucursal_nombre,
                       GROUP_CONCAT(DISTINCT fn.nombre SEPARATOR ' / ') as feriado_nombre
                FROM FeriadosStatus fs
                INNER JOIN Operarios o ON fs.cod_operario = o.CodOperario
                LEFT JOIN Contratos c ON fs.cod_contrato = c.CodContrato
                LEFT JOIN sucursales s ON c.cod_sucursal_contrato = s.codigo
                LEFT JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
                    AND fs.fecha_feriado >= anc.Fecha
                    AND (anc.Fin IS NULL OR anc.Fin = '0000-00-00' OR fs.fecha_feriado <= anc.Fin)
                LEFT JOIN sucursales s_actual ON anc.Sucursal = s_actual.codigo
                LEFT JOIN feriadosnic fn ON fs.fecha_feriado = fn.fecha
                    AND (fn.departamento_codigo IS NULL OR fn.departamento_codigo = COALESCE(s.cod_departamento, s_actual.cod_departamento))
                WHERE YEAR(fs.fecha_feriado) = ?
            ";
            $paramsImpr = [$anio];

            // Si NO puede ver todas las sucursales, filtrar por las propias
            if (!$puedeVerTodasSucursales) {
                require_once '../../../core/layout/menu_lateral.php';
                $sucursalesLider = obtenerSucursalesUsuario($_SESSION['usuario_id']);
                $codigos = array_column($sucursalesLider, 'codigo');
                if (!empty($codigos)) {
                    $placeholders = implode(',', array_fill(0, count($codigos), '?'));
                    $sqlImpr .= " AND COALESCE(s.codigo, s_actual.codigo) IN ($placeholders)";
                    foreach ($codigos as $cod) {
                        $paramsImpr[] = $cod;
                    }
                } else {
                    echo json_encode([]);
                    break;
                }
            }

            $sqlImpr .= " GROUP BY fs.id ORDER BY fs.fecha_feriado DESC, nombre_completo ASC LIMIT 500";

            $stmtImpr = $conn->prepare($sqlImpr);
            $stmtImpr->execute($paramsImpr);
            $registros = $stmtImpr->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($registros);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>