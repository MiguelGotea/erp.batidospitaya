<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoId = $usuario['CodNivelesCargos'] ?? 0;

// Verificar acceso al módulo
if (!tienePermiso('editar_colaborador', 'vista', $cargoId)) {
    header('Location: ../index.php');
    exit();
}

// 1. Obtener parámetros básicos
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = 'No se ha especificado un colaborador para editar';
    header('Location: colaboradores.php');
    exit();
}

$codOperario = intval($_GET['id']);
$pestaña_activa = isset($_GET['pestaña']) ? $_GET['pestaña'] : 'datos-personales';

// 2. Cargar funciones y lógica (ya tienen acceso a $codOperario y $pestaña_activa)
require_once 'editar_colaborador_componentes/logic/funciones_colaborador.php';

// 3. Obtener datos del colaborador
$colaborador = obtenerColaboradorPorId($codOperario);

if (!$colaborador) {
    $_SESSION['error'] = 'Colaborador no encontrado';
    header('Location: colaboradores.php');
    exit();
}

$cargoUsuario = $usuario['cargo_nombre'] ?? 'No definido';



// Renderizar la vista
require_once 'editar_colaborador_componentes/vista_colaborador.php';
