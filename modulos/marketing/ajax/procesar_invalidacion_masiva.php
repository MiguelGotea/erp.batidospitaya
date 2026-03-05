<?php
header('Content-Type: application/json');
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/auth/auth.php';

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    // Verificar permiso de edición para invalidación masiva
    if (!tienePermiso('gestion_sorteos', 'edicion', $cargoOperario)) {
        echo json_encode(['success' => false, 'message' => 'Sin permisos para realizar esta acción']);
        exit;
    }

    // ── 1. Cargar colaboradores activos ──
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

    // ── 2. Funciones de normalización y matching ──
    $stopwords = ['de', 'del', 'la', 'las', 'lo', 'los', 'el', 'los', 'los', 'una', 'uno', 'unos', 'unas', 'por', 'para', 'con', 'sin', 'san', 'santa', 'y', 'e', 'o', 'u'];

    $normalizar = function (string $str): string {
        $str = mb_strtolower($str, 'UTF-8');
        $str = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'u', 'n'],
            $str
        );
        return $str;
    };

    $extraerPalabras = function (string $nombre) use ($normalizar, $stopwords): array {
        return array_values(array_filter(
            explode(' ', $normalizar($nombre)),
            fn($p) => mb_strlen($p) >= 3 && !in_array($p, $stopwords, true)
        ));
    };

    $encontrarColaboradorSospechoso = function (string $nombreConcursante, array $colaboradores) use ($extraerPalabras): ?string {
        $palabrasConcursante = $extraerPalabras($nombreConcursante);
        if (count($palabrasConcursante) < 2) {
            return null;
        }

        foreach ($colaboradores as $colab) {
            $palabrasColab = $extraerPalabras($colab['nombre_completo_colab']);
            $coincidencias = count(array_intersect($palabrasConcursante, $palabrasColab));
            if ($coincidencias >= 2) {
                return $colab['nombre_completo_colab'];
            }
        }
        return null;
    };

    // ── 3. Procesar todos los registros ──
    $sqlAll = "SELECT id, nombre_completo, numero_factura, puntos_factura, codigo_sorteo_ia, puntos_ia FROM pitaya_love_registros";
    $stmtAll = ejecutarConsulta($sqlAll, []);
    $registros = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

    $idsUnicos = [];
    $conteoIA = 0;
    $conteoColab = 0;

    foreach ($registros as $reg) {
        $invalidoIA = false;
        $invalidoColab = false;

        // Lógica IA: Mismatch de código o puntos, o código nulo
        if (
            empty($reg['codigo_sorteo_ia']) ||
            $reg['numero_factura'] != $reg['codigo_sorteo_ia'] ||
            (int) $reg['puntos_factura'] != (int) $reg['puntos_ia']
        ) {
            $invalidoIA = true;
            $conteoIA++;
        }

        // Lógica Colaborador
        if ($encontrarColaboradorSospechoso($reg['nombre_completo'], $colaboradoresActivos)) {
            $invalidoColab = true;
            $conteoColab++;
        }

        if ($invalidoIA || $invalidoColab) {
            $idsUnicos[] = (int) $reg['id'];
        }
    }

    $totalActualizados = 0;
    if (!empty($idsUnicos)) {
        $idsUnicos = array_unique($idsUnicos);
        $placeholders = implode(',', array_fill(0, count($idsUnicos), '?'));
        $sqlUpdate = "UPDATE pitaya_love_registros SET valido = 0 WHERE id IN ($placeholders)";
        ejecutarConsulta($sqlUpdate, $idsUnicos);
        $totalActualizados = count($idsUnicos);
    }

    echo json_encode([
        'success' => true,
        'counts' => [
            'ia' => $conteoIA,
            'colab' => $conteoColab,
            'total' => $totalActualizados
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
