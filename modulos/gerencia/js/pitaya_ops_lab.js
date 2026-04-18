/**
 * Pitaya OPS Lab — Frontend Logic
 * modulos/gerencia/js/pitaya_ops_lab.js
 */
'use strict';

const OPS = {
    AJAX: 'ajax/pitaya_ops_get_datos.php',
    COLORES: {
        Batido: '#58a6ff',
        Waffle: '#e3b341',
        Bowl:   '#a371f7',
        Otro:   '#8b949e',
    },
    charts: {},

    // ── Estado ────────────────────────────────────────────────
    get params() {
        return {
            cod_sucursal: document.getElementById('opsSucursal').value,
            ini:          document.getElementById('opsIni').value,
            fin:          document.getElementById('opsFin').value,
            tipo_dia:     document.getElementById('opsTipoDia').value,
            turno:        document.getElementById('opsTurno').value,
        };
    },

    // ── Init ──────────────────────────────────────────────────
    init() {
        this.cargarSucursales();
        this.bindTabs();
        document.getElementById('opsBtnCargar').addEventListener('click', () => this.cargarTab(this.tabActivo));
        
        // Chart.js Defaults (Forced Light Mode)
        Chart.defaults.color = '#718096';
        Chart.defaults.borderColor = 'rgba(0,0,0,0.05)';
        Chart.defaults.font.family = "'Inter', sans-serif";
    },

    tabActivo: 'resumen',

    bindTabs() {
        document.querySelectorAll('.ops-tab').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.ops-tab').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.ops-tab-panel').forEach(p => p.classList.remove('active'));
                btn.classList.add('active');
                const tab = btn.dataset.tab;
                document.getElementById('panel' + tab.charAt(0).toUpperCase() + tab.slice(1)).classList.add('active');
                this.tabActivo = tab;
                this.cargarTab(tab);
            });
        });
    },

    cargarTab(tab) {
        const map = {
            resumen:    () => this.cargarResumen(),
            llegadas:   () => this.cargarLlegadas(),
            cycle:      () => this.cargarCycleTimes(),
            estaciones: () => this.cargarMixEstaciones(),
            multi:      () => this.cargarMultiEstacion(),
            config:     () => this.cargarConfig(),
        };
        if (map[tab]) map[tab]();
    },

    // ── AJAX helper ───────────────────────────────────────────
    async post(data) {
        const fd = new FormData();
        Object.assign(fd, data); // merge params
        const merged = { ...this.params, ...data };
        const form = new FormData();
        Object.entries(merged).forEach(([k,v]) => form.append(k, v));
        const r = await fetch(this.AJAX, { method: 'POST', body: form });
        return r.json();
    },

    // ── Sucursales ────────────────────────────────────────────
    async cargarSucursales() {
        const d = await this.post({ accion: 'sucursales' });
        if (!d.success) return;
        const sel = document.getElementById('opsSucursal');
        d.sucursales.forEach(s => {
            const o = document.createElement('option');
            o.value = s.codigo; o.textContent = s.nombre;
            sel.appendChild(o);
        });
        this.cargarTab('resumen');
    },

    // ── RESUMEN ───────────────────────────────────────────────
    async cargarResumen() {
        this.show('resumenLoader'); this.hide('resumenContent');
        const d = await this.post({ accion: 'resumen_mes' });
        this.hide('resumenLoader');
        if (!d.success) { this.toast('Error: ' + d.message, 'error'); return; }

        const p = this.params;
        document.getElementById('badgeResumen').textContent = p.ini + ' → ' + p.fin;

        // KPIs
        let totalPedidos = 0, totalUnidades = 0, totalVentas = 0, diasActivos = 0;
        d.resumen.forEach(r => {
            totalPedidos  += +r.total_pedidos;
            totalUnidades += +r.total_unidades;
            totalVentas   += +r.ventas_totales;
            diasActivos   = Math.max(diasActivos, +r.dias_activos);
        });
        const ticket = totalPedidos > 0 ? totalVentas / totalPedidos : 0;
        const pedidosDia = diasActivos > 0 ? totalPedidos / diasActivos : 0;

        document.getElementById('kpiGridResumen').innerHTML = `
            ${this.kpiCard('teal','fa-shopping-bag','Total Pedidos', this.num(totalPedidos), `${diasActivos} días observados`)}
            ${this.kpiCard('blue','fa-blender','Total Unidades', this.num(totalUnidades), `Prom ${(totalUnidades/Math.max(1,totalPedidos)).toFixed(1)} / pedido`)}
            ${this.kpiCard('gold','fa-receipt','Ticket Promedio', 'C$ '+this.num(ticket,2), '')}
            ${this.kpiCard('green','fa-calendar-day','Pedidos / Día', this.num(pedidosDia,1), 'Promedio del período')}
            ${this.kpiCard('purple','fa-store','Ventas Totales', 'C$ '+this.num(totalVentas,2), 'No anuladas')}
        `;

        // Mix global chart
        const mixLabels = d.mix_global.map(x => x.estacion);
        const mixData   = d.mix_global.map(x => +x.pedidos);
        const mixColors = mixLabels.map(l => this.COLORES[l] || this.COLORES.Otro);
        this.destroyChart('chartMixGlobal');
        const bg = '#f6f6f6'; // Matched to Global Body
        
        OPS.charts.chartMixGlobal = new Chart(document.getElementById('chartMixGlobal'), {
            type: 'doughnut',
            data: { 
                labels: mixLabels, 
                datasets: [{ 
                    data: mixData, 
                    backgroundColor: mixColors, 
                    borderColor: bg, 
                    borderWidth: 5, 
                    hoverOffset: 10 
                }] 
            },
            options: { 
                plugins: { legend: { position: 'bottom' } }, 
                cutout: '70%',
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Horas pico
        document.getElementById('horasPicoList').innerHTML = d.horas_pico.map((h,i) => `
            <div style="display:flex;align-items:center;gap:18px;padding:16px 0;border-bottom:1px solid rgba(0,0,0,0.03)">
                <div style="font-size:1.8rem;font-weight:900;color:var(--ops-teal);width:40px;text-align:center;opacity:0.2">${i+1}</div>
                <div>
                    <div style="font-weight:800;font-size:1.05rem;color:var(--ops-text)">${h.hora}:00 – ${h.hora}:59</div>
                    <div style="color:var(--ops-text-muted);font-size:.85rem">${this.num(h.pedidos)} pedidos acumulados</div>
                </div>
                <div style="margin-left:auto">
                    <div class="ops-progress-track" style="width:140px;height:8px;box-shadow:var(--neu-inset-shadow)">
                        <div class="ops-progress-fill" style="width:${Math.round(h.pedidos/d.horas_pico[0].pedidos*100)}%;background:var(--ops-teal)"></div>
                    </div>
                </div>
            </div>
        `).join('');

        this.show('resumenContent');
    },

    // ── LLEGADAS ──────────────────────────────────────────────
    async cargarLlegadas() {
        this.show('llegadasLoader'); this.hide('llegadasContent');
        const d = await this.post({ accion: 'llegadas' });
        this.hide('llegadasLoader');
        if (!d.success) { this.toast('Error: ' + d.message, 'error'); return; }

        const horas  = d.llegadas_por_hora;
        const labels = horas.map(h => `${h.hora}:00`);
        const lambdas = horas.map(h => h.lambda);
        const maxL   = Math.max(...lambdas, 1);

        document.getElementById('badgeLlegadas').textContent = `${horas.length} franjas horarias`;

        this.destroyChart('chartLlegadas');
        OPS.charts.chartLlegadas = new Chart(document.getElementById('chartLlegadas'), {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'λ Pedidos/hora',
                    data: lambdas,
                    backgroundColor: lambdas.map(v => `rgba(81,184,172,${0.4 + 0.6*(v/maxL)})`),
                    borderColor: 'transparent',
                    borderWidth: 0,
                    borderRadius: 8,
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    y: { title: { display: true, text: 'Pedidos / hora (λ)' }, beginAtZero: true },
                    x: { title: { display: true, text: 'Hora del día' } }
                }
            }
        });

        document.getElementById('tbodyLlegadas').innerHTML = horas.map(h => {
            const pct = maxL > 0 ? h.lambda / maxL : 0;
            const bar = `<div class="ops-progress-track" style="width:80px;display:inline-block;vertical-align:middle"><div class="ops-progress-fill" style="width:${Math.round(pct*100)}%"></div></div>`;
            return `<tr>
                <td>${h.hora}:00</td>
                <td><strong>${h.lambda}</strong></td>
                <td>${this.num(h.pedidos_total)}</td>
                <td>${h.dias_obs}</td>
                <td>${h.unidades_prom}</td>
                <td>${bar}</td>
            </tr>`;
        }).join('');

        this.show('llegadasContent');
    },

    // ── CYCLE TIMES ───────────────────────────────────────────
    async cargarCycleTimes() {
        this.show('cycleLoader'); this.hide('cycleContent');
        const d = await this.post({ accion: 'cycle_times' });
        this.hide('cycleLoader');
        if (!d.success) { this.toast('Error: ' + d.message, 'error'); return; }

        const cts = d.cycle_times;
        const labels = cts.map(c => c.estacion);
        const colors = labels.map(l => this.COLORES[l] || this.COLORES.Otro);

        // KPI cards
        document.getElementById('kpiGridCycle').innerHTML = cts.map(c => {
            const col = c.estacion === 'Batido' ? 'blue' : c.estacion === 'Waffle' ? 'gold' : 'purple';
            const icon = c.estacion === 'Batido' ? 'fa-blender' : c.estacion === 'Waffle' ? 'fa-bread-slice' : 'fa-bowl-food';
            return this.kpiCard(col, icon, `Lead Time ${c.estacion}`, `${c.lead_time_prom_min} min`, `Cola: ${c.queue_time_prom_min} min · ${this.num(c.registros)} registros`);
        }).join('');

        // Bar chart lead vs cycle
        this.destroyChart('chartCycleTimes');
        OPS.charts.chartCycleTimes = new Chart(document.getElementById('chartCycleTimes'), {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    { label: 'Lead Time (min)', data: cts.map(c=>c.lead_time_prom_min),  backgroundColor: colors.map(c=>c+'aa'), borderColor: colors, borderWidth:2, borderRadius:5 },
                    { label: 'Cycle Time (min)', data: cts.map(c=>c.cycle_time_prom_min), backgroundColor: colors.map(c=>c+'44'), borderColor: colors, borderWidth:2, borderRadius:5, borderDash:[5,4] },
                ]
            },
            options: { plugins: { legend: { position:'bottom' } }, scales: { y: { beginAtZero:true, title:{ display:true, text:'Minutos' } } } }
        });

        // Queue time doughnut
        this.destroyChart('chartQueueTime');
        OPS.charts.chartQueueTime = new Chart(document.getElementById('chartQueueTime'), {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Tiempo en Cola (min)',
                    data: cts.map(c => c.queue_time_prom_min),
                    backgroundColor: colors.map(c => c+'99'),
                    borderColor: colors,
                    borderWidth: 2,
                    borderRadius: 5,
                }]
            },
            options: { plugins: { legend: { display:false } }, scales: { y: { beginAtZero:true, title:{ display:true, text:'Minutos en cola' } } } }
        });

        // Tabla
        document.getElementById('tbodyCycle').innerHTML = cts.map(c => `
            <tr>
                <td><span class="badge-estacion badge-${c.estacion.toLowerCase()}">${c.estacion}</span></td>
                <td>${this.num(c.registros)}</td>
                <td><strong>${c.lead_time_prom_min}</strong> min</td>
                <td>${c.cycle_time_prom_min} min</td>
                <td>${c.queue_time_prom_min} min</td>
                <td class="muted">${c.lead_min_min} min</td>
                <td class="muted">${c.lead_max_min} min</td>
                <td class="muted">±${c.lead_stddev_min} min</td>
            </tr>
        `).join('');

        this.show('cycleContent');
    },

    // ── MIX ESTACIONES ────────────────────────────────────────
    async cargarMixEstaciones() {
        this.show('estacionesLoader'); this.hide('estacionesContent');
        const d = await this.post({ accion: 'mix_estaciones' });
        this.hide('estacionesLoader');
        if (!d.success) { this.toast('Error: ' + d.message, 'error'); return; }

        const mix = d.mix_por_hora;
        const labels = mix.map(h => `${h.hora}:00`);

        this.destroyChart('chartMixEstaciones');
        OPS.charts.chartMixEstaciones = new Chart(document.getElementById('chartMixEstaciones'), {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    { label:'Batido', data: mix.map(h=>h.Batido), backgroundColor:'rgba(88,166,255,0.75)', borderRadius:3 },
                    { label:'Waffle', data: mix.map(h=>h.Waffle), backgroundColor:'rgba(227,179,65,0.75)',  borderRadius:3 },
                    { label:'Bowl',   data: mix.map(h=>h.Bowl),   backgroundColor:'rgba(163,113,247,0.75)', borderRadius:3 },
                ]
            },
            options: { plugins:{ legend:{ position:'bottom' } }, scales:{ x:{ stacked:true }, y:{ stacked:true, beginAtZero:true, title:{ display:true, text:'Pedidos prom/día' } } } }
        });

        document.getElementById('tbodyMix').innerHTML = mix.map(h => `
            <tr>
                <td>${h.hora}:00</td>
                <td>${h.Batido}</td>
                <td>${h.Waffle}</td>
                <td>${h.Bowl}</td>
                <td>${h.total}</td>
                <td>${h.pct_Batido}%</td>
                <td>${h.pct_Waffle}%</td>
                <td>${h.pct_Bowl}%</td>
            </tr>
        `).join('');

        this.show('estacionesContent');
    },

    // ── MULTI ESTACION ────────────────────────────────────────
    async cargarMultiEstacion() {
        this.show('multiLoader'); this.hide('multiContent');
        const d = await this.post({ accion: 'multi_estacion' });
        this.hide('multiLoader');
        if (!d.success) { this.toast('Error: ' + d.message, 'error'); return; }

        const items = d.multi_estacion;
        const total = d.total_pedidos;

        // KPIs agrupados por num_estaciones
        const byN = {};
        items.forEach(i => { byN[i.num_estaciones] = (byN[i.num_estaciones]||0) + i.num_pedidos; });
        const kpis = Object.entries(byN).sort((a,b)=>+a[0]-+b[0]);
        const icons = { 1:'fa-circle', 2:'fa-circle-half-stroke', 3:'fa-circle-dot' };
        const cols  = { 1:'teal', 2:'orange', 3:'red' };
        document.getElementById('kpiGridMulti').innerHTML = kpis.map(([n,cnt]) => {
            const pct = total > 0 ? (cnt/total*100).toFixed(1) : 0;
            return this.kpiCard(cols[n]||'blue', icons[n]||'fa-circle-nodes',
                `${n} Estación${+n>1?'es':''}`, this.num(cnt) + ' pedidos', pct + '% del total');
        }).join('');

        // Doughnut
        const labels = kpis.map(([n]) => `${n} estación${+n>1?'es':''}`);
        const data   = kpis.map(([,c]) => c);
        const colors = ['#51B8AC','#e67e22','#d9534f'];
        const bg = '#f6f6f6'; // Matched to Global Body

        this.destroyChart('chartMultiDist');
        OPS.charts.chartMultiDist = new Chart(document.getElementById('chartMultiDist'), {
            type: 'doughnut',
            data: { labels, datasets: [{ data, backgroundColor: colors, borderColor: bg, borderWidth: 5, hoverOffset: 10 }] },
            options: { 
                plugins:{ legend:{ position:'bottom' } }, 
                cutout:'65%',
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Tabla
        document.getElementById('tbodyMulti').innerHTML = items.map(i => {
            const parts = (i.estaciones_combo||'').split(',');
            const pills = parts.map(p => `<span class="badge-estacion badge-${p.trim().toLowerCase()}">${p.trim()}</span>`).join(' ');
            return `<tr>
                <td class="ops-combo-pill">${pills}</td>
                <td style="text-align:center">${i.num_estaciones}</td>
                <td>${this.num(i.num_pedidos)}</td>
                <td>${i.items_prom}</td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px">
                        <div class="ops-progress-track" style="width:70px;display:inline-block">
                            <div class="ops-progress-fill" style="width:${i.pct}%"></div>
                        </div>
                        ${i.pct}%
                    </div>
                </td>
            </tr>`;
        }).join('');

        this.show('multiContent');
    },

    // ── CONFIG ────────────────────────────────────────────────
    async cargarConfig() {
        this.show('configLoader'); this.hide('configContent');
        const d = await this.post({ accion: 'config' });
        this.hide('configLoader');
        if (!d.success) { this.toast('Error: ' + d.message, 'error'); return; }

        const estIcons = { Batido:'fa-blender', Waffle:'fa-bread-slice', Bowl:'fa-bowl-food', General:'fa-cogs' };
        let html = '';
        for (const [est, params] of Object.entries(d.config)) {
            html += `<div class="ops-config-group">
                <div class="ops-config-group-title"><i class="fas ${estIcons[est]||'fa-cog'}"></i> ${est}</div>`;
            for (const [param, info] of Object.entries(params)) {
                const unit = param.includes('_min') ? 'min' : param.includes('_pct') ? '%' : 'u';
                html += `
                <div class="ops-config-row" data-est="${est}" data-param="${param}">
                    <div class="ops-config-label">${param.replace(/_/g,' ')} <small>${info.descripcion||''}</small></div>
                    <input type="number" class="ops-config-input" value="${info.valor}" step="0.1" data-original="${info.valor}">
                    <span class="ops-config-unit">${unit}</span>
                    <button class="ops-config-save-btn" onclick="OPS.guardarConfig(this)"><i class="fas fa-check"></i> Guardar</button>
                </div>`;
            }
            html += '</div>';
        }
        document.getElementById('configPanels').innerHTML = html;

        // Mostrar botón al cambiar valor
        document.querySelectorAll('.ops-config-input').forEach(inp => {
            inp.addEventListener('input', () => {
                const btn = inp.nextElementSibling.nextElementSibling;
                if (inp.value !== inp.dataset.original) {
                    inp.classList.add('dirty');
                    btn.style.opacity = '1';
                    btn.style.pointerEvents = 'auto';
                } else {
                    inp.classList.remove('dirty');
                    btn.style.opacity = '0';
                    btn.style.pointerEvents = 'none';
                }
            });
            inp.addEventListener('keydown', e => { if (e.key === 'Enter') inp.nextElementSibling.nextElementSibling.click(); });
        });

        this.show('configContent');
    },

    async guardarConfig(btn) {
        const row = btn.closest('.ops-config-row');
        const inp = row.querySelector('.ops-config-input');
        const est = row.dataset.est, param = row.dataset.param, valor = inp.value;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        const d = await this.post({ accion:'guardar_config', tipo_estacion:est, parametro:param, valor });
        if (d.success) {
            inp.dataset.original = valor;
            inp.classList.remove('dirty');
            btn.innerHTML = '<i class="fas fa-check"></i> ✓';
            btn.classList.add('saved');
            btn.style.opacity = '0'; btn.style.pointerEvents = 'none';
            setTimeout(() => { btn.classList.remove('saved'); btn.innerHTML='<i class="fas fa-check"></i> Guardar'; }, 2000);
            this.toast('Parámetro actualizado', 'success');
        } else {
            btn.innerHTML = '<i class="fas fa-times"></i> Error';
            this.toast('Error al guardar: ' + d.message, 'error');
        }
    },

    // ── Helpers ───────────────────────────────────────────────
    kpiCard(color, icon, label, valor, sub) {
        return `<div class="ops-kpi-card">
            <div class="ops-kpi-icon ${color}"><i class="fas ${icon}"></i></div>
            <div class="ops-kpi-body">
                <div class="ops-kpi-label">${label}</div>
                <div class="ops-kpi-valor">${valor}</div>
                ${sub ? `<div class="ops-kpi-sub">${sub}</div>` : ''}
            </div>
        </div>`;
    },

    num(n, dec=0) {
        return (+n).toLocaleString('es-NI', { minimumFractionDigits:dec, maximumFractionDigits:dec });
    },

    show(id) { const el = document.getElementById(id); if(el) el.style.display=''; },
    hide(id) { const el = document.getElementById(id); if(el) el.style.display='none'; },

    destroyChart(id) {
        if (OPS.charts[id]) { OPS.charts[id].destroy(); delete OPS.charts[id]; }
    },

    toast(msg, type='info') {
        const t = document.getElementById('opsToast');
        const icons = { success:'fa-check-circle', error:'fa-exclamation-circle', info:'fa-info-circle' };
        t.className = `ops-toast ${type}`;
        t.innerHTML = `<i class="fas ${icons[type]||'fa-info-circle'}"></i> ${msg}`;
        t.style.display = 'flex';
        clearTimeout(OPS._toastTimer);
        OPS._toastTimer = setTimeout(() => { t.style.display='none'; }, 3500);
    },
};

document.addEventListener('DOMContentLoaded', () => OPS.init());
