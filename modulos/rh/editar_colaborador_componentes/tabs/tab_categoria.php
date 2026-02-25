                            <div id="categoria" class="tab-pane <?= $pestaña_activa == 'categoria' ? 'active' : '' ?>">
                                <form method="POST" action="">
                                    <input type="hidden" name="accion_categoria" value="agregar">
                                    <input type="hidden" name="pestaña" value="categoria">

                                    <div class="form-row">
                                        <div class="form-col">
                                            <div class="form-group">
                                                <label for="id_categoria">Categoría *</label>
                                                <select id="id_categoria" name="id_categoria" class="form-control" required>
                                                    <option value="">Seleccionar categoría...</option>
                                                    <?php foreach ($todasCategorias as $categoria): ?>
                                                        <option value="<?= $categoria['idCategoria'] ?>">
                                                            <?= htmlspecialchars($categoria['NombreCategoria']) ?>
                                                            (Peso: <?= $categoria['Peso'] ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-col">
                                            <div class="form-group">
                                                <label for="fecha_inicio">Fecha de Inicio *</label>
                                                <input type="date" id="fecha_inicio" name="fecha_inicio"
                                                    class="form-control" value="<?= date('Y-m-d') ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn-submit">Agregar Categoría</button>
                                </form>

                                <div style="margin-top: 40px; border-top: 2px solid #0E544C; padding-top: 20px;">
                                    <h3 style="color: #0E544C; margin-bottom: 15px;">Historial de Categorías</h3>

                                    <?php if (count($categoriasColaborador) > 0): ?>
                                        <div style="overflow-x: auto;">
                                            <table style="width: 100%; border-collapse: collapse;">
                                                <thead>
                                                    <tr style="background-color: #0E544C; color: white;">
                                                        <th style="padding: 10px; text-align: left;">Categoría</th>
                                                        <th style="padding: 10px; text-align: left;">Peso</th>
                                                        <th style="padding: 10px; text-align: left;">Fecha Inicio</th>
                                                        <th style="padding: 10px; text-align: left;">Fecha Fin</th>
                                                        <th style="padding: 10px; text-align: left; display:none;">Estado
                                                        </th>
                                                        <th style="padding: 10px; text-align: center;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($categoriasColaborador as $categoria):
                                                        $estado = empty($categoria['FechaFin']) ?
                                                            '<span style="color: green; font-weight: bold;">ACTIVA</span>' :
                                                            '<span style="color: #6c757d;">INACTIVA</span>';
                                                        ?>
                                                        <tr style="border-bottom: 1px solid #ddd;">
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($categoria['NombreCategoria']) ?>
                                                            </td>
                                                            <td style="padding: 10px;"><?= $categoria['Peso'] ?></td>
                                                            <td style="padding: 10px;">
                                                                <?= date('d/m/Y', strtotime($categoria['FechaInicio'])) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= !empty($categoria['FechaFin']) ? date('d/m/Y', strtotime($categoria['FechaFin'])) : 'No definida' ?>
                                                            </td>
                                                            <td style="padding: 10px; display:none;"><?= $estado ?></td>
                                                            <td style="padding: 10px; text-align: center;">
                                                                <button type="button" class="btn-accion btn-editar"
                                                                    onclick="editarCategoria(<?= $categoria['id'] ?>)">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p style="text-align: center; color: #6c757d; padding: 20px;">No hay categorías
                                            registradas</p>
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

                                    <?php
                                    // Obtener archivos adjuntos de la pestaña categoría
                                    $archivosAdjuntosCategoria = obtenerArchivosAdjuntos($codOperario, 'categoria');
                                    ?>

                                    <?php if (count($archivosAdjuntosCategoria) > 0): ?>
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
                                                    <?php foreach ($archivosAdjuntosCategoria as $archivo):
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

