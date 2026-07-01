/* ===================================================
   Configuración de Logística — JavaScript
   Módulo: Productos
   =================================================== */

'use strict';

// --- Estado global ---
let sucursales   = [];
let configuraciones = {};   // cache: codigoSucursal → { sucursal: {}, productos: [] }
let currentSucursal = null;

// Mapeo de categorías de insumo
const CATEGORIAS_INSUMO = [
    { codigo: 'A', nombre: 'Frescos' },
    { codigo: 'B', nombre: 'Congelados' },
    { codigo: 'C', nombre: 'Fresas' },
    { codigo: 'D', nombre: 'Desechables' },
    { codigo: 'E', nombre: 'Fijos' },
    { codigo: 'F', nombre: 'Secos y Preparación' },
    { codigo: 'G', nombre: 'Productos de Mostrador' }
];

// ====================================================
// Inicialización
// ====================================================
$(document).ready(function () {
    cargarSucursales();
});

// ====================================================
// Cargar sucursales activas
// ====================================================
function cargarSucursales() {
    $.ajax({
        url: 'ajax/configuracion_logistica_get_sucursales.php',
        method: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.success) {
                sucursales = res.sucursales;
                renderizarTabs();
                if (sucursales.length > 0) {
                    currentSucursal = sucursales[0].codigo;
                    cargarConfiguracion(currentSucursal);
                }
            } else {
                mostrarError('Error al cargar sucursales: ' + res.message);
            }
        },
        error: function () {
            mostrarError('Error de conexión al cargar sucursales.');
        }
    });
}

// ====================================================
// Renderizar tabs y contenedores vacíos
// ====================================================
function renderizarTabs() {
    const tabsHtml = sucursales.map((s, i) => `
        <li class="nav-item" role="presentation">
            <button class="nav-link ${i === 0 ? 'active' : ''}"
                    id="tab-${s.codigo}"
                    data-bs-toggle="tab"
                    data-bs-target="#content-${s.codigo}"
                    type="button"
                    role="tab"
                    onclick="cambiarSucursal('${s.codigo}')">
                ${s.nombre}
            </button>
        </li>
    `).join('');

    const contentHtml = sucursales.map((s, i) => `
        <div class="tab-pane fade ${i === 0 ? 'show active' : ''}"
             id="content-${s.codigo}"
             role="tabpanel">
            <div id="panel-${s.codigo}">
                <div class="loader-container">
                    <div class="loader"></div>
                    <span class="small text-muted">Cargando configuración...</span>
                </div>
            </div>
        </div>
    `).join('');

    $('#sucursalesTabs').html(tabsHtml);
    $('#sucursalesTabContent').html(contentHtml);
}

// ====================================================
// Cambiar sucursal activa (lazy load)
// ====================================================
function cambiarSucursal(codigo) {
    currentSucursal = codigo;
    
    // Mostrar loader inmediatamente si no hay datos en cache
    if (!configuraciones[codigo]) {
        $(`#panel-${codigo}`).html(`
            <div class="loader-container">
                <div class="loader"></div>
                <span class="small text-muted">Cargando configuración...</span>
            </div>
        `);
        cargarConfiguracion(codigo);
    } else {
        // Si hay cache, renderizar de inmediato
        renderizarContenido(codigo);
    }
}

// ====================================================
// Cargar configuración de una sucursal desde el server
// ====================================================
function cargarConfiguracion(codigo) {
    $.ajax({
        url: 'ajax/configuracion_logistica_get_config.php',
        method: 'POST',
        data: { codigo_sucursal: codigo },
        dataType: 'json',
        success: function (res) {
            if (res.success) {
                configuraciones[codigo] = res.data;
                renderizarContenido(codigo);
            } else {
                mostrarError('Error al cargar configuración: ' + res.message);
            }
        },
        error: function () {
            mostrarError('Error de conexión al cargar configuración.');
        }
    });
}

// ====================================================
// Renderizar el contenido completo de un tab
// ====================================================
function renderizarContenido(codigo) {
    const data        = configuraciones[codigo] || {};
    const dataSuc     = data.sucursal  || {};
    const dataProds   = data.productos || {};   // { 'A': {...}, 'B': {...}, ... }

    const roAttr = puedeEditar ? '' : 'disabled';

    // --------------------------
    // Card de Encabezado
    // --------------------------
    const metaSucHTML = dataSuc.fecha_actualizacion
        ? `<span class="meta-encabezado">
               Última actualización: <strong>${dataSuc.fecha_actualizacion}</strong>
           </span>`
        : '';

    const onFocusAttr = 'onfocus="this.dataset.initial = this.value"';

    const cardEncabezado = `
        <div class="card-encabezado">
            <div class="card-header-custom">
                <i class="bi bi-building-gear"></i>
                Parámetros Globales de Sucursal
                ${!puedeEditar ? '<span class="ms-2 badge bg-secondary fw-normal" style="font-size:11px;"><i class="bi bi-eye me-1"></i>Solo lectura</span>' : ''}
            </div>
            <div class="card-body">
                <div class="encabezado-campos">
                    <div class="campo-encabezado">
                        <label for="capacidad_congelados_${codigo}">
                            <i class="bi bi-snow me-1"></i>Capacidad Congelados
                        </label>
                        <input type="number" min="0" step="0.01"
                               id="capacidad_congelados_${codigo}"
                               class="input-encabezado"
                               value="${dataSuc.capacidad_congelados ?? ''}"
                               placeholder="Ej: 150.00"
                               ${roAttr}
                               ${onFocusAttr}
                               onblur="guardarSucursal('${codigo}', 'capacidad_congelados', this.value, this)">
                    </div>
                </div>
                <div class="mt-2">${metaSucHTML}</div>
            </div>
        </div>
    `;

    // --------------------------
    // Tabla de Categorías
    // --------------------------
    let filas = '';
    CATEGORIAS_INSUMO.forEach(cat => {
        const fila = dataProds[cat.codigo] || {};
        const metaFila = fila.fecha_actualizacion
            ? `<small class="text-muted" title="Modificado por ${fila.modificado_por_nombre || '—'} el ${fila.fecha_actualizacion}">
                   <i class="bi bi-pencil-fill" style="font-size:10px;"></i>
               </small>`
            : '';

        filas += `
            <tr data-sucursal="${codigo}" data-codigo-insumo="${cat.codigo}">
                <td>
                    <div class="celda-categoria">
                        <span class="badge-categoria badge-${cat.codigo}">${cat.codigo}</span>
                        <span class="nombre-categoria">${cat.nombre}</span>
                        ${metaFila}
                    </div>
                </td>
                <td>
                    <input type="number" min="0" step="0.01"
                           class="input-config input-ciclo"
                           value="${fila.dias_ciclo ?? ''}"
                           placeholder="—"
                           ${roAttr}
                           ${onFocusAttr}
                           onblur="guardarProducto('${codigo}', '${cat.codigo}', 'dias_ciclo', this.value, this)">
                </td>
                <td>
                    <input type="number" min="0" step="0.01"
                           class="input-config input-desfase"
                           value="${fila.dias_desfase ?? ''}"
                           placeholder="—"
                           ${roAttr}
                           ${onFocusAttr}
                           onblur="guardarProducto('${codigo}', '${cat.codigo}', 'dias_desfase', this.value, this)">
                </td>
                <td>
                    <input type="number" min="0" step="0.01"
                           class="input-config input-abastec"
                           value="${fila.dias_abastecimiento_despacho ?? ''}"
                           placeholder="—"
                           ${roAttr}
                           ${onFocusAttr}
                           onblur="guardarProducto('${codigo}', '${cat.codigo}', 'dias_abastecimiento_despacho', this.value, this)">
                </td>
                <td>
                    <input type="number" step="0.0001"
                           class="input-config input-ajuste"
                           value="${fila.ajuste_demanda ?? ''}"
                           placeholder="Ej: 1.05"
                           ${roAttr}
                           ${onFocusAttr}
                           onblur="guardarProducto('${codigo}', '${cat.codigo}', 'ajuste_demanda', this.value, this)">
                </td>
            </tr>
        `;
    });

    const cardTabla = `
        <div class="card-tabla">
            <div class="card-header-custom">
                <i class="bi bi-table"></i>
                Parámetros por Categoría de Insumo
            </div>
            <div class="table-responsive">
                <table class="config-table">
                    <thead>
                        <tr>
                            <th>Categoría</th>
                            <th title="Días del ciclo de abastecimiento">Días Ciclo</th>
                            <th title="Días de desfase entre pedido y recepción">Días Desfase</th>
                            <th title="Días de abastecimiento hasta despacho">Días Abast. Despacho</th>
                            <th title="Factor de ajuste de demanda (ej: 1.05 = +5%)">Ajuste Demanda</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${filas}
                    </tbody>
                </table>
            </div>
        </div>
    `;

    $(`#panel-${codigo}`).html(cardEncabezado + cardTabla);
}

// ====================================================
// Guardar campo de encabezado (sucursal)
// ====================================================
function guardarSucursal(codigoSucursal, campo, valor, inputEl) {
    if (!puedeEditar) return;
    
    // Evitar guardado si el valor no ha cambiado
    if (valor === (inputEl.dataset.initial ?? '')) return;
    
    const $input = $(inputEl);
    $input.addClass('guardando');

    $.ajax({
        url: 'ajax/configuracion_logistica_save_sucursal.php',
        method: 'POST',
        data: {
            codigo_sucursal: codigoSucursal,
            campo: campo,
            valor: valor
        },
        dataType: 'json',
        success: function (res) {
            $input.removeClass('guardando');
            if (res.success) {
                // Actualizar cache local
                if (!configuraciones[codigoSucursal]) configuraciones[codigoSucursal] = { sucursal: {}, productos: {} };
                configuraciones[codigoSucursal].sucursal[campo] = valor;
                if (res.meta) {
                    Object.assign(configuraciones[codigoSucursal].sucursal, res.meta);
                }
                mostrarExito('Guardado correctamente');
            } else {
                mostrarError('Error al guardar: ' + res.message);
            }
        },
        error: function () {
            $input.removeClass('guardando');
            mostrarError('Error de conexión al guardar.');
        }
    });
}

// ====================================================
// Guardar campo de tabla por categoría
// ====================================================
function guardarProducto(codigoSucursal, codigoInsumo, campo, valor, inputEl) {
    if (!puedeEditar) return;
    
    // Evitar guardado si el valor no ha cambiado
    if (valor === (inputEl.dataset.initial ?? '')) return;

    const $input = $(inputEl);
    $input.addClass('guardando');

    $.ajax({
        url: 'ajax/configuracion_logistica_save_producto.php',
        method: 'POST',
        data: {
            codigo_sucursal: codigoSucursal,
            codigo_insumo:   codigoInsumo,
            campo:           campo,
            valor:           valor
        },
        dataType: 'json',
        success: function (res) {
            $input.removeClass('guardando');
            if (res.success) {
                // Actualizar cache local
                if (!configuraciones[codigoSucursal]) configuraciones[codigoSucursal] = { sucursal: {}, productos: {} };
                if (!configuraciones[codigoSucursal].productos[codigoInsumo])
                    configuraciones[codigoSucursal].productos[codigoInsumo] = {};
                configuraciones[codigoSucursal].productos[codigoInsumo][campo] = valor;
                if (res.meta) {
                    Object.assign(configuraciones[codigoSucursal].productos[codigoInsumo], res.meta);
                }
                // Actualizar indicador de meta en la fila
                actualizarMetaFila(codigoSucursal, codigoInsumo, res.meta);
                mostrarExito('Guardado correctamente');
            } else {
                mostrarError('Error al guardar: ' + res.message);
            }
        },
        error: function () {
            $input.removeClass('guardando');
            mostrarError('Error de conexión al guardar.');
        }
    });
}

// ====================================================
// Actualizar indicador de edición en la fila
// ====================================================
function actualizarMetaFila(codigoSucursal, codigoInsumo, meta) {
    if (!meta) return;
    const $fila = $(`tr[data-sucursal="${codigoSucursal}"][data-codigo-insumo="${codigoInsumo}"]`);
    if (!$fila.length) return;

    const tooltip = meta.fecha_actualizacion
        ? `Modificado por ${meta.modificado_por_nombre || '—'} el ${meta.fecha_actualizacion}`
        : '';

    const $metaEl = $fila.find('.celda-categoria small');
    if ($metaEl.length) {
        $metaEl.attr('title', tooltip);
    } else {
        $fila.find('.celda-categoria').append(
            `<small class="text-muted" title="${tooltip}">
                 <i class="bi bi-pencil-fill" style="font-size:10px;"></i>
             </small>`
        );
    }
}

// ====================================================
// Toasts de feedback
// ====================================================
function mostrarExito(mensaje) {
    Swal.fire({
        icon: 'success',
        title: 'Éxito',
        text: mensaje,
        timer: 1800,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}

function mostrarError(mensaje) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: mensaje,
        confirmButtonColor: '#51B8AC'
    });
}
