// =====================================================
// JavaScript para Módulo Híbrido de Vacaciones y Faltas
// erp.batidospitaya - Recursos Humanos
// =====================================================

// ── Selector de Duración (cantidad_dias) ─────────────────────────────────
/**
 * Muestra/oculta el campo personalizado de cantidad de días cuando el usuario
 * selecciona "Personalizado..." en el dropdown de duración.
 * @param {HTMLSelectElement} selectEl - El elemento <select> de duración.
 * @param {string} customContainerId - ID del <div> que contiene el input personalizado.
 */
function manejarCantidadDias(selectEl, customContainerId) {
    const customContainer = document.getElementById(customContainerId);
    if (!customContainer) return;
    if (selectEl.value === 'custom') {
        customContainer.style.display = 'block';
    } else {
        customContainer.style.display = 'none';
    }
}

/**
 * Actualiza el valor del <select> de duración cuando el usuario escribe un valor
 * personalizado en el input numérico. Si el valor no coincide con ninguna opción
 * predefinida, mantiene la opción "custom" seleccionada pero guarda el valor real
 * en un data-attribute para que sea recogido en el submit.
 * @param {HTMLInputElement} inputEl - El campo numérico personalizado.
 * @param {string} selectId - ID del <select> de duración relacionado.
 */
function actualizarCantidadPersonalizada(inputEl, selectId) {
    const selectEl = document.getElementById(selectId);
    if (!selectEl) return;
    const val = parseFloat(inputEl.value);
    if (isNaN(val) || val <= 0 || val > 1) return;
    // Guardar el valor en un data attribute del select para recuperarlo en el submit
    selectEl.dataset.customValue = val.toFixed(2);
}

/**
 * Obtiene el valor real de cantidad_dias de un elemento select de duración.
 * Si el valor es 'custom', retorna el data-custom-value.
 * @param {string} selectId - ID del <select> de duración.
 * @returns {string} El valor decimal a enviar (ej. "0.50").
 */
function obtenerCantidadDias(selectId) {
    const selectEl = document.getElementById(selectId);
    if (!selectEl) return '1.00';
    if (selectEl.value === 'custom') {
        return selectEl.dataset.customValue || '1.00';
    }
    return selectEl.value;
}

/**
 * Inicializa el selector de duración con un valor decimal dado.
 * Si el valor no coincide con ninguna opción predefinida, selecciona "Personalizado"
 * y rellena el campo numérico.
 * @param {string} selectId - ID del select de duración.
 * @param {string} customContainerId - ID del div contenedor del input personalizado.
 * @param {string} customInputId - ID del input numérico personalizado.
 * @param {number|string} valor - El valor decimal a inicializar (ej. 0.5 o "0.50").
 */
function inicializarSelectorDuracion(selectId, customContainerId, customInputId, valor) {
    const selectEl = document.getElementById(selectId);
    const customContainer = document.getElementById(customContainerId);
    const customInput = document.getElementById(customInputId);
    if (!selectEl) return;
    const strVal = parseFloat(valor).toFixed(2);
    // Verificar si existe la opción en el select
    let encontrado = false;
    for (const opt of selectEl.options) {
        if (opt.value === strVal) {
            selectEl.value = strVal;
            encontrado = true;
            break;
        }
    }
    if (!encontrado) {
        selectEl.value = 'custom';
        selectEl.dataset.customValue = strVal;
        if (customInput) customInput.value = strVal;
        if (customContainer) customContainer.style.display = 'block';
    } else {
        if (customContainer) customContainer.style.display = 'none';
    }
}

// Autocompletado de colaboradores para el buscador general
const operariosData = window.CONFIG_VACACIONES ? window.CONFIG_VACACIONES.operariosData : [];

function buscarOperarios(texto) {
    if (!texto) return operariosData;
    return operariosData.filter(op =>
        op.nombre.toLowerCase().includes(texto.toLowerCase())
    );
}

// Configurar buscador principal de operario si existe
const operarioInput = document.getElementById('operario');
const operarioIdInput = document.getElementById('operario_id');
const sugerenciasDiv = document.getElementById('operarios-sugerencias');

if (operarioInput && sugerenciasDiv) {
    operarioInput.addEventListener('input', function () {
        const texto = this.value.trim();

        if (texto === '') {
            operarioIdInput.value = '0';
            sugerenciasDiv.style.display = 'none';
            return;
        }

        const resultados = buscarOperarios(texto);
        sugerenciasDiv.innerHTML = '';

        if (resultados.length > 0) {
            resultados.forEach(op => {
                const div = document.createElement('div');
                div.textContent = op.nombre;
                div.style.padding = '8px';
                div.style.cursor = 'pointer';
                div.addEventListener('click', function () {
                    operarioInput.value = op.nombre;
                    operarioIdInput.value = op.id;
                    sugerenciasDiv.style.display = 'none';
                });
                div.addEventListener('mouseover', function () {
                    this.style.backgroundColor = 'rgba(81, 184, 172, 0.1)';
                });
                div.addEventListener('mouseout', function () {
                    this.style.backgroundColor = 'white';
                });
                sugerenciasDiv.appendChild(div);
            });
            sugerenciasDiv.style.display = 'block';
        } else {
            sugerenciasDiv.style.display = 'none';
        }
    });

    // Ocultar sugerencias al hacer clic fuera
    document.addEventListener('click', function (e) {
        if (e.target !== operarioInput) {
            sugerenciasDiv.style.display = 'none';
        }
    });

    // Tecla Enter
    operarioInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const texto = this.value.trim();
            const resultados = buscarOperarios(texto);
            if (resultados.length > 0) {
                this.value = resultados[0].nombre;
                operarioIdInput.value = resultados[0].id;
            }
            sugerenciasDiv.style.display = 'none';
        }
    });
}

// =====================================================
// MANEJO DE OPERARIOS DINÁMICOS POR SUCURSAL
// =====================================================
function cargarOperariosSucursal(codSucursal, selectId, fechaRef = '') {
    const selectOperario = document.getElementById(selectId);
    if (!selectOperario) return;

    if (!codSucursal) {
        selectOperario.innerHTML = '<option value="">Seleccione un colaborador</option>';
        return;
    }

    selectOperario.innerHTML = '<option value="">⏳ Cargando colaboradores...</option>';

    let url = `ajax/vacaciones_ajax.php?action=obtener_operarios&sucursal=${codSucursal}`;
    if (fechaRef) {
        url += `&fecha=${fechaRef}`;
    }

    fetch(url)
        .then(response => {
            if (!response.ok) throw new Error('Error en el servidor');
            return response.json();
        })
        .then(data => {
            let options = '<option value="">Seleccione un colaborador</option>';

            if (data.length > 0) {
                data.forEach(operario => {
                    const nombreCompleto = operario.Nombre + ' ' +
                        (operario.Apellido || '') + ' ' +
                        (operario.Apellido2 || '');
                    options += `<option value="${operario.CodOperario}">${nombreCompleto.trim()}</option>`;
                });
            } else {
                options = '<option value="">No hay colaboradores disponibles</option>';
            }

            selectOperario.innerHTML = options;
        })
        .catch(error => {
            console.error('Error al cargar colaboradores:', error);
            selectOperario.innerHTML = '<option value="">❌ Error al cargar colaboradores</option>';
        });
}

// =====================================================
// MÉTODOS DE CÁLCULO DE DÍAS Y PORCENTAJES
// =====================================================
function calcularDiasLaborables(fechaInicio, fechaFin) {
    if (!fechaInicio || !fechaFin) return 0;
    const inicio = new Date(fechaInicio + 'T00:00:00');
    const fin = new Date(fechaFin + 'T00:00:00');
    if (inicio > fin) return 0;
    
    let diasTotales = 0;
    const fechaActual = new Date(inicio);
    while (fechaActual <= fin) {
        diasTotales++;
        fechaActual.setDate(fechaActual.getDate() + 1);
    }
    return diasTotales;
}

// Vacaciones
function actualizarInfoRangoVacacion() {
    const fechaInicio = document.getElementById('nueva_fecha_inicio').value;
    const fechaFin = document.getElementById('nueva_fecha_fin').value;
    const infoRango = document.getElementById('info-rango-vacaciones');

    if (!fechaInicio || !fechaFin) {
        if (infoRango) infoRango.style.display = 'none';
        return;
    }

    const inicio = new Date(fechaInicio + 'T00:00:00');
    const fin = new Date(fechaFin + 'T00:00:00');

    if (inicio > fin) {
        if (infoRango) {
            infoRango.innerHTML = '<p style="color: #dc3545; margin:0;"><strong>Error:</strong> La fecha de inicio no puede ser mayor que la fecha fin</p>';
            infoRango.style.display = 'block';
        }
        return;
    }

    const diasLaborables = calcularDiasLaborables(fechaInicio, fechaFin);
    
    const infoDiasTotales = document.getElementById('info-dias-totales');
    if (infoDiasTotales) {
        infoDiasTotales.textContent = `Días totales en rango: ${diasLaborables}`;
    }
    
    const infoVacaciones = document.getElementById('info-vacaciones');
    if (infoVacaciones) {
        infoVacaciones.textContent = `Días a registrar como vacaciones: ${diasLaborables}`;
    }
    
    if (infoRango) infoRango.style.display = 'block';
}

function actualizarPorcentajeVacaciones(tipoFalta) {
    const select = document.getElementById('nueva_tipo');
    if (!select) return;
    const option = select.querySelector(`option[value="${tipoFalta}"]`);
    const infoElement = document.getElementById('info-porcentaje-vacaciones');
    if (!infoElement) return;

    if (option && option.dataset.porcentaje) {
        const porcentaje = parseFloat(option.dataset.porcentaje);
        let texto = '';

        if (porcentaje === -100) {
            texto = '⚠️ La empresa NO paga este día - se DEDUCE del salario';
            infoElement.style.color = '#dc3545';
        } else if (porcentaje === 0) {
            texto = 'ℹ️ La empresa NO paga este día (0%)';
            infoElement.style.color = '#ffc107';
        } else if (porcentaje === 100) {
            texto = '✅ La empresa paga el 100% de este día';
            infoElement.style.color = '#28a745';
        } else {
            texto = `📊 La empresa paga el ${porcentaje}% de este día`;
            infoElement.style.color = '#17a2b8';
        }

        infoElement.textContent = texto;
        infoElement.style.display = 'block';
    } else {
        infoElement.style.display = 'none';
    }
}

// Subsidios
function actualizarInfoRangoSubsidio() {
    const fechaInicio = document.getElementById('subsidio_fecha_inicio').value;
    const fechaFin = document.getElementById('subsidio_fecha_fin').value;
    const infoRango = document.getElementById('info-rango-subsidio');

    if (!fechaInicio || !fechaFin) {
        if (infoRango) infoRango.style.display = 'none';
        return;
    }

    const inicio = new Date(fechaInicio + 'T00:00:00');
    const fin = new Date(fechaFin + 'T00:00:00');

    if (inicio > fin) {
        if (infoRango) {
            infoRango.innerHTML = '<p style="color: #dc3545; margin:0;"><strong>Error:</strong> La fecha de inicio no puede ser mayor que la fecha fin</p>';
            infoRango.style.display = 'block';
        }
        return;
    }

    const diasLaborables = calcularDiasLaborables(fechaInicio, fechaFin);
    
    document.getElementById('info-dias-totales-subsidio').textContent = `Días totales en rango: ${diasLaborables}`;
    document.getElementById('info-dias-subsidio').textContent = `Días a registrar como subsidio: ${diasLaborables}`;
    
    if (infoRango) infoRango.style.display = 'block';
}

function actualizarPorcentajeSubsidio(tipoFalta) {
    const select = document.getElementById('subsidio_tipo');
    if (!select) return;
    const option = select.querySelector(`option[value="${tipoFalta}"]`);
    const infoElement = document.getElementById('info-porcentaje-subsidio');
    if (!infoElement) return;

    if (option && option.dataset.porcentaje) {
        const porcentaje = parseFloat(option.dataset.porcentaje);
        let texto = '';

        if (porcentaje === 0) {
            texto = 'ℹ️ La empresa NO paga este día (0%)';
            infoElement.style.color = '#ffc107';
        } else if (porcentaje === 100) {
            texto = '✅ La empresa paga el 100% de este día';
            infoElement.style.color = '#28a745';
        } else {
            texto = `📊 La empresa paga el ${porcentaje}% de este día`;
            infoElement.style.color = '#17a2b8';
        }

        infoElement.textContent = texto;
        infoElement.style.display = 'block';
    } else {
        infoElement.style.display = 'none';
    }
}

// Faltas/Permisos
function actualizarInfoRangoFaltaPermiso() {
    const fechaInicio = document.getElementById('falta_fecha_inicio').value;
    const fechaFin = document.getElementById('falta_fecha_fin').value;
    const infoRango = document.getElementById('info-rango-falta');

    if (!fechaInicio || !fechaFin) {
        if (infoRango) infoRango.style.display = 'none';
        return;
    }

    const inicio = new Date(fechaInicio + 'T00:00:00');
    const fin = new Date(fechaFin + 'T00:00:00');

    if (inicio > fin) {
        if (infoRango) {
            infoRango.innerHTML = '<p style="color: #dc3545; margin:0;"><strong>Error:</strong> La fecha de inicio no puede ser mayor que la fecha fin</p>';
            infoRango.style.display = 'block';
        }
        return;
    }

    const diasLaborables = calcularDiasLaborables(fechaInicio, fechaFin);
    
    document.getElementById('info-dias-totales-falta').textContent = `Días totales en rango: ${diasLaborables}`;
    document.getElementById('info-dias-falta').textContent = `Días a registrar como falta/permiso: ${diasLaborables}`;
    
    if (infoRango) infoRango.style.display = 'block';
}

function actualizarPorcentajeFaltaPermiso(tipoFalta) {
    const select = document.getElementById('falta_tipo');
    if (!select) return;
    const option = select.querySelector(`option[value="${tipoFalta}"]`);
    const infoElement = document.getElementById('info-porcentaje-falta');
    if (!infoElement) return;

    if (option && option.dataset.porcentaje) {
        const porcentaje = parseFloat(option.dataset.porcentaje);
        let texto = '';

        if (porcentaje === 0) {
            texto = 'ℹ️ La empresa NO paga este día (0%)';
            infoElement.style.color = '#ffc107';
        } else if (porcentaje === 100) {
            texto = '✅ La empresa paga el 100% de este día';
            infoElement.style.color = '#28a745';
        } else {
            texto = `📊 La empresa paga el ${porcentaje}% de este día`;
            infoElement.style.color = '#17a2b8';
        }

        infoElement.textContent = texto;
        infoElement.style.display = 'block';
    } else {
        infoElement.style.display = 'none';
    }
}

// Edición / Aprobación
function actualizarPorcentajeEdicion(tipoFalta) {
    const select = document.getElementById('editar_tipo');
    if (!select) return;
    const option = select.querySelector(`option[value="${tipoFalta}"]`);
    const infoElement = document.getElementById('info-porcentaje-edicion');
    if (!infoElement) return;

    if (option && option.dataset.porcentaje) {
        const porcentaje = parseFloat(option.dataset.porcentaje);
        let texto = '';

        if (porcentaje === -100) {
            texto = '⚠️ La empresa NO paga este día - se DEDUCE del salario';
            infoElement.style.color = '#dc3545';
        } else if (porcentaje === 0) {
            texto = 'ℹ️ La empresa NO paga este día (0%)';
            infoElement.style.color = '#ffc107';
        } else if (porcentaje === 100) {
            texto = '✅ La empresa paga el 100% de este día';
            infoElement.style.color = '#28a745';
        } else {
            texto = `📊 La empresa paga el ${porcentaje}% de este día`;
            infoElement.style.color = '#17a2b8';
        }

        infoElement.textContent = texto;
        infoElement.style.display = 'block';
    } else {
        infoElement.style.display = 'none';
    }
}

function recargarOperariosModal(prefijo) {
    const sucSel = document.getElementById(prefijo + '_sucursal');
    const fechaInput = document.getElementById(prefijo + '_fecha_inicio');
    if (sucSel && fechaInput) {
        cargarOperariosSucursal(sucSel.value, prefijo + '_operario', fechaInput.value);
    }
}

// =====================================================
// HELPER: CIERRA TODOS LOS MODALES BOOTSTRAP ABIERTOS
// Garantiza que solo un modal esté visible a la vez
// =====================================================
function cerrarTodosLosModales(callback) {
    // Quitar el foco de cualquier elemento activo para evitar warnings de accesibilidad (aria-hidden)
    if (document.activeElement && typeof document.activeElement.blur === 'function') {
        document.activeElement.blur();
    }

    const abiertos = document.querySelectorAll('.modal.show');
    if (abiertos.length === 0) {
        if (callback) callback();
        return;
    }
    let pendientes = abiertos.length;
    abiertos.forEach(function (modalEl) {
        const instancia = bootstrap.Modal.getInstance(modalEl);
        if (instancia) {
            modalEl.addEventListener('hidden.bs.modal', function handler() {
                modalEl.removeEventListener('hidden.bs.modal', handler);
                pendientes--;
                if (pendientes === 0 && callback) callback();
            }, { once: true });
            instancia.hide();
        } else {
            pendientes--;
            if (pendientes === 0 && callback) callback();
        }
    });
}

// =====================================================
// MOSTRAR U OCULTAR MODALES
// =====================================================
function mostrarModalNuevaVacacion() {
    cerrarTodosLosModales(function () {
        // Limpiar formulario antes de abrir
        const form = document.getElementById('formNuevaVacacion');
        if (form) form.reset();
        const infoRango = document.getElementById('info-rango');
        if (infoRango) infoRango.style.display = 'none';

        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNuevaVacacion'));
        modal.show();

        // Autocompletar con fecha actual
        const hoyStr = new Date().toISOString().split('T')[0];
        document.getElementById('nueva_fecha_inicio').value = hoyStr;
        document.getElementById('nueva_fecha_fin').value = hoyStr;

        recargarOperariosModal('nueva');
        actualizarInfoRangoVacacion();

        const tipoSel = document.getElementById('nueva_tipo');
        if (tipoSel) actualizarPorcentajeVacaciones(tipoSel.value);
    });
}

function mostrarModalNuevoSubsidio() {
    cerrarTodosLosModales(function () {
        // Limpiar formulario antes de abrir
        const form = document.getElementById('formNuevoSubsidio');
        if (form) form.reset();
        const infoRango = document.getElementById('info-rango-subsidio');
        if (infoRango) infoRango.style.display = 'none';

        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNuevoSubsidio'));
        modal.show();

        // Autocompletar con fecha actual
        const hoyStr = new Date().toISOString().split('T')[0];
        document.getElementById('subsidio_fecha_inicio').value = hoyStr;
        document.getElementById('subsidio_fecha_fin').value = hoyStr;

        recargarOperariosModal('subsidio');
        actualizarInfoRangoSubsidio();

        const tipoSel = document.getElementById('subsidio_tipo');
        if (tipoSel) actualizarPorcentajeSubsidio(tipoSel.value);
    });
}

function mostrarModalNuevaFaltaPermiso() {
    cerrarTodosLosModales(function () {
        // Limpiar formulario antes de abrir
        const form = document.getElementById('formNuevaFalta');
        if (form) form.reset();
        const infoRango = document.getElementById('info-rango-falta');
        if (infoRango) infoRango.style.display = 'none';

        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNuevaFalta'));
        modal.show();

        // Determinar fecha predeterminada y máxima según permisos
        const esRRHH = window.CONFIG_VACACIONES && (window.CONFIG_VACACIONES.esRH || window.CONFIG_VACACIONES.puedeAprobar);

        const hoyStr = new Date().toISOString().split('T')[0];
        const ayerObj = new Date();
        ayerObj.setDate(ayerObj.getDate() - 1);
        const ayerStr = ayerObj.toISOString().split('T')[0];

        const fechaInicioInput = document.getElementById('falta_fecha_inicio');
        const fechaFinInput   = document.getElementById('falta_fecha_fin');

        if (esRRHH) {
            fechaInicioInput.value = hoyStr;
            fechaFinInput.value   = hoyStr;
            fechaInicioInput.max  = hoyStr;
            fechaFinInput.max     = hoyStr;
        } else {
            fechaInicioInput.value = ayerStr;
            fechaFinInput.value   = ayerStr;
            fechaInicioInput.max  = ayerStr;
            fechaFinInput.max     = ayerStr;
        }

        recargarOperariosModal('falta');
        actualizarInfoRangoFaltaPermiso();

        const tipoSel = document.getElementById('falta_tipo');
        if (tipoSel) actualizarPorcentajeFaltaPermiso(tipoSel.value);
    });
}

function mostrarModalEditarAprobar(id, nombre, sucursal, fecha, tipoFalta, observaciones, observacionesRrhh, fotoPath, cantidadDias) {
    cerrarTodosLosModales(function () {
        document.getElementById('editar_id').value = id;
        document.getElementById('editar_nombre').textContent = nombre;
        document.getElementById('editar_sucursal').textContent = sucursal;

        // Formatear fecha local
        const fLocal = new Date(fecha + 'T00:00:00').toLocaleDateString('es-ES', {
            day: '2-digit', month: 'short', year: 'numeric'
        });
        document.getElementById('editar_fecha').textContent = fLocal;
        document.getElementById('editar_observaciones_lider').textContent = observaciones || '(Sin observaciones)';

        const selectTipo = document.getElementById('editar_tipo');
        if (selectTipo) {
            selectTipo.value = tipoFalta;
            actualizarPorcentajeEdicion(tipoFalta);
        }

        const obsRrhhInput = document.getElementById('editar_observaciones_rrhh');
        if (obsRrhhInput) {
            obsRrhhInput.value = observacionesRrhh || '';
        }

        // Foto Preview
        const previewContainer = document.getElementById('preview-container');
        const previewImage = document.getElementById('preview-image');
        if (previewContainer && previewImage) {
            if (fotoPath) {
                previewImage.src = '../..' + fotoPath;
                previewContainer.style.display = 'block';
            } else {
                previewContainer.style.display = 'none';
            }
        }

        // Inicializar el selector de duración con el valor actual
        inicializarSelectorDuracion(
            'editar_cantidad_dias',
            'editar_custom_dias',
            'editar_custom_input',
            cantidadDias || 1.0
        );

        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditarFalta'));
        modal.show();
    });
}

// =====================================================
// PROCESAMIENTO AJAX DE FORMULARIOS
// =====================================================
function procesarEnvioHibrido(formId, categoriaFalta) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        // Validaciones genéricas
        const codOperario = form.querySelector('[name="cod_operario"]').value;
        const codSucursal = form.querySelector('[name="cod_sucursal"]').value;
        const fechaInicio = form.querySelector('[name="fecha_inicio"]').value;
        const fechaFin = form.querySelector('[name="fecha_fin"]').value;
        const fotoInput = form.querySelector('[name="foto_falta"]');

        if (!fechaInicio || !fechaFin || !codOperario || !codSucursal) {
            alert('Debe completar todos los campos obligatorios');
            return false;
        }

        if (fechaInicio > fechaFin) {
            alert('La fecha de inicio no puede ser mayor que la fecha fin');
            return false;
        }

        if (!fotoInput.files || fotoInput.files.length === 0) {
            alert('Debe subir una foto como evidencia obligatoria');
            return false;
        }

        // Si es falta manual, validar que no sean futuras
        if (categoriaFalta === 'falta_permiso') {
            const esRRHH = window.CONFIG_VACACIONES && (window.CONFIG_VACACIONES.esRH || window.CONFIG_VACACIONES.puedeAprobar);
            
            const limite = new Date();
            if (!esRRHH) {
                // Líderes solo hasta ayer
                limite.setDate(limite.getDate() - 1);
            }
            limite.setHours(23, 59, 59, 999);
            
            const fInicioObj = new Date(fechaInicio + 'T00:00:00');
            const fFinObj = new Date(fechaFin + 'T00:00:00');

            if (fInicioObj > limite || fFinObj > limite) {
                const mensaje = esRRHH ? 
                    'Para faltas y permisos no se permiten fechas futuras.' : 
                    'Para faltas y permisos no se permiten fechas futuras ni el día actual.';
                alert(mensaje);
                return false;
            }
        }

        // Preguntar confirmación antes de guardar
        const diasRango = calcularDiasLaborables(fechaInicio, fechaFin);
        const puedeAprobar = window.CONFIG_VACACIONES && window.CONFIG_VACACIONES.puedeAprobar;

        // Determinar el nombre del select de cantidad_dias según el formulario
        const selectIdMap = {
            'formNuevaVacacion': 'nueva_cantidad_dias',
            'formNuevoSubsidio': 'subsidio_cantidad_dias',
            'formNuevaFalta':    'falta_cantidad_dias'
        };
        const selectCantId = selectIdMap[formId] || null;

        // Leer la cantidad de días seleccionada (solo si puede aprobar y existe el select)
        let cantidadDiasTexto = null;
        if (puedeAprobar && selectCantId) {
            const cantVal = parseFloat(obtenerCantidadDias(selectCantId));
            if (!isNaN(cantVal)) {
                // Buscar el texto de la opción seleccionada para describirlo
                const selectEl = document.getElementById(selectCantId);
                let optionLabel = '';
                if (selectEl && selectEl.value !== 'custom') {
                    optionLabel = selectEl.options[selectEl.selectedIndex]?.text || '';
                    // Quedarnos solo con la parte descriptiva (antes del paréntesis)
                    const parenIdx = optionLabel.indexOf('(');
                    if (parenIdx > 0) optionLabel = optionLabel.substring(0, parenIdx).trim();
                }
                cantidadDiasTexto = optionLabel || `${cantVal} día(s)`;
            }
        }

        let tipoNombre = '';
        const tipoFaltaEl = form.querySelector('[name="tipo_falta"]');
        if (tipoFaltaEl) {
            if (tipoFaltaEl.tagName === 'SELECT' && tipoFaltaEl.selectedIndex !== -1) {
                const optText = tipoFaltaEl.options[tipoFaltaEl.selectedIndex].text;
                // Quitar el texto de porcentaje como "(Paga 100%)"
                tipoNombre = optText.replace(/\s*\(Pagas?\s*-?\d+%\)/gi, '').trim();
            } else if (tipoFaltaEl.tagName === 'INPUT' && tipoFaltaEl.value !== 'Pendiente') {
                tipoNombre = tipoFaltaEl.value;
            }
        }

        let textoConfirmacion;

        if (!puedeAprobar) {
            // Líderes sin permiso de aprobar: el registro queda como Pendiente,
            // no se muestra cantidad de días ni tipo (lo define RRHH después)
            if (categoriaFalta === 'vacaciones') {
                textoConfirmacion = `¿Está seguro de solicitar vacaciones para este rango de ${diasRango} día(s)?\n\nQuedarán como Pendiente hasta ser aprobadas por RRHH.`;
            } else if (categoriaFalta === 'subsidio') {
                textoConfirmacion = `¿Está seguro de registrar el subsidio para este rango de ${diasRango} día(s)?\n\nQuedarán como Pendiente hasta ser revisados por RRHH.`;
            } else {
                textoConfirmacion = `¿Está seguro de registrar esta falta/permiso para este rango de ${diasRango} día(s)?\n\nQuedarán como Pendiente hasta ser revisados por RRHH.`;
            }
        } else {
            // Quien puede aprobar: mostrar tipo y cantidad de días exacta
            const descripcionDias = cantidadDiasTexto
                ? `${diasRango} día(s) — ${cantidadDiasTexto} cada uno`
                : `${diasRango} día(s)`;

            if (categoriaFalta === 'vacaciones') {
                textoConfirmacion = `¿Está seguro de registrar vacaciones para este rango?\n\n• Días en rango: ${diasRango}\n• Duración por día: ${cantidadDiasTexto || '1 día completo'}`;
            } else if (categoriaFalta === 'subsidio') {
                const tipoLower = tipoNombre ? tipoNombre.toLowerCase() : 'subsidio';
                textoConfirmacion = `¿Está seguro de registrar "${tipoNombre || 'subsidio'}" para este rango?\n\n• Días en rango: ${diasRango}\n• Duración por día: ${cantidadDiasTexto || '1 día completo'}`;
            } else {
                const tipoLower = tipoNombre ? tipoNombre.toLowerCase() : 'falta o permiso';
                textoConfirmacion = `¿Está seguro de registrar "${tipoNombre || 'falta/permiso'}" para este rango?\n\n• Días en rango: ${diasRango}\n• Duración por día: ${cantidadDiasTexto || '1 día completo'}`;
            }
        }

        if (!confirm(textoConfirmacion)) {
            return false;
        }

        // Enviar vía AJAX con FormData
        const formData = new FormData(form);
        formData.append('categoria_falta', categoriaFalta);

        // Mostrar loading
        const submitBtn = form.querySelector('button[type="submit"]') || document.querySelector(`button[type="submit"][form="${formId}"]`);
        const originalText = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            submitBtn.disabled = true;
        }

        fetch('ajax/vacaciones_ajax.php?action=guardar_registro', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.reload();
            } else {
                alert('Error: ' + data.error);
                if (submitBtn) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión al guardar el registro. Intente nuevamente.');
            if (submitBtn) {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });

        return false;
    });
}

// Configurar los submits y event listeners al cargar el DOM (una sola vez)
document.addEventListener('DOMContentLoaded', function () {
    procesarEnvioHibrido('formNuevaVacacion', 'vacaciones');
    procesarEnvioHibrido('formNuevoSubsidio', 'subsidio');
    procesarEnvioHibrido('formNuevaFalta', 'falta_permiso');

    // ── Event listeners modal Vacacion ──────────────────────────
    const sucNueva = document.getElementById('nueva_sucursal');
    if (sucNueva) sucNueva.addEventListener('change', () => recargarOperariosModal('nueva'));

    const fechaInicioNueva = document.getElementById('nueva_fecha_inicio');
    if (fechaInicioNueva) fechaInicioNueva.addEventListener('change', function () {
        actualizarInfoRangoVacacion();
        recargarOperariosModal('nueva');
    });
    const fechaFinNueva = document.getElementById('nueva_fecha_fin');
    if (fechaFinNueva) fechaFinNueva.addEventListener('change', actualizarInfoRangoVacacion);

    const tipoNueva = document.getElementById('nueva_tipo');
    if (tipoNueva) tipoNueva.addEventListener('change', function () {
        actualizarPorcentajeVacaciones(this.value);
    });

    // ── Event listeners modal Subsidio ───────────────────────────
    const sucSubsidio = document.getElementById('subsidio_sucursal');
    if (sucSubsidio) sucSubsidio.addEventListener('change', () => recargarOperariosModal('subsidio'));

    const fechaInicioSubsidio = document.getElementById('subsidio_fecha_inicio');
    if (fechaInicioSubsidio) fechaInicioSubsidio.addEventListener('change', function () {
        actualizarInfoRangoSubsidio();
        recargarOperariosModal('subsidio');
    });
    const fechaFinSubsidio = document.getElementById('subsidio_fecha_fin');
    if (fechaFinSubsidio) fechaFinSubsidio.addEventListener('change', actualizarInfoRangoSubsidio);

    const tipoSubsidio = document.getElementById('subsidio_tipo');
    if (tipoSubsidio) tipoSubsidio.addEventListener('change', function () {
        actualizarPorcentajeSubsidio(this.value);
    });

    // ── Event listeners modal Falta/Permiso ──────────────────────
    const sucFalta = document.getElementById('falta_sucursal');
    if (sucFalta) sucFalta.addEventListener('change', function () {
        cargarOperariosSucursal(this.value, 'falta_operario');
    });

    const fechaInicioFalta = document.getElementById('falta_fecha_inicio');
    if (fechaInicioFalta) fechaInicioFalta.addEventListener('change', function () {
        actualizarInfoRangoFaltaPermiso();
        recargarOperariosModal('falta');
    });
    const fechaFinFalta = document.getElementById('falta_fecha_fin');
    if (fechaFinFalta) fechaFinFalta.addEventListener('change', actualizarInfoRangoFaltaPermiso);

    const tipoFaltaSel = document.getElementById('falta_tipo');
    if (tipoFaltaSel) tipoFaltaSel.addEventListener('change', function () {
        actualizarPorcentajeFaltaPermiso(this.value);
    });

    // ── Event listeners modal Editar/Aprobar ─────────────────────
    const selectTipoEditar = document.getElementById('editar_tipo');
    if (selectTipoEditar) selectTipoEditar.addEventListener('change', function () {
        actualizarPorcentajeEdicion(this.value);
    });

    // ── Procesar edición/aprobación por RRHH ─────────────────────
    const formEditar = document.getElementById('formEditarFalta');
    if (formEditar) {
        formEditar.addEventListener('submit', function (e) {
            e.preventDefault();
            const id = document.getElementById('editar_id').value;
            const tipoFalta = document.getElementById('editar_tipo').value;
            const observacionesRrhh = document.getElementById('editar_observaciones_rrhh').value.trim();

            if (!observacionesRrhh) {
                alert('Las observaciones de Recursos Humanos son obligatorias para aprobar o editar.');
                return false;
            }

            // Confirmación con detalle de tipo y duración seleccionada
            const selectTipoEl = document.getElementById('editar_tipo');
            let tipoNombreEditar = tipoFalta;
            if (selectTipoEl && selectTipoEl.selectedIndex !== -1) {
                const optTxt = selectTipoEl.options[selectTipoEl.selectedIndex].text;
                tipoNombreEditar = optTxt.replace(/\s*\(-?\d+%\)/gi, '').trim();
            }

            const selectCantEditar = document.getElementById('editar_cantidad_dias');
            let cantDiasLabel = '1 día completo';
            if (selectCantEditar) {
                if (selectCantEditar.value !== 'custom') {
                    const optLabel = selectCantEditar.options[selectCantEditar.selectedIndex]?.text || '';
                    const parenIdx = optLabel.indexOf('(');
                    cantDiasLabel = parenIdx > 0 ? optLabel.substring(0, parenIdx).trim() : optLabel.trim();
                } else {
                    const customVal = selectCantEditar.dataset.customValue || '1.00';
                    cantDiasLabel = `${customVal} día(s) personalizado`;
                }
            }

            const nombreColaborador = document.getElementById('editar_nombre')?.textContent || '';
            const textoConfirmEditar = `¿Confirmar la aprobación/edición de este registro?\n\n• Colaborador: ${nombreColaborador}\n• Tipo: ${tipoNombreEditar}\n• Duración: ${cantDiasLabel}`;
            if (!confirm(textoConfirmEditar)) {
                return false;
            }

            const submitBtn = formEditar.querySelector('button[type="submit"]') || document.querySelector(`button[type="submit"][form="formEditarFalta"]`);
            const originalText = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
                submitBtn.disabled = true;
            }

            const params = new URLSearchParams({
                id: id,
                tipo_falta: tipoFalta,
                observaciones_rrhh: observacionesRrhh,
                cantidad_dias: obtenerCantidadDias('editar_cantidad_dias')
            });

            fetch('ajax/vacaciones_ajax.php?action=editar_aprobar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert('Error: ' + data.error);
                    if (submitBtn) {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al actualizar el registro.');
                if (submitBtn) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            });

            return false;
        });
    }
});

// Eliminar o rechazar solicitud
function eliminarSolicitud(id) {
    if (!confirm('¿Está seguro de que desea eliminar o rechazar esta solicitud? Esta acción no se puede deshacer.')) {
        return;
    }

    const params = new URLSearchParams({ id: id });

    fetch('ajax/vacaciones_ajax.php?action=eliminar_rechazar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al intentar eliminar el registro.');
    });
}

// Ampliar Imagen (Evidencias)
function mostrarFoto(rutaFoto) {
    ampliarImagen(rutaFoto);
}

function ampliarImagen(src) {
    const modalAmpliar = document.createElement('div');
    modalAmpliar.id = 'modalAmpliarImagen';
    modalAmpliar.style.position = 'fixed';
    modalAmpliar.style.top = '0';
    modalAmpliar.style.left = '0';
    modalAmpliar.style.width = '100%';
    modalAmpliar.style.height = '100%';
    modalAmpliar.style.backgroundColor = 'rgba(0,0,0,0.9)';
    modalAmpliar.style.display = 'flex';
    modalAmpliar.style.justifyContent = 'center';
    modalAmpliar.style.alignItems = 'center';
    modalAmpliar.style.zIndex = '3000';

    const img = document.createElement('img');
    img.src = src;
    img.style.maxWidth = '90%';
    img.style.maxHeight = '90%';
    img.style.objectFit = 'contain';
    img.style.boxShadow = '0 0 20px rgba(255,255,255,0.2)';

    const closeBtn = document.createElement('button');
    closeBtn.innerHTML = '&times;';
    closeBtn.style.position = 'absolute';
    closeBtn.style.top = '20px';
    closeBtn.style.right = '20px';
    closeBtn.style.fontSize = '2.5rem';
    closeBtn.style.color = 'white';
    closeBtn.style.background = 'none';
    closeBtn.style.border = 'none';
    closeBtn.style.cursor = 'pointer';
    closeBtn.style.zIndex = '3001';

    closeBtn.onclick = function () {
        document.body.removeChild(modalAmpliar);
    };

    modalAmpliar.appendChild(img);
    modalAmpliar.appendChild(closeBtn);
    document.body.appendChild(modalAmpliar);

    modalAmpliar.onclick = function (e) {
        if (e.target === modalAmpliar) {
            document.body.removeChild(modalAmpliar);
        }
    };

    const closeOnEsc = function (e) {
        if (e.key === 'Escape') {
            document.body.removeChild(modalAmpliar);
            document.removeEventListener('keydown', closeOnEsc);
        }
    };
    document.addEventListener('keydown', closeOnEsc);
}

// =====================================================
// PAGINACIÓN CLIENT-SIDE - Estilo Pitaya ERP
// =====================================================
let vacPaginaActual = 1;
let vacTotalRegistros = 0;

function vacRegistrosPorPagina() {
    const sel = document.getElementById('registrosPorPaginaVac');
    return sel ? parseInt(sel.value) : 25;
}

function vacRenderizar() {
    const tabla = document.getElementById('listaVacaciones');
    if (!tabla) return;

    const filas = Array.from(tabla.querySelectorAll('tbody tr'));
    vacTotalRegistros = filas.length;
    const rpp = vacRegistrosPorPagina();
    const totalPaginas = Math.ceil(vacTotalRegistros / rpp) || 1;

    if (vacPaginaActual > totalPaginas) vacPaginaActual = totalPaginas;
    if (vacPaginaActual < 1) vacPaginaActual = 1;

    const inicio = (vacPaginaActual - 1) * rpp;
    const fin = inicio + rpp;

    filas.forEach((fila, idx) => {
        fila.style.display = (idx >= inicio && idx < fin) ? '' : 'none';
    });

    const infoEl = document.getElementById('vacInfoRegistros');
    if (infoEl) {
        const desde = vacTotalRegistros === 0 ? 0 : inicio + 1;
        const hasta = Math.min(fin, vacTotalRegistros);
        infoEl.textContent = `Mostrando ${desde}–${hasta} de ${vacTotalRegistros} registros`;
    }

    vacRenderizarPaginacion(totalPaginas);
}

function vacRenderizarPaginacion(totalPaginas) {
    const paginacion = document.getElementById('paginacion');
    if (!paginacion) return;
    paginacion.innerHTML = '';

    const btnPrev = document.createElement('button');
    btnPrev.className = 'pagination-btn';
    btnPrev.innerHTML = '<i class="bi bi-chevron-left"></i>';
    btnPrev.disabled = vacPaginaActual === 1;
    btnPrev.onclick = () => vacCambiarPagina(vacPaginaActual - 1);
    paginacion.appendChild(btnPrev);

    let inicio = Math.max(1, vacPaginaActual - 2);
    let fin = Math.min(totalPaginas, vacPaginaActual + 2);

    if (inicio > 1) {
        paginacion.insertAdjacentHTML('beforeend', `<button class="pagination-btn" onclick="vacCambiarPagina(1)">1</button>`);
        if (inicio > 2) paginacion.insertAdjacentHTML('beforeend', `<span class="pagination-btn" style="cursor:default">…</span>`);
    }

    for (let i = inicio; i <= fin; i++) {
        const active = i === vacPaginaActual ? 'active' : '';
        paginacion.insertAdjacentHTML('beforeend',
            `<button class="pagination-btn ${active}" onclick="vacCambiarPagina(${i})">${i}</button>`);
    }

    if (fin < totalPaginas) {
        if (fin < totalPaginas - 1) paginacion.insertAdjacentHTML('beforeend', `<span class="pagination-btn" style="cursor:default">…</span>`);
        paginacion.insertAdjacentHTML('beforeend',
            `<button class="pagination-btn" onclick="vacCambiarPagina(${totalPaginas})">${totalPaginas}</button>`);
    }

    const btnNext = document.createElement('button');
    btnNext.className = 'pagination-btn';
    btnNext.innerHTML = '<i class="bi bi-chevron-right"></i>';
    btnNext.disabled = vacPaginaActual === totalPaginas;
    btnNext.onclick = () => vacCambiarPagina(vacPaginaActual + 1);
    paginacion.appendChild(btnNext);
}

function vacCambiarPagina(pagina) {
    const rpp = vacRegistrosPorPagina();
    const totalPaginas = Math.ceil(vacTotalRegistros / rpp) || 1;
    if (pagina < 1 || pagina > totalPaginas) return;
    vacPaginaActual = pagina;
    vacRenderizar();
    const tabla = document.getElementById('listaVacaciones');
    if (tabla) tabla.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

document.addEventListener('DOMContentLoaded', function () {
    vacRenderizar();
});

// =====================================================
// FAB ARRASTRABLE — SNAP A 4 ESQUINAS
// =====================================================
(function () {
    const MARGIN = 20;           // px de margen a los bordes
    const DRAG_THRESHOLD = 6;    // px mínimos para considerar arrastre
    const STORAGE_KEY = 'vac_fab_corner';

    function applyCorner(el, corner) {
        // Anular posiciones anteriores usando 'auto' para que no hereden 
        // valores de la hoja de estilos global (evitando conflictos top/bottom o left/right)
        el.style.top    = 'auto';
        el.style.bottom = 'auto';
        el.style.left   = 'auto';
        el.style.right  = 'auto';

        const m = MARGIN + 'px';
        if (corner === 'top-left')          { el.style.top = m;    el.style.left  = m; }
        else if (corner === 'top-right')    { el.style.top = m;    el.style.right = m; }
        else if (corner === 'bottom-left')  { el.style.bottom = m; el.style.left  = m; }
        else                               { el.style.bottom = m; el.style.right = m; }

        // Lado izquierdo → opciones se abren hacia la derecha
        if (corner === 'top-left' || corner === 'bottom-left') {
            el.classList.add('fab-left-side');
        } else {
            el.classList.remove('fab-left-side');
        }

        // Esquina superior → opciones se despliegan hacia abajo
        if (corner === 'top-left' || corner === 'top-right') {
            el.classList.add('fab-top-side');
        } else {
            el.classList.remove('fab-top-side');
        }
    }

    function getCornerFromCenter(el) {
        const rect = el.getBoundingClientRect();
        const cx = rect.left + rect.width  / 2;
        const cy = rect.top  + rect.height / 2;
        const isLeft = cx < window.innerWidth  / 2;
        const isTop  = cy < window.innerHeight / 2;
        if (isTop  && isLeft)  return 'top-left';
        if (isTop  && !isLeft) return 'top-right';
        if (!isTop && isLeft)  return 'bottom-left';
        return 'bottom-right';
    }

    function initDraggableFab() {
        const fab = document.getElementById('fabContainer');
        if (!fab) return;

        // Mover el contenedor al final del body para garantizar que se posicione respecto a la pantalla
        // y evitar heredar coordenadas de contenedores padres con transform, relative, absolute, etc.
        if (fab.parentElement !== document.body) {
            document.body.appendChild(fab);
        }

        const btn = fab.querySelector('.btn-floating-pitaya');
        if (!btn) return;

        let isDragging  = false;
        let dragStarted = false;
        let wasDragged  = false;
        let startX, startY, startLeft, startTop;

        // Restaurar esquina guardada (o default bottom-right)
        const savedCorner = localStorage.getItem(STORAGE_KEY) || 'bottom-right';
        applyCorner(fab, savedCorner);

        // ── Cursor de arrastre ────────────────────────────────────
        btn.style.cursor = 'grab';

        // ── mousedown / touchstart ────────────────────────────────
        function onDragStart(e) {
            // Solo botón izquierdo del ratón
            if (e.button !== undefined && e.button !== 0) return;

            isDragging  = true;
            dragStarted = false;
            wasDragged  = false;

            // Bloquear el :hover CSS y ocultar opciones durante el arrastre
            // (NO se toca .active — el CSS fab-dragging lo suprime con !important
            //  para que el toggle por clic siga funcionando correctamente)
            fab.classList.add('fab-dragging');

            // Deshabilitar pointer-events en las opciones durante el arrastre
            const fabOpts = fab.querySelector('.fab-options');
            if (fabOpts) fabOpts.style.pointerEvents = 'none';

            // Prevenir selección de texto accidental
            document.body.style.userSelect = 'none';

            const touch = e.touches ? e.touches[0] : e;
            startX = touch.clientX;
            startY = touch.clientY;

            const rect = fab.getBoundingClientRect();
            startLeft = rect.left;
            startTop  = rect.top;

            // Congelar transiciones y fijar posición con top/left
            fab.style.transition = 'none';
            fab.style.top    = startTop  + 'px';
            fab.style.left   = startLeft + 'px';
            fab.style.right  = 'auto';
            fab.style.bottom = 'auto';

            btn.style.cursor = 'grabbing';
            e.preventDefault();
        }

        // ── mousemove / touchmove ─────────────────────────────────
        function onDragMove(e) {
            if (!isDragging) return;

            const touch = e.touches ? e.touches[0] : e;
            const dx = touch.clientX - startX;
            const dy = touch.clientY - startY;

            if (!dragStarted && (Math.abs(dx) > DRAG_THRESHOLD || Math.abs(dy) > DRAG_THRESHOLD)) {
                dragStarted = true;
            }
            if (!dragStarted) return;

            wasDragged = true;

            // Calcular nueva posición con límites de pantalla
            const fabW   = fab.getBoundingClientRect().width  || 75;
            const fabH   = fab.getBoundingClientRect().height || 75;
            const maxLeft = window.innerWidth  - fabW  - MARGIN;
            const maxTop  = window.innerHeight - fabH  - MARGIN;

            fab.style.left = Math.max(MARGIN, Math.min(startLeft + dx, maxLeft)) + 'px';
            fab.style.top  = Math.max(MARGIN, Math.min(startTop  + dy, maxTop))  + 'px';

            e.preventDefault();
        }

        // ── mouseup / touchend ────────────────────────────────────
        function onDragEnd() {
            if (!isDragging) return;
            isDragging = false;

            btn.style.cursor = 'grab';

            // Restaurar pointer-events en opciones y selección de texto
            const fabOpts = fab.querySelector('.fab-options');
            if (fabOpts) fabOpts.style.pointerEvents = '';
            document.body.style.userSelect = '';

            // Quitar clase de arrastre (reactiva el hover CSS)
            fab.classList.remove('fab-dragging');

            if (!wasDragged) {
                // Clic normal (sin arrastre real):
                // Restaurar la esquina guardada con transición suave
                fab.style.transition = 'top 300ms cubic-bezier(0.25,0.8,0.25,1), left 300ms cubic-bezier(0.25,0.8,0.25,1), bottom 300ms cubic-bezier(0.25,0.8,0.25,1), right 300ms cubic-bezier(0.25,0.8,0.25,1)';
                applyCorner(fab, localStorage.getItem(STORAGE_KEY) || 'bottom-right');
                setTimeout(function () { fab.style.transition = ''; }, 320);
                return;
            }

            // Arrastre real: cerrar menú y deslizar suavemente a la esquina más cercana
            fab.classList.remove('active');
            fab.style.transition = 'top 350ms cubic-bezier(0.25,0.8,0.25,1), left 350ms cubic-bezier(0.25,0.8,0.25,1), bottom 350ms cubic-bezier(0.25,0.8,0.25,1), right 350ms cubic-bezier(0.25,0.8,0.25,1)';
            const corner = getCornerFromCenter(fab);
            applyCorner(fab, corner);
            localStorage.setItem(STORAGE_KEY, corner);
            setTimeout(function () { fab.style.transition = ''; }, 370);
        }

        // ── Prevenir que un arrastre active toggleFab ─────────────
        btn.addEventListener('click', function (e) {
            if (wasDragged) {
                e.stopImmediatePropagation();
                e.preventDefault();
                wasDragged = false;
            }
        }, true); // captura antes que el onclick inline

        // ── Registrar eventos ─────────────────────────────────────
        btn.addEventListener('mousedown',  onDragStart);
        btn.addEventListener('touchstart', onDragStart, { passive: false });
        document.addEventListener('mousemove',  onDragMove);
        document.addEventListener('touchmove',  onDragMove, { passive: false });
        document.addEventListener('mouseup',    onDragEnd);
        document.addEventListener('touchend',   onDragEnd);
    }

    document.addEventListener('DOMContentLoaded', initDraggableFab);
})();

// =====================================================
// CÁMARA PREMIUM — Módulo Vacaciones / Faltas / Subsidios
// =====================================================

(function () {
    let vacStream       = null;
    let vacVideoTrack   = null;
    let vacTorchActivo  = false;
    let vacFocusTimer   = null;
    let vacModalCamara  = null;
    // ID del formulario que activó la cámara ('formNuevoSubsidio' | 'formNuevaVacacion' | 'formNuevaFalta')
    let vacFormActivo   = null;

    // Map: formId → { inputId, previewId, previewImgId }
    const VAC_FOTO_MAP = {
        formNuevoSubsidio : { inputId: 'subsidio_foto', previewId: 'subsidio_preview', previewImgId: 'subsidio_preview_img' },
        formNuevaVacacion : { inputId: 'nueva_foto',    previewId: 'vacacion_preview', previewImgId: 'vacacion_preview_img' },
        formNuevaFalta    : { inputId: 'falta_foto',    previewId: 'falta_preview',    previewImgId: 'falta_preview_img'    }
    };

    // Inicializar modal Bootstrap cuando el DOM esté listo
    document.addEventListener('DOMContentLoaded', function () {
        const el = document.getElementById('vacModalCamara');
        if (el) {
            vacModalCamara = new bootstrap.Modal(el);
            // Elevar backdrop cuando el modal de cámara se muestra (aparece encima de otros modales)
            el.addEventListener('show.bs.modal', function () {
                setTimeout(function () {
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    if (backdrops.length > 0) {
                        backdrops[backdrops.length - 1].style.zIndex = '1079';
                    }
                }, 10);
            });
        }
    });

    // ── Abrir cámara ─────────────────────────────────────────────────────────
    window.vacAbrirCamara = function (formId) {
        vacFormActivo = formId;
        const video = document.getElementById('vac-video');

        const constraints = {
            audio: false,
            video: {
                facingMode: { ideal: 'environment' },
                width:  { ideal: 3840 },
                height: { ideal: 2160 },
                focusMode: { ideal: 'continuous' }
            }
        };

        navigator.mediaDevices.getUserMedia(constraints)
            .then(function (s) {
                vacStream     = s;
                vacVideoTrack = s.getVideoTracks()[0];
                video.srcObject = s;

                if (vacModalCamara) vacModalCamara.show();

                video.onloadedmetadata = function () {
                    vacInicializarControles();
                };

                // Enfoque táctil
                const vp = document.getElementById('vac-camera-viewport');
                if (vp) vp.addEventListener('click', vacEnfocarEnPunto);
            })
            .catch(function (err) {
                console.error('Error al acceder a la cámara:', err);
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Cámara', 'No se pudo acceder a la cámara. Verifica los permisos.', 'error');
                } else {
                    alert('No se pudo acceder a la cámara. Verifica los permisos.');
                }
            });
    };

    // ── Inicializar controles tras metadata ───────────────────────────────────
    function vacInicializarControles() {
        if (!vacVideoTrack) return;
        const caps = vacVideoTrack.getCapabilities ? vacVideoTrack.getCapabilities() : {};

        // Linterna
        const btnTorch       = document.getElementById('vac-btnTorch');
        const btnPlaceholder = document.getElementById('vac-btnTorchPlaceholder');
        if (caps.torch && btnTorch && btnPlaceholder) {
            btnTorch.style.display       = 'flex';
            btnPlaceholder.style.display = 'none';
        }

        // Enfoque continuo
        const focusStatus = document.getElementById('vac-cam-focus-status');
        if (caps.focusMode && caps.focusMode.includes('continuous')) {
            vacVideoTrack.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }).catch(() => {});
            if (focusStatus) {
                focusStatus.textContent = 'CONTINUO';
                focusStatus.className   = 'badge bg-success';
            }
        }

        vacMostrarFocusToast('Toca la pantalla para enfocar', 2000);
    }

    // ── Enfoque por toque ─────────────────────────────────────────────────────
    function vacEnfocarEnPunto(e) {
        const vp   = document.getElementById('vac-camera-viewport');
        const ring = document.getElementById('vac-focus-ring');
        if (!vp || !ring) return;

        const rect = vp.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        ring.style.left = x + 'px';
        ring.style.top  = y + 'px';
        ring.classList.remove('active', 'locked');
        void ring.offsetWidth; // reflow
        ring.classList.add('active');

        const focusStatus = document.getElementById('vac-cam-focus-status');
        if (focusStatus) {
            focusStatus.textContent = 'ENFOCANDO...';
            focusStatus.className   = 'badge bg-warning text-dark';
        }

        if (vacVideoTrack) {
            const xRatio = x / rect.width;
            const yRatio = y / rect.height;

            vacVideoTrack.applyConstraints({
                advanced: [{ pointsOfInterest: [{ x: xRatio, y: yRatio }], focusMode: 'single-shot' }]
            }).then(function () {
                ring.classList.add('locked');
                if (focusStatus) { focusStatus.textContent = 'ENFOCADO'; focusStatus.className = 'badge bg-success'; }
                vacMostrarFocusToast('✓ Enfocado', 1500);
                setTimeout(function () {
                    if (vacVideoTrack) vacVideoTrack.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }).catch(() => {});
                    if (focusStatus) focusStatus.textContent = 'CONTINUO';
                }, 2000);
            }).catch(function () {
                ring.classList.add('locked');
                vacMostrarFocusToast('Enfoque ajustado', 1200);
                if (focusStatus) { focusStatus.textContent = 'AUTO'; focusStatus.className = 'badge bg-secondary'; }
            });
        }

        setTimeout(function () { ring.classList.remove('active', 'locked'); }, 2500);
    }

    // ── Toast de enfoque ──────────────────────────────────────────────────────
    function vacMostrarFocusToast(msg, duracion) {
        const toast = document.getElementById('vac-focus-toast');
        if (!toast) return;
        if (vacFocusTimer) clearTimeout(vacFocusTimer);
        toast.textContent = msg;
        toast.style.opacity = '1';
        vacFocusTimer = setTimeout(function () { toast.style.opacity = '0'; }, duracion || 1500);
    }

    // ── Linterna ──────────────────────────────────────────────────────────────
    window.vacToggleLinterna = function () {
        if (!vacVideoTrack) return;
        vacTorchActivo = !vacTorchActivo;
        vacVideoTrack.applyConstraints({ advanced: [{ torch: vacTorchActivo }] })
            .then(function () {
                const btn = document.getElementById('vac-btnTorch');
                if (btn) btn.classList.toggle('on', vacTorchActivo);
            })
            .catch(function () {
                vacTorchActivo = false;
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Linterna', 'Este dispositivo no soporta linterna.', 'info');
                }
            });
    };

    // ── Cerrar cámara ─────────────────────────────────────────────────────────
    window.vacCerrarCamara = function () {
        if (vacTorchActivo && vacVideoTrack) {
            vacVideoTrack.applyConstraints({ advanced: [{ torch: false }] }).catch(() => {});
            vacTorchActivo = false;
        }
        if (vacStream) {
            vacStream.getTracks().forEach(function (t) { t.stop(); });
            vacStream     = null;
            vacVideoTrack = null;
        }

        // Limpiar listener de enfoque táctil clonando el nodo
        const vp = document.getElementById('vac-camera-viewport');
        if (vp) vp.replaceWith(vp.cloneNode(true));

        if (vacModalCamara) vacModalCamara.hide();

        // Resetear controles
        const btnTorch       = document.getElementById('vac-btnTorch');
        const btnPlaceholder = document.getElementById('vac-btnTorchPlaceholder');
        const focusStatus    = document.getElementById('vac-cam-focus-status');
        const ring           = document.getElementById('vac-focus-ring');
        if (btnTorch)       btnTorch.style.display = 'none';
        if (btnPlaceholder) btnPlaceholder.style.display = 'block';
        if (focusStatus)    { focusStatus.textContent = 'AUTO'; focusStatus.className = 'badge bg-secondary'; }
        if (ring)           ring.className = '';
    };

    // ── Capturar foto ─────────────────────────────────────────────────────────
    window.vacCapturarFoto = function () {
        const video  = document.getElementById('vac-video');
        const canvas = document.getElementById('vac-canvas');
        if (!video || !canvas) return;

        const ctx = canvas.getContext('2d');
        canvas.width  = video.videoWidth  || video.clientWidth;
        canvas.height = video.videoHeight || video.clientHeight;
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        canvas.toBlob(function (blob) {
            const file = new File([blob], 'evidencia_camara.jpg', { type: 'image/jpeg' });
            vacCerrarCamara();
            vacAsignarFotoAlForm(file);
        }, 'image/jpeg', 0.92);
    };

    // ── Asignar el blob capturado al input[file] del formulario activo ─────────
    function vacAsignarFotoAlForm(file) {
        const cfg = VAC_FOTO_MAP[vacFormActivo];
        if (!cfg) return;

        const input = document.getElementById(cfg.inputId);
        if (input) {
            // Reemplazar el FileList del input con el archivo capturado
            const dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
        }

        // Mostrar preview
        const preview    = document.getElementById(cfg.previewId);
        const previewImg = document.getElementById(cfg.previewImgId);
        if (preview && previewImg) {
            previewImg.src        = URL.createObjectURL(file);
            preview.style.display = 'block';
        }
    }

    // ── Eliminar preview y limpiar input ──────────────────────────────────────
    window.vacEliminarPreview = function (formId) {
        const cfg = VAC_FOTO_MAP[formId];
        if (!cfg) return;

        const input = document.getElementById(cfg.inputId);
        if (input) input.value = '';

        const preview = document.getElementById(cfg.previewId);
        if (preview) preview.style.display = 'none';

        const previewImg = document.getElementById(cfg.previewImgId);
        if (previewImg) previewImg.src = '';
    };

})();
