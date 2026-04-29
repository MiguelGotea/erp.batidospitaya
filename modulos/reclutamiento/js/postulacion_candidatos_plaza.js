// postulacion_candidatos_plaza.js

let registrosPorPagina = 10;
let paginaActual = 1;
let datosSolicitadosGlobal = [];
let candidatosGlobal = []; // Para búsqueda rápida en modales

document.addEventListener('DOMContentLoaded', function () {
    cargarCandidatos();
});

async function cargarCandidatos() {
    try {
        const response = await fetch('ajax/postulacion_candidatos_plaza_get_datos.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_plaza: idPlaza,
                registros_por_pagina: 9999 // Cargamos todo; la paginación es client-side para g1
            })
        });

        const data = await response.json();
        console.log('Datos de candidatos:', data);

        if (data.success) {
            renderizarTabla(data.datos);
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error al cargar candidatos:', error);
        Swal.fire('Error', 'No se pudieron cargar los candidatos', 'error');
    }
}

function renderizarTabla(datos) {
    candidatosGlobal = datos; // Guardar para acceso desde modales
    const bodySolicitados  = document.getElementById('bodySolicitados');
    const bodyAprobados    = document.getElementById('bodyAprobados');
    const bodySeleccionados = document.getElementById('bodySeleccionados');

    bodySolicitados.innerHTML  = '';
    bodyAprobados.innerHTML    = '';
    bodySeleccionados.innerHTML = '';

    const strtolower = (str) => (str || '').toLowerCase().trim();
    const s = (c) => strtolower(c.status);

    // Clasificar candidatos
    const solicitados   = datos.filter(c => s(c) === 'solicitado');
    const aprobados     = datos.filter(c => s(c) === 'aprobado');
    const seleccionados = datos.filter(c => s(c) === 'seleccionado');
    const denegados     = datos.filter(c => s(c) === 'denegado');
    const rechazados    = datos.filter(c => s(c) === 'rechazado');

    // g1: Solo solicitados y rechazados que NO tienen aún evaluación de RH (aunque esto último es raro, pero por seguridad)
    // O mejor aún: g1 son los Pendientes de Validación.
    // El usuario dice: "Los que hayan sido rechazados desde postulacion_evaluacion_rh.php ... deberían quedarse en el grupo de 'Proceso de Entrevistas (En Embudo)'"
    const g1 = solicitados.filter(c => !parseInt(c.has_rh_eval)); 
    const g2 = aprobados.concat(denegados).concat(rechazados.filter(c => parseInt(c.has_rh_eval)));
    const g3 = seleccionados; 

    // Actualizar badges de conteo
    document.getElementById('badgeSeleccionados').textContent = `${g3.length} FINALISTAS`;
    document.getElementById('badgeAprobados').textContent     = `${g2.length} EN PROCESO`;
    document.getElementById('badgeSolicitados').textContent   = g1.length > 0 ? `${g1.length} PENDIENTES` : 'PENDIENTES';

    // =============================================
    // Helpers de renderizado
    // =============================================

    const getCandidatoCell = (candidato) => `
        <td>
            <div class="candidato-nombre">${candidato.nombre}</div>
            <div class="candidato-email">${candidato.direccion || candidato.correo || 'Sin información'}</div>
        </td>
    `;

    const getScoreCell = (score, veredicto, hasEval) => {
        if (!hasEval) {
            return `<td class="text-center"><span class="sin-eval">—</span></td>`;
        }
        const val = score ? parseFloat(score).toFixed(1) : '0.0';
        let claseNumero = 'score-pendiente';
        let claseBadge  = 'score-badge-pendiente';
        let textoVeredicto = 'PENDIENTE';

        if (veredicto) {
            const v = veredicto.toLowerCase();
            if (v === 'aprobado') {
                claseNumero = 'score-aprobado'; claseBadge = 'score-badge-aprobado'; textoVeredicto = 'APROBADO';
            } else if (v === 'rechazado' || v === 'descartado' || v === 'denegado') {
                claseNumero = 'score-rechazado'; claseBadge = 'score-badge-rechazado'; textoVeredicto = veredicto.toUpperCase();
            }
        }

        return `
            <td class="text-center">
                <div class="score-cell">
                    <span class="score-numero ${claseNumero}">${val}/5</span>
                    <span class="score-badge ${claseBadge}">${textoVeredicto}</span>
                </div>
            </td>
        `;
    };

    const getDocusCell = (progreso) => {
        const pct = parseInt(progreso || 0);
        let claseBar = pct >= 100 ? 'alta' : (pct >= 50 ? 'media' : 'baja');
        return `
            <td>
                <div class="docus-cell">
                    <div class="docus-barra-wrap">
                        <div class="docus-barra ${claseBar}" style="width:${pct}%"></div>
                    </div>
                    <span class="docus-pct">${pct}%</span>
                </div>
            </td>
        `;
    };

    const getCredencialesCell = (candidato) => {
        const progreso = parseInt(candidato.porcentaje_completitud || 0);
        if (progreso === 100) {
            return `
                <td class="text-center">
                    <a href="https://erp.batidospitaya.com/modulos/rh/nuevo_colaborador.php?id_postulacion=${candidato.id}"
                       class="btn-credenciales-activo" title="Crear Credenciales">
                        <i class="bi bi-key-fill"></i> Crear Credenciales
                    </a>
                </td>
            `;
        }
        return `
            <td class="text-center">
                <span class="btn-credenciales-deshabilitado" title="Formulario incompleto (${progreso}%)">
                    <i class="bi bi-key"></i> Crear Credenciales
                </span>
            </td>
        `;
    };

    const getEstadoActualCell = (candidato) => {
        const currentStatus = s(candidato);
        let claseEstado = '';
        let textoEstado = '';

        if (currentStatus === 'denegado') {
            claseEstado = 'estado-descartado'; textoEstado = 'Descartado';
        } else if (currentStatus === 'aprobado') {
            if (!candidato.jefe_score && !candidato.jefe_veredicto) {
                // Aprobado por RH, aún no evaluado por jefe
                claseEstado = 'estado-entrevista-rh'; textoEstado = 'Entrevista RRHH';
            } else {
                const jv = (candidato.jefe_veredicto || '').toLowerCase();
                if (jv === 'rechazado' || jv === 'descartado' || jv === 'denegado') {
                    claseEstado = 'estado-pendiente-aprob'; textoEstado = 'Pendiente de Aprobación';
                } else {
                    claseEstado = 'estado-entrevista-jefe'; textoEstado = 'Entrevista Jefe Directo';
                }
            }
        } else {
            claseEstado = 'estado-pendiente-aprob'; textoEstado = 'Pendiente';
        }

        return `<td class="text-center"><span class="estado-badge ${claseEstado}">${textoEstado}</span></td>`;
    };

    const getIABadge = (candidato) => {
        if (!candidato.match_porcentaje) {
            return `<button class="btn-ia-validar" onclick="validarConIA(${candidato.id})" title="Analizar con IA">✦ Validar con IA</button>`;
        }
        
        const match = parseFloat(candidato.match_porcentaje);
        let claseBadge = 'badge-ia-insuficiente';
        let textoLabel = 'Match No Suficiente';

        if (match >= 100) {
            claseBadge = 'badge-ia-premium';
            textoLabel = 'Match Perfecto';
        } else if (match >= 75) {
            claseBadge = 'badge-ia-alto';
            textoLabel = 'Match Alto';
        } else if (match >= 50) {
            claseBadge = 'badge-ia-medio';
            textoLabel = 'Match Medio';
        }

        return `<span class="${claseBadge}" style="cursor:pointer" onclick="verAnalisisDetallado(${candidato.id}, ${match})">✦ ${textoLabel} (${match}%)</span>`;
    };

    const getStatusBadge = (candidato) => {
        const st = s(candidato);
        const mapa = {
            'solicitado':   'status-solicitado',
            'aprobado':     'status-aprobado',
            'rechazado':    'status-rechazado',
            'seleccionado': 'status-seleccionado',
            'denegado':     'status-denegado',
            'contratado':   'status-contratado',
        };
        const clase = mapa[st] || 'status-solicitado';
        return `<span class="status-badge ${clase}">${candidato.status.toUpperCase()}</span>`;
    };

    // =============================================
    // Grupo 3: Seleccionados
    // =============================================
    if (g3.length === 0) {
        bodySeleccionados.innerHTML = `<tr><td colspan="6" class="estado-vacio">No hay candidatos seleccionados aún</td></tr>`;
    } else {
        g3.forEach(c => {
            const rhEstado  = (s(c) === 'seleccionado' || s(c) === 'contratado') ? 'aprobado' : null;
            const jefEstado = (s(c) === 'seleccionado' || s(c) === 'contratado') ? 'aprobado' : null;
            
            const linkGenerado = c.solicitud_token ? true : false;
            let btnLink = '';
            
            if (linkGenerado) {
                btnLink = `
                    <div class="d-flex flex-column gap-1 align-items-center">
                        <div class="d-flex align-items-center gap-1">
                            <button class="btn-accion-icono ${c.link_status === 'activo' ? '' : 'text-danger'}" 
                                    onclick="copiarLinkSolicitud('${c.solicitud_token}', '${c.codigo_acceso}')" 
                                    title="${c.link_status === 'activo' ? 'Copiar Link' : 'Link Deshabilitado'}">
                                <i class="bi ${c.link_status === 'activo' ? 'bi-link-45deg' : 'bi-link-45deg'}"></i>
                            </button>
                            <button class="btn-accion-icono ${c.link_status === 'activo' ? 'text-success' : 'text-secondary'}" 
                                    onclick="toggleLinkStatus(${c.id}, '${c.link_status}')" 
                                    title="${c.link_status === 'activo' ? 'Deshabilitar Link' : 'Habilitar Link'}">
                                <i class="bi ${c.link_status === 'activo' ? 'bi-toggle-on' : 'bi-toggle-off'}"></i>
                            </button>
                        </div>
                        <small class="text-primary fw-bold" style="cursor:pointer" onclick="verCodigo('${c.codigo_acceso || ''}')" title="Ver Clave">
                            ${c.codigo_acceso && c.codigo_acceso !== 'undefined' ? c.codigo_acceso : '---'}
                        </small>
                    </div>
                `;
            } else {
                btnLink = `
                    <button class="btn-accion-icono text-primary" onclick="generarLinkSolicitud(${c.id})" title="Generar Link de Solicitud">
                        <i class="bi bi-plus-circle"></i>
                    </button>
                `;
            }

            const row = document.createElement('tr');
            row.innerHTML = `
                ${getCandidatoCell(c)}
                ${getScoreCell(c.rh_score, c.rh_veredicto, c.has_rh_eval)}
                ${getScoreCell(c.jefe_score, c.jefe_veredicto, c.has_jefe_eval)}
                ${getDocusCell(c.porcentaje_completitud)}
                ${getCredencialesCell(c)}
                <td class="text-center">
                    <div class="d-flex gap-1 justify-content-center align-items-center">
                        ${btnLink}
                        <button class="btn-accion-icono" onclick="verPerfil(${c.id})" title="Ver perfil">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </td>
            `;
            bodySeleccionados.appendChild(row);
        });
    }

    // =============================================
    // Grupo 2: En embudo
    // =============================================
    if (g2.length === 0) {
        bodyAprobados.innerHTML = `<tr><td colspan="5" class="estado-vacio">No hay candidatos en proceso de entrevista</td></tr>`;
    } else {
        g2.forEach(c => {
            const rhVeredicto = (c.rh_veredicto || '').toLowerCase();
            
            let btnAccion = '';
            
            // Botón Entrevista RH (si no tiene evaluación de RH)
            if (!parseInt(c.has_rh_eval)) {
                btnAccion += `<a href="postulacion_evaluacion_rh.php?id=${c.id}" class="btn-ia-validar px-2 py-1" style="font-size: 11px;">Entrevista RH</a>`;
            }
            
            // Botón Entrevista Jefe (si RH aprobó)
            if (rhVeredicto === 'aprobado') {
                btnAccion += `<a href="postulacion_evaluacion_jefe.php?id=${c.id}" class="btn-ia-validar px-2 py-1 bg-primary border-primary" style="font-size: 11px;">Entrevista Jefe</a>`;
            }

            const row = document.createElement('tr');
            row.innerHTML = `
                ${getCandidatoCell(c)}
                ${getScoreCell(c.rh_score, c.rh_veredicto, c.has_rh_eval)}
                ${getScoreCell(c.jefe_score, c.jefe_veredicto, c.has_jefe_eval)}
                ${getEstadoActualCell(c)}
                <td class="text-center">
                    <div class="d-flex gap-1 justify-content-center align-items-center">
                         ${btnAccion}
                        <button class="btn-accion-icono" onclick="verPerfil(${c.id})" title="Ver perfil">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </td>
            `;
            bodyAprobados.appendChild(row);
        });
    }

    // =============================================
    // Grupo 1: Pendientes de validación (con paginación)
    // =============================================
    datosSolicitadosGlobal = g1;
    paginaActual = 1;
    renderizarPaginaG1();
}

function renderizarPaginaG1() {
    const bodySolicitados = document.getElementById('bodySolicitados');
    bodySolicitados.innerHTML = '';

    const datos = datosSolicitadosGlobal;
    const totalPags = Math.ceil(datos.length / registrosPorPagina);
    const inicio = (paginaActual - 1) * registrosPorPagina;
    const fin    = inicio + registrosPorPagina;
    const pagina = datos.slice(inicio, fin);

    if (pagina.length === 0) {
        bodySolicitados.innerHTML = `<tr><td colspan="7" class="estado-vacio">No hay candidatos en esta etapa</td></tr>`;
    } else {
        pagina.forEach(c => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <div class="candidato-nombre">${c.nombre}</div>
                    <div class="candidato-email">${c.direccion || c.correo || ''}</div>
                </td>
                <td>${formatearFecha(c.fecha_postulacion)}</td>
                <td class="text-center">${c.experiencia_anos ? `${c.experiencia_anos} años` : (c.experiencia_laboral ? c.experiencia_laboral.substring(0,20) : '<span class="text-muted">—</span>')}</td>
                <td class="text-center">
                    ${c.ruta_cv
                        ? `<a href="${c.ruta_cv}" target="_blank" class="btn-cv-icon" title="Ver CV"><i class="bi bi-file-earmark-pdf-fill"></i></a>`
                        : `<span class="sin-eval">—</span>`}
                </td>
                <td class="text-center">${getIABadge(c)}</td>
                <td class="text-center">${getStatusBadge(c)}</td>
                <td class="text-center">
                    <button class="btn-ver-perfil" onclick="verPerfil(${c.id})">Ver Perfil</button>
                </td>
            `;
            bodySolicitados.appendChild(row);
        });
    }

    renderizarControlesPaginacion(totalPags);
}

function getIABadge(candidato) {
    if (!candidato.match_porcentaje) {
        return `<button class="btn-ia-validar" onclick="validarConIA(${candidato.id})" title="Analizar con IA">✦ Validar con IA</button>`;
    }
    
    const match = parseFloat(candidato.match_porcentaje);
    let claseBadge = 'badge-ia-insuficiente';
    let textoLabel = 'Match No Suficiente';

    if (match >= 100) {
        claseBadge = 'badge-ia-premium';
        textoLabel = 'Match Perfecto';
    } else if (match >= 75) {
        claseBadge = 'badge-ia-alto';
        textoLabel = 'Match Alto';
    } else if (match >= 50) {
        claseBadge = 'badge-ia-medio';
        textoLabel = 'Match Medio';
    }

    return `<span class="${claseBadge}" style="cursor:pointer" onclick="verAnalisisDetallado(${candidato.id}, ${match})">✦ ${textoLabel} (${match}%)</span>`;
}

function getStatusBadge(candidato) {
    const st = (candidato.status || '').toLowerCase().trim();
    const mapa = {
        'solicitado':   'status-solicitado',
        'aprobado':     'status-aprobado',
        'rechazado':    'status-rechazado',
        'seleccionado': 'status-seleccionado',
        'denegado':     'status-denegado',
        'contratado':   'status-contratado',
    };
    const clase = mapa[st] || 'status-solicitado';
    return `<span class="status-badge ${clase}">${(candidato.status || '').toUpperCase()}</span>`;
}

function renderizarControlesPaginacion(totalPags) {
    const contenedor = document.getElementById('controlesPaginacion');
    if (!contenedor) return;
    contenedor.innerHTML = '';

    // Botón anterior
    const btnPrev = document.createElement('button');
    btnPrev.className = 'paginacion-btn';
    btnPrev.innerHTML = '<i class="bi bi-chevron-left"></i>';
    btnPrev.disabled = paginaActual <= 1;
    btnPrev.onclick = () => { paginaActual--; renderizarPaginaG1(); };
    contenedor.appendChild(btnPrev);

    // Números de página
    for (let i = 1; i <= totalPags; i++) {
        const btn = document.createElement('button');
        btn.className = 'paginacion-btn' + (i === paginaActual ? ' activo' : '');
        btn.textContent = i;
        btn.onclick = ((p) => () => { paginaActual = p; renderizarPaginaG1(); })(i);
        contenedor.appendChild(btn);
    }

    // Si no hay páginas, mostrar al menos la 1
    if (totalPags === 0) {
        const btn = document.createElement('button');
        btn.className = 'paginacion-btn activo';
        btn.textContent = '1';
        contenedor.appendChild(btn);
    }

    // Botón siguiente
    const btnNext = document.createElement('button');
    btnNext.className = 'paginacion-btn';
    btnNext.innerHTML = '<i class="bi bi-chevron-right"></i>';
    btnNext.disabled = paginaActual >= totalPags;
    btnNext.onclick = () => { paginaActual++; renderizarPaginaG1(); };
    contenedor.appendChild(btnNext);
}

function formatearFecha(fecha) {
    if (!fecha) return '—';
    const d = new Date(fecha);
    const dia = d.getDate().toString().padStart(2, '0');
    const meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    const mes = meses[d.getMonth()];
    const año = d.getFullYear();
    return `${dia} ${mes}, ${año}`;
}

function verPerfil(idCandidato) {
    window.location.href = `postulacion_detalle_candidato.php?id=${idCandidato}`;
}

async function validarConIA(idCandidato) {
    try {
        const result = await Swal.fire({
            title: '¿Validar con IA?',
            text: "Se analizará el CV contra el perfil del puesto usando Inteligencia Artificial. Esto puede demorar unos segundos.",
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Sí, validar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#667eea'
        });

        if (result.isConfirmed) {
            Swal.fire({
                title: 'Analizando CV...',
                html: 'Nuestro motor de IA está procesando la información.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const response = await fetch('ajax/postulacion_cv_ia_validar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    id_postulacion: idCandidato,
                    id_plaza: idPlaza // Usamos la variable global idPlaza
                })
            });

            const data = await response.json();

            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Análisis Completado!',
                    text: `El candidato tiene un nivel de cumplimiento del ${data.puntaje}%`,
                    timer: 2000,
                    showConfirmButton: false
                });
                cargarCandidatos(); // Recargar la tabla
            } else {
                throw new Error(data.message);
            }
        }
    } catch (error) {
        console.error('Error IA:', error);
        Swal.fire('Error', 'No se pudo completar el análisis: ' + error.message, 'error');
    }
}

function verAnalisisDetallado(idCandidato, match) {
    const candi = candidatosGlobal.find(c => c.id == idCandidato);
    if (!candi || !candi.analisis_ia) {
        Swal.fire('Atención', 'No se encontró el análisis detallado para este candidato.', 'warning');
        return;
    }

    let info;
    try {
        // Al parsear el JSON, JS interpreta automáticamente los caracteres Unicode \uXXXX
        info = typeof candi.analisis_ia === 'string' ? JSON.parse(candi.analisis_ia) : candi.analisis_ia;
    } catch (e) {
        console.error("Error parseando analisis_ia:", e);
        Swal.fire('Error', 'El formato del análisis guardado no es válido.', 'error');
        return;
    }

    const matchColor = match >= 100 ? '#10b981' : (match >= 75 ? '#3b82f6' : (match >= 50 ? '#f59e0b' : '#ef4444'));

    let fortalezasHtml = info.fortalezas && info.fortalezas.length > 0 
        ? info.fortalezas.map(f => `<li class="mb-2 d-flex align-items-start"><i class="bi bi-check-circle-fill text-success me-2 mt-1"></i><span>${f}</span></li>`).join('')
        : '<li>No se especificaron fortalezas</li>';

    let brechasHtml = info.brechas && info.brechas.length > 0 
        ? info.brechas.map(b => `<li class="mb-2 d-flex align-items-start"><i class="bi bi-exclamation-triangle-fill text-warning me-2 mt-1"></i><span>${b}</span></li>`).join('')
        : '<li>Sin brechas críticas detectadas</li>';

    Swal.fire({
        title: `<div class="p-2" style="color: ${matchColor}; font-weight: 800; border-bottom: 2px solid ${matchColor}22;">Informe de Compatibilidad IA</div>`,
        html: `
            <div class="text-start mt-3" style="font-size: 0.95rem; line-height: 1.5; max-height: 70vh; overflow-y: auto; overflow-x: hidden;">
                <div class="d-flex justify-content-between align-items-center mb-4 p-3 bg-light rounded-3 shadow-sm">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Puntaje General</div>
                        <div style="font-size: 2.2rem; font-weight: 800; color: ${matchColor}; line-height: 1;">${match}%</div>
                    </div>
                    <div class="text-end">
                        <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Veredicto IA</div>
                        <div class="badge p-2 px-3 mt-1" style="background-color: ${matchColor}; color: white; font-size: 0.9rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">${info.recomendacion || 'Indefinida'}</div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <h6 class="fw-bold text-success border-bottom pb-2 mb-3 d-flex align-items-center"><i class="bi bi-plus-circle-fill me-2"></i>FORTALEZAS</h6>
                        <ul class="list-unstyled small ps-1" style="color: #374151;">
                            ${fortalezasHtml}
                        </ul>
                    </div>
                    <div class="col-md-6 mb-4">
                        <h6 class="fw-bold text-danger border-bottom pb-2 mb-3 d-flex align-items-center"><i class="bi bi-dash-circle-fill me-2"></i>BRECHAS</h6>
                        <ul class="list-unstyled small ps-1" style="color: #374151;">
                            ${brechasHtml}
                        </ul>
                    </div>
                </div>

                <div class="mt-2">
                    <h6 class="fw-bold text-primary border-bottom pb-2 mb-3 d-flex align-items-center"><i class="bi bi-chat-left-text-fill me-2"></i>ANÁLISIS DE IDONEIDAD</h6>
                    <div class="p-3 bg-light rounded-3 shadow-sm italic text-muted" style="border-left: 5px solid #3b82f6; background-color: #f8fafc !important;">
                        <span style="font-style: italic; color: #475569;">"${info.explicacion || 'Sin explicación disponible.'}"</span>
                    </div>
                </div>
            </div>
        `,
        width: '750px',
        confirmButtonText: 'Entendido',
        confirmButtonColor: '#4f46e5',
        showCloseButton: true,
        customClass: {
            popup: 'rounded-4 shadow-lg border-0',
            confirmButton: 'px-4 py-2 fw-bold text-uppercase letter-spacing-1'
        }
    });
}

async function generarLinkSolicitud(idPostulacion) {
    try {
        const result = await Swal.fire({
            title: '¿Generar link de solicitud?',
            text: "Se creará un acceso único para que el postulante complete su información.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, generar',
            cancelButtonText: 'Cancelar'
        });

        if (result.isConfirmed) {
            Swal.showLoading();
            const response = await fetch('ajax/generar_token_solicitud.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_postulacion: idPostulacion })
            });
            const data = await response.json();

            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Link y Código Generado!',
                    html: `
                        <p>El acceso ha sido creado con éxito.</p>
                        <div class="alert alert-info border-0 shadow-sm">
                            <label class="small text-muted d-block mb-1">Código de Verificación:</label>
                            <h3 class="mb-0 fw-bold letter-spacing-2 text-primary">${data.codigo_acceso}</h3>
                        </div>
                        <p class="small text-muted mt-2">Copia este código y envíalo junto con el link al postulante.</p>
                    `,
                    confirmButtonText: 'Entendido'
                });
                cargarCandidatos();
            } else {
                throw new Error(data.message);
            }
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', 'No se pudo generar el link: ' + error.message, 'error');
    }
}

function copiarLinkSolicitud(token, codigo) {
    if (!token) return;
    const link = `https://talento.batidospitaya.com/solicitud_empleo.php?t=${token}`;
    navigator.clipboard.writeText(link).then(() => {
        Swal.fire({
            icon: 'success',
            title: '¡Link Copiado!',
            html: `
                <p>El link se ha copiado al portapapeles.</p>
                <div class="alert alert-light border shadow-sm py-2">
                    <label class="small text-muted d-block mb-1">Recuerda enviar el código:</label>
                    <h4 class="mb-0 fw-bold text-dark">${codigo || 'No generado'}</h4>
                </div>
            `,
            timer: 3000,
            showConfirmButton: true,
            confirmButtonText: 'Cerrar'
        });
    });
}

function verCodigo(codigo) {
    Swal.fire({
        title: 'Código de Verificación',
        html: `<h2 class="display-4 fw-bold text-primary">${codigo}</h2>`,
        confirmButtonText: 'Cerrar'
    });
}

async function toggleLinkStatus(idPostulacion, currentStatus) {
    const newStatus = currentStatus === 'activo' ? 'deshabilitado' : 'activo';
    const actionText = newStatus === 'activo' ? 'habilitar' : 'deshabilitar';

    try {
        const result = await Swal.fire({
            title: `¿${actionText.charAt(0).toUpperCase() + actionText.slice(1)} link?`,
            text: `El postulante ${newStatus === 'activo' ? 'podrá' : 'NO podrá'} acceder al formulario.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: `Sí, ${actionText}`,
            cancelButtonText: 'Cancelar'
        });

        if (result.isConfirmed) {
            Swal.showLoading();
            const response = await fetch('ajax/toggle_link_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_postulacion: idPostulacion, status: newStatus })
            });

            const data = await response.json();
            if (data.success) {
                Swal.fire('¡Actualizado!', `El link ha sido ${newStatus}.`, 'success');
                cargarCandidatos();
            } else {
                throw new Error(data.message);
            }
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', 'No se pudo cambiar el estatus del link', 'error');
    }
}

function cambiarRegistrosPorPagina() {
    registrosPorPagina = parseInt(document.getElementById('registrosPorPagina').value);
    paginaActual = 1;
    renderizarPaginaG1();
}