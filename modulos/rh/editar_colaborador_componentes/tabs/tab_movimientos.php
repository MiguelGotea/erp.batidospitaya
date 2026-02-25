<!-- Pestaña de Movimientos -->
<?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
    <div id="movimientos" class="tab-pane <?= $pestaña_activa == 'movimientos' ? 'active' : '' ?>">
        <?php
        $historialCargos = obtenerHistorialCargos($codOperario);
        $cargosDisponibles = obtenerTodosCargos();
        $sucursales = obtenerTodasSucursales();
        ?>

        <div style="margin-bottom: 30px;">
            <h3 style="color: #0E544C; margin-bottom: 15px;">Agregar Nuevo Cargo</h3>

            <?php if ($contratoActual): ?>
                <div class="readonly-info" style="margin-bottom: 20px;">
                    <p><strong>Contrato Asociado:</strong>
                        <?= htmlspecialchars($contratoActual['codigo_manual_contrato'] ?? 'Sin código') ?>
                    </p>
                    <p><strong>Los movimientos se asociarán automáticamente a este contrato</strong>
                    </p>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="pestaña" value="movimientos">
                <input type="hidden" name="accion_movimiento" value="agregar">

                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="nuevo_cod_cargo">Cargo *</label>
                            <select id="nuevo_cod_cargo" name="cod_cargo" class="form-control" required>
                                <option value="">Seleccionar cargo...</option>
                                <?php foreach ($cargosDisponibles as $cargo): ?>
                                    <option value="<?= $cargo['CodNivelesCargos'] ?>">
                                        <?= htmlspecialchars($cargo['Nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="nuevo_sucursal">Sucursal *</label>
                            <select id="nuevo_sucursal" name="sucursal" class="form-control" required>
                                <option value="">Seleccionar sucursal...</option>
                                <?php foreach ($sucursales as $sucursal): ?>
                                    <option value="<?= $sucursal['codigo'] ?>">
                                        <?= htmlspecialchars($sucursal['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group">
                            <label for="nuevo_fecha_inicio">Fecha de Inicio *</label>
                            <input type="date" id="nuevo_fecha_inicio" name="fecha_inicio" class="form-control"
                                value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <!-- Tipo de contrato oculto con valor 3 -->
                        <input type="hidden" name="tipo_contrato" value="3">
                    </div>
                </div>

                <button type="submit" class="btn-submit">Agregar Cargo</button>
            </form>
        </div>

        <div style="border-top: 2px solid #0E544C; padding-top: 20px;">
            <h3 style="color: #0E544C; margin-bottom: 15px;">Historial de Cargos</h3>

            <?php if (count($historialCargos) > 0): ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background-color: #0E544C; color: white;">
                                <th style="padding: 10px; text-align: left;">Cargo</th>
                                <th style="padding: 10px; text-align: left;">Sucursal</th>
                                <th style="padding: 10px; text-align: left;">Fecha Inicio</th>
                                <th style="padding: 10px; text-align: left;">Fecha Fin</th>
                                <th style="padding: 10px; text-align: left;">Tipo Contrato</th>
                                <th style="padding: 10px; text-align: center;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historialCargos as $cargo): ?>
                                <tr style="border-bottom: 1px solid #ddd;">
                                    <td style="padding: 10px;">
                                        <?= htmlspecialchars($cargo['nombre_cargo'] ?? 'No definido') ?>
                                    </td>
                                    <td style="padding: 10px;">
                                        <?= htmlspecialchars($cargo['nombre_sucursal'] ?? 'No definida') ?>
                                    </td>
                                    <td style="padding: 10px;">
                                        <?= !empty($cargo['Fecha']) ? date('d/m/Y', strtotime($cargo['Fecha'])) : 'No definida' ?>
                                    </td>
                                    <td style="padding: 10px;">
                                        <?= !empty($cargo['Fin']) ? date('d/m/Y', strtotime($cargo['Fin'])) : 'Activo' ?>
                                    </td>
                                    <td style="padding: 10px;">
                                        <?= htmlspecialchars($cargo['nombre_tipo_contrato'] ?? 'No definido') ?>
                                    </td>
                                    <td style="padding: 10px; text-align: center;">
                                        <button type="button" class="btn-accion btn-editar"
                                            onclick="editarMovimiento(<?= $cargo['CodAsignacionNivelesCargos'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #6c757d; padding: 20px;">No hay historial de
                    cargos</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>