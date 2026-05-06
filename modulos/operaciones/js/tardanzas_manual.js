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

document.addEventListener('click', function (e) {
    if (e.target !== operarioInput) {
        sugerenciasDiv.style.display = 'none';
    }
});

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
    const ayer = new Date();
    ayer.setDate(ayer.getDate() - 1);
    document.getElementById('nueva_fecha').valueAsDate = ayer;
    document.getElementById('nueva_fecha').max = ayer.toISOString().split('T')[0];

    const selectOperario = document.getElementById('nueva_operario');
    selectOperario.innerHTML = '<option value="">Seleccione un colaborador</option>';

    const selectSucursal = document.getElementById('nueva_sucursal');
    const primeraSucursal = selectSucursal.value;
    if (primeraSucursal) {
        cargarOperariosSucursal(primeraSucursal);
    }
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

    let url = `tardanzas_manual.php?action=obtener_operarios&sucursal=${codSucursal}&fecha_tardanza=${fechaTardanza}`;

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
        const fotoUrl = `uploads/tardanzas/`;
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
        fetch(`obtener_horario_programado.php?cod_operario=&fecha=`).then(r => r.json()),
        fetch(`obtener_marcaciones.php?cod_operario=&fecha=`).then(r => r.json())
    ]).then(([horario, marcaciones]) => {
        const entradaProgramada = horario.hora_entrada ? formatoHoraAmPm(horario.hora_entrada) : 'No';
        const salidaProgramada = horario.hora_salida ? formatoHoraAmPm(horario.hora_salida) : 'No';
        document.getElementById('editar_entrada_programada').textContent = entradaProgramada;
        document.getElementById('editar_salida_programada').textContent = salidaProgramada;
        const entradaMarcada = marcaciones.hora_ingreso ? formatoHoraAmPm(marcaciones.hora_ingreso) : 'No marco';
        const salidaMarcada = marcaciones.hora_salida ? formatoHoraAmPm(marcaciones.hora_salida) : 'No marco';
        document.getElementById('editar_entrada_marcada').textContent = entradaMarcada;
        document.getElementById('editar_salida_marcada').textContent = salidaMarcada;
    }).catch(error => {
        console.error('Error al obtener datos:', error);
        ['editar_entrada_programada','editar_salida_programada','editar_entrada_marcada','editar_salida_marcada'].forEach(id => document.getElementById(id).textContent = 'Error');
    });
    const urlParams = new URLSearchParams(window.location.search);
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
