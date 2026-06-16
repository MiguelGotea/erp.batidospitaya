<?php
// postulacion_candidatos_plaza.php

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('postulacion_plazas_activas', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

// Permiso para selección directa (saltar evaluación del jefe)
$puedeSeleccionarDirecto = tienePermiso('postulacion_plazas_activas', 'seleccionar_directo', $cargoOperario);
$puedeAprobar = tienePermiso('postulacion_plazas_activas', 'aprobar', $cargoOperario);

// Obtener ID de plaza
$idPlaza = isset($_GET['plaza_id']) ? (int) $_GET['plaza_id'] : 0;

if ($idPlaza <= 0) {
    header('Location: postulacion_plazas_activas.php');
    exit();
}

// Obtener información de la plaza
$sqlPlaza = "SELECT 
                pc.id,
                nc.Nombre as nombre_cargo,
                nc.Area as area,
                s.nombre as sucursal_nombre,
                 pc.cantidad_real,
                pc.cantidad_adicional,
                pc.salario_propuesto,
                pc.nivel_urgencia,
                (SELECT COUNT(DISTINCT anc.CodOperario) 
                 FROM AsignacionNivelesCargos anc
                 INNER JOIN Contratos c ON anc.CodOperario = c.cod_operario
                 WHERE (
                     (pc.cargo IN (2, 44, 45, 46, 47) AND anc.CodNivelesCargos IN (2, 44, 45, 46, 47)) OR 
                     (pc.cargo IN (5, 43) AND anc.CodNivelesCargos IN (5, 43)) OR
                     (pc.cargo NOT IN (2, 44, 45, 46, 47, 5, 43) AND anc.CodNivelesCargos = pc.cargo)
                 )
                 AND (anc.Sucursal = pc.sucursal OR pc.sucursal IS NULL OR pc.sucursal = 0)
                 AND anc.Fecha <= CURDATE()
                 AND (anc.Fin IS NULL OR anc.Fin = '' OR anc.Fin >= CURDATE())
                 AND c.Finalizado = 0
                ) as cantidad_cubierta
            FROM plazas_cargos pc
            INNER JOIN NivelesCargos nc ON pc.cargo = nc.CodNivelesCargos
            LEFT JOIN sucursales s ON pc.sucursal = s.codigo
            WHERE pc.id = :id_plaza";

$stmtPlaza = $conn->prepare($sqlPlaza);
$stmtPlaza->bindValue(':id_plaza', $idPlaza, PDO::PARAM_INT);
$stmtPlaza->execute();
$plaza = $stmtPlaza->fetch(PDO::FETCH_ASSOC);

if (!$plaza) {
    header('Location: postulacion_plazas_activas.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidatos - <?php echo htmlspecialchars($plaza['nombre_cargo']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/postulacion_candidatos_plaza.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Candidatos - ' . $plaza['nombre_cargo']); ?>

            <div class="container-fluid p-4">

                <!-- ============================================================
                     Grupo 3: Candidatos Seleccionados (Fase Contratación)
                     ============================================================ -->
                <div class="seccion-candidatos">
                    <div class="seccion-header">
                        <h5 class="seccion-header-titulo">
                            <span class="borde-color borde-verde"></span>
                            Candidatos Seleccionados (Fase Contratación)
                        </h5>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge-conteo badge-finalistas" id="badgeSeleccionados">0 FINALISTAS</span>
                            <?php if ($puedeAprobar): ?>
                            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalAgregarCandidatoInmediato" style="background-color: #10b981; border-color: #10b981; font-weight: 600;">
                                <i class="bi bi-person-plus-fill me-1"></i> Agregar Candidato
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="tabla-candidatos" id="tablaSeleccionados">
                            <thead>
                                <tr>
                                    <th style="width:220px">Candidato</th>
                                    <th class="text-center">Filtro RRHH</th>
                                    <th class="text-center">Filtro Jefe</th>
                                    <th style="width:160px">Docus (%)</th>
                                    <th class="text-center">Credenciales</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="bodySeleccionados">
                                <!-- Cargado dinámicamente -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ============================================================
                     Grupo 2: Proceso de Entrevistas (En Embudo)
                     ============================================================ -->
                <div class="seccion-candidatos">
                    <div class="seccion-header">
                        <h5 class="seccion-header-titulo">
                            <span class="borde-color borde-azul"></span>
                            Proceso de Entrevistas (En Embudo)
                        </h5>
                        <span class="badge-conteo badge-en-proceso" id="badgeAprobados">0 EN PROCESO</span>
                    </div>
                    <div class="table-responsive">
                        <table class="tabla-candidatos" id="tablaAprobados">
                            <thead>
                                <tr>
                                    <th style="width:220px">Candidato</th>
                                    <th class="text-center">Filtro RRHH</th>
                                    <th class="text-center">Filtro Jefe</th>
                                    <th class="text-center">Estado Actual</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="bodyAprobados">
                                <!-- Cargado dinámicamente -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ============================================================
                     Grupo 1: Lista de Postulantes (Pendientes de Validación)
                     ============================================================ -->
                <div class="seccion-candidatos">
                    <div class="seccion-header">
                        <h5 class="seccion-header-titulo">
                            <span class="borde-color borde-gris"></span>
                            Lista de Postulantes (Pendientes de Validación)
                        </h5>
                        <span class="badge-conteo badge-pendientes" id="badgeSolicitados">PENDIENTES</span>
                    </div>
                    <div class="table-responsive">
                        <table class="tabla-candidatos" id="tablaSolicitados">
                            <thead>
                                <tr>
                                    <th style="width:200px">Nombre del Candidato</th>
                                    <th>Fecha de Postulación</th>
                                    <th class="text-center">Experiencia</th>
                                    <th class="text-center">CV</th>
                                    <th class="text-center">Análisis IA</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="bodySolicitados">
                                <!-- Cargado dinámicamente -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Barra de paginación -->
                    <div class="paginacion-barra">
                        <div class="paginacion-selector">
                            <label>Mostrar</label>
                            <select class="select-registros" id="registrosPorPagina" onchange="cambiarRegistrosPorPagina()">
                                <option value="10" selected>10 registros</option>
                                <option value="25">25 registros</option>
                                <option value="50">50 registros</option>
                            </select>
                        </div>
                        <div class="paginacion-controles" id="controlesPaginacion">
                            <!-- Renderizado por JS -->
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal de Ayuda -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel" aria-hidden="true"
        data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="pageHelpModalLabel">
                        <i class="fas fa-info-circle me-2"></i>
                        Guía de Candidatos
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold">
                                        <i class="bi bi-robot me-2"></i> Análisis IA
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        El sistema analiza automáticamente el CV y muestra un porcentaje de
                                        compatibilidad con el perfil del puesto.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-success border-bottom pb-2 fw-bold">
                                        <i class="bi bi-check-circle me-2"></i> Estados
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        <strong>SOLICITADO:</strong> El postulante envió su información.<br>
                                        <strong>APROBADO:</strong> Aprobación inicial de RH.<br>
                                        <strong>RECHAZADO:</strong> Rechazado por RH en cualquier etapa.<br>
                                        <strong>SELECCIONADO:</strong> Aprobado por el Jefe de Área.<br>
                                        <strong>DENEGADO:</strong> Descartado por el Jefe de Área.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-info border-bottom pb-2 fw-bold">
                                        <i class="bi bi-eye me-2"></i> Ver Perfil
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Haz clic en "Ver Perfil" para revisar la información completa del candidato y
                                        programar entrevistas.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Agregar Candidato Inmediato -->
    <div class="modal fade" id="modalAgregarCandidatoInmediato" tabindex="-1" aria-labelledby="modalAgregarCandidatoInmediatoLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="modalAgregarCandidatoInmediatoLabel">
                        <i class="bi bi-person-plus-fill me-2"></i> Agregar Candidato Inmediato
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formAgregarCandidatoInmediato" onsubmit="agregarCandidatoInmediato(event)">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="candiNombre" class="form-label fw-bold">Nombre Completo *</label>
                            <input type="text" class="form-control" id="candiNombre" required placeholder="Ej: Juan Pérez">
                        </div>
                        <div class="mb-3">
                            <label for="candiCorreo" class="form-label fw-bold">Correo Electrónico *</label>
                            <input type="email" class="form-control" id="candiCorreo" required placeholder="Ej: juan.perez@example.com">
                        </div>
                        <div class="mb-3">
                            <label for="candiTelefono" class="form-label fw-bold">Número de Teléfono *</label>
                            <input type="text" class="form-control" id="candiTelefono" required placeholder="Ej: 88888888">
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success" style="background-color: #10b981; border-color: #10b981;">
                            <i class="bi bi-save me-1"></i> Guardar y Crear Solicitud
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        #pageHelpModal {
            z-index: 1060 !important;
        }

        .modal-backdrop {
            z-index: 1050 !important;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const idPlaza = <?php echo $idPlaza; ?>;
        const puedeSeleccionarDirecto = <?php echo $puedeSeleccionarDirecto ? 'true' : 'false'; ?>;
        const puedeAprobar = <?php echo $puedeAprobar ? 'true' : 'false'; ?>;
    </script>
    <script src="js/postulacion_candidatos_plaza.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>