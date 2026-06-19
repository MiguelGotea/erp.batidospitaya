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
        smfSlot: (sMaxUso * ratio) / df
    };
}

$(document).ready(() => {
    cargarSucursales();
    $('#pa-btn-calcular').on('click', calcularAgenda);
    $('#pa-agenda').on('click', '.pa-row-expandible', function () {
        const ppId = $(this).data('pp-id');
        const sk = $(this).data('slot-key');
        $(`.pa-tienda-sub[data-slot-key="${sk}"][data-pp-id="${ppId}"]`).toggleClass('d-none');
        $(this).find('.pa-expand-icon').toggleClass('rotated');
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
function showDatos() { hideLoader(); $('#pa-panel-datos').removeClass('d-none'); }

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
            if (resPron.ok) Object.entries(resPron.stocks || {}).forEach(([id, val]) => { stockRonda1[String(id)] = val; });
        }

        fechasOrdenadas.forEach(fecha => {
            Object.entries(agendaMap[fecha]).forEach(([cat, slot]) => {
                slot.items.forEach(p => {
                    const df = p.despacho_factor > 0 ? p.despacho_factor : 1;
                    const ciclo = slot.cicloSlot;                        // ciclo real de esta ronda

                    // Proyección Dinámica WLS
                    if (p._current_wls_x === undefined) {
                        p._current_wls_x = (p.wls_n ?? 0) + 1; // Ronda 1 inicia proyectando a la semana n+1
                    }
                    
                    const wls_m = p.wls_m ?? 0;
                    const wls_b = p.wls_b ?? 0;
                    const semC_ronda = Math.max(0, (wls_m * p._current_wls_x) + wls_b);
                    const cd = semC_ronda / 7;

                    const maximos = calcularStockMaxSlot(p, ciclo, cd);
                    const smSlot = maximos.smSlot;
                    const smfSlot = maximos.smfSlot;        // stock_max recalculado para este ciclo

                    let stockD1Paq;
                    if (slot.round === 1) {
                        // Ronda 1: usar el pronóstico real de inventario D-1
                        const su = stockRonda1[String(p.id_pp)];
                        stockD1Paq = (su !== null && su !== undefined) ? Math.max(0, su / df) : null;
                    } else {
                        // Rondas siguientes: estimación teórica (stock_max del slot anterior − consumo del ciclo)
                        // Usamos el smfSlot del slot ANTERIOR (el que acaba de terminar) ≈ smfSlot actual
                        // (aproximación conservadora, idéntica a la lógica anterior pero con ciclo correcto)
                        stockD1Paq = Math.max(0, (smfSlot ?? 0) - (cd * ciclo) / df);
                    }

                    if (!p._porRonda) p._porRonda = {};
                    p._porRonda[slot.round] = {
                        stockD1Paq,
                        smSlot,
                        smfSlot,                // stock_max ajustado para este despacho específico
                        despachoPron: stockD1Paq !== null
                            ? Math.max(0, Math.ceil((smfSlot ?? 0) - stockD1Paq))
                            : null,
                        cd_dinamico: cd
                    };

                    // Avanzar la proyección de tiempo para la siguiente ronda de este producto
                    p._current_wls_x += (ciclo / 7);
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
                            despacho_factor: p.despacho_factor,
                            cons_semanal: 0, stock_minimo: 0, stock_maximo: 0, stock_max_final: 0,
                            _stockD1Total: null, _despTotal: null, _porTienda: {}
                        };
                    }
                    const item = byPP[p.id_pp];
                    item.cons_semanal += p.cons_semanal ?? 0;
                    item.stock_minimo += p.stock_minimo ?? 0;
                    item.stock_maximo += p.stock_maximo ?? 0;
                    item.stock_max_final += p.stock_max_final ?? p.stock_maximo ?? 0;

                    const rd = p._porRonda?.[slot.round] ?? {};
                    const sd = rd.stockD1Paq, dp = rd.despachoPron;
                    if (sd !== null && sd !== undefined) item._stockD1Total = (item._stockD1Total ?? 0) + sd;
                    if (dp !== null && dp !== undefined) item._despTotal = (item._despTotal ?? 0) + dp;

                    item._porTienda[cod] = {
                        nombre: slot.nombre, round: slot.round,
                        cons_semanal: p.cons_semanal, stock_minimo: p.stock_minimo,
                        stock_maximo: p._porRonda?.[slot.round]?.smSlot ?? p.stock_maximo,
                        stock_max_final: p._porRonda?.[slot.round]?.smfSlot ?? p.stock_max_final,
                        stockD1Paq: sd, despachoPron: dp
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
                let despPron = isConsolidado ? p._despTotal : (p._porRonda?.[slot.round]?.despachoPron);
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
        let stockD1Paq, despPron, smfDisplay, smDisplay;
        if (isConsolidado) {
            stockD1Paq = p._stockD1Total;
            despPron = p._despTotal;
            smfDisplay = p.stock_max_final;   // consolidado: usa genérico
            smDisplay = p.stock_maximo;
        } else {
            const rd = p._porRonda?.[round] ?? {};
            stockD1Paq = rd.stockD1Paq;
            despPron = rd.despachoPron;
            // smfSlot = stock_max ajustado para el ciclo real de ESTE despacho
            // (diferente al genérico stock_max_final para dias_semana)
            smfDisplay = rd.smfSlot ?? p.stock_max_final;
            smDisplay = rd.smSlot ?? p.stock_maximo;
        }

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

        let despHtml;
        if (despPron === null || despPron === undefined) {
            despHtml = '<span class="pa-na">—</span>';
        } else {
            despHtml = `<span class="pa-desp-val ${despPron > 0 ? 'needs' : 'ok'}">${despPron}</span>`;
        }

        const despTag = p.despacho_nombre ? `<div class="pa-prod-sub">${esc(p.despacho_nombre)}</div>` : '';

        // Celda del Stock Máx Ajustado: muestra smfDisplay (ciclo real de esta ronda)
        const smfCell = smfDisplay !== null && smfDisplay !== undefined
            ? fmt2(smfDisplay)
            : '<span class="pa-na">N/A</span>';

        if (isConsolidado) {
            rows += `
            <tr class="pa-row-expandible" data-pp-id="${p.id_pp}" data-slot-key="${slotKey}">
                <td><i class="bi bi-chevron-right pa-expand-icon"></i><div class="pa-prod-name">${esc(p.nombre)}</div>${despTag}</td>
                <td><span class="pa-unit">${esc(p.unidad || '—')}</span></td>
                <td>${fmt2(p.cons_semanal)}</td>
                <td>${fmt2(p.stock_minimo)}</td>
                <td>${fmt2(smDisplay)}</td>
                <td>${smfCell}</td>
                <td class="pa-col-desp">${stockHtml}</td>
                <td class="pa-col-desp">${despHtml}</td>
            </tr>
            ${buildSubRowsTiendas(p, slotKey)}`;
        } else {
            rows += `
            <tr>
                <td><div class="pa-prod-name">${esc(p.nombre)}</div>${despTag}</td>
                <td><span class="pa-unit">${esc(p.unidad || '—')}</span></td>
                <td>${fmt2(p.cons_semanal)}</td>
                <td>${fmt2(p.stock_minimo)}</td>
                <td>${fmt2(smDisplay)}</td>
                <td>${smfCell}</td>
                <td class="pa-col-desp">${stockHtml}</td>
                <td class="pa-col-desp">${despHtml}</td>
            </tr>`;
        }
    });

    const thead = `<thead><tr>
        <th style="text-align:left">Producto</th>
        <th style="text-align:left">Presentación</th>
        <th>Cons. Semanal<br><small style="font-size:9px;color:#9ca3af;font-weight:normal;text-transform:none;letter-spacing:normal;">(en unidades)</small></th>
        <th>Stock Mín</th><th>Stock Máx</th><th>Stock Máx Ajustado</th>
        <th>Pronóstico Inventario</th><th>Despacho</th>
    </tr></thead>`;

    return `<table class="pa-table">${thead}<tbody>${rows || '<tr class="pa-no-data-row"><td colspan="8">Sin productos</td></tr>'}</tbody></table>`;
}

function buildSubRowsTiendas(item, slotKey) {
    let rows = '';
    Object.entries(item._porTienda || {}).forEach(([cod, td]) => {
        const smf = td.stock_max_final ?? 0;
        let sHtml;
        if (td.stockD1Paq === null || td.stockD1Paq === undefined) {
            sHtml = '<span class="pa-na">Sin datos</span>';
        } else {
            const pct = smf > 0 ? td.stockD1Paq / smf : 0;
            const cls = pct >= 0.5 ? 'positive' : pct >= 0.25 ? 'low' : 'critical';
            sHtml = `<span class="pa-stock-d1 ${cls}">${td.stockD1Paq.toFixed(1)}</span>`;
        }
        const dHtml = (td.despachoPron === null || td.despachoPron === undefined)
            ? '<span class="pa-na">—</span>'
            : `<span class="pa-desp-val ${td.despachoPron > 0 ? 'needs' : 'ok'}">${td.despachoPron}</span>`;

        rows += `
        <tr class="pa-tienda-row pa-tienda-sub d-none" data-slot-key="${slotKey}" data-pp-id="${item.id_pp}">
            <td><span class="pa-tienda-badge">${esc(td.nombre)}</span></td>
            <td></td>
            <td>${fmt2(td.cons_semanal)}</td>
            <td>${fmt2(td.stock_minimo)}</td>
            <td>${fmt2(td.stock_maximo)}</td>
            <td>${fmt2(td.stock_max_final)}</td>
            <td class="pa-col-desp">${sHtml}</td>
            <td class="pa-col-desp">${dHtml}</td>
        </tr>`;
    });
    return rows;
}

