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

// Solo líderes/aprobadores pueden imprimir fichas de feriado
if (!tienePermiso('feriados_v2', 'aprobar', $cargoOperario)) {
    header('Location: ../index.php');
    exit();
}

// Obtener el ID del registro de feriado
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die("ID de registro inválido.");
}

// Consultar el registro completo del feriado
$sql = "
    SELECT fs.id, fs.fecha_feriado, fs.horas_trabajadas, fs.estado, fs.observaciones,
           fs.fecha_creacion, fs.fecha_actualizacion,
           o.CodOperario,
           c.CodContrato,
           CONCAT_WS(' ',
               TRIM(o.Nombre),
               NULLIF(TRIM(o.Nombre2), ''),
               TRIM(o.Apellido),
               NULLIF(TRIM(o.Apellido2), '')
           ) as nombre_completo,
           COALESCE(s.nombre, s_actual.nombre, 'Sin sucursal') as sucursal_nombre,
           CONCAT_WS(' ', TRIM(creado.Nombre), TRIM(creado.Apellido)) as creador_nombre,
           CONCAT_WS(' ', TRIM(act.Nombre), TRIM(act.Apellido)) as actualizador_nombre,
           nc.Nombre as cargo_nombre,
           GROUP_CONCAT(DISTINCT fn.nombre SEPARATOR ' / ') as feriado_nombre
    FROM FeriadosStatus fs
    INNER JOIN Operarios o ON fs.cod_operario = o.CodOperario
    LEFT JOIN Contratos c ON fs.cod_contrato = c.CodContrato
    LEFT JOIN sucursales s ON c.cod_sucursal_contrato = s.codigo
    LEFT JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
        AND fs.fecha_feriado >= anc.Fecha
        AND (anc.Fin IS NULL OR anc.Fin = '0000-00-00' OR fs.fecha_feriado <= anc.Fin)
    LEFT JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
    LEFT JOIN sucursales s_actual ON anc.Sucursal = s_actual.codigo
    LEFT JOIN Operarios creado ON fs.creado_por = creado.CodOperario
    LEFT JOIN Operarios act ON fs.actualizado_por = act.CodOperario
    LEFT JOIN feriadosnic fn ON fs.fecha_feriado = fn.fecha
        AND (fn.departamento_codigo IS NULL OR fn.departamento_codigo = COALESCE(s.cod_departamento, s_actual.cod_departamento))
    WHERE fs.id = ?
    GROUP BY fs.id
";
$stmt = $conn->prepare($sql);
$stmt->execute([$id]);
$reg = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reg) {
    die("Registro de feriado no encontrado.");
}

// Formatear fecha
function fmtFecha($fecha)
{
    if (empty($fecha) || $fecha === '0000-00-00')
        return '-';
    try {
        $d = new DateTime($fecha);
        // Mapear mes en español
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        return $d->format('d') . ' de ' . $meses[(int) $d->format('n') - 1] . ' de ' . $d->format('Y');
    } catch (Exception $e) {
        return $fecha;
    }
}

function fmtFechaCorta($fecha)
{
    if (empty($fecha) || $fecha === '0000-00-00')
        return '-';
    try {
        $d = new DateTime($fecha);
        return $d->format('d/m/Y');
    } catch (Exception $e) {
        return $fecha;
    }
}

$fechaFeriado = fmtFecha($reg['fecha_feriado']);
$fechaFeriadoCorta = fmtFechaCorta($reg['fecha_feriado']);
$fechaCreacion = $reg['fecha_creacion'] ? fmtFechaCorta(substr($reg['fecha_creacion'], 0, 10)) : '-';
$fechaEmision = fmtFechaCorta(date('Y-m-d'));

$estadoLabel = match ($reg['estado']) {
    'Pagado' => 'PAGADO',
    'Descansado' => 'COMPENSADO (DESCANSO)',
    'Pendiente' => 'PENDIENTE',
    default => strtoupper($reg['estado'] ?? '-')
};

$feriadoNombre = $reg['feriado_nombre'] ?? 'Feriado no registrado';
$horas = number_format($reg['horas_trabajadas'] ?? 0, 2);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha de Feriado - Batidos Pitaya</title>
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
            background-color: #0E544C;
            color: #fff;
            padding: 8px 8px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            margin-bottom: 12px;
            text-align: center;
            gap: 3px;
        }

        .header-title {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-family: Arial, sans-serif;
            opacity: 0.85;
        }

        .header-subtitle {
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 0.5px;
            font-family: Arial, sans-serif;
        }

        .header-doctype {
            font-size: 9px;
            font-family: Arial, sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            padding: 2px 6px;
            border-radius: 2px;
            margin-top: 2px;
        }

        /* Títulos de sección */
        .section-title {
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1px dashed #000;
            padding-bottom: 2px;
            margin-top: 12px;
            margin-bottom: 8px;
            font-size: 11px;
            font-family: Arial, sans-serif;
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
            font-size: 11px;
        }

        .field-value {
            flex-grow: 1;
            border-bottom: 1px dotted #000;
            min-height: 14px;
            padding-bottom: 1px;
            word-break: break-word;
            font-size: 11px;
        }

        .field-value.bold {
            font-weight: bold;
        }

        .status-badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 2px;
            font-size: 9px;
            font-weight: bold;
            font-family: Arial, sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pendiente {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
        }

        .status-pagado {
            background: #d1e7dd;
            color: #0a3622;
            border: 1px solid #198754;
        }

        .status-compensado {
            background: #cff4fc;
            color: #055160;
            border: 1px solid #0dcaf0;
        }

        /* Firmas */
        .signatures-section {
            margin-top: 20px;
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
            font-family: Arial, sans-serif;
        }

        /* Separadores y pie de página */
        .dashed-separator {
            border-top: 1px dashed #000;
            margin: 12px 0;
            width: 100%;
        }

        .ticket-footer {
            text-align: center;
            font-size: 9px;
            color: #555;
            font-family: Arial, sans-serif;
            margin-top: 4px;
        }

        .id-badge {
            font-size: 8px;
            font-family: Arial, sans-serif;
            color: #888;
            text-align: right;
            margin-bottom: 4px;
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
            <span class="header-subtitle">BATIDOS PITAYA</span>
            <span class="header-doctype">Ficha de Feriado Trabajado</span>
        </div>

        <!-- ID del registro -->
        <div class="id-badge">Ref. #<?= htmlspecialchars($reg['id']) ?> &nbsp;|&nbsp; Emitido: <?= $fechaEmision ?>
        </div>

        <!-- Datos Generales -->
        <div class="field-row">
            <span class="field-label">Tienda:</span>
            <span class="field-value bold"><?= htmlspecialchars($reg['sucursal_nombre']) ?></span>
        </div>
        <div class="field-row">
            <span class="field-label">Registrado por:</span>
            <span class="field-value"><?= htmlspecialchars($reg['creador_nombre'] ?? 'Sistema') ?></span>
        </div>
        <div class="field-row">
            <span class="field-label">Fecha registro:</span>
            <span class="field-value"><?= $fechaCreacion ?></span>
        </div>

        <!-- Datos del Colaborador -->
        <div class="section-title">Datos del Colaborador</div>

        <div class="field-row">
            <span class="field-label">Nombre:</span>
            <span class="field-value bold"><?= htmlspecialchars($reg['nombre_completo']) ?></span>
        </div>
        <?php if (!empty($reg['cargo_nombre'])): ?>
            <div class="field-row">
                <span class="field-label">Puesto:</span>
                <span class="field-value"><?= htmlspecialchars($reg['cargo_nombre']) ?></span>
            </div>
        <?php endif; ?>


        <!-- Detalles del Feriado -->
        <div class="section-title">Detalle del Feriado</div>

        <div class="field-row">
            <span class="field-label">Feriado:</span>
            <span class="field-value bold"><?= htmlspecialchars($feriadoNombre) ?></span>
        </div>
        <div class="field-row">
            <span class="field-label">Fecha:</span>
            <span class="field-value"><?= $fechaFeriado ?></span>
        </div>

        <div class="field-row" style="margin-top: 6px;">
            <span class="field-label">Estado:</span>
            <span class="field-value">
                <?php
                $badgeClass = match ($reg['estado']) {
                    'Pagado' => 'status-pagado',
                    'Descansado' => 'status-compensado',
                    default => 'status-pendiente'
                };
                ?>
                <span class="status-badge <?= $badgeClass ?>"><?= $estadoLabel ?></span>
            </span>
        </div>

        <?php if (!empty($reg['observaciones'])): ?>
            <div class="section-title" style="margin-top: 10px;">Observaciones</div>
            <div style="font-size: 11px; padding: 2px 0; word-break: break-word;">
                <?= htmlspecialchars($reg['observaciones']) ?>
            </div>
        <?php endif; ?>

        <div class="dashed-separator"></div>

        <!-- Firmas -->
        <div class="signatures-section">
            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-label">Firma del colaborador</div>
            </div>
            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-label">Firma del jefe directo</div>
            </div>
        </div>

        <div class="dashed-separator"></div>

        <div class="ticket-footer">
            Sistema ERP · Batidos Pitaya S.A.<br>
            <?= date('d/m/Y H:i') ?>
        </div>

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