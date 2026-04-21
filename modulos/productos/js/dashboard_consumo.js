/* ============================================================
   DASHBOARD CONSUMO DE INSUMOS — JavaScript
   modulos/productos/js/dashboard_consumo.js
   ============================================================ */

'use strict';

/* ── Referencias DOM ─────────────────────────────────────── */
const $semDesde    = $('#filtroSemanaDesde');
const $semHasta    = $('#filtroSemanaHasta');
const $sucursales  = $('#filtroSucursales');
const $btnAplicar  = $('#btnAplicar');
const $btnExportar = $('#btnExportar');


const $panelInicial = $('#panelInicial');
const $panelLoader  = $('#panelLoader');
const $panelDatos   = $('#panelDatos');

/* ── Estado global ───────────────────────────────────────── */
let datosActuales  = null;   // Respuesta completa del AJAX de datos
let chartTendencia = null;   // Instancia Chart.js activa
let modoChart      = 'bar';  // 'bar' | 'line'

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
    let _opciones    = []; // { value, label }
    let _seleccionados = new Set();

    const $trigger   = $('#dcSucTrigger');
    const $dropdown  = $('#dcSucDropdown');
    const $list      = $('#dcSucList');
    const $search    = $('#dcSucSearch');
    const $pills     = $('#dcSucPills');
    const $ph        = $('#dcSucPlaceholder');
    const $badge     = $('#dcSucCountBadge');
    const $clear     = $('#dcSucClear');
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
            $hiddenSel.find(`option[value="${v}"]`).prop('selected', true);
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
        const extra   = arr.length - MAX_PILLS;

        $pills.empty();
        visible.forEach(v => {
            const lbl = _opciones.find(o => o.value === v)?.label || v;
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
            const sel = _seleccionados.has(o.value);
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
        if (_seleccionados.has(value)) {
            _seleccionados.delete(value);
        } else {
            _seleccionados.add(value);
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
            const v = $(this).data('v');
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
            _seleccionados = new Set(_opciones.map(o => o.value));
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
            toggleItem($(this).data('v'));
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
}


/* ── Ligar eventos ────────────────────────────────────────── */
function bindEventos() {
    $btnAplicar.on('click', cargarDatos);

    // Modo gráfico
    $('#chartModoBarra, #chartModoLinea').on('click', function () {
        modoChart = $(this).data('modo');
        $('#chartModoBarra, #chartModoLinea').removeClass('active');
        $(this).addClass('active');
        if (datosActuales && !$('#chartWrap').hasClass('d-none')) {
            renderGrafico(datosActuales);
        }
    });

    // Cambiar insumo en el panel de análisis → re-renderizar gráfico + KPIs
    $('#chartInsumoSel').on('change', function () {
        if (!datosActuales) return;
        const idSel = parseInt($(this).val()) || 0;
        if (idSel > 0) {
            const item = datosActuales.consumo.find(c => c.id == idSel);
            $('#chartPlaceholder').addClass('d-none');
            $('#chartWrap').removeClass('d-none');
            renderKPIs(datosActuales, item);
            renderGrafico(datosActuales);
        } else {
            $('#chartPlaceholder').removeClass('d-none');
            $('#chartWrap').addClass('d-none');
            if (chartTendencia) { chartTendencia.destroy(); chartTendencia = null; }
            $('#tituloTendencia').html('<i class="fas fa-chart-line me-2"></i>Análisis de Insumo');
            renderKPIs(datosActuales, null);
        }
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



    // Panel alertas: toggle cuerpo (evitar propagar si click en sigma btns)
    $(document).on('click', '#alertasHeader', function (e) {
        if ($(e.target).closest('.dc-sigma-btns, .dc-sigma-btn').length) return;
        $('#alertasBody').toggleClass('collapsed');
        $('#alertasToggle').toggleClass('rotated');
    });

    // Botones sigma: recalcular alertas en tiempo real
    $(document).on('click', '.dc-sigma-btn', function (e) {
        e.stopPropagation();
        kSigmaActual = parseFloat($(this).data('k'));
        $('.dc-sigma-btn').removeClass('active');
        $(this).addClass('active');
        if (datosActuales) renderPanelAlertas(datosActuales, kSigmaActual);
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

            // Pre-cargar rango por defecto: 4 semanas hasta la actual
            const semHasta = sa.numero_semana;
            const semDesde = Math.max(1, semHasta - 3);
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
    if (estado === 'loader')  $panelLoader.removeClass('d-none');
    if (estado === 'datos')   $panelDatos.removeClass('d-none');
}

/* ════════════════════════════════════════════════════════════
   RENDER COMPLETO DEL DASHBOARD
   ════════════════════════════════════════════════════════════ */
function renderDashboard(data) {
    renderPanelAlertas(data, kSigmaActual);  // alertas de sobreconsumo (primero)
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
            $('#kpiPicoVal').text(`Sem. ${item.semana_pico_num}`);
            $('#kpiPicoSub').text(semPico
                ? `${formatFecha(semPico.fecha_inicio)}–${formatFecha(semPico.fecha_fin)}`
                : '');
        } else {
            $('#kpiPicoVal').text('—');
            $('#kpiPicoSub').text('');
        }

        $('#kpiProyVal').text(formatNum(item.proyeccion_4sem));
        $('#kpiProySub').text(`Prom. ${formatNum(item.prom_semana)} ${escHtml(item.unidad)}/sem`);

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
    { border: '#0E544C', bg: 'rgba(14,84,76,.25)'    },
    { border: '#2980b9', bg: 'rgba(41,128,185,.25)'  },
    { border: '#8e44ad', bg: 'rgba(142,68,173,.25)'  },
    { border: '#e67e22', bg: 'rgba(230,126,34,.25)'  },
    { border: '#c0392b', bg: 'rgba(192,57,43,.25)'   },
    { border: '#16a085', bg: 'rgba(22,160,133,.25)'  },
    { border: '#d35400', bg: 'rgba(211,84,0,.25)'    },
    { border: '#27ae60', bg: 'rgba(39,174,96,.25)'   },
    { border: '#2c3e50', bg: 'rgba(44,62,80,.25)'    },
    { border: '#f39c12', bg: 'rgba(243,156,18,.25)'  },
    { border: '#1abc9c', bg: 'rgba(26,188,156,.25)'  },
    { border: '#9b59b6', bg: 'rgba(155,89,182,.25)'  },
    { border: '#e74c3c', bg: 'rgba(231,76,60,.25)'   },
    { border: '#3498db', bg: 'rgba(52,152,219,.25)'  },
];

/* ── Vista del gráfico: 'total' | 'por_sucursal' ─────────── */
let modoVistaSuc = 'total';
let kSigmaActual  = 1.5;   // Factor σ activo para alertas de sobreconsumo

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

    const labels      = data.semanas.map(s => `Sem ${s.numero_semana}`);
    const semanasNros = data.semanas.map(s => s.numero_semana);
    const sucursales  = data.sucursales || [];
    const nombres     = data.sucursales_nombres || {};
    const prom        = item.prom_semana || 0;

    // ── Excluir semana en curso del promedio y proyección ────────────────────
    const semanaActual       = parseInt($('#semanaActualNum').text()) || 0;
    const esSemActualEnRango = semanaActual > 0 && semanasNros.includes(semanaActual);
    // Semanas completas: excluir la semana en curso si está dentro del rango
    const semanasCalc = esSemActualEnRango
        ? semanasNros.filter(n => n !== semanaActual)
        : semanasNros;

    // Promedio recalculado sin semana en curso
    let promCalc = prom;
    if (semanasCalc.length > 0) {
        const valsCalc = semanasCalc.map(n => item.por_semana[n] || 0);
        const valsPos  = valsCalc.filter(v => v > 0);
        promCalc = valsPos.length > 0 ? valsPos.reduce((a, b) => a + b, 0) / valsPos.length : prom;
    }

    // Proyección con regresión lineal (mínimos cuadrados) sobre semanas completas
    const ultimaSem = semanasNros[semanasNros.length - 1];
    let proyW1 = round2(promCalc), proyW2 = round2(promCalc), proyW3 = round2(promCalc);
    let regSlope = 0, regIntercept = promCalc;  // fallback: línea plana en promCalc
    if (semanasCalc.length >= 2) {
        const xV    = semanasCalc;
        const yV    = semanasCalc.map(n => item.por_semana[n] || 0);
        const n_    = xV.length;
        const sumX  = xV.reduce((a, b) => a + b, 0);
        const sumY  = yV.reduce((a, b) => a + b, 0);
        const sumXY = xV.reduce((acc, x, i) => acc + x * yV[i], 0);
        const sumX2 = xV.reduce((acc, x)    => acc + x * x, 0);
        const denom = n_ * sumX2 - sumX * sumX;
        if (Math.abs(denom) > 0.001) {
            regSlope     = (n_ * sumXY - sumX * sumY) / denom;
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

    // ── Mostrar/ocultar el toggle de vista
    renderToggleVistaSuc(hayDesglose, item, data);

    // ── Construir datasets
    let datasets = [];

    // Variable que indica si el modo actual apila barras por sucursal
    const esBarraSuc = hayDesglose && modoVistaSuc === 'por_sucursal' && modoChart === 'bar';
    const esLineaSuc = hayDesglose && modoVistaSuc === 'por_sucursal' && modoChart === 'line';

    if (hayDesglose && modoVistaSuc === 'por_sucursal') {
        // ━━ Modo Multi-Sucursal ━━

        // Sucursales ordenadas por consumo total desc
        const sucConTotal = sucursales.map((suc, i) => {
            const totalSuc = semanasNros.reduce((acc, n) => acc + (item.desglose_semxsuc[n]?.[suc] || 0), 0);
            return { suc, nombre: nombres[suc] || suc, totalSuc, idx: i };
        }).sort((a, b) => b.totalSuc - a.totalSuc);

        sucConTotal.forEach(({ suc, nombre, idx }) => {
            const color  = SUCURSAL_COLORS[idx % SUCURSAL_COLORS.length];
            const valores = semanasNros.map(n => round2(item.desglose_semxsuc[n]?.[suc] || 0));
            const label   = nombre.length > 22 ? nombre.substring(0, 20) + '…' : nombre;

            // En modo BARRA: color sólido (~75% opacidad) para cada segmento apilado
            const bgColor = esBarraSuc
                ? color.border.replace('#', 'rgba(').replace(/([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})/i,
                    (_, r, g, b) => `${parseInt(r,16)},${parseInt(g,16)},${parseInt(b,16)},0.75)`)
                : color.bg;

            datasets.push({
                label,
                data: valores,
                backgroundColor: bgColor,
                borderColor:     color.border,
                borderWidth:     esBarraSuc ? 0.5 : 2,
                tension:         0.3,
                fill:            false,
                pointRadius:     esLineaSuc ? 3 : undefined,
                pointBackgroundColor: color.border,
                // stack solo en modo barra — Chart.js apila datasets con el mismo stack id
                stack:           esBarraSuc ? 'suc' : undefined,
            });
        });

        // En modo LÍNEA por sucursal: NO se agrega línea TOTAL, Promedio ni Proyección
        // Solo se muestran las líneas individuales de cada sucursal
        if (!esLineaSuc) {
            // Promedio punteado — solo en modo barra por sucursal
            datasets.push({
                label:       `Prom./sem (sem. completas): ${formatNum(round2(promCalc))} ${escHtml(item.unidad)}`,
                data:        semanasNros.map(n => (esSemActualEnRango && n === semanaActual) ? null : round2(promCalc)),
                borderColor: '#e67e22',
                borderWidth: 1.5,
                borderDash:  [5, 4],
                pointRadius: 0,
                fill:        false,
                tension:     0,
                type:        'line',
                order:       1,
            });
        }

    } else {
        // ━━ Modo Total (1 sucursal o modo total seleccionado) ━━
        const valores = semanasNros.map(n => round2(item.por_semana[n] || 0));
        datasets = [
            {
                label:           item.nombre.length > 35 ? item.nombre.substring(0, 33) + '…' : item.nombre,
                data:            valores,
                backgroundColor: 'rgba(81,184,172,.35)',
                borderColor:     '#0E544C',
                borderWidth:     2,
                tension:         0.3,
                fill:            modoChart === 'line',
                pointRadius:     4,
                pointBackgroundColor: '#0E544C',
            },
            {
                label:       `Prom./sem (sem. completas): ${formatNum(round2(promCalc))} ${escHtml(item.unidad)}`,
                data:        semanasNros.map(n => (esSemActualEnRango && n === semanaActual) ? null : round2(promCalc)),
                borderColor: '#e67e22',
                borderWidth: 1.5,
                borderDash:  [5, 4],
                pointRadius: 0,
                fill:        false,
                tension:     0,
                type:        'line',
            },
        ];
    }

    // ── Proyección 3 semanas — solo en modo Total o Barra por sucursal ─────────
    // En modo Línea por sucursal (esLineaSuc) NO se muestran semanas proyectadas
    const labelsExtended = esLineaSuc ? labels : [
        ...labels,
        `Proy. ${ultimaSem + 1}`,
        `Proy. ${ultimaSem + 2}`,
        `Proy. ${ultimaSem + 3}`,
    ];
    const nSems = semanasNros.length;

    if (!esLineaSuc) {
        // Pad todos los datasets históricos con null para las 3 semanas proyectadas
        datasets.forEach(ds => {
            if (!Array.isArray(ds.data)) return;
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
            label:               `↗ Proyección`,
            data:                proyData,
            borderColor:         '#f39c12',
            backgroundColor:     'rgba(243,156,18,.12)',
            borderWidth:         2.5,
            borderDash:          [6, 4],
            pointRadius:         proyPointR,
            pointStyle:          'triangle',
            pointBackgroundColor: '#f39c12',
            pointBorderColor:    '#fff',
            pointBorderWidth:    1.5,
            fill:                false,
            tension:             0,
            type:                'line',
            spanGaps:            true,   // conecta sem. actual con las semanas proyectadas
            order:               0,
        });
    }

    if (chartTendencia) { chartTendencia.destroy(); chartTendencia = null; }

    const ctx = document.getElementById('chartTendencia').getContext('2d');

    // Leyenda siempre en la parte inferior para no comprimir el área del gráfico
    const numSucursales = hayDesglose ? sucursales.length : 1;
    const legendPos = numSucursales > 8 ? 'left' : 'bottom';

    chartTendencia = new Chart(ctx, {
        type: modoChart,
        data: { labels: labelsExtended, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: legendPos,
                    labels: {
                        font: { size: 10, family: 'Calibri' },
                        padding: 10,
                        boxWidth: 12,
                        // filtrar el dataset "Prom" de la leyenda si hay muchas series
                        filter: (item) => numSucursales > 6
                            ? !item.text.startsWith('Prom.')
                            : true,
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
}

/* ── Toggle Vista Sucursal ───────────────────────────────────
   Inyecta/actualiza un par de botones "Total / Por Sucursal"
   al lado de los chips Barras/Línea cuando hay desglose
   ──────────────────────────────────────────────────────────── */
function renderToggleVistaSuc(hayDesglose, item, data) {
    const $existente = $('#toggleVistaSuc');

    if (!hayDesglose) {
        $existente.remove();
        modoVistaSuc = 'total';
        return;
    }

    if ($existente.length === 0) {
        // Insertar antes de los chips de modo
        const html = `
            <div id="toggleVistaSuc" class="d-flex gap-1 align-items-center ms-2">
                <span style="font-size:.72rem;color:#889;font-weight:600">Vista:</span>
                <button class="btn btn-xs dc-chip ${modoVistaSuc === 'total' ? 'active' : ''}"
                    id="btnVistaTotalSuc" title="Ver consumo acumulado total">
                    <i class="fas fa-sigma me-1"></i>Total
                </button>
                <button class="btn btn-xs dc-chip ${modoVistaSuc === 'por_sucursal' ? 'active' : ''}"
                    id="btnVistaSucursal" title="Ver consumo desglosado por sucursal">
                    <i class="fas fa-store me-1"></i>Por Sucursal
                </button>
            </div>`;
        $('#chartModoBarra').closest('.d-flex').append(html);

        $(document).on('click', '#btnVistaTotalSuc', function () {
            modoVistaSuc = 'total';
            $('#btnVistaTotalSuc').addClass('active');
            $('#btnVistaSucursal').removeClass('active');
            renderGrafico(data);
        });
        $(document).on('click', '#btnVistaSucursal', function () {
            modoVistaSuc = 'por_sucursal';
            $('#btnVistaSucursal').addClass('active');
            $('#btnVistaTotalSuc').removeClass('active');
            renderGrafico(data);
        });
    } else {
        // Actualizar estado activo
        $('#btnVistaTotalSuc').toggleClass('active', modoVistaSuc === 'total');
        $('#btnVistaSucursal').toggleClass('active', modoVistaSuc === 'por_sucursal');
    }
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
                <td>${escHtml(item.unidad)}</td>
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

    if (data.consumo.length === 0) {
        html = `<tr><td colspan="9" class="text-center text-muted py-4">Sin datos de proyección.</td></tr>`;
    } else {
        data.consumo.forEach(item => {
            const trendIcon = item.tendencia === 'up'
                ? `<span class="dc-trend-up"><i class="fas fa-arrow-up me-1"></i>Creciente</span>`
                : item.tendencia === 'down'
                    ? `<span class="dc-trend-down"><i class="fas fa-arrow-down me-1"></i>Decreciente</span>`
                    : `<span class="dc-trend-flat"><i class="fas fa-minus me-1"></i>Estable</span>`;

            html += `
            <tr>
                <td>
                    <div class="fw-bold" style="font-size:.82rem">${escHtml(item.nombre)}</div>
                    <div class="text-muted" style="font-size:.72rem">${escHtml(item.maestro)}</div>
                </td>
                <td>${escHtml(item.unidad)}</td>
                <td class="text-end">${formatNum(item.prom_semana)}</td>
                <td class="text-end fw-bold" style="color:#0E544C">${formatNum(item.proyeccion_4sem)}</td>
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

    $sel.empty().append('<option value="">— Selecciona un insumo —</option>');
    data.consumo.forEach(item => {
        const tipoLabel = item.es_global ? ' [Global]' : '';
        $sel.append(`<option value="${item.id}">${escHtml(item.nombre)}${tipoLabel}</option>`);
    });

    // Si ya había una selección válida, restaurarla y re-renderizar
    if (prevVal && $sel.find(`option[value="${prevVal}"]`).length) {
        $sel.val(prevVal).trigger('change');
    } else {
        // Estado inicial: mostrar placeholder
        $('#chartPlaceholder').removeClass('d-none');
        $('#chartWrap').addClass('d-none');
        if (chartTendencia) { chartTendencia.destroy(); chartTendencia = null; }
        $('#tituloTendencia').html('<i class="fas fa-chart-line me-2"></i>Tendencia');
    }
}

/* ── Modal Desglose ──────────────────────────────────────── */
window.mostrarDesglose = function (idInsumo) {
    if (!datosActuales) return;
    const item = datosActuales.consumo.find(c => c.id == idInsumo);
    if (!item) return;

    const semanas    = datosActuales.semanas;
    const sucursales = datosActuales.sucursales;

    let theadHtml = '<tr><th style="min-width:100px">Semana</th>';
    sucursales.forEach(s => { theadHtml += `<th>${escHtml(s)}</th>`; });
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
        const nDec  = filas.filter(f => f.genera_decimal).length;

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

        const infoHtml = `<div class="mb-2 d-flex gap-3" style="font-size:.8rem">
            <span><strong>Presentación:</strong> ${escHtml(pp.nombre)}</span>
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
    if (tabActivo === 'tabSinMapeoBtn')   modo = 'sin_mapeo';

    const payload = {
        consumo:           datosActuales.consumo,
        semanas:           datosActuales.semanas,
        sin_mapeo:         datosActuales.sin_mapeo,
        sucursales:        datosActuales.sucursales        || [],
        sucursales_nombres: datosActuales.sucursales_nombres || {},
        sem_desde:         datosActuales._semDesde,
        sem_hasta:         datosActuales._semHasta,
        modo,
    };

    // Crear form oculto para descargar
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'ajax/dashboard_consumo_exportar.php';
    form.style.display = 'none';

    const input = document.createElement('input');
    input.type  = 'hidden';
    input.name  = 'not_used'; // El endpoint lee php://input directamente
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
        const url  = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href     = url;
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
    const alertas     = [];
    const nombres     = data.sucursales_nombres || {};
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
                        insumo:    item.nombre,
                        unidad:    item.unidad,
                        local:     nombres[suc] || suc,
                        semana:    n,
                        consumo:   v,
                        mu, sigma, umbral,
                        zScore:    (v - mu) / sigma,
                        pctExceso: Math.round((v - mu) / mu * 100),
                        idInsumo:  item.id,
                    });
                }
            });
        });
    });

    return alertas.sort((a, b) => b.zScore - a.zScore);
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
            `<div class="dc-alertas-vacio"><i class="fas fa-check-circle"></i>Todos los locales operan dentro del rango normal con umbral ${kSigma}σ</div>`
        );
        return;
    }

    // Restaurar colores de alerta rojo
    $('.dc-alertas-header').css('background', 'linear-gradient(135deg,#c0392b 0%,#e74c3c 100%)');
    $('.dc-alertas-panel').css('border-left-color', '#e74c3c');
    $('#alertasBadge').text(alertas.length).css({ background: '#fff', color: '#c0392b' });
    $('#alertasHint').text(`umbral: μ_local + ${kSigma}σ_local`);

    let filas = '';
    alertas.forEach(a => {
        const zCls = a.zScore >= 2.5 ? 'z-critico' : a.zScore >= 2 ? 'z-alto' : 'z-moderado';
        filas += `
        <tr>
            <td><span class="dc-semana-badge">Sem ${a.semana}</span></td>
            <td style="font-weight:600;font-size:.78rem">${escHtml(a.local)}</td>
            <td>
                <span class="dc-alerta-insumo-pill"
                    onclick="seleccionarInsumoDesdeAlerta(${a.idInsumo})"
                    title="Ver tendencia de ${escHtml(a.insumo)}">
                    ${escHtml(a.insumo)}
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
        <table class="dc-alertas-tabla">
            <thead>
                <tr>
                    <th>Semana</th>
                    <th>Local</th>
                    <th>Insumo</th>
                    <th class="text-end">Consumo Real</th>
                    <th class="text-end">Referencia (μ / σ)</th>
                    <th class="text-end">Z-Score</th>
                    <th class="text-end">% Exceso</th>
                </tr>
            </thead>
            <tbody>${filas}</tbody>
        </table>
    `);
}

/**
 * Selecciona un insumo desde el panel de alertas → activa el gráfico de tendencia.
 */
window.seleccionarInsumoDesdeAlerta = function (idInsumo) {
    $('#chartInsumoSel').val(idInsumo).trigger('change');
    const el = document.getElementById('cardTendencia');
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
};

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
    const meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    return `${d.getDate()}/${meses[d.getMonth()]}/${String(d.getFullYear()).slice(2)}`;
}

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function round2(n) {
    return Math.round(parseFloat(n) * 100) / 100;
}
