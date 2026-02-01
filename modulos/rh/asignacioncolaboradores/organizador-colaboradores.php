<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Colaboradores - Sucursales</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 2em;
        }
        
        .region {
            margin-bottom: 40px;
        }
        
        .region-title {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            font-size: 1.3em;
            font-weight: bold;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .sucursales-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .sucursal {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .sucursal:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .sucursal-nombre {
            font-size: 1.1em;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .seccion {
            margin-bottom: 15px;
        }
        
        .seccion-titulo {
            font-size: 0.85em;
            font-weight: bold;
            color: #666;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .drop-zone {
            min-height: 50px;
            background: white;
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 10px;
            transition: all 0.3s ease;
        }
        
        .drop-zone.drag-over {
            background: #e3f2fd;
            border-color: #2196F3;
            border-style: solid;
            box-shadow: 0 0 15px rgba(33, 150, 243, 0.3);
        }
        
        .colaborador {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 12px;
            margin: 5px 0;
            border-radius: 6px;
            cursor: move;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .colaborador:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
        }
        
        .colaborador.dragging {
            opacity: 0.5;
            transform: rotate(2deg);
        }
        
        .colaborador.vendedor {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        .colaborador-nombre {
            font-size: 0.9em;
            font-weight: 500;
        }
        
        .btn-eliminar {
            background: rgba(255, 255, 255, 0.3);
            border: none;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-eliminar:hover {
            background: rgba(255, 255, 255, 0.5);
            transform: scale(1.1);
        }
        
        .vacio {
            color: #999;
            font-style: italic;
            font-size: 0.85em;
            text-align: center;
            padding: 15px;
        }

        @media (max-width: 768px) {
            .sucursales-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏢 Gestión de Colaboradores por Sucursal</h1>
        
        <div class="region">
            <div class="region-title">📍 MANAGUA</div>
            <div class="sucursales-grid" id="managua-grid"></div>
        </div>
        
        <div class="region">
            <div class="region-title">📍 DEPARTAMENTOS</div>
            <div class="sucursales-grid" id="departamentos-grid"></div>
        </div>
    </div>

    <script>
        // Datos de ejemplo
        const sucursales = {
            managua: [
                'Sucursal Centro',
                'Sucursal Metrocentro',
                'Sucursal Bello Horizonte',
                'Sucursal Carretera Masaya',
                'Sucursal Villa Fontana',
                'Sucursal Las Colinas'
            ],
            departamentos: [
                'Sucursal Masaya',
                'Sucursal Granada',
                'Sucursal León',
                'Sucursal Estelí',
                'Sucursal Matagalpa',
                'Sucursal Chinandega',
                'Sucursal Rivas'
            ]
        };

        const lideres = [
            'Carlos Méndez', 'María López', 'Roberto Flores', 'Ana García',
            'José Ramírez', 'Laura Martínez', 'Diego Torres', 'Carmen Silva',
            'Fernando Cruz', 'Patricia Vega', 'Manuel Ortiz', 'Sofía Ruiz',
            'Ricardo Morales'
        ];

        const vendedores = [
            'Juan Pérez', 'Mónica Rivera', 'Luis Hernández', 'Gabriela Castro',
            'Pedro Gómez', 'Daniela Rojas', 'Miguel Ángel Soto', 'Andrea Vargas',
            'Raúl Jiménez', 'Valeria Núñez', 'Alberto Campos', 'Cristina Mejía',
            'Sergio Vásquez', 'Paola Delgado', 'Javier Reyes', 'Melissa Aguilar',
            'Andrés Mora', 'Karla Espinoza', 'Óscar Medina', 'Lucía Sandoval',
            'Marcos Gutiérrez', 'Natalia Romero', 'Héctor Salazar', 'Isabel Navarro',
            'Gustavo Peña', 'Diana Acosta', 'Ernesto Chávez', 'Rosa Téllez',
            'Víctor Bautista', 'Alejandra Padilla', 'Rodrigo Zamora', 'Verónica Luna',
            'Francisco Ramos', 'Lorena Cortés', 'Arturo Solís', 'Mariana Fuentes',
            'Esteban Guerrero', 'Claudia Mendoza', 'Iván Paredes', 'Fernanda Ríos',
            'Pablo Cáceres', 'Adriana Lara', 'Mauricio Figueroa', 'Sandra Molina',
            'Julio Carrillo', 'Beatriz Santos', 'Ramón Herrera', 'Yolanda Álvarez',
            'César Maldonado', 'Silvia Domínguez', 'Orlando Ibarra', 'Marisol Contreras',
            'Eduardo Ponce', 'Roxana Arias', 'Leonardo Serrano', 'Gloria Pacheco',
            'Armando Camacho', 'Cecilia Pereira', 'Rubén Garrido', 'Teresa Navarrete'
        ];

        let draggedElement = null;
        let draggedType = null;

        function crearSucursal(nombre, index) {
            const div = document.createElement('div');
            div.className = 'sucursal';
            div.innerHTML = `
                <div class="sucursal-nombre">${nombre}</div>
                
                <div class="seccion">
                    <div class="seccion-titulo">👔 Líder</div>
                    <div class="drop-zone lider-zone" data-sucursal="${index}" data-tipo="lider">
                        <div class="colaborador lider" draggable="true" data-tipo="lider">
                            <span class="colaborador-nombre">${lideres[index]}</span>
                            <button style="display:none;" class="btn-eliminar" onclick="eliminarColaborador(event)">×</button>
                        </div>
                    </div>
                </div>
                
                <div class="seccion">
                    <div class="seccion-titulo">👥 Vendedores</div>
                    <div class="drop-zone vendedor-zone" data-sucursal="${index}" data-tipo="vendedor">
                        ${crearVendedoresIniciales(index)}
                    </div>
                </div>
            `;
            return div;
        }

        function crearVendedoresIniciales(sucursalIndex) {
            const numVendedores = Math.floor(Math.random() * 3) + 4; // 4-6 vendedores
            const startIndex = sucursalIndex * 5;
            let html = '';
            
            for (let i = 0; i < numVendedores && (startIndex + i) < vendedores.length; i++) {
                html += `
                    <div class="colaborador vendedor" draggable="true" data-tipo="vendedor">
                        <span class="colaborador-nombre">${vendedores[startIndex + i]}</span>
                        <button style="display:none;" class="btn-eliminar" onclick="eliminarColaborador(event)">×</button>
                    </div>
                `;
            }
            
            return html || '<div class="vacio">Sin vendedores asignados</div>';
        }

        function inicializar() {
            const managuaGrid = document.getElementById('managua-grid');
            const departamentosGrid = document.getElementById('departamentos-grid');

            sucursales.managua.forEach((nombre, index) => {
                managuaGrid.appendChild(crearSucursal(nombre, index));
            });

            sucursales.departamentos.forEach((nombre, index) => {
                departamentosGrid.appendChild(crearSucursal(nombre, index + 6));
            });

            configurarDragAndDrop();
        }

        function configurarDragAndDrop() {
            document.addEventListener('dragstart', (e) => {
                if (e.target.classList.contains('colaborador')) {
                    draggedElement = e.target;
                    draggedType = e.target.dataset.tipo;
                    e.target.classList.add('dragging');
                    
                    // Resaltar zonas válidas
                    const zonas = document.querySelectorAll(`.${draggedType}-zone`);
                    zonas.forEach(zona => {
                        zona.style.borderColor = '#2196F3';
                        zona.style.borderWidth = '3px';
                    });
                }
            });

            document.addEventListener('dragend', (e) => {
                if (e.target.classList.contains('colaborador')) {
                    e.target.classList.remove('dragging');
                    
                    // Quitar resaltado
                    const zonas = document.querySelectorAll('.drop-zone');
                    zonas.forEach(zona => {
                        zona.style.borderColor = '#ccc';
                        zona.style.borderWidth = '2px';
                        zona.classList.remove('drag-over');
                    });
                }
            });

            document.addEventListener('dragover', (e) => {
                e.preventDefault();
                const dropZone = e.target.closest('.drop-zone');
                
                if (dropZone && dropZone.dataset.tipo === draggedType) {
                    dropZone.classList.add('drag-over');
                }
            });

            document.addEventListener('dragleave', (e) => {
                const dropZone = e.target.closest('.drop-zone');
                if (dropZone) {
                    dropZone.classList.remove('drag-over');
                }
            });

            document.addEventListener('drop', (e) => {
                e.preventDefault();
                const dropZone = e.target.closest('.drop-zone');
                
                if (dropZone && dropZone.dataset.tipo === draggedType && draggedElement) {
                    dropZone.classList.remove('drag-over');
                    
                    // Remover mensaje de vacío si existe
                    const vacio = dropZone.querySelector('.vacio');
                    if (vacio) vacio.remove();
                    
                    // Si es líder, reemplazar el existente
                    if (draggedType === 'lider') {
                        const liderExistente = dropZone.querySelector('.colaborador');
                        if (liderExistente) liderExistente.remove();
                    }
                    
                    // Mover el elemento
                    dropZone.appendChild(draggedElement);
                    
                    // Verificar si la zona original quedó vacía
                    verificarZonaVacia(draggedElement);
                }
                
                draggedElement = null;
                draggedType = null;
            });
        }

        function verificarZonaVacia(elemento) {
            const zonaOriginal = elemento.parentElement;
            if (zonaOriginal && zonaOriginal.classList.contains('drop-zone')) {
                setTimeout(() => {
                    if (zonaOriginal.children.length === 0) {
                        const tipo = zonaOriginal.dataset.tipo;
                        const mensaje = tipo === 'lider' ? 'Sin líder asignado' : 'Sin vendedores asignados';
                        zonaOriginal.innerHTML = `<div class="vacio">${mensaje}</div>`;
                    }
                }, 10);
            }
        }

        function eliminarColaborador(e) {
            e.stopPropagation();
            const colaborador = e.target.closest('.colaborador');
            const dropZone = colaborador.parentElement;
            
            colaborador.remove();
            
            // Verificar si quedó vacía
            if (dropZone.children.length === 0) {
                const tipo = dropZone.dataset.tipo;
                const mensaje = tipo === 'lider' ? 'Sin líder asignado' : 'Sin vendedores asignados';
                dropZone.innerHTML = `<div class="vacio">${mensaje}</div>`;
            }
        }

        // Inicializar al cargar la página
        window.addEventListener('DOMContentLoaded', inicializar);
    </script>
</body>
</html>