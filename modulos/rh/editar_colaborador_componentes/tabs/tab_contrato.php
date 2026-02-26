<!-- Pestaña de Contrato -->
<?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
    <div id="contrato" class="tab-pane <?= $pestaña_activa == 'contrato' ? 'active' : '' ?>">
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

        <?php
        // Obtener datos del contrato actual
        $contratoActual = obtenerContratoActual($codOperario);
        $estaFinalizado = $contratoActual ? contratoEstaFinalizado($contratoActual) : false;
        $estaActivo = $contratoActual ? contratoEstaActivo($contratoActual) : false;
        $asignacionCargoActual = obtenerAsignacionCargoActual($codOperario);
        $categoriaActual = obtenerCategoriaActual($codOperario);
        $salarioActual = obtenerSalarioActual($codOperario);

        // NUEVO: Determinar si debemos mostrar el formulario para nuevo contrato
        $mostrarFormularioNuevoContrato = !$contratoActual || $estaFinalizado;

        // NUEVO: Permiso específico para editar datos ya guardados
        $puedeEditarTodo = tienePermiso('editar_colaborador', 'editar_contrato', $cargoId);

        // Función auxiliar para determinar si un campo debe estar bloqueado
        $bloquearCampo = function ($valorActual) use ($puedeEditarTodo, $mostrarFormularioNuevoContrato) {
            if ($mostrarFormularioNuevoContrato) return ''; // En nuevo contrato todo es editable
            if ($puedeEditarTodo) return ''; // Si tiene permiso especial puede editar todo
            return (!empty($valorActual) || $valorActual === '0' || $valorActual === 0) ? 'readonly onclick="return false;" style="background-color: #f1f3f5; cursor: not-allowed;"' : '';
        };

        $bloquearSelect = function ($valorActual) use ($puedeEditarTodo, $mostrarFormularioNuevoContrato) {
            if ($mostrarFormularioNuevoContrato) return '';
            if ($puedeEditarTodo) return '';
            return (!empty($valorActual) || $valorActual === '0' || $valorActual === 0) ? 'disabled style="background-color: #f1f3f5; cursor: not-allowed;"' : '';
        };
        ?>


        <!-- FORMULARIO DE CONTRATO -->
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="pestaña" value="contrato">

            <!-- NUEVO: Cambiar el valor de accion_contrato según si es nuevo o edición -->
            <?php if ($mostrarFormularioNuevoContrato): ?>
                <input type="hidden" name="accion_contrato" value="guardar">
                <h3 style="color: #0E544C; margin-bottom: 15px;">Nuevo Contrato</h3>
            <?php else: ?>
                <input type="hidden" name="accion_contrato" value="guardar">
                <input type="hidden" name="id_contrato" value="<?= $contratoActual['CodContrato'] ?>">

                <!-- Campos ocultos para selects deshabilitados (para que viajen en el POST) -->
                <?php if (!$puedeEditarTodo): ?>
                    <?php if (!empty($contratoActual['cod_sucursal_contrato'])): ?>
                        <input type="hidden" name="sucursal" value="<?= $contratoActual['cod_sucursal_contrato'] ?>">
                    <?php endif; ?>
                    <?php if (!empty($asignacionCargoActual['CodNivelesCargos'])): ?>
                        <input type="hidden" name="cod_cargo" value="<?= $asignacionCargoActual['CodNivelesCargos'] ?>">
                    <?php endif; ?>
                    <?php if (!empty($contratoActual['cod_tipo_contrato'])): ?>
                        <input type="hidden" name="cod_tipo_contrato" value="<?= $contratoActual['cod_tipo_contrato'] ?>">
                    <?php endif; ?>
                <?php endif; ?>
<?php endif; ?>

            <div class="form-row">
                <!-- Columna Izquierda: Información Administrativa -->
                <div class="form-col">
                    <div class="form-group">
                        <label for="codigo_manual_contrato">Código de Contrato *</label>
                        <input type="text" id="codigo_manual_contrato" name="codigo_manual_contrato" class="form-control"
                            value="<?= (!$mostrarFormularioNuevoContrato && $contratoActual) ? htmlspecialchars($contratoActual['codigo_manual_contrato'] ?? '') : '' ?>"
                            <?= $bloquearCampo($contratoActual['codigo_manual_contrato'] ?? '') ?>
                            onblur="validarCodigoContrato(this.value)">
                        <div id="codigo-contrato-error" class="text-danger"
                            style="display: none; font-size: 12px; margin-top: 5px;">
                            ⚠️ Este código de contrato ya existe. Debe usar un código único.
                        </div>
                        <div id="codigo-contrato-success" class="text-success"
                            style="display: none; font-size: 12px; margin-top: 5px;">
                            ✅ Código disponible
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="cod_cargo">Cargo *</label>
                        <select id="cod_cargo" name="cod_cargo" class="form-control" required
                            <?= $bloquearSelect($asignacionCargoActual['CodNivelesCargos'] ?? '') ?>
                            onchange="actualizarCategoriaYMostrar()">
                            <option value="">Seleccionar cargo...</option>
                            <?php
                            $cargos = obtenerTodosCargos();
                            foreach ($cargos as $cargo):
                                // Determinar la categoría sugerida para este cargo (se mantiene en data-categoria pero no se muestra)
                                $idCategoriaSugerida = '';
                                if ($cargo['CodNivelesCargos'] == 2) {
                                    $idCategoriaSugerida = 5;
                                } elseif ($cargo['CodNivelesCargos'] == 5) {
                                    $idCategoriaSugerida = 1;
                                }
                                ?>
                                <option value="<?= $cargo['CodNivelesCargos'] ?>" data-categoria="<?= $idCategoriaSugerida ?>"
                                    <?= (!$mostrarFormularioNuevoContrato && $asignacionCargoActual && $asignacionCargoActual['CodNivelesCargos'] == $cargo['CodNivelesCargos']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cargo['Nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="cod_tipo_contrato">Tipo de Contrato *</label>
                        <select id="cod_tipo_contrato" name="cod_tipo_contrato" class="form-control" required <?= $bloquearSelect($contratoActual['cod_tipo_contrato'] ?? '') ?>>
                            <option value="">Seleccionar tipo de contrato...</option>
                            <?php
                            $tiposContrato = obtenerTiposContrato();
                            foreach ($tiposContrato as $tipo): ?>
                                <option value="<?= $tipo['CodTipoContrato'] ?>" <?= (!$mostrarFormularioNuevoContrato && $contratoActual && $contratoActual['cod_tipo_contrato'] == $tipo['CodTipoContrato']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tipo['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="monto_salario">Salario Básico *</label>
                        <input type="number" id="monto_salario" name="monto_salario" class="form-control" step="0.01"
                            min="0"
                            value="<?= (!$mostrarFormularioNuevoContrato && $contratoActual) ? ($contratoActual['salario_inicial'] ?? '') : '' ?>"
                            <?= $bloquearCampo($contratoActual['salario_inicial'] ?? '') ?>
                            required>
                    </div>
                </div>

                <!-- Columna Derecha: Ubicación y Fechas -->
                <div class="form-col">
                    <div class="form-group">
                        <label for="sucursal_contrato">Area / Tienda *</label>
                        <select id="sucursal_contrato" name="sucursal" class="form-control" required <?= $bloquearSelect($contratoActual['cod_sucursal_contrato'] ?? '') ?>>
                            <option value="">Seleccionar sucursal...</option>
                            <?php
                            $sucursales = obtenerTodasSucursales();
                            foreach ($sucursales as $sucursal): ?>
                                <option value="<?= $sucursal['codigo'] ?>" <?= (!$mostrarFormularioNuevoContrato && $contratoActual && $contratoActual['cod_sucursal_contrato'] == $sucursal['codigo']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sucursal['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="ciudad">Departamento *</label>
                        <select id="ciudad" name="ciudad" class="form-control" required <?= $bloquearSelect($contratoActual['ciudad'] ?? '') ?>>
                            <option value="">Seleccionar departamento...</option>
                            <?php
                            $departamentos = obtenerTodosDepartamentos();
                            foreach ($departamentos as $dep): ?>
                                <option value="<?= htmlspecialchars($dep['nombre']) ?>" <?= (!$mostrarFormularioNuevoContrato && $contratoActual && $contratoActual['ciudad'] == $dep['nombre']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dep['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="inicio_contrato">Fecha de Inicio *</label>
                        <input type="date" id="inicio_contrato" name="inicio_contrato" class="form-control"
                            value="<?= (!$mostrarFormularioNuevoContrato && $contratoActual) ? $contratoActual['inicio_contrato'] : date('Y-m-d') ?>"
                            <?= $bloquearCampo($contratoActual['inicio_contrato'] ?? '') ?>
                            required>
                    </div>

                    <div class="form-group" id="grupo_fecha_fin_contrato">
                        <label for="fin_contrato">
                            Fecha Fin de Contrato
                            <small style="color: #6c757d;">
                                (solo para contratos temporales)
                            </small>
                        </label>
                        <input type="date" id="fin_contrato" name="fin_contrato" class="form-control"
                            value="<?= (!$mostrarFormularioNuevoContrato && $contratoActual) ? ($contratoActual['fin_contrato'] ?? '') : '' ?>"
                            <?= $bloquearCampo($contratoActual['fin_contrato'] ?? '') ?>
                            <?= (!$mostrarFormularioNuevoContrato && $contratoActual && $contratoActual['cod_tipo_contrato'] != 1) ? 'disabled' : '' ?>>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="observaciones">Observaciones</label>
                <textarea id="observaciones" name="observaciones" class="form-control"
                    <?= $bloquearCampo($contratoActual['observaciones'] ?? '') ?>
                    rows="3"><?= (!$mostrarFormularioNuevoContrato && $contratoActual) ? htmlspecialchars($contratoActual['observaciones']) : '' ?></textarea>
            </div>

            <button type="submit" class="btn-submit">
                <?= $mostrarFormularioNuevoContrato ? 'Crear solo nuevo Contrato' : 'Guardar solo datos de Contrato' ?>
            </button>

            <!-- Sección de Terminación de Contrato -->
            <?php if (!$mostrarFormularioNuevoContrato && $contratoActual): ?>
                <?php if (empty($contratoActual['fin_contrato']) || $contratoActual['fin_contrato'] >= date('Y-m-d')): ?>
                    <button type="button" class="btn-submit" onclick="abrirModalTerminacion()"
                        style="background-color: #dc3545; margin-left: 10px;">
                        <i class="fas fa-times"></i> Finalizar Contrato
                    </button>
                <?php else: ?>
                    <span style="color: #6c757d; font-style: italic;">Contrato ya finalizado</span>
                <?php endif; ?>
            <?php endif; ?>
        </form>

        <!-- Sección de Historial de Contratos -->
        <div style="margin-top: 40px; border-top: 2px solid #6c757d; padding-top: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: #6c757d; margin: 0;">
                    Historial de Contratos
                    <span style="font-size: 0.8em; color: #0E544C;">
                        (Total: <?= count($historialContratos) ?>)
                    </span>
                </h3>
            </div>

            <?php if (count($historialContratos) > 0): ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                        <thead>
                            <tr style="background-color: #6c757d; color: white;">
                                <th style="padding: 10px; text-align: left;">Código</th>
                                <th style="padding: 10px; text-align: left;">Tipo</th>
                                <th style="padding: 10px; text-align: left;">Cargo</th>
                                <th style="padding: 10px; text-align: left; display:none;">Categoría
                                </th>
                                <th style="padding: 10px; text-align: left;">Inicio</th>
                                <th style="padding: 10px; text-align: left;">Fin</th>
                                <th style="padding: 10px; text-align: left;">Estado</th>
                                <th style="padding: 10px; text-align: left;">Fecha Salida</th>
                                <th style="padding: 10px; text-align: left;">Fecha Liquidación</th>
                                <th style="padding: 10px; text-align: center;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historialContratos as $contrato):
                                // Determinar si el contrato está finalizado (por fecha_salida)
                                $estaFinalizado = contratoEstaFinalizado($contrato);
                                $estaActivo = !$estaFinalizado && contratoEstaActivo($contrato);

                                $estiloFila = '';
                                $estado = '';

                                if ($estaFinalizado) {
                                    $estado = '<span style="color: #6c757d;">FINALIZADO</span>';
                                } elseif ($estaActivo) {
                                    $estiloFila = 'background-color: #e8f5e9;';
                                    $estado = '<span style="color: green; font-weight: bold;">ACTIVO</span>';
                                } else {
                                    $estado = '<span style="color: #dc3545;">VENCIDO</span>';
                                }

                                // Obtener categoría del contrato desde CategoriasOperarios
                                $categoriaContrato = obtenerCategoriaPorContrato($contrato['CodContrato']);

                                ?>
                                <tr style="border-bottom: 1px solid #ddd; <?= $estiloFila ?>">
                                    <td style="padding: 10px;">
                                        <?= !empty($contrato['codigo_manual_contrato']) ?
                                            htmlspecialchars($contrato['codigo_manual_contrato']) :
                                            '<span style="color: #6c757d; font-style: italic;">Sin código</span>' ?>
                                    </td>
                                    <td style="padding: 10px;">
                                        <?= htmlspecialchars($contrato['tipo_contrato'] ?? 'No especificado') ?>
                                    </td>
                                    <td style="padding: 10px;">
                                        <?= htmlspecialchars($contrato['cargo'] ?? 'No especificado') ?>
                                    </td>
                                    <td style="padding: 10px; display:none;">
                                        <?= $categoriaContrato ? htmlspecialchars($categoriaContrato) : '<span style="color: #6c757d; font-style: italic;">No definida</span>' ?>
                                    </td>
                                    <td style="padding: 10px;">
                                        <?= !empty($contrato['inicio_contrato']) ? date('d/m/Y', strtotime($contrato['inicio_contrato'])) : 'No definida' ?>
                                    </td>
                                    <!-- <td style="padding: 10px;"><?= !empty($contrato['fin_contrato']) ? date('d/m/Y', strtotime($contrato['fin_contrato'])) : 'No definida' ?></td> -->
                                    <td style="padding: 10px;">
                                        <?= !empty($contrato['fin_contrato']) && $contrato['fin_contrato'] != '0000-00-00' ?
                                            date('d/m/Y', strtotime($contrato['fin_contrato'])) :
                                            '<span style="color: #28a745; font-style: italic;">Indefinido</span>' ?>
                                    </td>
                                    <td style="padding: 10px;"><?= $estado ?></td>
                                    <td style="padding: 10px;">
                                        <?= !empty($contrato['fecha_salida']) && $contrato['fecha_salida'] != '0000-00-00' ?
                                            date('d/m/Y', strtotime($contrato['fecha_salida'])) :
                                            '<span style="color: #6c757d; font-style: italic;">No aplica</span>' ?>
                                    </td>
                                    <td style="padding: 10px;">
                                        <?= !empty($contrato['fecha_liquidacion']) && $contrato['fecha_liquidacion'] != '0000-00-00' ?
                                            date('d/m/Y', strtotime($contrato['fecha_liquidacion'])) :
                                            '<span style="color: #6c757d; font-style: italic;">No definida</span>' ?>
                                    </td>
                                    <td style="padding: 10px; text-align: center;">
                                        <?php if (!empty($contrato['foto'])): ?>
                                            <a href="<?= htmlspecialchars($contrato['foto']) ?>" target="_blank"
                                                class="btn-accion btn-editar" title="Ver contrato">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($contrato['foto_solicitud_renuncia'])): ?>
                                            <a href="<?= htmlspecialchars($contrato['foto_solicitud_renuncia']) ?>" target="_blank"
                                                class="btn-accion" title="Ver renuncia" style="color: #dc3545;">
                                                <i class="fas fa-file-alt"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button type="button" class="btn-accion btn-editar"
                                            onclick="abrirModalLiquidacion(<?= $contrato['CodContrato'] ?>, '<?= $contrato['fecha_liquidacion'] ?? '' ?>')"
                                            title="Asignar/Editar Fecha de Liquidación">
                                            <i class="fas fa-calendar-alt"></i>
                                        </button>

                                        <!-- NUEVO BOTÓN -->
                                        <button type="button" class="btn-accion"
                                            onclick="abrirModalEditarTerminacion(<?= $contrato['CodContrato'] ?>)"
                                            title="Editar Información de Terminación" style="color: #0E544C;">
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
                    contratos</p>
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