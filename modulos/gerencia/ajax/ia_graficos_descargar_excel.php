<?php
require_once '../../../core/database/conexion.php';
require_once '../../../core/auth/auth.php';
require_once '../../../core/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $estructura = isset($input['estructura']) ? $input['estructura'] : null;
    $datos = isset($input['datos']) ? $input['datos'] : [];
    $estadisticas = isset($input['estadisticas']) ? $input['estadisticas'] : null;

    if (!$estructura || empty($datos)) {
        throw new Exception('Datos incompletos');
    }

    // Crear nuevo spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Título
    $sheet->setCellValue('A1', 'REPORTE DE VENTAS - BATIDOS PITAYA');
    $sheet->mergeCells('A1:C1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Información del gráfico
    $row = 3;
    $sheet->setCellValue('A' . $row, 'Descripción:');
    $sheet->setCellValue('B' . $row, $estructura['descripcion_grafico'] ?? '');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);

    $row++;
    $sheet->setCellValue('A' . $row, 'Tipo de Gráfico:');
    $sheet->setCellValue('B' . $row, ucfirst($estructura['tipo_grafico']));

    $row++;
    $sheet->setCellValue('A' . $row, 'Métrica:');
    $sheet->setCellValue('B' . $row, $estructura['metrica_nombre']);

    $row++;
    $sheet->setCellValue('A' . $row, 'Fecha de Generación:');
    $sheet->setCellValue('B' . $row, date('d/m/Y H:i:s'));

    // Estadísticas
    if ($estadisticas) {
        $row += 2;
        $sheet->setCellValue('A' . $row, 'ESTADÍSTICAS');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);

        $row++;
        foreach ($estadisticas as $key => $stat) {
            $sheet->setCellValue('A' . $row, $stat['label'] . ':');

            $valor = $stat['valor'];
            if ($stat['formato'] === 'moneda') {
                $sheet->setCellValue('B' . $row, 'C$ ' . number_format($valor, 2));
            } else {
                $sheet->setCellValue('B' . $row, number_format($valor, 0));
            }

            $row++;
        }
    }

    // Tabla de datos
    $row += 2;
    $headerRow = $row;

    $sheet->setCellValue('A' . $row, $estructura['dimension_nombre'] ?? 'Dimensión');
    $sheet->setCellValue('B' . $row, $estructura['metrica_nombre'] ?? 'Valor');

    // Estilo del encabezado
    $sheet->getStyle('A' . $row . ':B' . $row)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0E544C']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);

    // Datos
    foreach ($datos as $dato) {
        $row++;
        $sheet->setCellValue('A' . $row, $dato['label']);

        $valor = floatval($dato['value']);
        if ($estructura['formato_metrica'] === 'moneda') {
            $sheet->setCellValue('B' . $row, 'C$ ' . number_format($valor, 2));
        } else {
            $sheet->setCellValue('B' . $row, number_format($valor, 0));
        }
    }

    // Bordes a la tabla
    $lastRow = $row;
    $sheet->getStyle('A' . $headerRow . ':B' . $lastRow)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ]);

    // Ajustar ancho de columnas
    $sheet->getColumnDimension('A')->setWidth(40);
    $sheet->getColumnDimension('B')->setWidth(20);
    $sheet->getColumnDimension('C')->setWidth(30);

    // Configurar headers
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="reporte_ventas_' . date('Y-m-d_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    // Escribir archivo
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();

} catch (Exception $e) {
    error_log('Error ia_graficos_descargar_excel: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>