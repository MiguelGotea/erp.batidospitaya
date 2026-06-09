/**
 * Lógica para Creación de Reembolsos con IA (Standalone Page)
 * Ubicación: /modulos/compras/js/reembolsos_ia_nuevo.js
 */

let itemsActuales = [];
let id_cuenta_proveedor = null;
let stream = null;
let modalCamara = null;

$(document).ready(function () {
    modalCamara = new bootstrap.Modal(document.getElementById('modalCamara'));

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('from_km') === '1') {
        cargarDatosKm(urlParams.get('semana'), urlParams.get('anio'), urlParams.get('costo'));
    } else if (editingId) {
        cargarDatosEdicion(editingId);
    } else if (visitaId) {
        cargarDatosVisita(visitaId);
    }
});

async function cargarDatosKm(semana, anio, costo) {
    $('#loader h5').text('Cargando consumos de KM de la semana...');
    $('#loader').css('display', 'flex').hide().fadeIn(300);

    try {
        const res = await $.get('../mantenimiento/ajax/get_km_reembolso_data.php', {
            semana: semana,
            anio: anio,
            costo_km: costo
        });

        if (res.success) {
            
            res.items.forEach(item => {
                itemsActuales.push({
                    cantidad: item.cantidad,
                    detalle: item.detalle,
                    total_cordobas: item.total_cordobas,
                    foto_path: null // Registros de KM no tienen foto individual aquí
                });
            });

            $('.empty-row').hide();
            renderTable();
            
            Swal.fire({
                icon: 'info',
                title: 'KM Importados',
                text: `Se han cargado ${res.items.length} registros de la semana #${semana}.`,
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    } catch (e) {
        console.error(e);
        Swal.fire('Error', 'No se pudieron cargar los datos de KM.', 'error');
    } finally {
        $('#loader').fadeOut(300);
    }
}

function cargarDatosEdicion(id) {
    $.get('ajax/reembolsos_ia_get_detalle.php', { id: id }, function (res) {
        if (res.success) {
            const s = res.solicitud;
            const idProv = s.id_proveedor;
            const idCuenta = s.id_cuenta_proveedor;

            $('#id_proveedor').val(idProv);
            $('#proveedor_nombre').val(s.proveedor_nombre || '');
            $('#moneda').val(s.moneda || 'Cordobas');
            $('#fecha_solicitud').val(s.fecha_solicitud);
            $('#concepto').val(s.concepto);
            $('#ceco').val(s.ceco);
            $('#ceco_nombre').val(s.ceco_nombre || s.ceco);
            cambiarMoneda(s.moneda || 'Cordobas');

            // Cargar el dropdown de cuentas y pre-seleccionar la guardada
            if (idProv) {
                cargarDatosProveedor(idProv, idCuenta);
            }

            // Cargar items
            itemsActuales = res.detalles.map(d => ({
                cantidad: d.cantidad,
                detalle: d.detalle,
                total_cordobas: d.monto_cordobas,
                foto_path: d.foto_factura
            }));

            $('.empty-row').hide();
            renderTable();
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    });
}

function abrirCamara() {
    const video = document.getElementById('video');

    navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment' }, // Preferir cámara trasera
        audio: false
    })
        .then(s => {
            stream = s;
            video.srcObject = stream;
            modalCamara.show();
        })
        .catch(err => {
            console.error("Error al acceder a la cámara:", err);
            Swal.fire('Cámara', 'No se pudo acceder a la cámara. Verifica los permisos.', 'error');
        });
}

function cerrarCamara() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
    }
    modalCamara.hide();
}

function capturarFoto() {
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const context = canvas.getContext('2d');

    // Ajustar dimensiones del canvas al video
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;

    // Dibujar frame
    context.drawImage(video, 0, 0, canvas.width, canvas.height);

    // Convertir a blob y procesar
    canvas.toBlob(blob => {
        const file = new File([blob], "captura_camara.jpg", { type: "image/jpeg" });
        cerrarCamara();
        procesarFoto(file);
    }, 'image/jpeg', 0.85);
}

function agregarFilaManual() {
    $('.empty-row').hide();
    itemsActuales.push({
        cantidad: 1,
        detalle: '',
        total_cordobas: 0,
        foto_path: null // Indica que es manual
    });
    renderTable();
}

function filtrarProveedor(valor) {
    const suggestionsContainer = $('#proveedor-suggestions');
    if (valor.length < 1) {
        suggestionsContainer.empty().hide();
        return;
    }

    const filtered = dataProveedores.filter(p => 
        p.nombre.toLowerCase().includes(valor.toLowerCase())
    );

    if (filtered.length > 0) {
        suggestionsContainer.empty();
        filtered.forEach(p => {
            const div = $('<div class="autocomplete-suggestion"></div>')
                .text(p.nombre)
                .on('click', function() {
                    seleccionarProveedorInternal(p.id, p.nombre);
                });
            suggestionsContainer.append(div);
        });
        suggestionsContainer.show();
    } else {
        suggestionsContainer.empty().hide();
    }
}

function seleccionarProveedorInternal(id, nombre) {
    $('#id_proveedor').val(id);
    $('#proveedor_nombre').val(nombre);
    $('#proveedor-suggestions').hide();
    
    // Disparar la lógica de cargar datos del proveedor
    cargarDatosProveedor(id);
}

function seleccionarProveedor(valor) {
    // Mantener por si acaso, aunque ya no se usa el datalist
}

function filtrarCECO(valor) {
    const suggestionsContainer = $('#ceco-suggestions');
    if (valor.length < 1) {
        suggestionsContainer.empty().hide();
        return;
    }

    const filtered = dataCecos.filter(c => 
        (c.Codigo + ' ' + c.Nombre).toLowerCase().includes(valor.toLowerCase())
    );

    if (filtered.length > 0) {
        suggestionsContainer.empty();
        filtered.forEach(c => {
            const text = `${c.Codigo} - ${c.Nombre}`;
            const div = $('<div class="autocomplete-suggestion"></div>')
                .text(text)
                .on('click', function() {
                    seleccionarCECOInternal(c.Codigo, text);
                });
            suggestionsContainer.append(div);
        });
        suggestionsContainer.show();
    } else {
        suggestionsContainer.empty().hide();
    }
}

function seleccionarCECOInternal(id, texto) {
    $('#ceco').val(id);
    $('#ceco_nombre').val(texto);
    $('#ceco-suggestions').hide();
}

$(document).on('click', function (e) {
    if (!$(e.target).closest('.position-relative').length) {
        $('.autocomplete-suggestions').hide();
    }
});

function cambiarMoneda(m) {
    const symbol = m === 'Dolares' ? 'US$' : 'C$';
    $('#thTotalSugerido').text(`Total Sugerido (${symbol})`);
    calcularTotal();
}

function cargarDatosProveedor(id, preseleccionarId) {
    const $sel = $('#select_cuenta_proveedor');

    if (!id) {
        $sel.empty().append('<option value="">— Selecciona un proveedor primero —</option>').prop('disabled', true);
        $('#cuenta_bancaria').val('');
        $('#banco_proveedor').val('');
        id_cuenta_proveedor = null;
        return;
    }

    $.post('ajax/proveedores_get_cuentas.php', { id_proveedor: id }, function (res) {
        $sel.empty();

        if (res.success && res.cuentas && res.cuentas.length > 0) {
            res.cuentas.forEach(function (c) {
                const label = `${c.banco} — ${c.numero_cuenta}${c.titular ? ' (' + c.titular + ')' : ''}${c.principal == 1 ? ' ★' : ''}`;
                const opt = $('<option>').val(c.id).text(label)
                    .data('banco', c.banco)
                    .data('numero_cuenta', c.numero_cuenta);
                $sel.append(opt);
            });

            // Pre-seleccionar: si se pide una cuenta específica, úsala; sino la primera (principal ya viene primera por ORDER BY)
            if (preseleccionarId) {
                $sel.val(preseleccionarId);
            }
            // Si no se pudo pre-seleccionar o no se pidió, queda el primero (principal)
            $sel.prop('disabled', false).trigger('change');
        } else {
            $sel.append('<option value="">— Sin cuentas registradas —</option>').prop('disabled', true);
            $('#cuenta_bancaria').val('');
            $('#banco_proveedor').val('');
            id_cuenta_proveedor = null;
        }
    }, 'json').fail(function () {
        $sel.empty().append('<option value="">— Error al cargar cuentas —</option>').prop('disabled', true);
        id_cuenta_proveedor = null;
    });
}

function seleccionarCuenta(el) {
    const $opt = $(el).find('option:selected');
    id_cuenta_proveedor = $(el).val() ? parseInt($(el).val()) : null;
    $('#cuenta_bancaria').val($opt.data('numero_cuenta') || '');
    $('#banco_proveedor').val($opt.data('banco') || '');
}



function procesarFoto(archivo) {
    let fileToUpload = null;

    // Si viene de un input file
    if (archivo instanceof HTMLInputElement) {
        if (!archivo.files || !archivo.files[0]) return;
        fileToUpload = archivo.files[0];
    } else {
        fileToUpload = archivo; // Es un File/Blob directo (desde cámara)
    }

    let formData = new FormData();
    formData.append('foto', fileToUpload);

    $('#loader').css('display', 'flex').hide().fadeIn(300);
    $('#statusIA').text('Cargando imagen...');

    $.ajax({
        url: 'ajax/reembolsos_ia_procesar_foto.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (res) {
            $('#loader').fadeOut(300);
            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Procesado!',
                    text: 'Extracción completada con ' + res.proveedor,
                    timer: 1500,
                    showConfirmButton: false
                });
                $('#statusIA').html('<span class="text-success"><i class="fas fa-check-circle"></i> Última factura leída con éxito.</span>');
                agregarAFilas(res.items, res.foto_path);
            } else {
                Swal.fire('Error de IA', res.message, 'error');
                $('#statusIA').html('<span class="text-danger"><i class="fas fa-times-circle"></i> Error al transcribir.</span>');
            }
        },
        error: function () {
            $('#loader').hide();
            Swal.fire('Error', 'No se pudo conectar con el servicio de IA.', 'error');
        }
    });
}

async function cargarDatosVisita(id) {
    $('#loader h5').text('Cargando información de la visita...');
    $('#loader').css('display', 'flex');

    try {
        const res = await $.get('../mantenimiento/ajax/get_datos_reembolso_visita.php', { visita_id: id });

        if (res.success) {
            const v = res.visita;
            // $('#concepto').val(`MANTENIMIENTO: Reembolso por visita a ${v.nombre_sucursal} (${v.fecha})`); // El usuario prefiere llenarlo manualmente

            if (res.compras && res.compras.length > 0) {
                $('.empty-row').hide();

                // Procesar cada factura de forma secuencial con IA
                for (let i = 0; i < res.compras.length; i++) {
                    const c = res.compras[i];
                    $('#statusIA').html(`<i class="fas fa-robot"></i> Procesando factura ${i + 1} de ${res.compras.length}...`);
                    $('#loader h5').text(`Extrayendo datos de factura ${i + 1}/${res.compras.length}...`);

                    const ruta = `modulos/mantenimiento/uploads/compras/${c.foto_factura}`;

                    try {
                        const iaRes = await $.ajax({
                            url: 'ajax/reembolsos_ia_procesar_existente.php',
                            type: 'POST',
                            data: JSON.stringify({ ruta: ruta }),
                            contentType: 'application/json'
                        });

                        if (iaRes.success) {
                            agregarAFilas(iaRes.items, iaRes.foto_path);
                            $('#statusIA').html(`<span class="text-success small"><i class="fas fa-check"></i> Factura ${i + 1} procesada.</span>`);
                        } else {
                            console.error(`Error en factura ${i + 1}:`, iaRes.message);
                            // Si falla la IA, agregar manualmente para no perder el item
                            agregarAFilas([{
                                cantidad: 1,
                                detalle: `(MANUAL) ${c.detalle}`,
                                total_cordobas: c.monto
                            }], `modulos/mantenimiento/uploads/compras/${c.foto_factura}`);
                        }
                    } catch (err) {
                        console.error("Error al conectar con IA para factura:", err);
                    }
                }
            }
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'No se pudieron cargar los datos de la visita.', 'error');
    } finally {
        $('#loader').fadeOut(300);
    }
}

function agregarAFilas(items, fotoPath) {
    $('.empty-row').hide();
    items.forEach(item => {
        item.foto_path = fotoPath;
        itemsActuales.push(item);
    });
    renderTable();
}

function actualizarDato(index, campo, valor) {
    itemsActuales[index][campo] = valor;
    calcularTotal();
}

function eliminarFila(index) {
    itemsActuales.splice(index, 1);
    renderTable();
}

function renderTable() {
    $('#bodyDetalles').empty();
    if (itemsActuales.length === 0) {
        let emptyRow = `
            <tr class="empty-row">
                <td colspan="5" class="text-center py-5 text-muted">
                    <i class="fas fa-cloud-upload-alt fa-3x mb-3 d-block opacity-25"></i>
                    Sube una foto para comenzar la extracción automática.
                </td>
            </tr>
        `;
        $('#bodyDetalles').append(emptyRow);
        calcularTotal();
        return;
    }

    itemsActuales.forEach((item, i) => {
        let row = `
            <tr data-index="${i}" class="align-middle">
                <td><input type="number" class="excel-input" value="${item.cantidad}" onchange="actualizarDato(${i}, 'cantidad', this.value)"></td>
                <td><input type="text" class="excel-input" value="${item.detalle}" onchange="actualizarDato(${i}, 'detalle', this.value)"></td>
                <td><input type="number" class="excel-input fw-bold text-primary" value="${item.total_cordobas}" onchange="actualizarDato(${i}, 'total_cordobas', this.value)"></td>
                <td class="text-center">
                    ${item.foto_path
                ? `<img src="../../${item.foto_path}" class="preview-img" onclick="window.open('../../${item.foto_path}')" title="Ver original">`
                : '<span class="badge bg-light text-secondary border"><i class="fas fa-keyboard me-1"></i> Manual</span>'}
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="eliminarFila(${i})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        $('#bodyDetalles').append(row);
    });
    calcularTotal();
}

function calcularTotal() {
    let total = 0;
    const moneda = $('#moneda').val();
    const symbol = moneda === 'Dolares' ? 'US$' : 'C$';

    itemsActuales.forEach(item => {
        total += parseFloat(item.total_cordobas) || 0;
    });
    $('#labelTotal').text(symbol + ' ' + total.toLocaleString('en-US', { minimumFractionDigits: 2 }));
}

async function guardarSolicitud() {
    const moneda = $('#moneda').val();
    let data = {
        id: editingId,
        id_proveedor: $('#id_proveedor').val(),
        id_cuenta_proveedor: id_cuenta_proveedor,
        concepto: $('#concepto').val(),
        ceco: $('#ceco').val(),
        moneda: moneda,
        fecha_solicitud: $('#fecha_solicitud').val(),
        total_cordobas: itemsActuales.reduce((acc, curr) => acc + (parseFloat(curr.total_cordobas) || 0), 0),
        items: itemsActuales
    };

    if (!data.concepto) {
        Swal.fire('Validación', 'Por favor ingresa un concepto para el reembolso.', 'warning');
        return;
    }

    if (itemsActuales.length === 0) {
        Swal.fire('Validación', 'No hay gastos registrados. Sube al menos una factura.', 'warning');
        return;
    }

    $('#loader h5').text('Registrando en base de datos...');
    $('#loader').css('display', 'flex');

    try {
        const res = await $.ajax({
            url: 'ajax/reembolsos_ia_guardar.php',
            type: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json'
        });

        $('#loader').hide();

        if (res.success) {
            // Vincular a KM si aplica
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('from_km') === '1') {
                try {
                    await $.ajax({
                        url: '../mantenimiento/ajax/marcar_informes_reembolsados.php',
                        type: 'POST',
                        data: JSON.stringify({
                            semana: urlParams.get('semana'),
                            anio: urlParams.get('anio'),
                            reembolso_id: res.id
                        }),
                        contentType: 'application/json'
                    });
                } catch (err) {
                    console.error("Error al marcar informes como reembolsados:", err);
                }
            }

            // Vincular a visita si aplica
            if (visitaId) {
                await $.ajax({
                    url: '../mantenimiento/ajax/vincular_reembolso_visita.php',
                    type: 'POST',
                    data: JSON.stringify({ visita_id: visitaId, reembolso_id: res.id }),
                    contentType: 'application/json'
                });
            }

            Swal.fire({
                icon: 'success',
                title: '¡Guardado!',
                text: res.message,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-print"></i> Imprimir',
                cancelButtonText: 'Ver Historial',
                confirmButtonColor: '#51B8AC'
            }).then((result) => {
                if (result.isConfirmed) {
                    const imprimirFotos = $('#chkImprimirFotos').is(':checked') ? 1 : 0;
                    window.open('reembolsos_ia_imprimir.php?id=' + res.id + '&fotos=' + imprimirFotos, '_blank');
                    location.href = 'reembolsos_ia_historial.php';
                } else {
                    location.href = 'reembolsos_ia_historial.php';
                }
            });
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    } catch (error) {
        $('#loader').hide();
        Swal.fire('Error', 'Ocurrió un error inesperado al intentar guardar.', 'error');
    }
}
