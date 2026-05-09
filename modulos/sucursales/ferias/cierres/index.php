<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

require_once '../../../../includes/auth.php';
require_once '../../../../includes/funciones.php';

// Verificar acceso
verificarAccesoModulo('sucursales');
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

if (!$esAdmin && !verificarAccesoSucursalCargo([27], [14])) {
    header('Location: ../../index.php');
    exit;
}

require_once '../db_ferias.php';

// Obtener todos los cierres ordenados por fecha más reciente
$stmt = $db_ferias->query("SELECT * FROM cierres ORDER BY fecha_hora DESC");
$cierres = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cierres - Batidos Pitaya</title>
    <link rel="icon" href="../../../../core/assets/img/icon12.png">
    <style>
        :root {
            --color-primario: #51B8AC;
            --color-secundario: #0E544C;
            --color-fondo: #F6F6F6;
            --font-size-base: clamp(12px, 2vw, 16px);
        }

        body {
            font-family: 'Calibri', Arial, sans-serif;
            background-color: var(--color-fondo);
            margin: 0;
            padding: 0;
            color: #333;
            font-size: var(--font-size-base);
        }

        h1 {
            font-size: clamp(18px, 4vw, 28px);
            margin-bottom: clamp(15px, 3vw, 25px);
        }

        .container {
            margin: 0 auto;
            padding: 20px;
        }

        header {
            background-color: white;
            padding: 15px 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            height: 45px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            color: white;
            background-color: var(--color-primario);
            transition: background-color 0.3s;
        }

        /* Agrega esto en tu sección de estilos */
        .btn-salir {
            background-color: #0E544C !important;
        }

        .btn-salir:hover {
            background-color: #0a3d37 !important;
            /* Un tono más oscuro para el hover */
        }

        .btn:hover {
            background-color: var(--color-secundario);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            font-size: var(--font-size-base);
        }

        th,
        td {
            padding: clamp(8px, 1.2vw, 12px);
            text-align: center;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: var(--color-secundario);
            color: white;
            position: sticky;
            top: 0;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            background-color: #e0e0e0;
            color: #333;
        }

        .empty-message {
            text-align: center;
            padding: 30px;
            color: #666;
            font-style: italic;
        }

        /* Ajustar botones en celdas */
        .btn {
            display: inline-block;
            padding: clamp(6px, 1vw, 8px) clamp(12px, 1.5vw, 20px);
            border-radius: 4px;
            text-decoration: none;
            color: white;
            background-color: var(--color-primario);
            transition: background-color 0.3s;
            font-size: var(--font-size-base);
        }

        /* Media query para móviles pequeños */
        @media (max-width: 480px) {

            /* Cambiar a scroll horizontal para la tabla */
            .container {
                overflow-x: auto;
                padding: 1px;
            }

            /* Ajustar el header */
            header {
                padding: 10px;
            }

            .logo {
                height: 35px;
            }

            /* Hacer que los botones sean más compactos */
            .btn {
                padding: 5px 10px;
                margin: 2px 0;
            }
        }

        @media (max-width: 768px) {

            th,
            td {
                padding: 6px 8px;
            }
        }

        /* Media query adicional para ajustes específicos */
        @media (max-width: 600px) {

            /* Ocultar columnas menos importantes si es necesario */
            td:nth-child(1),
            th:nth-child(1) {
                /* ID */
                display: none;
            }

            /* Ajustar layout de header */
            header {
                flex-direction: column;
                gap: 10px;
            }

            .btn {
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <header>
        <img src="../../../../core/assets/img/Logo.svg" alt="Batidos Pitaya" class="logo">
        <a href="../../index.php" class="btn btn-salir">Regresar a Módulo</a>
        <a href="../index.php" class="btn">Volver a Ventas</a>
    </header>

    <main class="container">
        <h1>Registro de Cierres</h1>

        <?php if (empty($cierres)): ?>
            <div class="empty-message">
                <p>No hay cierres registrados</p>
                <a href="/ventas/" class="btn">Ir a Ventas</a>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Total Ventas</th>
                        <th>Total POS</th>
                        <th>Total Efectivo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cierres as $cierre): ?>
                        <tr>
                            <td><?= $cierre['id'] ?></td>
                            <td><?= formatearFecha($cierre['fecha_hora']) ?></td>
                            <td>C$ <?= number_format($cierre['total_ventas'], 2) ?></td>
                            <td>C$ <?= number_format($cierre['total_pos'], 2) ?></td>
                            <td>C$ <?= number_format($cierre['total_efectivo'], 2) ?></td>
                            <td>
                                <a href="detalle.php?id=<?= $cierre['id'] ?>" class="btn">Detalle</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
</body>

</html>