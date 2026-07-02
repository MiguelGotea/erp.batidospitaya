/* ============================================================
   ALERTAS DE AGOTAMIENTO — Módulo independiente
   js/alertas_agotamiento.js

   Calcula y muestra insumos en riesgo de agotarse antes del
   próximo despacho, para TODAS las sucursales activas.

   Parámetros (con defaults editables desde la UI):
     - crecimiento : 15%
     - semCorte    : semActual - 1
     - semDesde    : semCorte - 3
     - semHasta    : semActual
   ============================================================ */
'use strict';

(function AltertasAgotamiento() {

    /* ── Estado interno ──────────────────────────────────────── */
    let _sucursales = [];
    let _calculando = false;

    /* ── Utilidades ─────────────────────────────────────────── */
    function _addDays(dateStr, n) {
        const d = new Date(dateStr + 'T12:00:00');
        d.setDate(d.getDate() + n);
        return d.toISOString().split('T')[0];
    }
    function _todayStr() {
        return new Date().toISOString().split('T')[0];
    }
    function _fmtFecha(dateStr) {
        if (!dateStr) return '—';
        const d = new Date(dateStr + 'T12:00:00');
        return d.toLocaleDateString('es-NI', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }
    function _esc(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    function _daysDiff(aStr, bStr) {
        const a = new Date(aStr + 'T12:00:00');
        const b = new Date(bStr + 'T12:00:00');
        return Math.round((b - a) / (1000 * 60 * 60 * 24));
    }

    /* ── WLS / CD dinámico ───────────────────────────────────── */
    function _getDynamicCD(prod, wls_lff, fechaStr) {
        let wls_x = prod.wls_n ?? 0;
        if (wls_lff) {
            const dF = new Date(wls_lff + 'T23:59:59');
            const dD = new Date(fechaStr + 'T12:00:00');
            wls_x += Math.ceil(Math.round((dD - dF) / 86400000) / 7);
        } else {
            wls_x += 1;
        }
        return Math.max(0, ((prod.wls_m ?? 0) * wls_x) + (prod.wls_b ?? 0)) / 7;
    }

    /* Aplica ICE (índice de crecimiento esperado) a los params WLS */
    function _applyICE(prod, ice) {
        if (ice <= 0) return prod;
        const m = prod.wls_m ?? 0;
        const b = prod.wls_b ?? 0;
        const n = prod.wls_n ?? 0;
        const base = Math.max(0, m * n + b);
        const em = base * (ice / 100);
        if (em > m) return Object.assign({}, prod, { wls_m: em, wls_b: base - em * n });
        return prod;
    }

    /* ── Leer parámetros de los inputs de la UI ─────────────── */
    function _getParams() {
        const semActual = window.PA_SEMANA_ACTUAL;
        const defCorte  = semActual ? semActual - 1 : null;
        const defDesde  = defCorte  ? defCorte  - 3 : null;

        return {
            corte       : parseInt(document.getElementById('aa-semCorte')?.value)    || defCorte,
            desde       : parseInt(document.getElementById('aa-semDesde')?.value)    || defDesde,
            hasta       : parseInt(document.getElementById('aa-semHasta')?.value)    || semActual,
            crecimiento : parseFloat(document.getElementById('aa-crecimiento')?.value) ?? 15
        };
    }

    /* ── UI helpers ──────────────────────────────────────────── */
    function _setLoading(on) {
        const spinner = document.getElementById('aa-spinner');
        const table   = document.getElementById('aa-tabla-container');
        if (spinner) spinner.style.display = on ? 'flex' : 'none';
        if (table)   table.style.display   = on ? 'none' : 'block';

        const btn = document.getElementById('aa-btn-calcular');
        if (btn) {
            btn.disabled   = on;
            btn.innerHTML  = on
                ? '<i class="bi bi-hourglass-split me-1"></i>Calculando…'
                : '<i class="bi bi-arrow-clockwise me-1"></i>Recalcular';
        }
    }

    /* ── Cálculo para una sucursal ───────────────────────────── */
    async function _calcularSucursal(suc, semDesde, semHasta, semCorte, ice) {
        try {
            /* 1 — Pedido sugerido */
            const fdP = new FormData();
            fdP.append('semana_desde_num', semDesde);
            fdP.append('semana_hasta_num',  semHasta);
            fdP.append('cod_sucursal',       suc.codigo);
            const rPed = await fetch('ajax/pedido_sugerido_calcular_v2.php', { method:'POST', body:fdP }).then(r=>r.json());
            if (!rPed.ok) return [];

            const GRUPOS   = ['B','D','F','G'];
            const todayD   = _todayStr();
            const wls_lff  = rPed.wls_last_fecha_fin;

            const productos = (rPed.productos || [])
                .filter(p => GRUPOS.includes(p.categoria_insumo) && p.fecha_proximo_despacho)
                .map(p => _applyICE(p, ice));

            if (!productos.length) return [];

            /* 2 — Pronóstico (stock D-1 + despachos reales) */
            const fdPr = new FormData();
            fdPr.append('semana_desde',  semDesde);
            fdPr.append('semana_hasta',  semHasta);
            fdPr.append('semana_corte',  semCorte);
            fdPr.append('cod_sucursal',  suc.codigo);
            productos.forEach(p => {
                const fDesp = p.hoy_es_despacho ? todayD : p.fecha_proximo_despacho;
                fdPr.append('ids_pp[]',                    p.id_pp);
                fdPr.append(`fechas_d1[${p.id_pp}]`,       _addDays(fDesp, -1));
            });
            const rPro = await fetch('ajax/pedido_sugerido_pronostico_v2.php', { method:'POST', body:fdPr }).then(r=>r.json());
            if (!rPro.ok) return [];

            const stocks       = rPro.stocks          || {};
            const despReales   = rPro.despachos_reales || {};
            const diasProy     = rPro.dias_proy        || {};

            /* 3 — Calcular stock hoy y fecha de agotamiento */
            const incidencias = [];

            for (const p of productos) {
                const id  = String(p.id_pp);
                const su  = stocks[id];
                if (su === null || su === undefined) continue;

                const fDesp  = p.hoy_es_despacho ? todayD : p.fecha_proximo_despacho;
                const fD1    = _addDays(fDesp, -1);
                const dP     = diasProy[id] || 0;
                const drMap  = despReales[id] || {};

                /* Proyectar desde el corte hasta D-1 (igual que módulo principal) */
                let stockD1 = su; // en Unidades de Control
                if (dP > 0) {
                    for (let k = 0; k < dP; k++) {
                        stockD1 -= _getDynamicCD(p, wls_lff, _addDays(fD1, -k));
                    }
                }
                stockD1 = Math.max(0, stockD1);

                /* Simular desde D-1 hasta hoy, incluyendo despacho real */
                let bal = stockD1;
                const diasSimular = _daysDiff(fD1, todayD);
                for (let d = 1; d <= diasSimular; d++) {
                    const dia = _addDays(fD1, d);
                    if (dia === fDesp) {
                        const dr = drMap[fDesp];
                        if (dr !== null && dr !== undefined) bal += dr;
                    }
                    bal -= _getDynamicCD(p, wls_lff, dia);
                }
                const stockHoyUC = bal; // puede ser negativo

                /* Calcular fecha de agotamiento (simular desde hoy hacia adelante) */
                let stockSim = bal;
                let fechaAgotamiento = null;
                for (let d = 1; d <= 365; d++) {
                    const dia = _addDays(todayD, d);
                    stockSim -= _getDynamicCD(p, wls_lff, dia);
                    if (stockSim <= 0) { fechaAgotamiento = dia; break; }
                }

                if (stockHoyUC <= 0 && !fechaAgotamiento) fechaAgotamiento = todayD;
                if (!fechaAgotamiento) continue; // no se agota en el próximo año

                /* Filtro: solo si se agota ANTES o EN el próximo despacho */
                if (fechaAgotamiento > fDesp) continue;

                incidencias.push({
                    sucursal             : suc.nombre,
                    producto             : p.nombre,
                    stockHoyUC,
                    fechaAgotamiento,
                    fechaProximoDespacho : fDesp,
                    diasHastaAgotamiento : _daysDiff(todayD, fechaAgotamiento)
                });
            }

            return incidencias;

        } catch (err) {
            console.warn(`[AlertasAgotamiento] ${suc.nombre}:`, err);
            return [];
        }
    }

    /* ── Render de la tabla ──────────────────────────────────── */
    function _renderTabla(lista) {
        const container = document.getElementById('aa-tabla-container');
        if (!container) return;

        /* Actualizar badge del header */
        const badge = document.getElementById('aa-header-badge');
        if (badge) {
            if (lista.length) {
                badge.textContent  = lista.length + ' incidencia' + (lista.length !== 1 ? 's' : '');
                badge.className    = 'aa-header-count aa-badge-critico';
            } else {
                badge.textContent  = 'Sin incidencias';
                badge.className    = 'aa-header-count aa-badge-ok';
            }
        }

        if (!lista.length) {
            container.innerHTML = `
                <div class="aa-sin-incidencias">
                    <i class="bi bi-check-circle-fill" style="font-size:2rem;color:#22c55e;"></i>
                    <div class="mt-2 fw-semibold" style="color:#16a34a;">Sin incidencias detectadas</div>
                    <div class="text-muted small">Todos los insumos tienen stock suficiente hasta su próximo despacho.</div>
                </div>`;
            return;
        }

        /* Ordenar por fecha de agotamiento ASC (más urgente primero) */
        lista.sort((a, b) => a.fechaAgotamiento.localeCompare(b.fechaAgotamiento));

        const rows = lista.map(inc => {
            const d = inc.diasHastaAgotamiento;
            let cls, lbl;
            if (d <= 0)     { cls = 'aa-badge-critico'; lbl = 'HOY / AGOTADO'; }
            else if (d <= 2){ cls = 'aa-badge-critico'; lbl = `${d} día${d!==1?'s':''}`; }
            else if (d <= 7){ cls = 'aa-badge-alerta';  lbl = `${d} días`; }
            else             { cls = 'aa-badge-aviso';   lbl = `${d} días`; }

            const stockHtml = inc.stockHoyUC <= 0
                ? `<span style="color:#dc2626;font-weight:700;">${inc.stockHoyUC.toFixed(1)}</span>`
                : `<span>${inc.stockHoyUC.toFixed(1)}</span>`;

            return `
            <tr>
                <td><span class="aa-tienda-chip">${_esc(inc.sucursal)}</span></td>
                <td class="fw-semibold">${_esc(inc.producto)}</td>
                <td class="text-center">${stockHtml}</td>
                <td class="text-center">
                    <div style="display:flex;align-items:center;justify-content:center;gap:6px;">
                        <span>${_fmtFecha(inc.fechaAgotamiento)}</span>
                        <span class="aa-badge ${cls}">${lbl}</span>
                    </div>
                </td>
                <td class="text-center">${_fmtFecha(inc.fechaProximoDespacho)}</td>
            </tr>`;
        }).join('');

        container.innerHTML = `
            <div class="table-responsive">
                <table class="aa-table">
                    <thead>
                        <tr>
                            <th>Sucursal</th>
                            <th>Producto</th>
                            <th class="text-center">Stock Actual<br><small>(Unid. de Control)</small></th>
                            <th class="text-center">Fecha de Agotamiento</th>
                            <th class="text-center">Fecha Próximo Despacho</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`;
    }

    /* ── Flujo principal ─────────────────────────────────────── */
    async function calcular() {
        if (_calculando) return;
        _calculando = true;
        _setLoading(true);

        try {
            const { corte, desde, hasta, crecimiento } = _getParams();

            if (!corte || !desde || !hasta) {
                document.getElementById('aa-tabla-container').innerHTML = `
                    <div class="aa-error">
                        <i class="bi bi-exclamation-circle-fill me-2"></i>
                        No se pudo determinar la semana actual. Verifique la configuración del sistema.
                    </div>`;
                return;
            }

            /* Cargar sucursales solo una vez */
            if (!_sucursales.length) {
                try {
                    const rSuc = await fetch('ajax/configuracion_logistica_get_sucursales.php').then(r => r.json());
                    if (rSuc.success) _sucursales = rSuc.sucursales || [];
                } catch (e) {}
            }

            if (!_sucursales.length) {
                document.getElementById('aa-tabla-container').innerHTML = `
                    <div class="aa-error">
                        <i class="bi bi-exclamation-circle-fill me-2"></i>No se encontraron sucursales configuradas.
                    </div>`;
                return;
            }

            /* Procesar SECUENCIALMENTE (una sucursal a la vez) para
               evitar saturar el servidor con peticiones simultáneas a
               pedido_sugerido_pronostico_v2.php → causa 504 en paralelo. */
            const todasIncidencias = [];
            const total = _sucursales.length;
            const spinnerText = document.querySelector('#aa-spinner span');

            for (let i = 0; i < total; i++) {
                const suc = _sucursales[i];
                if (spinnerText) {
                    spinnerText.textContent = `Analizando sucursal ${i + 1} de ${total}: ${suc.nombre}…`;
                }
                const inc = await _calcularSucursal(suc, desde, hasta, corte, crecimiento);
                todasIncidencias.push(...inc);
            }

            _renderTabla(todasIncidencias);

        } catch (err) {
            console.error('[AlertasAgotamiento] Error general:', err);
            const el = document.getElementById('aa-tabla-container');
            if (el) el.innerHTML = `<div class="aa-error"><i class="bi bi-exclamation-circle-fill me-2"></i>Error al calcular las alertas.</div>`;
        } finally {
            _setLoading(false);
            _calculando = false;
        }
    }


    /* ── Inicialización ──────────────────────────────────────── */
    function _init() {
        /* Poblar inputs con valores por defecto */
        const semActual = window.PA_SEMANA_ACTUAL;
        if (semActual) {
            const defCorte = semActual - 1;
            const defDesde = defCorte - 3;
            const elCorte  = document.getElementById('aa-semCorte');
            const elDesde  = document.getElementById('aa-semDesde');
            const elHasta  = document.getElementById('aa-semHasta');
            if (elCorte && !elCorte.value) elCorte.value = defCorte;
            if (elDesde && !elDesde.value) elDesde.value = defDesde;
            if (elHasta && !elHasta.value) elHasta.value = semActual;
        }

        /* Toggle colapsable (clic en header, excepto inputs/botones) */
        const header = document.getElementById('aa-header');
        if (header) {
            header.addEventListener('click', function (e) {
                if (e.target.closest('input,button,label,select')) return;
                document.getElementById('aa-body')?.classList.toggle('aa-collapsed');
                document.getElementById('aa-expand-icon')?.classList.toggle('rotated');
            });
        }

        /* Botón recalcular */
        document.getElementById('aa-btn-calcular')?.addEventListener('click', calcular);

        /* Auto-calcular en el primer arranque */
        calcular();
    }

    /* ── Guard de permiso ──────────────────────────────────────
       Si el servidor no otorgó el permiso 'alerta_agotamiento'
       el módulo no hace nada (el HTML tampoco se renderizó).   */
    if (window.PA_ALERTA_AGOTAMIENTO === false) return;

    /* Esperar a que el DOM esté listo */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', _init);
    } else {
        _init();
    }

})();
