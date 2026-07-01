'use strict';
const PA_GRUPOS = ['B', 'D', 'F', 'G'];
const PA_LABELS = { B: 'Congelados', D: 'Desechables', F: 'Secos y Preparación', G: 'Productos de Mostrador' };
const PA_DIAS_FUTURO = 8;

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

    let sMinUso = cd * dSM;
    const sMinReg = parseFloat(p.stock_minimo_registrado) || 0;
    if (sMinReg > 0 && sMinUso < sMinReg) {
        sMinUso = sMinReg;
    }
    
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
window.pa_dias_despacho_real = {};

$(document).ready(() => {
    cargarSucursales();
    $('#pa-btn-calcular').on('click', calcularAgenda);
    $('#pa-agenda').on('click', '.pa-row-expandible', function () {
        const ppId = $(this).data('pp-id');
        const sk = $(this).data('slot-key');
        $(`.pa-tienda-sub[data-slot-key="${sk}"][data-pp-id="${ppId}"]`).toggleClass('d-none');
        $(this).find('.pa-expand-icon').toggleClass('rotated');
    });

    $('#pa-agenda').on('click', '.pa-row-expandible-charts', function () {
        const ppId = $(this).data('pp-id');
        const sk = $(this).data('slot-key');
        const sucursal = $(this).data('sucursal');
        const fechaDespacho = $(this).data('fecha-despacho');
        const cicloSlot = $(this).data('ciclo');

        const subRow = $(`.pa-chart-sub[data-slot-key="${sk}"][data-pp-id="${ppId}"]`);

        subRow.toggleClass('d-none');
        $(this).find('.pa-expand-icon').toggleClass('rotated');

        if (!subRow.hasClass('d-none') && !subRow.data('loaded')) {
            subRow.data('loaded', true);
            const semDesde = $('#pa-desde').val();
            const semHasta = $('#pa-hasta').val();
            const semCorte = $('#pa-corte').val();

            if (window.cargarGraficasParaFila) {
                window.cargarGraficasParaFila(ppId, sk, sucursal, semDesde, semHasta, semCorte, fechaDespacho, cicloSlot);
            }
        }
    });
    $('#pa-agenda').on('change', '.pa-toggle-preingreso', function () {
        const isChecked = $(this).is(':checked');
        const fecha = $(this).data('fecha');
        if (fecha) {
            window.pa_dias_despacho_real[fecha] = isChecked;
        }

        if (window.lastStoreResults && currentAgendaData) {
            const expandedTiendas = [];
            $('.pa-row-expandible').each(function() {
                if ($(this).find('.pa-expand-icon').hasClass('rotated')) {
                    expandedTiendas.push({
                        ppId: $(this).data('pp-id'),
                        sk: $(this).data('slot-key')
                    });
                }
            });

            const expandedCharts = [];
            $('.pa-row-expandible-charts').each(function() {
                if ($(this).find('.pa-expand-icon').hasClass('rotated')) {
                    expandedCharts.push({
                        ppId: $(this).data('pp-id'),
                        sk: $(this).data('slot-key'),
                        sucursal: $(this).data('sucursal'),
                        fechaDespacho: $(this).data('fecha-despacho'),
                        ciclo: $(this).data('ciclo')
                    });
                }
            });

            recalcularChaining(window.lastStoreResults);

            if (currentAgendaData.isConsolidado) {
                const cons = consolidarResultados(window.lastStoreResults);
                currentAgendaData.agendaMap = cons.agendaMap;
            }

            renderAgenda(currentAgendaData.agendaMap, currentAgendaData.fechasOrdenadas, currentAgendaData.sinPlan, currentAgendaData.isConsolidado, currentAgendaData.nTiendas, currentAgendaData.auditoriaData);
            $('#pa-search-producto').trigger('input');

            expandedTiendas.forEach(item => {
                const $row = $(`.pa-row-expandible[data-pp-id="${item.ppId}"][data-slot-key="${item.sk}"]`);
                if ($row.length) {
                    $row.find('.pa-expand-icon').addClass('rotated');
                    $(`.pa-tienda-sub[data-pp-id="${item.ppId}"][data-slot-key="${item.sk}"]`).removeClass('d-none');
                }
            });

            const semDesde = $('#pa-desde').val();
            const semHasta = $('#pa-hasta').val();
            const semCorte = $('#pa-corte').val();

            expandedCharts.forEach(item => {
                const $row = $(`.pa-row-expandible-charts[data-pp-id="${item.ppId}"][data-slot-key="${item.sk}"]`);
                if ($row.length) {
                    $row.find('.pa-expand-icon').addClass('rotated');
                    const $sub = $(`.pa-chart-sub[data-pp-id="${item.ppId}"][data-slot-key="${item.sk}"]`);
                    $sub.removeClass('d-none');
                    $sub.data('loaded', true);
                    
                    if (window.cargarGraficasParaFila) {
                        window.cargarGraficasParaFila(item.ppId, item.sk, item.sucursal, semDesde, semHasta, semCorte, item.fechaDespacho, item.ciclo);
                    }
                }
            });
        }
    });

    $('#pa-search-producto').on('input', function () {
        const term = $(this).val().toLowerCase().trim();
        if (!term) {
            $('.pa-date-block, .pa-cat-card, .pa-table tbody tr').removeClass('d-none-search');
            return;
        }

        $('.pa-table tbody').each(function () {
            let hasVisibleRows = false;

            $(this).find('tr:not(.pa-tienda-sub)').each(function () {
                const $row = $(this);
                if ($row.hasClass('pa-no-data-row') || $row.hasClass('pa-tienda-sub') || $row.hasClass('pa-chart-sub')) return;

                const text = $row.find('.pa-prod-name').text().toLowerCase();
                const isMatch = text.includes(term);

                if (isMatch) {
                    $row.removeClass('d-none-search');
                    hasVisibleRows = true;

                    const ppId = $row.data('pp-id');
                    const slotKey = $row.data('slot-key');
                    if (ppId && slotKey) {
                        $row.siblings(`.pa-tienda-sub[data-pp-id="${ppId}"][data-slot-key="${slotKey}"]`).removeClass('d-none-search');
                        $row.siblings(`.pa-chart-sub[data-pp-id="${ppId}"][data-slot-key="${slotKey}"]`).removeClass('d-none-search');
                    }
                } else {
                    $row.addClass('d-none-search');

                    const ppId = $row.data('pp-id');
                    const slotKey = $row.data('slot-key');
                    if (ppId && slotKey) {
                        $row.siblings(`.pa-tienda-sub[data-pp-id="${ppId}"][data-slot-key="${slotKey}"]`).addClass('d-none-search');
                        $row.siblings(`.pa-chart-sub[data-pp-id="${ppId}"][data-slot-key="${slotKey}"]`).addClass('d-none-search');
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

        $('.pa-date-block').each(function () {
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
function limitStr() {
    const customDate = $('#pa-fecha-limite').val();
    if (customDate) return customDate;
    return addDaysStr(todayStr(), PA_DIAS_FUTURO);
}
function formatDateHeader(ds) {
    const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const d = new Date(ds + 'T12:00:00');
    return { weekday: DIAS[d.getDay()], day: d.getDate(), month: MESES[d.getMonth()], year: d.getFullYear() };
}
function fmt2(v) {
    if (v === null || v === undefined) return '<span class="pa-na">N/A</span>';
    return Number(v).toLocaleString('es-NI', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
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
    const minSemana = window.PA_SEMANA_ACTUAL ? Math.max(1, window.PA_SEMANA_ACTUAL - 5) : 1;
    if (semDesde && semDesde < minSemana) errores.push(`La semana "Desde" no puede ser menor a ${minSemana}.`);
    if (semHasta && semHasta < minSemana) errores.push(`La semana "Hasta" no puede ser menor a ${minSemana}.`);
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
            window.lastStoreResults = { [sucursal]: { ...datos, nombre: $('#pa-sucursal option:selected').text(), codigo: sucursal } };
            currentAgendaData = { agendaMap: datos.agendaMap, fechasOrdenadas: datos.fechasOrdenadas, sinPlan: datos.sinPlan, isConsolidado: false, nTiendas: 1, auditoriaData: datos.auditoriaData };
            renderAgenda(datos.agendaMap, datos.fechasOrdenadas, datos.sinPlan, false, 1, datos.auditoriaData);
            showDatos();
        }
    } catch (err) {
        console.error('calcularAgenda:', err);
        hideLoader(); showInicial();
        const esPermiso = err.message && err.message.toLowerCase().includes('permiso');
        Swal.fire({
            icon: esPermiso ? 'warning' : 'error',
            title: esPermiso ? 'Sin acceso' : 'Error de conexión',
            text: err.message || 'No se pudo comunicar con el servidor.',
            confirmButtonColor: '#0ea5e9'
        });
    }
}


async function calcularDatosParaSucursal(semDesde, semHasta, semCorte, codSuc) {
    try {
        const fdP = new FormData();
        fdP.append('semana_desde_num', semDesde);
        fdP.append('semana_hasta_num', semHasta);
        fdP.append('cod_sucursal', codSuc);

        const resPedido = await fetch('ajax/pedido_sugerido_calcular_v2.php', { method: 'POST', body: fdP }).then(r => r.json());
        if (!resPedido.ok) {
            // Lanzar error con mensaje del servidor para que calcularAgenda lo capture y muestre al usuario
            throw new Error(resPedido.msg || 'Error al calcular el pedido sugerido.');
        }

        const prodFiltrados = (resPedido.productos || []).filter(p => PA_GRUPOS.includes(p.categoria_insumo));
        if (!prodFiltrados.length) return null;

        const ice = parseFloat($('#pa-crecimiento-esperado').val()) || 0;

        const porCat = {};
        PA_GRUPOS.forEach(c => porCat[c] = []);
        prodFiltrados.forEach(p => {
            p._wls_orig_m = p.wls_m;
            p._wls_orig_b = p.wls_b;
            p._wls_m_forced = false;

            const m = p.wls_m ?? 0;
            const b = p.wls_b ?? 0;
            const n = p.wls_n ?? 0;
            let base_val = Math.max(0, (m * n) + b);

            if (ice > 0) {
                let expected_m = base_val * (ice / 100);
                if (expected_m > m) {
                    p.wls_m = expected_m;
                    p.wls_b = base_val - (expected_m * n);
                    p._wls_m_forced = true;
                }
            }

            p._wls_lff = resPedido.wls_last_fecha_fin;
            porCat[p.categoria_insumo]?.push(p);
        });


        const sinPlan = {}, conPlan = {};
        PA_GRUPOS.forEach(cat => {
            const items = porCat[cat];
            if (!items.length) return;
            if (!items[0].fecha_proximo_despacho) sinPlan[cat] = items;
            else conPlan[cat] = items;
        });

        const limit = limitStr();
        const agendaMap = {};
        const auditoriaMap = {};
        const todayD = todayStr();
        let hasAuditoria = false;

        // ─── Construir el agendaMap con ciclo real por ronda ─────────────
        // Para dias_semana cada despacho puede tener un ciclo distinto (ej: Lun=3d, Mié=4d).
        // calcularCicloSlot() computa los días reales desde la fecha concreta del despacho
        // hasta el siguiente, de modo que el stock_max_final varía entre rondas.
        Object.entries(conPlan).forEach(([cat, items]) => {
            if (items[0] && items[0].fecha_ultimo_despacho) {
                const uDate = items[0].fecha_ultimo_despacho;
                const cicloAud = calcularCicloSlot(items[0], uDate);
                auditoriaMap[cat] = { items, round: 0, cicloSlot: cicloAud, fecha: uDate };
                hasAuditoria = true;
            }

            let cur = (items[0] && items[0].hoy_es_despacho) ? todayD : items[0].fecha_proximo_despacho;
            let round = 1;
            while (cur <= limit) {
                if (!agendaMap[cur]) agendaMap[cur] = {};
                // cicloSlot = días reales que este despacho debe abastecer
                const cicloSlot = calcularCicloSlot(items[0], cur);
                agendaMap[cur][cat] = { items, round, cicloSlot, fecha: cur };
                cur = addDaysStr(cur, cicloSlot);
                round++;
            }
        });

        const fechasOrdenadas = Object.keys(agendaMap).sort();
        if (!fechasOrdenadas.length) return null;

        const prodConPlan = Object.values(conPlan).flat();
        const stockRonda1 = {};
        const despachosReales = {};
        const diasProyRonda1 = {};
        if (prodConPlan.length) {
            const fdPron = new FormData();
            fdPron.append('semana_desde', semDesde);
            fdPron.append('semana_hasta', semHasta);
            fdPron.append('semana_corte', semCorte);
            fdPron.append('cod_sucursal', codSuc);
            prodConPlan.forEach(p => {
                const primeraFechaAgenda = p.hoy_es_despacho ? todayD : p.fecha_proximo_despacho;
                fdPron.append('ids_pp[]', p.id_pp);
                fdPron.append(`fechas_d1[${p.id_pp}]`, addDaysStr(primeraFechaAgenda, -1));
            });
            const resPron = await fetch('ajax/pedido_sugerido_pronostico_v2.php', { method: 'POST', body: fdPron }).then(r => r.json());
            if (resPron.ok) {
                Object.entries(resPron.stocks || {}).forEach(([id, val]) => { stockRonda1[String(id)] = val; });
                Object.entries(resPron.despachos_reales || {}).forEach(([id, val]) => { despachosReales[String(id)] = val; });
                Object.entries(resPron.dias_proy || {}).forEach(([id, val]) => { diasProyRonda1[String(id)] = val; });
            }
        }

        fechasOrdenadas.forEach(fecha => {
            Object.entries(agendaMap[fecha]).forEach(([cat, slot]) => {
                let hayDespachoGrupo = false;
                slot.items.forEach(p => {
                    const dr = despachosReales[String(p.id_pp)]?.[fecha];
                    if (dr !== undefined && dr !== null) {
                        hayDespachoGrupo = true;
                    }
                });

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
                    let despachoRealRondaPaq = null;
                    let invTeoricoAyerPaq = null;

                    const dr = despachosReales[String(p.id_pp)]?.[fecha];
                    if (dr !== undefined && dr !== null) {
                        despachoRealRondaPaq = dr / df;
                    } else if (hayDespachoGrupo) {
                        despachoRealRondaPaq = 0;
                    }

                    if (slot.round === 1) {
                        // Ronda 1: usar el pronóstico real de inventario D-1 restando la proyección de consumo (WLS) por los días faltantes
                        const su = stockRonda1[String(p.id_pp)];
                        const dP = diasProyRonda1[String(p.id_pp)] || 0;

                        let proyD1 = null;
                        if (su !== null && su !== undefined) {
                            proyD1 = su;
                            if (dP > 0) {
                                const fechaD1 = addDaysStr(p.fecha_proximo_despacho, -1);
                                for (let k = 0; k < dP; k++) {
                                    const dStr = addDaysStr(fechaD1, -k);

                                    // Calculate dynamic CD for this specific day
                                    let wls_x_day = p.wls_n ?? 0;
                                    if (resPedido.wls_last_fecha_fin) {
                                        const dF = new Date(resPedido.wls_last_fecha_fin + 'T23:59:59');
                                        const dD = new Date(dStr + 'T12:00:00');
                                        const diffDays = Math.round((dD - dF) / (1000 * 60 * 60 * 24));
                                        const x_offset = Math.ceil(diffDays / 7);
                                        wls_x_day += x_offset;
                                    } else {
                                        wls_x_day += 1;
                                    }
                                    const semC_day = Math.max(0, ((wls_m) * wls_x_day) + (wls_b));
                                    const cd_day = semC_day / 7;

                                    proyD1 -= cd_day;
                                }
                            }
                        }

                        stockD1Paq = (proyD1 !== null) ? Math.max(0, proyD1 / df) : null;
                        invTeoricoAyerPaq = (su !== null && su !== undefined) ? (su / df) : null;
                        preHoyPaq = 0;
                    } else {
                        // Rondas siguientes: simular stock a partir de la ronda anterior
                        const prevRound = p._porRonda?.[slot.round - 1];
                        if (prevRound) {
                            const prevConsPaq = (prevRound.cd_dinamico * prevRound.ciclo) / df;
                            stockD1Paq = Math.max(0, (prevRound.stockPostDespachoPaq ?? 0) - prevConsPaq);
                        } else {
                            stockD1Paq = Math.max(0, (smfSlot ?? 0) - (cd * ciclo) / df); // Fallback
                        }
                    }

                    // Calcular stock post-despacho para esta ronda
                    const invBeforePaq = (stockD1Paq ?? 0);
                    const despSugeridoPaq = Math.max(0, Math.ceil((smfSlot ?? 0) - invBeforePaq));
                    const despachoAUsarPaq = ((window.pa_dias_despacho_real[fecha] || false) && despachoRealRondaPaq !== null) ? despachoRealRondaPaq : despSugeridoPaq;
                    const stockPostDespachoPaq = invBeforePaq + despachoAUsarPaq;

                    if (!p._porRonda) p._porRonda = {};
                    p._porRonda[slot.round] = {
                        stockD1Paq,
                        preHoyPaq,
                        despachoRealRondaPaq,
                        invTeoricoAyerPaq,
                        smSlot,
                        smfSlot,                // stock_max ajustado para este despacho específico
                        sMinSlot: maximos.sMinSlot, // stock mínimo ajustado
                        cd_dinamico: cd,
                        ciclo: ciclo,
                        despSugeridoPaq,
                        stockPostDespachoPaq: stockPostDespachoPaq
                    };

                });
            });
        });

        // Cálculos para Auditoría
        let auditoriaData = null;
        if (hasAuditoria) {
            auditoriaData = {};
            Object.entries(auditoriaMap).forEach(([cat, slot]) => {
                let hayDespachoGrupoAud = false;
                const uDate = slot.fecha;
                slot.items.forEach(p => {
                    const ph = despachosReales[String(p.id_pp)]?.[uDate];
                    if (ph !== undefined && ph !== null) {
                        hayDespachoGrupoAud = true;
                    }
                });

                slot.items.forEach(p => {
                    const df = p.despacho_factor > 0 ? p.despacho_factor : 1;
                    const ciclo = slot.cicloSlot;

                    const wls_m = p.wls_m ?? 0;
                    const wls_b = p.wls_b ?? 0;
                    let wls_x = p.wls_n ?? 0;
                    if (resPedido.wls_last_fecha_fin) {
                        const dF = new Date(resPedido.wls_last_fecha_fin + 'T23:59:59');
                        const dD = new Date(uDate + 'T12:00:00');
                        const diffDays = Math.round((dD - dF) / (1000 * 60 * 60 * 24));
                        const x_offset = Math.ceil(diffDays / 7);
                        wls_x += x_offset;
                    } else {
                        wls_x = Math.floor(p._current_wls_x || (p.wls_n ?? 0));
                    }
                    const semC_ronda = Math.max(0, (wls_m * wls_x) + wls_b);
                    const cd = semC_ronda / 7;

                    const maximos = calcularStockMaxSlot(p, ciclo, cd);

                    const su = stockRonda1[String(p.id_pp)];
                    const invTeoricoAyerPaq = (su !== null && su !== undefined) ? (su / df) : null;
                    const ph = despachosReales[String(p.id_pp)]?.[uDate];
                    let despachoRealRondaPaq = null;
                    if (ph !== undefined && ph !== null) {
                        despachoRealRondaPaq = ph / df;
                    } else if (hayDespachoGrupoAud) {
                        despachoRealRondaPaq = 0;
                    }

                    const stockD1Paq = invTeoricoAyerPaq; 
                    const smfSlot = maximos.smfSlot;
                    
                    const despSugeridoPaq = stockD1Paq !== null ? Math.max(0, Math.ceil((smfSlot ?? 0) - stockD1Paq)) : null;

                    if (!p._porRonda) p._porRonda = {};
                    p._porRonda[0] = {
                        stockD1Paq,
                        preHoyPaq: 0,
                        despachoRealRondaPaq,
                        invTeoricoAyerPaq,
                        smSlot: maximos.smSlot,
                        smfSlot,
                        sMinSlot: maximos.sMinSlot,
                        cd_dinamico: cd,
                        ciclo: ciclo,
                        despSugeridoPaq,
                        stockPostDespachoPaq: null // Not tracked for history
                    };
                });
                auditoriaData[cat] = slot;
            });
        }

        return { agendaMap, fechasOrdenadas, sinPlan, auditoriaData };
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

    window.lastStoreResults = storeResults;
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
                            dias_stock_min: p.dias_stock_min,
                            cons_semanal: 0, stock_minimo: 0, stock_maximo: 0, stock_max_final: 0, stock_minimo_registrado: p.stock_minimo_registrado || 0,
                            _sMinTotal: 0, _smTotal: 0, _smfTotal: 0,
                            _stockD1Total: null, _preHoyTotal: null, _invTeoricoAyerTotal: null, _porTienda: {}, _wls_m_forced: false
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
                    const cdDinamicoRound = p._porRonda?.[slot.round]?.cd_dinamico !== undefined && p._porRonda?.[slot.round]?.cd_dinamico !== null ? p._porRonda?.[slot.round]?.cd_dinamico : ((p.cons_semanal ?? 0) / 7);

                    item._sMinTotal = (item._sMinTotal ?? 0) + parseFloat(sMinRound.toFixed(2));
                    item._smTotal = (item._smTotal ?? 0) + parseFloat(smRound.toFixed(2));
                    item._smfTotal = (item._smfTotal ?? 0) + parseFloat(smfRound.toFixed(2));
                    item._cdTotal = (item._cdTotal ?? 0) + parseFloat(cdDinamicoRound.toFixed(2));
                    item._csTotal = (item._csTotal ?? 0) + parseFloat((cdDinamicoRound * 7).toFixed(2));
                    item._wls_m_forced = item._wls_m_forced || p._wls_m_forced;

                    const rd = p._porRonda?.[slot.round] ?? {};
                    const sd = rd.stockD1Paq, pre = rd.preHoyPaq, invTA = rd.invTeoricoAyerPaq, real = rd.despachoRealRondaPaq, sug = rd.despSugeridoPaq;
                    if (sd !== null && sd !== undefined) item._stockD1Total = (item._stockD1Total ?? 0) + parseFloat(sd.toFixed(2));
                    if (pre !== null && pre !== undefined) item._preHoyTotal = (item._preHoyTotal ?? 0) + parseFloat(pre.toFixed(2));
                    if (invTA !== null && invTA !== undefined) item._invTeoricoAyerTotal = (item._invTeoricoAyerTotal ?? 0) + parseFloat(invTA.toFixed(2));
                    if (real !== null && real !== undefined) item._realTotal = (item._realTotal ?? 0) + parseFloat(real.toFixed(2));
                    if (sug !== null && sug !== undefined) item._sugTotal = (item._sugTotal ?? 0) + parseFloat(sug.toFixed(2));

                    let stockD1Paq_sub = sd;
                    let sub_despSugerido = stockD1Paq_sub !== null ? Math.max(0, Math.ceil((smfRound ?? 0) - stockD1Paq_sub)) : 0;
                    let sub_aUsar = ((window.pa_dias_despacho_real[fecha] || false) && real !== null && real !== undefined) ? real : sub_despSugerido;

                    item._sugTotalCalc = (item._sugTotalCalc ?? 0) + sub_despSugerido;
                    item._aUsarTotal = (item._aUsarTotal ?? 0) + sub_aUsar;

                    item._porTienda[cod] = {
                        nombre: slot.nombre, round: slot.round,
                        cons_semanal: p.cons_semanal, stock_minimo: p.stock_minimo,
                        sMinSlot: sMinRound,
                        stock_maximo: smRound,
                        stock_max_final: smfRound,
                        stockD1Paq: sd, preHoyPaq: pre, invTeoricoAyerPaq: invTA,
                        despachoRealRondaPaq: real, despSugeridoPaq: sub_despSugerido, despAUsarPaq: sub_aUsar,
                        cd_dinamico: p._porRonda?.[slot.round]?.cd_dinamico,
                        _wls_m_forced: p._wls_m_forced
                    };
                });
            });
            agendaMap[fecha][cat] = { items: Object.values(byPP), tiendas, isConsolidado: true, fecha: fecha };
        });
        if (!Object.keys(agendaMap[fecha]).length) delete agendaMap[fecha];
    });

    Object.values(storeResults).forEach(sr => {
        Object.entries(sr.sinPlan || {}).forEach(([cat]) => { sinPlan[cat] = true; });
    });

    return { agendaMap, fechasOrdenadas: fechasOrdenadas.filter(f => agendaMap[f]), sinPlan };
}

function renderAgenda(agendaMap, fechasOrdenadas, sinPlan, isConsolidado = false, nTiendas = 1, auditoriaData = null) {
    const $w = $('#pa-warnings').empty();
    const spk = Object.keys(sinPlan);
    if (spk.length) {
        const labels = spk.map(c => `<strong>${c} — ${PA_LABELS[c]}</strong>`).join(', ');
        $w.html(`<div class="pa-warning-banner"><i class="bi bi-exclamation-triangle-fill"></i><div>Los siguientes grupos <strong>no tienen Plan de Despacho activo</strong> configurado y no se muestran en la agenda: ${labels}. Verifica la configuración en <em>Configuración Logística</em>.</div></div>`).removeClass('d-none');
    } else {
        $w.addClass('d-none');
    }

    let html = '';

    if (auditoriaData && !isConsolidado && window.PA_AUDITORIA_PASADA) {
        html += `
        <div class="pa-date-block pa-date-hoy" style="border: 2px solid #0ea5e9; padding: 10px; border-radius: 12px; background: #f0f9ff; margin-bottom: 2rem;">
            <div class="pa-date-header" style="cursor: pointer;" onclick="$('#pa-cats-hoy').slideToggle(); $(this).find('.pa-expand-date-icon').toggleClass('rotated');">
                <div class="pa-date-pill" style="background:#0ea5e9; color:white;">
                    <div class="pa-date-day-num" style="color:white;"><i class="bi bi-clock-history"></i></div>
                    <div class="pa-date-info">
                        <div class="pa-date-weekday" style="color:white;">Auditoría de Último Despacho</div>
                        <div class="pa-date-monthyear" style="color:rgba(255,255,255,0.9);">Anterior</div>
                    </div>
                </div>
                <div class="pa-date-line" style="border-color:#bae6fd;"></div>
                <i class="bi bi-chevron-down pa-expand-date-icon" style="color: #0ea5e9; font-size: 1.5rem; transition: transform 0.3s; margin-left: 10px;"></i>
            </div>
            <div class="pa-cats-row" id="pa-cats-hoy" style="display: none;">${buildCatsHtml(auditoriaData, isConsolidado, 'AUD', true)}</div>
        </div>`;
    }

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
            <div class="pa-cats-row">${buildCatsHtml(cats, isConsolidado, fecha, false)}</div>
        </div>`;
    });

    $('#pa-agenda').html(html || '<p class="text-muted text-center p-5">Sin datos para mostrar.</p>');
}

function buildCatsHtml(cats, isConsolidado, fechaArg, isHoy = false) {
    let html = '';
    PA_GRUPOS.forEach(cat => {
        if (!cats[cat]) return;
        const slot = cats[cat];
        const fecha = isHoy ? slot.fecha : fechaArg;

        // Badge de ronda / tiendas
        let badge;
        if (isConsolidado) {
            const n = Object.keys(slot.tiendas).length;
            badge = `<span class="pa-round-badge" style="background:rgba(16,185,129,0.12);color:#10b981;">${n} Tienda${n > 1 ? 's' : ''}</span>`;
        } else {
            badge = isHoy ? '<span class="pa-round-badge" style="background:rgba(14, 165, 233, 0.15);color:#0ea5e9;">Auditoría Hoy</span>' : (slot.round === 1
                ? '<span class="pa-round-badge" style="background:rgba(56,189,248,0.15);color:#38bdf8;">Ronda 1 · Inventario Real</span>'
                : `<span class="pa-round-badge">Ronda ${slot.round} · Proyección</span>`);
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
                if (!isHoy && (window.pa_dias_despacho_real[fecha] || false) && s_final !== null && pre_hoy) {
                    s_final += pre_hoy;
                }
                let despSugerido = s_final !== null ? Math.max(0, Math.ceil((smf ?? 0) - s_final)) : null;
                let real = isConsolidado ? p._realTotal : (p._porRonda?.[slot.round]?.despachoRealRondaPaq);
                let despAUsar = ((window.pa_dias_despacho_real[fecha] || false) && real !== null && real !== undefined) ? real : despSugerido;

                if (despAUsar !== null && despAUsar !== undefined) totalDespacho += despAUsar;
            });
            badgeB = `<span class="pa-round-badge" style="margin-left:auto; background:rgba(14,165,233,0.1); color:#0ea5e9; font-size:13px; font-weight:800; padding:4px 12px;">Total Despacho: ${totalDespacho}</span>`;
        }

        const slotKey = `${fecha.replace(/-/g, '')}-${cat}${isHoy ? '-HOY' : ''}`;
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
                ${buildTablaProductos(slot, isConsolidado, slotKey, isHoy, fecha)}
            </div>
        </div>`;
    });
    return html;
}

function buildTablaProductos(slot, isConsolidado, slotKey, isHoy = false, fecha = '') {
    const items = slot.items;
    const round = slot.round;
    let rows = '';

    items.forEach(p => {
        let stockD1Paq_base, preHoyPaq, despachoRealRondaPaq, smfDisplay, smDisplay, sMinDisplay, cdDisplay, csDisplay, invTeoricoAyerPaq, sugTotalConsolidado, aUsarTotalConsolidado;
        if (isConsolidado) {
            stockD1Paq_base = p._stockD1Total;
            preHoyPaq = p._preHoyTotal;
            despachoRealRondaPaq = p._realTotal;
            smfDisplay = p._smfTotal;
            smDisplay = p._smTotal;
            sMinDisplay = p._sMinTotal;
            cdDisplay = p._cdTotal !== undefined ? p._cdTotal : null;
            csDisplay = p._csTotal !== undefined ? p._csTotal : null;
            invTeoricoAyerPaq = p._invTeoricoAyerTotal;
            sugTotalConsolidado = p._sugTotalCalc;
            aUsarTotalConsolidado = p._aUsarTotal;
        } else {
            const rd = p._porRonda?.[round] ?? {};
            stockD1Paq_base = rd.stockD1Paq;
            preHoyPaq = rd.preHoyPaq;
            despachoRealRondaPaq = rd.despachoRealRondaPaq;
            smfDisplay = rd.smfSlot ?? p.stock_max_final;
            smDisplay = rd.smSlot ?? p.stock_maximo;
            sMinDisplay = rd.sMinSlot ?? p.stock_minimo;
            cdDisplay = rd.cd_dinamico ?? (p.cons_semanal !== null && p.cons_semanal !== undefined ? (p.cons_semanal / 7) : null);
            invTeoricoAyerPaq = rd.invTeoricoAyerPaq;
        }

        if (csDisplay === undefined) {
            csDisplay = cdDisplay !== null ? cdDisplay * 7 : null;
        }

        let forcedArrow = '';
        if (p._wls_m_forced) {
            forcedArrow = ' <i class="fas fa-arrow-up text-primary" title="Crecimiento forzado por porcentaje esperado" style="font-size:0.9em;"></i>';
        }
        let csHtml = csDisplay !== null ? fmt2(csDisplay) + forcedArrow : fmt2(null);

        const df = p.despacho_factor > 0 ? p.despacho_factor : 1;

        let stockD1Paq = stockD1Paq_base;
        let includeHoy = (!isHoy && (window.pa_dias_despacho_real[fecha] || false) && stockD1Paq !== null && preHoyPaq && round === 1);
        if (includeHoy) {
            stockD1Paq += preHoyPaq;
        }

        let despSugerido = isConsolidado ? sugTotalConsolidado : (stockD1Paq !== null ? Math.max(0, Math.ceil((smfDisplay ?? 0) - stockD1Paq)) : null);
        let despAUsar = isConsolidado ? aUsarTotalConsolidado : (((window.pa_dias_despacho_real[fecha] || false) && despachoRealRondaPaq !== null && despachoRealRondaPaq !== undefined) ? despachoRealRondaPaq : despSugerido);

        // Convert to Unid de control for display
        const sMinDisplayCtrl = sMinDisplay !== null && sMinDisplay !== undefined ? sMinDisplay * df : null;
        const smDisplayCtrl = smDisplay !== null && smDisplay !== undefined ? smDisplay * df : null;
        const smfDisplayCtrl = smfDisplay !== null && smfDisplay !== undefined ? smfDisplay * df : null;
        const invTeoricoAyerCtrl = invTeoricoAyerPaq !== null && invTeoricoAyerPaq !== undefined ? invTeoricoAyerPaq * df : null;
        const stockD1Ctrl = stockD1Paq !== null && stockD1Paq !== undefined ? stockD1Paq * df : null;
        const preHoyCtrl = preHoyPaq !== null && preHoyPaq !== undefined ? preHoyPaq * df : null;
        const realRondaCtrl = despachoRealRondaPaq !== null && despachoRealRondaPaq !== undefined ? despachoRealRondaPaq * df : null;
        const despSugeridoCtrl = despSugerido !== null && despSugerido !== undefined ? despSugerido * df : null;
        const despAUsarCtrl = despAUsar !== null && despAUsar !== undefined ? despAUsar * df : null;

        const smfRefCtrl = smfDisplayCtrl ?? 0;
        let stockHtml;
        if (stockD1Ctrl === null || stockD1Ctrl === undefined) {
            stockHtml = '<span class="pa-na">Sin datos</span>';
        } else {
            let extraIcon = includeHoy ? ` <i class="bi bi-info-circle-fill text-info ms-1" style="font-size:10px" title="Incluye +${preHoyCtrl.toFixed(1)} recibido en auditoría de hoy"></i>` : '';
            stockHtml = `<span class="pa-stock-d1">${stockD1Ctrl.toFixed(1)}</span>${extraIcon}`;
        }

        let despHtmlSugeridoCtrl = despSugeridoCtrl === null || despSugeridoCtrl === undefined ? '<span class="pa-na">—</span>' : `<span>${despSugeridoCtrl.toFixed(1)}</span>`;
        
        let despHtmlRealPaq = '';
        if (isHoy) {
             despHtmlRealPaq = despachoRealRondaPaq !== null && despachoRealRondaPaq !== undefined ? `<span class="pa-desp-val ok" style="color:blue;">${despachoRealRondaPaq.toFixed(1)}</span>` : '<span class="pa-na">—</span>';
        } else {
             despHtmlRealPaq = despachoRealRondaPaq !== null && despachoRealRondaPaq !== undefined ? `<del class="text-muted" style="font-size: 0.9em;">${despachoRealRondaPaq.toFixed(1)}</del>` : '<span class="pa-na">—</span>';
        }

        let finalHtmlCtrl = '';
        let finalHtmlPaq = '';
        if (despAUsarCtrl === null || despAUsarCtrl === undefined) {
             finalHtmlCtrl = '<span class="pa-na">—</span>';
             finalHtmlPaq = '<span class="pa-na">—</span>';
        } else {
             let isReal = ((window.pa_dias_despacho_real[fecha] || false) && realRondaCtrl !== null && realRondaCtrl !== undefined);
             if (isReal && !isHoy) {
                 despHtmlRealPaq = `<span class="pa-desp-val ${despachoRealRondaPaq > 0 ? 'needs' : 'ok'} fw-bold">${despachoRealRondaPaq.toFixed(1)}</span>`;
                 finalHtmlCtrl = `<span class="pa-desp-val ${despSugeridoCtrl > 0 ? 'needs' : 'ok'}">${despSugeridoCtrl.toFixed(1)}</span>`;
                 finalHtmlPaq = `<del class="text-muted" style="font-size: 0.9em;">${despSugerido.toFixed(1)}</del>`;
             } else {
                 finalHtmlCtrl = `<span class="pa-desp-val ${despSugeridoCtrl > 0 ? 'needs' : 'ok'}">${despSugeridoCtrl.toFixed(1)}</span>`;
                 finalHtmlPaq = `<span class="pa-desp-val ${despSugerido > 0 ? 'needs' : 'ok'}">${despSugerido.toFixed(1)}</span>`;
             }
             if(isHoy) {
                 finalHtmlCtrl = realRondaCtrl !== null && realRondaCtrl !== undefined ? `<span class="pa-desp-val ok" style="color:blue;">${realRondaCtrl.toFixed(1)}</span>` : '<span class="pa-na">—</span>';
                 finalHtmlPaq = despAUsar !== null ? `<span class="pa-desp-val ok" style="color:blue;">${despAUsar.toFixed(1)}</span>` : '<span class="pa-na">—</span>';
                 despHtmlSugeridoCtrl = `<del class="text-muted" style="font-size: 0.9em;">${despSugeridoCtrl.toFixed(1)}</del>`;
             }
        }

        const smfCell = smfDisplayCtrl !== null && smfDisplayCtrl !== undefined
            ? fmt2(smfDisplayCtrl)
            : '<span class="pa-na">N/A</span>';

        let invTAHtml;
        if (invTeoricoAyerCtrl === null || invTeoricoAyerCtrl === undefined) {
            invTAHtml = '<span class="pa-na">—</span>';
        } else {
            invTAHtml = `<span>${invTeoricoAyerCtrl.toFixed(1)}</span>`;
        }

        const tdDatosCompletos = window.PA_DATOS_COMPLETOS ? `<td>${smfCell}</td><td class="pa-col-desp">${invTAHtml}</td>` : '';
        const colspan = 10 + (window.PA_DATOS_COMPLETOS ? 2 : 0);

        if (isConsolidado) {
            rows += `
            <tr class="pa-row-expandible" data-pp-id="${p.id_pp}" data-slot-key="${slotKey}">
                <td><div class="d-flex align-items-center"><div class="pa-prod-name">${esc(p.nombre)}</div><i class="bi bi-chevron-right pa-expand-icon ms-2" style="margin-right: 0;"></i></div></td>
                <td>${csHtml}</td>
                <td>${cdDisplay !== null ? fmt2(cdDisplay) : fmt2(null)}</td>
                <td>${fmt2(sMinDisplayCtrl)}</td>
                <td>${fmt2(smDisplayCtrl)}</td>
                ${tdDatosCompletos}
                <td class="pa-col-desp">${stockHtml}</td>
                <td class="pa-col-desp text-center">${despHtmlSugeridoCtrl}</td>
                <td><span class="pa-unit">${esc(p.despacho_presentacion || p.unidad || '—')}</span></td>
                <td class="pa-col-desp">${finalHtmlPaq}</td>
                <td class="pa-col-desp text-center" style="background:#f8fafc;">${despHtmlRealPaq}</td>
            </tr>
            ${buildSubRowsTiendas(p, slotKey, fecha)}`;
        } else {
            const dsStr = slotKey.split('-')[0];
            const fDesp = `${dsStr.substring(0, 4)}-${dsStr.substring(4, 6)}-${dsStr.substring(6, 8)}`;
            const sucVal = $('#pa-sucursal').val();

            let rowRatio = 1;
            const df_ = p.despacho_factor > 0 ? p.despacho_factor : 1;
            if (p.es_ajustado && p.stock_maximo > 0 && p.stock_max_final !== null) {
                rowRatio = (p.stock_max_final * df_) / (p.stock_maximo * df_);
            }

            rows += `
            <tr class="pa-row-expandible-charts" style="cursor:pointer;" data-pp-id="${p.id_pp}" data-slot-key="${slotKey}" data-sucursal="${sucVal}" data-fecha-despacho="${fDesp}" data-ciclo="${slot.cicloSlot}"
                data-wls-m="${p.wls_m ?? 0}" data-wls-b="${p.wls_b ?? 0}" data-wls-n="${p.wls_n ?? 0}" data-wls-lff="${p._wls_lff || ''}" data-dsm="${p.dias_stock_min ?? 0}" data-ratio="${rowRatio}" data-smin-registrado="${p.stock_minimo_registrado ?? 0}"
                data-plan-tipo="${esc(p.plan_tipo_frecuencia || '')}" data-plan-dias="${esc(JSON.stringify(p.plan_dias_semana || []))}" data-plan-semanas="${p.plan_intervalo_semanas || 1}" data-dias-ciclo="${p.dias_ciclo || 7}">
                <td><div class="d-flex align-items-center"><div class="pa-prod-name">${esc(p.nombre)}</div><i class="bi bi-chevron-right pa-expand-icon ms-2" style="margin-right: 0;"></i></div></td>
                <td>${csHtml}</td>
                <td>${cdDisplay !== null ? fmt2(cdDisplay) : fmt2(null)}</td>
                <td>${fmt2(sMinDisplayCtrl)}</td>
                <td>${fmt2(smDisplayCtrl)}</td>
                ${tdDatosCompletos}
                <td class="pa-col-desp">${stockHtml}</td>
                <td class="pa-col-desp text-center">${despHtmlSugeridoCtrl}</td>
                <td><span class="pa-unit">${esc(p.despacho_presentacion || p.unidad || '—')}</span></td>
                <td class="pa-col-desp">${finalHtmlPaq}</td>
                <td class="pa-col-desp text-center" style="background:#f8fafc;">${despHtmlRealPaq}</td>
            </tr>
            <tr class="pa-chart-sub d-none" data-slot-key="${slotKey}" data-pp-id="${p.id_pp}">
                <td colspan="${colspan}" class="p-0">
                    <div class="pa-charts-container d-flex flex-wrap flex-md-nowrap" style="width: 100%; border-top: 1px solid #eee; background: #fafafa;">
                        <div class="pa-chart-wrapper w-100 w-md-50 p-3" style="border-right: 1px solid #eee;">
                            <div class="fw-bold mb-2 text-center" style="color:#0E544C; font-size: 0.85rem;"><i class="fas fa-chart-line me-2"></i>Consumo Real y Pronostico (Unidades de Control)</div>
                            <div style="height: 320px; position: relative;">
                                <canvas id="chart-tend-${slotKey}-${p.id_pp}"></canvas>
                            </div>
                        </div>
                        <div class="pa-chart-wrapper w-100 w-md-50 p-3">
                            <div class="fw-bold mb-2 text-center" style="color:#0E544C; font-size: 0.85rem;"><i class="fas fa-boxes me-2"></i>Movimiento de Stock (Unidades de Control)</div>
                            <div style="height: 320px; position: relative;">
                                <canvas id="chart-kardex-${slotKey}-${p.id_pp}"></canvas>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>`;
        }
    });

    const isChecked = (window.pa_dias_despacho_real[fecha] || false) ? 'checked' : '';
    const thDespachoReal = isHoy ? `Despacho Real<br>
            <small style="font-size:9px;color:#9ca3af;font-weight:normal;text-transform:none;letter-spacing:normal;">(Unid. Despacho)</small>` :
            `Despacho Real<br>
            <small style="font-size:9px;color:#9ca3af;font-weight:normal;text-transform:none;letter-spacing:normal;">(Unid. Despacho)</small><br>
            <div class="form-check form-switch d-inline-block mt-1">
                <input class="form-check-input pa-toggle-preingreso" type="checkbox" data-fecha="${fecha}" title="Usar despachos reales en la proyección" ${isChecked}>
            </div>`;

    const thDatosCompletos = window.PA_DATOS_COMPLETOS ? `<th>Stock Máx Ajustado<br><small style="font-size:9px;color:#9ca3af;font-weight:normal;text-transform:none;letter-spacing:normal;">(Unid. de control)</small></th><th>Inv. Teórico Ayer<br><small style="font-size:9px;color:#9ca3af;font-weight:normal;text-transform:none;letter-spacing:normal;">(Unid. de control)</small></th>` : '';

    let dsmText = '3 dias';
    if (items && items.length > 0 && items[0].dias_stock_min !== undefined && items[0].dias_stock_min !== null) {
        dsmText = items[0].dias_stock_min + (items[0].dias_stock_min == 1 ? ' dia' : ' dias');
    }

    const thead = `<thead><tr>
        <th style="text-align:left">Producto</th>
        <th>Pronostico consumo semana<br><small style="font-size:9px;color:#9ca3af;font-weight:normal;text-transform:none;letter-spacing:normal;">(Unid. de control)</small></th>
        <th>Pronostico consumo dia<br><small style="font-size:9px;color:#9ca3af;font-weight:normal;text-transform:none;letter-spacing:normal;">(Unid. de control)</small></th>
        <th>Stock Minimo<br><small style="font-size:9px;color:#9ca3af;font-weight:normal;text-transform:none;letter-spacing:normal;">(${dsmText} - Unid. de control)</small></th>
        <th>Requerido Total<br><small style="font-size:9px;color:#9ca3af;font-weight:normal;text-transform:none;letter-spacing:normal;">(Unid. de control)</small></th>
        ${thDatosCompletos}
        <th>Pronostico de inventario al dia de despacho<br><small style="font-size:9px;color:#9ca3af;font-weight:normal;text-transform:none;letter-spacing:normal;">(Unid. de control)</small></th>
        <th>Despacho requerido<br><small style="font-size:9px;color:#9ca3af;font-weight:normal;text-transform:none;letter-spacing:normal;">(Unid. de control)</small></th>
        <th>Presentacion de despacho</th>
        <th>Despacho requerido<br><small style="font-size:9px;color:#9ca3af;font-weight:normal;text-transform:none;letter-spacing:normal;">(Unid despacho)</small></th>
        <th style="width: 100px;">${thDespachoReal}</th>
    </tr></thead>`;

    return `<table class="pa-table">${thead}<tbody>${rows || '<tr class="pa-no-data-row"><td colspan="12">Sin productos</td></tr>'}</tbody></table>`;
}

function buildSubRowsTiendas(item, slotKey, fecha) {
    let rows = '';
    Object.entries(item._porTienda || {}).forEach(([cod, td]) => {
        const smf = td.stock_max_final ?? 0;
        const df = item.despacho_factor > 0 ? item.despacho_factor : 1;

        let stockD1Paq = td.stockD1Paq;
        let includeHoy = false;

        let despSugerido = stockD1Paq !== null ? Math.max(0, Math.ceil((smf ?? 0) - stockD1Paq)) : null;
        let despAUsar = ((window.pa_dias_despacho_real[fecha] || false) && td.despachoRealRondaPaq !== null && td.despachoRealRondaPaq !== undefined) ? td.despachoRealRondaPaq : despSugerido;

        const stockD1Ctrl = stockD1Paq !== null && stockD1Paq !== undefined ? stockD1Paq * df : null;
        const smfCtrl = smf * df;
        const smCtrl = (td.stock_maximo ?? 0) * df;
        const sMinCtrl = (td.sMinSlot ?? td.stock_minimo ?? 0) * df;
        const preHoyCtrl = td.preHoyPaq !== null && td.preHoyPaq !== undefined ? td.preHoyPaq * df : null;
        const realRondaCtrl = td.despachoRealRondaPaq !== null && td.despachoRealRondaPaq !== undefined ? td.despachoRealRondaPaq * df : null;
        const despSugeridoCtrl = despSugerido !== null && despSugerido !== undefined ? despSugerido * df : null;
        const invTeoricoAyerCtrl = td.invTeoricoAyerPaq !== null && td.invTeoricoAyerPaq !== undefined ? td.invTeoricoAyerPaq * df : null;

        let sHtml;
        if (stockD1Ctrl === null || stockD1Ctrl === undefined) {
            sHtml = '<span class="pa-na">Sin datos</span>';
        } else {
            let extraIcon = includeHoy ? ` <i class="bi bi-info-circle-fill text-info ms-1" style="font-size:10px"></i>` : '';
            sHtml = `<span class="pa-stock-d1">${stockD1Ctrl.toFixed(1)}</span>${extraIcon}`;
        }

        let despHtmlSugeridoCtrl = despSugeridoCtrl === null || despSugeridoCtrl === undefined ? '<span class="pa-na">—</span>' : `<span>${despSugeridoCtrl.toFixed(1)}</span>`;
        
        let despHtmlRealPaq = '';
        if (td.round === 0) {
             despHtmlRealPaq = td.despachoRealRondaPaq !== null && td.despachoRealRondaPaq !== undefined ? `<span class="pa-desp-val ok" style="color:blue;">${td.despachoRealRondaPaq.toFixed(1)}</span>` : '<span class="pa-na">—</span>';
        } else {
             despHtmlRealPaq = td.despachoRealRondaPaq !== null && td.despachoRealRondaPaq !== undefined ? `<del class="text-muted" style="font-size: 0.9em;">${td.despachoRealRondaPaq.toFixed(1)}</del>` : '<span class="pa-na">—</span>';
        }

        let finalHtmlPaq = '';
        if (despAUsar === null || despAUsar === undefined) {
             finalHtmlPaq = '<span class="pa-na">—</span>';
        } else {
             let isReal = ((window.pa_dias_despacho_real[fecha] || false) && td.despachoRealRondaPaq !== null && td.despachoRealRondaPaq !== undefined);
             if (isReal && td.round !== 0) {
                 despHtmlRealPaq = `<span class="pa-desp-val ${td.despachoRealRondaPaq > 0 ? 'needs' : 'ok'} fw-bold">${td.despachoRealRondaPaq.toFixed(1)}</span>`;
                 finalHtmlPaq = `<del class="text-muted" style="font-size: 0.9em;">${despSugerido.toFixed(1)}</del>`;
             } else {
                 finalHtmlPaq = `<span class="pa-desp-val ${despSugerido > 0 ? 'needs' : 'ok'}">${despSugerido.toFixed(1)}</span>`;
             }
             if(td.round === 0) {
                 despHtmlRealPaq = td.despachoRealRondaPaq !== null ? `<span class="pa-desp-val ok" style="color:blue;">${td.despachoRealRondaPaq.toFixed(1)}</span>` : '<span class="pa-na">—</span>';
                 finalHtmlPaq = despAUsar !== null ? `<span class="pa-desp-val ok" style="color:blue;">${despAUsar.toFixed(1)}</span>` : '<span class="pa-na">—</span>';
                 despHtmlSugeridoCtrl = `<del class="text-muted" style="font-size: 0.9em;">${despSugeridoCtrl.toFixed(1)}</del>`;
             }
        }

        let tdCdDisplay = td.cd_dinamico !== null && td.cd_dinamico !== undefined ? td.cd_dinamico : (td.cons_semanal !== null && td.cons_semanal !== undefined ? (td.cons_semanal / 7) : null);
        let tdCsDisplay = tdCdDisplay !== null ? tdCdDisplay * 7 : null;
        let subForcedArrow = '';
        if (td._wls_m_forced) {
            subForcedArrow = ' <i class="fas fa-arrow-up text-primary" title="Crecimiento forzado por porcentaje esperado" style="font-size:0.9em;"></i>';
        }
        let tdCsHtml = tdCsDisplay !== null ? fmt2(tdCsDisplay) + subForcedArrow : fmt2(null);

        let tdInvTAHtml = (invTeoricoAyerCtrl === null || invTeoricoAyerCtrl === undefined) ? '<span class="pa-na">—</span>' : `<span>${invTeoricoAyerCtrl.toFixed(1)}</span>`;
        
        const tdDatosCompletos = window.PA_DATOS_COMPLETOS ? `<td>${fmt2(smfCtrl)}</td><td class="pa-col-desp">${tdInvTAHtml}</td>` : '';

        rows += `
        <tr class="pa-tienda-row pa-tienda-sub d-none" data-slot-key="${slotKey}" data-pp-id="${item.id_pp}">
            <td><span class="pa-tienda-badge">${esc(td.nombre)}</span></td>
            <td>${tdCsHtml}</td>
            <td>${tdCdDisplay !== null ? fmt2(tdCdDisplay) : fmt2(null)}</td>
            <td>${fmt2(sMinCtrl)}</td>
            <td>${fmt2(smCtrl)}</td>
            ${tdDatosCompletos}
            <td class="pa-col-desp">${sHtml}</td>
            <td class="pa-col-desp text-center">${despHtmlSugeridoCtrl}</td>
            <td></td>
            <td class="pa-col-desp">${finalHtmlPaq}</td>
            <td class="pa-col-desp text-center" style="background:#f8fafc;">${despHtmlRealPaq}</td>
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

    let tiendaPrincipal = 'Consolidado';
    if (!currentAgendaData.isConsolidado && window.lastStoreResults) {
        const sr = Object.values(window.lastStoreResults)[0];
        if (sr && sr.nombre) tiendaPrincipal = sr.nombre;
    }

    currentAgendaData.fechasOrdenadas.forEach(fecha => {
        const agenda = currentAgendaData.agendaMap[fecha];
        PA_GRUPOS.forEach(cat => {
            if (!agenda[cat]) return;
            const slot = agenda[cat];

            // Recorrer los items
            slot.items.forEach(p => {
                let stockD1Paq_base, preHoyPaq, smfDisplay, smDisplay, sMinDisplay, cdDisplay, csDisplay, invTeoricoAyerPaq, despTotalConsolidado;
                if (currentAgendaData.isConsolidado) {
                    stockD1Paq_base = p._stockD1Total;
                    preHoyPaq = p._preHoyTotal;
                    smfDisplay = p._smfTotal;
                    smDisplay = p._smTotal;
                    sMinDisplay = p._sMinTotal;
                    cdDisplay = p._cdTotal !== undefined ? p._cdTotal : null;
                    csDisplay = p._csTotal !== undefined ? p._csTotal : null;
                    invTeoricoAyerPaq = p._invTeoricoAyerTotal;
                    despTotalConsolidado = p._despTotal;
                } else {
                    const rd = p._porRonda?.[slot.round] ?? {};
                    stockD1Paq_base = rd.stockD1Paq;
                    preHoyPaq = rd.preHoyPaq;
                    smfDisplay = rd.smfSlot ?? p.stock_max_final;
                    smDisplay = rd.smSlot ?? p.stock_maximo;
                    sMinDisplay = rd.sMinSlot ?? p.stock_minimo;
                    cdDisplay = rd.cd_dinamico ?? (p.cons_semanal !== null && p.cons_semanal !== undefined ? (p.cons_semanal / 7) : null);
                    csDisplay = cdDisplay !== null ? cdDisplay * 7 : null;
                    invTeoricoAyerPaq = rd.invTeoricoAyerPaq;
                }

                let stockD1Paq = stockD1Paq_base;
                let includeHoy = ((window.pa_dias_despacho_real[fecha] || false) && stockD1Paq !== null && preHoyPaq && slot.round === 1);
                if (includeHoy) {
                    stockD1Paq += preHoyPaq;
                }
                
                let realRondaPaq = currentAgendaData.isConsolidado ? p._realTotal : (p._porRonda?.[slot.round]?.despachoRealRondaPaq);
                let sugPaq = currentAgendaData.isConsolidado ? p._sugTotalCalc : (stockD1Paq !== null ? Math.max(0, Math.ceil((smfDisplay ?? 0) - stockD1Paq)) : null);
                let despAUsar = currentAgendaData.isConsolidado ? p._aUsarTotal : (((window.pa_dias_despacho_real[fecha] || false) && realRondaPaq !== null && realRondaPaq !== undefined) ? realRondaPaq : sugPaq);

                const df = p.despacho_factor > 0 ? p.despacho_factor : 1;

                // Apply * df for displaying in Control units
                const sMinDisplayCtrl = sMinDisplay !== null && sMinDisplay !== undefined ? sMinDisplay * df : null;
                const smDisplayCtrl = smDisplay !== null && smDisplay !== undefined ? smDisplay * df : null;
                const smfDisplayCtrl = smfDisplay !== null && smfDisplay !== undefined ? smfDisplay * df : null;
                const invTeoricoAyerCtrl = invTeoricoAyerPaq !== null && invTeoricoAyerPaq !== undefined ? invTeoricoAyerPaq * df : null;
                const stockD1Ctrl = stockD1Paq !== null && stockD1Paq !== undefined ? stockD1Paq * df : null;
                const realRondaCtrl = realRondaPaq !== null && realRondaPaq !== undefined ? realRondaPaq * df : null;
                const sugCtrl = sugPaq !== null && sugPaq !== undefined ? sugPaq * df : null;
                const despAUsarCtrl = despAUsar !== null && despAUsar !== undefined ? despAUsar * df : null;

                let pronosticoInv = stockD1Ctrl !== null && stockD1Ctrl !== undefined ? stockD1Ctrl.toFixed(1) : 'Sin datos';
                let despachoRealInfo = realRondaCtrl !== null && realRondaCtrl !== undefined ? realRondaCtrl.toFixed(1) : '-';

                let obj = {
                    "Tienda": tiendaPrincipal,
                    "Fecha de Despacho": fecha,
                    "Grupo": PA_LABELS[cat] || cat,
                    "Producto": p.nombre,
                    "Pronostico consumo semana (Unid. de control)": csDisplay !== null ? parseFloat(csDisplay).toFixed(1) : '',
                    "Pronostico consumo dia (Unid. de control)": cdDisplay !== null ? parseFloat(cdDisplay).toFixed(1) : '',
                    "Stock Minimo (Unid. de control)": sMinDisplayCtrl !== null ? parseFloat(sMinDisplayCtrl).toFixed(1) : '',
                    "Requerido Total (Unid. de control)": smDisplayCtrl !== null ? parseFloat(smDisplayCtrl).toFixed(1) : ''
                };
                
                if (window.PA_DATOS_COMPLETOS) {
                    obj["Stock Máx Ajustado (Unid. de control)"] = smfDisplayCtrl !== null ? parseFloat(smfDisplayCtrl).toFixed(1) : '';
                    obj["Inv. Teórico Ayer (Unid. de control)"] = invTeoricoAyerCtrl !== null ? parseFloat(invTeoricoAyerCtrl).toFixed(1) : '';
                }
                
                obj["Pronostico de inventario al dia de despacho (Unid. de control)"] = pronosticoInv;
                obj["Despacho requerido (Unid. de control)"] = sugCtrl !== null ? parseFloat(sugCtrl).toFixed(1) : '-';
                obj["Presentacion de despacho"] = p.despacho_presentacion || p.unidad || '-';
                obj["Despacho requerido (Unid despacho)"] = despAUsar !== null ? parseFloat(despAUsar).toFixed(1) : '-';
                obj["Despacho Real (Unid. Despacho)"] = realRondaPaq !== null && realRondaPaq !== undefined ? parseFloat(realRondaPaq).toFixed(1) : '-';
                
                datosExportar.push(obj);

                if (currentAgendaData.isConsolidado && p._porTienda) {
                    Object.entries(p._porTienda).forEach(([cod, td]) => {
                        let sub_smfDisplay = td.stock_max_final ?? 0;
                        let sub_smDisplay = td.stock_maximo ?? 0;
                        let sub_stockD1Paq = td.stockD1Paq;
                        let sub_sMinSlot = td.sMinSlot ?? td.stock_minimo;

                        let sub_includeHoy = ((window.pa_dias_despacho_real[fecha] || false) && sub_stockD1Paq !== null && td.preHoyPaq && td.round === 1);
                        if (sub_includeHoy) {
                            sub_stockD1Paq += td.preHoyPaq;
                        }

                        let sub_sugPaq = sub_stockD1Paq !== null ? Math.max(0, Math.ceil((sub_smfDisplay ?? 0) - sub_stockD1Paq)) : null;
                        let sub_realPaq = td.despachoRealRondaPaq;
                        let sub_aUsar = ((window.pa_dias_despacho_real[fecha] || false) && sub_realPaq !== null && sub_realPaq !== undefined) ? sub_realPaq : sub_sugPaq;

                        const sub_sMinDisplayCtrl = sub_sMinSlot !== null && sub_sMinSlot !== undefined ? sub_sMinSlot * df : null;
                        const sub_smDisplayCtrl = sub_smDisplay !== null && sub_smDisplay !== undefined ? sub_smDisplay * df : null;
                        const sub_smfDisplayCtrl = sub_smfDisplay !== null && sub_smfDisplay !== undefined ? sub_smfDisplay * df : null;
                        const sub_invTeoricoAyerCtrl = td.invTeoricoAyerPaq !== null && td.invTeoricoAyerPaq !== undefined ? td.invTeoricoAyerPaq * df : null;
                        const sub_stockD1Ctrl = sub_stockD1Paq !== null && sub_stockD1Paq !== undefined ? sub_stockD1Paq * df : null;
                        const sub_sugCtrl = sub_sugPaq !== null && sub_sugPaq !== undefined ? sub_sugPaq * df : null;
                        const sub_realCtrl = sub_realPaq !== null && sub_realPaq !== undefined ? sub_realPaq * df : null;

                        let sub_pronosticoInv = sub_stockD1Ctrl !== null && sub_stockD1Ctrl !== undefined ? sub_stockD1Ctrl.toFixed(1) : 'Sin datos';
                        let sub_despachoRealInfo = sub_realCtrl !== null && sub_realCtrl !== undefined ? sub_realCtrl.toFixed(1) : '-';

                        let sub_cdDisplay = td.cd_dinamico !== null && td.cd_dinamico !== undefined ? td.cd_dinamico : (td.cons_semanal !== null && td.cons_semanal !== undefined ? (td.cons_semanal / 7) : null);
                        let sub_csDisplay = sub_cdDisplay !== null ? sub_cdDisplay * 7 : null;

                        let subObj = {
                            "Tienda": td.nombre,
                            "Fecha de Despacho": fecha,
                            "Grupo": PA_LABELS[cat] || cat,
                            "Producto": p.nombre,
                            "Pronostico consumo semana (Unid. de control)": sub_csDisplay !== null ? parseFloat(sub_csDisplay).toFixed(1) : '',
                            "Pronostico consumo dia (Unid. de control)": sub_cdDisplay !== null ? parseFloat(sub_cdDisplay).toFixed(1) : '',
                            "Stock Minimo (Unid. de control)": sub_sMinDisplayCtrl !== null ? parseFloat(sub_sMinDisplayCtrl).toFixed(1) : '',
                            "Requerido Total (Unid. de control)": sub_smDisplayCtrl !== null ? parseFloat(sub_smDisplayCtrl).toFixed(1) : ''
                        };

                        if (window.PA_DATOS_COMPLETOS) {
                            subObj["Stock Máx Ajustado (Unid. de control)"] = sub_smfDisplayCtrl !== null ? parseFloat(sub_smfDisplayCtrl).toFixed(1) : '';
                            subObj["Inv. Teórico Ayer (Unid. de control)"] = sub_invTeoricoAyerCtrl !== null ? parseFloat(sub_invTeoricoAyerCtrl).toFixed(1) : '';
                        }
                        
                        subObj["Pronostico de inventario al dia de despacho (Unid. de control)"] = sub_pronosticoInv;
                        subObj["Despacho requerido (Unid. de control)"] = sub_sugCtrl !== null ? parseFloat(sub_sugCtrl).toFixed(1) : '-';
                        subObj["Presentacion de despacho"] = p.despacho_presentacion || p.unidad || '-';
                        subObj["Despacho requerido (Unid despacho)"] = sub_aUsar !== null ? parseFloat(sub_aUsar).toFixed(1) : '-';
                        subObj["Despacho Real (Unid. Despacho)"] = sub_realPaq !== null && sub_realPaq !== undefined ? parseFloat(sub_realPaq).toFixed(1) : '-';

                        datosExportar.push(subObj);
                    });
                }
            });
        });
    });

    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.json_to_sheet(datosExportar);

    // Auto-ajustar ancho de columnas
    let wscols = [
        { wch: 25 }, // Tienda
        { wch: 18 }, // Fecha de Despacho
        { wch: 25 }, // Grupo
        { wch: 40 }, // Producto
        { wch: 18 }, // Consumo Semanal
        { wch: 18 }, // Consumo Diario
        { wch: 15 }, // Stock Mín
        { wch: 15 }  // Requerido Total
    ];

    if (window.PA_DATOS_COMPLETOS) {
        wscols.push({ wch: 18 }); // Stock Máx Ajustado
        wscols.push({ wch: 18 }); // Inv. Teórico Ayer
    }

    wscols.push(
        { wch: 25 }, // Pronóstico Inventario
        { wch: 20 }, // Despacho requerido
        { wch: 25 }, // Presentación de despacho
        { wch: 18 }, // Despacho requerido
        { wch: 20 }  // Despacho Real (Unid. Despacho)
    );
    ws['!cols'] = wscols;

    XLSX.utils.book_append_sheet(wb, ws, "Pronóstico");

    let nombreArchivo = `Pronostico_Abastecimiento_${new Date().toISOString().slice(0, 10)}.xlsx`;
    XLSX.writeFile(wb, nombreArchivo);
}

function recalcularChaining(storeResults) {
    Object.values(storeResults).forEach(sr => {
        const byPP = {};
        sr.fechasOrdenadas.forEach(fecha => {
            Object.values(sr.agendaMap[fecha] || {}).forEach(slot => {
                (slot.items || []).forEach(p => {
                    if (!byPP[p.id_pp]) byPP[p.id_pp] = [];
                    byPP[p.id_pp].push({ slot, p, fecha });
                });
            });
        });

        Object.values(byPP).forEach(arr => {
            arr.sort((a, b) => a.slot.round - b.slot.round);
            arr.forEach(item => {
                const p = item.p;
                const round = item.slot.round;
                const fecha = item.fecha;
                const rd = p._porRonda[round];
                if (!rd) return;

                if (round === 1) {
                    const invBeforePaq = (rd.stockD1Paq ?? 0) + ((window.pa_dias_despacho_real[fecha] || false) ? rd.preHoyPaq : 0);
                    rd.despSugeridoPaq = Math.max(0, Math.ceil((rd.smfSlot ?? 0) - invBeforePaq));
                    const despachoAUsarPaq = ((window.pa_dias_despacho_real[fecha] || false) && rd.despachoRealRondaPaq !== null && rd.despachoRealRondaPaq !== undefined) ? rd.despachoRealRondaPaq : rd.despSugeridoPaq;
                    rd.stockPostDespachoPaq = invBeforePaq + despachoAUsarPaq;
                } else {
                    const prevRound = p._porRonda[round - 1];
                    const df = p.despacho_factor > 0 ? p.despacho_factor : 1;
                    const prevConsPaq = prevRound ? (prevRound.cd_dinamico * prevRound.ciclo) / df : 0;

                    rd.stockD1Paq = Math.max(0, (prevRound?.stockPostDespachoPaq ?? 0) - prevConsPaq);

                    const invBeforePaq = rd.stockD1Paq ?? 0; // Solo en ronda 1 se suma preHoyPaq
                    rd.despSugeridoPaq = Math.max(0, Math.ceil((rd.smfSlot ?? 0) - invBeforePaq));
                    const despachoAUsarPaq = ((window.pa_dias_despacho_real[fecha] || false) && rd.despachoRealRondaPaq !== null && rd.despachoRealRondaPaq !== undefined) ? rd.despachoRealRondaPaq : rd.despSugeridoPaq;
                    rd.stockPostDespachoPaq = invBeforePaq + despachoAUsarPaq;
                }
            });
        });
    });
}

function exportarPronosticoConsumoExcel() {
    if (!window.lastStoreResults || !currentAgendaData) {
        Swal.fire({ icon: 'warning', title: 'Sin datos', text: 'No hay datos para exportar.' });
        return;
    }

    const wb = XLSX.utils.book_new();

    function getUniqueProductsForStore(sr) {
        const unique = new Map();
        sr.fechasOrdenadas.forEach(f => {
            Object.values(sr.agendaMap[f] || {}).forEach(slot => {
                (slot.items || []).forEach(p => {
                    if (!unique.has(p.id_pp)) {
                        unique.set(p.id_pp, p);
                    }
                });
            });
        });
        return Array.from(unique.values()).sort((a, b) => (a.nombre || '').localeCompare(b.nombre || ''));
    }

    function calcularProyecciones(p) {
        const wls_m = p.wls_m ?? 0;
        const wls_b = p.wls_b ?? 0;
        const wls_n = p.wls_n ?? 0;

        return {
            cd1: Math.max(0, (wls_m * (wls_n + 1)) + wls_b) / 7,
            cd2: Math.max(0, (wls_m * (wls_n + 2)) + wls_b) / 7,
            cd3: Math.max(0, (wls_m * (wls_n + 3)) + wls_b) / 7,
            cd4: Math.max(0, (wls_m * (wls_n + 4)) + wls_b) / 7
        };
    }

    if (currentAgendaData.isConsolidado) {
        let consolidados = new Map();

        Object.values(window.lastStoreResults).forEach(sr => {
            const prods = getUniqueProductsForStore(sr);
            prods.forEach(p => {
                const proy = calcularProyecciones(p);
                if (!consolidados.has(p.id_pp)) {
                    consolidados.set(p.id_pp, {
                        nombre: p.nombre,
                        grupo: PA_LABELS[p.categoria_insumo] || p.categoria_insumo,
                        unidad: p.unidad || '-',
                        cd1: 0, cd2: 0, cd3: 0, cd4: 0
                    });
                }
                const c = consolidados.get(p.id_pp);
                c.cd1 += proy.cd1;
                c.cd2 += proy.cd2;
                c.cd3 += proy.cd3;
                c.cd4 += proy.cd4;
            });
        });

        const arrConsolidado = Array.from(consolidados.values()).sort((a, b) => (a.nombre || '').localeCompare(b.nombre || ''));
        const datosConsolidado = arrConsolidado.map(c => ({
            "Grupo": c.grupo,
            "Producto": c.nombre,
            "Unidad Base": c.unidad,
            "Semana 1 (Actual) [Uds]": parseFloat(c.cd1.toFixed(4)),
            "Semana 2 [Uds]": parseFloat(c.cd2.toFixed(4)),
            "Semana 3 [Uds]": parseFloat(c.cd3.toFixed(4)),
            "Semana 4 [Uds]": parseFloat(c.cd4.toFixed(4))
        }));

        const wsCons = XLSX.utils.json_to_sheet(datosConsolidado);
        wsCons['!cols'] = [{ wch: 25 }, { wch: 40 }, { wch: 20 }, { wch: 18 }, { wch: 18 }, { wch: 18 }, { wch: 18 }];
        XLSX.utils.book_append_sheet(wb, wsCons, "Consolidado");

        Object.values(window.lastStoreResults).forEach(sr => {
            const prods = getUniqueProductsForStore(sr);
            const datosTienda = prods.map(p => {
                const proy = calcularProyecciones(p);
                return {
                    "Grupo": PA_LABELS[p.categoria_insumo] || p.categoria_insumo,
                    "Producto": p.nombre,
                    "Unidad Base": p.unidad || '-',
                    "Semana 1 (Actual) [Uds]": parseFloat(proy.cd1.toFixed(4)),
                    "Semana 2 [Uds]": parseFloat(proy.cd2.toFixed(4)),
                    "Semana 3 [Uds]": parseFloat(proy.cd3.toFixed(4)),
                    "Semana 4 [Uds]": parseFloat(proy.cd4.toFixed(4))
                };
            });
            const wsTienda = XLSX.utils.json_to_sheet(datosTienda);
            wsTienda['!cols'] = [{ wch: 25 }, { wch: 40 }, { wch: 20 }, { wch: 18 }, { wch: 18 }, { wch: 18 }, { wch: 18 }];
            const safeName = sr.nombre.substring(0, 31).replace(/[\\/?*\[\]]/g, '');
            XLSX.utils.book_append_sheet(wb, wsTienda, safeName);
        });

    } else {
        const sr = Object.values(window.lastStoreResults)[0];
        const prods = getUniqueProductsForStore(sr);
        const datosTienda = prods.map(p => {
            const proy = calcularProyecciones(p);
            return {
                "Grupo": PA_LABELS[p.categoria_insumo] || p.categoria_insumo,
                "Producto": p.nombre,
                "Unidad Base": p.unidad || '-',
                "Semana 1 (Actual) [Uds]": parseFloat(proy.cd1.toFixed(4)),
                "Semana 2 [Uds]": parseFloat(proy.cd2.toFixed(4)),
                "Semana 3 [Uds]": parseFloat(proy.cd3.toFixed(4)),
                "Semana 4 [Uds]": parseFloat(proy.cd4.toFixed(4))
            };
        });
        const wsTienda = XLSX.utils.json_to_sheet(datosTienda);
        wsTienda['!cols'] = [{ wch: 25 }, { wch: 40 }, { wch: 20 }, { wch: 18 }, { wch: 18 }, { wch: 18 }, { wch: 18 }];
        XLSX.utils.book_append_sheet(wb, wsTienda, "Pronóstico Consumo");
    }

    let nombreArchivo = `Pronostico_Consumo_${new Date().toISOString().slice(0, 10)}.xlsx`;
    XLSX.writeFile(wb, nombreArchivo);
}
