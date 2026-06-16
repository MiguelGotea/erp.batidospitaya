<?php
require_once '../../core/auth/auth.php';
require_once '../../core/permissions/permissions.php';

// Verificar conexión
if (!$conn) {
    die("Error de conexión a la base de datos");
}

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
if (!tienePermiso('registro_vacaciones', 'vista', $cargoOperario)) {
    header('Location: ../../../index.php');
    exit();
}


// Obtener datos desde GET (pre-llenado desde el modal de vacaciones)
$tienda = $_GET['tienda'] ?? '';
$jefe = $_GET['jefe'] ?? '';
$fecha_emision = $_GET['fecha_emision'] ?? date('d/m/Y');
$nombre = $_GET['nombre'] ?? '';
$puesto = $_GET['puesto'] ?? '';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$total_dias = $_GET['total_dias'] ?? '';


// Formatear fechas para mostrar (de Y-m-d a d/m/Y)
function formatarFecha($fecha)
{
    if (empty($fecha))
        return '';
    $dt = DateTime::createFromFormat('Y-m-d', $fecha);
    return $dt ? $dt->format('d/m/Y') : $fecha;
}

$fecha_inicio_fmt = formatarFecha($fecha_inicio);
$fecha_fin_fmt = formatarFecha($fecha_fin);

$titulo_boleta = 'Acción de Personal - Vacaciones';
$label_total_dias = 'Total días de vacaciones:';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir
        <?= htmlspecialchars($titulo_boleta) ?> - Batidos Pitaya
    </title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        /* Estilos generales de pantalla */
        body {
            background-color: #f0f2f5;
            margin: 0;
            padding: 20px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            line-height: 1.4;
            color: #000;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Barra de herramientas superior (oculta al imprimir) */
        .print-toolbar {
            width: 80mm;
            background-color: #fff;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            gap: 10px;
            box-sizing: border-box;
        }

        .btn-action {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-family: Arial, sans-serif;
            font-size: 12px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: background-color 0.2s;
        }

        .btn-print {
            background-color: #17a2b8;
            color: white;
        }

        .btn-print:hover {
            background-color: #138496;
        }

        .btn-close-custom {
            background-color: #6c757d;
            color: white;
        }

        .btn-close-custom:hover {
            background-color: #5a6268;
        }

        /* Contenedor del Ticket Térmico */
        .ticket-container {
            background-color: #fff;
            width: 80mm;
            padding: 6mm 4mm;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            box-sizing: border-box;
            border: 1px solid #ddd;
        }

        /* Cabecera del Ticket */
        .ticket-header {
            background-color: #333;
            color: #fff;
            padding: 6px 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .header-left {
            display: flex;
            flex-direction: column;
        }

        .header-title {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-family: Arial, sans-serif;
        }

        .header-subtitle {
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .header-logo {
            background-color: #000;
            color: #fff;
            padding: 3px 8px;
            font-family: 'Georgia', serif;
            font-style: italic;
            font-size: 14px;
            font-weight: bold;
            border-radius: 2px;
            line-height: 1;
        }

        /* Títulos de sección */
        .section-title {
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1px dashed #000;
            padding-bottom: 2px;
            margin-top: 12px;
            margin-bottom: 8px;
            font-size: 12px;
        }

        /* Estructura de campos */
        .field-row {
            display: flex;
            align-items: flex-end;
            margin-bottom: 6px;
            width: 100%;
        }

        .field-label {
            font-weight: bold;
            white-space: nowrap;
            margin-right: 4px;
            flex-shrink: 0;
        }

        .field-value {
            flex-grow: 1;
            border-bottom: 1px dotted #000;
            min-height: 14px;
            padding-bottom: 1px;
            word-break: break-word;
        }

        .field-line {
            flex-grow: 1;
            border-bottom: 1px dotted #000;
            min-height: 12px;
        }

        .flex-row {
            display: flex;
            width: 100%;
            gap: 10px;
        }

        .flex-col {
            flex: 1;
            display: flex;
            align-items: flex-end;
        }

        /* Firmas */
        .signatures-section {
            margin-top: 25px;
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .signature-block {
            text-align: center;
            width: 100%;
        }

        .signature-line {
            border-bottom: 1px solid #000;
            width: 80%;
            margin: 0 auto 4px auto;
            height: 1px;
        }

        .signature-label {
            font-size: 10px;
            text-transform: uppercase;
        }

        /* Separadores y pie de página */
        .dashed-separator {
            border-top: 1px dashed #000;
            margin: 12px 0;
            width: 100%;
        }

        /* Estilos de impresión */
        @media print {
            body {
                background-color: #fff;
                padding: 0;
                margin: 0;
                align-items: flex-start;
            }

            .print-toolbar {
                display: none !important;
            }

            .ticket-container {
                width: 80mm;
                border: none;
                box-shadow: none;
                padding: 4mm 2mm;
            }

            @page {
                size: 80mm auto;
                margin: 0;
            }
        }
    </style>
</head>

<body>

    <!-- Barra de herramientas superior (oculta al imprimir) -->
    <div class="print-toolbar">
        <button onclick="window.print()" class="btn-action btn-print">
            <i class="fas fa-print"></i> Imprimir
        </button>
        <button onclick="window.close()" class="btn-action btn-close-custom">
            <i class="fas fa-times"></i> Cerrar
        </button>
    </div>

    <!-- Contenedor del Ticket Térmico -->
    <div class="ticket-container">

        <!-- Cabecera -->
        <div class="ticket-header">
            <div class="header-left">
                <span class="header-title">
                    <?= htmlspecialchars($titulo_boleta) ?>
                </span>
                <span class="header-subtitle">BATIDOS PITAYA</span>
            </div>
            <div class="header-logo">Pitaya</div>
        </div>

        <!-- Campos Generales -->
        <div class="field-row">
            <span class="field-label">Tienda:</span>
            <span class="field-value">
                <?= htmlspecialchars($tienda) ?>
            </span>
        </div>
        <div class="field-row">
            <span class="field-label">Jefe Inmediato:</span>
            <span class="field-value">
                <?= htmlspecialchars($jefe) ?>
            </span>
        </div>
        <div class="field-row">
            <span class="field-label">Fecha:</span>
            <span class="field-value">
                <?= htmlspecialchars($fecha_emision) ?>
            </span>
        </div>

        <!-- Datos del Colaborador -->
        <div class="section-title">Datos del Colaborador</div>

        <div class="field-row">
            <span class="field-label">Nombre:</span>
            <span class="field-value">
                <?= htmlspecialchars($nombre) ?>
            </span>
        </div>
        <div class="field-row">
            <span class="field-label">Puesto:</span>
            <span class="field-value">
                <?= htmlspecialchars($puesto) ?>
            </span>
        </div>

        <!-- Rango de Fechas -->
        <div class="flex-row">
            <div class="flex-col">
                <span class="field-label">Desde:</span>
                <span class="field-value">
                    <?= htmlspecialchars($fecha_inicio_fmt) ?>
                </span>
            </div>
            <div class="flex-col">
                <span class="field-label">Hasta:</span>
                <span class="field-value">
                    <?= htmlspecialchars($fecha_fin_fmt) ?>
                </span>
            </div>
        </div>

        <div class="field-row" style="margin-top: 6px;">
            <span class="field-label">
                <?= htmlspecialchars($label_total_dias) ?>
            </span>
            <span class="field-value">
                <?= htmlspecialchars($total_dias) ?>
            </span>
        </div>

        <!-- Firmas -->
        <div class="signatures-section">
            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-label">Firma del colaborador</div>
            </div>
            <div class="signature-block" style="margin-top: 10px;">
                <div class="signature-line"></div>
                <div class="signature-label">Firma del jefe directo</div>
            </div>
        </div>

        <div class="dashed-separator"></div>

    </div>

    <script>
        // Disparar diálogo de impresión automáticamente al terminar de cargar la página
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                window.print();
            }, 500);
        });
    </script>
</body>

</html>