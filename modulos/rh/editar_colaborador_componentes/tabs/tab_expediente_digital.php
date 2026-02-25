                            <div id="expediente-digital"
                                class="tab-pane <?= $pestaña_activa == 'expediente-digital' ? 'active' : '' ?>">
                                <?php
                                $expedienteCompleto = obtenerExpedienteDigitalCompleto($codOperario);
                                $documentosFaltantes = obtenerDocumentosFaltantes($codOperario);
                                $totalArchivos = array_sum(array_map('count', $expedienteCompleto));
                                ?>

                                <!-- Resumen del Expediente -->
                                <div class="resumen-expediente"
                                    style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px; display:none;">
                                    <h3 style="color: #0E544C; margin-bottom: 15px;">Resumen del Expediente Digital</h3>

                                    <div
                                        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                                        <div
                                            style="text-align: center; padding: 15px; background: white; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                            <div style="font-size: 2rem; font-weight: bold; color: #0E544C;">
                                                <?= $totalArchivos ?>
                                            </div>
                                            <div style="color: #6c757d;">Total de Documentos</div>
                                        </div>

                                        <?php foreach ($expedienteCompleto as $categoria => $archivos): ?>
                                            <div
                                                style="text-align: center; padding: 15px; background: white; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                <div style="font-size: 2rem; font-weight: bold; color: #0E544C;">
                                                    <?= count($archivos) ?>
                                                </div>
                                                <div style="color: #6c757d;"><?= $categoria ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Estado de finalización -->
                                    <?php $estadoGlobal = verificarEstadoGlobalDocumentos($codOperario); ?>
                                    <div style="text-align: center; padding: 15px; display:none; background: <?=
                                        $estadoGlobal == 'completo' ? '#d4edda' :
                                        ($estadoGlobal == 'parcial' ? '#fff3cd' : '#f8d7da')
                                        ?>; border-radius: 6px; border: 1px solid <?=
                                        $estadoGlobal == 'completo' ? '#c3e6cb' :
                                        ($estadoGlobal == 'parcial' ? '#ffeaa7' : '#f5c6cb')
                                        ?>;">
                                        <h4 style="margin: 0; color: <?=
                                            $estadoGlobal == 'completo' ? '#155724' :
                                            ($estadoGlobal == 'parcial' ? '#856404' : '#721c24')
                                            ?>;">
                                            <?=
                                                $estadoGlobal == 'completo' ? '✅ Expediente Completo' :
                                                ($estadoGlobal == 'parcial' ? '⏳ Expediente Parcial' : '❌ Expediente Incompleto')
                                                ?>
                                        </h4>
                                        <p style="margin: 5px 0 0 0; color: <?=
                                            $estadoGlobal == 'completo' ? '#155724' :
                                            ($estadoGlobal == 'parcial' ? '#856404' : '#721c24')
                                            ?>;">
                                            <?=
                                                $estadoGlobal == 'completo' ? 'Todos los documentos obligatorios están subidos' :
                                                ($estadoGlobal == 'parcial' ? 'Faltan algunos documentos obligatorios' : 'Faltan la mayoría de documentos obligatorios')
                                                ?>
                                        </p>
                                    </div>
                                </div>

                                <!-- Documentos Faltantes -->
                                <?php if (!empty($documentosFaltantes)): ?>
                                    <div class="documentos-faltantes"
                                        style="background: #fff3cd; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #ffeaa7; display:none;">
                                        <h4 style="color: #856404; margin-bottom: 15px;">
                                            <i class="fas fa-exclamation-triangle"></i> Documentos Obligatorios Faltantes
                                        </h4>

                                        <?php foreach ($documentosFaltantes as $pestaña => $info): ?>
                                            <div style="margin-bottom: 15px;">
                                                <h5 style="color: #856404; margin-bottom: 10px;">
                                                    <?= $info['pestaña_nombre'] ?>
                                                </h5>
                                                <ul style="color: #856404; margin: 0; padding-left: 20px;">
                                                    <?php foreach ($info['faltantes'] as $documento): ?>
                                                        <li><?= htmlspecialchars($documento['nombre']) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endforeach; ?>

                                        <p style="color: #856404; margin: 15px 0 0 0; font-style: italic;">
                                            <i class="fas fa-info-circle"></i> Estos documentos deben ser subidos en sus
                                            pestañas
                                            correspondientes.
                                        </p>
                                    </div>
                                <?php else: ?>
                                    <div class="sin-faltantes"
                                        style="background: #d4edda; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #c3e6cb;">
                                        <h4 style="color: #155724; margin: 0;">
                                            <i class="fas fa-check-circle"></i> Todos los documentos obligatorios están
                                            completos
                                        </h4>
                                    </div>
                                <?php endif; ?>

                                <!-- Expediente Digital Organizado -->
                                <div class="expediente-organizado">
                                    <h3 style="color: #0E544C; margin-bottom: 20px; display:none;">Expediente Digital
                                        Organizado
                                    </h3>

                                    <!-- Leyenda -->
                                    <div
                                        style="background: #e9ecef; padding: 15px; border-radius: 5px; margin-top: 10px; margin-bottom:10px;">
                                        <h5 style="margin: 0 0 10px 0; color: #495057;">Leyenda:</h5>
                                        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                                            <div style="display: flex; align-items: center; gap: 5px;">
                                                <span
                                                    style="display: inline-block; width: 12px; height: 12px; background: #dc3545; border-radius: 50%;"></span>
                                                <span style="font-size: 0.9em;">Documento Obligatorio</span>
                                            </div>
                                            <div style="display: flex; align-items: center; gap: 5px;">
                                                <span
                                                    style="display: inline-block; width: 12px; height: 12px; background: #6c757d; border-radius: 50%;"></span>
                                                <span style="font-size: 0.9em;">Documento Informativo</span>
                                            </div>
                                            <div style="display: flex; align-items: center; gap: 5px;">
                                                <i class="fas fa-eye" style="color: #0E544C;"></i>
                                                <span style="font-size: 0.9em;">Solo visualización</span>
                                            </div>
                                        </div>
                                    </div>

                                    <?php
                                    // Obtener todos los documentos esperados (incluyendo faltantes)
                                    $expedienteCompletoConFaltantes = obtenerExpedienteCompletoConFaltantes($codOperario);

                                    // Preparar arreglo para el carrusel de imágenes
                                    $imagenesParaCarrusel = [];
                                    ?>

                                    <?php if (!empty($expedienteCompletoConFaltantes)): ?>
                                        <div class="contenido-categoria"
                                            style="border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 5px 5px; padding: 0;">
                                            <table style="width: 100%; border-collapse: collapse;">
                                                <thead>
                                                    <tr style="background: #f8f9fa;">
                                                        <th style="padding: 12px; text-align: left; width: 15%;">Estado</th>
                                                        <th style="padding: 12px; text-align: left; width: 25%;">Documento
                                                        </th>
                                                        <th style="padding: 12px; text-align: left; width: 25%;">Descripción
                                                        </th>
                                                        <th style="padding: 12px; text-align: left; width: 15%;">Pestaña
                                                            Origen</th>
                                                        <th style="padding: 12px; text-align: left; width: 20%;">Subido por
                                                        </th>
                                                        <th style="padding: 12px; text-align: left; width: 15%;">Fecha</th>
                                                        <th style="padding: 12px; text-align: center; width: 10%;"></th>
                                                    </tr>
                                                </thead>
                                            </table>
                                        </div>

                                        <?php foreach ($expedienteCompletoConFaltantes as $categoriaPrincipal => $subcategorias): ?>
                                            <div class="categoria-expediente" style="margin-bottom: 30px;">
                                                <div class="header-categoria"
                                                    style="background: #0E544C; color: white; padding: 12px 15px; border-radius: 5px 5px 0 0;">
                                                    <h4
                                                        style="margin: 0; display: flex; justify-content: space-between; align-items: center;">
                                                        <span>
                                                            <?= htmlspecialchars($categoriaPrincipal) ?>
                                                            <small>(<?= array_sum(array_map('count', $subcategorias)) ?>
                                                                documento<?= array_sum(array_map('count', $subcategorias)) !== 1 ? 's' : '' ?>)</small>
                                                        </span>
                                                        <i class="fas fa-folder"></i>
                                                    </h4>
                                                </div>

                                                <div class="contenido-categoria"
                                                    style="border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 5px 5px; padding: 0;">
                                                    <?php foreach ($subcategorias as $subcategoria => $archivos): ?>
                                                        <!-- Tabla de documentos agrupados -->
                                                        <table style="width: 100%; border-collapse: collapse;">
                                                            <tbody>
                                                                <?php
                                                                // Agrupar archivos por tipo_documento dentro de esta subcategoría
                                                                $archivosAgrupadosPorTipo = [];
                                                                foreach ($archivos as $archivo) {
                                                                    $tipoKey = $archivo['tipo'] === 'faltante' ? ('faltante_' . $archivo['nombre_archivo']) : ($archivo['tipo_documento'] ?: 'sin_especificar');
                                                                    if (!isset($archivosAgrupadosPorTipo[$tipoKey])) {
                                                                        $archivosAgrupadosPorTipo[$tipoKey] = [];
                                                                    }
                                                                    $archivosAgrupadosPorTipo[$tipoKey][] = $archivo;
                                                                }

                                                                foreach ($archivosAgrupadosPorTipo as $tipoKey => $grupoArchivos):
                                                                    $primerArchivo = $grupoArchivos[0];
                                                                    $totalEnGrupo = count($grupoArchivos);
                                                                    ?>
                                                                    <tr style="border-bottom: 1px solid #dee2e6;">
                                                                        <!-- Columna Estado -->
                                                                        <td style="padding: 12px; width: 15%;">
                                                                            <?php if ($primerArchivo['tipo'] === 'faltante'): ?>
                                                                                <span
                                                                                    style="display: inline-block; padding: 3px 8px; background: #dc3545; color: white; border-radius: 12px; font-size: 0.8em;">FALTANTE</span>
                                                                            <?php else: ?>
                                                                                <?php if (!empty($primerArchivo['tipo_documento'])): ?>
                                                                                    <?php
                                                                                    $tiposDocumentos = obtenerTiposDocumentosPorPestaña($primerArchivo['pestaña']);
                                                                                    $todosTipos = array_merge($tiposDocumentos['obligatorios'], $tiposDocumentos['opcionales']);
                                                                                    $nombreTipo = $todosTipos[$primerArchivo['tipo_documento']] ?? $primerArchivo['tipo_documento'];
                                                                                    ?>
                                                                                    <span
                                                                                        style="display: inline-block; padding: 3px 8px; background: <?= $primerArchivo['obligatorio'] ? '#28a745' : '#6c757d' ?>; color: white; border-radius: 12px; font-size: 0.8em;">
                                                                                        <?= htmlspecialchars($nombreTipo) ?>
                                                                                        <?= $primerArchivo['obligatorio'] ? ' *' : '' ?>
                                                                                    </span>
                                                                                <?php else: ?>
                                                                                    <span style="color: #6c757d; font-style: italic;">Sin
                                                                                        tipo</span>
                                                                                <?php endif; ?>
                                                                            <?php endif; ?>
                                                                        </td>

                                                                        <!-- Columna Documento -->
                                                                        <td style="padding: 12px; width: 25%;">
                                                                            <div
                                                                                style="font-weight: 500; <?= $primerArchivo['tipo'] === 'faltante' ? 'color: #dc3545;' : '' ?>">
                                                                                <?= $primerArchivo['tipo'] === 'faltante' ? '<i class="fas fa-exclamation-circle"></i> ' : '' ?>
                                                                                <?= htmlspecialchars($primerArchivo['nombre_archivo']) ?>
                                                                                <?php if ($totalEnGrupo > 1): ?>
                                                                                    <span
                                                                                        style="background: #0E544C; color: white; border-radius: 10px; padding: 2px 6px; font-size: 0.75em; margin-left: 5px;">
                                                                                        <?= $totalEnGrupo ?> archivos
                                                                                    </span>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </td>

                                                                        <!-- Columna Descripción -->
                                                                        <td style="padding: 12px; width: 25%;">
                                                                            <?php
                                                                            $descripciones = [];
                                                                            foreach ($grupoArchivos as $a) {
                                                                                if (!empty($a['descripcion']))
                                                                                    $descripciones[] = $a['descripcion'];
                                                                            }
                                                                            if (!empty($descripciones)):
                                                                                ?>
                                                                                <div style="font-size: 0.9em; color: #6c757d;">
                                                                                    <?= htmlspecialchars(implode(' | ', array_unique($descripciones))) ?>
                                                                                </div>
                                                                            <?php elseif ($primerArchivo['tipo'] === 'faltante'): ?>
                                                                                <div style="color: #dc3545;"><i
                                                                                        class="fas fa-exclamation-circle"></i></div>
                                                                            <?php endif; ?>
                                                                        </td>

                                                                        <!-- Columna Pestaña -->
                                                                        <td style="padding: 12px; width: 15%;">
                                                                            <span
                                                                                style="display: inline-block; padding: 3px 8px; background: #e9ecef; color: #495057; border-radius: 12px; font-size: 0.8em;">
                                                                                <?= obtenerNombrePestaña($primerArchivo['pestaña']) ?>
                                                                            </span>
                                                                        </td>

                                                                        <!-- Columna Subido por -->
                                                                        <td style="padding: 12px; width: 20%;">
                                                                            <?php
                                                                            if ($primerArchivo['tipo'] === 'faltante') {
                                                                                echo 'No subido';
                                                                            } else {
                                                                                $usuarios = [];
                                                                                foreach ($grupoArchivos as $a) {
                                                                                    $usuarios[] = $a['nombre_usuario'] . ' ' . $a['apellido_usuario'];
                                                                                }
                                                                                echo htmlspecialchars(implode(', ', array_unique($usuarios)));
                                                                            }
                                                                            ?>
                                                                        </td>

                                                                        <!-- Columna Fecha -->
                                                                        <td style="padding: 12px; width: 15%;">
                                                                            <?php if ($primerArchivo['tipo'] === 'faltante'): ?>
                                                                                Pendiente
                                                                            <?php else: ?>
                                                                                <?php
                                                                                // Mostrar la fecha más reciente
                                                                                $fechas = array_map(function ($a) {
                                                                                    return strtotime($a['fecha_subida']);
                                                                                }, $grupoArchivos);
                                                                                echo date('d/m/Y H:i', max($fechas));
                                                                                ?>
                                                                            <?php endif; ?>
                                                                        </td>

                                                                        <!-- Columna Acciones -->
                                                                        <td style="padding: 12px; text-align: center; width: 10%;">
                                                                            <div
                                                                                style="display: flex; gap: 5px; justify-content: center; flex-wrap: wrap;">
                                                                                <?php if ($primerArchivo['tipo'] !== 'faltante'): ?>
                                                                                    <?php foreach ($grupoArchivos as $descArchivo): ?>
                                                                                        <?php
                                                                                        $esImagen = false;
                                                                                        if (!empty($descArchivo['ruta_archivo'])) {
                                                                                            $ext = strtolower(pathinfo($descArchivo['ruta_archivo'], PATHINFO_EXTENSION));
                                                                                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'])) {
                                                                                                $esImagen = true;
                                                                                                $imagenesParaCarrusel[] = [
                                                                                                    'url' => htmlspecialchars($descArchivo['ruta_archivo']),
                                                                                                    'nombre' => htmlspecialchars($descArchivo['nombre_archivo']),
                                                                                                    'categoria' => htmlspecialchars($descArchivo['pestaña'] ?? '')
                                                                                                ];
                                                                                                $indiceImagen = count($imagenesParaCarrusel) - 1;
                                                                                            }
                                                                                        }
                                                                                        ?>
                                                                                        <a href="javascript:void(0)"
                                                                                            onclick="<?= $esImagen ? "visualizarCarrusel($indiceImagen)" : "visualizarAdjunto('" . htmlspecialchars($descArchivo['ruta_archivo']) . "')" ?>"
                                                                                            class="btn-accion btn-editar"
                                                                                            title="Ver <?= $esImagen ? 'imagen' : 'documento' ?>">
                                                                                            <i
                                                                                                class="fas <?= $esImagen ? 'fa-image' : 'fa-file-pdf' ?>"></i>
                                                                                        </a>
                                                                                    <?php endforeach; ?>
                                                                                <?php else: ?>
                                                                                    <span style="color: #6c757d;">-</span>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div style="text-align: center; padding: 40px; color: #6c757d;">
                                            <i class="fas fa-folder-open" style="font-size: 3rem; margin-bottom: 15px;"></i>
                                            <p>No hay documentos en el expediente digital</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

