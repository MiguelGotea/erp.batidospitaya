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
            --bd-bg: var(--neu-bg, #f6f6f6);
            --bd-shadow-dark: var(--neu-shadow-dark, #d6d6d6);
            --bd-shadow-light: var(--neu-shadow-light, #ffffff);
            --bd-accent: #51B8AC;
        }

        body { background-color: var(--bd-bg); color: #455a64; }

        .bd-header-card {
            background: var(--bd-bg);
            border-radius: 20px; padding: 1.5rem;
            margin-bottom: 1.5rem; display: flex; flex-wrap: wrap;
            align-items: center; gap: 1rem; justify-content: space-between;
            box-shadow: 8px 8px 16px var(--bd-shadow-dark), -8px -8px 16px var(--bd-shadow-light);
        }
        .bd-header-card h2 { font-size: 1.3rem; font-weight: 800; color: #0E544C; margin: 0; }
        .bd-header-card .bd-meta { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .6rem; }
        .bd-pill-meta { 
            background: var(--bd-bg); border-radius: 20px;
            padding: .3rem .8rem; font-size: .75rem; color: #51B8AC; font-weight: 600;
            box-shadow: inset 2px 2px 5px var(--bd-shadow-dark), inset -3px -3px 6px var(--bd-shadow-light);
        }

        .bd-resumen-card {
            background: var(--bd-bg); border-radius: 20px;
            padding: 1.5rem; margin-bottom: 1.5rem;
            display: flex; flex-wrap: wrap; gap: 1rem;
            box-shadow: 10px 10px 20px var(--bd-shadow-dark), -10px -10px 20px var(--bd-shadow-light);
        }
        .bd-resumen-item { flex: 1; min-width: 140px; padding: 10px; border-radius: 12px; }
        .bd-resumen-label { font-size: .68rem; color: #888; text-transform: uppercase; letter-spacing: .05em; margin-bottom: .3rem; font-weight: 700; }
        .bd-resumen-val { font-size: 1.2rem; font-weight: 800; color: #455a64; }

        .bd-chart-card {
            background: var(--bd-bg); border-radius: 20px;
            padding: 1.5rem; margin-bottom: 1.5rem;
            box-shadow: 10px 10px 20px var(--bd-shadow-dark), -10px -10px 20px var(--bd-shadow-light);
        }

        .bd-section { margin-bottom: 2rem; }
        .bd-section-title {
            display: flex; align-items: center; gap: .7rem;
            font-size: .9rem; font-weight: 800; letter-spacing: .05em;
            text-transform: uppercase; margin-bottom: 1rem;
            padding: .8rem 1.2rem; border-radius: 14px;
            background: var(--bd-bg);
            box-shadow: 5px 5px 10px var(--bd-shadow-dark), -5px -5px 10px var(--bd-shadow-light);
        }
        .bd-sec-inv_inicial  { color: #2980b9; }
        .bd-sec-ajuste       { color: #27ae60; }
        .bd-sec-despacho     { color: #51B8AC; }
        .bd-sec-merma        { color: #e74c3c; }
        .bd-sec-inv_final    { color: #9b59b6; }

        .bd-sec-wrap { 
            background: var(--bd-bg); border-radius: 20px; overflow: hidden; 
            box-shadow: inset 6px 6px 12px var(--bd-shadow-dark), inset -6px -6px 12px var(--bd-shadow-light);
            padding: 10px;
        }
        .bd-tbl-wrap { max-height: 400px; overflow-y: auto; border-radius: 12px; }
        
        .bd-tbl { width: 100%; font-size: .8rem; border-collapse: separate; border-spacing: 0; }
        .bd-tbl thead th {
            background: #f0f2f5; color: #666; padding: .8rem .7rem;
            font-weight: 800; text-transform: uppercase; letter-spacing: .04em;
            position: sticky; top: 0; z-index: 1; font-size: .7rem;
            border-bottom: 2px solid var(--bd-shadow-dark);
        }
        .bd-tbl td { padding: .6rem .7rem; color: #455a64; border-bottom: 1px solid rgba(0,0,0,0.03); vertical-align: middle; }
        .bd-tbl tbody tr:hover td { background: rgba(81, 184, 172, 0.05); }
        .bd-tbl .td-num { text-align: right; font-variant-numeric: tabular-nums; font-weight: 600; }
        .bd-tbl .td-center { text-align: center; }

        .bd-conv-badge {
            display: inline-block; padding: .2rem .6rem; border-radius: 20px;
            font-size: .65rem; font-weight: 700; letter-spacing: .03em;
            box-shadow: 2px 2px 4px var(--bd-shadow-dark), -2px -2px 4px var(--bd-shadow-light);
        }
        .bd-conv-base       { background: #e8f5e9; color: #2e7d32; }
        .bd-conv-cascada    { background: #e3f2fd; color: #1565c0; }
        .bd-conv-alternativa{ background: #fff3e0; color: #ef6c00; }

        .bd-totales-row td { background: #f8f9fa; font-weight: 800; color: #0E544C; border-top: 2px solid var(--bd-shadow-dark); }
        .bd-empty { text-align: center; padding: 2.5rem; color: #999; font-size: .85rem; font-style: italic; }

        .bi-search {
            background: var(--bd-bg); border: none; border-radius: 14px;
            padding: .8rem 1.2rem; font-size: .9rem; color: #455a64;
            box-shadow: inset 4px 4px 8px var(--bd-shadow-dark), inset -4px -4px 8px var(--bd-shadow-light);
            transition: all .2s;
        }
        .bi-search:focus { outline: none; box-shadow: inset 5px 5px 10px var(--bd-shadow-dark), inset -5px -5px 10px var(--bd-shadow-light), 0 0 0 2px var(--bd-accent); }
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
    merma       : { label:'Merma',                    icon:'fas fa-trash-alt',       cls:'merma'        },
    inv_final   : { label:'Inventario Final',         icon:'fas fa-archive',         cls:'inv_final'    },
};
const ORD_TIPO = ['inv_inicial','ajuste','despacho','merma','inv_final'];

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
    const consRealTotal = res.consumo_real || 0;

    // Generar lista de todos los días en el rango
    const start = new Date(res.fecha_inicio + 'T12:00:00');
    const end   = new Date(res.fecha_fin + 'T12:00:00');
    const allDays = [];
    let curr = new Date(start);
    while (curr <= end) {
        allDays.push(curr.toISOString().split('T')[0]);
        curr.setDate(curr.getDate() + 1);
    }

    // Consumo diario lineal para la proyección
    const consDiario = allDays.length > 0 ? (consRealTotal / allDays.length) : 0;

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
    const stockRealData = [invIni];
    const stockTeoricoSinVentas = [invIni];
    
    let balReal = invIni;
    let balTeo  = invIni;

    allDays.forEach(day => {
        const mov = movsPorFecha[day] || 0;
        balReal = balReal + mov - consDiario;
        balTeo  = balTeo + mov;
        
        const dObj = new Date(day + 'T12:00:00');
        const dLabel = dObj.toLocaleDateString('es-ES', {weekday:'short', day:'numeric', month:'short'});
        
        labels.push(dLabel);
        stockRealData.push(balReal);
        stockTeoricoSinVentas.push(balTeo);
    });

    // Puntos finales para destacar
    const ptFinalTeo = new Array(labels.length).fill(null);
    ptFinalTeo[labels.length - 1] = balTeo;

    const ctx = document.getElementById('existenciaChart').getContext('2d');
    if (window.myChart) window.myChart.destroy();

    window.myChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Existencia Real Estimada (con consumo)',
                    data: stockRealData,
                    borderColor: '#51B8AC',
                    backgroundColor: 'rgba(81, 184, 172, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 2,
                    pointBackgroundColor: '#fff',
                },
                {
                    label: 'Disponibilidad Teórica (sin consumo)',
                    data: stockTeoricoSinVentas,
                    borderColor: '#90a4ae',
                    borderDash: [5, 5],
                    borderWidth: 1.5,
                    fill: false,
                    tension: 0.1,
                    pointRadius: 0,
                },
                {
                    label: 'Inv. Teórico Final',
                    data: ptFinalTeo,
                    borderColor: '#2980b9',
                    backgroundColor: '#2980b9',
                    pointRadius: 6,
                    pointStyle: 'circle',
                    showLine: false,
                },
                {
                    label: 'Inv. Registrado (Físico)',
                    data: new Array(labels.length - 1).concat([invFin]),
                    borderColor: '#e74c3c',
                    backgroundColor: '#e74c3c',
                    pointRadius: 6,
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
                    labels: { boxWidth: 10, font: { size: 9 }, padding: 10 }
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
        <span class="bd-pill-meta"><i class="fas fa-layer-group me-1"></i>${esc(prod.maestro||'')}</span>
        <span class="bd-pill-meta"><i class="fas fa-code me-1"></i>${res.num_cods_mapeados} Mapeos</span>
        <span class="bd-pill-meta" title="Referencia Inicial"><i class="fas fa-history me-1"></i>S${res.semana_ant}</span>
    `;
    const hc = document.getElementById('bdHeaderCard');
    hc.style.display = 'flex';
    hc.classList.remove('d-none');

    // Chart
    renderChart(res);

    // Resumen
    const cr = res.consumo_real;
    const t  = totales;
    document.getElementById('bdResumen').innerHTML = `
        <div class="bd-resumen-item">
            <div class="bd-resumen-label">Inv. Inicial</div>
            <div class="bd-resumen-val" style="color:#2980b9">${fmt(t.inv_inicial,2)}</div>
        </div>
        <div class="bd-resumen-item">
            <div class="bd-resumen-label">+ Ajuste</div>
            <div class="bd-resumen-val" style="color:${t.ajuste>=0?'#27ae60':'#e74c3c'}">${fmt(t.ajuste,2)}</div>
        </div>
        <div class="bd-resumen-item">
            <div class="bd-resumen-label">+ Despacho</div>
            <div class="bd-resumen-val" style="color:#51B8AC">${fmt(t.despacho,2)}</div>
        </div>
        <div class="bd-resumen-item">
            <div class="bd-resumen-label">− Merma</div>
            <div class="bd-resumen-val" style="color:#e74c3c">${fmt(t.merma,2)}</div>
        </div>
        <div class="bd-resumen-item">
            <div class="bd-resumen-label">− Inv. Final</div>
            <div class="bd-resumen-val" style="color:#9b59b6">${fmt(t.inv_final,2)}</div>
        </div>
        <div class="bd-resumen-item" style="box-shadow:inset 3px 3px 6px var(--bd-shadow-dark), inset -3px -3px 6px var(--bd-shadow-light); background:#f0f9f8;">
            <div class="bd-resumen-label">Consumo Real</div>
            <div class="bd-resumen-val" style="color:#0E544C">${fmt(cr,2)}</div>
        </div>
    `;
    document.getElementById('bdResumen').classList.remove('d-none');

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
