<?php
$_SERVER['HTTP_HOST'] = 'erp.batidospitaya.com';
require 'c:\Users\migue\Desktop\Sistema\Pitaya Web\VisualCode\erp.batidospitaya.com\core\database\conexion.php';

// Simulate balance_inventario_get_detalle logic for maestroToBase
$rMetaAll = $conn->prepare("
    SELECT pp.id, pp.Nombre, pp.id_unidad_producto AS unid, pp.cantidad AS cant, pp.id_producto_maestro AS mid, pp.Id_receta_producto
    FROM producto_presentacion pp
    WHERE pp.presentacion_basica_inventario=1 AND pp.Activo='SI' AND pp.Nombre LIKE '%Granola%'
");
$rMetaAll->execute();
$data = $rMetaAll->fetchAll(PDO::FETCH_ASSOC);
echo "BASE PRODUCTS:\n";
print_r($data);

echo "\n\nMAESTRO TO BASE AFTER FIX:\n";
$maestroToBase = [];
foreach ($data as $pm) {
    $mid = (int) $pm['mid'];
    if ($mid > 0) {
        if (!isset($maestroToBase[$mid]) || empty($pm['Id_receta_producto'])) {
            $maestroToBase[$mid] = [
                'base_pp_id' => (int)$pm['id'], 
                'Nombre'     => $pm['Nombre']
            ];
        }
    }
}
print_r($maestroToBase);
