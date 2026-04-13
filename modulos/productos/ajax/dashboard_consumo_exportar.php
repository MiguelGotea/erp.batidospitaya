<?php
/* ============================================================
   AJAX: Exportar consumo de insumos → XLSX multi-hoja
   Una pestaña por sucursal. Columnas: Producto + una por semana.
   Usa ZipArchive nativo de PHP (sin dependencias externas).
   modulos/productos/ajax/dashboard_consumo_exportar.php
   ============================================================ */
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('dashboard_consumo_insumos', 'exportar_consumo', $cargoOperario)) {
    http_response_code(403);
    echo 'Sin permiso de exportación.';
    exit();
}

/* ── Leer JSON del body ──────────────────────────────────── */
$rawData = file_get_contents('php://input');
$data    = json_decode($rawData, true);

$consumo          = $data['consumo']            ?? [];
$semanas          = $data['semanas']            ?? [];
$sucursales       = $data['sucursales']         ?? [];
$sucursalesNombres = $data['sucursales_nombres'] ?? [];
$semDesde         = $data['sem_desde']          ?? '';
$semHasta         = $data['sem_hasta']          ?? '';

/* ── Ordenar semanas por número ──────────────────────────── */
usort($semanas, fn($a, $b) => (int)$a['numero_semana'] <=> (int)$b['numero_semana']);
$semanaNros = array_column($semanas, 'numero_semana');

/* ════════════════════════════════════════════════════════════
   HELPERS
   ════════════════════════════════════════════════════════════ */

/** Convierte índice 1-based en letra(s) de columna Excel: 1→A, 27→AA */
function xlsCol(int $n): string {
    $r = '';
    while ($n > 0) {
        $n--;
        $r  = chr(65 + ($n % 26)) . $r;
        $n  = (int)($n / 26);
    }
    return $r;
}

/** Escapa caracteres especiales XML */
function xlsEsc(string $s): string {
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/** Sanea el nombre de la hoja (máx 31 chars, sin caracteres ilegales) */
function xlsSheetName(string $s): string {
    $s = preg_replace('/[\\\\\/\?\*\[\]:]/', '_', $s);
    return mb_substr($s, 0, 31);
}

/* ════════════════════════════════════════════════════════════
   CONSTRUIR HOJAS
   ════════════════════════════════════════════════════════════ */

/**
 * $sheets = [ ['name' => string, 'rows' => [[celda, ...], ...]], ... ]
 *
 * Estructura de cada hoja:
 *   Fila 1: "Producto" | "Semana NNN" | "Semana NNN" | ...
 *   Fila N: nombre_insumo | valor_sem1 | valor_sem2 | ...
 *
 * Hoja TOTAL : usa item['por_semana'][semNum]        (total todas sucursales)
 * Hoja Local : usa item['desglose_semxsuc'][semNum][codSuc]
 */
$sheets = [];

/* ── Hoja TOTAL ─────────────────────────────────────────── */
$header = array_merge(['Producto'], array_map(fn($n) => "Semana $n", $semanaNros));
$rows   = [$header];
foreach ($consumo as $item) {
    $row = [$item['nombre']];
    foreach ($semanaNros as $semNum) {
        $row[] = isset($item['por_semana'][$semNum]) ? (float)$item['por_semana'][$semNum] : 0;
    }
    $rows[] = $row;
}
$sheets[] = ['name' => 'TOTAL', 'rows' => $rows];

/* ── Una hoja por sucursal ──────────────────────────────── */
foreach ($sucursales as $codSuc) {
    $nombreSuc = $sucursalesNombres[$codSuc] ?? $codSuc;
    $rows      = [$header]; // mismo encabezado

    foreach ($consumo as $item) {
        $row = [$item['nombre']];
        foreach ($semanaNros as $semNum) {
            $val = (float)($item['desglose_semxsuc'][$semNum][$codSuc] ?? 0);
            $row[] = $val;
        }
        $rows[] = $row;
    }

    $sheets[] = ['name' => xlsSheetName($nombreSuc), 'rows' => $rows];
}

/* ════════════════════════════════════════════════════════════
   GENERAR XML DE CADA HOJA
   Estilos (índice):
     0 = número normal (#,##0.00)
     1 = header negrita
     2 = texto izquierda (columna Producto)
   ════════════════════════════════════════════════════════════ */
function buildSheetXml(array $sheetRows): string {
    $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
          . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';

    // Freeze top row
    $xml .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';

    // Auto-width hint: set column A a bit wider
    $totalCols = !empty($sheetRows[0]) ? count($sheetRows[0]) : 1;
    $xml .= '<cols>';
    $xml .= '<col min="1" max="1" width="42" customWidth="1"/>'; // columna Producto
    if ($totalCols > 1) {
        $xml .= '<col min="2" max="' . $totalCols . '" width="14" customWidth="1"/>';
    }
    $xml .= '</cols>';

    $xml .= '<sheetData>';

    foreach ($sheetRows as $rIdx => $row) {
        $rowNum  = $rIdx + 1;
        $isHeader = ($rIdx === 0);
        $xml .= "<row r=\"{$rowNum}\"" . ($isHeader ? ' ht="16" customHeight="1"' : '') . '>';

        foreach ($row as $cIdx => $cell) {
            $colNum  = $cIdx + 1;
            $cellRef = xlsCol($colNum) . $rowNum;
            $isFirstCol = ($colNum === 1);

            if ($isHeader) {
                // Header: siempre texto + estilo bold
                $esc  = xlsEsc((string)$cell);
                $xml .= "<c r=\"{$cellRef}\" t=\"inlineStr\" s=\"1\"><is><t>{$esc}</t></is></c>";
            } elseif ($isFirstCol) {
                // Columna Producto: texto alineado izquierda
                $esc  = xlsEsc((string)$cell);
                $xml .= "<c r=\"{$cellRef}\" t=\"inlineStr\" s=\"2\"><is><t>{$esc}</t></is></c>";
            } else {
                // Número
                $xml .= "<c r=\"{$cellRef}\" s=\"0\"><v>" . (float)$cell . "</v></c>";
            }
        }
        $xml .= '</row>';
    }

    $xml .= '</sheetData></worksheet>';
    return $xml;
}

/* ════════════════════════════════════════════════════════════
   ARMAR EL ARCHIVO XLSX (Open XML) VÍA ZipArchive
   ════════════════════════════════════════════════════════════ */
$nSheets  = count($sheets);
$tmpFile  = tempnam(sys_get_temp_dir(), 'xlx_');
$zip      = new ZipArchive();
$zip->open($tmpFile, ZipArchive::OVERWRITE);

/* ── [Content_Types].xml ─────────────────────────────────── */
$ct  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
$ct .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
$ct .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
$ct .= '<Default Extension="xml"  ContentType="application/xml"/>';
$ct .= '<Override PartName="/xl/workbook.xml"'
     . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
$ct .= '<Override PartName="/xl/styles.xml"'
     . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
for ($i = 1; $i <= $nSheets; $i++) {
    $ct .= "<Override PartName=\"/xl/worksheets/sheet{$i}.xml\""
         . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
}
$ct .= '</Types>';
$zip->addFromString('[Content_Types].xml', $ct);

/* ── _rels/.rels ─────────────────────────────────────────── */
$rootRels  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
$rootRels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
$rootRels .= '<Relationship Id="rId1"'
           . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"'
           . ' Target="xl/workbook.xml"/>';
$rootRels .= '</Relationships>';
$zip->addFromString('_rels/.rels', $rootRels);

/* ── xl/styles.xml ───────────────────────────────────────── */
// numFmtId 4 = "#,##0.00" (built-in Excel)
$styles  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
$styles .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
$styles .= '<fonts count="2">';
$styles .=   '<font><sz val="10"/><name val="Calibri"/></font>';               // 0: normal
$styles .=   '<font><b/><sz val="10"/><name val="Calibri"/></font>';           // 1: bold
$styles .= '</fonts>';
$styles .= '<fills count="2">';
$styles .=   '<fill><patternFill patternType="none"/></fill>';
$styles .=   '<fill><patternFill patternType="gray125"/></fill>';
$styles .= '</fills>';
$styles .= '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>';
$styles .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
$styles .= '<cellXfs count="3">';
// 0: número  #,##0.00
$styles .=   '<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0">'
           .   '<alignment horizontal="right"/>'
           . '</xf>';
// 1: header bold centrado
$styles .=   '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0">'
           .   '<alignment horizontal="left" vertical="center"/>'
           . '</xf>';
// 2: texto normal izquierda
$styles .=   '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0">'
           .   '<alignment horizontal="left"/>'
           . '</xf>';
$styles .= '</cellXfs>';
$styles .= '</styleSheet>';
$zip->addFromString('xl/styles.xml', $styles);

/* ── xl/workbook.xml ─────────────────────────────────────── */
$wb  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
$wb .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
     . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
$wb .= '<sheets>';
for ($i = 0; $i < $nSheets; $i++) {
    $rId      = $i + 1;
    $sheetId  = $i + 1;
    $shName   = xlsEsc($sheets[$i]['name']);
    $wb .= "<sheet name=\"{$shName}\" sheetId=\"{$sheetId}\" r:id=\"rId{$rId}\"/>";
}
$wb .= '</sheets></workbook>';
$zip->addFromString('xl/workbook.xml', $wb);

/* ── xl/_rels/workbook.xml.rels ──────────────────────────── */
$wbRels  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
$wbRels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
for ($i = 1; $i <= $nSheets; $i++) {
    $wbRels .= "<Relationship Id=\"rId{$i}\""
             . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
             . " Target=\"worksheets/sheet{$i}.xml\"/>";
}
// Styles
$stylesRid = $nSheets + 1;
$wbRels .= "<Relationship Id=\"rId{$stylesRid}\""
          . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"'
          . ' Target="styles.xml"/>';
$wbRels .= '</Relationships>';
$zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

/* ── xl/worksheets/sheetN.xml ────────────────────────────── */
for ($i = 0; $i < $nSheets; $i++) {
    $sheetXml = buildSheetXml($sheets[$i]['rows']);
    $zip->addFromString("xl/worksheets/sheet" . ($i + 1) . ".xml", $sheetXml);
}

$zip->close();

/* ── Enviar al cliente ───────────────────────────────────── */
$fecha    = date('Y-m-d');
$filename = "consumo_insumos_sem{$semDesde}_a_{$semHasta}_{$fecha}.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($tmpFile);
unlink($tmpFile);
