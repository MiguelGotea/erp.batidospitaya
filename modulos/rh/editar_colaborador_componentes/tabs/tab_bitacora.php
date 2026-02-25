                        <!-- Pestaña de Bitácora -->
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <div id="bitacora" class="tab-pane <?= $pestaña_activa == 'bitacora' ? 'active' : '' ?>">
                                <div style="margin-bottom: 30px;">
                                    <h3 style="color: #0E544C; margin-bottom: 15px;">Nueva Anotación</h3>

                                    <form method="POST" action="">
                                        <input type="hidden" name="accion_bitacora" value="agregar">
                                        <input type="hidden" name="pestaña" value="bitacora">

                                        <div class="form-group">
                                            <label for="anotacion">Anotación *</label>
                                            <textarea id="anotacion" name="anotacion" class="form-control" rows="5"
                                                placeholder="Escriba aquí cualquier nota, observación o comentario sobre el colaborador..."
                                                required></textarea>
                                            <small style="color: #6c757d;">
                                                Esta anotación quedará registrada permanentemente y no podrá ser editada
                                                ni
                                                eliminada.
                                            </small>
                                        </div>

                                        <button type="submit" class="btn-submit">
                                            <i class="fas fa-save"></i> Guardar Anotación
                                        </button>
                                    </form>
                                </div>

                                <div style="border-top: 2px solid #0E544C; padding-top: 20px;">
                                    <h3 style="color: #0E544C; margin-bottom: 15px;">
                                        Historial de Bitácora
                                        <span style="font-size: 0.8em; color: #6c757d;">(<?= count($bitacoraColaborador) ?>
                                            anotaciones)</span>
                                    </h3>

                                    <?php if (count($bitacoraColaborador) > 0): ?>
                                        <div style="display: flex; flex-direction: column; gap: 15px;">
                                            <?php foreach ($bitacoraColaborador as $anotacion): ?>
                                                <div
                                                    style="background: #f8f9fa; border-left: 4px solid #0E544C; padding: 15px; border-radius: 4px;">
                                                    <div
                                                        style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                                                        <div>
                                                            <strong style="color: #0E544C;">
                                                                <i class="fas fa-user"></i>
                                                                <?= htmlspecialchars($anotacion['nombre_usuario']) ?>
                                                            </strong>
                                                        </div>
                                                        <div style="color: #6c757d; font-size: 0.9em;">
                                                            <i class="fas fa-calendar"></i>
                                                            <?= date('d/m/Y H:i', strtotime($anotacion['fecha_registro'])) ?>
                                                        </div>
                                                    </div>
                                                    <div style="color: #333; line-height: 1.6; white-space: pre-wrap;">
                                                        <?= htmlspecialchars($anotacion['anotacion']) ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="text-align: center; padding: 40px; color: #6c757d;">
                                            <i class="fas fa-clipboard"
                                                style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;"></i>
                                            <p>No hay anotaciones en la bitácora</p>
                                            <p style="font-size: 0.9em;">Las anotaciones aparecerán aquí una vez que se
                                                registren</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
