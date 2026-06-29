// =============================================
// BÚSQUEDA DE OPERARIOS (autocomplete)
// =============================================

function buscarOperarios(texto) {
    if (!texto) {
        return operariosData;
    }
    return operariosData.filter(op =>
        op.nombre.toLowerCase().includes(texto.toLowerCase())
    );
}

// Manejar el input de operario
const operarioInput = document.getElementById('operario');
const operarioIdInput = document.getElementById('operario_id');
const sugerenciasDiv = document.getElementById('operarios-sugerencias');

if (operarioInput) {
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
                div.addEventListener('mouseover', function () { this.style.backgroundColor = '#f5f5f5'; });
                div.addEventListener('mouseout', function () { this.style.backgroundColor = 'white'; });
                sugerenciasDiv.appendChild(div);
            });
            sugerenciasDiv.style.display = 'block';
        } else {
            sugerenciasDiv.style.display = 'none';
        }
    });
}

document.addEventListener('click', function (e) {
    if (e.target !== operarioInput) {
        sugerenciasDiv.style.display = 'none';
    }
});

if (operarioInput) {
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

// =============================================
// FILTROS
// =============================================

function actualizarFiltros() {
    const sucursal = document.getElementById('sucursal').value;
    const desde = document.getElementById('desde').value;
    const hasta = document.getElementById('hasta').value;
    const operario = document.getElementById('operario_id').value;

    if (!desde || !hasta) {
        alert('Por favor seleccione ambas fechas');
        return;
    }
    if (new Date(desde) > new Date(hasta)) {
        alert('La fecha "Desde" no puede ser mayor que la fecha "Hasta"');
        return;
    }

    const params = new URLSearchParams();
    if (sucursal !== undefined) {
        params.append('sucursal', sucursal);
    }
    params.append('desde', desde);
    params.append('hasta', hasta);
    if (operario > 0) {
        params.append('operario', operario);
    }
    window.location.href = 'tardanzas_manual.php?' + params.toString();
}

// =============================================
// MODAL NUEVA TARDANZA
// =============================================

function mostrarModalNuevaTardanza() {
    const hoy = new Date();
    const ayer = new Date(hoy);
    ayer.setDate(hoy.getDate() - 1);
    
    // Formato local YYYY-MM-DD para evitar desfases de zona horaria (UTC)
    const yyyy = ayer.getFullYear();
    const mm = String(ayer.getMonth() + 1).padStart(2, '0');
    const dd = String(ayer.getDate()).padStart(2, '0');
    const fechaAyerStr = `${yyyy}-${mm}-${dd}`;
    
    const inputFecha = document.getElementById('nueva_fecha');
    const selectOperario = document.getElementById('nueva_operario');
    const selectSucursal = document.getElementById('nueva_sucursal');

    // Establecer fecha y su límite máximo
    inputFecha.value = fechaAyerStr;
    inputFecha.max = fechaAyerStr;

    // Resetear selector de operarios
    selectOperario.innerHTML = '<option value="">Seleccione un colaborador</option>';

    // Obtener sucursal seleccionada por defecto (la primera o la preseleccionada por PHP)
    const sucursalActual = selectSucursal.value;

    if (sucursalActual && fechaAyerStr) {
        // Forzamos la carga inmediata de operarios para esa sucursal y fecha
        cargarOperariosSucursal(sucursalActual, fechaAyerStr);
    } else if (!sucursalActual) {
        selectOperario.disabled = true;
        selectOperario.innerHTML = '<option value="">Primero seleccione una sucursal</option>';
    }

    // Mostrar el modal
    document.getElementById('modalNuevaTardanza').style.display = 'flex';
}

function cargarOperariosSucursal(codSucursal, fechaTardanza) {
    const selectOperario = document.getElementById('nueva_operario');
    const mensajeAdvertencia = document.getElementById('mensaje-advertencia-contrato-tardanza');

    if (!codSucursal) {
        selectOperario.innerHTML = '<option value="">Primero seleccione una sucursal</option>';
        selectOperario.disabled = true;
        return;
    }
    if (!fechaTardanza) {
        selectOperario.innerHTML = '<option value="">Primero seleccione una fecha</option>';
        selectOperario.disabled = true;
        return;
    }

    selectOperario.innerHTML = `<option value="">⏳ Cargando operarios para ${fechaTardanza}...</option>`;
    selectOperario.disabled = true;

    let url = `ajax/tardanzas_manual_obtener_operarios.php?sucursal=${codSucursal}&fecha_tardanza=${fechaTardanza}`;

    fetch(url)
        .then(response => {
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return response.json();
        })
        .then(data => {
            selectOperario.disabled = false;
            if (!data || data.length === 0) {
                selectOperario.innerHTML = '<option value="">No hay operarios activos para esta fecha</option>';
                if (mensajeAdvertencia) mensajeAdvertencia.style.display = 'none';
                return;
            }

            let options = '<option value="">Seleccione un colaborador</option>';
            let hayOperariosSinContrato = false;

            data.forEach(operario => {
                const nombre = operario.Nombre || '';
                const nombre2 = operario.Nombre2 || '';
                const apellido = operario.Apellido || '';
                const apellido2 = operario.Apellido2 || '';
                const nombreCompleto = `${nombre} ${nombre2} ${apellido} ${apellido2}`.trim();

                if (!operario.tiene_contrato) {
                    hayOperariosSinContrato = true;
                    options += `<option value="${operario.CodOperario}" data-sin-contrato="true">⚠️ ${nombreCompleto} (Sin contrato)</option>`;
                } else {
                    options += `<option value="${operario.CodOperario}">${nombreCompleto}</option>`;
                }
            });

            selectOperario.innerHTML = options;

            if (hayOperariosSinContrato && mensajeAdvertencia) {
                mensajeAdvertencia.style.display = 'block';
                mensajeAdvertencia.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Algunos colaboradores no tienen contrato registrado. Contactar con RH.';
            } else if (mensajeAdvertencia) {
                mensajeAdvertencia.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error cargando operarios:', error);
            selectOperario.disabled = false;
            selectOperario.innerHTML = '<option value="">❌ Error al cargar. Intente de nuevo</option>';
            if (mensajeAdvertencia) mensajeAdvertencia.style.display = 'none';
        });
}

document.getElementById('nueva_foto').addEventListener('change', function (e) {
    const preview = document.getElementById('nueva_foto_preview');
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function (e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        preview.style.display = 'none';
    }
});



// =============================================
// MODAL EDITAR TARDANZA
// =============================================

function mostrarModalEditarTardanza(id, codOperario, nombre, sucursal, fecha, tipoJustificacion, estado, observaciones, fotoPath) {
    document.getElementById('editar_id').value = id;
    document.getElementById('editar_cod_operario').value = codOperario;
    document.getElementById('editar_nombre').textContent = nombre;
    document.getElementById('editar_sucursal').textContent = sucursal;
    document.getElementById('editar_fecha').textContent = formatearFechaLocal(fecha);
    document.getElementById('editar_tipo_justificacion').textContent = tipoJustificacion.replace('_', ' ');
    document.getElementById('editar_estado').value = estado;
    document.getElementById('editar_observaciones').value = observaciones || '';

    const fotoPreview = document.getElementById('editar_foto_preview');
    const fotoLink = document.getElementById('editar_foto_link');
    const fotoContainer = document.getElementById('foto-container');

    if (fotoPath) {
        const fotoUrl = 'uploads/tardanzas/' + fotoPath;
        fotoPreview.src = fotoUrl;
        fotoPreview.style.display = 'block';
        fotoLink.href = fotoUrl;
        fotoLink.style.display = 'inline-block';
        fotoContainer.style.display = 'block';
    } else {
        fotoPreview.style.display = 'none';
        fotoLink.style.display = 'none';
        fotoContainer.style.display = 'none';
    }

    Promise.all([
        fetch('ajax/obtener_horario_programado.php?cod_operario=' + codOperario + '&fecha=' + fecha).then(r => r.json()),
        fetch('ajax/obtener_marcaciones.php?cod_operario=' + codOperario + '&fecha=' + fecha).then(r => r.json())
    ]).then(function (results) {
        var horario = results[0], marcaciones = results[1];
        document.getElementById('editar_entrada_programada').textContent = horario.hora_entrada ? formatoHoraAmPm(horario.hora_entrada) : 'No';
        document.getElementById('editar_salida_programada').textContent = horario.hora_salida ? formatoHoraAmPm(horario.hora_salida) : 'No';
        document.getElementById('editar_entrada_marcada').textContent = marcaciones.hora_ingreso ? formatoHoraAmPm(marcaciones.hora_ingreso) : 'No marco';
        document.getElementById('editar_salida_marcada').textContent = marcaciones.hora_salida ? formatoHoraAmPm(marcaciones.hora_salida) : 'No marco';
    }).catch(function (error) {
        console.error('Error al obtener datos:', error);
        ['editar_entrada_programada', 'editar_salida_programada', 'editar_entrada_marcada', 'editar_salida_marcada']
            .forEach(function (eid) { document.getElementById(eid).textContent = 'Error'; });
    });

    var urlParams = new URLSearchParams(window.location.search);
    document.querySelector('#formEditarTardanza input[name="sucursal"]').value = urlParams.get('sucursal') || '';
    document.querySelector('#formEditarTardanza input[name="desde"]').value = urlParams.get('desde') || '';
    document.querySelector('#formEditarTardanza input[name="hasta"]').value = urlParams.get('hasta') || '';
    document.getElementById('modalEditarTardanza').style.display = 'flex';
    document.querySelector('#modalEditarTardanza .modal-content').scrollTop = 0;
}

function cerrarModal() {
    document.getElementById('modalNuevaTardanza').style.display = 'none';
    document.getElementById('modalEditarTardanza').style.display = 'none';
}

// =============================================
// EVENTOS: botones, sucursal, fecha, submit
// =============================================

var _elNuevaOperario = document.getElementById('nueva_operario');
var _elNuevaFecha = document.getElementById('nueva_fecha');
var _elNuevaSucursal = document.getElementById('nueva_sucursal');
var _elBtnConsultarNew = document.getElementById('btnConsultarMarcacionesNueva');
var _elBtnConsultarEdt = document.getElementById('btnConsultarMarcacionesEditar');
var _elFormNueva = document.getElementById('formNuevaTardanza');

if (_elNuevaOperario) {
    _elNuevaOperario.addEventListener('change', function () {
        var btnConsultar = document.getElementById('btnConsultarMarcacionesNueva');
        if (btnConsultar) {
            btnConsultar.disabled = !this.value || !document.getElementById('nueva_fecha').value;
        }
    });
}

if (_elNuevaFecha) {
    _elNuevaFecha.addEventListener('change', function () {
        var btnConsultar = document.getElementById('btnConsultarMarcacionesNueva');
        if (btnConsultar) {
            btnConsultar.disabled = !this.value || !document.getElementById('nueva_operario').value;
        }
        var sucursalSelect = document.getElementById('nueva_sucursal');
        if (sucursalSelect && sucursalSelect.value && this.value) {
            cargarOperariosSucursal(sucursalSelect.value, this.value);
        }
    });
}

if (_elBtnConsultarNew) {
    _elBtnConsultarNew.addEventListener('click', function () {
        var codOperario = document.getElementById('nueva_operario').value;
        var fecha = document.getElementById('nueva_fecha').value;
        var nombre = document.getElementById('nueva_operario').options[document.getElementById('nueva_operario').selectedIndex].text;
        var sucursal = document.getElementById('nueva_sucursal').options[document.getElementById('nueva_sucursal').selectedIndex].text;
        if (!codOperario || !fecha) {
            alert('Seleccione un colaborador y una fecha para consultar las marcaciones');
            return;
        }
        mostrarModalConsultarMarcaciones(codOperario, nombre, sucursal, fecha, 0);
    });
}

if (_elBtnConsultarEdt) {
    _elBtnConsultarEdt.addEventListener('click', function () {
        var nombre = document.getElementById('editar_nombre').textContent;
        var sucursal = document.getElementById('editar_sucursal').textContent;
        var fecha = document.getElementById('editar_fecha').textContent;
        var minutos = parseInt(document.getElementById('editar_minutos').textContent);
        var codOperario = document.getElementById('editar_cod_operario').value;
        mostrarModalConsultarMarcaciones(codOperario, nombre, sucursal, fecha, minutos);
    });
}

if (_elNuevaSucursal) {
    _elNuevaSucursal.addEventListener('change', function () {
        var fechaInput = document.getElementById('nueva_fecha');
        if (fechaInput && fechaInput.value) {
            cargarOperariosSucursal(this.value, fechaInput.value);
        } else {
            var selectOp = document.getElementById('nueva_operario');
            if (selectOp) selectOp.innerHTML = '<option value="">Primero seleccione una fecha</option>';
        }
    });
}

if (_elFormNueva) {
    var formNuevaEnviado = false;
    _elFormNueva.addEventListener('submit', function (e) {
        if (formNuevaEnviado) {
            e.preventDefault();
            return false;
        }
        var fotoInput = document.getElementById('nueva_foto');
        if (!fotoInput.files || fotoInput.files.length === 0) {
            alert('Debe seleccionar una foto como evidencia');
            e.preventDefault();
            return false;
        }
        var file = fotoInput.files[0];
        if (!file.type.match('image.*')) {
            alert('El archivo debe ser una imagen');
            e.preventDefault();
            return false;
        }
        var selectOperario = document.getElementById('nueva_operario');
        var optionSeleccionada = selectOperario.options[selectOperario.selectedIndex];
        if (optionSeleccionada && optionSeleccionada.dataset.sinContrato === 'true') {
            e.preventDefault();
            alert('Este colaborador no tiene registro de contrato. Por favor contactar con el área de RH antes de registrar una tardanza.');
            return false;
        }

        formNuevaEnviado = true;
        var submitBtn = _elFormNueva.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Registrando...';
        }
        return true;
    });
}

var _elFormEditar = document.getElementById('formEditarTardanza');
if (_elFormEditar) {
    var formEditarEnviado = false;
    _elFormEditar.addEventListener('submit', function (e) {
        if (formEditarEnviado) {
            e.preventDefault();
            return false;
        }
        formEditarEnviado = true;
        var submitBtn = _elFormEditar.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Guardando...';
        }
        return true;
    });
}

// =============================================
// MODAL CONSULTAR MARCACIONES
// =============================================

function mostrarModalConsultarMarcaciones(codOperario, nombre, sucursal, fechaTardanza, minutosTardanza) {
    document.getElementById('consulta_nombre').textContent = nombre;
    document.getElementById('consulta_sucursal').textContent = sucursal;
    document.getElementById('consulta_fecha_tardanza').textContent = formatoFechaCompleta(fechaTardanza);
    document.getElementById('consulta_minutos_tardanza').textContent = minutosTardanza + ' minutos';

    var debugInfo = 'Iniciando consulta para:\n- Colaborador: ' + codOperario + '\n- Fecha: ' + fechaTardanza + '\n';
    var fechaConsulta;
    try {
        if (fechaTardanza.match(/^\d{4}-\d{2}-\d{2}$/)) {
            fechaConsulta = fechaTardanza;
        } else {
            var fechaObj = new Date(fechaTardanza);
            if (isNaN(fechaObj.getTime())) throw new Error('Formato de fecha no reconocido');
            fechaConsulta = fechaObj.toISOString().split('T')[0];
        }
    } catch (e) {
        fechaConsulta = fechaTardanza;
        debugInfo += '- Error al formatear fecha: ' + e.message + '\n';
    }

    debugInfo += '- Fecha enviada al servidor: ' + fechaConsulta + '\n';
    document.getElementById('consulta_fecha_utilizada').textContent = formatoFechaCompleta(fechaConsulta);

    fetch('ajax/obtener_marcaciones.php?cod_operario=' + codOperario + '&fecha=' + fechaConsulta + '&debug=1')
        .then(function (response) { return response.json(); })
        .then(function (data) {
            debugInfo += 'Respuesta del servidor:\n' + JSON.stringify(data, null, 2) + '\n';

            function mostrarHoraConFecha(hora, elementoHora, elementoFecha, tipo) {
                var textoFecha = '(Consultado para ' + tipo + ' en fecha: ' + formatoFechaCompleta(fechaConsulta) + ')';
                document.getElementById(elementoHora).textContent = hora ? formatoHoraAmPm(hora) : 'No registrado';
                document.getElementById(elementoFecha).textContent = textoFecha;
            }

            mostrarHoraConFecha(data.hora_entrada_programada, 'consulta_entrada_programada', 'consulta_fecha_entrada_programada', 'entrada programada');
            mostrarHoraConFecha(data.hora_ingreso, 'consulta_entrada_marcada', 'consulta_fecha_entrada_marcada', 'entrada marcada');
            mostrarHoraConFecha(data.hora_salida_programada, 'consulta_salida_programada', 'consulta_fecha_salida_programada', 'salida programada');
            mostrarHoraConFecha(data.hora_salida, 'consulta_salida_marcada', 'consulta_fecha_salida_marcada', 'salida marcada');

            if (data.semana_horario) {
                debugInfo += 'Semana: ' + data.semana_horario.id + ' (' + data.semana_horario.fecha_inicio + ' a ' + data.semana_horario.fecha_fin + ')\n';
            }
            document.getElementById('consulta_debug_info').textContent = debugInfo;
            document.getElementById('modalConsultarMarcaciones').style.display = 'flex';
        })

        .catch(function (error) {
            console.error('Error al obtener marcaciones:', error);
            debugInfo += 'Error en la consulta: ' + error.message + '\n';
            document.getElementById('consulta_debug_info').textContent = debugInfo;
            document.getElementById('modalConsultarMarcaciones').style.display = 'flex';
        });
}

function cerrarModalConsultar() {
    document.getElementById('modalConsultarMarcaciones').style.display = 'none';
}

// =============================================
// UTILIDADES DE FECHA / HORA
// =============================================

function formatearFechaLocal(fechaStr) {
    var fecha = new Date(fechaStr + 'T00:00:00');
    var opciones = { day: '2-digit', month: 'short', year: '2-digit' };
    return fecha.toLocaleDateString('es-ES', opciones);
}

function formatoFechaCompleta(fechaStr) {
    try {
        var fecha = new Date(fechaStr + 'T00:00:00');
        var opciones = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', timeZone: 'UTC' };
        return fecha.toLocaleDateString('es-ES', opciones) + ' (' + fecha.toISOString().split('T')[0] + ')';
    } catch (e) {
        return fechaStr;
    }
}

function formatoHoraAmPm(hora) {
    if (!hora) return '-';
    return new Date('2000-01-01T' + hora).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
}

// =============================================
// VISOR DE FOTOS ESTANDARIZADO
// =============================================

function mostrarFotoAmpliadaDesdeTabla(fotoPath) {
    if (!fotoPath) { alert('No hay foto disponible'); return; }
    mostrarFotoAmpliada('uploads/tardanzas/' + fotoPath);
}

function mostrarFotoAmpliada(src) {
    const carouselInner = $('#carouselFotosInner');
    carouselInner.empty();
    
    const isHeic = src.toLowerCase().endsWith('.heic') || src.toLowerCase().endsWith('.heif');
    const imgId = 'evidencia-photo';
    
    carouselInner.append(`
        <div class="carousel-item active">
            <div class="d-flex justify-content-center align-items-center" style="min-height: 200px; background: #f8f9fa;">
                <img id="${imgId}" src="${src}" class="d-block w-100" alt="Evidencia" onerror="this.src='/core/assets/img/broken-image.png'" style="max-height: 500px; object-fit: contain;">
                <div id="loader-${imgId}" class="spinner-border text-primary position-absolute" role="status" style="display: none;">
                    <span class="visually-hidden">Cargando...</span>
                </div>
            </div>
        </div>
    `);

    if (isHeic) {
        const loader = document.getElementById(`loader-${imgId}`);
        if (loader) loader.style.display = 'block';
        
        fetch(src)
            .then(res => res.blob())
            .then(blob => heic2any({ 
                blob, 
                toType: "image/jpeg",
                quality: 0.6
            }))
            .then(conversionResult => {
                const url = URL.createObjectURL(Array.isArray(conversionResult) ? conversionResult[0] : conversionResult);
                document.getElementById(imgId).src = url;
                if (loader) loader.style.display = 'none';
            })
            .catch(e => {
                console.error("Error converting HEIC:", e);
                if (loader) loader.style.display = 'none';
            });
    }

    const modal = new bootstrap.Modal(document.getElementById('modalFotos'));
    modal.show();
}

function cerrarModalFoto() {
    const modalEl = document.getElementById('modalFotos');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
}

document.addEventListener('DOMContentLoaded', function () {
    const modalFotos = document.getElementById('modalFotos');
    if (modalFotos) {
        modalFotos.addEventListener('hidden.bs.modal', function () {
            $('#carouselFotosInner').empty();
        });
    }
});

window.addEventListener('click', function (event) {
    ['modalNuevaTardanza', 'modalEditarTardanza'].forEach(function (modalId) {
        var modal = document.getElementById(modalId);
        if (event.target === modal) cerrarModal();
    });
});

// =============================================
// DROPDOWN POSICIONAMIENTO
// =============================================

function ajustarPosicionDropdown() {
    var input = document.getElementById('operario');
    var dropdown = document.getElementById('operarios-sugerencias');
    if (input && dropdown) {
        var rect = input.getBoundingClientRect();
        dropdown.style.top = (rect.bottom + window.scrollY) + 'px';
        dropdown.style.left = rect.left + 'px';
        dropdown.style.width = rect.width + 'px';
    }
}

window.addEventListener('resize', function () { if (sugerenciasDiv.style.display === 'block') ajustarPosicionDropdown(); });
window.addEventListener('scroll', function () { if (sugerenciasDiv.style.display === 'block') ajustarPosicionDropdown(); });


// =============================================
// AJAX: ACTUALIZAR ESTADO / OBSERVACIONES
// =============================================

function actualizarEstado(id, nuevoEstado) {
    if (!confirm('¿Está seguro de ' + (nuevoEstado === 'Justificado' ? 'aprobar' : 'rechazar') + ' esta tardanza?')) {
        return;
    }
    var observaciones = document.getElementById('obs-edit-' + id).value;
    var actionsDiv = document.getElementById('actions-' + id);
    var originalHTML = actionsDiv.innerHTML;
    actionsDiv.innerHTML = '<div style="display:flex;align-items:center;gap:8px;"><i class="fas fa-spinner fa-spin"></i> Procesando...</div>';

    fetch('ajax/actualizar_estado_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ 'id': id, 'estado': nuevoEstado, 'observaciones': observaciones })
    })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data.success) {
                var badge = document.getElementById('status-badge-' + id);
                badge.textContent = nuevoEstado;
                badge.className = 'status-badge status-' + nuevoEstado.toLowerCase().replace(' ', '-');
                actualizarBotonesAccion(id, nuevoEstado);
                mostrarNotificacion('success', data.message);
            } else {
                actionsDiv.innerHTML = originalHTML;
                mostrarNotificacion('error', data.message);
            }
        })
        .catch(function (error) {
            console.error('Error:', error);
            actionsDiv.innerHTML = originalHTML;
            mostrarNotificacion('error', 'Error al actualizar el estado');
        });
}

function cambiarEstado(id, estadoActual) {
    var nuevoEstado = estadoActual === 'Justificado' ? 'No Válido' : 'Justificado';
    actualizarEstado(id, nuevoEstado);
}

function toggleEditObservaciones(id) {
    var displayDiv = document.getElementById('obs-display-' + id);
    var editTextarea = document.getElementById('obs-edit-' + id);
    var actionsDiv = document.getElementById('actions-' + id);
    var saveCancelDiv = document.getElementById('save-cancel-' + id);

    if (!editandoObservaciones[id]) {
        observacionesOriginales[id] = editTextarea.value;
    }
    displayDiv.style.display = 'none';
    editTextarea.style.display = 'block';
    actionsDiv.style.display = 'none';
    saveCancelDiv.style.display = 'flex';
    editandoObservaciones[id] = true;
    editTextarea.focus();
}

function guardarObservaciones(id) {
    var editTextarea = document.getElementById('obs-edit-' + id);
    var nuevasObservaciones = editTextarea.value.trim();
    var badge = document.getElementById('status-badge-' + id);
    var estadoActual = badge.textContent.trim();
    var saveCancelDiv = document.getElementById('save-cancel-' + id);
    var originalHTML = saveCancelDiv.innerHTML;
    saveCancelDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    fetch('ajax/actualizar_estado_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ 'id': id, 'estado': estadoActual, 'observaciones': nuevasObservaciones })
    })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data.success) {
                var displayDiv = document.getElementById('obs-display-' + id);
                displayDiv.innerHTML = nuevasObservaciones
                    ? nuevasObservaciones.replace(/\n/g, '<br>')
                    : '<span class="text-muted">Sin observaciones</span>';
                finalizarEdicionObservaciones(id);
                mostrarNotificacion('success', 'Observaciones actualizadas correctamente');
            } else {
                saveCancelDiv.innerHTML = originalHTML;
                mostrarNotificacion('error', data.message);
            }
        })
        .catch(function (error) {
            console.error('Error:', error);
            saveCancelDiv.innerHTML = originalHTML;
            mostrarNotificacion('error', 'Error al guardar las observaciones');
        });
}

function cancelarEditObservaciones(id) {
    var editTextarea = document.getElementById('obs-edit-' + id);
    if (observacionesOriginales[id] !== undefined) {
        editTextarea.value = observacionesOriginales[id];
    }
    finalizarEdicionObservaciones(id);
}

function finalizarEdicionObservaciones(id) {
    var displayDiv = document.getElementById('obs-display-' + id);
    var editTextarea = document.getElementById('obs-edit-' + id);
    var actionsDiv = document.getElementById('actions-' + id);
    var saveCancelDiv = document.getElementById('save-cancel-' + id);
    displayDiv.style.display = 'block';
    editTextarea.style.display = 'none';
    actionsDiv.style.display = 'flex';
    saveCancelDiv.style.display = 'none';
    delete editandoObservaciones[id];
    delete observacionesOriginales[id];
}

function actualizarBotonesAccion(id, nuevoEstado) {
    var actionsDiv = document.getElementById('actions-' + id);
    if (nuevoEstado === 'Pendiente') {
        actionsDiv.innerHTML =
            '<button type="button" class="btn-action btn-approve" onclick="actualizarEstado(' + id + ', \'Justificado\')" title="Aprobar"><i class="fas fa-check"></i></button>' +
            '<button type="button" class="btn-action btn-reject" onclick="actualizarEstado(' + id + ', \'No Válido\')" title="Rechazar"><i class="fas fa-times"></i></button>' +
            '<button type="button" class="btn-action btn-edit" onclick="toggleEditObservaciones(' + id + ')" title="Editar observaciones"><i class="fas fa-edit"></i></button>';
    } else {
        actionsDiv.innerHTML =
            '<button type="button" class="btn-action btn-change" onclick="cambiarEstado(' + id + ', \'' + nuevoEstado + '\')" title="Cambiar estado"><i class="fas fa-exchange-alt"></i></button>' +
            '<button type="button" class="btn-action btn-edit" onclick="toggleEditObservaciones(' + id + ')" title="Editar observaciones"><i class="fas fa-edit"></i></button>';
    }
}

function mostrarNotificacion(tipo, mensaje) {
    var notification = document.createElement('div');
    notification.className = 'notification notification-' + tipo;
    notification.innerHTML =
        '<i class="fas fa-' + (tipo === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i>' +
        '<span>' + mensaje + '</span>';
    notification.style.cssText =
        'position:fixed;top:20px;right:20px;padding:15px 20px;border-radius:8px;color:white;font-weight:bold;' +
        'display:flex;align-items:center;gap:10px;z-index:10000;animation:slideIn 0.3s ease;' +
        'box-shadow:0 4px 12px rgba(0,0,0,0.15);' +
        'background:' + (tipo === 'success'
            ? 'linear-gradient(135deg,#28a745 0%,#20c997 100%)'
            : 'linear-gradient(135deg,#dc3545 0%,#e83e8c 100%)') + ';';
    document.body.appendChild(notification);
    setTimeout(function () {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(function () { notification.remove(); }, 300);
    }, 3000);
}

// Animaciones de notificación
(function () {
    var style = document.createElement('style');
    style.textContent =
        '@keyframes slideIn{from{transform:translateX(400px);opacity:0}to{transform:translateX(0);opacity:1}}' +
        '@keyframes slideOut{from{transform:translateX(0);opacity:1}to{transform:translateX(400px);opacity:0}}';
    document.head.appendChild(style);
})();

// =============================================
// REGISTRO RÁPIDO DE TARDANZA NO REPORTADA
// =============================================

function registrarTardanzaNoReportada(codOperario, fecha, sucursalNombre, minutos, codSucursal) {
    if (!codSucursal) {
        alert('Error: No se pudo determinar la sucursal. Intente seleccionar una sucursal en los filtros.');
        return;
    }
    if (confirm('¿Desea registrar la tardanza del colaborador en fecha ' + formatearFechaLocal(fecha) + '?\n\nTardanza: ' + minutos + ' minutos\nSucursal: ' + sucursalNombre)) {
        mostrarModalRegistroRapido(codOperario, fecha, codSucursal, minutos, sucursalNombre);
    }
}

function mostrarModalRegistroRapido(codOperario, fecha, codSucursal, minutos, sucursalNombre) {
    var nombreColaborador = '';
    var fila = document.querySelector('#tardanza-nr-' + codOperario + '-' + fecha + ' td:first-child');
    if (fila) nombreColaborador = fila.textContent;

    var modalHTML =
        '<div class="modal" id="modalRegistroRapido">' +
        '<div class="modal-content" style="max-width:500px;">' +
        '<div class="modal-header">' +
        '<h2 class="modal-title">Registrar Tardanza</h2>' +
        '<button class="modal-close" onclick="cerrarModalRegistroRapido()">&times;</button>' +
        '</div>' +
        '<form id="formRegistroRapido" method="post" enctype="multipart/form-data">' +
        '<input type="hidden" name="registrar_tardanza" value="1">' +
        '<input type="hidden" name="cod_operario" value="' + codOperario + '">' +
        '<input type="hidden" name="fecha_tardanza" value="' + fecha + '">' +
        '<input type="hidden" name="cod_sucursal" value="' + codSucursal + '">' +
        '<div class="modal-body">' +
        '<div class="info-group"><span class="info-label">Colaborador:</span><span class="info-value">' + nombreColaborador + '</span></div>' +
        '<div class="info-group"><span class="info-label">Sucursal:</span><span class="info-value">' + sucursalNombre + '</span></div>' +
        '<div class="info-group"><span class="info-label">Fecha:</span><span class="info-value">' + formatearFechaLocal(fecha) + '</span></div>' +
        '<div class="info-group"><span class="info-label">Minutos de tardanza:</span><span class="info-value">' + minutos + ' minutos</span></div>' +
        '<div class="form-group"><label for="rapido_tipo" class="form-label">Tipo de Justificación:</label>' +
        '<select id="rapido_tipo" name="tipo_justificacion" class="form-select" required>' +
        '<option value="llave">Problema con llave</option>' +
        '<option value="error_sistema">Error del sistema</option>' +
        '<option value="accidente">Accidente/tráfico</option>' +
        '<option value="transporte">Problema de transporte</option>' +
        '<option value="personal">Asunto personal</option>' +
        '</select></div>' +
        '<div class="form-group"><label for="rapido_foto" class="form-label">Foto (obligatorio):</label>' +
        '<input type="file" id="rapido_foto" name="foto" class="form-input" accept="image/*" required>' +
        '<img id="rapido_foto_preview" class="photo-preview" src="#" alt="Vista previa"></div>' +
        '<div class="form-group"><label for="rapido_observaciones" class="form-label">Observaciones:</label>' +
        '<textarea id="rapido_observaciones" name="observaciones" class="form-textarea" placeholder="Opcional"></textarea></div>' +
        '</div>' +
        '<div class="modal-footer">' +
        '<button type="button" onclick="cerrarModalRegistroRapido()" class="btn btn-secondary">Cancelar</button>' +
        '<button type="submit" class="btn btn-primary">Registrar</button>' +
        '</div></form></div></div>';

    document.body.insertAdjacentHTML('beforeend', modalHTML);
    document.getElementById('modalRegistroRapido').style.display = 'flex';

    document.getElementById('rapido_foto').addEventListener('change', function (e) {
        var preview = document.getElementById('rapido_foto_preview');
        var file = e.target.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function (e) { preview.src = e.target.result; preview.style.display = 'block'; };
            reader.readAsDataURL(file);
        } else {
            preview.style.display = 'none';
        }
    });

    var formRapidoEnviado = false;
    document.getElementById('formRegistroRapido').addEventListener('submit', function (e) {
        if (formRapidoEnviado) { e.preventDefault(); return false; }
        if (!validarFormularioRapido()) { e.preventDefault(); return false; }

        formRapidoEnviado = true;
        var submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Registrando...';
        }
        return true;
    });
}

function cerrarModalRegistroRapido() {
    var modal = document.getElementById('modalRegistroRapido');
    if (modal) modal.remove();
}

function validarFormularioRapido() {
    var fotoInput = document.getElementById('rapido_foto');
    if (!fotoInput.files || fotoInput.files.length === 0) {
        alert('Debe seleccionar una foto como evidencia');
        return false;
    }
    if (!fotoInput.files[0].type.match('image.*')) {
        alert('El archivo debe ser una imagen');
        return false;
    }
    return true;
}

// =============================================
// DETALLES TARDANZA NO REPORTADA
// =============================================

function verDetallesTardanzaNoReportada(codOperario, fecha, sucursalNombre, minutos) {
    var nombreColaborador = '';
    var fila = document.querySelector('#tardanza-nr-' + codOperario + '-' + fecha + ' td:first-child');
    if (fila) nombreColaborador = fila.textContent;

    var detallesHTML =
        '<div class="modal" id="modalDetallesNR">' +
        '<div class="modal-content">' +
        '<div class="modal-header">' +
        '<h2 class="modal-title">Detalles de Tardanza Detectada</h2>' +
        '<button class="modal-close" onclick="cerrarModalDetallesNR()">&times;</button>' +
        '</div>' +
        '<div class="modal-body">' +
        '<div class="info-group"><span class="info-label">Colaborador:</span><span class="info-value">' + nombreColaborador + '</span></div>' +
        '<div class="info-group"><span class="info-label">Sucursal:</span><span class="info-value">' + sucursalNombre + '</span></div>' +
        '<div class="info-group"><span class="info-label">Fecha:</span><span class="info-value">' + formatearFechaLocal(fecha) + '</span></div>' +
        '<div class="info-group"><span class="info-label">Minutos de tardanza:</span><span class="info-value">' + minutos + ' minutos</span></div>' +
        '<div class="info-group"><span class="info-label">Estado:</span><span class="info-value"><span class="status-badge status-no-reportada">No Reportada</span></span></div>' +
        '<div class="info-group"><span class="info-label">Descripción:</span><span class="info-value">Esta tardanza fue detectada automáticamente por el sistema al comparar el horario programado con las marcaciones reales. Aún no ha sido reportada manualmente por un líder.</span></div>' +
        '</div>' +
        '<div class="modal-footer"><button type="button" onclick="cerrarModalDetallesNR()" class="btn btn-primary">Cerrar</button></div>' +
        '</div></div>';

    document.body.insertAdjacentHTML('beforeend', detallesHTML);
    document.getElementById('modalDetallesNR').style.display = 'flex';
}

function cerrarModalDetallesNR() {
    var modal = document.getElementById('modalDetallesNR');
    if (modal) modal.remove();
}

function mostrarMinutosTardanza(minutos) {
    if (minutos <= 0) return 'A tiempo';
    if (minutos === 1) return '1 minuto (gracia)';
    return minutos + ' minutos';
}

// =============================================
// DATATABLES
// =============================================

$(document).ready(function () {
    $('#listaTardanzasMan').DataTable({
        language: { url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json' },
        dom: '<"top"l>rt<"bottom"ip>',
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
        pageLength: 25,
        order: [],
        ordering: true,
        orderMulti: true,
        columnDefs: [{ orderable: true, targets: '_all' }]
    });
});