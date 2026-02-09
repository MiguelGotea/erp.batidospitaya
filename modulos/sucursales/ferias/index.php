<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

require_once '../../../includes/auth.php';
require_once '../../../includes/funciones.php';

// Verificar acceso al módulo (cargo 27 y sucursal 14)
verificarAccesoModulo('sucursales');
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

if (!$esAdmin && !verificarAccesoSucursalCargo([27], [14])) {
    header('Location: ../index.php');
    exit;
}

// Conexión a la base de datos de ferias
require_once 'db_ferias.php';
$productos = obtenerProductos();
$ventaActiva = obtenerVentaActiva();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas - Batidos Pitaya</title>
    <link rel="icon" href="/assets/img/icon12.png">
    <style>
        /* Estilos generales */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Calibri', Arial, sans-serif;
        }
        
        body {
            background-color: #F6F6F6;
            min-height: 100vh;
        }
        
        /* Header */
        .header-ventas {
            background-color: white;
            padding: 10px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .logo-ventas {
            height: 40px;
        }
        
        /* Botones */
        .btn {
            background-color: #51B8AC;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background-color: #3fa89c;
        }
        
        .btn-especial {
            background-color: #0E544C;
        }
        
        .btn-especial:hover {
            background-color: #0c473f;
        }
        
        .btn-pago {
            background-color: #e0e0e0;
            color: #333;
            padding: 8px 15px;
            margin-right: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-pago.active {
            background-color: #51B8AC;
            color: white;
        }
        
        .btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        
        /* Contenedor principal */
        .container-ventas {
            max-width: 1200px;
            margin: 0 auto;
            padding: 15px;
        }
        
        /* Sección de productos */
        .productos-section {
            background: white;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .productos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
        }
        
        .producto-card {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 15px 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid #eee;
        }
        
        .producto-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-color: #51B8AC;
        }
        
        .producto-card h3 {
            font-size: 16px;
            margin-bottom: 5px;
            color: #333;
        }
        
        .producto-card p {
            color: #0E544C;
            font-weight: bold;
            font-size: 15px;
        }
        
        /* Sección del pedido */
        .pedido-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .pedido-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .pedido-header h2 {
            font-size: 18px;
            color: #0E544C;
        }
        
        /* Tabla de pedido */
        .tabla-pedido {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: clamp(11px, 2vw, 16px); /* Añade esta línea */
        }
        
        .tabla-pedido th {
            text-align: left;
            padding: 10px 5px;
            border-bottom: 2px solid #ddd;
            font-size: 14px;
            color: #666;
        }
        
        .tabla-pedido td {
            padding: 12px 5px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        .tabla-pedido th,
        .tabla-pedido td {
            font-size: clamp(11px, 2vw, 14px); /* Puedes ajustar los valores */
            padding: clamp(5px, 1.5vw, 12px) clamp(3px, 1vw, 5px); /* Padding responsive */
        }
        
        .cantidad-control {
            display: flex;
            align-items: center;
        }
        
        .btn-cantidad {
            background: #f0f0f0;
            border: none;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.2s;
        }
        
        .btn-cantidad:hover {
            background: #e0e0e0;
        }
        
        .cantidad-value {
            margin: 0 10px;
            min-width: 20px;
            text-align: center;
        }
        
        .input-notas {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Calibri', Arial, sans-serif;
        }
        
        .btn-eliminar {
            background: none;
            border: none;
            color: #ff5252;
            font-size: 20px;
            cursor: pointer;
            padding: 0 5px;
            line-height: 1;
        }
        
        /* Sección total */
        .total-section {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid #ddd;
        }
        
        .total-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .total-line h3 {
            font-size: 20px;
            color: #0E544C;
        }
        
        .metodos-pago {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .pago-options {
            display: flex;
        }
        
        /* Mensaje cuando no hay productos */
        .empty-message {
            text-align: center;
            padding: 30px;
            color: #888;
            font-style: italic;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .productos-grid {
                grid-template-columns: repeat(3, 1fr); /* 3 columnas fijas */
                gap: 10px; /* Reducir el espacio entre elementos */
            }
            
            .metodos-pago {
                flex-direction: column;
                gap: 10px;
            }
            
            .pago-options {
                width: 100%;
                justify-content: space-between;
            }
            
            #imprimirBtn {
                width: 100%;
            }
            
            /* Nuevos estilos para la tabla */
            .tabla-pedido {
                font-size: clamp(11px, 2vw, 14px);
            }
            
            .tabla-pedido th,
            .tabla-pedido td {
                padding: 8px 3px;
            }
            
            .btn-cantidad {
                width: 20px;
                height: 20px;
                font-size: 12px;
            }
            
            .input-notas {
                padding: 5px;
            }
            
            .header-ventas {
                padding: 10px 8px; /* Reducir padding del header */
            }
            
            .btn-header {
                padding: 6px 10px; /* Padding más ajustado para móviles */
            }
            
            .logo-ventas {
                height: 30px; /* Logo un poco más pequeño */
            }
        }
        
        /* Estilos para los botones del header */
        .btn-header {
            font-size: clamp(12px, 2.5vw, 16px); /* Tamaño responsive */
            padding: clamp(8px, 2vw, 10px) clamp(12px, 3vw, 20px); /* Padding responsive */
            white-space: nowrap; /* Evita que el texto se divida en dos líneas */
        }
        
        /* Agrega esto en tu sección de estilos */
    .btn-salir {
        background-color: #0E544C !important;
    }
    
    .btn-salir:hover {
        background-color: #0a3d37 !important; /* Un tono más oscuro para el hover */
    }
    
    @media (max-width: 480px) {
        .productos-grid {
            gap: 5px; /* Espacio aún más reducido */
        }
        
        .producto-card {
            padding: 10px 5px; /* Menos padding en las cards */
        }
        
        .producto-card h3 {
            font-size: 14px; /* Texto un poco más pequeño */
        }
        
        .producto-card p {
            font-size: 13px; /* Precio un poco más pequeño */
        }
        
        .btn-header {
            font-size: 12px; /* Tamaño mínimo */
            padding: 5px 8px; /* Padding mínimo */
        }
    }
    </style>
</head>
<body>
    <header class="header-ventas">
        <img src="../../../assets/img/Logo.svg" alt="Batidos Pitaya" class="logo-ventas">
        <a href="../index.php" class="btn btn-salir btn-header">Regresar a Módulo</a>
        <button id="cerrarEvento" class="btn btn-especial btn-header">CERRAR EVENTO</button>
    </header>
    
    <main class="container-ventas">
        <section>
            <h2>Productos Disponibles</h2>
            <div class="productos-grid">
                <?php foreach ($productos as $producto): ?>
                    <div class="producto-card" data-id="<?= $producto['id'] ?>" data-precio="<?= $producto['precio'] ?>">
                        <h3><?= htmlspecialchars($producto['nombre']) ?></h3>
                        <p>C$ <?= number_format($producto['precio'], 2) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        
        <section class="pedido-section">
            <div class="pedido-header">
                <h2>PEDIDO #<?= $ventaActiva ? $ventaActiva['id'] : 'Nuevo' ?></h2>
            </div>
            
            <!-- En la sección pedido-section, justo después del pedido-header -->
            <div class="cliente-section" style="margin-bottom: 15px;">
                <label for="nombreCliente" style="display: block; margin-bottom: 5px; color: #666;">Nombre del Cliente</label>
                <input type="text" id="nombreCliente" class="input-notas" placeholder="Ingrese nombre del cliente">
            </div>
            
            <table class="tabla-pedido">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Notas</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tablaPedidoBody">
                    <!-- Mensaje cuando no hay productos -->
                    <tr id="emptyRow" class="empty-message">
                        <td colspan="4">No hay productos en el pedido</td>
                    </tr>
                </tbody>
            </table>
            
            <div class="total-section">
                <div class="total-line">
                    <h3>TOTAL:</h3>
                    <h3>C$ <span id="totalPedido">0.00</span></h3>
                </div>
                
                <div class="metodos-pago">
                    <div class="pago-options">
                        <button class="btn btn-pago" data-tipo="POS">POS</button>
                        <button class="btn btn-pago" data-tipo="Efectivo">Efectivo</button>
                    </div>
                    <button id="imprimirBtn" class="btn btn-especial" disabled>IMPRIMIR</button>
                </div>
            </div>
        </section>
    </main>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Variables globales
            let productosSeleccionados = [];
            let tipoPagoSeleccionado = null;
            let ventaId = <?= $ventaActiva ? $ventaActiva['id'] : 'null' ?>;
            
            // Elementos del DOM
            const tablaPedidoBody = document.getElementById('tablaPedidoBody');
            const emptyRow = document.getElementById('emptyRow');
            const totalPedido = document.getElementById('totalPedido');
            const btnImprimir = document.getElementById('imprimirBtn');
            const btnCerrarEvento = document.getElementById('cerrarEvento');
            const btnPagos = document.querySelectorAll('.btn-pago');
            
            // Función para actualizar el estado del botón imprimir
            function actualizarEstadoBotonImprimir() {
                const puedeImprimir = productosSeleccionados.length > 0 && tipoPagoSeleccionado !== null;
                btnImprimir.disabled = !puedeImprimir;
            }
            
            // Eventos para productos
            document.querySelectorAll('.producto-card').forEach(card => {
                card.addEventListener('click', function() {
                    const productoId = parseInt(this.dataset.id);
                    const productoNombre = this.querySelector('h3').textContent;
                    const productoPrecio = parseFloat(this.dataset.precio);
                    
                    // Verificar si el producto ya está en el pedido
                    const productoExistente = productosSeleccionados.find(p => p.id === productoId);
                    
                    if (productoExistente) {
                        productoExistente.cantidad += 1;
                    } else {
                        productosSeleccionados.push({
                            id: productoId,
                            nombre: productoNombre,
                            precio: productoPrecio,
                            cantidad: 1,
                            notas: ''
                        });
                    }
                    
                    actualizarTablaPedido();
                });
            });
            
            // Función para actualizar la tabla del pedido
            function actualizarTablaPedido() {
                tablaPedidoBody.innerHTML = '';
                let total = 0;
                
                if (productosSeleccionados.length === 0) {
                    tablaPedidoBody.appendChild(emptyRow);
                } else {
                    productosSeleccionados.forEach((producto, index) => {
                        const subtotal = producto.precio * producto.cantidad;
                        total += subtotal;
                        
                        const row = tablaPedidoBody.insertRow();
                        
                        // Celda de nombre
                        row.insertCell(0).textContent = producto.nombre;
                        
                        // Celda de cantidad
                        const cellCantidad = row.insertCell(1);
                        const divCantidad = document.createElement('div');
                        divCantidad.className = 'cantidad-control';
                        divCantidad.innerHTML = `
                            <button class="btn-cantidad" data-index="${index}" data-accion="restar">-</button>
                            <span class="cantidad-value">${producto.cantidad}</span>
                            <button class="btn-cantidad" data-index="${index}" data-accion="sumar">+</button>
                        `;
                        cellCantidad.appendChild(divCantidad);
                        
                        // Celda de notas
                        const cellNotas = row.insertCell(2);
                        const inputNotas = document.createElement('input');
                        inputNotas.type = 'text';
                        inputNotas.className = 'input-notas';
                        inputNotas.value = producto.notas;
                        inputNotas.placeholder = 'Notas...';
                        inputNotas.addEventListener('change', function() {
                            productosSeleccionados[index].notas = this.value;
                        });
                        cellNotas.appendChild(inputNotas);
                        
                        // Celda de acciones
                        const cellAcciones = row.insertCell(3);
                        const btnEliminar = document.createElement('button');
                        btnEliminar.className = 'btn-eliminar';
                        btnEliminar.innerHTML = '&times;';
                        btnEliminar.addEventListener('click', function() {
                            productosSeleccionados.splice(index, 1);
                            actualizarTablaPedido();
                        });
                        cellAcciones.appendChild(btnEliminar);
                    });
                }
                
                totalPedido.textContent = total.toFixed(2);
                actualizarEstadoBotonImprimir();
            }
            
            // Eventos para botones de cantidad
            tablaPedidoBody.addEventListener('click', function(e) {
                if (e.target.classList.contains('btn-cantidad')) {
                    const index = parseInt(e.target.dataset.index);
                    const accion = e.target.dataset.accion;
                    
                    if (accion === 'sumar') {
                        productosSeleccionados[index].cantidad += 1;
                    } else if (accion === 'restar' && productosSeleccionados[index].cantidad > 1) {
                        productosSeleccionados[index].cantidad -= 1;
                    }
                    
                    actualizarTablaPedido();
                }
            });
            
            // Eventos para métodos de pago
            btnPagos.forEach(btn => {
                btn.addEventListener('click', function() {
                    btnPagos.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    tipoPagoSeleccionado = this.dataset.tipo;
                    actualizarEstadoBotonImprimir();
                });
            });
            
            // Modificar las URLs en las llamadas fetch
            btnImprimir.addEventListener('click', async function() {
                if (productosSeleccionados.length === 0 || tipoPagoSeleccionado === null) return;
            
                const originalText = btnImprimir.textContent;
                btnImprimir.textContent = 'Procesando...';
                btnImprimir.disabled = true;
            
                try {
                    // Validar productos
                    const productosValidados = productosSeleccionados.map(p => {
                        if (!p.id || !p.precio || !p.cantidad) {
                            throw new Error('Producto inválido: ' + JSON.stringify(p));
                        }
                        return {
                            id: parseInt(p.id),
                            cantidad: parseInt(p.cantidad),
                            precio: parseFloat(p.precio),
                            notas: p.notas || ''
                        };
                    });
            
                    // Crear objeto de venta
                    const ventaData = {
                        productos: productosValidados,
                        tipoPago: tipoPagoSeleccionado,
                        nombreCliente: document.getElementById('nombreCliente').value.trim() || null
                    };
            
                    // Mostrar datos que se enviarán (para diagnóstico)
                    console.log('Enviando datos:', ventaData);
                    
                const response = await fetch('procesar.php', {  // Cambiado de '/ventas/procesar.php'
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(ventaData)
                });
                
                // Verificar respuesta
                    if (!response.ok) {
                        const errorText = await response.text();
                        console.error('Error en respuesta:', errorText);
                        throw new Error(`Error ${response.status}: ${errorText.substring(0, 100)}`);
                    }
            
                    const data = await response.json();
                    
                    if (!data.success) {
                        throw new Error(data.message || 'Error al procesar la venta');
                    }
            
                    // Abrir ventana de impresión
                    const ventanaImpresion = window.open(`/ventas/imprimir.php?id=${data.ventaId}`, '_blank');
                    
                    if (!ventanaImpresion) {
                        throw new Error('Por favor permite ventanas emergentes para este sitio');
                    }
            
                    // Reiniciar pedido
                    productosSeleccionados = [];
                    tipoPagoSeleccionado = null;
                    btnPagos.forEach(b => b.classList.remove('active'));
                    actualizarTablaPedido();
            
                } catch (error) {
                    console.error('Error completo:', error);
                    
                    // Mostrar mensaje de error detallado
                    let errorMessage = error.message;
                    
                    if (error instanceof SyntaxError) {
                        errorMessage = 'El servidor respondió con formato inválido. Revisa la consola para más detalles.';
                    }
                    
                    alert(`Error al procesar venta:\n${errorMessage}`);
                    
                } finally {
                    btnImprimir.textContent = originalText;
                    actualizarEstadoBotonImprimir();
                }
            });
            
            // Evento para cerrar evento
            btnCerrarEvento.addEventListener('click', function() {
                if (confirm('¿Estás seguro de que deseas cerrar el evento? Esto generará un reporte de todas las ventas no cerradas.')) {
                    this.textContent = 'Procesando...';
                    this.disabled = true;
                    
                    fetch('/cierres/generar.php', {
                        method: 'POST'
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Error en la respuesta del servidor');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            window.location.href = '/cierres/';
                        } else {
                            throw new Error(data.message || 'Error al cerrar el evento');
                        }
                    })
                    .catch(error => {
                        alert(error.message);
                        this.textContent = 'CERRAR EVENTO';
                        this.disabled = false;
                    });
                }
            });
            
            // Inicializar tabla
            actualizarTablaPedido();
        });
    </script>
</body>
</html>