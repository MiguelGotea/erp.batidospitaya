let chartInstance = null;
let lastGeneratedData = null;
let modalGuardarFavorito = null;

// Inicializar al cargar
document.addEventListener('DOMContentLoaded', function() {
    modalGuardarFavorito = new bootstrap.Modal(document.getElementById('modalGuardarFavorito'));
    cargarFavoritos();
    
    // Detectar Enter para generar
    document.getElementById('promptInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            generarGrafico();
        }
    });
});

// ========== FUNCIONES DE FAVORITOS ==========

// Toggle sección de favoritos
function toggleFavoritos() {
    const body = document.getElementById('favoritosBody');
    const chevron = document.getElementById('favoritosChevron');
    const header = document.querySelector('.favoritos-header');
    
    body.classList.toggle('d-none');
    body.classList.toggle('show');
    header.classList.toggle('expanded');
}

// Cargar favoritos del usuario
async function cargarFavoritos() {
    try {
        const response = await fetch('ajax/ia_graficos_favoritos_listar.php');
        const data = await response.json();
        
        if (data.success) {
            renderizarFavoritos(data.favoritos);
            document.getElementById('favoritosBadge').textContent = data.favoritos.length;
        }
    } catch (error) {
        console.error('Error cargando favoritos:', error);
    }
}

// Renderizar lista de favoritos
function renderizarFavoritos(favoritos) {
    const listaFavoritos = document.getElementById('listaFavoritos');
    const noFavoritos = document.getElementById('noFavoritos');
    
    if (favoritos.length === 0) {
        listaFavoritos.innerHTML = '';
        noFavoritos.classList.remove('d-none');
        return;
    }
    
    noFavoritos.classList.add('d-none');
    
    let html = '';
    favoritos.forEach(fav => {
        html += `
            <div class="favorito-card" onclick="usarFavorito(${fav.id})">
                <div class="favorito-header">
                    <div class="favorito-nombre">
                        <i class="bi bi-star-fill text-warning"></i> ${fav.nombre_favorito}
                    </div>
                    <span class="favorito-tipo">${fav.tipo_grafico}</span>
                </div>
                <div class="favorito-prompt">
                    "${fav.prompt_original}"
                </div>
                ${fav.descripcion ? `<div class="favorito-descripcion">${fav.descripcion}</div>` : ''}
                <div class="favorito-footer">
                    <div class="favorito-meta">
                        <i class="bi bi-clock"></i> ${formatearFechaRelativa(fav.fecha_creacion)}
                        ${fav.veces_usado > 0 ? `<span class="ms-2"><i class="bi bi-eye"></i> ${fav.veces_usado} veces</span>` : ''}
                    </div>
                    <div class="favorito-actions">
                        <button class="btn btn-sm btn-favorito btn-delete" 
                                onclick="event.stopPropagation(); eliminarFavorito(${fav.id})"
                                title="Eliminar">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    listaFavoritos.innerHTML = html;
}

// Usar un favorito
async function usarFavorito(favoritoId) {
    mostrarLoader(true);
    ocultarPaneles();
    
    try {
        // Obtener datos del favorito
        const response = await fetch('ajax/ia_graficos_favoritos_obtener.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ favorito_id: favoritoId })
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message);
        }
        
        const favorito = data.favorito;
        const estructura = JSON.parse(favorito.estructura_json);
        
        // Actualizar prompt
        document.getElementById('promptInput').value = favorito.prompt_original;
        
        // Mostrar interpretación
        mostrarInterpretacion(estructura);
        
        // Ejecutar consulta
        const resultadoSQL = await ejecutarConsulta(estructura);
        
        if (!resultadoSQL.success) {
            throw new Error(resultadoSQL.message);
        }
        
        // Guardar datos
        lastGeneratedData = {
            estructura: estructura,
            datos: resultadoSQL.data,
            estadisticas: resultadoSQL.estadisticas
        };
        
        // Renderizar gráfico
        renderizarGrafico(
            estructura.tipo_grafico,
            resultadoSQL.data,
            estructura
        );
        
        // Mostrar explicación
        mostrarExplicacion(estructura, resultadoSQL.estadisticas);
        
        // Mostrar panel de resultado
        document.getElementById('resultPanel').classList.remove('d-none');
        
    } catch (error) {
        console.error('Error:', error);
        mostrarError(error.message);
    } finally {
        mostrarLoader(false);
    }
}

// Guardar favorito
function guardarFavorito() {
    if (!lastGeneratedData) {
        alert('No hay ningún gráfico generado para guardar');
        return;
    }
    
    // Limpiar formulario
    document.getElementById('nombreFavorito').value = '';
    document.getElementById('descripcionFavorito').value = '';
    
    // Abrir modal
    modalGuardarFavorito.show();
}

// Confirmar guardar favorito
async function confirmarGuardarFavorito() {
    const nombre = document.getElementById('nombreFavorito').value.trim();
    const descripcion = document.getElementById('descripcionFavorito').value.trim();
    
    if (!nombre) {
        alert('Debes ingresar un nombre para el favorito');
        return;
    }
    
    if (!lastGeneratedData) {
        alert('No hay datos para guardar');
        return;
    }
    
    try {
        const response = await fetch('ajax/ia_graficos_favoritos_guardar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nombre_favorito: nombre,
                descripcion: descripcion,
                prompt_original: document.getElementById('promptInput').value,
                estructura_json: JSON.stringify(lastGeneratedData.estructura),
                tipo_grafico: lastGeneratedData.estructura.tipo_grafico
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            modalGuardarFavorito.hide();
            alert('✓ Favorito guardado exitosamente');
            cargarFavoritos();
        } else {
            alert('Error: ' + data.message);
        }
        
    } catch (error) {
        console.error('Error guardando favorito:', error);
        alert('Error al guardar el favorito');
    }
}

// Eliminar favorito
async function eliminarFavorito(favoritoId) {
    if (!confirm('¿Estás seguro de eliminar este favorito?')) {
        return;
    }
    
    try {
        const response = await fetch('ajax/ia_graficos_favoritos_eliminar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ favorito_id: favoritoId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            cargarFavoritos();
        } else {
            alert('Error: ' + data.message);
        }
        
    } catch (error) {
        console.error('Error eliminando favorito:', error);
        alert('Error al eliminar el favorito');
    }
}

// ========== FUNCIONES DE DESCARGA EXCEL ==========

async function descargarExcel() {
    if (!lastGeneratedData) {
        alert('No hay datos para descargar');
        return;
    }
    
    try {
        const response = await fetch('ajax/ia_graficos_descargar_excel.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                estructura: lastGeneratedData.estructura,
                datos: lastGeneratedData.datos,
                estadisticas: lastGeneratedData.estadisticas
            })
        });
        
        if (!response.ok) {
            throw new Error('Error al generar el archivo Excel');
        }
        
        // Descargar archivo
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'datos_grafico_' + Date.now() + '.xlsx';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
    } catch (error) {
        console.error('Error descargando Excel:', error);
        alert('Error al descargar el archivo Excel');
    }
}

// ========== FUNCIONES PRINCIPALES (MANTENER LAS EXISTENTES) ==========

// Usar ejemplo de consulta
function usarEjemplo(element) {
    const texto = element.textContent.trim().replace(/"/g, '');
    document.getElementById('promptInput').value = texto;
    document.getElementById('promptInput').focus();
}

// Función principal para generar gráfico
async function generarGrafico() {
    const prompt = document.getElementById('promptInput').value.trim();
    
    if (!prompt) {
        mostrarError('Por favor, escribe una consulta');
        return;
    }
    
    ocultarPaneles();
    mostrarLoader(true);
    
    try {
        const estructuraIA = await procesarPromptConIA(prompt);
        
        if (!estructuraIA.success) {
            throw new Error(estructuraIA.message || 'Error al procesar el prompt');
        }
        
        mostrarInterpretacion(estructuraIA.data);
        
        const resultadoSQL = await ejecutarConsulta(estructuraIA.data);
        
        if (!resultadoSQL.success) {
            throw new Error(resultadoSQL.message || 'Error al ejecutar la consulta');
        }
        
        lastGeneratedData = {
            estructura: estructuraIA.data,
            datos: resultadoSQL.data,
            estadisticas: resultadoSQL.estadisticas
        };
        
        renderizarGrafico(
            estructuraIA.data.tipo_grafico,
            resultadoSQL.data,
            estructuraIA.data
        );
        
        mostrarExplicacion(estructuraIA.data, resultadoSQL.estadisticas);
        
        document.getElementById('resultPanel').classList.remove('d-none');
        
    } catch (error) {
        console.error('Error:', error);
        mostrarError(error.message);
    } finally {
        mostrarLoader(false);
    }
}

async function procesarPromptConIA(prompt) {
    try {
        const response = await fetch('ajax/ia_graficos_procesar_prompt.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ prompt: prompt })
        });
        
        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor');
        }
        
        return await response.json();
        
    } catch (error) {
        console.error('Error procesando prompt:', error);
        return {
            success: false,
            message: 'Error al conectar con el servicio de IA'
        };
    }
}

async function ejecutarConsulta(estructura) {
    try {
        const response = await fetch('ajax/ia_graficos_ejecutar_query.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ estructura: estructura })
        });
        
        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor');
        }
        
        return await response.json();
        
    } catch (error) {
        console.error('Error ejecutando consulta:', error);
        return {
            success: false,
            message: 'Error al ejecutar la consulta en la base de datos'
        };
    }
}

function ordenarDatosSiEsTemporal(datos, estructura) {
    if (estructura.dimension_tipo === 'temporal') {
        return datos.sort((a, b) => {
            const fechaA = new Date(a.label);
            const fechaB = new Date(b.label);
            
            if (!isNaN(fechaA) && !isNaN(fechaB)) {
                return fechaA - fechaB;
            }
            
            return a.label.localeCompare(b.label);
        });
    }
    
    return datos;
}

function renderizarGrafico(tipoGrafico, datos, estructura) {
    const canvas = document.getElementById('chartCanvas');
    const ctx = canvas.getContext('2d');
    
    if (chartInstance) {
        chartInstance.destroy();
    }
    
    const datosOrdenados = ordenarDatosSiEsTemporal(datos, estructura);
    
    const labels = datosOrdenados.map(row => row.label);
    const values = datosOrdenados.map(row => parseFloat(row.value) || 0);
    
    const config = {
        type: mapearTipoGrafico(tipoGrafico),
        data: {
            labels: labels,
            datasets: [{
                label: estructura.metrica_nombre || 'Valor',
                data: values,
                backgroundColor: generarColores(values.length, 0.6),
                borderColor: generarColores(values.length, 1),
                borderWidth: 2,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: tipoGrafico !== 'circular',
                    position: 'top'
                },
                title: {
                    display: true,
                    text: estructura.descripcion_grafico || 'Gráfico de Ventas',
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (estructura.formato_metrica === 'moneda') {
                                label += 'C$ ' + context.parsed.y.toLocaleString('es-NI', {minimumFractionDigits: 2});
                            } else {
                                label += context.parsed.y.toLocaleString('es-NI');
                            }
                            return label;
                        }
                    }
                }
            },
            scales: tipoGrafico !== 'circular' ? {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            if (estructura.formato_metrica === 'moneda') {
                                return 'C$ ' + value.toLocaleString('es-NI');
                            }
                            return value.toLocaleString('es-NI');
                        }
                    }
                }
            } : {}
        }
    };
    
    chartInstance = new Chart(ctx, config);
}

function mapearTipoGrafico(tipo) {
    const mapeo = {
        'lineal': 'line',
        'barras': 'bar',
        'columnas': 'bar',
        'circular': 'pie',
        'area': 'line'
    };
    return mapeo[tipo] || 'bar';
}

function generarColores(cantidad, opacidad) {
    const colores = [
        `rgba(81, 184, 172, ${opacidad})`,
        `rgba(14, 84, 76, ${opacidad})`,
        `rgba(52, 152, 219, ${opacidad})`,
        `rgba(155, 89, 182, ${opacidad})`,
        `rgba(241, 196, 15, ${opacidad})`,
        `rgba(230, 126, 34, ${opacidad})`,
        `rgba(231, 76, 60, ${opacidad})`,
        `rgba(149, 165, 166, ${opacidad})`
    ];
    
    const resultado = [];
    for (let i = 0; i < cantidad; i++) {
        resultado.push(colores[i % colores.length]);
    }
    return resultado;
}

function mostrarInterpretacion(estructura) {
    const panel = document.getElementById('interpretationPanel');
    const content = document.getElementById('interpretationContent');
    
    let html = '<div class="row">';
    
    html += `
        <div class="col-md-6 mb-3">
            <div class="interpretation-item">
                <strong><i class="bi bi-graph-up"></i> Tipo de Gráfico:</strong> 
                <span class="ms-2">${estructura.tipo_grafico}</span>
            </div>
        </div>
    `;
    
    html += `
        <div class="col-md-6 mb-3">
            <div class="interpretation-item">
                <strong><i class="bi bi-calculator"></i> Métrica:</strong> 
                <span class="ms-2">${estructura.metrica_nombre}</span>
            </div>
        </div>
    `;
    
    if (estructura.dimension_nombre) {
        html += `
            <div class="col-md-6 mb-3">
                <div class="interpretation-item">
                    <strong><i class="bi bi-diagram-3"></i> Agrupado por:</strong> 
                    <span class="ms-2">${estructura.dimension_nombre}</span>
                </div>
            </div>
        `;
    }
    
    if (estructura.rango_temporal) {
        html += `
            <div class="col-md-6 mb-3">
                <div class="interpretation-item">
                    <strong><i class="bi bi-calendar-range"></i> Período:</strong> 
                    <span class="ms-2">${estructura.rango_temporal.descripcion}</span>
                </div>
            </div>
        `;
    }
    
    if (estructura.filtros && estructura.filtros.length > 0) {
        html += `
            <div class="col-12">
                <div class="interpretation-item">
                    <strong><i class="bi bi-funnel"></i> Filtros aplicados:</strong>
                    <ul class="mb-0 mt-2">
        `;
        estructura.filtros.forEach(filtro => {
            html += `<li>${filtro.descripcion}</li>`;
        });
        html += `
                    </ul>
                </div>
            </div>
        `;
    }
    
    html += '</div>';
    
    content.innerHTML = html;
    panel.classList.remove('d-none');
}

function mostrarExplicacion(estructura, estadisticas) {
    const explanationBox = document.getElementById('explanationBox');
    explanationBox.innerHTML = `
        <h6><i class="bi bi-info-circle"></i> ¿Qué estás viendo?</h6>
        <p>${estructura.descripcion_grafico || 'Visualización de datos de ventas'}</p>
        ${estructura.observaciones ? `<p class="text-muted small"><strong>Nota:</strong> ${estructura.observaciones}</p>` : ''}
    `;
    
    const statsGrid = document.getElementById('statsGrid');
    let statsHtml = '';
    
    if (estadisticas) {
        Object.keys(estadisticas).forEach(key => {
            const stat = estadisticas[key];
            statsHtml += `
                <div class="stat-card">
                    <span class="stat-value">${formatearValor(stat.valor, stat.formato)}</span>
                    <span class="stat-label">${stat.label}</span>
                </div>
            `;
        });
    }
    
    statsGrid.innerHTML = statsHtml;
}

function formatearValor(valor, formato) {
    if (formato === 'moneda') {
        return 'C$ ' + parseFloat(valor).toLocaleString('es-NI', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    } else if (formato === 'numero') {
        return parseFloat(valor).toLocaleString('es-NI');
    } else if (formato === 'porcentaje') {
        return parseFloat(valor).toFixed(1) + '%';
    }
    return valor;
}

function formatearFechaRelativa(fecha) {
    const ahora = new Date();
    const fechaObj = new Date(fecha);
    const diffMs = ahora - fechaObj;
    const diffDias = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    
    if (diffDias === 0) return 'Hoy';
    if (diffDias === 1) return 'Ayer';
    if (diffDias < 7) return `Hace ${diffDias} días`;
    if (diffDias < 30) return `Hace ${Math.floor(diffDias / 7)} semanas`;
    if (diffDias < 365) return `Hace ${Math.floor(diffDias / 30)} meses`;
    return `Hace ${Math.floor(diffDias / 365)} años`;
}

function descargarGrafico() {
    if (!chartInstance) {
        alert('No hay gráfico para descargar');
        return;
    }
    
    const canvas = document.getElementById('chartCanvas');
    const url = canvas.toDataURL('image/png');
    const link = document.createElement('a');
    link.download = 'grafico_ventas_' + Date.now() + '.png';
    link.href = url;
    link.click();
}

function limpiarResultado() {
    if (confirm('¿Deseas limpiar el resultado actual?')) {
        ocultarPaneles();
        document.getElementById('promptInput').value = '';
        document.getElementById('promptInput').focus();
        
        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }
        
        lastGeneratedData = null;
    }
}

function mostrarLoader(mostrar) {
    const loader = document.getElementById('loader');
    const btnGenerar = document.getElementById('btnGenerar');
    
    if (mostrar) {
        loader.classList.remove('d-none');
        btnGenerar.disabled = true;
        btnGenerar.innerHTML = '<i class="bi bi-hourglass-split"></i> Procesando...';
    } else {
        loader.classList.add('d-none');
        btnGenerar.disabled = false;
        btnGenerar.innerHTML = '<i class="bi bi-graph-up"></i> Generar Gráfico';
    }
}

function mostrarError(mensaje) {
    const errorPanel = document.getElementById('errorPanel');
    const errorMessage = document.getElementById('errorMessage');
    
    errorMessage.textContent = mensaje;
    errorPanel.classList.remove('d-none');
    
    setTimeout(() => {
        errorPanel.classList.add('d-none');
    }, 8000);
}

function ocultarPaneles() {
    document.getElementById('interpretationPanel').classList.add('d-none');
    document.getElementById('resultPanel').classList.add('d-none');
    document.getElementById('errorPanel').classList.add('d-none');
}