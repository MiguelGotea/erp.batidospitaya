<?php
// /public_html/core/auth/auth.php

// Extender sesión a 6 horas (21600 segundos equivalente)
ini_set('session.gc_maxlifetime', 21600);
session_set_cookie_params(21600);
session_start();

// ✅ USAR RUTAS ABSOLUTAS basadas en DOCUMENT_ROOT
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/helpers/funciones.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/database/conexion.php';

// Verificar autenticación
function verificarAutenticacion()
{
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: /login.php');
        exit();
    }
}

// Obtener información del usuario actual
function obtenerUsuarioActual()
{
    if (!isset($_SESSION['usuario_id'])) {
        return null;
    }

    global $conn;
    $stmt = $conn->prepare("
        SELECT o.*, nc.Nombre as cargo_nombre, nc.CodNivelesCargos, anc.Sucursal as sucursal_codigo
        FROM Operarios o
        JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
        JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
        WHERE o.CodOperario = ? 
        AND (anc.Fin IS NULL OR anc.Fin > NOW())
        ORDER BY anc.Fecha DESC
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['usuario_id']]);
    return $stmt->fetch();
}

// Verificar acceso a módulo
function verificarAccesoModulo($modulo)
{
    verificarAutenticacion();

    // Admin tiene acceso a todo
    if (isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin') {
        return;
    }

    $cargosUsuario = obtenerCargosUsuario($_SESSION['usuario_id']);
    if (empty($cargosUsuario)) {
        header('Location: /index.php');
        exit();
    }

    // Normalizar nombres de módulos comunes (singular/plural)
    $moduloBuscado = trim(strtolower($modulo));
    if ($moduloBuscado === 'operario') $moduloBuscado = 'operarios';
    if ($moduloBuscado === 'sistema') $moduloBuscado = 'sistemas';

    global $conn;
    $placeholders = implode(',', array_fill(0, count($cargosUsuario), '?'));

    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM NivelesCargos 
            WHERE CodNivelesCargos IN ($placeholders) 
              AND modulo_ruta = ?
        ");
        
        $params = array_merge($cargosUsuario, [$moduloBuscado]);
        $stmt->execute($params);
        $tieneAcceso = $stmt->fetchColumn() > 0;

        if (!$tieneAcceso) {
            header('Location: /index.php');
            exit();
        }
    } catch (PDOException $e) {
        error_log("Error en verificarAccesoModulo: " . $e->getMessage());
        header('Location: /index.php');
        exit();
    }
}
