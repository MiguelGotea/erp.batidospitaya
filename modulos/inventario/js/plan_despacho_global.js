/* ============================================================
   JS: Plan de Despacho Global
   Ruta: modulos/inventario/js/plan_despacho_global.js
   ============================================================ */
'use strict';

const PDG = {
    AJAX_BASE: 'ajax/',
    CATEGORIAS: {
        A: { nombre: 'Frescos', cls: 'pdg-cat-A', icon: 'bi-apple' },
        B: { nombre: 'Congelados', cls: 'pdg-cat-B', icon: 'bi-snow2' },
        C: { nombre: 'Fresas', cls: 'pdg-cat-C', icon: 'bi-heart-fill' },
        D: { nombre: 'Desechables', cls: 'pdg-cat-D', icon: 'bi-trash3' },
        E: { nombre: 'Fijos', cls: 'pdg-cat-E', icon: 'bi-box-seam' },
        F: { nombre: 'Secos y Preparación', cls: 'pdg-cat-F', icon: 'bi-bucket' },
        G: { nombre: 'Prod. de Mostrador', cls: 'pdg-cat-G', icon: 'bi-shop' },
    },
    DIAS: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
    sucursales: [],
    configCache: {},
};

/* ── Bootstrap tooltip helper ── */
function initTooltips(ctx) {
    $(ctx).find('[data-bs-toggle="tooltip"]').each(function () {
        new bootstrap.Tooltip(this, { trigger: 'hover focus', html: true });
    });
}

/* ── Toast SweetAlert2 ── */
function toastOk(msg) {
    Swal.fire({
        toast: true, position: 'top-end', icon: 'success', title: msg,
        showConfirmButton: false, timer: 2800, timerProgressBar: true
    });
}
function toastErr(msg) {
    Swal.fire({
        toast: true, position: 'top-end', icon: 'error', title: msg,
        showConfirmButton: false, timer: 4000
    });
}

/* ══════════════════════════════════════════════════════════
   RENDER HTML
   ══════════════════════════════════════════════════════════ */

function buildStoreListItem(suc, idx) {
    const active = idx === 0 ? 'active' : '';
    return `<button type="button" class="list-group-item list-group-item-action ${active}" 
                data-cod="${suc.codigo}" id="store-${suc.codigo}">
                <i class="bi bi-building me-2"></i>${suc.nombre}
            </button>`;
}


function buildDiaCheckboxes(cod, cat, selected) {
    return PDG.DIAS.map((d, i) => {
        const uid = `dia-${cod}-${cat}-${i}`;
        const chk = Array.isArray(selected) && selected.includes(i) ? 'checked' : '';
        return `<input type="checkbox" class="pdg-dia-check" id="${uid}"
                    data-idx="${i}" ${chk}>
                <label class="pdg-dia-label" for="${uid}">${d.charAt(0)}</label>`;
    }).join('');
}

function buildCatRow(cod, cat, cfg) {
    const info = PDG.CATEGORIAS[cat];
    const isNSem = !cfg || cfg.tipo_frecuencia !== 'dias_semana';
    const tipo = cfg ? cfg.tipo_frecuencia : 'n_semanas';
    const intervalo = cfg ? cfg.intervalo_semanas : 1;
    const dia = cfg ? cfg.dia_despacho : 1;
    const ancla = cfg && cfg.semana_ancla ? cfg.semana_ancla : '';
    const diasSel = cfg ? (cfg.dias_semana || []) : [];
    const disabled = PUEDE_EDITAR ? '' : 'disabled';
    const showAncla = isNSem && intervalo > 1;

    const rowCls = [
        cat === 'B' ? 'pdg-row-cat-b' : ''
    ].join(' ');

    const anclaTooltip = `Ingresa el número de semana de un despacho real ya ocurrido.<br>
        Ej: si la semana 540 fue el último despacho, escribe 540.<br>
        El sistema calcula: (semana_actual − ancla) % intervalo == 0.`;

    return `<tr class="${rowCls}" data-cat="${cat}">
        <!-- Categoría -->
        <td>
            <div class="d-flex flex-column align-items-start gap-2">
                <span class="pdg-badge-cat ${info.cls}">
                    <i class="bi ${info.icon}"></i>${cat} – ${info.nombre}
                </span>
                ${cat === 'G' ? `<button type="button" class="btn btn-sm btn-outline-success mt-1" onclick="abrirConfigStockG('${cod}', '${escHtml(PDG.sucursales.find(s => s.codigo === cod)?.nombre || '')}')" ${disabled}><i class="bi bi-box-seam me-1"></i>Stock Mín. Registrado</button>` : ''}
            </div>
        </td>
        <!-- Tipo -->
        <td>
            <div class="pdg-tipo-group">
                <label class="pdg-tipo-label">
                    <input type="radio" name="tipo-${cat}" value="n_semanas" class="pdg-tipo-radio me-1" ${isNSem ? 'checked' : ''} ${disabled}>
                    Cada N semanas
                </label>
                <label class="pdg-tipo-label">
                    <input type="radio" name="tipo-${cat}" value="dias_semana" class="pdg-tipo-radio me-1" ${!isNSem ? 'checked' : ''} ${disabled}>
                    Días específicos
                </label>
            </div>
        </td>
        <!-- Frecuencia -->
        <td style="min-width:220px;">
            <div class="pdg-n-semanas-fields ${isNSem ? '' : 'd-none'}">
                <select class="form-select form-select-sm pdg-intervalo" ${disabled}>
                    <option value="1" ${intervalo == 1 ? 'selected' : ''}>Cada semana</option>
                    <option value="2" ${intervalo == 2 ? 'selected' : ''}>Quincenal</option>
                    <option value="3" ${intervalo == 3 ? 'selected' : ''}>Cada 3 semanas</option>
                </select>
            </div>
            <div class="pdg-dias-semana-fields ${!isNSem ? '' : 'd-none'}">
                <div class="pdg-dias-group">
                    ${buildDiaCheckboxes(cod, cat, diasSel)}
                </div>
            </div>
        </td>
        <!-- Día despacho -->
        <td style="min-width:110px;">
            <div class="pdg-n-semanas-fields ${isNSem ? '' : 'd-none'}">
                <select class="form-select form-select-sm pdg-dia-despacho" ${disabled}>
                    ${PDG.DIAS.map((d, i) => `<option value="${i}" ${dia == i ? 'selected' : ''}>${d}</option>`).join('')}
                </select>
            </div>
            <span class="pdg-dias-semana-fields text-muted small ${!isNSem ? '' : 'd-none'}">—</span>
        </td>
        <!-- Semana Ancla -->
        <td style="min-width:130px;">
            <div class="pdg-n-semanas-fields ${isNSem ? '' : 'd-none'}">
                <div class="pdg-ancla-group pdg-ancla-wrap ${showAncla ? '' : 'd-none'}">
                    <input type="number" class="form-control form-control-sm pdg-semana-ancla"
                        value="${ancla}" min="1" placeholder="Ej: 540" ${disabled}>
                    <i class="bi bi-info-circle pdg-info-icon"
                        data-bs-toggle="tooltip" data-bs-html="true"
                        title="${anclaTooltip}"></i>
                </div>
                <span class="pdg-ancla-na ${showAncla ? 'd-none' : ''} text-muted small">N/A</span>
            </div>
            <span class="pdg-dias-semana-fields text-muted small ${!isNSem ? '' : 'd-none'}">—</span>
        </td>
    </tr>`;
}

function buildCongeladorSection(cap) {
    if (!PUEDE_EDITAR) {
        return `<div class="pdg-congelador-card d-none">
            <div class="pdg-congelador-title"><i class="bi bi-snow2"></i>Capacidad Congelador (Cat B)</div>
            <div class="row g-2">
                <div class="col-auto"><strong>${cap.paquetes ?? '—'}</strong> paquetes</div>
                <div class="col">${cap.obs ? `<span class="text-muted small">${cap.obs}</span>` : ''}</div>
            </div>
        </div>`;
    }
    return `<div class="pdg-congelador-card d-none">
        <div class="pdg-congelador-title"><i class="bi bi-snow2"></i>Capacidad Congelador (Categoría B)</div>
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small fw-bold mb-1">Capacidad (paquetes)</label>
                <input type="number" class="form-control form-control-sm" id="capCongelados"
                    value="${cap.paquetes ?? ''}" min="0" placeholder="Ej: 120" style="width:110px;">
            </div>
            <div class="col">
                <label class="form-label small fw-bold mb-1">Observaciones</label>
                <input type="text" class="form-control form-control-sm" id="capCongeladosObs"
                    value="${cap.obs ?? ''}" placeholder="Ej: 2 congeladores de 60 paq c/u" maxlength="200">
            </div>
        </div>
        <p class="small text-muted mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>
            Este valor se guarda al presionar <strong>Guardar</strong> en la fila de <strong>Congelados (B)</strong>.</p>
    </div>`;
}

function buildContent(cod, data) {
    const { plan, capacidad_congelados } = data;
    let rows = Object.keys(PDG.CATEGORIAS).map(cat => buildCatRow(cod, cat, plan[cat] || null)).join('');

    return `<div class="pdg-fade-in">
        <div class="table-responsive">
            <table class="pdg-tabla" id="tabla-${cod}">
                <thead>
                    <tr>
                        <th style="min-width:200px;">Categoría</th>
                        <th style="min-width:150px;">Tipo</th>
                        <th>Frecuencia</th>
                        <th>Día Despacho</th>
                        <th>Sem. Ancla <i class="bi bi-question-circle text-white-50 small"></i></th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>
        ${buildCongeladorSection(capacidad_congelados)}
    </div>`;
}

/* ══════════════════════════════════════════════════════════
   MINI-CALENDARIO (próximas 14 días)
   ══════════════════════════════════════════════════════════ */

function getCurrentWeekNumber() {
    const now = new Date();
    const jan1 = new Date(now.getFullYear(), 0, 1);
    const days = Math.floor((now - jan1) / 86400000);
    return Math.ceil((days + jan1.getDay() + 1) / 7);
}

function renderGlobalCalendar() {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const weekNow = getCurrentWeekNumber();
    const daysLabel = [];
    const datesArr = [];

    for (let i = 0; i < 14; i++) {
        const d = new Date(today);
        d.setDate(today.getDate() + i);
        const dow = (d.getDay() + 6) % 7; // 0=Lun
        daysLabel.push({ dow, label: PDG.DIAS[dow].substring(0, 2), date: d, isToday: i === 0 });
        datesArr.push({ d, dow, weekNum: weekNow + Math.floor(i / 7) });
    }

    // Header días
    let grid = `<div class="pdg-cal-header" style="text-align:right;">Cat</div>`;
    daysLabel.forEach((dl, i) => {
        const sep = dl.dow === 0 && i > 0 ? 'pdg-cal-week-sep' : '';
        const today = dl.isToday ? 'pdg-cal-today' : '';
        grid += `<div class="pdg-cal-header ${sep} ${today}">${dl.label}<br><span style="font-size:.6rem;">${dl.date.getDate()}/${dl.date.getMonth() + 1}</span></div>`;
    });

    // Filas por categoría
    Object.keys(PDG.CATEGORIAS).forEach(cat => {
        const info = PDG.CATEGORIAS[cat];
        grid += `<div class="pdg-cal-cat-label"><span class="pdg-badge-cat ${info.cls}" style="font-size:.68rem;padding:.15em .5em;">${cat}</span></div>`;

        datesArr.forEach(({ d, dow, weekNum }, i) => {
            const sep = dow === 0 && i > 0 ? 'pdg-cal-week-sep' : '';
            const todayCls = i === 0 ? 'pdg-cal-today-col' : '';
            let cellCls = '';
            let storesDispatching = [];

            // Check which stores are dispatching this category today
            PDG.sucursales.forEach(suc => {
                const cod = suc.codigo;
                const storeData = PDG.configCache[cod];
                if (!storeData || !storeData.plan) return;
                const cfg = storeData.plan[cat];
                if (cfg && (cfg.activo || cfg.activo === undefined)) {
                    let isDespacho = false;
                    if (cfg.tipo_frecuencia === 'dias_semana') {
                        let diasSemanaArr = [];
                        try {
                            diasSemanaArr = typeof cfg.dias_semana === 'string' ? JSON.parse(cfg.dias_semana) : cfg.dias_semana;
                        } catch(e) {}
                        isDespacho = Array.isArray(diasSemanaArr) && diasSemanaArr.includes(dow);
                    } else {
                        const diaMach = parseInt(cfg.dia_despacho) === dow;
                        let intervalMach = true;
                        if (parseInt(cfg.intervalo_semanas) > 1 && cfg.semana_ancla) {
                            intervalMach = (weekNum - parseInt(cfg.semana_ancla)) % parseInt(cfg.intervalo_semanas) === 0;
                        }
                        isDespacho = diaMach && intervalMach;
                    }
                    if (isDespacho) {
                        storesDispatching.push(suc.nombre);
                    }
                }
            });

            let cellTxt = '';
            if (storesDispatching.length > 0) {
                cellCls = 'pdg-cal-despacho';
                let tagsHtml = storesDispatching.map(storeName => {
                    return `<span style="color: var(--pdg-green); font-size: 0.68rem; font-weight: 700; word-break: break-word;" title="${storeName}">${storeName}</span>`;
                }).join('<span style="color: rgba(14,84,76,.4); font-size: 0.68rem; margin-right: 2px;">,</span> ');
                cellTxt = `<div style="text-align: center; line-height: 1.2; padding: 2px 2px; width: 100%; white-space: normal;">${tagsHtml}</div>`;
            }

            grid += `<div class="pdg-cal-cell ${cellCls} ${sep} ${todayCls}" style="flex-direction:column; justify-content:center;">${cellTxt}</div>`;
        });
    });

    $(`#globalCalendar`).html(`
        <div class="pdg-calendar-title">
            <i class="bi bi-calendar3-week"></i> Vista previa
        </div>
        <div class="pdg-calendar-grid">${grid}</div>
    `);
}

/* ══════════════════════════════════════════════════════════
   INTERACCIÓN DE FORMULARIO
   ══════════════════════════════════════════════════════════ */

function bindRowEvents(cod) {
    const $ctx = $(`#contentSelectedStore`);

    // Cambio de tipo (n_semanas ↔ dias_semana)
    $ctx.off('change', '.pdg-tipo-radio').on('change', '.pdg-tipo-radio', function () {
        const $tr = $(this).closest('tr');
        const tipo = $(this).val();
        const isNSem = tipo === 'n_semanas';
        $tr.find('.pdg-n-semanas-fields').toggleClass('d-none', !isNSem);
        $tr.find('.pdg-dias-semana-fields').toggleClass('d-none', isNSem);
        updateAnclaVisibility($tr);
        saveRow(cod, $tr.data('cat'));
    });

    // Auto-save on any other input/select change
    $ctx.off('change', 'select, input[type="number"], input[type="text"], input[type="checkbox"]').on('change', 'select, input[type="number"], input[type="text"], input[type="checkbox"]', function() {
        if ($(this).hasClass('pdg-tipo-radio')) return; // Handled above
        const $tr = $(this).closest('tr');
        if ($tr.length) {
            const cat = $tr.data('cat');
            if(cat) saveRow(cod, cat);
        } else if ($(this).closest('.pdg-congelador-card').length) {
            saveRow(cod, 'B'); // Guardar cat B si cambia el congelador
        }
    });
}

function updateAnclaVisibility($tr) {
    const tipo = $tr.find('.pdg-tipo-radio:checked').val();
    const interv = parseInt($tr.find('.pdg-intervalo').val()) || 1;
    const showAnc = tipo === 'n_semanas' && interv > 1;
    $tr.find('.pdg-ancla-wrap').toggleClass('d-none', !showAnc);
    $tr.find('.pdg-ancla-na').toggleClass('d-none', showAnc);
}

function collectRowData(cod, cat) {
    const $tr = $(`#tabla-${cod} tr[data-cat="${cat}"]`);
    const tipo = $tr.find('.pdg-tipo-radio:checked').val() || 'n_semanas';
    const data = { cod_sucursal: cod, categoria_insumo: cat, tipo_frecuencia: tipo };

    if (tipo === 'n_semanas') {
        data.intervalo_semanas = $tr.find('.pdg-intervalo').val();
        data.dia_despacho = $tr.find('.pdg-dia-despacho').val();
        const ancla = $tr.find('.pdg-semana-ancla').val();
        data.semana_ancla = ancla !== '' ? ancla : '';
    } else {
        const dias = [];
        $tr.find('.pdg-dia-check:checked').each(function () {
            dias.push(parseInt($(this).data('idx')));
        });
        data.dias_semana = JSON.stringify(dias);
    }

    data.dias_preparacion = 1; // Default
    data.activo = 1; // Default

    // Cat B: capacidad congelador
    if (cat === 'B') {
        data.capacidad_congelados_paquetes = $('#capCongelados').val();
        data.capacidad_congelados_obs = $('#capCongeladosObs').val();
    }

    return data;
}

function saveRow(cod, cat) {
    if (!PUEDE_EDITAR) return;
    const payload = collectRowData(cod, cat);

    $.ajax({
        url: PDG.AJAX_BASE + 'plan_despacho_save.php',
        method: 'POST',
        data: payload,
        dataType: 'json',
    }).done(function (res) {
        if (res.success) {
            toastOk('Guardado.');
            
            // Invalidar caché y re-render calendario
            if (PDG.configCache[cod] && PDG.configCache[cod].plan) {
                if (!PDG.configCache[cod].plan[cat]) PDG.configCache[cod].plan[cat] = {};
                
                const cachePayload = { ...payload };
                if (cachePayload.dias_semana && typeof cachePayload.dias_semana === 'string') {
                    try { cachePayload.dias_semana = JSON.parse(cachePayload.dias_semana); } catch(e) { cachePayload.dias_semana = []; }
                }
                
                Object.assign(PDG.configCache[cod].plan[cat], cachePayload);
                if (cat === 'B') {
                    PDG.configCache[cod].capacidad_congelados.paquetes = payload.capacidad_congelados_paquetes;
                    PDG.configCache[cod].capacidad_congelados.obs = payload.capacidad_congelados_obs;
                }
            }
            renderGlobalCalendar();
        } else {
            toastErr(res.message || 'Error al guardar.');
        }
    }).fail(function () {
        toastErr('Error de conexión al guardar.');
    });
}

/* ══════════════════════════════════════════════════════════
   CARGA DE DATOS
   ══════════════════════════════════════════════════════════ */

function loadAllConfigs() {
    const requests = PDG.sucursales.map(suc => {
        return $.ajax({
            url: PDG.AJAX_BASE + 'plan_despacho_get_config.php',
            method: 'POST',
            data: { cod_sucursal: suc.codigo },
            dataType: 'json'
        });
    });

    Promise.allSettled(requests).then(results => {
        results.forEach((res, index) => {
            if(res.status === 'fulfilled' && res.value.success) {
                const cod = PDG.sucursales[index].codigo;
                PDG.configCache[cod] = res.value.data;
            }
        });
        
        if(PDG.sucursales.length > 0) {
            selectStore(PDG.sucursales[0].codigo);
        }
        
        $('#calendarContainer').show();
        renderGlobalCalendar();
    });
}

function selectStore(cod) {
    $('.pdg-store-list .list-group-item').removeClass('active');
    $(`#store-${cod}`).addClass('active');
    
    PDG.currentStore = cod;
    
    renderConfig(cod, PDG.configCache[cod]);
}

function renderConfig(cod, data) {
    const $content = $(`#contentSelectedStore`);
    if(data) {
        $content.html(buildContent(cod, data));
        initTooltips($content);
        bindRowEvents(cod);
    } else {
        $content.html('<p class="text-danger p-3">Error cargando configuración.</p>');
    }
}

function loadSucursales() {
    $.ajax({
        url: PDG.AJAX_BASE + 'plan_despacho_get_sucursales.php',
        method: 'GET',
        dataType: 'json',
    }).done(function (res) {
        $('#loaderSucursales').hide();
        if (!res.success || !res.data.length) {
            $('#sinSucursales').show();
            return;
        }
        PDG.sucursales = res.data;
        const $list = $('#sucursalesList');
        $list.empty();

        res.data.forEach(function (suc, idx) {
            $list.append(buildStoreListItem(suc, idx));
        });

        $('#mainLayout').show();

        $list.on('click', '.list-group-item', function() {
            const cod = $(this).data('cod');
            selectStore(cod);
        });

        loadAllConfigs();

    }).fail(function () {
        $('#loaderSucursales').hide();
        toastErr('No se pudo cargar la lista de sucursales.');
    });
}

/* ── Init ── */
$(document).ready(function () {
    loadSucursales();
});

/* ══════════════════════════════════════════════════════════
   STOCK MINIMO POR PRODUCTO (GRUPO G)
   ══════════════════════════════════════════════════════════ */

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function abrirConfigStockG(codSucursal, nombreSucursal) {
    $('#stockMinimoGSucursalNombre').text(nombreSucursal);
    const $modal = new bootstrap.Modal(document.getElementById('modalConfigStockMinimoG'));
    $modal.show();

    $('#stockGContent').hide();
    $('#loaderStockG').show();
    $('#listaStockG').empty();

    $.ajax({
        url: PDG.AJAX_BASE + 'plan_despacho_get_stock_g.php',
        method: 'POST',
        data: { cod_sucursal: codSucursal },
        dataType: 'json'
    }).done(function (res) {
        $('#loaderStockG').hide();
        $('#stockGContent').show();

        if (res.success && res.productos.length > 0) {
            let html = '';
            res.productos.forEach(p => {
                const stockVal = parseFloat(p.stock_minimo_unidades) || 0;
                // Formateamos visualmente para no mostrar 0.0000, sino 0 ó el número limpio.
                const valStr = stockVal === 0 ? '0' : stockVal.toString();
                
                html += `
                    <tr>
                        <td class="ps-3"><span class="fw-medium">${escHtml(p.nombre)}</span></td>
                        <td class="text-secondary small">${escHtml(p.presentacion || '')}</td>
                        <td class="pe-3">
                            <input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end"
                                value="${valStr}"
                                data-id-pp="${p.id_producto_presentacion}"
                                data-cod-sucursal="${codSucursal}"
                                ${!PUEDE_EDITAR ? 'disabled' : ''}
                                onfocus="this.dataset.initial = this.value"
                                onblur="guardarStockG(this)">
                        </td>
                    </tr>
                `;
            });
            $('#listaStockG').html(html);
        } else {
            $('#listaStockG').html('<tr><td colspan="3" class="text-center text-muted py-4">No se encontraron productos activos del Grupo G.</td></tr>');
        }
    }).fail(function () {
        $('#loaderStockG').hide();
        $('#listaStockG').html('<tr><td colspan="3" class="text-center text-danger py-4">Error de conexión al cargar productos.</td></tr>');
        $('#stockGContent').show();
    });
}

function guardarStockG(inputEl) {
    if (!PUEDE_EDITAR) return;
    const $input = $(inputEl);
    const valorActual = $input.val();
    const valorInicial = $input.data('initial');

    if (valorActual === valorInicial) return;

    const idPP = $input.data('id-pp');
    const codSucursal = $input.data('cod-sucursal');
    const valFinal = parseFloat(valorActual) || 0;

    $input.addClass('border-warning');

    $.ajax({
        url: PDG.AJAX_BASE + 'plan_despacho_save_stock_g.php',
        method: 'POST',
        data: {
            cod_sucursal: codSucursal,
            id_producto_presentacion: idPP,
            stock_minimo_unidades: valFinal
        },
        dataType: 'json'
    }).done(function (res) {
        $input.removeClass('border-warning');
        if (res.success) {
            $input.addClass('border-success');
            setTimeout(() => $input.removeClass('border-success'), 1500);
            $input.data('initial', valFinal);
        } else {
            $input.addClass('border-danger');
            toastErr(res.message || 'Error al guardar.');
        }
    }).fail(function () {
        $input.removeClass('border-warning').addClass('border-danger');
        toastErr('Error de conexión al guardar.');
    });
}

