let modalContacto;
let modalCuenta;

$(document).ready(function() {
    modalContacto = new bootstrap.Modal(document.getElementById('modalContacto'));
    modalCuenta = new bootstrap.Modal(document.getElementById('modalCuenta'));
    
    // Si es edición, cargar datos adicionales
    if (ES_EDICION) {
        // Cargar contactos, cuentas e historial cuando se cambia de pestaña
        $('#contactos-tab').on('shown.bs.tab', function() {
            cargarContactos();
        });
        
        $('#cuentas-tab').on('shown.bs.tab', function() {
            cargarCuentas();
        });
        
        $('#historial-tab').on('shown.bs.tab', function() {
            cargarHistorial();
        });
    }
});

// Guardar datos básicos - MEJORADO CON MEJOR MANEJO DE ERRORES
function guardarDatosBasicos() {
    const formData = $('#formDatosBasicos').serialize();
    
    // Recopilar tipos de pago seleccionados
    const tiposPagoSeleccionados = [];
    $('.tipo-pago-checkbox:checked').each(function() {
        tiposPagoSeleccionados.push($(this).val());
    });
    
    const datosCompletos = formData + '&tipos_pago=' + JSON.stringify(tiposPagoSeleccionados);
    
    // Mostrar indicador de carga
    const btnGuardar = event.target;
    const textoOriginal = btnGuardar.innerHTML;
    btnGuardar.disabled = true;
    btnGuardar.innerHTML = '<i class="bi bi-hourglass-split"></i> Guardando...';
    
    $.ajax({
        url: 'ajax/proveedores_guardar_basicos.php',
        method: 'POST',
        data: datosCompletos,
        dataType: 'json',
        success: function(response) {
            btnGuardar.disabled = false;
            btnGuardar.innerHTML = textoOriginal;
            
            if (response.success) {
                alert('✅ ' + response.message);
                if (!ES_EDICION && response.id_proveedor) {
                    // Redirigir a la página de edición con el ID del nuevo proveedor
                    window.location.href = 'proveedor_detalle.php?id=' + response.id_proveedor;
                }
            } else {
                // MOSTRAR ERROR COMPLETO
                let mensajeError = '❌ Error al guardar:\n\n' + response.message;
                
                // Si hay detalles adicionales, mostrarlos
                if (response.error_code) {
                    mensajeError += '\n\nCódigo: ' + response.error_code;
                }
                if (response.trace) {
                    console.error('Stack trace:', response.trace);
                    mensajeError += '\n\n(Ver consola del navegador para más detalles)';
                }
                
                alert(mensajeError);
            }
        },
        error: function(xhr, status, error) {
            btnGuardar.disabled = false;
            btnGuardar.innerHTML = textoOriginal;
            
            // MOSTRAR ERROR DETALLADO DEL SERVIDOR
            let mensajeError = '❌ Error de conexión con el servidor:\n\n';
            
            try {
                // Intentar parsear respuesta JSON
                const response = JSON.parse(xhr.responseText);
                mensajeError += response.message || 'Error desconocido';
                
                if (response.error_code) {
                    mensajeError += '\n\nCódigo: ' + response.error_code;
                }
                if (response.trace) {
                    console.error('Stack trace:', response.trace);
                    mensajeError += '\n\n(Ver consola del navegador para más detalles)';
                }
            } catch (e) {
                // Si no es JSON, mostrar respuesta cruda
                mensajeError += 'Respuesta del servidor:\n' + xhr.responseText;
                
                if (xhr.status === 404) {
                    mensajeError = '❌ Error 404: Archivo no encontrado\n\n';
                    mensajeError += 'Verifica que el archivo exista en:\n';
                    mensajeError += 'ajax/proveedores_guardar_basicos.php';
                } else if (xhr.status === 500) {
                    mensajeError = '❌ Error 500: Error interno del servidor\n\n';
                    mensajeError += 'Revisa los logs del servidor PHP';
                }
            }
            
            console.error('Error AJAX:', {
                status: xhr.status,
                statusText: xhr.statusText,
                responseText: xhr.responseText,
                error: error
            });
            
            mensajeError += '\n\nEstado HTTP: ' + xhr.status;
            mensajeError += '\nVer consola del navegador (F12) para más detalles';
            
            alert(mensajeError);
        }
    });
}

// ============= CONTACTOS =============

function cargarContactos() {
    $.ajax({
        url: 'ajax/proveedores_get_contactos.php',
        method: 'POST',
        data: { id_proveedor: ID_PROVEEDOR },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderizarContactos(response.contactos);
            } else {
                $('#listaContactos').html('<p class="text-muted">Error al cargar contactos</p>');
            }
        },
        error: function(xhr) {
            console.error('Error al cargar contactos:', xhr.responseText);
            $('#listaContactos').html('<p class="text-danger">Error: ' + xhr.statusText + '</p>');
        }
    });
}

function renderizarContactos(contactos) {
    const lista = $('#listaContactos');
    lista.empty();
    
    if (contactos.length === 0) {
        lista.html('<p class="text-muted">No hay contactos registrados</p>');
        return;
    }
    
    contactos.forEach(contacto => {
        const principal = contacto.principal == 1 ? '<span class="badge bg-primary ms-2">Principal</span>' : '';
        const card = $(`
            <div class="contacto-card">
                <div class="contacto-info">
                    <h6>${contacto.nombre}${principal}</h6>
                    <p class="mb-1"><strong>Cargo:</strong> ${contacto.cargo || '-'}</p>
                    <p class="mb-1"><strong>Teléfono:</strong> ${contacto.telefono || '-'}</p>
                    <small class="text-muted">Registrado: ${formatearFechaHora(contacto.fecha_registro)}</small>
                </div>
                ${PERMISOS.editar ? `
                <div class="contacto-acciones">
                    <button class="btn btn-sm btn-outline-primary" onclick="editarContacto(${contacto.id})">
                        <i class="bi bi-pencil"></i> Editar
                    </button>
                </div>
                ` : ''}
            </div>
        `);
        lista.append(card);
    });
}

function abrirModalContacto() {
    $('#modalContactoTitulo').text('Agregar Contacto');
    $('#formContacto')[0].reset();
    $('#contactoId').val('');
    modalContacto.show();
}

function editarContacto(id) {
    $.ajax({
        url: 'ajax/proveedores_get_contacto.php',
        method: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#modalContactoTitulo').text('Editar Contacto');
                $('#contactoId').val(response.data.id);
                $('#contactoNombre').val(response.data.nombre);
                $('#contactoTelefono').val(response.data.telefono);
                $('#contactoCargo').val(response.data.cargo);
                $('#contactoPrincipal').prop('checked', response.data.principal == 1);
                modalContacto.show();
            } else {
                alert('❌ Error: ' + response.message);
            }
        },
        error: function(xhr) {
            console.error('Error:', xhr.responseText);
            alert('❌ Error al cargar el contacto: ' + xhr.statusText);
        }
    });
}

function guardarContacto() {
    const formData = $('#formContacto').serialize();
    
    $.ajax({
        url: 'ajax/proveedores_guardar_contacto.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                modalContacto.hide();
                cargarContactos();
                alert('✅ ' + response.message);
            } else {
                alert('❌ Error: ' + response.message);
            }
        },
        error: function(xhr) {
            console.error('Error:', xhr.responseText);
            alert('❌ Error al guardar: ' + xhr.statusText + '\n\nVer consola para más detalles');
        }
    });
}

// ============= CUENTAS =============

function cargarCuentas() {
    $.ajax({
        url: 'ajax/proveedores_get_cuentas.php',
        method: 'POST',
        data: { id_proveedor: ID_PROVEEDOR },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderizarCuentas(response.cuentas);
            } else {
                $('#listaCuentas').html('<p class="text-muted">Error al cargar cuentas</p>');
            }
        },
        error: function(xhr) {
            console.error('Error al cargar cuentas:', xhr.responseText);
            $('#listaCuentas').html('<p class="text-danger">Error: ' + xhr.statusText + '</p>');
        }
    });
}

function renderizarCuentas(cuentas) {
    const lista = $('#listaCuentas');
    lista.empty();
    
    if (cuentas.length === 0) {
        lista.html('<p class="text-muted">No hay cuentas bancarias registradas</p>');
        return;
    }
    
    cuentas.forEach(cuenta => {
        const principal = cuenta.principal == 1 ? '<span class="badge bg-primary ms-2">Principal</span>' : '';
        const card = $(`
            <div class="cuenta-card">
                <div class="cuenta-info">
                    <h6>${cuenta.banco}${principal}</h6>
                    <p class="mb-1"><strong>Número:</strong> ${cuenta.numero_cuenta}</p>
                    <p class="mb-1"><strong>Titular:</strong> ${cuenta.titular}</p>
                    <p class="mb-1"><strong>Moneda:</strong> ${cuenta.moneda}</p>
                    <small class="text-muted">Registrado: ${formatearFechaHora(cuenta.fecha_registro)}</small>
                </div>
                ${PERMISOS.editar ? `
                <div class="cuenta-acciones">
                    <button class="btn btn-sm btn-outline-primary" onclick="editarCuenta(${cuenta.id})">
                        <i class="bi bi-pencil"></i> Editar
                    </button>
                </div>
                ` : ''}
            </div>
        `);
        lista.append(card);
    });
}

function abrirModalCuenta() {
    $('#modalCuentaTitulo').text('Agregar Cuenta Bancaria');
    $('#formCuenta')[0].reset();
    $('#cuentaId').val('');
    modalCuenta.show();
}

function editarCuenta(id) {
    $.ajax({
        url: 'ajax/proveedores_get_cuenta.php',
        method: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#modalCuentaTitulo').text('Editar Cuenta Bancaria');
                $('#cuentaId').val(response.data.id);
                $('#cuentaNumero').val(response.data.numero_cuenta);
                $('#cuentaTitular').val(response.data.titular);
                $('#cuentaBanco').val(response.data.banco);
                $('#cuentaMoneda').val(response.data.moneda);
                $('#cuentaPrincipal').prop('checked', response.data.principal == 1);
                modalCuenta.show();
            } else {
                alert('❌ Error: ' + response.message);
            }
        },
        error: function(xhr) {
            console.error('Error:', xhr.responseText);
            alert('❌ Error al cargar la cuenta: ' + xhr.statusText);
        }
    });
}

function guardarCuenta() {
    const formData = $('#formCuenta').serialize();
    
    $.ajax({
        url: 'ajax/proveedores_guardar_cuenta.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                modalCuenta.hide();
                cargarCuentas();
                alert('✅ ' + response.message);
            } else {
                alert('❌ Error: ' + response.message);
            }
        },
        error: function(xhr) {
            console.error('Error:', xhr.responseText);
            alert('❌ Error al guardar: ' + xhr.statusText + '\n\nVer consola para más detalles');
        }
    });
}

// ============= HISTORIAL =============

function cargarHistorial() {
    $.ajax({
        url: 'ajax/proveedores_get_historial.php',
        method: 'POST',
        data: { id_proveedor: ID_PROVEEDOR },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderizarHistorial(response.historial);
            } else {
                $('#listaHistorial').html('<p class="text-muted">Error al cargar historial</p>');
            }
        },
        error: function(xhr) {
            console.error('Error al cargar historial:', xhr.responseText);
            $('#listaHistorial').html('<p class="text-danger">Error: ' + xhr.statusText + '</p>');
        }
    });
}

function renderizarHistorial(historial) {
    const lista = $('#listaHistorial');
    lista.empty();
    
    if (historial.length === 0) {
        lista.html('<p class="text-muted">No hay cambios registrados</p>');
        return;
    }
    
    historial.forEach(item => {
        const iconos = {
            'datos_basicos': 'bi-building',
            'contacto': 'bi-person-lines-fill',
            'cuenta': 'bi-credit-card',
            'tipo_pago': 'bi-cash-stack',
            'vigencia': 'bi-toggle-on'
        };
        
        const icono = iconos[item.tipo_cambio] || 'bi-pencil';
        
        const card = $(`
            <div class="historial-item">
                <div class="historial-icon">
                    <i class="bi ${icono}"></i>
                </div>
                <div class="historial-info">
                    <h6>${item.descripcion}</h6>
                    <small class="text-muted">
                        ${formatearFechaHora(item.fecha_cambio)} - ${item.usuario_nombre || 'Sistema'}
                    </small>
                </div>
            </div>
        `);
        lista.append(card);
    });
}

// Utilidades
function formatearFechaHora(fechaHora) {
    if (!fechaHora) return '-';
    const d = new Date(fechaHora);
    const fecha = `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
    const hora = `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
    return `${fecha} ${hora}`;
}