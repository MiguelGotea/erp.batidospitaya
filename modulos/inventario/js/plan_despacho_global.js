/* ============================================================
   JS: Plan de Despacho Global
   Ruta: modulos/inventario/js/plan_despacho_global.js
   ============================================================ */
'use strict';

const PDG = {
    AJAX_BASE: 'ajax/',
    CATEGORIAS: {
        A: { nombre: 'Frescos',              cls: 'pdg-cat-A', icon: 'bi-apple'         },
        B: { nombre: 'Congelados',           cls: 'pdg-cat-B', icon: 'bi-snow2'         },
        C: { nombre: 'Fresas',               cls: 'pdg-cat-C', icon: 'bi-heart-fill'    },
        D: { nombre: 'Desechables',          cls: 'pdg-cat-D', icon: 'bi-trash3'        },
        E: { nombre: 'Fijos',                cls: 'pdg-cat-E', icon: 'bi-box-seam'      },
        F: { nombre: 'Secos y Preparación',  cls: 'pdg-cat-F', icon: 'bi-bucket'        },
        G: { nombre: 'Prod. de Mostrador',   cls: 'pdg-cat-G', icon: 'bi-shop'          },
    },
    DIAS: ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'],
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
    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: msg,
        showConfirmButton: false, timer: 2800, timerProgressBar: true });
}
function toastErr(msg) {
    Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: msg,
        showConfirmButton: false, timer: 4000 });
}

/* ══════════════════════════════════════════════════════════
   RENDER HTML
   ══════════════════════════════════════════════════════════ */

function buildTabNav(suc, idx) {
    const active = idx === 0 ? 'active' : '';
    return `<li class="nav-item" role="presentation">
        <button class="nav-link ${active}" id="tab-${suc.codigo}" data-bs-toggle="tab"
            data-bs-target="#pane-${suc.codigo}" type="button" role="tab"
            data-cod="${suc.codigo}">
            <i class="bi bi-building me-1"></i>${suc.nombre}
        </button>
    </li>`;
}

function buildTabPane(suc, idx) {
    const active = idx === 0 ? 'show active' : '';
    return `<div class="tab-pane fade ${active}" id="pane-${suc.codigo}" role="tabpanel">
        <div class="pdg-tab-loader" id="loader-${suc.codigo}">
            <div class="spinner-border" role="status"></div>
            <p class="mt-2 small">Cargando configuración…</p>
        </div>
        <div id="content-${suc.codigo}" style="display:none;"></div>
    </div>`;
}

function buildDiaCheckboxes(cat, selected) {
    return PDG.DIAS.map((d, i) => {
        const chk = Array.isArray(selected) && selected.includes(i) ? 'checked' : '';
        return `<input type="checkbox" class="pdg-dia-check" id="dia-${cat}-${i}"
                    data-idx="${i}" ${chk}>
                <label class="pdg-dia-label" for="dia-${cat}-${i}">${d.charAt(0)}</label>`;
    }).join('');
}

function buildCatRow(cat, cfg) {
    const info      = PDG.CATEGORIAS[cat];
    const isNSem    = !cfg || cfg.tipo_frecuencia !== 'dias_semana';
    const tipo      = cfg ? cfg.tipo_frecuencia : 'n_semanas';
    const intervalo = cfg ? cfg.intervalo_semanas : 1;
    const dia       = cfg ? cfg.dia_despacho     : 1;
    const ancla     = cfg && cfg.semana_ancla ? cfg.semana_ancla : '';
    const prep      = cfg ? cfg.dias_preparacion : 1;
    const activo    = cfg ? cfg.activo : 1;
    const diasSel   = cfg ? (cfg.dias_semana || []) : [];
    const metaTxt   = cfg && cfg.modificado_por_nombre
        ? `<div class="pdg-meta-info"><i class="bi bi-person-check me-1"></i>${cfg.modificado_por_nombre}
           <span class="ms-1">${cfg.fecha_actualizacion ? cfg.fecha_actualizacion.substring(0,16) : ''}</span></div>`
        : '';
    const disabled  = PUEDE_EDITAR ? '' : 'disabled';
    const showAncla = isNSem && intervalo > 1;

    // Opcionalmente deshabilitar si inactivo
    const rowCls = [
        cat === 'B' ? 'pdg-row-cat-b' : '',
        activo ? '' : 'pdg-row-inactive'
    ].join(' ');

    const anclaTooltip = `Ingresa el número de semana de un despacho real ya ocurrido.<br>
        Ej: si la semana 540 fue el último despacho, escribe 540.<br>
        El sistema calcula: (semana_actual − ancla) % intervalo == 0.`;

    return `<tr class="${rowCls}" data-cat="${cat}">
        <!-- Categoría -->
        <td>
            <span class="pdg-badge-cat ${info.cls}">
                <i class="bi ${info.icon}"></i>${cat} – ${info.nombre}
            </span>
            ${metaTxt}
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
                    <option value="1" ${intervalo==1?'selected':''}>Cada semana</option>
                    <option value="2" ${intervalo==2?'selected':''}>Quincenal</option>
                    <option value="3" ${intervalo==3?'selected':''}>Cada 3 semanas</option>
                </select>
            </div>
            <div class="pdg-dias-semana-fields ${!isNSem ? '' : 'd-none'}">
                <div class="pdg-dias-group">
                    ${buildDiaCheckboxes(cat, diasSel)}
                </div>
            </div>
        </td>
        <!-- Día despacho -->
        <td style="min-width:110px;">
            <div class="pdg-n-semanas-fields ${isNSem ? '' : 'd-none'}">
                <select class="form-select form-select-sm pdg-dia-despacho" ${disabled}>
                    ${PDG.DIAS.map((d,i)=>`<option value="${i}" ${dia==i?'selected':''}>${d}</option>`).join('')}
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
        <!-- Preparación -->
        <td style="width:80px; text-align:center;">
            <input type="number" class="form-control form-control-sm text-center pdg-prep"
                value="${prep}" min="0" max="30" ${disabled}>
        </td>
        <!-- Activo -->
        <td style="text-align:center;">
            <div class="form-check form-switch d-flex justify-content-center">
                <input class="form-check-input pdg-activo" type="checkbox"
                    ${activo ? 'checked' : ''} ${disabled}>
            </div>
        </td>
        <!-- Guardar -->
        <td style="text-align:center; white-space:nowrap;">
            ${PUEDE_EDITAR
                ? `<button class="btn-pdg-save btn-save-row" data-cat="${cat}">
                       <i class="bi bi-floppy me-1"></i>Guardar
                   </button>`
                : '<span class="text-muted small">—</span>'
            }
        </td>
    </tr>`;
}

function buildCongeladorSection(cap) {
    if (!PUEDE_EDITAR) {
        return `<div class="pdg-congelador-card">
            <div class="pdg-congelador-title"><i class="bi bi-snow2"></i>Capacidad Congelador (Cat B)</div>
            <div class="row g-2">
                <div class="col-auto"><strong>${cap.paquetes ?? '—'}</strong> paquetes</div>
                <div class="col">${cap.obs ? `<span class="text-muted small">${cap.obs}</span>` : ''}</div>
            </div>
        </div>`;
    }
    return `<div class="pdg-congelador-card">
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
    let rows = Object.keys(PDG.CATEGORIAS).map(cat => buildCatRow(cat, plan[cat] || null)).join('');

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
                        <th style="width:80px;">Prep. (días)</th>
                        <th style="width:70px;">Activo</th>
                        <th style="width:100px;">Acción</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>
        ${buildCongeladorSection(capacidad_congelados)}
        <div class="pdg-calendar-section" id="cal-${cod}"></div>
    </div>`;
}

/* ══════════════════════════════════════════════════════════
   MINI-CALENDARIO (próximas 14 días)
   ══════════════════════════════════════════════════════════ */

function getCurrentWeekNumber() {
    const now  = new Date();
    const jan1 = new Date(now.getFullYear(), 0, 1);
    const days = Math.floor((now - jan1) / 86400000);
    return Math.ceil((days + jan1.getDay() + 1) / 7);
}

function renderCalendar(cod, plan) {
    const today     = new Date();
    today.setHours(0,0,0,0);
    const weekNow   = getCurrentWeekNumber();
    const daysLabel = [];
    const datesArr  = [];

    for (let i = 0; i < 14; i++) {
        const d = new Date(today);
        d.setDate(today.getDate() + i);
        const dow = (d.getDay() + 6) % 7; // 0=Lun
        daysLabel.push({ dow, label: PDG.DIAS[dow].substring(0,2), date: d, isToday: i===0 });
        datesArr.push({ d, dow, weekNum: weekNow + Math.floor(i/7) });
    }

    // Header días
    let grid = `<div class="pdg-cal-header" style="text-align:right;">Cat</div>`;
    daysLabel.forEach((dl, i) => {
        const sep   = dl.dow === 0 && i > 0 ? 'pdg-cal-week-sep' : '';
        const today = dl.isToday ? 'pdg-cal-today' : '';
        grid += `<div class="pdg-cal-header ${sep} ${today}">${dl.label}<br><span style="font-size:.6rem;">${dl.date.getDate()}/${dl.date.getMonth()+1}</span></div>`;
    });

    // Filas por categoría
    Object.keys(PDG.CATEGORIAS).forEach(cat => {
        const cfg  = plan[cat];
        const info = PDG.CATEGORIAS[cat];
        grid += `<div class="pdg-cal-cat-label"><span class="pdg-badge-cat ${info.cls}" style="font-size:.68rem;padding:.15em .5em;">${cat}</span></div>`;

        datesArr.forEach(({ d, dow, weekNum }, i) => {
            const sep = dow === 0 && i > 0 ? 'pdg-cal-week-sep' : '';
            const todayCls = i === 0 ? 'pdg-cal-today-col' : '';
            let cellCls = '', cellTxt = '';

            if (cfg && cfg.activo) {
                let isDespacho = false;
                if (cfg.tipo_frecuencia === 'dias_semana') {
                    isDespacho = Array.isArray(cfg.dias_semana) && cfg.dias_semana.includes(dow);
                } else {
                    const diaMach = cfg.dia_despacho === dow;
                    let intervalMach = true;
                    if (cfg.intervalo_semanas > 1 && cfg.semana_ancla) {
                        intervalMach = (weekNum - cfg.semana_ancla) % cfg.intervalo_semanas === 0;
                    }
                    isDespacho = diaMach && intervalMach;
                }
                if (isDespacho) {
                    cellCls = 'pdg-cal-despacho';
                    cellTxt = '<i class="bi bi-truck-front-fill"></i>';
                }
            }

            grid += `<div class="pdg-cal-cell ${cellCls} ${sep} ${todayCls}">${cellTxt}</div>`;
        });
    });

    $(`#cal-${cod}`).html(`
        <div class="pdg-calendar-title">
            <i class="bi bi-calendar3-week"></i> Vista previa — Próximos 14 días
            <span class="text-muted fw-normal" style="font-size:.75rem;">
                <i class="bi bi-truck-front-fill text-white bg-success rounded px-1"></i> = día de despacho
            </span>
        </div>
        <div class="pdg-calendar-grid">${grid}</div>
    `);
}

/* ══════════════════════════════════════════════════════════
   INTERACCIÓN DE FORMULARIO
   ══════════════════════════════════════════════════════════ */

function bindRowEvents(cod) {
    const $ctx = $(`#content-${cod}`);

    // Cambio de tipo (n_semanas ↔ dias_semana)
    $ctx.on('change', '.pdg-tipo-radio', function () {
        const $tr     = $(this).closest('tr');
        const tipo    = $(this).val();
        const isNSem  = tipo === 'n_semanas';
        $tr.find('.pdg-n-semanas-fields').toggleClass('d-none', !isNSem);
        $tr.find('.pdg-dias-semana-fields').toggleClass('d-none', isNSem);
        updateAnclaVisibility($tr);
    });

    // Cambio intervalo → mostrar/ocultar ancla
    $ctx.on('change', '.pdg-intervalo', function () {
        updateAnclaVisibility($(this).closest('tr'));
    });

    // Toggle activo → estilo visual
    $ctx.on('change', '.pdg-activo', function () {
        $(this).closest('tr').toggleClass('pdg-row-inactive', !this.checked);
    });

    // Guardar por fila
    $ctx.on('click', '.btn-save-row', function () {
        const cat = $(this).data('cat');
        saveRow(cod, cat, $(this));
    });
}

function updateAnclaVisibility($tr) {
    const tipo    = $tr.find('.pdg-tipo-radio:checked').val();
    const interv  = parseInt($tr.find('.pdg-intervalo').val()) || 1;
    const showAnc = tipo === 'n_semanas' && interv > 1;
    $tr.find('.pdg-ancla-wrap').toggleClass('d-none', !showAnc);
    $tr.find('.pdg-ancla-na').toggleClass('d-none', showAnc);
}

function collectRowData(cod, cat) {
    const $tr  = $(`#tabla-${cod} tr[data-cat="${cat}"]`);
    const tipo = $tr.find('.pdg-tipo-radio:checked').val() || 'n_semanas';
    const data = { cod_sucursal: cod, categoria_insumo: cat, tipo_frecuencia: tipo };

    if (tipo === 'n_semanas') {
        data.intervalo_semanas = $tr.find('.pdg-intervalo').val();
        data.dia_despacho      = $tr.find('.pdg-dia-despacho').val();
        const ancla = $tr.find('.pdg-semana-ancla').val();
        data.semana_ancla      = ancla !== '' ? ancla : '';
    } else {
        const dias = [];
        $tr.find('.pdg-dia-check:checked').each(function () {
            dias.push(parseInt($(this).data('idx')));
        });
        data.dias_semana = JSON.stringify(dias);
    }

    data.dias_preparacion = $tr.find('.pdg-prep').val();
    data.activo           = $tr.find('.pdg-activo').is(':checked') ? 1 : 0;

    // Cat B: capacidad congelador
    if (cat === 'B') {
        data.capacidad_congelados_paquetes = $('#capCongelados').val();
        data.capacidad_congelados_obs      = $('#capCongeladosObs').val();
    }

    return data;
}

function saveRow(cod, cat, $btn) {
    const payload = collectRowData(cod, cat);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

    $.ajax({
        url: PDG.AJAX_BASE + 'plan_despacho_save.php',
        method: 'POST',
        data: payload,
        dataType: 'json',
    }).done(function (res) {
        if (res.success) {
            toastOk(res.message || 'Guardado correctamente.');
            // Actualizar meta info
            if (res.meta) {
                const $tr = $(`#tabla-${cod} tr[data-cat="${cat}"]`);
                const metaHtml = `<div class="pdg-meta-info">
                    <i class="bi bi-person-check me-1"></i>${res.meta.modificado_por_nombre}
                    <span class="ms-1">${res.meta.fecha_actualizacion.substring(0,16)}</span>
                </div>`;
                $tr.find('td:first .pdg-meta-info').remove();
                $tr.find('td:first').append(metaHtml);
            }
            // Invalidar caché y re-render calendario
            delete PDG.configCache[cod];
            loadConfig(cod, true);
        } else {
            toastErr(res.message || 'Error al guardar.');
        }
    }).fail(function () {
        toastErr('Error de conexión al guardar.');
    }).always(function () {
        $btn.prop('disabled', false).html('<i class="bi bi-floppy me-1"></i>Guardar');
    });
}

/* ══════════════════════════════════════════════════════════
   CARGA DE DATOS
   ══════════════════════════════════════════════════════════ */

function loadConfig(cod, calOnly) {
    if (!calOnly) {
        $(`#loader-${cod}`).show();
        $(`#content-${cod}`).hide();
    }

    if (PDG.configCache[cod] && !calOnly) {
        renderConfig(cod, PDG.configCache[cod]);
        return;
    }

    $.ajax({
        url: PDG.AJAX_BASE + 'plan_despacho_get_config.php',
        method: 'POST',
        data: { cod_sucursal: cod },
        dataType: 'json',
    }).done(function (res) {
        if (res.success) {
            PDG.configCache[cod] = res.data;
            if (calOnly) {
                renderCalendar(cod, res.data.plan);
            } else {
                renderConfig(cod, res.data);
            }
        } else {
            toastErr('Error cargando datos: ' + (res.message || ''));
            $(`#loader-${cod}`).html('<p class="text-danger small">Error cargando datos.</p>');
        }
    }).fail(function () {
        toastErr('Error de conexión.');
        $(`#loader-${cod}`).html('<p class="text-danger small">Error de conexión.</p>');
    });
}

function renderConfig(cod, data) {
    const $content = $(`#content-${cod}`);
    $content.html(buildContent(cod, data));
    $(`#loader-${cod}`).hide();
    $content.show();
    initTooltips(`#content-${cod}`);
    bindRowEvents(cod);
    renderCalendar(cod, data.plan);
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
        const $nav     = $('#sucursalesTabs');
        const $content = $('#sucursalesTabContent');

        res.data.forEach(function (suc, idx) {
            $nav.append(buildTabNav(suc, idx));
            $content.append(buildTabPane(suc, idx));
        });

        $('#sucursalesContainer').show();

        // Cargar la primera pestaña
        if (res.data.length > 0) {
            loadConfig(res.data[0].codigo, false);
        }

        // Cargar al cambiar de tab (lazy)
        $('#sucursalesTabs').on('shown.bs.tab', 'button[data-cod]', function () {
            const cod = $(this).data('cod');
            if (!PDG.configCache[cod]) loadConfig(cod, false);
        });
    }).fail(function () {
        $('#loaderSucursales').hide();
        toastErr('No se pudo cargar la lista de sucursales.');
    });
}

/* ── Init ── */
$(document).ready(function () {
    loadSucursales();
});
