// postulacion_plazas_activas.js

let registrosPorPagina = 10;

document.addEventListener('DOMContentLoaded', function () {
    cargarPlazas();
});

async function cargarPlazas() {
    try {
        const response = await fetch('ajax/postulacion_plazas_activas_get_datos.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                registros_por_pagina: registrosPorPagina
            })
        });

        const data = await response.json();

        if (data.success) {
            renderizarTabla(data.datos);
            document.getElementById('registrosMostrados').textContent = data.datos.length;

            // Renderizar indicadores si existen
            if (data.indicadores) {
                document.getElementById('indicadorPlazasAbiertas').textContent = data.indicadores.plazas_abiertas;
                document.getElementById('indicadorEnEntrevista').textContent = data.indicadores.en_entrevista;
                document.getElementById('indicadorEnEleccion').textContent = data.indicadores.en_eleccion;
                document.getElementById('indicadorTotalCubiertas').textContent = data.indicadores.total_cubiertas;
            }
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error al cargar plazas:', error);
        Swal.fire('Error', 'No se pudieron cargar las plazas activas', 'error');
    }
}

function renderizarTabla(datos) {
    const tbody = document.getElementById('tablaPlazasBody');
    tbody.innerHTML = '';

    if (datos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No hay plazas activas en este momento</td></tr>';
        return;
    }

    datos.forEach(plaza => {
        const urgenciaTextos = ['', 'BAJA', 'MEDIA', 'ALTA', 'CRÍTICO'];
        const urgenciaTexto = urgenciaTextos[plaza.nivel_urgencia];
        const urgenciaClase = `urgencia-badge-${plaza.nivel_urgencia}`;

        // Construir ubicación
        let ubicacion = '';
        if (plaza.es_agrupado) {
            ubicacion = `Múltiples sucursales (${plaza.departamento_nombre})`;
        } else if (plaza.sucursal_nombre) {
            ubicacion = plaza.sucursal_nombre;
            if (plaza.departamento_nombre) {
                ubicacion += ', ' + plaza.departamento_nombre;
            }
        } else {
            ubicacion = 'Corporativo - Central';
        }

        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="text-start">
                <strong>${plaza.nombre_cargo}</strong>
                <br><small class="text-muted">${ubicacion}</small>
            </td>
            <td class="text-start">${plaza.area || '-'}</td>
            <td>
                <span class="badge badge-plazas">${plaza.plazas_abiertas}</span>
            </td>
            <td>
                <span class="badge badge-cvs">${plaza.cvs_recibidos}</span>
            </td>
            <td>C$ ${formatearNumero(plaza.salario_propuesto)}</td>
            <td>
                <span class="badge ${urgenciaClase}">${urgenciaTexto}</span>
            </td>
            <td>
                <a href="https://talento.batidospitaya.com/postular.php?plaza=${plaza.id_plaza}&cargo=${plaza.id_cargo}&sucursal=${plaza.id_sucursal}" target="_blank" class="btn btn-sm btn-outline-primary btn-action" title="Link de postulación">
                    <i class="bi bi-link-45deg"></i>
                </a>
                <button class="btn btn-sm btn-info btn-action" onclick="verCandidatos(${plaza.id_plaza})" title="Ver candidatos">
                    <i class="bi bi-eye me-1"></i>Ver
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function formatearNumero(numero) {
    return parseFloat(numero).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

function verCandidatos(idPlaza) {
    window.location.href = `postulacion_candidatos_plaza.php?plaza_id=${idPlaza}`;
}

function cambiarRegistrosPorPagina() {
    registrosPorPagina = parseInt(document.getElementById('registrosPorPagina').value);
    cargarPlazas();
}