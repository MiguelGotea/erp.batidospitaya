/**
 * kpi_reportes_ventas.js
 */

document.addEventListener('DOMContentLoaded', function () {
    loadKPIData();
});

let kpiData = null;
let currentStartMonth = 1; // Default to show from month 1 (will be adjusted to current month)

async function loadKPIData() {
    try {
        const response = await fetch('ajax/get_kpi_ventas_mensuales.php');
        const result = await response.json();

        if (result.success) {
            kpiData = result.data;
            // Set default view to current month (show current and 2 previous if possible)
            const currentMes = kpiData.actual.mes;
            currentStartMonth = Math.max(1, currentMes - 2);
            renderKPITable();
        } else {
            console.error('Error loading KPI data:', result.message);
        }
    } catch (error) {
        console.error('Fetch error:', error);
    }
}

function renderKPITable() {
    if (!kpiData) return;

    const container = document.getElementById('kpiTableContainer');
    if (!container) return;

    const sucursales = kpiData.sucursales;
    const meses = kpiData.meses;

    // Group sucursales
    const mtapBranches = sucursales.filter(s => s.VMTAP == 1);
    const otherBranches = sucursales.filter(s => s.VMTAP == 0);

    // Visible months (3 at a time)
    const visibleMonths = [];
    for (let i = 0; i < 3; i++) {
        let m = currentStartMonth + i;
        if (m <= 12) visibleMonths.push(m);
    }

    let html = `
        <div class="kpi-ventas-section">
            <div class="kpi-header">
                <h4><i class="fas fa-chart-line text-primary"></i> KPI de Ventas por Sucursal</h4>
                <div class="kpi-nav">
                    <button class="btn-kpi-nav" onclick="moveMonth(-1)" ${currentStartMonth === 1 ? 'disabled' : ''}>
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="btn-kpi-nav" onclick="moveMonth(1)" ${currentStartMonth + 2 >= 12 ? 'disabled' : ''}>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            <div class="kpi-table-wrapper">
                <table class="kpi-table" id="tablaKpiVentas">
                    <colgroup>
                        <col style="width: 160px;">
                        ${visibleMonths.map(() => `
                            <col style="width: 65px;">
                            <col style="width: 65px;">
                            <col style="width: 65px;">
                            <col style="width: 65px;">
                        `).join('')}
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="tienda-col" rowspan="2">Tienda</th>
                            ${visibleMonths.map(m => `
                                <th colspan="4" class="month-header">${meses[m].nombre}-${kpiData.actual.año.toString().slice(-2)}</th>
                            `).join('')}
                        </tr>
                        <tr>
                            ${visibleMonths.map(m => `
                                <th class="sub-header">Meta</th>
                                <th class="sub-header">Real</th>
                                <th class="sub-header">V. Cal</th>
                                <th class="sub-header">%Var.</th>
                            `).join('')}
                        </tr>
                    </thead>
                    <tbody>
                        ${renderBranchGroup(mtapBranches, visibleMonths, 'SubTotal MTAP')}
                        ${renderBranchGroup(otherBranches, visibleMonths, 'SubTotal Nuevas Tiendas')}
                        ${renderTotalRow(sucursales, visibleMonths)}
                    </tbody>
                </table>
            </div>
        </div>
    `;

    container.innerHTML = html;
}

function renderBranchGroup(branches, visibleMonths, label) {
    let rowsHtml = branches.map(s => {
        return `
            <tr>
                <td class="tienda-col">${s.nombre}</td>
                ${visibleMonths.map(m => {
            const val = kpiData.meses[m].valores[s.codigo] || { meta: 0, real: 0, calendario: 0, var: 0 };
            return `
                        <td>${formatNumber(val.meta)}</td>
                        <td>${val.real > 0 ? formatNumber(val.real) : '-'}</td>
                        <td class="cal-cell">${val.calendario > 0 ? formatNumber(val.calendario) : '-'}</td>
                        <td class="var-cell">${renderVariation(val.var, val.real)}</td>
                    `;
        }).join('')}
            </tr>
        `;
    }).join('');

    // Group Subtotal
    rowsHtml += `
        <tr class="group-row">
            <td class="tienda-col">${label}</td>
            ${visibleMonths.map(m => {
        let subMeta = 0, subReal = 0, subCal = 0;
        branches.forEach(s => {
            const val = kpiData.meses[m].valores[s.codigo] || { meta: 0, real: 0, calendario: 0 };
            subMeta += val.meta;
            subReal += val.real;
            subCal += val.calendario;
        });
        let subVar = subMeta > 0 ? ((subReal - subMeta) / subMeta) * 100 : 0;
        return `
                    <td>${formatNumber(subMeta)}</td>
                    <td>${subReal > 0 ? formatNumber(subReal) : '-'}</td>
                    <td class="cal-cell">${subCal > 0 ? formatNumber(subCal) : '-'}</td>
                    <td class="var-cell">${renderVariation(subVar, subReal)}</td>
                `;
    }).join('')}
        </tr>
    `;

    return rowsHtml;
}

function renderTotalRow(branches, visibleMonths) {
    return `
        <tr class="total-row">
            <td class="tienda-col">Total</td>
            ${visibleMonths.map(m => {
        let totMeta = 0, totReal = 0, totCal = 0;
        branches.forEach(s => {
            const val = kpiData.meses[m].valores[s.codigo] || { meta: 0, real: 0, calendario: 0 };
            totMeta += val.meta;
            totReal += val.real;
            totCal += val.calendario;
        });
        let totVar = totMeta > 0 ? ((totReal - totMeta) / totMeta) * 100 : 0;
        return `
                    <td>${formatNumber(totMeta)}</td>
                    <td>${totReal > 0 ? formatNumber(totReal) : '-'}</td>
                    <td class="cal-cell">${totCal > 0 ? formatNumber(totCal) : '-'}</td>
                    <td class="var-cell">${renderVariation(totVar, totReal)}</td>
                `;
    }).join('')}
        </tr>
    `;
}

function moveMonth(delta) {
    currentStartMonth = Math.max(1, Math.min(10, currentStartMonth + delta));
    renderKPITable();
}

function formatNumber(num) {
    return num.toLocaleString('en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
}

function renderVariation(val, real) {
    if (real === 0) return '-';
    let dotClass = 'dot-green';
    if (val < -5) dotClass = 'dot-red';
    else if (val < 0) dotClass = 'dot-yellow';

    return `<span class="indicator-dot ${dotClass}"></span> ${val.toFixed(1)}%`;
}
