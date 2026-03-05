<!-- Pestaña de Adendums -->
<?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
    <div id="adendums" class="tab-pane <?= $pestaña_activa == 'adendums' ? 'active' : '' ?>">
        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
            <?php if (!tieneContratoActivo($codOperario)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    No se puede agregar información de <?= $pestaña_activa ?> porque el colaborador no
                    tiene un
                    contrato activo.
                    Por favor, complete la información del contrato primero.
                </div>
            <?php else: ?>
                <div style="margin-bottom: 30px;">
                    <h3 style="color: #0E544C; margin-bottom: 15px; display:none;">Nuevo
                        Adendum/Movimiento</h3>

                    <form method="POST" action="">
                        <input type="hidden" name="accion_adendum" value="agregar">
                        <input type="hidden" name="pestaña" value="adendums">

                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="tipo_adendum">Tipo de Adendum *</label>
                                    <select id="tipo_adendum" name="tipo_adendum" class="form-control" required
                                        onchange="actualizarCamposAdendum()">
                                        <option value="">Seleccionar tipo...</option>
                                        <option value="cargo">Cambio de Cargo</option>
                                        <option value="salario">Ajuste Salarial</option>
                                        <option value="ambos">Cambio de Cargo y Salario</option>
                                        <option value="movimiento">Movimiento de Tienda</option>
                                    </select>
                                </div>

                                <div class="form-group" id="grupo_cargo">
                                    <label for="cod_cargo_adendum">Cargo *</label>
                                    <select id="cod_cargo_adendum" name="cod_cargo" class="form-control">
                                        <option value="">Seleccionar cargo...</option>
                                        <?php foreach ($cargosDisponibles as $cargo): ?>
                                            <option value="<?= $cargo['CodNivelesCargos'] ?>">
                                                <?= htmlspecialchars($cargo['Nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group" id="grupo_sucursal">
                                    <label for="sucursal_adendum">Sucursal *</label>
                                    <select id="sucursal_adendum" name="sucursal" class="form-control">
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
                                <div class="form-group" id="grupo_categoria" style="display:none;">
                                    <div class="form-group" id="grupo_categoria" style="display:none;">
                                        <label for="id_categoria_adendum">Categoría *</label>
                                        <select id="id_categoria_adendum" name="id_categoria" class="form-control">
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

                                <div class="form-group" id="grupo_salario">
                                    <label for="salario_adendum">Salario (C$) *</label>
                                    <input type="number" id="salario_adendum" name="salario" class="form-control" step="0.01"
                                        min="0" placeholder="0.00">
                                    <small style="color: #6c757d;">Salario de referencia:
                                        <?php
                                        $salarioReferencia = obtenerSalarioReferencia($codOperario);
                                        echo 'C$ ' . number_format($salarioReferencia, 2);
                                        ?>
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label for="fecha_inicio_adendum">Fecha de Inicio *</label>
                                    <input type="date" id="fecha_inicio_adendum" name="fecha_inicio" class="form-control"
                                        value="<?= date('Y-m-d') ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="fecha_fin_adendum">Fecha de Fin (opcional)</label>
                                    <input type="date" id="fecha_fin_adendum" name="fecha_fin" class="form-control">
                                    <small style="color: #6c757d;">
                                        Dejar vacío si es un adendum indefinido. Solo se aplica si es el
                                        primer
                                        adendum o si desea especificar una fecha final.
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="observaciones_adendum">Observaciones</label>
                            <textarea id="observaciones_adendum" name="observaciones" class="form-control" rows="3"
                                placeholder="Observaciones sobre el adendum..."></textarea>
                        </div>

                        <button type="submit" class="btn-submit">
                            Guardar solo este Adendum
                        </button>
                    </form>
                </div>

                <div style="border-top: 2px solid #0E544C; padding-top: 20px;">
                    <h3 style="color: #0E544C; margin-bottom: 15px;">Historial de Adendums</h3>

                    <?php if (count($adendumsColaborador) > 0): ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background-color: #0E544C; color: white;">
                                        <th style="padding: 10px; text-align: left;">Tipo</th>
                                        <th style="padding: 10px; text-align: left;">Cargo</th>
                                        <th style="padding: 10px; text-align: left; display:none;">Categoría
                                        </th>
                                        <th style="padding: 10px; text-align: left;">Salario</th>
                                        <th style="padding: 10px; text-align: left;">Sucursal</th>
                                        <th style="padding: 10px; text-align: left;">Fecha Inicio</th>
                                        <th style="padding: 10px; text-align: left;">Fecha Fin</th>
                                        <th style="padding: 10px; text-align: left;">Estado</th>
                                        <th style="padding: 10px; text-align: center;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($adendumsColaborador as $adendum):
                                        $estado = empty($adendum['Fin']) ?
                                            '<span style="color: green; font-weight: bold;">ACTIVO</span>' :
                                            '<span style="color: #6c757d;">INACTIVO</span>';

                                        $tipoTexto = [
                                            'cargo' => 'Cambio Cargo',
                                            'salario' => 'Ajuste Salarial',
                                            'ambos' => 'Cargo y Salario',
                                            'movimiento' => 'Movimiento de Tienda'
                                        ];
                                    ?>
                                        <tr style="border-bottom: 1px solid #ddd;">
                                            <td style="padding: 10px;">
                                                <?= $tipoTexto[$adendum['TipoAdendum']] ?? 'No definido' ?>
                                            </td>
                                            <td style="padding: 10px;">
                                                <?= htmlspecialchars($adendum['nombre_cargo'] ?? 'No definido') ?>
                                            </td>

                                            <td style="padding: 10px;">
                                                <?= $adendum['Salario'] ? 'C$ ' . number_format($adendum['Salario'], 2) : 'No definido' ?>
                                            </td>
                                            <td style="padding: 10px;">
                                                <?= htmlspecialchars($adendum['nombre_sucursal'] ?? 'No definida') ?>
                                            </td>
                                            <td style="padding: 10px;">
                                                <?= date('d/m/Y', strtotime($adendum['Fecha'])) ?>
                                            </td>
                                            <td style="padding: 10px;">
                                                <?= !empty($adendum['Fin']) ? date('d/m/Y', strtotime($adendum['Fin'])) : 'No definida' ?>
                                            </td>
                                            <td style="padding: 10px;"><?= $estado ?></td>
                                            <td style="padding: 10px; text-align: center;">
                                                <button style="display:none;" type="button" class="btn-accion btn-editar"
                                                    onclick="editarAdendum(<?= $adendum['CodAsignacionNivelesCargos'] ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if (empty($adendum['Fin'])): ?>
                                                    <button type="button" class="btn-accion"
                                                        onclick="abrirModalFinalizarAdenda(<?= $adendum['CodAsignacionNivelesCargos'] ?>)"
                                                        style="color: #dc3545; display:none;" title="Finalizar Adenda">
                                                        <i class="fas fa-flag-checkered"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 8px; margin: 20px 0;">
                            <i class="fas fa-folder-open" style="font-size: 3rem; color: #6c757d; margin-bottom: 15px;"></i>
                            <h4 style="color: #6c757d;">No hay adendums registrados</h4>
                            <p style="color: #6c757d;">Para subir archivos en esta pestaña, primero debe
                                crear un
                                adendum.</p>

                            <div style="margin-top: 20px;">
                                <p style="color: #0E544C; font-weight: bold;">
                                    <i class="fas fa-info-circle"></i> Flujo correcto:
                                </p>
                                <ol style="text-align: left; display: inline-block; color: #6c757d;">
                                    <li>Crear el adendum usando el formulario superior</li>
                                    <li>Luego podrá subir archivos asociados al adendum</li>
                                </ol>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sección de Archivos Adjuntos -->
                <div style="margin-top: 40px; border-top: 2px solid #6c757d; padding-top: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="color: #6c757d; margin: 0;">Archivos Adjuntos</h3>
                        <button type="button" class="btn-submit" onclick="abrirModalAdjunto('<?= $pestaña_activa ?>')"
                            style="margin: 0;">
                            <i class="fas fa-plus"></i> Agregar Archivo
                        </button>
                    </div>

                    <?php
                    $archivosAdjuntosAdendums = obtenerArchivosAdjuntos($codOperario, 'adendums');
                    ?>

                    <?php if (count($archivosAdjuntosAdendums) > 0): ?>
                        <div style="overflow-x: auto;">
                            <?php
                            // Agrupar archivos por adendum
                            $archivosPorAdendum = [];
                            foreach ($archivosAdjuntosAdendums as $archivo) {
                                $adendumId = $archivo['cod_adendum_asociado'] ?? 'sin_adendum';
                                if (!isset($archivosPorAdendum[$adendumId])) {
                                    $archivosPorAdendum[$adendumId] = [
                                        'info' => $archivo, // Información del adendum
                                        'archivos' => []
                                    ];
                                }
                                $archivosPorAdendum[$adendumId]['archivos'][] = $archivo;
                            }

                            // Ordenar por ID de adendum (más reciente primero)
                            krsort($archivosPorAdendum);
                            ?>

                            <?php foreach ($archivosPorAdendum as $adendumId => $grupo): ?>
                                <?php if ($adendumId !== 'sin_adendum'): ?>
                                    <div style="background: #f8f9fa; padding: 10px; margin: 15px 0; border-left: 4px solid #0E544C;">
                                        <strong>Adendum: </strong>
                                        <?= htmlspecialchars($grupo['info']['TipoAdendum'] ?? 'N/A') ?> |
                                        <strong>Cargo:
                                        </strong><?= htmlspecialchars($grupo['info']['nombre_cargo_adendum'] ?? 'N/A') ?>
                                        |
                                        <strong>Salario: </strong>C$
                                        <?= number_format($grupo['info']['salario_adendum'] ?? 0, 2) ?> |
                                        <strong>Fecha:
                                        </strong><?= !empty($grupo['info']['FechaInicio']) ? date('d/m/Y', strtotime($grupo['info']['FechaInicio'])) : 'N/A' ?>
                                    </div>
                                <?php else: ?>
                                    <div style="background: #fff3cd; padding: 10px; margin: 15px 0; border-left: 4px solid #ffc107;">
                                        <strong>Archivos sin adendum asociado</strong>
                                    </div>
                                <?php endif; ?>

                                <!-- Tabla de archivos para este adendum -->
                                <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
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
                                        <?php foreach ($archivosAdjuntosAdendums as $archivo):
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
                                                    <a href="<?= htmlspecialchars($archivo['ruta_archivo']) ?>" target="_blank"
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
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: #6c757d; padding: 20px;">No hay archivos
                            adjuntos</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Pestaña de Expediente Digital -->