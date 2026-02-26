                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <div id="datos-personales"
                                class="tab-pane <?= $pestaña_activa == 'datos-personales' ? 'active' : '' ?>">
                                <!-- Sección de Documentos Obligatorios Faltantes -->
                                <?php
                                $documentosFaltantesPestana = obtenerDocumentosFaltantesPestana($codOperario, $pestaña_activa);
                                if (!empty($documentosFaltantesPestana)): ?>
                                    <div style="margin: 20px 0; padding: 15px; background: #fff3cd; border-radius: 8px; border: 1px solid #ffeaa7;">
                                        <h4 style="color: #856404; margin-bottom: 15px;">
                                            <i class="fas fa-exclamation-triangle"></i> Documentos Obligatorios Faltantes - <?= obtenerNombrePestaña($pestaña_activa) ?>
                                        </h4>
                                        <ul style="color: #856404; margin: 0; padding-left: 20px;">
                                            <?php foreach ($documentosFaltantesPestana as $documento): ?>
                                                <li><?= htmlspecialchars($documento) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <p style="color: #856404; margin: 15px 0 0 0; font-style: italic;">
                                            <i class="fas fa-info-circle"></i> Estos documentos deben ser subidos para completar la información.
                                        </p>
                                    </div>
                                <?php else: ?>
                                    <div style="margin: 20px 0; padding: 15px; background: #d4edda; border-radius: 8px; border: 1px solid #c3e6cb; color: #155724;">
                                        <i class="fas fa-check-circle"></i> Todos los documentos obligatorios están completos para <?= obtenerNombrePestaña($pestaña_activa) ?>.
                                    </div>
                                <?php endif; ?>

                                <form method="POST" action="">
                                    <input type="hidden" name="accion" value="guardar_datos_personales">
                                    <input type="hidden" name="pestaña" value="datos-personales">

                                    <div class="readonly-info">
                                        <p><strong>Código:</strong> <?= htmlspecialchars($colaborador['CodOperario']) ?>
                                        </p>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-col">
                                            <div class="form-group">
                                                <label for="nombre">Primer Nombre *</label>
                                                <input type="text" id="nombre" name="nombre" class="form-control"
                                                    value="<?= htmlspecialchars($colaborador['Nombre'] ?? '') ?>" required>
                                            </div>

                                            <div class="form-group">
                                                <label for="apellido">Primer Apellido *</label>
                                                <input type="text" id="apellido" name="apellido" class="form-control"
                                                    value="<?= htmlspecialchars($colaborador['Apellido'] ?? '') ?>"
                                                    required>
                                            </div>

                                            <div class="form-group">
                                                <label for="cedula">Cédula *</label>
                                                <input type="text" id="cedula" name="cedula" class="form-control"
                                                    value="<?= htmlspecialchars($colaborador['Cedula'] ?? '') ?>"
                                                    placeholder="Ej: XXX-XXXXXX-XXXX"
                                                    pattern="[0-9]{3}-[0-9]{6}-[0-9]{4}[A-Za-z]?"
                                                    title="Formato: 001-234567-8910A">
                                            </div>
                                        </div>

                                        <div class="form-col">
                                            <div class="form-group">
                                                <label for="nombre2">Segundo Nombre</label>
                                                <input type="text" id="nombre2" name="nombre2" class="form-control"
                                                    value="<?= htmlspecialchars($colaborador['Nombre2'] ?? '') ?>">
                                            </div>

                                            <div class="form-group">
                                                <label for="apellido2">Segundo Apellido</label>
                                                <input type="text" id="apellido2" name="apellido2" class="form-control"
                                                    value="<?= htmlspecialchars($colaborador['Apellido2'] ?? '') ?>">
                                            </div>

                                            <div class="form-group">
                                                <label for="genero">Género</label>
                                                <select id="genero" name="genero" class="form-control">
                                                    <option value="">Seleccionar...</option>
                                                    <option value="M" <?= (isset($colaborador['Genero']) && $colaborador['Genero'] == 'M') ? 'selected' : '' ?>>Masculino
                                                    </option>
                                                    <option value="F" <?= (isset($colaborador['Genero']) && $colaborador['Genero'] == 'F') ? 'selected' : '' ?>>Femenino
                                                    </option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="cumpleanos">Fecha de Cumpleaños</label>
                                        <input type="date" id="cumpleanos" name="cumpleanos" class="form-control"
                                            value="<?= !empty($colaborador['Cumpleanos']) ? date('Y-m-d', strtotime($colaborador['Cumpleanos'])) : '' ?>">
                                    </div>

                                    <div class="form-row">
                                        <div class="form-col">
                                            <div class="form-group">
                                                <label for="usuario">Usuario</label>
                                                <input type="text" id="usuario" name="usuario" class="form-control"
                                                    value="<?= htmlspecialchars($colaborador['usuario'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="form-col">
                                            <div class="form-group">
                                                <label for="clave">Clave <small style="color: #6c757d;">(dejar vacío si
                                                        no desea
                                                        cambiar)</small></label>
                                                <div style="display: flex; align-items: center;">
                                                    <input type="password" id="clave" name="clave" class="form-control"
                                                        value="<?= htmlspecialchars($colaborador['clave'] ?? '') ?>"
                                                        style="flex: 1; margin-right: 10px;">
                                                    <button type="button" id="toggleClave"
                                                        style="background: #0E544C; color: white; border: none; padding: 8px; border-radius: 4px; cursor: pointer;">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>

                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn-submit">Guardar solo Datos Personales</button>
                                </form>

                                <!-- Sección de Cuentas Bancarias -->
                                <div style="margin-top: 40px; border-top: 2px solid #0E544C; padding-top: 20px;">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                        <h3 style="color: #0E544C; margin: 0;">Cuentas Bancarias</h3>
                                        <button type="button" class="btn-submit" onclick="abrirModalCuenta()"
                                            style="margin: 0;">
                                            <i class="fas fa-plus"></i> Agregar
                                        </button>
                                    </div>

                                    <?php if (count($cuentasBancarias) > 0): ?>
                                        <div style="overflow-x: auto;">
                                            <table style="width: 100%; border-collapse: collapse;">
                                                <thead>
                                                    <tr style="background-color: #0E544C; color: white;">
                                                        <th style="padding: 10px; text-align: left;">Número Cuenta</th>
                                                        <th style="padding: 10px; text-align: left;">Titular</th>
                                                        <th style="padding: 10px; text-align: left;">Banco</th>
                                                        <th style="padding: 10px; text-align: left;">Moneda</th>
                                                        <th style="padding: 10px; text-align: left;">Desde</th>
                                                        <th style="padding: 10px; text-align: center; display:none;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($cuentasBancarias as $cuenta): ?>
                                                        <tr style="border-bottom: 1px solid #ddd;">
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($cuenta['numero_cuenta']) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($cuenta['titular']) ?>
                                                            </td>
                                                            <td style="padding: 10px;"><?= htmlspecialchars($cuenta['banco']) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($cuenta['moneda']) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= !empty($cuenta['desde']) ? date('d/m/Y', strtotime($cuenta['desde'])) : '' ?>
                                                            </td>
                                                            <td style="padding: 10px; text-align: center; display:none;">
                                                                <button type="button" class="btn-accion btn-editar"
                                                                    onclick="editarCuenta(<?= $cuenta['id'] ?>)">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <form method="POST" action="" style="display: inline;">
                                                                    <input type="hidden" name="accion_cuenta" value="eliminar">
                                                                    <input type="hidden" name="id_cuenta"
                                                                        value="<?= $cuenta['id'] ?>">
                                                                    <button type="submit"
                                                                        onclick="return confirm('¿Está seguro de eliminar esta cuenta bancaria?')"
                                                                        class="btn-accion btn-eliminar">
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
                                        <p style="text-align: center; color: #6c757d; padding: 20px;">No hay cuentas
                                            bancarias
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

