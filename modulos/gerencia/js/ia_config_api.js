/**
 * modulos/gerencia/js/ia_config_api.js
 * Lógica para la gestión de proveedores de IA con Modal y Ping
 */

document.addEventListener('DOMContentLoaded', () => {
    // Escuchar el cambio en el switch de activa para actualizar el label
    const switchActiva = document.getElementById('editActiva');
    if (switchActiva) {
        switchActiva.addEventListener('change', function () {
            document.getElementById('editActivaLabel').textContent = this.checked ? 'Si' : 'No';
        });
    }
});

/**
 * Abre el modal para registrar un nuevo proveedor
 */
function nuevoProveedor() {
    limpiarForm();
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('apiModal'));
    document.getElementById('apiModalLabel').textContent = "Registrar Nuevo Proveedor";
    modal.show();
}

/**
 * Carga los datos de un proveedor en el formulario y abre el modal
 */
function editar(data) {
    document.getElementById('apiModalLabel').textContent = "Editar Proveedor: " + data.proveedor.toUpperCase();
    document.getElementById('editId').value = data.id;
    document.getElementById('editProveedor').value = data.proveedor;
    document.getElementById('editKey').value = data.api_key;
    document.getElementById('editPassword').value = data.password || '';

    const switchActiva = document.getElementById('editActiva');
    switchActiva.checked = data.activa == 1;
    document.getElementById('editActivaLabel').textContent = switchActiva.checked ? 'Si' : 'No';

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('apiModal'));
    modal.show();
}

/**
 * Limpia el formulario del modal
 */
function limpiarForm() {
    document.getElementById('apiForm').reset();
    document.getElementById('editId').value = '';
    document.getElementById('editActiva').checked = true;
    document.getElementById('editActivaLabel').textContent = 'Si';
}

/**
 * Confirmación de eliminación
 */
function confirmarEliminacion() {
    return confirm('¿Estás seguro de que deseas eliminar este proveedor? Esta acción no se puede deshacer.');
}

/**
 * Ejecuta una prueba de conexión (Ping) para un proveedor específico
 */
function probarConexion(id) {
    const statusDiv = document.getElementById(`status-test-${id}`);
    if (!statusDiv) return;

    // Mostrar loading
    statusDiv.innerHTML = '<i class="fas fa-spinner ping-loading" title="Probando..."></i>';

    $.ajax({
        url: 'ajax/ia_config_api_handler.php',
        method: 'POST',
        data: {
            accion: 'test',
            id: id
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                statusDiv.innerHTML = `<i class="fas fa-check-circle ping-success" title="${response.message}"></i>`;
            } else {
                statusDiv.innerHTML = `<i class="fas fa-times-circle ping-error" title="${response.message}"></i>`;
            }
        },
        error: function () {
            statusDiv.innerHTML = '<i class="fas fa-exclamation-triangle ping-error" title="Error de comunicación"></i>';
        }
    });
}
