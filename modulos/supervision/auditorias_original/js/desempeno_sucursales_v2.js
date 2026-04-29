// js/desempeno_sucursales_v2.js

'use strict';

// ── Inicialización ───────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {
    cargarTabla();

    // Interceptar submit del formulario → carga AJAX sin recargar página
    const form = document.getElementById('dsv2-form');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            cargarTabla();
        });
    }

    // Igualar altura de las 8 tarjetas del modal cuando se abre
    const helpModal = document.getElementById('pageHelpModal');
    if (helpModal) {
        helpModal.addEventListener('shown.bs.modal', equalizarAlturasTarjetas);
    }

    // Re-igualar si cambia el tamaño de ventana mientras el modal está abierto
    window.addEventListener('resize', function () {
        const helpModal = document.getElementById('pageHelpModal');
        if (helpModal && helpModal.classList.contains('show')) {
            equalizarAlturasTarjetas();
        }
    });
});

// ── Carga de datos via AJAX ──────────────────────────────────────────────────

function cargarTabla() {
    const mes  = document.getElementById('mes').value;
    const anio = document.getElementById('anio').value;

    mostrarCargando(true);

    fetch('ajax/desempeno_sucursales_v2.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'mes=' + encodeURIComponent(mes) + '&anio=' + encodeURIComponent(anio),
    })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            mostrarCargando(false);
            if (data.success) {
                renderizarTabla(data.sucursales, data.totales);
                
                // Carga diferida del reporte de Looker Studio (solo la primera vez)
                if (typeof initLookerReport === 'function' && !window.lookerCargado) {
                    setTimeout(initLookerReport, 500); // 500ms de gracia para el renderizado
                    window.lookerCargado = true;
                }
            } else {
                mostrarError('Error al obtener datos: ' + (data.message || ''));
            }
        })
        .catch(function (err) {
            mostrarCargando(false);
            mostrarError('Error de conexión: ' + err.message);
        });
}

// ── Renderizado de tabla ─────────────────────────────────────────────────────

function renderizarTabla(sucursales, totales) {
    const tbody = document.getElementById('dsv2-tbody');
    if (!tbody) return;

    let html = '';

    sucursales.forEach(function (s) {
        html += '<tr>';

        // Sucursal
        html += '<td>' + escHtml(s.nombre) + '</td>';

        // Limpieza
        html += '<td style="text-align:center;">';
        if (s.limpieza_cantidad > 0) {
            html += '<div class="contenedor-valor">'
                + circulo(s.color_limpieza)
                + '<div>' + s.pct_limpieza
                + ' <span class="cantidad-auditorias">(' + s.limpieza_cantidad + ')</span>'
                + '</div>'
                + '<a href="' + s.link_limpieza + '" style="color:#51B8AC;margin-left:5px;display:none;">'
                + '<i class="fas fa-eye"></i></a>'
                + '</div>';
        } else {
            html += '--';
        }
        html += '</td>';

        // Personal
        html += '<td style="text-align:center;">';
        if (s.personal_cantidad > 0) {
            html += '<div class="contenedor-valor">'
                + circulo(s.color_personal)
                + '<div>' + s.pct_personal
                + ' <span class="cantidad-auditorias">(' + s.personal_cantidad + ')</span>'
                + '</div>'
                + '<a href="' + s.link_personal + '" style="color:#51B8AC;margin-left:5px;display:none;">'
                + '<i class="fas fa-eye"></i></a>'
                + '</div>';
        } else {
            html += '--';
        }
        html += '</td>';

        // Servicio
        html += '<td style="text-align:center;">';
        if (s.servicio_cantidad > 0) {
            html += '<div class="contenedor-valor">'
                + circulo(s.color_servicio)
                + '<div>' + s.pct_servicio
                + ' <span class="cantidad-auditorias">('+  s.servicio_cantidad + ')</span>'
                + '</div>'
                + '<a href="' + s.link_servicio + '" style="color:#51B8AC;margin-left:5px;display:none;">'
                + '<i class="fas fa-eye"></i></a>'
                + '</div>';
        } else {
            html += '--';
        }
        html += '</td>';

        // Membresías
        html += '<td style="text-align:center;">';
        if (s.cant_membresias > 0) {
            html += '<div class="contenedor-valor">'
                + circulo(s.color_membresia)
                + '<div>' + s.pct_membresia
                + ' <span class="cantidad-auditorias">(' + s.cant_membresias + ')</span>'
                + '</div>'
                + '</div>';
        } else {
            html += '--';
        }
        html += '</td>';

        // Tamaño Normal (solo porcentaje, sin paréntesis)
        html += '<td style="text-align:center;">';
        if (s.cant_total_tam > 0) {
            html += '<div class="contenedor-valor">'
                + circulo(s.color_tamano)
                + '<div>' + s.pct_tamano + '</div>'
                + '</div>';
        } else {
            html += '--';
        }
        html += '</td>';

        // Mostrador
        html += '<td style="text-align:center;">';
        if (s.pct_mostrador > 0) {
            html += '<div class="contenedor-valor">'
                + circulo(s.color_mostrador)
                + '<div>' + s.pct_mostrador + '</div>'
                + '</div>';
        } else {
            html += '--';
        }
        html += '</td>';

        // Reclamos — siempre mostrar (100% si no hay reclamos)
        html += '<td class="' + (s.reclamos_totales > 0 ? 'reclamos-clickable' : '') + '" style="text-align:center;'
            + (s.reclamos_totales > 0 ? 'cursor:pointer;' : '')
            + '"'
            + (s.reclamos_totales > 0 ? ' onclick="window.location.href=\'' + s.link_reclamos + '\'"' : '')
            + '>';
        
        var cantReclamos = s.reclamos_cantidad || 0;
        var totalReclamos = s.reclamos_totales || 0;
        
        html += '<div class="contenedor-valor">'
            + circulo(s.color_reclamos)
            + '<div>' + s.pct_reclamos.toFixed(0)
            + ' <span class="cantidad-auditorias">(' + cantReclamos + '/' + totalReclamos + ')</span></div>'
            + '</div>';
        html += '</td>';

        // Reseñas Google — siempre mostrar (0% si no hay reseñas)
        html += '<td style="text-align:center;">';
        html += '<div class="contenedor-valor">'
            + circulo(s.color_resenas)
            + '<div>' + s.pct_resenas
            + ' <span class="cantidad-auditorias">(' + (s.cant_resenas || 0) + ')</span>'
            + '</div>'
            + '</div>';
        html += '</td>';



        // Desempeño de Tienda
        html += '<td style="text-align:center;">';
        html += '<div class="contenedor-valor">'
            + circulo(s.color_desempeno)
            + '<div>' + s.pct_desempeno.toFixed(1) + '</div>'
            + '</div>';
        html += '</td>';

        // Cumplimiento de Ventas %
        html += '<td style="text-align:center;">' + s.factor_visual.toFixed(1) + '</td>';

        // Total % (Propina Interna)
        html += '<td style="text-align:center;" class="total-destacado">' + s.total_porcentaje.toFixed(1) + '</td>';

        html += '</tr>';
    });

    // ── Fila de totales ──────────────────────────────────────────────────────

    html += '<tr class="total-row">';
    html += '<td style="text-align:center;">Total</td>';

    // Total Limpieza
    html += '<td style="text-align:center;">';
    if (totales.tiene_limpieza) {
        html += '<div class="contenedor-valor">'
            + circulo(totales.color_limpieza)
            + '<div>' + totales.pct_limpieza + '</div>'
            + '</div>';
    } else { html += '--'; }
    html += '</td>';

    // Total Personal
    html += '<td style="text-align:center;">';
    if (totales.tiene_personal) {
        html += '<div class="contenedor-valor">'
            + circulo(totales.color_personal)
            + '<div>' + totales.pct_personal + '</div>'
            + '</div>';
    } else { html += '--'; }
    html += '</td>';

    // Total Servicio
    html += '<td style="text-align:center;">';
    if (totales.tiene_servicio) {
        html += '<div class="contenedor-valor">'
            + circulo(totales.color_servicio)
            + '<div>' + totales.pct_servicio + '</div>'
            + '</div>';
    } else { html += '--'; }
    html += '</td>';

    // Total Membresías
    html += '<td style="text-align:center;">';
    if (totales.cant_membresias > 0) {
        html += '<div class="contenedor-valor">'
            + circulo(totales.color_membresia)
            + '<div>' + totales.pct_membresia
            + '</div>'
            + '</div>';
    } else { html += '--'; }
    html += '</td>';

    // Total Tamaño Normal
    html += '<td style="text-align:center;">';
    if (totales.cant_total_tam > 0) {
        html += '<div class="contenedor-valor">'
            + circulo(totales.color_tamano)
            + '<div>' + totales.pct_tamano
            + '</div>'
            + '</div>';
    } else { html += '--'; }
    html += '</td>';

    // Total Mostrador
    html += '<td style="text-align:center;">';
    if (totales.pct_mostrador > 0) {
        html += '<div class="contenedor-valor">'
            + circulo(totales.color_mostrador)
            + '<div>' + totales.pct_mostrador + '</div>'
            + '</div>';
    } else { html += '--'; }
    html += '</td>';

    // Total Reclamos
    html += '<td style="text-align:center;">';
    html += '<div class="contenedor-valor">'
        + circulo(totales.color_reclamos)
        + '<div>' + totales.pct_reclamos.toFixed(1)
        + ' <span class="cantidad-auditorias">(' + (totales.reclamos_cantidad || 0) + '/' + (totales.reclamos_totales || 0) + ')</span></div>'
        + '</div>';
    html += '</td>';

    // Total Reseñas
    html += '<td style="text-align:center;">';
    html += '<div class="contenedor-valor">'
        + circulo(totales.color_resenas)
        + '<div>' + totales.pct_resenas
        + ' <span class="cantidad-auditorias">(' + (totales.cant_resenas || 0) + ')</span>'
        + '</div>'
        + '</div>';
    html += '</td>';



    // Total Desempeño de Tienda
    html += '<td style="text-align:center;">';
    html += '<div class="contenedor-valor">'
        + circulo(totales.color_desempeno)
        + '<div>' + totales.pct_desempeno.toFixed(1) + '</div>'
        + '</div>';
    html += '</td>';

    // Factor % (oculto visualmente)
    html += '<td style="text-align:center;visibility:hidden;">' + totales.factor_visual.toFixed(1) + '%</td>';

    // Total % Final (oculto visualmente)
    html += '<td style="text-align:center;visibility:hidden;">' + totales.total_porcentaje.toFixed(2) + '%</td>';

    html += '</tr>';

    tbody.innerHTML = html;
}

// ── Helpers de UI ────────────────────────────────────────────────────────────

function circulo(colorClass) {
    return '<span class="color-circle ' + colorClass + '"></span>';
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function mostrarCargando(show) {
    const el = document.getElementById('dsv2-loading');
    if (el) el.style.display = show ? 'block' : 'none';

    const tbody = document.getElementById('dsv2-tbody');
    if (tbody && show) {
        tbody.innerHTML = '<tr><td colspan="15" style="text-align:center;padding:20px;color:#999;">Cargando...</td></tr>';
    }
}

function mostrarError(msg) {
    const tbody = document.getElementById('dsv2-tbody');
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="15" style="text-align:center;color:#FF6F61;padding:15px;">' + escHtml(msg) + '</td></tr>';
    }
}

// ── Igualador de altura de tarjetas del modal ────────────────────────────────

function equalizarAlturasTarjetas() {
    const cards = document.querySelectorAll('.metrics-grid .guide-card');
    if (!cards.length) return;

    // 1. Resetear alturas para medir natural
    cards.forEach(function (c) { c.style.height = 'auto'; });

    // 2. Encontrar la altura máxima
    var maxH = 0;
    cards.forEach(function (c) {
        maxH = Math.max(maxH, c.offsetHeight);
    });

    // 3. Aplicar altura uniforme a todas
    if (maxH > 0) {
        cards.forEach(function (c) { c.style.height = maxH + 'px'; });
    }
}

// ── Integración Looker Studio ───────────────────────────────────────────────

/**
 * Inicializa la carga del reporte de Looker Studio de forma diferida
 * para dar prioridad a la tabla de desempeño principal.
 */
function initLookerReport() {
    const wrapper = document.getElementById('looker-iframe-wrapper');
    if (!wrapper) return;

    // Solo cargamos si no existe ya el iframe
    if (wrapper.querySelector('iframe')) return;

    const lookerUrl = 'https://lookerstudio.google.com/embed/reporting/0bcca88f-a7ad-49ec-ac68-b2662e4fdaff/page/vEdYF';

    // Creamos el iframe dinámicamente
    const iframe = document.createElement('iframe');
    iframe.src = lookerUrl;
    iframe.width = "100%";
    iframe.height = "100%";
    iframe.frameBorder = "0";
    iframe.style.border = "0";
    iframe.allowFullscreen = true;
    iframe.setAttribute('sandbox', 'allow-storage-access-by-user-activation allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox');
    // loading="lazy" para extra optimización
    iframe.loading = "lazy";

    // Cuando el iframe carga, removemos el placeholder
    iframe.onload = function() {
        const placeholder = wrapper.querySelector('.looker-placeholder');
        if (placeholder) {
            // Fade out suave del placeholder
            placeholder.style.transition = 'opacity 0.5s ease';
            placeholder.style.opacity = '0';
            setTimeout(function() { placeholder.style.display = 'none'; }, 500);
        }
    };

    // Inyectamos el iframe
    wrapper.appendChild(iframe);
}

/**
 * Recarga el reporte de Looker Studio sin recargar toda la página.
 * Útil para errores intermitentes de conexión de Google.
 */
function reloadLookerReport() {
    const wrapper = document.getElementById('looker-iframe-wrapper');
    if (!wrapper) return;

    const iframe = wrapper.querySelector('iframe');
    const placeholder = wrapper.querySelector('.looker-placeholder');

    if (iframe) {
        // Mostrar placeholder de nuevo
        if (placeholder) {
            placeholder.style.display = 'block';
            placeholder.style.opacity = '1';
        }
        
        // Forzar recarga reinyectando el src
        const currentSrc = iframe.src;
        iframe.src = '';
        
        // Pequeño retardo para asegurar que el navegador registre el cambio de src
        setTimeout(function() {
            iframe.src = currentSrc;
        }, 150);
    } else {
        // Si por alguna razón no se había iniciado, lo iniciamos
        initLookerReport();
    }
}


