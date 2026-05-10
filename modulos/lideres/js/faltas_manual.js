
        // Datos de operarios para el autocompletado
        const operariosData = CONFIG_FALTAS.operariosData;

        // Función para buscar operarios
        function buscarOperarios(texto) {
            if (!texto) {
                return operariosData;
            }
            return operariosData.filter(op =>
                op.nombre.toLowerCase().includes(texto.toLowerCase())
            );
        }

        // Manejar el input de operario
        const operarioInput = document.getElementById('operario');
        const operarioIdInput = document.getElementById('operario_id');
        const sugerenciasDiv = document.getElementById('operarios-sugerencias');

        // Modificar el evento input del campo operario
        operarioInput.addEventListener('input', function () {
            const texto = this.value.trim();

            // Si el campo está vacío, resetear a "todos"
            if (texto === '') {
                operarioIdInput.value = '0';
                sugerenciasDiv.style.display = 'none';
                return;
            }

            const resultados = buscarOperarios(texto);

            sugerenciasDiv.innerHTML = '';

            if (resultados.length > 0) {
                resultados.forEach(op => {
                    const div = document.createElement('div');
                    div.textContent = op.nombre;
                    div.style.padding = '8px';
                    div.style.cursor = 'pointer';
                    div.addEventListener('click', function () {
                        operarioInput.value = op.nombre;
                        operarioIdInput.value = op.id;
                        sugerenciasDiv.style.display = 'none';
                    });
                    div.addEventListener('mouseover', function () {
                        this.style.backgroundColor = '#f5f5f5';
                    });
                    div.addEventListener('mouseout', function () {
                        this.style.backgroundColor = 'white';
                    });
                    sugerenciasDiv.appendChild(div);
                });
                sugerenciasDiv.style.display = 'block';
            } else {
                sugerenciasDiv.style.display = 'none';
            }
        });

        // Ocultar sugerencias al hacer clic fuera
        document.addEventListener('click', function (e) {
            if (e.target !== operarioInput) {
                sugerenciasDiv.style.display = 'none';
            }
        });

        // Manejar tecla Enter en el input
        operarioInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const texto = this.value.trim();
                const resultados = buscarOperarios(texto);
                if (resultados.length > 0) {
                    this.value = resultados[0].nombre;
                    operarioIdInput.value = resultados[0].id;
                }
                sugerenciasDiv.style.display = 'none';
            }
        });

        // Función para mostrar foto en un modal
        function mostrarFoto(rutaFoto) {
            ampliarImagen(rutaFoto);
        }

        // Actualizar filtros y recargar la página
        function actualizarFiltros() {
            const desde = document.getElementById('desde').value;
            const hasta = document.getElementById('hasta').value;

            // Validar fechas
            if (!desde || !hasta) {
                alert('Por favor seleccione ambas fechas');
                return;
            }

            if (new Date(desde) > new Date(hasta)) {
                alert('La fecha "Desde" no puede ser mayor que la fecha "Hasta"');
                return;
            }

            // Construir URL con parámetros
            const params = new URLSearchParams();

            if (CONFIG_FALTAS.esRH) {
                const modo = document.getElementById('modo')?.value || 'sucursal';
                params.append('modo', modo);

                if (modo === 'sucursal') {
                    const sucursal = document.getElementById('sucursal').value;
                    if (sucursal) params.append('sucursal', sucursal);
                }
            } else {
                const sucursal = document.getElementById('sucursal').value;
                if (sucursal) params.append('sucursal', sucursal);
            }

            params.append('desde', desde);
            params.append('hasta', hasta);

            window.location.href = 'faltas_manual.php?' + params.toString();
        }

        // Mostrar modal para nueva falta
        function mostrarModalNuevaFalta() {
            // Establecer fecha predeterminada como ayer
            const ayer = new Date();
            ayer.setDate(ayer.getDate() - 1);
            const fechaInput = document.getElementById('nueva_fecha');
            fechaInput.valueAsDate = ayer;
            fechaInput.max = ayer.toISOString().split('T')[0];

            // Limpiar y resetear el select de operarios
            // NUEVA VALIDACIÓN: Verificar si el operario seleccionado tiene contrato
            const selectOperario = document.getElementById('nueva_operario');
            const optionSeleccionada = selectOperario.options[selectOperario.selectedIndex];

            if (optionSeleccionada && optionSeleccionada.dataset.sinContrato === 'true') {
                e.preventDefault();
                alert('Este colaborador no tiene registro de contrato. Por favor contactar con el área de RH antes de registrar una falta.');
                return false;
            }

            selectOperario.innerHTML = '<option value="">Cargando...</option>';
            selectOperario.disabled = true;

            // Obtener sucursal seleccionada
            const selectSucursal = document.getElementById('nueva_sucursal');

            // Mostrar el modal PRIMERO
            document.getElementById('modalNuevaFalta').style.display = 'flex';

            // Pequeño delay para asegurar que el DOM está listo
            setTimeout(() => {
                // Cargar operarios con la fecha
                if (selectSucursal.value && fechaInput.value) {
                    cargarOperariosSucursal(selectSucursal.value, fechaInput.value);
                } else {
                    selectOperario.innerHTML = '<option value="">Seleccione fecha y sucursal</option>';
                    selectOperario.disabled = false;
                }
            }, 100);
        }

        // FUNCIÓN MODIFICADA: Cargar operarios considerando fecha de liquidación
        function cargarOperariosSucursal(codSucursal, fechaFalta) {
            const selectOperario = document.getElementById('nueva_operario');
            const mensajeAdvertencia = document.getElementById('mensaje-advertencia-contrato');

            if (!codSucursal) {
                selectOperario.innerHTML = '<option value="">Primero seleccione una sucursal</option>';
                selectOperario.disabled = true;
                return;
            }

            if (!fechaFalta) {
                selectOperario.innerHTML = '<option value="">Primero seleccione una fecha</option>';
                selectOperario.disabled = true;
                return;
            }

            // Indicar estado de carga
            selectOperario.innerHTML = '<option value="">⏳ Cargando operarios para ' + fechaFalta + '...</option>';
            selectOperario.disabled = true;

            // NUEVA URL: Incluye fecha_falta para filtrar por liquidación
            let url = `ajax/obtener_operarios_sucursal.php?sucursal=${codSucursal}&fecha_falta=${fechaFalta}`;

            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Operarios recibidos:', data);

                    selectOperario.disabled = false;

                    if (!data || data.length === 0) {
                        selectOperario.innerHTML = '<option value="">No hay operarios activos para esta fecha</option>';
                        return;
                    }

                    let options = '<option value="">Seleccione un operario</option>';
                    let hayOperariosSinContrato = false;

                    data.forEach(operario => {
                        const nombre = operario.Nombre || '';
                        const nombre2 = operario.Nombre2 || '';
                        const apellido = operario.Apellido || '';
                        const apellido2 = operario.Apellido2 || '';
                        const nombreCompleto = `${nombre} ${nombre2} ${apellido} ${apellido2}`.trim();

                        // NUEVA VALIDACIÓN: Verificar si tiene contrato
                        if (!operario.tiene_contrato) {
                            hayOperariosSinContrato = true;
                            options += `<option value="${operario.CodOperario}" data-sin-contrato="true">⚠️ ${nombreCompleto} (Sin contrato)</option>`;
                        } else {
                            options += `<option value="${operario.CodOperario}">${nombreCompleto}</option>`;
                        }
                    });

                    selectOperario.innerHTML = options;

                    // MOSTRAR ADVERTENCIA si hay operarios sin contrato
                    if (hayOperariosSinContrato && mensajeAdvertencia) {
                        mensajeAdvertencia.style.display = 'block';
                        mensajeAdvertencia.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Algunos colaboradores no tienen contrato registrado. Contactar con RH.';
                    } else if (mensajeAdvertencia) {
                        mensajeAdvertencia.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error cargando operarios:', error);
                    selectOperario.disabled = false;
                    selectOperario.innerHTML = '<option value="">❌ Error al cargar. Intente de nuevo</option>';
                });
        }

        // Validar formulario antes de enviar
        document.getElementById('formNuevaFalta').addEventListener('submit', function (e) {
            const fechaInput = document.getElementById('nueva_fecha');
            const fechaSeleccionada = new Date(fechaInput.value);
            const fechaActual = new Date();
            fechaActual.setHours(0, 0, 0, 0); // Resetear hora para comparar solo fechas

            // Validar que no sea fecha futura
            if (fechaSeleccionada > fechaActual) {
                e.preventDefault();
                alert('No se pueden registrar faltas con fechas futuras');
                return false;
            }

            // Nueva validación: Verificar con AJAX si realmente hubo falta
            e.preventDefault();

            const codOperario = document.getElementById('nueva_operario').value;
            const codSucursal = document.getElementById('nueva_sucursal').value;
            const fechaFalta = fechaInput.value;

            if (!codOperario || !codSucursal || !fechaFalta) {
                alert('Complete todos los campos obligatorios');
                return false;
            }

            // Mostrar loading
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando...';
            submitBtn.disabled = true;

            // Hacer petición AJAX para verificar si realmente hubo falta
            fetch('ajax/verificar_falta_real.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    cod_operario: codOperario,
                    cod_sucursal: codSucursal,
                    fecha_falta: fechaFalta
                })
            })
                .then(response => response.json())
                .then(data => {
                    // MODIFICADO: Verificar si hay error específico
                    if (data.error) {
                        alert(data.error);
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    } else if (data.existe_falta) {
                        document.getElementById('formNuevaFalta').submit();
                    } else {
                        alert('No se puede registrar falta: El colaborador tiene marcaciones registradas para esta fecha o no tenía horario programado con estado Activo, Otra.Tienda, Subsidio o Vacaciones.');
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al verificar la falta. Intente nuevamente.');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });

            return false;
        });

        // Cargar operarios cuando cambia la sucursal en el modal de nueva falta
        document.getElementById('nueva_sucursal').addEventListener('change', function () {
            const fechaInput = document.getElementById('nueva_fecha');
            if (fechaInput.value) {
                cargarOperariosSucursal(this.value, fechaInput.value);
            } else {
                cargarOperariosSucursal(this.value, null);
            }
        });

        // EVENTO MODIFICADO: Cargar operarios cuando cambia la fecha
        document.getElementById('nueva_fecha').addEventListener('change', function () {
            const sucursalSelect = document.getElementById('nueva_sucursal');
            if (sucursalSelect.value && this.value) {
                console.log('Fecha cambiada a:', this.value);
                cargarOperariosSucursal(sucursalSelect.value, this.value);
            }
        });

        // Función auxiliar al script
        function formatearFechaLocal(fechaStr) {
            const fecha = new Date(fechaStr + 'T00:00:00');
            const opciones = { day: '2-digit', month: 'short', year: '2-digit' };
            return fecha.toLocaleDateString('es-ES', opciones);
        }

        // Función para mostrar el tipo de falta correcto en el modal de edición
        function mostrarModalEditarFalta(id, nombre, sucursal, fecha, tipo, observaciones, observaciones_rrhh, fotoPath) {
            console.log('Datos recibidos:', { id, nombre, sucursal, fecha, tipo, observaciones, observaciones_rrhh, fotoPath });

            document.getElementById('editar_id').value = id;
            document.getElementById('editar_nombre').textContent = nombre;
            document.getElementById('editar_sucursal').textContent = sucursal;

            document.getElementById('editar_fecha').textContent = formatearFechaLocal(fecha);

            document.getElementById('editar_foto_path').value = fotoPath;

            // Mostrar observaciones del líder (solo lectura)
            document.getElementById('editar_observaciones_lider').textContent = observaciones || '(Sin observaciones)';

            // Convertir "Pendiente" de nuevo a "No_Pagado" para el formulario
            const tipoParaForm = tipo;
            const selectTipo = document.getElementById('editar_tipo');
            selectTipo.value = tipoParaForm;

            // Asegurarse de que el tipo actual esté seleccionado, incluso si no existe en las opciones
            if (!selectTipo.querySelector(`option[value="${tipoParaForm}"]`)) {
                // Si el tipo no existe en las opciones, agregarlo temporalmente
                const nuevaOpcion = new Option(tipoParaForm.replace(/_/g, ' '), tipoParaForm);
                selectTipo.add(nuevaOpcion);
                selectTipo.value = tipoParaForm;
            }

            // Actualizar la información del porcentaje basado en el tipo actual
            actualizarPorcentajeEdicion(tipo);

            // Manejar observaciones RRHH según el tipo de usuario
            if (CONFIG_FALTAS.esRH) {
                document.getElementById('editar_observaciones_rrhh').value = observaciones_rrhh || '';
            } else {
                // Para no-RRHH, mostrar solo lectura
                document.getElementById('editar_observaciones_rrhh_view').textContent = observaciones_rrhh || '(Sin observaciones RRHH)';
                document.getElementById('editar_observaciones_rrhh').value = observaciones_rrhh || '';
            }

            // Mostrar u ocultar la previsualización de imagen
            const previewContainer = document.getElementById('preview-container');
            const previewImage = document.getElementById('preview-image');

            if (fotoPath && fotoPath !== '') {
                // Usar ruta absoluta para la imagen
                const rutaCompleta = '../..' + fotoPath;
                console.log('Cargando imagen:', rutaCompleta);
                previewImage.src = rutaCompleta;
                previewContainer.style.display = 'block';

                // Verificar si la imagen se carga correctamente
                previewImage.onload = function () {
                    console.log('Imagen cargada correctamente');
                };
                previewImage.onerror = function () {
                    console.error('Error al cargar la imagen:', rutaCompleta);
                    previewContainer.style.display = 'none';
                };
            } else {
                console.log('No hay imagen para mostrar');
                previewContainer.style.display = 'none';
            }

            document.getElementById('modalEditarFalta').style.display = 'flex';
        }

        // Validar formulario de edición
        document.getElementById('formEditarFalta').addEventListener('submit', function (e) {
            const observacionesRRHH = document.getElementById('editar_observaciones_rrhh').value.trim();

            if (!observacionesRRHH) {
                e.preventDefault();
                alert('El campo Observaciones RRHH es obligatorio');
                return false;
            }

            return true;
        });

        // Función para consultar marcaciones relacionadas con una falta
        function consultarMarcacion(codOperario, nombre, sucursalNombre, codSucursal, fechaFalta) {
            // Mostrar información básica
            document.getElementById('consulta_nombre').textContent = nombre;
            document.getElementById('consulta_sucursal').textContent = sucursalNombre;

            document.getElementById('consulta_fecha').textContent = formatearFechaLocal(fechaFalta);

            // Resetear valores mientras se carga
            document.getElementById('consulta_hora_entrada_programada').textContent = '-';
            document.getElementById('consulta_hora_salida_programada').textContent = '-';
            document.getElementById('consulta_hora_entrada').textContent = '-';
            document.getElementById('consulta_hora_salida').textContent = '-';
            document.getElementById('consulta_diferencia_entrada').textContent = '-';
            document.getElementById('consulta_diferencia_salida').textContent = '-';

            // Mostrar el modal
            document.getElementById('modalConsultarMarcacion').style.display = 'flex';

            // Hacer petición AJAX para obtener los datos
            fetch(`ajax/consultar_marcacion_falta.php?cod_operario=${codOperario}&cod_sucursal=${codSucursal}&fecha=${fechaFalta}`)
                .then(response => response.json())
                .then(data => {
                    // Mostrar horario programado
                    if (data.horario_programado) {
                        const hp = data.horario_programado;
                        document.getElementById('consulta_hora_entrada_programada').textContent =
                            hp.hora_entrada_programada ? formatoHoraAmPm(hp.hora_entrada_programada) : '-';
                        document.getElementById('consulta_hora_salida_programada').textContent =
                            hp.hora_salida_programada ? formatoHoraAmPm(hp.hora_salida_programada) : '-';
                    }

                    // Mostrar marcaciones
                    if (data.marcaciones) {
                        const m = data.marcaciones;
                        document.getElementById('consulta_hora_entrada').textContent =
                            m.hora_ingreso ? formatoHoraAmPm(m.hora_ingreso) : '-';
                        document.getElementById('consulta_hora_salida').textContent =
                            m.hora_salida ? formatoHoraAmPm(m.hora_salida) : '-';

                        // Calcular y mostrar diferencias si hay datos
                        if (data.horario_programado && data.marcaciones) {
                            const hp = data.horario_programado;
                            const m = data.marcaciones;

                            // Diferencia entrada
                            if (hp.hora_entrada_programada && m.hora_ingreso) {
                                const difEntrada = calcularDiferenciaMinutos(
                                    hp.hora_entrada_programada,
                                    m.hora_ingreso
                                );
                                mostrarDiferencia('consulta_diferencia_entrada', difEntrada);
                            }

                            // Diferencia salida
                            if (hp.hora_salida_programada && m.hora_salida) {
                                const difSalida = calcularDiferenciaMinutos(
                                    hp.hora_salida_programada,
                                    m.hora_salida
                                );
                                mostrarDiferencia('consulta_diferencia_salida', difSalida);
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error al consultar marcación:', error);
                    alert('Error al obtener los datos de marcación');
                });
        }

        // Función auxiliar para formatear hora en formato 12h AM/PM
        function formatoHoraAmPm(hora) {
            if (!hora || hora === '00:00:00') return '-';
            return new Date(`2000-01-01T${hora}`).toLocaleTimeString('es-ES', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
        }

        // Función para calcular diferencia en minutos entre dos horas
        function calcularDiferenciaMinutos(horaProgramada, horaReal) {
            const hp = new Date(`2000-01-01T${horaProgramada}`);
            const hr = new Date(`2000-01-01T${horaReal}`);

            const diffMs = hr - hp;
            return Math.round(diffMs / 60000); // Convertir a minutos
        }

        // Función para mostrar diferencia con colores
        function mostrarDiferencia(elementId, minutos) {
            const element = document.getElementById(elementId);

            if (minutos > 0) {
                element.innerHTML = `<span class="diferencia-tarde">+${minutos} min (Tarde)</span>`;
            } else if (minutos < 0) {
                element.innerHTML = `<span class="diferencia-temprano">${minutos} min (Temprano)</span>`;
            } else {
                element.innerHTML = `<span>${minutos} min (Exacto)</span>`;
            }
        }

        // Función para ampliar imagen (funciona sobre modales existentes)
        function ampliarImagen(src) {
            const modalAmpliar = document.createElement('div');
            modalAmpliar.id = 'modalAmpliarImagen';
            modalAmpliar.style.position = 'fixed';
            modalAmpliar.style.top = '0';
            modalAmpliar.style.left = '0';
            modalAmpliar.style.width = '100%';
            modalAmpliar.style.height = '100%';
            modalAmpliar.style.backgroundColor = 'rgba(0,0,0,0.9)';
            modalAmpliar.style.display = 'flex';
            modalAmpliar.style.justifyContent = 'center';
            modalAmpliar.style.alignItems = 'center';
            modalAmpliar.style.zIndex = '3000'; // Mayor z-index para que esté sobre el modal de edición

            const img = document.createElement('img');
            img.src = src;
            img.style.maxWidth = '90%';
            img.style.maxHeight = '90%';
            img.style.objectFit = 'contain';
            img.style.boxShadow = '0 0 20px rgba(255,255,255,0.2)';

            const closeBtn = document.createElement('button');
            closeBtn.innerHTML = '&times;';
            closeBtn.style.position = 'absolute';
            closeBtn.style.top = '20px';
            closeBtn.style.right = '20px';
            closeBtn.style.fontSize = '2.5rem';
            closeBtn.style.color = 'white';
            closeBtn.style.background = 'none';
            closeBtn.style.border = 'none';
            closeBtn.style.cursor = 'pointer';
            closeBtn.style.zIndex = '3001';

            closeBtn.onclick = function () {
                document.body.removeChild(modalAmpliar);
            };

            modalAmpliar.appendChild(img);
            modalAmpliar.appendChild(closeBtn);
            document.body.appendChild(modalAmpliar);

            // Cerrar al hacer clic fuera de la imagen
            modalAmpliar.onclick = function (e) {
                if (e.target === modalAmpliar) {
                    document.body.removeChild(modalAmpliar);
                }
            };

            // Cerrar con tecla ESC
            const closeOnEsc = function (e) {
                if (e.key === 'Escape') {
                    document.body.removeChild(modalAmpliar);
                    document.removeEventListener('keydown', closeOnEsc);
                }
            };

            document.addEventListener('keydown', closeOnEsc);
        }

        // Cerrar modal
        function cerrarModal() {
            document.getElementById('modalNuevaFalta').style.display = 'none';
            document.getElementById('modalEditarFalta').style.display = 'none';
            document.getElementById('modalConsultarMarcacion').style.display = 'none';
        }

        // Cargar operarios cuando se selecciona una sucursal en el modal de nueva falta
        document.getElementById('nueva_sucursal').addEventListener('change', function () {
            const codSucursal = this.value;
            const selectOperario = document.getElementById('nueva_operario');

            if (!codSucursal) {
                selectOperario.innerHTML = '<option value="">Seleccione un operario</option>';
                return;
            }

            // Hacer petición AJAX para obtener operarios de la sucursal
            fetch('ajax/obtener_operarios_sucursal.php?sucursal=' + codSucursal)
                .then(response => response.json())
                .then(data => {
                    let options = '<option value="">Seleccione un operario</option>';

                    data.forEach(operario => {
                        options += `<option value="${operario.CodOperario}">${operario.Nombre} ${operario.Apellido}</option>`;
                    });

                    selectOperario.innerHTML = options;
                })
                .catch(error => {
                    console.error('Error al cargar operarios:', error);
                    selectOperario.innerHTML = '<option value="">Error al cargar operarios</option>';
                });
        });

        // Cerrar modal al hacer clic fuera del contenido
        //window.addEventListener('click', function(event) {
        //    const modals = ['modalNuevaFalta', 'modalEditarFalta'];
        //  
        //modals.forEach(modalId => {
        //  const modal = document.getElementById(modalId);
        //if (event.target === modal) {
        //            cerrarModal();
        //      }
        //    });
        //});

        $(document).ready(function () {
            $('#listaFaltas').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
                },
                dom: '<"top"l>rt<"bottom"ip>', // Quitamos la "f" en "top"lf (filter/search)
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                pageLength: 25,

                // CONFIGURACIÓN PARA 3 CLICKS
                order: [], // Sin orden inicial - respeta el orden de la consulta SQL
                ordering: true, // Habilitar ordenamiento
                orderMulti: true, // Permitir ordenamiento múltiple con Ctrl+click

                // Configuración específica para el ciclo de 3 clicks
                columnDefs: [{
                    orderable: true, // Todas las columnas son ordenables
                    targets: '_all' // Aplicar a todas las columnas
                }]
            });
        });

        // En el formulario de nueva falta, después de seleccionar sucursal
        document.getElementById('nueva_sucursal').addEventListener('change', function () {
            const sucursalEspecial = ['6', '18'].includes(this.value);
            const esRH = CONFIG_FALTAS.esRH;
            const mensaje = document.getElementById('mensaje-especial');

            if (!mensaje) {
                const nuevoMensaje = document.createElement('div');
                nuevoMensaje.id = 'mensaje-especial';
                nuevoMensaje.style.padding = '10px';
                nuevoMensaje.style.margin = '10px 0';
                nuevoMensaje.style.borderRadius = '4px';
                document.querySelector('#formNuevaFalta .modal-body').prepend(nuevoMensaje);
            }

            // Para RH: mostrar mensaje específico
            if (esRH) {
                document.getElementById('mensaje-especial').innerHTML =
                    '<div style="background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 4px;">' +
                    '<i class="fas fa-info-circle"></i> Modo RH: Se mostrarán todos los operarios que tuvieron horario programado en esta sucursal' +
                    '</div>';
            }
            // Para sucursales especiales
            else if (sucursalEspecial) {
                document.getElementById('mensaje-especial').innerHTML =
                    '<div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 4px;">' +
                    '<i class="fas fa-info-circle"></i> Sucursal especial: No se requiere validación de horario programado' +
                    '</div>';
            }
            // Para líderes en sucursales normales
            else {
                document.getElementById('mensaje-especial').innerHTML =
                    '<div style="background: #fff3cd; color: #856404; padding: 8px; border-radius: 4px; font-size: 12px;">' +
                    '<i class="fas fa-info-circle"></i> Se mostrarán operarios asignados a esta sucursal' +
                    '</div>';
            }
        });

        // Función para actualizar la información del porcentaje
        function actualizarPorcentaje(tipoFalta) {
            const select = document.getElementById('nueva_tipo');
            const option = select.querySelector(`option[value="${tipoFalta}"]`);
            const infoElement = document.getElementById('info-porcentaje');

            if (option && option.dataset.porcentaje) {
                const porcentaje = parseFloat(option.dataset.porcentaje);
                let texto = '';

                if (porcentaje === -100) {
                    texto = '⚠️ La empresa NO paga este día - se DEDUCE del salario';
                    infoElement.style.color = '#dc3545';
                } else if (porcentaje === 0) {
                    texto = 'ℹ️ La empresa NO paga este día';
                    infoElement.style.color = '#ffc107';
                } else if (porcentaje === 100) {
                    texto = '✅ La empresa paga el 100% de este día';
                    infoElement.style.color = '#28a745';
                } else {
                    texto = `📊 La empresa paga el ${porcentaje}% de este día`;
                    infoElement.style.color = '#17a2b8';
                }

                infoElement.textContent = texto;
                infoElement.style.display = 'block';
            } else {
                infoElement.style.display = 'none';
            }
        }

        // También para el modal de edición
        function actualizarPorcentajeEdicion(tipoFalta) {
            const select = document.getElementById('editar_tipo');
            const option = select.querySelector(`option[value="${tipoFalta}"]`);
            const infoElement = document.getElementById('info-porcentaje-edicion');

            if (option && option.dataset.porcentaje && infoElement) {
                const porcentaje = parseFloat(option.dataset.porcentaje);
                let texto = '';

                if (porcentaje === -100) {
                    texto = '⚠️ La empresa NO paga este día - se DEDUCE del salario';
                    infoElement.style.color = '#dc3545';
                } else if (porcentaje === 0) {
                    texto = 'ℹ️ La empresa NO paga este día';
                    infoElement.style.color = '#ffc107';
                } else if (porcentaje === 100) {
                    texto = '✅ La empresa paga el 100% de este día';
                    infoElement.style.color = '#28a745';
                } else {
                    texto = `📊 La empresa paga el ${porcentaje}% de este día`;
                    infoElement.style.color = '#17a2b8';
                }

                infoElement.textContent = texto;
                infoElement.style.display = 'block';
            } else if (infoElement) {
                infoElement.style.display = 'none';
            }
        }

        // Manejar clicks en botones de editar con data attributes (más seguro)
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.btn-editar-falta');
            if (!btn) return;

            const data = {
                id: btn.dataset.id,
                nombre: btn.dataset.nombre,
                sucursal: btn.dataset.sucursal,
                fecha: btn.dataset.fecha,
                tipo: btn.dataset.tipo,
                observaciones: btn.dataset.observaciones,
                observacionesRrhh: btn.dataset.observacionesRrhh,
                foto: btn.dataset.foto
            };

            console.log('Abriendo modal con datos:', data); // DEBUG

            mostrarModalEditarFalta(
                data.id,
                data.nombre,
                data.sucursal,
                data.fecha,
                data.tipo,
                data.observaciones,
                data.observacionesRrhh,
                data.foto
            );
        });

