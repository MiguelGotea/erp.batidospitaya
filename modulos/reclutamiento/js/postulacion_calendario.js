// postulacion_calendario.js

let fechaActual = new Date();
let entrevistas = [];
let filtroEntrevistador = 'todos';

const mesesNombres = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
const diasSemana = ['LUNES', 'MARTES', 'MIÉRCOLES', 'JUEVES', 'VIERNES', 'SÁBADO', 'DOMINGO'];

document.addEventListener('DOMContentLoaded', function () {
    configurarFiltros();
    cargarEntrevistas();
    if (document.getElementById('filtroEntrevistador')) {
        cargarEntrevistadoresFiltro();
    }
});

async function cargarEntrevistadoresFiltro() {
    try {
        const response = await fetch('ajax/postulacion_detalle_candidato_get_entrevistadores.php');
        const data = await response.json();
        if (data.success) {
            const select = document.getElementById('filtroEntrevistador');
            data.datos.forEach(e => {
                const opt = document.createElement('option');
                opt.value = e.CodOperario;
                opt.textContent = e.nombre_completo;
                select.appendChild(opt);
            });
        }
    } catch (e) {
        console.error('Error cargando filtradores:', e);
    }
}

function configurarFiltros() {
    const select = document.getElementById('filtroEntrevistador');
    if (select) {
        select.addEventListener('change', (e) => {
            filtroEntrevistador = e.target.value;
            cargarEntrevistas();
        });
    }
}

function parsearFechaLocal(fechaString) {
    const partes = fechaString.split('-');
    return new Date(parseInt(partes[0]), parseInt(partes[1]) - 1, parseInt(partes[2]));
}

async function cargarEntrevistas() {
    try {
        const response = await fetch('ajax/postulacion_calendario_get_entrevistas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ entrevistador_id: filtroEntrevistador })
        });
        const data = await response.json();
        if (data.success) {
            entrevistas = data.entrevistas;
            renderizarCalendarioSemanal();
            renderizarAgendaHoy();
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

function getLunesDeSemana(d) {
    d = new Date(d);
    let day = d.getDay(),
        diff = d.getDate() - day + (day === 0 ? -6 : 1);
    return new Date(d.setDate(diff));
}

function renderizarCalendarioSemanal() {
    const lunes = getLunesDeSemana(fechaActual);
    const domingo = new Date(lunes);
    domingo.setDate(lunes.getDate() + 6);

    // Actualizar Encabezados
    document.getElementById('mesActual').textContent = `${mesesNombres[lunes.getMonth()]} ${lunes.getFullYear()}`;
    document.getElementById('rangoSemana').textContent = `Del ${lunes.getDate()} de ${mesesNombres[lunes.getMonth()]} al ${domingo.getDate()} de ${mesesNombres[domingo.getMonth()]}`;

    const container = document.getElementById('calendario');
    let html = '<div class="calendario-header">';
    diasSemana.forEach(dia => html += `<div class="dia-semana">${dia}</div>`);
    html += '</div><div class="calendario-grid" style="grid-template-rows: auto;">';

    for (let i = 0; i < 7; i++) {
        const dia = new Date(lunes);
        dia.setDate(lunes.getDate() + i);
        const esHoy = esMismoDia(dia, new Date());
        const entrevistasDia = obtenerEntrevistasDelDia(dia);

        html += `<div class="dia-celda ${esHoy ? 'hoy' : ''}" style="min-height: 400px; padding: 10px;">
                    <div class="dia-numero text-primary fw-bold mb-2">${dia.getDate()}</div>`;

        entrevistasDia.forEach(e => {
            const hora = e.hora_entrevista.substring(0, 5);
            html += `<div class="evento-item" onclick="verDetalleCandidato(${e.id_postulacion || 0})">
                        <div class="hora-evento">${hora}</div>
                        <div class="nombre-postulante">${e.candidato_nombre}</div>
                        <div class="cargo-evento">${e.cargo_nombre}</div>
                     </div>`;
        });
        html += '</div>';
    }
    html += '</div>';
    container.innerHTML = html;
}

function verDetalleCandidato(id) {
    if (id > 0) window.location.href = `postulacion_detalle_candidato.php?id=${id}`;
}

function renderizarAgendaHoy() {
    const hoy = new Date();
    const entrevistasHoy = obtenerEntrevistasDelDia(hoy);
    const container = document.getElementById('agendaHoy');

    if (entrevistasHoy.length === 0) {
        container.innerHTML = '<div class="empty-state"><i class="bi bi-calendar-check"></i><p>Sin citas para hoy</p></div>';
        return;
    }

    let html = '';
    entrevistasHoy.forEach(e => {
        let botones = '';
        if (!e.rh_eval_id) {
            botones += `<a href="postulacion_evaluacion_rh.php?id=${e.id_postulacion}" class="btn btn-sm btn-primary mt-2 w-100">
                            <i class="bi bi-person-check me-1"></i>Entrevista RH
                        </a>`;
        } else if (e.rh_veredicto === 'Aprobado' && !e.jefe_eval_id) {
            botones += `<a href="postulacion_evaluacion_jefe.php?id=${e.id_postulacion}" class="btn btn-sm btn-info text-white mt-2 w-100">
                            <i class="bi bi-person-workspace me-1"></i>Entrevista Jefe
                        </a>`;
        }

        html += `
            <div class="entrevista-card border-start border-4 border-success mb-3 shadow-sm p-3 rounded bg-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="fw-bold d-block h5 mb-0">${e.hora_entrevista.substring(0, 5)}</span>
                        <span class="badge bg-light text-success border border-success small">${e.modalidad_entrevista}</span>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary border-0" onclick="verDetalleCandidato(${e.id_postulacion})">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div class="mt-2 fw-bold text-primary cursor-pointer" onclick="verDetalleCandidato(${e.id_postulacion})" style="cursor: pointer;">${e.candidato_nombre}</div>
                <div class="small text-muted fw-bold">${e.cargo_nombre}</div>
                <div class="mt-2 small text-secondary"><i class="bi bi-person-badge me-1"></i>${e.entrevistador_nombre}</div>
                ${botones}
            </div>`;
    });
    container.innerHTML = html;
}

function obtenerEntrevistasDelDia(fecha) {
    return entrevistas.filter(e => {
        if (!e.fecha_entrevista) return false;
        try {
            return esMismoDia(parsearFechaLocal(e.fecha_entrevista), fecha);
        } catch (err) {
            return false;
        }
    }).sort((a, b) => (a.hora_entrevista || '').localeCompare(b.hora_entrevista || ''));
}

function esMismoDia(f1, f2) {
    return f1.getDate() === f2.getDate() && f1.getMonth() === f2.getMonth() && f1.getFullYear() === f2.getFullYear();
}

function cambiarSemana(dir) {
    fechaActual.setDate(fechaActual.getDate() + (dir * 7));
    cargarEntrevistas();
}

function irHoy() {
    fechaActual = new Date();
    cargarEntrevistas();
}

// Compatibilidad con funciones viejas si se llaman de algún lado
function renderizarCalendario() { renderizarCalendarioSemanal(); }
function cambiarMes(dir) { cambiarSemana(dir); }
function cambiarVista(v) { /* Forzado a semana */ }
