<?php
// clientes_exportar_excel.php
require_once '../../../core/database/conexion.php';
require_once '../../../core/auth/auth.php';

// No dependemos de vendor/autoload.php ya que usaremos el método HTML

try {
    $filtros = isset($_POST['filtros']) ? json_decode($_POST['filtros'], true) : [];
    $orden = isset($_POST['orden']) ? json_decode($_POST['orden'], true) : ['columna' => null, 'direccion' => 'asc'];

    // Construir WHERE (Idéntico a clientes_get_datos.php)
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

    // Configurar headers para descarga de archivo .xls
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="HistorialClientes_' . date('Ymd_His') . '.xls"');
    header('Cache-Control: max-age=0');

    // Iniciar salida HTML que Excel interpretará
    echo '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
    </head>
    <body>
        <table border="1">
            <thead>
                <tr style="background-color: #0E544C; color: #FFFFFF; font-weight: bold;">
                    <th>Membresía</th>
                    <th>Nombre</th>
                    <th>Apellido</th>
                    <th>Celular</th>
                    <th>Fecha Nacimiento</th>
                    <th>Correo</th>
                    <th>Fecha Inscripción</th>
                    <th>Última Compra</th>
                    <th>Sucursal</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($datos as $dato) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($dato['membresia']) . '</td>';
        echo '<td>' . htmlspecialchars($dato['nombre']) . '</td>';
        echo '<td>' . htmlspecialchars($dato['apellido']) . '</td>';
        echo '<td>' . htmlspecialchars($dato['celular']) . '</td>';
        echo '<td>' . htmlspecialchars($dato['fecha_nacimiento']) . '</td>';
        echo '<td>' . htmlspecialchars($dato['correo']) . '</td>';
        echo '<td>' . htmlspecialchars($dato['fecha_registro']) . '</td>';
        echo '<td>' . htmlspecialchars($dato['ultima_compra'] ?: '-') . '</td>';
        echo '<td>' . htmlspecialchars($dato['nombre_sucursal']) . '</td>';
        echo '</tr>';
    }

    echo '</tbody>
        </table>
    </body>
    </html>';
    exit();

} catch (Exception $e) {
    echo "Error al generar el Excel: " . $e->getMessage();
}
