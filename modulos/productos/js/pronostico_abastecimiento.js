'use strict';
const PA_GRUPOS = ['B', 'D', 'F', 'G'];
const PA_LABELS = { B: 'Congelados', D: 'Desechables', F: 'Secos y Preparación', G: 'Productos de Mostrador' };
const PA_SEMANAS = 4;

let PA_SUCURSALES = [];

/**
 * Dado el plan de despacho de una categoría y una fecha de despacho concreta,
 * devuelve los días reales que ese despacho debe abastecer (días hasta el siguiente).
 *
 * Para n_semanas → siempre intervalo × 7  (constante entre rondas).
 * Para dias_semana → cuenta los días desde el DOW de la fecha hasta el DOW del
 *   próximo día de despacho, que puede ser 3, 4, etc. según el calendario.
 *
 * @param {Object}   p        Producto con plan_tipo_frecuencia / plan_dias_semana / plan_intervalo_semanas
 * @param {string}   fechaStr Fecha del despacho 'YYYY-MM-DD' (a partir del que calculamos)
 * @returns {number}          Días que debe cubrir este despacho
 */
function calcularCicloSlot(p, fechaStr) {
    const tipo = p.plan_tipo_frecuencia;

    if (tipo === 'n_semanas') {
        return (p.plan_intervalo_semanas || 1) * 7;
    }

    if (tipo === 'dias_semana') {
        const dias = Array.isArray(p.plan_dias_semana) ? [...p.plan_dias_semana].sort((a, b) => a - b) : [];
        const n = dias.length;
        if (n === 0) return 7;
        if (n === 1) return 7;

        // DOW de la fecha de despacho: 0=Lun, …, 6=Dom (igual que PHP: date('N')-1)
        const dt = new Date(fechaStr + 'T12:00:00');
        const dowJS = dt.getDay(); // 0=Dom, 1=Lun, …, 6=Sáb  (JS nativo)
        // Convertir a 0=Lun, …, 6=Dom (mismo sistema que PHP)
        const dowDispatch = (dowJS + 6) % 7;

        // Buscar cuántos días hasta el SIGUIENTE despacho
        for (let d = 1; d <= 7; d++) {
            const next = (dowDispatch + d) % 7;
            if (dias.includes(next)) return d;
        }
        return 7 / n; // Fallback de seguridad
    }

    // Fallback genérico
    return p.dias_ciclo || 7;
}

/**
 * Calcula el stock_max_final ajustado para UNA ronda específica de un producto
 * con plan tipo dias_semana. El stock máx varía según el ciclo real de esa ronda.
 *
 * Para n_semanas el ciclo es constante, así que stock_max_final no varía entre rondas.
 *
 * @param {Object} p         Producto base
 * @param {number} cicloSlot Ciclo real de esta ronda (días)
 * @returns {number|null}
 */
function calcularStockMaxSlot(p, cicloSlot, cd_dinamico = null) {
    if (p.plan_tipo_frecuencia !== 'dias_semana') {
        // Para n_semanas el ciclo es fijo
        return {
            smSlot: p.stock_maximo,
            smfSlot: p.stock_max_final
        };
    }
    // Nueva fórmula: Consumo Diario * Ciclo + Stock Mínimo Base
    const cd = cd_dinamico !== null ? cd_dinamico : (p.cons_diario ?? 0);
    const dSM = p.dias_stock_min ?? 0;
    const df = p.despacho_factor > 0 ? p.despacho_factor : 1;

    const sMinUso = cd * dSM;
    const sMaxUso = (cd * cicloSlot) + sMinUso;

    let ratio = 1;
    if (p.es_ajustado && p.stock_maximo > 0 && p.stock_max_final !== null) {
        ratio = (p.stock_max_final * df) / (p.stock_maximo * df); // ratio en uso
    }
    return {
        smSlot: sMaxUso / df,
        smfSlot: (sMaxUso * ratio) / df,
        sMinSlot: sMinUso / df
    };
}

let currentAgendaData = null;
window.pa_include_preingreso = false;

$(document).ready(() => {
    cargarSucursales();
    $('#pa-btn-calcular').on('click', calcularAgenda);
    $('#pa-agenda').on('click', '.pa-row-expandible', function () {
        const ppId = $(this).data('pp-id');
        const sk = $(this).data('slot-key');
        $(`.pa-tienda-sub[data-slot-key="${sk}"][data-pp-id="${ppId}"]`).toggleClass('d-none');
        $(this).find('.pa-expand-icon').toggleClass('rotated');
    });
    $('#pa-agenda').on('change', '.pa-toggle-preingreso', function () {
        window.pa_include_preingreso = $(this).is(':checked');
        if (currentAgendaData) {
            renderAgenda(currentAgendaData.agendaMap, currentAgendaData.fechasOrdenadas, currentAgendaData.sinPlan, currentAgendaData.isConsolidado, currentAgendaData.nTiendas);
            $('#pa-search-producto').trigger('input');
        }
    });

    $('#pa-search-producto').on('input', function() {
        const term = $(this).val().toLowerCase().trim();
        if (!term) {
            $('.pa-date-block, .pa-cat-card, .pa-table tbody tr').removeClass('d-none-search');
            return;
        }

        $('.pa-table tbody').each(function() {
            let hasVisibleRows = false;
            
            $(this).find('tr:not(.pa-tienda-sub)').each(function() {
                const $row = $(this);
                if ($row.hasClass('pa-no-data-row')) return;
                
                const text = $row.find('.pa-prod-name').text().toLowerCase();
                const isMatch = text.includes(term);
                
                if (isMatch) {
                    $row.removeClass('d-none-search');
                    hasVisibleRows = true;
                    
                    const ppId = $row.data('pp-id');
                    const slotKey = $row.data('slot-key');
                    if (ppId && slotKey) {
                        $row.siblings(`.pa-tienda-sub[data-pp-id="${ppId}"][data-slot-key="${slotKey}"]`).removeClass('d-none-search');
                    }
                } else {
                    $row.addClass('d-none-search');
                    
                    const ppId = $row.data('pp-id');
                    const slotKey = $row.data('slot-key');
                    if (ppId && slotKey) {
                        $row.siblings(`.pa-tienda-sub[data-pp-id="${ppId}"][data-slot-key="${slotKey}"]`).addClass('d-none-search');
                    }
                }
            });
            
            const $card = $(this).closest('.pa-cat-card');
            if (hasVisibleRows) {
                $card.removeClass('d-none-search');
            } else {
                $card.addClass('d-none-search');
            }
        });

        $('.pa-date-block').each(function() {
            const hasVisibleCards = $(this).find('.pa-cat-card:not(.d-none-search)').length > 0;
            if (hasVisibleCards) {
                $(this).removeClass('d-none-search');
            } else {
                $(this).addClass('d-none-search');
            }
        });
    });
});


function cargarSucursales() {
    $.getJSON('ajax/configuracion_logistica_get_sucursales.php', res => {
        if (res.success && res.sucursales.length) {
            PA_SUCURSALES = res.sucursales;
            $('#pa-sucursal').append(`<option value="TODAS">🏪 Todas las Tiendas (Consolidado)</option>`);
            res.sucursales.forEach(s => {
                $('#pa-sucursal').append(`<option value="${s.codigo}">${s.nombre}</option>`);
            });
            $('#pa-sucursal').select2({ width: '100%', dropdownAutoWidth: true });
        }
    });
}

function addDaysStr(d, n) {
    const dt = new Date(d + 'T12:00:00'); dt.setDate(dt.getDate() + n);
    return dt.toISOString().split('T')[0];
}
function todayStr() { return new Date().toISOString().split('T')[0]; }
function limitStr() { return addDaysStr(todayStr(), PA_SEMANAS * 7); }
function formatDateHeader(ds) {
    const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const d = new Date(ds + 'T12:00:00');
    return { weekday: DIAS[d.getDay()], day: d.getDate(), month: MESES[d.getMonth()], year: d.getFullYear() };
}
function fmt2(v) {
    if (v === null || v === undefined) return '<span class="pa-na">N/A</span>';
    return Number(v).toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function esc(s) { return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
function setLoaderStep(m) { $('#pa-loader-step').text(m); }
function showLoader() { $('#pa-panel-inicial,#pa-panel-datos').addClass('d-none'); $('#pa-loader').removeClass('d-none'); }
function hideLoader() { $('#pa-loader').addClass('d-none'); }
function showInicial() { hideLoader(); $('#pa-panel-inicial').removeClass('d-none'); $('#pa-panel-datos').addClass('d-none'); }
function showDatos() { hideLoader(); $('#pa-panel-datos').removeClass('d-none'); $('#pa-search-producto').trigger('input'); }

async function calcularAgenda() {
    const semDesde = parseInt($('#pa-desde').val());
    const semHasta = parseInt($('#pa-hasta').val());
    const semCorte = parseInt($('#pa-corte').val());
    const sucursal = $('#pa-sucursal').val();

    const errores = [];
    if (!semDesde || !semHasta) errores.push('Ingresa el rango de semanas (Desde / Hasta).');
    if (!semCorte) errores.push('Ingresa la Semana de Corte para el pronóstico D-1.');
    if (!sucursal) errores.push('Selecciona una sucursal.');
    if (semDesde && semHasta && semDesde > semHasta) errores.push('La semana "Desde" debe ser ≤ que "Hasta".');
    if (semCorte && semDesde && semHasta && (semCorte < semDesde || semCorte > semHasta))
        errores.push(`La semana de corte (${semCorte}) debe estar dentro del rango ${semDesde}–${semHasta}.`);

    if (errores.length) {
        Swal.fire({ icon: 'warning', title: 'Datos incompletos', html: errores.map(e => `<div>• ${e}</div>`).join(''), confirmButtonColor: '#0ea5e9' });
        return;
    }

    showLoader();
    setLoaderStep('Iniciando cálculo…');

    try {
        if (sucursal === 'TODAS') {
            await calcularAgendaConsolidada(semDesde, semHasta, semCorte);
        } else {
            const datos = await calcularDatosParaSucursal(semDesde, semHasta, semCorte, sucursal);
            if (!datos) return;
            currentAgendaData = { agendaMap: datos.agendaMap, fechasOrdenadas: datos.fechasOrdenadas, sinPlan: datos.sinPlan, isConsolidado: false, nTiendas: 1 };
            renderAgenda(datos.agendaMap, datos.fechasOrdenadas, datos.sinPlan, false);
            showDatos();
        }
    } catch (err) {
        console.error('calcularAgenda:', err);
        hideLoader(); showInicial();
        Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo comunicar con el servidor.', confirmButtonColor: '#0ea5e9' });
    }
}


async function calcularDatosParaSucursal(semDesde, semHasta, semCorte, codSuc) {
    try {
        const fdP = new FormData();
        fdP.append('semana_desde_num', semDesde);
        fdP.append('semana_hasta_num', semHasta);
        fdP.append('cod_sucursal', codSuc);

        const resPedido = await fetch('ajax/pedido_sugerido_calcular_v2.php', { method: 'POST', body: fdP }).then(r => r.json());
        if (!resPedido.ok) return null;

        const prodFiltrados = (resPedido.productos || []).filter(p => PA_GRUPOS.includes(p.categoria_insumo));
        if (!prodFiltrados.length) return null;

        const porCat = {};
        PA_GRUPOS.forEach(c => porCat[c] = []);
        prodFiltrados.forEach(p => { porCat[p.categoria_insumo]?.push(p); });

        const sinPlan = {}, conPlan = {};
        PA_GRUPOS.forEach(cat => {
            const items = porCat[cat];
            if (!items.length) return;
            if (!items[0].fecha_proximo_despacho) sinPlan[cat] = items;
            else conPlan[cat] = items;
        });

        const limit = limitStr();
        const agendaMap = {};

        // ─── Construir el agendaMap con ciclo real por ronda ─────────────
        // Para dias_semana cada despacho puede tener un ciclo distinto (ej: Lun=3d, Mié=4d).
        // calcularCicloSlot() computa los días reales desde la fecha concreta del despacho
        // hasta el siguiente, de modo que el stock_max_final varía entre rondas.
        Object.entries(conPlan).forEach(([cat, items]) => {
            let cur = items[0].fecha_proximo_despacho;
            let round = 1;
            while (cur <= limit) {
                if (!agendaMap[cur]) agendaMap[cur] = {};
                // cicloSlot = días reales que este despacho debe abastecer
                const cicloSlot = calcularCicloSlot(items[0], cur);
                agendaMap[cur][cat] = { items, round, cicloSlot };
                cur = addDaysStr(cur, cicloSlot);
                round++;
            }
        });

        const fechasOrdenadas = Object.keys(agendaMap).sort();
        if (!fechasOrdenadas.length) return null;

        const prodConPlan = Object.values(conPlan).flat();
        const stockRonda1 = {};
        const preingresosHoy = {};
        const diasProyRonda1 = {};
        if (prodConPlan.length) {
            const fdPron = new FormData();
            fdPron.append('semana_desde', semDesde);
            fdPron.append('semana_hasta', semHasta);
            fdPron.append('semana_corte', semCorte);
            fdPron.append('cod_sucursal', codSuc);
            prodConPlan.forEach(p => {
                fdPron.append('ids_pp[]', p.id_pp);
                fdPron.append(`fechas_d1[${p.id_pp}]`, addDaysStr(p.fecha_proximo_despacho, -1));
            });
            const resPron = await fetch('ajax/pedido_sugerido_pronostico_v2.php', { method: 'POST', body: fdPron }).then(r => r.json());
            if (resPron.ok) {
                Object.entries(resPron.stocks || {}).forEach(([id, val]) => { stockRonda1[String(id)] = val; });
                Object.entries(resPron.preingresos_hoy || {}).forEach(([id, val]) => { preingresosHoy[String(id)] = val; });
                Object.entries(resPron.dias_proy || {}).forEach(([id, val]) => { diasProyRonda1[String(id)] = val; });
            }
        }

        fechasOrdenadas.forEach(fecha => {
            Object.entries(agendaMap[fecha]).forEach(([cat, slot]) => {
                slot.items.forEach(p => {
                    const df = p.despacho_factor > 0 ? p.despacho_factor : 1;
                    const ciclo = slot.cicloSlot;                        // ciclo real de esta ronda

                    // Proyección Dinámica WLS por semana calendario
                    const wls_m = p.wls_m ?? 0;
                    const wls_b = p.wls_b ?? 0;
                    let wls_x = p.wls_n ?? 0;
                    
                    if (resPedido.wls_last_fecha_fin) {
                        const dF = new Date(resPedido.wls_last_fecha_fin + 'T23:59:59');
                        const dD = new Date(fecha + 'T12:00:00');
                        const diffDays = Math.round((dD - dF) / (1000 * 60 * 60 * 24));
                        const x_offset = Math.ceil(diffDays / 7);
                        wls_x += x_offset;
                    } else {
                        // Fallback
                        if (p._current_wls_x === undefined) {
                            p._current_wls_x = (p.wls_n ?? 0) + 1; 
                        }
                        wls_x = Math.floor(p._current_wls_x);
                    }
                    
                    const semC_ronda = Math.max(0, (wls_m * wls_x) + wls_b);
                    const cd = semC_ronda / 7;

                    const maximos = calcularStockMaxSlot(p, ciclo, cd);
                    const smSlot = maximos.smSlot;
                    const smfSlot = maximos.smfSlot;        // stock_max recalculado para este ciclo

                    let stockD1Paq;
                    let preHoyPaq = 0;
                    let invTeoricoAyerPaq = null;
                    if (slot.round === 1) {
                        // Ronda 1: usar el pronóstico real de inventario D-1 restando la proyección de consumo (WLS) por los días faltantes
                        const su = stockRonda1[String(p.id_pp)];
                        const dP = diasProyRonda1[String(p.id_pp)] || 0;
                        const proyD1 = (su !== null && su !== undefined) ? (su - (cd * dP)) : null;
                        stockD1Paq = (proyD1 !== null) ? Math.max(0, proyD1 / df) : null;
                        invTeoricoAyerPaq = (su !== null && su !== undefined) ? (su / df) : null;
                        const ph = preingresosHoy[String(p.id_pp)];
                        preHoyPaq = (ph !== null && ph !== undefined && ph > 0) ? (ph / df) : 0;
                    } else {
                        // Rondas siguientes: estimación teórica (stock_max del slot anterior − consumo del ciclo)
                        stockD1Paq = Math.max(0, (smfSlot ?? 0) - (cd * ciclo) / df);
                    }

                    if (!p._porRonda) p._porRonda = {};
                    p._porRonda[slot.round] = {
                        stockD1Paq,
                        preHoyPaq,
                        invTeoricoAyerPaq,
                        smSlot,
                        smfSlot,                // stock_max ajustado para este despacho específico
                        sMinSlot: maximos.sMinSlot, // stock mínimo ajustado
                        cd_dinamico: cd
                    };

                });
            });
        });

        return { agendaMap, fechasOrdenadas, sinPlan };
    } catch (err) {
        console.warn(`calcularDatosParaSucursal(${codSuc}):`, err);
        return null;
    }
}

async function calcularAgendaConsolidada(semDesde, semHasta, semCorte) {
    const total = PA_SUCURSALES.length;
    const storeResults = {};

    for (let i = 0; i < PA_SUCURSALES.length; i++) {
        const suc = PA_SUCURSALES[i];
        setLoaderStep(`Calculando tienda ${i + 1} de ${total}: ${suc.nombre}…`);
        const datos = await calcularDatosParaSucursal(semDesde, semHasta, semCorte, suc.codigo);
        if (datos) storeResults[suc.codigo] = { ...datos, nombre: suc.nombre, codigo: suc.codigo };
    }

    if (!Object.keys(storeResults).length) {
        hideLoader(); showInicial();
        Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No hay datos para ninguna tienda en el período seleccionado.', confirmButtonColor: '#0ea5e9' });
        return;
    }

    setLoaderStep('Consolidando resultados…');
    const cons = consolidarResultados(storeResults);
    if (!cons.fechasOrdenadas.length) {
        hideLoader(); showInicial();
        Swal.fire({ icon: 'info', title: 'Sin despachos próximos', text: 'No hay despachos programados en las próximas 4 semanas para ninguna tienda.', confirmButtonColor: '#0ea5e9' });
        return;
    }

    currentAgendaData = { agendaMap: cons.agendaMap, fechasOrdenadas: cons.fechasOrdenadas, sinPlan: cons.sinPlan, isConsolidado: true, nTiendas: Object.keys(storeResults).length };
    renderAgenda(cons.agendaMap, cons.fechasOrdenadas, cons.sinPlan, true, Object.keys(storeResults).length);
    showDatos();
}

function consolidarResultados(storeResults) {
    const allFechas = new Set();
    Object.values(storeResults).forEach(sr => sr.fechasOrdenadas.forEach(f => allFechas.add(f)));
    const fechasOrdenadas = [...allFechas].sort();

    const agendaMap = {};
    const sinPlan = {};

    fechasOrdenadas.forEach(fecha => {
        agendaMap[fecha] = {};
        PA_GRUPOS.forEach(cat => {
            const tiendas = {};
            Object.entries(storeResults).forEach(([cod, sr]) => {
                if (sr.agendaMap[fecha]?.[cat]) tiendas[cod] = { ...sr.agendaMap[fecha][cat], nombre: sr.nombre };
            });
            if (!Object.keys(tiendas).length) return;

            const byPP = {};
            Object.entries(tiendas).forEach(([cod, slot]) => {
                slot.items.forEach(p => {
                    if (!byPP[p.id_pp]) {
                        byPP[p.id_pp] = {
                            id_pp: p.id_pp, nombre: p.nombre, unidad: p.unidad,
                            categoria_insumo: p.categoria_insumo,
                            despacho_nombre: p.despacho_nombre,
                            despacho_presentacion: p.despacho_presentacion,
                            despacho_factor: p.despacho_factor,
                            cons_semanal: 0, stock_minimo: 0, stock_maximo: 0, stock_max_final: 0,
                            _sMinTotal: 0, _smTotal: 0, _smfTotal: 0,
                            _stockD1Total: null, _preHoyTotal: null, _invTeoricoAyerTotal: null, _porTienda: {}
                        };
                    }
                    const item = byPP[p.id_pp];
                    item.cons_semanal += p.cons_semanal ?? 0;
                    item.stock_minimo += p.stock_minimo ?? 0;
                    item.stock_maximo += p.stock_maximo ?? 0;
                    item.stock_max_final += p.stock_max_final ?? p.stock_maximo ?? 0;
                    
                    const sMinRound = p._porRonda?.[slot.round]?.sMinSlot ?? p.stock_minimo ?? 0;
                    const smRound = p._porRonda?.[slot.round]?.smSlot ?? p.stock_maximo ?? 0;
                    const smfRound = p._porRonda?.[slot.round]?.smfSlot ?? p.stock_max_final ?? p.stock_maximo ?? 0;
                    item._sMinTotal += sMinRound;
                    item._smTotal += smRound;
                    item._smfTotal += smfRound;

                    const rd = p._porRonda?.[slot.round] ?? {};
                    const sd = rd.stockD1Paq, pre = rd.preHoyPaq, invTA = rd.invTeoricoAyerPaq;
                    if (sd !== null && sd !== undefined) item._stockD1Total = (item._stockD1Total ?? 0) + sd;
                    if (pre !== null && pre !== undefined) item._preHoyTotal = (item._preHoyTotal ?? 0) + pre;
                    if (invTA !== null && invTA !== undefined) item._invTeoricoAyerTotal = (item._invTeoricoAyerTotal ?? 0) + invTA;

                    item._porTienda[cod] = {
                        nombre: slot.nombre, round: slot.round,
                        cons_semanal: p.cons_semanal, stock_minimo: p.stock_minimo,
                        sMinSlot: sMinRound,
                        stock_maximo: smRound,
                        stock_max_final: smfRound,
                        stockD1Paq: sd, preHoyPaq: pre, invTeoricoAyerPaq: invTA,
                        cd_dinamico: p._porRonda?.[slot.round]?.cd_dinamico
                    };
                });
            });
            agendaMap[fecha][cat] = { items: Object.values(byPP), tiendas, isConsolidado: true };
        });
        if (!Object.keys(agendaMap[fecha]).length) delete agendaMap[fecha];
    });

    Object.values(storeResults).forEach(sr => {
        Object.entries(sr.sinPlan || {}).forEach(([cat]) => { sinPlan[cat] = true; });
    });

    return { agendaMap, fechasOrdenadas: fechasOrdenadas.filter(f => agendaMap[f]), sinPlan };
}

function renderAgenda(agendaMap, fechasOrdenadas, sinPlan, isConsolidado = false, nTiendas = 1) {
    const $w = $('#pa-warnings').empty();
    const spk = Object.keys(sinPlan);
    if (spk.length) {
        const labels = spk.map(c => `<strong>${c} — ${PA_LABELS[c]}</strong>`).join(', ');
        $w.html(`<div class="pa-warning-banner"><i class="bi bi-exclamation-triangle-fill"></i><div>Los siguientes grupos <strong>no tienen Plan de Despacho activo</strong> configurado y no se muestran en la agenda: ${labels}. Verifica la configuración en <em>Configuración Logística</em>.</div></div>`).removeClass('d-none');
    } else {
        $w.addClass('d-none');
    }

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
            <div class="pa-cats-row">${buildCatsHtml(cats, isConsolidado, fecha)}</div>
        </div>`;
    });

    $('#pa-agenda').html(html || '<p class="text-muted text-center p-5">Sin datos para mostrar.</p>');
}

function buildCatsHtml(cats, isConsolidado, fecha) {
    let html = '';
    PA_GRUPOS.forEach(cat => {
        if (!cats[cat]) return;
        const slot = cats[cat];

        // Badge de ronda / tiendas
        let badge;
        if (isConsolidado) {
            const n = Object.keys(slot.tiendas).length;
            badge = `<span class="pa-round-badge" style="background:rgba(16,185,129,0.12);color:#10b981;">${n} Tienda${n > 1 ? 's' : ''}</span>`;
        } else {
            badge = slot.round === 1
                ? '<span class="pa-round-badge" style="background:rgba(56,189,248,0.15);color:#38bdf8;">Ronda 1 · Inventario Real</span>'
                : `<span class="pa-round-badge">Ronda ${slot.round} · Proyección</span>`;
        }

        // Badge de ciclo (días que cubre este despacho) — solo para tienda individual
        let badgeCiclo = '';
        if (!isConsolidado && slot.cicloSlot) {
            const dias = Math.round(slot.cicloSlot);
            badgeCiclo = `<span class="pa-round-badge" title="Días que este despacho debe abastecer hasta el siguiente" style="background:rgba(139,92,246,0.12);color:#8b5cf6;"><i class="bi bi-clock me-1"></i>${dias} día${dias !== 1 ? 's' : ''}</span>`;
        }

        // Badge de total despacho (cat B)
        let badgeB = '';
        if (cat === 'B') {
            let totalDespacho = 0;
            slot.items.forEach(p => {
                let s_base, pre_hoy, smf;
                if (isConsolidado) {
                    s_base = p._stockD1Total;
                    pre_hoy = p._preHoyTotal;
                    smf = p._smfTotal;
                } else {
                    const rd = p._porRonda?.[slot.round] ?? {};
                    s_base = rd.stockD1Paq;
                    pre_hoy = rd.preHoyPaq;
                    smf = rd.smfSlot ?? p.stock_max_final;
                }
                
                let s_final = s_base;
                if (window.pa_include_preingreso && s_final !== null && pre_hoy) {
                    s_final += pre_hoy;
                }
                let despPron = s_final !== null ? Math.max(0, Math.ceil((smf ?? 0) - s_final)) : null;

                if (despPron !== null && despPron !== undefined) totalDespacho += despPron;
            });
            badgeB = `<span class="pa-round-badge" style="margin-left:auto; background:rgba(14,165,233,0.1); color:#0ea5e9; font-size:13px; font-weight:800; padding:4px 12px;">Total Despacho: ${totalDespacho}</span>`;
        }

        const slotKey = `${fecha.replace(/-/g, '')}-${cat}`;
        html += `
        <div class="pa-cat-card pa-cat-${cat.toLowerCase()}">
            <div class="pa-cat-header">
                <div class="pa-cat-badge">${cat}</div>
                <span>${PA_LABELS[cat] || cat}</span>
                ${badge}
                ${badgeCiclo}
                ${badgeB}
            </div>
            <div class="pa-table-wrap">
                ${buildTablaProductos(slot, isConsolidado, slotKey)}
            </div>
        </div>`;
    });
    return html;
}

function buildTablaProductos(slot, isConsolidado, slotKey) {
    const items = slot.items;
    const round = slot.round;
    let rows = '';

    items.forEach(p => {
        let stockD1Paq_base, preHoyPaq, smfDisplay, smDisplay, sMinDisplay, cdDisplay, invTeoricoAyerPaq;
        if (isConsolidado) {
            stockD1Paq_base = p._stockD1Total;
            preHoyPaq = p._preHoyTotal;
            smfDisplay = p._smfTotal;
            smDisplay = p._smTotal;
            sMinDisplay = p._sMinTotal;
            cdDisplay = p.cons_semanal !== null && p.cons_semanal !== undefined ? (p.cons_semanal / 7) : null;
            invTeoricoAyerPaq = p._invTeoricoAyerTotal;
        } else {
            const rd = p._porRonda?.[round] ?? {};
            stockD1Paq_base = rd.stockD1Paq;
            preHoyPaq = rd.preHoyPaq;
            smfDisplay = rd.smfSlot ?? p.stock_max_final;
            smDisplay = rd.smSlot ?? p.stock_maximo;
            sMinDisplay = rd.sMinSlot ?? p.stock_minimo;
            cdDisplay = rd.cd_dinamico ?? (p.cons_semanal !== null && p.cons_semanal !== undefined ? (p.cons_semanal / 7) : null);
            invTeoricoAyerPaq = rd.invTeoricoAyerPaq;
        }
        
        let csDisplay = cdDisplay !== null ? cdDisplay * 7 : null;

        let stockD1Paq = stockD1Paq_base;
        if (window.pa_include_preingreso && stockD1Paq !== null && preHoyPaq) {
            stockD1Paq += preHoyPaq;
        }

        let despPron = stockD1Paq !== null ? Math.max(0, Math.ceil((smfDisplay ?? 0) - stockD1Paq)) : null;

        const smfRef = smfDisplay ?? 0;
        let stockHtml;
        if (stockD1Paq === null || stockD1Paq === undefined) {
            stockHtml = '<span class="pa-na">Sin datos</span>';
        } else {
            // Clasificar el nivel de stock respecto al stock_max del ESTE despacho
            const pct = smfRef > 0 ? stockD1Paq / smfRef : 0;
            const cls = pct >= 0.5 ? 'positive' : pct >= 0.25 ? 'low' : 'critical';
            stockHtml = `<span class="pa-stock-d1 ${cls}">${stockD1Paq.toFixed(1)}</span>`;
        }

        let preHtml = '';
        if (preHoyPaq) {
            preHtml = window.pa_include_preingreso 
                ? `<span class="pa-stock-d1 positive" title="Sumado a Pronóstico Inventario">+${preHoyPaq.toFixed(1)}</span>`
                : `<span class="pa-stock-d1" style="color:#9ca3af;" title="Desactivado">+${preHoyPaq.toFixed(1)}</span>`;
        } else {
            preHtml = '<span class="pa-na">—</span>';
        }

        let despHtml;
        if (despPron === null || despPron === undefined) {
            despHtml = '<span class="pa-na">—</span>';
        } else {
            despHtml = `<span class="pa-desp-val ${despPron > 0 ? 'needs' : 'ok'}">${despPron}</span>`;
        }

        // El subtítulo de presentación se eliminó porque ahora la columna "Presentación de Despacho" muestra este dato

        // Celda del Stock Máx Ajustado: muestra smfDisplay (ciclo real de esta ronda)
        const smfCell = smfDisplay !== null && smfDisplay !== undefined
            ? fmt2(smfDisplay)
            : '<span class="pa-na">N/A</span>';

        let invTAHtml;
        if (invTeoricoAyerPaq === null || invTeoricoAyerPaq === undefined) {
            invTAHtml = '<span class="pa-na">—</span>';
        } else {
            invTAHtml = `<span>${invTeoricoAyerPaq.toFixed(1)}</span>`;
        }

        if (isConsolidado) {
            rows += `
            <tr class="pa-row-expandible" data-pp-id="${p.id_pp}" data-slot-key="${slotKey}">
                <td><i class="bi bi-chevron-right pa-expand-icon"></i><div class="pa-prod-name">${esc(p.nombre)}</div></td>
                <td><span class="pa-unit">${esc(p.despacho_presentacion || p.unidad || '—')}</span></td>
                <td>${cdDisplay !== null ? fmt2(cdDisplay) : fmt2(null)}</td>
                <td>${csDisplay !== null ? fmt2(csDisplay) : fmt2(null)}</td>
                <td>${fmt2(sMinDisplay)}</td>
                <td>${fmt2(smDisplay)}</td>
                <td>${smfCell}</td>
                <td class="pa-col-desp">${invTAHtml}</td>
                <td class="pa-col-desp">${stockHtml}</td>
                <td class="pa-col-desp" style="background:#f8fafc;">${preHtml}</td>
                <td class="pa-col-desp">${despHtml}</td>
            </tr>
            ${buildSubRowsTiendas(p, slotKey)}`;
        } else {
            rows += `
            <tr>
                <td><div class="pa-prod-name">${esc(p.nombre)}</div></td>
                <td><span class="pa-unit">${esc(p.despacho_presentacion || p.unidad || '—')}</span></td>
                <td>${cdDisplay !== null ? fmt2(cdDisplay) : fmt2(null)}</td>
                <td>${csDisplay !== null ? fmt2(csDisplay) : fmt2(null)}</td>
                <td>${fmt2(sMinDisplay)}</td>
                <td>${fmt2(smDisplay)}</td>
                <td>${smfCell}</td>
                <td class="pa-col-desp">${invTAHtml}</td>
                <td class="pa-col-desp">${stockHtml}</td>
                <td class="pa-col-desp" style="background:#f8fafc;">${preHtml}</td>
                <td class="pa-col-desp">${despHtml}</td>
            </tr>`;
        }
    });

    const isChecked = window.pa_include_preingreso ? 'checked' : '';
    const thead = `<thead><tr>
        <th style="text-align:left">Producto</th>
        <th style="text-align:left">Presentación de Despacho</th>
        <th>Consumo Diario<br><small style="font-size:9px;color:#9ca3af;font-weight:normal;text-transform:none;letter-spacing:normal;">(en unidades)</small></th>
        <th>Consumo Semanal<br><small style="font-size:9px;color:#9ca3af;font-weight:normal;text-transform:none;letter-spacing:normal;">(en unidades)</small></th>
        <th>Stock Mín</th><th>Stock Máx</th><th>Stock Máx Ajustado</th>
        <th>Inv. Teórico Ayer</th>
        <th>Pronóstico Inventario</th>
        <th style="width: 100px;">Despacho en Curso<br>
            <div class="form-check form-switch d-inline-block mt-1">
                <input class="form-check-input pa-toggle-preingreso" type="checkbox" title="Incluir despachos de hoy" ${isChecked}>
            </div>
        </th>
        <th>Despacho</th>
    </tr></thead>`;

    return `<table class="pa-table">${thead}<tbody>${rows || '<tr class="pa-no-data-row"><td colspan="11">Sin productos</td></tr>'}</tbody></table>`;
}

function buildSubRowsTiendas(item, slotKey) {
    let rows = '';
    Object.entries(item._porTienda || {}).forEach(([cod, td]) => {
        const smf = td.stock_max_final ?? 0;
        
        let stockD1Paq = td.stockD1Paq;
        if (window.pa_include_preingreso && stockD1Paq !== null && td.preHoyPaq) {
            stockD1Paq += td.preHoyPaq;
        }
        let despPron = stockD1Paq !== null ? Math.max(0, Math.ceil((smf ?? 0) - stockD1Paq)) : null;

        let sHtml;
        if (stockD1Paq === null || stockD1Paq === undefined) {
            sHtml = '<span class="pa-na">Sin datos</span>';
        } else {
            const pct = smf > 0 ? stockD1Paq / smf : 0;
            const cls = pct >= 0.5 ? 'positive' : pct >= 0.25 ? 'low' : 'critical';
            sHtml = `<span class="pa-stock-d1 ${cls}">${stockD1Paq.toFixed(1)}</span>`;
        }
        
        let preHtml = '';
        if (td.preHoyPaq) {
            preHtml = window.pa_include_preingreso 
                ? `<span class="pa-stock-d1 positive" style="transform: scale(0.9);">+${td.preHoyPaq.toFixed(1)}</span>`
                : `<span class="pa-stock-d1 text-muted" style="transform: scale(0.9);">+${td.preHoyPaq.toFixed(1)}</span>`;
        } else {
            preHtml = '<span class="pa-na">—</span>';
        }

        const dHtml = (despPron === null || despPron === undefined)
            ? '<span class="pa-na">—</span>'
            : `<span class="pa-desp-val ${despPron > 0 ? 'needs' : 'ok'}">${despPron}</span>`;

        let tdCdDisplay = td.cd_dinamico !== null && td.cd_dinamico !== undefined ? td.cd_dinamico : (td.cons_semanal !== null && td.cons_semanal !== undefined ? (td.cons_semanal / 7) : null);
        let tdCsDisplay = tdCdDisplay !== null ? tdCdDisplay * 7 : null;
        let tdInvTAHtml = (td.invTeoricoAyerPaq === null || td.invTeoricoAyerPaq === undefined) ? '<span class="pa-na">—</span>' : `<span>${td.invTeoricoAyerPaq.toFixed(1)}</span>`;

        rows += `
        <tr class="pa-tienda-row pa-tienda-sub d-none" data-slot-key="${slotKey}" data-pp-id="${item.id_pp}">
            <td><span class="pa-tienda-badge">${esc(td.nombre)}</span></td>
            <td></td>
            <td>${tdCdDisplay !== null ? fmt2(tdCdDisplay) : fmt2(null)}</td>
            <td>${tdCsDisplay !== null ? fmt2(tdCsDisplay) : fmt2(null)}</td>
            <td>${fmt2(td.sMinSlot ?? td.stock_minimo)}</td>
            <td>${fmt2(td.stock_maximo)}</td>
            <td>${fmt2(td.stock_max_final)}</td>
            <td class="pa-col-desp">${tdInvTAHtml}</td>
            <td class="pa-col-desp">${sHtml}</td>
            <td class="pa-col-desp" style="background:#f8fafc;">${preHtml}</td>
            <td class="pa-col-desp">${dHtml}</td>
        </tr>`;
    });
    return rows;
}

function exportarPronosticoExcel() {
    if (!currentAgendaData || !currentAgendaData.fechasOrdenadas.length) {
        Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay datos para exportar.' });
        return;
    }
    
    let datosExportar = [];
    
    currentAgendaData.fechasOrdenadas.forEach(fecha => {
        const agenda = currentAgendaData.agendaMap[fecha];
        PA_GRUPOS.forEach(cat => {
            if (!agenda[cat]) return;
            const slot = agenda[cat];
            
            // Recorrer los items
            slot.items.forEach(p => {
                let stockD1Paq_base, preHoyPaq, smfDisplay, smDisplay;
                if (currentAgendaData.isConsolidado) {
                    stockD1Paq_base = p._stockD1Total;
                    preHoyPaq = p._preHoyTotal;
                    smfDisplay = p._smfTotal;
                    smDisplay = p._smTotal;
                } else {
                    const rd = p._porRonda?.[slot.round] ?? {};
                    stockD1Paq_base = rd.stockD1Paq;
                    preHoyPaq = rd.preHoyPaq;
                    smfDisplay = rd.smfSlot ?? p.stock_max_final;
                    smDisplay = rd.smSlot ?? p.stock_maximo;
                }
                
                let stockD1Paq = stockD1Paq_base;
                if (window.pa_include_preingreso && stockD1Paq !== null && preHoyPaq) {
                    stockD1Paq += preHoyPaq;
                }
                
                let despPron = stockD1Paq !== null ? Math.max(0, Math.ceil((smfDisplay ?? 0) - stockD1Paq)) : null;
                
                let productoNombre = p.nombre;
                
                let pronosticoInv = stockD1Paq !== null && stockD1Paq !== undefined ? stockD1Paq.toFixed(2) : 'Sin datos';
                if (window.pa_include_preingreso && preHoyPaq) pronosticoInv += ` (+${preHoyPaq.toFixed(2)})`;

                let cdDisplay = null;
                let invTeoricoAyerPaq = null;
                if (currentAgendaData.isConsolidado) {
                    cdDisplay = p.cons_semanal !== null && p.cons_semanal !== undefined ? (p.cons_semanal / 7) : null;
                    invTeoricoAyerPaq = p._invTeoricoAyerTotal;
                } else {
                    const rd = p._porRonda?.[slot.round] ?? {};
                    cdDisplay = rd.cd_dinamico ?? (p.cons_semanal !== null && p.cons_semanal !== undefined ? (p.cons_semanal / 7) : null);
                    invTeoricoAyerPaq = rd.invTeoricoAyerPaq;
                }
                let csDisplay = cdDisplay !== null ? cdDisplay * 7 : null;
                
                datosExportar.push({
                    "Fecha de Despacho": fecha,
                    "Grupo": PA_LABELS[cat] || cat,
                    "Producto": productoNombre,
                    "Presentación de Despacho": p.despacho_presentacion || p.unidad || '-',
                    "Consumo Diario": cdDisplay !== null ? cdDisplay.toFixed(2) : '',
                    "Consumo Semanal": csDisplay !== null ? csDisplay.toFixed(2) : '',
                    "Stock Mín": p.stock_minimo !== null && p.stock_minimo !== undefined ? parseFloat(p.stock_minimo).toFixed(2) : '',
                    "Stock Máx": smDisplay !== null && smDisplay !== undefined ? parseFloat(smDisplay).toFixed(2) : '',
                    "Stock Máx Ajustado": smfDisplay !== null && smfDisplay !== undefined ? parseFloat(smfDisplay).toFixed(2) : '',
                    "Inv. Teórico Ayer": invTeoricoAyerPaq !== null && invTeoricoAyerPaq !== undefined ? invTeoricoAyerPaq.toFixed(2) : '',
                    "Pronóstico Inventario": pronosticoInv,
                    "Despacho": despPron !== null ? despPron : '-'
                });
                
                if (currentAgendaData.isConsolidado && p._porTienda) {
                    Object.entries(p._porTienda).forEach(([cod, td]) => {
                        let sub_smfDisplay = td.stock_max_final ?? 0;
                        let sub_smDisplay = td.stock_maximo ?? 0;
                        let sub_stockD1Paq = td.stockD1Paq;
                        
                        if (window.pa_include_preingreso && sub_stockD1Paq !== null && td.preHoyPaq) {
                            sub_stockD1Paq += td.preHoyPaq;
                        }
                        
                        let sub_despPron = sub_stockD1Paq !== null ? Math.max(0, Math.ceil((sub_smfDisplay ?? 0) - sub_stockD1Paq)) : null;
                        
                        let sub_pronosticoInv = sub_stockD1Paq !== null && sub_stockD1Paq !== undefined ? sub_stockD1Paq.toFixed(2) : 'Sin datos';
                        if (window.pa_include_preingreso && td.preHoyPaq) sub_pronosticoInv += ` (+${td.preHoyPaq.toFixed(2)})`;
                        
                        datosExportar.push({
                            "Fecha de Despacho": fecha,
                            "Grupo": PA_LABELS[cat] || cat,
                            "Producto": `    - ${td.nombre}`, // Indentado
                            "Presentación de Despacho": "-",
                            "Consumo Diario": td.cons_semanal !== null && td.cons_semanal !== undefined ? parseFloat(td.cons_semanal / 7).toFixed(2) : '',
                            "Stock Mín": td.stock_minimo !== null && td.stock_minimo !== undefined ? parseFloat(td.stock_minimo).toFixed(2) : '',
                            "Stock Máx": sub_smDisplay !== null && sub_smDisplay !== undefined ? parseFloat(sub_smDisplay).toFixed(2) : '',
                            "Stock Máx Ajustado": sub_smfDisplay !== null && sub_smfDisplay !== undefined ? parseFloat(sub_smfDisplay).toFixed(2) : '',
                            "Pronóstico Inventario": sub_pronosticoInv,
                            "Despacho": sub_despPron !== null ? sub_despPron : '-'
                        });
                    });
                }
            });
        });
    });
    
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.json_to_sheet(datosExportar);
    
    // Auto-ajustar ancho de columnas
    const wscols = [
        {wch: 18}, // Fecha
        {wch: 25}, // Grupo
        {wch: 40}, // Producto
        {wch: 25}, // Presentación de Despacho
        {wch: 15}, // Consumo Diario
        {wch: 15}, // Stock Mín
        {wch: 15}, // Stock Máx
        {wch: 18}, // Stock Máx Ajustado
        {wch: 25}, // Pronóstico Inventario
        {wch: 15}  // Despacho
    ];
    ws['!cols'] = wscols;

    XLSX.utils.book_append_sheet(wb, ws, "Pronóstico");
    
    let nombreArchivo = `Pronostico_Abastecimiento_${new Date().toISOString().slice(0,10)}.xlsx`;
    XLSX.writeFile(wb, nombreArchivo);
}
