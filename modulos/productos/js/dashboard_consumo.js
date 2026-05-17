/* ============================================================
   DASHBOARD CONSUMO DE INSUMOS — JavaScript
   modulos/productos/js/dashboard_consumo.js
   ============================================================ */

'use strict';

/* ── Referencias DOM ─────────────────────────────────────── */
const $semDesde = $('#filtroSemanaDesde');
const $semHasta = $('#filtroSemanaHasta');
const $sucursales = $('#filtroSucursales');
const $btnAplicar = $('#btnAplicar');
const $btnExportar = $('#btnExportar');


const $panelInicial = $('#panelInicial');
const $panelLoader = $('#panelLoader');
const $panelDatos = $('#panelDatos');

/* ── Estado global ───────────────────────────────────────── */
let datosActuales = null;   // Respuesta completa del AJAX de datos
let chartTendencia = null;   // Instancia Chart.js activa
let modoGrafico = 'barras'; // 'barras' | 'linea_total' | 'linea_suc'

/* ════════════════════════════════════════════════════════════
   INICIALIZACIÓN
   ════════════════════════════════════════════════════════════ */
$(document).ready(function () {
    inicializarSelect2();
    cargarFiltros();     // carga semana actual + sucursales + insumos
    bindEventos();
});

/* ── SucursalPicker — Custom Pill Dropdown ───────────────── */
const SucPicker = (() => {
    const MAX_PILLS = 3;   // max pills visibles en trigger (el resto → badge "+N")
    let _opciones = []; // { value, label }
    let _seleccionados = new Set();

    const $trigger = $('#dcSucTrigger');
    const $dropdown = $('#dcSucDropdown');
    const $list = $('#dcSucList');
    const $search = $('#dcSucSearch');
    const $pills = $('#dcSucPills');
    const $ph = $('#dcSucPlaceholder');
    const $badge = $('#dcSucCountBadge');
    const $clear = $('#dcSucClear');
    const $hiddenSel = $('#filtroSucursales');  // select oculto para compatibilidad

    /* ── Abrir / Cerrar ── */
    function open() {
        $trigger.addClass('open').attr('aria-expanded', 'true');
        $dropdown.addClass('open');
        $search.val('').trigger('input').focus();
    }
    function close() {
        $trigger.removeClass('open').attr('aria-expanded', 'false');
        $dropdown.removeClass('open');
    }
    function toggle() { $trigger.hasClass('open') ? close() : open(); }

    /* ── Sincronizar select oculto → lo usa cargarDatos() ── */
    function syncHidden() {
        $hiddenSel.find('option').prop('selected', false);
        _seleccionados.forEach(v => {
            $hiddenSel.find(`option[value="${String(v)}"]`).prop('selected', true);
        });
    }

    /* ── Render trigger pills ── */
    function renderTrigger() {
        const n = _seleccionados.size;
        if (n === 0) {
            $ph.show();
            $pills.hide().empty();
            $badge.hide();
            $clear.hide();
            return;
        }
        $ph.hide();
        $pills.show();
        $clear.show();

        const arr = [..._seleccionados];
        const visible = arr.slice(0, MAX_PILLS);
        const extra = arr.length - MAX_PILLS;

        $pills.empty();
        visible.forEach(v => {
            const lbl = _opciones.find(o => String(o.value) === String(v))?.label || v;
            const shortLbl = lbl.length > 14 ? lbl.substring(0, 12) + '…' : lbl;
            const $pill = $(`
                <span class="dc-suc-pill" title="${escHtml(lbl)}">
                    ${escHtml(shortLbl)}
                    <button class="dc-suc-pill-remove" data-v="${v}" type="button" title="Quitar">
                        <i class="fas fa-times"></i>
                    </button>
                </span>`);
            $pills.append($pill);
        });

        if (extra > 0) {
            $badge.text(`+${extra} más`).show();
        } else {
            $badge.hide();
        }
    }

    /* ── Render lista dropdown ── */
    function renderList(query) {
        $list.empty();
        const q = (query || '').toLowerCase();
        const filtradas = _opciones.filter(o => o.label.toLowerCase().includes(q));

        if (filtradas.length === 0) {
            $list.append('<div class="dc-suc-empty"><i class="fas fa-search me-1"></i>Sin resultados</div>');
            return;
        }
        filtradas.forEach(o => {
            const sel = _seleccionados.has(String(o.value));  // normalizar comparación
            const $item = $(`
                <div class="dc-suc-item ${sel ? 'selected' : ''}" data-v="${o.value}" role="option" aria-selected="${sel}">
                    <span class="dc-suc-checkbox">
                        <i class="fas fa-check dc-suc-checkbox-icon"></i>
                    </span>
                    <span class="dc-suc-item-label" title="${escHtml(o.label)}">${escHtml(o.label)}</span>
                </div>`);
            $list.append($item);
        });
    }

    /* ── Toggle selección ── */
    function toggleItem(value) {
        const v = String(value);   // normalizar siempre a string
        if (_seleccionados.has(v)) {
            _seleccionados.delete(v);
        } else {
            _seleccionados.add(v);
        }
        syncHidden();
        renderTrigger();
        renderList($search.val());
    }

    /* ── API pública ── */
    function init() {
        // Trigger click
        $trigger.on('click', function (e) {
            // no cerrar si click en pill-remove
            if ($(e.target).closest('.dc-suc-pill-remove').length) return;
            toggle();
        });

        // Pill remove (dentro del trigger)
        $pills.on('click', '.dc-suc-pill-remove', function (e) {
            e.stopPropagation();
            const v = String($(this).data('v'));   // normalizar a string
            _seleccionados.delete(v);
            syncHidden();
            renderTrigger();
            renderList($search.val());
        });

        // Botón limpiar todos
        $clear.on('click', function (e) {
            e.stopPropagation();
            _seleccionados.clear();
            syncHidden();
            renderTrigger();
            renderList($search.val());
        });

        // Botón "Todas"
        $('#dcSucSelAll').on('click', function () {
            _seleccionados = new Set(_opciones.map(o => String(o.value)));  // normalizar a string
            syncHidden();
            renderTrigger();
            renderList($search.val());
        });

        // Botón "Ninguna"
        $('#dcSucNone').on('click', function () {
            _seleccionados.clear();
            syncHidden();
            renderTrigger();
            renderList($search.val());
        });

        // Click en item de lista
        $list.on('click', '.dc-suc-item', function () {
            toggleItem(String($(this).data('v')));   // normalizar a string
        });

        // Búsqueda
        $search.on('input', function () { renderList($(this).val()); });

        // Cerrar al click fuera
        $(document).on('click.sucpicker', function (e) {
            if (!$trigger.is(e.target) && $trigger.has(e.target).length === 0
                && !$dropdown.is(e.target) && $dropdown.has(e.target).length === 0) {
                close();
            }
        });

        // Teclado
        $trigger.on('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
            if (e.key === 'Escape') close();
        });
    }

    function setOpciones(arr) {
        // arr = [{ value, label }]
        _opciones = arr;
        _seleccionados.clear();

        // Rebuild hidden select options
        $hiddenSel.empty();
        arr.forEach(o => {
            $hiddenSel.append(`<option value="${o.value}">${escHtml(o.label)}</option>`);
        });

        renderTrigger();
        renderList();
    }

    function getSelected() {
        return [..._seleccionados];
    }

    return { init, setOpciones, getSelected };
})();

function inicializarSelect2() {
    // Reemplazado por SucPicker — llamar init al arrancar
    SucPicker.init();
    InsumoPicker.init();
}


/* ── Ligar eventos ────────────────────────────────────────── */
function bindEventos() {
    $btnAplicar.on('click', cargarDatos);

    // Modo gráfico — 3 botones directos
    $('#chartModoBarras, #chartModoLineaTotal, #chartModoLineaSuc').on('click', function () {
        modoGrafico = $(this).data('modo');
        $('#chartModoBarras, #chartModoLineaTotal, #chartModoLineaSuc').removeClass('active');
        $(this).addClass('active');
        $('#chartLegendReset').hide();  // reset el estado de series ocultas al cambiar modo
        if (datosActuales && !$('#chartWrap').hasClass('d-none')) {
            renderGrafico(datosActuales);
        }
    });

    // Botón reset de leyenda — mostrar todas las series
    $('#chartLegendReset').on('click', function () {
        if (!chartTendencia) return;
        chartTendencia.data.datasets.forEach((_, i) => chartTendencia.show(i));
        chartTendencia.update();
        $(this).hide();
    });

    // Cambiar insumo en el panel de análisis → re-renderizar gráfico + KPIs + Kardex
    $('#chartInsumoSel').on('change', function () {
        if (!datosActuales) return;
        const idSel = parseInt($(this).val()) || 0;
        if (idSel > 0) {
            const item = datosActuales.consumo.find(c => c.id == idSel);
            $('#panelAnalisisInsumo').removeClass('d-none');  // mostrar panel
            $('#chartPlaceholder').addClass('d-none');
            $('#chartWrap').removeClass('d-none');
            // Actualizar hint en el header del panel
            $('#insumoNombreHint').text(item ? `— ${item.nombre}` : '');
            renderKPIs(datosActuales, item);
            renderGrafico(datosActuales);
            // Limpiar corte al cambiar de insumo para que use el semDesde como default
            $('#kardexSemanaCorte').val('');
            cargarKardex(idSel, item);
        } else {
            $('#panelAnalisisInsumo').addClass('d-none');  // ocultar panel
            $('#chartPlaceholder').removeClass('d-none');
            $('#chartWrap').addClass('d-none');
            $('#panelKardex').addClass('d-none');
            $('#insumoNombreHint').text('— selecciona un insumo arriba para ver KPIs y tendencia');
            if (chartTendencia) { chartTendencia.destroy(); chartTendencia = null; }
            $('#tituloTendencia').html('<i class="fas fa-chart-line me-2"></i>Análisis de Insumo');
            renderKPIs(datosActuales, null);
        }
    });

    // Botón Actualizar Kardex → recarga con la semana de corte actual
    $('#btnRefreshKardex').on('click', function () {
        const idSel = parseInt($('#chartInsumoSel').val()) || 0;
        if (!datosActuales || !idSel) {
            Swal.fire({ icon: 'info', title: 'Sin insumo seleccionado', text: 'Primero selecciona un insumo para cargar el Kardex.', confirmButtonColor: '#0E544C' });
            return;
        }
        const item = datosActuales.consumo.find(c => c.id == idSel);
        cargarKardex(idSel, item);
    });


    // Buscar en tabla historial
    $('#buscarHistorial').on('keyup', function () {
        const q = $(this).val().toLowerCase();
        $('#tbodyHistorial tr').each(function () {
            const txt = $(this).text().toLowerCase();
            $(this).toggle(txt.includes(q));
        });
    });

    // Exportar
    $btnExportar.on('click', exportarCSV);



    // Panel alertas sobreconsumo: toggle (evitar sigma btns)
    $(document).on('click', '#alertasHeader', function (e) {
        if ($(e.target).closest('.dc-sigma-btns, .dc-sigma-btn').length) return;
        $('#alertasBody').toggleClass('collapsed');
        $('#alertasToggle').toggleClass('rotated');
    });

    // Botones sigma sobreconsumo: recalcular en tiempo real
    $(document).on('click', '#alertasHeader .dc-sigma-btn', function (e) {
        e.stopPropagation();
        kSigmaActual = parseFloat($(this).data('k'));
        $(this).closest('.dc-sigma-btns').find('.dc-sigma-btn').removeClass('active');
        $(this).addClass('active');
        if (datosActuales) renderPanelAlertas(datosActuales, kSigmaActual);
    });

    // Panel crecimiento: toggle (evitar botones de filtro)
    $(document).on('click', '#crecimientoHeader', function (e) {
        if ($(e.target).closest('.dc-sigma-btns, .dc-sigma-btn').length) return;
        $('#crecimientoBody').toggleClass('collapsed');
        $('#crecimientoToggle').toggleClass('rotated');
    });

    // Botones umbral crecimiento: redetectar con nuevo slope
    $(document).on('click', '#crecUmbralBtns .dc-sigma-btn', function (e) {
        e.stopPropagation();
        kSlopeActual = parseFloat($(this).data('slope'));
        $(this).closest('.dc-sigma-btns').find('.dc-sigma-btn').removeClass('active');
        $(this).addClass('active');
        if (datosActuales) renderPanelCrecimiento(datosActuales);
    });

    // Botones filtro severidad crecimiento: filtrar lista ya calculada
    $(document).on('click', '#crecSevBtns .dc-sigma-btn', function (e) {
        e.stopPropagation();
        filtroSevCrec = $(this).data('sev');
        $(this).closest('.dc-sigma-btns').find('.dc-sigma-btn').removeClass('active');
        $(this).addClass('active');
        if (datosActuales) renderPanelCrecimiento(datosActuales);
    });
}

/* ════════════════════════════════════════════════════════════
   CARGAR FILTROS — detecta semana actual + puebla sucursales e insumos
   ════════════════════════════════════════════════════════════ */
async function cargarFiltros() {
    try {
        const resp = await $.ajax({
            url: 'ajax/dashboard_consumo_get_filtros.php',
            type: 'GET',
        });

        if (!resp.ok) {
            console.error('Error filtros:', resp.msg);
            return;
        }

        // ── Semana actual ───────────────────────────────────────
        if (resp.semana_actual) {
            const sa = resp.semana_actual;
            $('#semanaActualNum').text(sa.numero_semana);
            $('#semanaActualRango').text('');
            $('#badgeSemanaActual').show();

            // Restricción: no permitir semanas futuras
            $semDesde.attr('max', sa.numero_semana);
            $semHasta.attr('max', sa.numero_semana);

            // Pre-cargar rango por defecto: CREC_IDEAL_SEMANAS + 1 (la actual se excluye en cálculo)
            // 6 semanas completas → seleccionar 7 semanas en el filtro
            const semHasta = sa.numero_semana;
            const semDesde = Math.max(1, semHasta - (CREC_IDEAL_SEMANAS)); // semHasta - 6 = 7 semanas
            $semDesde.val(semDesde);
            $semHasta.val(semHasta);
        }

        // ── Sucursales ─────────────────────────────────────────
        SucPicker.setOpciones(
            resp.sucursales.map(s => ({ value: String(s.codigo), label: s.nombre }))
        );

    } catch (err) {
        console.error('Error cargando filtros:', err);
    }
}

/* ════════════════════════════════════════════════════════════
   CARGAR DATOS (ANALIZAR)
   ════════════════════════════════════════════════════════════ */
async function cargarDatos() {
    const semDesdeNum = parseInt($semDesde.val());
    const semHastaNum = parseInt($semHasta.val());

    if (!semDesdeNum || !semHastaNum || isNaN(semDesdeNum) || isNaN(semHastaNum)) {
        Swal.fire({ icon: 'warning', title: 'Semanas requeridas', text: 'Ingresa los números de semana de inicio y fin.', confirmButtonColor: '#0E544C' });
        return;
    }

    const semD = Math.min(semDesdeNum, semHastaNum);
    const semH = Math.max(semDesdeNum, semHastaNum);

    // Restricción: no analizar semanas futuras
    const semActual = parseInt($('#semanaActualNum').text()) || 0;
    if (semActual > 0 && semH > semActual) {
        Swal.fire({
            icon: 'warning',
            title: 'Semana futura',
            text: `La semana máxima disponible es la ${semActual} (semana en curso). No se pueden analizar semanas futuras.`,
            confirmButtonColor: '#0E544C'
        });
        $semHasta.val(semActual);
        return;
    }

    // Obtener sucursales seleccionadas del SucPicker custom
    const sucursalesSelec = SucPicker.getSelected();

    // Mostrar loader
    mostrarEstado('loader');
    $btnAplicar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Calculando…');
    $btnExportar.prop('disabled', true);

    try {
        const formData = new FormData();
        formData.append('semana_desde_num', semD);
        formData.append('semana_hasta_num', semH);
        sucursalesSelec.forEach(s => formData.append('sucursales[]', s));

        const resp = await $.ajax({
            url: 'ajax/dashboard_consumo_get_datos.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
        });

        if (!resp.ok) {
            Swal.fire({ icon: 'error', title: 'Error', text: resp.msg || 'Error al calcular consumo.', confirmButtonColor: '#0E544C' });
            mostrarEstado('inicial');
            return;
        }

        if (resp.consumo.length === 0) {
            mostrarEstado('inicial');
            Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No hay ventas válidas en el período seleccionado.', confirmButtonColor: '#0E544C' });
            return;
        }

        datosActuales = resp;
        // Guardar info de semanas para exportar
        datosActuales._semDesde = semD;
        datosActuales._semHasta = semH;

        renderDashboard(resp);
        mostrarEstado('datos');

        if (PUEDE_EXPORTAR) $btnExportar.prop('disabled', false);

    } catch (err) {
        console.error('Error cargando datos:', err);
        Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar al servidor.', confirmButtonColor: '#0E544C' });
        mostrarEstado('inicial');
    } finally {
        $btnAplicar.prop('disabled', false).html('<i class="fas fa-search me-1"></i>Analizar');
    }
}

function obtenerNumSemana(semId) {
    return $semDesde.find(`option[value="${semId}"]`).data('num') || semId;
}

/* ── Controlar visibilidad de paneles ─────────────────────── */
function mostrarEstado(estado) {
    $panelInicial.addClass('d-none');
    $panelLoader.addClass('d-none');
    $panelDatos.addClass('d-none');

    if (estado === 'inicial') $panelInicial.removeClass('d-none');
    if (estado === 'loader') $panelLoader.removeClass('d-none');
    if (estado === 'datos') $panelDatos.removeClass('d-none');
}

/* ════════════════════════════════════════════════════════════
   RENDER COMPLETO DEL DASHBOARD
   ════════════════════════════════════════════════════════════ */
function renderDashboard(data) {
    renderPanelCrecimiento(data);            // alertas de crecimiento sostenido (primero)
    renderPanelAlertas(data, kSigmaActual);  // alertas de sobreconsumo (segundo)
    renderKPIs(data, null);      // placeholder hasta que se elija insumo
    renderInsumoSel(data);       // poblar selector del panel de análisis
    renderTablaHistorial(data);
    renderTablaProyeccion(data);
}

/* ── KPIs — muestran datos del insumo seleccionado ───────── */
function renderKPIs(data, item) {
    if (item) {
        // Insumo específico seleccionado
        $('#kpiTotalVal').text(formatNum(item.total));
        $('#kpiTotalSub').html(
            `<span style="color:#51B8AC;font-weight:600">${escHtml(item.nombre)}</span>` +
            ` · ${escHtml(item.unidad)}`
        );

        if (item.semana_pico_num) {
            const semPico = data.semanas.find(s => s.numero_semana == item.semana_pico_num);
            $('#kpiPicoVal').text(`${item.semana_pico_num}`);
            $('#kpiPicoSub').text(semPico
                ? `${formatFecha(semPico.fecha_inicio)}–${formatFecha(semPico.fecha_fin)}`
                : '');
        } else {
            $('#kpiPicoVal').text('—');
            $('#kpiPicoSub').text('');
        }

        // Proyección 3 semanas con regresión lineal (misma lógica que el gráfico)
        const semanasNrosKpi = data.semanas.map(s => s.numero_semana);
        const semanaActualKpi = parseInt($('#semanaActualNum').text()) || 0;
        const esSemActKpi = semanaActualKpi > 0 && semanasNrosKpi.includes(semanaActualKpi);
        const semanasCalcKpi = esSemActKpi
            ? semanasNrosKpi.filter(n => n !== semanaActualKpi)
            : semanasNrosKpi;
        const ultimaSemKpi = semanasNrosKpi[semanasNrosKpi.length - 1];

        // Promedio sobre semanas completas
        let promCalcKpi = item.prom_semana || 0;
        if (semanasCalcKpi.length > 0) {
            const valsC = semanasCalcKpi.map(n => item.por_semana[n] || 0);
            const valsP = valsC.filter(v => v > 0);
            if (valsP.length > 0) promCalcKpi = valsP.reduce((a, b) => a + b, 0) / valsP.length;
        }

        let kpiProy3 = 0;
        let kpiProm3 = promCalcKpi;
        if (semanasCalcKpi.length >= 2) {
            const xV = semanasCalcKpi;
            const yV = semanasCalcKpi.map(n => item.por_semana[n] || 0);
            const nk = xV.length;
            const sumX = xV.reduce((a, b) => a + b, 0);
            const sumY = yV.reduce((a, b) => a + b, 0);
            const sumXY = xV.reduce((acc, x, i) => acc + x * yV[i], 0);
            const sumX2 = xV.reduce((acc, x) => acc + x * x, 0);
            const denom = nk * sumX2 - sumX * sumX;
            if (Math.abs(denom) > 0.001) {
                const sl = (nk * sumXY - sumX * sumY) / denom;
                const ic = (sumY - sl * sumX) / nk;
                const w1 = Math.max(0, sl * (ultimaSemKpi + 1) + ic);
                const w2 = Math.max(0, sl * (ultimaSemKpi + 2) + ic);
                const w3 = Math.max(0, sl * (ultimaSemKpi + 3) + ic);
                kpiProy3 = w1 + w2 + w3;
                kpiProm3 = kpiProy3 / 3;
            } else {
                kpiProy3 = promCalcKpi * 3;
                kpiProm3 = promCalcKpi;
            }
        } else {
            kpiProy3 = promCalcKpi * 3;
            kpiProm3 = promCalcKpi;
        }

        $('#kpiProyVal').text(Math.round(kpiProy3).toLocaleString('es-NI'));
        $('#kpiProySub').text(`Prom. ${Math.round(kpiProm3).toLocaleString('es-NI')} ${escHtml(item.unidad)}/sem`);

    } else {
        // Sin selección: placeholder
        const n = data ? data.num_insumos : 0;
        $('#kpiTotalVal').text('—');
        $('#kpiTotalSub').text(n > 0 ? `${n} insumos cargados · selecciona uno` : '');
        $('#kpiPicoVal').text('—');
        $('#kpiPicoSub').text('');
        $('#kpiProyVal').text('—');
        $('#kpiProySub').text('Selecciona un insumo del panel');
    }
}


/* ── Paleta de colores para sucursales (hasta 14) ─────────── */
const SUCURSAL_COLORS = [
    { border: '#0E544C', bg: 'rgba(14,84,76,.25)' },
    { border: '#2980b9', bg: 'rgba(41,128,185,.25)' },
    { border: '#8e44ad', bg: 'rgba(142,68,173,.25)' },
    { border: '#e67e22', bg: 'rgba(230,126,34,.25)' },
    { border: '#c0392b', bg: 'rgba(192,57,43,.25)' },
    { border: '#16a085', bg: 'rgba(22,160,133,.25)' },
    { border: '#d35400', bg: 'rgba(211,84,0,.25)' },
    { border: '#27ae60', bg: 'rgba(39,174,96,.25)' },
    { border: '#2c3e50', bg: 'rgba(44,62,80,.25)' },
    { border: '#f39c12', bg: 'rgba(243,156,18,.25)' },
    { border: '#1abc9c', bg: 'rgba(26,188,156,.25)' },
    { border: '#9b59b6', bg: 'rgba(155,89,182,.25)' },
    { border: '#e74c3c', bg: 'rgba(231,76,60,.25)' },
    { border: '#3498db', bg: 'rgba(52,152,219,.25)' },
];

let kSigmaActual = 1.5;   // Factor σ activo para alertas de sobreconsumo
let kSlopeActual = 0.06;  // Umbral β/μ activo para alertas de crecimiento
let filtroSevCrec = 'critico'; // Filtro de severidad activo: 'critico' | 'notable' | 'todos'

/* ── Gráfico de Tendencia ─────────────────────────────────── */
function renderGrafico(data) {
    const idInsumoSel = parseInt($('#chartInsumoSel').val()) || 0;
    if (!idInsumoSel) {
        $('#chartPlaceholder').removeClass('d-none');
        $('#chartWrap').addClass('d-none');
        return;
    }

    const item = data.consumo.find(c => c.id == idInsumoSel);
    if (!item) {
        $('#chartPlaceholder').removeClass('d-none');
        $('#chartWrap').addClass('d-none');
        return;
    }

    $('#chartPlaceholder').addClass('d-none');
    $('#chartWrap').removeClass('d-none');

    // ── Actualizar título
    $('#tituloTendencia').html(
        `<i class="fas fa-chart-line me-2"></i>Tendencia: <strong>${escHtml(item.nombre)}</strong>` +
        `<span class="ms-2 text-muted" style="font-size:.72rem;font-weight:400">${escHtml(item.unidad)}</span>`
    );

    const labels = data.semanas.map(s => `Sem ${s.numero_semana}`);
    const semanasNros = data.semanas.map(s => s.numero_semana);
    const sucursales = data.sucursales || [];
    const nombres = data.sucursales_nombres || {};
    const prom = item.prom_semana || 0;

    // ── Excluir semana en curso del promedio y proyección ────────────────────
    const semanaActual = parseInt($('#semanaActualNum').text()) || 0;
    const esSemActualEnRango = semanaActual > 0 && semanasNros.includes(semanaActual);
    // Semanas completas: excluir la semana en curso si está dentro del rango
    const semanasCalc = esSemActualEnRango
        ? semanasNros.filter(n => n !== semanaActual)
        : semanasNros;

    // Promedio recalculado sin semana en curso
    let promCalc = prom;
    if (semanasCalc.length > 0) {
        const valsCalc = semanasCalc.map(n => item.por_semana[n] || 0);
        const valsPos = valsCalc.filter(v => v > 0);
        promCalc = valsPos.length > 0 ? valsPos.reduce((a, b) => a + b, 0) / valsPos.length : prom;
    }

    // Proyección con regresión lineal (mínimos cuadrados) sobre semanas completas
    const ultimaSem = semanasNros[semanasNros.length - 1];
    let proyW1 = round2(promCalc), proyW2 = round2(promCalc), proyW3 = round2(promCalc);
    let regSlope = 0, regIntercept = promCalc;  // fallback: línea plana en promCalc
    if (semanasCalc.length >= 2) {
        const xV = semanasCalc;
        const yV = semanasCalc.map(n => item.por_semana[n] || 0);
        const n_ = xV.length;
        const sumX = xV.reduce((a, b) => a + b, 0);
        const sumY = yV.reduce((a, b) => a + b, 0);
        const sumXY = xV.reduce((acc, x, i) => acc + x * yV[i], 0);
        const sumX2 = xV.reduce((acc, x) => acc + x * x, 0);
        const denom = n_ * sumX2 - sumX * sumX;
        if (Math.abs(denom) > 0.001) {
            regSlope = (n_ * sumXY - sumX * sumY) / denom;
            regIntercept = (sumY - regSlope * sumX) / n_;
            proyW1 = Math.max(0, round2(regSlope * (ultimaSem + 1) + regIntercept));
            proyW2 = Math.max(0, round2(regSlope * (ultimaSem + 2) + regIntercept));
            proyW3 = Math.max(0, round2(regSlope * (ultimaSem + 3) + regIntercept));
        }
    }
    // Estimado de cierre para la semana en curso (basado en regresión)
    const proyActual = esSemActualEnRango
        ? Math.max(0, round2(regSlope * semanaActual + regIntercept))
        : null;

    // ── Detectar si hay múltiples sucursales con datos en desglose
    const hayDesglose = sucursales.length > 1 && item.desglose_semxsuc &&
        Object.keys(item.desglose_semxsuc).length > 0;

    // ── Construir datasets
    let datasets = [];

    // Modos derivados del selector unificado
    const esBarraSuc = hayDesglose && modoGrafico === 'barras';
    const esLineaSuc = hayDesglose && modoGrafico === 'linea_suc';
    const esLineaTotal = modoGrafico === 'linea_total';

    // Ocultar botón "Línea x Suc." si no hay desglose
    $('#chartModoLineaSuc').toggle(hayDesglose);

    // ── Extender labels para proyección (se necesitan antes de construir los datasets de Stock Mín)
    const labelsExtended = esLineaSuc ? labels : [
        ...labels,
        `Proy. ${ultimaSem + 1}`,
        `Proy. ${ultimaSem + 2}`,
        `Proy. ${ultimaSem + 3}`,
    ];

    if (esLineaSuc || esBarraSuc) {
        // ━━ Modo Multi-Sucursal ━━

        // Sucursales ordenadas por consumo total desc
        const sucConTotal = sucursales.map((suc, i) => {
            const totalSuc = semanasNros.reduce((acc, n) => acc + (item.desglose_semxsuc[n]?.[suc] || 0), 0);
            return { suc, nombre: nombres[suc] || suc, totalSuc, idx: i };
        }).sort((a, b) => b.totalSuc - a.totalSuc);

        sucConTotal.forEach(({ suc, nombre, idx }) => {
            const color = SUCURSAL_COLORS[idx % SUCURSAL_COLORS.length];
            const valores = semanasNros.map(n => round2(item.desglose_semxsuc[n]?.[suc] || 0));
            const label = nombre.length > 22 ? nombre.substring(0, 20) + '…' : nombre;

            // En modo BARRA: color sólido (~75% opacidad) para cada segmento apilado
            const bgColor = esBarraSuc
                ? color.border.replace('#', 'rgba(').replace(/([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})/i,
                    (_, r, g, b) => `${parseInt(r, 16)},${parseInt(g, 16)},${parseInt(b, 16)},0.75)`)
                : color.bg;

            datasets.push({
                label,
                data: valores,
                backgroundColor: bgColor,
                borderColor: color.border,
                borderWidth: esBarraSuc ? 0.5 : 2,
                tension: 0.3,
                fill: false,
                pointRadius: esLineaSuc ? 3 : undefined,
                pointBackgroundColor: color.border,
                // stack solo en modo barra — Chart.js apila datasets con el mismo stack id
                stack: esBarraSuc ? 'suc' : undefined,
            });
        });

        // En modo LÍNEA por sucursal: NO se agrega línea TOTAL, Promedio ni Proyección
        // Solo se muestran las líneas individuales de cada sucursal
        if (!esLineaSuc) {
            // Promedio punteado — solo en modo barra por sucursal
            datasets.push({
                label: `Prom./sem (sem. completas): ${formatNum(round2(promCalc))} ${escHtml(item.unidad)}`,
                data: semanasNros.map(n => (esSemActualEnRango && n === semanaActual) ? null : round2(promCalc)),
                borderColor: '#e67e22',
                borderWidth: 1.5,
                borderDash: [5, 4],
                pointRadius: 0,
                fill: false,
                tension: 0,
                type: 'line',
                order: 1,
            });
        }

        // ── LÍNEA DE STOCK MÍNIMO (Línea x Tienda cuando hay varias tiendas)
        if (esLineaSuc && item.stock_min_suc) {
            sucConTotal.forEach(({ suc, nombre, idx }) => {
                const color = SUCURSAL_COLORS[idx % SUCURSAL_COLORS.length];
                const valMin = item.stock_min_suc[suc] || 0;
                if (valMin > 0) {
                    datasets.push({
                        label: `Stock Mín (${nombre}): ${formatNum(valMin)}`,
                        data: labelsExtended.map(() => valMin),
                        borderColor: color.border,
                        borderWidth: 1.5,
                        borderDash: [3, 6], // puntos/guiones muy finos
                        pointRadius: 0,
                        fill: false,
                        tension: 0,
                        type: 'line',
                        order: 5, // por debajo de las líneas principales
                    });
                }
            });
        }

    } else {
        // ━━ Modo Total / Línea Total (1 línea con promedio y proyección) ━━
        const valores = semanasNros.map(n => round2(item.por_semana[n] || 0));
        datasets = [
            {
                label: item.nombre.length > 35 ? item.nombre.substring(0, 33) + '…' : item.nombre,
                data: valores,
                backgroundColor: 'rgba(81,184,172,.35)',
                borderColor: '#0E544C',
                borderWidth: 2,
                tension: 0.3,
                fill: esLineaTotal,
                pointRadius: 4,
                pointBackgroundColor: '#0E544C',
            },
            {
                label: `Prom./sem (sem. completas): ${formatNum(round2(promCalc))} ${escHtml(item.unidad)}`,
                data: semanasNros.map(n => (esSemActualEnRango && n === semanaActual) ? null : round2(promCalc)),
                borderColor: '#e67e22',
                borderWidth: 1.5,
                borderDash: [5, 4],
                pointRadius: 0,
                fill: false,
                tension: 0,
                type: 'line',
            },
        ];

        // ── LÍNEA DE STOCK MÍNIMO (modo Línea Total y Barras — siempre que haya valor)
        // Se muestra en cualquier cantidad de sucursales cuando el modo es linea_total o barras
        if ((esLineaTotal || modoGrafico === 'barras') && item.stock_min > 0) {
            datasets.push({
                label: `Stock Mín: ${formatNum(item.stock_min)} ${escHtml(item.unidad)}`,
                data: labelsExtended.map(() => item.stock_min),  // ya cubre zona proyección
                borderColor: '#e74c3c',
                borderWidth: 2,
                borderDash: [8, 4],
                pointRadius: 0,
                fill: false,
                tension: 0,
                type: 'line',
                order: 5,
                _stockMin: true,  // marca para excluir del null-padding
            });
        }
    }


    if (!esLineaSuc) {
        // Pad todos los datasets históricos con null para las 3 semanas proyectadas
        // EXCEPCIÓN: datasets marcados con _stockMin ya tienen datos para toda la zona (labelsExtended)
        datasets.forEach(ds => {
            if (!Array.isArray(ds.data)) return;
            if (ds._stockMin) return;  // ya cubre labelsExtended completo — no padear
            ds.data = [...ds.data, null, null, null];
            if (Array.isArray(ds.pointRadius)) {
                ds.pointRadius = [...ds.pointRadius, 0, 0, 0];
            }
        });

        // Dataset de proyección: nace en semana actual (si aplica) y se extiende 3 semanas
        // Con spanGaps:true la línea conecta automáticamente el punto de sem. actual con las futuras
        const proyData = [...semanasNros.map(n =>
            (esSemActualEnRango && n === semanaActual) ? proyActual : null
        ), proyW1, proyW2, proyW3];
        const proyPointR = [...semanasNros.map(n =>
            (esSemActualEnRango && n === semanaActual) ? 5 : 0
        ), 6, 6, 6];
        datasets.push({
            label: `↗ Proyección`,
            data: proyData,
            borderColor: '#f39c12',
            backgroundColor: 'rgba(243,156,18,.12)',
            borderWidth: 2.5,
            borderDash: [6, 4],
            pointRadius: proyPointR,
            pointStyle: 'triangle',
            pointBackgroundColor: '#f39c12',
            pointBorderColor: '#fff',
            pointBorderWidth: 1.5,
            fill: false,
            tension: 0,
            type: 'line',
            spanGaps: true,   // conecta sem. actual con las semanas proyectadas
            order: 0,
        });
    }

    // Tipo de gráfico Chart.js: barras → 'bar', ambas líneas → 'line'
    const tipoChartJS = modoGrafico === 'barras' ? 'bar' : 'line';

    if (chartTendencia) { chartTendencia.destroy(); chartTendencia = null; }

    const ctx = document.getElementById('chartTendencia').getContext('2d');

    // Leyenda siempre en la parte inferior para no comprimir el área del gráfico
    const numSucursales = hayDesglose ? sucursales.length : 1;

    chartTendencia = new Chart(ctx, {
        type: tipoChartJS,
        data: { labels: labelsExtended, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'bottom',
                    onClick: function (e, legendItem, legend) {
                        // Toggle nativo: ocultar/mostrar dataset al hacer clic en leyenda
                        const index = legendItem.datasetIndex;
                        const ci = legend.chart;
                        if (ci.isDatasetVisible(index)) {
                            ci.hide(index);
                            legendItem.hidden = true;
                        } else {
                            ci.show(index);
                            legendItem.hidden = false;
                        }
                        // Mostrar/ocultar botón de reset según si hay algo oculto
                        const hayOcultos = ci.data.datasets.some((_, i) => !ci.isDatasetVisible(i));
                        $('#chartLegendReset').toggle(hayOcultos);
                    },
                    labels: {
                        font: { size: 10, family: 'Calibri' },
                        padding: 12,
                        boxWidth: 12,
                        usePointStyle: true,
                        // filtrar el dataset "Prom" de la leyenda si hay muchas series
                        filter: (item) => numSucursales > 6
                            ? (!item.text.startsWith('Prom.') && !item.text.startsWith('Stock Mín'))
                            : true,
                        generateLabels: function (chart) {
                            // Usar el generador nativo y añadir el sufijo de hint solo en el primero
                            const labels = Chart.defaults.plugins.legend.labels.generateLabels(chart);
                            return labels;
                        },
                    },
                },
                tooltip: {
                    callbacks: {
                        label: ctx => {
                            // Ocultar promedio del body si hay muchas series
                            if (ctx.dataset.label && ctx.dataset.label.startsWith('Prom.')) return null;
                            // Etiqueta de proyección con ícono de avance
                            if (ctx.dataset.label && ctx.dataset.label.startsWith('Proy.')) {
                                return `⏩ ${ctx.dataset.label}: ${formatNum(ctx.parsed.y)} ${escHtml(item.unidad)}`;
                            }
                            return `${ctx.dataset.label}: ${formatNum(ctx.parsed.y)} ${escHtml(item.unidad)}`;
                        },
                        // En modo barra apilada: footer muestra el TOTAL de la semana
                        footer: (items) => {
                            if (!esBarraSuc) return undefined;
                            // Excluir Prom./Proy. — sumar solo datasets de sucursal
                            const total = items
                                .filter(i => i.dataset.label && !i.dataset.label.startsWith('Prom.') && !i.dataset.label.startsWith('Proy.'))
                                .reduce((acc, i) => acc + (i.parsed.y || 0), 0);
                            return `TOTAL: ${formatNum(round2(total))} ${escHtml(item.unidad)}`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    stacked: esBarraSuc,
                    grid: { color: '#e8f0f4' },
                    ticks: { font: { size: 10 }, maxRotation: 45 },
                },
                y: {
                    // stacked:true en modo barra por sucursal → las barras se apilan correctamente
                    stacked: esBarraSuc,
                    grid: { color: '#e8f0f4' },
                    ticks: { font: { size: 10 }, callback: v => formatNum(v) },
                    beginAtZero: true,
                },
            },
        },
    });

    // Nuevo chart: todas las series visibles → ocultar botón reset
    $('#chartLegendReset').hide();
}



/* ── Tabla Historial ─────────────────────────────────────── */
function renderTablaHistorial(data) {
    let html = '';
    const total = data.consumo.length;

    if (total === 0) {
        html = `<tr><td colspan="8" class="text-center text-muted py-4">Sin datos de consumo en el período.</td></tr>`;
    } else {
        data.consumo.forEach(item => {
            const tipoBadge = item.es_global
                ? `<span class="badge-global"><i class="fas fa-layer-group me-1"></i>Global</span>`
                : `<span class="badge-normal"><i class="fas fa-box me-1"></i>Simple</span>`;

            const trendIcon = item.tendencia === 'up'
                ? `<span class="dc-trend-up"><i class="fas fa-arrow-up"></i></span>`
                : item.tendencia === 'down'
                    ? `<span class="dc-trend-down"><i class="fas fa-arrow-down"></i></span>`
                    : `<span class="dc-trend-flat"><i class="fas fa-minus"></i></span>`;

            html += `
            <tr>
                <td>
                    <div class="fw-bold" style="font-size:.82rem">${escHtml(item.nombre)}
                        ${item.es_p1 ? '<span class="ms-1" style="font-size:.65rem;background:#e8f5e9;color:#2e7d32;border-radius:3px;padding:1px 5px">P1</span>' : ''}
                    </div>
                    <div class="text-muted" style="font-size:.72rem">${escHtml(item.maestro)}</div>
                </td>
                <td><span class="badge bg-light text-dark border" style="font-size:.7rem">${escHtml(item.categoria_insumo || '—')}</span></td>
                <td class="text-end fw-bold" style="color:#0E544C">${formatNum(item.total)}</td>
                <td class="text-end">${formatNum(item.prom_semana)}</td>
                <td class="text-end">
                    ${item.semana_pico_num
                    ? `<span class="dc-semana-badge">Sem ${item.semana_pico_num}</span>
                           <br><small class="text-muted">${formatNum(item.max_consumo_sem)}</small>`
                    : '—'}
                </td>
                <td class="text-center">${Object.keys(item.por_semana).length}</td>
                <td class="text-center">${tipoBadge}</td>
                <td class="text-center" style="white-space:nowrap">
                    <button class="btn-desglose" onclick="mostrarDesglose(${item.id})" title="Desglose por semana/sucursal">
                        <i class="fas fa-expand-arrows-alt me-1"></i>Ver
                    </button>
                    <button class="btn-desglose ms-1" style="background:#fff3e0;color:#e65100;border:1px solid #ffb74d" onclick="mostrarAuditoria(${item.id})" title="Auditoría venta por venta">
                        <i class="fas fa-microscope"></i>
                    </button>
                </td>
            </tr>`;
        });
    }

    $('#tbodyHistorial').html(html);
    $('#labelResultados').text(total > 0 ? `${total} insumo(s) encontrado(s)` : '');
}

/* ── Tabla Proyección ─────────────────────────────────────── */
function renderTablaProyeccion(data) {
    let html = '';

    // Pre-calcular semanas completas (misma lógica que gráfico y KPIs)
    const semanasNrosTbl = data.semanas.map(s => s.numero_semana);
    const semanaActualTbl = parseInt($('#semanaActualNum').text()) || 0;
    const esSemActTbl = semanaActualTbl > 0 && semanasNrosTbl.includes(semanaActualTbl);
    const semanasCalcTbl = esSemActTbl
        ? semanasNrosTbl.filter(n => n !== semanaActualTbl)
        : semanasNrosTbl;
    const ultimaSemTbl = semanasNrosTbl[semanasNrosTbl.length - 1];

    if (data.consumo.length === 0) {
        html = `<tr><td colspan="9" class="text-center text-muted py-4">Sin datos de proyección.</td></tr>`;
    } else {
        data.consumo.forEach(item => {
            // Proyección 3 semanas con regresión lineal (misma lógica que gráfico/KPIs)
            let promCalcTbl = item.prom_semana || 0;
            if (semanasCalcTbl.length > 0) {
                const valsC = semanasCalcTbl.map(n => item.por_semana[n] || 0);
                const valsP = valsC.filter(v => v > 0);
                if (valsP.length > 0) promCalcTbl = valsP.reduce((a, b) => a + b, 0) / valsP.length;
            }

            let proy3Tbl = promCalcTbl * 3;
            let slTbl = 0;  // pendiente OLS — usada también para Tendencia
            if (semanasCalcTbl.length >= 2) {
                const xV = semanasCalcTbl;
                const yV = semanasCalcTbl.map(n => item.por_semana[n] || 0);
                const nk = xV.length;
                const sumX = xV.reduce((a, b) => a + b, 0);
                const sumY = yV.reduce((a, b) => a + b, 0);
                const sumXY = xV.reduce((acc, x, i) => acc + x * yV[i], 0);
                const sumX2 = xV.reduce((acc, x) => acc + x * x, 0);
                const denom = nk * sumX2 - sumX * sumX;
                if (Math.abs(denom) > 0.001) {
                    slTbl = (nk * sumXY - sumX * sumY) / denom;
                    const ic = (sumY - slTbl * sumX) / nk;
                    const w1 = Math.max(0, slTbl * (ultimaSemTbl + 1) + ic);
                    const w2 = Math.max(0, slTbl * (ultimaSemTbl + 2) + ic);
                    const w3 = Math.max(0, slTbl * (ultimaSemTbl + 3) + ic);
                    proy3Tbl = w1 + w2 + w3;
                }
            }

            // Tendencia basada en la pendiente OLS (mismo criterio que el gráfico)
            // ±5% del promedio por semana como umbral de "estable"
            const olsThreshold = promCalcTbl * 0.05;
            const trendOls = slTbl > olsThreshold
                ? 'up'
                : slTbl < -olsThreshold
                    ? 'down'
                    : 'flat';
            const trendIcon = trendOls === 'up'
                ? `<span class="dc-trend-up"><i class="fas fa-arrow-up me-1"></i>Creciente</span>`
                : trendOls === 'down'
                    ? `<span class="dc-trend-down"><i class="fas fa-arrow-down me-1"></i>Decreciente</span>`
                    : `<span class="dc-trend-flat"><i class="fas fa-minus me-1"></i>Estable</span>`;

            html += `
            <tr>
                <td>
                    <div class="fw-bold" style="font-size:.82rem">${escHtml(item.nombre)}</div>
                    <div class="text-muted" style="font-size:.72rem">${escHtml(item.maestro)}</div>
                </td>
                <td><span class="badge bg-light text-dark border" style="font-size:.7rem">${escHtml(item.categoria_insumo || '—')}</span></td>
                <td class="text-end">${formatNum(item.prom_semana)}</td>
                <td class="text-end fw-bold" style="color:#0E544C">${Math.round(proy3Tbl).toLocaleString('es-NI')}</td>
                <td class="text-end" style="color:#e67e22">${formatNum(item.stock_min)}</td>
                <td class="text-end" style="color:#27ae60">${formatNum(item.stock_max)}</td>
                <td class="text-end">
                    ${item.semana_pico_num ? `<span class="dc-semana-badge">Sem ${item.semana_pico_num}</span>` : '—'}
                </td>
                <td class="text-end">
                    ${item.semana_low_num ? `<span class="dc-semana-badge">Sem ${item.semana_low_num}</span>` : '—'}
                </td>
                <td class="text-center">${trendIcon}</td>
            </tr>`;
        });
    }

    $('#tbodyProyeccion').html(html);
}

/* ── Selector de insumo en panel de análisis ────────────── */
function renderInsumoSel(data) {
    const $sel = $('#chartInsumoSel');
    const prevVal = $sel.val();   // conservar selección si ya hay una al recargar

    $sel.empty().append('<option value=""></option>');
    
    const opciones = data.consumo.map(item => {
        const tipoLabel = item.es_global ? ' [Global]' : '';
        const label = `${item.nombre}${tipoLabel}`;
        $sel.append(`<option value="${item.id}">${escHtml(label)}</option>`);
        return { value: item.id, label: label, subtext: item.maestro };
    });

    InsumoPicker.setOpciones(opciones);

    // Si ya había una selección válida, restaurarla y re-renderizar
    if (prevVal && $sel.find(`option[value="${prevVal}"]`).length) {
        $sel.val(prevVal);
        InsumoPicker.setValue(prevVal);
        $('#chartInsumoSel').trigger('change');
    } else {
        // Estado inicial: mostrar placeholder
        $('#chartPlaceholder').removeClass('d-none');
        $('#chartWrap').addClass('d-none');
        if (chartTendencia) { chartTendencia.destroy(); chartTendencia = null; }
        $('#tituloTendencia').html('<i class="fas fa-chart-line me-2"></i>Tendencia');
        InsumoPicker.setValue(null);
    }
}


/* ── InsumoPicker — Single Searchable Select ─────────────── */
const InsumoPicker = (() => {
    let _opciones = []; // { value, label, subtext }
    let _seleccionado = null;

    const $trigger = $('#dcInsumoTrigger');
    const $search = $('#dcInsumoSearch');
    const $dropdown = $('#dcInsumoDropdown');
    const $list = $('#dcInsumoList');
    const $hiddenSel = $('#chartInsumoSel');

    function open() {
        $trigger.addClass('open');
        $dropdown.addClass('open');
        renderList($search.val());
    }

    function close() {
        $trigger.removeClass('open');
        $dropdown.removeClass('open');
        // Restaurar el label del seleccionado si se cierra sin elegir
        if (_seleccionado) {
            const opt = _opciones.find(o => String(o.value) === String(_seleccionado));
            $search.val(opt ? opt.label : '');
        } else {
            $search.val('');
        }
    }

    function toggle() { $dropdown.hasClass('open') ? close() : open(); }

    function renderList(query) {
        $list.empty();
        const q = (query || '').toLowerCase();
        const filtradas = _opciones.filter(o => 
            o.label.toLowerCase().includes(q) || 
            (o.subtext && o.subtext.toLowerCase().includes(q))
        );

        if (filtradas.length === 0) {
            $list.append('<div class="dc-suc-empty"><i class="fas fa-search me-1"></i>Sin resultados</div>');
            return;
        }

        filtradas.forEach(o => {
            const sel = String(_seleccionado) === String(o.value);
            const $item = $(`
                <div class="dc-suc-item ${sel ? 'selected' : ''}" data-v="${o.value}" style="padding-left:15px">
                    <div class="dc-suc-item-label">
                        <div class="fw-bold" style="font-size:.82rem">${escHtml(o.label)}</div>
                        <div class="text-muted" style="font-size:.7rem">${escHtml(o.subtext || '')}</div>
                    </div>
                </div>`);
            $list.append($item);
        });
    }

    function select(value) {
        const v = value ? String(value) : null;
        _seleccionado = v;
        $hiddenSel.val(v || '').trigger('change');
        
        if (v) {
            const opt = _opciones.find(o => String(o.value) === v);
            $search.val(opt ? opt.label : '');
        } else {
            $search.val('');
        }
        close();
    }

    function init() {
        $search.on('focus', open);
        
        $search.on('input', function () {
            if (!$dropdown.hasClass('open')) open();
            renderList($(this).val());
        });

        $list.on('click', '.dc-suc-item', function () {
            select($(this).data('v'));
        });

        // Click en el chevron abre/cierra
        $('#dcInsumoChevron').on('click', function(e) {
            e.stopPropagation();
            toggle();
        });

        $(document).on('click.insumopicker', function (e) {
            if (!$trigger.is(e.target) && $trigger.has(e.target).length === 0
                && !$dropdown.is(e.target) && $dropdown.has(e.target).length === 0) {
                close();
            }
        });
    }

    function setOpciones(arr) {
        _opciones = arr;
    }

    function setValue(v) {
        _seleccionado = v ? String(v) : null;
        if (_seleccionado) {
            const opt = _opciones.find(o => String(o.value) === _seleccionado);
            $search.val(opt ? opt.label : '');
        } else {
            $search.val('');
        }
    }

    return { init, setOpciones, setValue };
})();

/* ── Modal Desglose ──────────────────────────────────────── */
window.mostrarDesglose = function (idInsumo) {
    if (!datosActuales) return;
    const item = datosActuales.consumo.find(c => c.id == idInsumo);
    if (!item) return;

    const semanas = datosActuales.semanas;
    const sucursales = datosActuales.sucursales;

    let theadHtml = '<tr><th style="min-width:100px">Semana</th>';
    sucursales.forEach(s => { theadHtml += `<th>${escHtml(datosActuales.sucursales_nombres?.[s] || s)}</th>`; });
    theadHtml += '<th>TOTAL</th></tr>';

    let tbodyHtml = '';
    let totalGeneral = 0;
    const totalesSuc = {};
    sucursales.forEach(s => { totalesSuc[s] = 0; });

    semanas.forEach(sem => {
        let totalSem = 0;
        let fila = `<tr><td><span class="dc-semana-badge">${sem.numero_semana}</span></td>`;
        sucursales.forEach(suc => {
            const v = item.desglose_semxsuc[sem.numero_semana]?.[suc] || 0;
            totalSem += v;
            totalesSuc[suc] += v;
            fila += `<td class="text-end">${v > 0 ? formatNum(v) : '<span class="text-muted">—</span>'}</td>`;
        });
        totalGeneral += totalSem;
        fila += `<td class="text-end fw-bold" style="color:#0E544C">${formatNum(totalSem)}</td></tr>`;
        tbodyHtml += fila;
    });

    // Fila total
    let filaTot = `<tr style="background:#f0f8f6;font-weight:700"><td>TOTAL PERÍODO</td>`;
    sucursales.forEach(s => { filaTot += `<td class="text-end">${formatNum(totalesSuc[s])}</td>`; });
    filaTot += `<td class="text-end" style="color:#0E544C">${formatNum(totalGeneral)}</td></tr>`;

    const contenido = `
        <div class="mb-2">
            <strong>${escHtml(item.nombre)}</strong>
            <span class="badge bg-light text-dark border ms-2" style="font-size:.7rem">${escHtml(item.categoria_insumo || '—')}</span>
            <span class="text-muted ms-2" style="font-size:.8rem">· ${escHtml(item.unidad)} · ${escHtml(item.maestro)}</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover dc-tabla mb-0" style="font-size:.78rem">
                <thead>${theadHtml}</thead>
                <tbody>${tbodyHtml}${filaTot}</tbody>
            </table>
        </div>
    `;

    $('#modalDesgloseLabel').html(`<i class="fas fa-expand-arrows-alt me-2"></i>${escHtml(item.nombre)} — Detalle`);
    $('#modalDesgloseContenido').html(contenido);
    const modal = new bootstrap.Modal(document.getElementById('modalDesglose'));
    modal.show();
};

/* ── Modal Auditoría venta × venta ───────────────────────── */
window.mostrarAuditoria = function (idInsumo) {
    if (!datosActuales) return;
    const item = datosActuales.consumo.find(c => c.id == idInsumo);
    if (!item) return;

    const semD = datosActuales._semDesde;
    const semH = datosActuales._semHasta;
    const sucs = $sucursales.val() || [];

    // Abrir modal con loader
    $('#modalAuditoriaLabel').html(`<i class="fas fa-microscope me-2"></i>${escHtml(item.nombre)} — Auditoría de cálculo`);
    $('#modalAuditoriaContenido').html(`
        <div class="text-center py-5">
            <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
            <div class="mt-2 text-muted">Cargando ventas individuales…</div>
        </div>
    `);
    const modal = new bootstrap.Modal(document.getElementById('modalAuditoria'));
    modal.show();

    const fd = new FormData();
    fd.append('id_presentacion', idInsumo);
    fd.append('semana_desde_num', semD);
    fd.append('semana_hasta_num', semH);
    sucs.forEach(s => fd.append('sucursales[]', s));

    $.ajax({
        url: 'ajax/dashboard_consumo_auditoria.php',
        type: 'POST',
        data: fd,
        processData: false,
        contentType: false,
    }).done(function (resp) {
        if (!resp.ok) {
            $('#modalAuditoriaContenido').html(`<div class="alert alert-danger">${escHtml(resp.msg)}</div>`);
            return;
        }

        const pp = resp.presentacion;
        const filas = resp.filas;
        const nDec = filas.filter(f => f.genera_decimal).length;

        let thead = `<tr style="font-size:.72rem;position:sticky;top:0;background:#fff;z-index:1">
            <th>Sem</th><th>Sucursal</th><th>Fecha</th><th>Batido</th>
            <th>Ingrediente</th><th>Und.Access</th><th style="text-align:right">Ventas</th>
            <th style="text-align:right">Cant.Receta</th><th style="text-align:right">Total raw</th>
            <th style="text-align:right">Factor</th><th style="text-align:right">pp_cant</th>
            <th style="text-align:right">Crudo</th><th style="text-align:right">Final</th>
            <th>Tipo</th><th>Nivel</th>
        </tr>`;

        let tbody = '';
        let sumCrudo = 0, sumFinal = 0;
        filas.forEach(f => {
            sumCrudo += f.consumo_crudo;
            sumFinal += f.consumo_final;
            const tipoMapeo = f.tipo_mapeo || (f.es_p1 ? 'P1' : 'P2');
            const tipoBadgeStyle = tipoMapeo === 'P1'
                ? 'background:#c8e6c9;color:#1b5e20'
                : tipoMapeo === 'P2'
                    ? 'background:#bbdefb;color:#0d47a1'
                    : 'background:#ffe0b2;color:#bf360c';
            const tipoMapeoHtml = `<span style="font-size:.65rem;border-radius:3px;padding:1px 5px;font-weight:700;${tipoBadgeStyle}">${tipoMapeo}</span>`;

            const rowBg = f.genera_decimal
                ? 'background:#fff8e1'
                : tipoMapeo === 'P1'
                    ? 'background:#f1f8e9'
                    : tipoMapeo === 'P2'
                        ? 'background:#f3f8ff'
                        : 'background:#fff8f2';

            const diffBadge = f.genera_decimal
                ? `<span style="font-size:.65rem;background:#ffe082;border-radius:3px;padding:1px 4px" title="Crudo: ${f.consumo_crudo}">Δ${round05(f.consumo_crudo).toFixed(1)}</span>`
                : '';
            tbody += `<tr style="font-size:.72rem;${rowBg}">
                <td>${f.semana}</td>
                <td>${escHtml(f.sucursal)}</td>
                <td style="white-space:nowrap">${f.fecha}</td>
                <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(f.nombre_batido)}">${escHtml(f.nombre_batido)}</td>
                <td style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(f.nombre_ingrediente)}">${escHtml(f.nombre_ingrediente)}</td>
                <td>${escHtml(f.unidad_access)}</td>
                <td class="text-end">${f.ventas}</td>
                <td class="text-end">${f.cant_receta}</td>
                <td class="text-end">${f.cant_total}</td>
                <td class="text-end" style="color:#1565c0">${f.factor}</td>
                <td class="text-end">${f.pp_cantidad}</td>
                <td class="text-end" style="color:#555">${f.consumo_crudo}</td>
                <td class="text-end fw-bold" style="color:#0E544C">${f.consumo_final} ${diffBadge}</td>
                <td>${tipoMapeoHtml}</td>
                <td style="font-size:.65rem;color:#777">${escHtml(f.nivel)}</td>
            </tr>`;
        });

        // Totales
        tbody += `<tr style="background:#e8f5e9;font-weight:700;font-size:.73rem">
            <td colspan="11" class="text-end">TOTAL</td>
            <td class="text-end">${round2(sumCrudo)}</td>
            <td class="text-end" style="color:#0E544C">${round2(sumFinal)}</td>
            <td colspan="2"></td>
        </tr>`;

        const alertaHtml = nDec > 0
            ? `<div class="alert alert-warning py-2 mb-2" style="font-size:.8rem">
                <i class="fas fa-exclamation-triangle me-1"></i>
                <strong>${nDec} fila(s) P1</strong> tuvieron consumo crudo redondeado al 0.5 más cercano (fondo amarillo).
               </div>`
            : `<div class="alert alert-success py-2 mb-2" style="font-size:.8rem">
                <i class="fas fa-check-circle me-1"></i> Todos los cálculos P1 caen exactamente en múltiplos de 0.5.
               </div>`;

        // Leyenda de tipos de mapeo
        const leyendaHtml = `<div class="d-flex gap-2 mb-2 flex-wrap" style="font-size:.75rem">
            <span style="background:#c8e6c9;color:#1b5e20;border-radius:3px;padding:1px 7px;font-weight:700">P1</span>
            <span style="color:#555">Porción directa — redondea al 0.5 más cercano</span>
            <span class="ms-3" style="background:#bbdefb;color:#0d47a1;border-radius:3px;padding:1px 7px;font-weight:700">P2</span>
            <span style="color:#555">Cotización base — 4 decimales</span>
            <span class="ms-3" style="background:#ffe0b2;color:#bf360c;border-radius:3px;padding:1px 7px;font-weight:700">P3</span>
            <span style="color:#555">Fallback — 4 decimales</span>
        </div>`;

        const infoHtml = `<div class="mb-2 d-flex gap-3 flex-wrap" style="font-size:.8rem">
            <span><strong>Presentación:</strong> ${escHtml(pp.nombre)}</span>
            <span><strong>Categoría:</strong> <span class="badge bg-light text-dark border" style="font-size:.7rem">${escHtml(pp.categoria_insumo || '—')}</span></span>
            <span><strong>Unidad ERP:</strong> ${escHtml(pp.unidad)}</span>
            <span><strong>pp_cantidad:</strong> ${pp.pp_cant}</span>
            <span><strong>Filas:</strong> ${resp.total_filas}</span>
        </div>`;

        const html = `
            ${infoHtml}${leyendaHtml}${alertaHtml}
            <div class="table-responsive" style="max-height:60vh;overflow-y:auto">
                <table class="table table-hover dc-tabla mb-0" style="font-size:.72rem">
                    <thead>${thead}</thead>
                    <tbody>${tbody}</tbody>
                </table>
            </div>
        `;
        $('#modalAuditoriaContenido').html(html);
    }).fail(function () {
        $('#modalAuditoriaContenido').html('<div class="alert alert-danger">Error de conexión.</div>');
    });
};

function round05(n) { return Math.round(parseFloat(n) * 2) / 2; }

/* ── Exportar CSV ─────────────────────────────────────────── */
function exportarCSV() {
    if (!datosActuales || !PUEDE_EXPORTAR) return;

    // Determinar modo según tab activo
    const tabActivo = $('.dc-tab-btn.active').attr('id');
    let modo = 'historial';
    if (tabActivo === 'tabProyeccionBtn') modo = 'proyeccion';
    if (tabActivo === 'tabSinMapeoBtn') modo = 'sin_mapeo';

    const payload = {
        consumo: datosActuales.consumo,
        semanas: datosActuales.semanas,
        sin_mapeo: datosActuales.sin_mapeo,
        sucursales: datosActuales.sucursales || [],
        sucursales_nombres: datosActuales.sucursales_nombres || {},
        sem_desde: datosActuales._semDesde,
        sem_hasta: datosActuales._semHasta,
        modo,
    };

    // Crear form oculto para descargar
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'ajax/dashboard_consumo_exportar.php';
    form.style.display = 'none';

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'not_used'; // El endpoint lee php://input directamente
    form.appendChild(input);
    document.body.appendChild(form);

    // Usar fetch para descargar
    fetch('ajax/dashboard_consumo_exportar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    })
        .then(res => res.blob())
        .then(blob => {
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `consumo_insumos_sem${datosActuales._semDesde}_${datosActuales._semHasta}.xlsx`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
            document.body.removeChild(form);
        })
        .catch(err => {
            console.error('Error exportando:', err);
            Swal.fire({ icon: 'error', title: 'Error al exportar', text: 'No se pudo generar el CSV.', confirmButtonColor: '#0E544C' });
        });
}

/* ════════════════════════════════════════════════════════════
   ALERTAS DE SOBRECONSUMO
   ════════════════════════════════════════════════════════════ */

/**
 * Por cada sucursal × insumo calcula la serie semanal de esa sucursal
 * y alerta si alguna semana supera μ_suc + kSigma × σ_suc.
 * Cada local se compara contra SÍ MISMO (no contra otros locales).
 */
function calcularAlertasSobreconsumo(data, kSigma) {
    const alertas = [];
    const nombres = data.sucursales_nombres || {};
    const semanasNros = data.semanas.map(s => s.numero_semana);

    data.consumo.forEach(item => {
        data.sucursales.forEach(suc => {
            // Serie semanal completa de ESTA sucursal para ESTE insumo
            const serieCompleta = semanasNros.map(n =>
                item.desglose_semxsuc?.[n]?.[suc] || 0
            );
            // Usar solo semanas con venta para calcular μ y σ
            const serieConValor = serieCompleta.filter(v => v > 0);
            if (serieConValor.length < 2) return; // mínimo 2 semanas

            const mu = serieConValor.reduce((a, b) => a + b, 0) / serieConValor.length;
            const sigma = Math.sqrt(
                serieConValor.map(v => (v - mu) ** 2).reduce((a, b) => a + b, 0) / serieConValor.length
            );
            if (sigma < 0.001) return; // patrón plano → sin anomalía

            const umbral = mu + kSigma * sigma;

            semanasNros.forEach((n, idx) => {
                const v = serieCompleta[idx];
                if (v > umbral) {
                    alertas.push({
                        insumo: item.nombre,
                        unidad: item.unidad,
                        local: nombres[suc] || suc,
                        semana: n,
                        consumo: v,
                        mu, sigma, umbral,
                        zScore: (v - mu) / sigma,
                        pctExceso: Math.round((v - mu) / mu * 100),
                        idInsumo: item.id,
                    });
                }
            });
        });
    });

    // Ordenar: semana DESC, luego sucursal ASC, luego insumo ASC
    return alertas.sort((a, b) => {
        if (b.semana !== a.semana) return b.semana - a.semana;
        const locCmp = a.local.localeCompare(b.local, 'es');
        if (locCmp !== 0) return locCmp;
        return a.insumo.localeCompare(b.insumo, 'es');
    });
}

/**
 * Renderiza el panel colapsable de alertas encima de los KPIs.
 * Si no hay datos suficientes oculta el panel.
 */
function renderPanelAlertas(data, kSigma) {
    const $panel = $('#panelAlertas');

    if (!data || !data.sucursales || data.sucursales.length < 1) {
        $panel.hide();
        return;
    }

    const alertas = calcularAlertasSobreconsumo(data, kSigma);
    $panel.show();

    if (alertas.length === 0) {
        $('#alertasBadge').text('0').css({ background: '#27ae60', color: '#fff' });
        $('#alertasHint').text('sin spikes detectados');
        // Cambiar cabecera a verde si no hay alertas
        $('.dc-alertas-header').css('background', 'linear-gradient(135deg,#1a7a41 0%,#27ae60 100%)');
        $('.dc-alertas-panel').css('border-left-color', '#27ae60');
        $('#alertasContenido').html(
            `<div class="dc-alertas-vacio"><i class="fas fa-check-circle"></i>Todas las tiendas operan dentro del rango normal con umbral ${kSigma}σ</div>`
        );
        return;
    }

    // Restaurar colores de alerta rojo
    $('.dc-alertas-header').css('background', 'linear-gradient(135deg,#c0392b 0%,#e74c3c 100%)');
    $('.dc-alertas-panel').css('border-left-color', '#e74c3c');
    $('#alertasBadge').text(alertas.length).css({ background: '#fff', color: '#c0392b' });
    $('#alertasHint').text(`umbral: μ_local + ${kSigma}σ_local`);

    // Renderizar lista plana ya ordenada: semana DESC → sucursal ASC → insumo ASC
    let filas = '';
    alertas.forEach(a => {
        const zCls = a.zScore >= 2.5 ? 'z-critico' : a.zScore >= 2 ? 'z-alto' : 'z-moderado';
        // Escapar el nombre del local para usarlo de forma segura en el atributo onclick
        const localEscJS = a.local.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
        filas += `
        <tr>
            <td style="text-align:center;font-weight:700;font-size:.78rem;color:#0E544C">${a.semana}</td>
            <td style="font-size:.75rem;color:#777;font-style:italic">${escHtml(a.local)}</td>
            <td>
                <span class="dc-alerta-insumo-pill"
                    onclick="seleccionarInsumoDesdeAlerta(${a.idInsumo}, '${localEscJS}')"
                    title="Ver tendencia de ${escHtml(a.insumo)} · solo ${escHtml(a.local)}">
                    <i class="fas fa-chart-line me-1" style="font-size:.62rem;opacity:.7"></i>${escHtml(a.insumo)}
                </span>
            </td>
            <td class="text-end fw-bold" style="color:#c0392b">
                ${formatNum(a.consumo)} ${escHtml(a.unidad)}
            </td>
            <td class="text-end" style="color:#888;font-size:.72rem">
                μ ${formatNum(round2(a.mu))} &nbsp;σ ${formatNum(round2(a.sigma))}
            </td>
            <td class="text-end">
                <span class="dc-alerta-zscore ${zCls}">+${a.zScore.toFixed(2)}σ</span>
            </td>
            <td class="text-end" style="color:#c0392b;font-weight:700;font-size:.78rem">
                +${a.pctExceso}%
            </td>
        </tr>`;
    });

    $('#alertasContenido').html(`
        <div style="overflow-x:auto;">
            <table class="dc-alertas-tabla">
                <thead>
                    <tr>
                        <th style="text-align:center">Semana</th>
                        <th>Tienda</th>
                        <th>Insumo</th>
                        <th class="text-end">Consumo Real</th>
                        <th class="text-end">Referencia (μ / σ)</th>
                        <th class="text-end">Z-Score</th>
                        <th class="text-end">% Exceso</th>
                    </tr>
                </thead>
                <tbody>${filas}</tbody>
            </table>
        </div>
    `);
}

/**
 * Selecciona un insumo desde el panel de alertas → activa el gráfico de tendencia.
 */
/**
 * Selecciona insumo desde alerta de sobreconsumo.
 * También activa el modo "Línea x Tienda" y filtra a solo la tienda del incidente.
 * @param {number} idInsumo   - ID del insumo a seleccionar
 * @param {string} [localName] - Nombre exacto de la tienda que tuvo el spike (opcional)
 */
window.seleccionarInsumoDesdeAlerta = function (idInsumo, localName) {
    // 1) Seleccionar el insumo en el dropdown
    $('#chartInsumoSel').val(idInsumo).trigger('change');

    // 2) Determinar modo: Línea x Tienda solo si hay >1 sucursal con desglose.
    //    Si se filtró una única sucursal, el botón está oculto → usar Línea Total.
    const sucursalesActuales = datosActuales ? (datosActuales.sucursales || []) : [];
    const hayMultiSuc = sucursalesActuales.length > 1;

    if (hayMultiSuc) {
        modoGrafico = 'linea_suc';
        $('#chartModoBarras, #chartModoLineaTotal, #chartModoLineaSuc').removeClass('active');
        $('#chartModoLineaSuc').addClass('active');
    } else {
        // Una sola sucursal → caer a Línea Total
        modoGrafico = 'linea_total';
        $('#chartModoBarras, #chartModoLineaTotal, #chartModoLineaSuc').removeClass('active');
        $('#chartModoLineaTotal').addClass('active');
    }

    // 3) Re-renderizar con el nuevo modo
    if (datosActuales) {
        renderGrafico(datosActuales);
    }

    // 4) Si se pasó un nombre de tienda Y estamos en modo multi-sucursal,
    //    ocultar todos los datasets menos el de esa tienda.
    if (localName && hayMultiSuc && chartTendencia) {
        // Esperar un tick para que el chart esté completamente renderizado
        setTimeout(() => {
            const chart = chartTendencia;
            if (!chart) return;
            chart.data.datasets.forEach((ds, i) => {
                // El label del dataset puede ser truncado, comparamos con startsWith
                const dsLabel = (ds.label || '').trim();
                const isTarget = dsLabel === localName ||
                    localName.startsWith(dsLabel) ||
                    dsLabel.startsWith(localName.substring(0, Math.min(localName.length, 20)));
                if (isTarget) {
                    chart.show(i);
                } else {
                    chart.hide(i);
                }
            });
            // Mostrar botón reset ya que hay series ocultas
            const hayOcultos = chart.data.datasets.some((_, i) => !chart.isDatasetVisible(i));
            $('#chartLegendReset').toggle(hayOcultos);
        }, 80);
    }

    // 5) Scroll al gráfico
    const el = document.getElementById('cardTendencia');
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
};

/* ════════════════════════════════════════════════════════════
   ALERTAS DE CRECIMIENTO SOSTENIDO
   ════════════════════════════════════════════════════════════

   Lógica matemática profesional de 3 indicadores independientes.
   Un insumo dispara la alerta cuando ≥ 2 de 3 indicadores
   superan sus umbrales — esto separa el crecimiento estructural
   de los spikes puntuales que ya captura el panel de sobreconsumo.

   INDICADOR 1 — Pendiente de regresión lineal normalizada (β̂):
     Ajustamos y = α + β·t sobre las semanas con valor > 0.
     Normalizamos: β_rel = β / μ  (crecimiento relativo por semana).
     Umbral: β_rel > MIN_SLOPE_REL  (ej. 6% crecimiento semanal).

   INDICADOR 2 — Mann-Kendall Tau normalizado (τ):
     S = Σ_{i<j} sgn(y_j − y_i)
     τ = S / (n(n−1)/2)  ∈ [-1, +1]
     Umbral: τ > MIN_MK_TAU  (ej. 0.45 = tendencia monótona fuerte).

   INDICADOR 3 — Ratio de incrementos consecutivos (run ratio):
     Conta transiciones y[t] > y[t−1] en las ÚLTIMAS semanas.
     run_ratio = positivas / (n_sem − 1)
     Umbral: run_ratio > MIN_RUN_RATIO  (ej. 0.65).

   Severidad compuesta:
     • Moderado  — 2 de 3 indicadores activos
     • Notable   — 2 de 3 + β_rel > 20%
     • Crítico   — 3 de 3 ó β_rel > 40%
   ════════════════════════════════════════════════════════════ */

const CREC_MIN_SLOPE_REL = 0.06;   // β/μ mínimo: 6 % de crecimiento semanal relativo
const CREC_MIN_MK_TAU = 0.45;   // τ Mann-Kendall mínimo (0 = sin tendencia, 1 = perfectamente monótono)
const CREC_MIN_RUN_RATIO = 0.65;   // ratio de semanas consecutivas en alza
const CREC_MIN_SEMANAS = 3;      // mínimo absoluto de semanas con dato para calcular
const CREC_IDEAL_SEMANAS = 6;      // mínimo para veredicto estadísticamente consistente
// (Mann-Kendall S_max=15, OLS con 3 DoF)
// Si el rango incluye la semana actual → seleccionar 7 sem
// Si el rango es 100% histórico → basta con 6 sem

/**
 * Calcula alertas de crecimiento sostenido por SUCURSAL × INSUMO.
 * @param {object} data   - Datos del dashboard
 * @param {number} kSlope - Umbral mínimo de β/μ (default: CREC_MIN_SLOPE_REL)
 * Devuelve array ordenado por sucursal ASC → insumo ASC.
 */
function calcularAlertasCrecimiento(data, kSlope) {
    const minSlope = (typeof kSlope === 'number') ? kSlope : CREC_MIN_SLOPE_REL;
    const alertas = [];
    const nombres = data.sucursales_nombres || {};
    const semanasNros = data.semanas.map(s => s.numero_semana);

    data.consumo.forEach(item => {
        data.sucursales.forEach(suc => {
            // Serie semanal de ESTA sucursal para ESTE insumo
            const serieCompleta = semanasNros.map(n =>
                item.desglose_semxsuc?.[n]?.[suc] || 0
            );

            // Excluir semana en curso SOLO si el rango analizado termina en ella
            // (dato parcial que distorsiona la pendiente).
            // Si el rango es histórico (sem_hasta < semana actual), todas las semanas están completas.
            const semActualCrec = parseInt($('#semanaActualNum').text()) || 0;
            const maxSemEnDatos = semanasNros.length > 0 ? Math.max(...semanasNros) : 0;
            const excluirActual = semActualCrec > 0 && maxSemEnDatos === semActualCrec;

            // Filtrar semanas con dato > 0 (y excluir la actual si aplica)
            const puntos = semanasNros
                .map((n, i) => ({ n, v: serieCompleta[i] }))
                .filter(d => d.v > 0 && (!excluirActual || d.n !== semActualCrec));

            if (puntos.length < CREC_MIN_SEMANAS) return;

            const ys = puntos.map(d => d.v);
            const xs = puntos.map(d => d.n);  // número de semana como eje X
            const N = ys.length;

            // ── μ (sobre semanas con valor) ──────────────────────────
            const mu = ys.reduce((a, b) => a + b, 0) / N;
            if (mu < 0.001) return;

            // ── INDICADOR 1: Regresión lineal OLS ───────────────────
            const sumX = xs.reduce((a, b) => a + b, 0);
            const sumY = ys.reduce((a, b) => a + b, 0);
            const sumXY = xs.reduce((acc, x, i) => acc + x * ys[i], 0);
            const sumX2 = xs.reduce((acc, x) => acc + x * x, 0);
            const denom = N * sumX2 - sumX * sumX;
            let slope = 0;
            if (Math.abs(denom) > 0.001) {
                slope = (N * sumXY - sumX * sumY) / denom;
            }
            const beta_rel = slope / mu;
            const ind1 = beta_rel > minSlope;   // usa el umbral dinámico

            // ── INDICADOR 2: Mann-Kendall τ ──────────────────────────
            let S = 0;
            for (let i = 0; i < N - 1; i++) {
                for (let j = i + 1; j < N; j++) {
                    const diff = ys[j] - ys[i];
                    if (diff > 0) S++;
                    else if (diff < 0) S--;
                }
            }
            const S_max = N * (N - 1) / 2;
            const tau = S_max > 0 ? S / S_max : 0;
            const ind2 = tau > CREC_MIN_MK_TAU;

            // ── INDICADOR 3: Ratio de incrementos consecutivos ───────
            let positivos = 0;
            for (let i = 1; i < N; i++) {
                if (ys[i] > ys[i - 1]) positivos++;
            }
            const run_ratio = (N > 1) ? positivos / (N - 1) : 0;
            const ind3 = run_ratio > CREC_MIN_RUN_RATIO;

            // ── Decisión: ≥ 2 de 3 ──────────────────────────────────
            const score = (ind1 ? 1 : 0) + (ind2 ? 1 : 0) + (ind3 ? 1 : 0);
            if (score < 2) return;

            // ── Severidad ────────────────────────────────────────────
            let severidad;
            if (score === 3 || beta_rel > 0.40) {
                severidad = 'critico';
            } else if (beta_rel > 0.20 || score >= 2) {
                severidad = 'notable';
            } else {
                severidad = 'moderado';
            }

            // Crecimiento acumulado en el período (línea de regresión)
            const intercept = (sumY - slope * sumX) / N;
            const y_inicio = Math.max(0, slope * xs[0] + intercept);
            const y_fin = Math.max(0, slope * xs[N - 1] + intercept);
            const pct_periodo = y_inicio > 0.001
                ? Math.round((y_fin - y_inicio) / y_inicio * 100)
                : 0;

            alertas.push({
                insumo: item.nombre,
                unidad: item.unidad,
                idInsumo: item.id,
                local: nombres[suc] || suc,
                mu: round2(mu),
                beta_rel,
                tau: round2(tau),
                run_ratio: round2(run_ratio),
                score,
                ind1, ind2, ind3,
                severidad,
                pct_semanal: Math.round(beta_rel * 100),
                pct_periodo,
                semanas_ok: N,
            });
        });
    });

    // Orden: sucursal ASC → insumo ASC
    return alertas.sort((a, b) => {
        const locCmp = a.local.localeCompare(b.local, 'es');
        if (locCmp !== 0) return locCmp;
        return a.insumo.localeCompare(b.insumo, 'es');
    });
}

/**
 * Renderiza el panel colapsable de alertas de crecimiento sostenido.
 */
function renderPanelCrecimiento(data) {
    const $panel = $('#panelCrecimiento');

    if (!data || !data.sucursales || data.sucursales.length < 1 ||
        !data.semanas || data.semanas.length < CREC_MIN_SEMANAS) {
        $panel.hide();
        return;
    }

    // Semanas reales analizadas:
    // Si el rango termina en la semana actual → se excluye (dato parcial)
    // Si el rango es histórico → todas las semanas del filtro están completas
    const semActualN = parseInt($('#semanaActualNum').text()) || 0;
    const maxSemData = data.semanas.length > 0
        ? Math.max(...data.semanas.map(s => s.numero_semana)) : 0;
    const excluirActual = semActualN > 0 && maxSemData === semActualN;
    const semsAnalizadas = excluirActual
        ? data.semanas.filter(s => s.numero_semana !== semActualN).length
        : data.semanas.length;
    const bajoideal = semsAnalizadas < CREC_IDEAL_SEMANAS;

    const alertasTodas = calcularAlertasCrecimiento(data, kSlopeActual);
    $panel.show();

    const totalDetectados = alertasTodas.length;

    // Aplicar filtro de severidad
    const alertas = alertasTodas.filter(a => {
        if (filtroSevCrec === 'critico') return a.severidad === 'critico';
        if (filtroSevCrec === 'notable') return a.severidad === 'critico' || a.severidad === 'notable';
        return true; // 'todos'
    });

    const total = alertas.length;
    const $badge = $('#crecimientoBadge');
    const $hint = $('#crecimientoHint');

    if (total === 0) {
        const sinMsg = totalDetectados > 0
            ? `${totalDetectados} detectado(s) · ninguno coincide con el filtro seleccionado`
            : 'consumo estable en el período';
        $badge.text(totalDetectados).css(totalDetectados > 0
            ? { background: '#fff', color: '#1a5276' }
            : { background: '#27ae60', color: '#fff' }
        );
        $hint.text(sinMsg);
        $('.dc-crec-header').css('background', 'linear-gradient(135deg,#1a5276 0%,#2980b9 100%)');
        $('.dc-crec-panel').css('border-left-color', '#2980b9');
        $('#crecimientoContenido').html(
            `<div class="dc-alertas-vacio" style="color:#2980b9"><i class="fas fa-check-circle"></i>${escHtml(sinMsg)}</div>`
        );
        return;
    }

    // Colores según mayor severidad presente
    const maxSev = alertas[0].severidad;
    const headerGrad = maxSev === 'critico'
        ? 'linear-gradient(135deg,#1a5276 0%,#8e44ad 100%)'
        : maxSev === 'notable'
            ? 'linear-gradient(135deg,#1a5276 0%,#2471a3 100%)'
            : 'linear-gradient(135deg,#1f618d 0%,#2e86c1 100%)';
    const borderCol = maxSev === 'critico' ? '#8e44ad' : '#2980b9';

    $('.dc-crec-header').css('background', headerGrad);
    $('.dc-crec-panel').css('border-left-color', borderCol);
    $badge.text(totalDetectados).css({ background: '#fff', color: '#1a5276' });
    const mostrando = total < totalDetectados ? ` · mostrando ${total}` : '';
    $hint.text(`${semsAnalizadas} sem analizadas · ${totalDetectados} detectado(s)${mostrando}`);

    let filas = '';
    alertas.forEach(a => {
        const sevClass = a.severidad === 'critico' ? 'crec-critico'
            : a.severidad === 'notable' ? 'crec-notable'
                : 'crec-moderado';
        const sevLabel = a.severidad === 'critico' ? '<i class="fas fa-fire"></i> Crítico'
            : a.severidad === 'notable' ? '<i class="fas fa-arrow-up"></i> Notable'
                : '<i class="fas fa-chart-line"></i> Moderado';

        // Indicadores activos como pills
        const pill = (activo, label, title) => activo
            ? `<span class="dc-crec-ind-pill active" title="${title}">${label}</span>`
            : `<span class="dc-crec-ind-pill"        title="${title}">${label}</span>`;

        const ind_html = [
            pill(a.ind1, 'Regresión', `β/μ = ${(a.beta_rel * 100).toFixed(1)}%/sem · umbral ${CREC_MIN_SLOPE_REL * 100}%`),
            pill(a.ind2, 'Mann-Kendall', `τ = ${a.tau} · umbral ${CREC_MIN_MK_TAU}`),
            pill(a.ind3, 'Run-ratio', `${Math.round(a.run_ratio * 100)}% incrementos · umbral ${CREC_MIN_RUN_RATIO * 100}%`),
        ].join(' ');

        const insumoEsc = escHtml(a.insumo);
        const localEscAttr = escHtml(a.local);
        const localEscJS = a.local.replace(/\\/g, '\\\\').replace(/'/g, "\\'");

        filas += `
        <tr>
            <td style="font-size:.75rem;color:#777;font-style:italic">${localEscAttr}</td>
            <td>
                <span class="dc-crec-insumo-pill"
                    onclick="seleccionarInsumoDesdeAlerta(${a.idInsumo}, '${localEscJS}')"
                    title="Ver tendencia de ${insumoEsc} · solo ${localEscAttr}">
                    <i class="fas fa-chart-line me-1" style="font-size:.62rem;opacity:.7"></i>${insumoEsc}
                </span>
            </td>
            <td class="text-end" style="font-size:.75rem;color:#888">${a.semanas_ok} sem</td>
            <td class="text-end fw-bold" style="color:#1a5276">
                +${a.pct_semanal}%<span style="font-weight:400;font-size:.7rem;color:#888">/sem</span>
            </td>
            <td class="text-end" style="color:#5d6d7e;font-size:.75rem">${a.pct_periodo > 0 ? '+' : ''}${a.pct_periodo}% período</td>
            <td>${ind_html}</td>
            <td><span class="dc-crec-sev ${sevClass}">${sevLabel}</span></td>
        </tr>`;
    });

    // Banner de advertencia si las semanas analizadas están por debajo del ideal
    const warningBanner = bajoideal ? `
        <div style="
            background: linear-gradient(135deg,rgba(230,126,34,.12),rgba(230,126,34,.05));
            border-left: 3px solid #e67e22;
            padding: 6px 14px;
            font-size: .72rem;
            color: #7d6608;
            display: flex;
            align-items: center;
            gap: 8px;
        ">
            <i class="fas fa-exclamation-triangle" style="color:#e67e22;flex-shrink:0"></i>
            <span>
                <strong>Veredicto orientativo</strong> &mdash;
                se analizaron <strong>${semsAnalizadas} semana(s)</strong> completa(s).
                Para un veredicto estadísticamente consistente se recomiendan
                <strong>${CREC_IDEAL_SEMANAS} semanas</strong> 
                (selecciona al menos <strong>${CREC_IDEAL_SEMANAS + 1} semanas</strong> en el filtro).
            </span>
        </div>` : '';

    $('#crecimientoContenido').html(`
        ${warningBanner}
        <div style="overflow-x:auto;">
            <table class="dc-crec-tabla">
                <thead>
                    <tr>
                        <th>Tienda</th>
                        <th>Insumo</th>
                        <th class="text-end">Semanas</th>
                        <th class="text-end">Crecim. Semanal</th>
                        <th class="text-end">Crecim. Período</th>
                        <th>Indicadores</th>
                        <th>Severidad</th>
                    </tr>
                </thead>
                <tbody>${filas}</tbody>
            </table>
        </div>
    `);
}

/* ════════════════════════════════════════════════════════════
   UTILIDADES
   ════════════════════════════════════════════════════════════ */
function formatNum(n) {
    if (n === null || n === undefined || isNaN(n)) return '0';
    const num = parseFloat(n);
    if (num === 0) return '0';
    // Si tiene decimales significativos muéstralos, sino entero
    if (Math.abs(num - Math.round(num)) < 0.001) return num.toLocaleString('es-NI');
    return num.toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
}

function formatFecha(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    return `${d.getDate()}/${meses[d.getMonth()]}/${String(d.getFullYear()).slice(2)}`;
}

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function round2(n) {
    return Math.round(parseFloat(n) * 100) / 100;
}

/* ════════════════════════════════════════════════════════════
   KARDEX (MOVIMIENTO DE EXISTENCIA)
   ════════════════════════════════════════════════════════════ */
let chartKardexExistencia = null;

function cargarKardex(idPP, item) {
    const KARDEX_AJAX = 'ajax/';

    // Validar semana de corte — si vacío, usar semDesde como default
    const semDesde = datosActuales._semDesde;
    const semHasta = datosActuales._semHasta;
    const inputCorte = $('#kardexSemanaCorte');

    // Auto-poblar con semDesde si está vacío (primera carga)
    if (!inputCorte.val()) {
        inputCorte.val(semDesde);
    }

    const semCorteRaw = parseInt(inputCorte.val());

    if (!semCorteRaw || semCorteRaw < semDesde || semCorteRaw > semHasta) {
        Swal.fire({
            icon: 'warning',
            title: 'Semana de Corte fuera de rango',
            html: `La semana de corte debe estar entre <strong>${semDesde}</strong> y <strong>${semHasta}</strong>.<br>
                   <small style="color:#888">Se resetea al valor por defecto (${semDesde}).</small>`,
            confirmButtonColor: '#0E544C'
        });
        inputCorte.val(semDesde);
        return;
    }

    $('#panelKardex').removeClass('d-none');
    $('#bdLoaderKardex').removeClass('d-none');
    $('#bdResumen').addClass('d-none');
    $('#bdChartWrap').addClass('d-none');
    $('#bdChartStockBadge').hide();
    $('#bdChartNota').hide();

    const sucursalesSelec = SucPicker.getSelected();

    const fd = new FormData();
    fd.append('id_pp', idPP);
    fd.append('semana_desde', semDesde);
    fd.append('semana_hasta', semHasta);
    fd.append('semana_corte', semCorteRaw);
    sucursalesSelec.forEach(s => fd.append('sucursales[]', s));

    fetch(KARDEX_AJAX + 'balance_inventario_get_detalle.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            $('#bdLoaderKardex').addClass('d-none');
            if (!res.ok) {
                $('#bdResumen').html(`<div class="bd-empty"><i class="fas fa-info-circle me-1"></i>${escHtml(res.msg || 'Sin datos de kardex')}</div>`).removeClass('d-none');
                return;
            }
            renderDetalleKardex(res);
            cargarStockMinMaxKardex(idPP, semHasta);
        })
        .catch(() => {
            $('#bdLoaderKardex').addClass('d-none');
            console.error("Error al cargar kardex");
        });
}

function renderDetalleKardex(res) {
    const t = res.totales_tipo || {};
    const bdResumen = document.getElementById('bdResumen');
    
    // Función fmt local para kardex
    const fmtKardex = (v, d = 4) => v === null || v === undefined ? '—' : parseFloat(v).toLocaleString('es', { minimumFractionDigits: d, maximumFractionDigits: d });
    
    const semCorte = res.semana_corte || '?';
    bdResumen.innerHTML = `
        <div class="bd-resumen-item" style="border-left:3px solid #f39c12">
            <div class="bd-resumen-label"><i class="fas fa-cut me-1" style="color:#f39c12"></i>INV. CORTE S${semCorte}</div>
            <div class="bd-resumen-val" style="color:#f39c12">${fmtKardex(t.inv_inicial, 2)}</div>
        </div>
        <div class="bd-resumen-item">
            <div class="bd-resumen-label">+ AJUSTE</div>
            <div class="bd-resumen-val" style="color:var(--bd-pos)">${fmtKardex(t.ajuste, 2)}</div>
        </div>
        <div class="bd-resumen-item">
            <div class="bd-resumen-label">+ DESPACHO</div>
            <div class="bd-resumen-val" style="color:var(--neu-accent)">${fmtKardex(t.despacho, 2)}</div>
        </div>
        <div class="bd-resumen-item">
            <div class="bd-resumen-label">+ COMPRAS</div>
            <div class="bd-resumen-val" style="color:var(--bd-pos)">${fmtKardex(t.compras, 2)}</div>
        </div>
        <div class="bd-resumen-item">
            <div class="bd-resumen-label">- MERMA</div>
            <div class="bd-resumen-val" style="color:var(--bd-neg)">${fmtKardex(t.merma, 2)}</div>
        </div>
        <div class="bd-resumen-item">
            <div class="bd-resumen-label">- INV. FINAL</div>
            <div class="bd-resumen-val" style="color:var(--bd-final)">${fmtKardex(t.inv_final, 2)}</div>
        </div>
        <div class="bd-resumen-item" style="background:rgba(81,184,172,0.03)">
            <div class="bd-resumen-label">Consumo Teórico (Ventas)</div>
            <div class="bd-resumen-val" style="color:var(--bd-neutral)">${fmtKardex(res.consumo_teorico, 2)}</div>
        </div>
        <div class="bd-resumen-item" style="background:rgba(14,84,76,0.03)">
            <div class="bd-resumen-label">Consumo Real (Kardex)</div>
            <div class="bd-resumen-val" style="color:#0E544C">${fmtKardex(res.consumo_real, 2)}</div>
        </div>
    `;
    bdResumen.classList.remove('d-none');

    // Chart
    renderChartKardex(res);
}

function renderChartKardex(res, stockMinVal, stockMaxFinalVal) {
    const regs = res.registros || [];
    const t = res.totales_tipo;
    const invCorte      = t.inv_inicial || 0;       // Inventario de la semana de corte
    const invFin        = t.inv_final   || 0;
    const semCorte      = res.semana_corte;
    const pivotDate     = res.fecha_inicio_corte;  // Primer día de la semana de corte
    const invIniRango   = res.inv_inicial_rango ?? null;  // Inicial real del rango completo
    const semAntRango   = res.semana_ant_rango   || 0;
    const consTeoDiario = res.consumo_teorico_diario || {};
    const puntosDomingo = res.puntos_domingo  || {};
    const fmtKardex = (v, d = 4) => v === null || v === undefined ? '—' : parseFloat(v).toLocaleString('es', { minimumFractionDigits: d, maximumFractionDigits: d });

    // ── Construir lista de días del rango completo ──────────────────────
    const start = new Date(res.fecha_inicio + 'T12:00:00');
    const end   = new Date(res.fecha_fin   + 'T12:00:00');
    const allDays = [];
    let curr = new Date(start);
    while (curr <= end) {
        allDays.push(curr.toISOString().split('T')[0]);
        curr.setDate(curr.getDate() + 1);
    }

    // ── Extender allDays hasta fecha objetivo de pronóstico (si aplica) ──
    const fechaObjetivoPronostico = ($('#kardexFechaPronostico').val() || '').trim();
    // Guardar el límite del rango real ANTES de extender (el Kardex solo usa hasta aquí)
    const originalRangeLen = allDays.length;
    if (fechaObjetivoPronostico && fechaObjetivoPronostico > allDays[allDays.length - 1]) {
        const endExt = new Date(fechaObjetivoPronostico + 'T12:00:00');
        let extCurr = new Date(allDays[allDays.length - 1] + 'T12:00:00');
        extCurr.setDate(extCurr.getDate() + 1);
        while (extCurr <= endExt) {
            allDays.push(extCurr.toISOString().split('T')[0]);
            extCurr.setDate(extCurr.getDate() + 1);
        }
    }

    // ── Movimientos por fecha (merma negativa, resto positivo) ──────────
    const movsPorFecha = {};
    regs.forEach(r => {
        if (r.tipo === 'inv_inicial' || r.tipo === 'inv_final') return;
        if (!movsPorFecha[r.fecha]) movsPorFecha[r.fecha] = 0;
        let val = r.qty_base;
        if (r.tipo === 'merma') val = -val;
        movsPorFecha[r.fecha] += val;
    });

    // ── Índice del pivot (primer día de semana de corte) ─────────────────
    const pivotIdx = pivotDate ? allDays.indexOf(pivotDate) : 0;
    const pIdx = pivotIdx >= 0 ? pivotIdx : 0;

    // ── Cálculo bidireccional ─────────────────────────────────────────────
    // stockTeoData[i] = balance al FINAL del día i
    // invCorte = balance al INICIO del primer día del corte
    //          = balance al FINAL del día (pIdx - 1)
    const stockTeoData = new Array(allDays.length).fill(null);

    // Hacia adelante: pIdx → fin del rango original (el Kardex no se extiende a días de pronóstico)
    let balFwd = invCorte;
    for (let i = pIdx; i < originalRangeLen; i++) {
        const mov  = movsPorFecha[allDays[i]] || 0;
        const cTeo = consTeoDiario[allDays[i]] || 0;
        balFwd = balFwd + mov - cTeo;
        stockTeoData[i] = balFwd;
    }

    // Hacia atrás: (pIdx - 1) → 0
    // end_of_day[i] = end_of_day[i+1] - mov[i+1] + cTeo[i+1]
    // end_of_day[pIdx - 1] = invCorte (inicio del pivot = fin del día anterior)
    if (pIdx > 0) {
        let balBwd = invCorte;
        stockTeoData[pIdx - 1] = balBwd;
        for (let i = pIdx - 2; i >= 0; i--) {
            const mov  = movsPorFecha[allDays[i + 1]] || 0;
            const cTeo = consTeoDiario[allDays[i + 1]] || 0;
            balBwd = balBwd - mov + cTeo;
            stockTeoData[i] = balBwd;
        }
    }

    // ── Labels ────────────────────────────────────────────────────────────
    const labels = allDays.map(day => {
        const dObj = new Date(day + 'T12:00:00');
        return dObj.toLocaleDateString('es-ES', { weekday: 'short', day: 'numeric', month: 'short' });
    });

    // ── Dataset: Marcador del corte (triángulo naranja en pIdx) ──────────
    // Lo colocamos en pIdx - 1 si existe (= el valor invCorte justo antes del pivot)
    // o en pIdx como primer punto del forward si pIdx = 0
    const corteMarkerIdx = pIdx > 0 ? pIdx - 1 : pIdx;
    const corteMarker = new Array(labels.length).fill(null);
    corteMarker[corteMarkerIdx] = invCorte;

    // ── Dataset: Inventario físico de semanas (scatter rojo) ────────────
    const domingoData = allDays.map(day => {
        const v = puntosDomingo[day];
        return (v !== undefined && v !== null) ? v : null;
    });

    // ── Dataset: Inventario físico FINAL (en el último día del rango real, no el extendido) ─
    const realFinalPoint = new Array(allDays.length).fill(null);
    realFinalPoint[originalRangeLen - 1] = invFin;

    // ── Dataset: Inventario real al INICIO del rango (estrella verde en pos 0) ─
    const invIniRangoData = new Array(allDays.length).fill(null);
    if (invIniRango !== null) invIniRangoData[0] = invIniRango;

    // ── Actualizar tag de corte en header del Kardex ──────────────────────
    const corteTag = document.getElementById('bdKardexCorteTag');
    if (corteTag) corteTag.textContent = `✂ Corte S${semCorte}: ${fmtKardex(invCorte, 2)}  |  Ini Rango S${semAntRango}: ${fmtKardex(invIniRango, 2)}`;

    // ── Construir datasets ────────────────────────────────────────────────
    const ctx = document.getElementById('existenciaChart').getContext('2d');
    if (chartKardexExistencia) chartKardexExistencia.destroy();

    const datasets = [
        {
            label: 'Stock Teórico (Ventas + Kardex)',
            data: stockTeoData,
            borderColor: '#51B8AC',
            backgroundColor: 'rgba(81,184,172,0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.3,
            pointRadius: 2,
            pointBackgroundColor: '#fff',
        },
        {
            label: `Corte S${semCorte} (${fmtKardex(invCorte, 2)})`,
            data: corteMarker,
            borderColor: '#f39c12',
            backgroundColor: '#f39c12',
            pointRadius: 11,
            pointHoverRadius: 13,
            pointStyle: 'triangle',
            showLine: false,
        },
        {
            label: 'Inv. Físico Conteo',
            data: domingoData,
            borderColor: '#e74c3c',
            backgroundColor: '#e74c3c',
            pointRadius: 7,
            pointHoverRadius: 9,
            pointStyle: 'rectRot',
            showLine: false,
        },
        {
            label: 'Inv. Final S' + res.semana_ant,
            data: realFinalPoint,
            borderColor: '#8e44ad',
            backgroundColor: '#8e44ad',
            pointRadius: 9,
            pointHoverRadius: 11,
            pointStyle: 'rectRot',
            showLine: false,
        },
        {
            label: `Ini Rango S${semAntRango} (${fmtKardex(invIniRango, 2)})`,
            data: invIniRangoData,
            borderColor: '#27ae60',
            backgroundColor: '#27ae60',
            pointRadius: 10,
            pointHoverRadius: 12,
            pointStyle: 'star',
            showLine: false,
        },
    ];

    const n = labels.length;
    if (stockMinVal !== null && stockMinVal !== undefined) {
        datasets.push({
            label: 'Stock Mínimo *',
            data: new Array(n).fill(stockMinVal),
            borderColor: '#f9a825',
            backgroundColor: 'transparent',
            borderWidth: 2,
            borderDash: [6, 4],
            pointRadius: 0,
            fill: false,
            tension: 0,
        });
    }
    if (stockMaxFinalVal !== null && stockMaxFinalVal !== undefined) {
        datasets.push({
            label: 'Stock Máx Final *',
            data: new Array(n).fill(stockMaxFinalVal),
            borderColor: '#6d597a',
            backgroundColor: 'transparent',
            borderWidth: 2,
            borderDash: [6, 4],
            pointRadius: 0,
            fill: false,
            tension: 0,
        });
    }

    // ── Pronóstico de consumo (línea adicional, no modifica nada existente) ──
    if (fechaObjetivoPronostico) {
        // Calcular promedios de consumo teórico usando TODO el rango analizado (semDesde→semHasta),
        // sin filtrar por pivotDate. El punto de corte solo ancla el inventario; no afecta
        // al consumo promedio. Esto alinea con prom_consumo / 7 de pedido_sugerido.
        const _cntDow = [0,0,0,0,0,0,0];
        const _sumDow = [0,0,0,0,0,0,0];
        let _totalCons = 0;
        const allDates = Object.keys(consTeoDiario);
        allDates.forEach(f => {
            const v = consTeoDiario[f] ?? 0;
            if (v > 0) {
                const dow = new Date(f + 'T12:00:00').getDay();
                _sumDow[dow] += v;
                _cntDow[dow]++;
            }
            _totalCons += v;
        });
        // Denominador = días calendario totales del rango (= nSemanas × 7),
        // igual que prom_consumo / 7 en calcular_v2.
        const _totalDias = allDates.length > 0 ? allDates.length : 1;
        const _promDiario = _totalCons / _totalDias;
        const _promDow = _sumDow.map((s, i) => _cntDow[i] > 0 ? s / _cntDow[i] : _promDiario);

        // Consumo SIEMPRE proyectado (estimado por DOW histórico) — nunca usa el real.
        // Esto es correcto: el pronóstico debe mostrar qué esperamos consumir,
        // no lo que ya consumimos (eso ya lo muestra la línea de Stock Teórico).
        const _getConsProy = (fechaStr) => {
            const dow = new Date(fechaStr + 'T12:00:00').getDay();
            const pDow = _promDow[dow] > 0 ? _promDow[dow] : _promDiario;
            return 0.65 * pDow + 0.35 * _promDiario;
        };

        // Construir línea de pronóstico desde la semana de corte:
        //   - Consumo: SIEMPRE proyectado (nunca el real)
        //   - Otros movimientos (despacho, compras, ajustes, merma): usa los REALES
        //     del kardex cuando ya existen (no hay razón para ignorarlos — son hechos).
        const forecastData = new Array(allDays.length).fill(null);
        // Ancla visual: mismo punto que el triángulo de corte
        forecastData[corteMarkerIdx] = invCorte;
        // Avanzar desde pIdx aplicando movimientos reales + consumo proyectado
        let _balFc = invCorte;
        for (let i = pIdx; i < allDays.length; i++) {
            if (allDays[i] > fechaObjetivoPronostico) break;
            // Movimientos reales del kardex (despachos +, compras +, ajustes +/-, mermas -)
            // ya están procesados en movsPorFecha con su signo correcto.
            const movReal = movsPorFecha[allDays[i]] || 0;
            // Consumo proyectado (nunca el real)
            const consProy = _getConsProy(allDays[i]);
            _balFc = _balFc + movReal - consProy;
            forecastData[i] = _balFc;
        }

        // Punto final destacado en la fecha objetivo
        const _idxObj = allDays.indexOf(fechaObjetivoPronostico);
        const _valObj = _idxObj >= 0 ? forecastData[_idxObj] : null;
        const _finalPoint = new Array(allDays.length).fill(null);
        if (_idxObj >= 0 && _valObj !== null) _finalPoint[_idxObj] = _valObj;

        datasets.push({
            label: `Pronóstico → ${fechaObjetivoPronostico}`,
            data: forecastData,
            borderColor: '#8e44ad',
            backgroundColor: 'rgba(142,68,173,0.06)',
            borderWidth: 2.5,
            borderDash: [10, 5],
            fill: false,
            tension: 0.2,
            pointRadius: 2,
            pointBackgroundColor: '#8e44ad',
            spanGaps: false,
        });

        if (_valObj !== null) {
            datasets.push({
                label: `Al ${fechaObjetivoPronostico}: ${fmtKardex(_valObj, 2)}`,
                data: _finalPoint,
                borderColor: '#8e44ad',
                backgroundColor: '#8e44ad',
                pointRadius: 11,
                pointHoverRadius: 13,
                pointStyle: 'crossRot',
                showLine: false,
            });
        }
    }

    chartKardexExistencia = new Chart(ctx, {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true, position: 'top', labels: { boxWidth: 10, font: { size: 9, weight: 'bold' }, padding: 15 } },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function (context) {
                            if (context.raw === null) return null;
                            let label = context.dataset.label || '';
                            if (label) label += ': ';
                            label += fmtKardex(context.raw, 2);
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: { beginAtZero: false, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 10 } } },
                x: { grid: { display: false }, ticks: { font: { size: 9 }, maxRotation: 45, minRotation: 45 } }
            }
        }
    });
    document.getElementById('bdChartWrap').classList.remove('d-none');
}

function cargarStockMinMaxKardex(idPP, semAnalisis) {
    const KARDEX_AJAX = 'ajax/';
    const semActual = parseInt($('#semanaActualNum').text()) || 0;
    const sucursalesSelec = SucPicker.getSelected();
    const primeraSuc = sucursalesSelec.length > 0 ? sucursalesSelec[0] : '';
    const fmtKardex = (v, d = 4) => v === null || v === undefined ? '—' : parseFloat(v).toLocaleString('es', { minimumFractionDigits: d, maximumFractionDigits: d });

    const fd = new FormData();
    fd.append('id_pp', idPP);
    fd.append('sem_analisis', semAnalisis);
    fd.append('sem_actual', semActual);
    if (primeraSuc) fd.append('cod_sucursal', primeraSuc);

    fetch(KARDEX_AJAX + 'balance_inventario_get_stock_minmax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (!res.ok || (res.stock_minimo === null && res.stock_max_final === null)) return;

            if (chartKardexExistencia) {
                const n = chartKardexExistencia.data.labels.length;
                chartKardexExistencia.data.datasets = chartKardexExistencia.data.datasets
                    .filter(ds => !ds.label.includes('Stock Mín') && !ds.label.includes('Stock Máx Final'));

                if (res.stock_minimo !== null) {
                    chartKardexExistencia.data.datasets.push({
                        label: 'Stock Mínimo *',
                        data: new Array(n).fill(res.stock_minimo),
                        borderColor: '#f9a825',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        borderDash: [6, 4],
                        pointRadius: 0,
                        fill: false,
                        tension: 0,
                    });
                }
                if (res.stock_max_final !== null) {
                    chartKardexExistencia.data.datasets.push({
                        label: 'Stock Máx Final *',
                        data: new Array(n).fill(res.stock_max_final),
                        borderColor: '#6d597a',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        borderDash: [6, 4],
                        pointRadius: 0,
                        fill: false,
                        tension: 0,
                    });
                }
                chartKardexExistencia.update();

                const badge = document.getElementById('bdChartStockBadge');
                if (badge) {
                    let parts = [];
                    if (res.stock_minimo !== null) parts.push('Mín: ' + fmtKardex(res.stock_minimo, 2));
                    if (res.stock_max_final !== null) parts.push('Máx: ' + fmtKardex(res.stock_max_final, 2));
                    if (parts.length) {
                        badge.textContent = '* ' + parts.join(' · ');
                        badge.style.display = '';
                    }
                }
            }

            const notaEl = document.getElementById('bdChartNota');
            const notaTxt = document.getElementById('bdChartNotaText');
            if (notaEl && notaTxt && res.retrocedido) {
                notaTxt.textContent = `* Las líneas de Stock Mín y Máx se calculan con las semanas ${res.sem_desde}–${res.sem_hasta} ` +
                    `(se retrocedió 1 semana desde la semana actual ${res.sem_actual} para usar solo semanas con datos completos de 7 días).`;
                notaEl.style.display = '';
            } else if (notaEl && notaTxt) {
                notaTxt.textContent = `* Stock Mín y Máx calculados con semanas ${res.sem_desde}–${res.sem_hasta} (últimas 5 semanas completas).`;
                notaEl.style.display = '';
            }
        })
        .catch(() => { /* silencioso */ });
}
