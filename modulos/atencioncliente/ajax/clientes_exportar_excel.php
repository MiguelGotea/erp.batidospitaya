<?php
// clientes_exportar_excel.php
require_once '../../../core/database/conexion.php';
require_once '../../../core/auth/auth.php';
require_once '../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

try {
    $filtros = isset($_POST['filtros']) ? json_decode($_POST['filtros'], true) : [];
    $orden = isset($_POST['orden']) ? json_decode($_POST['orden'], true) : ['columna' => null, 'direccion' => 'asc'];

    // Construir WHERE (Reutilizado de clientes_get_datos.php)
    $where = [];
    $params = [];

    $filtrosTexto = ['membresia', 'nombre', 'apellido', 'celular', 'correo'];
    foreach ($filtrosTexto as $campo) {
        if (isset($filtros[$campo]) && $filtros[$campo] !== '') {
            $where[] = "$campo LIKE :$campo";
            $params[":$campo"] = '%' . $filtros[$campo] . '%';
        }
    }

    if (isset($filtros['nombre_sucursal']) && is_array($filtros['nombre_sucursal']) && count($filtros['nombre_sucursal']) > 0) {
        $placeholders = [];
        foreach ($filtros['nombre_sucursal'] as $idx => $valor) {
            $key = ":sucursal_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = "nombre_sucursal IN (" . implode(',', $placeholders) . ")";
    }

    if (isset($filtros['fecha_registro']) && is_array($filtros['fecha_registro'])) {
        if (!empty($filtros['fecha_registro']['desde'])) {
            $where[] = "fecha_registro >= :fecha_registro_desde";
            $params[':fecha_registro_desde'] = $filtros['fecha_registro']['desde'];
        }
        if (!empty($filtros['fecha_registro']['hasta'])) {
            $where[] = "fecha_registro <= :fecha_registro_hasta";
            $params[':fecha_registro_hasta'] = $filtros['fecha_registro']['hasta'];
        }
    }

    if (isset($filtros['ultima_compra']) && is_array($filtros['ultima_compra'])) {
        if (!empty($filtros['ultima_compra']['desde'])) {
            $where[] = "ultima_compra >= :ultima_compra_desde";
            $params[':ultima_compra_desde'] = $filtros['ultima_compra']['desde'];
        }
        if (!empty($filtros['ultima_compra']['hasta'])) {
            $where[] = "ultima_compra <= :ultima_compra_hasta";
            $params[':ultima_compra_hasta'] = $filtros['ultima_compra']['hasta'];
        }
    }

    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    $orderClause = '';
    if ($orden['columna']) {
        $columnas_validas = ['membresia', 'nombre', 'apellido', 'celular', 'fecha_nacimiento', 'correo', 'fecha_registro', 'nombre_sucursal', 'ultima_compra'];
        if (in_array($orden['columna'], $columnas_validas)) {
            $direccion = strtoupper($orden['direccion']) === 'DESC' ? 'DESC' : 'ASC';
            $orderClause = "ORDER BY {$orden['columna']} $direccion";
        }
    } else {
        $orderClause = "ORDER BY fecha_registro DESC";
    }

    // Consulta de datos completa (sin LIMIT)
    $sql = "SELECT * FROM (
                SELECT 
                    membresia,
                    nombre,
                    apellido,
                    celular,
                    fecha_nacimiento,
                    correo,
                    fecha_registro,
                    nombre_sucursal,
                    (SELECT MAX(v.Fecha) 
                     FROM VentasGlobalesAccessCSV v 
                     WHERE v.CodCliente = c.membresia AND v.Anulado = 0) as ultima_compra
                FROM clientesclub c
            ) as t
            $whereClause
            $orderClause";

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Crear Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Historial de Clientes');

    // Encabezados
    $encabezados = [
        'A1' => 'Membresía',
        'B1' => 'Nombre',
        'C1' => 'Apellido',
        'D1' => 'Celular',
        'E1' => 'Fecha Nacimiento',
        'F1' => 'Correo',
        'G1' => 'Fecha Inscripción',
        'H1' => 'Última Compra',
        'I1' => 'Sucursal'
    ];

    foreach ($encabezados as $celda => $texto) {
        $sheet->setCellValue($celda, $texto);
    }

    // Estilo encabezado
    $sheet->getStyle('A1:I1')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0E544C']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);

    // Llenar datos
    $row = 2;
    foreach ($datos as $dato) {
        $sheet->setCellValue('A' . $row, $dato['membresia']);
        $sheet->setCellValue('B' . $row, $dato['nombre']);
        $sheet->setCellValue('C' . $row, $dato['apellido']);
        $sheet->setCellValue('D' . $row, $dato['celular']);
        $sheet->setCellValue('E' . $row, $dato['fecha_nacimiento']);
        $sheet->setCellValue('F' . $row, $dato['correo']);
        $sheet->setCellValue('G' . $row, $dato['fecha_registro']);
        $sheet->setCellValue('H' . $row, $dato['ultima_compra'] ?: '-');
        $sheet->setCellValue('I' . $row, $dato['nombre_sucursal']);
        $row++;
    }

    // Ajustar columnas
    foreach (range('A', 'I') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Bordes
    $sheet->getStyle('A1:I' . ($row - 1))->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ]);

    // Salida
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="HistorialClientes_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();

} catch (Exception $e) {
    echo "Error al generar el Excel: " . $e->getMessage();
}
