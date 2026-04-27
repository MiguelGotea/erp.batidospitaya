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
    <style>
        /* ── Detalle extras ─────────────────────────────────────── */
        .bd-back-btn {
            display:inline-flex; align-items:center; gap:.45rem;
            background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.13);
            color:#ccc; border-radius:8px; padding:.35rem .9rem; font-size:.82rem;
            text-decoration:none; transition:.2s;
        }
        .bd-back-btn:hover { background:rgba(255,255,255,.14); color:#fff; }

        .bd-header-card {
            background:linear-gradient(135deg,#0E544C 0%,#0b3d38 100%);
            border-radius:14px; padding:1.4rem 1.6rem 1.2rem;
            margin-bottom:1.4rem; display:flex; flex-wrap:wrap;
            align-items:center; gap:1rem; justify-content:space-between;
        }
        .bd-header-card h2 { font-size:1.15rem; font-weight:700; color:#fff; margin:0; }
        .bd-header-card .bd-meta { display:flex; flex-wrap:wrap; gap:.5rem; margin-top:.4rem; }
        .bd-pill-meta { background:rgba(255,255,255,.12); border-radius:20px;
            padding:.2rem .75rem; font-size:.75rem; color:#c5f0ea; }

        .bd-section { margin-bottom:1.5rem; }
        .bd-section-title {
            display:flex; align-items:center; gap:.6rem;
            font-size:.88rem; font-weight:700; letter-spacing:.04em;
            text-transform:uppercase; margin-bottom:.65rem;
            padding:.5rem .85rem; border-radius:8px;
        }
        .bd-sec-inv_inicial  { background:rgba(52,152,219,.15); color:#5dade2; border-left:3px solid #2980b9; }
        .bd-sec-ajuste       { background:rgba(39,174,96,.15);  color:#58d68d; border-left:3px solid #27ae60; }
        .bd-sec-despacho     { background:rgba(41,128,185,.12); color:#85c1e9; border-left:3px solid #1a6fa0; }
        .bd-sec-merma        { background:rgba(231,76,60,.15);  color:#f1948a; border-left:3px solid #e74c3c; }
        .bd-sec-inv_final    { background:rgba(155,89,182,.15); color:#c39bd3; border-left:3px solid #9b59b6; }

        .bd-tbl { width:100%; font-size:.78rem; border-collapse:separate; border-spacing:0; }
        .bd-tbl thead th {
            background:#0b3d38; color:#a8e6df; padding:.45rem .7rem;
            font-weight:600; text-transform:uppercase; letter-spacing:.03em;
            position:sticky; top:0; z-index:1; font-size:.7rem;
        }
        .bd-tbl tbody tr { border-bottom:1px solid rgba(255,255,255,.04); }
        .bd-tbl tbody tr:hover { background:rgba(255,255,255,.04); }
        .bd-tbl td { padding:.38rem .7rem; color:#d0d0d0; vertical-align:middle; }
        .bd-tbl .td-num { text-align:right; font-variant-numeric:tabular-nums; }
        .bd-tbl .td-center { text-align:center; }

        .bd-conv-badge {
            display:inline-block; padding:.1rem .5rem; border-radius:20px;
            font-size:.68rem; font-weight:600; letter-spacing:.03em;
        }
        .bd-conv-base       { background:rgba(39,174,96,.2);  color:#2ecc71; }
        .bd-conv-cascada    { background:rgba(52,152,219,.2); color:#5dade2; }
        .bd-conv-alternativa{ background:rgba(230,126,34,.2); color:#e67e22; }

        .bd-totales-row td { background:rgba(255,255,255,.07); font-weight:700; color:#fff; }
        .bd-empty { text-align:center; padding:1.5rem; color:#777; font-size:.83rem; }

        .bd-resumen-card {
            display:flex; flex-wrap:wrap; gap:.75rem;
            background:#121f1e; border:1px solid rgba(255,255,255,.09);
            border-radius:12px; padding:1rem 1.2rem; margin-bottom:1.4rem;
        }
        .bd-resumen-item { flex:1; min-width:130px; }
        .bd-resumen-label { font-size:.7rem; color:#999; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.15rem; }
        .bd-resumen-val { font-size:1.05rem; font-weight:700; color:#fff; }

        .bd-sec-wrap { background:#131f1e; border:1px solid rgba(255,255,255,.07); border-radius:12px; overflow:hidden; }
        .bd-tbl-wrap { max-height:350px; overflow-y:auto; }
    </style>
</head>
<body>
<?php echo renderMenuLateral($cargoOperario); ?>
<div class="main-container">
    <div class="sub-container">
        <?php echo renderHeader($usuario, false, 'Detalle de Balance'); ?>

        <div class="bi-wrapper">

            <!-- Back + header info -->
            <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
                <a href="balance_inventario_access_host.php" class="bd-back-btn">
                    <i class="fas fa-arrow-left"></i>Volver al Balance
                </a>
                <div id="bdHeaderCard" class="bd-header-card flex-grow-1" style="display:none!important">
                    <div>
                        <div style="font-size:.72rem;color:#a8e6df;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.2rem">Detalle de producto</div>
                        <h2 id="bdNombreProd">—</h2>
                        <div class="bd-meta" id="bdMetaRow"></div>
                    </div>
                </div>
            </div>

            <!-- Loader -->
            <div id="bdLoader" class="bi-loader-wrap">
                <div class="spinner-border text-success" role="status"></div>
                <div>Cargando registros…<br><small class="text-muted">Consultando todas las tablas kardex</small></div>
            </div>

            <!-- Resumen -->
            <div id="bdResumen" class="bd-resumen-card d-none"></div>

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
        <span class="bd-pill-meta"><i class="fas fa-code me-1"></i>${res.num_cods_mapeados} CodCotizacion mapeados</span>
        <span class="bd-pill-meta"><i class="fas fa-history me-1"></i>Inv. Inicial: Sem. ${res.semana_ant}</span>
    `;
    const hc = document.getElementById('bdHeaderCard');
    hc.style.display = 'flex';
    hc.classList.remove('d-none');

    // Resumen
    const cr = res.consumo_real;
    const t  = totales;
    document.getElementById('bdResumen').innerHTML = `
        <div class="bd-resumen-item">
            <div class="bd-resumen-label">Inv. Inicial</div>
            <div class="bd-resumen-val" style="color:#5dade2">${fmt(t.inv_inicial)}</div>
        </div>
        <div class="bd-resumen-item">
            <div class="bd-resumen-label">+ Ajuste</div>
            <div class="bd-resumen-val" style="color:${t.ajuste>=0?'#2ecc71':'#e74c3c'}">${fmt(t.ajuste)}</div>
        </div>
        <div class="bd-resumen-item">
            <div class="bd-resumen-label">+ Despacho</div>
            <div class="bd-resumen-val" style="color:#85c1e9">${fmt(t.despacho)}</div>
        </div>
        <div class="bd-resumen-item">
            <div class="bd-resumen-label">− Merma</div>
            <div class="bd-resumen-val" style="color:#f1948a">${fmt(t.merma)}</div>
        </div>
        <div class="bd-resumen-item">
            <div class="bd-resumen-label">− Inv. Final</div>
            <div class="bd-resumen-val" style="color:#c39bd3">${fmt(t.inv_final)}</div>
        </div>
        <div class="bd-resumen-item" style="border-left:2px solid rgba(255,255,255,.15);padding-left:.9rem">
            <div class="bd-resumen-label">= Consumo Real</div>
            <div class="bd-resumen-val" style="color:#0ff;font-size:1.2rem">${fmt(cr)}</div>
        </div>
        <div class="bd-resumen-item">
            <div class="bd-resumen-label">Registros</div>
            <div class="bd-resumen-val">${regs.length}</div>
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
    searchWrap.className = 'mb-3';
    searchWrap.innerHTML = `<input type="text" class="bi-search w-100" id="bdBusqueda" placeholder="Buscar en todos los registros (sucursal, fecha, CodCotizacion…)">`;
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
                <span style="margin-left:auto;font-size:.82rem;opacity:.8">${rows.length} registros · Total: ${fmt(total)}</span>
            </div>
            <div class="bd-sec-wrap">
                <div class="bd-tbl-wrap">
                    <table class="bd-tbl" id="tbl_${tipo}">
                        <thead>
                            <tr>
                                <th>Sem.</th>
                                <th>Fecha</th>
                                <th>Sucursal</th>
                                ${tipo==='despacho' ? '<th>Destino</th>' : ''}
                                <th>CodCotiz.</th>
                                <th>Producto Original</th>
                                <th>Conversión</th>
                                <th class="td-num">Factor</th>
                                <th class="td-num">Qty Original</th>
                                <th class="td-num">Qty Base</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_${tipo}"></tbody>
                        <tfoot>
                            <tr class="bd-totales-row">
                                <td colspan="${tipo==='despacho'?6:5}" style="text-align:right;font-size:.78rem">TOTAL</td>
                                <td></td><td></td>
                                <td class="td-num">${fmt(total)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>`;
        cont.appendChild(sec);

        const tbody = document.getElementById('tbody_'+tipo);
        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="10" class="bd-empty"><i class="fas fa-inbox me-1"></i>Sin registros</td></tr>`;
            return;
        }
        rows.forEach(r => {
            const convBadge = `<span class="bd-conv-badge bd-conv-${esc(r.tipo_conversion)}">${esc(r.tipo_conversion)}</span>`;
            const tr = document.createElement('tr');
            tr.dataset.search = [r.semana, r.fecha, r.suc_nombre, r.cod_cotizacion, r.nombre_original||'', r.tipo_conversion].join(' ').toLowerCase();
            tr.innerHTML = `
                <td class="td-center">${r.semana}</td>
                <td>${esc(r.fecha)}</td>
                <td>${esc(r.suc_nombre)}</td>
                ${tipo==='despacho' ? `<td style="font-size:.7rem;color:#aaa">${esc(r.destino_texto||'')}</td>` : ''}
                <td class="td-center" style="font-family:monospace;color:#aaa">${r.cod_cotizacion}</td>
                <td>${r.nombre_original ? esc(r.nombre_original) : '<span style="opacity:.35;font-style:italic">—</span>'}</td>
                <td class="td-center">${convBadge}</td>
                <td class="td-num" style="color:#aaa;font-size:.74rem">×${r.factor}</td>
                <td class="td-num">${fmt2(r.qty_original)}</td>
                <td class="td-num" style="font-weight:700">${fmt(r.qty_base)}</td>
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
