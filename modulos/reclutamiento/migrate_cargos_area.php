<?php
// migrate_cargos_area.php
// Script temporal para actualizar el área de los cargos movidos a CDS

require_once '../../core/database/conexion.php';

try {
    $cargosAMover = [17, 19, 12, 9, 10];
    $cargosStr = implode(',', $cargosAMover);

    $sql = "UPDATE plazas_cargos 
            SET area = 'Produccion' 
            WHERE cargo IN ($cargosStr) 
            AND area = 'Administrativo'";

    $stmt = $conn->prepare($sql);
    $stmt->execute();

    $count = $stmt->rowCount();

    echo "Migración completada. Filas actualizadas: $count\n";
} catch (Exception $e) {
    echo "Error en la migración: " . $e->getMessage() . "\n";
}
