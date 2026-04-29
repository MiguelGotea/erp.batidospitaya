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
    $stmt = $conn->prepare("SELECT a.*, a.promedio_general, a.comentarios,
                                   COALESCE(CONCAT(o.Nombre, ' ', o.Apellido), a.persona) AS persona_nombre
                            FROM auditoria a
                            LEFT JOIN Operarios o ON a.operario_id = o.CodOperario
                            WHERE a.id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$registro) {
        die(prepararTexto("No se encontró el registro con ID: ") . $id);
    }
    
    // Obtener las fotos asociadas de la tabla auditoria_fotos
    $stmtFotos = $conn->prepare("SELECT ruta_foto FROM auditoria_fotos WHERE auditoria_id = :id");
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
    $pdf->Cell(0, 10, prepararTexto('AUDITORIA DE LIMPIEZA'), 0, 1, 'C');
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
    
    // Promedio General
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor($colorSecundario[0], $colorSecundario[1], $colorSecundario[2]);
    $pdf->Cell(0, 10, prepararTexto('PROMEDIO GENERAL'), 0, 1, 'C');
    
    $pdf->SetFillColor($colorPrincipal[0], $colorPrincipal[1], $colorPrincipal[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, prepararTexto('PROMEDIO: ') . (!empty($registro['promedio_general']) ? $registro['promedio_general'] : 'N/A'), 0, 1, 'C', true);
    $pdf->Ln(15);
    
    // Sección Limpieza Exterior
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor($colorSecundario[0], $colorSecundario[1], $colorSecundario[2]);
    $pdf->Cell(0, 10, prepararTexto('LIMPIEZA EXTERIOR'), 0, 1, 'C');
    
    $pdf->SetFillColor($colorPrincipal[0], $colorPrincipal[1], $colorPrincipal[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, prepararTexto('PROMEDIO: ') . (!empty($registro['promedio_exterior']) ? $registro['promedio_exterior'] : 'N/A'), 0, 1, 'C', true);
    $pdf->Ln(8);
    
    // Tabla de resultados - Exterior
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor($colorSecundario[0], $colorSecundario[1], $colorSecundario[2]);
    $pdf->Cell(140, 8, prepararTexto('ITEM'), 1, 0, 'C', true);
    $pdf->Cell(30, 8, prepararTexto('CALIFICACION'), 1, 1, 'C', true);
    
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetTextColor(0, 0, 0);
    
    $itemsExterior = [
        'Acera y cunetas correctamente barridas' => $registro['limpieza_exterior_1_1_1'] ?? null,
        'Hay basura en exteriores' => $registro['limpieza_exterior_1_1_2'] ?? null,
        'Vidrios limpios' => $registro['limpieza_exterior_1_1_3'] ?? null,
        'Cortinas metálicas están limpias' => $registro['limpieza_exterior_1_1_4'] ?? null,
        'Bolsas de basura en su contenedor' => $registro['limpieza_exterior_1_1_5'] ?? null,
        'Contenedor de basura está limpio' => $registro['limpieza_exterior_1_1_6'] ?? null,
        'Se ha regado con manguera al exterior' => $registro['limpieza_exterior_1_1_7'] ?? null,
        'Plantas ornamentales regadas y en buen estado' => $registro['limpieza_exterior_1_1_8'] ?? null,
        'Paredes externas limpias y bien pintadas' => $registro['limpieza_exterior_1_1_9'] ?? null,
        'Luces externas están limpias y sin polvo' => $registro['limpieza_exterior_1_1_10'] ?? null,
        'Cámaras externas limpias y sin polvo' => $registro['limpieza_exterior_1_1_11'] ?? null,
        'Rótulos de Pitaya, limpio y sin manchas' => $registro['limpieza_exterior_1_1_12'] ?? null,
        'Sillas y mesas externas limpias' => $registro['limpieza_exterior_1_1_13'] ?? null
    ];
    
    $fill = false;
    foreach ($itemsExterior as $item => $valor) {
        if ($pdf->GetY() > 250) {
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(140, 8, prepararTexto('ITEM'), 1, 0, 'C', true);
            $pdf->Cell(30, 8, prepararTexto('CALIFICACION'), 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 11);
        }
        
        $pdf->SetFillColor($fill ? 240 : 255, $fill ? 240 : 255, $fill ? 240 : 255);
        $pdf->Cell(140, 8, prepararTexto($item), 1, 0, 'L', $fill);
        $pdf->Cell(30, 8, (!empty($valor) || $valor === '0' ? $valor : 'N/A'), 1, 1, 'C', $fill);
        $fill = !$fill;
    }
    
    $pdf->Ln(15);
    
    // Sección Limpieza Interior
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor($colorSecundario[0], $colorSecundario[1], $colorSecundario[2]);
    $pdf->Cell(0, 10, prepararTexto('LIMPIEZA INTERIOR'), 0, 1, 'C');
    
    $pdf->SetFillColor($colorPrincipal[0], $colorPrincipal[1], $colorPrincipal[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, prepararTexto('PROMEDIO: ') . (!empty($registro['promedio_interior']) ? $registro['promedio_interior'] : 'N/A'), 0, 1, 'C', true);
    $pdf->Ln(8);
    
    // Tabla de resultados - Interior
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor($colorSecundario[0], $colorSecundario[1], $colorSecundario[2]);
    $pdf->Cell(140, 8, prepararTexto('ITEM'), 1, 0, 'C', true);
    $pdf->Cell(30, 8, prepararTexto('CALIFICACION'), 1, 1, 'C', true);
    
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetTextColor(0, 0, 0);
    
    $itemsInterior = [
        'Paredes interiores del edificio están limpias' => $registro['limpieza_interior_1_2_1'] ?? null,
        'Vidrios internos limpios y pulidos' => $registro['limpieza_interior_1_2_2'] ?? null,
        'Piso limpio y con buen aroma (lampaseado)' => $registro['limpieza_interior_1_2_3'] ?? null,
        'Sillas y mesas limpias y sin chicles por debajo' => $registro['limpieza_interior_1_2_4'] ?? null,
        'Hay música en la Tienda' => $registro['limpieza_interior_1_2_5'] ?? null,
        'Vitrinas limpias por dentro y por fuera' => $registro['limpieza_interior_1_2_6'] ?? null,
        'Área de facturación limpia y en orden' => $registro['limpieza_interior_1_2_7'] ?? null,
        'Mesas de acero y pantry, limpias y sin sarro' => $registro['limpieza_interior_1_2_8'] ?? null,
        'Productos Pitaya en orden y con su respetiva etiqueta' => $registro['limpieza_interior_1_2_9'] ?? null,
        'Paredes y techo sin telarañas' => $registro['limpieza_interior_1_2_10'] ?? null,
        'Abanicos de salón limpios y sin polvo acumulado' => $registro['limpieza_interior_1_2_11'] ?? null,
        'Bodega limpia y ordenada' => $registro['limpieza_interior_1_2_12'] ?? null,
        'Productos de bodega clasificados y etiquetados' => $registro['limpieza_interior_1_2_13'] ?? null,
        'Baños limpios y sin mal olor' => $registro['limpieza_interior_1_2_14'] ?? null,
        'Productos de mostrador no vencidos' => $registro['limpieza_interior_1_2_15'] ?? null,
        'Buena rotación de productos e insumos' => $registro['limpieza_interior_1_2_16'] ?? null,
        'Productos procesados rotulados con fecha de elaboración' => $registro['limpieza_interior_1_2_17'] ?? null,
        'Frutas sin dañar o deterioradas en cajillas' => $registro['limpieza_interior_1_2_18'] ?? null,
        'Miel y azúcar con fecha de recepción' => $registro['limpieza_interior_1_2_19'] ?? null
    ];
    
    $fill = false;
    foreach ($itemsInterior as $item => $valor) {
        if ($pdf->GetY() > 250) {
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(140, 8, prepararTexto('ITEM'), 1, 0, 'C', true);
            $pdf->Cell(30, 8, prepararTexto('CALIFICACION'), 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 11);
        }
        
        $pdf->SetFillColor($fill ? 240 : 255, $fill ? 240 : 255, $fill ? 240 : 255);
        $pdf->Cell(140, 8, prepararTexto($item), 1, 0, 'L', $fill);
        $pdf->Cell(30, 8, (!empty($valor) || $valor === '0' ? $valor : 'N/A'), 1, 1, 'C', $fill);
        $fill = !$fill;
    }
    
    $pdf->Ln(15);
    
    // Sección Limpieza de Equipos
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor($colorSecundario[0], $colorSecundario[1], $colorSecundario[2]);
    $pdf->Cell(0, 10, prepararTexto('LIMPIEZA DE EQUIPOS Y UTENSILIOS'), 0, 1, 'C');
    
    $pdf->SetFillColor($colorPrincipal[0], $colorPrincipal[1], $colorPrincipal[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, prepararTexto('PROMEDIO: ') . (!empty($registro['promedio_equipo']) ? $registro['promedio_equipo'] : 'N/A'), 0, 1, 'C', true);
    $pdf->Ln(8);
    
    // Tabla de resultados - Equipos
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor($colorSecundario[0], $colorSecundario[1], $colorSecundario[2]);
    $pdf->Cell(140, 8, prepararTexto('ITEM'), 1, 0, 'C', true);
    $pdf->Cell(30, 8, prepararTexto('CALIFICACION'), 1, 1, 'C', true);
    
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetTextColor(0, 0, 0);
    
    $itemsEquipos = [
        'Vasos de licuadoras están limpios y en buen estado' => $registro['limpieza_equipo_1_3_1'] ?? null,
        'Tapa de licuadora limpia, sin moho ni curtida' => $registro['limpieza_equipo_1_3_2'] ?? null,
        'Empaques de hule de licuadora limpios y sin residuos' => $registro['limpieza_equipo_1_3_3'] ?? null,
        'Motor de licuadoras, limpios y en buen estado (botones, patas, cable, domos)' => $registro['limpieza_equipo_1_3_4'] ?? null,
        'Refrigeradora limpia y presentable exteriormente' => $registro['limpieza_equipo_1_3_5'] ?? null,
        'Refrigeradora limpia internamente (empaques, rejilla, costados)' => $registro['limpieza_equipo_1_3_6'] ?? null,
        'Frízer limpios y presentables externamente' => $registro['limpieza_equipo_1_3_7'] ?? null,
        'Frízeres limpios internamente (empaque, rejilla sin hielo)' => $registro['limpieza_equipo_1_3_8'] ?? null,
        'Waflera luce limpia y presentable externamente' => $registro['limpieza_equipo_1_3_9'] ?? null,
        'Waflera sin costras por malas prácticas de limpieza' => $registro['limpieza_equipo_1_3_10'] ?? null,
        'Extractor de frutas limpio (canales, cable, superficie)' => $registro['limpieza_equipo_1_3_11'] ?? null,
        'Piezas plásticas de extractor limpias y no están curtidas' => $registro['limpieza_equipo_1_3_12'] ?? null,
        'Menaje en buen estado y limpio' => $registro['limpieza_equipo_1_3_13'] ?? null
    ];
    
    $fill = false;
    foreach ($itemsEquipos as $item => $valor) {
        if ($pdf->GetY() > 250) {
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(140, 8, prepararTexto('ITEM'), 1, 0, 'C', true);
            $pdf->Cell(30, 8, prepararTexto('CALIFICACION'), 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 11);
        }
        
        $pdf->SetFillColor($fill ? 240 : 255, $fill ? 240 : 255, $fill ? 240 : 255);
        $pdf->Cell(140, 8, prepararTexto($item), 1, 0, 'L', $fill);
        $pdf->Cell(30, 8, (!empty($valor) || $valor === '0' ? $valor : 'N/A'), 1, 1, 'C', $fill);
        $fill = !$fill;
    }
    
    $pdf->Ln(15);
    
    // Sección Manejo de Insumos
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor($colorSecundario[0], $colorSecundario[1], $colorSecundario[2]);
    $pdf->Cell(0, 10, prepararTexto('MANEJO DE INSUMOS'), 0, 1, 'C');
    
    $pdf->SetFillColor($colorPrincipal[0], $colorPrincipal[1], $colorPrincipal[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, prepararTexto('PROMEDIO: ') . (!empty($registro['promedio_insumos']) ? $registro['promedio_insumos'] : 'N/A'), 0, 1, 'C', true);
    $pdf->Ln(8);
    
    // Tabla de resultados - Interior
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor($colorSecundario[0], $colorSecundario[1], $colorSecundario[2]);
    $pdf->Cell(140, 8, prepararTexto('ITEM'), 1, 0, 'C', true);
    $pdf->Cell(30, 8, prepararTexto('CALIFICACION'), 1, 1, 'C', true);
    
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetTextColor(0, 0, 0);
    
    $itemsInterior = [
        'Disponibilidad de insumos' => $registro['limpieza_insumos_1_4_1'] ?? null,
        'Productos de mostrador no vencidos' => $registro['limpieza_insumos_1_4_2'] ?? null,
        'Buena rotación de productos e insumos. Primeros en entrar, primeros en salir' => $registro['limpieza_insumos_1_4_3'] ?? null,
        'Productos procesados rotulados con fecha de elaboración (naranja, limón)' => $registro['limpieza_insumos_1_4_4'] ?? null,
        'Frutas sin dañar o deterioradas en cajillas' => $registro['limpieza_insumos_1_4_5'] ?? null,
        'Miel y azúcar con fecha de recepción' => $registro['limpieza_insumos_1_4_6'] ?? null
    ];
    
    $fill = false;
    foreach ($itemsInterior as $item => $valor) {
        if ($pdf->GetY() > 250) {
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(140, 8, prepararTexto('ITEM'), 1, 0, 'C', true);
            $pdf->Cell(30, 8, prepararTexto('CALIFICACION'), 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 11);
        }
        
        $pdf->SetFillColor($fill ? 240 : 255, $fill ? 240 : 255, $fill ? 240 : 255);
        $pdf->Cell(140, 8, prepararTexto($item), 1, 0, 'L', $fill);
        $pdf->Cell(30, 8, (!empty($valor) || $valor === '0' ? $valor : 'N/A'), 1, 1, 'C', $fill);
        $fill = !$fill;
    }
    
    // Sección de comentarios - Añadir esto antes de la sección de fotos
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
        
        // Escribir el texto de comentarios con MultiCell
        $pdf->MultiCell(0, 8, prepararTexto($registro['comentarios']));
        $pdf->Ln(5);
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
    $tipoAuditoria = 'Limpieza';
    $fechaArchivo = formatFechaArchivo($registro['fecha_hora']);
    $nombreArchivo = 'Auditoria_' . $tipoAuditoria . '_' . $fechaArchivo . '.pdf';
    
    // Salida del PDF
    $pdf->Output('D', $nombreArchivo);
    
} catch (Exception $e) {
    die(prepararTexto("Error al generar el PDF: ") . $e->getMessage());
}