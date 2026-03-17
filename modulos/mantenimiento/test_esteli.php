<?php
require_once __DIR__ . '/../../../core/database/conexion.php';
require_once __DIR__ . '/config/database.php';

$db = new Database();

// Check Branches
$sql = "SELECT id, codigo, nombre, Latitude, Longitude FROM sucursales WHERE nombre LIKE '%Esteli%'";
$res = $db->getConnection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo "Sucursales Esteli:\n";
print_r($res);

// Check Tickets
$sql = "SELECT id, cod_sucursal, nivel_urgencia, titulo, status, tipo_formulario FROM mtto_tickets WHERE cod_sucursal IN (SELECT codigo FROM sucursales WHERE nombre LIKE '%Esteli%') AND status IN ('solicitado', 'agendado')";
$res = $db->getConnection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo "\nTickets Pendientes en Esteli:\n";
print_r($res);
