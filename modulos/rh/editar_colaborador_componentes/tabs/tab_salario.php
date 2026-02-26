<!-- Pestaña de Salario -->
<?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
    <div id="salario" class="tab-pane <?= $pestaña_activa == 'salario' ? 'active' : '' ?>">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="color: #0E544C; margin: 0;">Historial de Salarios</h3>
            <button type="button" class="btn-submit" onclick="abrirModalSalario()" style="margin: 0;">
                <i class="fas fa-plus"></i> Guardar solo este Salario Adicional
            </button>
        </div>

        <?php if (count($salarios) > 0): ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background-color: #0E544C; color: white;">
                            <th style="padding: 10px; text-align: left;">Monto</th>
                            <th style="padding: 10px; text-align: left;">Desde</th>
                            <th style="padding: 10px; text-align: left;">Hasta</th>
                            <th style="padding: 10px; text-align: left;">Frecuencia</th>
                            <th style="padding: 10px; text-align: left;">Tipo</th>
                            <th style="padding: 10px; text-align: left;">Observaciones</th>
                            <th style="padding: 10px; text-align: center;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($salarios as $salario): ?>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 10px;">C$ <?= number_format($salario['monto'], 2) ?>
                                </td>
                                <td style="padding: 10px;">
                                    <?= !empty($salario['inicio']) ? date('d/m/Y', strtotime($salario['inicio'])) : '' ?>
                                </td>
                                <td style="padding: 10px;">
                                    <?= !empty($salario['fin']) ? date('d/m/Y', strtotime($salario['fin'])) : 'Actual' ?>
                                </td>
                                <td style="padding: 10px;"><?= ucfirst($salario['frecuencia_pago']) ?>
                                </td>
                                <td style="padding: 10px;">
                                    <?= $salario['es_salario_inicial'] ? '<span style="color: #0E544C; font-weight: bold;">Salario Inicial</span>' : 'Salario Adicional' ?>
                                </td>
                                <td style="padding: 10px;">
                                    <?= htmlspecialchars($salario['observaciones'] ?? '') ?>
                                </td>
                                <td style="padding: 10px; text-align: center;">
                                    <!-- Solo permitir editar/eliminar salarios adicionales, no el inicial -->
                                    <?php if (!$salario['es_salario_inicial']): ?>
                                        <button type="button" class="btn-accion btn-editar"
                                            onclick="editarSalario(<?= $salario['CodSalarioOperario'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="accion_salario" value="eliminar">
                                            <input type="hidden" name="id_salario" value="<?= $salario['CodSalarioOperario'] ?>">
                                            <button style="display:none;" type="submit"
                                                onclick="return confirm('¿Está seguro de eliminar este registro de salario?')"
                                                class="btn-accion btn-eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #6c757d; font-style: italic;">Editar en pestaña
                                            Contrato</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="text-align: center; color: #6c757d; padding: 20px;">No hay registros de
                salario</p>
        <?php endif; ?>

        <!-- Sección de Archivos Adjuntos -->
        <div style="margin-top: 40px; border-top: 2px solid #6c757d; padding-top: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: #6c757d; margin: 0;">Archivos Adjuntos</h3>
                <button type="button" class="btn-submit" onclick="abrirModalAdjunto('<?= $pestaña_activa ?>')"
                    style="margin: 0;">
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
                                // Formatear tamaño del archivo
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
                                                <small style="color: #0E544C;"><?= htmlspecialchars($archivo['tipo_contrato']) ?></small>
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
                                            <input type="hidden" name="id_adjunto" value="<?= $archivo['id'] ?>">
                                            <input type="hidden" name="pestaña_adjunto" value="<?= $pestaña_activa ?>">
                                            <button type="submit" onclick="return confirm('¿Está seguro de eliminar este archivo?')"
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