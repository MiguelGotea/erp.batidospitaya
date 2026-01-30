// ventas_meta_funciones.js

$(document).ready(function () {
    cargarDatos();
});

function cargarDatos() {
    $('#tablaMetasBody').html(`
        <tr>
            <td colspan="14" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
            </td>
        </tr>
    `);

    $.ajax({
        url: 'ajax/ventas_meta_ajax.php',
        method: 'POST',
        data: {
            action: 'get_data',
            anio: anioSeleccionado
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                renderizarTabla(response.sucursales, response.metas);
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function () {
            Swal.fire('Error', 'No se pudieron cargar los datos', 'error');
        }
    });
}

function renderizarTabla(sucursales, metas) {
    const tbody = $('#tablaMetasBody');
    tbody.empty();

    if (sucursales.length === 0) {
        tbody.append('<tr><td colspan="14" class="text-center py-4">No hay sucursales registradas</td></tr>');
        return;
    }

    sucursales.forEach(suc => {
        const tr = $('<tr>');
        tr.append(`<td class="sticky-col">${suc.nombre}</td>`);

        let anualSum = 0;
        for (let mes = 1; mes <= 12; mes++) {
            const valRaw = (metas[suc.codigo] && metas[suc.codigo][mes]) ? metas[suc.codigo][mes] : 0;
            const valor = parseFloat(valRaw);
            anualSum += valor;

            const cell = $(`<td class="editable-cell" data-sucursal="${suc.codigo}" data-mes="${mes}">
                <span class="valor-texto">${valor > 0 ? valor.toLocaleString('en-US') : '-'}</span>
            </td>`);

            if (PUEDE_EDITAR) {
                cell.on('click', function () {
                    iniciarEdicion($(this));
                });
            }

            tr.append(cell);
        }

        tr.append(`<td class="fw-bold bg-light total-anual">${anualSum > 0 ? anualSum.toLocaleString('en-US') : '-'}</td>`);
        tbody.append(tr);
    });
}

function iniciarEdicion(cell) {
    if (cell.find('input').length > 0) return;

    const valorRaw = cell.find('.valor-texto').text() === '-' ? '' : cell.find('.valor-texto').text().replace(/,/g, '');
    const valorActual = valorRaw !== '' ? valorRaw : '';
    const input = $(`<input type="number" class="edit-input" value="${valorActual}" step="1">`);

    cell.empty().append(input);
    input.focus();

    input.on('blur', function () {
        finalizarEdicion(cell, $(this).val());
    });

    input.on('keypress', function (e) {
        if (e.which == 13) {
            $(this).blur();
        }
    });
}

function finalizarEdicion(cell, nuevoValor) {
    const sucursalId = cell.data('sucursal');
    const mes = cell.data('mes');
    const valorActual = cell.find('input').val();

    if (nuevoValor === '' || isNaN(nuevoValor)) {
        cell.empty().append(`<span class="valor-texto">-</span>`);
        return;
    }

    // Mostrar spinner o indicador de guardado
    cell.empty().append('<div class="spinner-border spinner-border-sm text-success" role="status"></div>');

    $.ajax({
        url: 'ajax/ventas_meta_ajax.php',
        method: 'POST',
        data: {
            action: 'save_meta',
            cod_sucursal: sucursalId,
            mes: mes,
            anio: anioSeleccionado,
            valor: nuevoValor
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                const valorFloat = parseFloat(nuevoValor);
                cell.empty().append(`<span class="valor-texto">${valorFloat > 0 ? valorFloat.toLocaleString('en-US') : '-'}</span>`);
                actualizarTotalAnual(cell.closest('tr'));
            } else {
                Swal.fire('Error', response.message, 'error');
                cell.empty().append(`<span class="valor-texto">-</span>`);
            }
        },
        error: function () {
            Swal.fire('Error', 'Error de conexión al guardar', 'error');
            cell.empty().append(`<span class="valor-texto">-</span>`);
        }
    });
}

function actualizarTotalAnual(tr) {
    let sum = 0;
    tr.find('.editable-cell .valor-texto').each(function () {
        const val = $(this).text();
        if (val !== '-') {
            sum += parseFloat(val.replace(/,/g, ''));
        }
    });
    tr.find('.total-anual').text(sum > 0 ? sum.toLocaleString('en-US') : '-');
}

function cambiarAnio(delta) {
    anioSeleccionado += delta;
    $('#txtAnio, #valAnio').text(anioSeleccionado);
    cargarDatos();
}
