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
    document.getElementById('editEmail').value = data.cuenta_correo || '';
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
    const btn = event.currentTarget;
    const originalIcon = btn.innerHTML;

    // Mostrar loading en el botón
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;

    $.ajax({
        url: 'ajax/ia_config_api_handler.php',
        method: 'POST',
        data: {
            accion: 'test',
            id: id
        },
        dataType: 'json',
        success: function (response) {
            mostrarModalResultado(response.success, response.message);
        },
        error: function () {
            mostrarModalResultado(false, 'Error de comunicación con el servidor');
        },
        complete: function () {
            // Revertir estado del botón
            btn.innerHTML = originalIcon;
            btn.disabled = false;
        }
    });
}

/**
 * Muestra el modal de resultado con estilo dinámico
 */
function mostrarModalResultado(success, message) {
    const header = document.getElementById('pingModalHeader');
    const iconDiv = document.getElementById('pingModalIcon');
    const title = document.getElementById('pingModalTitle');
    const msg = document.getElementById('pingModalMessage');

    if (success) {
        header.className = 'modal-header border-0 bg-success';
        iconDiv.innerHTML = '<i class="fas fa-check-circle text-success pulse"></i>';
        title.textContent = '¡Conexión Exitosa!';
    } else {
        header.className = 'modal-header border-0 bg-danger';
        iconDiv.innerHTML = '<i class="fas fa-times-circle text-danger shake"></i>';
        title.textContent = 'Error de Conexión';
    }

    msg.textContent = message;

    const modal = new bootstrap.Modal(document.getElementById('pingResultModal'));
    modal.show();
}

/**
 * Reinicia el límite diario de un proveedor
 */
function reiniciarLimite(id) {
    if (!confirm('¿Estás seguro de que deseas reiniciar el límite diario de este proveedor?')) return;

    const btn = event.currentTarget;
    const originalIcon = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;

    $.ajax({
        url: 'ajax/ia_config_api_handler.php',
        method: 'POST',
        data: {
            accion: 'reiniciar_limite',
            id: id
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.message);
                btn.innerHTML = originalIcon;
                btn.disabled = false;
            }
        },
        error: function () {
            alert('Error de comunicación con el servidor');
            btn.innerHTML = originalIcon;
            btn.disabled = false;
        }
    });
}

/**
 * Cambia el estado activo/inactivo de un proveedor mediante un switch
 */

function toggleStatus(id, checkbox) {
    const activa = checkbox.checked ? 1 : 0;
    
    $.ajax({
        url: 'ajax/ia_config_api_handler.php',
        method: 'POST',
        data: {
            accion: 'toggle_status',
            id: id,
            activa: activa
        },
        dataType: 'json',
        success: function(response) {
            if (!response.success) {
                alert('Error al actualizar estado: ' + response.message);
                checkbox.checked = !checkbox.checked; // Revertir
            }
        },
        error: function() {
            alert('Error de comunicación con el servidor');
            checkbox.checked = !checkbox.checked; // Revertir
        }
    });
}

// Lógica de Filtros (Client-side para esta página pequeña)
let filtrosActivos = {};
let panelFiltroAbierto = null;

function toggleFilter(icon) {
    const th = $(icon).closest('th');
    const columna = th.data('column');
    const tipo = th.data('type');

    if (panelFiltroAbierto === columna) {
        cerrarTodosFiltros();
        return;
    }

    cerrarTodosFiltros();
    crearPanelFiltro(th, columna, tipo, icon);
    panelFiltroAbierto = columna;
    $(icon).addClass('active');
}

function cerrarTodosFiltros() {
    $('.filter-panel').remove();
    $('.filter-icon').removeClass('active');
    panelFiltroAbierto = null;
}

function crearPanelFiltro(th, columna, tipo, icon) {
    const panel = $('<div class="filter-panel show"></div>');
    const valorActual = filtrosActivos[columna] || '';

    panel.append(`<span class="filter-section-title">Filtrar ${columna}:</span>`);

    if (tipo === 'text') {
        panel.append(`
            <input type="text" class="filter-search" placeholder="Buscar..." 
                   value="${valorActual}" oninput="aplicarFiltro('${columna}', this.value)">
        `);
    } else if (tipo === 'list') {
        const opciones = obtenerOpcionesUnicas(columna);
        let html = '<div class="filter-options mt-2" style="max-height: 200px; overflow-y: auto;">';
        opciones.forEach(opt => {
            const checked = (filtrosActivos[columna] && filtrosActivos[columna].includes(opt)) ? 'checked' : '';
            html += `
                <div class="form-check small">
                    <input class="form-check-input" type="checkbox" value="${opt}" ${checked} 
                           onchange="toggleOpcionFiltro('${columna}', '${opt}', this.checked)">
                    <label class="form-check-label">${opt}</label>
                </div>
            `;
        });
        html += '</div>';
        panel.append(html);
    }

    panel.append(`
        <div class="filter-actions">
            <button class="filter-btn-clear" onclick="limpiarFiltro('${columna}')">Limpiar</button>
        </div>
    `);

    $('body').append(panel);
    posicionarPanel(panel, icon);
}

function posicionarPanel(panel, icon) {
    const offset = $(icon).offset();
    panel.css({
        top: (offset.top + 25) + 'px',
        left: (offset.left - 200) + 'px'
    });
}

function aplicarFiltro(columna, valor) {
    if (valor.trim() === '') {
        delete filtrosActivos[columna];
    } else {
        filtrosActivos[columna] = valor.toLowerCase();
    }
    ejecutarFiltrado();
}

function toggleOpcionFiltro(columna, valor, checked) {
    if (!filtrosActivos[columna]) filtrosActivos[columna] = [];
    
    if (checked) {
        filtrosActivos[columna].push(valor);
    } else {
        filtrosActivos[columna] = filtrosActivos[columna].filter(v => v !== valor);
        if (filtrosActivos[columna].length === 0) delete filtrosActivos[columna];
    }
    ejecutarFiltrado();
}

function limpiarFiltro(columna) {
    delete filtrosActivos[columna];
    cerrarTodosFiltros();
    ejecutarFiltrado();
}

function ejecutarFiltrado() {
    const rows = $('table tbody tr');
    let visibles = 0;

    rows.each(function() {
        if ($(this).find('td').length < 2) return; // Saltar mensaje vacío

        let mostrar = true;
        const proveedor = $(this).find('td:eq(0)').text().trim().toLowerCase();
        const correo = $(this).find('td:eq(1)').text().trim().toLowerCase();
        const activa = $(this).find('.form-check-input').prop('checked') ? 'SI' : 'NO';
        const estado = $(this).find('td:eq(4)').text().trim();

        // Filtro Texto: Proveedor
        if (filtrosActivos.proveedor && !proveedor.includes(filtrosActivos.proveedor)) mostrar = false;
        
        // Filtro Texto: Correo
        if (filtrosActivos.cuenta_correo && !correo.includes(filtrosActivos.cuenta_correo)) mostrar = false;

        // Filtro Lista: Activa
        if (filtrosActivos.activa && !filtrosActivos.activa.includes(activa)) mostrar = false;

        // Filtro Lista: Estado
        if (filtrosActivos.estado && !filtrosActivos.estado.includes(estado)) mostrar = false;

        $(this).toggle(mostrar);
        if (mostrar) visibles++;
    });

    // Actualizar iconos
    $('.filter-icon').removeClass('has-filter');
    Object.keys(filtrosActivos).forEach(col => {
        $(`th[data-column="${col}"] .filter-icon`).addClass('has-filter');
    });
}

function obtenerOpcionesUnicas(columna) {
    let opciones = [];
    if (columna === 'activa') return ['SI', 'NO'];
    if (columna === 'estado') return ['DISPONIBLE', 'AGOTADA'];
    
    // Para otros (ej: proveedor si fuera lista)
    const index = $(`th[data-column="${columna}"]`).index();
    $(`table tbody tr`).each(function() {
        const val = $(this).find(`td:eq(${index})`).text().trim();
        if (val && !opciones.includes(val)) opciones.push(val);
    });
    return opciones;
}

// Cerrar al hacer clic fuera
$(document).on('mousedown', function(e) {
    if (!$(e.target).closest('.filter-panel, .filter-icon').length) {
        cerrarTodosFiltros();
    }
});

