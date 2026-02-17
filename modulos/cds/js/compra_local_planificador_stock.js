/* ===================================================
   Planificador de Stock Mínimo - JavaScript
   =================================================== */

let simulaciones = [];

$(document).ready(function () {
    inicializarBusqueda();
});

// Inicializar Select2 para búsqueda de productos
function inicializarBusqueda() {
    $('#product-planner-search').select2({
        theme: 'bootstrap-5',
        placeholder: 'Busque un producto...',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: 'ajax/compra_local_planificador_stock_buscar.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    search: params.term
                };
            },
            processResults: function (data) {
                if (data.success) {
                    return {
                        results: data.productos.map(p => ({
                            id: p.id,
                            text: `${p.Nombre} (${p.SKU})`,
                            nombre: p.Nombre,
                            sku: p.SKU
                        }))
                    };
                }
                return { results: [] };
            },
            cache: true
        }
    }).on('select2:select', function (e) {
        const data = e.params.data;
        agregarSimulacion(data);
        $(this).val(null).trigger('change');
    });
}

// Agregar producto a la lista de simulación
function agregarSimulacion(producto) {
    // Si ya existe, no agregarlo de nuevo (o avisar)
    if (simulaciones.find(s => s.id === producto.id)) {
        return;
    }

    const nuevaSimulacion = {
        id: producto.id,
        nombre: producto.nombre,
        sku: producto.sku,
        contingencia: 0,
        vidaUtil: 7,
        consumo: 1,
        factor: 1,
        frecuencia: 1, // 1 semana por defecto
        diaPedido: 1,  // Lunes
        diaDespacho: 2, // Martes
        stockMinimo: 0
    };

    simulaciones.unshift(nuevaSimulacion);
    renderizarTabla();
}

// Calcular stock mínimo basado en la fórmula
function calcularStock(sim) {
    // 1. Calcular días entre Pedido y Despacho (Lead Time Operativo)
    let leadTimeOperativo = parseInt(sim.diaDespacho) - parseInt(sim.diaPedido);
    if (leadTimeOperativo < 0) leadTimeOperativo += 7; // Ajuste si cruza la semana

    // 2. Calcular el Gap de la frecuencia (ej: 2 semanas = 14 días)
    const gapFrecuencia = parseInt(sim.frecuencia) * 7;

    // 3. Días totales a cubrir: LeadTime + Frecuencia + Contingencia
    // Según lógica de negocio: El pedido debe durar hasta el SIGUIENTE despacho
    const diasTotal = Math.min(leadTimeOperativo + gapFrecuencia + parseInt(sim.contingencia), parseInt(sim.vidaUtil));

    // 4. Demanda diaria proyectada
    const demandaDiaria = parseFloat(sim.consumo) * parseFloat(sim.factor);

    return Math.ceil(demandaDiaria * diasTotal);
}

// Renderizar la tabla de resultados
function renderizarTabla() {
    if (simulaciones.length === 0) {
        $('#planner-container').html(`
            <div class="text-center p-5 text-muted bg-light rounded-3">
                <i class="bi bi-calculator fs-1 opacity-25"></i>
                <p class="mt-2">No hay productos en simulación. <br> Use el buscador arriba para agregar productos.</p>
            </div>
        `);
        return;
    }

    const diasNombre = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

    let html = `
        <div class="table-responsive">
            <table class="table planner-table align-middle">
                <thead>
                    <tr class="text-center">
                        <th class="text-start">Producto</th>
                        <th style="width: 100px;">Consumo x Día</th>
                        <th style="width: 100px;">F. Evento</th>
                        <th style="width: 120px;">Ciclo Pedido</th>
                        <th style="width: 100px;">Frecuencia</th>
                        <th style="width: 80px;">Cont.</th>
                        <th style="width: 80px;">Vida Útil</th>
                        <th style="width: 140px;">Stock Mínimo</th>
                        <th style="width: 40px;"></th>
                    </tr>
                </thead>
                <tbody>
    `;

    simulaciones.forEach((sim, index) => {
        const stock = calcularStock(sim);
        html += `
            <tr class="row-sim">
                <td>
                    <div class="fw-bold text-primary">${sim.nombre}</div>
                    <div class="x-small text-muted">${sim.sku}</div>
                </td>
                <td>
                    <input type="number" step="0.01" class="form-control form-control-sm simulation-input" 
                           value="${sim.consumo}" onchange="actualizarDato(${index}, 'consumo', this.value)">
                </td>
                <td>
                    <input type="number" step="0.1" class="form-control form-control-sm simulation-input" 
                           value="${sim.factor}" onchange="actualizarDato(${index}, 'factor', this.value)">
                </td>
                <td>
                    <div class="d-flex flex-column gap-1">
                        <select class="form-select form-select-sm x-small" title="Día de Pedido"
                                onchange="actualizarDato(${index}, 'diaPedido', this.value)">
                            ${[1, 2, 3, 4, 5, 6, 7].map(d => `<option value="${d}" ${sim.diaPedido == d ? 'selected' : ''}>P: ${diasNombre[d]}</option>`).join('')}
                        </select>
                        <select class="form-select form-select-sm x-small" title="Día de Despacho"
                                onchange="actualizarDato(${index}, 'diaDespacho', this.value)">
                            ${[1, 2, 3, 4, 5, 6, 7].map(d => `<option value="${d}" ${sim.diaDespacho == d ? 'selected' : ''}>D: ${diasNombre[d]}</option>`).join('')}
                        </select>
                    </div>
                </td>
                <td>
                    <select class="form-select form-select-sm simulation-input" 
                            onchange="actualizarDato(${index}, 'frecuencia', this.value)">
                        <option value="1" ${sim.frecuencia == 1 ? 'selected' : ''}>Semanal</option>
                        <option value="2" ${sim.frecuencia == 2 ? 'selected' : ''}>Quincenal</option>
                        <option value="4" ${sim.frecuencia == 4 ? 'selected' : ''}>Mensual</option>
                    </select>
                </td>
                <td>
                    <input type="number" step="1" class="form-control form-control-sm simulation-input" 
                           value="${sim.contingencia}" onchange="actualizarDato(${index}, 'contingencia', this.value)">
                </td>
                <td>
                    <input type="number" step="1" class="form-control form-control-sm simulation-input" 
                           value="${sim.vidaUtil}" onchange="actualizarDato(${index}, 'vidaUtil', this.value)">
                </td>
                <td class="text-center">
                    <span class="result-badge badge-stock">${stock}</span>
                    <div class="x-small text-muted mt-1">unidades sugeridas</div>
                </td>
                <td>
                    <button class="btn-remove-row" onclick="eliminarFila(${index})" title="Eliminar Simulation">
                        <i class="fas fa-times-circle"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
    `;

    $('#planner-container').html(html);
}

// Actualizar valores de simulación
function actualizarDato(index, campo, valor) {
    simulaciones[index][campo] = valor;
    renderizarTabla();
}

// Eliminar fila
function eliminarFila(index) {
    simulaciones.splice(index, 1);
    renderizarTabla();
}

// Limpiar todo
function limpiarPlanificador() {
    Swal.fire({
        title: '¿Limpiar todo?',
        text: "Se borrarán todos los productos de la simulación actual.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#51B8AC',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, limpiar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            simulaciones = [];
            renderizarTabla();
        }
    });
}
