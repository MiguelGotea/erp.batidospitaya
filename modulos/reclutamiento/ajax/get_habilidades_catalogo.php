<?php
// get_habilidades_catalogo.php
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    if (!tienePermiso('postulacion_panel_control', 'vista', $cargoOperario)) {
        throw new Exception("Sin permiso");
    }

    $idConfig = isset($_GET['id_config']) ? (int)$_GET['id_config'] : 0;

    // Catálogo completo de habilidades activas
    $sqlCat  = "SELECT id, nombre, categoria FROM habilidades_talento WHERE activo = 1 ORDER BY categoria, nombre";
    $stmtCat = $conn->prepare($sqlCat);
    $stmtCat->execute();
    $catalogo = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

    // IDs seleccionados actualmente para esta plaza
    $seleccionadas = [];
    if ($idConfig > 0) {
        $sqlSel  = "SELECT habilidades FROM plazas_cargos WHERE id = :id LIMIT 1";
        $stmtSel = $conn->prepare($sqlSel);
        $stmtSel->bindValue(':id', $idConfig, PDO::PARAM_INT);
        $stmtSel->execute();
        $habilidadesStr = $stmtSel->fetchColumn();

        if (!empty($habilidadesStr)) {
            $seleccionadas = array_map('intval', array_filter(explode(',', $habilidadesStr), 'is_numeric'));
        }
    }

    echo json_encode([
        'success'      => true,
        'catalogo'     => $catalogo,
        'seleccionadas' => $seleccionadas
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
