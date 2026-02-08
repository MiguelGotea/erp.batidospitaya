// gestion_sorteos.js

let paginaActual = 1;
let registrosPorPagina = 50;
let filtrosActivos = {};
let tienePermisoEdicion = false; // Will be set from PHP inline script

$(document).ready(function () {
    cargarRegistros();
});

function cargarRegistros() {
    const params = new URLSearchParams({
        page: paginaActual,
        per_page: registrosPorPagina,
        ...filtrosActivos
    });

    $.ajax({
        url: `ajax/get_registros_sorteos.php?${params}`,
        method: 'GET',
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                renderizarTabla(response.data);
                renderizarPaginacion(response.total_pages, response.page);
            } else {
                mostrarError('Error al cargar registros');
            }
        },
        error: function () {
            mostrarError('Error de conexión');
        }
    });
}

function renderizarTabla(registros) {
    const tbody = $('#tablaSorteosBody');
    tbody.empty();

    if (registros.length === 0) {
        tbody.append(`
            <tr>
                <td colspan="12" class="text-center text-muted py-4">
                    No se encontraron registros
                </td>
            </tr>
        `);
        return;
    }

    registros.forEach(registro => {
        const validadoBadge = registro.validado_ia == 1
            ? '<span class="validado-badge validado-si">✅ Validado</span>'
            : '<span class="validado-badge validado-no">❌ No validado</span>';

        const tipoBadge = registro.tipo_qr === 'online'
            ? '<span class="tipo-qr-badge tipo-online">Online</span>'
            : '<span class="tipo-qr-badge tipo-offline">Offline</span>';

        const fecha = new Date(registro.fecha_registro).toLocaleString('es-NI', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });

        const btnEliminar = tienePermisoEdicion
            ? `<button class="btn btn-sm btn-danger" onclick="eliminarRegistro(${registro.id})" title="Eliminar">
                   <i class="bi bi-trash"></i>
               </button>`
            : '';

        tbody.append(`
            <tr>
                <td>${registro.id}</td>
                <td>${fecha}</td>
                <td>${registro.nombre_completo}</td>
                <td>${registro.numero_contacto}</td>
                <td>${registro.numero_cedula || '-'}</td>
                <td>${registro.numero_factura}</td>
                <td>${registro.correo_electronico || '-'}</td>
                <td>C$ ${parseFloat(registro.monto_factura).toFixed(2)}</td>
                <td>${registro.puntos_factura}</td>
                <td>${tipoBadge}</td>
                <td>${validadoBadge}</td>
                <td>
                    <button class="btn btn-sm btn-primary btn-ver-foto" onclick="verFoto(${registro.id}, '${registro.foto_factura}')" title="Ver Foto">
                        <i class="bi bi-eye"></i> Ver
                    </button>
                    ${btnEliminar}
                </td>
            </tr>
        `);
    });
}

function verFoto(id, fotoNombre) {
    // Cargar datos del registro
    $.ajax({
        url: `ajax/get_registros_sorteos.php?id=${id}`,
        method: 'GET',
        dataType: 'json',
        success: function (response) {
            if (response.success && response.data.length > 0) {
                const registro = response.data[0];

                // Mostrar foto
                $('#fotoFactura').attr('src', `../PitayaLove/uploads/${fotoNombre}`);

                // Mostrar datos
                const validado = registro.validado_ia == 1 ? '✅ Sí' : '❌ No';
                const fecha = new Date(registro.fecha_registro).toLocaleString('es-NI');

                $('#datosRegistro').html(`
                    <div class="mb-2"><strong>ID:</strong> ${registro.id}</div>
                    <div class="mb-2"><strong>Fecha:</strong> ${fecha}</div>
                    <div class="mb-2"><strong>Nombre:</strong> ${registro.nombre_completo}</div>
                    <div class="mb-2"><strong>Contacto:</strong> ${registro.numero_contacto}</div>
                    <div class="mb-2"><strong>Cédula:</strong> ${registro.numero_cedula || 'N/A'}</div>
                    <div class="mb-2"><strong>No. Factura:</strong> ${registro.numero_factura}</div>
                    <div class="mb-2"><strong>Correo:</strong> ${registro.correo_electronico || 'N/A'}</div>
                    <div class="mb-2"><strong>Monto:</strong> C$ ${parseFloat(registro.monto_factura).toFixed(2)}</div>
                    <div class="mb-2"><strong>Puntos:</strong> ${registro.puntos_factura}</div>
                    <div class="mb-2"><strong>Tipo QR:</strong> ${registro.tipo_qr}</div>
                    <div class="mb-2"><strong>Validado IA:</strong> ${validado}</div>
                `);

                // Mostrar modal
                new bootstrap.Modal(document.getElementById('modalVerFoto')).show();
            }
        }
    });
}

function eliminarRegistro(id) {
    if (!confirm('¿Está seguro de eliminar este registro? Esta acción no se puede deshacer.')) {
        return;
    }

    $.ajax({
        url: 'ajax/delete_registro_sorteo.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ id: id }),
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                mostrarExito(response.message);
                cargarRegistros();
            } else {
                mostrarError(response.message);
            }
        },
        error: function () {
            mostrarError('Error al eliminar registro');
        }
    });
}

function cambiarRegistrosPorPagina() {
    registrosPorPagina = parseInt($('#registrosPorPagina').val());
    paginaActual = 1;
    cargarRegistros();
}

function renderizarPaginacion(totalPaginas, paginaActual) {
    const paginacion = $('#paginacion');
    paginacion.empty();

    if (totalPaginas <= 1) return;

    let html = '<nav><ul class="pagination pagination-sm mb-0">';

    // Botón anterior
    html += `<li class="page-item ${paginaActual === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="cambiarPagina(${paginaActual - 1}); return false;">Anterior</a>
    </li>`;

    // Números de página
    for (let i = 1; i <= totalPaginas; i++) {
        if (i === 1 || i === totalPaginas || (i >= paginaActual - 2 && i <= paginaActual + 2)) {
            html += `<li class="page-item ${i === paginaActual ? 'active' : ''}">
                <a class="page-link" href="#" onclick="cambiarPagina(${i}); return false;">${i}</a>
            </li>`;
        } else if (i === paginaActual - 3 || i === paginaActual + 3) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    // Botón siguiente
    html += `<li class="page-item ${paginaActual === totalPaginas ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="cambiarPagina(${paginaActual + 1}); return false;">Siguiente</a>
    </li>`;

    html += '</ul></nav>';
    paginacion.html(html);
}

function cambiarPagina(pagina) {
    paginaActual = pagina;
    cargarRegistros();
}

function mostrarExito(mensaje) {
    alert(mensaje); // TODO: Implementar toast notifications
}

function mostrarError(mensaje) {
    alert(mensaje); // TODO: Implementar toast notifications
}
