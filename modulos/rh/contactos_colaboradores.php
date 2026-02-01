<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

// Verificar autenticación y permisos
verificarAutenticacion();

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo RH (SIEMPRE debe existir permiso 'vista')
verificarPermisoORedireccionar('contactos_colaboradores', 'vista', $cargoOperario);

// Obtener el cargo principal del usuario
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);

/**
 * Obtiene los contactos de todos los colaboradores activos
 */
function obtenerContactosColaboradores()
{
    global $conn;

    $sql = "
        SELECT 
            o.CodOperario,
            o.Nombre,
            o.Nombre2,
            o.Apellido,
            o.Apellido2,
            o.Celular,
            o.telefono_corporativo,
            -- Obtener el primer cargo encontrado (no mostrar múltiples cargos)
            COALESCE(
                (SELECT nc.Nombre 
                 FROM AsignacionNivelesCargos anc
                 JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
                 WHERE anc.CodOperario = o.CodOperario 
                 AND (anc.Fin IS NULL OR anc.Fin > CURDATE())
                 ORDER BY anc.CodNivelesCargos DESC
                 LIMIT 1),
                'Sin cargo definido'
            ) as cargo_nombre,
            -- Verificar si está activo (sin fecha_liquidacion o fecha_liquidacion futura)
            CASE 
                WHEN EXISTS (
                    SELECT 1 FROM Contratos c 
                    WHERE c.cod_operario = o.CodOperario 
                    AND (c.fecha_liquidacion IS NULL OR c.fecha_liquidacion = '0000-00-00' OR c.fecha_liquidacion > CURDATE())
                    -- AND (c.fin_contrato IS NULL OR c.fin_contrato >= CURDATE())
                ) THEN 1
                ELSE 0
            END as esta_activo
        FROM Operarios o
        WHERE o.Operativo = 1
        AND (o.Celular IS NOT NULL OR o.telefono_corporativo IS NOT NULL)
        ORDER BY o.Nombre, o.Apellido
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Formatea el nombre completo del colaborador
 */
function formatearNombreCompleto($colaborador)
{
    $partes = [];

    if (!empty($colaborador['Nombre'])) {
        $partes[] = trim($colaborador['Nombre']);
    }
    if (!empty($colaborador['Nombre2'])) {
        $partes[] = trim($colaborador['Nombre2']);
    }
    if (!empty($colaborador['Apellido'])) {
        $partes[] = trim($colaborador['Apellido']);
    }
    if (!empty($colaborador['Apellido2'])) {
        $partes[] = trim($colaborador['Apellido2']);
    }

    return implode(' ', $partes);
}

/**
 * Genera archivo CSV para exportar contactos
 */
function generarCSVContactos($contactos)
{
    $filename = 'contactos_colaboradores_' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // BOM para UTF-8
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

    // Encabezados
    fputcsv($output, [
        'Nombre Completo',
        'Cargo',
        'Teléfono Personal',
        'Teléfono Corporativo',
        'Estado'
    ], ';');

    // Datos
    foreach ($contactos as $contacto) {
        $nombreCompleto = formatearNombreCompleto($contacto);
        $estado = $contacto['esta_activo'] ? 'Activo' : 'Inactivo';

        fputcsv($output, [
            $nombreCompleto,
            $contacto['cargo_nombre'],
            $contacto['Celular'] ?? '',
            $contacto['telefono_corporativo'] ?? '',
            $estado
        ], ';');
    }

    fclose($output);
    exit();
}

/**
 * Genera archivo VCF (vCard) para importar en móviles
 */
function generarVCFContactos($contactos)
{
    $filename = 'contactos_colaboradores_' . date('Y-m-d') . '.vcf';

    header('Content-Type: text/vcard; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    foreach ($contactos as $contacto) {
        if (!$contacto['esta_activo'])
            continue; // Solo activos para VCF

        $nombreCompleto = formatearNombreCompleto($contacto);

        echo "BEGIN:VCARD\r\n";
        echo "VERSION:3.0\r\n";
        echo "FN:" . $nombreCompleto . "\r\n";
        echo "N:" . ($contacto['Apellido'] ?? '') . ";" . ($contacto['Nombre'] ?? '') . ";;;\r\n";

        if (!empty($contacto['Celular'])) {
            echo "TEL;TYPE=CELL:" . limpiarTelefono($contacto['Celular']) . "\r\n";
        }

        if (!empty($contacto['telefono_corporativo'])) {
            echo "TEL;TYPE=WORK:" . limpiarTelefono($contacto['telefono_corporativo']) . "\r\n";
        }

        if (!empty($contacto['cargo_nombre']) && $contacto['cargo_nombre'] != 'Sin cargo definido') {
            echo "TITLE:" . $contacto['cargo_nombre'] . "\r\n";
        }

        echo "ORG:Batidos Pitaya\r\n";
        echo "END:VCARD\r\n";
    }

    exit();
}

/**
 * Limpia formato de teléfono para VCF
 */
function limpiarTelefono($telefono)
{
    // Remover espacios, guiones, paréntesis
    $limpio = preg_replace('/[^0-9+]/', '', $telefono);

    // Si no empieza con +, asumir que es número nicaragüense
    if (substr($limpio, 0, 1) !== '+' && substr($limpio, 0, 1) !== '0') {
        $limpio = '+505' . $limpio;
    }

    return $limpio;
}

// Procesar exportación
if (isset($_GET['exportar'])) {
    $contactos = obtenerContactosColaboradores();

    switch ($_GET['exportar']) {
        case 'csv':
            generarCSVContactos($contactos);
            break;
        case 'vcf':
            generarVCFContactos($contactos);
            break;
    }
}

// Obtener contactos para mostrar en la tabla
$contactos = obtenerContactosColaboradores();

// Filtrar por estado si se solicita
$filtroEstado = $_GET['estado'] ?? 'activos';
if ($filtroEstado === 'activos') {
    $contactos = array_filter($contactos, function ($c) {
        return $c['esta_activo']; });
} elseif ($filtroEstado === 'inactivos') {
    $contactos = array_filter($contactos, function ($c) {
        return !$c['esta_activo']; });
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contactos de Colaboradores</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <style>
        .container-contactos {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 10px;
        }

        .title {
            color: #0E544C;
            font-size: 1.5rem;
            margin-bottom: 20px;
            text-align: center;
        }

        /* Controles de filtro y exportación */
        .controles {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .filtros {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .exportacion {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-exportar {
            background-color: #0E544C;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background 0.3s;
        }

        .btn-exportar:hover {
            background-color: #51B8AC;
        }

        .btn-filtro {
            background-color: #f8f9fa;
            color: #333;
            border: 1px solid #dee2e6;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-filtro.activo {
            background-color: #0E544C;
            color: white;
            border-color: #0E544C;
        }

        .btn-filtro:hover {
            background-color: #e9ecef;
        }

        /* Estilos para la tabla */
        .tabla-container {
            overflow-x: auto;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        th,
        td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #0E544C;
            color: white;
            font-weight: bold;
            position: sticky;
            top: 0;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        .estado-activo {
            color: #28a745;
            font-weight: bold;
        }

        .estado-inactivo {
            color: #dc3545;
            font-weight: bold;
        }

        .sin-telefono {
            color: #6c757d;
            font-style: italic;
        }

        .badge-cargo {
            display: inline-block;
            padding: 3px 8px;
            background-color: #e9ecef;
            color: #495057;
            border-radius: 12px;
            font-size: 0.8em;
            white-space: nowrap;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .controles {
                flex-direction: column;
                align-items: stretch;
            }

            .filtros,
            .exportacion {
                justify-content: center;
            }

            th,
            td {
                padding: 8px 10px;
            }
        }

        .resumen {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #0E544C;
        }

        .resumen h3 {
            color: #0E544C;
            margin-bottom: 10px;
        }

        .estadisticas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }

        .tarjeta-estadistica {
            background: white;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .tarjeta-estadistica .numero {
            font-size: 2rem;
            font-weight: bold;
            color: #0E544C;
            display: block;
        }

        .tarjeta-estadistica .etiqueta {
            color: #6c757d;
            font-size: 0.9em;
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Contactos de Colaboradores'); ?>

            <div class="container-fluid p-3">
                <div class="container-contactos">

        <!-- Información sobre exportación -->
        <div style="background: #e8f5e8; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;">
            <h4 style="color: #155724; margin-bottom: 10px;">
                <i class="fas fa-info-circle"></i> Información sobre Exportación
            </h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                <div>
                    <strong>CSV:</strong> Formato de hoja de cálculo compatible con Excel, Google Sheets, etc.
                </div>
                <div>
                    <strong>VCF (vCard):</strong> Formato para importar contactos directamente en aplicaciones de móvil.
                </div>
            </div>
            <p style="margin-top: 10px; color: #2d5016; font-size: 0.9em;">
                <i class="fas fa-lightbulb"></i> <strong>Tip:</strong> Para importar en tu móvil, descarga el archivo
                VCF y ábrelo con tu aplicación de contactos.
            </p>
        </div>

        <!-- Resumen de contactos -->
        <div class="resumen">
            <h3><i class="fas fa-info-circle"></i> Resumen de Contactos</h3>
            <div class="estadisticas">
                <div class="tarjeta-estadistica">
                    <span class="numero"><?= count($contactos) ?></span>
                    <span class="etiqueta">Total Colaboradores</span>
                </div>
                <div class="tarjeta-estadistica">
                    <span class="numero">
                        <?= count(array_filter($contactos, function ($c) {
                            return !empty($c['Celular']);
                        })) ?>
                    </span>
                    <span class="etiqueta">Con Teléfono Personal</span>
                </div>
                <div class="tarjeta-estadistica">
                    <span class="numero">
                        <?= count(array_filter($contactos, function ($c) {
                            return !empty($c['telefono_corporativo']);
                        })) ?>
                    </span>
                    <span class="etiqueta">Con Teléfono Corporativo</span>
                </div>
                <div class="tarjeta-estadistica">
                    <span class="numero">
                        <?= count(array_filter($contactos, function ($c) {
                            return !empty($c['Celular']) && !empty($c['telefono_corporativo']);
                        })) ?>
                    </span>
                    <span class="etiqueta">Con Ambos Teléfonos</span>
                </div>
            </div>
        </div>

        <!-- Controles de filtro y exportación -->
        <div class="controles">
            <div class="filtros">
                <strong>Filtrar por estado:</strong>
                <a href="?estado=activos" class="btn-filtro <?= $filtroEstado === 'activos' ? 'activo' : '' ?>">
                    <i class="fas fa-user-check"></i> Activos
                </a>
                <a href="?estado=inactivos" class="btn-filtro <?= $filtroEstado === 'inactivos' ? 'activo' : '' ?>">
                    <i class="fas fa-user-times"></i> Inactivos
                </a>
                <a href="?" class="btn-filtro <?= !isset($_GET['estado']) ? 'activo' : '' ?>">
                    <i class="fas fa-users"></i> Todos
                </a>
            </div>

            <div class="exportacion">
                <a href="?exportar=csv" class="btn-exportar">
                    <i class="fas fa-file-csv"></i> Exportar CSV
                </a>
                <a href="?exportar=vcf" class="btn-exportar">
                    <i class="fas fa-address-card"></i> Exportar VCF
                </a>
            </div>
        </div>

        <!-- Tabla de contactos -->
        <div class="tabla-container">
            <table id="listaContactos">
                <thead>
                    <tr>
                        <th>Nombre Completo</th>
                        <th>Cargo</th>
                        <th>Teléfono Personal</th>
                        <th>Teléfono Corporativo</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($contactos) > 0): ?>
                        <?php foreach ($contactos as $contacto): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars(formatearNombreCompleto($contacto)) ?></strong>
                                    <?php if (!$contacto['esta_activo']): ?>
                                        <br><small style="color: #6c757d;">Código: <?= $contacto['CodOperario'] ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-cargo"><?= htmlspecialchars($contacto['cargo_nombre']) ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($contacto['Celular'])): ?>
                                        <a href="tel:<?= htmlspecialchars($contacto['Celular']) ?>"
                                            style="color: #0E544C; text-decoration: none;">
                                            <i class="fas fa-phone"></i> <?= htmlspecialchars($contacto['Celular']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="sin-telefono">No registrado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($contacto['telefono_corporativo'])): ?>
                                        <a href="tel:<?= htmlspecialchars($contacto['telefono_corporativo']) ?>"
                                            style="color: #0E544C; text-decoration: none;">
                                            <i class="fas fa-phone"></i> <?= htmlspecialchars($contacto['telefono_corporativo']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="sin-telefono">No registrado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($contacto['esta_activo']): ?>
                                        <span class="estado-activo">
                                            <i class="fas fa-check-circle"></i> Activo
                                        </span>
                                    <?php else: ?>
                                        <span class="estado-inactivo">
                                            <i class="fas fa-times-circle"></i> Inactivo
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: #6c757d;">
                                <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 15px; display: block;"></i>
                                <h3>No se encontraron contactos</h3>
                                <p>No hay colaboradores que coincidan con el filtro seleccionado.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!--
            Aplicar en function obtenerContactosColaboradores() esto para excluir los cargos 27 de sucursales
            $sql = "
                SELECT 
                    o.CodOperario,
                    o.Nombre,
                    o.Nombre2,
                    o.Apellido,
                    o.Apellido2,
                    o.Celular,
                    o.telefono_corporativo,
                    -- Obtener el primer cargo encontrado (no mostrar múltiples cargos)
                    COALESCE(
                        (SELECT nc.Nombre 
                         FROM AsignacionNivelesCargos anc
                         JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
                         WHERE anc.CodOperario = o.CodOperario 
                         AND anc.CodNivelesCargos != 27  -- EXCLUIR CARGO 27
                         AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                         ORDER BY anc.CodNivelesCargos DESC
                         LIMIT 1),
                        'Sin cargo definido'
                    ) as cargo_nombre,
                    -- Verificar si está activo (sin fecha_liquidacion o fecha_liquidacion futura)
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 FROM Contratos c 
                            WHERE c.cod_operario = o.CodOperario 
                            AND (c.fecha_liquidacion IS NULL OR c.fecha_liquidacion = '0000-00-00' OR c.fecha_liquidacion > CURDATE())
                            AND (c.fin_contrato IS NULL OR c.fin_contrato >= CURDATE())
                        ) THEN 1
                        ELSE 0
                    END as esta_activo
                FROM Operarios o
                WHERE o.Operativo = 1
                AND (o.Celular IS NOT NULL OR o.telefono_corporativo IS NOT NULL)
                -- EXCLUIR OPERARIOS CON CARGO 27 ACTIVO
                AND o.CodOperario NOT IN (
                    SELECT DISTINCT anc.CodOperario 
                    FROM AsignacionNivelesCargos anc
                    WHERE anc.CodNivelesCargos = 27
                    AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                )
                ORDER BY o.Nombre, o.Apellido
            ";
        -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para copiar número de teléfono al portapapeles
        function copiarTelefono(numero) {
            navigator.clipboard.writeText(numero).then(function () {
                // Mostrar notificación de éxito
                mostrarNotificacion('Número copiado: ' + numero);
            }).catch(function (err) {
                console.error('Error al copiar: ', err);
            });
        }

        // Función para mostrar notificaciones temporales
        function mostrarNotificacion(mensaje) {
            // Crear elemento de notificación
            const notificacion = document.createElement('div');
            notificacion.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #28a745;
                color: white;
                padding: 15px 20px;
                border-radius: 5px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000;
                font-size: 14px;
                animation: slideIn 0.3s ease-out;
            `;
            notificacion.textContent = mensaje;

            document.body.appendChild(notificacion);

            // Remover después de 3 segundos
            setTimeout(() => {
                notificacion.style.animation = 'slideOut 0.3s ease-in';
                setTimeout(() => {
                    if (notificacion.parentNode) {
                        notificacion.parentNode.removeChild(notificacion);
                    }
                }, 300);
            }, 3000);
        }

        // Agregar estilos para animaciones
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            
            /* Mejorar la interactividad de los números de teléfono */
            .numero-telefono {
                cursor: pointer;
                transition: color 0.3s;
            }
            .numero-telefono:hover {
                color: #0E544C !important;
            }
        `;
        document.head.appendChild(style);

        // Agregar event listeners para los números de teléfono
        document.addEventListener('DOMContentLoaded', function () {
            // Agregar clase a los números de teléfono
            document.querySelectorAll('a[href^="tel:"]').forEach(link => {
                link.classList.add('numero-telefono');

                // Agregar tooltip para copiar
                link.title = 'Haz clic para llamar. Mantén presionado para copiar.';

                // Agregar evento de click largo para copiar
                let pressTimer;

                link.addEventListener('mousedown', function (e) {
                    pressTimer = window.setTimeout(function () {
                        const numero = link.textContent.trim().replace('📞 ', '');
                        copiarTelefono(numero);
                    }, 500);
                });

                link.addEventListener('mouseup', function () {
                    clearTimeout(pressTimer);
                });

                link.addEventListener('mouseleave', function () {
                    clearTimeout(pressTimer);
                });

                // Para dispositivos táctiles
                link.addEventListener('touchstart', function (e) {
                    pressTimer = window.setTimeout(function () {
                        const numero = link.textContent.trim().replace('📞 ', '');
                        copiarTelefono(numero);
                        e.preventDefault();
                    }, 500);
                });

                link.addEventListener('touchend', function () {
                    clearTimeout(pressTimer);
                });
            });
        });

        $(document).ready(function () {
            $('#listaContactos').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
                },
                dom: '<"top"l>rt<"bottom"ip>', // Quitamos la "f" en "top"lf (filter/search)
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                pageLength: 25,

                // CONFIGURACIÓN PARA 3 CLICKS
                order: [], // Sin orden inicial - respeta el orden de la consulta SQL
                ordering: true, // Habilitar ordenamiento
                orderMulti: true, // Permitir ordenamiento múltiple con Ctrl+click

                // Configuración específica para el ciclo de 3 clicks
                columnDefs: [{
                    orderable: true, // Todas las columnas son ordenables
                    targets: '_all' // Aplicar a todas las columnas
                }]
            });
        });
    </script>
</body>
</html>
