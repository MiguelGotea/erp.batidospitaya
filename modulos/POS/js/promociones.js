/**
 * promociones.js — Configurador de Promociones, Pitaya ERP
 * Maneja tanto la página de listado como el formulario de creación/edición.
 */

/* ============================================================
   CONSTANTES Y ESTADO GLOBAL
   ============================================================ */
const AJAX_BASE = 'ajax/';

// Estado del listado
let paginaActual = 1;
let totalPaginas = 1;
let regPorPagina = 25;
let totalRegistros = 0;
let filtrosActivos = {};
let ordenActivo = { columna: null, direccion: 'asc' };
let panelFiltroAbierto = null;
let scrollTopInicial = 0;
let windowWidthInicial = window.innerWidth;

// Estado del formulario
let condicionesData = [];   // Array de objetos de condición
let condicionCounter = 0;   // ID único por tarjeta

/* ============================================================
   INICIALIZACIÓN
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
    if (window.PROMO_PAGE === 'listado') {
        iniciarListado();
    } else if (window.PROMO_PAGE === 'formulario') {
        iniciarFormulario();
    }
});

/* ============================================================
   PÁGINA DE LISTADO
   ============================================================ */
function iniciarListado() {
    cargarPromociones();

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.filter-panel, .filter-icon')) {
            cerrarTodosFiltros();
        }
    });

    const wrapper = document.querySelector('.table-responsive');
    if (wrapper) {
        wrapper.addEventListener('scroll', function (e) {
            e.stopPropagation();
        });
    }

    window.addEventListener('scroll', function () {
        if (panelFiltroAbierto && Math.abs(window.scrollY - scrollTopInicial) > 100) {
            cerrarTodosFiltros();
        }
    });

    window.addEventListener('resize', function () {
        if (panelFiltroAbierto && window.innerWidth !== windowWidthInicial) {
            cerrarTodosFiltros();
            windowWidthInicial = window.innerWidth;
        }
    });
}

async function cargarPromociones(pagina = 1) {
    paginaActual = pagina;
    regPorPagina = parseInt(document.getElementById('registrosPorPagina').value);
    const tbody   = document.getElementById('tablaPromocionesBody');
    tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">
        <i class="bi bi-arrow-repeat spin-icon"></i> Cargando…</td></tr>`;

    try {
        const resp = await fetch(AJAX_BASE + 'promo_get_datos.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                pagina,
                registros_por_pagina: regPorPagina,
                filtros: JSON.stringify(filtrosActivos),
                orden: JSON.stringify(ordenActivo)
            })
        });
        const data = await resp.json();

        if (!data.success) throw new Error(data.message);

        totalRegistros = parseInt(data.total_registros || 0, 10);
        renderTablaPromociones(data.datos);
        renderPaginacion(totalRegistros);
        actualizarInfoRegistros(totalRegistros, pagina, regPorPagina);
        actualizarIndicadoresFiltros();

    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-3">
            <i class="bi bi-exclamation-circle"></i> ${err.message}</td></tr>`;
    }
}

function cambiarRegistrosPorPagina() {
    regPorPagina = parseInt(document.getElementById('registrosPorPagina').value || '25', 10);
    paginaActual = 1;
    cargarPromociones(1);
}

function toggleFilter(icon) {
    const th = icon.closest('th');
    const columna = th?.dataset?.column;
    const tipo = th?.dataset?.type;

    if (!columna || !tipo) return;
    if (panelFiltroAbierto === columna) {
        cerrarTodosFiltros();
        return;
    }

    cerrarTodosFiltros();
    scrollTopInicial = window.scrollY;
    windowWidthInicial = window.innerWidth;
    crearPanelFiltro(columna, tipo, icon);
    panelFiltroAbierto = columna;
    icon.classList.add('active');
    actualizarIndicadoresFiltros();
}

function crearPanelFiltro(columna, tipo, icon) {
    const panel = document.createElement('div');
    panel.className = 'filter-panel show';
    if (tipo === 'daterange') panel.classList.add('has-daterange');

    const ascLabel = tipo === 'number' ? 'Menor a mayor' : 'A→Z';
    const descLabel = tipo === 'number' ? 'Mayor a menor' : 'Z→A';

    panel.innerHTML = `
        <div class="filter-section">
            <span class="filter-section-title">Ordenar:</span>
            <div class="filter-sort-buttons">
                <button class="filter-sort-btn ${ordenActivo.columna === columna && ordenActivo.direccion === 'asc' ? 'active' : ''}" onclick="aplicarOrden('${columna}','asc')">
                    <i class="bi bi-sort-alpha-down"></i> ${ascLabel}
                </button>
                <button class="filter-sort-btn ${ordenActivo.columna === columna && ordenActivo.direccion === 'desc' ? 'active' : ''}" onclick="aplicarOrden('${columna}','desc')">
                    <i class="bi bi-sort-alpha-up"></i> ${descLabel}
                </button>
            </div>
        </div>
        <div class="filter-actions">
            <button class="filter-action-btn clear" onclick="limpiarFiltro('${columna}')">
                <i class="bi bi-x-circle"></i> Limpiar
            </button>
        </div>
    `;

    document.body.appendChild(panel);

    if (tipo === 'text' || tipo === 'number') {
        const valor = filtrosActivos[columna] || '';
        const extra = document.createElement('div');
        extra.className = 'filter-section';
        extra.style.marginTop = '12px';
        extra.innerHTML = `
            <span class="filter-section-title">Buscar:</span>
            <input type="text" class="filter-search" placeholder="Escribir..." value="${escHtml(String(valor))}"
                   oninput="filtrarBusqueda('${columna}', this.value)">`;
        panel.appendChild(extra);
        posicionarPanelFiltro(panel, icon);
    } else if (tipo === 'list') {
        cargarOpcionesFiltro(panel, columna, icon);
    } else if (tipo === 'daterange') {
        crearCalendarioRango(panel, columna);
        posicionarPanelFiltro(panel, icon);
    }
}

function cargarOpcionesFiltro(panel, columna, icon) {
    fetch(AJAX_BASE + 'promo_get_opciones_filtro.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ columna })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) throw new Error(data.message || 'No se pudieron cargar opciones');

        const wrapper = document.createElement('div');
        wrapper.className = 'filter-section';
        wrapper.style.marginTop = '12px';
        let html = '<span class="filter-section-title">Filtrar por:</span>';
        html += '<input type="text" class="filter-search" placeholder="Buscar..." onkeyup="buscarEnOpciones(this)">';
        html += '<div class="filter-options">';
        (data.opciones || []).forEach(op => {
            const valor = String(op.valor ?? '');
            const texto = String(op.texto ?? '');
            const checked = Array.isArray(filtrosActivos[columna]) && filtrosActivos[columna].includes(valor) ? 'checked' : '';
            html += `<div class="filter-option">
                <input type="checkbox" value="${escHtml(valor)}" ${checked}
                    onchange="toggleOpcionFiltro('${columna}','${escHtml(valor)}',this.checked)">
                <span>${escHtml(texto)}</span>
            </div>`;
        });
        html += '</div>';
        wrapper.innerHTML = html;
        panel.appendChild(wrapper);
        posicionarPanelFiltro(panel, icon);
    })
    .catch(() => {
        posicionarPanelFiltro(panel, icon);
    });
}

function filtrarBusqueda(columna, valor) {
    if (!valor.trim()) delete filtrosActivos[columna];
    else filtrosActivos[columna] = valor.trim();
    paginaActual = 1;
    cargarPromociones(1);
}

function toggleOpcionFiltro(columna, valor, checked) {
    if (!Array.isArray(filtrosActivos[columna])) filtrosActivos[columna] = [];
    if (checked) {
        if (!filtrosActivos[columna].includes(valor)) filtrosActivos[columna].push(valor);
    } else {
        filtrosActivos[columna] = filtrosActivos[columna].filter(v => v !== valor);
        if (filtrosActivos[columna].length === 0) delete filtrosActivos[columna];
    }
    paginaActual = 1;
    cargarPromociones(1);
}

function buscarEnOpciones(input) {
    const q = input.value.toLowerCase();
    input.parentElement.querySelectorAll('.filter-option').forEach(op => {
        op.style.display = op.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

function aplicarOrden(columna, direccion) {
    ordenActivo = { columna, direccion };
    cerrarTodosFiltros();
    paginaActual = 1;
    cargarPromociones(1);
}

function limpiarFiltro(columna) {
    delete filtrosActivos[columna];
    cerrarTodosFiltros();
    paginaActual = 1;
    cargarPromociones(1);
}

function cerrarTodosFiltros() {
    document.querySelectorAll('.filter-panel').forEach(p => p.remove());
    document.querySelectorAll('.filter-icon').forEach(i => i.classList.remove('active'));
    panelFiltroAbierto = null;
}

function actualizarIndicadoresFiltros() {
    document.querySelectorAll('.filter-icon').forEach(i => i.classList.remove('has-filter'));
    Object.keys(filtrosActivos).forEach(col => {
        const v = filtrosActivos[col];
        const active = (Array.isArray(v) && v.length > 0) ||
            (v && typeof v === 'object' && (v.desde || v.hasta)) ||
            (typeof v === 'string' && v.trim() !== '');
        if (active) {
            document.querySelector(`th[data-column="${col}"] .filter-icon`)?.classList.add('has-filter');
        }
    });
}

function posicionarPanelFiltro(panel, icon) {
    const iconRect = icon.getBoundingClientRect();
    const panelRect = panel.getBoundingClientRect();
    let top = window.scrollY + iconRect.bottom + 5;
    let left = window.scrollX + iconRect.right - panelRect.width;

    if (left + panelRect.width > window.scrollX + window.innerWidth) left = window.scrollX + window.innerWidth - panelRect.width - 10;
    if (left < window.scrollX + 10) left = window.scrollX + 10;
    if (top + panelRect.height > window.scrollY + window.innerHeight) {
        top = Math.max(window.scrollY + 10, window.scrollY + window.innerHeight - panelRect.height - 10);
    }
    panel.style.top = `${top}px`;
    panel.style.left = `${left}px`;
}

function crearCalendarioRango(panel, columna) {
    const hoy = new Date();
    const mesActual = hoy.getMonth();
    const anioActual = hoy.getFullYear();

    const cont = document.createElement('div');
    cont.className = 'filter-section';
    cont.style.marginTop = '8px';
    cont.innerHTML = `
        <span class="filter-section-title">Seleccionar rango:</span>
        <div class="daterange-calendar-container">
            <div class="daterange-month-selector">
                <select id="mesCalendario" onchange="actualizarCalendarioUnico('${columna}')"></select>
                <select id="anioCalendario" onchange="actualizarCalendarioUnico('${columna}')"></select>
            </div>
            <div class="daterange-calendar" id="calendarioUnico"></div>
        </div>`;
    panel.appendChild(cont);

    setTimeout(() => {
        const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        const selMes = document.getElementById('mesCalendario');
        const selAnio = document.getElementById('anioCalendario');
        meses.forEach((m, i) => selMes.insertAdjacentHTML('beforeend', `<option value="${i}" ${i === mesActual ? 'selected' : ''}>${m}</option>`));
        for (let a = anioActual - 8; a <= anioActual + 1; a++) {
            selAnio.insertAdjacentHTML('beforeend', `<option value="${a}" ${a === anioActual ? 'selected' : ''}>${a}</option>`);
        }
        actualizarCalendarioUnico(columna);
    }, 30);
}

function actualizarCalendarioUnico(columna) {
    const mes = parseInt(document.getElementById('mesCalendario').value, 10);
    const anio = parseInt(document.getElementById('anioCalendario').value, 10);
    const primerDia = new Date(anio, mes, 1).getDay();
    const diasMes = new Date(anio, mes + 1, 0).getDate();
    const dias = ['D', 'L', 'M', 'M', 'J', 'V', 'S'];
    let html = '<div class="daterange-calendar-header">';
    dias.forEach(d => html += `<div class="daterange-calendar-day-name">${d}</div>`);
    html += '</div><div class="daterange-calendar-days">';
    for (let i = 0; i < primerDia; i++) html += '<div class="daterange-calendar-day empty"></div>';
    for (let d = 1; d <= diasMes; d++) {
        const fecha = `${anio}-${String(mes + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        const clase = obtenerClasesCalendario(fecha, columna);
        html += `<div class="daterange-calendar-day ${clase}" onclick="event.stopPropagation(); seleccionarFechaRango('${fecha}','${columna}')">${d}</div>`;
    }
    html += '</div>';
    document.getElementById('calendarioUnico').innerHTML = html;
}

function obtenerClasesCalendario(fecha, columna) {
    const d = filtrosActivos[columna]?.desde;
    const h = filtrosActivos[columna]?.hasta;
    if (fecha === d || fecha === h) return 'selected';
    if (d && h && fecha > d && fecha < h) return 'in-range';
    return '';
}

function seleccionarFechaRango(fecha, columna) {
    if (!filtrosActivos[columna]) filtrosActivos[columna] = { desde: null, hasta: null };
    const desde = filtrosActivos[columna].desde;
    const hasta = filtrosActivos[columna].hasta;
    if (!desde) filtrosActivos[columna].desde = fecha;
    else if (!hasta) {
        if (fecha < desde) {
            filtrosActivos[columna].desde = fecha;
            filtrosActivos[columna].hasta = desde;
        } else filtrosActivos[columna].hasta = fecha;
    } else {
        filtrosActivos[columna].desde = fecha;
        filtrosActivos[columna].hasta = null;
    }
    actualizarCalendarioUnico(columna);
    if (filtrosActivos[columna].desde && filtrosActivos[columna].hasta) {
        paginaActual = 1;
        cargarPromociones(1);
    }
}

function renderTablaPromociones(rows) {
    const tbody = document.getElementById('tablaPromocionesBody');
    if (!rows || rows.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-5">
            <i class="bi bi-inbox fs-2 d-block mb-2 opacity-40"></i>No se encontraron promociones.</td></tr>`;
        return;
    }
    tbody.innerHTML = rows.map(r => {
        const estadoBadge = {
            borrador:  '<span class="promo-badge-estado promo-badge-borrador">Borrador</span>',
            activa:    '<span class="promo-badge-estado promo-badge-activa">Activa</span>',
            inactiva:  '<span class="promo-badge-estado promo-badge-inactiva">Inactiva</span>',
            archivada: '<span class="promo-badge-estado promo-badge-archivada">Archivada</span>'
        }[r.estado] || r.estado;

        const resultadoMap = {
            pct_producto:   `% producto`,
            pct_factura:    `% factura`,
            monto_producto: `C$ producto`,
            monto_factura:  `C$ factura`
        };
        const resTxt = `<span class="promo-resultado-chip">${r.resultado_valor}${resultadoMap[r.resultado_tipo] || r.resultado_tipo}</span>`;

        const vigencia = (r.fecha_inicio || r.fecha_fin)
            ? `${r.fecha_inicio || '∞'} → ${r.fecha_fin || '∞'}`
            : '<span class="text-muted small">Sin límite</span>';

        const perms = window.PROMO_PERMISOS || {};
        let acciones = '';
        if (perms.edicion) acciones += `<button class="promo-btn-accion promo-btn-editar" onclick="editarPromo(${r.id})"><i class="bi bi-pencil"></i></button>`;
        if (perms.cambiarEstado) {
            const toggleLabel = r.estado === 'activa' ? 'Pausar' : 'Activar';
            acciones += `<button class="promo-btn-accion promo-btn-toggle" title="${toggleLabel}" onclick="cambiarEstadoPromo(${r.id}, '${r.estado}')"><i class="bi bi-${r.estado === 'activa' ? 'pause' : 'play'}-circle"></i></button>`;
        }
        acciones += `<button class="promo-btn-accion promo-btn-duplicar" title="Duplicar" onclick="duplicarPromo(${r.id})"><i class="bi bi-files"></i></button>`;
        if (perms.eliminar) acciones += `<button class="promo-btn-accion promo-btn-eliminar" title="Eliminar" onclick="eliminarPromo(${r.id}, '${escHtml(r.nombre)}')"><i class="bi bi-trash"></i></button>`;

        return `<tr>
            <td><code class="text-success fw-bold">#${r.id}</code></td>
            <td><strong>${escHtml(r.nombre)}</strong></td>
            <td>${estadoBadge}</td>
            <td class="text-center"><span class="badge bg-secondary">${r.num_condiciones ?? 0}</span></td>
            <td>${resTxt}</td>
            <td><small>${vigencia}</small></td>
            <td class="text-center"><span class="badge" style="background:var(--verde-oscuro)">${r.prioridad}</span></td>
            <td class="text-center">${acciones}</td>
        </tr>`;
    }).join('');
}

function renderPaginacion(totalReg) {
    regPorPagina = parseInt(document.getElementById('registrosPorPagina').value);
    totalPaginas = Math.max(1, Math.ceil(totalReg / regPorPagina));
    const cont = document.getElementById('paginacion');
    if (!cont) return;

    let html = `<button class="promo-page-btn" onclick="cargarPromociones(${paginaActual - 1})" ${paginaActual === 1 ? 'disabled' : ''}><i class="bi bi-chevron-left"></i></button>`;

    let inicio = Math.max(1, paginaActual - 2);
    let fin = Math.min(totalPaginas, paginaActual + 2);
    if (inicio > 1) {
        html += `<button class="promo-page-btn" onclick="cargarPromociones(1)">1</button>`;
        if (inicio > 2) html += `<span class="promo-page-btn" disabled>...</span>`;
    }

    for (let p = inicio; p <= fin; p++) {
        html += `<button class="promo-page-btn ${p === paginaActual ? 'active' : ''}" onclick="cargarPromociones(${p})">${p}</button>`;
    }

    if (fin < totalPaginas) {
        if (fin < totalPaginas - 1) html += `<span class="promo-page-btn" disabled>...</span>`;
        html += `<button class="promo-page-btn" onclick="cargarPromociones(${totalPaginas})">${totalPaginas}</button>`;
    }

    html += `<button class="promo-page-btn" onclick="cargarPromociones(${paginaActual + 1})" ${paginaActual === totalPaginas ? 'disabled' : ''}><i class="bi bi-chevron-right"></i></button>`;
    cont.innerHTML = html;
}

function actualizarInfoRegistros(total, pagina, rpp) {
    const el = document.getElementById('infoRegistros');
    if (!el) return;
    const desde = (pagina - 1) * rpp + 1;
    const hasta = Math.min(total, pagina * rpp);
    el.textContent = total > 0 ? `Mostrando ${desde}–${hasta} de ${total}` : 'Sin resultados';
}

function editarPromo(id) {
    window.location.href = `promocion_form.php?id=${id}`;
}

async function cambiarEstadoPromo(id, estadoActual) {
    const nuevoEstado = estadoActual === 'activa' ? 'inactiva' : 'activa';
    const label = nuevoEstado === 'activa' ? 'Activar' : 'Pausar';

    const ok = await Swal.fire({
        title: `¿${label} esta promoción?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0E544C',
        cancelButtonText: 'Cancelar',
        confirmButtonText: label
    });
    if (!ok.isConfirmed) return;

    try {
        const resp = await fetch(AJAX_BASE + 'promo_cambiar_estado.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ id, estado: nuevoEstado })
        });
        const data = await resp.json();
        if (!data.success) throw new Error(data.message);
        Swal.fire({ icon:'success', title:'Listo', text: data.message, timer:1500, showConfirmButton:false });
        cargarPromociones(paginaActual);
    } catch (err) {
        Swal.fire({ icon:'error', title:'Error', text: err.message });
    }
}

async function duplicarPromo(id) {
    const ok = await Swal.fire({ title:'¿Duplicar esta promoción?', icon:'question', showCancelButton:true, confirmButtonColor:'#0E544C', cancelButtonText:'Cancelar', confirmButtonText:'Duplicar' });
    if (!ok.isConfirmed) return;

    try {
        const resp = await fetch(AJAX_BASE + 'promo_get_promocion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ id })
        });
        const data = await resp.json();
        if (!data.success) throw new Error(data.message);

        const p = data.promo;
        const payload = {
            promo: { ...p, id: 0, nombre: 'Copia — ' + p.nombre, codigo_interno: '', estado: 'borrador' },
            condiciones: data.condiciones || []
        };

        const resp2 = await fetch(AJAX_BASE + 'promo_guardar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ payload: JSON.stringify(payload) })
        });
        const data2 = await resp2.json();
        if (!data2.success) throw new Error(data2.message);
        Swal.fire({ icon:'success', title:'Duplicada', text:'La promoción fue duplicada como borrador.', timer:1800, showConfirmButton:false });
        cargarPromociones(paginaActual);
    } catch (err) {
        Swal.fire({ icon:'error', title:'Error', text: err.message });
    }
}

async function eliminarPromo(id, nombre) {
    const ok = await Swal.fire({
        title: '¿Eliminar promoción?',
        html: `<strong>${escHtml(nombre)}</strong> se archivará y no podrá reactivarse.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Cancelar',
        confirmButtonText: 'Eliminar'
    });
    if (!ok.isConfirmed) return;

    try {
        const resp = await fetch(AJAX_BASE + 'promo_eliminar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ id })
        });
        const data = await resp.json();
        if (!data.success) throw new Error(data.message);
        Swal.fire({ icon:'success', title:'Eliminada', timer:1500, showConfirmButton:false });
        cargarPromociones(paginaActual);
    } catch (err) {
        Swal.fire({ icon:'error', title:'Error', text: err.message });
    }
}

/* ============================================================
   PÁGINA DE FORMULARIO
   ============================================================ */
async function iniciarFormulario() {
    // Si es modo edición, cargar datos
    if (window.PROMO_ID > 0) {
        await cargarPromocionParaEditar(window.PROMO_ID);
    }
    // Inicializar Select2 en los selectores que ya existen
    inicializarSelect2Global();
    actualizarPreview();
}

async function cargarPromocionParaEditar(id) {
    try {
        const resp = await fetch(AJAX_BASE + 'promo_get_promocion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ id })
        });
        const data = await resp.json();
        if (!data.success) throw new Error(data.message);

        poblarFormulario(data.promo, data.condiciones);
    } catch (err) {
        Swal.fire({ icon:'error', title:'Error al cargar', text: err.message });
    }
}

function poblarFormulario(p, condiciones) {
    setVal('promoNombre', p.nombre);
    setVal('promoCodigo', p.codigo_interno || '');
    setVal('promoDesc', p.descripcion_interna || '');
    setVal('promoFechaInicio', p.fecha_inicio || '');
    setVal('promoFechaFin', p.fecha_fin || '');
    setVal('promoEstado', p.estado);
    setVal('promoPrioridad', p.prioridad);
    setCheck('promoAutomatico', p.ejecucion_automatica == 1);
    setCheck('promoCombinable', p.combinable == 1);
    setCheck('promoUsoUnico', p.uso_unico_cliente == 1);
    setCheck('promoAutorizacion', p.requiere_autorizacion == 1);
    setVal('resultadoValor', p.resultado_valor);
    setVal('resultadoMaxCS', p.descuento_maximo_cs || '');
    setVal('usosMaximos', p.usos_maximos || '');

    // Objetivo
    seleccionarObjetivo(p.objetivo_descuento || 'todos');
    if (p.objetivo_get_y_cant) setVal('objetivoGetYCant', p.objetivo_get_y_cant);
    if (p.objetivo_upgrade_de) setVal('objetivoUpgradeDe', p.objetivo_upgrade_de);
    if (p.objetivo_upgrade_a)  setVal('objetivoUpgradeA', p.objetivo_upgrade_a);

    // Resultado
    seleccionarResultado(p.resultado_tipo || 'pct_producto');

    // Condiciones
    if (condiciones && condiciones.length > 0) {
        condiciones.forEach(c => restaurarCondicion(c));
    }

    actualizarPreview();
}

/* ============================================================
   RULE BUILDER — CONDICIONES
   ============================================================ */
const COND_CONFIG = {
    // Grupo A — Contexto
    dia_semana:   { tipo:'A', label:'📅 Día de semana',           buildFn: buildDiaSemana   },
    horario:      { tipo:'A', label:'🕐 Horario',                  buildFn: buildHorario      },
    sucursal:     { tipo:'A', label:'🏪 Sucursal',                  buildFn: buildSucursal     },
    tipo_cliente: { tipo:'A', label:'👤 Tipo de cliente',           buildFn: buildTipoCliente  },
    canal_venta:  { tipo:'A', label:'📱 Canal de venta',            buildFn: buildCanalVenta   },
    // Grupo B — Carrito
    producto:      { tipo:'B', label:'🧃 Producto específico',      buildFn: buildProducto     },
    grupo_producto:{ tipo:'B', label:'📦 Grupo / Subgrupo',         buildFn: buildGrupoProducto},
    tamano:        { tipo:'B', label:'📏 Tamaño',                   buildFn: buildTamano       },
    cantidad_min:  { tipo:'B', label:'🔢 Cantidad mínima',          buildFn: buildCantidadMin  },
    monto_min:     { tipo:'B', label:'💰 Monto mínimo de factura',  buildFn: buildMontoMin     },
    combo:         { tipo:'B', label:'🎯 Combo X',                  buildFn: buildCombo        }
};

function agregarCondicion(tipo, nombre, valorJson = null) {
    const cfg = COND_CONFIG[nombre];
    if (!cfg) return;

    condicionCounter++;
    const uid = `cond_${condicionCounter}`;

    const card = document.createElement('div');
    card.className = 'promo-condition-card';
    card.id = uid;
    card.dataset.tipo   = tipo;
    card.dataset.nombre = nombre;

    const badgeClass = tipo === 'A' ? 'promo-badge-a' : 'promo-badge-b';
    card.innerHTML = `
        <span class="${badgeClass}">${tipo}</span>
        <span class="promo-cond-name">${cfg.label}</span>
        <div class="promo-cond-fields" id="fields_${uid}"></div>
        <button type="button" class="promo-cond-del" onclick="eliminarCondicion('${uid}')" title="Eliminar">✕</button>`;

    const contenedor = document.getElementById('contenedorCondiciones');
    document.getElementById('sinCondiciones')?.style.setProperty('display','none');
    contenedor.appendChild(card);

    // Construir los campos dinámicos
    cfg.buildFn(`fields_${uid}`, valorJson);

    // Si hay Select2 nuevos en la tarjeta, inicializarlos
    inicializarSelect2Global();
    actualizarContadorCondiciones();
    return uid;
}

function restaurarCondicion(c) {
    const uid = agregarCondicion(c.tipo_cond, c.nombre_cond, c.valor_json);
    // uid ahora contiene los campos ya construidos con valorJson aplicado
}

function eliminarCondicion(uid) {
    document.getElementById(uid)?.remove();
    actualizarContadorCondiciones();
    const cont = document.getElementById('contenedorCondiciones');
    if (cont.querySelectorAll('.promo-condition-card').length === 0) {
        document.getElementById('sinCondiciones').style.removeProperty('display');
    }
}

function actualizarContadorCondiciones() {
    const n = document.querySelectorAll('.promo-condition-card').length;
    const badge = document.getElementById('contadorCondiciones');
    if (badge) badge.textContent = n;
}

/* ── BUILD FUNCTIONS para cada tipo de condición ── */

function buildDiaSemana(containerId, val) {
    const dias = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
    const selec = val?.dias || [];
    const pills = dias.map(d => {
        const act = selec.includes(d) ? 'active' : '';
        return `<label class="promo-pill ${act}">
            <input type="checkbox" name="dia[]" value="${d}" ${act?'checked':''} onchange="togglePill(this)"> ${d}
        </label>`;
    }).join('');
    document.getElementById(containerId).innerHTML = `<div class="promo-pills">${pills}</div>`;
}

function buildHorario(containerId, val) {
    const desde = val?.desde || '';
    const hasta = val?.hasta || '';
    document.getElementById(containerId).innerHTML = `
        <div class="d-flex align-items-center gap-2">
            <label class="promo-label mb-0">Desde</label>
            <input type="time" class="form-control form-control-sm" name="horario_desde" value="${desde}" style="width:120px;">
            <label class="promo-label mb-0">Hasta</label>
            <input type="time" class="form-control form-control-sm" name="horario_hasta" value="${hasta}" style="width:120px;">
        </div>`;
}

function buildFechaRango(containerId, val) {
    const desde = val?.desde || '';
    const hasta = val?.hasta || '';
    document.getElementById(containerId).innerHTML = `
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <label class="promo-label mb-0">Desde</label>
            <input type="date" class="form-control form-control-sm" name="fecha_desde" value="${desde}" style="width:150px;">
            <label class="promo-label mb-0">Hasta</label>
            <input type="date" class="form-control form-control-sm" name="fecha_hasta" value="${hasta}" style="width:150px;">
        </div>`;
}

async function buildSucursal(containerId, val) {
    const el = document.getElementById(containerId);
    el.innerHTML = `<span class="text-muted small"><i class="bi bi-arrow-repeat spin-icon"></i> Cargando sucursales…</span>`;

    try {
        const resp = await fetch(AJAX_BASE + 'promo_get_grupos.php?tipo=sucursales');
        const data = await resp.json();
        if (!data.success) throw new Error(data.message);

        const selec = val?.ids || [];
        const pills = (data.data || []).map(s => {
            const act = selec.includes(String(s.id)) ? 'active' : '';
            return `<label class="promo-pill ${act}">
                <input type="checkbox" name="sucursal[]" value="${s.id}" ${act?'checked':''} onchange="togglePill(this)"> ${escHtml(s.nombre || s.Nombre)}
            </label>`;
        }).join('');
        el.innerHTML = `<div class="promo-pills">${pills || '<span class="text-muted small">Sin sucursales</span>'}</div>`;
    } catch (err) {
        el.innerHTML = `<span class="text-danger small">${err.message}</span>`;
    }
}

function buildTipoCliente(containerId, val) {
    const tipos = ['Club','General','PedidosYa','Colaborador','Empresa afiliada','Nuevo: delivery propio'];
    const selec = val?.tipos || [];
    const pills = tipos.map(t => {
        const act = selec.includes(t) ? 'active' : '';
        return `<label class="promo-pill ${act}">
            <input type="checkbox" name="tipo_cliente[]" value="${t}" ${act?'checked':''} onchange="togglePill(this)"> ${t}
        </label>`;
    }).join('');
    document.getElementById(containerId).innerHTML = `<div class="promo-pills">${pills}</div>`;
}

function buildCanalVenta(containerId, val) {
    const canales = ['página web','general'];
    const selec = val?.canales || [];
    const pills = canales.map(c => {
        const act = selec.includes(c) ? 'active' : '';
        return `<label class="promo-pill ${act}">
            <input type="checkbox" name="canal[]" value="${c}" ${act?'checked':''} onchange="togglePill(this)"> ${c}
        </label>`;
    }).join('');
    document.getElementById(containerId).innerHTML = `<div class="promo-pills">${pills}</div>`;
}

function buildProducto(containerId, val) {
    const uid = containerId.replace('fields_','');
    const prodId  = val?.id_producto || '';
    const cantMin = val?.cantidad_min || 1;
    document.getElementById(containerId).innerHTML = `
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <select class="promo-select2-product" id="sel_prod_${uid}" name="producto_id" style="min-width:280px;"
                    data-initial-id="${prodId}" data-initial-text="${escHtml(val?.nombre_producto||'')}">
                ${prodId ? `<option value="${prodId}" selected>${escHtml(val?.nombre_producto||'')}</option>` : '<option value="">Buscar producto…</option>'}
            </select>
            <label class="promo-label mb-0 ms-1">Cant. mín.</label>
            <input type="number" class="form-control form-control-sm" name="cantidad_min_prod" value="${cantMin}" min="1" style="width:80px;">
        </div>`;
    // Inicializar Select2 para este nuevo select
    setTimeout(() => {
        $(`#sel_prod_${uid}`).select2({
            theme: 'bootstrap-5',
            placeholder: 'Buscar producto…',
            minimumInputLength: 1,
            ajax: {
                url: AJAX_BASE + 'promo_get_productos.php',
                type: 'POST',
                dataType: 'json',
                delay: 250,
                data: params => ({ term: params.term }),
                processResults: data => ({ results: data.success ? data.data : [] })
            }
        });
    }, 50);
}

async function buildGrupoProducto(containerId, val) {
    const uid = containerId.replace('fields_','');
    const cantidad = val?.cantidad || 1;
    document.getElementById(containerId).innerHTML = `
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <select class="form-select form-select-sm" id="sel_grupo_${uid}" name="grupo_id" style="width:180px;" onchange="cargarSubgruposEnCard('${uid}')">
                <option value="">— Grupo —</option>
            </select>
            <select class="form-select form-select-sm" id="sel_subgrupo_${uid}" name="subgrupo_id" style="width:180px;">
                <option value="">— Subgrupo (opcional) —</option>
            </select>
            <label class="promo-label mb-0 ms-1">Cant.</label>
            <input type="number" class="form-control form-control-sm" name="cantidad_grupo" value="${cantidad}" min="1" style="width:70px;">
        </div>
        <div class="form-text small text-muted mt-1"><i class="bi bi-info-circle"></i> Si no se elige subgrupo se consideran todos los productos del grupo.</div>`;
    try {
        const resp = await fetch(AJAX_BASE + 'promo_get_grupos.php?tipo=grupos');
        const data = await resp.json();
        const sel = document.getElementById(`sel_grupo_${uid}`);
        (data.data || []).forEach(g => {
            const opt = new Option(g.Nombre || g.nombre, g.id, false, String(g.id) === String(val?.grupo_id));
            sel.add(opt);
        });
        if (val?.grupo_id) await cargarSubgruposEnCard(uid, val.subgrupo_id);
    } catch (e) { /* silencioso */ }
}

async function cargarSubgruposEnCard(uid, seleccionado = null) {
    const grupoId  = document.getElementById(`sel_grupo_${uid}`)?.value;
    const selSub   = document.getElementById(`sel_subgrupo_${uid}`);
    if (!selSub) return;
    selSub.innerHTML = '<option value="">— Subgrupo (opcional) —</option>';
    if (!grupoId) return;
    try {
        const resp = await fetch(`${AJAX_BASE}promo_get_grupos.php?tipo=subgrupos&id_grupo=${grupoId}`);
        const data = await resp.json();
        (data.data || []).forEach(s => {
            const opt = new Option(s.Nombre || s.nombre, s.id, false, String(s.id) === String(seleccionado));
            selSub.add(opt);
        });
    } catch(e) { /* silencioso */ }
}

function buildTamano(containerId, val) {
    const tamanos = ['16oz','20oz'];
    const selec = val?.tamanos || [];
    const cantidad = val?.cantidad || 1;
    const pills = tamanos.map(t => {
        const act = selec.includes(t) ? 'active' : '';
        return `<label class="promo-pill ${act}">
            <input type="checkbox" name="tamano[]" value="${t}" ${act?'checked':''} onchange="togglePill(this)"> ${t}
        </label>`;
    }).join('');
    document.getElementById(containerId).innerHTML = `
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="promo-pills">${pills}</div>
            <label class="promo-label mb-0 ms-1">Cant.</label>
            <input type="number" class="form-control form-control-sm" name="cantidad_tamano" value="${cantidad}" min="1" style="width:70px;">
        </div>`;
}

function buildCantidadMin(containerId, val) {
    const cantidad = val?.cantidad || 1;
    document.getElementById(containerId).innerHTML = `
        <div class="d-flex align-items-center gap-2">
            <label class="promo-label mb-0">Mínimo de ítems</label>
            <input type="number" class="form-control form-control-sm" name="cantidad_min_orden" value="${cantidad}" min="1" style="width:100px;">
        </div>`;
}

function buildMontoMin(containerId, val) {
    const monto = val?.monto || 0;
    document.getElementById(containerId).innerHTML = `
        <div class="d-flex align-items-center gap-2">
            <span class="fw-bold text-muted">C$</span>
            <input type="number" class="form-control form-control-sm" name="monto_min" value="${monto}" min="0" step="0.01" style="width:140px;">
        </div>`;
}

function buildCombo(containerId, val) {
    const uid = containerId.replace('fields_','');
    const items = val?.items || [{ id: '', text: '', cant: 1 }];
    
    let html = `<div id="combo_items_${uid}" class="d-flex flex-column gap-2 mb-2">`;
    items.forEach((item, idx) => {
        html += renderComboRow(uid, idx, item);
    });
    html += `</div>
        <button type="button" class="btn btn-sm btn-outline-success" onclick="agregarItemCombo('${uid}')">
            <i class="bi bi-plus"></i> Agregar ítem al combo
        </button>`;
        
    document.getElementById(containerId).innerHTML = html;
    
    // Inicializar Select2 para filas existentes
    setTimeout(() => {
        items.forEach((_, idx) => inicializarSelect2Combo(uid, idx));
    }, 50);
}

function renderComboRow(uid, idx, data = {}) {
    const prodId = data.id || '';
    const text   = data.text || '';
    const cant   = data.cant || 1;
    return `
        <div class="d-flex align-items-center gap-2 combo-row" data-idx="${idx}">
            <select class="promo-select2-product combo-prod-sel" name="combo_prod_${idx}" style="min-width:280px;">
                ${prodId ? `<option value="${prodId}" selected>${escHtml(text)}</option>` : '<option value="">Buscar producto…</option>'}
            </select>
            <label class="promo-label mb-0">Cant.</label>
            <input type="number" class="form-control form-control-sm combo-cant-input" value="${cant}" min="1" style="width:70px;">
            ${idx > 0 ? `<button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.combo-row').remove()"><i class="bi bi-trash"></i></button>` : ''}
        </div>`;
}

function agregarItemCombo(uid) {
    const container = document.getElementById(`combo_items_${uid}`);
    const nextIdx = container.querySelectorAll('.combo-row').length;
    const rowHtml = renderComboRow(uid, nextIdx);
    const wrapper = document.createElement('div');
    wrapper.innerHTML = rowHtml;
    container.appendChild(wrapper.firstElementChild);
    inicializarSelect2Combo(uid, nextIdx);
}

function inicializarSelect2Combo(uid, idx) {
    const sel = $(`#${uid} [name="combo_prod_${idx}"]`);
    sel.select2({
        theme: 'bootstrap-5',
        placeholder: 'Buscar producto…',
        minimumInputLength: 1,
        ajax: {
            url: AJAX_BASE + 'promo_get_productos.php',
            type: 'POST',
            dataType: 'json',
            delay: 250,
            data: params => ({ term: params.term }),
            processResults: data => ({ results: data.success ? data.data : [] })
        }
    });
}

/* ── Toggle de pills ── */
function togglePill(cb) {
    const label = cb.closest('label.promo-pill');
    if (label) {
        label.classList.toggle('active', cb.checked);
    }
}

/* ============================================================
   OBJETIVO DEL DESCUENTO
   ============================================================ */
function seleccionarObjetivo(valor) {
    document.querySelectorAll('.promo-target-card').forEach(c => c.classList.remove('selected'));
    const card = document.querySelector(`.promo-target-card[data-value="${valor}"]`);
    if (card) card.classList.add('selected');
    document.getElementById('objetivoDescuento').value = valor;

    // Mostrar campos extra
    ['extraNthItem','extraGetY','extraUpgrade'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    });
    if (valor === 'nth_item')  document.getElementById('extraNthItem')?.style.setProperty('display','block');
    if (valor === 'get_y')     document.getElementById('extraGetY')?.style.setProperty('display','block');
    if (valor === 'upgrade')   document.getElementById('extraUpgrade')?.style.setProperty('display','block');

    actualizarPreview();
}

/* ============================================================
   RESULTADO (TIPO Y VALOR)
   ============================================================ */
function seleccionarResultado(valor) {
    document.querySelectorAll('.promo-result-card').forEach(c => c.classList.remove('selected'));
    const card = document.querySelector(`.promo-result-card[data-value="${valor}"]`);
    if (card) card.classList.add('selected');
    document.getElementById('resultadoTipo').value = valor;

    const esPct   = valor.startsWith('pct_');
    const label   = esPct ? 'Valor del descuento (%)' : 'Monto fijo (C$)';
    const unidad  = esPct ? '%' : 'C$';

    const labelEl  = document.getElementById('resultadoValorLabel');
    const unidadEl = document.getElementById('resultadoUnidad');
    if (labelEl)  labelEl.innerHTML = label + ' <span class="text-danger">*</span>';
    if (unidadEl) unidadEl.textContent = unidad;

    actualizarPreview();
}

/* ============================================================
   VERIFICACIÓN DE CÓDIGO INTERNO (DEBOUNCE)
   ============================================================ */
let codigoTimerId = null;
function verificarCodigo(valor) {
    clearTimeout(codigoTimerId);
    const icon = document.getElementById('codigoIcon');
    const msg  = document.getElementById('codigoMsg');
    const box  = document.getElementById('codigoStatus');

    if (!valor.trim()) {
        icon.className = 'bi bi-dash';
        box.className = 'input-group-text';
        if (msg) msg.textContent = '';
        return;
    }

    icon.className = 'bi bi-arrow-repeat spin-icon';
    codigoTimerId = setTimeout(async () => {
        try {
            const resp = await fetch(AJAX_BASE + 'promo_verificar_codigo.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ codigo: valor, id: window.PROMO_ID || 0 })
            });
            const data = await resp.json();
            if (data.disponible) {
                icon.className = 'bi bi-check-circle-fill text-success';
                box.className = 'input-group-text codigo-disponible';
                if (msg) { msg.textContent = '✓ Disponible'; msg.className = 'form-text text-success'; }
            } else {
                icon.className = 'bi bi-x-circle-fill text-danger';
                box.className = 'input-group-text codigo-ocupado';
                if (msg) { msg.textContent = '✗ Ya está en uso'; msg.className = 'form-text text-danger'; }
            }
        } catch (e) {
            icon.className = 'bi bi-dash';
            box.className  = 'input-group-text';
        }
    }, 500);
}

/* ============================================================
   VISTA PREVIA EN TIEMPO REAL
   ============================================================ */
function actualizarPreview() {
    const nombre    = document.getElementById('promoNombre')?.value?.trim() || '[Sin nombre]';
    const valor     = document.getElementById('resultadoValor')?.value || '0';
    const tipoRes   = document.getElementById('resultadoTipo')?.value || 'pct_producto';
    const objetivo  = document.getElementById('objetivoDescuento')?.value || 'todos';
    const numCond   = document.querySelectorAll('.promo-condition-card').length;
    const combinable = document.getElementById('promoCombinable')?.checked;
    const automatico = document.getElementById('promoAutomatico')?.checked;
    const estado    = document.getElementById('promoEstado')?.value || 'borrador';

    const resultadoTexto = {
        pct_producto:   `${valor}% de descuento sobre el producto`,
        pct_factura:    `${valor}% de descuento sobre toda la factura`,
        monto_producto: `C$${valor} de descuento sobre el producto`,
        monto_factura:  `C$${valor} de descuento sobre toda la factura`
    }[tipoRes] || '';

    const objetivoTexto = {
        todos:      'a todos los ítems que califican',
        mas_barato: 'solo al ítem más barato',
        get_y:      'a un producto distinto (Get Y)',
        factura:    'sobre el total de la factura',
        upgrade:    'como upgrade de tamaño'
    }[objetivo] || '';

    // Construir texto de condiciones detallado
    const cards = document.querySelectorAll('.promo-condition-card');
    let condsTexto = '';
    if (cards.length === 0) {
        condsTexto = 'sin condiciones de activación';
    } else {
        const descripciones = [];
        cards.forEach(card => {
            const nombre = card.dataset.nombre;
            const fieldsId = card.querySelector('[id^="fields_"]')?.id;
            const val = extraerValoresCondicion(fieldsId, nombre);
            
            switch(nombre) {
                case 'dia_semana': descripciones.push(`Día: ${val.dias?.join(', ') || '...'}`); break;
                case 'horario': descripciones.push(`Hora: ${val.desde}-${val.hasta}`); break;
                case 'sucursal': descripciones.push(`${val.ids?.length} sucursal(es)`); break;
                case 'tipo_cliente': descripciones.push(`Cliente: ${val.tipos?.join('/') || '...'}`); break;
                case 'canal_venta': descripciones.push(`Canal: ${val.canales?.join('/') || '...'}`); break;
                case 'producto': descripciones.push(`${val.cantidad_min}x ${val.nombre_producto || 'Producto'}`); break;
                case 'grupo_producto': descripciones.push(`${val.cantidad || 1}x en ${val.nombre_subgrupo || val.nombre_grupo || 'Grupo'}`); break;
                case 'tamano': descripciones.push(`${val.cantidad || 1}x de ${val.tamanos?.join('/') || '...'}`); break;
                case 'cantidad_min': descripciones.push(`mín. ${val.cantidad} ítems`); break;
                case 'monto_min': descripciones.push(`mín. C$${val.monto}`); break;
                case 'combo': descripciones.push(`Combo de ${val.items?.length || 0} ítems`); break;
            }
        });
        condsTexto = `si cumple: [${descripciones.join(' + ')}]`;
    }

    const combText   = combinable ? '— combinable' : '';
    const autoText   = automatico ? '— automática' : '';
    const estadoEmoji = { borrador:'📝', activa:'✅', inactiva:'⏸', archivada:'🗄' }[estado] || '';

    // Si es upgrade, el texto es diferente
    let preview = '';
    if (objetivo === 'upgrade') {
        const de = document.getElementById('objetivoUpgradeDe')?.value || '...';
        const a  = document.getElementById('objetivoUpgradeA')?.value || '...';
        preview = `${estadoEmoji} "${nombre}" aplica un upgrade de ${de} a ${a}, con ${condsTexto}. ${autoText} ${combText}`;
    } else {
        preview = `${estadoEmoji} "${nombre}" aplica ${resultadoTexto} ${objetivoTexto}, con ${condsTexto}. ${autoText} ${combText}`;
    }

    const el = document.getElementById('vistaPreviewTexto');
    if (el) el.textContent = preview;

    // Si es upgrade, el resultado es opcional
    const resSection = document.querySelector('.promo-result-card')?.closest('.promo-section');
    if (objetivo === 'upgrade') {
        resSection?.classList.add('opacity-50');
    } else {
        resSection?.classList.remove('opacity-50');
    }
}

/* ============================================================
   GUARDAR PROMOCIÓN
   ============================================================ */
async function guardarPromo() {
    // Validación básica
    const nombre = document.getElementById('promoNombre')?.value?.trim();
    if (!nombre) {
        Swal.fire({ icon:'warning', title:'Nombre requerido', text:'El nombre de la promoción es obligatorio.', confirmButtonColor:'#0E544C' });
        document.getElementById('promoNombre')?.focus();
        return;
    }

    const objetivo = document.getElementById('objetivoDescuento')?.value || 'todos';
    const resultadoValor = parseFloat(document.getElementById('resultadoValor')?.value || 0);
    
    // Validación de valor (se ignora para Upgrade)
    if (objetivo !== 'upgrade' && resultadoValor <= 0) {
        Swal.fire({ icon:'warning', title:'Valor inválido', text:'El valor del descuento debe ser mayor a 0.', confirmButtonColor:'#0E544C' });
        return;
    }

    // Serializar condiciones
    const condiciones = serializarCondiciones();

    // Construir payload
    const promo = {
        id:                    window.PROMO_ID || 0,
        nombre:                nombre,
        codigo_interno:        document.getElementById('promoCodigo')?.value?.trim() || '',
        descripcion_interna:   document.getElementById('promoDesc')?.value?.trim() || '',
        fecha_inicio:          document.getElementById('promoFechaInicio')?.value || null,
        fecha_fin:             document.getElementById('promoFechaFin')?.value || null,
        prioridad:             parseInt(document.getElementById('promoPrioridad')?.value) || 10,
        estado:                document.getElementById('promoEstado')?.value || 'borrador',
        ejecucion_automatica:  document.getElementById('promoAutomatico')?.checked ? 1 : 0,
        combinable:            document.getElementById('promoCombinable')?.checked ? 1 : 0,
        uso_unico_cliente:     document.getElementById('promoUsoUnico')?.checked ? 1 : 0,
        requiere_autorizacion: document.getElementById('promoAutorizacion')?.checked ? 1 : 0,
        objetivo_descuento:    objetivo,
        objetivo_get_y_prod:   document.getElementById('objetivoGetYProd')?.value || null,
        objetivo_get_y_cant:   document.getElementById('objetivoGetYCant')?.value || 1,
        objetivo_upgrade_de:   document.getElementById('objetivoUpgradeDe')?.value || null,
        objetivo_upgrade_a:    document.getElementById('objetivoUpgradeA')?.value || null,
        resultado_tipo:        objetivo === 'upgrade' ? null : (document.getElementById('resultadoTipo')?.value || 'pct_producto'),
        resultado_valor:       objetivo === 'upgrade' ? 0 : resultadoValor,
        descuento_maximo_cs:   objetivo === 'upgrade' ? null : (document.getElementById('resultadoMaxCS')?.value || null),
        usos_maximos:          document.getElementById('usosMaximos')?.value || null
    };

    const payload = { promo, condiciones };

    // Mostrar spinner
    Swal.fire({ title:'Guardando…', allowOutsideClick:false, didOpen:()=>Swal.showLoading() });

    try {
        const resp = await fetch(AJAX_BASE + 'promo_guardar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ payload: JSON.stringify(payload) })
        });
        const data = await resp.json();
        if (!data.success) throw new Error(data.message);

        await Swal.fire({ icon:'success', title:'Guardado', text: data.message, timer:1800, showConfirmButton:false });
        window.location.href = 'promociones.php';

    } catch (err) {
        Swal.fire({ icon:'error', title:'Error al guardar', text: err.message, confirmButtonColor:'#0E544C' });
    }
}

/* ── Serializar todas las condiciones a un array de objetos ── */
function serializarCondiciones() {
    const cards = document.querySelectorAll('.promo-condition-card');
    const result = [];

    cards.forEach((card, idx) => {
        const tipo   = card.dataset.tipo;
        const nombre = card.dataset.nombre;
        const fieldsId = card.querySelector('[id^="fields_"]')?.id;
        if (!fieldsId) return;

        const valor_json = extraerValoresCondicion(fieldsId, nombre);
        result.push({ tipo_cond: tipo, nombre_cond: nombre, valor_json, orden: idx });
    });

    return result;
}

function extraerValoresCondicion(fieldsId, nombre) {
    const container = document.getElementById(fieldsId);
    if (!container) return {};

    switch (nombre) {
        case 'dia_semana': {
            const dias = [...container.querySelectorAll('input[name="dia[]"]:checked')].map(c => c.value);
            return { dias };
        }
        case 'horario': {
            return {
                desde: container.querySelector('[name="horario_desde"]')?.value || '',
                hasta: container.querySelector('[name="horario_hasta"]')?.value || ''
            };
        }
        case 'fecha_rango': {
            return {
                desde: container.querySelector('[name="fecha_desde"]')?.value || '',
                hasta: container.querySelector('[name="fecha_hasta"]')?.value || ''
            };
        }
        case 'sucursal': {
            const ids = [...container.querySelectorAll('input[name="sucursal[]"]:checked')].map(c => c.value);
            return { ids };
        }
        case 'tipo_cliente': {
            const tipos = [...container.querySelectorAll('input[name="tipo_cliente[]"]:checked')].map(c => c.value);
            return { tipos };
        }
        case 'canal_venta': {
            const canales = [...container.querySelectorAll('input[name="canal[]"]:checked')].map(c => c.value);
            return { canales };
        }
        case 'producto': {
            const sel = container.querySelector('[name="producto_id"]');
            const cantMin = container.querySelector('[name="cantidad_min_prod"]')?.value || 1;
            return {
                id_producto: sel?.value || '',
                nombre_producto: sel?.options[sel.selectedIndex]?.text || '',
                cantidad_min: parseInt(cantMin)
            };
        }
        case 'grupo_producto': {
            const grupoSel   = container.querySelector('[name="grupo_id"]');
            const subgrupoSel = container.querySelector('[name="subgrupo_id"]');
            const cantidad    = container.querySelector('[name="cantidad_grupo"]')?.value || 1;
            return {
                grupo_id:       grupoSel?.value || '',
                nombre_grupo:   grupoSel?.options[grupoSel.selectedIndex]?.text || '',
                subgrupo_id:    subgrupoSel?.value || '',
                nombre_subgrupo:subgrupoSel?.options[subgrupoSel.selectedIndex]?.text || '',
                cantidad:       parseInt(cantidad)
            };
        }
        case 'tamano': {
            const tamanos = [...container.querySelectorAll('input[name="tamano[]"]:checked')].map(c => c.value);
            const cantidad = container.querySelector('[name="cantidad_tamano"]')?.value || 1;
            return { tamanos, cantidad: parseInt(cantidad) };
        }
        case 'cantidad_min': {
            return { cantidad: parseInt(container.querySelector('[name="cantidad_min_orden"]')?.value || 1) };
        }
        case 'monto_min': {
            return { monto: parseFloat(container.querySelector('[name="monto_min"]')?.value || 0) };
        }
        case 'combo': {
            const items = [];
            container.querySelectorAll('.combo-row').forEach(row => {
                const sel = row.querySelector('.combo-prod-sel');
                if (sel?.value) {
                    items.push({
                        id: sel.value,
                        text: sel.options[sel.selectedIndex]?.text || '',
                        cant: parseInt(row.querySelector('.combo-cant-input')?.value || 1)
                    });
                }
            });
            return { items };
        }
        default: return {};
    }
}

/* ============================================================
   SELECT2 GLOBAL
   ============================================================ */
function inicializarSelect2Global() {
    if (typeof $ === 'undefined' || typeof $.fn.select2 === 'undefined') return;

    // Select2 para el campo "Get Y" en el objetivo
    $('#objetivoGetYProd').select2({
        theme: 'bootstrap-5',
        placeholder: 'Buscar producto…',
        minimumInputLength: 1,
        ajax: {
            url: AJAX_BASE + 'promo_get_productos.php',
            type: 'POST',
            dataType: 'json',
            delay: 250,
            data: params => ({ term: params.term }),
            processResults: data => ({ results: data.success ? data.data : [] })
        }
    });
}

/* ============================================================
   UTILIDADES
   ============================================================ */
function setVal(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value ?? '';
}
function setCheck(id, bool) {
    const el = document.getElementById(id);
    if (el) el.checked = bool;
}
function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
