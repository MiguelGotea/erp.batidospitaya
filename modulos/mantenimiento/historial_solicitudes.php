<?php
// historial_solicitudes.php
$version = "1.0.1";

// Variables de control de filtros
$codigo_sucursal_busqueda = ''; // Rellenar con código de sucursal si es necesario
$cargoOperario = 0; // Rellenar con el cargo del operario (5 = restringido)

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Ticket.php';

$ticketModel = new Ticket();

// Parámetros de paginación
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 25;
$offset = ($page - 1) * $per_page;

// Función para obtener color de urgencia
function getColorUrgencia($nivel) {
    switch($nivel) {
        case 1: return '#28a745';
        case 2: return '#ffc107';
        case 3: return '#fd7e14';
        case 4: return '#dc3545';
        default: return '#8b8b8bff';