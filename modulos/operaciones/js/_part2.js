
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
        fetch('obtener_horario_programado.php?cod_operario=' + codOperario + '&fecha=' + fecha).then(r => r.json()),
        fetch('obtener_marcaciones.php?cod_operario=' + codOperario + '&fecha=' + fecha).then(r => r.json())
    ]).then(function(results) {
        var horario = results[0], marcaciones = results[1];
        document.getElementById('editar_entrada_programada').textContent = horario.hora_entrada ? formatoHoraAmPm(horario.hora_entrada) : 'No';
        document.getElementById('editar_salida_programada').textContent = horario.hora_salida ? formatoHoraAmPm(horario.hora_salida) : 'No';
        document.getElementById('editar_entrada_marcada').textContent = marcaciones.hora_ingreso ? formatoHoraAmPm(marcaciones.hora_ingreso) : 'No marco';
        document.getElementById('editar_salida_marcada').textContent = marcaciones.hora_salida ? formatoHoraAmPm(marcaciones.hora_salida) : 'No marco';
    }).catch(function(error) {
        console.error('Error al obtener datos:', error);
        ['editar_entrada_programada','editar_salida_programada','editar_entrada_marcada','editar_salida_marcada']
            .forEach(function(eid) { document.getElementById(eid).textContent = 'Error'; });
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

document.getElementById('nueva_operario').addEventListener('change', function () {
    var btnConsultar = document.getElementById('btnConsultarMarcacionesNueva');
    btnConsultar.disabled = !this.value || !document.getElementById('nueva_fecha').value;
});

document.getElementById('nueva_fecha').addEventListener('change', function () {
    var btnConsultar = document.getElementById('btnConsultarMarcacionesNueva');
    btnConsultar.disabled = !this.value || !document.getElementById('nueva_operario').value;
    var sucursalSelect = document.getElementById('nueva_sucursal');
    if (sucursalSelect.value && this.value) {
        cargarOperariosSucursal(sucursalSelect.value, this.value);
    }
});

document.getElementById('btnConsultarMarcacionesNueva').addEventListener('click', function () {
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

document.getElementById('btnConsultarMarcacionesEditar').addEventListener('click', function () {
    var nombre = document.getElementById('editar_nombre').textContent;
    var sucursal = document.getElementById('editar_sucursal').textContent;
    var fecha = document.getElementById('editar_fecha').textContent;
    var minutos = parseInt(document.getElementById('editar_minutos').textContent);
    var codOperario = document.getElementById('editar_cod_operario').value;
    mostrarModalConsultarMarcaciones(codOperario, nombre, sucursal, fecha, minutos);
});

document.getElementById('nueva_sucursal').addEventListener('change', function () {
    var fechaInput = document.getElementById('nueva_fecha');
    if (fechaInput.value) {
        cargarOperariosSucursal(this.value, fechaInput.value);
    } else {
        document.getElementById('nueva_operario').innerHTML = '<option value="">Primero seleccione una fecha</option>';
    }
});

document.getElementById('formNuevaTardanza').addEventListener('submit', function (e) {
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
    return true;
});

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

    fetch('obtener_marcaciones.php?cod_operario=' + codOperario + '&fecha=' + fechaConsulta + '&debug=1')
        .then(function(response) { return response.json(); })
        .then(function(data) {
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
        .catch(function(error) {
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
// VISOR DE FOTOS CON ZOOM
// =============================================

function mostrarFotoAmpliadaDesdeTabla(fotoPath) {
    if (!fotoPath) { alert('No hay foto disponible'); return; }
    var fotoAmpliada = document.getElementById('fotoAmpliada');
    currentZoomLevel = 1;
    fotoAmpliada.style.transform = 'scale(1)';
    fotoAmpliada.style.cursor = 'zoom-in';
    fotoAmpliada.src = 'uploads/tardanzas/' + fotoPath;
    document.getElementById('modalVerFoto').style.display = 'flex';
}

function mostrarFotoAmpliada(src) {
    var fotoAmpliada = document.getElementById('fotoAmpliada');
    currentZoomLevel = 1;
    fotoAmpliada.style.transform = 'scale(1)';
    fotoAmpliada.style.cursor = 'zoom-in';
    fotoAmpliada.src = src;
    document.getElementById('modalVerFoto').style.display = 'flex';
}

function cerrarModalFoto() {
    document.getElementById('modalVerFoto').style.display = 'none';
    currentZoomLevel = 1;
    var fotoAmpliada = document.getElementById('fotoAmpliada');
    if (fotoAmpliada) { fotoAmpliada.style.transform = 'scale(1)'; fotoAmpliada.style.cursor = 'zoom-in'; }
}

function zoomIn() { if (currentZoomLevel < maxZoomLevel) { currentZoomLevel += zoomStep; applyZoom(); } }
function zoomOut() { if (currentZoomLevel > minZoomLevel) { currentZoomLevel -= zoomStep; applyZoom(); } }
function resetZoom() { currentZoomLevel = 1; applyZoom(); }

function applyZoom() {
    var fotoAmpliada = document.getElementById('fotoAmpliada');
    if (fotoAmpliada) {
        fotoAmpliada.style.transform = 'scale(' + currentZoomLevel + ')';
        fotoAmpliada.style.cursor = currentZoomLevel > 1 ? 'zoom-out' : 'zoom-in';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    var modalFoto = document.getElementById('modalVerFoto');
    var imageContainer = document.getElementById('imageContainer');
    var fotoAmpliada = document.getElementById('fotoAmpliada');

    if (modalFoto) {
        modalFoto.addEventListener('click', function (e) { if (e.target === modalFoto) cerrarModalFoto(); });
    }
    if (imageContainer) {
        imageContainer.addEventListener('click', function (e) { e.stopPropagation(); });
    }
    if (fotoAmpliada) {
        fotoAmpliada.addEventListener('wheel', function (e) {
            e.preventDefault(); e.stopPropagation();
            if (e.deltaY < 0) zoomIn(); else zoomOut();
        });
        fotoAmpliada.addEventListener('click', function (e) {
            e.stopPropagation();
            if (currentZoomLevel === 1) zoomIn(); else resetZoom();
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.getElementById('modalVerFoto').style.display === 'flex') {
            cerrarModalFoto();
        }
    });
});

window.addEventListener('click', function (event) {
    ['modalNuevaTardanza', 'modalEditarTardanza'].forEach(function(modalId) {
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
