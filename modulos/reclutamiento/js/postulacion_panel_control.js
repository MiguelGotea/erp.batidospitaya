// postulacion_panel_control.js

document.addEventListener('DOMContentLoaded', function () {
    cargarDatosSucursales();
    cargarDatosAdministrativo();
    cargarDatosProduccion();

    // Inicializar tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// ========================================
// CARGAR DATOS SUCURSALES
// ========================================
async function cargarDatosSucursales() {
    try {
        const response = await fetch('ajax/postulacion_panel_control_get_sucursales.php');
        const data = await response.json();

        if (data.success) {
            renderizarTablaSucursales(data.datos);

            // Renderizar PDFs globales en el contenedor de tarjetas
            const vHeader = document.getElementById('globalVendedoresBtns');
            const lHeader = document.getElementById('globalLideresBtns');
            if (data.global_pdf) {
                if (vHeader) {
                    vHeader.innerHTML = generarColumnaPDF(data.global_pdf.vendedor.ruta, data.global_pdf.vendedor.id, 2, 0, 'Sucursales');
                }
                if (lHeader) {
                    lHeader.innerHTML = generarColumnaPDF(data.global_pdf.lider.ruta, data.global_pdf.lider.id, 5, 0, 'Sucursales');
                }
            }
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error al cargar sucursales:', error);
        Swal.fire('Error', 'No se pudieron cargar los datos de sucursales', 'error');
    }
}



function renderizarTablaSucursales(datos) {
    const tbody = document.getElementById('tablaSucursalesBody');
    tbody.innerHTML = '';

    datos.forEach(sucursal => {
        // Fila de Encabezado de Sucursal
        const headerRow = document.createElement('tr');
        headerRow.innerHTML = `
            <th colspan="6" class="bg-light text-primary text-start ps-3">
                <i class="bi bi-shop me-2"></i>${sucursal.nombre_sucursal}
            </th>
        `;
        tbody.appendChild(headerRow);

        // Fila Vendedores
        const vRow = document.createElement('tr');
        vRow.innerHTML = `
            <td class="text-start ps-4 fw-bold">
                Vendedores
                <input type="hidden" value="${sucursal.vendedor_salario || 0}" 
                       data-sucursal="${sucursal.codigo_sucursal}" data-tipo="vendedor_salario">
            </td>
            <td class="cell-editable">
                <input type="number" class="form-control form-control-sm text-center editable-input" 
                       value="${sucursal.vendedor_oblig || 0}" min="0"
                       data-sucursal="${sucursal.codigo_sucursal}" data-tipo="vendedor_oblig"
                       ${!puedeEditar ? 'disabled' : ''} onchange="marcarCambio(this)">
            </td>
            <td class="cell-editable">
                <input type="number" class="form-control form-control-sm text-center editable-input" 
                       value="${sucursal.vendedor_adic || 0}" min="0"
                       data-sucursal="${sucursal.codigo_sucursal}" data-tipo="vendedor_adic"
                       ${!puedeEditar ? 'disabled' : ''} onchange="marcarCambio(this)">
            </td>
            <td class="text-center">
                <span class="fw-bold text-dark">${sucursal.vendedor_cubierto || 0}</span>
            </td>
            <td>
                <div class="d-flex align-items-center justify-content-center gap-2">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" 
                               ${sucursal.vendedor_web == 1 ? 'checked' : ''}
                               data-sucursal="${sucursal.codigo_sucursal}" data-tipo="vendedor_web"
                               ${!puedeEditar ? 'disabled' : ''} onchange="toggleWebLink(this)">
                    </div>
                    ${sucursal.vendedor_id > 0 ? `
                    <a href="https://talento.batidospitaya.com/postular.php?plaza=${sucursal.vendedor_id}&cargo=2&sucursal=${sucursal.codigo_sucursal}" 
                       target="_blank" 
                       class="btn btn-sm btn-outline-primary py-0 px-1 link-postulacion ${sucursal.vendedor_web == 1 ? '' : 'd-none'}" 
                       title="Link de postulación">
                        <i class="bi bi-link-45deg"></i>
                    </a>
                    ` : ''}
                </div>
            </td>
            <td class="d-none">
                ${generarSelectUrgencia(4, sucursal.codigo_sucursal, 'vendedor_urgencia', 'Sucursales', true)}
            </td>
            <td class="text-center">
                ${generarColumnaBanner(sucursal.vendedor_banner, sucursal.vendedor_id, 2, sucursal.codigo_sucursal, 'Sucursales')}
            </td>
        `;
        tbody.appendChild(vRow);

        // Fila Líderes
        const lRow = document.createElement('tr');
        lRow.innerHTML = `
            <td class="text-start ps-4 fw-bold">
                Líderes
                <input type="hidden" value="${sucursal.lider_salario || 0}" 
                       data-sucursal="${sucursal.codigo_sucursal}" data-tipo="lider_salario">
            </td>
            <td class="cell-editable">
                <input type="number" class="form-control form-control-sm text-center editable-input" 
                       value="${sucursal.lider_oblig || 1}" min="0"
                       data-sucursal="${sucursal.codigo_sucursal}" data-tipo="lider_oblig"
                       disabled>
            </td>
            <td class="cell-editable">
                <input type="number" class="form-control form-control-sm text-center editable-input" 
                       value="${sucursal.lider_adic || 0}" min="0"
                       data-sucursal="${sucursal.codigo_sucursal}" data-tipo="lider_adic"
                       ${!puedeEditar ? 'disabled' : ''} onchange="marcarCambio(this)">
            </td>
            <td class="text-center">
                <span class="fw-bold text-dark">${sucursal.lider_cubierto || 0}</span>
            </td>
            <td>
                <div class="d-flex align-items-center justify-content-center gap-2">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" 
                               ${sucursal.lider_web == 1 ? 'checked' : ''}
                               data-sucursal="${sucursal.codigo_sucursal}" data-tipo="lider_web"
                               ${!puedeEditar ? 'disabled' : ''} onchange="toggleWebLink(this)">
                    </div>
                    ${sucursal.lider_id > 0 ? `
                    <a href="https://talento.batidospitaya.com/postular.php?plaza=${sucursal.lider_id}&cargo=5&sucursal=${sucursal.codigo_sucursal}" 
                       target="_blank" 
                       class="btn btn-sm btn-outline-primary py-0 px-1 link-postulacion ${sucursal.lider_web == 1 ? '' : 'd-none'}" 
                       title="Link de postulación">
                        <i class="bi bi-link-45deg"></i>
                    </a>
                    ` : ''}
                </div>
            </td>
            <td class="d-none">
                ${generarSelectUrgencia(4, sucursal.codigo_sucursal, 'lider_urgencia', 'Sucursales', true)}
            </td>
            <td class="text-center">
                ${generarColumnaBanner(sucursal.lider_banner, sucursal.lider_id, 5, sucursal.codigo_sucursal, 'Sucursales')}
            </td>
        `;
        tbody.appendChild(lRow);
    });
}

// ========================================
// CARGAR DATOS ADMINISTRATIVO
// ========================================
async function cargarDatosAdministrativo() {
    try {
        const response = await fetch('ajax/postulacion_panel_control_get_administrativo.php');
        const data = await response.json();

        if (data.success) {
            renderizarTablaAdministrativo(data.datos);
            const activos = data.datos.filter(c => parseInt(c.operativo) !== 0);
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error al cargar administrativos:', error);
        Swal.fire('Error', 'No se pudieron cargar los datos administrativos', 'error');
    }
}

function renderizarTablaAdministrativo(datos) {
    const tbody = document.getElementById('tablaAdministrativoBody');
    tbody.innerHTML = '';

    const activos   = datos.filter(c => parseInt(c.operativo) !== 0);
    const inactivos = datos.filter(c => parseInt(c.operativo) === 0);

    let areaActual = '';
    activos.forEach(cargo => {
        if (cargo.area_cargo !== areaActual) {
            areaActual = cargo.area_cargo;
            const headerRow = document.createElement('tr');
            headerRow.innerHTML = `
                <th colspan="9" class="bg-light text-secondary text-start ps-3 py-2">
                    <i class="bi bi-diagram-3 me-2"></i>Área: ${areaActual || 'Sin Área Definida'}
                </th>`;
            tbody.appendChild(headerRow);
        }
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="text-start fw-bold">
                ${cargo.nombre_cargo}
                <input type="hidden" value="${cargo.salario_propuesto || 0}"
                       data-cargo="${cargo.cod_cargo}" data-area="Administrativo" data-campo="salario_propuesto">
            </td>
            <td class="cell-editable">
                <input type="number" class="form-control form-control-sm editable-input"
                       value="${cargo.cantidad_real || 0}" min="0"
                       data-cargo="${cargo.cod_cargo}" data-area="Administrativo" data-campo="cantidad_real"
                       ${!puedeEditar ? 'disabled' : ''} onchange="marcarCambio(this)">
            </td>
            <td class="cell-editable">
                <input type="number" class="form-control form-control-sm editable-input"
                       value="${cargo.cantidad_adicional || 0}" min="0"
                       data-cargo="${cargo.cod_cargo}" data-area="Administrativo" data-campo="cantidad_adicional"
                       ${!puedeEditar ? 'disabled' : ''} onchange="marcarCambio(this)">
            </td>
            <td><span class="fw-bold text-dark">${cargo.cantidad_cubierta || 0}</span></td>
            <td>
                <div class="d-flex align-items-center justify-content-center gap-2">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox"
                               ${cargo.visible_web == 1 ? 'checked' : ''}
                               data-cargo="${cargo.cod_cargo}" data-area="Administrativo" data-campo="visible_web"
                               ${!puedeEditar ? 'disabled' : ''} onchange="toggleWebLink(this)">
                    </div>
                    ${cargo.config_id > 0 ? `
                    <a href="https://talento.batidospitaya.com/postular.php?plaza=${cargo.config_id}&cargo=${cargo.cod_cargo}&sucursal=18"
                       target="_blank"
                       class="btn btn-sm btn-outline-primary py-0 px-1 link-postulacion ${cargo.visible_web == 1 ? '' : 'd-none'}"
                       title="Link de postulación"><i class="bi bi-link-45deg"></i></a>` : ''}
                </div>
            </td>
            <td>${generarSelectUrgencia(cargo.nivel_urgencia || 1, cargo.cod_cargo, 'nivel_urgencia', 'Administrativo', false)}</td>
            <td class="text-center">${generarColumnaPDF(cargo.ruta_pdf_cargo, cargo.config_id, cargo.cod_cargo, 18, 'Administrativo')}</td>
            <td class="text-center">${generarColumnaBanner(cargo.ruta_banner, cargo.config_id, cargo.cod_cargo, 18, 'Administrativo')}</td>
            <td class="text-center">
                <div class="form-check form-switch d-flex justify-content-center mb-0">
                    <input class="form-check-input operativo-toggle" type="checkbox" role="switch" checked
                           title="Desactivar cargo" ${!puedeEditar ? 'disabled' : ''}
                           onchange="toggleOperativo(this, ${cargo.cod_cargo}, 'administrativo')">
                </div>
            </td>`;
        tbody.appendChild(row);
    });

    // Inactivos
    const container = document.getElementById('inactivosAdministrativoContainer');
    const lista     = document.getElementById('listaInactivosAdmin');
    const counter   = document.getElementById('countInactivosAdmin');
    lista.innerHTML = '';
    if (inactivos.length > 0) {
        container.classList.remove('d-none');
        counter.textContent = inactivos.length;
        inactivos.forEach(cargo => {
            const item = document.createElement('div');
            item.className = 'inactive-cargo-item';
            item.innerHTML = `
                <div class="cargo-info">
                    <i class="bi bi-person-dash text-muted"></i>
                    <span class="cargo-nombre">${cargo.nombre_cargo}</span>
                    ${cargo.area_cargo ? `<span class="cargo-area">${cargo.area_cargo}</span>` : ''}
                </div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input operativo-toggle" type="checkbox" role="switch"
                           title="Activar cargo" ${!puedeEditar ? 'disabled' : ''}
                           onchange="toggleOperativo(this, ${cargo.cod_cargo}, 'administrativo')">
                </div>`;
            lista.appendChild(item);
        });
    } else {
        container.classList.add('d-none');
    }
}

// ========================================
// CARGAR DATOS PRODUCCIÓN
// ========================================
async function cargarDatosProduccion() {
    try {
        const response = await fetch('ajax/postulacion_panel_control_get_produccion.php');
        const data = await response.json();

        if (data.success) {
            renderizarTablaProduccion(data.datos);
            const activos = data.datos.filter(c => parseInt(c.operativo) !== 0);
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error al cargar producción:', error);
        Swal.fire('Error', 'No se pudieron cargar los datos de producción', 'error');
    }
}

function renderizarTablaProduccion(datos) {
    const tbody = document.getElementById('tablaProduccionBody');
    tbody.innerHTML = '';

    const activos   = datos.filter(c => parseInt(c.operativo) !== 0);
    const inactivos = datos.filter(c => parseInt(c.operativo) === 0);

    let areaActual = '';
    activos.forEach(cargo => {
        if (cargo.area_cargo !== areaActual) {
            areaActual = cargo.area_cargo;
            const headerRow = document.createElement('tr');
            headerRow.innerHTML = `
                <th colspan="9" class="bg-light text-secondary text-start ps-3 py-2">
                    <i class="bi bi-diagram-3 me-2"></i>Área: ${areaActual || 'Sin Área Definida'}
                </th>`;
            tbody.appendChild(headerRow);
        }
        const cantidadReal      = parseInt(cargo.cantidad_real) || 0;
        const cantidadAdicional = parseInt(cargo.cantidad_adicional) || 0;
        const sucursalId = [17, 19, 12, 9, 10].includes(parseInt(cargo.cod_cargo)) ? 18 : 6;
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="text-start fw-bold">
                ${cargo.nombre_cargo}
                <input type="hidden" value="${cargo.salario_propuesto || 0}"
                       data-cargo="${cargo.cod_cargo}" data-area="Produccion" data-campo="salario_propuesto">
            </td>
            <td class="cell-editable">
                <input type="number" class="form-control form-control-sm cantidad-input editable-input"
                       value="${cantidadReal}" min="0"
                       data-cargo="${cargo.cod_cargo}" data-area="Produccion" data-campo="cantidad_real" data-row="${cargo.cod_cargo}"
                       ${!puedeEditar ? 'disabled' : ''} onchange="marcarCambio(this)">
            </td>
            <td class="cell-editable">
                <input type="number" class="form-control form-control-sm adicional-input editable-input"
                       value="${cantidadAdicional}" min="0"
                       data-cargo="${cargo.cod_cargo}" data-area="Produccion" data-campo="cantidad_adicional" data-row="${cargo.cod_cargo}"
                       ${!puedeEditar ? 'disabled' : ''} onchange="marcarCambio(this)">
            </td>
            <td class="text-center"><span class="fw-bold text-dark">${cargo.cantidad_cubierta || 0}</span></td>
            <td>
                <div class="d-flex align-items-center justify-content-center gap-2">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox"
                               ${cargo.visible_web == 1 ? 'checked' : ''}
                               data-cargo="${cargo.cod_cargo}" data-area="Produccion" data-campo="visible_web" data-row="${cargo.cod_cargo}"
                               ${!puedeEditar ? 'disabled' : ''} onchange="toggleWebLink(this)">
                    </div>
                    ${cargo.config_id > 0 ? `
                    <a href="https://talento.batidospitaya.com/postular.php?plaza=${cargo.config_id}&cargo=${cargo.cod_cargo}&sucursal=${sucursalId}"
                       target="_blank"
                       class="btn btn-sm btn-outline-primary py-0 px-1 link-postulacion ${cargo.visible_web == 1 ? '' : 'd-none'}"
                       title="Link de postulación"><i class="bi bi-link-45deg"></i></a>` : ''}
                </div>
            </td>
            <td>${generarSelectUrgencia(cargo.nivel_urgencia || 1, cargo.cod_cargo, 'nivel_urgencia', 'Produccion', false)}</td>
            <td class="text-center">${generarColumnaPDF(cargo.ruta_pdf_cargo, cargo.config_id, cargo.cod_cargo, sucursalId, 'Produccion')}</td>
            <td class="text-center">${generarColumnaBanner(cargo.ruta_banner, cargo.config_id, cargo.cod_cargo, sucursalId, 'Produccion')}</td>
            <td class="text-center">
                <div class="form-check form-switch d-flex justify-content-center mb-0">
                    <input class="form-check-input operativo-toggle" type="checkbox" role="switch" checked
                           title="Desactivar cargo" ${!puedeEditar ? 'disabled' : ''}
                           onchange="toggleOperativo(this, ${cargo.cod_cargo}, 'produccion')">
                </div>
            </td>`;
        tbody.appendChild(row);
    });

    // Inactivos
    const container = document.getElementById('inactivosCDSContainer');
    const lista     = document.getElementById('listaInactivosCDS');
    const counter   = document.getElementById('countInactivosCDS');
    lista.innerHTML = '';
    if (inactivos.length > 0) {
        container.classList.remove('d-none');
        counter.textContent = inactivos.length;
        inactivos.forEach(cargo => {
            const item = document.createElement('div');
            item.className = 'inactive-cargo-item';
            item.innerHTML = `
                <div class="cargo-info">
                    <i class="bi bi-person-dash text-muted"></i>
                    <span class="cargo-nombre">${cargo.nombre_cargo}</span>
                    ${cargo.area_cargo ? `<span class="cargo-area">${cargo.area_cargo}</span>` : ''}
                </div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input operativo-toggle" type="checkbox" role="switch"
                           title="Activar cargo" ${!puedeEditar ? 'disabled' : ''}
                           onchange="toggleOperativo(this, ${cargo.cod_cargo}, 'produccion')">
                </div>`;
            lista.appendChild(item);
        });
    } else {
        container.classList.add('d-none');
    }
}



// ========================================
// MARCAR CAMBIOS Y GUARDAR
// ========================================
let cambiosPendientes = false;

function marcarCambio(element) {
    cambiosPendientes = true;
    element.classList.add('border-warning');
}

function toggleWebLink(checkbox) {
    marcarCambio(checkbox);
    const container = checkbox.closest('.d-flex');
    if (container) {
        const link = container.querySelector('.link-postulacion');
        if (link) {
            if (checkbox.checked) {
                link.classList.remove('d-none');
            } else {
                link.classList.add('d-none');
            }
        }
    }
}

async function guardarCambios() {
    if (!cambiosPendientes) {
        Swal.fire('Info', 'No hay cambios pendientes para guardar', 'info');
        return;
    }

    const result = await Swal.fire({
        title: '¿Guardar cambios?',
        text: 'Se actualizará la configuración de todas las plazas',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#218838',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, guardar',
        cancelButtonText: 'Cancelar'
    });

    if (!result.isConfirmed) return;

    // Mostrar loader
    const btnGuardar = event.target;
    btnGuardar.classList.add('btn-loading');
    btnGuardar.disabled = true;

    try {
        // Recopilar datos de sucursales
        const datosSucursalesMap = new Map();

        document.querySelectorAll('#tablaSucursalesBody input[data-sucursal]').forEach(input => {
            const sucursal = input.dataset.sucursal;
            const tipo = input.dataset.tipo;

            if (!datosSucursalesMap.has(sucursal)) {
                datosSucursalesMap.set(sucursal, {
                    vendedor: { cargo: 2, oblig: 0, adic: 0, web: 0, salario: 0 },
                    lider: { cargo: 5, oblig: 0, adic: 0, web: 0, salario: 0 }
                });
            }

            const data = datosSucursalesMap.get(sucursal);
            const value = input.type === 'checkbox' ? (input.checked ? 1 : 0) : (parseFloat(input.value) || 0);

            if (tipo.startsWith('vendedor_')) {
                const field = tipo.replace('vendedor_', '');
                data.vendedor[field] = value;
            } else if (tipo.startsWith('lider_')) {
                const field = tipo.replace('lider_', '');
                data.lider[field] = value;
            }
        });

        // También los selects de urgencia para sucursales
        document.querySelectorAll('#tablaSucursalesBody select[data-sucursal]').forEach(select => {
            const sucursal = select.dataset.sucursal;
            const tipo = select.dataset.tipo;
            const value = parseInt(select.value) || 1;

            if (!datosSucursalesMap.has(sucursal)) {
                datosSucursalesMap.set(sucursal, {
                    vendedor: { cargo: 2, oblig: 0, adic: 0, web: 0, salario: 0, urgencia: 1 },
                    lider: { cargo: 5, oblig: 0, adic: 0, web: 0, salario: 0, urgencia: 1 }
                });
            }

            const data = datosSucursalesMap.get(sucursal);
            if (tipo === 'vendedor_urgencia') data.vendedor.urgencia = value;
            else if (tipo === 'lider_urgencia') data.lider.urgencia = value;
        });

        const datosSucursales = [];
        datosSucursalesMap.forEach((data, sucursal) => {
            datosSucursales.push({
                sucursal,
                cargo: 2,
                cantidad_real: data.vendedor.oblig,
                cantidad_adicional: data.vendedor.adic,
                obligatorio: 1,
                visible_web: data.vendedor.web,
                salario_propuesto: data.vendedor.salario,
                nivel_urgencia: data.vendedor.urgencia
            });
            datosSucursales.push({
                sucursal,
                cargo: 5,
                cantidad_real: 0,
                cantidad_adicional: data.lider.adic,
                obligatorio: 1,
                visible_web: data.lider.web,
                salario_propuesto: data.lider.salario,
                nivel_urgencia: data.lider.urgencia
            });
        });

        // Recopilar datos de Administrativo
        const datosAdministrativo = [];
        document.querySelectorAll('#tablaAdministrativoBody tr').forEach(row => {
            const inputs = row.querySelectorAll('input');
            if (inputs.length === 0) return; // Saltar filas de encabezado de área

            const cargo = inputs[0].dataset.cargo;
            const area = inputs[0].dataset.area;
            const cantidadReal = parseInt(inputs[0].value) || 0;
            const cantidadAdicional = parseInt(inputs[1].value) || 0;
            const visibleWeb = row.querySelector('input[data-campo="visible_web"]').checked ? 1 : 0;
            const salarioPropuesto = parseFloat(row.querySelector('input[data-campo="salario_propuesto"]').value) || 0;
            const selectUrgencia = row.querySelector('select[data-campo="nivel_urgencia"]');
            const nivelUrgencia = selectUrgencia ? (parseInt(selectUrgencia.value) || 1) : 1;

            datosAdministrativo.push({
                cargo,
                area,
                cantidad_real: cantidadReal,
                cantidad_adicional: cantidadAdicional,
                obligatorio: 1, // Siempre 1
                visible_web: visibleWeb,
                salario_propuesto: salarioPropuesto,
                nivel_urgencia: nivelUrgencia
            });
        });

        // Recopilar datos de Producción
        const datosProduccion = [];
        document.querySelectorAll('#tablaProduccionBody tr').forEach(row => {
            const inputs = row.querySelectorAll('input');
            if (inputs.length === 0) return; // Saltar filas de encabezado de área

            const cargo = inputs[0].dataset.cargo;
            const area = inputs[0].dataset.area;
            const cantidadReal = parseInt(inputs[0].value) || 0;
            const cantidadAdicional = parseInt(inputs[1].value) || 0;
            const visibleWeb = row.querySelector('input[data-campo="visible_web"]').checked ? 1 : 0;
            const salarioPropuesto = parseFloat(row.querySelector('input[data-campo="salario_propuesto"]').value) || 0;
            const selectUrgencia = row.querySelector('select[data-campo="nivel_urgencia"]');
            const nivelUrgencia = selectUrgencia ? (parseInt(selectUrgencia.value) || 1) : 1;

            datosProduccion.push({
                cargo,
                area,
                cantidad_real: cantidadReal,
                cantidad_adicional: cantidadAdicional,
                obligatorio: 1, // Siempre 1
                visible_web: visibleWeb,
                salario_propuesto: salarioPropuesto,
                nivel_urgencia: nivelUrgencia
            });
        });

        // Combinar datos
        const allCargos = [
            ...datosAdministrativo.map(c => ({ ...c, sucursal: 18 })),
            ...datosProduccion.map(c => {
                // Si es uno de los cargos movidos desde administrativo (aunque estén en pestaña CDS), su sucursal sigue siendo 18
                const sucursalId = [17, 19, 12, 9, 10].includes(parseInt(c.cargo)) ? 18 : 6;
                return { ...c, sucursal: sucursalId };
            })
        ];

        const response = await fetch('ajax/postulacion_panel_control_guardar.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                sucursales: datosSucursales,
                cargos: allCargos
            })
        });

        const data = await response.json();

        if (data.success) {
            cambiosPendientes = false;

            // Remover bordes de advertencia
            document.querySelectorAll('.border-warning').forEach(el => {
                el.classList.remove('border-warning');
            });

            await Swal.fire('Éxito', 'Configuración guardada correctamente', 'success');

            // Recargar datos para reflejar cambios
            cargarDatosSucursales();
            cargarDatosAdministrativo();
            cargarDatosProduccion();
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error al guardar:', error);
        Swal.fire('Error', 'No se pudieron guardar los cambios: ' + error.message, 'error');
    } finally {
        btnGuardar.classList.remove('btn-loading');
        btnGuardar.disabled = false;
    }
}

// ========================================
// FUNCIONES PDF
// ========================================

function generarColumnaPDF(ruta, idConfig, cargo, sucursal, area, isHeader = false) {
    const btnClassView = isHeader ? 'btn-light text-primary' : 'btn-outline-primary';
    const btnClassDel = isHeader ? 'btn-light text-danger' : 'btn-outline-danger';
    const btnClassUp = isHeader ? 'btn-light text-success' : 'btn-outline-success';

    if (ruta && ruta !== '') {
        return `
            <div class="btn-group btn-group-sm">
                <a href="uploads/cargos/${ruta}" target="_blank" class="btn ${btnClassView}" title="Ver PDF">
                    <i class="bi bi-file-earmark-pdf"></i>
                </a>
                ${puedeEditar ? `
                <button type="button" class="btn ${btnClassDel}" 
                        onclick="eliminarArchivo(${idConfig}, 'pdf', ${cargo}, ${sucursal}, '${area}')" title="Eliminar PDF">
                    <i class="bi bi-trash"></i>
                </button>
                ` : ''}
            </div>
        `;
    } else {
        return puedeEditar ? `
            <button type="button" class="btn btn-sm ${btnClassUp}" 
                    onclick="abrirDialogoSubida(${idConfig}, ${cargo}, ${sucursal}, '${area}')" 
                    title="Subir PDF">
                <i class="bi bi-upload"></i>
            </button>
        ` : '<span class="text-muted small">Sin PDF</span>';
    }
}

let uploadMeta = {};

function abrirDialogoSubida(idConfig, cargo, sucursal, area, tipo = 'pdf') {
    uploadMeta = { idConfig, cargo, sucursal, area, tipo };
    if (tipo === 'pdf') {
        document.getElementById('inputUploadPDF').click();
    } else {
        document.getElementById('inputUploadBanner').click();
    }
}

function generarColumnaBanner(ruta, idConfig, cargo, sucursal, area) {
    if (ruta && ruta !== '') {
        return `
            <div class="btn-group btn-group-sm">
                <a href="uploads/banner_puesto/${ruta}" target="_blank" class="btn btn-outline-info" title="Ver Banner">
                    <i class="bi bi-image"></i>
                </a>
                ${puedeEditar ? `
                <button type="button" class="btn btn-outline-danger" 
                        onclick="eliminarArchivo(${idConfig}, 'banner', ${cargo}, ${sucursal}, '${area}')" title="Eliminar Banner">
                    <i class="bi bi-trash"></i>
                </button>
                ` : ''}
            </div>
        `;
    } else {
        return puedeEditar ? `
            <button type="button" class="btn btn-sm btn-outline-success" 
                    onclick="abrirDialogoSubida(${idConfig}, ${cargo}, ${sucursal}, '${area}', 'banner')" 
                    title="Subir Banner">
                <i class="bi bi-image"></i>
            </button>
        ` : '<span class="text-muted small">Sin Banner</span>';
    }
}

document.getElementById('inputUploadBanner').addEventListener('change', async function () {
    const file = this.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('accion', 'subir');
    formData.append('tipo', 'banner');
    formData.append('banner', file);
    formData.append('id_config', uploadMeta.idConfig);
    formData.append('cargo', uploadMeta.cargo);
    formData.append('sucursal', uploadMeta.sucursal);
    formData.append('area', uploadMeta.area);

    try {
        Swal.fire({
            title: 'Subiendo imagen...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        const response = await fetch('ajax/postulacion_panel_control_pdf_accion.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (data.success) {
            Swal.fire('Éxito', data.message, 'success');
            if (uploadMeta.area === 'Sucursales') cargarDatosSucursales();
            else if (uploadMeta.area === 'Administrativo') cargarDatosAdministrativo();
            else if (uploadMeta.area === 'Produccion') cargarDatosProduccion();
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        Swal.fire('Error', error.message, 'error');
    } finally {
        this.value = '';
    }
});

document.getElementById('inputUploadPDF').addEventListener('change', async function () {
    const file = this.files[0];
    if (!file) return;

    const allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp'];
    const fileExtension = file.name.split('.').pop().toLowerCase();

    if (!allowedExtensions.includes(fileExtension)) {
        Swal.fire('Error', 'Solo se permiten archivos PDF, Word o Imágenes (JPG, PNG, WEBP)', 'error');
        this.value = '';
        return;
    }

    const formData = new FormData();
    formData.append('accion', 'subir');
    formData.append('tipo', 'pdf');
    formData.append('pdf', file);
    formData.append('id_config', uploadMeta.idConfig);
    formData.append('cargo', uploadMeta.cargo);
    formData.append('sucursal', uploadMeta.sucursal);
    formData.append('area', uploadMeta.area);

    try {
        Swal.fire({
            title: 'Subiendo...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        const response = await fetch('ajax/postulacion_panel_control_pdf_accion.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (data.success) {
            Swal.fire('Éxito', data.message, 'success');
            // Recargar datos
            if (uploadMeta.area === 'Sucursales') cargarDatosSucursales();
            else if (uploadMeta.area === 'Administrativo') cargarDatosAdministrativo();
            else if (uploadMeta.area === 'Produccion') cargarDatosProduccion();
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        Swal.fire('Error', error.message, 'error');
    } finally {
        this.value = '';
    }
});

async function eliminarArchivo(idConfig, tipo = 'pdf', cargo = 0, sucursal = 0, area = '') {
    const isGlobal = (sucursal === 0);
    const result = await Swal.fire({
        title: '¿Estás seguro?',
        text: isGlobal
            ? `Se eliminará el ${tipo === 'pdf' ? 'documento PDF' : 'banner'} para TODAS las sucursales activas.`
            : `Se eliminará el ${tipo === 'pdf' ? 'documento PDF' : 'banner'} asociado a este cargo.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    });

    if (result.isConfirmed) {
        try {
            const formData = new FormData();
            formData.append('accion', 'eliminar');
            formData.append('tipo', tipo);
            formData.append('id_config', idConfig);
            formData.append('cargo', cargo);
            formData.append('sucursal', sucursal);
            formData.append('area', area);

            const response = await fetch('ajax/postulacion_panel_control_pdf_accion.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (data.success) {
                Swal.fire('Eliminado', data.message, 'success');
                cargarDatosSucursales();
                cargarDatosAdministrativo();
                cargarDatosProduccion();
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    }
}

// Alias para mantener compatibilidad si se usa el nombre viejo
function eliminarPDF(idConfig) {
    eliminarArchivo(idConfig, 'pdf');
}

function generarSelectUrgencia(valor, id, tipo, area, isFixed = false) {
    const opciones = [
        { val: 1, text: '⚪ No urgente' },
        { val: 2, text: '🟡 Medio' },
        { val: 3, text: '🟠 Urgente' },
        { val: 4, text: '🔴 Crítico' }
    ];

    // Si es fixed, forzar valor a 4 (Crítico)
    const valorFinal = isFixed ? 4 : valor;

    let html = `<select class="form-select form-select-sm" 
                        onchange="marcarCambio(this)"
                        ${(!puedeEditar || isFixed) ? 'disabled' : ''}
                        data-tipo="${tipo}" 
                        data-area="${area}"
                        ${area === 'Sucursales' ? `data-sucursal="${id}"` : `data-cargo="${id}" data-campo="nivel_urgencia"`}>`;

    opciones.forEach(opt => {
        html += `<option value="${opt.val}" ${valorFinal == opt.val ? 'selected' : ''}>${opt.text}</option>`;
    });

    html += `</select>`;
    return html;
}

// ========================================
// TOGGLE OPERATIVO (activo / inactivo)
// ========================================
async function toggleOperativo(checkbox, codCargo, tipo) {
    const nuevoEstado = checkbox.checked ? 1 : 0;
    const accion      = nuevoEstado ? 'activar' : 'desactivar';

    const result = await Swal.fire({
        title: `¿${nuevoEstado ? 'Activar' : 'Desactivar'} este cargo?`,
        text: nuevoEstado
            ? 'El cargo volverá a aparecer en la tabla de configuración activa.'
            : 'El cargo pasará a la lista de inactivos y no podrá editarse hasta reactivarse.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: nuevoEstado ? '#198754' : '#6c757d',
        cancelButtonColor: '#dc3545',
        confirmButtonText: `Sí, ${accion}`,
        cancelButtonText: 'Cancelar'
    });

    if (!result.isConfirmed) {
        checkbox.checked = !checkbox.checked; // revertir
        return;
    }

    try {
        const response = await fetch('ajax/postulacion_panel_control_toggle_operativo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cod_cargo: codCargo, operativo: nuevoEstado })
        });
        const data = await response.json();
        if (data.success) {
            if (tipo === 'administrativo') cargarDatosAdministrativo();
            else cargarDatosProduccion();
        } else {
            checkbox.checked = !checkbox.checked;
            Swal.fire('Error', data.message, 'error');
        }
    } catch (error) {
        checkbox.checked = !checkbox.checked;
        Swal.fire('Error', 'No se pudo actualizar el estado del cargo.', 'error');
    }
}
