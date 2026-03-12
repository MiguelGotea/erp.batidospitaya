<!DOCTYPE html>
<html lang="es">
<?php
$imagenesParaCarrusel = [];
?>


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Colaborador</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/modales_premium.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/editar_colaborador.css?v=<?php echo mt_rand(1, 10000); ?>">
    <style>
        /* Estilos para las barras de progreso en las pestañas */
        .tab-button {
            position: relative;
            padding-bottom: 12px !important;
        }

        .tab-progress-container {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: #f1f3f5;
            border-radius: 0 0 4px 4px;
            overflow: hidden;
            display: none;
            /* Se muestra vía JS */
        }

        .tab-progress-bar {
            height: 100%;
            transition: width 0.5s ease, background-color 0.5s ease;
            width: 0%;
        }

        .tab-percentage {
            font-size: 0.62rem;
            font-weight: 900;
            margin-left: 8px;
            padding: 2px 6px;
            min-width: 28px;
            height: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            background: #eee;
            color: white !important;
            flex-shrink: 0;
            display: none;
            /* Se muestra vía JS */
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Colores del sistema de semáforo */
        .bg-red {
            background-color: #e74c3c !important;
        }

        .bg-yellow {
            background-color: #f1c40f !important;
            color: #856404 !important;
            /* Texto oscuro para mejor contraste en amarillo */
        }

        .bg-green {
            background-color: #27ae60 !important;
        }

        /* Color institucional para la barra */
        .bg-institucional {
            background-color: #0E544C !important;
        }
    </style>
</head>


<body>
    <?php echo renderMenuLateral($cargoId); ?>
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Datos del Colaborador'); ?>
            <div class="container-fluid">

                <?php if (isset($_SESSION['exito'])): ?>
                    <div class="alert alert-success">
                        <?= $_SESSION['exito'] ?>
                        <?php unset($_SESSION['exito']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?= $_SESSION['error'] ?>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <div class="form-container">
                    <!-- Sección de Perfil del Colaborador -->
                    <div class="tabs">
                        <!-- Foto y nombre del colaborador -->
                        <div class="perfil-colaborador">
                            <div class="foto-perfil-container">
                                <form id="formFotoPerfil" method="POST"
                                    action="editar_colaborador.php?id=<?= $codOperario ?>" enctype="multipart/form-data"
                                    style="position: relative;">
                                    <input type="hidden" name="pestaña" value="datos-personales">
                                    <input type="hidden" name="accion" value="guardar_foto_perfil">
                                    <input type="file" id="inputFotoPerfil" name="foto_perfil" accept="image/*"
                                        style="display: none;">

                                    <div class="foto-perfil" style="position: relative; cursor: pointer;">
                                        <?php if (!empty($colaborador['foto_perfil'])): ?>
                                            <img src="../../<?= htmlspecialchars($colaborador['foto_perfil']) ?>"
                                                alt="Foto de perfil" class="foto-img"
                                                onclick="abrirModalVerFoto('../../<?= htmlspecialchars($colaborador['foto_perfil']) ?>')">
                                        <?php else: ?>
                                            <div class="iniciales">
                                                <?= strtoupper(substr($colaborador['Nombre'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="edit-icon"
                                            onclick="event.stopPropagation(); document.getElementById('inputFotoPerfil').click()"
                                            title="Cambiar foto de perfil" style="cursor: pointer;">
                                            <i class="fas fa-pencil-alt"></i>
                                        </div>
                                        <?php if (!empty($colaborador['foto_perfil'])): ?>
                                            <div class="view-icon"
                                                onclick="abrirModalVerFoto('../../<?= htmlspecialchars($colaborador['foto_perfil']) ?>')"
                                                title="Ver foto completa"
                                                style="position: absolute; bottom: 10px; left: 10px; background: rgba(14, 84, 76, 0.9); color: white; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; opacity: 0; transition: opacity 0.3s;">
                                                <i class="fas fa-eye"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>

                            <div class="info-colaborador">
                                <h3 style="text-align:center;" class="nombre-completo">
                                    <?= htmlspecialchars($colaborador['Nombre'] . ' ' . $colaborador['Apellido'] . ' ' . ($colaborador['Apellido2'] ?? '')) ?>
                                </h3>
                                <p style="display:none;" class="cargo-actual">
                                    <?= htmlspecialchars($colaborador['cargo_nombre'] ?? 'Sin cargo definido') ?>
                                </p>
                                <p style="visibility:hidden;" class="codigo-operario">Código:
                                    <?= htmlspecialchars($colaborador['CodOperario']) ?>
                                </p>
                            </div>
                        </div>

                        <!-- Pestañas de navegación -->
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <a href="?id=<?= $codOperario ?>&pestaña=datos-personales"
                                class="tab-button <?= $pestaña_activa == 'datos-personales' ? 'active' : '' ?>">Datos
                                Personales</a>
                        <?php endif; ?>
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <a href="?id=<?= $codOperario ?>&pestaña=datos-contacto"
                                class="tab-button <?= $pestaña_activa == 'datos-contacto' ? 'active' : '' ?>">Datos de
                                Contacto</a>
                        <?php endif; ?>
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <a href="?id=<?= $codOperario ?>&pestaña=contactos-emergencia"
                                class="tab-button <?= $pestaña_activa == 'contactos-emergencia' ? 'active' : '' ?>">Contactos
                                de
                                Emergencia</a>
                        <?php endif; ?>
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <a href="?id=<?= $codOperario ?>&pestaña=contrato"
                                class="tab-button <?= $pestaña_activa == 'contrato' ? 'active' : '' ?>">Contrato</a>
                        <?php endif; ?>
                        <?php if (verificarAccesoCargo([0])): ?>
                            <a href="?id=<?= $codOperario ?>&pestaña=salario"
                                class="tab-button <?= $pestaña_activa == 'salario' ? 'active' : '' ?>">Salario</a>
                        <?php endif; ?>
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <a href="?id=<?= $codOperario ?>&pestaña=inss"
                                class="tab-button <?= $pestaña_activa == 'inss' ? 'active' : '' ?>">INSS</a>
                        <?php endif; ?>
                        <?php if (verificarAccesoCargo([0])): ?>
                            <a href="?id=<?= $codOperario ?>&pestaña=movimientos"
                                class="tab-button <?= $pestaña_activa == 'movimientos' ? 'active' : '' ?>">Movimientos</a>
                        <?php endif; ?>
                        <?php if (verificarAccesoCargo([0])): ?>
                            <a href="?id=<?= $codOperario ?>&pestaña=categoria"
                                class="tab-button <?= $pestaña_activa == 'categoria' ? 'active' : '' ?>">Categoría</a>
                        <?php endif; ?>
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <a href="?id=<?= $codOperario ?>&pestaña=adendums"
                                class="tab-button <?= $pestaña_activa == 'adendums' ? 'active' : '' ?>">Adenda de Contrato y
                                Movimientos</a>
                        <?php endif; ?>
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <a href="?id=<?= $codOperario ?>&pestaña=expediente-digital"
                                class="tab-button <?= $pestaña_activa == 'expediente-digital' ? 'active' : '' ?>">
                                Expediente Digital
                            </a>
                        <?php endif; ?>
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <a href="?id=<?= $codOperario ?>&pestaña=bitacora"
                                class="tab-button <?= $pestaña_activa == 'bitacora' ? 'active' : '' ?>">Bitácora</a>
                        <?php endif; ?>
                    </div>

                    <div class="tab-content">
                        <?php require_once 'editar_colaborador_componentes/tabs/tab_datos_personales.php'; ?>
                        <?php require_once 'editar_colaborador_componentes/tabs/tab_datos_contacto.php'; ?>
                        <?php require_once 'editar_colaborador_componentes/tabs/tab_contactos_emergencia.php'; ?>
                        <?php require_once 'editar_colaborador_componentes/tabs/tab_contrato.php'; ?>
                        <?php require_once 'editar_colaborador_componentes/tabs/tab_salario.php'; ?>
                        <?php require_once 'editar_colaborador_componentes/tabs/tab_inss.php'; ?>
                        <?php require_once 'editar_colaborador_componentes/tabs/tab_movimientos.php'; ?>
                        <?php require_once 'editar_colaborador_componentes/tabs/tab_categoria.php'; ?>
                        <?php require_once 'editar_colaborador_componentes/tabs/tab_adendums.php'; ?>
                        <?php require_once 'editar_colaborador_componentes/tabs/tab_expediente_digital.php'; ?>
                        <?php require_once 'editar_colaborador_componentes/tabs/tab_bitacora.php'; ?>
                    </div>

                    <!-- Modal para agregar/editar cuenta bancaria -->
                    <div id="modalCuenta" class="modal-backdrop">
                        <div class="modal-content">
                            <h3 style="color: #0E544C; margin-bottom: 20px;" id="tituloModalCuenta">Agregar Cuenta
                                Bancaria
                            </h3>

                            <form method="POST" action="" id="formCuenta">
                                <input type="hidden" name="accion_cuenta" id="accionCuenta" value="agregar">
                                <input type="hidden" name="id_cuenta" id="idCuenta" value="">

                                <div class="form-group">
                                    <label for="numero_cuenta_modal">Número de Cuenta</label>
                                    <input type="text" id="numero_cuenta_modal" name="numero_cuenta"
                                        class="form-control" maxlength="9" required>
                                </div>

                                <div class="form-group">
                                    <label for="titular_modal">Titular</label>
                                    <input type="text" id="titular_modal" name="titular" class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label for="banco_modal">Banco</label>
                                    <select id="banco_modal" name="banco" class="form-control" required>
                                        <option value="Lafise" selected>Lafise</option>
                                    </select>
                                    <input type="hidden" name="banco" value="Lafise">
                                </div>

                                <div class="form-group">
                                    <label for="moneda_modal">Moneda</label>
                                    <select id="moneda_modal" name="moneda" class="form-control" required>
                                        <option value="NIO" selected>Córdobas (NIO)</option>
                                    </select>
                                    <input type="hidden" name="moneda" value="NIO">
                                </div>

                                <div class="form-group">
                                    <label for="desde_modal">Desde</label>
                                    <input type="date" id="desde_modal" name="desde" class="form-control" required>
                                </div>

                                <div
                                    style="display: flex; justify-content: space-between; gap: 10px; margin-top: 25px;">
                                    <button type="button" class="btn-modern btn-modern-secondary"
                                        onclick="cerrarModalCuenta()">
                                        <i class="fas fa-times"></i> Cancelar
                                    </button>
                                    <button type="submit" class="btn-modern btn-modern-primary">
                                        <i class="fas fa-save"></i> Guardar solo esta Cuenta
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Modal para agregar/editar contacto de emergencia -->
                    <div id="modalContacto" class="modal-backdrop">
                        <div class="modal-content">
                            <h3 style="color: #0E544C; margin-bottom: 20px;" id="tituloModalContacto">Agregar Contacto
                                de
                                Emergencia
                            </h3>

                            <form method="POST" action="" id="formContacto">
                                <input type="hidden" name="accion_contacto" id="accionContacto" value="agregar">
                                <input type="hidden" name="id_contacto" id="idContacto" value="">

                                <div class="form-group">
                                    <label for="nombre_contacto_modal">Nombre</label>
                                    <input type="text" id="nombre_contacto_modal" name="nombre_contacto"
                                        class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label for="parentesco_modal">Parentesco</label>
                                    <input type="text" id="parentesco_modal" name="parentesco" class="form-control"
                                        required>
                                </div>

                                <div class="form-group">
                                    <label for="telefono_movil_modal">Teléfono Móvil</label>
                                    <input type="text" id="telefono_movil_modal" name="telefono_movil"
                                        class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label for="telefono_casa_modal">Teléfono de Casa</label>
                                    <input type="text" id="telefono_casa_modal" name="telefono_casa"
                                        class="form-control">
                                </div>

                                <div class="form-group">
                                    <label for="telefono_trabajo_modal">Teléfono de Trabajo</label>
                                    <input type="text" id="telefono_trabajo_modal" name="telefono_trabajo"
                                        class="form-control">
                                </div>

                                <div
                                    style="display: flex; justify-content: space-between; gap: 10px; margin-top: 25px;">
                                    <button type="button" class="btn-modern btn-modern-secondary"
                                        onclick="cerrarModalContacto()">
                                        <i class="fas fa-times"></i> Cancelar
                                    </button>
                                    <button type="submit" class="btn-modern btn-modern-primary">
                                        <i class="fas fa-save"></i> Guardar solo este Contacto
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Modal para terminación de contrato -->
                    <div id="modalTerminacion" class="modal-backdrop">
                        <div class="modal-content">
                            <h3 style="color: #dc3545; margin-bottom: 20px;">Terminar Contrato</h3>

                            <form method="POST" action="" enctype="multipart/form-data" id="formTerminacion">
                                <input type="hidden" name="pestaña" value="contrato">
                                <input type="hidden" name="accion_contrato" value="terminar"> <!-- CAMBIADO -->
                                <input type="hidden" name="id_contrato" id="idContratoTerminar"
                                    value="<?= $contratoActual ? $contratoActual['CodContrato'] : '' ?>">

                                <!-- Fecha Fin de Contrato - SOLO LECTURA -->
                                <div class="form-group">
                                    <label for="fecha_fin_contrato">Fecha Fin de Contrato (solo lectura)</label>
                                    <input type="date" id="fecha_fin_contrato" name="fecha_fin_contrato"
                                        class="form-control"
                                        value="<?= $contratoActual ? ($contratoActual['fin_contrato'] ?? '') : '' ?>"
                                        readonly style="background-color: #f8f9fa;">
                                    <small style="color: #6c757d;">Esta fecha no se puede modificar al terminar el
                                        contrato</small>
                                </div>

                                <div class="form-group">
                                    <label for="fecha_terminacion">Fecha de Salida/Terminación *</label>
                                    <input type="date" id="fecha_terminacion" name="fecha_terminacion"
                                        class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="fecha_liquidacion">Fecha de Liquidación (opcional - puede asignarse
                                        después)</label>
                                    <input type="date" id="fecha_liquidacion" name="fecha_liquidacion"
                                        class="form-control">
                                    <small style="color: #6c757d; display:none;">Fecha cuando se realizará el pago de
                                        liquidación</small>
                                </div>

                                <div class="form-group">
                                    <label for="tipo_salida">Tipo de Salida *</label>
                                    <select id="tipo_salida" name="tipo_salida" class="form-control" required>
                                        <option value="">Seleccionar tipo de salida...</option>
                                        <?php
                                        $tiposSalida = obtenerTiposSalida();
                                        foreach ($tiposSalida as $tipo): ?>
                                            <option value="<?= $tipo['CodTipoSalida'] ?>">
                                                <?= htmlspecialchars($tipo['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="motivo_salida">Motivo de Salida *</label>
                                    <textarea id="motivo_salida" name="motivo_salida" class="form-control" rows="3"
                                        required></textarea>
                                </div>

                                <div style="display:none;" class="form-group">
                                    <label for="foto_renuncia">Foto de Renuncia (opcional)</label>
                                    <input type="file" id="foto_renuncia" name="foto_renuncia" class="form-control"
                                        accept="image/*,.pdf">
                                </div>

                                <div style="display:none;" class="form-group">
                                    <label for="devolucion_herramientas">Devolución de Herramientas de Trabajo</label>
                                    <select id="devolucion_herramientas" name="devolucion_herramientas"
                                        class="form-control">
                                        <option value="0">No aplica</option>
                                        <option value="1">Sí aplica</option>
                                    </select>
                                </div>

                                <div class="form-group" id="grupoPersonaHerramientas" style="display: none;">
                                    <label for="persona_recibe_herramientas">Persona que Recibe Herramientas</label>
                                    <input type="text" id="persona_recibe_herramientas"
                                        name="persona_recibe_herramientas" class="form-control">
                                </div>

                                <div style="display:none;" class="form-group">
                                    <label for="dias_trabajados">Días Trabajados *</label>
                                    <input type="number" id="dias_trabajados" name="dias_trabajados"
                                        class="form-control" min="1" required>
                                </div>

                                <div style="display:none;" class="form-group">
                                    <label for="monto_indemnizacion">Indemnización</label>
                                    <input type="number" id="monto_indemnizacion" name="monto_indemnizacion"
                                        class="form-control" step="0.01" min="0">
                                    <small style="color: #6c757d;">Monto en córdobas (opcional)</small>
                                </div>

                                <div
                                    style="display: flex; justify-content: space-between; gap: 10px; margin-top: 25px;">
                                    <button type="button" class="btn-modern btn-modern-secondary"
                                        onclick="cerrarModalTerminacion()">
                                        <i class="fas fa-times"></i> Cancelar
                                    </button>
                                    <button type="submit" class="btn-modern btn-modern-danger">
                                        <i class="fas fa-user-slash"></i> Confirmar solo Terminación de
                                        Contrato
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Modal para agregar/editar salario -->
                    <div id="modalSalario" class="modal-backdrop">
                        <div class="modal-content">
                            <h3 style="color: #0E544C; margin-bottom: 20px;" id="tituloModalSalario">Agregar Salario
                            </h3>

                            <form method="POST" action="" id="formSalario">
                                <input type="hidden" name="accion_salario" id="accionSalario" value="agregar">
                                <input type="hidden" name="id_salario" id="idSalario" value="">

                                <div class="form-group">
                                    <label for="monto_modal">Monto (C$)</label>
                                    <input type="number" id="monto_modal" name="monto" class="form-control" step="0.01"
                                        min="0" required>
                                </div>

                                <div class="form-group">
                                    <label for="inicio_modal">Desde</label>
                                    <input type="date" id="inicio_modal" name="inicio" class="form-control" required>
                                </div>

                                <div class="form-group" style="display: none;">
                                    <label for="fin_modal">Hasta (opcional)</label>
                                    <input type="date" id="fin_modal" name="fin" class="form-control">
                                    <small style="color: #6c757d;">Dejar vacío si es el salario actual</small>
                                </div>

                                <div class="form-group">
                                    <label for="frecuencia_pago_modal">Frecuencia de Pago</label>
                                    <select id="frecuencia_pago_modal" name="frecuencia_pago" class="form-control"
                                        required>
                                        <option value="">Seleccionar...</option>
                                        <option value="quincenal">Quincenal</option>
                                        <option value="mensual">Mensual</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="observaciones_modal">Observaciones (opcional)</label>
                                    <textarea id="observaciones_modal" name="observaciones" class="form-control"
                                        rows="3"></textarea>
                                </div>

                                <div
                                    style="display: flex; justify-content: space-between; gap: 10px; margin-top: 25px;">
                                    <button type="button" class="btn-modern btn-modern-secondary"
                                        onclick="cerrarModalSalario()">
                                        <i class="fas fa-times"></i> Cancelar
                                    </button>
                                    <button type="submit" class="btn-modern btn-modern-primary">
                                        <i class="fas fa-save"></i> Guardar solo este Salario
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Modal para agregar archivos adjuntos -->
                    <div id="modalAdjunto" class="modal-backdrop">
                        <div class="modal-content">
                            <h3 style="color: #0E544C; margin-bottom: 20px;">Agregar Archivo Adjunto</h3>

                            <form method="POST" action="" enctype="multipart/form-data" id="formAdjunto">
                                <input type="hidden" name="accion_adjunto" value="agregar">
                                <input type="hidden" name="pestaña_adjunto" id="pestañaAdjunto" value="">
                                <input type="hidden" name="cod_adendum_asociado" id="codAdendumAsociado" value="">

                                <div class="form-group">
                                    <label for="tipo_documento_adjunto">Tipo de Documento *</label>
                                    <select id="tipo_documento_adjunto" name="tipo_documento" class="form-control"
                                        required onchange="actualizarDescripcionPorTipo()">
                                        <option value="">Seleccionar tipo de documento...</option>
                                        <!-- Las opciones se llenarán dinámicamente con JavaScript -->
                                    </select>
                                    <small id="ayudaTipoDocumento" style="color: #6c757d; display: none;"></small>
                                </div>

                                <div class="form-group" id="grupo_fecha_vencimiento" style="display: none;">
                                    <label for="fecha_vencimiento_adjunto">Fecha de Vencimiento *</label>
                                    <input type="date" id="fecha_vencimiento_adjunto" name="fecha_vencimiento"
                                        class="form-control">
                                    <small style="color: #6c757d;">Este documento requiere fecha de vencimiento</small>
                                </div>

                                <div class="form-group">
                                    <label for="descripcion_adjunto">Descripción (opcional)</label>
                                    <textarea id="descripcion_adjunto" name="descripcion_adjunto" class="form-control"
                                        rows="2" placeholder="Breve descripción del archivo"></textarea>
                                </div>

                                <div id="infoDocumentoObligatorio"
                                    style="display: none; background-color: #e8f5e8; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                                    <i class="fas fa-info-circle" style="color: #0E544C;"></i>
                                    <span style="color: #0E544C; font-weight: bold;">Documento Obligatorio</span>
                                    <p style="margin: 5px 0 0 0; color: #2d5016;" id="textoObligatorio"></p>
                                </div>

                                <!-- Zona Unificada de Adjuntos -->
                                <div id="zonaAdjuntos"
                                    style="border: 2px dashed #51B8AC; padding: 12px; border-radius: 12px; margin-bottom: 20px; background: #f8fbfb;">
                                    <h4
                                        style="margin: 0 0 10px 0; color: #0E544C; font-size: 0.8rem; font-weight: 800; text-transform: uppercase;">
                                        <i class="fas fa-paperclip"></i> Zona de Adjuntos
                                    </h4>

                                    <!-- Botones de Acción -->
                                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                        <!-- 1. Seleccionar PDF -->
                                        <div style="flex: 1; min-width: 120px;">
                                            <button type="button" class="btn-submit"
                                                style="background-color: #0E544C; font-size: 0.75rem; padding: 6px 10px; width: 100%; border-radius: 8px;"
                                                onclick="document.getElementById('archivo_adjunto').click()">
                                                <i class="fas fa-file-pdf"></i> PDF
                                            </button>
                                            <input type="file" id="archivo_adjunto" style="display: none;" accept=".pdf"
                                                multiple onchange="manejarSeleccionPDF(this)">
                                        </div>

                                        <!-- 2. Seleccionar Foto -->
                                        <div style="flex: 1; min-width: 120px;">
                                            <button type="button" class="btn-submit"
                                                style="background-color: #51B8AC; font-size: 0.75rem; padding: 6px 10px; width: 100%; border-radius: 8px;"
                                                onclick="document.getElementById('inputSeleccionarFotos').click()">
                                                <i class="fas fa-images"></i> Foto
                                            </button>
                                            <input type="file" id="inputSeleccionarFotos" style="display: none;"
                                                multiple accept="image/*" onchange="manejarSeleccionFotos(this)">
                                        </div>

                                        <!-- 3. Iniciar Cámara -->
                                        <div style="flex: 1; min-width: 120px;">
                                            <button type="button" id="btnToggleCamara" class="btn-submit"
                                                style="background-color: #1a9083; font-size: 0.75rem; padding: 6px 10px; width: 100%; border-radius: 8px;"
                                                onclick="toggleCamara()">
                                                <i class="fas fa-camera"></i> Cámara
                                            </button>
                                            <button type="button" id="btnCambiarCamara" class="btn-submit"
                                                style="background-color: #6c757d; font-size: 0.75rem; padding: 6px 10px; width: 100%; border-radius: 8px; display: none; margin-top: 4px;"
                                                onclick="cambiarCamara()">
                                                <i class="fas fa-sync"></i> Cambiar
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Contenedor Video Cámara -->
                                    <div id="contenedorVideo"
                                        style="display: none; position: relative; margin-top: 15px; text-align: center; border-radius: 12px; overflow: hidden; background: #000; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                                        <video id="videoCaptura"
                                            style="width: 100%; max-width: 400px; display: block; margin: 0 auto;"></video>
                                        <button type="button" id="btnFlash" onclick="toggleFlash()"
                                            style="display: none; position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.5); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; cursor: pointer; align-items: center; justify-content: center; z-index: 10;"><i
                                                class="fas fa-bolt"></i></button>
                                        <button type="button" onclick="capturarFoto()"
                                            style="position: absolute; bottom: 15px; left: 50%; transform: translateX(-50%); background: #fff; color: #0E544C; border: 4px solid #0E544C; border-radius: 50%; width: 50px; height: 50px; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.3); transition: all 0.2s;"><i
                                                class="fas fa-camera" style="font-size: 1.5rem;"></i></button>
                                        <canvas id="canvasAuxiliar" style="display: none;"></canvas>
                                    </div>

                                    <!-- Lista Unificada de Adjuntos -->
                                    <div id="listaAdjuntosUnificada"
                                        style="margin-top: 15px; display: flex; flex-direction: column; gap: 8px;">
                                        <!-- Se llenará dinámicamente -->
                                    </div>
                                    <input type="hidden" name="adjuntos_unificados_json" id="adjuntosUnificadosInput"
                                        value="[]">
                                </div>

                                <div
                                    style="display: flex; justify-content: space-between; gap: 10px; margin-top: 25px;">
                                    <button type="button" class="btn-modern btn-modern-secondary"
                                        onclick="cerrarModalAdjunto()">
                                        <i class="fas fa-times"></i> Cancelar
                                    </button>
                                    <button type="submit" class="btn-modern btn-modern-primary" id="btnSubirAdjunto">
                                        <i class="fas fa-upload"></i> Subir solo este
                                        Archivo
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Modal para agregar Salario INSS -->
                    <div id="modalSalarioINSS" class="modal-backdrop">
                        <div class="modal-content">
                            <h3 style="color: #0E544C; margin-bottom: 20px;">Agregar Salario INSS</h3>

                            <form method="POST" action="" id="formSalarioINSS">
                                <input type="hidden" name="accion_inss" value="agregar">
                                <input type="hidden" name="pestaña" value="inss">

                                <div class="form-group">
                                    <label for="monto_salario_inss_modal">Salario INSS (C$)</label>
                                    <input type="number" id="monto_salario_inss_modal" name="monto_salario_inss"
                                        class="form-control" step="0.01" min="0" required>
                                </div>

                                <div class="form-group">
                                    <label for="inicio_inss_modal">Inicio INSS</label>
                                    <input type="date" id="inicio_inss_modal" name="inicio" class="form-control"
                                        required>
                                </div>

                                <div class="form-group">
                                    <label for="observaciones_inss_modal">Observaciones</label>
                                    <textarea id="observaciones_inss_modal" name="observaciones_inss"
                                        class="form-control" rows="3"></textarea>
                                </div>

                                <div
                                    style="display: flex; justify-content: space-between; gap: 10px; margin-top: 25px;">
                                    <button type="button" class="btn-modern btn-modern-secondary"
                                        onclick="cerrarModalSalarioINSS()">
                                        <i class="fas fa-times"></i> Cancelar
                                    </button>
                                    <button type="submit" class="btn-modern btn-modern-primary">
                                        <i class="fas fa-save"></i> Guardar solo datos de INSS
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>


                    <!-- Modal para editar movimiento de cargo -->
                    <div id="modalMovimiento" class="modal-backdrop">
                        <div class="modal-content">
                            <h3 style="color: #0E544C; margin-bottom: 20px;">Editar Movimiento de Cargo</h3>

                            <form method="POST" action="" id="formMovimiento">
                                <input type="hidden" name="accion_movimiento" value="editar">
                                <input type="hidden" name="id_movimiento" id="idMovimiento" value="">
                                <input type="hidden" name="pestaña" value="movimientos">

                                <div class="form-group">
                                    <label for="edit_cod_cargo">Cargo *</label>
                                    <select id="edit_cod_cargo" name="cod_cargo" class="form-control" required>
                                        <option value="">Seleccionar cargo...</option>
                                        <?php foreach ($cargosDisponibles as $cargo): ?>
                                            <option value="<?= $cargo['CodNivelesCargos'] ?>">
                                                <?= htmlspecialchars($cargo['Nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="edit_sucursal">Sucursal *</label>
                                    <select id="edit_sucursal" name="sucursal" class="form-control" required>
                                        <option value="">Seleccionar sucursal...</option>
                                        <?php foreach ($sucursales as $sucursal): ?>
                                            <option value="<?= $sucursal['codigo'] ?>">
                                                <?= htmlspecialchars($sucursal['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="edit_fecha_inicio">Fecha de Inicio *</label>
                                    <input type="date" id="edit_fecha_inicio" name="fecha_inicio" class="form-control"
                                        required>
                                </div>

                                <div class="form-group">
                                    <label for="edit_fecha_fin">Fecha de Fin (opcional)</label>
                                    <input type="date" id="edit_fecha_fin" name="fecha_fin" class="form-control">
                                </div>

                                <!-- Tipo de contrato oculto, no se muestra ni edita -->
                                <input type="hidden" name="tipo_contrato" id="edit_tipo_contrato" value="">

                                <div
                                    style="display: flex; justify-content: space-between; gap: 10px; margin-top: 25px;">
                                    <button type="button" class="btn-modern btn-modern-secondary"
                                        onclick="cerrarModalMovimiento()">
                                        <i class="fas fa-times"></i> Cancelar
                                    </button>
                                    <button type="submit" class="btn-modern btn-modern-primary">
                                        <i class="fas fa-save"></i> Guardar solo este Movimiento
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Modal de previsualización -->
                    <div id="previewModal" class="preview-modal">
                        <div class="preview-content">
                            <h3 class="preview-title">Previsualización de foto de perfil</h3>
                            <img id="previewImage" class="preview-image" src="" alt="Vista previa">
                            <p>¿Deseas usar esta imagen como tu foto de perfil?</p>
                            <div class="preview-buttons"
                                style="display: flex; justify-content: center; gap: 15px; margin-top: 25px;">
                                <button class="btn-modern btn-modern-secondary" onclick="cancelarPreview()">
                                    <i class="fas fa-times"></i> Cancelar
                                </button>
                                <button class="btn-modern btn-modern-primary" onclick="confirmarFoto()">
                                    <i class="fas fa-check"></i> Sí, usar esta foto
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Modal para finalizar adenda -->
                    <div id="modalFinalizarAdenda" class="modal-backdrop">
                        <div class="modal-content">
                            <h3 style="color: #dc3545; margin-bottom: 20px;">Finalizar Adenda</h3>

                            <form method="POST" action="" id="formFinalizarAdenda">
                                <input type="hidden" name="accion_finalizar_adenda" value="finalizar">
                                <input type="hidden" name="id_adendum_finalizar" id="idAdendumFinalizar" value="">
                                <input type="hidden" name="pestaña" value="adendums">

                                <div class="form-group">
                                    <label for="fecha_fin_adenda">Fecha de Finalización *</label>
                                    <input type="date" id="fecha_fin_adenda" name="fecha_fin_adenda"
                                        class="form-control" value="<?= date('Y-m-d') ?>" required>
                                    <small style="color: #6c757d;">Fecha cuando finaliza esta adenda</small>
                                </div>

                                <div
                                    style="display: flex; justify-content: space-between; gap: 10px; margin-top: 25px;">
                                    <button type="button" class="btn-modern btn-modern-secondary"
                                        onclick="cerrarModalFinalizarAdenda()">
                                        <i class="fas fa-times"></i> Cancelar
                                    </button>
                                    <button type="submit" class="btn-modern btn-modern-danger">
                                        <i class="fas fa-check-circle"></i> Finalizar solo esta Adenda
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Modal para ver foto de perfil en tamaño completo y Carrusel -->
                    <div id="modalVerFoto"
                        style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.95); overflow: hidden; user-select: none;">

                        <!-- Botón Cerrar -->
                        <span onclick="cerrarModalVerFoto()"
                            style="position: absolute; top: 20px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; cursor: pointer; z-index: 10001; transition: 0.3s;"
                            onmouseover="this.style.color='#bbb'" onmouseout="this.style.color='#f1f1f1'">&times;</span>

                        <!-- Contenedor de Imagen -->
                        <div
                            style="width: 100%; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; position: relative;">

                            <!-- Botón Anterior -->
                            <div id="btnPrevCarrusel" onclick="navegarCarrusel(-1)"
                                style="position: absolute; left: 20px; color: white; font-size: 50px; cursor: pointer; z-index: 10000; padding: 20px; display: none;">
                                <i class="fas fa-chevron-left"></i>
                            </div>

                            <!-- Imagen -->
                            <div id="contenedorImagenModal"
                                style="position: relative; max-width: 90%; max-height: 80%; display: flex; justify-content: center; align-items: center;">
                                <!-- Spinner de Carga -->
                                <div id="spinnerCargaModal"
                                    style="display: none; position: absolute; color: white; flex-direction: column; align-items: center; justify-content: center;">
                                    <i class="fas fa-spinner fa-spin" style="font-size: 3rem; margin-bottom: 10px;"></i>
                                    <span>Cargando imagen...</span>
                                </div>

                                <!-- Mensaje de Error -->
                                <div id="errorCargaModal"
                                    style="display: none; position: absolute; color: #ff6b6b; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                                    <i class="fas fa-exclamation-triangle"
                                        style="font-size: 3rem; margin-bottom: 10px;"></i>
                                    <div style="font-size: 1.2rem; margin-bottom: 15px;">No se pudo cargar la imagen
                                    </div>
                                    <button onclick="reintentarCargaImagen()"
                                        style="background: #ff6b6b; color: white; border: none; padding: 8px 20px; border-radius: 5px; cursor: pointer; font-weight: bold;">
                                        <i class="fas fa-sync-alt"></i> Reintentar
                                    </button>
                                </div>

                                <img id="imagenFotoCompleta" onload="onImagenCargada()" onerror="onImagenError()"
                                    style="max-width: 100%; max-height: 100%; object-fit: contain; box-shadow: 0 0 20px rgba(0,0,0,0.5); transition: transform 0.3s ease; display: none;">
                            </div>

                            <!-- Pie de foto / Título -->
                            <div id="infoCarrusel"
                                style="color: white; margin-top: 20px; text-align: center; font-family: sans-serif;">
                                <div id="tituloImagenCarrusel"
                                    style="font-size: 1.2rem; margin-bottom: 5px; font-weight: 500;">
                                </div>
                                <div id="contadorCarrusel" style="font-size: 1rem; color: #ccc;"></div>
                            </div>

                            <!-- Botón Siguiente -->
                            <div id="btnNextCarrusel" onclick="navegarCarrusel(1)"
                                style="position: absolute; right: 20px; color: white; font-size: 50px; cursor: pointer; z-index: 10000; padding: 20px; display: none;">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Modal para editar categoría -->
                    <div id="modalCategoria" class="modal-backdrop">
                        <div class="modal-content">
                            <h3>Editar Categoría</h3>

                            <form method="POST" action="" id="formCategoria">
                                <input type="hidden" name="accion_categoria" value="editar">
                                <input type="hidden" name="id_categoria_edit" id="idCategoriaEdit" value="">
                                <input type="hidden" name="pestaña" value="categoria">

                                <div class="form-group">
                                    <label for="edit_id_categoria">Categoría *</label>
                                    <select id="edit_id_categoria" name="id_categoria" class="form-control" required>
                                        <option value="">Seleccionar categoría...</option>
                                        <?php foreach ($todasCategorias as $categoria): ?>
                                            <option value="<?= $categoria['idCategoria'] ?>">
                                                <?= htmlspecialchars($categoria['NombreCategoria']) ?>
                                                (Peso: <?= $categoria['Peso'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="edit_fecha_inicio">Fecha de Inicio *</label>
                                    <input type="date" id="edit_fecha_inicio" name="fecha_inicio" class="form-control"
                                        required>
                                </div>

                                <div style="display:none;" class="form-group">
                                    <label for="edit_fecha_fin">Fecha de Fin</label>
                                    <input type="date" id="edit_fecha_fin" name="fecha_fin" class="form-control">
                                    <small style="color: #6c757d;">Dejar vacío si es la categoría actual</small>
                                </div>

                                <div
                                    style="display: flex; justify-content: space-between; gap: 10px; margin-top: 25px;">
                                    <button type="button" class="btn-modern btn-modern-secondary"
                                        onclick="cerrarModalCategoria()">
                                        <i class="fas fa-times"></i> Cancelar
                                    </button>
                                    <button type="submit" class="btn-modern btn-modern-primary">
                                        <i class="fas fa-save"></i> Guardar Cambios
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Modal para editar adendum -->
                    <div id="modalAdendum" class="modal-backdrop">
                        <div class="modal-content">
                            <h3>Editar Adendum</h3>

                            <form method="POST" action="" id="formAdendum">
                                <input type="hidden" name="accion_adendum" value="editar">
                                <input type="hidden" name="id_adendum" id="edit_id_adendum" value="">
                                <input type="hidden" name="pestaña" value="adendums">

                                <div class="form-group">
                                    <label for="edit_tipo_adendum">Tipo de Adendum *</label>
                                    <select id="edit_tipo_adendum" name="tipo_adendum" class="form-control" required
                                        onchange="actualizarCamposEdicionAdendum()">
                                        <option value="">Seleccionar tipo...</option>
                                        <option value="cargo">Cambio de Cargo</option>
                                        <option value="salario">Ajuste Salarial</option>
                                        <option value="ambos">Cambio de Cargo y Salario</option>
                                    </select>
                                </div>

                                <div class="form-row">
                                    <div class="form-col">
                                        <div class="form-group" id="edit_grupo_cargo">
                                            <label for="edit_cod_cargo_adendum">Cargo</label>
                                            <select id="edit_cod_cargo_adendum" name="cod_cargo" class="form-control">
                                                <option value="">Seleccionar cargo...</option>
                                                <?php foreach ($cargosDisponibles as $cargo): ?>
                                                    <option value="<?= $cargo['CodNivelesCargos'] ?>">
                                                        <?= htmlspecialchars($cargo['Nombre']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="form-group" id="edit_grupo_sucursal">
                                            <label for="edit_sucursal_adendum">Sucursal</label>
                                            <select id="edit_sucursal_adendum" name="sucursal" class="form-control">
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
                                        <div class="form-group" id="edit_grupo_categoria">
                                            <label for="edit_id_categoria_adendum">Categoría</label>
                                            <select id="edit_id_categoria_adendum" name="id_categoria"
                                                class="form-control">
                                                <option value="">Seleccionar categoría...</option>
                                                <?php foreach ($todasCategorias as $categoria): ?>
                                                    <option value="<?= $categoria['idCategoria'] ?>">
                                                        <?= htmlspecialchars($categoria['NombreCategoria']) ?>
                                                        (Peso: <?= $categoria['Peso'] ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="form-group" id="edit_grupo_salario">
                                            <label for="edit_salario_adendum">Salario (C$)</label>
                                            <input type="number" id="edit_salario_adendum" name="salario"
                                                class="form-control" step="0.01" min="0" placeholder="0.00">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="edit_fecha_inicio_adendum">Fecha de Inicio *</label>
                                    <input type="date" id="edit_fecha_inicio_adendum" name="fecha_inicio"
                                        class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label for="edit_fecha_fin_adendum">Fecha de Fin</label>
                                    <input type="date" id="edit_fecha_fin_adendum" name="fecha_fin"
                                        class="form-control">
                                    <small style="color: #6c757d;">Dejar vacío si es el adendum actual</small>
                                </div>

                                <div class="form-group">
                                    <label for="edit_observaciones_adendum">Observaciones</label>
                                    <textarea id="edit_observaciones_adendum" name="observaciones" class="form-control"
                                        rows="3"></textarea>
                                </div>

                                <div
                                    style="display: flex; justify-content: space-between; gap: 10px; margin-top: 25px;">
                                    <button type="button" class="btn-modern btn-modern-secondary"
                                        onclick="cerrarModalAdendum()">
                                        <i class="fas fa-times"></i> Cancelar
                                    </button>
                                    <button type="submit" class="btn-modern btn-modern-primary">
                                        <i class="fas fa-save"></i> Guardar Cambios
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>


                    <script>
                        // Función para ocultar mensajes automáticamente después de 5 segundos
                        function ocultarMensajesAutomaticamente() {
                            const mensajes = document.querySelectorAll('.alert');
                            mensajes.forEach(mensaje => {
                                setTimeout(() => {
                                    mensaje.style.transition = 'opacity 0.5s ease';
                                    mensaje.style.opacity = '0';
                                    setTimeout(() => {
                                        mensaje.remove();
                                    }, 500);
                                }, 5000); // 5 segundos
                            });
                        }

                        // Función para actualizar categoría automáticamente según el cargo seleccionado
                        function actualizarCategoria() {
                            const codCargo = document.getElementById('cod_cargo').value;
                            const selectCategoria = document.getElementById('id_categoria');

                            if (codCargo == '2') {
                                // Si es cargo 2 (Operario), seleccionar categoría 5
                                for (let i = 0; i < selectCategoria.options.length; i++) {
                                    if (selectCategoria.options[i].value == '5') {
                                        selectCategoria.value = '5';
                                        break;
                                    }
                                }
                            } else if (codCargo == '5') {
                                // Si es cargo 5 (Líder de Sucursal), seleccionar categoría 1
                                for (let i = 0; i < selectCategoria.options.length; i++) {
                                    if (selectCategoria.options[i].value == '1') {
                                        selectCategoria.value = '1';
                                        break;
                                    }
                                }
                            }
                            // Para otros cargos, no se selecciona automáticamente
                        }

                        // Función mejorada para abrir modal de terminación
                        function abrirModalTerminacion() {
                            document.getElementById('modalTerminacion').style.display = 'block';

                            // Obtener información del contrato actual
                            const contratoActual = <?= $contratoActual ? json_encode($contratoActual) : 'null' ?>;

                            if (contratoActual) {
                                // Establecer el ID del contrato en el formulario
                                document.getElementById('idContratoTerminar').value = contratoActual.CodContrato;

                                // Mostrar fecha fin del contrato (solo lectura)
                                if (contratoActual.fin_contrato && contratoActual.fin_contrato != '0000-00-00') {
                                    document.getElementById('fecha_fin_contrato').value = contratoActual.fin_contrato;
                                } else {
                                    document.getElementById('fecha_fin_contrato').value = '';
                                    document.getElementById('fecha_fin_contrato').placeholder = 'Contrato indefinido';
                                }

                                // Calcular días trabajados automáticamente desde inicio_contrato hasta fecha actual
                                const inicioContrato = contratoActual.inicio_contrato;
                                if (inicioContrato) {
                                    const inicio = new Date(inicioContrato);
                                    const hoy = new Date();
                                    const diffTime = Math.abs(hoy - inicio);
                                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                                    document.getElementById('dias_trabajados').value = diffDays;
                                }
                            }

                            // Establecer fecha de hoy como fecha de terminación por defecto
                            document.getElementById('fecha_terminacion').valueAsDate = new Date();
                        }

                        // Función para confirmar la terminación con validación
                        function confirmarTerminacion() {
                            const fechaTerminacion = document.getElementById('fecha_terminacion').value;
                            const tipoSalida = document.getElementById('tipo_salida').value;
                            const motivoSalida = document.getElementById('motivo_salida').value;

                            if (!fechaTerminacion || !tipoSalida || !motivoSalida.trim()) {
                                alert('Por favor complete todos los campos obligatorios.');
                                return false;
                            }

                            if (confirm('¿Está seguro de que desea terminar este contrato? Esta acción cerrará todos los registros activos del colaborador y no se puede deshacer.')) {
                                return true;
                            }

                            return false;
                        }

                        function cerrarModalTerminacion() {
                            const modal = document.getElementById('modalTerminacion');
                            const form = document.getElementById('formTerminacion');

                            // Restaurar título
                            document.querySelector('#modalTerminacion h3').textContent = 'Terminar Contrato';

                            // Restaurar acción a "terminar"
                            const inputAccion = form.querySelector('input[name="accion_contrato"]');
                            inputAccion.value = 'terminar';

                            // Rehabilitar id_contrato original
                            const inputIdOriginal = form.querySelector('input[name="id_contrato"]');
                            if (inputIdOriginal) {
                                inputIdOriginal.disabled = false;
                            }

                            // Eliminar id_contrato_editar si existe
                            const inputIdEditar = form.querySelector('input[name="id_contrato_editar"]');
                            if (inputIdEditar) {
                                inputIdEditar.remove();
                            }

                            // Restaurar texto del botón
                            const btnSubmit = form.querySelector('button[type="submit"]');
                            btnSubmit.textContent = 'Confirmar solo Terminación de Contrato';
                            btnSubmit.style.backgroundColor = '#dc3545'; // Restaurar color rojo

                            // Cerrar modal
                            modal.style.display = 'none';
                        }

                        // Asignar el evento de confirmación al formulario
                        document.getElementById('formTerminacion').addEventListener('submit', function (e) {
                            if (!confirmarTerminacion()) {
                                e.preventDefault();
                            }
                        });

                        // Función para mostrar/ocultar contraseña
                        function togglePasswordVisibility() {
                            const passwordInput = document.getElementById('clave');
                            const toggleButton = document.getElementById('toggleClave');
                            const icon = toggleButton.querySelector('i');

                            if (passwordInput.type === 'password') {
                                passwordInput.type = 'text';
                                icon.classList.remove('fa-eye');
                                icon.classList.add('fa-eye-slash');
                                toggleButton.title = 'Ocultar contraseña';
                            } else {
                                passwordInput.type = 'password';
                                icon.classList.remove('fa-eye-slash');
                                icon.classList.add('fa-eye');
                                toggleButton.title = 'Mostrar contraseña';
                            }
                        }


                        document.addEventListener('DOMContentLoaded', function () {
                            const codCargo = document.getElementById('cod_cargo');

                            // Mapeo de cargos a categorías para referencia
                            window.mapaCargosCategorias = {
                                '2': {
                                    id: 5,
                                    nombre: 'Operario'
                                }, // Cargo Operario -> Categoría Operario (id 5)
                                '5': {
                                    id: 1,
                                    nombre: 'Líder'
                                } // Cargo Líder -> Categoría Líder (id 1)
                                // Agregar más mapeos según necesites
                            };
                        });

                        // Función para obtener información de categoría por cargo
                        function obtenerInfoCategoriaPorCargo(codCargo) {
                            const mapa = window.mapaCargosCategorias || {};
                            return mapa[codCargo] || null;
                        }

                        // Modificar la función existente
                        function toggleFechaFinContrato() {
                            const tipoContrato = document.getElementById('cod_tipo_contrato').value;
                            const grupoFechaFin = document.getElementById('grupo_fecha_fin_contrato');
                            const inputFechaFin = document.getElementById('fin_contrato');

                            if (tipoContrato == '1') { // Contrato temporal
                                grupoFechaFin.style.display = 'block';
                                inputFechaFin.disabled = false;
                                inputFechaFin.required = true;
                            } else if (tipoContrato == '2') { // Contrato indefinido - NUEVO
                                grupoFechaFin.style.display = 'block';
                                inputFechaFin.disabled = true;
                                inputFechaFin.required = false;
                                inputFechaFin.value = ''; // Limpiar el valor visualmente
                                inputFechaFin.placeholder = 'No aplica para contratos indefinidos';
                            } else {
                                grupoFechaFin.style.display = 'block';
                                inputFechaFin.disabled = true;
                                inputFechaFin.required = false;
                            }
                        }

                        // Mostrar/ocultar campo de persona que recibe herramientas
                        document.getElementById('devolucion_herramientas').addEventListener('change', function () {
                            const grupoPersona = document.getElementById('grupoPersonaHerramientas');
                            grupoPersona.style.display = this.value == '1' ? 'block' : 'none';
                        });

                        // Cerrar modal al hacer clic fuera
                        document.getElementById('modalTerminacion').addEventListener('click', function (e) {
                            if (e.target === this) {
                                cerrarModalTerminacion();
                            }
                        });

                        // Ejecutar cuando el documento esté cargado
                        document.addEventListener('DOMContentLoaded', function () {
                            ocultarMensajesAutomaticamente();

                            const toggleButton = document.getElementById('toggleClave');
                            if (toggleButton) {
                                toggleButton.addEventListener('click', togglePasswordVisibility);
                            }

                            // Actualizar categoría al cargar la página si ya hay un cargo seleccionado
                            const codCargo = document.getElementById('cod_cargo');
                            if (codCargo) {
                                actualizarCategoria();
                            }

                            //const selectTipoContrato = document.getElementById('cod_tipo_contrato');
                            //if (selectTipoContrato) {
                            //    toggleFechaFinContrato();
                            //    selectTipoContrato.addEventListener('change', toggleFechaFinContrato);
                            //}

                            const selectTipoContrato = document.getElementById('cod_tipo_contrato');
                            if (selectTipoContrato) {
                                toggleFechaFinContrato(); // Llamar al cargar
                                selectTipoContrato.addEventListener('change', toggleFechaFinContrato); // Ya existe

                                // AGREGAR ESTE CÓDIGO NUEVO:
                                // Detectar cuando cambia a tipo 2 (indefinido) y limpiar fecha fin
                                selectTipoContrato.addEventListener('change', function () {
                                    if (this.value == '2') {
                                        const inputFechaFin = document.getElementById('fin_contrato');
                                        inputFechaFin.value = ''; // Limpiar visualmente

                                        // Mostrar mensaje informativo (opcional)
                                        console.log('Tipo de contrato cambiado a Indefinido. Fecha fin será eliminada al guardar.');
                                    }
                                });
                            }

                            // También permitir cerrar mensajes haciendo clic en ellos
                            document.querySelectorAll('.alert').forEach(mensaje => {
                                mensaje.style.cursor = 'pointer';
                                mensaje.addEventListener('click', function () {
                                    this.style.transition = 'opacity 0.5s ease';
                                    this.style.opacity = '0';
                                    setTimeout(() => {
                                        this.remove();
                                    }, 500);
                                });
                            });
                        });

                        // Script para formatear automáticamente la cédula con guiones
                        document.addEventListener('DOMContentLoaded', function () {
                            const cedulaInput = document.getElementById('cedula');

                            if (cedulaInput) {
                                cedulaInput.addEventListener('input', function () {
                                    // Obtener valor sin guiones y mantener cualquier letra al final
                                    let value = this.value.replace(/-/g, '');

                                    // Guardar la posición del cursor
                                    const startPos = this.selectionStart;

                                    // Separar números y letra final si existe
                                    let numbers = value.replace(/[^0-9]/g, '');
                                    let letter = '';

                                    // Verificar si hay una letra al final
                                    if (value.length > 0 && /[A-Za-z]$/.test(value)) {
                                        letter = value.slice(-1);
                                        numbers = numbers.slice(0, numbers.length);
                                    }

                                    // Limitar a 13 números como máximo
                                    if (numbers.length > 13) {
                                        numbers = numbers.substring(0, 13);
                                    }

                                    // Aplicar el formato con guiones
                                    let formattedValue = numbers;
                                    if (numbers.length > 9) {
                                        formattedValue = numbers.substring(0, 3) + '-' + numbers.substring(3, 9) + '-' + numbers.substring(9);
                                    } else if (numbers.length > 3) {
                                        formattedValue = numbers.substring(0, 3) + '-' + numbers.substring(3);
                                    }

                                    // Agregar la letra al final si existe
                                    if (letter) {
                                        formattedValue += letter;
                                    }

                                    // Actualizar el valor
                                    this.value = formattedValue;

                                    // Ajustar la posición del cursor
                                    let adjustedPos = startPos;

                                    // Si agregamos guiones antes de la posición actual, ajustar
                                    if (startPos >= 3 && numbers.length >= 3) adjustedPos++;
                                    if (startPos >= 9 && numbers.length >= 9) adjustedPos++;

                                    // Asegurarse de que no exceda la longitud
                                    if (adjustedPos > formattedValue.length) {
                                        adjustedPos = formattedValue.length;
                                    }

                                    this.setSelectionRange(adjustedPos, adjustedPos);
                                });

                                // También formatear el valor inicial si existe
                                if (cedulaInput.value) {
                                    // Disparar el evento input para formatear el valor existente
                                    cedulaInput.dispatchEvent(new Event('input'));
                                }
                            }
                        });

                        // Funciones para el modal de cuentas bancarias
                        function abrirModalCuenta() {
                            document.getElementById('modalCuenta').style.display = 'block';
                            document.getElementById('tituloModalCuenta').textContent = 'Agregar Cuenta Bancaria';
                            document.getElementById('accionCuenta').value = 'agregar';
                            document.getElementById('idCuenta').value = '';
                            document.getElementById('formCuenta').reset();
                        }

                        function editarCuenta(idCuenta) {
                            // Hacer una solicitud AJAX para obtener los datos de la cuenta
                            fetch(`ajax/obtener_cuenta.php?id=${idCuenta}`)
                                .then(response => response.json())
                                .then(cuenta => {
                                    document.getElementById('modalCuenta').style.display = 'block';
                                    document.getElementById('tituloModalCuenta').textContent = 'Editar Cuenta Bancaria';
                                    document.getElementById('accionCuenta').value = 'editar';
                                    document.getElementById('idCuenta').value = idCuenta;
                                    document.getElementById('numero_cuenta_modal').value = cuenta.numero_cuenta || '';
                                    document.getElementById('titular_modal').value = cuenta.titular || '';
                                    document.getElementById('banco_modal').value = cuenta.banco || '';
                                    document.getElementById('moneda_modal').value = cuenta.moneda || '';
                                    document.getElementById('desde_modal').value = cuenta.desde || '';
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    alert('Error al cargar los datos de la cuenta');
                                });
                        }

                        function cerrarModalCuenta() {
                            document.getElementById('modalCuenta').style.display = 'none';
                        }

                        // Funciones para el modal de contactos de emergencia
                        function abrirModalContacto() {
                            document.getElementById('modalContacto').style.display = 'block';
                            document.getElementById('tituloModalContacto').textContent = 'Agregar Contacto de Emergencia';
                            document.getElementById('accionContacto').value = 'agregar';
                            document.getElementById('idContacto').value = '';
                            document.getElementById('formContacto').reset();
                        }

                        function editarContacto(idContacto) {
                            // Hacer una solicitud AJAX para obtener los datos del contacto
                            fetch(`ajax/obtener_contacto.php?id=${idContacto}`)
                                .then(response => response.json())
                                .then(contacto => {
                                    document.getElementById('modalContacto').style.display = 'block';
                                    document.getElementById('tituloModalContacto').textContent = 'Editar Contacto de Emergencia';
                                    document.getElementById('accionContacto').value = 'editar';
                                    document.getElementById('idContacto').value = idContacto;
                                    document.getElementById('nombre_contacto_modal').value = contacto.nombre_contacto || '';
                                    document.getElementById('parentesco_modal').value = contacto.parentesco || '';
                                    document.getElementById('telefono_movil_modal').value = contacto.telefono_movil || '';
                                    document.getElementById('telefono_casa_modal').value = contacto.telefono_casa || '';
                                    document.getElementById('telefono_trabajo_modal').value = contacto.telefono_trabajo || '';
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    alert('Error al cargar los datos del contacto');
                                });
                        }

                        function cerrarModalContacto() {
                            document.getElementById('modalContacto').style.display = 'none';
                        }

                        // Funciones para el modal de salarios
                        function abrirModalSalario() {
                            document.getElementById('modalSalario').style.display = 'block';
                            document.getElementById('tituloModalSalario').textContent = 'Agregar Salario';
                            document.getElementById('accionSalario').value = 'agregar';
                            document.getElementById('idSalario').value = '';
                            document.getElementById('formSalario').reset();

                            // Establecer fecha de hoy como valor por defecto para "Desde"
                            document.getElementById('inicio_modal').valueAsDate = new Date();
                        }

                        function editarSalario(idSalario) {
                            // Hacer una solicitud AJAX para obtener los datos del salario
                            fetch(`ajax/obtener_salario.php?id=${idSalario}`)
                                .then(response => response.json())
                                .then(salario => {
                                    document.getElementById('modalSalario').style.display = 'block';
                                    document.getElementById('tituloModalSalario').textContent = 'Editar Salario';
                                    document.getElementById('accionSalario').value = 'editar';
                                    document.getElementById('idSalario').value = idSalario;
                                    document.getElementById('monto_modal').value = salario.monto || '';
                                    document.getElementById('inicio_modal').value = salario.inicio || '';
                                    document.getElementById('fin_modal').value = salario.fin || '';
                                    document.getElementById('frecuencia_pago_modal').value = salario.frecuencia_pago || '';
                                    document.getElementById('observaciones_modal').value = salario.observaciones || '';
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    alert('Error al cargar los datos del salario');
                                });
                        }

                        function cerrarModalSalario() {
                            document.getElementById('modalSalario').style.display = 'none';
                        }

                        // Cerrar modal de salario al hacer clic fuera del contenido
                        document.getElementById('modalSalario').addEventListener('click', function (e) {
                            if (e.target === this) {
                                cerrarModalSalario();
                            }
                        });

                        // Cerrar modales al hacer clic fuera del contenido
                        document.getElementById('modalCuenta').addEventListener('click', function (e) {
                            if (e.target === this) {
                                cerrarModalCuenta();
                            }
                        });

                        document.getElementById('modalContacto').addEventListener('click', function (e) {
                            if (e.target === this) {
                                cerrarModalContacto();
                            }
                        });

                        // Configuración de tipos de documentos por pestaña (Cargados desde la Base de Datos)
                        <?php
                        // Obtener todas las pestañas que tienen documentos configurados dinámicamente
                        try {
                            $stmtPestanas = $conn->query("SELECT DISTINCT pestaña FROM contratos_tiposDocumentos WHERE activo = 1");
                            $pestañasJS = $stmtPestanas->fetchAll(PDO::FETCH_COLUMN);

                            // Asegurar que las pestañas básicas siempre estén si por alguna razón no tienen tipos configurados aún
                            $pestañasBasicas = ['datos-personales', 'inss', 'contrato', 'contactos-emergencia', 'salario', 'movimientos', 'categoria', 'adendums', 'expediente-digital'];
                            $pestañasJS = array_unique(array_merge($pestañasJS, $pestañasBasicas));
                        } catch (Exception $e) {
                            $pestañasJS = ['datos-personales', 'inss', 'contrato', 'contactos-emergencia', 'salario', 'movimientos', 'categoria', 'adendums', 'expediente-digital'];
                        }

                        $tiposDocumentosPHP = [];
                        foreach ($pestañasJS as $p) {
                            $tipos = obtenerTiposDocumentosPorPestaña($p);
                            if (!empty($tipos['obligatorios']) || !empty($tipos['opcionales'])) {
                                $tiposDocumentosPHP[$p] = [
                                    'obligatorios' => [],
                                    'opcionales' => [],
                                    'vencimientos' => []
                                ];

                                // Mapear vencimientos por ID
                                foreach ($tipos['vencimientos'] as $cl => $tieneVenc) {
                                    $idReal = $tipos['ids'][$cl];
                                    $tiposDocumentosPHP[$p]['vencimientos'][$idReal] = $tieneVenc;
                                }

                                foreach ($tipos['obligatorios'] as $clave => $nombre) {
                                    $tiposDocumentosPHP[$p]['obligatorios'][] = [
                                        'valor' => $tipos['ids'][$clave], // Usar el ID como valor para el select
                                        'texto' => $nombre,
                                        'clave' => $clave
                                    ];
                                }

                                foreach ($tipos['opcionales'] as $clave => $nombre) {
                                    $tiposDocumentosPHP[$p]['opcionales'][] = [
                                        'valor' => $tipos['ids'][$clave], // Usar el ID como valor para el select
                                        'texto' => $nombre,
                                        'clave' => $clave
                                    ];
                                }
                            }
                        }
                        ?>
                        const tiposDocumentos = <?= json_encode($tiposDocumentosPHP) ?>;


                        // Función para abrir el modal de adjuntos con los tipos de documentos
                        function abrirModalAdjunto(pestaña, codAdendum = null, idTipoDocumento = null) {
                            // Verificar si requiere contrato y si existe
                            const pestañasRequierenContrato = ['contrato', 'adendums', 'inss', 'salario', 'movimientos', 'categoria', 'expediente-digital'];
                            const tieneContrato = <?= tieneContratoActivo($codOperario) ? 'true' : 'false' ?>;

                            if (pestañasRequierenContrato.includes(pestaña) && !tieneContrato) {
                                alert('No se puede subir archivos en esta pestaña porque no hay un contrato activo. Complete la información del contrato primero.');
                                return;
                            }

                            // VALIDACIÓN CORREGIDA PARA ADENDUMS: Verificar que exista al menos un adendum
                            if (pestaña === 'adendums') {
                                const tieneAdendums = <?= count($adendumsColaborador) > 0 ? 'true' : 'false' ?>;
                                if (!tieneAdendums) {
                                    alert('No se puede subir archivos en la pestaña Adendums porque no hay adendums registrados. Debe crear al menos un adendum primero.');
                                    return;
                                }
                            }

                            // Si es pestaña de adendums y tenemos código de adendum, guardarlo
                            if (pestaña === 'adendums' && codAdendum) {
                                document.getElementById('codAdendumAsociado').value = codAdendum;
                            } else if (pestaña === 'adendums') {
                                // Si no se proporciona código, intentar obtener el último adendum activo
                                obtenerUltimoAdendumActivoId().then(id => {
                                    if (id) document.getElementById('codAdendumAsociado').value = id;
                                });
                            }

                            document.getElementById('modalAdjunto').style.display = 'block';
                            document.getElementById('pestañaAdjunto').value = pestaña;
                            document.getElementById('formAdjunto').reset();

                            // Limpiar datos de adjuntos unificados
                            detenerCamara();
                            adjuntosSesion = [];
                            actualizarListaAdjuntosUI();
                            document.getElementById('contenedorVideo').style.display = 'none';
                            document.getElementById('btnToggleCamara').textContent = 'Iniciar Cámara';

                            // Mostrar zona de adjuntos para todas las pestañas
                            document.getElementById('zonaAdjuntos').style.display = 'block';

                            // Limpiar y llenar el select de tipos de documento
                            const selectTipo = document.getElementById('tipo_documento_adjunto');
                            selectTipo.innerHTML = '<option value="">Seleccionar tipo de documento...</option>';
                            selectTipo.disabled = false; // Habilitar por defecto

                            const documentosPestaña = tiposDocumentos[pestaña] || {
                                obligatorios: [],
                                opcionales: []
                            };

                            // Función auxiliar para agregar opciones
                            const agregarOpciones = (lista, label) => {
                                if (lista.length > 0) {
                                    const optGroup = document.createElement('optgroup');
                                    optGroup.label = label;
                                    lista.forEach(doc => {
                                        const option = document.createElement('option');
                                        option.value = doc.valor;
                                        option.textContent = doc.texto;
                                        option.setAttribute('data-obligatorio', label === 'Documentos Obligatorios' ? '1' : '0');
                                        option.setAttribute('data-vencimiento', documentosPestaña.vencimientos[doc.valor] || '0');
                                        optGroup.appendChild(option);
                                    });
                                    selectTipo.appendChild(optGroup);
                                }
                            };

                            agregarOpciones(documentosPestaña.obligatorios, 'Documentos Obligatorios');
                            agregarOpciones(documentosPestaña.opcionales, 'Documentos Opcionales');

                            // Si se proporciona un ID de tipo, seleccionarlo y bloquear el select
                            if (idTipoDocumento) {
                                selectTipo.value = idTipoDocumento;
                                selectTipo.disabled = true;
                                // Disparar manualmente la lógica de UI (vencimiento, descripción, etc.)
                                actualizarDescripcionPorTipo();

                                // Crear un input hidden temporal para enviar el valor si el select está disabled
                                let hiddenInput = document.getElementById('hidden_id_tipo_documento');
                                if (!hiddenInput) {
                                    hiddenInput = document.createElement('input');
                                    hiddenInput.type = 'hidden';
                                    hiddenInput.id = 'hidden_id_tipo_documento';
                                    hiddenInput.name = 'tipo_documento_adjunto_hidden';
                                    document.getElementById('formAdjunto').appendChild(hiddenInput);
                                }
                                hiddenInput.value = idTipoDocumento;
                            } else {
                                const hiddenInput = document.getElementById('hidden_id_tipo_documento');
                                if (hiddenInput) hiddenInput.value = '';
                            }

                            // Ocultar/mostrar sección de obligatorios
                            document.getElementById('infoDocumentoObligatorio').style.display = 'none';
                        }

                        // Función mejorada para obtener el último adendum activo via AJAX
                        function obtenerUltimoAdendumActivoId() {
                            return new Promise((resolve, reject) => {
                                fetch(`ajax/obtener_ultimo_adendum.php?cod_operario=<?= $codOperario ?>`)
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.exito && data.adendum) {
                                            resolve(data.adendum.id);
                                        } else {
                                            resolve(null);
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                        resolve(null);
                                    });
                            });
                        }

                        // Función para verificar si puede crear adendum
                        function verificarPuedeCrearAdendum() {
                            const tieneContrato = <?= tieneContratoActivo($codOperario) ? 'true' : 'false' ?>;

                            if (!tieneContrato) {
                                return {
                                    puede: false,
                                    motivo: 'No hay contrato activo'
                                };
                            }

                            return {
                                puede: true,
                                motivo: ''
                            };
                        }

                        // Actualizar estado del formulario de adendum al cargar la página
                        document.addEventListener('DOMContentLoaded', function () {
                            const estado = verificarPuedeCrearAdendum();
                            const formAdendum = document.querySelector('form[action*="pestaña=adendums"]');
                            const btnSubmit = formAdendum ? formAdendum.querySelector('button[type="submit"]') : null;

                            if (btnSubmit && !estado.puede) {
                                btnSubmit.disabled = true;
                                btnSubmit.title = 'No puede crear adendum: ' + estado.motivo;
                                btnSubmit.innerHTML = '<i class="fas fa-ban"></i> ' + estado.motivo;
                            }
                        });

                        // Función para actualizar la descripción según el tipo seleccionado
                        function actualizarDescripcionPorTipo() {
                            const selectTipo = document.getElementById('tipo_documento_adjunto');
                            const descripcionInput = document.getElementById('descripcion_adjunto');
                            const infoObligatorio = document.getElementById('infoDocumentoObligatorio');
                            const textoObligatorio = document.getElementById('textoObligatorio');
                            const ayudaTipo = document.getElementById('ayudaTipoDocumento');

                            const valorSeleccionado = selectTipo.value;
                            const esObligatorio = selectTipo.options[selectTipo.selectedIndex]?.getAttribute('data-obligatorio') === '1';
                            const tieneVencimiento = selectTipo.options[selectTipo.selectedIndex]?.getAttribute('data-vencimiento') === '1';
                            const grupoVencimiento = document.getElementById('grupo_fecha_vencimiento');
                            const inputVencimiento = document.getElementById('fecha_vencimiento_adjunto');

                            if (valorSeleccionado) {
                                // Mostrar/Ocultar campo de vencimiento
                                if (tieneVencimiento) {
                                    grupoVencimiento.style.display = 'block';
                                    inputVencimiento.required = true;
                                } else {
                                    grupoVencimiento.style.display = 'none';
                                    inputVencimiento.required = false;
                                    inputVencimiento.value = '';
                                }

                                if (esObligatorio) {
                                    infoObligatorio.style.display = 'block';
                                    textoObligatorio.textContent = 'Este documento es requerido para completar la información del colaborador.';
                                    ayudaTipo.style.display = 'none';
                                } else {
                                    infoObligatorio.style.display = 'none';
                                    ayudaTipo.style.display = 'block';
                                    ayudaTipo.textContent = 'Documento opcional - puede subir múltiples archivos';
                                    ayudaTipo.style.color = '#6c757d';
                                }

                                // Auto-completar descripción para tipos específicos
                                if (valorSeleccionado !== 'otro') {
                                    descripcionInput.value = selectTipo.options[selectTipo.selectedIndex].textContent;
                                } else {
                                    descripcionInput.value = '';
                                }
                            } else {
                                infoObligatorio.style.display = 'none';
                                ayudaTipo.style.display = 'none';
                                grupoVencimiento.style.display = 'none';
                                inputVencimiento.required = false;
                                inputVencimiento.value = '';
                                descripcionInput.value = '';
                            }
                        }

                        // --- LÓGICA DE CÁMARA PARA ADJUNTOS ---
                        let streamCamara = null;
                        let trackCamara = null;
                        let flashActivo = false;

                        let dispositivosVideo = [];
                        let indiceCamaraActual = 0;

                        async function obtenerDispositivosVideo() {
                            try {
                                const devices = await navigator.mediaDevices.enumerateDevices();
                                dispositivosVideo = devices.filter(device => device.kind === 'videoinput');
                                console.log("Cámaras encontradas:", dispositivosVideo);

                                // Mostrar botón de cambio si hay más de una cámara
                                const btnCambiar = document.getElementById('btnCambiarCamara');
                                if (dispositivosVideo.length > 1) {
                                    btnCambiar.style.display = 'inline-block';
                                } else {
                                    btnCambiar.style.display = 'none';
                                }
                            } catch (err) {
                                console.error("Error al enumerar cámaras:", err);
                            }
                        }

                        async function toggleCamara(deviceId = null) {
                            const video = document.getElementById('videoCaptura');
                            const contenedor = document.getElementById('contenedorVideo');
                            const btn = document.getElementById('btnToggleCamara');

                            if (streamCamara && !deviceId) {
                                detenerCamara();
                            } else {
                                if (streamCamara) detenerCamara(); // Detener actual si estamos cambiando

                                try {
                                    // Si no se pasó deviceId, intentar obtener la lista y buscar la trasera
                                    if (!deviceId) {
                                        await obtenerDispositivosVideo();
                                        // Preferir cámara con "back" o "rear" en el label
                                        const trasera = dispositivosVideo.find(d =>
                                            d.label.toLowerCase().includes('back') ||
                                            d.label.toLowerCase().includes('rear') ||
                                            d.label.toLowerCase().includes('trasera')
                                        );
                                        if (trasera) {
                                            deviceId = trasera.deviceId;
                                            indiceCamaraActual = dispositivosVideo.indexOf(trasera);
                                        }
                                    }

                                    const constraints = {
                                        video: deviceId ? {
                                            deviceId: {
                                                exact: deviceId
                                            }
                                        } : {
                                            facingMode: {
                                                ideal: "environment"
                                            }
                                        },
                                        audio: false
                                    };

                                    const stream = await navigator.mediaDevices.getUserMedia(constraints);
                                    streamCamara = stream;
                                    trackCamara = stream.getVideoTracks()[0];
                                    video.srcObject = stream;

                                    // Actualizar lista de dispositivos (ahora con labels porque ya hay permiso)
                                    await obtenerDispositivosVideo();

                                    video.onloadedmetadata = () => {
                                        video.play();
                                        contenedor.style.display = 'block';
                                        btn.textContent = 'Detener Cámara';
                                        btn.style.backgroundColor = '#dc3545';
                                        detectarCapacidadesFlash();
                                    };
                                } catch (err) {
                                    console.error("Error al acceder a la cámara:", err);
                                    if (deviceId && dispositivosVideo.length > 1) {
                                        console.log("Reintentando con constraints genéricos...");
                                        toggleCamara(); // Reintentar sin id específico
                                    } else {
                                        alert("No se pudo acceder a la cámara. Verifique los permisos.\n\nError: " + err.name);
                                    }
                                }
                            }
                        }

                        async function cambiarCamara() {
                            if (dispositivosVideo.length < 2) return;

                            indiceCamaraActual = (indiceCamaraActual + 1) % dispositivosVideo.length;
                            const proximoDispositivo = dispositivosVideo[indiceCamaraActual];

                            console.log("Cambiando a cámara:", proximoDispositivo.label);
                            await toggleCamara(proximoDispositivo.deviceId);
                        }

                        async function detectarCapacidadesFlash() {
                            if (!trackCamara) return;

                            const btnFlash = document.getElementById('btnFlash');
                            btnFlash.style.display = 'none'; // Ocultar por defecto

                            const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);

                            // Pequeño retardo para dar tiempo a que el track se estabilice en algunos navegadores
                            await new Promise(r => setTimeout(r, 300));

                            let hasTorch = false;

                            // 1. Intento estándar con getCapabilities
                            if (typeof trackCamara.getCapabilities === 'function') {
                                const cap = trackCamara.getCapabilities();
                                if (cap.torch) hasTorch = true;
                            }

                            // 2. Si no reporta torch pero es móvil y cámara trasera, mostrar de todos modos (iOS fallback)
                            if (!hasTorch && isMobile) {
                                const settings = trackCamara.getSettings ? trackCamara.getSettings() : {};
                                if (settings.facingMode === 'environment') {
                                    hasTorch = true;
                                }
                            }

                            if (hasTorch) {
                                btnFlash.style.display = 'flex';
                            } else {
                                console.log("Cámara actual no reporta soporte para Flash/Torch.");
                                // Si tenemos más cámaras y esta no tiene flash, podríamos sugerir cambiar
                                if (dispositivosVideo.length > 1) {
                                    console.log("Sugerencia: Intente cambiar de cámara para buscar soporte de flash.");
                                }
                            }
                        }

                        async function toggleFlash() {
                            if (!trackCamara) {
                                alert("Inicie la cámara primero para usar el flash.");
                                return;
                            }

                            const btnFlash = document.getElementById('btnFlash');

                            // Intentar activar/desactivar
                            const nuevoEstado = !flashActivo;

                            try {
                                // Verificar si applyConstraints existe
                                if (typeof trackCamara.applyConstraints !== 'function') {
                                    throw new Error("Su navegador no permite controlar el flash de la cámara.");
                                }

                                // Aplicar restricción
                                await trackCamara.applyConstraints({
                                    advanced: [{
                                        torch: nuevoEstado
                                    }]
                                });

                                flashActivo = nuevoEstado;

                                // Actualizar UI
                                if (flashActivo) {
                                    btnFlash.style.color = '#ffc107';
                                    btnFlash.style.backgroundColor = 'rgba(255, 255, 255, 0.2)';
                                } else {
                                    btnFlash.style.color = 'white';
                                    btnFlash.style.backgroundColor = 'rgba(0,0,0,0.5)';
                                }

                            } catch (err) {
                                console.error("Error al controlar el flash:", err);

                                let msg = "No se pudo cambiar el estado del flash.";

                                if (err.name === 'OverconstrainedError' || err.name === 'NotSupportedError') {
                                    msg = "Esta cámara no permite el uso del flash en este momento o está siendo bloqueada por otra página.";
                                } else if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
                                    msg = "El flash requiere una conexión segura (HTTPS).";
                                }

                                // Información de diagnóstico detallada
                                let diagnostic = `\n\nInfo: ${err.name}`;
                                try {
                                    const cap = trackCamara.getCapabilities ? trackCamara.getCapabilities() : {};
                                    const settings = trackCamara.getSettings ? trackCamara.getSettings() : {};
                                    diagnostic += `\nTorch compatible: ${!!cap.torch}`;
                                    diagnostic += `\nID: ${settings.deviceId ? settings.deviceId.substring(0, 8) + '...' : 'N/A'}`;
                                    const currentDevice = dispositivosVideo.find(d => d.deviceId === settings.deviceId);
                                    diagnostic += `\nEtiqueta: ${currentDevice ? currentDevice.label : 'N/A'}`;
                                } catch (e) { }

                                alert(msg + diagnostic);
                                flashActivo = false;

                                // Si falla catastróficamente, ocultar el botón
                                if (err.name === 'OverconstrainedError') {
                                    btnFlash.style.display = 'none';
                                }
                            }
                        }

                        function detenerCamara() {
                            const video = document.getElementById('videoCaptura');
                            const contenedor = document.getElementById('contenedorVideo');
                            const btn = document.getElementById('btnToggleCamara');

                            if (streamCamara) {
                                streamCamara.getTracks().forEach(track => track.stop());
                                streamCamara = null;
                                trackCamara = null;
                                flashActivo = false;
                                video.srcObject = null;

                                const btnFlash = document.getElementById('btnFlash');
                                btnFlash.style.display = 'none';
                                btnFlash.style.color = 'white';
                                btnFlash.style.backgroundColor = 'rgba(0,0,0,0.5)';
                            }
                            if (contenedor) contenedor.style.display = 'none';
                            if (btn) {
                                btn.textContent = 'Iniciar Cámara';
                                btn.style.backgroundColor = '#51B8AC';
                            }
                        }

                        // --- LÓGICA DE COLA UNIFICADA DE ADJUNTOS ---
                        let adjuntosSesion = [];

                        function manejarSeleccionPDF(input) {
                            if (input.files && input.files.length > 0) {
                                Array.from(input.files).forEach(file => {
                                    const reader = new FileReader();
                                    reader.onload = function (e) {
                                        adjuntosSesion.push({
                                            tipo: 'pdf',
                                            nombre: file.name,
                                            tamaño: file.size,
                                            data: e.target.result
                                        });
                                        actualizarListaAdjuntosUI();
                                    };
                                    reader.readAsDataURL(file);
                                });
                                input.value = '';
                            }
                        }

                        function manejarSeleccionFotos(input) {
                            if (input.files && input.files.length > 0) {
                                Array.from(input.files).forEach(file => {
                                    const reader = new FileReader();
                                    reader.onload = function (e) {
                                        adjuntosSesion.push({
                                            tipo: 'imagen',
                                            nombre: file.name,
                                            tamaño: file.size,
                                            data: e.target.result
                                        });
                                        actualizarListaAdjuntosUI();
                                    };
                                    reader.readAsDataURL(file);
                                });
                                input.value = '';
                            }
                        }

                        function capturarFoto() {
                            const video = document.getElementById('videoCaptura');
                            const canvas = document.getElementById('canvasAuxiliar');
                            const context = canvas.getContext('2d');

                            canvas.width = video.videoWidth;
                            canvas.height = video.videoHeight;
                            context.drawImage(video, 0, 0, canvas.width, canvas.height);

                            const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
                            const index = adjuntosSesion.filter(a => a.tipo === 'captura').length + 1;

                            adjuntosSesion.push({
                                tipo: 'captura',
                                nombre: `captura_${index}.jpg`,
                                tamaño: Math.round((dataUrl.length * 3) / 4), // Estimación tamaño base64
                                data: dataUrl
                            });

                            actualizarListaAdjuntosUI();
                        }

                        function actualizarListaAdjuntosUI() {
                            const container = document.getElementById('listaAdjuntosUnificada');
                            const inputOculto = document.getElementById('adjuntosUnificadosInput');

                            container.innerHTML = '';
                            inputOculto.value = JSON.stringify(adjuntosSesion);

                            if (adjuntosSesion.length === 0) {
                                container.innerHTML = '<div style="text-align: center; color: #6c757d; font-size: 0.75rem; font-style: italic; padding: 10px;">No hay archivos seleccionados</div>';
                                return;
                            }

                            adjuntosSesion.forEach((adjunto, index) => {
                                const item = document.createElement('div');
                                item.style.display = 'flex';
                                item.style.alignItems = 'center';
                                item.style.justifyContent = 'space-between';
                                item.style.padding = '8px 12px';
                                item.style.background = 'white';
                                item.style.borderRadius = '8px';
                                item.style.border = '1px solid #e0e6e5';
                                item.style.boxShadow = '0 1px 3px rgba(0,0,0,0.05)';

                                let icono = 'fa-file-alt';
                                let color = '#6c757d';

                                if (adjunto.tipo === 'pdf') {
                                    icono = 'fa-file-pdf';
                                    color = '#0E544C';
                                } else if (adjunto.tipo === 'imagen' || adjunto.tipo === 'captura') {
                                    icono = 'fa-file-image';
                                    color = '#51B8AC';
                                }

                                item.innerHTML = `
                                    <div style="display: flex; align-items: center; gap: 10px; overflow: hidden; flex: 1;">
                                        <i class="fas ${icono}" style="color: ${color}; font-size: 1rem;"></i>
                                        <div style="overflow: hidden;">
                                            <div style="font-size: 0.75rem; font-weight: 700; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${adjunto.nombre}</div>
                                            <div style="font-size: 0.65rem; color: #666;">${(adjunto.tamaño / 1024).toFixed(1)} KB</div>
                                        </div>
                                    </div>
                                    <button type="button" onclick="eliminarAdjunto(${index})" style="background: #fff0f0; color: #dc3545; border: none; border-radius: 4px; width: 24px; height: 24px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                `;

                                // Hover effect for remove button
                                const btnX = item.querySelector('button');
                                btnX.onmouseover = () => {
                                    btnX.style.background = '#fde2e2';
                                };
                                btnX.onmouseout = () => {
                                    btnX.style.background = '#fff0f0';
                                };

                                container.appendChild(item);
                            });
                        }

                        function eliminarAdjunto(index) {
                            adjuntosSesion.splice(index, 1);
                            actualizarListaAdjuntosUI();
                        }

                        function cerrarModalAdjunto() {
                            detenerCamara();
                            document.getElementById('modalAdjunto').style.display = 'none';
                        }

                        // Validar envío del formulario unificado
                        document.getElementById('formAdjunto').addEventListener('submit', function (e) {
                            if (adjuntosSesion.length === 0) {
                                alert('Debe adjuntar al menos un archivo (PDF, Imagen o Captura).');
                                e.preventDefault();
                            }
                        });

                        // Función para actualizar los íconos de estado en las pestañas
                        // Función para actualizar los íconos de estado y porcentajes en las pestañas
                        function actualizarIconosEstadoPestanas() {
                            const pestañas = ['datos-personales', 'datos-contacto', 'inss', 'contrato', 'contactos-emergencia',
                                'salario', 'movimientos', 'categoria', 'adendums', 'expediente-digital', 'bitacora'
                            ];

                            const exentas = ['contactos-emergencia', 'adendums', 'movimientos', 'bitacora'];

                            pestañas.forEach(pestaña => {
                                fetch(`ajax/obtener_estado_documentos.php?cod_operario=<?= $codOperario ?>&pestaña=${pestaña}`)
                                    .then(response => response.json())
                                    .then(data => {
                                        const tabButton = document.querySelector(`.tab-button[href*="pestaña=${pestaña}"]`);
                                        if (tabButton) {
                                            // 1. ELIMINAR ícono y contenedores de estado antiguos
                                            tabButton.querySelectorAll('.estado-documentos, i.fas, i.far').forEach(el => el.remove());

                                            const esExenta = exentas.includes(pestaña);

                                            // 2. Manejar el porcentaje de cumplimiento (CÍRCULO)
                                            if (data.porcentaje !== undefined) {
                                                let percentLabel = tabButton.querySelector('.tab-percentage');
                                                if (!percentLabel) {
                                                    percentLabel = document.createElement('span');
                                                    percentLabel.className = 'tab-percentage';
                                                    tabButton.appendChild(percentLabel);
                                                }

                                                if (esExenta) {
                                                    percentLabel.style.display = 'none';
                                                } else {
                                                    percentLabel.textContent = `${data.porcentaje}%`;
                                                    percentLabel.style.display = 'inline-flex';

                                                    // Aplicar color de FONDO según semáforo
                                                    percentLabel.classList.remove('bg-red', 'bg-yellow', 'bg-green');
                                                    if (data.porcentaje < 50) percentLabel.classList.add('bg-red');
                                                    else if (data.porcentaje < 100) percentLabel.classList.add('bg-yellow');
                                                    else percentLabel.classList.add('bg-green');
                                                }

                                                // 3. Manejar la barra de progreso (SIEMPRE VERDE INSTITUCIONAL)
                                                let progressCont = tabButton.querySelector('.tab-progress-container');
                                                if (!progressCont) {
                                                    progressCont = document.createElement('div');
                                                    progressCont.className = 'tab-progress-container';
                                                    progressCont.innerHTML = '<div class="tab-progress-bar"></div>';
                                                    tabButton.appendChild(progressCont);
                                                }
                                                progressCont.style.display = 'block';

                                                const progressBar = progressCont.querySelector('.tab-progress-bar');

                                                // Si es exenta, forzar 100% verde
                                                if (esExenta) {
                                                    progressBar.style.width = `100%`;
                                                } else {
                                                    progressBar.style.width = `${data.porcentaje}%`;
                                                }

                                                // Color UNIFICADO
                                                progressBar.classList.remove('bg-red', 'bg-yellow', 'bg-green');
                                                progressBar.classList.add('bg-institucional');
                                            }
                                        }
                                    })
                                    .catch(error => console.error('Error al obtener estado para ' + pestaña + ':', error));
                            });
                        }


                        // Llamar a la función cuando se cargue la página
                        document.addEventListener('DOMContentLoaded', function () {
                            actualizarIconosEstadoPestanas();
                        });

                        // Cerrar modal al hacer clic fuera
                        //document.getElementById('modalAdjunto').addEventListener('click', function(e) {
                        //    if (e.target === this) {
                        //        cerrarModalAdjunto();
                        //    }
                        //});

                        // Funciones para el modal de Salario INSS
                        function abrirModalSalarioINSS() {
                            document.getElementById('modalSalarioINSS').style.display = 'block';
                            document.getElementById('formSalarioINSS').reset();

                            // Establecer fecha de hoy como valor por defecto
                            document.getElementById('inicio_inss_modal').valueAsDate = new Date();
                        }

                        function cerrarModalSalarioINSS() {
                            document.getElementById('modalSalarioINSS').style.display = 'none';

                            // Restablecer el formulario a modo agregar
                            const form = document.getElementById('formSalarioINSS');
                            form.querySelector('input[name="accion_inss"]').value = 'agregar';
                            const hiddenId = form.querySelector('input[name="id_salario_inss"]');
                            if (hiddenId) {
                                hiddenId.remove();
                            }
                            form.reset();
                        }

                        // Cerrar modal al hacer clic fuera
                        document.getElementById('modalSalarioINSS').addEventListener('click', function (e) {
                            if (e.target === this) {
                                cerrarModalSalarioINSS();
                            }
                        });

                        // Variable global para almacenar la imagen temporal
                        let imagenTemporal = null;

                        // Cuando el documento esté cargado
                        document.addEventListener('DOMContentLoaded', function () {
                            // Configurar el evento change del input file
                            const inputFoto = document.getElementById('inputFotoPerfil');
                            if (inputFoto) {
                                inputFoto.addEventListener('change', function (e) {
                                    if (this.files && this.files[0]) {
                                        imagenTemporal = this.files[0];
                                        const reader = new FileReader();

                                        reader.onload = function (e) {
                                            // Mostrar la previsualización
                                            document.getElementById('previewImage').src = e.target.result;
                                            document.getElementById('previewModal').classList.add('active');
                                            document.body.style.overflow = 'hidden'; // Evitar scroll
                                        }

                                        reader.readAsDataURL(this.files[0]);
                                    }
                                });
                            }
                        });

                        // Función para previsualizar la foto antes de subir
                        document.getElementById('inputFotoPerfil').addEventListener('change', function (e) {
                            if (this.files && this.files[0]) {
                                imagenTemporal = this.files[0]; // Guardar archivo temporalmente
                                const reader = new FileReader();
                                reader.onload = function (e) {
                                    mostrarPreview(e.target.result);
                                }
                                reader.readAsDataURL(this.files[0]);
                            }
                        });

                        // Función para confirmar la foto
                        function confirmarFoto() {
                            const previewModal = document.getElementById('previewModal');

                            // Cambiar a estado de carga
                            previewModal.querySelector('.preview-content').innerHTML = `
                <h3 class="preview-title">Subiendo foto</h3>
                <div class="loading-spinner"></div>
                <p>Por favor espera...</p>
            `;

                            // Crear un FormData y enviar el archivo
                            const formData = new FormData();
                            formData.append('foto_perfil', imagenTemporal);
                            formData.append('pestaña', 'datos-personales');

                            // Enviar con Fetch API
                            fetch('', {
                                method: 'POST',
                                body: formData
                            })
                                .then(response => {
                                    if (!response.ok) {
                                        throw new Error('Error en la respuesta del servidor');
                                    }
                                    return response.text();
                                })
                                .then(() => {
                                    // Mostrar animación de éxito
                                    previewModal.querySelector('.preview-content').innerHTML = `
                    <h3 class="preview-title">¡Éxito!</h3>
                    <div class="success-check">
                        <i class="fas fa-check"></i>
                    </div>
                    <p>Foto actualizada correctamente</p>
                    <p>La página se recargará automáticamente</p>
                `;

                                    // Recargar después de 2 segundos
                                    setTimeout(() => {
                                        window.location.reload();
                                    }, 2000);
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    // Mostrar mensaje de error
                                    previewModal.querySelector('.preview-content').innerHTML = `
                    <h3 class="preview-title">Error</h3>
                    <div class="error-icon">
                        <i class="fas fa-times"></i>
                    </div>
                    <p>Ocurrió un error al subir la foto</p>
                    <div class="preview-buttons">
                        <button class="btn-cancel" onclick="cancelarPreview()">Cerrar</button>
                    </div>
                `;
                                });
                        }

                        // Función para cancelar la previsualización
                        function cancelarPreview() {
                            const previewModal = document.getElementById('previewModal');
                            previewModal.classList.remove('active');
                            document.body.style.overflow = ''; // Restaurar scroll

                            // Limpiar el input de archivo
                            document.getElementById('inputFotoPerfil').value = '';
                            imagenTemporal = null;
                        }

                        // Cerrar modal al hacer clic fuera del contenido
                        document.getElementById('previewModal').addEventListener('click', function (e) {
                            if (e.target === this) {
                                cancelarPreview();
                            }
                        });

                        // Cerrar modal con la tecla Escape
                        document.addEventListener('keydown', function (e) {
                            if (e.key === 'Escape') {
                                cancelarPreview();
                            }
                        });

                        // Tooltip para la foto de perfil
                        document.querySelector('.foto-perfil').addEventListener('mouseenter', function () {
                            const tooltip = document.createElement('div');
                            tooltip.className = 'tooltip';
                            tooltip.textContent = 'Haz clic para cambiar la foto';
                            tooltip.style.position = 'absolute';
                            tooltip.style.bottom = '100%';
                            tooltip.style.left = '50%';
                            tooltip.style.transform = 'translateX(-50%)';
                            tooltip.style.background = '#333';
                            tooltip.style.color = 'white';
                            tooltip.style.padding = '5px 10px';
                            tooltip.style.borderRadius = '4px';
                            tooltip.style.fontSize = '12px';
                            tooltip.style.whiteSpace = 'nowrap';
                            tooltip.style.marginBottom = '5px';
                            tooltip.style.zIndex = '1000';

                            this.appendChild(tooltip);

                            setTimeout(() => {
                                if (this.contains(tooltip)) {
                                    this.removeChild(tooltip);
                                }
                            }, 2000);
                        });

                        document.querySelector('.foto-perfil').addEventListener('mouseleave', function () {
                            const tooltip = this.querySelector('.tooltip');
                            if (tooltip) {
                                this.removeChild(tooltip);
                            }
                        });

                        // Variables para controlar la validación
                        let ultimoCodigoValidado = '';
                        let codigoEsValido = true;

                        // Validar código de contrato único con mejor UX
                        function validarCodigoContrato(codigo) {
                            // Si está vacío o es el mismo que ya validamos, no hacer nada
                            if (!codigo || codigo === ultimoCodigoValidado) {
                                return;
                            }

                            // Si estamos editando un contrato existente, obtener su ID para excluirlo de la validación
                            const idContratoActual = '<?= $contratoActual ? $contratoActual["CodContrato"] : 0 ?>';

                            // Mostrar estado de carga
                            document.getElementById('codigo-contrato-error').style.display = 'none';
                            document.getElementById('codigo-contrato-success').style.display = 'none';

                            // Crear o mostrar indicador de carga
                            let loadingIndicator = document.getElementById('loading-indicator');
                            if (!loadingIndicator) {
                                loadingIndicator = document.createElement('div');
                                loadingIndicator.id = 'loading-indicator';
                                loadingIndicator.className = 'loading-indicator';
                                document.getElementById('codigo_manual_contrato').parentNode.appendChild(loadingIndicator);
                            }
                            loadingIndicator.style.display = 'inline-block';

                            fetch(`ajax/validar_codigo_contrato.php?codigo=${encodeURIComponent(codigo)}&excluir=${idContratoActual}`)
                                .then(response => response.json())
                                .then(data => {
                                    // Ocultar indicador de carga
                                    loadingIndicator.style.display = 'none';

                                    ultimoCodigoValidado = codigo;

                                    if (data.existe) {
                                        // Código ya existe
                                        document.getElementById('codigo-contrato-error').style.display = 'block';
                                        document.getElementById('codigo-contrato-success').style.display = 'none';
                                        codigoEsValido = false;

                                        // Resaltar el campo en rojo
                                        document.getElementById('codigo_manual_contrato').style.borderColor = '#dc3545';
                                        document.getElementById('codigo_manual_contrato').style.boxShadow = '0 0 0 0.2rem rgba(220, 53, 69, 0.25)';
                                    } else {
                                        // Código disponible
                                        document.getElementById('codigo-contrato-error').style.display = 'none';
                                        document.getElementById('codigo-contrato-success').style.display = 'block';
                                        codigoEsValido = true;

                                        // Quitar resaltado
                                        document.getElementById('codigo_manual_contrato').style.borderColor = '';
                                        document.getElementById('codigo_manual_contrato').style.boxShadow = '';
                                    }
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    loadingIndicator.style.display = 'none';
                                    codigoEsValido = true; // En caso de error, permitir enviar el formulario
                                });
                        }

                        // También validar cuando el usuario escribe (pero con debounce para no hacer muchas peticiones)
                        let timeoutId;
                        document.getElementById('codigo_manual_contrato').addEventListener('input', function (e) {
                            clearTimeout(timeoutId);
                            timeoutId = setTimeout(() => {
                                validarCodigoContrato(this.value);
                            }, 800); // Esperar 800ms después de que el usuario deje de escribir
                        });

                        // Validar antes de enviar el formulario
                        document.querySelector('form').addEventListener('submit', function (e) {
                            if (!codigoEsValido) {
                                e.preventDefault();
                                alert('No puede guardar el contrato con un código que ya existe. Por favor, use un código único.');
                                document.getElementById('codigo_manual_contrato').focus();
                            }
                        });

                        // Funciones para el modal de movimientos
                        function editarMovimiento(idMovimiento) {
                            // Hacer una solicitud AJAX para obtener los datos del movimiento
                            fetch(`ajax/obtener_movimiento.php?id=${idMovimiento}`)
                                .then(response => {
                                    if (!response.ok) {
                                        throw new Error('Error en la respuesta del servidor');
                                    }
                                    return response.json();
                                })
                                .then(movimiento => {
                                    document.getElementById('modalMovimiento').style.display = 'block';
                                    document.getElementById('idMovimiento').value = idMovimiento;
                                    document.getElementById('edit_cod_cargo').value = movimiento.CodNivelesCargos || '';
                                    document.getElementById('edit_sucursal').value = movimiento.Sucursal || '';

                                    // Formatear fecha (eliminar la parte de tiempo si existe)
                                    const fechaInicio = movimiento.Fecha ? movimiento.Fecha.split(' ')[0] : '';
                                    document.getElementById('edit_fecha_inicio').value = fechaInicio;

                                    const fechaFin = movimiento.Fin ? movimiento.Fin.split(' ')[0] : '';
                                    document.getElementById('edit_fecha_fin').value = fechaFin;

                                    document.getElementById('edit_tipo_contrato').value = movimiento.CodTipoContrato || '';
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    alert('Error al cargar los datos del movimiento');
                                });
                        }

                        function cerrarModalMovimiento() {
                            document.getElementById('modalMovimiento').style.display = 'none';
                        }

                        // Cerrar modal al hacer clic fuera
                        document.getElementById('modalMovimiento').addEventListener('click', function (e) {
                            if (e.target === this) {
                                cerrarModalMovimiento();
                            }
                        });

                        function editarSalarioINSS(idSalarioINSS) {
                            fetch(`ajax/obtener_salario_inss.php?id=${idSalarioINSS}`)
                                .then(response => {
                                    if (!response.ok) {
                                        throw new Error('Error en la respuesta del servidor');
                                    }
                                    return response.json();
                                })
                                .then(salario => {
                                    // Llenar el formulario modal con los datos
                                    document.getElementById('monto_salario_inss_modal').value = salario.monto_salario_inss || '';
                                    document.getElementById('inicio_inss_modal').value = salario.inicio || '';
                                    document.getElementById('observaciones_inss_modal').value = salario.observaciones_inss || '';

                                    // Mostrar el modal de edición
                                    document.getElementById('modalSalarioINSS').style.display = 'block';

                                    // Cambiar el formulario para modo edición
                                    const form = document.getElementById('formSalarioINSS');
                                    // Crear campo hidden para el ID si no existe
                                    if (!form.querySelector('input[name="id_salario_inss"]')) {
                                        const hiddenInput = document.createElement('input');
                                        hiddenInput.type = 'hidden';
                                        hiddenInput.name = 'id_salario_inss';
                                        hiddenInput.value = idSalarioINSS;
                                        form.appendChild(hiddenInput);
                                    } else {
                                        form.querySelector('input[name="id_salario_inss"]').value = idSalarioINSS;
                                    }

                                    // Cambiar la acción a editar
                                    const accionInput = form.querySelector('input[name="accion_inss"]');
                                    if (accionInput) {
                                        accionInput.value = 'editar';
                                    }
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    alert('Error al cargar los datos del salario INSS');
                                });
                        }

                        // Función para editar categoría
                        function editarCategoria(idCategoria) {
                            // Mostrar indicador de carga
                            const modal = document.getElementById('modalCategoria');
                            modal.querySelector('.modal-content').innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <div class="loading-spinner"></div>
                    <p>Cargando datos de la categoría...</p>
                </div>
            `;
                            modal.style.display = 'block';

                            // Hacer una solicitud AJAX para obtener los datos de la categoría
                            fetch(`ajax/obtener_categoria.php?id=${idCategoria}`)
                                .then(response => {
                                    if (!response.ok) {
                                        throw new Error('Error en la respuesta del servidor: ' + response.status);
                                    }
                                    return response.json();
                                })
                                .then(categoria => {
                                    // Restaurar el formulario
                                    modal.querySelector('.modal-content').innerHTML = `
                        <h3 style="color: #0E544C; margin-bottom: 20px;">Editar Categoría</h3>
                        <form method="POST" action="" id="formCategoria">
                            <input type="hidden" name="accion_categoria" value="editar">
                            <input type="hidden" name="id_categoria_edit" id="idCategoriaEdit" value="">
                            <input type="hidden" name="pestaña" value="categoria">
                            
                            <div class="form-group">
                                <label for="edit_id_categoria">Categoría *</label>
                                <select id="edit_id_categoria" name="id_categoria" class="form-control" required>
                                    <option value="">Seleccionar categoría...</option>
                                    <?php foreach ($todasCategorias as $categoria): ?>
                                        <option value="<?= $categoria['idCategoria'] ?>">
                                            <?= htmlspecialchars($categoria['NombreCategoria']) ?> 
                                            (Peso: <?= $categoria['Peso'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_fecha_inicio">Fecha de Inicio *</label>
                                <input type="date" id="edit_fecha_inicio" name="fecha_inicio" class="form-control" required>
                            </div>
                            
                            <div style="display:none;" class="form-group">
                                <label for="edit_fecha_fin">Fecha de Fin</label>
                                <input type="date" id="edit_fecha_fin" name="fecha_fin" class="form-control">
                                <small style="color: #6c757d;">Dejar vacío si es la categoría actual</small>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                                <button type="button" class="btn-submit" onclick="cerrarModalCategoria()" style="background-color: #6c757d;">Cancelar</button>
                                <button type="submit" class="btn-submit">Guardar Cambios</button>
                            </div>
                        </form>
                    `;

                                    // Llenar el formulario con los datos
                                    document.getElementById('idCategoriaEdit').value = idCategoria;
                                    document.getElementById('edit_id_categoria').value = categoria.idCategoria || '';
                                    document.getElementById('edit_fecha_inicio').value = categoria.FechaInicio || '';
                                    document.getElementById('edit_fecha_fin').value = categoria.FechaFin || '';

                                    // Reasignar el event listener al modal
                                    document.getElementById('modalCategoria').addEventListener('click', function (e) {
                                        if (e.target === this) {
                                            cerrarModalCategoria();
                                        }
                                    });
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    modal.querySelector('.modal-content').innerHTML = `
                        <h3 style="color: #dc3545; margin-bottom: 20px;">Error</h3>
                        <p>Error al cargar los datos de la categoría: ${error.message}</p>
                        <div style="text-align: center; margin-top: 20px;">
                            <button type="button" class="btn-submit" onclick="cerrarModalCategoria()">Cerrar</button>
                        </div>
                    `;
                                });
                        }

                        // Función para cerrar el modal de categoría
                        function cerrarModalCategoria() {
                            document.getElementById('modalCategoria').style.display = 'none';
                        }

                        // Cerrar modal al hacer clic fuera
                        document.getElementById('modalCategoria').addEventListener('click', function (e) {
                            if (e.target === this) {
                                cerrarModalCategoria();
                            }
                        });

                        // Función para actualizar campos según el tipo de adendum
                        function actualizarCamposAdendum() {
                            const tipoAdendum = document.getElementById('tipo_adendum').value;
                            const codCargo = document.getElementById('cod_cargo_adendum').value;

                            // Grupos de campos
                            const grupoCargo = document.getElementById('grupo_cargo');
                            const grupoSucursal = document.getElementById('grupo_sucursal');
                            const grupoCategoria = document.getElementById('grupo_categoria');
                            const grupoSalario = document.getElementById('grupo_salario');

                            // Campos individuales
                            const cargoInput = document.getElementById('cod_cargo_adendum');
                            const sucursalInput = document.getElementById('sucursal_adendum');
                            const categoriaInput = document.getElementById('id_categoria_adendum');
                            const salarioInput = document.getElementById('salario_adendum');

                            // Resetear requeridos
                            cargoInput.required = false;
                            sucursalInput.required = false;
                            categoriaInput.required = false;
                            salarioInput.required = false;

                            // MOSTRAR/OCULTAR CATEGORÍA SEGÚN CÓDIGO DE CARGO
                            if (codCargo === '2' || codCargo === '5') {
                                // Mostrar categoría solo para códigos 2 y 5
                                grupoCategoria.style.display = 'block';
                                categoriaInput.required = true;
                            } else {
                                grupoCategoria.style.display = 'none';
                                categoriaInput.required = false;
                            }

                            switch (tipoAdendum) {
                                case 'cargo':
                                    grupoCargo.style.display = 'block';
                                    grupoSucursal.style.display = 'block';
                                    // La categoría ya se maneja según el código de cargo
                                    grupoSalario.style.display = 'none';

                                    cargoInput.required = true;
                                    sucursalInput.required = true;
                                    break;

                                case 'salario':
                                    grupoCargo.style.display = 'none';
                                    grupoSucursal.style.display = 'none';
                                    grupoCategoria.style.display = 'none'; // Ocultar categoría en ajuste salarial
                                    grupoSalario.style.display = 'block';

                                    salarioInput.required = true;
                                    break;

                                case 'movimiento':
                                    grupoCargo.style.display = 'none';
                                    grupoSucursal.style.display = 'block';
                                    grupoCategoria.style.display = 'none';
                                    grupoSalario.style.display = 'none';

                                    sucursalInput.required = true;
                                    break;

                                case 'ambos':
                                    grupoCargo.style.display = 'block';
                                    grupoSucursal.style.display = 'block';
                                    // La categoría ya se maneja según el código de cargo
                                    grupoSalario.style.display = 'block';

                                    cargoInput.required = true;
                                    sucursalInput.required = true;
                                    salarioInput.required = true;
                                    break;

                                default:
                                    grupoCargo.style.display = 'none';
                                    grupoSucursal.style.display = 'none';
                                    grupoCategoria.style.display = 'none';
                                    grupoSalario.style.display = 'none';
                            }
                        }

                        // Función para editar adendum
                        function editarAdendum(idAdendum) {
                            // Hacer una solicitud AJAX para obtener los datos del adendum
                            fetch(`ajax/obtener_adendum.php?id=${idAdendum}`)
                                .then(response => {
                                    if (!response.ok) {
                                        throw new Error('Error en la respuesta del servidor');
                                    }
                                    return response.json();
                                })
                                .then(adendum => {
                                    // Abrir modal de edición (similar al de categorías)
                                    document.getElementById('modalAdendum').style.display = 'block';

                                    // Llenar el formulario con los datos
                                    document.getElementById('edit_id_adendum').value = adendum.CodAsignacionNivelesCargos;
                                    document.getElementById('edit_tipo_adendum').value = adendum.TipoAdendum || '';
                                    document.getElementById('edit_cod_cargo_adendum').value = adendum.CodNivelesCargos || '';
                                    document.getElementById('edit_sucursal_adendum').value = adendum.Sucursal || '';
                                    //document.getElementById('edit_id_categoria_adendum').value = adendum.idCategoria || '';
                                    document.getElementById('edit_salario_adendum').value = adendum.Salario || '';
                                    document.getElementById('edit_fecha_inicio_adendum').value = adendum.Fecha || '';
                                    document.getElementById('edit_fecha_fin_adendum').value = adendum.Fin || '';
                                    document.getElementById('edit_observaciones_adendum').value = adendum.Observaciones || '';

                                    // Actualizar campos según el tipo
                                    actualizarCamposEdicionAdendum();
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    alert('Error al cargar los datos del adendum');
                                });
                        }

                        // Modal para editar adendum (agregar al HTML)
                        function cerrarModalAdendum() {
                            document.getElementById('modalAdendum').style.display = 'none';
                        }

                        // Función para establecer fecha actual en todos los modales
                        function establecerFechasActuales() {
                            const fechaActual = new Date().toISOString().split('T')[0];

                            // Campos de fecha que deben tener fecha actual por defecto
                            const camposFecha = [
                                'desde_modal', 'inicio_modal', 'inicio_inss_modal',
                                'nuevo_fecha_inicio', 'fecha_inicio', 'fecha_inicio_adendum',
                                'edit_fecha_inicio', 'edit_fecha_inicio_adendum'
                            ];

                            camposFecha.forEach(id => {
                                const campo = document.getElementById(id);
                                if (campo && !campo.value) {
                                    campo.value = fechaActual;
                                }
                            });
                        }

                        // Ejecutar cuando se abra cualquier modal
                        document.addEventListener('DOMContentLoaded', function () {
                            // Observar cambios en los modales
                            const observer = new MutationObserver(function (mutations) {
                                mutations.forEach(function (mutation) {
                                    if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                                        const modal = mutation.target;
                                        if (modal.style.display === 'block') {
                                            establecerFechasActuales();
                                        }
                                    }
                                });
                            });

                            // Observar todos los modales
                            const modales = document.querySelectorAll('.modal-backdrop');
                            modales.forEach(modal => {
                                observer.observe(modal, {
                                    attributes: true
                                });
                            });
                        });

                        // Deshabilitar funcionalidades si no hay contrato activo
                        function verificarContratoActivo() {
                            const tieneContrato = <?= tieneContratoActivo($codOperario) ? 'true' : 'false' ?>;
                            const pestañasRequierenContrato = ['inss', 'adendums', 'salario', 'categoria', 'movimientos'];
                            const pestañaActual = '<?= $pestaña_activa ?>';

                            if (!tieneContrato && pestañasRequierenContrato.includes(pestañaActual)) {
                                // Deshabilitar botones de agregar
                                const botonesAgregar = document.querySelectorAll('button[onclick*="abrirModal"], .btn-submit');
                                botonesAgregar.forEach(boton => {
                                    if (!boton.onclick || !boton.onclick.toString().includes('Terminacion')) {
                                        boton.disabled = true;
                                        boton.title = 'Requiere contrato activo';
                                        boton.style.opacity = '0.6';
                                        boton.style.cursor = 'not-allowed';
                                    }
                                });

                                // Deshabilitar formularios
                                const formularios = document.querySelectorAll('form');
                                formularios.forEach(form => {
                                    if (!form.id.includes('Terminacion')) {
                                        form.addEventListener('submit', function (e) {
                                            e.preventDefault();
                                            alert('No se puede realizar esta acción porque el colaborador no tiene un contrato activo.');
                                        });
                                    }
                                });
                            }
                        }

                        // Ejecutar al cargar la página
                        document.addEventListener('DOMContentLoaded', verificarContratoActivo);

                        // Función para mostrar categoría sugerida según el cargo
                        //function mostrarCategoriaSugerida() {
                        //    const codCargo = document.getElementById('cod_cargo').value;
                        //    const grupoCategoria = document.getElementById('grupo_categoria_contrato');
                        //    
                        //    if (!grupoCategoria) {
                        //        // Crear el elemento si no existe
                        //        const afterSucursal = document.getElementById('sucursal').parentNode;
                        //        const nuevoGrupo = document.createElement('div');
                        //        nuevoGrupo.id = 'grupo_categoria_contrato';
                        //        nuevoGrupo.className = 'form-group';
                        //        nuevoGrupo.innerHTML = `
                        //            <label for="id_categoria_contrato">Categoría Sugerida</label>
                        //            <select id="id_categoria_contrato" name="id_categoria" class="form-control">
                        //                <option value="">Seleccionar categoría...</option>
                        //                <?php foreach ($todasCategorias as $categoria): ?>
                        //                    <option value="<?= $categoria['idCategoria'] ?>">
                        //                        <?= htmlspecialchars($categoria['NombreCategoria']) ?> (Peso: <?= $categoria['Peso'] ?>)
                        //                    </option>
                        //                <?php endforeach; ?>
                        //            </select>
                        //            <small style="color: #6c757d;" id="textoCategoriaSugerida"></small>
                        //        `;
                        //        afterSucursal.parentNode.insertBefore(nuevoGrupo, afterSucursal.nextSibling);
                        //    }
                        //    
                        // Mapeo de cargos a categorías sugeridas
                        //    const categoriasSugeridas = {
                        //        '2': '5',  // Operario -> Categoría 5
                        //        '5': '1',  // Líder de Sucursal -> Categoría 1
                        //        // Agregar más mapeos según necesites
                        //    };
                        //    
                        //    const categoriaSugerida = categoriasSugeridas[codCargo];
                        //    const selectCategoria = document.getElementById('id_categoria_contrato');
                        //    const textoCategoria = document.getElementById('textoCategoriaSugerida');
                        //    
                        //    if (categoriaSugerida && selectCategoria) {
                        //        for (let i = 0; i < selectCategoria.options.length; i++) {
                        //            if (selectCategoria.options[i].value == categoriaSugerida) {
                        //                selectCategoria.value = categoriaSugerida;
                        //                textoCategoria.textContent = 'Categoría sugerida para este cargo';
                        //                break;
                        //            }
                        //        }
                        //    } else {
                        //        textoCategoria.textContent = 'Seleccione una categoría apropiada para el cargo';
                        //    }
                        //}

                        // Llamar la función cuando cambie el cargo
                        //document.getElementById('cod_cargo').addEventListener('change', mostrarCategoriaSugerida);

                        // También llamar al cargar la página si ya hay un cargo seleccionado
                        //document.addEventListener('DOMContentLoaded', function() {
                        //    if (document.getElementById('cod_cargo').value) {
                        //        mostrarCategoriaSugerida();
                        //    }
                        //});

                        // Agregar evento al cambiar el cargo en adendums
                        document.addEventListener('DOMContentLoaded', function () {
                            const cargoSelectAdendum = document.getElementById('cod_cargo_adendum');
                            if (cargoSelectAdendum) {
                                cargoSelectAdendum.addEventListener('change', function () {
                                    actualizarCamposAdendum();
                                });
                            }

                            // También para la edición
                            const cargoSelectEditAdendum = document.getElementById('edit_cod_cargo_adendum');
                            if (cargoSelectEditAdendum) {
                                cargoSelectEditAdendum.addEventListener('change', function () {
                                    actualizarCamposEdicionAdendum();
                                });
                            }
                        });

                        // Función para actualizar el comportamiento del campo fecha fin
                        function actualizarComportamientoFechaFin() {
                            const fechaFinInput = document.getElementById('fecha_fin_adendum');
                            if (!fechaFinInput) return; // Salir si el elemento no existe (ej: colaborador sin contrato)

                            const ayudaFechaFin = document.createElement('small');
                            ayudaFechaFin.style.color = '#6c757d';
                            ayudaFechaFin.style.display = 'block';
                            fechaFinInput.parentNode.appendChild(ayudaFechaFin);

                            // Verificar si hay adendums existentes
                            const tieneAdendums = <?= count($adendumsColaborador) > 0 ? 'true' : 'false' ?>;

                            if (tieneAdendums) {
                                ayudaFechaFin.textContent = 'Puede crear múltiples adendas activas simultáneamente. Para finalizar una adenda, use el botón de finalización en el historial.';
                                fechaFinInput.placeholder = 'Opcional - para adendum con fecha específica';
                            } else {
                                ayudaFechaFin.textContent = 'Para el primer adendum, puede especificar una fecha fin o dejar vacío para adendum indefinido.';
                                fechaFinInput.placeholder = 'Opcional';
                            }
                        }

                        // Llamar al cargar la página
                        document.addEventListener('DOMContentLoaded', function () {
                            actualizarComportamientoFechaFin();
                        });


                        // Funciones para el modal de finalizar adenda
                        function abrirModalFinalizarAdenda(idAdendum) {
                            document.getElementById('modalFinalizarAdenda').style.display = 'block';
                            document.getElementById('idAdendumFinalizar').value = idAdendum;
                            document.getElementById('fecha_fin_adenda').valueAsDate = new Date();
                        }

                        function cerrarModalFinalizarAdenda() {
                            document.getElementById('modalFinalizarAdenda').style.display = 'none';
                        }

                        // Cerrar modal al hacer clic fuera
                        document.getElementById('modalFinalizarAdenda').addEventListener('click', function (e) {
                            if (e.target === this) {
                                cerrarModalFinalizarAdenda();
                            }
                        });

                        // Función para editar terminación de contrato del historial
                        function abrirModalEditarTerminacion(idContrato) {
                            // Cargar datos del contrato vía AJAX
                            fetch(`ajax/obtener_contrato_terminacion.php?id=${idContrato}`)
                                .then(response => response.json())
                                .then(contrato => {
                                    document.getElementById('modalTerminacion').style.display = 'block';
                                    document.querySelector('#modalTerminacion h3').textContent = 'Editar Información de Terminación';

                                    // Cambiar el formulario a modo edición
                                    const form = document.getElementById('formTerminacion');

                                    // CAMBIAR LA ACCIÓN A EDITAR_TERMINACION
                                    const inputAccion = form.querySelector('input[name="accion_contrato"]');
                                    inputAccion.value = 'editar_terminacion';

                                    // Agregar campo hidden para ID si no existe
                                    let inputIdEditar = form.querySelector('input[name="id_contrato_editar"]');
                                    if (!inputIdEditar) {
                                        inputIdEditar = document.createElement('input');
                                        inputIdEditar.type = 'hidden';
                                        inputIdEditar.name = 'id_contrato_editar';
                                        form.appendChild(inputIdEditar);
                                    }
                                    inputIdEditar.value = idContrato;

                                    // Ocultar el campo id_contrato original para no causar conflictos
                                    const inputIdOriginal = form.querySelector('input[name="id_contrato"]');
                                    if (inputIdOriginal) {
                                        inputIdOriginal.disabled = true;
                                    }

                                    // Llenar los campos con los datos del contrato
                                    document.getElementById('fecha_fin_contrato').value = contrato.fin_contrato || '';
                                    document.getElementById('fecha_terminacion').value = contrato.fecha_salida || '';
                                    document.getElementById('fecha_liquidacion').value = contrato.fecha_liquidacion || '';
                                    document.getElementById('tipo_salida').value = contrato.cod_tipo_salida || '';
                                    document.getElementById('motivo_salida').value = contrato.motivo || '';
                                    document.getElementById('dias_trabajados').value = contrato.dias_trabajados || '';
                                    document.getElementById('monto_indemnizacion').value = contrato.monto_indemnizacion || '';
                                    document.getElementById('devolucion_herramientas').value = contrato.devolucion_herramientas_trabajo || '0';
                                    document.getElementById('persona_recibe_herramientas').value = contrato.persona_recibe_herramientas_trabajo || '';

                                    // Mostrar campo de persona si aplica devolución
                                    if (contrato.devolucion_herramientas_trabajo == '1') {
                                        document.getElementById('grupoPersonaHerramientas').style.display = 'block';
                                    }

                                    // Cambiar el texto del botón
                                    const btnSubmit = form.querySelector('button[type="submit"]');
                                    btnSubmit.textContent = 'Guardar Cambios';
                                    btnSubmit.style.backgroundColor = ' #0E544C'; // Color verde en lugar de rojo
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    alert('Error al cargar los datos del contrato: ' + error.message);
                                });
                        }
                    </script>

                    <!-- Modal para ver foto de perfil en tamaño completo y Carrusel -->
                    <div id="modalVerFoto"
                        style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.95); overflow: hidden; user-select: none;">

                        <!-- Botón Cerrar -->
                        <span onclick="cerrarModalVerFoto()"
                            style="position: absolute; top: 20px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; cursor: pointer; z-index: 10001; transition: 0.3s;"
                            onmouseover="this.style.color='#bbb'" onmouseout="this.style.color='#f1f1f1'">&times;</span>

                        <!-- Contenedor de Imagen -->
                        <div
                            style="width: 100%; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; position: relative;">

                            <!-- Botón Anterior -->
                            <div id="btnPrevCarrusel" onclick="navegarCarrusel(-1)"
                                style="position: absolute; left: 20px; color: white; font-size: 50px; cursor: pointer; z-index: 10000; padding: 20px; display: none;">
                                <i class="fas fa-chevron-left"></i>
                            </div>

                            <!-- Imagen -->
                            <div id="contenedorImagenModal"
                                style="position: relative; max-width: 90%; max-height: 80%; display: flex; justify-content: center; align-items: center;">
                                <!-- Spinner de Carga -->
                                <div id="spinnerCargaModal"
                                    style="display: none; position: absolute; color: white; flex-direction: column; align-items: center; justify-content: center;">
                                    <i class="fas fa-spinner fa-spin" style="font-size: 3rem; margin-bottom: 10px;"></i>
                                    <span>Cargando imagen...</span>
                                </div>

                                <!-- Mensaje de Error -->
                                <div id="errorCargaModal"
                                    style="display: none; position: absolute; color: #ff6b6b; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                                    <i class="fas fa-exclamation-triangle"
                                        style="font-size: 3rem; margin-bottom: 10px;"></i>
                                    <div style="font-size: 1.2rem; margin-bottom: 15px;">No se pudo cargar la imagen
                                    </div>
                                    <button onclick="reintentarCargaImagen()"
                                        style="background: #ff6b6b; color: white; border: none; padding: 8px 20px; border-radius: 5px; cursor: pointer; font-weight: bold;">
                                        <i class="fas fa-sync-alt"></i> Reintentar
                                    </button>
                                </div>

                                <img id="imagenFotoCompleta" onload="onImagenCargada()" onerror="onImagenError()"
                                    style="max-width: 100%; max-height: 100%; object-fit: contain; box-shadow: 0 0 20px rgba(0,0,0,0.5); transition: transform 0.3s ease; display: none;">
                            </div>

                            <!-- Pie de foto / Título -->
                            <div id="infoCarrusel"
                                style="color: white; margin-top: 20px; text-align: center; font-family: sans-serif;">
                                <div id="tituloImagenCarrusel"
                                    style="font-size: 1.2rem; margin-bottom: 5px; font-weight: 500;">
                                </div>
                                <div id="contadorCarrusel" style="font-size: 1rem; color: #ccc;"></div>
                            </div>

                            <!-- Botón Siguiente -->
                            <div id="btnNextCarrusel" onclick="navegarCarrusel(1)"
                                style="position: absolute; right: 20px; color: white; font-size: 50px; cursor: pointer; z-index: 10000; padding: 20px; display: none;">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </div>
                    </div>

                    <script>
                        // Mostrar ícono de ver al hacer hover sobre la foto               de perfil
                        document.addEventListener('DOMContentLoaded', function () {
                            const fotoContainer = document.querySelector('.foto-perfil');
                            const viewIcon = document.querySelector('.view-icon');

                            if (fotoContainer && viewIcon) {
                                fotoContainer.addEventListener('mouseenter', function () {
                                    viewIcon.style.opacity = '1';
                                });

                                fotoContainer.addEventListener('mouseleave', function () {
                                    viewIcon.style.opacity = '0';
                                });
                            }
                        });

                        // Lista de imágenes para el carrusel exportada desde PHP
                        const listaImagenesAdjuntas = <?= json_encode($imagenesParaCarrusel) ?>;
                        let indiceImagenActual = -1;

                        // Función para abrir modal de ver foto (caso simple, e.g. foto de perfil)
                        function abrirModalVerFoto(rutaFoto, titulo = "") {
                            indiceImagenActual = -1; // No es carrusel
                            document.getElementById('modalVerFoto').style.display = 'block';
                            document.body.style.overflow = 'hidden'; // Bloquear scroll

                            cargarImagenEnModal(rutaFoto, titulo, "");
                        }

                        // Función para abrir el carrusel en una posición específica
                        function visualizarCarrusel(indice, listaCustom = null) {
                            const listaReferencia = listaCustom || listaImagenesAdjuntas;
                            if (indice < 0 || indice >= listaReferencia.length) return;

                            indiceImagenActual = indice;
                            const img = listaReferencia[indice];

                            document.getElementById('modalVerFoto').style.display = 'block';
                            document.body.style.overflow = 'hidden';

                            // Mostrar botones de navegación si hay más de una imagen
                            const displayNav = listaReferencia.length > 1 ? 'block' : 'none';
                            document.getElementById('btnPrevCarrusel').style.display = displayNav;
                            document.getElementById('btnNextCarrusel').style.display = displayNav;

                            const titulo = img.nombre + (img.categoria ? " (" + img.categoria + ")" : "");
                            const contador = (indice + 1) + " de " + listaReferencia.length;

                            cargarImagenEnModal(img.url, titulo, contador);

                            // Guardar la lista actual para la navegación por flechas si es una lista customizada
                            if (listaCustom) {
                                window.listaCarruselActual = listaCustom;
                            } else {
                                window.listaCarruselActual = listaImagenesAdjuntas;
                            }
                        }

                        // Nueva función centralizada para cargar imagen con estados
                        function cargarImagenEnModal(url, titulo, contador) {
                            const imgElement = document.getElementById('imagenFotoCompleta');
                            const spinner = document.getElementById('spinnerCargaModal');
                            const errorMsg = document.getElementById('errorCargaModal');

                            // Resetear estados
                            imgElement.style.display = 'none';
                            errorMsg.style.display = 'none';
                            spinner.style.display = 'flex';

                            // Actualizar textos
                            document.getElementById('tituloImagenCarrusel').textContent = titulo;
                            document.getElementById('contadorCarrusel').textContent = contador;

                            // Asignar URL (esto dispara onload o onerror)
                            imgElement.src = url;
                        }

                        function onImagenCargada() {
                            document.getElementById('spinnerCargaModal').style.display = 'none';
                            document.getElementById('errorCargaModal').style.display = 'none';
                            document.getElementById('imagenFotoCompleta').style.display = 'block';
                        }

                        function onImagenError() {
                            document.getElementById('spinnerCargaModal').style.display = 'none';
                            document.getElementById('imagenFotoCompleta').style.display = 'none';
                            document.getElementById('errorCargaModal').style.display = 'flex';
                        }

                        function reintentarCargaImagen() {
                            const imgElement = document.getElementById('imagenFotoCompleta');
                            const currentSrc = imgElement.src;

                            // Forzar recarga limpiando el src momentáneamente
                            imgElement.src = "";
                            setTimeout(() => {
                                onImagenCargada(); // Reset visual
                                cargarImagenEnModal(currentSrc, document.getElementById('tituloImagenCarrusel').textContent, document.getElementById('contadorCarrusel').textContent);
                            }, 50);
                        }

                        // Navegar por el carrusel
                        function navegarCarrusel(direccion) {
                            const listaReferencia = window.listaCarruselActual || listaImagenesAdjuntas;
                            if (indiceImagenActual === -1 || listaReferencia.length <= 1) return;

                            let nuevoIndice = indiceImagenActual + direccion;

                            // Bucle infinito
                            if (nuevoIndice < 0) nuevoIndice = listaReferencia.length - 1;
                            if (nuevoIndice >= listaReferencia.length) nuevoIndice = 0;

                            visualizarCarrusel(nuevoIndice, window.listaCarruselActual);
                        }


                        // Función para cerrar modal de ver foto
                        function cerrarModalVerFoto() {
                            document.getElementById('modalVerFoto').style.display = 'none';
                            document.body.style.overflow = 'auto'; // Restaurar scroll
                            window.listaCarruselActual = null;
                        }

                        // Cerrar modal al hacer clic fuera de la imagen (en el fondo oscuro)
                        document.getElementById('modalVerFoto').addEventListener('click', function (e) {
                            if (e.target === this || e.target.parentElement === this) {
                                cerrarModalVerFoto();
                            }
                        });

                        // Soporte para flechas de teclado
                        document.addEventListener('keydown', function (e) {
                            if (document.getElementById('modalVerFoto').style.display === 'block') {
                                if (e.key === 'ArrowLeft') navegarCarrusel(-1);
                                if (e.key === 'ArrowRight') navegarCarrusel(1);
                                if (e.key === 'Escape') cerrarModalVerFoto();
                            }
                        });

                        // Función para ver adjuntos (imágenes en modal, PDFs en nueva pestaña)
                        function visualizarAdjunto(url, listaCustom = null) {
                            const extension = url.split('.').pop().toLowerCase();
                            const esImagen = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(extension);

                            if (esImagen) {
                                // Buscar si está en la lista proporcionada o en la global
                                const listaReferencia = listaCustom || listaImagenesAdjuntas;
                                const index = listaReferencia.findIndex(img => img.url === url);
                                if (index !== -1) {
                                    visualizarCarrusel(index, listaCustom);
                                } else {
                                    abrirModalVerFoto(url);
                                }
                            } else {
                                window.open(url, '_blank');
                            }
                        }
                    </script>
                </div>
            </div>
</body>

</html>