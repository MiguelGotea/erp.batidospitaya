// =====================================================
// JavaScript para Módulo de Feriados V2 (Solicitudes)
// erp.batidospitaya - Operaciones / Recursos Humanos
// =====================================================

// Autocompletado de colaboradores para el buscador general
const operariosData = window.CONFIG_FERIADOS ? window.CONFIG_FERIADOS.operariosData : [];

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
                div.className = 'sugerencia-item';
                div.textContent = op.nombre;
                div.addEventListener('click', function () {
                    operarioInput.value = op.nombre;
                    operarioIdInput.value = op.id;
                    sugerenciasDiv.style.display = 'none';
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
// CARGAR OPERARIOS DINÁMICAMENTE POR SUCURSAL (MODAL)
// =====================================================
function cargarOperariosSucursal(codSucursal, selectId, fechaRef = '') {
    const selectOperario = document.getElementById(selectId);
    if (!selectOperario) return;

    if (!codSucursal) {
        selectOperario.innerHTML = '<option value="">Seleccione un colaborador</option>';
        return;
    }

    selectOperario.innerHTML = '<option value="">⏳ Cargando colaboradores...</option>';

    let url = `ajax/feriados_v2_ajax.php?action=obtener_operarios&sucursal=${codSucursal}`;
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
                    options += `<option value="${operario.CodOperario}">${operario.nombre_completo}</option>`;
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
// CARGAR FERIADOS DISPONIBLES POR SUCURSAL (MODAL)
// =====================================================
function cargarFeriadosSucursal(codSucursal) {
    const selectFecha = document.getElementById('solicitud_fecha');
    const selectOperario = document.getElementById('solicitud_operario');
    if (!selectFecha) return;

    if (!codSucursal) {
        selectFecha.innerHTML = '<option value="">Seleccione primero una sucursal...</option>';
        if (selectOperario) selectOperario.innerHTML = '<option value="">Seleccione un colaborador</option>';
        return;
    }

    selectFecha.innerHTML = '<option value="">⏳ Cargando feriados...</option>';

    fetch(`ajax/feriados_v2_ajax.php?action=obtener_feriados_sucursal&sucursal=${encodeURIComponent(codSucursal)}`)
        .then(response => {
            if (!response.ok) throw new Error('Error en el servidor');
            return response.json();
        })
        .then(data => {
            if (data.error) throw new Error(data.error);

            if (data.length === 0) {
                selectFecha.innerHTML = '<option value="">No hay feriados registrados para esta sucursal</option>';
                if (selectOperario) selectOperario.innerHTML = '<option value="">Seleccione un colaborador</option>';
                return;
            }

            const months = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
            let options = '<option value="">Seleccione el feriado trabajado</option>';
            data.forEach(f => {
                const parts = f.fecha.split('-');
                const label = `${parts[2]}-${months[parseInt(parts[1],10)-1]}-${parts[0]} — ${f.nombre}`
                            + (f.tipo === 'Departamental' && f.departamento_nombre ? ` (${f.departamento_nombre})` : '');
                options += `<option value="${f.fecha}">${label}</option>`;
            });
            selectFecha.innerHTML = options;

            // Después de cargar feriados, recargar colaboradores con la primera fecha disponible
            recargarOperariosModal();
        })
        .catch(error => {
            console.error('Error al cargar feriados:', error);
            selectFecha.innerHTML = '<option value="">❌ Error al cargar feriados</option>';
        });
}

function recargarOperariosModal() {
    const sucSel = document.getElementById('solicitud_sucursal');
    const fechaSel = document.getElementById('solicitud_fecha');
    if (sucSel && fechaSel) {
        cargarOperariosSucursal(sucSel.value, 'solicitud_operario', fechaSel.value);
    }
}

// =====================================================
// MOSTRAR U OCULTAR MODALES
// =====================================================
function mostrarModalSolicitud() {
    const modal = document.getElementById('modalSolicitud');
    const form = document.getElementById('formNuevaSolicitud');
    if (form) form.reset();

    // Cargar feriados para la sucursal actualmente seleccionada en el modal
    const sucSel = document.getElementById('solicitud_sucursal');
    if (sucSel && sucSel.value) {
        cargarFeriadosSucursal(sucSel.value);
    } else {
        const fechaSel = document.getElementById('solicitud_fecha');
        if (fechaSel) fechaSel.innerHTML = '<option value="">Seleccione primero una sucursal...</option>';
    }

    if (modal) modal.style.display = 'flex';
}

function cerrarModalSolicitud() {
    const modal = document.getElementById('modalSolicitud');
    if (modal) modal.style.display = 'none';
}

function mostrarModalAprobacion(id, nombre, sucursal, fecha, horas, estado, observaciones) {
    document.getElementById('aprobacion_id').value = id;
    document.getElementById('aprobacion_nombre').textContent = nombre;
    document.getElementById('aprobacion_sucursal').textContent = sucursal;

    // Formatear fecha local
    const fLocal = new Date(fecha + 'T00:00:00').toLocaleDateString('es-ES', {
        day: '2-digit', month: 'short', year: 'numeric'
    });
    document.getElementById('aprobacion_fecha').textContent = fLocal;
    document.getElementById('aprobacion_horas').textContent = parseFloat(horas).toFixed(2);
    
    const selectEstado = document.getElementById('aprobacion_estado');
    if (selectEstado) selectEstado.value = estado;

    const obsInput = document.getElementById('aprobacion_observaciones');
    if (obsInput) obsInput.value = observaciones || '';

    const modal = document.getElementById('modalAprobacion');
    if (modal) modal.style.display = 'flex';
}

function cerrarModalAprobacion() {
    const modal = document.getElementById('modalAprobacion');
    if (modal) modal.style.display = 'none';
}

// Cerrar modales haciendo clic fuera del contenido
window.addEventListener('click', function (e) {
    const modalSolicitud = document.getElementById('modalSolicitud');
    const modalAprobacion = document.getElementById('modalAprobacion');
    if (e.target === modalSolicitud) {
        cerrarModalSolicitud();
    }
    if (e.target === modalAprobacion) {
        cerrarModalAprobacion();
    }
});

// =====================================================
// FILTROS Y RECARGA
// =====================================================
function actualizarFiltros() {
    const sucursalEl = document.getElementById('sucursal');
    const operarioIdEl = document.getElementById('operario_id');
    const desdeEl = document.getElementById('desde');
    const hastaEl = document.getElementById('hasta');
    const estadoEl = document.getElementById('estado_filtro');

    const params = new URLSearchParams();
    if (sucursalEl && sucursalEl.value) params.append('sucursal', sucursalEl.value);
    if (operarioIdEl && operarioIdEl.value && operarioIdEl.value != '0') params.append('operario', operarioIdEl.value);
    if (desdeEl && desdeEl.value) params.append('desde', desdeEl.value);
    if (hastaEl && hastaEl.value) params.append('hasta', hastaEl.value);
    if (estadoEl && estadoEl.value) params.append('estado', estadoEl.value);

    window.location.href = 'feriados_v2.php?' + params.toString();
}

function limpiarFiltros() {
    window.location.href = 'feriados_v2.php';
}

// =====================================================
// PROCESAMIENTO AJAX
// =====================================================
document.addEventListener('DOMContentLoaded', function () {
    // Cambio de sucursal: recargar lista de feriados (que a su vez recarga colaboradores)
    const sucModal = document.getElementById('solicitud_sucursal');
    if (sucModal) sucModal.addEventListener('change', function () {
        cargarFeriadosSucursal(this.value);
    });

    // Cambio de feriado seleccionado: recargar colaboradores con esa fecha
    const fechaModal = document.getElementById('solicitud_fecha');
    if (fechaModal) fechaModal.addEventListener('change', recargarOperariosModal);

    // Formulario de Creación/Solicitud
    const formSolicitud = document.getElementById('formNuevaSolicitud');
    if (formSolicitud) {
        formSolicitud.addEventListener('submit', function (e) {
            e.preventDefault();

            const operario = document.getElementById('solicitud_operario').value;
            const fecha = document.getElementById('solicitud_fecha').value;
            const observaciones = document.getElementById('solicitud_observaciones').value.trim();

            if (!operario || !fecha) {
                alert('Debe seleccionar el colaborador y la fecha del feriado.');
                return false;
            }

            if (!confirm('¿Está seguro de registrar esta solicitud de feriado trabajado?')) {
                return false;
            }

            const submitBtn = formSolicitud.querySelector('button[type="submit"]');
            const originalText = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
                submitBtn.disabled = true;
            }

            const formData = new FormData(formSolicitud);

            fetch('ajax/feriados_v2_ajax.php?action=guardar_solicitud', {
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
                alert('Error de conexión al guardar la solicitud. Intente nuevamente.');
                if (submitBtn) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            });

            return false;
        });
    }

    // Formulario de Aprobación
    const formAprobacion = document.getElementById('formAprobacionSolicitud');
    if (formAprobacion) {
        formAprobacion.addEventListener('submit', function (e) {
            e.preventDefault();

            const id = document.getElementById('aprobacion_id').value;
            const estado = document.getElementById('aprobacion_estado').value;
            const observaciones = document.getElementById('aprobacion_observaciones').value.trim();

            if (!confirm('¿Está seguro de actualizar esta solicitud?')) {
                return false;
            }

            const submitBtn = formAprobacion.querySelector('button[type="submit"]');
            const originalText = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
                submitBtn.disabled = true;
            }

            const params = new URLSearchParams({
                id: id,
                estado: estado,
                observaciones: observaciones
            });

            fetch('ajax/feriados_v2_ajax.php?action=editar_aprobar', {
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

    fetch('ajax/feriados_v2_ajax.php?action=eliminar_rechazar', {
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
