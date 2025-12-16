// public_html/modulos/mantenimiento/js/equipos_registro.js

document.addEventListener('DOMContentLoaded', function() {
    // Event listeners
    document.getElementById('formRegistro').addEventListener('submit', guardarEquipo);
    document.getElementById('ubicacion_inicial').addEventListener('change', toggleSucursal);
    
    // Calcular fecha vencimiento garantía automáticamente
    const fechaCompra = document.querySelector('input[name="fecha_compra"]');
    const mesesGarantia = document.querySelector('input[name="garantia_meses"]');
    const fechaVencimiento = document.querySelector('input[name="fecha_vencimiento_garantia"]');
    
    function calcularVencimiento() {
        if (fechaCompra.value && mesesGarantia.value) {
            const fecha = new Date(fechaCompra.value);
            fecha.setMonth(fecha.getMonth() + parseInt(mesesGarantia.value));
            fechaVencimiento.value = fecha.toISOString().split('T')[0];
        }
    }
    
    fechaCompra.addEventListener('change', calcularVencimiento);
    mesesGarantia.addEventListener('change', calcularVencimiento);
});

// Toggle mostrar sucursal
function toggleSucursal() {
    const ubicacion = document.getElementById('ubicacion_inicial').value;
    const grupoSucursal = document.getElementById('grupoSucursal');
    const selectSucursal = document.getElementById('sucursal_inicial_id');
    
    if (ubicacion === 'Sucursal') {
        grupoSucursal.style.display = 'block';
        selectSucursal.required = true;
    } else {
        grupoSucursal.style.display = 'none';
        selectSucursal.required = false;
        selectSucursal.value = '';
    }
}

// Guardar equipo
async function guardarEquipo(event) {
    event.preventDefault();
    
    const btnGuardar = document.getElementById('btnGuardar');
    btnGuardar.disabled = true;
    btnGuardar.innerHTML = '<span class="loading"></span> Guardando...';
    
    try {
        const formData = new FormData(document.getElementById('formRegistro'));
        
        const response = await fetch('ajax/equipos_registro_guardar.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Equipo registrado exitosamente');
            window.location.href = 'equipos_lista.php';
        } else {
            alert('Error: ' + data.message);
            btnGuardar.disabled = false;
            btnGuardar.innerHTML = 'Guardar Equipo';
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al guardar el equipo. Por favor intente nuevamente.');
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = 'Guardar Equipo';
    }
}