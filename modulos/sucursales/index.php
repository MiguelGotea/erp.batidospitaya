<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso al módulo (gestionado dinámicamente desde el panel de permisos)
verificarPermisoORedireccionar('index_sucursales', 'vista', $cargoOperario, '../index.php');

// Obtener todas las sucursales
$sucursales = obtenerTodasSucursales();

/**
 * Obtiene los colaboradores que cumplen años hoy en una sucursal específica
 */
function obtenerCumpleanerosHoySucursal($codSucursal) {
    global $conn;
    
    $hoy = date('m-d');
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                o.CodOperario,
                o.Nombre,
                o.Apellido,
                o.Cumpleanos,
                nc.Nombre as cargo_nombre
            FROM Operarios o
            INNER JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
            INNER JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
            WHERE o.Cumpleanos IS NOT NULL 
            AND o.Cumpleanos != '0000-00-00 00:00:00'
            AND DATE_FORMAT(o.Cumpleanos, '%m-%d') = ?
            AND o.Operativo = 1
            AND anc.Sucursal = ?
            AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
            ORDER BY o.Nombre, o.Apellido
        ");
        $stmt->execute([$hoy, $codSucursal]);
        $cumpleaneros = $stmt->fetchAll();
        
        // Formatear los datos
        $resultado = [];
        foreach ($cumpleaneros as $cumpleanero) {
            $resultado[] = [
                'codigo' => $cumpleanero['CodOperario'],
                'nombre' => trim($cumpleanero['Nombre'] . ' ' . $cumpleanero['Apellido']),
                'cargo' => $cumpleanero['cargo_nombre'],
                'edad' => calcularEdad($cumpleanero['Cumpleanos'])
            ];
        }
        
        return $resultado;
        
    } catch (Exception $e) {
        error_log("Error obteniendo cumpleañeros de sucursal: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtiene las sucursales donde el usuario actual tiene asignación activa.
 * Si está activo en más de una sucursal, aparecen todas.
 */
function obtenerSucursalesUsuarioActual() {
    global $conn;
    
    if (!isset($_SESSION['usuario_id'])) {
        return [];
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT DISTINCT s.codigo, s.nombre 
            FROM AsignacionNivelesCargos anc
            JOIN sucursales s ON anc.Sucursal = s.codigo
            WHERE anc.CodOperario = ? 
            AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
            AND s.activa = 1
            ORDER BY s.nombre
        ");
        $stmt->execute([$_SESSION['usuario_id']]);
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Error obteniendo sucursales del usuario: " . $e->getMessage());
        return [];
    }
}

/**
 * Calcula la fecha de inicio del período continuo actual de contrato de un colaborador.
 * Une contratos si el nuevo inicia inmediatamente (hasta 2 días de diferencia para cubrir fin de semana/feriado)
 * después de que finaliza el anterior.
 */
function obtenerFechaInicioContinua($codOperario) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT MIN(inicio_contrato) as fecha_inicio 
            FROM Contratos 
            WHERE cod_operario = ? 
            AND inicio_contrato IS NOT NULL 
            AND inicio_contrato != '0000-00-00'
        ");
        $stmt->execute([$codOperario]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['fecha_inicio'] : null;
        
    } catch (Exception $e) {
        error_log("Error en obtenerFechaInicioContinua para operario $codOperario: " . $e->getMessage());
        return null;
    }
}

/**
 * Obtiene los colaboradores que cumplen aniversario de contrato hoy en una sucursal específica
 */
function obtenerAniversariantesHoySucursal($codSucursal) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT DISTINCT
                o.CodOperario,
                o.Nombre,
                o.Apellido,
                nc.Nombre as cargo_nombre
            FROM Operarios o
            INNER JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
            INNER JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
            WHERE o.Operativo = 1
            AND anc.Sucursal = ?
            AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
            ORDER BY o.Nombre, o.Apellido
        ");
        $stmt->execute([$codSucursal]);
        $operarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $hoyMD = date('m-d');
        $hoyAnio = (int)date('Y');
        $aniversariantes = [];
        
        foreach ($operarios as $operario) {
            $fechaInicioContinua = obtenerFechaInicioContinua($operario['CodOperario']);
            if ($fechaInicioContinua) {
                $inicioMD = date('m-d', strtotime($fechaInicioContinua));
                if ($inicioMD === $hoyMD) {
                    $inicioAnio = (int)date('Y', strtotime($fechaInicioContinua));
                    $anios = $hoyAnio - $inicioAnio;
                    
                    if ($anios > 0) {
                        $aniversariantes[] = [
                            'codigo' => $operario['CodOperario'],
                            'nombre' => trim($operario['Nombre'] . ' ' . $operario['Apellido']),
                            'cargo' => $operario['cargo_nombre'],
                            'fecha_ingreso' => $fechaInicioContinua,
                            'anios' => $anios
                        ];
                    }
                }
            }
        }
        
        return $aniversariantes;
        
    } catch (Exception $e) {
        error_log("Error obteniendo aniversariantes de sucursal: " . $e->getMessage());
        return [];
    }
}

// Obtener las sucursales asignadas al usuario actual
$sucursalesUsuario = obtenerSucursalesUsuarioActual();

// Obtener cumpleañeros de las sucursales del usuario
$cumpleanerosPorSucursal = [];
$totalCumpleaneros = 0;

foreach ($sucursalesUsuario as $sucursal) {
    $cumpleaneros = obtenerCumpleanerosHoySucursal($sucursal['codigo']);
    if (!empty($cumpleaneros)) {
        $cumpleanerosPorSucursal[$sucursal['codigo']] = [
            'sucursal_nombre' => $sucursal['nombre'],
            'colaboradores' => $cumpleaneros
        ];
        $totalCumpleaneros += count($cumpleaneros);
    }
}

// Obtener aniversariantes de las sucursales del usuario
$aniversariantesPorSucursal = [];
$totalAniversariantes = 0;

foreach ($sucursalesUsuario as $sucursal) {
    $aniversarios = obtenerAniversariantesHoySucursal($sucursal['codigo']);
    if (!empty($aniversarios)) {
        $aniversariantesPorSucursal[$sucursal['codigo']] = [
            'sucursal_nombre' => $sucursal['nombre'],
            'colaboradores' => $aniversarios
        ];
        $totalAniversariantes += count($aniversarios);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sucursales - Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <link rel="stylesheet" href="../../core/assets/css/indexmodulos.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'].'/core/assets/css/indexmodulos.css') ?>"> <!-- CSS propio con manejo de versiones  evitar cache de buscador -->
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Calibri', sans-serif;
            font-size: clamp(12px, 2vw, 18px) !important;
        }
        
        body {
            background-color: #F6F6F6;
            color: #333;
            margin: 0;
            padding: 0;
        }
        
        /* Estilos para la sección de cumpleaños en MI sucursal - CON EFECTOS COMPLETOS */
.cumpleanos-mi-sucursal-container {
    max-width: 1200px;
    margin: 0 auto 30px auto;
}

.cumpleanos-mi-sucursal-card {
    background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 50%, #FF6B6B 100%);
    border-radius: 20px;
    padding: 30px;
    color: white;
    box-shadow: 0 10px 30px rgba(255, 107, 107, 0.3);
    position: relative;
    overflow: hidden;
    border: 3px solid rgba(255, 255, 255, 0.2);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.02); }
    100% { transform: scale(1); }
}

.cumpleanos-mi-sucursal-card::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
    transform: rotate(45deg);
    animation: shine 3s infinite;
}

@keyframes shine {
    0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
    100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
}

.cumpleanos-mi-sucursal-content {
    position: relative;
    z-index: 2;
}

.cumpleanos-mi-sucursal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    position: relative;
    z-index: 2;
}

.cumpleanos-mi-sucursal-title {
    font-size: 2rem !important;
    margin: 0;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    display: flex;
    align-items: center;
    gap: 15px;
}

.cumpleanos-mi-sucursal-title i {
    color: #FFD700;
    animation: bounce 2s infinite;
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.cumpleanos-mi-sucursal-count {
    font-size: 1.2rem !important;
    background: rgba(255, 255, 255, 0.2);
    padding: 10px 20px;
    border-radius: 25px;
    font-weight: bold;
    backdrop-filter: blur(10px);
}

.cumpleanos-mi-sucursal-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
    position: relative;
    z-index: 2;
}

.mi-sucursal-cumpleanos-card {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 15px;
    padding: 20px;
    color: #333;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border: 2px solid rgba(255, 215, 0, 0.3);
    position: relative;
    overflow: hidden;
}

.mi-sucursal-cumpleanos-card::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(45deg, transparent, rgba(255,255,255,0.3), transparent);
    transform: rotate(45deg);
    animation: cardShine 4s infinite;
    z-index: 1;
}

@keyframes cardShine {
    0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
    100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
}

.mi-sucursal-cumpleanos-card:hover {
    transform: translateY(-5px) scale(1.02);
    box-shadow: 0 15px 30px rgba(0,0,0,0.2);
}

.mi-sucursal-cumpleanos-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid rgba(255, 107, 107, 0.3);
    position: relative;
    z-index: 2;
}

.mi-sucursal-cumpleanos-nombre {
    font-size: 1.4rem !important;
    margin: 0;
    color: #FF6B6B;
    display: flex;
    align-items: center;
    gap: 10px;
}

.mi-sucursal-cumpleanos-nombre i {
    color: #FF8E53;
    animation: bounce 2s infinite;
}

.mi-sucursal-cumpleanos-count {
    background: linear-gradient(135deg, #FF6B6B, #FF8E53);
    color: white;
    padding: 6px 15px;
    border-radius: 20px;
    font-size: 1rem !important;
    font-weight: bold;
    box-shadow: 0 3px 10px rgba(255, 107, 107, 0.3);
}

.mi-colaboradores-cumpleanos-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    position: relative;
    z-index: 2;
}

.mi-colaborador-cumpleanos-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: rgba(255, 255, 255, 0.8);
    border-radius: 12px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(255, 107, 107, 0.1);
}

.mi-colaborador-cumpleanos-item::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(45deg, transparent, rgba(255,107,107,0.05), transparent);
    transform: rotate(45deg);
    animation: itemShine 5s infinite;
    z-index: 1;
}

@keyframes itemShine {
    0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
    100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
}

.mi-colaborador-cumpleanos-item:hover {
    background: rgba(255, 255, 255, 0.95);
    transform: translateX(8px);
    box-shadow: 0 5px 15px rgba(255, 107, 107, 0.2);
}

.mi-colaborador-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #FF6B6B, #FF8E53);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 1.3rem !important;
    flex-shrink: 0;
    box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
    animation: avatarPulse 3s infinite;
    position: relative;
    z-index: 2;
}

@keyframes avatarPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.mi-colaborador-info {
    flex: 1;
    position: relative;
    z-index: 2;
}

.mi-colaborador-nombre {
    font-weight: bold;
    font-size: 1.2rem !important;
    color: #FF6B6B;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.mi-colaborador-edad-badge {
    background: linear-gradient(135deg, #FF6B6B, #FF8E53);
    color: white;
    padding: 3px 10px;
    border-radius: 15px;
    font-size: 0.9rem !important;
    font-weight: bold;
    box-shadow: 0 2px 8px rgba(255, 107, 107, 0.3);
    animation: badgePulse 2s infinite;
}

@keyframes badgePulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.mi-colaborador-cargo {
    font-size: 0.95rem !important;
    color: #666;
    background: rgba(255, 107, 107, 0.1);
    padding: 5px 12px;
    border-radius: 15px;
    display: inline-block;
    border: 1px solid rgba(255, 107, 107, 0.2);
}

.mi-colaborador-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    position: relative;
    z-index: 3;
}

.mi-colaborador-confetti {
    font-size: 1.6rem !important;
    animation: spin 3s linear infinite;
    position: relative;
    z-index: 3;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.mi-colaborador-felicitar {
    background: linear-gradient(135deg, #FF6B6B, #FF8E53);
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    color: white;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 3px 10px rgba(255, 107, 107, 0.3);
    animation: heartBeat 2s infinite;
}

@keyframes heartBeat {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.mi-colaborador-felicitar:hover {
    transform: scale(1.2);
    box-shadow: 0 5px 20px rgba(255, 107, 107, 0.5);
}

.cumpleanos-mi-sucursal-message {
    text-align: center;
    padding: 25px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 15px;
    margin-top: 20px;
    position: relative;
    z-index: 2;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.cumpleanos-mi-sucursal-message p {
    margin: 8px 0;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
}

.cumpleanos-mi-sucursal-message p:first-child {
    font-size: 1.3rem !important;
    font-weight: bold;
}

/* Efectos de confeti adicionales */
.cumpleanos-mi-sucursal-card::after {
    content: '🎉🎊🎂🥳✨🌟';
    position: absolute;
    bottom: 15px;
    right: 25px;
    font-size: 2rem;
    opacity: 0.6;
    z-index: 3;
    animation: confettiFloat 4s ease-in-out infinite;
}

@keyframes confettiFloat {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-10px) rotate(10deg); }
}

.mi-sucursal-cumpleanos-card::after {
    content: '🎉';
    position: absolute;
    top: 10px;
    right: 15px;
    font-size: 1.5rem;
    opacity: 0.5;
    animation: confettiSpin 3s linear infinite;
}

@keyframes confettiSpin {
    0% { transform: rotate(0deg) scale(1); }
    50% { transform: rotate(180deg) scale(1.2); }
    100% { transform: rotate(360deg) scale(1); }
}

/* Responsive */
@media (max-width: 768px) {
    .cumpleanos-mi-sucursal-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .cumpleanos-mi-sucursal-grid {
        grid-template-columns: 1fr;
    }
    
    .mi-sucursal-cumpleanos-header {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
    
    .mi-colaborador-cumpleanos-item {
        flex-direction: column;
        text-align: center;
        gap: 12px;
        padding: 20px;
    }
    
    .mi-colaborador-nombre {
        justify-content: center;
    }
    
    .cumpleanos-mi-sucursal-title {
        font-size: 1.6rem !important;
        flex-direction: column;
        gap: 10px;
    }
    
    .mi-colaborador-actions {
        justify-content: center;
        width: 100%;
    }
}

/* En la sección de estilos del index.php */
.fa-music {
    color: #51B8AC; /* Mismo color que los otros íconos */
}

/* Estilos para la sección de aniversarios de contrato - Elegante morado/violeta */
.aniversario-mi-sucursal-container {
    max-width: 1200px;
    margin: 0 auto 30px auto;
}

.aniversario-mi-sucursal-card {
    background: linear-gradient(135deg, #7B2CBF 0%, #9D4EDD 50%, #7B2CBF 100%);
    border-radius: 20px;
    padding: 30px;
    color: white;
    box-shadow: 0 10px 30px rgba(123, 44, 191, 0.3);
    position: relative;
    overflow: hidden;
    border: 3px solid rgba(255, 255, 255, 0.2);
    animation: pulseAniversario 2.5s infinite;
}

@keyframes pulseAniversario {
    0% { transform: scale(1); }
    50% { transform: scale(1.015); }
    100% { transform: scale(1); }
}

.aniversario-mi-sucursal-card::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(45deg, transparent, rgba(255,255,255,0.08), transparent);
    transform: rotate(45deg);
    animation: shineAniversario 4s infinite;
}

@keyframes shineAniversario {
    0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
    100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
}

.aniversario-mi-sucursal-content {
    position: relative;
    z-index: 2;
}

.aniversario-mi-sucursal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    position: relative;
    z-index: 2;
}

.aniversario-mi-sucursal-title {
    font-size: 2rem !important;
    margin: 0;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    display: flex;
    align-items: center;
    gap: 15px;
}

.aniversario-mi-sucursal-title i {
    color: #FFD700;
    animation: bounceAniversario 2s infinite;
}

@keyframes bounceAniversario {
    0% { transform: scale(1); }
    50% { transform: scale(1.15) rotate(5deg); }
    100% { transform: scale(1); }
}

.aniversario-mi-sucursal-count {
    font-size: 1.2rem !important;
    background: rgba(255, 255, 255, 0.2);
    padding: 10px 20px;
    border-radius: 25px;
    font-weight: bold;
    backdrop-filter: blur(10px);
}

.aniversario-mi-sucursal-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
    position: relative;
    z-index: 2;
}

.mi-sucursal-aniversario-card {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 15px;
    padding: 20px;
    color: #333;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border: 2px solid rgba(255, 215, 0, 0.2);
    position: relative;
    overflow: hidden;
}

.mi-sucursal-aniversario-card:hover {
    transform: translateY(-5px) scale(1.02);
    box-shadow: 0 15px 30px rgba(0,0,0,0.2);
}

.mi-sucursal-aniversario-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid rgba(123, 44, 191, 0.3);
    position: relative;
    z-index: 2;
}

.mi-sucursal-aniversario-nombre {
    font-size: 1.4rem !important;
    margin: 0;
    color: #7B2CBF;
    display: flex;
    align-items: center;
    gap: 10px;
}

.mi-sucursal-aniversario-count {
    background: linear-gradient(135deg, #7B2CBF, #9D4EDD);
    color: white;
    padding: 6px 15px;
    border-radius: 20px;
    font-size: 1rem !important;
    font-weight: bold;
    box-shadow: 0 3px 10px rgba(123, 44, 191, 0.3);
}

.mi-colaboradores-aniversario-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    position: relative;
    z-index: 2;
}

.mi-colaborador-aniversario-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: rgba(255, 255, 255, 0.8);
    border-radius: 12px;
    transition: all 0.3s ease;
    border: 1px solid rgba(123, 44, 191, 0.1);
    position: relative;
    overflow: hidden;
}

.mi-colaborador-aniversario-item:hover {
    background: rgba(255, 255, 255, 0.95);
    transform: translateX(8px);
    box-shadow: 0 5px 15px rgba(123, 44, 191, 0.15);
}

.mi-colaborador-avatar-aniv {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #7B2CBF, #9D4EDD);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 1.3rem !important;
    flex-shrink: 0;
    box-shadow: 0 5px 15px rgba(123, 44, 191, 0.3);
    position: relative;
    z-index: 3;
}

.mi-colaborador-nombre-aniv {
    font-weight: bold;
    font-size: 1.2rem !important;
    color: #7B2CBF;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.mi-colaborador-anios-badge {
    background: linear-gradient(135deg, #7B2CBF, #9D4EDD);
    color: white;
    padding: 3px 10px;
    border-radius: 15px;
    font-size: 0.9rem !important;
    font-weight: bold;
    box-shadow: 0 2px 8px rgba(123, 44, 191, 0.3);
}

.mi-colaborador-cargo-aniv {
    font-size: 0.95rem !important;
    color: #666;
    background: rgba(123, 44, 191, 0.08);
    padding: 5px 12px;
    border-radius: 15px;
    display: inline-block;
    border: 1px solid rgba(123, 44, 191, 0.15);
}

.aniversario-mi-sucursal-message {
    text-align: center;
    padding: 25px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 15px;
    margin-top: 20px;
    position: relative;
    z-index: 2;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.aniversario-mi-sucursal-message p {
    margin: 8px 0;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
}

.aniversario-mi-sucursal-message p:first-child {
    font-size: 1.3rem !important;
    font-weight: bold;
}

/* Efecto de estrellas flotantes para aniversarios */
.aniversario-mi-sucursal-card::after {
    content: '🏆✨🎖️👏⭐';
    position: absolute;
    bottom: 15px;
    right: 25px;
    font-size: 2rem;
    opacity: 0.6;
    z-index: 3;
    animation: starsFloat 4s ease-in-out infinite;
}

@keyframes starsFloat {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-10px) rotate(-10deg); }
}

.mi-sucursal-aniversario-card::after {
    content: '✨';
    position: absolute;
    top: 10px;
    right: 15px;
    font-size: 1.5rem;
    opacity: 0.5;
    animation: starsSpin 3s linear infinite;
}

@keyframes starsSpin {
    0% { transform: rotate(0deg) scale(1); }
    50% { transform: rotate(-180deg) scale(1.2); }
    100% { transform: rotate(-360deg) scale(1); }
}

@media (max-width: 768px) {
    .aniversario-mi-sucursal-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .aniversario-mi-sucursal-grid {
        grid-template-columns: 1fr;
    }
    
    .mi-sucursal-aniversario-header {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
    
    .mi-colaborador-aniversario-item {
        flex-direction: column;
        text-align: center;
        gap: 12px;
        padding: 20px;
    }
    
    .mi-colaborador-nombre-aniv {
        justify-content: center;
    }
    
    .aniversario-mi-sucursal-title {
        font-size: 1.6rem !important;
        flex-direction: column;
        gap: 10px;
    }
}
</style>
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container">
        <div class="contenedor-principal">
            <?php echo renderHeader($usuario,'Bienvenidos al equipo pitaya!'); ?>
            
            <h2 class="section-title" style="Display:None">
                <i class="fas fa-chart-line"></i> Indicadores de Control
            </h2>
            
            <!-- Sección de Cumpleaños de MI Sucursal - CON EFECTOS COMPLETOS -->
            <?php if ($totalCumpleaneros > 0): ?>
                <div class="cumpleanos-mi-sucursal-container">
                    <div class="cumpleanos-mi-sucursal-card">
                        <div class="cumpleanos-mi-sucursal-content">
                            <div class="cumpleanos-mi-sucursal-header">
                                <h2 class="cumpleanos-mi-sucursal-title">
                                    <i class="fas fa-birthday-cake"></i>
                                    ¡Cumpleaños en Mi Sucursal! 🎉
                                </h2>
                                <div class="cumpleanos-mi-sucursal-count">
                                    <?= $totalCumpleaneros ?> colaborador(es) de tu sucursal celebran hoy
                                </div>
                            </div>
                            
                            <div class="cumpleanos-mi-sucursal-grid">
                                <?php foreach ($cumpleanerosPorSucursal as $sucursalCodigo => $sucursalData): ?>
                                    <div class="mi-sucursal-cumpleanos-card">
                                        <div class="mi-sucursal-cumpleanos-header">
                                            <h3 class="mi-sucursal-cumpleanos-nombre">
                                                <i class="fas fa-store"></i>
                                                <?= htmlspecialchars($sucursalData['sucursal_nombre']) ?>
                                            </h3>
                                            <span class="mi-sucursal-cumpleanos-count">
                                                <?= count($sucursalData['colaboradores']) ?> cumpleañero(s)
                                            </span>
                                        </div>
                                        
                                        <div class="mi-colaboradores-cumpleanos-list">
                                            <?php foreach ($sucursalData['colaboradores'] as $colaborador): ?>
                                                <div class="mi-colaborador-cumpleanos-item">
                                                    <div class="mi-colaborador-avatar">
                                                        <?= strtoupper(substr($colaborador['nombre'], 0, 1)) ?>
                                                    </div>
                                                    <div class="mi-colaborador-info">
                                                        <div class="mi-colaborador-nombre">
                                                            <?= htmlspecialchars($colaborador['nombre']) ?>
                                                            <?php if ($colaborador['edad']): ?>
                                                                <span style="display:none;" class="mi-colaborador-edad-badge"><?= $colaborador['edad'] ?> años</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="mi-colaborador-cargo">
                                                            <?= htmlspecialchars($colaborador['cargo']) ?>
                                                        </div>
                                                    </div>
                                                    <div class="mi-colaborador-actions">
                                                        <span class="mi-colaborador-confetti">🎂</span>
                                                        <button class="mi-colaborador-felicitar" onclick="felicitarColaborador(<?= $colaborador['codigo'] ?>, '<?= htmlspecialchars($colaborador['nombre']) ?>')">
                                                            <i class="fas fa-heart"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="cumpleanos-mi-sucursal-message">
                                <p>🎊 <strong>¡Felicidades a tus compañeros de sucursal!</strong> 🎊</p>
                                <p>No olvides darles tus mejores deseos en su día especial.</p>
                                Te invitamos a pasar por tu batido de cortesía en cualquier sucursal si tienes membresía de Club Pitaya 🥤<br>
                                    <strong>Con nuestros mejores deseos, el equipo de Batidos Pitaya 💜✨</strong>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Sección de Aniversarios de Contrato de MI Sucursal - CON EFECTOS COMPLETOS -->
            <?php if ($totalAniversariantes > 0): ?>
                <div class="aniversario-mi-sucursal-container">
                    <div class="aniversario-mi-sucursal-card">
                        <div class="aniversario-mi-sucursal-content">
                            <div class="aniversario-mi-sucursal-header">
                                <h2 class="aniversario-mi-sucursal-title">
                                    <i class="fas fa-award"></i>
                                    ¡Aniversarios Laborales en Mi Sucursal! 🏆
                                </h2>
                                <div class="aniversario-mi-sucursal-count">
                                    <?= $totalAniversariantes ?> colaborador(es) cumplen años con nosotros hoy
                                </div>
                            </div>
                            
                            <div class="aniversario-mi-sucursal-grid">
                                <?php foreach ($aniversariantesPorSucursal as $sucursalCodigo => $sucursalData): ?>
                                    <div class="mi-sucursal-aniversario-card">
                                        <div class="mi-sucursal-aniversario-header">
                                            <h3 class="mi-sucursal-aniversario-nombre">
                                                <i class="fas fa-store"></i>
                                                <?= htmlspecialchars($sucursalData['sucursal_nombre']) ?>
                                            </h3>
                                            <span class="mi-sucursal-aniversario-count">
                                                <?= count($sucursalData['colaboradores']) ?> aniversario(s)
                                            </span>
                                        </div>
                                        
                                        <div class="mi-colaboradores-aniversario-list">
                                            <?php foreach ($sucursalData['colaboradores'] as $colaborador): ?>
                                                <div class="mi-colaborador-aniversario-item">
                                                    <div class="mi-colaborador-avatar-aniv">
                                                        <?= strtoupper(substr($colaborador['nombre'], 0, 1)) ?>
                                                    </div>
                                                    <div class="mi-colaborador-info">
                                                        <div class="mi-colaborador-nombre-aniv">
                                                            <?= htmlspecialchars($colaborador['nombre']) ?>
                                                            <span class="mi-colaborador-anios-badge"><?= $colaborador['anios'] ?> <?= $colaborador['anios'] == 1 ? 'año' : 'años' ?></span>
                                                        </div>
                                                        <div class="mi-colaborador-cargo-aniv">
                                                            <?= htmlspecialchars($colaborador['cargo']) ?>
                                                        </div>
                                                    </div>
                                                    <div class="mi-colaborador-actions">
                                                        <span class="mi-colaborador-confetti">🏆</span>
                                                        <button class="mi-colaborador-felicitar" onclick="felicitarColaboradorAniversario(<?= $colaborador['codigo'] ?>, '<?= htmlspecialchars($colaborador['nombre']) ?>', <?= $colaborador['anios'] ?>)">
                                                            <i class="fas fa-award"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="aniversario-mi-sucursal-message">
                                <p>👏 <strong>¡Muchísimas felicidades por tu trayectoria en Batidos Pitaya!</strong> 👏</p>
                                <p>Agradecemos tu esfuerzo, dedicación y compromiso diario con nuestro equipo.</p>
                                <strong>¡Que sigan los éxitos juntos! 💜✨</strong>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>


            <h2 class="section-title">
                <i class="fas fa-bolt"></i> Accesos Rápidos
            </h2>

            <div class="quick-access-grid">
                <a href="marcacion.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-fingerprint"></i>
                    </div>
                    <div class="quick-access-title">Marcacion</div>
                </a>
                
                <a href="historial_marcaciones_sucursales.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="quick-access-title">Historial de Marcaciones</div>
                </a>
                
                <a href="../supervision/ver_horarios_compactos.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="quick-access-title">Horarios Programados</div>
                </a>
                <a href="ferias/index.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-people-carry"></i>
                    </div>
                    <div class="quick-access-title">Gestión de Ferias</div>
                </a>
                <?php if (verificarAccesoCargo([16, 49])): ?>
                <a href="cierres.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="quick-access-title">Cierres</div>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
// Función para felicitar a un colaborador
function felicitarColaborador(codigoColaborador, nombreColaborador) {
    // Aquí puedes implementar diferentes acciones:
    
    // Opción 1: Mostrar alerta de felicitación
    alert(`¡Has felicitado a ${nombreColaborador}! 🎉\n\n¡Que tengas un excelente día!`);
    
    // Opción 2: Enviar notificación al sistema
    // enviarFelicitacionSistema(codigoColaborador);
    
    // Opción 3: Abrir modal personalizado
    // abrirModalFelicitacion(codigoColaborador, nombreColaborador);
    
    // Opción 4: Vibrar en dispositivos móviles (si está permitido)
    if (navigator.vibrate) {
        navigator.vibrate([100, 50, 100]);
    }
}

// Función para felicitar a un colaborador por su aniversario
function felicitarColaboradorAniversario(codigoColaborador, nombreColaborador, anios) {
    alert(`¡Has felicitado a ${nombreColaborador} por su aniversario de ${anios} ${anios === 1 ? 'año' : 'años'} en la empresa! 🏆🎉\n\n¡Gracias por tu dedicación!`);
    if (navigator.vibrate) {
        navigator.vibrate([100, 50, 100]);
    }
}

// Función para enviar felicitación al sistema (ejemplo)
function enviarFelicitacionSistema(codigoColaborador) {
    fetch('../../api/felicitar_cumpleanos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            codigo_colaborador: codigoColaborador,
            felicitador: '<?= $_SESSION['usuario_id'] ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('¡Felicitación enviada! 🎉');
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Efectos de confeti y estrellas al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    const cumpleContainer = document.querySelector('.cumpleanos-mi-sucursal-container');
    if (cumpleContainer) {
        // Agregar efecto de confeti visual
        crearEfectoConfeti();
    }
    
    const aniversarioContainer = document.querySelector('.aniversario-mi-sucursal-container');
    if (aniversarioContainer) {
        // Agregar efecto de estrellas visuales
        crearEfectoEstrellas();
    }
});

function crearEfectoConfeti() {
    // Efecto simple de "confeti" con emojis
    const confetiEmojis = ['🎉', '🎊', '🎂', '🥳', '✨', '🌟'];
    const container = document.querySelector('.cumpleanos-mi-sucursal-container');
    
    for (let i = 0; i < 10; i++) {
        setTimeout(() => {
            const confeti = document.createElement('div');
            confeti.textContent = confetiEmojis[Math.floor(Math.random() * confetiEmojis.length)];
            confeti.style.position = 'absolute';
            confeti.style.fontSize = '1.5rem';
            confeti.style.opacity = '0.7';
            confeti.style.zIndex = '1';
            confeti.style.left = Math.random() * 100 + '%';
            confeti.style.top = '-30px';
            confeti.style.animation = `confetiFall ${2 + Math.random() * 2}s linear forwards`;
            
            container.appendChild(confeti);
            
            // Remover después de la animación
            setTimeout(() => {
                if (confeti.parentNode) {
                    confeti.parentNode.removeChild(confeti);
                }
            }, 4000);
        }, i * 300);
    }
}

function crearEfectoEstrellas() {
    // Efecto de "estrellas y premios" con emojis
    const estrellaEmojis = ['🏆', '✨', '🎖️', '👏', '⭐', '🌟'];
    const container = document.querySelector('.aniversario-mi-sucursal-container');
    
    for (let i = 0; i < 10; i++) {
        setTimeout(() => {
            const estrella = document.createElement('div');
            estrella.textContent = estrellaEmojis[Math.floor(Math.random() * estrellaEmojis.length)];
            estrella.style.position = 'absolute';
            estrella.style.fontSize = '1.5rem';
            estrella.style.opacity = '0.7';
            estrella.style.zIndex = '1';
            estrella.style.left = Math.random() * 100 + '%';
            estrella.style.top = '-30px';
            estrella.style.animation = `estrellaFall ${2.5 + Math.random() * 2}s linear forwards`;
            
            container.appendChild(estrella);
            
            // Remover después de la animación
            setTimeout(() => {
                if (estrella.parentNode) {
                    estrella.parentNode.removeChild(estrella);
                }
            }, 4500);
        }, i * 300);
    }
}

// Agregar la animación de confeti fall y estrella fall
const style = document.createElement('style');
style.textContent = `
    @keyframes confetiFall {
        0% {
            transform: translateY(0) rotate(0deg);
            opacity: 0.7;
        }
        100% {
            transform: translateY(100vh) rotate(360deg);
            opacity: 0;
        }
    }
    @keyframes estrellaFall {
        0% {
            transform: translateY(0) rotate(0deg) scale(0.8);
            opacity: 0.7;
        }
        50% {
            transform: translateY(50vh) rotate(180deg) scale(1.2);
            opacity: 0.8;
        }
        100% {
            transform: translateY(100vh) rotate(360deg) scale(0.8);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
</script>
</body>
</html>