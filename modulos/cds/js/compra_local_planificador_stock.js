/* ===================================================
   Planificador de Stock Mínimo - JavaScript
   =================================================== */

let simulaciones = [];
let perfiles = [];

$(document).ready(function () {
    cargarPerfiles();
    inicializarBusqueda();
});

// Cargar perfiles disponibles para simulación
function cargarPerfiles() {
    $.ajax({
        url: 'ajax/compra_local_planificador_stock_get_perfiles.php',
        method: 'GET',
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                perfiles = response.perfiles;
            }
        }
    });
}

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
        vidaUtil: 30, // Vida útil mayor para simulaciones
        consumo: 1,
        factor: 1,
        idPerfil: "", // Perfil opcional
        frecuencia: 1, // 1 semana por defecto (si no hay perfil)
        diaPedido: 1,  // Lunes (si no hay perfil)
        diaDespacho: 2, // Martes (si no hay perfil)
        stockMinimo: 0
    };

    simulaciones.unshift(nuevaSimulacion);
    renderizarTabla();
}

// Calcular stock mínimo basado en la fórmula
function calcularStock(sim) {
    let diasTotal = 0;
    const contingencia = parseInt(sim.contingencia) || 0;
    const vidaUtil = parseInt(sim.vidaUtil) || 30;

    if (sim.idPerfil) {
        // LÓGICA POR PERFIL
        const perfil = perfiles.find(p => p.id == sim.idPerfil);
        if (perfil) {
            // Re-mapear perfil para getDaysUntilNextDelivery
            const pData = {
                frecuencia: parseInt(perfil.frecuencia),
                semana_referencia: perfil.semana_referencia ? parseInt(perfil.semana_referencia) : null,
                dias: {
                    1: parseInt(perfil.lunes),
                    2: parseInt(perfil.martes),
                    3: parseInt(perfil.miercoles),
                    4: parseInt(perfil.jueves),
                    5: parseInt(perfil.viernes),
                    6: parseInt(perfil.sabado),
                    7: parseInt(perfil.domingo)
                }
            };

            // Simular para una fecha de entrega "HOY"
            const hoy = new Date();
            const gap = getDaysUntilNextDelivery(pData, hoy);

            // Suponemos Lead Time = 1 para el planner por perfil (simplificado)
            diasTotal = Math.min(gap + 1 + contingencia, vidaUtil);
        }
    } else {
        // LÓGICA MANUAL (Lead Time Operativo + Frecuencia semanas)
        let leadTimeOperativo = parseInt(sim.diaDespacho) - parseInt(sim.diaPedido);
        if (leadTimeOperativo < 0) leadTimeOperativo += 7;

        const gapFrecuencia = parseInt(sim.frecuencia) * 7;
        diasTotal = Math.min(leadTimeOperativo + gapFrecuencia + contingencia, vidaUtil);
    }

    const demandaDiaria = parseFloat(sim.consumo) * parseFloat(sim.factor);
    return Math.ceil(demandaDiaria * diasTotal);
}

// Determinar cuántos días faltan hasta la siguiente entrega según el perfil
function getDaysUntilNextDelivery(perfil, fechaEntregaActual) {
    const freq = perfil.frecuencia || 1;
    const semRef = perfil.semana_referencia;
    const diasConfig = perfil.dias || {};

    let diasContados = 0;
    let fechaLoop = new Date(fechaEntregaActual);

    for (let i = 1; i <= 35; i++) {
        fechaLoop.setDate(fechaLoop.getDate() + 1);
        diasContados++;

        let numDia = fechaLoop.getDay();
        if (numDia === 0) numDia = 7;

        if (diasConfig[numDia] == 1) {
            if (freq > 1 && semRef !== null) {
                const numSemana = getISOWeek(fechaLoop);
                if ((numSemana - semRef) % freq === 0) {
                    return diasContados;
                }
            } else {
                return diasContados;
            }
        }
    }
    return 7;
}

function getISOWeek(date) {
    const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
    const dayNum = d.getUTCDay() || 7;
    d.setUTCDate(d.getUTCDate() + 4 - dayNum);
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
    return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
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
                        <th style="width: 180px;">Plan de Perfil (Opcional)</th>
                        <th style="width: 120px;">Ciclo Manual</th>
                        <th style="width: 100px;">Frecuencia</th>
                        <th style="width: 70px;">Cont.</th>
                        <th style="width: 70px;">Vida Útil</th>
                        <th style="width: 140px;">Stock Mínimo</th>
                        <th style="width: 40px;"></th>
                    </tr>
                </thead>
                <tbody>
    `;

    simulaciones.forEach((sim, index) => {
        const stock = calcularStock(sim);
        const hasProfile = sim.idPerfil !== "";

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
                    <select class="form-select form-select-sm x-small" 
                            onchange="actualizarDato(${index}, 'idPerfil', this.value)">
                        <option value="">-- Manual --</option>
                        ${perfiles.map(p => `<option value="${p.id}" ${sim.idPerfil == p.id ? 'selected' : ''}>${p.nombre}</option>`).join('')}
                    </select>
                </td>
                <td>
                    <div class="d-flex flex-column gap-1 ${hasProfile ? 'opacity-25' : ''}">
                        <select class="form-select form-select-sm x-small" title="Día de Pedido"
                                ${hasProfile ? 'disabled' : ''}
                                onchange="actualizarDato(${index}, 'diaPedido', this.value)">
                            ${[1, 2, 3, 4, 5, 6, 7].map(d => `<option value="${d}" ${sim.diaPedido == d ? 'selected' : ''}>P: ${diasNombre[d]}</option>`).join('')}
                        </select>
                        <select class="form-select form-select-sm x-small" title="Día de Despacho"
                                ${hasProfile ? 'disabled' : ''}
                                onchange="actualizarDato(${index}, 'diaDespacho', this.value)">
                            ${[1, 2, 3, 4, 5, 6, 7].map(d => `<option value="${d}" ${sim.diaDespacho == d ? 'selected' : ''}>D: ${diasNombre[d]}</option>`).join('')}
                        </select>
                    </div>
                </td>
                <td>
                    <select class="form-select form-select-sm simulation-input ${hasProfile ? 'opacity-25' : ''}" 
                            ${hasProfile ? 'disabled' : ''}
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
