/**
 * prueba_promociones.js — Lógica para la página de pruebas de promociones, de prueba
 */

let cart = [];
let approvedPromoIds = [];

document.addEventListener('DOMContentLoaded', () => {
    inicializarSucursales();
    inicializarBusquedaProducto();
    // Quitamos los listeners automáticos para que el cálculo sea manual
});

/**
 * Carga las sucursales disponibles en el select
 */
async function inicializarSucursales() {
    const sel = document.getElementById('simSucursal');
    try {
        const resp = await fetch('ajax/promo_get_grupos.php?tipo=sucursales');
        const data = await resp.json();
        if (data.success) {
            sel.innerHTML = data.data.map(s => `<option value="${s.id}">${s.nombre}</option>`).join('');
        } else {
            sel.innerHTML = '<option value="">Error al cargar</option>';
        }
    } catch (e) {
        sel.innerHTML = '<option value="">Error de red</option>';
    }
}

/**
 * Configura Select2 para buscar productos
 */
function inicializarBusquedaProducto() {
    $('#searchProducto').select2({
        theme: 'bootstrap-5',
        placeholder: 'Buscar producto...',
        minimumInputLength: 1,
        ajax: {
            url: 'ajax/promo_get_productos_precios.php',
            type: 'POST',
            dataType: 'json',
            delay: 250,
            data: params => ({ term: params.term }),
            processResults: data => ({ results: data.success ? data.data : [] })
        }
    }).on('select2:select', function(e) {
        const prod = e.params.data;
        agregarAlCarrito(prod);
        $(this).val(null).trigger('change');
    });
}

/**
 * Agrega un producto al carrito local
 */
function agregarAlCarrito(prod) {
    const index = cart.findIndex(item => item.id === prod.id);
    if (index > -1) {
        cart[index].cantidad++;
    } else {
        cart.push({
            id: prod.id,
            nombre: prod.text,
            precio: parseFloat(prod.precio || 0),
            cantidad: 1,
            id_producto_maestro: prod.id_producto_maestro,
            id_subgrupo: prod.id_subgrupo,
            id_grupo: prod.id_grupo,
            tamano: prod.tamano
        });
    }
    renderCarrito();
}

function cambiarCantidad(id, delta) {
    const index = cart.findIndex(item => item.id == id);
    if (index > -1) {
        cart[index].cantidad += delta;
        if (cart[index].cantidad <= 0) {
            cart.splice(index, 1);
        }
    }
    renderCarrito();
}

/**
 * Renderiza la lista de productos en el carrito
 */
function renderCarrito() {
    const cont = document.getElementById('cartList');
    if (cart.length === 0) {
        cont.innerHTML = '<div class="text-center text-muted py-3">El carrito está vacío</div>';
        resetSimulacion();
        return;
    }

    cont.innerHTML = cart.map(item => `
        <div class="cart-item">
            <div style="flex:1">
                <div class="fw-bold small">${item.nombre}</div>
                <div class="text-muted" style="font-size:0.8em">C$${item.precio.toFixed(2)}</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="cambiarCantidad(${item.id}, -1)">-</button>
                <span class="small fw-bold" style="min-width:20px; text-align:center">${item.cantidad}</span>
                <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="cambiarCantidad(${item.id}, 1)">+</button>
            </div>
            <button class="btn btn-sm btn-outline-danger border-0 ms-2" onclick="cambiarCantidad(${item.id}, -999)">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `).join('');
}

function resetSimulacion() {
    approvedPromoIds = [];
    document.getElementById('resultEmpty').classList.remove('d-none');
    document.getElementById('resultContent').classList.add('d-none');
}

/**
 * Envía el carrito y el contexto al backend para evaluar promociones
 */
async function procesarPromociones() {
    if (cart.length === 0) {
        Swal.fire('Carrito vacío', 'Agrega productos primero.', 'warning');
        return;
    }

    const context = {
        sucursal: document.getElementById('simSucursal').value,
        dia: document.getElementById('simDia').value,
        hora: document.getElementById('simHora').value,
        canal: document.getElementById('simCanal').value,
        tipo_cliente: document.getElementById('simTipoCliente').value
    };

    document.getElementById('resultEmpty').classList.add('d-none');
    document.getElementById('resultContent').classList.add('d-none');
    document.getElementById('resultLoading').classList.remove('d-none');

    try {
        const resp = await fetch('ajax/promo_aplicar_prueba.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ context, cart, approved_ids: approvedPromoIds })
        });
        const data = await resp.json();

        if (data.success) {
            renderResultados(data);
        } else {
            throw new Error(data.message);
        }
    } catch (e) {
        Swal.fire('Error', e.message, 'error');
        document.getElementById('resultEmpty').classList.remove('d-none');
    } finally {
        document.getElementById('resultLoading').classList.add('d-none');
    }
}

function aprobarPromocion(id) {
    if (!approvedPromoIds.includes(id)) {
        approvedPromoIds.push(id);
        procesarPromociones();
    }
}

function quitarPromocion(id) {
    approvedPromoIds = approvedPromoIds.filter(pid => pid != id);
    procesarPromociones();
}

/**
 * Renderiza los resultados de la evaluación
 */
function renderResultados(res) {
    document.getElementById('resultContent').classList.remove('d-none');

    // Tabla de items
    const tbody = document.getElementById('resTableBody');
    tbody.innerHTML = res.items.map(item => `
        <tr>
            <td>
                <div class="small">${item.nombre}</div>
                ${item.promos.map(p => `<div class="text-success" style="font-size:0.75em"><i class="bi bi-tag-fill"></i> ${p.nombre}</div>`).join('')}
            </td>
            <td class="text-center">${item.cantidad}</td>
            <td class="text-end price-old">C$${(item.precio * item.cantidad).toFixed(2)}</td>
            <td class="text-end text-danger">C$${item.descuento_total.toFixed(2)}</td>
            <td class="text-end price-new">C$${item.subtotal_final.toFixed(2)}</td>
        </tr>
    `).join('');

    document.getElementById('resTotalDesc').textContent = `C$${res.total_descuento.toFixed(2)}`;
    document.getElementById('resTotalFinal').textContent = `C$${res.total_final.toFixed(2)}`;

    // Promos APLICADAS (Aprobadas)
    const appliedCont = document.getElementById('appliedPromos');
    if (res.promos_aplicadas.length === 0) {
        appliedCont.innerHTML = '<div class="small text-muted">Ninguna promoción aplicada.</div>';
    } else {
        appliedCont.innerHTML = res.promos_aplicadas.map(p => `
            <div class="promo-applied d-flex justify-content-between align-items-center">
                <div>
                    <strong>${p.nombre}</strong><br>
                    <span class="small text-muted">${p.resumen}</span>
                </div>
                <button class="btn btn-sm btn-outline-danger border-0" onclick="quitarPromocion(${p.id})">
                    <i class="bi bi-x-circle"></i> Quitar
                </button>
            </div>
        `).join('');
    }

    // Promos que CALIFICAN (Esperando aprobación)
    const suggestedCont = document.getElementById('suggestedPromos');
    if (res.promos_califican.length === 0) {
        suggestedCont.innerHTML = '<div class="small text-muted">No hay promociones listas para aprobar.</div>';
    } else {
        suggestedCont.innerHTML = res.promos_califican.map(p => `
            <div class="promo-applied border-primary" style="background:#e3f2fd; border-left: 4px solid #0d6efd;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${p.nombre}</strong><br>
                        <span class="small text-primary">${p.resumen}</span>
                    </div>
                    <button class="btn btn-sm btn-primary" onclick="aprobarPromocion(${p.id})">
                        <i class="bi bi-check-circle"></i> Aprobar
                    </button>
                </div>
            </div>
        `).join('');
    }

    // Promos POTENCIALES (NUEVO)
    const potentialCont = document.getElementById('potentialPromos');
    if (!res.promos_potenciales || res.promos_potenciales.length === 0) {
        potentialCont.innerHTML = '<div class="small text-muted">No hay promociones potenciales.</div>';
    } else {
        potentialCont.innerHTML = res.promos_potenciales.map(p => `
            <div class="promo-applied border-warning" style="background:#fffcf2; border-left: 4px solid #ffc107;">
                <strong>${p.nombre}</strong><br>
                <div class="d-flex justify-content-between align-items-center mt-1">
                    <span class="small text-warning fw-bold">
                        Met: ${p.condiciones_met}/${p.total_condiciones} cond.
                    </span>
                    <span class="small text-muted" style="font-size:0.85em italic">
                        Falta: ${p.faltante}
                    </span>
                </div>
                <div class="progress mt-1" style="height: 4px;">
                    <div class="progress-bar bg-warning" role="progressbar" style="width: ${(p.condiciones_met/p.total_condiciones)*100}%"></div>
                </div>
            </div>
        `).join('');
    }

    // Promos Descartadas
    const rejectedCont = document.getElementById('rejectedPromos');
    if (res.promos_descartadas.length === 0) {
        rejectedCont.innerHTML = '<div class="small text-muted">No hay promociones descartadas.</div>';
    } else {
        rejectedCont.innerHTML = res.promos_descartadas.map(p => `
            <div class="promo-rejected">
                <strong>${p.nombre}</strong><br>
                <span class="small text-danger">Condición NO cumplida: ${p.motivo}</span>
            </div>
        `).join('');
    }
}
