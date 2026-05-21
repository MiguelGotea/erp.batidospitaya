// =====================================================
// JavaScript para Módulo Híbrido de Vacaciones y Faltas
// erp.batidospitaya - Recursos Humanos
// =====================================================

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

function mostrarModalEditarAprobar(id, nombre, sucursal, fecha, tipoFalta, observaciones, observacionesRrhh, fotoPath) {
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
        const dias = calcularDiasLaborables(fechaInicio, fechaFin);
        let textoConfirmacion = `¿Está seguro de registrar este rango de ${dias} días de ausencias?`;
        if (categoriaFalta === 'vacaciones') {
            textoConfirmacion = `¿Está seguro de registrar este rango de ${dias} días de vacaciones?`;
        } else if (categoriaFalta === 'subsidio') {
            textoConfirmacion = `¿Está seguro de registrar este rango de ${dias} días de subsidio?`;
        } else if (categoriaFalta === 'falta_permiso') {
            textoConfirmacion = `¿Está seguro de registrar este rango de ${dias} días de falta o permiso?`;
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

            const submitBtn = formEditar.querySelector('button[type="submit"]') || document.querySelector(`button[type="submit"][form="formEditarFalta"]`);
            const originalText = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
                submitBtn.disabled = true;
            }

            const params = new URLSearchParams({
                id: id,
                tipo_falta: tipoFalta,
                observaciones_rrhh: observacionesRrhh
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
