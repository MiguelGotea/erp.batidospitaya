/**
 * Lógica para Gestión de Reembolsos
 * Ubicación: /modulos/compras/js/reembolsos_historial.js
 */

let itemsActuales = [];
let id_cuenta_proveedor = null;

$(document).ready(function() {
    // Inicializaciones si son necesarias
});

function cargarDatosProveedor(id) {
    if (!id) {
        $('#cuenta_bancaria').val('');
        $('#banco_proveedor').val('');
        id_cuenta_proveedor = null;
        return;
    }

    $.get('ajax/reembolsos_get_proveedor_data.php', { id_proveedor: id }, function(res) {
        if (res.success && res.data) {
            $('#cuenta_bancaria').val(res.data.numero_cuenta);
            $('#banco_proveedor').val(res.data.banco);
            id_cuenta_proveedor = res.data.id;
        } else {
            $('#cuenta_bancaria').val('');
            $('#banco_proveedor').val('');
            id_cuenta_proveedor = null;
        }
    });
}

function procesarFoto(input) {
    if (!input.files || !input.files[0]) return;

    let formData = new FormData();
    formData.append('foto', input.files[0]);

    $('#loader').css('display', 'flex');

    $.ajax({
        url: 'ajax/reembolsos_procesar_foto.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            $('#loader').hide();
            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Transcripción Exitosa',
                    text: 'IA procesó la factura usando ' + res.proveedor,
                    timer: 2000,
                    showConfirmButton: false
                });
                agregarAFilas(res.items, res.foto_path);
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        },
        error: function() {
            $('#loader').hide();
            Swal.fire('Error', 'Error de conexión con el servidor', 'error');
        }
    });
}

function agregarAFilas(items, fotoPath) {
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
    itemsActuales.forEach((item, i) => {
        let row = `
            <tr data-index="${i}">
                <td><input type="number" class="excel-input" value="${item.cantidad}" onchange="actualizarDato(${i}, 'cantidad', this.value)"></td>
                <td><input type="text" class="excel-input" value="${item.detalle}" onchange="actualizarDato(${i}, 'detalle', this.value)"></td>
                <td><input type="number" class="excel-input" value="${item.total_cordobas}" onchange="actualizarDato(${i}, 'total_cordobas', this.value)"></td>
                <td class="text-center"><img src="../../${item.foto_path}" class="preview-img" onclick="window.open('../../${item.foto_path}')"></td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarFila(${i})">
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
    itemsActuales.forEach(item => {
        total += parseFloat(item.total_cordobas) || 0;
    });
    $('#labelTotal').text('C$ ' + total.toLocaleString('en-US', { minimumFractionDigits: 2 }));
}

async function guardarSolicitud() {
    let data = {
        id_proveedor: $('#id_proveedor').val(),
        id_cuenta_proveedor: id_cuenta_proveedor,
        concepto: $('#concepto').val(),
        ceco: $('#ceco').val(),
        fecha_solicitud: $('#fecha_solicitud').val(),
        total_cordobas: itemsActuales.reduce((acc, curr) => acc + (parseFloat(curr.total_cordobas) || 0), 0),
        items: itemsActuales
    };

    if (!data.concepto) {
        Swal.fire('Validación', 'Debe ingresar un concepto para el reembolso.', 'warning');
        return;
    }

    if (itemsActuales.length === 0) {
        Swal.fire('Validación', 'Debe agregar al menos un gasto subiendo una factura.', 'warning');
        return;
    }

    $('#loader h5').text('Guardando...');
    $('#loader').css('display', 'flex');

    try {
        const res = await $.ajax({
            url: 'ajax/reembolsos_guardar.php',
            type: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json'
        });

        $('#loader').hide();

        if (res.success) {
            Swal.fire('¡Éxito!', res.message, 'success').then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    } catch (error) {
        $('#loader').hide();
        Swal.fire('Error', 'No se pudo guardar la solicitud.', 'error');
    }
}

function verDetalle(id) {
    Swal.fire('Información', 'La vista de detalle histórica se habilitará en la siguiente fase.', 'info');
}
