/* modulos/gerencia/js/ia_config_api.js */

/**
 * Carga los datos de un proveedor en el formulario para su edición
 * @param {Object} data - Objeto con los datos del proveedor
 */
function editar(data) {
    document.getElementById('formTitle').textContent = "Editar Proveedor: " + data.proveedor.toUpperCase();
    document.getElementById('editId').value = data.id;
    document.getElementById('editProveedor').value = data.proveedor;
    document.getElementById('editKey').value = data.api_key;
    document.getElementById('editPassword').value = data.password || '';
    document.getElementById('editActiva').checked = data.activa == 1;

    // Desplazamiento suave al formulario
    window.scrollTo({ top: 0, behavior: 'smooth' });

    // Feedback visual opcional
    const formCard = document.getElementById('formCard');
    formCard.style.ring = "2px solid var(--color-principal)";
    setTimeout(() => formCard.style.ring = "none", 1000);
}

/**
 * Limpia el formulario para un nuevo registro
 */
function limpiarForm() {
    document.getElementById('formTitle').textContent = "Registro de Proveedor";
    document.getElementById('editId').value = '';
    document.getElementById('editKey').value = '';
    document.getElementById('editPassword').value = '';
    document.getElementById('editActiva').checked = true;
}

/**
 * Confirmación antes de eliminar
 */
function confirmarEliminacion() {
    return confirm('¿Estás seguro de que deseas eliminar este proveedor? Esta acción no se puede deshacer.');
}
