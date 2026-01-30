<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';

verificarAccesoModulo('atencioncliente');

$usuario = obtenerUsuarioActual();
$sucursal_id = $usuario['sucursal_id'] ?? null;

// Obtener parámetros de filtrado
$fecha = $_GET['fecha'] ?? date('Y-m-d');
$estado = $_GET['estado'] ?? '';
$sucursal_filtro = $_GET['sucursal'] ?? $sucursal_id;

// Construir consulta con filtros
$where = [];
$params = [];

if (!empty($sucursal_filtro)) {
    $where[] = "v.sucursal_id = ?";
    $params[] = $sucursal_filtro;
}

if (!empty($estado)) {
    $where[] = "v.estado = ?";
    $params[] = $estado;
}

if (!empty($fecha)) {
    $where[] = "DATE(DATE_SUB(v.fecha_hora, INTERVAL 6 HOUR)) = ?";
    $params[] = $fecha;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Obtener lista de pedidos
$query = "SELECT v.id, v.codigo, v.fecha_hora, 
          IFNULL(c.nombre, 'Sin cliente') as cliente, 
          v.monto_total, v.estado, s.nombre as sucursal
          FROM ventas v
          LEFT JOIN clientes c ON v.cliente_id = c.id
          LEFT JOIN sucursales s ON v.sucursal_id = s.id
          $where_clause
          ORDER BY v.fecha_hora DESC LIMIT 50";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pedidos = [];
    error_log("Error al obtener pedidos: " . $e->getMessage());
}

// Obtener sucursales para el filtro
try {
    $stmt = $conn->query("SELECT id, nombre FROM sucursales WHERE activa = 1");
    $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sucursales = [];
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batidos Pitaya - Ventas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="icon" type="image/png" href="../../assets/img/icon12.png">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Calibri', sans-serif;
        }

        body {
            background-color: #f5f5f5;
            color: #333;
            font-size: 14px;
        }

        .container {
            max-width: auto;
            margin: 0 auto;
            padding: 20px;
        }

        .header-ventas {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }

        .header-ventas h1 {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #0E544C;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .btn-nuevo-pedido {
            background-color: #51B8AC;
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.3s;
        }

        .btn-nuevo-pedido:hover {
            background-color: #0E544C;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .user-info small {
            color: #666;
        }

        .filtros {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .filtro-group {
            display: flex;
            flex-direction: column;
            min-width: 150px;
        }

        .filtro-group label {
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }

        .filtro-group input,
        .filtro-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .btn-filtrar {
            background-color: #51B8AC;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            align-self: flex-end;
            transition: background-color 0.3s;
        }

        .btn-filtrar:hover {
            background-color: #0E544C;
        }

        .pedidos-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
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
            font-weight: normal;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tr:hover {
            background-color: #f1f1f1;
        }

        .estado-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .estado-pendiente {
            background-color: #fff3cd;
            color: #856404;
        }

        .estado-completado {
            background-color: #d4edda;
            color: #155724;
        }

        .estado-cancelado {
            background-color: #f8d7da;
            color: #721c24;
        }

        .btn-accion {
            color: #0E544C;
            margin: 0 5px;
            font-size: 1.1rem;
            transition: color 0.3s;
        }

        .btn-accion:hover {
            color: #51B8AC;
        }

        .logo {
            height: 50px;
        }

        @media (max-width: 768px) {
            .header-ventas {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .header-actions {
                width: 100%;
                justify-content: space-between;
            }

            .filtros {
                flex-direction: column;
            }

            .btn-filtrar {
                align-self: flex-start;
            }

            th,
            td {
                padding: 8px 10px;
                font-size: 0.9rem;
            }

            table {
                display: block;
                overflow-x: auto;
            }
        }

        /* Animaciones */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .pedidos-list {
            animation: fadeIn 0.5s ease-out;
        }

        /* Notificaciones */
        .notificacion {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 15px 20px;
            background: #51B8AC;
            color: white;
            border-radius: 4px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 1000;
            animation: fadeIn 0.3s;
        }

        .notificacion.error {
            background: #dc3545;
        }

        .notificacion.success {
            background: #28a745;
        }
    </style>
</head>

<body>
    <div class="container">
        <header class="header-ventas">
            <a href="../../index.php">
                <img src="../../assets/img/Logo.svg" alt="Batidos Pitaya" class="logo">
            </a>
            <h1><i class="fas fa-cash-register"></i> Ventas</h1>
            <div class="header-actions">
                <a href="crearpedido.php" class="btn-nuevo-pedido" target="_blank">
                    <i class="fas fa-plus"></i> Nuevo Pedido
                </a>
                <div class="user-info">
                    <span><?= htmlspecialchars($usuario['nombre']) ?></span>
                    <small><?= ucfirst($usuario['rol']) ?></small>
                </div>
            </div>
        </header>

        <div class="filtros">
            <div class="filtro-group">
                <label for="fecha">Fecha:</label>
                <input type="date" id="fecha" name="fecha" value="<?= htmlspecialchars($fecha) ?>">
            </div>
            <div class="filtro-group">
                <label for="sucursal">Sucursal:</label>
                <select id="sucursal" name="sucursal">
                    <option value="">Todas</option>
                    <?php foreach ($sucursales as $sucursal): ?>
                        <option value="<?= $sucursal['id'] ?>" <?= $sucursal['id'] == $sucursal_filtro ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sucursal['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filtro-group">
                <label for="estado">Estado:</label>
                <select id="estado" name="estado">
                    <option value="">Todos</option>
                    <option value="pendiente" <?= $estado == 'pendiente' ? 'selected' : '' ?>>Enviado al Cliente</option>
                    <option value="completado" <?= $estado == 'completado' ? 'selected' : '' ?>>Completados</option>
                    <option value="cancelado" <?= $estado == 'cancelado' ? 'selected' : '' ?>>Cancelados</option>
                </select>
            </div>
            <button id="btn-filtrar" class="btn-filtrar">
                <i class="fas fa-filter"></i> Filtrar
            </button>
        </div>

        <div class="pedidos-list">
            <?php if (empty($pedidos)): ?>
                <div style="padding: 20px; text-align: center; color: #666;">
                    No se encontraron pedidos con los filtros seleccionados
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha/Hora</th>
                            <th>Sucursal</th>
                            <th>Cliente</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos as $pedido): ?>
                            <tr>
                                <td><?= htmlspecialchars($pedido['codigo']) ?></td>
                                <td>
                                    <?php
                                    $fechaHora = date('Y-m-d H:i:s', strtotime($pedido['fecha_hora'] . ' -6 hours'));
                                    ?>
                                    <div><?= formatoFecha($fechaHora) ?></div>
                                    <small><?= formatoHora($fechaHora) ?></small>
                                </td>
                                <td><?= htmlspecialchars($pedido['sucursal']) ?></td>
                                <td><?= htmlspecialchars($pedido['cliente']) ?></td>
                                <td>C$ <?= number_format($pedido['monto_total'], 0) ?></td>
                                <td>
                                    <span class="estado-badge estado-<?= $pedido['estado'] ?>">
                                        <?= $pedido['estado'] === 'pendiente' ? 'Enviado al cliente' : ucfirst($pedido['estado']) ?>
                                    </span>
                                </td>
                                <!-- Columna botones de acciones -->
                                <td>
                                    <!-- Botón para ver el pedido (siempre visible) -->
                                    <a href="verpedido.php?id=<?= $pedido['id'] ?>" class="btn-accion" title="Ver pedido"
                                        target="_blank">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (!in_array($pedido['estado'], ['completado', 'cancelado'])): ?>
                                        <a href="crearpedido.php?id=<?= $pedido['id'] ?>" class="btn-accion" title="Editar"
                                            target="_blank" style="display:none;">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a style="display:none;" href="#" class="btn-accion btn-reimprimir"
                                            data-id="<?= $pedido['id'] ?>" title="Enviar a Sucursal/Motorizado">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($pedido['estado'] == 'pendiente'): ?>
                                        <a style="display:none;" href="#" class="btn-accion btn-completar"
                                            data-id="<?= $pedido['id'] ?>" title="Marcar como completado">
                                            <i class="fas fa-check"></i>
                                        </a>
                                        <a href="#" class="btn-accion btn-cancelar" data-id="<?= $pedido['id'] ?>"
                                            title="Cancelar pedido">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        $(document).ready(function () {
            // Filtrar pedidos
            $('#btn-filtrar').click(function () {
                let fecha = $('#fecha').val();
                let sucursal = $('#sucursal').val();
                let estado = $('#estado').val();

                let params = new URLSearchParams();
                if (fecha) params.append('fecha', fecha);
                if (sucursal) params.append('sucursal', sucursal);
                if (estado) params.append('estado', estado);

                window.location.href = 'index.php?' + params.toString();
            });

            // Marcar pedido como completado
            $(document).on('click', '.btn-completar', function (e) {
                e.preventDefault();
                let pedidoId = $(this).data('id');

                if (confirm('¿Marcar este pedido como completado?')) {
                    $.ajax({
                        url: 'procesar_estado.php',
                        method: 'POST',
                        data: {
                            id: pedidoId,
                            estado: 'completado'
                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                mostrarNotificacion('Pedido marcado como completado', 'success');
                                setTimeout(() => location.reload(), 1500);
                            } else {
                                mostrarNotificacion(response.error || 'Error al actualizar', 'error');
                            }
                        },
                        error: function (xhr, status, error) {
                            mostrarNotificacion('Error en la solicitud', 'error');
                        }
                    });
                }
            });

            // Reimprimir pedido
            $(document).on('click', '.btn-reimprimir', function (e) {
                e.preventDefault();
                let pedidoId = $(this).data('id');

                if (confirm('¿Marcar este pedido como entregado e imprimir?')) {
                    $.ajax({
                        url: 'imprimir_pedido.php',
                        method: 'POST',
                        data: {
                            id: pedidoId,
                            estado: 'completado' // Cambiar estado a completado (entregado)
                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                mostrarNotificacion('Pedido marcado como entregado y enviado a impresión', 'success');
                                setTimeout(() => location.reload(), 1500);
                            } else {
                                mostrarNotificacion(response.error || 'Error al imprimir', 'error');
                            }
                        },
                        error: function (xhr, status, error) {
                            mostrarNotificacion('Error en la solicitud', 'error');
                        }
                    });
                }
            });

            // Cancelar
            $(document).on('click', '.btn-cancelar', function (e) {
                e.preventDefault();
                let pedidoId = $(this).data('id');

                if (confirm('¿Cancelar este pedido? Esta acción no se puede deshacer.')) {
                    $.ajax({
                        url: 'procesar_estado.php',
                        method: 'POST',
                        data: {
                            id: pedidoId,
                            estado: 'cancelado'
                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                mostrarNotificacion('Pedido cancelado', 'success');
                                setTimeout(() => location.reload(), 1500);
                            } else {
                                mostrarNotificacion(response.error || 'Error al cancelar', 'error');
                            }
                        },
                        error: function (xhr, status, error) {
                            mostrarNotificacion('Error en la solicitud', 'error');
                        }
                    });
                }
            });

            // Función para mostrar notificaciones
            function mostrarNotificacion(mensaje, tipo = 'info') {
                const tipos = {
                    success: { icon: 'check-circle', color: '#28a745' },
                    error: { icon: 'exclamation-circle', color: '#dc3545' },
                    info: { icon: 'info-circle', color: '#17a2b8' }
                };

                // Eliminar notificaciones anteriores
                $('.notificacion').remove();

                const notif = $(`
                    <div class="notificacion ${tipo}">
                        <i class="fas fa-${tipos[tipo].icon}"></i>
                        <span>${mensaje}</span>
                    </div>
                `);

                $('body').append(notif);

                setTimeout(() => {
                    notif.fadeOut(300, () => notif.remove());
                }, 3000);
            }

            // Función para verificar nuevos pedidos
            function verificarNuevosPedidos() {
                const ultimoId = <?= !empty($pedidos) ? $pedidos[0]['id'] : 0 ?>;

                $.ajax({
                    url: 'check_new_orders.php',
                    method: 'POST',
                    data: {
                        ultimo_id: ultimoId,
                        sucursal_id: $('#sucursal').val(),
                        estado: $('#estado').val(),
                        fecha: $('#fecha').val()
                    },
                    dataType: 'json',
                    success: function (response) {
                        if (response.nuevos > 0) {
                            mostrarNotificacion(`Hay ${response.nuevos} nuevo(s) pedido(s)`, 'info');
                            location.reload();
                        }
                    },
                    complete: function () {
                        // Verificar cada 3 segundos
                        setTimeout(verificarNuevosPedidos, 3000);
                    }
                });
            }

            // Iniciar la verificación (solo si el usuario tiene permiso)
            if (<?= $usuario['rol'] === 'admin' || $usuario['rol'] === 'supervisor' ? 'true' : 'false' ?>) {
                setTimeout(verificarNuevosPedidos, 3000);
            }
        });
    </script>
</body>

</html>