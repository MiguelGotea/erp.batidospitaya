<?php
// ajax/bcd_get_detalle_cierre.php
// Calcula todos los valores del balance para un cierre específico
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $fecha          = isset($_POST['fecha'])          ? $_POST['fecha']              : null;
    $sucursal       = isset($_POST['sucursal'])       ? $_POST['sucursal']           : null;
    $cod_cierre     = isset($_POST['cod_cierre'])     ? (int)$_POST['cod_cierre']    : null;
    $hora_final     = isset($_POST['hora_final'])     ? $_POST['hora_final']         : null;
    $hora_inicial   = isset($_POST['hora_inicial'])   ? $_POST['hora_inicial']       : null;
    $cod_operario   = isset($_POST['cod_operario'])   ? (int)$_POST['cod_operario']  : null;
    $mf_cor         = isset($_POST['mf_cor'])         ? (float)$_POST['mf_cor']      : 0;
    $mf_dol         = isset($_POST['mf_dol'])         ? (float)$_POST['mf_dol']      : 0;
    $total_pos      = isset($_POST['total_pos'])      ? (float)$_POST['total_pos']   : 0;
    $total_transfer = isset($_POST['total_transfer']) ? (float)$_POST['total_transfer'] : 0;
    $total_py       = isset($_POST['total_py'])       ? (float)$_POST['total_py']    : 0;
    $observaciones  = isset($_POST['observaciones'])  ? $_POST['observaciones']      : '';

    if (!$fecha || !$sucursal || !$cod_cierre) {
        echo json_encode(['success' => false, 'message' => 'Faltan parámetros requeridos.']);
        exit;
    }

    // ── 1. Obtener id interno de sucursal ────────────────────────────────────
    $stmtSuc = $conn->prepare("SELECT id FROM sucursales WHERE codigo = :codigo LIMIT 1");
    $stmtSuc->bindValue(':codigo', $sucursal);
    $stmtSuc->execute();
    $rowSuc = $stmtSuc->fetch(PDO::FETCH_ASSOC);
    if (!$rowSuc) {
        echo json_encode(['success' => false, 'message' => 'Sucursal no encontrada.']);
        exit;
    }
    $sucursal_id = (int)$rowSuc['id'];

    // ── 2. Nombre completo del cajero ─────────────────────────────────────────
    $cajero = 'Desconocido';
    if ($cod_operario) {
        $stmtOp = $conn->prepare(
            "SELECT CONCAT(COALESCE(Nombre,''), ' ',
                           COALESCE(Nombre2,''), ' ',
                           COALESCE(Apellido,''), ' ',
                           COALESCE(Apellido2,'')) AS nombre_completo
             FROM Operarios
             WHERE CodOperario = :cod
             LIMIT 1"
        );
        $stmtOp->bindValue(':cod', $cod_operario, PDO::PARAM_INT);
        $stmtOp->execute();
        $rowOp = $stmtOp->fetch(PDO::FETCH_ASSOC);
        if ($rowOp) {
            $cajero = trim(preg_replace('/\s+/', ' ', $rowOp['nombre_completo']));
        }
    }

    // ── 3. Obtener hora_inicial del cierre ───────────────────────────────────
    $stmtCierre = $conn->prepare(
        "SELECT HoraInicial FROM msaccess_masivo_CierreDiario
         WHERE Sucursal = :suc AND CodigoCierre = :cod LIMIT 1"
    );
    $stmtCierre->bindValue(':suc', $sucursal_id, PDO::PARAM_INT);
    $stmtCierre->bindValue(':cod', $cod_cierre,  PDO::PARAM_INT);
    $stmtCierre->execute();
    $rowCierre = $stmtCierre->fetch(PDO::FETCH_ASSOC);
    $hora_inicial_real = $rowCierre ? $rowCierre['HoraInicial'] : null;

    // ── 4. Estado Inicial (caja inicial + tipo de cambio) ─────────────────────
    $stmtEI = $conn->prepare(
        "SELECT Dinero, TipoCambio_C
         FROM msaccess_masivo_EstadoInicial
         WHERE Fecha = :fecha AND Sucursal = :suc
         LIMIT 1"
    );
    $stmtEI->bindValue(':fecha', $fecha);
    $stmtEI->bindValue(':suc',   $sucursal_id, PDO::PARAM_INT);
    $stmtEI->execute();
    $rowEI       = $stmtEI->fetch(PDO::FETCH_ASSOC);
    $caja_inicial = $rowEI ? (float)$rowEI['Dinero']       : 0;
    $tipo_cambio  = $rowEI ? (float)$rowEI['TipoCambio_C'] : 0;
    if ($tipo_cambio <= 0) $tipo_cambio = 1; // protección contra división por cero

    // ── 5. Ventas por modalidad (≤ HoraFinal) ────────────────────────────────
    // Usamos SUM agrupado por modalidad en una sola consulta
    $sqlVentas = "SELECT
                    v.Modalidad,
                    SUM(CASE WHEN v.Anulado = 1 THEN 0 ELSE COALESCE(v.Precio, 0) END) AS total
                  FROM VentasGlobalesAccessCSV v
                  WHERE v.Fecha   = :fecha
                    AND v.local   = :sucursal";
    if ($hora_final) {
        $sqlVentas .= " AND v.Hora <= :hora_final";
    }
    $sqlVentas .= " GROUP BY v.Modalidad";

    $stmtV = $conn->prepare($sqlVentas);
    $stmtV->bindValue(':fecha',    $fecha);
    $stmtV->bindValue(':sucursal', $sucursal);
    if ($hora_final) {
        $stmtV->bindValue(':hora_final', $hora_final);
    }
    $stmtV->execute();
    $rowsVentas = $stmtV->fetchAll(PDO::FETCH_ASSOC);

    $ventas = [
        'POS'          => 0,
        'TRANSFERENCIA' => 0,
        'PEDIDOSYA'    => 0,
        'EFECTIVO'     => 0,
    ];
    foreach ($rowsVentas as $rv) {
        $mod = strtoupper(trim($rv['Modalidad']));
        if (isset($ventas[$mod])) {
            $ventas[$mod] = (float)$rv['total'];
        }
    }

    // ── 6. Aligeramientos (depósitos del día, todos los cierres) ─────────────
    $stmtDep = $conn->prepare(
        "SELECT d.Monto, d.Denominacion
         FROM msaccess_masivo_Depositos d
         WHERE d.Fecha    = :fecha
           AND d.Sucursal = :suc"
    );
    $stmtDep->bindValue(':fecha', $fecha);
    $stmtDep->bindValue(':suc',   $sucursal_id, PDO::PARAM_INT);
    $stmtDep->execute();
    $rowsDep = $stmtDep->fetchAll(PDO::FETCH_ASSOC);

    $aligeramientos = 0;
    foreach ($rowsDep as $dep) {
        $monto = (float)$dep['Monto'];
        $denom = strtolower(trim($dep['Denominacion']));
        if ($denom === 'dolares' || $denom === 'dólares') {
            $monto = $monto * $tipo_cambio;
        }
        $aligeramientos += $monto;
    }

    // ── 7. Compras de Caja (del día, Tipo = CAJA) ─────────────────────────────
    $stmtComp = $conn->prepare(
        "SELECT SUM(COALESCE(CostoTotal, 0)) AS total
         FROM msaccess_masivo_Compras
         WHERE Fecha    = :fecha
           AND Sucursal = :suc
           AND Tipo     = 'CAJA'"
    );
    $stmtComp->bindValue(':fecha', $fecha);
    $stmtComp->bindValue(':suc',   $sucursal_id, PDO::PARAM_INT);
    $stmtComp->execute();
    $rowComp     = $stmtComp->fetch(PDO::FETCH_ASSOC);
    $compras_caja = (float)($rowComp['total'] ?? 0);

    // ── 8. Conteo de Caja ─────────────────────────────────────────────────────
    // MFCor + MFDol * TipoCambio_C
    $conteo_caja = $mf_cor + ($mf_dol * $tipo_cambio);

    // ── 9. Boleta Físico Efectivo ─────────────────────────────────────────────
    // Conteo de Caja - Caja Inicial + Aligeramientos + Compras
    $efectivo_fisico = $conteo_caja - $caja_inicial + $aligeramientos + $compras_caja;

    // ── Armar respuesta ───────────────────────────────────────────────────────
    $datos = [
        // Encabezado
        'cod_cierre'    => $cod_cierre,
        'cajero'        => $cajero,
        'fecha'         => $fecha,
        'hora_inicial'  => $hora_inicial_real,
        'hora_final'    => $hora_final,

        // Boleta Físico
        'total_pos_fisico'      => $total_pos,
        'total_transfer_fisico' => $total_transfer,
        'total_py_fisico'       => $total_py,
        'efectivo_fisico'       => $efectivo_fisico,

        // Sistema Pitaya
        'pos_sistema'       => $ventas['POS'],
        'transfer_sistema'  => $ventas['TRANSFERENCIA'],
        'py_sistema'        => $ventas['PEDIDOSYA'],
        'efectivo_sistema'  => $ventas['EFECTIVO'],

        // Balance de Efectivo
        'caja_inicial'    => $caja_inicial,
        'aligeramientos'  => $aligeramientos,
        'compras_caja'    => $compras_caja,
        'conteo_caja'     => $conteo_caja,
        'tipo_cambio'     => $tipo_cambio,

        // Observaciones
        'observaciones' => $observaciones,
    ];

    echo json_encode(['success' => true, 'datos' => $datos]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
