// Función para actualizar los contadores
function actualizarContadores() {
    let totalGlobal = 0;

    document.querySelectorAll('.sucursal-card').forEach(function (card) {
        const sucursalId = card.dataset.sucursalId;

        const areaLideres = card.querySelector('.sortable-lideres');
        let totalLideres = 0;
        if (areaLideres) {
            totalLideres = areaLideres.querySelectorAll('.drag-item').length;
        }

        const areaColaboradores = card.querySelector('.sortable-colaboradores');
        let totalColaboradores = 0;
        if (areaColaboradores) {
            totalColaboradores = areaColaboradores.querySelectorAll('.drag-item').length;
        }

        const totalSucursal = totalLideres + totalColaboradores;

        const counter = card.querySelector('.sucursal-counter');
        if (counter) {
            counter.textContent = totalSucursal;
        }

        totalGlobal += totalSucursal;
    });

    const areaNoAsignados = document.querySelector('.sortable-no-asignados');
    if (areaNoAsignados) {
        totalGlobal += areaNoAsignados.querySelectorAll('.drag-item').length;
    }

    const globalCounter = document.querySelector('.global-counter');
    if (globalCounter) {
        globalCounter.textContent = 'Total: ' + totalGlobal + ' colaboradores';
    }
}

let estadoInicial = null;

document.addEventListener('DOMContentLoaded', function () {
    const tipoSemana = document.body.dataset.tipoSemana;
    const tienePermisoPlanificacion = document.body.dataset.tienePermisoPlanificacion === '1';

    if (tipoSemana === 'siguiente') {
        actualizarContadores();

        if (tienePermisoPlanificacion) {
            guardarEstadoInicial();

            document.querySelectorAll('.sortable-lideres').forEach(function (el) {
                new Sortable(el, {
                    group: {
                        name: 'lideres',
                        put: function (to, from, item) {
                            const cargo = parseInt(item.dataset.cargo);
                            if (cargo !== 5 && cargo !== 43) {
                                return false;
                            }

                            if (to.el.children.length >= parseInt(to.el.dataset.max)) {
                                alert('Máximo ' + to.el.dataset.max + ' líderes por sucursal');
                                return false;
                            }

                            return true;
                        }
                    },
                    animation: 150,
                    ghostClass: 'grabbing',
                    onEnd: function (evt) {
                        actualizarMovimientos();
                        actualizarContadores();
                    }
                });
            });

            document.querySelectorAll('.sortable-colaboradores').forEach(function (el) {
                new Sortable(el, {
                    group: {
                        name: 'colaboradores',
                        put: function (to, from, item) {
                            const cargo = parseInt(item.dataset.cargo);
                            if (cargo === 5 || cargo === 43) {
                                return false;
                            }
                            return true;
                        }
                    },
                    animation: 150,
                    ghostClass: 'grabbing',
                    onEnd: function (evt) {
                        actualizarMovimientos();
                        actualizarContadores();
                    }
                });
            });

            const sortableNoAsignados = document.querySelector('.sortable-no-asignados');
            if (sortableNoAsignados) {
                new Sortable(sortableNoAsignados, {
                    group: {
                        name: 'colaboradores',
                        put: true,
                        pull: true
                    },
                    animation: 150,
                    ghostClass: 'grabbing',
                    onEnd: function (evt) {
                        actualizarMovimientos();
                        actualizarContadores();
                    }
                });
            }

            const btnReset = document.getElementById('btnReset');
            if (btnReset) btnReset.addEventListener('click', restaurarEstadoInicial);

            const btnGuardar = document.getElementById('btnGuardar');
            if (btnGuardar) btnGuardar.addEventListener('click', function (e) {
                e.preventDefault();

                const errores = validarAntesDeGuardar();
                if (errores.length > 0) {
                    alert('Errores encontrados:\n\n' + errores.join('\n'));
                    return;
                }

                if (confirm('¿Está seguro de guardar los cambios? Las nuevas asignaciones se aplicarán desde la semana siguiente.')) {
                    actualizarMovimientos();

                    const formData = new FormData(document.getElementById('movimientosForm'));

                    fetch('ajax/gestion_colaboradores.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                window.location.href = 'gestion_colaboradores.php?semana=siguiente';
                            } else {
                                alert(data.error || 'Error al guardar los cambios.');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error de conexión al guardar los cambios.');
                        });
                }
            });
        } else {
            document.body.classList.add('read-only');
        }
    } else {
        document.body.classList.add('read-only');
    }

    // Configurar clic para editar colaborador
    const tienePermisoEditar = document.body.dataset.tienePermisoEditar === '1';
    if (tienePermisoEditar) {
        let startPos = { x: 0, y: 0, time: 0 };

        document.addEventListener('mousedown', function (e) {
            startPos = { x: e.clientX, y: e.clientY, time: Date.now() };
        });

        document.addEventListener('touchstart', function (e) {
            if (e.touches.length > 0) {
                startPos = { x: e.touches[0].clientX, y: e.touches[0].clientY, time: Date.now() };
            }
        }, { passive: true });

        document.querySelectorAll('.drag-item').forEach(function (item) {
            item.style.cursor = 'pointer';

            // Efecto visual sutil al hacer hover si tiene permiso
            item.addEventListener('mouseenter', function () {
                if (!item.classList.contains('sortable-drag')) {
                    item.style.outline = '2px solid rgba(14, 84, 76, 0.5)';
                    item.style.outlineOffset = '-2px';
                    item.style.borderRadius = '6px';
                }
            });
            item.addEventListener('mouseleave', function () {
                item.style.outline = 'none';
            });

            item.addEventListener('click', function (e) {
                let clientX = e.clientX;
                let clientY = e.clientY;

                // Algunas veces el evento click en touch no tiene clientX/Y
                if (clientX === undefined && e.changedTouches) {
                    clientX = e.changedTouches[0].clientX;
                    clientY = e.changedTouches[0].clientY;
                }

                const diffX = Math.abs(clientX - startPos.x);
                const diffY = Math.abs(clientY - startPos.y);
                const diffTime = Date.now() - startPos.time;

                // Si el movimiento es muy pequeño y fue rápido, lo consideramos un click real
                // 10 pixeles de tolerancia y 500ms
                if (diffX < 10 && diffY < 10 && diffTime < 500) {
                    const codOperario = this.dataset.id;
                    window.open(`https://erp.batidospitaya.com/modulos/rh/editar_colaborador.php?id=${codOperario}&pestaña=adendums`, '_blank');
                } else {
                    e.preventDefault();
                }
            });
        });
    }
});

function guardarEstadoInicial() {
    estadoInicial = {};

    document.querySelectorAll('.sucursal-card').forEach(function (card) {
        const sucursalId = card.dataset.sucursalId;
        estadoInicial[sucursalId] = {
            lideres: [],
            colaboradores: []
        };

        const areaLideres = card.querySelector('.sortable-lideres');
        if (areaLideres) {
            areaLideres.querySelectorAll('.drag-item').forEach(function (item) {
                estadoInicial[sucursalId].lideres.push({
                    id: item.dataset.id,
                    cargo: item.dataset.cargo
                });
            });
        }

        const areaColaboradores = card.querySelector('.sortable-colaboradores');
        if (areaColaboradores) {
            areaColaboradores.querySelectorAll('.drag-item').forEach(function (item) {
                estadoInicial[sucursalId].colaboradores.push({
                    id: item.dataset.id,
                    cargo: item.dataset.cargo
                });
            });
        }
    });

    const areaNoAsignados = document.querySelector('.sortable-no-asignados');
    if (areaNoAsignados) {
        estadoInicial['no_asignados'] = [];
        areaNoAsignados.querySelectorAll('.drag-item').forEach(function (item) {
            estadoInicial['no_asignados'].push({
                id: item.dataset.id,
                cargo: item.dataset.cargo
            });
        });
    }
}

function restaurarEstadoInicial() {
    if (!estadoInicial) return;
    if (confirm('¿Está seguro de restaurar todas las asignaciones originales? Se perderán los cambios no guardados.')) {
        window.location.reload();
    }
}

function actualizarMovimientos() {
    const movimientos = [];

    document.querySelectorAll('.sucursal-card').forEach(function (card) {
        const sucursalId = card.dataset.sucursalId;

        const areaLideres = card.querySelector('.sortable-lideres');
        if (areaLideres) {
            areaLideres.querySelectorAll('.drag-item').forEach(function (item) {
                movimientos.push({
                    cod_operario: item.dataset.id,
                    cod_sucursal_destino: sucursalId,
                    cod_cargo: item.dataset.cargo
                });
            });
        }

        const areaColaboradores = card.querySelector('.sortable-colaboradores');
        if (areaColaboradores) {
            areaColaboradores.querySelectorAll('.drag-item').forEach(function (item) {
                movimientos.push({
                    cod_operario: item.dataset.id,
                    cod_sucursal_destino: sucursalId,
                    cod_cargo: item.dataset.cargo
                });
            });
        }
    });

    const movimientosData = document.getElementById('movimientosData');
    movimientosData.innerHTML = '';

    movimientos.forEach(function (mov, index) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = `movimientos[${index}][cod_operario]`;
        input.value = mov.cod_operario;
        movimientosData.appendChild(input);

        const input2 = document.createElement('input');
        input2.type = 'hidden';
        input2.name = `movimientos[${index}][cod_sucursal_destino]`;
        input2.value = mov.cod_sucursal_destino;
        movimientosData.appendChild(input2);

        const input3 = document.createElement('input');
        input3.type = 'hidden';
        input3.name = `movimientos[${index}][cod_cargo]`;
        input3.value = mov.cod_cargo;
        movimientosData.appendChild(input3);
    });
}

function validarAntesDeGuardar() {
    let errores = [];

    document.querySelectorAll('.sucursal-card').forEach(function (card) {
        const areaLideres = card.querySelector('.sortable-lideres');
        if (areaLideres) {
            const liderIds = [];
            areaLideres.querySelectorAll('.drag-item').forEach(function (item) {
                if (liderIds.includes(item.dataset.id)) {
                    errores.push(`El líder #${item.dataset.id} está duplicado en una sucursal`);
                }
                liderIds.push(item.dataset.id);
            });
        }
    });

    return errores;
}
