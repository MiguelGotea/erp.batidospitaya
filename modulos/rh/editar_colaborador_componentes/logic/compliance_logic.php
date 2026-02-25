<?php
/**
 * Lógica pura de cálculo de cumplimiento y llenado.
 * Este archivo no debe contener procesamiento de POST ni efectos secundarios de redirección.
 */

/**
 * Calcula el porcentaje de documentos obligatorios completados para un contrato específico
 */
function calcularPorcentajeDocumentosObligatoriosContrato($codOperario, $codContrato)
{
    global $conn;

    // Pestañas que tienen documentos obligatorios
    $pestañasConObligatorios = ['datos-personales', 'inss', 'contrato'];

    $totalObligatorios = 0;
    $completados = 0;

    foreach ($pestañasConObligatorios as $pestaña) {
        $tiposDocumentos = obtenerTiposDocumentosPorPestaña($pestaña);
        $obligatorios = $tiposDocumentos['obligatorios'];

        if (empty($obligatorios))
            continue;

        $totalObligatorios += count($obligatorios);

        // Obtener documentos obligatorios subidos para este contrato específico
        $placeholders = str_repeat('?,', count($obligatorios) - 1) . '?';
        $tipos = array_keys($obligatorios);

        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT tipo_documento) as completados
            FROM ArchivosAdjuntos
            WHERE cod_operario = ?
            AND pestaña = ?
            AND tipo_documento IN ($placeholders)
            AND obligatorio = 1
            AND (cod_contrato_asociado = ? OR ? IS NULL)
        ");

        $params = array_merge([$codOperario, $pestaña], $tipos, [$codContrato, $codContrato]);
        $stmt->execute($params);
        $result = $stmt->fetch();

        $completados += $result['completados'] ?? 0;
    }

    if ($totalObligatorios == 0) {
        return ['porcentaje' => 100, 'completados' => 0, 'total' => 0];
    }

    $porcentaje = round(($completados / $totalObligatorios) * 100);

    return [
        'porcentaje' => $porcentaje,
        'completados' => $completados,
        'total' => $totalObligatorios
    ];
}

/**
 * Obtiene el nombre amigable de la pestaña
 */
function obtenerNombrePestaña($pestaña)
{
    $pestaña = $pestaña ?? '';
    $nombres = [
        'datos-personales' => 'Datos Personales',
        'inss' => 'INSS',
        'contrato' => 'Contrato',
        'contactos-emergencia' => 'Contactos de Emergencia',
        'salario' => 'Salario',
        'movimientos' => 'Movimientos',
        'categoria' => 'Categoría',
        'adendums' => 'Adendums',
        'expediente-digital' => 'Expediente Digital',
        'bitacora' => 'Bitácora'
    ];

    return $nombres[$pestaña] ?? ucfirst(str_replace('-', ' ', $pestaña));
}

/**
 * Verifica el estado global de documentos obligatorios
 */
function verificarEstadoGlobalDocumentos($codOperario)
{
    $pestañasRevisar = ['datos-personales', 'inss', 'contrato'];
    $totalObligatorios = 0;
    $completos = 0;

    foreach ($pestañasRevisar as $pestaña) {
        $estado = verificarEstadoDocumentosObligatorios($codOperario, $pestaña);

        if ($estado !== 'no_aplica') {
            $totalObligatorios++;

            if ($estado === 'completo') {
                $completos++;
            }
        }
    }

    if ($totalObligatorios == 0)
        return 'no_aplica';
    if ($completos == $totalObligatorios)
        return 'completo';
    if ($completos == 0)
        return 'pendiente';
    return 'parcial';
}

/**
 * Verifica si existen archivos adjuntos para adendums
 */
function verificarArchivosAdendum($codOperario)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM ArchivosAdjuntos
        WHERE cod_operario = ?
        AND pestaña = 'adendums'
        AND obligatorio = 1
    ");
    $stmt->execute([$codOperario]);
    $result = $stmt->fetch();

    return $result['total'] > 0;
}

/**
 * Define los requerimientos de cumplimiento (campos y documentos) por pestaña
 */
function obtenerRequerimientosPestaña($pestaña)
{
    $requerimientos = [
        'campos' => [],
        'documentos' => [],
        'tablas' => []
    ];

    // Obtener documentos obligatorios configurados en BD para esta pestaña
    $docsPestana = obtenerTiposDocumentosPorPestaña($pestaña);
    if (!empty($docsPestana['obligatorios'])) {
        foreach ($docsPestana['obligatorios'] as $clave => $nombre) {
            $requerimientos['documentos'][] = ['clave' => $clave, 'nombre' => $nombre, 'id' => $docsPestana['ids'][$clave]];
        }
    }

    switch ($pestaña) {
        case 'datos-personales':
            $requerimientos['campos'] = [
                ['columna' => 'Nombre', 'nombre' => 'Primer Nombre'],
                ['columna' => 'Apellido', 'nombre' => 'Primer Apellido'],
                ['columna' => 'Cedula', 'nombre' => 'Cédula']
            ];
            break;

        case 'datos-contacto':
            $requerimientos['campos'] = [
                ['columna' => 'direccion', 'nombre' => 'Dirección'],
                ['columna' => 'Celular', 'nombre' => 'Teléfono Móvil'],
                ['columna' => 'Ciudad', 'nombre' => 'Ciudad'],
                ['columna' => 'email_personal', 'nombre' => 'Email Personal']
            ];
            break;

        case 'inss':
            $requerimientos['campos'] = [
                ['columna' => 'codigo_inss', 'nombre' => 'Número de Seguro INSS'],
                ['columna' => 'numero_planilla', 'nombre' => 'Número de Planilla', 'tabla' => 'Contratos'],
                ['columna' => 'hospital_inss', 'nombre' => 'Hospital Asociado', 'tabla' => 'Contratos']
            ];
            break;

        case 'contrato':
            $requerimientos['campos'] = [
                ['columna' => 'cod_tipo_contrato', 'nombre' => 'Tipo de Contrato', 'tabla' => 'Contratos'],
                ['columna' => 'inicio_contrato', 'nombre' => 'Fecha de Inicio', 'tabla' => 'Contratos'],
                ['columna' => 'codigo_manual_contrato', 'nombre' => 'Código de Contrato', 'tabla' => 'Contratos'],
                ['columna' => 'cod_sucursal_contrato', 'nombre' => 'Sucursal', 'tabla' => 'Contratos']
            ];
            break;

        case 'adendums':
            // Verificamos si hay documentos subidos para esta pestaña
            break;

        case 'contactos-emergencia':
            $requerimientos['tablas'][] = ['tabla' => 'ContactosEmergencia', 'nombre' => 'Al menos un contacto'];
            break;

        case 'salario':
            $requerimientos['tablas'][] = ['tabla' => 'SalarioOperario', 'nombre' => 'Historial de salarios'];
            break;

        case 'movimientos':
            $requerimientos['tablas'][] = ['tabla' => 'AsignacionNivelesCargos', 'nombre' => 'Historial de cargos'];
            break;

        case 'categoria':
            $requerimientos['tablas'][] = ['tabla' => 'OperariosCategorias', 'nombre' => 'Historial de categorías'];
            break;
    }

    return $requerimientos;
}

/**
 * Calcula el porcentaje de cumplimiento para una pestaña específica
 */
function calcularPorcentajeCumplimiento($codOperario, $pestaña)
{
    global $conn;

    if ($pestaña == 'expediente' || $pestaña == 'global') {
        return calcularCumplimientoGlobal($codOperario);
    }

    // Caso Especial: Expediente Digital (Cálculo Global de Documentos)
    if ($pestaña == 'expediente-digital') {
        // Obtenemos todos los tipos de documentos obligatorios del sistema
        $stmt = $conn->prepare("SELECT id FROM contratos_tiposDocumentos WHERE es_obligatorio = 1 AND activo = 1");
        $stmt->execute();
        $docsObligatorios = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $completados = 0;
        $totalDocs = count($docsObligatorios);

        if ($totalDocs > 0) {
            $placeholders = str_repeat('?,', $totalDocs - 1) . '?';
            $stmt = $conn->prepare("
                SELECT id_tipo_documento, COUNT(*) as cantidad
                FROM ArchivosAdjuntos
                WHERE cod_operario = ? AND id_tipo_documento IN ($placeholders)
                GROUP BY id_tipo_documento
            ");
            $stmt->execute(array_merge([$codOperario], $docsObligatorios));
            $subidos = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            foreach ($docsObligatorios as $idDoc) {
                if (isset($subidos[$idDoc]) && $subidos[$idDoc] > 0) {
                    $completados++;
                }
            }
        }

        $porcentaje = $totalDocs > 0 ? round(($completados / $totalDocs) * 100) : 100;
        return [
            'porcentaje' => $porcentaje,
            'completados' => $completados,
            'total' => $totalDocs,
            'detalles' => []
        ];
    }

    $requerimientos = obtenerRequerimientosPestaña($pestaña);
    $totalRequerimientos = count($requerimientos['campos']) + count($requerimientos['documentos']) + count($requerimientos['tablas']);

    if ($totalRequerimientos == 0) {
        return ['porcentaje' => 100, 'completados' => 0, 'total' => 0, 'detalles' => []];
    }

    $completados = 0;
    $detalles = [];

    // 1. Verificar campos en base de datos
    if (!empty($requerimientos['campos'])) {
        // Separar campos por tabla
        $camposPorTabla = [];
        foreach ($requerimientos['campos'] as $campo) {
            $tabla = $campo['tabla'] ?? 'Operarios';
            $camposPorTabla[$tabla][] = $campo;
        }

        foreach ($camposPorTabla as $tabla => $campos) {
            $columnas = array_column($campos, 'columna');
            $idCol = ($tabla == 'Operarios') ? 'CodOperario' : 'cod_operario';

            // Query para obtener los valores
            $sql = "SELECT " . implode(', ', $columnas) . " FROM $tabla WHERE $idCol = ?";
            if ($tabla == 'Contratos') {
                $sql .= " AND (fecha_salida IS NULL OR fecha_salida = '0000-00-00') ORDER BY inicio_contrato DESC LIMIT 1";
            }

            $stmt = $conn->prepare($sql);
            $stmt->execute([$codOperario]);
            $registro = $stmt->fetch();

            foreach ($campos as $campo) {
                $valor = $registro[$campo['columna']] ?? null;
                $pasa = !empty($valor);
                if ($pasa)
                    $completados++;
                $detalles[] = ['nombre' => $campo['nombre'], 'completado' => $pasa, 'tipo' => 'campo'];
            }
        }
    }

    // 2. Verificar documentos
    if (!empty($requerimientos['documentos'])) {
        $idsDocs = array_column($requerimientos['documentos'], 'id');
        $placeholders = str_repeat('?,', count($idsDocs) - 1) . '?';

        $stmt = $conn->prepare("
            SELECT id_tipo_documento, COUNT(*) as cantidad
            FROM ArchivosAdjuntos
            WHERE cod_operario = ? AND id_tipo_documento IN ($placeholders)
            GROUP BY id_tipo_documento
        ");
        $stmt->execute(array_merge([$codOperario], $idsDocs));
        $subidos = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($requerimientos['documentos'] as $doc) {
            $pasa = isset($subidos[$doc['id']]) && $subidos[$doc['id']] > 0;
            if ($pasa)
                $completados++;
            $detalles[] = ['nombre' => $doc['nombre'], 'completado' => $pasa, 'tipo' => 'documento'];
        }
    }

    // 3. Verificar tablas (existencia de registros)
    if (!empty($requerimientos['tablas'])) {
        foreach ($requerimientos['tablas'] as $tablaInfo) {
            $tabla = $tablaInfo['tabla'];

            if ($tabla == 'SalarioOperario') {
                // Caso especial: Salarios se vinculan vía contrato
                $sql = "SELECT COUNT(*) FROM SalarioOperario so JOIN Contratos c ON so.cod_contrato = c.CodContrato WHERE c.cod_operario = ?";
            } else {
                $idCol = 'cod_operario';
                if ($tabla == 'AsignacionNivelesCargos')
                    $idCol = 'CodOperario';
                if ($tabla == 'OperariosCategorias')
                    $idCol = 'CodOperario';
                $sql = "SELECT COUNT(*) FROM $tabla WHERE $idCol = ?";
            }

            $stmt = $conn->prepare($sql);
            $stmt->execute([$codOperario]);
            $count = $stmt->fetchColumn();

            $pasa = $count > 0;
            if ($pasa)
                $completados++;
            $detalles[] = ['nombre' => $tablaInfo['nombre'], 'completado' => $pasa, 'tipo' => 'tabla'];
        }
    }


    $porcentaje = $totalRequerimientos > 0 ? round(($completados / $totalRequerimientos) * 100) : 100;

    return [
        'porcentaje' => $porcentaje,
        'completados' => $completados,
        'total' => $totalRequerimientos,
        'detalles' => $detalles
    ];
}

/**
 * Calcula el cumplimiento global del colaborador
 */
function calcularCumplimientoGlobal($codOperario)
{
    $pestanas = ['datos-personales', 'datos-contacto', 'inss', 'contrato', 'contactos-emergencia', 'salario', 'movimientos', 'categoria'];

    $totalG = 0;
    $completadosG = 0;

    foreach ($pestanas as $p) {
        $res = calcularPorcentajeCumplimiento($codOperario, $p);
        $totalG += $res['total'];
        $completadosG += $res['completados'];
    }

    $porcentaje = $totalG > 0 ? round(($completadosG / $totalG) * 100) : 100;

    return [
        'porcentaje' => $porcentaje,
        'completados' => $completadosG,
        'total' => $totalG
    ];
}

/**
 * Calcula el porcentaje de llenado considerando solo las 4 pestañas solicitadas:
 * Datos Personales, Datos del Contacto, Contrato e INSS.
 */
function calcularPorcentajeLlenadoGlobal($codOperario)
{
    $pestanas = ['datos-personales', 'datos-contacto', 'contrato', 'inss'];

    $totalG = 0;
    $completadosG = 0;

    foreach ($pestanas as $p) {
        $res = calcularPorcentajeCumplimiento($codOperario, $p);
        $totalG += $res['total'];
        $completadosG += $res['completados'];
    }

    $porcentaje = $totalG > 0 ? round(($completadosG / $totalG) * 100) : 100;

    return [
        'porcentaje' => $porcentaje,
        'completados' => $completadosG,
        'total' => $totalG
    ];
}
