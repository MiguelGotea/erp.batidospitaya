<?php
/* ============================================================
   BALANCE SEMANAL DE EXISTENCIAS
   modulos/productos/balance_inventario_access_host.php
   ============================================================ */
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('balance_inventario_access_host', 'vista', $cargoOperario)) {
    header('Location: /login.php'); exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balance de Inventario · Pitaya ERP</title>
    <meta name="description" content="Balance semanal de existencias: inventario, ajustes, despacho, merma vs consumo teórico por sucursal.">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1,9999); ?>">
    <link rel="stylesheet" href="css/balance_inventario.css?v=<?php echo mt_rand(1,9999); ?>">
</head>
<body>
<?php echo renderMenuLateral($cargoOperario); ?>
<div class="main-container">
    <div class="sub-container">
        <?php echo renderHeader($usuario, 'Balance Semanal de Existencias'); ?>

        <div class="bi-wrapper">

            <!-- ── FILTROS ─────────────────────────────────────────── -->
            <div class="bi-filtros-card">
                <div class="row g-2 align-items-end">

                    <div class="col-6 col-md-2">
                        <label class="bi-label" for="filtroSemDesde"><i class="fas fa-hashtag me-1"></i>Semana Desde</label>
                        <input type="number" class="form-control form-control-sm bi-input-sem" id="filtroSemDesde" min="1" placeholder="Ej: 14">
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="bi-label" for="filtroSemHasta"><i class="fas fa-hashtag me-1"></i>Semana Hasta</label>
                        <input type="number" class="form-control form-control-sm bi-input-sem" id="filtroSemHasta" min="1" placeholder="Ej: 16">
                    </div>

                    <div class="col-12 col-md-4" style="position:relative">
                        <label class="bi-label"><i class="fas fa-store me-1"></i>Sucursales</label>
                        <div class="bi-suc-trigger" id="biSucTrigger" tabindex="0">
                            <div id="biSucInner">
                                <span id="biSucPlaceholder" style="color:#aaa"><i class="fas fa-store me-1" style="opacity:.4"></i>Todas las sucursales</span>
                                <div id="biSucPills" class="bi-suc-pills" style="display:none"></div>
                            </div>
                            <i class="fas fa-chevron-down ms-2" id="biSucChevron" style="color:#aaa;font-size:.75rem"></i>
                        </div>
                        <div class="bi-suc-dropdown" id="biSucDropdown">
                            <input type="text" class="bi-suc-search" id="biSucSearch" placeholder="Buscar sucursal…">
                            <div class="bi-suc-actions">
                                <button class="bi-suc-action-btn" id="biSucAll"><i class="fas fa-check-double me-1"></i>Todas</button>
                                <button class="bi-suc-action-btn" id="biSucNone"><i class="fas fa-times me-1"></i>Ninguna</button>
                            </div>
                            <div id="biSucList"></div>
                        </div>
                        <select id="filtroSucursales" multiple style="display:none"></select>
                    </div>

                    <div class="col-12 col-md-4 d-flex align-items-center gap-2 justify-content-end flex-wrap">
                        <div id="biBadgeSem" class="bi-badge-sem d-none">
                            <i class="fas fa-calendar-check me-1"></i>Sem. Actual: <strong id="biSemActualNum">—</strong>
                        </div>
                        <button class="bi-btn-primary" id="btnAnalizar">
                            <i class="fas fa-balance-scale me-1"></i>Calcular Balance
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── ESTADO VACÍO ────────────────────────────────────── -->
            <div id="panelInicial" class="text-center py-5 text-muted">
                <div class="bi-empty-icon"><i class="fas fa-balance-scale"></i></div>
                <h5 class="mt-2">Configura los filtros</h5>
                <p class="small">Selecciona el rango de semanas y haz clic en <strong>Calcular Balance</strong>.</p>
            </div>

            <!-- ── LOADER ──────────────────────────────────────────── -->
            <div id="panelLoader" class="bi-loader-wrap d-none">
                <div class="spinner-border text-success" role="status"></div>
                <div>Calculando balance…<br><small class="text-muted">Cruzando kardex con ventas…</small></div>
            </div>

            <!-- ── PANEL DATOS ─────────────────────────────────────── -->
            <div id="panelDatos" class="d-none">

                <!-- KPIs -->
                <div class="bi-kpi-row" id="kpiRow"></div>

                <!-- Tabla -->
                <div class="bi-table-card">
                    <div class="bi-toolbar">
                        <div>
                            <span class="bi-toolbar-title"><i class="fas fa-table me-1"></i>Balance por Producto</span>
                            <span class="text-muted ms-2 small" id="lblResultados"></span>
                        </div>
                        <div class="d-flex gap-2 align-items-center flex-wrap">
                            <!-- Selector de sucursal activa -->
                            <div class="bi-suc-tabs" id="tabsSucursal" style="padding:0;border:none;background:none"></div>
                            <input type="text" class="bi-search" id="buscarProducto" placeholder="Buscar producto…">
                        </div>
                    </div>

                    <div class="bi-table-responsive">
                        <table class="bi-table" id="tablaBalance">
                            <thead>
                                <tr>
                                    <th rowspan="2" style="width:120px">Categoría</th>
                                    <th rowspan="2" style="min-width:220px">Producto ERP</th>
                                    <th class="th-group td-num" style="width:95px">Inv. Inicial</th>
                                    <th class="th-group td-num" style="width:85px">+ Ajuste</th>
                                    <th class="th-group td-num" style="width:85px">+ Despacho</th>
                                    <th class="th-group td-num" style="width:85px">+ Compras</th>
                                    <th class="th-group td-num" style="width:85px">− Merma</th>
                                    <th class="th-group td-num" style="width:95px">− Inv. Final</th>
                                    <th class="th-group td-num" style="width:100px;background:#0b4a42;color:#fff">= C. Real</th>
                                    <th class="th-group td-num" style="width:100px;background:#0b4a42;color:#fff">C. Teórico</th>
                                    <th class="th-group td-num" style="width:90px;background:#0b4a42;color:#fff">Varianza</th>
                                    <th class="th-group td-center" style="width:70px;background:#0b4a42;color:#fff">% Var</th>
                                    <th class="th-group td-center" style="width:80px">Det.</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyBalance"></tbody>
                        </table>
                    </div>

                    <div class="bi-legend">
                        <span class="bi-legend-item"><span class="bi-legend-dot" style="background:#27ae60"></span>Varianza ≤ 5%</span>
                        <span class="bi-legend-item"><span class="bi-legend-dot" style="background:#e67e22"></span>5% &lt; Varianza ≤ 15%</span>
                        <span class="bi-legend-item"><span class="bi-legend-dot" style="background:#e74c3c"></span>Varianza &gt; 15%</span>
                        <span class="ms-auto text-muted">Consumo Real = Inv.Inicial + Ajuste + Despacho + Compras − Merma − Inv.Final</span>
                    </div>
                </div>
            </div>

        </div><!-- /bi-wrapper -->

        <!-- ── MODAL AYUDA ──────────────────────────────────────────── -->
        <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLbl" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header" style="background:#0E544C;color:#fff">
                        <h5 class="modal-title" id="pageHelpModalLbl">
                            <i class="fas fa-question-circle me-2"></i>¿Cómo funciona el Balance Semanal de Existencias?
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">

                        <!-- ROW 1: Fórmula + Fuentes de datos -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="card h-100 border-0 bg-light">
                                    <div class="card-body">
                                        <h6 class="fw-bold border-bottom pb-2" style="color:#0E544C">
                                            <i class="fas fa-calculator me-2"></i>Fórmula del Balance
                                        </h6>
                                        <p class="small text-muted mb-1">
                                            El <strong>Consumo Real</strong> se calcula a partir del kardex sincronizado desde Access:
                                        </p>
                                        <div class="bg-white rounded p-2 mb-2" style="font-family:monospace;font-size:.8rem;border-left:3px solid #0E544C">
                                            C.Real = Inv.Inicial + Ajuste + Despacho + Compras − Merma − Inv.Final
                                        </div>
                                        <p class="small text-muted mb-0">
                                            La <strong>Varianza</strong> = C.Real − C.Teórico.<br>
                                            Varianza positiva = se consumió más de lo esperado.<br>
                                            Varianza negativa = se consumió menos de lo esperado.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 border-0 bg-light">
                                    <div class="card-body">
                                        <h6 class="fw-bold border-bottom pb-2" style="color:#0E544C">
                                            <i class="fas fa-database me-2"></i>Fuentes de Datos (Kardex Access)
                                        </h6>
                                        <ul class="small text-muted mb-0 ps-3">
                                            <li><strong>Inv. Inicial</strong> — <code>msaccess_masivo_InventarioCotizacion</code> de la semana anterior al rango.</li>
                                            <li><strong>Ajuste</strong> — <code>msaccess_masivo_AjustesInventario</code> en el período.</li>
                                            <li><strong>Despacho</strong> — <code>msaccess_masivo_SubPreIngresosPitaya</code> (PreIngresoPitaya.Destino = "Pitaya N").</li>
                                            <li><strong>Compras</strong> — <code>msaccess_masivo_Compras</code> (ingresos de almacén en el período).</li>
                                            <li><strong>Merma</strong> — <code>msaccess_masivo_MermaCotizacion</code> en el período.</li>
                                            <li><strong>Inv. Final</strong> — <code>msaccess_masivo_InventarioCotizacion</code> de la última semana del rango.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ROW 2: Consumo Teórico -->
                        <div class="row g-3 mb-3">
                            <div class="col-12">
                                <div class="card border-0 bg-light">
                                    <div class="card-body">
                                        <h6 class="fw-bold border-bottom pb-2" style="color:#0E544C">
                                            <i class="fas fa-cogs me-2"></i>Consumo Teórico — Traducción Access→ERP (3 etapas)
                                        </h6>
                                        <p class="small text-muted mb-1">
                                            El consumo teórico se calcula cruzando <code>VentasGlobalesAccessCSV × SubReceta</code> y
                                            traduciendo cada ingrediente a su <strong>Presentación de Consumo</strong>
                                            (<code>presentacion_basica_inventario = 1</code>). La cotización se resuelve vía
                                            <strong>P1</strong> (codporcion directo) · <strong>P2</strong> (cotización base Conversion=1, Prioridad=1) ·
                                            <strong>P3</strong> (fallback). Luego se localiza la presentación básica en <strong>3 etapas en cascada</strong>:
                                        </p>
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <div class="bg-white rounded p-2 h-100" style="border-left:3px solid #27ae60">
                                                    <div class="fw-bold small mb-1" style="color:#27ae60">Paso A — Mapeo directo</div>
                                                    <p class="small text-muted mb-0">
                                                        La cotización en el diccionario ya apunta a una presentación con
                                                        <code>basica_inventario = 1</code>. Caso más común.
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="bg-white rounded p-2 h-100" style="border-left:3px solid #2980b9">
                                                    <div class="fw-bold small mb-1" style="color:#2980b9">Paso B — Rastreo por maestro</div>
                                                    <p class="small text-muted mb-0">
                                                        La presentación mapeada es de despacho/otra (basica=0). Se obtiene su
                                                        <code>id_producto_maestro</code> y se busca la presentación básica del mismo maestro.<br>
                                                        <em>Ej: Chocolate pote 1.36kg → maestro → oz ✅</em>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="bg-white rounded p-2 h-100" style="border-left:3px solid #e67e22">
                                                    <div class="fw-bold small mb-1" style="color:#e67e22">Paso C — Rastreo vía CodIngrediente</div>
                                                    <p class="small text-muted mb-0">
                                                        Réplica exacta del <strong>AUTO</strong> del Visor de Recetas. Para productos donde
                                                        la presentación mapeada no tiene FK de maestro.<br>
                                                        Traza: <em>CodCotizacion → CodIngrediente → todas sus cotizaciones → cualquier
                                                        presentación con maestro → presentación básica</em>.<br>
                                                        <em>Ej: Maní Horneado 1lb (sin maestro FK) → oz ✅</em>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <p class="small text-muted mt-2 mb-1">
                                            Consumo = <code>(Cantidad_receta × factor_conversión) / pp_cantidad × ventas</code>.
                                            Redondeo: P1 → múltiplo de 0.5 | P2/P3 → 4 decimales.
                                        </p>
                                        <div class="p-2 rounded" style="background:#e8eaf6;border-left:3px solid #283593;font-size:.78rem">
                                            <strong style="color:#283593"><i class="fas fa-info-circle me-1"></i>Nota técnica — Cascada de Despacho:</strong>
                                            Las búsquedas usan <code>LEFT JOIN producto_maestro</code> (no INNER JOIN).
                                            El <strong>Fallback 2</strong> (receta de 1 componente = Presentación Uso)
                                            se ejecuta <strong>siempre</strong>, incluso cuando
                                            <code>id_producto_maestro = NULL</code> en el producto de uso.
                                            Esto cubre dos casos: (a) paquetes con maestro diferente al uso
                                            (ej: <em>Ristra Vaso</em>), y (b) productos de uso que son recetas sin maestro
                                            (ej: <em>Mix de Waffle → Paquete Mix Waffle 10u</em>).
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ROW 3: Varianza + Tips -->
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="card h-100 border-0 bg-light">
                                    <div class="card-body">
                                        <h6 class="fw-bold border-bottom pb-2" style="color:#0E544C">
                                            <i class="fas fa-traffic-light me-2"></i>Semáforo de Varianza
                                        </h6>
                                        <ul class="small text-muted mb-0 ps-3">
                                            <li><span style="color:#27ae60">●</span> <strong>Verde (≤ 5%)</strong> — Consumo alineado. Sin acción requerida.</li>
                                            <li><span style="color:#e67e22">●</span> <strong>Naranja (5%–15%)</strong> — Desviación moderada. Revisar mermas o registros.</li>
                                            <li><span style="color:#e74c3c">●</span> <strong>Rojo (&gt;15%)</strong> — Desviación alta. Investigar diferencias de inventario o errores de mapeo.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 border-0 bg-light">
                                    <div class="card-body">
                                        <h6 class="fw-bold border-bottom pb-2" style="color:#0E544C">
                                            <i class="fas fa-lightbulb me-2"></i>Tips de uso
                                        </h6>
                                        <ul class="small text-muted mb-0 ps-3">
                                            <li>Usa un rango de <strong>1 semana</strong> para diagnóstico preciso.</li>
                                            <li>Si un producto muestra C.Teórico = 0, verifica que tenga su presentación básica mapeada en el Diccionario de Productos.</li>
                                            <li>Si el C.Real = 0, verifica que Access haya sincronizado los datos de inventario y kardex para el período.</li>
                                            <li>Usa el botón <i class="fas fa-list-ul"></i> para ver el detalle de registros del kardex.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div><!-- /modal-body -->
                    <div class="modal-footer">
                        <small class="text-muted me-auto">
                            <i class="fas fa-book me-1"></i>Referencia técnica completa:
                            <code>modulos/productos/guia_reportes_consumo.md</code>
                        </small>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- /MODAL AYUDA -->
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
/* ============================================================
   BALANCE INVENTARIO — JS
   ============================================================ */
const AJAX = 'ajax/';
let datosGlobales = null;
let sucActiva = null;
let todasSucursales = [];

// ── Formateo ──────────────────────────────────────────────────
const fmt = (v, dec=2) => v===null||v===undefined ? '—' : parseFloat(v).toLocaleString('es',{minimumFractionDigits:dec,maximumFractionDigits:dec});
const fmtPct = v => v===null||v===undefined ? '—' : (v>0?'+':'')+parseFloat(v).toFixed(1)+'%';

function varClass(pct) {
    if (pct===null||pct===undefined) return 'var-neutral';
    const a = Math.abs(pct);
    if (a<=5)  return 'var-ok';
    if (a<=15) return 'var-warn';
    return 'var-bad';
}
function varCellClass(pct) {
    if (pct===null) return '';
    const a = Math.abs(pct);
    if (a<=5)  return 'var-cell-ok';
    if (a<=15) return 'var-cell-warn';
    return 'var-cell-bad';
}

// ── Sucursal selector ─────────────────────────────────────────
let sucSeleccionadas = {};

function buildSucList(data) {
    const list = document.getElementById('biSucList');
    list.innerHTML = '';
    data.forEach(s => {
        const div = document.createElement('div');
        div.className = 'bi-suc-item';
        div.innerHTML = `<input type="checkbox" value="${s.codigo}" id="bsuc${s.codigo}">
            <label for="bsuc${s.codigo}" style="cursor:pointer;flex:1">${s.nombre}</label>`;
        div.querySelector('input').addEventListener('change', e => {
            if (e.target.checked) sucSeleccionadas[s.codigo] = s.nombre;
            else delete sucSeleccionadas[s.codigo];
            updateSucPills();
        });
        list.appendChild(div);
    });
}

function updateSucPills() {
    const pills  = document.getElementById('biSucPills');
    const ph     = document.getElementById('biSucPlaceholder');
    const codes  = Object.keys(sucSeleccionadas);
    if (codes.length === 0) { pills.style.display='none'; ph.style.display=''; return; }
    pills.style.display='flex'; ph.style.display='none';
    pills.innerHTML = codes.map(c =>
        `<span class="bi-pill">${sucSeleccionadas[c]} <span class="bi-pill-x" data-cod="${c}">×</span></span>`
    ).join('');
    pills.querySelectorAll('.bi-pill-x').forEach(x => {
        x.addEventListener('click', e => {
            e.stopPropagation();
            const cod = x.dataset.cod;
            delete sucSeleccionadas[cod];
            const cb = document.querySelector(`#bsuc${cod}`);
            if (cb) cb.checked = false;
            updateSucPills();
        });
    });
}

// Toggle dropdown
document.getElementById('biSucTrigger').addEventListener('click', () => {
    const dd = document.getElementById('biSucDropdown');
    dd.classList.toggle('open');
    document.getElementById('biSucChevron').className = dd.classList.contains('open')
        ? 'fas fa-chevron-up ms-2' : 'fas fa-chevron-down ms-2';
});
document.addEventListener('click', e => {
    if (!e.target.closest('#biSucTrigger') && !e.target.closest('#biSucDropdown')) {
        document.getElementById('biSucDropdown').classList.remove('open');
        document.getElementById('biSucChevron').className='fas fa-chevron-down ms-2';
    }
});
document.getElementById('biSucSearch').addEventListener('input', e => {
    const q = e.target.value.toLowerCase();
    document.querySelectorAll('#biSucList .bi-suc-item').forEach(item => {
        const lbl = item.querySelector('label').textContent.toLowerCase();
        item.style.display = lbl.includes(q) ? '' : 'none';
    });
});
document.getElementById('biSucAll').addEventListener('click', () => {
    document.querySelectorAll('#biSucList input[type=checkbox]').forEach(cb => {
        cb.checked = true;
        sucSeleccionadas[cb.value] = cb.closest('.bi-suc-item').querySelector('label').textContent;
    });
    updateSucPills();
});
document.getElementById('biSucNone').addEventListener('click', () => {
    document.querySelectorAll('#biSucList input[type=checkbox]').forEach(cb => cb.checked=false);
    sucSeleccionadas = {};
    updateSucPills();
});

// ── Cargar filtros al inicio ──────────────────────────────────
fetch(AJAX+'balance_inventario_get_filtros.php')
    .then(r=>r.json()).then(res=>{
        if (!res.ok) return;
        todasSucursales = res.sucursales||[];
        buildSucList(todasSucursales);
        if (res.semana_actual) {
            const sem = res.semana_actual.numero_semana;
            document.getElementById('biSemActualNum').textContent = sem;
            document.getElementById('biBadgeSem').classList.remove('d-none');
            document.getElementById('filtroSemDesde').value = sem;
            document.getElementById('filtroSemHasta').value = sem;
        }
    });

// ── Analizar ──────────────────────────────────────────────────
document.getElementById('btnAnalizar').addEventListener('click', cargarBalance);
[document.getElementById('filtroSemDesde'), document.getElementById('filtroSemHasta')].forEach(el => {
    el.addEventListener('keydown', e => { if (e.key==='Enter') cargarBalance(); });
});

function cargarBalance() {
    const semD = parseInt(document.getElementById('filtroSemDesde').value)||0;
    const semH = parseInt(document.getElementById('filtroSemHasta').value)||0;
    if (!semD || !semH) {
        Swal.fire({icon:'warning',title:'Filtros incompletos',text:'Ingresa los números de semana.',confirmButtonColor:'#0E544C'});
        return;
    }
    document.getElementById('panelInicial').classList.add('d-none');
    document.getElementById('panelDatos').classList.add('d-none');
    document.getElementById('panelLoader').classList.remove('d-none');

    const fd = new FormData();
    fd.append('semana_desde', Math.min(semD,semH));
    fd.append('semana_hasta', Math.max(semD,semH));
    Object.keys(sucSeleccionadas).forEach(c => fd.append('sucursales[]', c));

    fetch(AJAX+'balance_inventario_get_datos.php', {method:'POST', body:fd})
        .then(r=>r.json()).then(res=>{
            document.getElementById('panelLoader').classList.add('d-none');
            if (!res.ok) {
                Swal.fire({icon:'error',title:'Error',text:res.msg,confirmButtonColor:'#0E544C'});
                document.getElementById('panelInicial').classList.remove('d-none');
                return;
            }
            datosGlobales = res;
            renderDatos(res);
        })
        .catch(()=>{
            document.getElementById('panelLoader').classList.add('d-none');
            document.getElementById('panelInicial').classList.remove('d-none');
            Swal.fire({icon:'error',title:'Error de red',text:'No se pudo conectar con el servidor.',confirmButtonColor:'#0E544C'});
        });
}

// ── Render datos ──────────────────────────────────────────────
function renderDatos(res) {
    const productos  = res.productos || [];
    const sucursales = res.sucursales || [];

    // Tabs de sucursal
    const tabsEl = document.getElementById('tabsSucursal');
    tabsEl.innerHTML = '';
    if (sucursales.length > 1) {
        const btnAll = document.createElement('button');
        btnAll.className = 'bi-suc-tab active';
        btnAll.dataset.cod = '__all__';
        btnAll.textContent = 'Todas';
        btnAll.addEventListener('click', ()=>setActiveSuc('__all__'));
        tabsEl.appendChild(btnAll);
    }
    sucursales.forEach(s => {
        const btn = document.createElement('button');
        btn.className = 'bi-suc-tab';
        btn.dataset.cod = s.codigo;
        btn.textContent = s.nombre;
        btn.addEventListener('click', ()=>setActiveSuc(s.codigo));
        tabsEl.appendChild(btn);
    });
    sucActiva = sucursales.length===1 ? sucursales[0].codigo : '__all__';

    // KPIs globales
    renderKPIs(productos, sucursales);

    // Tabla
    document.getElementById('lblResultados').textContent = `${productos.length} productos`;
    renderTabla(productos);

    document.getElementById('panelDatos').classList.remove('d-none');
}

function setActiveSuc(cod) {
    sucActiva = cod;
    document.querySelectorAll('#tabsSucursal .bi-suc-tab').forEach(b => {
        b.classList.toggle('active', b.dataset.cod === cod);
    });
    renderTabla(datosGlobales.productos);
}

function getValores(prod, suc) {
    if (suc === '__all__') return prod.totales;
    return prod.por_sucursal?.[suc] ?? {inv_inicial:0,ajuste:0,despacho:0,compras:0,merma:0,inv_final:0,consumo_real:0,consumo_teorico:0,varianza:0,pct_varianza:null};
}

function renderKPIs(productos, sucursales) {
    const row = document.getElementById('kpiRow');
    let totalReal=0, totalTeorico=0, countOk=0, countBad=0;
    productos.forEach(p => {
        const v = p.totales;
        totalReal += v.consumo_real||0;
        totalTeorico += v.consumo_teorico||0;
        const pct = Math.abs(v.pct_varianza||0);
        if (pct<=5) countOk++; else if (pct>15) countBad++;
    });
    const varTotal = totalReal - totalTeorico;
    const pctTotal = totalTeorico ? (varTotal/totalTeorico*100) : null;
    row.innerHTML = `
        <div class="bi-kpi-card">
            <div class="bi-kpi-icon" style="background:${Math.abs(pctTotal||0)<=5?'#27ae60':Math.abs(pctTotal||0)<=15?'#e67e22':'#e74c3c'}"><i class="fas fa-balance-scale"></i></div>
            <div><div class="bi-kpi-label">Varianza Global</div><div class="bi-kpi-val ${varClass(pctTotal)}">${fmtPct(pctTotal)}</div></div>
        </div>
        <div class="bi-kpi-card">
            <div class="bi-kpi-icon" style="background:#27ae60"><i class="fas fa-check-circle"></i></div>
            <div><div class="bi-kpi-label">Productos OK (≤5%)</div><div class="bi-kpi-val">${countOk}</div></div>
        </div>
        <div class="bi-kpi-card">
            <div class="bi-kpi-icon" style="background:#e74c3c"><i class="fas fa-exclamation-triangle"></i></div>
            <div><div class="bi-kpi-label">Productos Alertas (&gt;15%)</div><div class="bi-kpi-val" style="color:#e74c3c">${countBad}</div></div>
        </div>
    `;
}

function renderTabla(productos) {
    const q = (document.getElementById('buscarProducto').value||'').toLowerCase();
    const suc = sucActiva;
    const tbody = document.getElementById('tbodyBalance');
    tbody.innerHTML = '';

    const filtrados = productos.filter(p =>
        !q || p.nombre.toLowerCase().includes(q) || (p.maestro||'').toLowerCase().includes(q) || (p.categoria||'').toLowerCase().includes(q)
    ).sort((a, b) => {
        const catA = (a.categoria || '').toLowerCase();
        const catB = (b.categoria || '').toLowerCase();
        if (catA < catB) return -1;
        if (catA > catB) return 1;
        const nomA = (a.nombre || '').toLowerCase();
        const nomB = (b.nombre || '').toLowerCase();
        return nomA.localeCompare(nomB);
    });
    if (!filtrados.length) {
        tbody.innerHTML = `<tr><td colspan="13" class="text-center text-muted py-4">Sin productos con ese filtro.</td></tr>`;
        return;
    }

    filtrados.forEach((p, i) => {
        const v = getValores(p, suc);
        const pct = v.pct_varianza;
        const vc  = varClass(pct);
        const vcc = varCellClass(pct);
        const rowId = 'brow_'+i;

        // Main row
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                ${p.categoria?`<span class="bi-badge-cat">${esc(p.categoria)}</span>`:''}
            </td>
            <td>
                <div style="font-weight:600;font-size:.82rem">${esc(p.nombre)}</div>
            </td>
            <td class="td-num">${fmt(v.inv_inicial)}</td>
            <td class="td-num" style="color:${v.ajuste>=0?'#27ae60':'#e74c3c'}">${fmt(v.ajuste)}</td>
            <td class="td-num" style="color:#2980b9">${fmt(v.despacho)}</td>
            <td class="td-num" style="color:#17a589;font-weight:600">${fmt(v.compras)}</td>
            <td class="td-num" style="color:#e74c3c">${fmt(v.merma)}</td>
            <td class="td-num">${fmt(v.inv_final)}</td>
            <td class="td-num" style="font-weight:700">${fmt(v.consumo_real)}</td>
            <td class="td-num">${fmt(v.consumo_teorico)}</td>
            <td class="td-num ${vc}">${fmt(v.varianza)}</td>
            <td class="td-center ${vcc} ${vc}">${fmtPct(pct)}</td>
            <td class="td-center" style="white-space:nowrap">
                <a class="bi-detail-btn" id="lnk_${rowId}"
                   href="balance_inventario_detalle.php?id=${p.id}&sem_desde=${datosGlobales.semanas[0]?.numero_semana||0}&sem_hasta=${datosGlobales.semanas[datosGlobales.semanas.length-1]?.numero_semana||0}&sucs=${Object.keys(sucSeleccionadas).join(',')}"
                   title="Ver detalle de registros" target="_blank">
                    <i class="fas fa-list-ul"></i>
                </a>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// Búsqueda en tiempo real
document.getElementById('buscarProducto').addEventListener('input', () => {
    if (datosGlobales) renderTabla(datosGlobales.productos);
});
</script>
</body>
</html>
