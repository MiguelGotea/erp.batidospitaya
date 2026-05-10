<?php
//session_start(); // Asegurar que la sesión esté iniciada
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

require_once '../../core/auth/auth.php';
//******************************Estándar para header******************************

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([5, 43, 16]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([5, 43, 16]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

date_default_timezone_set('America/Managua'); // Zona horaria de Nicaragua (UTC-6)

// Obtener sucursales asignadas al líder
$sucursalesLider = obtenerSucursalesLider($_SESSION['usuario_id']);

// Obtener semana actual y siguiente con zona horaria correcta
$semanaActual = obtenerSemanaActual();
$hoy = new DateTime('now', new DateTimeZone('America/Managua'));
$horaActual = $hoy->format('H:i');
$hoyFecha = $hoy->format('Y-m-d');

// Obtener la semana siguiente a la actual
$semanaSiguiente = obtenerSemanaPorNumero($semanaActual['numero_semana'] + 1);

// Obtener datos para la vista
$semanaSeleccionada = $_GET['semana'] ?? $semanaActual['numero_semana'];
$sucursalSeleccionada = $_GET['sucursal'] ?? ($sucursalesLider[0]['codigo'] ?? null);

// Determinar si estamos en el período de edición (lunes 00:00 a viernes 23:59 hora de Nicaragua)
$periodoEdicion = false;
if ($semanaSiguiente) {
    $lunesSemanaActual = new DateTime($semanaActual['fecha_inicio'], new DateTimeZone('America/Managua'));
    $viernesSemanaActual = clone $lunesSemanaActual;
    $viernesSemanaActual->modify('+4 days'); // Viernes de la semana actual
    
    // Establecer horarios para el período de edición
    $lunesSemanaActual->setTime(0, 0, 0); // Lunes 00:00:00
    $viernesSemanaActual->setTime(23, 59, 59); // Viernes 23:59:59
    
    // Verificar si estamos en el período permitido (incluyendo zona horaria)
    if ($hoy >= $lunesSemanaActual && $hoy <= $viernesSemanaActual) {
        $periodoEdicion = true;
    }
}

// Determinar qué semana se puede editar (solo la siguiente en período de edición)
$semanaPermitida = null;
if ($periodoEdicion) {
    $semanaPermitida = $semanaSiguiente['numero_semana'];
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar_horarios'])) {
        // Verificar si está autorizado a editar (ahora incluye autorización de supervisión), este bloque lo comentamos cuando habilitamos autorización a los líderes
        //if ((!$periodoEdicion || $_POST['semana'] != $semanaPermitida) && !$horarioAutorizado) {
        //    $_SESSION['error'] = 'No tiene permiso para editar este horario';
        //    header('Location: programar_horarios_lider.php?semana='.$_POST['semana'].'&sucursal='.$_POST['sucursal']);
        //    exit();
        //}
        
        procesarHorarios($sucursalesLider);
    } elseif (isset($_POST['agregar_operario'])) {
        // Procesar agregar operario
        $codOperario = $_POST['cod_operario'];
        $semanaSeleccionada = $_POST['semana'];
        $sucursalSeleccionada = $_POST['sucursal'];
        
        // Verificar si el operario ya está en la lista
        $operarios = obtenerOperariosSucursal($sucursalSeleccionada, $semana['fecha_inicio'], $semana['fecha_fin']);
        $existe = false;
        foreach ($operarios as $op) {
            if ($op['CodOperario'] == $codOperario) {
                $existe = true;
                break;
            }
        }
        
        if (!$existe) {
            // Agregar operario a la lista
            $operarioNuevo = obtenerOperarioPorCodigo($codOperario);
            if ($operarioNuevo) {
                // Inicializar la sesión si no existe
                if (!isset($_SESSION['operarios_adicionales'])) {
                    $_SESSION['operarios_adicionales'] = [];
                }
                
                // Verificar que no esté duplicado en sesión
                $yaEnSesion = false;
                foreach ($_SESSION['operarios_adicionales'] as $opSesion) {
                    if ($opSesion['CodOperario'] == $codOperario) {
                        $yaEnSesion = true;
                        break;
                    }
                }
                
                if (!$yaEnSesion) {
                    $_SESSION['operarios_adicionales'][$codOperario] = $operarioNuevo;
                    $_SESSION['exito'] = 'Colaborador adicional agregado correctamente';
                } else {
                    $_SESSION['error'] = 'El colaborador ya está en la lista de adicionales';
                }
            } else {
                $_SESSION['error'] = 'No se encontró el colaborador seleccionado';
            }
        } else {
            $_SESSION['error'] = 'El colaborador ya está en la lista principal de la sucursal';
        }
        
        header('Location: programar_horarios_lider.php?semana='.$semanaSeleccionada.'&sucursal='.$sucursalSeleccionada);
        exit();
    }
}

// Obtener datos para la vista
//$semanaSeleccionada = $_GET['semana'] ?? $semanaActual['numero_semana'];
//$sucursalSeleccionada = $_GET['sucursal'] ?? ($sucursalesLider[0]['codigo'] ?? null);

// Obtener operarios y horarios si hay sucursal y semana seleccionada
$operarios = [];
$semana = null;
if ($sucursalSeleccionada && $semanaSeleccionada) {
    $semana = obtenerSemanaPorNumero($semanaSeleccionada);
    if ($semana) {
        // Obtener el parámetro para forzar mostrar todos
        $mostrarTodos = isset($_GET['mostrar_todos']) && $_GET['mostrar_todos'] == '1';
        
        // Obtener operarios que YA TIENEN horario en la base de datos para esta semana/sucursal
        $operariosConHorario = obtenerOperariosSucursalConHorario($sucursalSeleccionada, $semana['id']);
        
        // Obtener operarios de la sucursal (todos los asignados)
        $operariosAsignados = obtenerOperariosSucursal($sucursalSeleccionada, $semana['fecha_inicio'], $semana['fecha_fin']);
        
        // Asegúrate de que sea un array antes de usarlo
        if (!is_array($operariosAsignados)) {
            $operariosAsignados = [];
        }
        
        // Obtener TODOS los operarios con horario (incluyendo adicionales históricos)
        $todosConHorario = obtenerTodosOperariosConHorario($sucursalSeleccionada, $semana['id']);
        
        // DECISIÓN MEJORADA: 
        // - Si hay operarios con horario guardado Y no se fuerza mostrar todos, mostrar esos
        // - Si no hay ninguno guardado o se fuerza mostrar todos, mostrar todos los asignados + adicionales guardados
        if (!empty($operariosConHorario) && !$mostrarTodos) {
            $operarios = $operariosConHorario;
            $mostrandoSoloConHorario = true;
        } else {
            // Combinar operarios asignados + operarios adicionales guardados en BD
            $operarios = $operariosAsignados;
            $mostrandoSoloConHorario = false;
            
            // Agregar operarios adicionales que tienen horario guardado pero no están asignados actualmente
            foreach ($todosConHorario as $opConHorario) {
                if ($opConHorario['esta_asignado'] == 0) {
                    // Verificar que no esté duplicado
                    $existe = false;
                    foreach ($operarios as $op) {
                        if ($op['CodOperario'] == $opConHorario['CodOperario']) {
                            $existe = true;
                            break;
                        }
                    }
                    if (!$existe) {
                        $operarios[] = [
                            'CodOperario' => $opConHorario['CodOperario'],
                            'Nombre' => $opConHorario['Nombre'],
                            'Apellido' => $opConHorario['Apellido'],
                            'Apellido2' => $opConHorario['Apellido2'],
                            'es_adicional_guardado' => true // Marcar como adicional guardado
                        ];
                    }
                }
            }
        }
        
        // Obtener categorías de los operarios (versión modificada para considerar fechas de vigencia)
        $codigosOperarios = array_column($operarios, 'CodOperario');
        if (!empty($codigosOperarios)) {
            $placeholders = implode(',', array_fill(0, count($codigosOperarios), '?'));
            $stmt = $conn->prepare("
                SELECT oc.CodOperario, co.NombreCategoria, co.Peso, co.idCategoria 
                FROM OperariosCategorias oc
                JOIN CategoriasOperarios co ON oc.idCategoria = co.idCategoria
                WHERE oc.CodOperario IN ($placeholders)
                AND (oc.FechaFin IS NULL OR oc.FechaFin >= CURDATE())
                AND oc.FechaInicio <= CURDATE()
                ORDER BY oc.FechaInicio DESC
            ");
            
            $stmt->execute($codigosOperarios);
            $categoriasOperarios = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
            
            // Asignar categorías a los operarios (tomará la más reciente por ORDER BY FechaInicio DESC)
            foreach ($operarios as &$operario) {
                $codOperario = $operario['CodOperario'];
                if (isset($categoriasOperarios[$codOperario])) {
                    $operario['categoria'] = $categoriasOperarios[$codOperario][0];
                } else {
                    // Categoría por defecto si no tiene asignada
                    $operario['categoria'] = [
                        'NombreCategoria' => 'Sin categoría',
                        'Peso' => '-',
                        'idCategoria' => 0
                    ];
                }
            }
            unset($operario); // Romper la referencia
        }
        
        // Agregar operarios adicionales TEMPORALES (en sesión) si existen
        if (isset($_SESSION['operarios_adicionales'])) {
            foreach ($_SESSION['operarios_adicionales'] as $opAdicional) {
                // Verificar que no esté duplicado
                $existe = false;
                foreach ($operarios as $op) {
                    if ($op['CodOperario'] == $opAdicional['CodOperario']) {
                        $existe = true;
                        break;
                    }
                }
                if (!$existe) {
                    // Asegurarse de que el operario adicional tenga categoría
                    if (!isset($opAdicional['categoria'])) {
                        $opAdicional['categoria'] = [
                            'NombreCategoria' => 'Sin categoría',
                            'Peso' => '-',
                            'idCategoria' => 0
                        ];
                    }
                    $operarios[] = $opAdicional;
                }
            }
        }
    }
}

// Verificar si el horario está autorizado para edición por supervisión
$horarioAutorizado = false;
if ($sucursalSeleccionada && $semanaSeleccionada && isset($semana) && $semana) {
    $stmt = $conn->prepare("SELECT COUNT(*) as autorizado FROM AutorizacionesEdicion 
                           WHERE id_semana = ? AND cod_sucursal = ? AND autorizado = 1");
    $stmt->execute([$semana['id'], $sucursalSeleccionada]);
    $result = $stmt->fetch();
    $horarioAutorizado = ($result['autorizado'] > 0);
}

// Determinar si se puede editar (ahora incluye autorización de supervisión)
//$puedeEditar = ($periodoEdicion && $semanaSeleccionada == $semanaPermitida) || $horarioAutorizado;
$puedeEditar = false;
if ($periodoEdicion && $semanaSeleccionada == $semanaPermitida) {
    $puedeEditar = true;
} elseif ($horarioAutorizado) {
    $puedeEditar = true;
}

// Determinar si se puede editar
//$puedeEditar = ($periodoEdicion && $semanaSeleccionada == $semanaPermitida);

function obtenerColorCategoria($idCategoria) {
    $colores = [
        1 => '#E8F5E9',  // Líder - Verde muy claro
        2 => '#E3F2FD',  // Asistente de Líder - Azul muy claro  
        3 => '#FFF3E0',  // Experto - Naranja muy claro
        4 => '#F1F8E9',  // Junior - Verde claro suave
        5 => '#F5F5F5',  // Training - Gris claro
        0 => '#FFFFFF'   // Sin categoría - Blanco
    ];
    
    return $colores[$idCategoria] ?? '#FFFFFF';
}

// Función auxiliar para obtener el nombre de la clase CSS
function obtenerClaseCategoria($nombreCategoria) {
    // Si no se proporciona nombre de categoría, usar "Sin categoría"
    if (empty($nombreCategoria)) {
        $nombreCategoria = 'Sin categoría';
    }
    
    $clases = [
        'Líder' => 'tr-categoria-lider',
        'Asistente de Líder' => 'tr-categoria-asistente',
        'Experto' => 'tr-categoria-experto',
        'Junior' => 'tr-categoria-junior',
        'Training' => 'tr-categoria-training',
        'Sin categoría' => 'tr-categoria-sin-categoria'
    ];
    
    return $clases[$nombreCategoria] ?? 'tr-categoria-sin-categoria';
}

function obtenerColorBordeCategoria($idCategoria) {
    $coloresBorde = [
        1 => '#0E544C',  // Líder - Verde oscuro
        2 => '#1565C0',  // Asistente de Líder - Azul oscuro
        3 => '#E65100',  // Experto - Naranja oscuro
        4 => '#2E7D32',  // Junior - Verde
        5 => '#616161',  // Training - Gris
        0 => '#BDBDBD'   // Sin categoría - Gris claro
    ];
    
    return $coloresBorde[$idCategoria] ?? '#BDBDBD';
}

function obtenerNombreCategoria($idCategoria) {
    global $conn;
    
    if ($idCategoria == 0) {
        return 'Sin categoría';
    }
    
    $stmt = $conn->prepare("SELECT NombreCategoria, Peso FROM CategoriasOperarios WHERE idCategoria = ?");
    $stmt->execute([$idCategoria]);
    $categoria = $stmt->fetch();
    
    if ($categoria) {
        return $categoria['NombreCategoria'] . ' (' . $categoria['Peso'] . ')';
    }
    
    return 'Sin categoría';
}

function obtenerCategoriasDesdeBD() {
    global $conn;
    
    $stmt = $conn->prepare("SELECT idCategoria, NombreCategoria, Peso FROM CategoriasOperarios ORDER BY idCategoria");
    $stmt->execute();
    $categorias = $stmt->fetchAll();
    
    // Agregar la categoría "Sin categoría"
    $categorias[] = [
        'idCategoria' => 0,
        'NombreCategoria' => 'Sin categoría',
        'Peso' => '-'
    ];
    
    return $categorias;
}

// Funciones auxiliares
function obtenerOperarioPorCodigo($codOperario) {
    global $conn;

    $stmt = $conn->prepare("
        SELECT 
            CodOperario, 
            Nombre, 
            Apellido, 
            Apellido2 
        FROM Operarios 
        WHERE CodOperario = ?
        LIMIT 1
    ");
    
    $stmt->execute([$codOperario]);
    $operario = $stmt->fetch();
    
    if ($operario) {
        // Obtener la categoría del operario (si existe)
        $stmtCategoria = $conn->prepare("
            SELECT oc.CodOperario, co.NombreCategoria, co.Peso, co.idCategoria 
            FROM OperariosCategorias oc
            JOIN CategoriasOperarios co ON oc.idCategoria = co.idCategoria
            WHERE oc.CodOperario = ?
            AND (oc.FechaFin IS NULL OR oc.FechaFin >= CURDATE())
            AND oc.FechaInicio <= CURDATE()
            ORDER BY oc.FechaInicio DESC
            LIMIT 1
        ");
        $stmtCategoria->execute([$codOperario]);
        $categoria = $stmtCategoria->fetch();
        
        // Asignar categoría (si no tiene, usar por defecto)
        if ($categoria) {
            $operario['categoria'] = [
                'NombreCategoria' => $categoria['NombreCategoria'],
                'Peso' => $categoria['Peso'],
                'idCategoria' => $categoria['idCategoria']
            ];
        } else {
            $operario['categoria'] = [
                'NombreCategoria' => 'Sin categoría',
                'Peso' => '-',
                'idCategoria' => 0
            ];
        }
    }
    
    return $operario;
}

function procesarHorarios($sucursalesPermitidas) {
    global $conn;
    
    // Validar sucursal
    $sucursalPermitida = false;
    foreach ($sucursalesPermitidas as $sucursal) {
        if ($sucursal['codigo'] == $_POST['sucursal']) {
            $sucursalPermitida = true;
            break;
        }
    }
    
    if (!$sucursalPermitida) {
        $_SESSION['error'] = 'No tiene permiso para programar horarios en esta sucursal';
        header('Location: programar_horarios_lider.php');
        exit();
    }
    
    // Procesar cada operario
    foreach ($_POST['horarios'] as $codOperario => $horario) {
        // Inicializar el array de horario si no está completo
        $dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
        $horarioCompleto = [];
        $totalHoras = 0;
        
        foreach ($dias as $dia) {
            // Inicializar campos para cada día
            $horarioCompleto["{$dia}_estado"] = $horario["{$dia}_estado"] ?? 'Activo';
            $horarioCompleto["{$dia}_comentario"] = $horario["{$dia}_comentario"] ?? '';
            $horarioCompleto["{$dia}_entrada"] = $horario["{$dia}_entrada"] ?? null;
            $horarioCompleto["{$dia}_salida"] = $horario["{$dia}_salida"] ?? null;
            
            // Calcular horas si hay entrada y salida
            if (!empty($horarioCompleto["{$dia}_entrada"]) && !empty($horarioCompleto["{$dia}_salida"])) {
                $entrada = new DateTime($horarioCompleto["{$dia}_entrada"]);
                $salida = new DateTime($horarioCompleto["{$dia}_salida"]);
                $diff = $entrada->diff($salida);
                $horas = $diff->h + ($diff->i / 60);
                $horarioCompleto["{$dia}_horas"] = $horas;
                $totalHoras += $horas;
            } else {
                $horarioCompleto["{$dia}_horas"] = 0;
            }
        }
        
        $horarioCompleto['total_horas'] = $totalHoras;
        
        // Verificar si ya existe registro
        $existente = obtenerHorarioOperario($codOperario, $_POST['semana'], $_POST['sucursal']);
        
        if ($existente) {
            // Actualizar registro existente
            actualizarHorario($existente['id'], $horarioCompleto);
        } else {
            // Crear nuevo registro
            crearHorario($_POST['semana'], $codOperario, $_POST['sucursal'], $horarioCompleto);
        }
    }
    
    // SOLO limpiar operarios adicionales TEMPORALES (en sesión) después de guardar
    // PERO mantener los que ya están guardados en BD
    if (isset($_SESSION['operarios_adicionales'])) {
        // Verificar cuáles operarios adicionales NO tienen horario guardado en BD
        $operariosParaMantener = [];
        foreach ($_SESSION['operarios_adicionales'] as $codOp => $operario) {
            $tieneHorario = obtenerHorarioOperario($codOp, $_POST['semana'], $_POST['sucursal']);
            if (!$tieneHorario) {
                // Si no tiene horario guardado, mantenerlo en sesión
                $operariosParaMantener[$codOp] = $operario;
            }
        }
        
        // Actualizar la sesión con solo los que no tienen horario guardado
        $_SESSION['operarios_adicionales'] = $operariosParaMantener;
        
        // Si no quedan operarios en sesión, limpiar completamente
        if (empty($_SESSION['operarios_adicionales'])) {
            unset($_SESSION['operarios_adicionales']);
        }
    }
    
    $_SESSION['exito'] = 'Horarios guardados correctamente';
    header('Location: programar_horarios_lider.php?semana=' . $_POST['semana'] . '&sucursal=' . $_POST['sucursal']);
    exit();
}

function actualizarHorario($idHorario, $horario) {
    global $conn;
    
    // Obtener el código del operario para actualizar el contrato
    $stmtOperario = $conn->prepare("SELECT cod_operario FROM HorariosSemanales WHERE id = ?");
    $stmtOperario->execute([$idHorario]);
    $horarioActual = $stmtOperario->fetch();
    
    $codContrato = null;
    if ($horarioActual) {
        $codContrato = obtenerUltimoCodigoContrato($horarioActual['cod_operario']);
    }
    
    $stmt = $conn->prepare("
        UPDATE HorariosSemanales SET
        cod_contrato = ?,
        lunes_estado = ?, lunes_comentario = ?, lunes_entrada = ?, lunes_salida = ?, lunes_horas = ?,
        martes_estado = ?, martes_comentario = ?, martes_entrada = ?, martes_salida = ?, martes_horas = ?,
        miercoles_estado = ?, miercoles_comentario = ?, miercoles_entrada = ?, miercoles_salida = ?, miercoles_horas = ?,
        jueves_estado = ?, jueves_comentario = ?, jueves_entrada = ?, jueves_salida = ?, jueves_horas = ?,
        viernes_estado = ?, viernes_comentario = ?, viernes_entrada = ?, viernes_salida = ?, viernes_horas = ?,
        sabado_estado = ?, sabado_comentario = ?, sabado_entrada = ?, sabado_salida = ?, sabado_horas = ?,
        domingo_estado = ?, domingo_comentario = ?, domingo_entrada = ?, domingo_salida = ?, domingo_horas = ?,
        total_horas = ?, actualizado_por = ?, fecha_actualizacion = NOW()
        WHERE id = ?
    ");
    
    $dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
    $params = [$codContrato];
    
    foreach ($dias as $dia) {
        array_push($params, 
            $horario["{$dia}_estado"],
            $horario["{$dia}_comentario"],
            $horario["{$dia}_entrada"],
            $horario["{$dia}_salida"],
            $horario["{$dia}_horas"]
        );
    }
    
    array_push($params, 
        $horario['total_horas'],
        $_SESSION['usuario_id'],
        $idHorario
    );
    
    $stmt->execute($params);
}

function crearHorario($numeroSemana, $codOperario, $codSucursal, $horario) {
    global $conn;
    
    // Obtener el ID real de la semana
    $semana = obtenerSemanaPorNumero($numeroSemana);
    if (!$semana) {
        throw new Exception("Semana no encontrada: " . $numeroSemana);
    }
    
    // Obtener el código del contrato actual
    $codContrato = obtenerUltimoCodigoContrato($codOperario);
    
    $stmt = $conn->prepare("
        INSERT INTO HorariosSemanales (
            id_semana_sistema, cod_operario, cod_contrato, cod_sucursal,
            lunes_estado, lunes_comentario, lunes_entrada, lunes_salida, lunes_horas,
            martes_estado, martes_comentario, martes_entrada, martes_salida, martes_horas,
            miercoles_estado, miercoles_comentario, miercoles_entrada, miercoles_salida, miercoles_horas,
            jueves_estado, jueves_comentario, jueves_entrada, jueves_salida, jueves_horas,
            viernes_estado, viernes_comentario, viernes_entrada, viernes_salida, viernes_horas,
            sabado_estado, sabado_comentario, sabado_entrada, sabado_salida, sabado_horas,
            domingo_estado, domingo_comentario, domingo_entrada, domingo_salida, domingo_horas,
            total_horas, creado_por, fecha_creacion
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, NOW()
        )
    ");
    
    $dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
    $params = [$semana['id'], $codOperario, $codContrato, $codSucursal];
    
    foreach ($dias as $dia) {
        array_push($params, 
            $horario["{$dia}_estado"],
            $horario["{$dia}_comentario"],
            $horario["{$dia}_entrada"],
            $horario["{$dia}_salida"],
            $horario["{$dia}_horas"]
        );
    }
    
    array_push($params, $horario['total_horas'], $_SESSION['usuario_id']);
    
    return $stmt->execute($params);
}

/**
 * Obtiene los operarios que ya tienen horario guardado para una semana/sucursal
 */
function obtenerOperariosConHorarioGuardado($codSucursal, $idSemana) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT DISTINCT o.CodOperario, o.Nombre, o.Apellido, o.Apellido2 
        FROM Operarios o
        JOIN HorariosSemanales hs ON o.CodOperario = hs.cod_operario
        WHERE hs.cod_sucursal = ?
        AND hs.id_semana_sistema = ?
        AND o.Operativo = 1
        ORDER BY o.Nombre, o.Apellido, o.Apellido2
    ");
    $stmt->execute([$codSucursal, $idSemana]);
    return $stmt->fetchAll();
}

function obtenerCategoriaPorDefecto() {
    return [
        'NombreCategoria' => 'Sin categoría',
        'Peso' => '-',
        'idCategoria' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programar Horarios - Líder de Sucursal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <style>
        /* Mantener el mismo CSS que el original */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Calibri', sans-serif;
            font-size: clamp(11px, 2vw, 16px) !important;
        }
        
        body {
            background-color: #F6F6F6;
            color: #333;
            padding: 1px;
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

        .title {
            color: #0E544C;
            font-size: 1.5rem !important;
        }
        
        .subtitle {
            color: #51B8AC;
            font-size: 1.2rem !important;
            margin-bottom: 20px;
        }
        
        .current-week {
            font-size: 0.9rem !important;
            color: #666;
            margin-bottom: 5px;
        }
        
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 200px;
        }
        
        label {
            margin-bottom: 5px;
            font-weight: bold;
            color: #0E544C;
        }
        
        select, input, button {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .btn {
            padding: 8px 15px;
            background-color: #51B8AC;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #0E544C;
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-success {
            background-color: #28a745;
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        .btn-danger {
            background-color: #dc3545;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-primary {
            background-color: #218838;
        }
        
        .btn-primary:hover {
            background-color: #1d6f42;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
            width: 100%;
            max-width: calc(100vw - 20px); /* Asegura que no sobresalga de la pantalla */
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th, td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
            vertical-align: top;
        }

        th {
            background-color: #0E544C;
            color: white;
            text-align: center;
        }
        
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        
        .day-header {
            font-weight: bold;
        }

        .day-date {
            font-size: 0.8rem !important;
            color: #ffffff;
            text-align: center;
        }
        
/* Columna del nombre del operario */
th:first-child,
td:first-child {
    font-size: clamp(9px, 2vw, 14px) !important;
    width: 90px; /* Ancho fijo para la columna de nombres */
    min-width: 90px;
    max-width: 90px;
}

/* Columnas de los días (7 días) */
th:not(:first-child):not(:last-child),
td:not(:first-child):not(:last-child) {
    width: 180px; /* Aumentamos el ancho de 140px a 180px */
    min-width: 180px;
    max-width: 180px;
}

/* Columna de total horas */
th:nth-last-child(2),
td:nth-last-child(2) {
    width: 90px !important; /* Ancho para total horas */
    min-width: 90px !important;
    max-width: 90px !important;
}

/* Última columna (acciones) */
th:last-child,
td:last-child {
    width: 50px; /* Ancho mínimo para ícono */
    min-width: 50px;
    max-width: 50px;
}
        
        .status-select {
            width: 100%;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 5px;
        }
        
        .comment-input {
            width: 100%;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 5px;
            font-size: 0.8rem !important;
        }
        
        .time-input-group {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        
        .time-input {
            width: 100%;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .hours-display {
            display: inline-block;
            width: 100%;
            text-align: center;
            font-weight: bold;
            padding: 5px;
            border-radius: 4px;
            margin-top: 3px;
        }
        
        .normal-hours {
            background-color: #d4edda;
        }
        
        .extended-hours {
            background-color: #fff3cd;
        }
        
        .total-hours {
            text-align: center;
            align-content: center;
            font-weight: bold;
            font-size: 0.85rem !important; /* Texto un poco más pequeño */
            white-space: nowrap; /* Evita saltos de línea */
            /*background-color: #e9ecef;*/
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
        
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .day-cell {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .day-cell-left {
            flex: 1;
        }
        
        .day-cell-right {
            margin-top: auto;
        }
        
        .original-value {
            font-size: 0.8rem !important;
            color: #666;
            border-top: 1px dashed #ccc;
            padding-top: 3px;
            margin-top: 3px;
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
    
            .filters {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .time-input {
                width: 100%;
            }
        }
        
        .status-indicator {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            margin-left: 15px;
            font-weight: bold;
        }
        
        .status-published {
            background-color: #d4edda;
            color: #155724;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
            
        .btn-danger {
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn i {
            margin-right: 0;
        }
        
        /* Efectos para filas */
        tr {
            transition: all 0.3s ease;
        }
        
        /* Estilo para filas de operarios existentes en BD */
        tr[data-existente="true"] {
            border-left: 4px solid #0E544C !important;
        }
        
        /* Estilo para filas de operarios nuevos (no en BD) */
        tr[data-existente="false"] {
            border-left: 4px solid #dc3545 !important;
        }
        
        /* Efecto al pasar el mouse sobre las filas */
        /*tr:hover {
            background-color: rgba(81, 184, 172, 0.1) !important;
        }*/
        
        /* Efecto al pasar el mouse sobre el botón eliminar */
        .btn-danger:hover {
            transform: scale(1.1);
            transition: transform 0.2s ease;
        }
        
        /*Estado diferente a activo*/
        .inactive-hours {
            background-color: #53a1fa;
            color: white;
        }
        
        /* Agregar estos nuevos estilos */
        .status-partial {
            background-color: #ffeeba;
            color: #856404;
        }
        
        .status-published {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* Agregar nuevos estilos para el selector de operarios */
        .operario-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .operario-selector select {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .operario-selector button {
            padding: 8px 15px;
        }
        
        /* Estilo para el mensaje de restricción */
        .restriction-message {
            background-color: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            border-left: 4px solid #ffc107;
        }
        
        .btn-info {
            background-color: #17a2b8;
            color: white;
        }
        
        .btn-info:hover {
            background-color: #138496;
        }
        
        /* Estilos para categorías de operarios */
        .operario-cell {
            font-weight: bold;
            position: relative;
            padding-bottom: 20px !important; /* Espacio para el indicador */
        }
        
        .categoria-indicator {
            position: absolute;
            bottom: 2px;
            left: 2px;
            font-size: 0.7rem !important;
            padding: 2px 5px;
            border-radius: 3px;
            opacity: 0.9;
            width: calc(100% - 4px);
            text-align: center;
        }
        
        /* Colores de categoría 
        .categoria-lider .categoria-indicator { background-color: #0E544C; color: white; }
        .categoria-asistente .categoria-indicator { background-color: #2E7D32; color: white; }
        .categoria-experto .categoria-indicator { background-color: #0288D1; color: white; }
        .categoria-junior .categoria-indicator { background-color: #F57C00; color: white; }
        .categoria-training .categoria-indicator { background-color: #616161; color: white; }
        .categoria-sin-categoria .categoria-indicator { background-color: #757575; color: white; }
        */
        
        /* Nuevos estilos para el diseño compacto */
    .compact-cell {
        min-height: 90px; /* Para que no quede muy apretado verticalmente */
        display: flex;
        flex-direction: column;
        gap: 3px;
    }
    
    .status-comment-group {
        display: flex;
        gap: 5px;
        align-items: center;
    }
    
    .status-select-compact {
        width: calc(100% - 30px);
        min-width: 0;
        font-size: 0.9rem !important; /* Aumentamos un poco el tamaño de fuente */
    }
        
    .comment-btn {
        width: 26px;
        height: 26px;
        padding: 0;
        flex-shrink: 0;
    }
    
    .comment-btn:hover {
        background-color: #5a6268;
    }
    
    .comment-btn.has-comment {
        background-color: #17a2b8;
    }
    
    .comment-btn.has-comment:hover {
        background-color: #138496;
    }
    
    .time-input-group-compact {
        display: flex;
        gap: 3px;
    }
    
    .time-input-compact {
        width: calc(50% - 2px);
        min-width: 0;
        padding: 2px;
        font-size: clamp(9px, 2vw, 11px) !important; /* Aumentamos un poco el tamaño de fuente */
        box-sizing: border-box;
    }
    
    /* Modal para comentarios */
    .comment-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }
    
    .comment-modal-content {
        background-color: white;
        padding: 20px;
        border-radius: 8px;
        width: 90%;
        max-width: 500px;
    }
    
    .comment-modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 15px;
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

/* Nuevos estilos para la reorganización de filtros */
.filters-row {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-item {
    display: flex;
    flex-direction: column;
}

.filter-item label {
    margin-bottom: 5px;
    font-weight: bold;
    color: #0E544C;
    white-space: nowrap;
}

.filter-controls {
    display: flex;
    gap: 10px;
    align-items: center;
}

.filter-controls select,
.filter-controls input {
    min-width: 120px;
}

.operario-agregar-container {
    display: flex;
    gap: 10px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.operario-agregar-container select {
    min-width: 250px;
}

@media (max-width: 1024px) {
    .filters-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-controls {
        flex-wrap: wrap;
    }
    
    .operario-agregar-container {
        margin-top: 10px;
    }
}

@media (max-width: 768px) {
    .filter-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-controls > * {
        width: 100%;
    }
    
    .operario-agregar-container {
        flex-direction: column;
    }
    
    .operario-agregar-container select,
    .operario-agregar-container button {
        width: 100%;
    }
}

/* Leyenda de categorías */
.categoria-leyenda {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 15px;
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 4px;
    border: 1px solid #dee2e6;
}

.leyenda-item {
    padding: 5px 10px;
    border-radius: 3px;
    font-size: 0.85rem !important;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.leyenda-item::before {
    content: "";
    width: 12px;
    height: 12px;
    border-radius: 2px;
    background-color: inherit;
}

/* Estilos de fondo por categoría (se aplican a toda la fila) */
.tr-categoria-lider {
    background-color: #E8F5E9 !important; /* Verde muy claro */
}

.tr-categoria-asistente {
    background-color: #E3F2FD !important; /* Azul muy claro */
}

.tr-categoria-experto {
    background-color: #FFF3E0 !important; /* Naranja muy claro */
}

.tr-categoria-junior {
    background-color: #F1F8E9 !important; /* Verde claro suave */
}

.tr-categoria-training {
    background-color: #F5F5F5 !important; /* Gris claro */
}

.tr-categoria-sin-categoria {
    background-color: #FFFFFF !important; /* Blanco */
}

/* Efecto al pasar el mouse sobre las filas */
tr:hover {
    filter: brightness(0.95) !important;
}

/* ========== ESTILOS PARA GRÁFICOS ========== */

/* Estilos para los botones de modo */
.btn-modografico {
    padding: 10px 20px;
    border: none;
    background: transparent;
    color: #666;
    cursor: pointer;
    border-radius: 6px;
    transition: all 0.3s ease;
    font-weight: 500;
}

.btn-modografico.activo {
    background: #51B8AC;
    color: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btn-modografico:hover:not(.activo) {
    background: #e9ecef;
    color: #0E544C;
}

/* Estilos existentes del gráfico */
.grafico-barra {
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    background: white;
    padding: 8px 15px;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border-left: 4px solid #51B8AC;
}

.grafico-barra:hover {
    transform: translateX(5px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.grafico-barra.encima-limite {
    border-left-color: #dc3545;
    background: #fff5f5;
}

.grafico-barra.dia-inactivo {
    border-left-color: #6c757d;
    background: #f8f9fa;
}

.barra-contenedor {
    flex-grow: 1;
    margin: 0 15px;
    background: #e9ecef;
    border-radius: 10px;
    height: 25px;
    position: relative;
    overflow: hidden;
}

.barra-progreso {
    height: 100%;
    background: linear-gradient(90deg, #51B8AC, #0E544C);
    border-radius: 10px;
    transition: width 0.5s ease;
    position: relative;
    min-width: 30px;
}

.barra-progreso.encima-limite {
    background: linear-gradient(90deg, #ff6b6b, #dc3545);
}

.barra-progreso.dia-inactivo {
    background: linear-gradient(90deg, #6c757d, #495057);
}

.barra-texto {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: white;
    font-weight: bold;
    font-size: 0.8rem;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
    z-index: 2;
}

.barra-marcador {
    position: absolute;
    top: 0;
    height: 100%;
    width: 2px;
    background: rgba(0,0,0,0.3);
    z-index: 1;
}

.barra-marcador.maximo {
    background: #0E544C;
    width: 3px;
}

.barra-marcador.diario {
    background: #0E544C;
    width: 3px;
}

.nombre-operario {
    min-width: 180px;
    font-weight: 600;
    color: #333;
    font-size: 0.9rem;
}

.horas-totales {
    min-width: 80px;
    text-align: right;
    font-weight: bold;
    color: #0E544C;
    font-size: 0.9rem;
}

.horas-totales.encima-limite {
    color: #dc3545;
}

.horas-totales.dia-inactivo {
    color: #6c757d;
}

.indicador-limite {
    display: inline-block;
    margin-left: 8px;
    padding: 2px 6px;
    background: #dc3545;
    color: white;
    border-radius: 3px;
    font-size: 0.7rem;
    font-weight: bold;
}

.indicador-inactivo {
    display: inline-block;
    margin-left: 8px;
    padding: 2px 6px;
    background: #6c757d;
    color: white;
    border-radius: 3px;
    font-size: 0.7rem;
    font-weight: bold;
}

/* Estilos para el gráfico de línea de tiempo */
.grafico-timeline {
    background: white;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    margin-bottom: 15px;
}

.timeline-container {
    position: relative;
    min-height: 400px;
    margin: 20px 0;
}

.timeline-eje-x {
    position: absolute;
    top: 0;
    left: 120px;
    right: 0;
    height: 2px;
    background: #333;
    z-index: 1;
}

.timeline-horas {
    position: absolute;
    top: -25px;
    left: 120px;
    right: 0;
    display: flex;
    justify-content: space-between;
    z-index: 2;
}

.timeline-hora {
    font-size: 0.8rem;
    color: #666;
    text-align: center;
    width: 40px;
    margin-left: -20px;
}

.timeline-hora::before {
    content: '';
    position: absolute;
    top: -5px;
    left: 50%;
    width: 1px;
    height: 10px;
    background: #999;
}

.timeline-hora.media-hora {
    color: #999;
    font-size: 0.7rem;
}

.timeline-hora.media-hora::before {
    height: 5px;
    top: -3px;
}

.timeline-filas {
    margin-top: 40px;
}

.timeline-fila {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    min-height: 35px;
    position: relative;
}

.timeline-nombre {
    width: 120px;
    font-weight: 600;
    font-size: 0.85rem;
    color: #333;
    padding-right: 10px;
    text-align: right;
    flex-shrink: 0;
}

.timeline-barra-container {
    flex: 1;
    height: 25px;
    background: #f8f9fa;
    border-radius: 4px;
    position: relative;
    border: 1px solid #e9ecef;
    margin-left: 10px;
}

.timeline-barra {
    position: absolute;
    height: 100%;
    background: linear-gradient(90deg, #51B8AC, #0E544C);
    border-radius: 3px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.75rem;
    font-weight: bold;
    text-shadow: 1px 1px 1px rgba(0,0,0,0.5);
    cursor: pointer;
}

.timeline-barra:hover {
    filter: brightness(1.1);
    transform: scaleY(1.1);
    z-index: 10;
}

.timeline-barra.manana { background: linear-gradient(90deg, #3498db, #2980b9); }
.timeline-barra.tarde { background: linear-gradient(90deg, #e74c3c, #c0392b); }
.timeline-barra.noche { background: linear-gradient(90deg, #9b59b6, #8e44ad); }
.timeline-barra.mixto { background: linear-gradient(90deg, #f39c12, #d35400); }

.timeline-barra.inactivo {
    background: linear-gradient(90deg, #95a5a6, #7f8c8d);
    opacity: 0.7;
}

.timeline-marcadores {
    position: absolute;
    top: 0;
    left: 120px;
    right: 0;
    bottom: 0;
    pointer-events: none;
}

.timeline-marcador {
    position: absolute;
    top: 0;
    width: 1px;
    height: 100%;
    background: rgba(0,0,0,0.1);
}

.timeline-marcador.hora-completa {
    background: rgba(0,0,0,0.2);
}

.timeline-leyenda-turnos {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.leyenda-turno {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85rem;
}

.color-turno {
    width: 20px;
    height: 8px;
    border-radius: 2px;
}

/* Responsive para gráficos */
@media (max-width: 768px) {
    .grafico-barra {
        flex-direction: column;
        align-items: flex-start;
        padding: 10px;
    }
    
    .barra-contenedor {
        margin: 8px 0;
        width: 100%;
    }
    
    .nombre-operario {
        min-width: auto;
        margin-bottom: 5px;
    }
    
    .horas-totales {
        align-self: flex-end;
        min-width: auto;
    }
    
    .btn-modografico {
        padding: 8px 15px;
        font-size: 0.9rem;
    }
    
    .timeline-nombre {
        width: 100px;
        font-size: 0.8rem;
    }
    
    .timeline-hora {
        font-size: 0.7rem;
        width: 30px;
        margin-left: -15px;
    }
    
    .timeline-hora.media-hora {
        font-size: 0.6rem;
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
                    <a href="faltas_manual.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'faltas_manual.php' ? 'activo' : '' ?>">
                        <i class="fas fa-user-times"></i> <span class="btn-text">Faltas/Ausencias</span>
                    </a>
                    <a href="../operaciones/tardanzas_manual.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == '../operaciones/tardanzas_manual.php' ? 'activo' : '' ?>">
                        <i class="fas fa-user-clock"></i> <span class="btn-text">Tardanzas</span>
                    </a>
                    <a href="programar_horarios_lider.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'programar_horarios_lider.php' ? 'activo' : '' ?>">
                        <i class="fas fa-user-clock"></i> <span class="btn-text">Generar Horarios</span>
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
        
        <?php if ($semanaSeleccionada && $sucursalSeleccionada && $semana && !empty($operarios)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <?php 
                $totalOperarios = count($operarios);
                
                // Calcular operarios asignados vs adicionales de forma segura
                $operariosAsignadosCount = 0;
                $operariosAdicionalesCount = 0;
                
                foreach ($operarios as $operario) {
                    if (isset($operario['es_adicional_guardado']) || 
                        (isset($_SESSION['operarios_adicionales']) && 
                         isset($_SESSION['operarios_adicionales'][$operario['CodOperario']]))) {
                        $operariosAdicionalesCount++;
                    } else {
                        $operariosAsignadosCount++;
                    }
                }
                
                if (isset($mostrandoSoloConHorario) && $mostrandoSoloConHorario): 
                ?>
                    Mostrando <?= $totalOperarios ?> operarios con horario guardado para esta semana.
                    <?php if ($operariosAdicionalesCount > 0): ?>
                        (<?= $operariosAdicionalesCount ?> colaboradores adicionales)
                    <?php endif; ?>
                    <a href="javascript:void(0)" onclick="mostrarTodosOperarios()" style="margin-left: 10px; font-weight: bold;">
                        <i class="fas fa-eye"></i> Ver todos los operarios de la sucursal
                    </a>
                <?php else: ?>
                    Mostrando todos los <?= $totalOperarios ?> colaboradores 
                    (<?= $operariosAsignadosCount ?> asignados + <?= $operariosAdicionalesCount ?> adicionales).
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Leyenda de colores por categoría -->
        <?php if ($semanaSeleccionada && $sucursalSeleccionada && $semana && !empty($operarios)): ?>
            <div style="display:none;" class="categoria-leyenda">
                <strong>Leyenda de categorías (Peso):</strong>
                <?php
                $categoriasMostradas = [];
                foreach ($operarios as $operario) {
                    $catId = $operario['categoria']['idCategoria'];
                    if (!in_array($catId, $categoriasMostradas)) {
                        $categoriasMostradas[] = $catId;
                    }
                }
                
                // Obtener todas las categorías desde la BD
                $todasCategorias = obtenerCategoriasDesdeBD();
                
                // Mostrar solo las categorías que están presentes
                foreach ($todasCategorias as $categoria): 
                    if (in_array($categoria['idCategoria'], $categoriasMostradas)):
                        $colorFondo = obtenerColorCategoria($categoria['idCategoria']);
                ?>
                    <span class="leyenda-item" style="background-color: <?= $colorFondo ?>;">
                        <?= htmlspecialchars($categoria['NombreCategoria']) ?> (<?= $categoria['Peso'] ?>)
                    </span>
                <?php 
                    endif;
                endforeach; 
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Nueva estructura para filtros en una sola línea -->
        <div class="filters-row">
            <!-- Filtro de semana -->
            <div class="filter-item">
                <label for="semana">No. Semana</label>
                <input type="number" id="semana" name="semana" 
                       min="1" max="1825"
                       value="<?= $semanaSeleccionada ?>" 
                       placeholder="Ej: 495">
            </div>
            
            <!-- Filtro de sucursal con botón buscar -->
            <div class="filter-item">
                <label for="sucursal">Sucursal</label>
                <div class="filter-controls">
                    <select id="sucursal" name="sucursal" <?= count($sucursalesLider) === 1 ? 'disabled' : '' ?>>
                        <?php foreach ($sucursalesLider as $sucursal): ?>
                            <option value="<?= $sucursal['codigo'] ?>" <?= $sucursalSeleccionada == $sucursal['codigo'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sucursal['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" onclick="cambiarSemana()" class="btn">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
                <?php if (count($sucursalesLider) === 1): ?>
                    <input type="hidden" name="sucursal" value="<?= $sucursalesLider[0]['codigo'] ?>">
                <?php endif; ?>
            </div>
            
            <!-- Formulario para agregar operarios adicionales -->
            <?php if ($puedeEditar && $semanaSeleccionada && $sucursalSeleccionada && $semana): ?>
                <div class="operario-agregar-container">
                    <select name="cod_operario" required>
                        <option value="">Seleccione colaborador adicional</option>
                        <?php 
                        // Obtener operarios con cargos 2 y 5
                        $todosOperarios = obtenerTodosOperariosParaSelector();
                        
                        // Crear array de códigos que YA están en la tabla
                        $codigosEnTabla = array_column($operarios, 'CodOperario');
                        
                        // Array para controlar duplicados en el selector
                        $codigosYaMostrados = [];
                        
                        foreach ($todosOperarios as $op): 
                            // Evitar duplicados por código de operario en el selector
                            if (in_array($op['CodOperario'], $codigosYaMostrados)) {
                                continue;
                            }
                            $codigosYaMostrados[] = $op['CodOperario'];
                            
                            // Excluir operarios que ya están en la tabla
                            if (in_array($op['CodOperario'], $codigosEnTabla)) {
                                continue;
                            }
                            
                            $nombreCompleto = htmlspecialchars(
                                $op['Nombre'] . ' ' . 
                                $op['Apellido'] . ' ' . 
                                ($op['Apellido2'] ?? '')
                            );
                        ?>
                            <option value="<?= $op['CodOperario'] ?>">
                                <?= $nombreCompleto ?> <?//= $op['CodOperario'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="button" onclick="agregarOperario()" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Agregar
                    </button>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Mostrar mensaje de restricción si aplica -->
        <?php if (!$puedeEditar): ?>
            <div class="restriction-message">
                <i class="fas fa-exclamation-triangle"></i> 
                <?php if ($semanaSeleccionada == $semanaActual['numero_semana']): ?>
                    No se puede editar la semana actual del sistema.
                <?php elseif ($periodoEdicion && $semanaSeleccionada == $semanaSiguiente['numero_semana']): ?>
                    Solo puede editar la semana <?= $semanaSiguiente['numero_semana'] ?> desde el lunes a las 00:00 hasta el viernes a las 23:59 (hora de Nicaragua).
                <?php elseif (!$periodoEdicion && $semanaSeleccionada == $semanaSiguiente['numero_semana']): ?>
                    El período de edición para la semana <?= $semanaSiguiente['numero_semana'] ?> ha terminado (solo de lunes 00:00 a viernes 23:59 hora de Nicaragua).
                <?php elseif ($horarioAutorizado): ?>
                    <span style="color: #28a745;">
                        <i class="fas fa-check-circle"></i> La supervisión ha autorizado ediciones para esta semana.
                    </span>
                <?php else: ?>
                    No tiene permiso para editar esta semana.
                <?php endif; ?>
                
                <?php if ($semanaSiguiente && $semanaSeleccionada == $semanaSiguiente['numero_semana']): ?>
                    <br><strong>Período de edición:</strong> 
                    <?= $lunesSemanaActual->format('d-m-Y H:i') ?> a 
                    <?= $viernesSemanaActual->format('d-m-Y H:i') ?> (hora Nicaragua)
                    <br><strong>Hora actual:</strong> <?= $hoy->format('d-m-Y H:i:s') ?> (hora Nicaragua)
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($semanaSeleccionada && $sucursalSeleccionada && $semana): ?>
            <div style="font-weight:bold; display:none;" class="subtitle">
                Programando horarios para la semana <?= $semanaSeleccionada ?> 
                (<?= formatoFecha($semana['fecha_inicio']) ?> al <?= formatoFecha($semana['fecha_fin']) ?>)
                | Sucursal: <?= htmlspecialchars(array_column($sucursalesLider, 'nombre', 'codigo')[$sucursalSeleccionada]) ?>
            </div>
            
            <form method="post" id="horariosForm" action="programar_horarios_lider.php" onsubmit="return false;">
                <input type="hidden" name="semana" value="<?= $semanaSeleccionada ?>">
                <input type="hidden" name="sucursal" value="<?= $sucursalSeleccionada ?>">
                <input type="hidden" name="guardar_horarios" value="1">
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th rowspan="2" style="width: 180px;">Colaborador</th>
                                <?php 
                                $diasSemana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
                                $fechasSemana = [];
                                $fechaActual = new DateTime($semana['fecha_inicio']);
                                
                                foreach ($diasSemana as $dia) {
                                    echo '<th class="day-header">' . $dia . '</th>';
                                    $fechasSemana[] = $fechaActual->format('Y-m-d');
                                    $fechaActual->modify('+1 day');
                                }
                                ?>
                                <th rowspan="2">Horas</th>
                                <th rowspan="2"></th>
                            </tr>
                            <tr>
                                <?php 
                                $fechaActual = new DateTime($semana['fecha_inicio']);
                                foreach ($diasSemana as $dia) {
                                    echo '<th class="day-date">' . formatoFecha($fechaActual->format('Y-m-d')) . '</th>';
                                    $fechaActual->modify('+1 day');
                                }
                                ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($operarios as $operario): 
                                $horarioExistente = obtenerHorarioOperario($operario['CodOperario'], $semana['id'], $sucursalSeleccionada);
                            ?>
                                <tr class="<?= obtenerClaseCategoria($operario['categoria']['NombreCategoria'] ?? 'Sin categoría') ?>" 
                                    data-existente="<?= $horarioExistente ? 'true' : 'false' ?>" 
                                    data-operario="<?= $operario['CodOperario'] ?>">
                                    
                                    <td class="operario-cell">
                                        <div style="margin-bottom: 5px; font-weight: bold;">
                                            <?= htmlspecialchars($operario['Nombre'] . ' ' . $operario['Apellido'] . ' ' . $operario['Apellido2']) ?> 
                                            <?php if (isset($operario['es_adicional_guardado']) || (isset($_SESSION['operarios_adicionales'][$operario['CodOperario']]))): ?>
                                                <span style="background: #ffc107; color: #000; padding: 2px 6px; border-radius: 3px; font-size: 6px; margin-left: 2px;">
                                                    ADICIONAL
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: 0.8rem !important; color: #666;">
                                            <?//= htmlspecialchars($operario['CodOperario']) ?>
                                        </div>
                                        <div style="font-size: 0.75rem !important; color: #888; margin-top: 3px;">
                                            <?= htmlspecialchars($operario['categoria']['NombreCategoria'] ?? 'Sin categoría') ?> 
                                            <!-- (<?= $operario['categoria']['Peso'] ?? '-' ?>) -->
                                        </div>
                                    </td>
                                    <?php 
                                    $totalHoras = 0;
                                    $dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
                                    
                                    foreach ($dias as $dia): 
                                        $estado = $horarioExistente ? $horarioExistente["{$dia}_estado"] : 'Activo';
                                        $comentario = $horarioExistente ? $horarioExistente["{$dia}_comentario"] : '';
                                        $entrada = $horarioExistente ? $horarioExistente["{$dia}_entrada"] : '';
                                        $salida = $horarioExistente ? $horarioExistente["{$dia}_salida"] : '';
                                        $horasDia = $horarioExistente ? $horarioExistente["{$dia}_horas"] : 0;
                                        $totalHoras += $horasDia;
                                    ?>
                                        <td>
                                            <div class="compact-cell">
                                                <div class="status-comment-group">
                                                    <select name="horarios[<?= $operario['CodOperario'] ?>][<?= $dia ?>_estado]" 
                                                            class="status-select-compact" 
                                                            onchange="actualizarEstado(this, '<?= $dia ?>_<?= $operario['CodOperario'] ?>')"
                                                            <?= !$puedeEditar ? 'disabled' : '' ?>>
                                                        <option value="Activo" <?= $estado == 'Activo' ? 'selected' : '' ?>>Activo</option>
                                                        <option value="Vacaciones" <?= $estado == 'Vacaciones' ? 'selected' : '' ?>>Vacaciones</option>
                                                        <option value="Subsidio" <?= $estado == 'Subsidio' ? 'selected' : '' ?>>Subsidio</option>
                                                        <option value="Libre" <?= $estado == 'Libre' ? 'selected' : '' ?>>Libre</option>
                                                        <option value="Feriado" <?= $estado == 'Feriado' ? 'selected' : '' ?>>Feriado</option>
                                                        <option value="Comp.Feriado" <?= $estado == 'Comp.Feriado' ? 'selected' : '' ?>>Comp. Feriado</option>
                                                        <option value="Otra.Tienda" <?= $estado == 'Otra.Tienda' ? 'selected' : '' ?>>Otra tienda</option>
                                                        <option value="Finalizado" <?= $estado == 'Finalizado' ? 'selected' : '' ?>>Contrato Finalizado</option>
                                                    </select>
                                                    
                                                    <button type="button" 
                                                            class="comment-btn <?= !empty($comentario) ? 'has-comment' : '' ?>" 
                                                            onclick="openCommentModal('<?= $dia ?>_<?= $operario['CodOperario'] ?>', '<?= htmlspecialchars($comentario) ?>', this)"
                                                            title="<?= !empty($comentario) ? 'Editar comentario: ' . htmlspecialchars($comentario) : 'Agregar comentario' ?>"
                                                            <?= !$puedeEditar ? 'disabled' : '' ?>>
                                                        <i class="fas fa-comment<?= !empty($comentario) ? '' : '-alt' ?>"></i>
                                                    </button>
                                                    
                                                    <input type="hidden" 
                                                       name="horarios[<?= $operario['CodOperario'] ?>][<?= $dia ?>_comentario]" 
                                                       id="comment_<?= $dia ?>_<?= $operario['CodOperario'] ?>" 
                                                       value="<?= htmlspecialchars($comentario) ?>">
                                                </div>
                                                
                                                <div class="time-input-group-compact">
                                                    <input type="time" 
                                                           name="horarios[<?= $operario['CodOperario'] ?>][<?= $dia ?>_entrada]" 
                                                           value="<?= $entrada ?>" 
                                                           class="time-input-compact entrada_<?= $dia ?>_<?= $operario['CodOperario'] ?>" 
                                                           step="1800" 
                                                           min="06:00" 
                                                           max="22:00"
                                                           <?= $estado != 'Activo' ? 'disabled' : '' ?>
                                                           <?= !$puedeEditar ? 'disabled' : '' ?>
                                                           onchange="calcularHoras('<?= $dia ?>_<?= $operario['CodOperario'] ?>')"
                                                           placeholder="Entrada">
                                                    
                                                    <input type="time" 
                                                           name="horarios[<?= $operario['CodOperario'] ?>][<?= $dia ?>_salida]" 
                                                           value="<?= $salida ?>" 
                                                           class="time-input-compact salida_<?= $dia ?>_<?= $operario['CodOperario'] ?>" 
                                                           step="1800" 
                                                           min="06:00" 
                                                           max="22:00"
                                                           <?= $estado != 'Activo' ? 'disabled' : '' ?>
                                                           <?= !$puedeEditar ? 'disabled' : '' ?>
                                                           onchange="calcularHoras('<?= $dia ?>_<?= $operario['CodOperario'] ?>')"
                                                           placeholder="Salida">
                                                </div>
                                                
                                                <span class="hours-display 
                                                      <?= ($estado != 'Activo') ? 'inactive-hours' : 
                                                         (($salida && substr($salida, 0, 2) >= 19) ? 'extended-hours' : 'normal-hours') ?>">
                                                    <?= number_format($horasDia, 2) ?>
                                                </span>
                                            </div>
                                        </td>
                                    <?php endforeach; ?>
                                    
                                    <td class="total-hours" id="total_horas_<?= $operario['CodOperario'] ?>">
                                        <?= number_format($totalHoras, 2) ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($puedeEditar): ?>
                                            <button type="button" 
                                                    onclick="eliminarOperario(this, <?= $operario['CodOperario'] ?>, <?= $semana['id'] ?? 'null' ?>, '<?= $sucursalSeleccionada ?>')" 
                                                    class="btn btn-danger" 
                                                    style="padding: 5px 8px;"
                                                    <?= !$puedeEditar ? 'disabled' : '' ?>
                                                    title="Eliminar este operario">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($semanaSeleccionada && $sucursalSeleccionada && $semana && !empty($operarios)): ?>
                <div style="display:none;">
<!-- Selector de modo de gráfico -->
<div style="margin: 20px 0; text-align: center;">
    <div style="display: inline-flex; background: #f8f9fa; padding: 5px; border-radius: 8px; border: 1px solid #dee2e6;">
        <button type="button" id="btnModoSemanal" class="btn-modografico" onclick="cambiarModoGrafico('semanal')">
            <i class="fas fa-chart-bar"></i> Vista Semanal
        </button>
        <button type="button" id="btnModoDiario" class="btn-modografico" onclick="cambiarModoGrafico('diario')">
            <i class="fas fa-calendar-day"></i> Vista por Día
        </button>
        <button type="button" id="btnModoHorarios" class="btn-modografico activo" onclick="cambiarModoGrafico('horarios')">
            <i class="fas fa-timeline"></i> Línea de Tiempo
        </button>
    </div>
</div>

<!-- Gráfico de distribución de horas -->
<div style="margin-top: 10px; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6;">
    <h3 style="color: #0E544C; margin-bottom: 20px; text-align: center;">
        <i class="fas fa-chart-bar" id="iconoModoGrafico"></i> 
        <span id="tituloModoGrafico">Distribución Semanal de Horas</span>
    </h3>
    
    <!-- Selector de día (solo visible en modo diario) -->
    <div id="selectorDia" style="display: none; margin-bottom: 20px; text-align: center;">
        <label for="selectDia" style="font-weight: bold; margin-right: 10px;">Seleccionar día:</label>
        <select id="selectDia" onchange="actualizarGraficoHoras()" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
            <option value="lunes">Lunes (<?= formatoFecha($fechasSemana[0] ?? '') ?>)</option>
            <option value="martes">Martes (<?= formatoFecha($fechasSemana[1] ?? '') ?>)</option>
            <option value="miercoles">Miércoles (<?= formatoFecha($fechasSemana[2] ?? '') ?>)</option>
            <option value="jueves">Jueves (<?= formatoFecha($fechasSemana[3] ?? '') ?>)</option>
            <option value="viernes">Viernes (<?= formatoFecha($fechasSemana[4] ?? '') ?>)</option>
            <option value="sabado">Sábado (<?= formatoFecha($fechasSemana[5] ?? '') ?>)</option>
            <option value="domingo">Domingo (<?= formatoFecha($fechasSemana[6] ?? '') ?>)</option>
        </select>
    </div>
    
    <!-- Selector de día para distribución por horarios (solo visible en modo horarios) -->
    <div id="selectorDiaHorarios" style="display: none; margin-bottom: 20px; text-align: center;">
        <label for="selectDiaHorarios" style="font-weight: bold; margin-right: 10px;">Seleccionar día para distribución:</label>
        <select id="selectDiaHorarios" onchange="actualizarGraficoHoras()" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
            <option value="lunes">Lunes (<?= formatoFecha($fechasSemana[0] ?? '') ?>)</option>
            <option value="martes">Martes (<?= formatoFecha($fechasSemana[1] ?? '') ?>)</option>
            <option value="miercoles">Miércoles (<?= formatoFecha($fechasSemana[2] ?? '') ?>)</option>
            <option value="jueves">Jueves (<?= formatoFecha($fechasSemana[3] ?? '') ?>)</option>
            <option value="viernes">Viernes (<?= formatoFecha($fechasSemana[4] ?? '') ?>)</option>
            <option value="sabado">Sábado (<?= formatoFecha($fechasSemana[5] ?? '') ?>)</option>
            <option value="domingo">Domingo (<?= formatoFecha($fechasSemana[6] ?? '') ?>)</option>
        </select>
    </div>
    
    <div id="graficoHoras" style="width: 100%; height: auto; min-height: 300px;">
        <!-- El gráfico se generará aquí dinámicamente -->
        <div style="text-align: center; padding: 50px; color: #666;">
            <i class="fas fa-chart-bar" style="font-size: 48px; margin-bottom: 15px;"></i>
            <p>El gráfico se actualizará automáticamente al modificar los horarios</p>
        </div>
    </div>
    
    <!-- Leyenda del gráfico -->
    <div style="margin-top: 15px; display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 5px;">
            <div style="width: 15px; height: 15px; background-color: #51B8AC; border-radius: 3px;"></div>
            <span style="font-size: 0.85rem;">Horas Programadas</span>
        </div>
        <div style="display: flex; align-items: center; gap: 5px;">
            <div style="width: 15px; height: 15px; background-color: #0E544C; border-radius: 3px;"></div>
            <span style="font-size: 0.85rem;" id="textoLeyendaLimite">Máximo Recomendado (48h)</span>
        </div>
        <div style="display: flex; align-items: center; gap: 5px;" id="leyendaInactivo" style="display: none;">
            <div style="width: 15px; height: 15px; background-color: #6c757d; border-radius: 3px;"></div>
            <span style="font-size: 0.85rem;">Día Inactivo</span>
        </div>
    </div>
</div>
</div>
<?php endif; ?>
                
                <?php if ($puedeEditar): ?>
                    <div style="text-align: center; margin-top: 20px;">
                        <button type="button" onclick="guardarHorarios()" class="btn btn-success">
                            <i class="fas fa-save"></i> Guardar Horarios
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        <?php elseif ($sucursalSeleccionada && !$semanaSeleccionada): ?>
            <div style="text-align: center; padding: 20px; color: #666;">
                Ingrese un número de semana para programar horarios
            </div>
        <?php elseif ($semanaSeleccionada && !$semana): ?>
            <div style="text-align: center; padding: 20px; color: #dc3545;">
                La semana ingresada no existe en el sistema
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Agregar el modal para comentarios al final del body -->
    <div id="commentModal" class="comment-modal">
        <div class="comment-modal-content">
            <h3>Editar Comentario</h3>
            <textarea id="commentTextarea" class="comment-input" rows="3" style="width: 100%;"></textarea>
            <div class="comment-modal-actions">
                <button type="button" onclick="closeCommentModal()" class="btn btn-secondary">Cancelar</button>
                <button type="button" onclick="saveComment()" class="btn btn-primary">Guardar</button>
            </div>
        </div>
    </div>

<script>
    // Variables para el modal de comentarios
    let currentCommentPrefix = null;
    let currentCommentBtn = null;
    
    function openCommentModal(prefix, currentComment, btn) {
        currentCommentPrefix = prefix;
        currentCommentBtn = btn;
        
        // Decodificar el comentario (por si tiene caracteres especiales)
        try {
            currentComment = decodeURIComponent(currentComment);
        } catch (e) {
            // Si hay error al decodificar, mantener el valor original
            console.log('Error al decodificar comentario:', e);
        }
        
        document.getElementById('commentTextarea').value = currentComment || '';
        document.getElementById('commentModal').style.display = 'flex';
    }
    
    function closeCommentModal() {
        document.getElementById('commentModal').style.display = 'none';
        currentCommentPrefix = null;
        currentCommentBtn = null;
    }
    
    function saveComment() {
        if (!currentCommentPrefix) return;
        
        const comment = document.getElementById('commentTextarea').value;
        const commentInput = document.getElementById(`comment_${currentCommentPrefix}`);
        
        if (commentInput) {
            commentInput.value = comment;
            
            // Actualizar el botón de comentario
            if (currentCommentBtn) {
                if (comment.trim()) {
                    currentCommentBtn.classList.add('has-comment');
                    currentCommentBtn.title = 'Editar comentario: ' + comment;
                    currentCommentBtn.innerHTML = '<i class="fas fa-comment"></i>';
                } else {
                    currentCommentBtn.classList.remove('has-comment');
                    currentCommentBtn.title = 'Agregar comentario';
                    currentCommentBtn.innerHTML = '<i class="fas fa-comment-alt"></i>';
                }
            }
        }
        
        closeCommentModal();
    }
</script>
    
    <script>
        // Cambiar semana en la URL
        function cambiarSemana() {
            var semana = document.getElementById('semana').value;
            var sucursal = document.getElementById('sucursal').value;
            
            if (semana) {
                // Opcional: limpiar operarios adicionales al cambiar de semana/sucursal
                if (confirm('¿Desea limpiar los operarios adicionales al cambiar de semana/sucursal?')) {
                    // Hacer una petición para limpiar la sesión
                    fetch('limpiar_operarios_adicionales.php', {
                        method: 'POST'
                    });
                }
                
                window.location.href = 'programar_horarios_lider.php?semana=' + semana + '&sucursal=' + sucursal;
            } else {
                alert('Por favor ingrese un número de semana');
            }
        }
        
        // Cambiar sucursal en la URL
        function cambiarSucursal() {
            var semana = document.getElementById('semana').value;
            var sucursal = document.getElementById('sucursal').value;
            
            if (semana && sucursal) {
                window.location.href = 'programar_horarios_lider.php?semana=' + semana + '&sucursal=' + sucursal;
            }
        }
        
        // Actualizar estado (Activo/Inactivo) y habilitar/deshabilitar campos de hora
        function actualizarEstado(selectElement, prefix) {
            var estado = selectElement.value;
            var entrada = document.querySelector('.entrada_' + prefix);
            var salida = document.querySelector('.salida_' + prefix);
            var horasDisplay = document.querySelector('.salida_' + prefix).parentElement.nextElementSibling;
            
            if (estado === 'Activo') {
                entrada.disabled = false;
                salida.disabled = false;
                // Remover clase inactiva y aplicar clase normal o extendida según hora
                horasDisplay.classList.remove('inactive-hours');
                if (salida.value && salida.value >= '19:00') {
                    horasDisplay.classList.remove('normal-hours');
                    horasDisplay.classList.add('extended-hours');
                } else {
                    horasDisplay.classList.remove('extended-hours');
                    horasDisplay.classList.add('normal-hours');
                }
            } else {
                entrada.disabled = true;
                salida.disabled = true;
                entrada.value = '';
                salida.value = '';
                horasDisplay.textContent = '0.00';
                // Aplicar estilo para estado inactivo
                horasDisplay.classList.remove('normal-hours', 'extended-hours');
                horasDisplay.classList.add('inactive-hours');
            }
            
            // Recalcular totales cuando cambia el estado
            var operarioId = prefix.split('_')[1];
            calcularTotalHoras(operarioId);
        }
        
        // Calcular horas trabajadas en un día
        function calcularHoras(prefix) {
            var entrada = document.querySelector('.entrada_' + prefix);
            var salida = document.querySelector('.salida_' + prefix);
            var horasDisplay = document.querySelector('.salida_' + prefix).parentElement.nextElementSibling;
            
            if (!entrada.value || !salida.value) {
                horasDisplay.textContent = '0.00';
                calcularTotalHoras(prefix.split('_')[1]);
                return;
            }
            
            // Ajustar minutos a 00 o 30
            ajustarMinutos(entrada);
            ajustarMinutos(salida);
            
            // Calcular diferencia de horas
            var horaEntrada = new Date('2000-01-01T' + entrada.value + ':00');
            var horaSalida = new Date('2000-01-01T' + salida.value + ':00');
            
            // Validar que la hora de salida sea posterior a la de entrada
            if (horaSalida <= horaEntrada) {
                alert('La hora de salida debe ser posterior a la de entrada');
                salida.value = '';
                horasDisplay.textContent = '0.00';
                calcularTotalHoras(prefix.split('_')[1]);
                return;
            }
            
            var diffMs = horaSalida - horaEntrada;
            var diffHrs = diffMs / (1000 * 60 * 60);
            
            // Actualizar display de horas
            horasDisplay.textContent = diffHrs.toFixed(2);
            
            // Cambiar color según hora de salida
            if (salida.value >= '19:00') {
                horasDisplay.classList.remove('normal-hours');
                horasDisplay.classList.add('extended-hours');
            } else {
                horasDisplay.classList.remove('extended-hours');
                horasDisplay.classList.add('normal-hours');
            }
            
            // Calcular total de horas por operario
            calcularTotalHoras(prefix.split('_')[1]);
        }

        // Función para ajustar minutos a 00 o 30
        function ajustarMinutos(inputTime) {
            if (!inputTime.value) return;
            
            var timeParts = inputTime.value.split(':');
            var hours = parseInt(timeParts[0]);
            var minutes = parseInt(timeParts[1]);
            
            // Redondear minutos a 00 o 30
            if (minutes < 15) {
                minutes = 0;
            } else if (minutes < 45) {
                minutes = 30;
            } else {
                minutes = 0;
                hours += 1;
            }
            
            // Asegurar que no pase de 23:59
            if (hours >= 24) {
                hours = 23;
                minutes = 59;
            }
            
            // Formatear con dos dígitos
            var formattedHours = hours.toString().padStart(2, '0');
            var formattedMinutes = minutes.toString().padStart(2, '0');
            
            inputTime.value = formattedHours + ':' + formattedMinutes;
        }
        
        // Calcular total de horas por operario
        function calcularTotalHoras(operarioId) {
            let total = 0;
            let diasActivos = 0;
            let diasExtendidos = 0;
            const dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
            
            dias.forEach(function(dia) {
                const selectEstado = document.querySelector(`select[name="horarios[${operarioId}][${dia}_estado]"]`);
                if (!selectEstado) return;
                
                const estado = selectEstado.value;
                const salida = document.querySelector(`.salida_${dia}_${operarioId}`)?.value;
                
                if (estado === 'Activo') {
                    diasActivos++;
                    
                    // Verificar si es día extendido (salida después de 20:00)
                    if (salida && salida >= '20:00') {
                        diasExtendidos++;
                    }
                }
                
                const horasDisplay = document.querySelector(`.salida_${dia}_${operarioId}`)?.parentElement?.nextElementSibling;
                if (horasDisplay) {
                    total += parseFloat(horasDisplay.textContent) || 0;
                }
            });
            
            // Calcular horas teóricas:
            // Días normales (8 horas) + días extendidos (7.5 horas)
            const diasNormales = diasActivos - diasExtendidos;
            const horasTeoricas = (diasNormales * 8) + (diasExtendidos * 7.5);
            
            // Actualizar el total en la celda correspondiente
            const celdaTotal = document.getElementById('total_horas_' + operarioId);
            if (celdaTotal) {
                celdaTotal.textContent = total.toFixed(2) + ' / ' + horasTeoricas.toFixed(2);
            }
        }
        
        // Modificar los event listeners para los inputs de tiempo
        document.querySelectorAll('input[type="time"]').forEach(function(input) {
            input.addEventListener('change', function() {
                ajustarMinutos(this);
                
                // Obtener el prefijo (día_operario) del nombre del input
                var nameParts = this.name.match(/horarios\[(\d+)\]\[(\w+)_(entrada|salida)\]/);
                if (nameParts) {
                    var operarioId = nameParts[1];
                    var dia = nameParts[2];
                    calcularHoras(dia + '_' + operarioId);
                }
            });
        });
        
        // Prevenir envío del formulario al presionar Enter
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                return false;
            }
        });
        
        function validarHorarioOperario(operarioId) {
            const dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
            let valido = true;
            
            dias.forEach(dia => {
                const estado = document.querySelector(`select[name="horarios[${operarioId}][${dia}_estado]"]`).value;
                const entrada = document.querySelector(`input[name="horarios[${operarioId}][${dia}_entrada]"]`);
                const salida = document.querySelector(`input[name="horarios[${operarioId}][${dia}_salida]"]`);
                
                if (estado === 'Activo' && (!entrada.value || !salida.value)) {
                    entrada.style.borderColor = '#dc3545';
                    salida.style.borderColor = '#dc3545';
                    valido = false;
                    
                    // Mostrar notificación y desplazarse al campo
                    if (!entrada.value) {
                        mostrarNotificacion(`Falta hora de entrada para ${dia}`, 'error');
                        entrada.focus();
                    } else {
                        mostrarNotificacion(`Falta hora de salida para ${dia}`, 'error');
                        salida.focus();
                    }
                } else {
                    entrada.style.borderColor = '';
                    salida.style.borderColor = '';
                }
            });
            
            return valido;
        }
        
        function validarTodosHorarios() {
            const operarios = Array.from(document.querySelectorAll('tr[data-operario]')).map(tr => 
                tr.getAttribute('data-operario')
            );
            
            let todosValidos = true;
            
            operarios.forEach(operarioId => {
                if (!validarHorarioOperario(operarioId)) {
                    todosValidos = false;
                }
            });
            
            return todosValidos;
        }
        
        // Función para guardar que usa la validación mejorada
        function guardarHorarios() {
            if (validarTodosHorarios() && confirm('¿Está seguro que desea guardar todos los horarios?')) {
                const form = document.getElementById('horariosForm');
                const formData = new FormData(form);
                
                fetch(form.action || window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.ok) {
                        window.location.reload(); // Recargar para ver cambios
                    } else {
                        alert('Error al guardar: ' + response.statusText);
                    }
                })
                .catch(error => {
                    alert('Error de conexión: ' + error.message);
                });
            }
        }
        
        // Función para eliminar un operario con efectos visuales
        function eliminarOperario(boton, codOperario, idSemana, codSucursal) {
            const fila = boton.closest('tr');
            const existeEnBD = fila.getAttribute('data-existente') === 'true';
            
            if (!confirm(`¿Está seguro que desea ${existeEnBD ? 'eliminar permanentemente' : 'remover'} este operario?`)) {
                return;
            }
            
            // Efecto visual inmediato
            fila.style.transition = 'all 0.5s ease';
            fila.style.opacity = '0.5';
            fila.style.backgroundColor = '#ffebee';
            
            // Enviar solicitud al servidor si existe en BD
            if (existeEnBD && idSemana) {
                fetch('eliminar_horario_operario.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `cod_operario=${codOperario}&id_semana=${idSemana}&cod_sucursal=${codSucursal}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Efecto de eliminación completa
                        fila.style.transform = 'translateX(-100%)';
                        setTimeout(() => {
                            fila.remove();
                            mostrarNotificacion('Operario eliminado permanentemente de la base de datos', 'success');
                        }, 500);
                    } else {
                        // Revertir efectos si falla
                        fila.style.opacity = '1';
                        fila.style.backgroundColor = '';
                        mostrarNotificacion('Error al eliminar: ' + (data.message || 'Error desconocido'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    fila.style.opacity = '1';
                    fila.style.backgroundColor = '';
                    mostrarNotificacion('Error al conectar con el servidor', 'error');
                });
            } else {
                // Efecto de eliminación visual (para operarios no guardados)
                fila.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    fila.remove();
                    mostrarNotificacion('Operario removido de la vista (no estaba en la base de datos)', 'info');
                }, 500);
            }
        }
        
        // Función para mostrar notificaciones bonitas
        function mostrarNotificacion(mensaje, tipo = 'info') {
            const estilos = {
                success: { background: '#d4edda', color: '#155724', icon: 'check-circle' },
                error: { background: '#f8d7da', color: '#721c24', icon: 'exclamation-circle' },
                info: { background: '#e2e3e5', color: '#383d41', icon: 'info-circle' }
            };
            
            const estilo = estilos[tipo] || estilos.info;
            
            const notificacion = document.createElement('div');
            notificacion.style.position = 'fixed';
            notificacion.style.top = '20px';
            notificacion.style.right = '20px';
            notificacion.style.padding = '15px';
            notificacion.style.borderRadius = '4px';
            notificacion.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
            notificacion.style.backgroundColor = estilo.background;
            notificacion.style.color = estilo.color;
            notificacion.style.zIndex = '1000';
            notificacion.style.display = 'flex';
            notificacion.style.alignItems = 'center';
            notificacion.style.gap = '10px';
            notificacion.style.maxWidth = '300px';
            notificacion.innerHTML = `
                <i class="fas fa-${estilo.icon}" style="font-size: 1.2rem;"></i>
                <span>${mensaje}</span>
            `;
            
            document.body.appendChild(notificacion);
            
            setTimeout(() => {
                notificacion.style.opacity = '0';
                notificacion.style.transition = 'opacity 0.5s ease';
                setTimeout(() => notificacion.remove(), 500);
            }, 3000);
        }
        
        function actualizarOperarios() {
            if (confirm('¿Desea actualizar la lista de operarios?\n\nNota: Cualquier cambio no guardado se perderá.\nRecomendamos guardar los cambios antes de actualizar.')) {
                // Recargar la página manteniendo los parámetros actuales
                const urlParams = new URLSearchParams(window.location.search);
                const semana = urlParams.get('semana');
                const sucursal = urlParams.get('sucursal');
                
                if (semana && sucursal) {
                    window.location.href = `programar_horarios_lider.php?semana=${semana}&sucursal=${sucursal}`;
                } else {
                    window.location.reload();
                }
            }
        }
        
        function agregarOperario() {
            const select = document.querySelector('.operario-agregar-container select');
            const codOperario = select.value;
            const semana = document.getElementById('semana').value;
            const sucursal = document.getElementById('sucursal').value;
            
            if (!codOperario) {
                alert('Por favor seleccione un colaborador');
                return;
            }
            
            if (!semana) {
                alert('Por favor ingrese un número de semana primero');
                document.getElementById('semana').focus();
                return;
            }
            
            if (!sucursal) {
                alert('Por favor seleccione una sucursal primero');
                return;
            }
            
            // Crear formulario y enviar
            const form = document.createElement('form');
            form.method = 'post';
            form.style.display = 'none';
            
            const inputSemana = document.createElement('input');
            inputSemana.type = 'hidden';
            inputSemana.name = 'semana';
            inputSemana.value = semana;
            
            const inputSucursal = document.createElement('input');
            inputSucursal.type = 'hidden';
            inputSucursal.name = 'sucursal';
            inputSucursal.value = sucursal;
            
            const inputCodOperario = document.createElement('input');
            inputCodOperario.type = 'hidden';
            inputCodOperario.name = 'cod_operario';
            inputCodOperario.value = codOperario;
            
            const inputAgregar = document.createElement('input');
            inputAgregar.type = 'hidden';
            inputAgregar.name = 'agregar_operario';
            inputAgregar.value = '1';
            
            form.appendChild(inputSemana);
            form.appendChild(inputSucursal);
            form.appendChild(inputCodOperario);
            form.appendChild(inputAgregar);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        // Función para mostrar todos los operarios de la sucursal
        function mostrarTodosOperarios() {
            if (confirm('¿Desea ver todos los operarios de la sucursal?\n\n⚠️  Los cambios no guardados se perderán.\nRecomendamos guardar antes de cambiar la vista.')) {
                // Agregar parámetro para forzar mostrar todos
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('mostrar_todos', '1');
                
                // Mantener semana y sucursal
                const semana = document.getElementById('semana').value;
                const sucursal = document.getElementById('sucursal').value;
                
                window.location.href = `programar_horarios_lider.php?semana=${semana}&sucursal=${sucursal}&mostrar_todos=1`;
            }
        }
        
        // Calcular todos los totales al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            // Obtener todos los operarios que tienen filas en la tabla
            const filasOperarios = document.querySelectorAll('tr[data-operario]');
            
            // Calcular totales para cada operario
            filasOperarios.forEach(fila => {
                const operarioId = fila.getAttribute('data-operario');
                calcularTotalHoras(operarioId);
            });
        });
        
// ========== SISTEMA DE GRÁFICOS ==========

// Variables globales para el modo del gráfico
let modoGraficoActual = 'horarios'; // Por defecto en línea de tiempo
let diaSeleccionado = 'lunes';
let diaSeleccionadoHorarios = 'lunes';

// Función para cambiar entre modos
function cambiarModoGrafico(modo) {
    modoGraficoActual = modo;
    
    // Actualizar botones
    document.getElementById('btnModoSemanal').classList.toggle('activo', modo === 'semanal');
    document.getElementById('btnModoDiario').classList.toggle('activo', modo === 'diario');
    document.getElementById('btnModoHorarios').classList.toggle('activo', modo === 'horarios');
    
    // Mostrar/ocultar selectores
    document.getElementById('selectorDia').style.display = modo === 'diario' ? 'block' : 'none';
    document.getElementById('selectorDiaHorarios').style.display = modo === 'horarios' ? 'block' : 'none';
    
    // Actualizar título e icono
    const titulo = document.getElementById('tituloModoGrafico');
    const icono = document.getElementById('iconoModoGrafico');
    const leyenda = document.getElementById('textoLeyendaLimite');
    const leyendaInactivo = document.getElementById('leyendaInactivo');
    
    if (modo === 'semanal') {
        titulo.textContent = 'Distribución Semanal de Horas';
        icono.className = 'fas fa-chart-bar';
        leyenda.textContent = 'Máximo Recomendado (48h)';
        leyendaInactivo.style.display = 'none';
    } else if (modo === 'diario') {
        titulo.textContent = `Distribución de Horas - ${capitalizeFirst(diaSeleccionado)}`;
        icono.className = 'fas fa-calendar-day';
        leyenda.textContent = 'Jornada Completa (8h)';
        leyendaInactivo.style.display = 'flex';
    } else {
        titulo.textContent = `Línea de Tiempo - ${capitalizeFirst(diaSeleccionadoHorarios)}`;
        icono.className = 'fas fa-timeline';
        leyenda.textContent = 'Distribución horaria por colaborador';
        leyendaInactivo.style.display = 'none';
    }
    
    // Actualizar el gráfico
    actualizarGraficoHoras();
}

// Función para capitalizar la primera letra
function capitalizeFirst(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}

// Función para actualizar el gráfico según el modo
function actualizarGraficoHoras() {
    if (modoGraficoActual === 'diario') {
        diaSeleccionado = document.getElementById('selectDia').value;
        actualizarGraficoDiario();
    } else if (modoGraficoActual === 'horarios') {
        diaSeleccionadoHorarios = document.getElementById('selectDiaHorarios').value;
        actualizarGraficoDistribucionHorarios();
    } else {
        actualizarGraficoSemanal();
    }
}

// Función para el gráfico semanal
function actualizarGraficoSemanal() {
    const operarios = Array.from(document.querySelectorAll('tr[data-operario]'));
    const datosGrafico = [];
    
    // Recolectar datos de cada operario
    operarios.forEach(fila => {
        const operarioId = fila.getAttribute('data-operario');
        const nombreElement = fila.querySelector('.operario-cell');
        const nombre = nombreElement ? nombreElement.textContent.trim().split('\n')[0] : `Operario ${operarioId}`;
        const totalHorasElement = document.getElementById(`total_horas_${operarioId}`);
        
        if (totalHorasElement) {
            const textoTotal = totalHorasElement.textContent;
            // Extraer solo el primer número (horas actuales)
            const horasMatch = textoTotal.match(/(\d+\.?\d*)/);
            const horas = horasMatch ? parseFloat(horasMatch[1]) : 0;
            
            datosGrafico.push({
                id: operarioId,
                nombre: nombre,
                horas: horas,
                encimaLimite: horas > 48 // Límite de 48 horas semanales
            });
        }
    });
    
    // Ordenar por horas (descendente)
    datosGrafico.sort((a, b) => b.horas - a.horas);
    
    // Generar HTML del gráfico semanal
    generarHTMLGraficoSemanal(datosGrafico);
}

// Función para el gráfico diario
function actualizarGraficoDiario() {
    const operarios = Array.from(document.querySelectorAll('tr[data-operario]'));
    const datosGrafico = [];
    const dia = diaSeleccionado;
    
    // Recolectar datos de cada operario para el día seleccionado
    operarios.forEach(fila => {
        const operarioId = fila.getAttribute('data-operario');
        const nombreElement = fila.querySelector('.operario-cell');
        const nombre = nombreElement ? nombreElement.textContent.trim().split('\n')[0] : `Operario ${operarioId}`;
        
        // Obtener estado y horas del día
        const selectEstado = document.querySelector(`select[name="horarios[${operarioId}][${dia}_estado]"]`);
        const inputEntrada = document.querySelector(`input[name="horarios[${operarioId}][${dia}_entrada]"]`);
        const inputSalida = document.querySelector(`input[name="horarios[${operarioId}][${dia}_salida]"]`);
        const horasDisplay = document.querySelector(`.salida_${dia}_${operarioId}`)?.parentElement?.nextElementSibling;
        
        if (selectEstado && horasDisplay) {
            const estado = selectEstado.value;
            const horasTexto = horasDisplay.textContent;
            const horas = estado === 'Activo' ? parseFloat(horasTexto) || 0 : 0;
            const esActivo = estado === 'Activo';
            const encimaLimite = horas > 8; // Límite de 8 horas diarias
            
            datosGrafico.push({
                id: operarioId,
                nombre: nombre,
                horas: horas,
                encimaLimite: encimaLimite,
                esActivo: esActivo,
                estado: estado
            });
        }
    });
    
    // Ordenar por horas (descendente)
    datosGrafico.sort((a, b) => b.horas - a.horas);
    
    // Generar HTML del gráfico diario
    generarHTMLGraficoDiario(datosGrafico, dia);
}

// ========== LÍNEA DE TIEMPO ==========
function actualizarGraficoDistribucionHorarios() {
    const operarios = Array.from(document.querySelectorAll('tr[data-operario]'));
    const dia = diaSeleccionadoHorarios;
    
    // Configuración de la línea de tiempo
    const horaInicio = 6; // 6:00 AM
    const horaFin = 22;   // 10:00 PM
    const totalHoras = horaFin - horaInicio;
    
    const operariosConHorarios = [];
    
    // Recolectar datos de operarios
    operarios.forEach(fila => {
        const operarioId = fila.getAttribute('data-operario');
        const nombreElement = fila.querySelector('.operario-cell');
        const nombre = nombreElement ? nombreElement.textContent.trim().split('\n')[0] : `Operario ${operarioId}`;
        
        const selectEstado = document.querySelector(`select[name="horarios[${operarioId}][${dia}_estado]"]`);
        const inputEntrada = document.querySelector(`input[name="horarios[${operarioId}][${dia}_entrada]"]`);
        const inputSalida = document.querySelector(`input[name="horarios[${operarioId}][${dia}_salida]"]`);
        const horasDisplay = document.querySelector(`.salida_${dia}_${operarioId}`)?.parentElement?.nextElementSibling;
        
        if (selectEstado && inputEntrada && inputSalida) {
            const estado = selectEstado.value;
            const entrada = inputEntrada.value;
            const salida = inputSalida.value;
            const horas = horasDisplay ? parseFloat(horasDisplay.textContent) || 0 : 0;
            
            // Calcular posición y ancho para la barra
            let left = 0;
            let width = 0;
            let claseTurno = 'inactivo';
            
            if (estado === 'Activo' && entrada && salida) {
                const [horaEnt, minEnt] = entrada.split(':').map(Number);
                const [horaSal, minSal] = salida.split(':').map(Number);
                
                // Convertir a minutos desde las 6:00 AM
                const minutosEntrada = (horaEnt - horaInicio) * 60 + minEnt;
                const minutosSalida = (horaSal - horaInicio) * 60 + minSal;
                
                left = (minutosEntrada / 60) * 100 / totalHoras;
                width = ((minutosSalida - minutosEntrada) / 60) * 100 / totalHoras;
                
                // Determinar turno por color
                if (horaEnt >= 6 && horaSal <= 12) {
                    claseTurno = 'manana';
                } else if (horaEnt >= 12 && horaSal <= 18) {
                    claseTurno = 'tarde';
                } else if (horaEnt >= 18) {
                    claseTurno = 'noche';
                } else {
                    claseTurno = 'mixto';
                }
            }
            
            operariosConHorarios.push({
                id: operarioId,
                nombre: nombre,
                entrada: entrada,
                salida: salida,
                horas: horas,
                estado: estado,
                left: left,
                width: width,
                claseTurno: claseTurno,
                activo: estado === 'Activo' && entrada && salida
            });
        }
    });
    
    // Ordenar operarios por hora de entrada
    operariosConHorarios.sort((a, b) => {
        if (!a.activo && !b.activo) return 0;
        if (!a.activo) return 1;
        if (!b.activo) return -1;
        return a.entrada.localeCompare(b.entrada);
    });
    
    // Generar HTML de la línea de tiempo
    generarHTMLTimeline(operariosConHorarios, horaInicio, horaFin, totalHoras);
}

// Función para generar el HTML de la línea de tiempo
function generarHTMLTimeline(operarios, horaInicio, horaFin, totalHoras) {
    const contenedorGrafico = document.getElementById('graficoHoras');
    
    let html = `
        <div class="grafico-timeline">
            <h4 style="text-align: center; color: #0E544C; margin-bottom: 20px;">
                Línea de Tiempo - ${capitalizeFirst(diaSeleccionadoHorarios)}
            </h4>
            
            <div class="timeline-container">
                <!-- Eje X con horas -->
                <div class="timeline-eje-x"></div>
                
                <div class="timeline-horas">
    `;
    
    // Generar marcas de horas
    for (let hora = horaInicio; hora <= horaFin; hora++) {
        for (let minuto = 0; minuto < 60; minuto += 30) {
            const horaActual = hora + minuto / 60;
            const porcentaje = ((horaActual - horaInicio) / totalHoras) * 100;
            
            if (minuto === 0) {
                // Hora completa
                html += `
                    <div class="timeline-hora" style="left: ${porcentaje}%">
                        ${hora.toString().padStart(2, '0')}:00
                    </div>
                `;
            } else {
                // Media hora
                html += `
                    <div class="timeline-hora media-hora" style="left: ${porcentaje}%">
                        ${hora.toString().padStart(2, '0')}:30
                    </div>
                `;
            }
        }
    }
    
    html += `
                </div>
                
                <!-- Marcadores de fondo -->
                <div class="timeline-marcadores">
    `;
    
    // Generar marcadores de fondo
    for (let hora = horaInicio; hora <= horaFin; hora++) {
        for (let minuto = 0; minuto < 60; minuto += 30) {
            const horaActual = hora + minuto / 60;
            const porcentaje = ((horaActual - horaInicio) / totalHoras) * 100;
            const clase = minuto === 0 ? 'hora-completa' : '';
            
            html += `<div class="timeline-marcador ${clase}" style="left: ${porcentaje}%"></div>`;
        }
    }
    
    html += `
                </div>
                
                <!-- Filas de operarios -->
                <div class="timeline-filas">
    `;
    
    // Generar filas de operarios
    operarios.forEach(operario => {
        const textoHorario = operario.activo ? 
            `${operario.entrada} - ${operario.salida} (${operario.horas.toFixed(1)}h)` : 
            operario.estado;
        
        html += `
            <div class="timeline-fila" data-operario="${operario.id}">
                <div class="timeline-nombre" title="${operario.nombre}">
                    ${operario.nombre.length > 20 ? operario.nombre.substring(0, 20) + '...' : operario.nombre}
                </div>
                <div class="timeline-barra-container">
        `;
        
        if (operario.activo) {
            html += `
                <div class="timeline-barra ${operario.claseTurno}" 
                     style="left: ${operario.left}%; width: ${operario.width}%;"
                     onclick="resaltarOperarioEnTabla(${operario.id})"
                     title="${operario.nombre}: ${textoHorario}">
                    ${operario.entrada} - ${operario.salida}
                </div>
            `;
        } else {
            html += `
                <div class="timeline-barra inactivo" 
                     style="left: 0; width: 100%;"
                     onclick="resaltarOperarioEnTabla(${operario.id})"
                     title="${operario.nombre}: ${textoHorario}">
                    ${operario.estado}
                </div>
            `;
        }
        
        html += `
                </div>
            </div>
        `;
    });
    
    html += `
                </div>
            </div>
            
            <!-- Leyenda de turnos -->
            <div class="timeline-leyenda-turnos">
                <div class="leyenda-turno">
                    <div class="color-turno" style="background: #3498db;"></div>
                    <span>Turno Mañana</span>
                </div>
                <div class="leyenda-turno">
                    <div class="color-turno" style="background: #e74c3c;"></div>
                    <span>Turno Tarde</span>
                </div>
                <div class="leyenda-turno">
                    <div class="color-turno" style="background: #9b59b6;"></div>
                    <span>Turno Noche</span>
                </div>
                <div class="leyenda-turno">
                    <div class="color-turno" style="background: #f39c12;"></div>
                    <span>Turno Mixto</span>
                </div>
                <div class="leyenda-turno">
                    <div class="color-turno" style="background: #95a5a6;"></div>
                    <span>Inactivo</span>
                </div>
            </div>
        </div>
    `;
    
    contenedorGrafico.innerHTML = html;
}

// Funciones auxiliares para generar gráficos semanales y diarios
function generarHTMLGraficoSemanal(datos) {
    const contenedorGrafico = document.getElementById('graficoHoras');
    const maxHoras = Math.max(...datos.map(d => d.horas), 48);
    
    if (datos.length === 0) {
        contenedorGrafico.innerHTML = `
            <div style="text-align: center; padding: 50px; color: #666;">
                <i class="fas fa-chart-bar" style="font-size: 48px; margin-bottom: 15px;"></i>
                <p>No hay datos para mostrar</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    
    datos.forEach(dato => {
        const porcentaje = (dato.horas / maxHoras) * 100;
        const claseExtra = dato.encimaLimite ? 'encima-limite' : '';
        
        html += `
            <div class="grafico-barra ${claseExtra}">
                <div class="nombre-operario">
                    ${dato.nombre}
                    ${dato.encimaLimite ? '<span class="indicador-limite">+48h</span>' : ''}
                </div>
                
                <div class="barra-contenedor">
                    <div class="barra-progreso ${claseExtra}" style="width: ${porcentaje}%">
                        <span class="barra-texto">${dato.horas.toFixed(1)}h</span>
                    </div>
                    <div class="barra-marcador maximo" style="left: ${(48 / maxHoras) * 100}%"></div>
                </div>
                
                <div class="horas-totales ${claseExtra}">
                    ${dato.horas.toFixed(1)}h
                </div>
            </div>
        `;
    });
    
    contenedorGrafico.innerHTML = html;
}

function generarHTMLGraficoDiario(datos, dia) {
    const contenedorGrafico = document.getElementById('graficoHoras');
    const maxHoras = Math.max(...datos.map(d => d.horas), 8);
    
    if (datos.length === 0) {
        contenedorGrafico.innerHTML = `
            <div style="text-align: center; padding: 50px; color: #666;">
                <i class="fas fa-calendar-day" style="font-size: 48px; margin-bottom: 15px;"></i>
                <p>No hay datos para mostrar</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    
    datos.forEach(dato => {
        const porcentaje = dato.esActivo ? (dato.horas / maxHoras) * 100 : 0;
        const claseExtra = dato.encimaLimite ? 'encima-limite' : (!dato.esActivo ? 'dia-inactivo' : '');
        
        html += `
            <div class="grafico-barra ${claseExtra}">
                <div class="nombre-operario">
                    ${dato.nombre}
                    ${dato.encimaLimite ? '<span class="indicador-limite">+8h</span>' : ''}
                    ${!dato.esActivo ? `<span class="indicador-inactivo">${dato.estado}</span>` : ''}
                </div>
                
                <div class="barra-contenedor">
                    <div class="barra-progreso ${claseExtra}" style="width: ${porcentaje}%">
                        ${dato.esActivo ? `<span class="barra-texto">${dato.horas.toFixed(1)}h</span>` : ''}
                    </div>
                    <div class="barra-marcador diario" style="left: ${(8 / maxHoras) * 100}%"></div>
                </div>
                
                <div class="horas-totales ${claseExtra}">
                    ${dato.esActivo ? `${dato.horas.toFixed(1)}h` : dato.estado}
                </div>
            </div>
        `;
    });
    
    contenedorGrafico.innerHTML = html;
}

// Función para resaltar operario en la tabla
function resaltarOperarioEnTabla(operarioId) {
    // Quitar resaltado anterior
    document.querySelectorAll('tr[data-operario]').forEach(fila => {
        fila.style.background = '';
        fila.style.boxShadow = '';
    });
    
    // Resaltar la fila del operario
    const filaOperario = document.querySelector(`tr[data-operario="${operarioId}"]`);
    if (filaOperario) {
        filaOperario.style.background = '#fff3cd';
        filaOperario.style.boxShadow = '0 0 0 2px #ffc107';
        
        // Hacer scroll a la fila
        filaOperario.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Quitar el resaltado después de 3 segundos
        setTimeout(() => {
            filaOperario.style.background = '';
            filaOperario.style.boxShadow = '';
        }, 3000);
    }
}

// Función para inicializar el gráfico
function inicializarGrafico() {
    // Establecer modo horarios por defecto
    cambiarModoGrafico('horarios');
    
    // Actualizar gráfico al cargar la página
    actualizarGraficoHoras();
    
    // Observar cambios en los totales de horas
    const observer = new MutationObserver(function(mutations) {
        let debeActualizar = false;
        
        mutations.forEach(function(mutation) {
            if (mutation.type === 'characterData' || mutation.type === 'childList') {
                if (mutation.target.textContent.match(/\d+\.?\d*/)) {
                    debeActualizar = true;
                }
            }
        });
        
        if (debeActualizar) {
            setTimeout(actualizarGraficoHoras, 100);
        }
    });
    
    // Observar todas las celdas de total horas y displays de horas diarias
    document.querySelectorAll('.total-hours, .hours-display').forEach(elemento => {
        observer.observe(elemento, {
            characterData: true,
            childList: true,
            subtree: true
        });
    });
    
    // Observar cambios en selects de estado
    document.querySelectorAll('select[name*="_estado"]').forEach(select => {
        select.addEventListener('change', function() {
            setTimeout(actualizarGraficoHoras, 200);
        });
    });
}

// Inicializar gráficos cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        inicializarGrafico();
        
        // También actualizar el gráfico cuando se modifiquen los horarios
        document.querySelectorAll('input[type="time"]').forEach(elemento => {
            elemento.addEventListener('change', function() {
                setTimeout(actualizarGraficoHoras, 200);
            });
        });
    }, 500);
});

// Observar eliminación de operarios
const observerEliminacion = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.removedNodes) {
            mutation.removedNodes.forEach(function(node) {
                if (node.nodeType === 1 && node.matches('tr[data-operario]')) {
                    setTimeout(actualizarGraficoHoras, 100);
                }
            });
        }
    });
});

if (document.querySelector('tbody')) {
    observerEliminacion.observe(document.querySelector('tbody'), {
        childList: true,
        subtree: true
    });
}
    </script>
</body>
</html>