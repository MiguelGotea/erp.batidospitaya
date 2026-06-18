'use strict';
/* ============================================================
   Pronóstico de Abastecimiento — JavaScript
   modulos/productos/js/pronostico_abastecimiento.js

   Grupos mostrados: B, D, F, G
   Proyección: 4 semanas hacia adelante
   Ronda 1:  stock D-1 vía AJAX bulk (Kardex real)
   Rondas 2+: cadena cliente: smf − (cons_diario × ciclo / desp_factor)
   ============================================================ */

const PA_GRUPOS   = ['B', 'D', 'F', 'G'];
const PA_LABELS   = {
    B: 'Congelados', D: 'Desechables',
    F: 'Secos y Preparación', G: 'Productos de Mostrador'
};
const PA_SEMANAS  = 4;   // semanas de proyección hacia el futuro

// ── Inicialización ────────────────────────────────────────────
$(document).ready(() => {
    cargarSucursales();
    $('#pa-btn-calcular').on('click', calcularAgenda);
});

// ── Cargar sucursales ─────────────────────────────────────────
function cargarSucursales() {
    $.getJSON('ajax/configuracion_logistica_get_sucursales.php', res => {
        if (res.success && res.sucursales.length) {
            res.sucursales.forEach(s => {
                $('#pa-sucursal').append(`<option value="${s.codigo}">${s.nombre}</option>`);
            });
            $('#pa-sucursal').select2({ width: '100%', dropdownAutoWidth: true });
        }
    });
}

// ── Utilidades de fecha ───────────────────────────────────────
function addDaysStr(dateStr, days) {
    const d = new Date(dateStr + 'T12:00:00');
    d.setDate(d.getDate() + days);
    return d.toISOString().split('T')[0];
}
function todayStr() {
    return new Date().toISOString().split('T')[0];
}
function limitStr() {
    return addDaysStr(todayStr(), PA_SEMANAS * 7);
}
function formatDateHeader(dateStr) {
    const DIAS  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    const MESES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                   'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    const d = new Date(dateStr + 'T12:00:00');
    return {
        weekday : DIAS[d.getDay()],
        day     : d.getDate(),
        month   : MESES[d.getMonth()],
        year    : d.getFullYear()
    };
}
function fmt2(v) {
    if (v === null || v === undefined) return '<span class="pa-na">N/A</span>';
    return Number(v).toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function esc(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function setLoaderStep(msg) {
    $('#pa-loader-step').text(msg);
}

// ── Mostrar / ocultar paneles ─────────────────────────────────
function showLoader()  {
    $('#pa-panel-inicial,#pa-panel-datos').addClass('d-none');
    $('#pa-loader').removeClass('d-none');
}
function hideLoader()  { $('#pa-loader').addClass('d-none'); }
function showInicial() {
    hideLoader();
    $('#pa-panel-inicial').removeClass('d-none');
    $('#pa-panel-datos').addClass('d-none');
}
function showDatos()   {
    hideLoader();
    $('#pa-panel-datos').removeClass('d-none');
}

// ═══════════════════════════════════════════════════════════════
//  CALCULAR AGENDA — flujo principal
// ═══════════════════════════════════════════════════════════════
async function calcularAgenda() {
    const semDesde = parseInt($('#pa-desde').val());
    const semHasta = parseInt($('#pa-hasta').val());
    const semCorte = parseInt($('#pa-corte').val());
    const sucursal = $('#pa-sucursal').val();

    // ── Validaciones ──────────────────────────────────────────
    const errores = [];
    if (!semDesde || !semHasta) errores.push('Ingresa el rango de semanas (Desde / Hasta).');
    if (!semCorte)              errores.push('Ingresa la Semana de Corte para el pronóstico D-1.');
    if (!sucursal)              errores.push('Selecciona una sucursal.');
    if (semDesde && semHasta && semDesde > semHasta)
        errores.push('La semana "Desde" debe ser ≤ que "Hasta".');
    if (semCorte && semDesde && semHasta && (semCorte < semDesde || semCorte > semHasta))
        errores.push(`La semana de corte (${semCorte}) debe estar dentro del rango ${semDesde}–${semHasta}.`);

    if (errores.length) {
        Swal.fire({
            icon: 'warning', title: 'Datos incompletos',
            html: errores.map(e => `<div>• ${e}</div>`).join(''),
            confirmButtonColor: '#0ea5e9'
        });
        return;
    }

    showLoader();
    setLoaderStep('Analizando consumo histórico…');

    try {
        // ── PASO 1: Pedido Sugerido ───────────────────────────
        const fdPedido = new FormData();
        fdPedido.append('semana_desde_num', semDesde);
        fdPedido.append('semana_hasta_num',  semHasta);
        fdPedido.append('cod_sucursal',      sucursal);

        const resPedido = await fetch('ajax/pedido_sugerido_calcular_v2.php', {
            method: 'POST', body: fdPedido
        }).then(r => r.json());

        if (!resPedido.ok) {
            hideLoader(); showInicial();
            Swal.fire({ icon: 'error', title: 'Error al calcular', text: resPedido.msg, confirmButtonColor: '#0ea5e9' });
            return;
        }

        // ── PASO 2: Filtrar solo B, D, F, G ──────────────────
        const todosProd = resPedido.productos || [];
        const prodFiltrados = todosProd.filter(p => PA_GRUPOS.includes(p.categoria_insumo));

        if (!prodFiltrados.length) {
            hideLoader(); showInicial();
            Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No hay productos en los grupos B, D, F o G para esta sucursal en el período seleccionado.', confirmButtonColor: '#0ea5e9' });
            return;
        }

        // ── PASO 3: Separar grupos con/sin plan de despacho ──
        const porCategoria = {};
        PA_GRUPOS.forEach(c => porCategoria[c] = []);
        prodFiltrados.forEach(p => { porCategoria[p.categoria_insumo]?.push(p); });

        const sinPlan  = {}; // cat → items
        const conPlan  = {}; // cat → items

        PA_GRUPOS.forEach(cat => {
            const items = porCategoria[cat];
            if (!items.length) return;
            if (!items[0].fecha_proximo_despacho) sinPlan[cat]  = items;
            else                                   conPlan[cat]  = items;
        });

        // ── PASO 4: Construir agenda (fechas × categorías) ────
        // agendaMap: { 'YYYY-MM-DD': { B: {items,round}, D: {...} } }
        const hoy   = todayStr();
        const limit = limitStr();
        const agendaMap = {};

        Object.entries(conPlan).forEach(([cat, items]) => {
            const p0    = items[0];
            const cycle = Math.max(1, Math.round(p0.dias_ciclo || 7));
            let cur     = p0.fecha_proximo_despacho;
            let round   = 1;

            while (cur <= limit) {
                if (!agendaMap[cur]) agendaMap[cur] = {};
                agendaMap[cur][cat] = { items, round };
                cur = addDaysStr(cur, cycle);
                round++;
            }
        });

        const fechasOrdenadas = Object.keys(agendaMap).sort();

        if (!fechasOrdenadas.length) {
            hideLoader(); showInicial();
            Swal.fire({ icon: 'info', title: 'Sin despachos próximos', text: 'No hay despachos programados en las próximas 4 semanas para los grupos seleccionados.', confirmButtonColor: '#0ea5e9' });
            return;
        }

        // ── PASO 5: Pronóstico D-1 bulk (Ronda 1 únicamente) ─
        setLoaderStep('Calculando pronóstico de stock (Ronda 1)…');

        const prodConPlan = Object.values(conPlan).flat();
        const stockRonda1 = {}; // id_pp → stock en uso (null = sin datos Kardex)

        if (prodConPlan.length) {
            const fdPron = new FormData();
            fdPron.append('semana_desde', semDesde);
            fdPron.append('semana_hasta', semHasta);
            fdPron.append('semana_corte', semCorte);
            fdPron.append('cod_sucursal', sucursal);

            prodConPlan.forEach(p => {
                fdPron.append('ids_pp[]', p.id_pp);
                // D-1 = fecha_proximo_despacho − 1 día
                const d1 = addDaysStr(p.fecha_proximo_despacho, -1);
                fdPron.append(`fechas_d1[${p.id_pp}]`, d1);
            });

            const resPron = await fetch('ajax/pedido_sugerido_pronostico_v2.php', {
                method: 'POST', body: fdPron
            }).then(r => r.json());

            if (resPron.ok) {
                Object.entries(resPron.stocks || {}).forEach(([idpp, val]) => {
                    stockRonda1[String(idpp)] = val; // float | null
                });
            }
        }

        // ── PASO 6: Calcular stock D-1 y despacho por ronda ──
        // Para cada slot (fecha, cat), anotar en cada producto:
        //   _stockD1Paq  → stock D-1 en paquetes
        //   _despachoPron → despacho sugerido en paquetes

        fechasOrdenadas.forEach(fecha => {
            Object.entries(agendaMap[fecha]).forEach(([cat, slot]) => {
                slot.items.forEach(p => {
                    const df  = (p.despacho_factor > 0) ? p.despacho_factor : 1;
                    const smf = p.stock_max_final ?? 0;
                    const cd  = p.cons_diario  ?? 0;
                    const dc  = p.dias_ciclo   ?? 7;

                    let stockD1Paq;

                    if (slot.round === 1) {
                        // Ronda 1: datos reales del Kardex
                        const stockUso = stockRonda1[String(p.id_pp)];
                        stockD1Paq = (stockUso !== null && stockUso !== undefined)
                            ? stockUso / df
                            : null;
                    } else {
                        // Rondas 2+: cadena de proyección
                        // Asumimos que la ronda anterior despachó hasta smf.
                        // Consumo durante un ciclo: (cd × dc) / df paquetes
                        const consumoCiclo = (cd * dc) / df;
                        stockD1Paq = Math.max(0, smf - consumoCiclo);
                    }

                    // Guardar por ronda para que cada slot tenga su propio valor
                    if (!p._porRonda) p._porRonda = {};
                    p._porRonda[slot.round] = {
                        stockD1Paq,
                        despachoPron: stockD1Paq !== null
                            ? Math.max(0, Math.ceil(smf - stockD1Paq))
                            : null
                    };
                });
            });
        });

        // ── PASO 7: Renderizar ────────────────────────────────
        setLoaderStep('Generando agenda…');
        renderAgenda(agendaMap, fechasOrdenadas, sinPlan);
        showDatos();

    } catch (err) {
        console.error('calcularAgenda:', err);
        hideLoader(); showInicial();
        Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo comunicar con el servidor.', confirmButtonColor: '#0ea5e9' });
    }
}

// ═══════════════════════════════════════════════════════════════
//  RENDER AGENDA
// ═══════════════════════════════════════════════════════════════
function renderAgenda(agendaMap, fechasOrdenadas, sinPlan) {

    // ── Warnings de grupos sin plan ───────────────────────────
    const $warnings = $('#pa-warnings').empty();
    const sinPlanKeys = Object.keys(sinPlan);
    if (sinPlanKeys.length) {
        const labels = sinPlanKeys.map(c => `<strong>${c} — ${PA_LABELS[c]}</strong>`).join(', ');
        $warnings.html(`
            <div class="pa-warning-banner">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>Los siguientes grupos <strong>no tienen Plan de Despacho activo</strong> configurado para esta sucursal y no se muestran en la agenda: ${labels}.
                Verifica la configuración en <em>Configuración Logística</em>.</div>
            </div>
        `).removeClass('d-none');
    } else {
        $warnings.addClass('d-none');
    }

    // ── Construir HTML de agenda ──────────────────────────────
    let html = '';

    fechasOrdenadas.forEach(fecha => {
        const info = formatDateHeader(fecha);
        const cats = agendaMap[fecha];

        html += `
        <div class="pa-date-block">
            <div class="pa-date-header">
                <div class="pa-date-pill">
                    <div class="pa-date-day-num">${info.day}</div>
                    <div class="pa-date-info">
                        <div class="pa-date-weekday">${info.weekday}</div>
                        <div class="pa-date-monthyear">${info.month} ${info.year}</div>
                    </div>
                </div>
                <div class="pa-date-line"></div>
            </div>
            <div class="pa-cats-row">
                ${buildCatsHtml(cats)}
            </div>
        </div>`;
    });

    $('#pa-agenda').html(html || '<p class="text-muted text-center p-5">Sin datos para mostrar.</p>');
}

function buildCatsHtml(cats) {
    let html = '';
    PA_GRUPOS.forEach(cat => {
        if (!cats[cat]) return;
        const { items, round } = cats[cat];
        const roundLabel = round === 1
            ? '<span class="pa-round-badge" style="background:rgba(56,189,248,0.15);color:#38bdf8;">Ronda 1 · Inventario Real</span>'
            : `<span class="pa-round-badge">Ronda ${round} · Proyección</span>`;

        html += `
        <div class="pa-cat-card pa-cat-${cat.toLowerCase()}">
            <div class="pa-cat-header">
                <div class="pa-cat-badge">${cat}</div>
                <span>${PA_LABELS[cat] || cat}</span>
                ${roundLabel}
            </div>
            <div class="pa-table-wrap">
                ${buildTablaProductos(items, round)}
            </div>
        </div>`;
    });
    return html;
}

function buildTablaProductos(items, round) {
    let rows = '';
    items.forEach(p => {
        const ronda = p._porRonda?.[round] ?? {};
        const stockD1   = ronda.stockD1Paq;
        const despPron  = ronda.despachoPron;
        const smf       = p.stock_max_final ?? 0;
        const df        = (p.despacho_factor > 0) ? p.despacho_factor : 1;

        // Stock D-1 cell
        let stockHtml;
        if (stockD1 === null || stockD1 === undefined) {
            stockHtml = '<span class="pa-na">Sin datos</span>';
        } else {
            const pct   = smf > 0 ? stockD1 / smf : 0;
            const cls   = pct >= 0.5 ? 'positive' : pct >= 0.25 ? 'low' : 'critical';
            stockHtml   = `<span class="pa-stock-d1 ${cls}">${stockD1.toFixed(1)}</span>`;
        }

        // Despacho Pron cell
        let despHtml;
        if (despPron === null || despPron === undefined) {
            despHtml = '<span class="pa-na">—</span>';
        } else {
            const cls = despPron > 0 ? 'needs' : 'ok';
            despHtml  = `<span class="pa-desp-val ${cls}">${despPron}</span>`;
        }

        const despTag = p.despacho_nombre
            ? `<div class="pa-prod-sub">${esc(p.despacho_nombre)}</div>`
            : '';

        rows += `
        <tr>
            <td>
                <div class="pa-prod-name">${esc(p.nombre)}</div>
                ${despTag}
            </td>
            <td><span class="pa-unit">${esc(p.unidad || '—')}</span></td>
            <td>${fmt2(p.cons_semanal)}</td>
            <td>${fmt2(p.stock_minimo)}</td>
            <td>${fmt2(p.stock_maximo)}</td>
            <td>${p.stock_max_final !== null ? fmt2(p.stock_max_final) : '<span class="pa-na">N/A</span>'}</td>
            <td class="pa-col-desp">${stockHtml}</td>
            <td class="pa-col-desp">${despHtml}</td>
        </tr>`;
    });

    return `
    <table class="pa-table">
        <thead>
            <tr>
                <th style="text-align:left">Producto</th>
                <th style="text-align:left">Presentación</th>
                <th>Cons. Semanal<br><small style="font-size:9px;color:#9ca3af;font-weight:normal;text-transform:none;letter-spacing:normal;">(en unidades)</small></th>
                <th>Stock Mín</th>
                <th>Stock Máx</th>
                <th>Stock Máx Ajustado</th>
                <th>Pronóstico Inventario</th>
                <th>Despacho</th>
            </tr>
        </thead>
        <tbody>${rows || '<tr class="pa-no-data-row"><td colspan="8">Sin productos</td></tr>'}</tbody>
    </table>`;
}
