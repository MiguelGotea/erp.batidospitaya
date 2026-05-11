<?php
// obtener_detalles_cambios.php
// require_once '../../includes/auth.php';
// require_once '../../includes/funciones.php';
require_once '../../core/auth/auth.php'; // Se centralizó el acceso a auth, db y funciones
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('confirmar_horarios', 'vista', $cargoOperario)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No tiene permiso para realizar esta acción']);
    exit();
}


// Función para obtener operario por código (debe estar definida o incluirse)
function obtenerOperarioPorCodigo($codOperario) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT o.CodOperario, o.Nombre, o.Apellido 
        FROM Operarios o
        WHERE o.CodOperario = ? 
        AND o.Operativo = 1
        AND (o.Fin IS NULL OR o.Fin >= CURDATE())
    ");
    $stmt->execute([$codOperario]);
    return $stmt->fetch();
}

// Función para obtener horarios del líder
function obtenerHorariosLiderPorSemanaYSucursal($idSemana, $codSucursal) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT cod_operario, 
               lunes_estado, lunes_comentario, lunes_entrada, lunes_salida,
               martes_estado, martes_comentario, martes_entrada, martes_salida,
               miercoles_estado, miercoles_comentario, miercoles_entrada, miercoles_salida,
               jueves_estado, jueves_comentario, jueves_entrada, jueves_salida,
               viernes_estado, viernes_comentario, viernes_entrada, viernes_salida,
               sabado_estado, sabado_comentario, sabado_entrada, sabado_salida,
               domingo_estado, domingo_comentario, domingo_entrada, domingo_salida
        FROM HorariosSemanales
        WHERE id_semana_sistema = ? AND cod_sucursal = ?
    ");
    $stmt->execute([$idSemana, $codSucursal]);
    
    $resultados = [];
    while ($row = $stmt->fetch()) {
        $resultados[$row['cod_operario']] = $row;
    }
    return $resultados;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $semana = $_POST['semana'] ?? null;
    $sucursal = $_POST['sucursal'] ?? null;
    
    if (!$semana || !$sucursal) {
        echo json_encode(['success' => false, 'message' => 'Faltan parámetros']);
        exit;
    }
    
    // Obtener semana del sistema
    $semanaObj = obtenerSemanaPorNumero($semana);
    if (!$semanaObj) {
        echo json_encode(['success' => false, 'message' => 'Semana no válida']);
        exit;
    }
    
    // Obtener horarios del líder
    $horariosLider = obtenerHorariosLiderPorSemanaYSucursal($semanaObj['id'], $sucursal);
    
    // Obtener todos los horarios de operaciones
    $stmt = $conn->prepare("SELECT * FROM HorariosSemanalesOperaciones WHERE id_semana_sistema = ? AND cod_sucursal = ?");
    $stmt->execute([$semanaObj['id'], $sucursal]);
    $horariosOperaciones = [];
    
    while ($row = $stmt->fetch()) {
        $horariosOperaciones[$row['cod_operario']] = $row;
    }
    
    // Función para detectar cambios
    function detectarCambiosParaModal($horariosLider, $horariosOperaciones) {
        $cambios = [];
        
        foreach ($horariosLider as $codOperario => $horarioLider) {
            if (!isset($horariosOperaciones[$codOperario])) {
                continue;
            }
            
            $horarioOperaciones = $horariosOperaciones[$codOperario];
            $dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
            
            foreach ($dias as $dia) {
                $campos = ['estado', 'comentario', 'entrada', 'salida'];
                
                foreach ($campos as $campo) {
                    $campoLider = $horarioLider["{$dia}_{$campo}"] ?? '';
                    $campoOperaciones = $horarioOperaciones["{$dia}_{$campo}"] ?? '';
                    
                    // Normalizar valores null
                    $campoLider = $campoLider === null ? '' : $campoLider;
                    $campoOperaciones = $campoOperaciones === null ? '' : $campoOperaciones;
                    
                    if ($campoLider != $campoOperaciones) {
                        $cambios[$codOperario][$dia][$campo] = [
                            'lider' => $campoLider,
                            'operaciones' => $campoOperaciones
                        ];
                    }
                }
            }
        }
        
        return $cambios;
    }
    
    $cambios = detectarCambiosParaModal($horariosLider, $horariosOperaciones);
    
    if (empty($cambios)) {
        echo json_encode(['success' => true, 'html' => '<p>No se detectaron cambios entre los horarios del líder y los confirmados por operaciones.</p>']);
        exit;
    }
    
    // Generar HTML con los detalles
    $html = '<div class="cambios-lista">';
    $html .= '<p>Se detectaron cambios en ' . count($cambios) . ' colaborador(es):</p>';
    
    foreach ($cambios as $codOperario => $dias) {
        $operario = obtenerOperarioPorCodigo($codOperario);
        $nombreOperario = $operario ? $operario['Nombre'] . ' ' . $operario['Apellido'] : 'Código: ' . $codOperario;
        
        $html .= '<div class="cambio-operario">';
        $html .= '<h4>' . htmlspecialchars($nombreOperario) . ' (' . $codOperario . ')</h4>';
        $html .= '<ul>';
        
        foreach ($dias as $dia => $campos) {
            $html .= '<li><strong>' . ucfirst($dia) . ':</strong>';
            $html .= '<ul>';
            
            foreach ($campos as $campo => $valores) {
                $html .= '<li>' . ucfirst($campo) . ': ';
                $html .= 'Líder: <span style="color: #dc3545;">"' . htmlspecialchars($valores['lider']) . '"</span>, ';
                $html .= 'Operaciones: <span style="color: #28a745;">"' . htmlspecialchars($valores['operaciones']) . '"</span>';
                $html .= '</li>';
            }
            
            $html .= '</ul></li>';
        }
        
        $html .= '</ul></div>';
    }
    
    $html .= '</div>';
    
    echo json_encode(['success' => true, 'html' => $html]);
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>