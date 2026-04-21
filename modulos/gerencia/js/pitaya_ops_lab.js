/**
 * Pitaya OPS Lab — Frontend Logic
 * modulos/gerencia/js/pitaya_ops_lab.js
 */
'use strict';

const OPS = {
    AJAX: 'ajax/pitaya_ops_get_datos.php',
    COLORES: {
        Batido: '#64b5f6', // Light Blue
        Waffle: '#ffb74d', // Light Gold
        Bowl: '#a567d1', // Light Purple
        Otro: '#b0bec5', // Muted Gray-Blue
    },
    charts: {},

    // ── Estado ────────────────────────────────────────────────
    get params() {
        return {
            cod_sucursal: document.getElementById('opsSucursal').value,
            ini: document.getElementById('opsIni').value,
            fin: document.getElementById('opsFin').value,
            tipo_dia: document.getElementById('opsTipoDia').value,
            turno: document.getElementById('opsTurno').value,
        };
    },

    // ── Init ──────────────────────────────────────────────────
    init() {
        this.cargarSucursales();
        this.bindTabs();
        document.getElementById('opsBtnCargar').addEventListener('click', () => this.cargarTab(this.tabActivo));

        // Chart.js Defaults (Forced Light Mode - Pastel Palette)
        Chart.defaults.color = '#78909c';
        Chart.defaults.borderColor = 'rgba(0,0,0,0.03)';
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
            resumen:       () => this.cargarResumen(),
            llegadas:      () => this.cargarLlegadas(),
            cycle:         () => this.cargarCycleTimes(),
            estaciones:    () => this.cargarMixEstaciones(),
            multi:         () => this.cargarMultiEstacion(),
            config:        () => this.cargarConfig(),
            planificador:  () => this.initPlanificador(),
            simulador:     () => this.initSimulador(),
            lean:          () => this.cargarLean(),
        };
        if (map[tab]) map[tab]();
    },

    // ── AJAX helper ───────────────────────────────────────────
    async post(data) {
        const fd = new FormData();
        Object.assign(fd, data); // merge params
        const merged = { ...this.params, ...data };
        const form = new FormData();
        Object.entries(merged).forEach(([k, v]) => form.append(k, v));
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
            totalPedidos += +r.total_pedidos;
            totalUnidades += +r.total_unidades;
            totalVentas += +r.ventas_totales;
            diasActivos = Math.max(diasActivos, +r.dias_activos);
        });
        const ticket = totalPedidos > 0 ? totalVentas / totalPedidos : 0;
        const pedidosDia = diasActivos > 0 ? totalPedidos / diasActivos : 0;

        document.getElementById('kpiGridResumen').innerHTML = `
            ${this.kpiCard('teal', 'fa-shopping-bag', 'Total Pedidos', this.num(totalPedidos), `${diasActivos} días observados`)}
            ${this.kpiCard('blue', 'fa-blender', 'Total Unidades', this.num(totalUnidades), `Prom ${(totalUnidades / Math.max(1, totalPedidos)).toFixed(1)} / pedido`)}
            ${this.kpiCard('gold', 'fa-receipt', 'Ticket Promedio', 'C$ ' + this.num(ticket, 2), '')}
            ${this.kpiCard('green', 'fa-calendar-day', 'Pedidos / Día', this.num(pedidosDia, 1), 'Promedio del período')}
            ${this.kpiCard('purple', 'fa-store', 'Ventas Totales', 'C$ ' + this.num(totalVentas, 2), 'No anuladas')}
        `;

        // Mix global chart
        const mixLabels = d.mix_global.map(x => x.estacion);
        const mixData = d.mix_global.map(x => +x.pedidos);
        const mixColors = mixLabels.map(l => this.COLORES[l] || this.COLORES.Otro);
        this.destroyChart('chartMixGlobal');
        const bg = '#f6f6f6'; // Forced Light Mode

        OPS.charts.chartMixGlobal = new Chart(document.getElementById('chartMixGlobal'), {
            type: 'doughnut',
            data: {
                labels: mixLabels,
                datasets: [{
                    data: mixData,
                    backgroundColor: mixColors,
                    borderColor: bg,
                    borderWidth: 6,
                    hoverOffset: 12
                }]
            },
            options: {
                plugins: { legend: { position: 'bottom' } },
                cutout: '75%',
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Horas pico
        document.getElementById('horasPicoList').innerHTML = d.horas_pico.map((h, i) => `
            <div style="display:flex;align-items:center;gap:18px;padding:16px 0;border-bottom:1px solid rgba(0,0,0,0.03)">
                <div style="font-size:1.8rem;font-weight:900;color:var(--ops-teal);width:40px;text-align:center;opacity:0.2">${i + 1}</div>
                <div>
                    <div style="font-weight:800;font-size:1.05rem;color:var(--ops-text)">${h.hora}:00 – ${h.hora}:59</div>
                    <div style="color:var(--ops-text-muted);font-size:.85rem">${this.num(h.pedidos)} pedidos acumulados</div>
                </div>
                <div style="margin-left:auto">
                    <div class="ops-progress-track" style="width:140px;height:8px;box-shadow:var(--neu-inset-shadow)">
                        <div class="ops-progress-fill" style="width:${Math.round(h.pedidos / d.horas_pico[0].pedidos * 100)}%;background:var(--ops-teal)"></div>
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

        const horas = d.llegadas_por_hora;
        const labels = horas.map(h => `${h.hora}:00`);
        const lambdas = horas.map(h => h.lambda);
        const maxL = Math.max(...lambdas, 1);

        document.getElementById('badgeLlegadas').textContent = `${horas.length} franjas horarias`;

        this.destroyChart('chartLlegadas');
        OPS.charts.chartLlegadas = new Chart(document.getElementById('chartLlegadas'), {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'λ Pedidos/hora',
                    data: lambdas,
                    backgroundColor: lambdas.map(v => `rgba(81, 184, 172, ${0.4 + 0.6 * (v / maxL)})`),
                    borderColor: 'transparent',
                    borderWidth: 0,
                    borderRadius: 10,
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { title: { display: true, text: 'Pedidos / hora (λ)' }, beginAtZero: true },
                    x: { title: { display: true, text: 'Hora del día' } }
                }
            }
        });

        document.getElementById('tbodyLlegadas').innerHTML = horas.map(h => {
            const pct = maxL > 0 ? h.lambda / maxL : 0;
            const bar = `<div class="ops-progress-track" style="width:80px;display:inline-block;vertical-align:middle"><div class="ops-progress-fill" style="width:${Math.round(pct * 100)}%"></div></div>`;
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
            return this.kpiCard(col, icon,
                `Tiempo de Caja — ${c.estacion}`,
                `${c.tiempo_caja_prom_min ?? c.lead_time_prom_min} min`,
                `Ingreso productos: ${c.tiempo_ingreso_prom_min ?? c.cycle_time_prom_min} min · ${this.num(c.registros)} registros`);
        }).join('');

        // Bar chart: tiempo de caja vs tiempo ingreso productos
        this.destroyChart('chartCycleTimes');
        OPS.charts.chartCycleTimes = new Chart(document.getElementById('chartCycleTimes'), {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    { label: 'Tiempo de Caja (HoraCreado→HoraImpreso)', data: cts.map(c => c.tiempo_caja_prom_min ?? c.lead_time_prom_min), backgroundColor: colors.map(c => c + 'aa'), borderColor: colors, borderWidth: 2, borderRadius: 5 },
                    { label: 'Ingreso Productos (HoraIngresoProducto→HoraImpreso)', data: cts.map(c => c.tiempo_ingreso_prom_min ?? c.cycle_time_prom_min), backgroundColor: colors.map(c => c + '44'), borderColor: colors, borderWidth: 2, borderRadius: 5 },
                ]
            },
            options: { 
                plugins: { legend: { position: 'bottom' } }, 
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, title: { display: true, text: 'Minutos' } } } 
            }
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
                    backgroundColor: colors.map(c => c + '99'),
                    borderColor: colors,
                    borderWidth: 2,
                    borderRadius: 5,
                }]
            },
            options: { 
                plugins: { legend: { display: false } }, 
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, title: { display: true, text: 'Minutos en cola' } } } 
            }
        });

        // Tabla
        document.getElementById('tbodyCycle').innerHTML = cts.map(c => `
            <tr>
                <td><span class="badge-estacion badge-${c.estacion.toLowerCase()}">${c.estacion}</span></td>
                <td>${this.num(c.registros)}</td>
                <td><strong>${c.tiempo_caja_prom_min ?? c.lead_time_prom_min}</strong> min</td>
                <td>${c.tiempo_ingreso_prom_min ?? c.cycle_time_prom_min} min</td>
                <td>${c.queue_time_prom_min} min</td>
                <td class="muted">${c.lead_min_min ?? c.tiempo_caja_min_min} min</td>
                <td class="muted">${c.lead_max_min ?? c.tiempo_caja_max_min} min</td>
                <td class="muted">±${c.lead_stddev_min ?? c.tiempo_caja_std_min} min</td>
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
                    { label: 'Batido', data: mix.map(h => h.Batido), backgroundColor: 'rgba(88,166,255,0.75)', borderRadius: 3 },
                    { label: 'Waffle', data: mix.map(h => h.Waffle), backgroundColor: 'rgba(227,179,65,0.75)', borderRadius: 3 },
                    { label: 'Bowl', data: mix.map(h => h.Bowl), backgroundColor: 'rgba(163,113,247,0.75)', borderRadius: 3 },
                ]
            },
            options: { 
                plugins: { legend: { position: 'bottom' } }, 
                responsive: true,
                maintainAspectRatio: false,
                scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, title: { display: true, text: 'Pedidos prom/día' } } } 
            }
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
        items.forEach(i => { byN[i.num_estaciones] = (byN[i.num_estaciones] || 0) + i.num_pedidos; });
        const kpis = Object.entries(byN).sort((a, b) => +a[0] - +b[0]);
        const icons = { 1: 'fa-circle', 2: 'fa-circle-half-stroke', 3: 'fa-circle-dot' };
        const cols = { 1: 'teal', 2: 'orange', 3: 'red' };
        document.getElementById('kpiGridMulti').innerHTML = kpis.map(([n, cnt]) => {
            const pct = total > 0 ? (cnt / total * 100).toFixed(1) : 0;
            return this.kpiCard(cols[n] || 'blue', icons[n] || 'fa-circle-nodes',
                `${n} Estación${+n > 1 ? 'es' : ''}`, this.num(cnt) + ' pedidos', pct + '% del total');
        }).join('');

        // Doughnut
        const labels = kpis.map(([n]) => `${n} estación${+n > 1 ? 'es' : ''}`);
        const data = kpis.map(([, c]) => c);
        const colors = ['#51B8AC', '#e67e22', '#e57373'];
        const bg = '#f6f6f6'; // Forced Light Mode

        this.destroyChart('chartMultiDist');
        OPS.charts.chartMultiDist = new Chart(document.getElementById('chartMultiDist'), {
            type: 'doughnut',
            data: { labels, datasets: [{ data, backgroundColor: colors, borderColor: bg, borderWidth: 6, hoverOffset: 12 }] },
            options: {
                plugins: { legend: { position: 'bottom' } },
                cutout: '70%',
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Tabla
        document.getElementById('tbodyMulti').innerHTML = items.map(i => {
            const parts = (i.estaciones_combo || '').split(',');
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

    // ── PLANIFICADOR DE CAPACIDAD ─────────────────────────────
    _planBound: false,
    initPlanificador() {
        if (!this._planBound) {
            this._planBound = true;
            document.getElementById('planUtil').addEventListener('input', () => {
                document.getElementById('planUtilVal').textContent = document.getElementById('planUtil').value;
            });
            document.getElementById('btnCalcularPlan').addEventListener('click', () => this.calcularPlan());
        }
    },

    async calcularPlan() {
        const utilMax = +document.getElementById('planUtil').value / 100;
        const tipoDia = document.getElementById('planTipoDia').value;
        const suc     = document.getElementById('opsSucursal').value;
        const sucNom  = document.getElementById('opsSucursal').selectedOptions[0]?.text || 'Todas las tiendas';

        if (!suc) {
            this.toast('⚠️ Selecciona una sucursal específica para el planificador', 'info');
            return;
        }

        document.getElementById('planLoader').style.display = 'flex';
        document.getElementById('planResultados').style.display = 'none';

        // Cargar llegadas + mix + config en paralelo
        const [dLleg, dMix, dCfg] = await Promise.all([
            this.post({ accion: 'llegadas',       tipo_dia: tipoDia }),
            this.post({ accion: 'mix_estaciones', tipo_dia: tipoDia }),
            this.post({ accion: 'config' }),
        ]);

        document.getElementById('planLoader').style.display = 'none';
        if (!dLleg.success || !dMix.success || !dCfg.success) {
            this.toast('Error cargando datos del planificador', 'error');
            return;
        }

        // Extraer cycle times de configuración (en minutos)
        const cfg = dCfg.config;
        const ctBat = (+(cfg.Batido?.tiempo_licuado_min?.valor  || 2)) + (+(cfg.Batido?.tiempo_servido_min?.valor || 0.5));
        const ctWaf = (+(cfg.Waffle?.tiempo_mezcla_min?.valor   || 2)) + (+(cfg.Waffle?.tiempo_coccion_min?.valor || 5)) + (+(cfg.Waffle?.tiempo_emplato_min?.valor || 1));
        const ctBow = (+(cfg.Bowl?.tiempo_licuado_min?.valor    || 3)) + (+(cfg.Bowl?.tiempo_servido_min?.valor  || 2));
        const batBatch = +(cfg.Batido?.max_batch_size?.valor || 3);
        const bowBatch = +(cfg.Bowl?.max_batch_size?.valor   || 2);

        // Índices de llegadas y mix por hora
        const llegIdx = {};
        dLleg.llegadas_por_hora.forEach(h => { llegIdx[h.hora] = h.lambda; });
        const mixIdx = {};
        dMix.mix_por_hora.forEach(h => {
            mixIdx[h.hora] = {
                pB: h.pct_Batido / 100,
                pW: h.pct_Waffle / 100,
                pO: h.pct_Bowl   / 100,
            };
        });

        const lambdaMax = Math.max(...dLleg.llegadas_por_hora.map(h => h.lambda), 0.01);
        const rows = [];
        let maxOp = 0, horaPicoOp = 6, maxLam = 0, horaPicoLam = 6;

        for (let hora = 6; hora <= 22; hora++) {
            const lam  = llegIdx[hora] ?? 0;
            const mix  = mixIdx[hora] ?? { pB: 0.5, pW: 0.3, pO: 0.2 };

            const lamBat = lam * mix.pB;
            const lamWaf = lam * mix.pW;
            const lamBow = lam * mix.pO;

            // M/M/c: c_min = ceil(λ × CT / 60 / utilMax)
            // Para batidos: batch reduce carga efectiva
            const cBat = lamBat > 0 ? Math.ceil((lamBat * (ctBat / batBatch)) / 60 / utilMax) : 0;
            const cWaf = lamWaf > 0 ? Math.ceil((lamWaf * ctWaf)              / 60 / utilMax) : 0;
            const cBow = lamBow > 0 ? Math.ceil((lamBow * (ctBow / bowBatch)) / 60 / utilMax) : 0;

            // Carga total en persona-minutos por hora
            const cargaTotal = (lamBat * ctBat + lamWaf * ctWaf + lamBow * ctBow) / 60; // horas-persona
            // +1 cajero si hay demanda; operarios mín = max(máquinas activas, carga/turno)
            const estActivas = (cBat > 0 ? 1 : 0) + (cWaf > 0 ? 1 : 0) + (cBow > 0 ? 1 : 0);
            let opMin = 0;
            if (lam > 0) {
                // En waffles pueden trabajar hasta 3 en paralelo, en batidos 1 operario por 2 licuadoras
                const opBat = Math.ceil(cBat / 2);       // 1 op por cada 2 licuadoras
                const opWaf = Math.min(cWaf, 3);          // máx 3 en waffles (mezcla/cocción/decorado)
                const opBow = Math.ceil(cBow / 1);        // 1 op por motor
                opMin = Math.max(estActivas, opBat + opWaf + opBow) + (lam > 2 ? 1 : 0); // +cajero si hay carga
            }
            opMin = Math.min(opMin, 7); // cap máximo físico

            if (opMin > maxOp)  { maxOp = opMin; horaPicoOp = hora; }
            if (lam > maxLam)   { maxLam = lam;  horaPicoLam = hora; }

            // Nivel de carga
            const pct = lambdaMax > 0 ? lam / lambdaMax : 0;
            let nivel = 'BAJA', nivelColor = '#51B8AC33';
            if (pct > 0.75)     { nivel = 'PICO CRÍTICO'; nivelColor = '#e74c3c22'; }
            else if (pct > 0.5) { nivel = 'ALTA';         nivelColor = '#e67e2222'; }
            else if (pct > 0.2) { nivel = 'MEDIA';        nivelColor = '#51B8AC22'; }
            else if (lam === 0) { nivel = '—';            nivelColor = 'transparent'; }

            rows.push({ hora, lam, lamBat, lamWaf, lamBow, cBat, cWaf, cBow, opMin, nivel, nivelColor, pct });
        }

        // ── Renderizar alerta de sucursal ─────────────────────
        document.getElementById('planAlertaSucursal').innerHTML =
            `<div class="ops-alert ops-alert-ok" style="margin:0;">
                <i class="fas fa-store me-1"></i> Plan calculado para: <strong>${sucNom}</strong> ·
                Utilización objetivo: <strong>${Math.round(utilMax*100)}%</strong> · Tipo de día: <strong>${tipoDia}</strong>
                &nbsp;|&nbsp; <span style="color:var(--ops-red);font-weight:700;">Hora pico demanda: ${horaPicoLam}:00</span> ·
                <span style="color:var(--ops-teal);font-weight:700;">Máx. operarios requeridos: ${maxOp}</span>
             </div>`;

        // ── Heatmap ───────────────────────────────────────────
        document.getElementById('badgePlanHoraPico').textContent = `Pico: ${horaPicoLam}:00 (λ=${maxLam.toFixed(1)})`;
        document.getElementById('planHeatmap').innerHTML = rows.map(r => {
            const h = Math.round(40 + r.pct * 120);
            const bg = r.lam === 0 ? '#f0f0f0'
                     : r.pct > 0.75 ? '#e74c3c'
                     : r.pct > 0.5  ? '#e67e22'
                     : r.pct > 0.2  ? '#51B8AC'
                     : '#a8d8d3';
            return `<div title="${r.hora}:00 — λ=${r.lam} · ${r.opMin} operarios" style="display:flex;flex-direction:column;align-items:center;gap:3px;cursor:default;">
                <div style="width:36px;height:${h}px;background:${bg};border-radius:5px 5px 0 0;transition:height .3s;"></div>
                <div style="font-size:.68rem;color:#888;">${r.hora}:00</div>
            </div>`;
        }).join('');

        // ── Tabla por hora ────────────────────────────────────
        document.getElementById('badgePlanTotal').textContent = `${rows.filter(r=>r.lam>0).length} horas activas`;
        document.getElementById('tbodyPlan').innerHTML = rows.map(r => {
            if (r.lam === 0) return `<tr style="opacity:.4;">
                <td>${r.hora}:00–${r.hora+1}:00</td>
                <td colspan="9" style="text-align:center;font-size:.82rem;color:#aaa;">Sin demanda</td>
            </tr>`;
            const maqBg  = c => c >= 3 ? 'color:var(--ops-red);font-weight:800;' : c >= 2 ? 'color:var(--ops-gold);font-weight:700;' : '';
            const opBg   = o => o >= 5 ? 'background:#e74c3c22;' : o >= 4 ? 'background:#e67e2222;' : o >= 3 ? 'background:#fff3cd;' : '';
            const badge  = (n, c) => `<span style="display:inline-block;background:${c};color:white;border-radius:12px;padding:2px 9px;font-size:.78rem;font-weight:700;">${n}</span>`;
            const nb = r.nivel === 'PICO CRÍTICO' ? badge(r.nivel,'#e74c3c')
                     : r.nivel === 'ALTA'         ? badge(r.nivel,'#e67e22')
                     : r.nivel === 'MEDIA'        ? badge(r.nivel,'#51B8AC')
                     : `<span style="color:#aaa;font-size:.8rem;">${r.nivel}</span>`;
            return `<tr style="background:${r.nivelColor};">
                <td><strong>${r.hora}:00</strong></td>
                <td><strong>${r.lam.toFixed(1)}</strong></td>
                <td style="color:var(--ops-blue);">${r.lamBat.toFixed(1)}</td>
                <td><span style="${maqBg(r.cBat)}">${r.cBat || '—'}</span></td>
                <td style="color:var(--ops-gold);">${r.lamWaf.toFixed(1)}</td>
                <td><span style="${maqBg(r.cWaf)}">${r.cWaf || '—'}</span></td>
                <td style="color:var(--ops-purple);">${r.lamBow.toFixed(1)}</td>
                <td><span style="${maqBg(r.cBow)}">${r.cBow || '—'}</span></td>
                <td style="font-size:1.1rem;font-weight:900;text-align:center;${opBg(r.opMin)}">${r.opMin}</td>
                <td>${nb}</td>
            </tr>`;
        }).join('');

        // ── KPIs resumen ──────────────────────────────────────
        const horasActivas = rows.filter(r => r.lam > 0);
        const opPromedio   = horasActivas.length ? (horasActivas.reduce((a,r)=>a+r.opMin,0)/horasActivas.length).toFixed(1) : 0;
        const maqMaxBat    = Math.max(...rows.map(r=>r.cBat));
        const maqMaxWaf    = Math.max(...rows.map(r=>r.cWaf));
        const maqMaxBow    = Math.max(...rows.map(r=>r.cBow));
        document.getElementById('kpiGridPlan').innerHTML = [
            this.kpiCard('red',    'fa-users',      `Máx. operarios (${horaPicoOp}:00)`, maxOp + ' personas',  `Pico de demanda`),
            this.kpiCard('teal',   'fa-users',      'Promedio operarios/hora',            opPromedio + ' personas', `Horas activas`),
            this.kpiCard('blue',   'fa-blender',    'Licuadoras necesarias',              maqMaxBat + ' máx',   `Hora pico batidos`),
            this.kpiCard('gold',   'fa-bread-slice','Waffleras necesarias',               maqMaxWaf + ' máx',   `Hora pico waffles`),
            this.kpiCard('purple', 'fa-bowl-food',  'Motores necesarios',                 maqMaxBow + ' máx',   `Hora pico bowls`),
        ].join('');

        // ── Gráfica stacked ───────────────────────────────────
        const labels = rows.map(r => r.hora + ':00');
        this.destroyChart('chartPlanDotacion');
        OPS.charts.chartPlanDotacion = new Chart(document.getElementById('chartPlanDotacion'), {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    { label: 'Operarios recomendados', data: rows.map(r=>r.opMin), backgroundColor: rows.map(r=>
                        r.pct>0.75?'rgba(231,76,60,.8)':r.pct>0.5?'rgba(230,126,34,.8)':r.pct>0.2?'rgba(81,184,172,.8)':'rgba(200,200,200,.4)'),
                      borderRadius: 5 },
                    { label: 'λ demanda (escala)', data: rows.map(r=>+(r.lam/lambdaMax*maxOp).toFixed(2)),
                      type: 'line', borderColor: '#0E544C', borderWidth: 2, borderDash:[4,3],
                      pointRadius: 3, tension: 0.4, fill: false, yAxisID: 'y' },
                ]
            },
            options: {
                plugins: { legend: { position: 'bottom' },
                    tooltip: { callbacks: { label: ctx => ctx.datasetIndex===0
                        ? `${ctx.raw} operarios recomendados`
                        : `Demanda relativa` }}},
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Operarios' },
                         ticks: { stepSize: 1 } }
                },
                responsive: true,
                maintainAspectRatio: false
            }
        });

        document.getElementById('planResultados').style.display = '';
        this.toast('Plan calculado para ' + sucNom, 'success');
    },

    // ── CONFIG ────────────────────────────────────────────────
    async cargarConfig() {
        this.show('configLoader'); this.hide('configContent');
        const d = await this.post({ accion: 'config' });
        this.hide('configLoader');
        if (!d.success) { this.toast('Error: ' + d.message, 'error'); return; }

        const estIcons = { Batido: 'fa-blender', Waffle: 'fa-bread-slice', Bowl: 'fa-bowl-food', General: 'fa-cogs' };
        let html = '';
        for (const [est, params] of Object.entries(d.config)) {
            html += `<div class="ops-config-group">
                <div class="ops-config-group-title"><i class="fas ${estIcons[est] || 'fa-cog'}"></i> ${est}</div>`;
            for (const [param, info] of Object.entries(params)) {
                const unit = param.includes('_min') ? 'min' : param.includes('_pct') ? '%' : 'u';
                html += `
                <div class="ops-config-row" data-est="${est}" data-param="${param}">
                    <div class="ops-config-label">${param.replace(/_/g, ' ')} <small>${info.descripcion || ''}</small></div>
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
        const d = await this.post({ accion: 'guardar_config', tipo_estacion: est, parametro: param, valor });
        if (d.success) {
            inp.dataset.original = valor;
            inp.classList.remove('dirty');
            btn.innerHTML = '<i class="fas fa-check"></i> ✓';
            btn.classList.add('saved');
            btn.style.opacity = '0'; btn.style.pointerEvents = 'none';
            setTimeout(() => { btn.classList.remove('saved'); btn.innerHTML = '<i class="fas fa-check"></i> Guardar'; }, 2000);
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

    num(n, dec = 0) {
        return (+n).toLocaleString('es-NI', { minimumFractionDigits: dec, maximumFractionDigits: dec });
    },

    show(id) { const el = document.getElementById(id); if (el) el.style.display = ''; },
    hide(id) { const el = document.getElementById(id); if (el) el.style.display = 'none'; },

    destroyChart(id) {
        if (OPS.charts[id]) { OPS.charts[id].destroy(); delete OPS.charts[id]; }
    },

    toast(msg, type = 'info') {
        const t = document.getElementById('opsToast');
        const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', info: 'fa-info-circle' };
        t.className = `ops-toast ${type}`;
        t.innerHTML = `<i class="fas ${icons[type] || 'fa-info-circle'}"></i> ${msg}`;
        t.style.display = 'flex';
        clearTimeout(OPS._toastTimer);
        OPS._toastTimer = setTimeout(() => { t.style.display = 'none'; }, 3500);
    },

    _simBound: false, _simBase: null,
    initSimulador() {
        if (this._simBound) return; this._simBound = true;
        [['simPersonas', 'simPersonasVal', 0], ['sBatLic', 'sBatLicVal', 2], ['sBatSer', 'sBatSerVal', 2], ['sBatLim', 'sBatLimVal', 2], ['sWafMez', 'sWafMezVal', 2], ['sWafCoc', 'sWafCocVal', 2], ['sWafEmp', 'sWafEmpVal', 2], ['sWafLim', 'sWafLimVal', 2], ['sBowLic', 'sBowLicVal', 2], ['sBowSer', 'sBowSerVal', 2], ['sBowLim', 'sBowLimVal', 2]
        ].forEach(([s, v, d]) => { const el = document.getElementById(s); if (el) el.addEventListener('input', () => document.getElementById(v).textContent = (+el.value).toFixed(d)); });
        document.getElementById('btnEjecutarSim').addEventListener('click', () => this.ejecutarSimulacion(false));
        document.getElementById('btnCompararSim').addEventListener('click', () => this.ejecutarSimulacion(true));
    },
    _gsp() { const g = id => document.getElementById(id); return { turno: g('simTurno').value, tipoDia: g('simTipoDia').value, personas: +g('simPersonas').value, Batido: { lic: +g('sBatLic').value, ser: +g('sBatSer').value, lim: +g('sBatLim').value, maq: +g('sBatMaq').value, batch: +g('sBatBatch').value }, Waffle: { mez: +g('sWafMez').value, coc: +g('sWafCoc').value, emp: +g('sWafEmp').value, lim: +g('sWafLim').value, maq: +g('sWafMaq').value }, Bowl: { lic: +g('sBowLic').value, ser: +g('sBowSer').value, lim: +g('sBowLim').value, maq: +g('sBowMaq').value, batch: +g('sBowBatch').value } }; },
    async ejecutarSimulacion(cmp) {
        document.getElementById('simLoader').style.display = 'flex'; this.hide('simResultados');
        const p = this._gsp(), d = await this.post({ accion: 'lambda_simulador', tipo_dia: p.tipoDia });
        document.getElementById('simLoader').style.display = 'none';
        if (!d.success) { this.toast('Error: ' + d.message, 'error'); return; }
        const res = this._runDES(p, d.llegadas_por_hora, d.mix_por_hora);
        if (cmp && this._simBase) this._renderComparativa(this._simBase, res);
        else { this._simBase = res; document.getElementById('simComparativaWrap').style.display = 'none'; }
        this._renderSimResultados(res); this.show('simResultados');
    },
    _expR(lam) { return lam > 0 ? -Math.log(1 - Math.random()) / lam : 60; },


    _runDES(p, lambdas, mix) {
        const RNG = { manana: [6, 14], tarde: [14, 22], completo: [6, 22] };
        const [hI, hF] = RNG[p.turno] || [6, 22], dur = (hF - hI) * 60;
        const E = { Batido: { maq: p.Batido.maq, svc: p.Batido.lic + p.Batido.ser, lim: p.Batido.lim }, Waffle: { maq: p.Waffle.maq, svc: p.Waffle.mez + p.Waffle.coc + p.Waffle.emp, lim: p.Waffle.lim }, Bowl: { maq: p.Bowl.maq, svc: p.Bowl.lic + p.Bowl.ser, lim: p.Bowl.lim } };
        const M = { Batido: { busy: 0, wq: [], lq: [], wip: 0, tp: 0 }, Waffle: { busy: 0, wq: [], lq: [], wip: 0, tp: 0 }, Bowl: { busy: 0, wq: [], lq: [], wip: 0, tp: 0 } };
        const TH = {}; for (let h = hI; h < hF; h++)TH[h] = 0;
        const MF = { Batido: [], Waffle: [], Bowl: [] };
        ['Batido', 'Waffle', 'Bowl'].forEach(e => { for (let i = 0; i < E[e].maq; i++)MF[e].push(0); });
        const cola = { Batido: [], Waffle: [], Bowl: [] }, res = [];
        const gLam = h => { const r = lambdas.find(l => +l.hora === h); return r ? +r.lambda : 0; };
        const gMix = h => { const r = mix.find(m => +m.hora === h) || { pct_Batido: 50, pct_Waffle: 30, pct_Bowl: 20 }; return [+r.pct_Batido, +r.pct_Waffle, +r.pct_Bowl]; };
        const proc = (est, now) => {
            while (cola[est].length > 0) {
                const mi = MF[est].findIndex(f => f <= now); if (mi < 0) break;
                const ped = cola[est].shift(), v = 0.9 + 0.2 * Math.random(), svc = E[est].svc * v;
                const fin = Math.max(now, MF[est][mi]) + svc; MF[est][mi] = fin + E[est].lim;
                const wq = Math.max(0, now - ped.tL); M[est].busy += svc; M[est].wq.push(wq); M[est].tp++;
                const hF2 = hI + Math.floor(fin / 60); if (TH[hF2] !== undefined) TH[hF2]++; res.push({ est, wq });
            }
        };
        const evs = []; let tA = 0, pid = 0;
        while (tA < dur) {
            const h = hI + Math.floor(tA / 60), lam = gLam(h) / 60; tA += this._expR(lam || 0.1); if (tA >= dur) break;
            const [pB, pW] = gMix(h), r = Math.random() * 100, est = r < pB ? 'Batido' : r < pB + pW ? 'Waffle' : 'Bowl';
            evs.push({ t: tA, tipo: 'arr', est, tL: tA, pid: pid++ });
        }
        const li = dur / 7; for (let i = 1; i <= 6; i++) ['Batido', 'Waffle', 'Bowl'].forEach(e => evs.push({ t: i * li, tipo: 'limp', est: e, dur: 15 }));
        evs.sort((a, b) => a.t - b.t);
        evs.forEach(ev => {
            if (ev.tipo === 'arr') { cola[ev.est].push(ev); M[ev.est].lq.push(cola[ev.est].length); M[ev.est].wip = Math.max(M[ev.est].wip, cola[ev.est].length); proc(ev.est, ev.t); }
            else MF[ev.est] = MF[ev.est].map(f => Math.max(f, ev.t) + ev.dur);
        });
        const result = {};
        ['Batido', 'Waffle', 'Bowl'].forEach(est => {
            const m = M[est], u = Math.min(100, dur > 0 ? m.busy / (dur * E[est].maq) * 100 : 0);
            const lq = m.lq.length ? m.lq.reduce((a, b) => a + b, 0) / m.lq.length : 0;
            const wq = m.wq.length ? m.wq.reduce((a, b) => a + b, 0) / m.wq.length : 0;
            result[est] = { util: Math.round(u * 10) / 10, lq: Math.round(lq * 100) / 100, wq: Math.round(wq * 100) / 100, throughput: m.tp, wip: m.wip };
        });
        return { result, thHora: TH, totalPedidos: res.length, fueraMeta: res.filter(r => r.wq > 5).length, params: p };
    },


    _renderSimResultados(r) {
        const { result, thHora, totalPedidos, fueraMeta } = r;
        const GC = ['Batido', 'Waffle', 'Bowl'];
        const gc = u => u < 60 ? 'var(--ops-green)' : u < 85 ? 'var(--ops-gold)' : 'var(--ops-red)';
        const maxU = Math.max(...Object.values(result).map(e => e.util));
        document.getElementById('simGaugesWrap').innerHTML = GC.map(est => {
            const e = result[est], iC = (e.util === maxU && e.util > 0), col = gc(e.util);
            const r2 = 54, cx = 70, cy = 70, ci = 2 * Math.PI * r2, da = ci * e.util / 100;
            return '<div class="ops-gauge-card' + (iC ? ' cuello' : '') + '">'
                + (iC ? '<div class="ops-cuello-badge">&#128308; CUELLO DE BOTELLA</div>' : '')
                + '<svg width="140" height="140"><circle cx="' + cx + '" cy="' + cy + '" r="' + r2 + '" fill="none" stroke="#ddd" stroke-width="10"/>'
                + '<circle cx="' + cx + '" cy="' + cy + '" r="' + r2 + '" fill="none" stroke="' + col + '" stroke-width="10"'
                + ' stroke-dasharray="' + da.toFixed(1) + ' ' + ci.toFixed(1) + '" stroke-dashoffset="' + (ci / 4).toFixed(1) + '"'
                + ' stroke-linecap="round" style="transition:stroke-dasharray .8s"/>'
                + '<text x="' + cx + '" y="' + (cy - 4) + '" text-anchor="middle" font-size="20" font-weight="800" fill="' + col + '">' + e.util + '%</text>'
                + '<text x="' + cx + '" y="' + (cy + 14) + '" text-anchor="middle" font-size="10" fill="#78909c">Utilizacion</text></svg>'
                + '<div class="ops-gauge-label">' + est + '</div>'
                + '<div class="ops-gauge-sub">Cola: ' + e.lq + ' | Wq: ' + e.wq + ' min | TP: ' + e.throughput + '</div></div>';
        }).join('');
        const hs = Object.keys(thHora).map(Number).sort((a, b) => a - b);
        document.getElementById('badgeSimThroughput').textContent = totalPedidos + ' pedidos simulados';
        OPS.destroyChart('chartSimThroughput');
        OPS.charts.chartSimThroughput = new Chart(document.getElementById('chartSimThroughput'), {
            type: 'bar', data: { labels: hs.map(h => h + ':00'), datasets: [{ label: 'Pedidos/hora', data: hs.map(h => thHora[h]), backgroundColor: 'rgba(81,184,172,.7)', borderRadius: 6 }] },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, title: { display: true, text: 'Pedidos completados' } } } }
        });
        document.getElementById('simGanttWrap').innerHTML = this._renderGantt(r);
        document.getElementById('tbodySimMetricas').innerHTML = GC.map(est => {
            const e = result[est];
            return '<tr><td><span class="badge-estacion badge-' + est.toLowerCase() + '">' + est + '</span></td>'
                + '<td><strong style="color:' + gc(e.util) + '">' + e.util + '%</strong></td>'
                + '<td>' + e.lq + '</td><td>' + e.wq + '</td><td>' + e.wip + '</td><td>' + e.throughput + '</td></tr>';
        }).join('');
        const pF = totalPedidos > 0 ? Math.round(fueraMeta / totalPedidos * 100) : 0;
        let rc = '';
        if (result.Waffle.util > 85) rc += '<div class="ops-alert ops-alert-warn">&#9888;&#65039; Anadir wafflera reduciria cola de Waffles en ~' + (result.Waffle.wq * 0.4).toFixed(1) + ' min</div>';
        if (result.Batido.util > 85) rc += '<div class="ops-alert ops-alert-warn">&#9888;&#65039; Anadir licuadora libera ~' + Math.round(result.Batido.throughput * 0.2) + ' pedidos/hora</div>';
        if (pF > 20) rc += '<div class="ops-alert ops-alert-danger">&#128308; Sistema no cumple meta 5 min: ' + pF + '% fuera de meta</div>';
        if (!rc) rc = '<div class="ops-alert ops-alert-ok">&#10003; Sistema operando dentro de parametros aceptables</div>';
        document.getElementById('simRecomBody').innerHTML = rc;
    },
    _renderGantt(r) {
        const hs = Object.keys(r.thHora).map(Number).sort((a, b) => a - b);
        const mx = Math.max(...Object.values(r.thHora), 1);
        const eqs = [
            ...Array.from({ length: r.params.Batido.maq }, (_, i) => ({ lb: 'Licuadora ' + (i + 1), est: 'Batido', col: 'var(--ops-blue)' })),
            ...Array.from({ length: r.params.Waffle.maq }, (_, i) => ({ lb: 'Wafflera ' + (i + 1), est: 'Waffle', col: 'var(--ops-gold)' })),
            ...Array.from({ length: r.params.Bowl.maq }, (_, i) => ({ lb: 'Motor ' + (i + 1), est: 'Bowl', col: 'var(--ops-purple)' }))
        ];
        let h = '<div class="ops-gantt"><div class="ops-gantt-header"><div class="ops-gantt-row-label"></div>'
            + hs.map(x => '<div class="ops-gantt-cell-h">' + x + '</div>').join('') + '</div>';
        eqs.forEach(eq => {
            h += '<div class="ops-gantt-row"><div class="ops-gantt-row-label">' + eq.lb + '</div>';
            hs.forEach(hr => {
                const p = (r.thHora[hr] || 0) / mx;
                const bg = p > .7 ? eq.col : p > .3 ? eq.col + '99' : '#e0e0e0';
                h += '<div class="ops-gantt-cell" style="background:' + bg + '" title="' + hr + ':00"></div>';
            });
            h += '</div>';
        });
        return h + '</div>';
    },
    _renderComparativa(base, mod) {
        const delta = (a, b, inv) => {
            const v = +(b - a).toFixed(1), s = v > 0 ? '+' : '', g = inv ? v < 0 : v > 0;
            const col = v === 0 ? 'inherit' : g ? 'var(--ops-green)' : 'var(--ops-red)';
            return '<span style="color:' + col + '">' + s + v + '</span>';
        };
        document.getElementById('tbodySimComparativa').innerHTML =
            '<thead><tr><th>Metrica</th><th>Base</th><th>Modificado</th><th>Delta</th></tr></thead><tbody>'
            + ['Batido', 'Waffle', 'Bowl'].map(e =>
                '<tr><td colspan="4" style="font-weight:800;padding:10px 14px">' + e + '</td></tr>'
                + '<tr><td>Utilizacion %</td><td>' + base.result[e].util + '%</td><td>' + mod.result[e].util + '%</td><td>' + delta(base.result[e].util, mod.result[e].util, true) + '</td></tr>'
                + '<tr><td>Wq prom (min)</td><td>' + base.result[e].wq + '</td><td>' + mod.result[e].wq + '</td><td>' + delta(base.result[e].wq, mod.result[e].wq, true) + '</td></tr>'
                + '<tr><td>Throughput</td><td>' + base.result[e].throughput + '</td><td>' + mod.result[e].throughput + '</td><td>' + delta(base.result[e].throughput, mod.result[e].throughput, false) + '</td></tr>'
            ).join('') + '</tbody>';
        document.getElementById('simComparativaWrap').style.display = '';
    },

    // ── LEAN SIX SIGMA ───────────────────────────────────────
    _leanLoaded: false,
    async cargarLean() {
        if (this._leanLoaded) return;
        this._leanLoaded = true;
        this.show('leanLoader'); this.hide('leanContent');
        const [dLean, dCycle, dCtrl] = await Promise.all([
            this.post({ accion: 'lean_data' }),
            this.post({ accion: 'cycle_times' }),
            this.post({ accion: 'control_chart_data' }),
        ]);
        this.hide('leanLoader');
        if (!dLean.success || !dCycle.success) { this.toast('Error cargando datos Lean', 'error'); return; }
        this._renderOEE();
        this._renderTaktTime(dCycle.cycle_times, dLean);
        this._renderDPMO(dLean);
        this._renderMuda(dLean, dCycle.cycle_times);
        if (dCtrl.success) this._renderControlChart(dCtrl.control_chart);
        this.show('leanContent');
    },
    _renderOEE() {
        const turno = 480, setup = 30, limpiezas = 90, disp = turno - setup - limpiezas, oee = Math.round(disp / turno * 100);
        const r2 = 70, cx = 90, cy = 90, ci = 2 * Math.PI * r2, da = ci * oee / 100;
        const col = oee >= 75 ? 'var(--ops-green)' : oee >= 60 ? 'var(--ops-gold)' : 'var(--ops-red)';
        document.getElementById('oeeGaugeWrap').innerHTML =
            '<svg width="180" height="180"><circle cx="' + cx + '" cy="' + cy + '" r="' + r2 + '" fill="none" stroke="#ddd" stroke-width="12"/>'
            + '<circle cx="' + cx + '" cy="' + cy + '" r="' + r2 + '" fill="none" stroke="' + col + '" stroke-width="12"'
            + ' stroke-dasharray="' + da.toFixed(1) + ' ' + ci.toFixed(1) + '" stroke-dashoffset="' + (ci / 4).toFixed(1) + '"'
            + ' stroke-linecap="round" style="transition:stroke-dasharray 1s"/>'
            + '<text x="' + cx + '" y="' + (cy - 6) + '" text-anchor="middle" font-size="28" font-weight="900" fill="' + col + '">' + oee + '%</text>'
            + '<text x="' + cx + '" y="' + (cy + 14) + '" text-anchor="middle" font-size="11" fill="#78909c">Disponibilidad</text></svg>';
        document.getElementById('oeeDesglose').innerHTML =
            '<div style="display:flex;flex-direction:column;gap:12px">'
            + '<div class="ops-oee-row"><span>Turno total</span><strong>480 min</strong></div>'
            + '<div class="ops-oee-row warn"><span>- Setup apertura</span><strong>-30 min</strong></div>'
            + '<div class="ops-oee-row warn"><span>- Limpiezas (6x15 min)</span><strong>-90 min</strong></div>'
            + '<div class="ops-oee-row ok"><span>Tiempo disponible neto</span><strong>360 min</strong></div>'
            + '<div class="ops-oee-row ok"><span>OEE Disponibilidad</span><strong>' + oee + '%</strong></div>'
            + '</div>';
        OPS.destroyChart('chartOeeDesglose');
        OPS.charts.chartOeeDesglose = new Chart(document.getElementById('chartOeeDesglose'), {
            type: 'doughnut',
            data: { labels: ['Disponible', 'Setup', 'Limpiezas'], datasets: [{ data: [360, 30, 90], backgroundColor: ['var(--ops-green)', 'var(--ops-gold)', 'var(--ops-red)'], borderColor: '#f6f6f6', borderWidth: 6, hoverOffset: 10 }] },
            options: { plugins: { legend: { position: 'bottom' } }, cutout: '70%', responsive: true, maintainAspectRatio: false }
        });
    },
    _renderTaktTime(cycleTimes, dLean) {
        const disp = 360;
        const pd = dLean.pedidos_dia || 1;
        const takt = disp / pd;
        document.getElementById('badgeTaktTime').textContent = takt.toFixed(1) + ' min/pedido';
        const labels = cycleTimes.map(c => c.estacion);
        const ctVals = cycleTimes.map(c => +c.cycle_time_prom_min);
        const colors = labels.map(l => this.COLORES[l] || this.COLORES.Otro);
        OPS.destroyChart('chartTaktTime');
        OPS.charts.chartTaktTime = new Chart(document.getElementById('chartTaktTime'), {
            type: 'bar',
            data: {
                labels, datasets: [
                    { label: 'Cycle Time prom (min)', data: ctVals, backgroundColor: colors.map(c => c + 'aa'), borderColor: colors, borderWidth: 2, borderRadius: 5 },
                ]
            },
            options: {
                plugins: { legend: { position: 'bottom' }, annotation: { annotations: { takt: { type: 'line', yMin: takt, yMax: takt, borderColor: 'var(--ops-red)', borderWidth: 2, borderDash: [6, 4], label: { content: 'Takt Time ' + takt.toFixed(1) + ' min', enabled: true, position: 'end' } } } } },
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, title: { display: true, text: 'Minutos' } } }
            }
        });
        const alertas = cycleTimes.filter(c => +c.cycle_time_prom_min > takt).map(c => '<div class="ops-alert ops-alert-danger">Estacion <strong>' + c.estacion + '</strong>: Cycle Time (' + c.cycle_time_prom_min + ' min) > Takt Time (' + takt.toFixed(1) + ' min) — no puede seguir el ritmo de la demanda</div>').join('');
        document.getElementById('taktAlerta').innerHTML = alertas || '<div class="ops-alert ops-alert-ok">Todas las estaciones pueden seguir el ritmo del Takt Time</div>';
    },
    _renderDPMO(dLean) {
        const SIGMA_TABLE = [{ dpmo: 3.4, sigma: 6 }, { dpmo: 233, sigma: 5 }, { dpmo: 6210, sigma: 4 }, { dpmo: 66807, sigma: 3 }, { dpmo: 308537, sigma: 2 }, { dpmo: 690000, sigma: 1 }];
        const total = +dLean.total_pedidos || 1, anulados = +dLean.total_anulados || 0;
        const dpmo = Math.round(anulados / total * 1000000);
        let sigma = 1;
        for (const s of SIGMA_TABLE) { if (dpmo <= s.dpmo) { sigma = s.sigma; break; } }
        const pct = Math.min(100, Math.round(sigma / 6 * 100));
        const col = sigma >= 4 ? 'var(--ops-green)' : sigma >= 3 ? 'var(--ops-gold)' : 'var(--ops-red)';
        const r2 = 70, cx = 90, cy = 90, ci = 2 * Math.PI * r2, da = ci * pct / 100;
        document.getElementById('sigmaGaugeWrap').innerHTML =
            '<svg width="180" height="180"><circle cx="' + cx + '" cy="' + cy + '" r="' + r2 + '" fill="none" stroke="#ddd" stroke-width="12"/>'
            + '<circle cx="' + cx + '" cy="' + cy + '" r="' + r2 + '" fill="none" stroke="' + col + '" stroke-width="12"'
            + ' stroke-dasharray="' + da.toFixed(1) + ' ' + ci.toFixed(1) + '" stroke-dashoffset="' + (ci / 4).toFixed(1) + '"'
            + ' stroke-linecap="round" style="transition:stroke-dasharray 1s"/>'
            + '<text x="' + cx + '" y="' + (cy - 6) + '" text-anchor="middle" font-size="32" font-weight="900" fill="' + col + '">' + sigma + '&#963;</text>'
            + '<text x="' + cx + '" y="' + (cy + 14) + '" text-anchor="middle" font-size="11" fill="#78909c">Nivel Sigma</text></svg>';
        document.getElementById('sigmaDesglose').innerHTML =
            '<div style="display:flex;flex-direction:column;gap:10px">'
            + '<div class="ops-oee-row"><span>Total pedidos</span><strong>' + this.num(total) + '</strong></div>'
            + '<div class="ops-oee-row warn"><span>Anulados (defectos)</span><strong>' + this.num(anulados) + '</strong></div>'
            + '<div class="ops-oee-row"><span>DPMO</span><strong>' + this.num(dpmo) + '</strong></div>'
            + '<div class="ops-oee-row"><span>Nivel Sigma</span><strong>' + sigma + ' &#963;</strong></div>'
            + '<div class="ops-oee-row ' + (sigma >= 4 ? 'ok' : sigma >= 3 ? 'warn' : 'danger') + '"><span>Referencia industria alimentos</span><strong>3&#963; estandar · 4&#963; objetivo</strong></div>'
            + '</div>';
        const anulList = dLean.motivos || [];
        document.getElementById('tbodyAnulaciones').innerHTML = anulList.map(m => {
            const pct2 = total > 0 ? Math.round(+m.cantidad / total * 100 * 10) / 10 : 0;
            return '<tr><td>' + (m.motivo || 'Sin motivo') + '</td><td>' + this.num(m.cantidad) + '</td><td>' + pct2 + '%</td></tr>';
        }).join('') || '<tr><td colspan="3" class="muted">Sin anulaciones en el periodo</td></tr>';
    },
    _renderMuda(dLean, cycleTimes) {
        const total = +dLean.total_pedidos || 1, anulados = +dLean.total_anulados || 0;
        const dpmo = Math.round(anulados / total * 1000000);
        const wq = cycleTimes.length ? Math.max(...cycleTimes.map(c => +c.queue_time_prom_min)) : 0;
        const mudas = [
            { n: 'Sobreproduccion', icono: 'fa-industry', nivel: anulados / total < 0.02 ? 'verde' : anulados / total < 0.05 ? 'amarillo' : 'rojo', detalle: 'Anulados: ' + Math.round(anulados / total * 100) + '% de pedidos' },
            { n: 'Tiempo de espera', icono: 'fa-hourglass-half', nivel: wq < 2 ? 'verde' : wq < 5 ? 'amarillo' : 'rojo', detalle: 'Wq promedio: ' + wq.toFixed(1) + ' min' },
            { n: 'Transporte', icono: 'fa-truck', nivel: 'amarillo', detalle: 'Distancia refrigerador a estacion — parametro fijo' },
            { n: 'Sobreproceso', icono: 'fa-cogs', nivel: 'amarillo', detalle: 'Personalización de productos (endulzantes, extras)' },
            { n: 'Inventario', icono: 'fa-boxes', nivel: 'gris', detalle: 'No medible con datos actuales' },
            { n: 'Movimiento', icono: 'fa-walking', nivel: 'verde', detalle: 'Operarios polivalentes — movimiento optimizado por pool compartido' },
            { n: 'Defectos', icono: 'fa-bug', nivel: dpmo < 6210 ? 'verde' : dpmo < 66807 ? 'amarillo' : 'rojo', detalle: 'DPMO: ' + this.num(dpmo) },
        ];
        const colMap = { verde: 'var(--ops-green)', amarillo: 'var(--ops-gold)', rojo: 'var(--ops-red)', gris: '#b0bec5' };
        document.getElementById('mudaGrid').innerHTML = mudas.map(m =>
            '<div class="ops-muda-card">'
            + '<div class="ops-muda-icon" style="color:' + colMap[m.nivel] + '"><i class="fas ' + m.icono + '"></i></div>'
            + '<div class="ops-muda-semaforo" style="background:' + colMap[m.nivel] + '"></div>'
            + '<div class="ops-muda-title">' + m.n + '</div>'
            + '<div class="ops-muda-detalle">' + m.detalle + '</div>'
            + '</div>'
        ).join('');
    },
    _renderControlChart(data) {
        if (!data || !data.length) { document.getElementById('chartControlChart').closest('.ops-card-body').innerHTML = '<p class="muted">Sin datos suficientes para grafica de control</p>'; return; }
        const labels = data.map(d => d.fecha);
        const vals = data.map(d => +d.lead_avg);
        const media = vals.reduce((a, b) => a + b, 0) / vals.length;
        const std = Math.sqrt(vals.reduce((a, b) => a + (b - media) ** 2, 0) / vals.length);
        const ucl = media + 3 * std, lcl = Math.max(0, media - 3 * std);
        const outPts = vals.filter(v => v > ucl || v < lcl).length;
        document.getElementById('badgeControlChart').textContent = outPts + ' puntos fuera de control';
        OPS.destroyChart('chartControlChart');
        OPS.charts.chartControlChart = new Chart(document.getElementById('chartControlChart'), {
            type: 'line',
            data: {
                labels, datasets: [
                    { label: 'Lead Time prom (min)', data: vals, borderColor: 'var(--ops-teal)', backgroundColor: 'rgba(81,184,172,.1)', borderWidth: 2, pointRadius: vals.map(v => v > ucl || v < lcl ? 6 : 3), pointBackgroundColor: vals.map(v => v > ucl || v < lcl ? 'var(--ops-red)' : 'var(--ops-teal)'), tension: .3, fill: true },
                    { label: 'UCL', data: vals.map(() => ucl), borderColor: 'var(--ops-red)', borderDash: [6, 4], borderWidth: 1.5, pointRadius: 0, fill: false },
                    { label: 'Media', data: vals.map(() => media), borderColor: 'var(--ops-blue)', borderDash: [3, 3], borderWidth: 1.5, pointRadius: 0, fill: false },
                    { label: 'LCL', data: vals.map(() => lcl), borderColor: 'var(--ops-red)', borderDash: [6, 4], borderWidth: 1.5, pointRadius: 0, fill: false },
                ]
            },
            options: { plugins: { legend: { position: 'bottom' } }, responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: false, title: { display: true, text: 'Minutos' } } } }
        });
        document.getElementById('controlChartAlerta').innerHTML = outPts > 0
            ? '<div class="ops-alert ops-alert-warn">' + outPts + ' dias con lead time fuera de los limites de control (UCL=' + ucl.toFixed(1) + ' min, LCL=' + lcl.toFixed(1) + ' min) — proceso inestable</div>'
            : '<div class="ops-alert ops-alert-ok">Proceso bajo control estadistico — todos los puntos dentro de UCL/LCL</div>';
    },

};

document.addEventListener('DOMContentLoaded', () => OPS.init());
