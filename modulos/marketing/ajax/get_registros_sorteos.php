<?php
header('Content-Type: application/json');
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/auth/auth.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar permiso de vista
if (!tienePermiso('gestion_sorteos', 'vista', $cargoOperario)) {
    error_log("Sin permiso de vista para cargo: $cargoOperario");
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
}



$response = ['success' => false, 'data' => [], 'total' => 0];

try {
    // Parámetros de paginación y ordenamiento
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 50;
    $offset = ($page - 1) * $perPage;

    $ordenColumna = isset($_GET['orden_columna']) ? $_GET['orden_columna'] : 'fecha_registro';
    $ordenDireccion = isset($_GET['orden_direccion']) ? strtoupper($_GET['orden_direccion']) : 'DESC';

    // Lista de columnas válidas para filtrar y ordenar
    $columnasValidas = [
        'nombre_completo',
        'numero_contacto',
        'numero_cedula',
        'numero_factura',
        'correo_electronico',
        'monto_factura',
        'puntos_factura',
        'puntos_globales',  // columna virtual (subquery alias)
        'tipo_qr',
        'validado_ia',
        'valido',
        'fecha_registro'
    ];

    // Columnas virtuales (subconsultas): sólo ORDER BY y HAVING, nunca WHERE
    $columnasVirtuales = ['puntos_globales'];

    // Validar columna de ordenamiento
    // MySQL permite ORDER BY con alias de subquery en el SELECT
    if (!in_array($ordenColumna, $columnasValidas)) {
        $ordenColumna = 'fecha_registro';
    }
    if (!in_array($ordenDireccion, ['ASC', 'DESC'])) {
        $ordenDireccion = 'DESC';
    }

    // Construir WHERE clause
    $where = [];
    $having = []; // Para columnas virtuales (subquery aliases)
    $params = [];
    $havingParams = [];

    // Filtro especial para ID único (para modal)
    if (isset($_GET['id']) && $_GET['id'] !== '') {
        $where[] = "id = ?";
        $params[] = (int) $_GET['id'];
    }

    // Procesar filtros de cada columna
    foreach ($_GET as $key => $value) {
        // Saltar parámetros de sistema
        if (in_array($key, ['page', 'per_page', 'orden_columna', 'orden_direccion'])) {
            continue;
        }

        // Columnas virtuales: manejar con HAVING
        if (in_array($key, $columnasVirtuales)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                if (isset($decoded['min']) && $decoded['min'] !== '') {
                    $having[] = "$key >= ?";
                    $havingParams[] = $decoded['min'];
                }
                if (isset($decoded['max']) && $decoded['max'] !== '') {
                    $having[] = "$key <= ?";
                    $havingParams[] = $decoded['max'];
                }
            }
            continue;
        }

        // Solo procesar columnas válidas reales
        if (!in_array($key, $columnasValidas)) {
            continue;
        }

        // Filtro de texto simple (but check for '0' explicitly)
        if (is_string($value) && $value !== '' && $value[0] !== '{' && $value[0] !== '[') {
            // For valido column, use exact match instead of LIKE
            if ($key === 'valido') {
                $where[] = "$key = ?";
                $params[] = (int) $value;
            } else {
                $where[] = "$key LIKE ?";
                $params[] = "%$value%";
            }
            continue;
        }

        // Intentar decodificar JSON para filtros complejos
        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // Filtro de rango numérico o fecha
            if (isset($decoded['min']) && $decoded['min'] !== '') {
                $where[] = "$key >= ?";
                $params[] = $decoded['min'];
            }
            if (isset($decoded['max']) && $decoded['max'] !== '') {
                $where[] = "$key <= ?";
                $params[] = $decoded['max'];
            }
            if (isset($decoded['desde']) && $decoded['desde'] !== '') {
                $where[] = "DATE($key) >= ?";
                $params[] = $decoded['desde'];
            }
            if (isset($decoded['hasta']) && $decoded['hasta'] !== '') {
                $where[] = "DATE($key) <= ?";
                $params[] = $decoded['hasta'];
            }
        } elseif (is_array($value) || (is_string($value) && strpos($value, ',') !== false)) {
            // Filtro de lista (array o string separado por comas)
            $valores = is_array($value) ? $value : explode(',', $value);
            if (!empty($valores)) {
                $placeholders = implode(',', array_fill(0, count($valores), '?'));
                $where[] = "$key IN ($placeholders)";
                $params = array_merge($params, $valores);
            }
        }
    }

    // Filtros legacy (mantener compatibilidad)
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $fechaInicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
    $fechaFin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';
    $tipoQR = isset($_GET['tipo_qr']) ? $_GET['tipo_qr'] : '';
    $validadoIA = isset($_GET['validado_ia']) ? $_GET['validado_ia'] : '';

    if (!empty($search)) {
        $where[] = "(nombre_completo LIKE ? OR numero_factura LIKE ? OR numero_contacto LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    if (!empty($fechaInicio)) {
        $where[] = "DATE(fecha_registro) >= ?";
        $params[] = $fechaInicio;
    }

    if (!empty($fechaFin)) {
        $where[] = "DATE(fecha_registro) <= ?";
        $params[] = $fechaFin;
    }

    if ($tipoQR !== '') {
        $where[] = "tipo_qr = ?";
        $params[] = $tipoQR;
    }

    if ($validadoIA !== '') {
        $where[] = "validado_ia = ?";
        $params[] = (int) $validadoIA;
    }

    // Filtro Verificación IA (3 opciones: verified, review, all)
    $iaFilter = isset($_GET['ia_filter']) ? $_GET['ia_filter'] : '';
    if ($iaFilter === 'verified') {
        $where[] = "(codigo_sorteo_ia IS NOT NULL AND codigo_sorteo_ia != '' AND numero_factura = codigo_sorteo_ia AND puntos_factura = puntos_ia)";
    } elseif ($iaFilter === 'review') {
        $where[] = "(codigo_sorteo_ia IS NULL OR codigo_sorteo_ia = '' OR numero_factura != codigo_sorteo_ia OR puntos_factura != puntos_ia)";
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // ── Cargar colaboradores activos una sola vez ──────────────────────────────
    // Colaborador activo = contrato con Finalizado=0
    $sqlColabs = "SELECT 
                      o.CodOperario,
                      TRIM(CONCAT_WS(' ',
                          COALESCE(o.Nombre, ''),
                          COALESCE(o.Nombre2, ''),
                          COALESCE(o.Apellido, ''),
                          COALESCE(o.Apellido2, '')
                      )) AS nombre_completo_colab
                  FROM Operarios o
                  INNER JOIN Contratos c ON c.cod_operario = o.CodOperario
                  WHERE c.Finalizado = 0
                  GROUP BY o.CodOperario";
    $stmtColabs = ejecutarConsulta($sqlColabs, []);
    $colaboradoresActivos = $stmtColabs->fetchAll(PDO::FETCH_ASSOC);

    /**
     * Compara las palabras significativas de dos nombres y retorna el nombre del
     * colaborador activo que coincide en ≥2 palabras, o null si no hay match.
     *
     * Se excluyen palabras de enlace típicas (artículos, preposiciones, conjunciones)
     * para evitar falsos positivos como "de", "los", "del", "las", etc.
     */
    function encontrarColaboradorSospechoso(string $nombreConcursante, array $colaboradores): ?string
    {
        // Palabras de enlace a ignorar en la comparación de nombres
        $stopwords = [
            'de',
            'del',
            'la',
            'las',
            'lo',
            'los',
            'el',
            'los',
            'los',
            'una',
            'uno',
            'unos',
            'unas',
            'por',
            'para',
            'con',
            'sin',
            'san',
            'santa',
            'y',
            'e',
            'o',
            'u',
        ];

        // Normalizar: minúsculas, quitar tildes básicas
        $normalizar = function (string $str): string {
            $str = mb_strtolower($str, 'UTF-8');
            $str = str_replace(
                ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'],
                ['a', 'e', 'i', 'o', 'u', 'u', 'n'],
                $str
            );
            return $str;
        };

        // Extraer palabras significativas: ≥3 chars Y no es stopword
        $extraerPalabras = function (string $nombre) use ($normalizar, $stopwords): array {
            return array_values(array_filter(
                explode(' ', $normalizar($nombre)),
                fn($p) => mb_strlen($p) >= 3 && !in_array($p, $stopwords, true)
            ));
        };

        $palabrasConcursante = $extraerPalabras($nombreConcursante);

        if (count($palabrasConcursante) < 2) {
            return null; // Nombre sin suficientes palabras significativas
        }

        foreach ($colaboradores as $colab) {
            $palabrasColab = $extraerPalabras($colab['nombre_completo_colab']);

            $coincidencias = count(array_intersect($palabrasConcursante, $palabrasColab));
            if ($coincidencias >= 2) {
                return $colab['nombre_completo_colab'];
            }
        }
        return null;
    }

    // Parámetro de filtro por verificación colaborador
    $collabFilter = isset($_GET['collab_filter']) ? $_GET['collab_filter'] : '';

    // Cláusula HAVING para columnas virtuales (puntos_globales, etc.)
    $havingClause = !empty($having) ? 'HAVING ' . implode(' AND ', $having) : '';

    $sqlSelect = "SELECT 
                id,
                nombre_completo,
                numero_contacto,
                numero_cedula,
                numero_factura,
                correo_electronico,
                monto_factura,
                puntos_factura,
                tipo_qr,
                foto_factura,
                validado_ia,
                codigo_sorteo_ia,
                puntos_ia,
                valido,
                fecha_registro,
                (
                    SELECT SUM(p2.puntos_factura)
                    FROM pitaya_love_registros p2
                    WHERE LOWER(TRIM(p2.nombre_completo)) = LOWER(TRIM(pitaya_love_registros.nombre_completo))
                ) AS puntos_globales
            FROM pitaya_love_registros
            $whereClause
            $havingClause
            ORDER BY $ordenColumna $ordenDireccion";

    // Los params del HAVING se añaden después de los del WHERE (mismo orden que la query)
    $paramsConHaving = array_merge($params, $havingParams);

    if ($collabFilter !== '') {
        // ── Filtro colaborador activo: traer TODOS para matching PHP correcto ──
        $stmtAll = ejecutarConsulta($sqlSelect, $paramsConHaving);
        $todosRegistros = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

        // Hacer matching en todos los registros
        foreach ($todosRegistros as &$reg) {
            if (!empty($reg['foto_factura'])) {
                $reg['foto_url'] = 'https://pitayalove.batidospitaya.com/uploads/' . $reg['foto_factura'];
            } else {
                $reg['foto_url'] = null;
            }
            $match = encontrarColaboradorSospechoso($reg['nombre_completo'], $colaboradoresActivos);
            $reg['colaborador_sospechoso'] = $match !== null ? 1 : 0;
            $reg['nombre_colaborador'] = $match ?? '';
        }
        unset($reg);

        // Aplicar filtro colaborador
        if ($collabFilter === 'review') {
            $todosRegistros = array_values(array_filter($todosRegistros, fn($r) => $r['colaborador_sospechoso'] == 1));
        } elseif ($collabFilter === 'verified') {
            $todosRegistros = array_values(array_filter($todosRegistros, fn($r) => $r['colaborador_sospechoso'] == 0));
        }

        // Total real y paginación en PHP
        $total = count($todosRegistros);
        $registros = array_slice($todosRegistros, $offset, $perPage);

    } else {
        // ── Sin filtro colaborador: flujo normal (COUNT + SELECT paginado) ──
        // Para COUNT con HAVING usamos subquery envolvente
        if (!empty($having)) {
            $countSql = "SELECT COUNT(*) as total FROM ($sqlSelect) AS sub";
            $countStmt = ejecutarConsulta($countSql, $paramsConHaving);
        } else {
            $countSql = "SELECT COUNT(*) as total FROM pitaya_love_registros $whereClause";
            $countStmt = ejecutarConsulta($countSql, $params);
        }
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        $pagParams = array_merge($paramsConHaving, [$perPage, $offset]);
        $stmt = ejecutarConsulta($sqlSelect . " LIMIT ? OFFSET ?", $pagParams);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Matching colaborador para mostrar badge (sin filtrar)
        foreach ($registros as &$registro) {
            if (!empty($registro['foto_factura'])) {
                $registro['foto_url'] = 'https://pitayalove.batidospitaya.com/uploads/' . $registro['foto_factura'];
            } else {
                $registro['foto_url'] = null;
            }
            $match = encontrarColaboradorSospechoso($registro['nombre_completo'], $colaboradoresActivos);
            $registro['colaborador_sospechoso'] = $match !== null ? 1 : 0;
            $registro['nombre_colaborador'] = $match ?? '';
        }
        unset($registro);
    }

    $response['success'] = true;
    $response['data'] = $registros;
    $response['total'] = $total;
    $response['page'] = $page;
    $response['per_page'] = $perPage;
    $response['total_pages'] = ceil($total / $perPage);

} catch (Exception $e) {
    $response['message'] = 'Error al obtener registros: ' . $e->getMessage();
}

echo json_encode($response);
