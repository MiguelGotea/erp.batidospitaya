<?php
/* ============================================================
   DETALLE DE BALANCE — por producto
   modulos/productos/balance_inventario_detalle.php
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

$idPP     = isset($_GET['id'])        ? (int)$_GET['id']        : 0;
$semDesde = isset($_GET['sem_desde']) ? (int)$_GET['sem_desde'] : 0;
$semHasta = isset($_GET['sem_hasta']) ? (int)$_GET['sem_hasta'] : 0;
$sucsRaw  = isset($_GET['sucs'])      ? trim($_GET['sucs'])      : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Balance · Pitaya ERP</title>
    <meta name="description" content="Detalle de registros kardex que componen el balance semanal de un producto.">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1,9999); ?>">
    <link rel="stylesheet" href="css/balance_inventario.css?v=<?php echo mt_rand(1,9999); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ── Detalle Neumorphism Overrides ───────────────────────── */
        :root {
            --neu-bg: #f6f6f6;
            --neu-shadow-dark: #d6d6d6;
            --neu-shadow-light: #ffffff;
            --neu-accent: #51B8AC;
            --neu-text-primary: #455a64;
            --neu-text-secondary: #757575;
            --neu-radius: 20px;
            --neu-blur: 14px;
            --neu-distance: 8px;

            /* Specific component colors */
            --bd-pos: #51B8AC;
            --bd-neg: #e76f51;
            --bd-neutral: #457b9d;
            --bd-final: #6d597a;
        }

        body { 
            background-color: var(--neu-bg); 
            color: var(--neu-text-primary);
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        .bd-header-card {
            background: var(--neu-bg);
            border-radius: var(--neu-radius); 
            padding: 1.5rem;
            margin-bottom: 1.5rem; 
            display: flex; 
            flex-wrap: wrap;
            align-items: center; 
            gap: 1rem; 
            justify-content: space-between;
            box-shadow: var(--neu-distance) var(--neu-distance) var(--neu-blur) var(--neu-shadow-dark), 
                        calc(-1 * var(--neu-distance)) calc(-1 * var(--neu-distance)) var(--neu-blur) var(--neu-shadow-light);
        }
        .bd-header-card h2 { font-size: 1.4rem; font-weight: 800; color: #0E544C; margin: 0; }
        .bd-header-card .bd-meta { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .8rem; }
        
        .bd-pill-meta { 
            background: var(--neu-bg); 
            border-radius: 30px;
            padding: .4rem 1rem; 
            font-size: .75rem; 
            color: var(--neu-accent); 
            font-weight: 600;
            box-shadow: inset 2px 2px 5px var(--neu-shadow-dark), 
                        inset -2px -2px 5px var(--neu-shadow-light);
        }

        .bd-resumen-card {
            background: var(--neu-bg); 
            border-radius: var(--neu-radius);
            padding: 1.5rem; 
            margin-bottom: 1.5rem;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 1.2rem;
            box-shadow: var(--neu-distance) var(--neu-distance) var(--neu-blur) var(--neu-shadow-dark), 
                        calc(-1 * var(--neu-distance)) calc(-1 * var(--neu-distance)) var(--neu-blur) var(--neu-shadow-light);
        }
        .bd-resumen-item { 
            background: var(--neu-bg);
            padding: 15px; 
            border-radius: 16px;
            box-shadow: inset 4px 4px 8px var(--neu-shadow-dark), 
                        inset -4px -4px 8px var(--neu-shadow-light);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .bd-resumen-label { 
            font-size: .65rem; 
            color: var(--neu-text-secondary); 
            text-transform: uppercase; 
            letter-spacing: .1em; 
            margin-bottom: .4rem; 
            font-weight: 700; 
        }
        .bd-resumen-val { 
            font-size: 1.3rem; 
            font-weight: 800; 
            color: var(--neu-text-primary); 
        }

        .bd-chart-card {
            background: var(--neu-bg); 
            border-radius: var(--neu-radius);
            padding: 1.8rem; 
            margin-bottom: 1.5rem;
            box-shadow: var(--neu-distance) var(--neu-distance) var(--neu-blur) var(--neu-shadow-dark), 
                        calc(-1 * var(--neu-distance)) calc(-1 * var(--neu-distance)) var(--neu-blur) var(--neu-shadow-light);
        }

        .bd-section { margin-bottom: 2rem; }
        .bd-section-title {
            display: flex; 
            align-items: center; 
            gap: .7rem;
            font-size: .9rem; 
            font-weight: 800; 
            letter-spacing: .05em;
            text-transform: uppercase; 
            margin-bottom: 1.2rem;
            padding: .9rem 1.4rem; 
            border-radius: 16px;
            background: var(--neu-bg);
            box-shadow: 6px 6px 12px var(--neu-shadow-dark), 
                        -6px -6px 12px var(--neu-shadow-light);
        }
        .bd-sec-inv_inicial  { color: var(--bd-neutral); }
        .bd-sec-ajuste       { color: var(--bd-pos); }
        .bd-sec-despacho     { color: var(--neu-accent); }
        .bd-sec-compras      { color: var(--bd-pos); }
        .bd-sec-merma        { color: var(--bd-neg); }
        .bd-sec-inv_final    { color: var(--bd-final); }

        .bd-sec-wrap { 
            background: var(--neu-bg); 
            border-radius: var(--neu-radius); 
            overflow: hidden; 
            box-shadow: inset 6px 6px 12px var(--neu-shadow-dark), 
                        inset -6px -6px 12px var(--neu-shadow-light);
            padding: 12px;
        }
        .bd-tbl-wrap { max-height: 400px; overflow-y: auto; border-radius: 12px; }
        
        .bd-tbl { width: 100%; font-size: .8rem; border-collapse: separate; border-spacing: 0; }
        .bd-tbl thead th {
            background: #f0f2f5; color: #666; padding: .8rem .7rem;
            font-weight: 800; text-transform: uppercase; letter-spacing: .04em;
            position: sticky; top: 0; z-index: 1; font-size: .7rem;
            border-bottom: 2px solid var(--neu-shadow-dark);
        }
        .bd-tbl td { padding: .6rem .7rem; color: var(--neu-text-primary); border-bottom: 1px solid rgba(0,0,0,0.03); vertical-align: middle; }
        .bd-tbl tbody tr:hover td { background: rgba(81, 184, 172, 0.05); }
        .bd-tbl .td-num { text-align: right; font-variant-numeric: tabular-nums; font-weight: 600; }
        .bd-tbl .td-center { text-align: center; }

        .bd-conv-badge {
            display: inline-block; padding: .2rem .6rem; border-radius: 20px;
            font-size: .65rem; font-weight: 700; letter-spacing: .03em;
            box-shadow: 2px 2px 4px var(--neu-shadow-dark), -2px -2px 4px var(--neu-shadow-light);
        }
        .bd-conv-base       { background: #e8f5e9; color: #2e7d32; }
        .bd-conv-cascada    { background: #e3f2fd; color: #1565c0; }
        .bd-conv-alternativa{ background: #fff3e0; color: #ef6c00; }

        .bd-totales-row td { 
            background: #f8f9fa; 
            font-weight: 800; 
            color: #0E544C; 
            border-top: 2px solid var(--neu-shadow-dark); 
        }
        .bd-empty { text-align: center; padding: 2.5rem; color: #999; font-size: .85rem; font-style: italic; }

        .bi-search {
            background: var(--neu-bg); 
            border: none; 
            border-radius: 14px;
            padding: .8rem 1.2rem; 
            font-size: .9rem; 
            color: var(--neu-text-primary);
            box-shadow: inset 4px 4px 8px var(--neu-shadow-dark), 
                        inset -4px -4px 8px var(--neu-shadow-light);
            transition: all .2s ease;
        }
        .bi-search:focus { 
            outline: none; 
            box-shadow: inset 5px 5px 10px var(--neu-shadow-dark), 
                        inset -5px -5px 10px var(--neu-shadow-light);
            border-left: 4px solid var(--neu-accent);
        }

        /* ── Consumo Teórico (Auditoría por Facturación) ─────── */
        .bd-sec-consumo_teo { color: #e65100; }
        .bd-audit-badge {
            display:inline-block; font-size:.63rem; border-radius:3px;
            padding:1px 5px; font-weight:700; line-height:1.4;
        }
        .bd-audit-p1 { background:#c8e6c9; color:#1b5e20; }
        .bd-audit-p2 { background:#bbdefb; color:#0d47a1; }
        .bd-audit-p3 { background:#ffe0b2; color:#bf360c; }
        .bd-cteo-info {
            display:flex; flex-wrap:wrap; gap:.6rem 1.5rem;
            font-size:.8rem; padding:1.2rem;
            background:var(--neu-bg); border-radius:14px;
            margin-bottom:1.2rem;
            box-shadow: inset 4px 4px 8px var(--neu-shadow-dark), 
                        inset -4px -4px 8px var(--neu-shadow-light);
            border-left: 5px solid #e65100;
        }
        .bd-cteo-legend { display:flex; flex-wrap:wrap; gap:.8rem 1.5rem; font-size:.74rem; margin-bottom:1.2rem; align-items:center; }
        .bd-cteo-alerta {
            padding:1rem 1.2rem; border-radius:12px; font-size:.8rem;
            margin-bottom:1.2rem;
            box-shadow: 4px 4px 10px var(--neu-shadow-dark), 
                        -4px -4px 10px var(--neu-shadow-light);
        }
        .bd-cteo-alerta.warn { background:#fffcf0; border-left:4px solid #f9a825; color:#795548; }
        .bd-cteo-alerta.ok   { background:#f6fbf6; border-left:4px solid #43a047; color:#2e7d32; }
    </style>

</head>
<body>
<?php echo renderMenuLateral($cargoOperario); ?>
<div class="main-container">
    <div class="sub-container">
        <?php echo renderHeader($usuario, false, 'Detalle de Balance'); ?>

        <div class="bi-wrapper">

            <!-- header info -->
            <div id="bdHeaderCard" class="bd-header-card" style="display:none">
                <div>
                    <div style="font-size:.75rem;color:#51B8AC;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.3rem;font-weight:700">Detalle Analítico de Producto</div>
                    <h2 id="bdNombreProd">—</h2>
                    <div class="bd-meta" id="bdMetaRow"></div>
                </div>
            </div>

            <!-- Loader -->
            <div id="bdLoader" class="bi-loader-wrap text-center">
                <div class="spinner-border text-success mb-3" role="status"></div>
                <div class="fw-bold">Consultando registros históricos…</div>
                <div class="small text-muted">Buscando en todas las tablas del Kardex sincronizado</div>
            </div>

            <!-- Resumen Neumorphic -->
            <div id="bdResumen" class="bd-resumen-card d-none"></div>

            <!-- Gráfico de Existencia -->
            <div id="bdChartWrap" class="bd-chart-card d-none">
                <div class="bd-section-title" style="box-shadow:none; padding:0; margin-bottom:1.5rem;">
                    <i class="fas fa-chart-line" style="color:#51B8AC"></i>
                    Movimiento de Existencia (Kardex)
                </div>
                <div style="height:320px; position:relative;">
                    <canvas id="existenciaChart"></canvas>
                </div>
            </div>

            <!-- Secciones -->
            <div id="bdSecciones"></div>

            <!-- ── Consumo Teórico (Auditoría Venta × Venta) ─── -->
            <div id="bdConsumoTeoWrap" class="bd-section" style="display:none">
                <div class="bd-section-title bd-sec-consumo_teo">
                    <i class="fas fa-microscope"></i>
                    Consumo Teórico por Facturación
                    <span style="margin-left:.5rem;font-size:.72rem;opacity:.6;font-weight:400;font-style:italic">Auditoría Venta × Venta</span>
                    <span id="bdCteoSubtitle" style="margin-left:auto;font-size:.72rem;opacity:.7;font-weight:600">Cargando…</span>
                </div>
                <div class="bd-sec-wrap">
                    <div id="bdCteoLoader" class="text-center py-4">
                        <div class="spinner-border" role="status" style="width:1.4rem;height:1.4rem;color:#e65100"></div>
                        <div class="small text-muted mt-2">Calculando consumo por facturación…</div>
                    </div>
                    <div id="bdCteoContent" style="display:none"></div>
                </div>
            </div>


        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
const AJAX = 'ajax/';
const ID_PP     = <?php echo $idPP; ?>;
const SEM_DESDE = <?php echo $semDesde; ?>;
const SEM_HASTA = <?php echo $semHasta; ?>;
const SUCS_RAW  = '<?php echo htmlspecialchars($sucsRaw); ?>';

const TIPOS = {
    inv_inicial : { label:'Inventario Inicial',       icon:'fas fa-box-open',        cls:'inv_inicial'  },
    ajuste      : { label:'Ajustes de Inventario',    icon:'fas fa-sliders-h',       cls:'ajuste'       },
    despacho    : { label:'Despacho (PreIngreso)',     icon:'fas fa-truck',           cls:'despacho'     },
    compras     : { label:'Compras (Ingresos)',        icon:'fas fa-shopping-cart',   cls:'compras'      },
    merma       : { label:'Merma',                    icon:'fas fa-trash-alt',       cls:'merma'        },
    inv_final   : { label:'Inventario Final',         icon:'fas fa-archive',         cls:'inv_final'    },
};
const ORD_TIPO = ['inv_inicial','ajuste','despacho','compras','merma','inv_final'];

const fmt  = (v, d=4) => v===null||v===undefined ? '—' : parseFloat(v).toLocaleString('es',{minimumFractionDigits:d,maximumFractionDigits:d});
const fmt2 = v => fmt(v, 2);
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// ── Cargar datos ──────────────────────────────────────────────
function cargar() {
    const fd = new FormData();
    fd.append('id_pp',       ID_PP);
    fd.append('semana_desde', SEM_DESDE);
    fd.append('semana_hasta', SEM_HASTA);
    if (SUCS_RAW) {
        SUCS_RAW.split(',').forEach(s => { if(s.trim()) fd.append('sucursales[]', s.trim()); });
    }
    fetch(AJAX+'balance_inventario_get_detalle.php', {method:'POST', body:fd})
        .then(r=>r.json()).then(res=>{
            document.getElementById('bdLoader').classList.add('d-none');
            if (!res.ok) {
                Swal.fire({icon:'error',title:'Error',text:res.msg,confirmButtonColor:'#0E544C'});
                return;
            }
            renderDetalle(res);
            cargarConsumoTeorico();
        })
        .catch(()=>{
            document.getElementById('bdLoader').classList.add('d-none');
            Swal.fire({icon:'error',title:'Error de red',text:'No se pudo cargar el detalle.',confirmButtonColor:'#0E544C'});
        });
}

function renderChart(res) {
    const regs = res.registros || [];
    const t = res.totales_tipo;
    const invIni = t.inv_inicial || 0;
    const invFin = t.inv_final || 0;
    const consTeoDiario = res.consumo_teorico_diario || {};

    // Generar lista de todos los días en el rango
    const start = new Date(res.fecha_inicio + 'T12:00:00');
    const end   = new Date(res.fecha_fin + 'T12:00:00');
    const allDays = [];
    let curr = new Date(start);
    while (curr <= end) {
        allDays.push(curr.toISOString().split('T')[0]);
        curr.setDate(curr.getDate() + 1);
    }

    // Movimientos por fecha (ajustes, despachos, mermas)
    const movsPorFecha = {};
    regs.forEach(r => {
        if (r.tipo === 'inv_inicial' || r.tipo === 'inv_final') return;
        if (!movsPorFecha[r.fecha]) movsPorFecha[r.fecha] = 0;
        let val = r.qty_base;
        if (r.tipo === 'merma') val = -val;
        movsPorFecha[r.fecha] += val;
    });

    const labels = ['Inicial (S'+res.semana_ant+')'];
    const stockTeoData = [invIni];
    
    let balTeo = invIni;

    allDays.forEach(day => {
        const mov = movsPorFecha[day] || 0;
        const cTeo = consTeoDiario[day] || 0;
        balTeo = balTeo + mov - cTeo;
        
        const dObj = new Date(day + 'T12:00:00');
        const dLabel = dObj.toLocaleDateString('es-ES', {weekday:'short', day:'numeric', month:'short'});
        
        labels.push(dLabel);
        stockTeoData.push(balTeo);
    });

    // Puntos finales para destacar
    const realFinalPoint = new Array(labels.length).fill(null);
    realFinalPoint[labels.length - 1] = invFin;

    const ctx = document.getElementById('existenciaChart').getContext('2d');
    if (window.myChart) window.myChart.destroy();

    window.myChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Stock Teórico (Ventas + Kardex)',
                    data: stockTeoData,
                    borderColor: '#51B8AC',
                    backgroundColor: 'rgba(81, 184, 172, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3,
                    pointBackgroundColor: '#fff',
                },
                {
                    label: 'Inventario Físico Real (Conteo)',
                    data: realFinalPoint,
                    borderColor: '#e74c3c',
                    backgroundColor: '#e74c3c',
                    pointRadius: 8,
                    pointStyle: 'rectRot',
                    showLine: false,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { 
                    display: true,
                    position: 'top',
                    labels: { boxWidth: 10, font: { size: 9, weight: 'bold' }, padding: 15 }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(context) {
                            if (context.raw === null) return null;
                            let label = context.dataset.label || '';
                            if (label) label += ': ';
                            label += fmt(context.raw, 2);
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: { font: { size: 10 } }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 9 }, maxRotation: 45, minRotation: 45 }
                }
            }
        }
    });
    document.getElementById('bdChartWrap').classList.remove('d-none');
}

function renderDetalle(res) {
    const prod    = res.producto;
    const regs    = res.registros || [];
    const totales = res.totales_tipo || {};

    // Header
    document.getElementById('bdNombreProd').textContent = prod.Nombre || prod.nombre || '—';
    document.getElementById('bdMetaRow').innerHTML = `
        <span class="bd-pill-meta"><i class="fas fa-hashtag me-1"></i>Semana ${res.sem_desde === res.sem_hasta ? res.sem_desde : res.sem_desde+' – '+res.sem_hasta}</span>
        <span class="bd-pill-meta"><i class="fas fa-ruler me-1"></i>${esc(prod.unidad||'')}</span>
        <span class="bd-pill-meta"><i class="fas fa-layer-group me-1"></i>${esc(res.producto.maestro || 'Sin Maestro')}</span>
        <span class="bd-pill-meta"><i class="fas fa-code me-1"></i>${res.num_mapeos || 0} Mapeos</span>
        <span class="bd-pill-meta" title="Referencia Inicial"><i class="fas fa-history me-1"></i>S${res.semana_ant}</span>
    `;
    const hc = document.getElementById('bdHeaderCard');
    hc.classList.remove('d-none');

    // Chart
    renderChart(res);

    // Resumen de Totales
    const t = res.totales_tipo;
    const bdResumen = document.getElementById('bdResumen');
    bdResumen.innerHTML = `
        <div class="bd-resumen-item">
            <div class="bd-resumen-label">INV. INICIAL</div>
            <div class="bd-resumen-val" style="color:var(--bd-neutral)">${fmt(t.inv_inicial,2)}</div>
        </div>
        <div class="bd-resumen-item">
            <div class="bd-resumen-label">+ AJUSTE</div>
            <div class="bd-resumen-val" style="color:var(--bd-pos)">${fmt(t.ajuste,2)}</div>
        </div>
        <div class="bd-resumen-item">
            <div class="bd-resumen-label">+ DESPACHO</div>
            <div class="bd-resumen-val" style="color:var(--neu-accent)">${fmt(t.despacho,2)}</div>
        </div>
        <div class="bd-resumen-item">
            <div class="bd-resumen-label">+ COMPRAS</div>
            <div class="bd-resumen-val" style="color:var(--bd-pos)">${fmt(t.compras,2)}</div>
        </div>
        <div class="bd-resumen-item">
            <div class="bd-resumen-label">- MERMA</div>
            <div class="bd-resumen-val" style="color:var(--bd-neg)">${fmt(t.merma,2)}</div>
        </div>
        <div class="bd-resumen-item">
            <div class="bd-resumen-label">- INV. FINAL</div>
            <div class="bd-resumen-val" style="color:var(--bd-final)">${fmt(t.inv_final,2)}</div>
        </div>
        <div class="bd-resumen-item" style="background:rgba(81,184,172,0.03)">
            <div class="bd-resumen-label">Consumo Teórico (Ventas)</div>
            <div class="bd-resumen-val" style="color:var(--bd-neutral)">${fmt(res.consumo_teorico,2)}</div>
        </div>
        <div class="bd-resumen-item" style="background:rgba(14,84,76,0.03)">
            <div class="bd-resumen-label">Consumo Real (Kardex)</div>
            <div class="bd-resumen-val" style="color:#0E544C">${fmt(res.consumo_real,2)}</div>
        </div>
    `;
    bdResumen.classList.remove('d-none');

    // Agrupar por tipo
    const porTipo = {};
    regs.forEach(r => {
        if (!porTipo[r.tipo]) porTipo[r.tipo] = [];
        porTipo[r.tipo].push(r);
    });

    const cont = document.getElementById('bdSecciones');
    cont.innerHTML = '';

    // Buscar campo de búsqueda global
    const searchWrap = document.createElement('div');
    searchWrap.className = 'mb-4';
    searchWrap.innerHTML = `<input type="text" class="bi-search w-100" id="bdBusqueda" placeholder="Filtrar registros (sucursal, fecha, CodCotizacion, producto…)">`;
    cont.appendChild(searchWrap);

    ORD_TIPO.forEach(tipo => {
        const rows = porTipo[tipo] || [];
        const def  = TIPOS[tipo];
        const total = rows.reduce((s, r) => s + r.qty_base, 0);

        const sec = document.createElement('div');
        sec.className = 'bd-section';
        sec.dataset.tipo = tipo;
        sec.innerHTML = `
            <div class="bd-section-title bd-sec-${def.cls}">
                <i class="${def.icon}"></i>
                ${def.label}
                <span style="margin-left:auto; font-size:.75rem; opacity:.7; font-weight:600">${rows.length} regs · Total: ${fmt(total, 2)}</span>
            </div>
            <div class="bd-sec-wrap">
                <div class="bd-tbl-wrap">
                    <table class="bd-tbl" id="tbl_${tipo}">
                        <thead>
                            <tr>
                                <th style="width:50px">Sem.</th>
                                <th style="width:90px">Fecha</th>
                                <th>Sucursal</th>
                                ${tipo==='despacho' ? '<th>Destino</th>' : ''}
                                <th style="width:80px">Cod.</th>
                                <th>Producto Original</th>
                                <th style="width:90px">Conv.</th>
                                <th class="td-num" style="width:80px">Factor</th>
                                <th class="td-num" style="width:90px">Qty Orig</th>
                                <th class="td-num" style="width:100px">Qty Base</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_${tipo}"></tbody>
                        <tfoot>
                            <tr class="bd-totales-row">
                                <td colspan="${tipo==='despacho'?7:6}" style="text-align:right;font-size:.7rem;letter-spacing:.05em">TOTAL ${def.label.toUpperCase()}</td>
                                <td></td><td></td>
                                <td class="td-num">${fmt(total, 2)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>`;
        cont.appendChild(sec);

        const tbody = document.getElementById('tbody_'+tipo);
        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="11" class="bd-empty">Sin registros de ${def.label.toLowerCase()}</td></tr>`;
            return;
        }
        rows.forEach(r => {
            const convBadge = `<span class="bd-conv-badge bd-conv-${esc(r.tipo_conversion)}">${esc(r.tipo_conversion)}</span>`;
            const tr = document.createElement('tr');
            tr.dataset.search = [r.semana, r.fecha, r.suc_nombre, r.cod_cotizacion, r.nombre_original||'', r.tipo_conversion].join(' ').toLowerCase();
            tr.innerHTML = `
                <td class="td-center">${r.semana}</td>
                <td>${esc(r.fecha)}</td>
                <td style="font-weight:600">${esc(r.suc_nombre)}</td>
                ${tipo==='despacho' ? `<td style="font-size:.68rem;color:#888">${esc(r.destino_texto||'')}</td>` : ''}
                <td class="td-center" style="font-family:monospace;color:#888">${r.cod_cotizacion}</td>
                <td style="font-size:.75rem">${r.nombre_original ? esc(r.nombre_original) : '<span style="opacity:.35;font-style:italic">—</span>'}</td>
                <td class="td-center">${convBadge}</td>
                <td class="td-num" style="color:#999;font-size:.7rem">×${r.factor}</td>
                <td class="td-num" style="color:#777">${fmt(r.qty_original, 2)}</td>
                <td class="td-num" style="color:#0E544C">${fmt(r.qty_base, 2)}</td>
            `;
            tbody.appendChild(tr);
        });
    });

    // Búsqueda global
    document.getElementById('bdBusqueda').addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#bdSecciones tbody tr[data-search]').forEach(tr => {
            tr.style.display = (!q || tr.dataset.search.includes(q)) ? '' : 'none';
        });
    });
}


// ── Consumo Teórico — carga y render ────────────────────────────────────────
function cargarConsumoTeorico() {
    const wrap = document.getElementById('bdConsumoTeoWrap');
    wrap.style.display = '';

    const fd = new FormData();
    fd.append('id_presentacion',  ID_PP);
    fd.append('semana_desde_num', SEM_DESDE);
    fd.append('semana_hasta_num', SEM_HASTA);
    if (SUCS_RAW) {
        SUCS_RAW.split(',').forEach(s => { if (s.trim()) fd.append('sucursales[]', s.trim()); });
    }
    fetch(AJAX + 'balance_inventario_auditoria_consumo.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(res => renderConsumoTeorico(res))
        .catch(() => renderConsumoTeorico({ok:false, msg:'Error de conexión al calcular consumo teórico.'}));
}

function renderConsumoTeorico(res) {
    document.getElementById('bdCteoLoader').style.display = 'none';
    const content  = document.getElementById('bdCteoContent');
    const subtitle = document.getElementById('bdCteoSubtitle');
    content.style.display = '';

    if (!res.ok) {
        subtitle.textContent = 'Sin datos';
        content.innerHTML = `<div class="bd-empty"><i class="fas fa-info-circle me-1"></i>${esc(res.msg || 'Sin datos.')}</div>`;
        return;
    }

    const pp    = res.presentacion;
    const filas = res.filas || [];
    const nDec  = filas.filter(f => f.genera_decimal).length;

    subtitle.textContent = `${res.total_filas} registros · Total: ${fmt2(res.total_consumo)} ${pp.unidad}`;

    // ── Info strip
    const infoHtml = `
        <div class="bd-cteo-info">
            <span><strong>Presentación:</strong> ${esc(pp.nombre)}</span>
            <span><strong>Categoría:</strong> <span class="bd-pill-meta" style="font-size:.68rem">${esc(pp.categoria_insumo || '—')}</span></span>
            <span><strong>Unidad ERP:</strong> ${esc(pp.unidad)}</span>
            <span><strong>pp_cantidad:</strong> ${pp.pp_cant}</span>
            <span><strong>Registros:</strong> ${res.total_filas}</span>
            <span><strong>Total:</strong> <span style="color:#e65100;font-weight:800">${fmt2(res.total_consumo)}</span></span>
        </div>`;

    // ── Leyenda
    const leyendaHtml = `
        <div class="bd-cteo-legend">
            <span class="bd-audit-badge bd-audit-p1">P1</span><span style="color:#555">Porción directa — redondea al 0.5</span>
            <span class="bd-audit-badge bd-audit-p2">P2</span><span style="color:#555">Cotización base — 4 dec</span>
            <span class="bd-audit-badge bd-audit-p3">P3</span><span style="color:#555">Fallback — 4 dec</span>
            ${ nDec > 0
                ? `<span class="ms-2" style="background:#fff8e1;border-radius:3px;padding:2px 6px;font-size:.7rem"><i class="fas fa-exclamation-triangle text-warning me-1"></i>${nDec} fila(s) redondeadas</span>`
                : '' }
        </div>`;

    // ── Alerta P1
    const alertaHtml = nDec > 0
        ? `<div class="bd-cteo-alerta warn"><i class="fas fa-exclamation-triangle me-1"></i><strong>${nDec} fila(s) P1</strong> tuvieron consumo crudo redondeado al 0.5 más cercano.</div>`
        : `<div class="bd-cteo-alerta ok"><i class="fas fa-check-circle me-1"></i>Todos los cálculos P1 caen exactamente en múltiplos de 0.5.</div>`;

    // ── Thead
    const thead = `<tr>
        <th style="width:42px">Sem</th>
        <th style="width:82px">Fecha</th>
        <th>Sucursal</th>
        <th>Batido</th>
        <th>Ingrediente</th>
        <th style="width:52px">Und.</th>
        <th class="td-num" style="width:54px">Ventas</th>
        <th class="td-num" style="width:62px">Cant.Rec.</th>
        <th class="td-num" style="width:62px">Raw</th>
        <th class="td-num" style="width:58px">Factor</th>
        <th class="td-num" style="width:58px">pp_cant</th>
        <th class="td-num" style="width:62px">Crudo</th>
        <th class="td-num" style="width:72px">Final</th>
        <th style="width:42px">Tipo</th>
        <th style="width:90px">Nivel</th>
    </tr>`;

    // ── Tbody
    let tbody = '';
    let sumCrudo = 0, sumFinal = 0;
    filas.forEach(f => {
        sumCrudo += f.consumo_crudo;
        sumFinal += f.consumo_final;

        const tipoMapeo = f.tipo_mapeo || (f.es_p1 ? 'P1' : 'P2');
        const tipoCls   = tipoMapeo === 'P1' ? 'bd-audit-p1' : tipoMapeo === 'P2' ? 'bd-audit-p2' : 'bd-audit-p3';
        const tipoHtml  = `<span class="bd-audit-badge ${tipoCls}">${tipoMapeo}</span>`;

        const rowBg = f.genera_decimal
            ? 'background:#fff8e1'
            : tipoMapeo === 'P1' ? 'background:#f1f8e9'
            : tipoMapeo === 'P2' ? 'background:#f3f8ff'
            : 'background:#fff8f2';

        const diffBadge = f.genera_decimal
            ? `<span style="font-size:.6rem;background:#ffe082;border-radius:2px;padding:1px 3px" title="Crudo:${f.consumo_crudo}">Δ${(Math.round(parseFloat(f.consumo_crudo)*2)/2).toFixed(1)}</span>`
            : '';

        const srch = esc([f.fecha, f.sucursal, f.nombre_batido, f.nombre_ingrediente, f.nivel, f.semana].join(' ').toLowerCase());

        tbody += `<tr style="font-size:.72rem;${rowBg}" data-search="${srch}">
            <td class="td-center">${f.semana}</td>
            <td style="white-space:nowrap">${esc(f.fecha)}</td>
            <td style="font-weight:600;font-size:.7rem">${esc(f.sucursal)}</td>
            <td style="max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(f.nombre_batido)}">${esc(f.nombre_batido)}</td>
            <td style="max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(f.nombre_ingrediente)}">${esc(f.nombre_ingrediente)}</td>
            <td style="font-size:.68rem;color:#888">${esc(f.unidad_access)}</td>
            <td class="td-num">${f.ventas}</td>
            <td class="td-num">${f.cant_receta}</td>
            <td class="td-num">${f.cant_total}</td>
            <td class="td-num" style="color:#1565c0">${f.factor}</td>
            <td class="td-num">${f.pp_cantidad}</td>
            <td class="td-num" style="color:#555">${f.consumo_crudo}</td>
            <td class="td-num" style="color:#0E544C;font-weight:700">${f.consumo_final} ${diffBadge}</td>
            <td>${tipoHtml}</td>
            <td style="font-size:.62rem;color:#999">${esc(f.nivel)}</td>
        </tr>`;
    });

    // Fila totales
    tbody += `<tr class="bd-totales-row">
        <td colspan="11" style="text-align:right;font-size:.7rem;letter-spacing:.05em">TOTAL CONSUMO TEÓRICO</td>
        <td class="td-num">${fmt(sumCrudo, 2)}</td>
        <td class="td-num" style="color:#e65100">${fmt(sumFinal, 2)}</td>
        <td colspan="2"></td>
    </tr>`;

    content.innerHTML = `
        ${infoHtml}
        ${leyendaHtml}
        ${alertaHtml}
        <div class="mb-3">
            <input type="text" class="bi-search w-100" id="bdCteoBusqueda"
                   placeholder="Filtrar por fecha, batido, ingrediente, sucursal, nivel…">
        </div>
        <div class="bd-tbl-wrap" style="max-height:520px">
            <table class="bd-tbl">
                <thead>${thead}</thead>
                <tbody id="bdCteoTbody">${tbody}</tbody>
            </table>
        </div>`;

    // Búsqueda
    document.getElementById('bdCteoBusqueda').addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#bdCteoTbody tr[data-search]').forEach(tr => {
            tr.style.display = (!q || tr.dataset.search.includes(q)) ? '' : 'none';
        });
    });
}

// ── Inicio ────────────────────────────────────────────────────
if (!ID_PP || !SEM_DESDE || !SEM_HASTA) {
    document.getElementById('bdLoader').classList.add('d-none');
    document.getElementById('bdSecciones').innerHTML = `
        <div class="text-center py-5 text-muted">
            <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
            <p>Parámetros inválidos. <a href="balance_inventario_access_host.php" style="color:#0ff">Volver al balance</a>.</p>
        </div>`;
} else {
    cargar();
}
</script>
</body>
</html>
