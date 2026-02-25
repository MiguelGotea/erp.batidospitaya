                        <!-- Pestaña de INSS -->
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <div id="inss" class="tab-pane <?= $pestaña_activa == 'inss' ? 'active' : '' ?>">
                                <!-- Sección de Documentos Obligatorios Faltantes -->
                                <div
                                    style="margin: 20px 0; padding: 15px; background: #fff3cd; border-radius: 8px; border: 1px solid #ffeaa7;">
                                    <h4 style="color: #856404; margin-bottom: 15px;">
                                        <i class="fas fa-exclamation-triangle"></i> Documentos Obligatorios Faltantes -
                                        <?= obtenerNombrePestaña($pestaña_activa) ?>
                                    </h4>

                                    <?php
                                    $documentosFaltantesPestana = obtenerDocumentosFaltantesPestana($codOperario, $pestaña_activa);
                                    ?>

                                    <?php if (!empty($documentosFaltantesPestana)): ?>
                                        <ul style="color: #856404; margin: 0; padding-left: 20px;">
                                            <?php foreach ($documentosFaltantesPestana as $documento): ?>
                                                <li><?= htmlspecialchars($documento) ?></li>
                                            <?php endforeach; ?>
                                        </ul>

                                        <p style="color: #856404; margin: 15px 0 0 0; font-style: italic;">
                                            <i class="fas fa-info-circle"></i> Estos documentos deben ser subidos para
                                            completar la
                                            información.
                                        </p>
                                    <?php else: ?>
                                        <div style="color: #155724; background: #d4edda; padding: 10px; border-radius: 4px;">
                                            <i class="fas fa-check-circle"></i> Todos los documentos obligatorios están
                                            completos para
                                            esta pestaña.
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php
                                // Obtener datos del contrato actual con información INSS
                                $contratoConINSS = obtenerContratoConINSS($codOperario);
                                ?>

                                <?php if (isset($_GET['confirmar']) && $_GET['confirmar'] == 1 && isset($_SESSION['confirmacion_inss'])): ?>
                                    <div class="alert alert-warning">
                                        <h4>Confirmación requerida</h4>
                                        <p>Ya existe un salario INSS registrado para este colaborador. ¿Desea registrar un
                                            nuevo salario
                                            INSS?
                                            El registro anterior será finalizado automáticamente.</p>
                                        <form method="POST" action="">
                                            <input type="hidden" name="pestaña" value="inss">

                                            <div class="form-row">
                                                <div class="form-col">
                                                    <div class="form-group">
                                                        <label for="codigo_inss">Número de Seguro INSS</label>
                                                        <input type="text" id="codigo_inss" name="codigo_inss"
                                                            class="form-control"
                                                            value="<?= htmlspecialchars($colaborador['codigo_inss'] ?? '') ?>">
                                                    </div>

                                                    <div class="form-group">
                                                        <label for="hospital_riesgo_laboral">Hospital Asignado para Riesgo
                                                            Laboral</label>
                                                        <input type="text" id="hospital_riesgo_laboral"
                                                            name="hospital_riesgo_laboral" class="form-control"
                                                            value="<?= htmlspecialchars($colaborador['hospital_riesgo_laboral'] ?? '') ?>">
                                                    </div>
                                                </div>

                                                <div class="form-col">
                                                    <div class="form-group">
                                                        <label for="numero_planilla">Número de Planilla</label>
                                                        <select id="numero_planilla" name="numero_planilla"
                                                            class="form-control">
                                                            <option value="">Seleccionar planilla...</option>
                                                            <?php foreach ($planillasPatronales as $planilla): ?>
                                                                <option value="<?= $planilla['CodPlanilla'] ?>" <?= ($contratoConINSS && $contratoConINSS['numero_planilla'] == $planilla['CodPlanilla']) ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($planilla['nombre_planilla']) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="form-group">
                                                        <label for="hospital_inss">Hospital Asociado</label>
                                                        <input type="text" id="hospital_inss" name="hospital_inss"
                                                            class="form-control"
                                                            value="<?= $contratoConINSS ? htmlspecialchars($contratoConINSS['hospital_inss'] ?? '') : '' ?>">
                                                    </div>
                                                </div>
                                            </div>

                                            <button type="submit" class="btn-submit">Guardar Cambios INSS</button>
                                        </form>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" action="">
                                    <input type="hidden" name="pestaña" value="inss">

                                    <div class="form-row">
                                        <div class="form-col">
                                            <div class="form-group">
                                                <label for="codigo_inss">Número de Seguro INSS</label>
                                                <input type="text" id="codigo_inss" name="codigo_inss" class="form-control"
                                                    value="<?= htmlspecialchars($colaborador['codigo_inss'] ?? '') ?>">
                                            </div>

                                            <div class="form-group">
                                                <label for="hospital_riesgo_laboral">Hospital Asignado para Riesgo
                                                    Laboral</label>
                                                <input type="text" id="hospital_riesgo_laboral"
                                                    name="hospital_riesgo_laboral" class="form-control"
                                                    value="<?= htmlspecialchars($colaborador['hospital_riesgo_laboral'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="form-col">
                                            <div class="form-group">
                                                <label for="numero_planilla">Número de Planilla</label>
                                                <select id="numero_planilla" name="numero_planilla" class="form-control">
                                                    <option value="">Seleccionar planilla...</option>
                                                    <?php foreach ($planillasPatronales as $planilla): ?>
                                                        <option value="<?= $planilla['CodPlanilla'] ?>" <?= ($contratoConINSS && $contratoConINSS['numero_planilla'] == $planilla['CodPlanilla']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($planilla['nombre_planilla']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label for="hospital_inss">Hospital Asociado</label>
                                                <input type="text" id="hospital_inss" name="hospital_inss"
                                                    class="form-control"
                                                    value="<?= $contratoConINSS ? htmlspecialchars($contratoConINSS['hospital_inss'] ?? '') : '' ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn-submit">Guardar Cambios INSS</button>
                                </form>

                                <div
                                    style="display: flex; justify-content: space-between; align-items: center; margin: 30px 0 20px 0; display:none;">
                                    <h3 style="color: #0E544C; margin: 0;">Historial de Salarios INSS</h3>
                                    <button type="button" class="btn-submit" onclick="abrirModalSalarioINSS()"
                                        style="margin: 0;">
                                        <i class="fas fa-plus"></i> Agregar Salario INSS
                                    </button>
                                </div>

                                <div style="display:none;">
                                    <?php if (count($salariosINSS) > 0): ?>
                                        <div style="overflow-x: auto;">
                                            <table style="width: 100%; border-collapse: collapse;">
                                                <thead>
                                                    <tr style="background-color: #0E544C; color: white;">
                                                        <th style="padding: 10px; text-align: left;">Salario INSS</th>
                                                        <th style="padding: 10px; text-align: left;">Inicio</th>
                                                        <th style="padding: 10px; text-align: left;">Final</th>
                                                        <th style="padding: 10px; text-align: left;">Observaciones</th>
                                                        <th style="padding: 10px; text-align: left;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($salariosINSS as $salario): ?>
                                                        <tr style="border-bottom: 1px solid #ddd;">
                                                            <td style="padding: 10px;">C$
                                                                <?= number_format($salario['monto_salario_inss'], 2) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= !empty($salario['inicio']) ? date('d/m/Y', strtotime($salario['inicio'])) : '' ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= !empty($salario['final']) ? date('d/m/Y', strtotime($salario['final'])) : 'Actual' ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($salario['observaciones_inss'] ?? '') ?>
                                                            </td>
                                                            <td style="padding: 10px; text-align: center;">
                                                                <button type="button" class="btn-accion btn-editar"
                                                                    onclick="editarSalarioINSS(<?= $salario['id'] ?>)">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p style="text-align: center; color: #6c757d; padding: 20px;">No hay registros de
                                            salario INSS
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <!-- Sección de Archivos Adjuntos -->
                                <div style="margin-top: 40px; border-top: 2px solid #6c757d; padding-top: 20px;">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                        <h3 style="color: #6c757d; margin: 0;">Archivos Adjuntos</h3>
                                        <button type="button" class="btn-submit"
                                            onclick="abrirModalAdjunto('<?= $pestaña_activa ?>')" style="margin: 0;">
                                            <i class="fas fa-plus"></i> Agregar Archivo
                                        </button>
                                    </div>

                                    <?php if (count($archivosAdjuntos) > 0): ?>
                                        <div style="overflow-x: auto;">
                                            <table style="width: 100%; border-collapse: collapse;">
                                                <thead>
                                                    <tr style="background-color: #6c757d; color: white;">
                                                        <th style="padding: 10px; text-align: left;">Nombre</th>
                                                        <th style="padding: 10px; text-align: left;">Descripción</th>
                                                        <th style="padding: 10px; text-align: left; display:none;">Tamaño
                                                        </th>
                                                        <th style="padding: 10px; text-align: left;">Subido por</th>
                                                        <th style="padding: 10px; text-align: left;">Fecha</th>
                                                        <th style="padding: 10px; text-align: left;">Tipo de Documento</th>
                                                        <th style="padding: 10px; text-align: left;">Contrato Asociado</th>
                                                        <th style="padding: 10px; text-align: center;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($archivosAdjuntos as $archivo):
                                                        $tamaño = $archivo['tamaño'];
                                                        if ($tamaño < 1024) {
                                                            $tamañoFormateado = $tamaño . ' B';
                                                        } elseif ($tamaño < 1048576) {
                                                            $tamañoFormateado = round($tamaño / 1024, 2) . ' KB';
                                                        } else {
                                                            $tamañoFormateado = round($tamaño / 1048576, 2) . ' MB';
                                                        }
                                                        ?>
                                                        <tr style="border-bottom: 1px solid #ddd;">
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($archivo['nombre_archivo']) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($archivo['descripcion'] ?? '-') ?>
                                                            </td>
                                                            <td style="padding: 10px; display:none;"><?= $tamañoFormateado ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($archivo['nombre_usuario'] . ' ' . $archivo['apellido_usuario']) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= date('d/m/Y H:i', strtotime($archivo['fecha_subida'])) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?php
                                                                if (!empty($archivo['tipo_documento'])) {
                                                                    $tiposDocumentos = obtenerTiposDocumentosPorPestaña($pestaña_activa);
                                                                    $todosTipos = array_merge($tiposDocumentos['obligatorios'], $tiposDocumentos['opcionales']);
                                                                    echo htmlspecialchars($todosTipos[$archivo['tipo_documento']] ?? $archivo['tipo_documento']);

                                                                    if ($archivo['obligatorio']) {
                                                                        echo ' <span style="color: #dc3545;" title="Documento Obligatorio">●</span>';
                                                                    }
                                                                } else {
                                                                    echo '<span style="color: #6c757d; font-style: italic;">Sin categorizar</span>';
                                                                }
                                                                ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?php
                                                                // Mostrar información del contrato usando codigo_manual_contrato
                                                                $pestañasConContrato = ['contrato', 'adendums', 'inss', 'salario', 'movimientos', 'categoria'];
                                                                if (in_array($pestaña_activa, $pestañasConContrato) && !empty($archivo['codigo_manual_contrato'])):
                                                                    ?>
                                                                    <span
                                                                        style="font-weight: 500;"><?= htmlspecialchars($archivo['codigo_manual_contrato']) ?></span>
                                                                    <br>
                                                                    <small style="color: #6c757d;">
                                                                        <?= !empty($archivo['inicio_contrato']) ? date('d/m/Y', strtotime($archivo['inicio_contrato'])) : 'Fecha no disponible' ?>
                                                                        <?php if (!empty($archivo['fin_contrato']) && $archivo['fin_contrato'] != '0000-00-00'): ?>
                                                                            - <?= date('d/m/Y', strtotime($archivo['fin_contrato'])) ?>
                                                                        <?php else: ?>
                                                                            (Activo)
                                                                        <?php endif; ?>
                                                                    </small>
                                                                    <?php if (!empty($archivo['tipo_contrato'])): ?>
                                                                        <br>
                                                                        <small
                                                                            style="color: #0E544C;"><?= htmlspecialchars($archivo['tipo_contrato']) ?></small>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <span style="color: #6c757d; font-style: italic;">
                                                                        <?= in_array($pestaña_activa, $pestañasConContrato) ? 'Sin contrato asociado' : 'No aplica' ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td style="padding: 10px; text-align: center;">
                                                                <a href="javascript:void(0)"
                                                                    onclick="visualizarAdjunto('<?= htmlspecialchars($archivo['ruta_archivo']) ?>')"
                                                                    class="btn-accion btn-editar" title="Ver archivo">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <form method="POST" action="" style="display: inline;">
                                                                    <input type="hidden" name="accion_adjunto" value="eliminar">
                                                                    <input type="hidden" name="id_adjunto"
                                                                        value="<?= $archivo['id'] ?>">
                                                                    <input type="hidden" name="pestaña_adjunto"
                                                                        value="<?= $pestaña_activa ?>">
                                                                    <button type="submit"
                                                                        onclick="return confirm('¿Está seguro de eliminar este archivo?')"
                                                                        class="btn-accion btn-eliminar" title="Eliminar archivo">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p style="text-align: center; color: #6c757d; padding: 20px;">No hay archivos
                                            adjuntos</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

