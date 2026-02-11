/* ===================================================
   Consolidado de Pedidos - JavaScript
   =================================================== */

let consolidado = [];
let sucursales = [];
let filtros = {
    sucursal: '',
    dia: ''
};

// Días de la semana
const diasSemana = {
    1: 'Lunes',
    2: 'Martes',
    3: 'Miércoles',
    4: 'Jueves',
    5: 'Viernes',
    6: 'Sábado',
    7: 'Domingo'
};

// Inicializar
$(document).ready(function () {
    cargarSucursales();
    cargarConsolidado();
});

// Cargar lista de sucursales para el filtro
function cargarSucursales() {
    $.ajax({
        url: 'ajax/compra_local_consolidado_pedidos_get_sucursales.php',
        method: 'GET',
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                sucursales = response.sucursales;
                renderizarFiltroSucursales();
            }
        },
        error: function () {
            console.error('Error al cargar sucursales');
        }
    });
}

// Renderizar opciones del filtro de sucursales
function renderizarFiltroSucursales() {
    let options = '<option value="">Todas las sucursales</option>';
    sucursales.forEach(sucursal => {
        options += `<option value="${sucursal.codigo}">${sucursal.nombre}</option>`;
    });
    $('#filtro-sucursal').html(options);
}

// Aplicar filtros
function aplicarFiltros() {
    filtros.sucursal = $('#filtro-sucursal').val();
    filtros.dia = $('#filtro-dia').val();
    cargarConsolidado();
}

// Cargar datos consolidados
function cargarConsolidado() {
    $('#consolidado-container').html(`
        <div class="loader-container">
            <div class="loader"></div>
        </div>
    `);

    $.ajax({
        url: 'ajax/compra_local_consolidado_pedidos_get_datos.php',
        method: 'POST',
        data: filtros,
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                consolidado = response.consolidado;
                renderizarTabla();
            } else {
                mostrarError('Error al cargar datos: ' + response.message);
            }
        },
        error: function () {
            mostrarError('Error de conexión al cargar datos');
        }
    });
}

// Renderizar tabla consolidada
function renderizarTabla() {
    if (consolidado.length === 0) {
        $('#consolidado-container').html(`
            <div class="no-data-message">
                <i class="bi bi-inbox"></i>
                <p>No hay pedidos registrados con los filtros seleccionados</p>
            </div>
        `);
        return;
    }

    // Calcular totales
    const totales = calcularTotales();

    // Renderizar resumen
    let html = `
        <div class="summary-card">
            <div class="row">
                <div class="col-md-4 summary-item">
                    <div class="summary-value">${totales.productos}</div>
                    <div class="summary-label">Productos</div>
                </div>
                <div class="col-md-4 summary-item">
                    <div class="summary-value">${totales.pedidos}</div>
                    <div class="summary-label">Pedidos Totales</div>
                </div>
                <div class="col-md-4 summary-item">
                    <div class="summary-value">${totales.sucursales}</div>
                    <div class="summary-label">Sucursales</div>
                </div>
            </div>
        </div>
    `;

    // Renderizar tabla
    html += `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Detalle de Pedidos</h5>
            <button class="btn btn-export" onclick="exportarExcel()">
                <i class="bi bi-file-earmark-excel"></i> Exportar a Excel
            </button>
        </div>
        <div class="table-responsive">
            <table class="table consolidado-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Día</th>
                        <th>Total Pedido</th>
                        <th>Sucursales</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
    `;

    consolidado.forEach((item, index) => {
        html += `
            <tr>
                <td>${item.nombre_producto}</td>
                <td>
                    <span class="day-badge ${diasSemana[item.dia_entrega].toLowerCase()}">
                        ${diasSemana[item.dia_entrega]}
                    </span>
                </td>
                <td class="data-cell has-value">${item.total_cantidad}</td>
                <td>${item.num_sucursales}</td>
                <td>
                    <button class="btn-expand" onclick="toggleDetails(${index})" id="btn-expand-${index}">
                        <i class="bi bi-chevron-down"></i>
                    </button>
                </td>
            </tr>
            <tr class="details-row" id="details-${index}" style="display: none;">
                <td colspan="5">
                    <div class="details-content">
                        <strong>Desglose por Sucursal:</strong>
                        <div class="mt-2">
                            ${item.detalles.map(detalle => `
                                <div class="branch-detail">
                                    <span class="branch-name">${detalle.nombre_sucursal}</span>
                                    <span class="branch-quantity">${detalle.cantidad} unidades</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
    `;

    $('#consolidado-container').html(html);
}

// Calcular totales
function calcularTotales() {
    const productos = new Set();
    const sucursalesSet = new Set();
    let totalPedidos = 0;

    consolidado.forEach(item => {
        productos.add(item.id_producto_presentacion);
        totalPedidos += parseInt(item.total_cantidad);
        item.detalles.forEach(detalle => {
            sucursalesSet.add(detalle.codigo_sucursal);
        });
    });

    return {
        productos: productos.size,
        pedidos: totalPedidos,
        sucursales: sucursalesSet.size
    };
}

// Expandir/contraer detalles
function toggleDetails(index) {
    const detailsRow = $(`#details-${index}`);
    const btn = $(`#btn-expand-${index}`);

    if (detailsRow.is(':visible')) {
        detailsRow.hide();
        btn.removeClass('expanded');
    } else {
        detailsRow.show();
        btn.addClass('expanded');
    }
}

// Exportar a Excel (simulado - en producción usar librería como SheetJS)
function exportarExcel() {
    Swal.fire({
        icon: 'info',
        title: 'Exportar a Excel',
        text: 'Funcionalidad de exportación en desarrollo',
        confirmButtonColor: '#51B8AC'
    });

    // TODO: Implementar exportación real con SheetJS o similar
    // Por ahora, se puede generar un CSV simple
    /*
    let csv = 'Producto,Día,Total Pedido,Sucursales\n';
    consolidado.forEach(item => {
        csv += `"${item.nombre_producto}","${diasSemana[item.dia_entrega]}",${item.total_cantidad},${item.num_sucursales}\n`;
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'consolidado_pedidos.csv';
    a.click();
    */
}

// Mostrar mensaje de error
function mostrarError(mensaje) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: mensaje,
        confirmButtonColor: '#51B8AC'
    });
}
