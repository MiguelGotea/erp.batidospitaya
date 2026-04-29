<?php
// Configuración inicial para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir la conexión a la base de datos
require 'conexion.php';

// Incluir TFPDF
require_once('tfpdf/tfpdf.php');

// Función para preparar el texto con codificación correcta
function prepararTexto($texto) {
    if (is_string($texto)) {
        return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
        // Alternativa si la anterior no funciona:
        // return utf8_decode($texto);
    }
    return $texto;
}

// Verificar si se ha pasado un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die(prepararTexto("ID no válido o no proporcionado."));
}

$id = (int)$_GET['id'];

try {
    // Obtener el registro de la base de datos
    $stmt = $conn->prepare("SELECT asv.*, asv.tipo_auditoria, asv.promedio_calificacion, asv.comentarios,
                                   COALESCE(CONCAT(o.Nombre, ' ', o.Apellido), asv.persona) AS persona_nombre
                            FROM auditoria_servicio asv
                            LEFT JOIN Operarios o ON asv.operario_id = o.CodOperario
                            WHERE asv.id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$registro) {
        die(prepararTexto("No se encontró el registro con ID: ") . $id);
    }
    
    // Obtener las fotos asociadas
    $stmtFotos = $conn->prepare("SELECT ruta_foto FROM auditoria_servicio_fotos WHERE auditoria_id = :id");
    $stmtFotos->bindParam(':id', $id, PDO::PARAM_INT);
    $stmtFotos->execute();
    $fotos = $stmtFotos->fetchAll(PDO::FETCH_COLUMN, 0);
    
    // Crear PDF
    $pdf = new tFPDF('P', 'mm', 'A4');
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();
    
    // Colores corporativos
    $colorPrincipal = array(81, 184, 172); // #51B8AC
    $colorSecundario = array(14, 84, 76);  // #0E544C
    $colorFondo = array(246, 246, 246);    // #F6F6F6
    
    // Configurar fuente
    $pdf->SetFont('Arial', '', 12);
    
    // Función para formatear fecha para nombre de archivo
    function formatFechaArchivo($fecha) {
        if (empty($fecha)) return date('Ymd_His');
        
        try {
            $date = new DateTime($fecha);
            return $date->format('Ymd_His');
        } catch (Exception $e) {
            return date('Ymd_His');
        }
    }
    
    // Función para formatear fecha para visualización
    function formatFechaVisual($fecha) {
        if (empty($fecha)) return 'N/A';
        
        $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 
                 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        
        try {
            $date = new DateTime($fecha);
            $dia = $date->format('d');
            $mes = $meses[(int)$date->format('m') - 1];
            $anio = $date->format('y');
            $hora = $date->format('H:i');
            
            if ($hora == '00:00') {
                $hora = '12:00 am';
            } else {
                $hora = $date->format('g:i a');
            }
            
            return prepararTexto("$dia-$mes-$anio $hora");
        } catch (Exception $e) {
            return prepararTexto($fecha);
        }
    }
    
    // Encabezado
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor($colorSecundario[0], $colorSecundario[1], $colorSecundario[2]);
    $pdf->Cell(0, 10, prepararTexto('AUDITORIA DE SERVICIO'), 0, 1, 'C');
    $pdf->Ln(5);
    
    // Línea decorativa
    $pdf->SetFillColor($colorPrincipal[0], $colorPrincipal[1], $colorPrincipal[2]);
    $pdf->Cell(0, 2, '', 0, 1, 'L', true);
    $pdf->Ln(8);
    
    // Información básica
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(40, 8, prepararTexto('No. Auditoria:'));
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, $registro['id'], 0, 1);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(40, 8, prepararTexto('Fecha:'));
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, formatFechaVisual($registro['fecha_hora']), 0, 1);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(40, 8, prepararTexto('Sucursal:'));
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, prepararTexto($registro['sucursal']), 0, 1);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(40, 8, prepararTexto('Verificador(a):'));
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, prepararTexto($registro['persona_nombre']), 0, 1);
    $pdf->Ln(10);
    
    // Sección de resultados
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor($colorSecundario[0], $colorSecundario[1], $colorSecundario[2]);
    $pdf->Cell(0, 10, prepararTexto('EVALUACION DE SERVICIO'), 0, 1, 'C');
    
    // Promedio
    $pdf->SetFillColor($colorPrincipal[0], $colorPrincipal[1], $colorPrincipal[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, prepararTexto('PROMEDIO: ') . (!empty($registro['promedio_calificacion']) ? $registro['promedio_calificacion'] : 'N/A'), 0, 1, 'C', true);
    $pdf->Ln(8);
    
    // Tabla de resultados
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor($colorSecundario[0], $colorSecundario[1], $colorSecundario[2]);
    $pdf->Cell(140, 8, prepararTexto('INDICADOR'), 1, 0, 'C', true);
    $pdf->Cell(30, 8, prepararTexto('CALIFICACION'), 1, 1, 'C', true);
    
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetTextColor(0, 0, 0);
    
    $items = [
        'Da la bienvenida a los clientes según protocolo' => $registro['evaluacion_servicio_4_1'] ?? null,
        'Mantiene contacto visual con el cliente' => $registro['evaluacion_servicio_4_2'] ?? null,
        'Pregunta el # de membresía de Club Pitaya' => $registro['evaluacion_servicio_4_3'] ?? null,
        'Ofrece ayuda si el cliente está indeciso' => $registro['evaluacion_servicio_4_4'] ?? null,
        'Sugiere promociones vigentes y tarjeta Club Pitaya' => $registro['evaluacion_servicio_4_5'] ?? null,
        'Sugiere el tamaño normal para los batidos' => $registro['evaluacion_servicio_4_6'] ?? null,
        'Menciona todas las opciones de endulzante' => $registro['evaluacion_servicio_4_7'] ?? null,
        'Pregunta adecuadamente el nombre del cliente' => $registro['evaluacion_servicio_4_8'] ?? null,
        'Lo llama por su nombre y repite la orden antes del cobro' => $registro['evaluacion_servicio_4_9'] ?? null,
        'Invita a esperar/sentarse mientras preparan el batido' => $registro['evaluacion_servicio_4_10'] ?? null,
        'Se llama por el nombre y repite la orden para hacer la entrega' => $registro['evaluacion_servicio_4_11'] ?? null,
        'Se despide según protocolo de servicio' => $registro['evaluacion_servicio_4_12'] ?? null,
        'Usa tono de voz y vocabulario adecuado' => $registro['evaluacion_servicio_4_13'] ?? null,
        'Posición y lenguaje corporal adecuados' => $registro['evaluacion_servicio_4_14'] ?? null,
        'No usa gestos inadecuados' => $registro['evaluacion_servicio_4_15'] ?? null
    ];
    
    $fill = false;
    foreach ($items as $item => $valor) {
        if ($pdf->GetY() > 250) {
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(140, 8, prepararTexto('INDICADOR'), 1, 0, 'C', true);
            $pdf->Cell(30, 8, prepararTexto('CALIFICACION'), 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 11);
        }
        
        $pdf->SetFillColor($fill ? 240 : 255, $fill ? 240 : 255, $fill ? 240 : 255);
        $pdf->Cell(140, 8, prepararTexto($item), 1, 0, 'L', $fill);
        $pdf->Cell(30, 8, (!empty($valor) || $valor === '0' ? $valor : 'N/A'), 1, 1, 'C', $fill);
        $fill = !$fill;
    }
    
    // Sección de comentarios - Añadir esto antes de la sección de fotos
    if (!empty($registro['comentarios'])) {
        // Verificar si necesitamos nueva página
        if ($pdf->GetY() > 220) { // Si estamos cerca del final de la página
            $pdf->AddPage();
        }
        
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor($colorSecundario[0], $colorSecundario[1], $colorSecundario[2]);
        $pdf->Cell(0, 10, prepararTexto('COMENTARIOS:'), 0, 1);
        
        // Configurar estilo para el texto de comentarios
        $pdf->SetFont('Arial', '', 11);
        $pdf->SetTextColor(0, 0, 0);
        
        // Crear un cuadro con fondo gris claro y borde izquierdo verde
        $pdf->SetFillColor(248, 248, 248); // Fondo gris claro #f8f8f8
        $pdf->SetDrawColor($colorPrincipal[0], $colorPrincipal[1], $colorPrincipal[2]); // Borde izquierdo verde
        
        // Guardar posición X
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        
        // Dibujar rectángulo de fondo
        $pdf->Rect($x, $y, 180, 20, 'F');
        
        // Dibujar borde izquierdo más grueso
        $pdf->SetLineWidth(1.5);
        $pdf->Line($x, $y, $x, $y + 20);
        $pdf->SetLineWidth(0.2);
        
        // Escribir el texto de comentarios con MultiCell para manejar saltos de línea
        $pdf->SetXY($x + 5, $y + 5); // Pequeño margen interno
        $pdf->MultiCell(170, 8, prepararTexto($registro['comentarios']));
        
        // Actualizar posición Y (MultiCell ya maneja esto automáticamente)
        // Pero necesitamos calcular cuánto espacio ocupó realmente
        $lineas = ceil($pdf->GetStringWidth($registro['comentarios']) / 170);
        $altura_usada = $lineas * 8;
        
        // Si el comentario es muy largo, ajustar el rectángulo
        if ($altura_usada > 20) {
            $pdf->Rect($x, $y, 180, $altura_usada + 10, 'F');
            $pdf->SetLineWidth(1.5);
            $pdf->Line($x, $y, $x, $y + $altura_usada + 10);
            $pdf->SetLineWidth(0.2);
            $pdf->SetY($y + $altura_usada + 15);
        } else {
            $pdf->SetY($y + 25);
        }
    }
    
    // Sección de fotos en el PDF - Versión mejorada con manejo de saltos de página
    if (!empty($fotos)) {
        $pdf->Ln(10);
        
        // Verificar espacio disponible antes de empezar la galería
        $altura_necesaria = 70; // Altura aproximada que necesitamos (título + 1 fila de fotos)
        if ($pdf->GetY() + $altura_necesaria > 250) {
            $pdf->AddPage();
        }
        
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor($colorSecundario[0], $colorSecundario[1], $colorSecundario[2]);
        $pdf->Cell(0, 10, prepararTexto('GALERÍA DE FOTOS:'), 0, 1);
        
        // Configuración de la galería
        $margen = 15; // Margen izquierdo y derecho
        $ancho_disponible = 210 - ($margen * 2); // Ancho total disponible
        $fotos_por_fila = 3;
        $espacio_entre = 5; // Espacio entre fotos
        $ancho_foto = ($ancho_disponible - ($espacio_entre * ($fotos_por_fila - 1))) / $fotos_por_fila;
        $alto_maximo = 50; // Altura máxima por fila de fotos
        $margen_inferior = 20; // Margen inferior para evitar que quede muy pegada al borde
        
        // Posición inicial
        $x = $margen;
        $y = $pdf->GetY() + 5;
        $fila_actual = 0;
        
        foreach ($fotos as $index => $fotoPath) {
            // Verificar si necesitamos nueva página antes de cada nueva fila
            if ($fila_actual == 0 && $y + $alto_maximo > (297 - $margen_inferior)) {
                $pdf->AddPage();
                $y = 15;
                $x = $margen;
            }
            
            if (file_exists($fotoPath)) {
                try {
                    // Obtener dimensiones de la imagen
                    list($img_width, $img_height) = getimagesize($fotoPath);
                    $relacion_aspecto = $img_height / $img_width;
                    
                    // Calcular dimensiones para mantener proporción
                    $alto_foto = $ancho_foto * $relacion_aspecto;
                    
                    // Si la foto es muy alta, ajustamos para que no exceda el alto máximo
                    if ($alto_foto > $alto_maximo) {
                        $factor_escala = $alto_maximo / $alto_foto;
                        $alto_foto = $alto_maximo;
                        $ancho_foto_ajustado = $ancho_foto * $factor_escala;
                        
                        // Centrar la foto en su espacio asignado
                        $x_centrado = $x + (($ancho_foto - $ancho_foto_ajustado) / 2);
                        $pdf->Image($fotoPath, $x_centrado, $y, $ancho_foto_ajustado, $alto_foto, '', '', 'T');
                    } else {
                        $pdf->Image($fotoPath, $x, $y, $ancho_foto, $alto_foto, '', '', 'T');
                    }
                    
                    // Marco alrededor de la foto (opcional, estilo Instagram)
                    $pdf->SetDrawColor(200, 200, 200);
                    $pdf->Rect($x, $y, $ancho_foto, $alto_foto);
                    
                } catch (Exception $e) {
                    // Manejar error sin romper el flujo
                    $pdf->SetXY($x, $y);
                    $pdf->SetFont('Arial', '', 8);
                    $pdf->Cell($ancho_foto, 10, prepararTexto('Error al cargar imagen'), 0, 0, 'C');
                }
            } else {
                // Foto no encontrada
                $pdf->SetXY($x, $y);
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell($ancho_foto, 10, prepararTexto('Foto no disponible'), 0, 0, 'C');
            }
            
            // Actualizar posición para la siguiente foto
            $x += $ancho_foto + $espacio_entre;
            $fila_actual++;
            
            // Si completamos una fila, pasar a la siguiente
            if ($fila_actual >= $fotos_por_fila) {
                $fila_actual = 0;
                $x = $margen;
                $y += $alto_maximo + $espacio_entre;
            }
        }
        
        // Actualizar posición Y después de las fotos
        $pdf->SetY($y + $alto_maximo + $espacio_entre);
        
        // Pequeño pie de página para la galería
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->Cell(0, 5, prepararTexto(sprintf('Total de fotos: %d', count($fotos))), 0, 1, 'R');
    }
    
    // Generar nombre del archivo
    $tipoAuditoria = 'Servicio';
    $fechaArchivo = formatFechaArchivo($registro['fecha_hora']);
    $nombreArchivo = 'Auditoria_' . $tipoAuditoria . '_' . $fechaArchivo . '.pdf';
    
    // Salida del PDF
    $pdf->Output('D', $nombreArchivo);
    
} catch (Exception $e) {
    die(prepararTexto("Error al generar el PDF: ") . $e->getMessage());
}