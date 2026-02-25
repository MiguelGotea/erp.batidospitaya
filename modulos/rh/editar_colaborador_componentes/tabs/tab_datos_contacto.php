<!-- Pestaña de Datos de Contacto -->
<?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
    <div id="datos-contacto" class="tab-pane <?= $pestaña_activa == 'datos-contacto' ? 'active' : '' ?>">
        <form method="POST" action="">
            <input type="hidden" name="accion" value="guardar_datos_contacto">
            <input type="hidden" name="pestaña" value="datos-contacto">

            <div class="form-group">
                <label for="direccion">Dirección *</label>
                <textarea id="direccion" name="direccion" class="form-control"
                    rows="3"><?= htmlspecialchars($colaborador['direccion'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="ciudad">Ciudad *</label>
                        <input type="text" id="ciudad" name="ciudad" class="form-control"
                            value="<?= htmlspecialchars($colaborador['Ciudad'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="celular">Teléfono Móvil (celular) *</label>
                        <input type="text" id="celular" name="celular" class="form-control"
                            value="<?= htmlspecialchars($colaborador['Celular'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="email_personal">Email Personal *</label>
                        <input type="email" id="email_personal" name="email_personal" class="form-control"
                            value="<?= htmlspecialchars($colaborador['email_personal'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-col">
                    <div class="form-group">
                        <label for="telefono_casa">Teléfono de Casa</label>
                        <input type="text" id="telefono_casa" name="telefono_casa" class="form-control"
                            value="<?= htmlspecialchars($colaborador['telefono_casa'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="telefono_corporativo">Teléfono Corporativo</label>
                        <input type="text" id="telefono_corporativo" name="telefono_corporativo" class="form-control"
                            value="<?= htmlspecialchars($colaborador['telefono_corporativo'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="email_trabajo">Email de Trabajo</label>
                        <input type="email" id="email_trabajo" name="email_trabajo" class="form-control"
                            value="<?= htmlspecialchars($colaborador['email_trabajo'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-submit">Guardar Cambios</button>
        </form>
    </div>
<?php endif; ?>