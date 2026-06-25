<?php
$_SERVER['HTTP_HOST'] = 'erp.batidospitaya.com';
require 'c:\Users\migue\Desktop\Sistema\Pitaya Web\VisualCode\erp.batidospitaya.com\core\database\conexion.php';
$r = $conn->query("SELECT id, Nombre, Id_receta_producto, id_producto_maestro, presentacion_basica_inventario, presentacion_receta FROM producto_presentacion WHERE Nombre LIKE '%Granola%' AND Activo='SI'");
echo json_encode($r->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
