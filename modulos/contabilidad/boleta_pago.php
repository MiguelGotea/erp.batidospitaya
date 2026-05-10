<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

//session_start();
require_once '../../core/auth/auth.php';

 // Redirige automáticamente si no está logueado

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario = obtenerUsuarioActual();
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
$assets_url = '/assets/';

// Obtener la última boleta del operario basada en el último contrato
$cod_operario = $_SESSION['usuario_id'];

// Primero obtener el último CodContrato del operario
$ultimo_cod_contrato = obtenerUltimoCodigoContrato($cod_operario);

$boleta = null;

if ($ultimo_cod_contrato) {
    // Buscar la boleta usando el CodContrato
    $stmt = ejecutarConsulta(
        "SELECT * FROM BoletaPago 
         WHERE cod_operario = ? 
         ORDER BY fecha_planilla DESC, id_boleta DESC 
         LIMIT 1",
        [$ultimo_cod_contrato] // Cambiado de $cod_operario a $ultimo_cod_contrato
    );

    if ($stmt && $stmt->rowCount() > 0) {
        $boleta = $stmt->fetch();
    }
    
    // Si no encuentra con CodContrato, intentar con cod_operario original como fallback
    if (!$boleta) {
        $stmt = ejecutarConsulta(
            "SELECT * FROM BoletaPago 
             WHERE cod_operario = ? 
             ORDER BY fecha_planilla DESC, id_boleta DESC 
             LIMIT 1",
            [$cod_operario]
        );

        if ($stmt && $stmt->rowCount() > 0) {
            $boleta = $stmt->fetch();
        }
    }
} else {
    // Fallback: buscar directamente por cod_operario si no hay contrato
    $stmt = ejecutarConsulta(
        "SELECT * FROM BoletaPago 
         WHERE cod_operario = ? 
         ORDER BY fecha_planilla DESC, id_boleta DESC 
         LIMIT 1",
        [$cod_operario]
    );

    if ($stmt && $stmt->rowCount() > 0) {
        $boleta = $stmt->fetch();
    }
}

// Función para formatear valores (mostrar - si es null o 0)
function formatearValor($valor, $esMonto = false) {
    if ($valor === null || $valor === '' || $valor == 0) {
        return '-';
    }
    
    if ($esMonto) {
        // Formatear con comas para miles y punto para decimales
        return number_format($valor, 2, '.', ',');
    }
    
    return $valor;
}

// Calcular totales
$total_ingresos = 0;
$total_deducciones = 0;
$total_quincena = 0;

if ($boleta) {
    $total_ingresos = ($boleta['salario_basico_quincenal_monto'] ?? 0) + 
                     ($boleta['feriados_laborados_monto'] ?? 0) + 
                     ($boleta['horas_extras_monto'] ?? 0);
    
    // Usar valor absoluto para asegurar que las deducciones sean siempre positivas
    $total_deducciones = abs($boleta['faltas_septimo_dia_monto'] ?? 0) + 
                        abs($boleta['inss_empleado_monto'] ?? 0) +
                        abs($boleta['Deducciones'] ?? 0);
    
    $total_quincena = $total_ingresos - $total_deducciones;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boleta de Pago - Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="<?php echo $assets_url; ?>img/icon12.png" type="image/png">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Calibri', sans-serif;
        }
        
        body {
            background-color: #F6F6F6;
            color: #333;
            padding: 0;
            margin: 0;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        /* Header styles */
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .logo {
            height: 50px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background-color: #51B8AC;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .btn-logout {
            background: #51B8AC;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-logout:hover {
            background: #0E544C;
        }
        
        /* Boleta styles */
        .boleta-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .boleta-header h1 {
            color: #0E544C;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .empleado-info {
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .fecha-info {
            color: #666;
            margin-bottom: 20px;
        }
        
        .seccion {
            margin-bottom: 25px;
        }
        
        .seccion h2 {
            color: #0E544C;
            border-bottom: 2px solid #51B8AC;
            padding-bottom: 5px;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .tabla-boleta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        
        .tabla-boleta th {
            background-color: #0E544C;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: normal;
        }
        
        .tabla-boleta td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .tabla-boleta tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .total-row {
            font-weight: bold;
            background-color: #e8f5e9 !important;
        }
        
        .total-quincena {
            font-size: 20px;
            color: #0E544C;
            text-align: center;
            padding: 15px;
            background-color: #f1f8e9;
            border-radius: 4px;
            margin: 20px 0;
        }
        
        .info-adicional {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        
        .firma {
            text-align: center;
            width: 45%;
        }
        
        .firma p {
            margin-top: 50px;
            border-top: 1px solid #333;
            padding-top: 5px;
        }
        
        .sin-boleta {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }
        
        .sin-boleta i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #ccc;
        }
        
        @media print {
            .btn-logout {
                display: none;
            }
            
            .header-container {
                border-bottom: none;
                margin-bottom: 10px;
            }
            
            body {
                background-color: white;
                padding: 0;
            }
            
            .container {
                box-shadow: none;
                padding: 10px;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header-container {
                flex-direction: column;
                text-align: center;
            }
            
            .info-adicional {
                flex-direction: column;
                gap: 30px;
            }
            
            .firma {
                width: 100%;
            }
        }
        
        .buttons-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            flex-grow: 1;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .btn-agregar {
            background-color: transparent;
            color: #51B8AC;
            border: 1px solid #51B8AC;
            text-decoration: none;
            padding: 6px 10px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            white-space: nowrap;
            font-size: 14px;
            flex-shrink: 0;
        }
        
        .btn-agregar.activo {
            background-color: #51B8AC;
            color: white;
            font-weight: normal;
        }
        
        .btn-agregar:hover {
            background-color: #0E544C;
            color: white;
            border-color: #0E544C;
        }
        
        /* Responsive styles */
        @media (max-width: 768px) {
            .header-container {
                flex-direction: row;
                align-items: center;
                gap: 10px;
            }
            
            .buttons-container {
                position: static;
                transform: none;
                order: 3;
                width: 100%;
                justify-content: center;
                margin-top: 10px;
            }
            
            .logo-container {
                order: 1;
                margin-right: 0;
            }
            
            .user-info {
                order: 2;
                margin-left: auto;
            }
            
            .btn-agregar {
                padding: 6px 10px;
                font-size: 13px;
            }
        }
        
        @media (max-width: 480px) {
            .btn-agregar {
                flex-grow: 1;
                justify-content: center;
                white-space: normal;
                text-align: center;
                padding: 8px 5px;
            }
            
            .user-info {
                flex-direction: column;
                align-items: flex-end;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-container">
                <div class="logo-container">
                    <img src="<?php echo $assets_url; ?>img/Logo.svg" alt="Batidos Pitaya" class="logo">
                </div>
                
                <div class="buttons-container" style="display:none;">
                    <a href="../operarios/historial_marcacion_individual.php" class="btn-agregar">
                        <i class="fas fa-history"></i> <span class="btn-text">Mi Asistencia</span>
                    </a>
                </div>
                
                <div class="user-info">
                    <div class="user-avatar">
                        <?= false ? 
                            strtoupper(substr($usuario['nombre'], 0, 1)) : 
                            strtoupper(substr($usuario['Nombre'], 0, 1)) ?>
                    </div>
                    <div>
                        <div>
                            <?= false ? 
                                htmlspecialchars($usuario['nombre']) : 
                                htmlspecialchars($usuario['Nombre'].' '.$usuario['Apellido']) ?>
                        </div>
                        <small>
                            <?= htmlspecialchars($cargoUsuario) ?>
                        </small>
                    </div>
                    <a href="/index.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </header>
        
        <?php if ($boleta): ?>
            <div class="boleta-header">
                <h1>BOLETA DE PAGO</h1>
                <div class="empleado-info"><?= htmlspecialchars($boleta['empleado_nombre']) ?></div>
                <div class="fecha-info">Fecha de Planilla: <?= date('d-M-y', strtotime($boleta['fecha_planilla'])) ?></div>
                <div class="fecha-info">Salario Básico: <?= $boleta['salario_basico'] ?></div>
            </div>
            
            <!-- Ingresos -->
            <div class="seccion">
                <h2>Ingresos</h2>
                <table class="tabla-boleta">
                    <tr style="display:none;">
                        <th>Concepto</th>
                        <th>Cantidad</th>
                        <th>Monto</th>
                    </tr>
                    <tr>
                        <td>Salario Básico Quincenal</td>
                        <td style="text-align:right;"><?= formatearValor($boleta['salario_basico_quincenal_dias']) ?></td>
                        <td>días</td>
                        <td style="text-align:right;"><?= formatearValor($boleta['salario_basico_quincenal_monto'], true) ?></td>
                        <td>C$</td>
                    </tr>
                    <tr>
                        <td>Feriados Laborados</td>
                        <td style="text-align:right;"><?= formatearValor($boleta['feriados_laborados_horas']) ?></td>
                        <td>hrs</td>
                        <td style="text-align:right;"><?= formatearValor($boleta['feriados_laborados_monto'], true) ?></td>
                        <td>C$</td>
                    </tr>
                    <tr>
                        <td>Horas Extras</td>
                        <td style="text-align:right;"><?= formatearValor($boleta['horas_extras_horas']) ?></td>
                        <td>horas</td>
                        <td style="text-align:right;"><?= formatearValor($boleta['horas_extras_monto'], true) ?></td>
                        <td>C$</td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="3"><strong>Total Ingresos:</strong></td>
                        <td style="text-align:right;"><strong><?= number_format($total_ingresos, 2, '.', ',') ?></strong></td>
                        <td><strong>C$</strong></td>
                    </tr>
                </table>
            </div>
            
            <!-- Deducciones -->
            <div class="seccion">
                <h2>Deducciones</h2>
                <table class="tabla-boleta">
                    <tr style="display:none;">
                        <th>Concepto</th>
                        <th>Cantidad</th>
                        <th>Monto</th>
                    </tr>
                    <tr>
                        <td>Faltas + Séptimo día</td>
                        <td style="text-align:right;"><?= formatearValor($boleta['faltas_septimo_dia_dias']) ?></td>
                        <td>días</td>
                        <td style="text-align:right;">-<?= formatearValor(abs($boleta['faltas_septimo_dia_monto']), true) ?></td>
                        <td>C$</td>
                    </tr>
                    <tr>
                        <td colspan="3">INSS Empleado (<?= formatearValor($boleta['inss_empleado_porcentaje']) ?> %)</td>
                        <td style="text-align:right;">-<?= formatearValor(abs($boleta['inss_empleado_monto']), true) ?></td>
                        <td>C$</td>
                    </tr>
                    <!-- NUEVA FILA PARA LA DEDUCCIÓN ADICIONAL -->
                    <tr>
                        <td colspan="3"><?= htmlspecialchars($boleta['nombre_deduccion_adicional'] ?? 'Deducciones y Pérdidas') ?></td>
                        <td style="text-align:right;">-<?= formatearValor(abs($boleta['Deducciones'] ?? 0), true) ?></td>
                        <td>C$</td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="3"><strong>Total Deducciones:</strong></td>
                        <td style="text-align:right;"><strong>-<?= number_format(abs($total_deducciones), 2, '.', ',') ?></strong></td>
                        <td><strong>C$</strong></td>
                    </tr>
                </table>
            </div>
            
            <!-- Total Quincena -->
            <div class="total-quincena">
                <strong>Total Quincena: <?= number_format($total_quincena, 2, '.', ',') ?> C$</strong>
            </div>
            
            <!-- Información Adicional -->
            <div class="seccion">
                <table class="tabla-boleta">
                    <tr>
                        <td>Vacaciones tomadas del mes:</td>
                        <td style="text-align:right;"><?= formatearValor($boleta['vacaciones_dias']) ?></td>
                        <td>días</td>
                    </tr>
                </table>
            </div>
            
            <!-- Firmas -->
            <div class="info-adicional">
                <div class="firma">
                    <p style="margin-top:75px;">_________________________</p>
                    <div><strong>Entrega:</strong></div>
                    <div>Batidos Pitaya</div>
                </div>
                
                <div class="firma">
                    <p style="margin-top:75px;">_________________________</p>
                    <div><strong>Recibe conforme:</strong></div>
                    <div><?= htmlspecialchars($boleta['empleado_nombre']) ?></div>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 23px; font-style: italic; color: #666;">
                Gracias por ser parte del Equipo Pitaya.
            </div>
            
            <div style="text-align: center; margin-top: 5px; font-style: italic; color: #666; margin-bottom:15px;">
                Si tienes dudas, por favor contáctanos.
            </div>
            
        <?php else: ?>
            <div class="sin-boleta">
                <i class="fas fa-file-invoice"></i>
                <h2>No se encontró boleta de pago</h2>
                <p>No hay boletas de pago registradas para su usuario.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Opción para imprimir la boleta
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>