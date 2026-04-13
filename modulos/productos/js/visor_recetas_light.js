/* ============================================================
   VISOR RECETAS LIGHT — JavaScript
   modulos/productos/js/visor_recetas_light.js
   ============================================================ */

/* ── Constantes AJAX ────────────────────────────────────────── */
const AJAX_PRODUCTOS = 'ajax/visor_recetas_light_get_productos.php';
const AJAX_RECETA = '../sistemas/ajax/accessantiguo_get_detalle_receta.php';

/* ── Paleta de colores para grupos ──────────────────────────── */
const GRUPO_COLORES = [
    '#2d6a4f', '#1565c0', '#6a1b9a', '#c62828', '#e65100',
    '#00838f', '#4527a0', '#2e7d32', '#4e342e', '#ad1457',
];

/* ── Mapeo de tamaños de medida ─────────────────────────────── */
const MEDIDA_OZ = { 'gigantona': '20oz', 'mediano': '16oz', 'pequeño': '12oz' };

/* ── Estado global ──────────────────────────────────────────── */
let todosGrupos = [];   // árbol completo cargado desde AJAX
let productoActual = null; // { Nombre, versiones: [] }
let versionActual = null; // { CodBatido, Medida, Precio }

/* ── Utilidades DOM ─────────────────────────────────────────── */
function esc(s) {
    if (s === null || s === undefined) return '';
    return String(s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function show(id) { document.getElementById(id).classList.remove('d-none'); }
function hide(id) { document.getElementById(id).classList.add('d-none'); }
function esPedidosYa(cod) {
    return typeof cod === 'string' && cod.toLowerCase().endsWith('d');
}
function labelMedida(medida) {
    if (!medida) return '';
    const oz = MEDIDA_OZ[medida.toLowerCase()];
    return oz ? `${medida} ${oz}` : medida;
}

/* ═══════════════════════════════════════════════════════════════
   MENÚ DE PRODUCTOS
   ═══════════════════════════════════════════════════════════════ */

/* ── Cargar árbol de productos ──────────────────────────────── */
async function iniciarMenu() {
    try {
        const res = await fetch(AJAX_PRODUCTOS);
        const data = await res.json();
        if (!data.success) throw new Error(data.message);
        todosGrupos = data.grupos;
        renderMenu(todosGrupos);
        // Listener único — sobrevive re-renders del innerHTML
        document.getElementById('menuBody').addEventListener('click', onMenuClick);
        // Todos los grupos empiezan ocultos (JS controla display)
        document.querySelectorAll('.vrl-productos-list').forEach(el => el.style.display = 'none');
    } catch (e) {
        document.getElementById('menuBody').innerHTML =
            `<div class="menu-loading text-danger"><i class="fas fa-exclamation-circle me-1"></i>Error al cargar</div>`;
    }
}

/* ── Renderizar menú ─────────────────────────────────────────── */
function renderMenu(grupos) {
    const body = document.getElementById('menuBody');
    if (!grupos.length) {
        body.innerHTML = `<div class="menu-loading">Sin productos activos</div>`;
        return;
    }

    let html = '';
    grupos.forEach((g, gIdx) => {
        const color = GRUPO_COLORES[gIdx % GRUPO_COLORES.length];
        const totalProds = g.productos.length;
        const gid = `vrl-lista-${g.CodGrupo}`;
        const nombre = g.alias || g.NombreGrupo;

        // data-codgrupo en lugar de onclick con JSON (seguro ante caracteres especiales)
        html += `
        <button class="vrl-grupo-btn" id="vrl-btn-${g.CodGrupo}"
                data-codgrupo="${esc(String(g.CodGrupo))}">
            <span class="grupo-icon" style="background:${color}">
                <i class="fas fa-box-open"></i>
            </span>
            <span class="flex-grow-1 text-start" style="font-size:.81rem">${esc(nombre)}</span>
            <span class="grupo-count">${totalProds}</span>
            <i class="fas fa-chevron-down chevron"></i>
        </button>
        <div class="vrl-productos-list" id="${gid}">`;


        // Usamos data-nombre en lugar de data-prodidx para evitar discrepancias de índice
        g.productos.forEach(p => {
            html += `
            <div class="vrl-prod-item"
                 data-codgrupo="${esc(String(g.CodGrupo))}"
                 data-nombre="${esc(String(p.Nombre ?? ''))}">
                <span class="prod-nombre">${esc(p.Nombre)}</span>
                <span class="prod-versiones-count">${p.versiones.length}v</span>
            </div>`;
        });

        html += `</div>`;
    });

    body.innerHTML = html;
}

/* ── Event delegation (un solo listener en menuBody) ────────── */
function onMenuClick(e) {
    const btnGrupo = e.target.closest('.vrl-grupo-btn');
    if (btnGrupo) {
        toggleGrupo(btnGrupo.dataset.codgrupo);
        return;
    }
    const itemProd = e.target.closest('.vrl-prod-item');
    if (itemProd) {
        const codGrupo = itemProd.dataset.codgrupo;
        const nombre = itemProd.dataset.nombre;
        seleccionarProductoPorNombre(codGrupo, nombre, itemProd);
    }
}

/* ── Toggle grupo ────────────────────────────────────────────── */
function toggleGrupo(codGrupo) {
    const lista = document.getElementById(`vrl-lista-${codGrupo}`);
    if (!lista) return;

    const isOpen = lista.style.display === 'block';

    // Cerrar todos
    document.querySelectorAll('.vrl-productos-list').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.vrl-grupo-btn.active').forEach(el => el.classList.remove('active'));

    // Abrir si estaba cerrado
    if (!isOpen) {
        lista.style.display = 'block';
        const btnEl = document.querySelector(`[data-codgrupo="${CSS.escape(String(codGrupo))}"].vrl-grupo-btn`);
        if (btnEl) btnEl.classList.add('active');
        lista.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

/* ── Búsqueda en menú ────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('inputBuscar').addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        if (!q) {
            renderMenu(todosGrupos);
            document.querySelectorAll('.vrl-productos-list').forEach(el => el.style.display = 'none');
            return;
        }

        const filtrado = todosGrupos.map(g => ({
            ...g,
            productos: g.productos.filter(p => p.Nombre != null && p.Nombre.toLowerCase().includes(q))
        })).filter(g => g.productos.length > 0);

        renderMenu(filtrado);

        // Abrir automáticamente todos los grupos con resultados
        filtrado.forEach(g => {
            const lista = document.getElementById(`vrl-lista-${g.CodGrupo}`);
            const btnEl = document.querySelector(`[data-codgrupo="${CSS.escape(String(g.CodGrupo))}"].vrl-grupo-btn`);
            if (lista) lista.style.display = 'block';
            if (btnEl) btnEl.classList.add('active');
        });
    });
});


/* ═══════════════════════════════════════════════════════════════
   SELECCIÓN DE PRODUCTO Y VERSIÓN
   ═══════════════════════════════════════════════════════════════ */

/* ── Seleccionar producto por nombre ────────────────────────── */
function seleccionarProductoPorNombre(codGrupo, nombre, elClicked) {
    document.querySelectorAll('.vrl-prod-item.selected').forEach(el => el.classList.remove('selected'));
    if (elClicked) elClicked.classList.add('selected');

    const grupo = todosGrupos.find(g => String(g.CodGrupo) === String(codGrupo));
    if (!grupo) return;
    const prod = grupo.productos.find(p => String(p.Nombre ?? '') === String(nombre));
    if (!prod) return;

    productoActual = prod;
    versionActual = null;

    resetContenido();

    // Auto-cargar primera versión inmediatamente (flujo 2 clicks)
    const v0 = prod.versiones[0];
    if (v0) seleccionarVersion(v0.CodBatido);
}

/* ── Seleccionar versión ─────────────────────────────────────── */
function seleccionarVersion(codBatido) {
    if (!productoActual) return;
    versionActual = productoActual.versiones.find(v => v.CodBatido === codBatido);

    renderBarraProducto(productoActual, codBatido);

    hide('panelTabla');
    hide('panelVacio');
    document.getElementById('spinnerReceta').style.display = 'block';
    document.getElementById('tbodyReceta').innerHTML = '';

    fetch(`${AJAX_RECETA}?cod_batido=${encodeURIComponent(codBatido)}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('spinnerReceta').style.display = 'none';
            if (!data.success) { show('panelVacio'); return; }
            renderTabla(data.ingredientes);
        })
        .catch(() => {
            document.getElementById('spinnerReceta').style.display = 'none';
            show('panelVacio');
        });
}

/* ── Barra compacta producto + chips de versión ─────────────── */
function renderBarraProducto(prod, codActivo) {
    const barra = document.getElementById('barraProducto');
    let chipsHtml = '';

    if (prod.versiones.length > 1) {
        chipsHtml = `<div class="bp-chips">`;
        prod.versiones.forEach(v => {
            const esPY = esPedidosYa(v.CodBatido);
            const pyHtml = esPY
                ? ` <span class="badge-pedidosya" style="font-size:.55rem;padding:1px 5px"><i class="fas fa-motorcycle"></i></span>`
                : '';
            const label = labelMedida(v.Medida) || v.CodBatido;
            const activo = v.CodBatido === codActivo ? 'active' : '';
            chipsHtml += `<button class="bp-chip ${activo}" data-cod="${esc(v.CodBatido)}"
                onclick="seleccionarVersion('${esc(v.CodBatido)}')">${esc(label)}${pyHtml}</button>`;
        });
        chipsHtml += `</div>`;
    } else if (prod.versiones.length === 1) {
        const v = prod.versiones[0];
        const esPY = esPedidosYa(v.CodBatido);
        const pyHtml = esPY
            ? ` <span class="badge-pedidosya" style="font-size:.55rem;padding:1px 5px"><i class="fas fa-motorcycle"></i></span>`
            : '';
        const label = labelMedida(v.Medida) || v.CodBatido;
        chipsHtml = `<span class="bp-chip active" style="cursor:default">${esc(label)}${pyHtml}</span>`;
    }

    const grupoTag = prod.NombreGrupo
        ? `<span class="bp-grupo">${esc(prod.NombreGrupo)}</span>` : '';

    barra.innerHTML = `
        <span class="bp-nombre">${esc(prod.Nombre)}</span>
        ${grupoTag}
        ${chipsHtml}`;

    barra.classList.remove('d-none');
}

/* ── Reset contenido lado derecho ────────────────────────────── */
function resetContenido() {
    const barra = document.getElementById('barraProducto');
    if (barra) barra.classList.add('d-none');
    hide('panelTabla');
    hide('panelVacio');
    document.getElementById('spinnerReceta').style.display = 'none';
    document.getElementById('tbodyReceta').innerHTML = '';
}

/* ═══════════════════════════════════════════════════════════════
   TABLA DE INGREDIENTES
   ═══════════════════════════════════════════════════════════════ */
function renderTabla(ingredientes) {
    const tbody = document.getElementById('tbodyReceta');

    if (!ingredientes.length) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-5">
            <i class="fas fa-exclamation-circle me-2"></i>Receta sin ingredientes registrados.</td></tr>`;
        show('panelTabla');
        return;
    }

    tbody.innerHTML = ingredientes.map((ingr, idx) => {
        const tipo = ingr.Tipo || '—';
        const orden = ingr.ordenreceta ?? (idx + 1);
        const filaClass = `fila-${tipo}`;

        // Badge tipo
        const tipoClasses = { B: 'badge-tipo-B', L: 'badge-tipo-L', P: 'badge-tipo-P' };
        const tipoCls = tipoClasses[tipo] || 'badge-tipo-X';
        const tipoBadge = `<span class="badge-tipo ${tipoCls}">${esc(tipo)}</span>`;

        // ── Insumo Receta ──────────────────────────────────────────
        const ir = ingr.insumo_receta;
        const np = ingr.nuevo_producto;
        const escenario = ingr.escenario_erp;
        const recetaTag = `<span class="tag-receta">📋 Receta</span>`;

        let celInsumo;
        if (escenario === 'receta_global' && ir) {
            celInsumo = `<div class="cel-insumo">
                <div class="nom-erp">${esc(ir.NombreNuevo)}${recetaTag}</div>
                <div class="uni-erp">Unidades · 1</div>
            </div>`;
        } else if (ir) {
            const inacTag = ir.activoNuevo === 'NO'
                ? `<span class="tag-inactivo ms-1">INACTIVO</span>` : '';
            celInsumo = `<div class="cel-insumo">
                <div class="nom-erp">${esc(ir.NombreNuevo)}${inacTag}</div>
                <div class="uni-erp">${esc(ir.unidadNueva || '')}${ir.cantidad ? ' · ' + ir.cantidad : ''}</div>
            </div>`;
        } else if (np) {
            celInsumo = `<span class="cel-na"><i class="fas fa-search me-1 text-warning"></i>Sin equiv. de unidad</span>`;
        } else if (ingr.cotizacion) {
            celInsumo = `<span class="cel-na"><i class="fas fa-exclamation-triangle me-1 text-warning"></i>Sin mapeo ERP</span>`;
        } else {
            celInsumo = `<span class="cel-error"><i class="fas fa-times-circle me-1"></i>No resuelto</span>`;
        }

        // ── Cantidad ERP ───────────────────────────────────────────
        let celCantidad = '—';
        if (escenario === 'receta_global') {
            const srCant = parseFloat(ingr.Cantidad);
            celCantidad = isNaN(srCant) ? '—'
                : (srCant % 1 === 0 ? srCant.toString() : srCant.toFixed(4).replace(/\.?0+$/, ''));
        } else if (ir && ir.cantidad != null) {
            const ppCant = parseFloat(ir.cantidad);
            const srCant = parseFloat(ingr.Cantidad);
            const factor = ir.factor_conversion != null ? parseFloat(ir.factor_conversion) : 1;
            const esDirP1 = ingr.metodo_cotizacion === 'directa';
            if (ppCant > 0 && !isNaN(srCant)) {
                const resultado = (srCant * factor) / ppCant;
                if (esDirP1) {
                    const r = Math.round(resultado * 2) / 2;
                    celCantidad = r % 1 === 0 ? r.toString() : r.toFixed(1);
                } else {
                    celCantidad = resultado % 1 === 0
                        ? resultado.toString()
                        : parseFloat(resultado.toFixed(4)).toString();
                }
                if (factor !== 1 && ir.factor_conversion != null) {
                    const exacto = parseFloat(resultado.toFixed(4));
                    celCantidad = `<span title="${ingr.Cantidad} × ${factor} ÷ ${ppCant} = ${exacto}">${celCantidad}</span>`;
                }
            }
        }

        // ── Presentación Uso ───────────────────────────────────────
        let celPresentacion;
        if (np) {
            const inacTag = np.activoNuevo === 'NO'
                ? `<span class="tag-inactivo ms-1">INACTIVO</span>` : '';
            const autoTag = ingr.metodo_resolucion === 'maestro'
                ? `<span style="font-size:.62rem;background:#e8f5e9;color:#2e7d32;border-radius:3px;padding:1px 5px;margin-left:4px">AUTO</span>` : '';
            const npRecetaTag = escenario === 'receta_global' ? recetaTag : '';
            let varHTML = '';
            if (np.variedades && np.variedades.length > 0) {
                varHTML = `<div style="font-size:.72rem;color:#aaa;margin-top:2px">
                    ${np.variedades.map(v => `<span style="margin-right:5px">${esc(v.nombre)}${v.es_principal == 1 ? ' ★' : ''}</span>`).join('')}
                </div>`;
            }
            celPresentacion = `<div class="cel-insumo">
                <div class="nom-erp">${esc(np.NombreNuevo)}${inacTag}${autoTag}${npRecetaTag}</div>
                <div class="uni-erp">${esc(np.unidadNueva || '')}${np.cantidad ? ' · ' + np.cantidad : ''}${np.productoMaestro ? ' — ' + esc(np.productoMaestro) : ''}</div>
                ${varHTML}
            </div>`;
        } else if (ingr.cotizacion) {
            celPresentacion = `<span class="cel-na"><i class="fas fa-exclamation-triangle me-1 text-warning"></i>Sin mapeo</span>`;
        } else {
            celPresentacion = `<span class="cel-error"><i class="fas fa-times-circle me-1"></i>No resuelto</span>`;
        }

        return `<tr class="${filaClass}">
            <td class="text-center"><span class="orden-num">${orden}</span></td>
            <td class="text-center">${tipoBadge}</td>
            <td class="td-sep-left">${celInsumo}</td>
            <td class="text-center fw-bold" style="font-size:.9rem">${celCantidad}</td>
            <td>${celPresentacion}</td>
        </tr>`;
    }).join('');

    show('panelTabla');
}

/* ── Init ────────────────────────────────────────────────────── */
iniciarMenu();
