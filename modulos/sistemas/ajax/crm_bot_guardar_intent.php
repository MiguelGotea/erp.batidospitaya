<?php
/**
 * ajax/crm_bot_guardar_intent.php — Crear/actualizar bot_intents + regenerar embeddings
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');
$usuario = obtenerUsuarioActual();
if (!tienePermiso('crm_bot', 'gestionar_intents', $usuario['CodNivelesCargos'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permiso']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$id = (int) ($body['id'] ?? 0);
$nombre = trim($body['intent_name'] ?? '');
$keywords = trim($body['keywords'] ?? '');
$templates = $body['response_templates'] ?? [];
$prioridad = (int) ($body['priority'] ?? 5);
$activo = (int) ($body['is_active'] ?? 1);

if (!$nombre) {
    echo json_encode(['success' => false, 'error' => 'nombre requerido']);
    exit;
}
if (!$templates) {
    echo json_encode(['success' => false, 'error' => 'templates requeridos']);
    exit;
}

$templatesJson = json_encode($templates, JSON_UNESCAPED_UNICODE);

try {
    if ($id) {
        $stmt = $conn->prepare("
            UPDATE bot_intents
            SET intent_name = :n, keywords = :k, response_templates = :t,
                priority = :p, is_active = :a, updated_at = CONVERT_TZ(NOW(),'+00:00','-06:00')
            WHERE id = :id
        ");
        $stmt->execute([':n' => $nombre, ':k' => $keywords, ':t' => $templatesJson, ':p' => $prioridad, ':a' => $activo, ':id' => $id]);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO bot_intents (intent_name, keywords, response_templates, priority, is_active, created_at, updated_at)
            VALUES (:n, :k, :t, :p, :a, CONVERT_TZ(NOW(),'+00:00','-06:00'), CONVERT_TZ(NOW(),'+00:00','-06:00'))
        ");
        $stmt->execute([':n' => $nombre, ':k' => $keywords, ':t' => $templatesJson, ':p' => $prioridad, ':a' => $activo]);
        $id = $conn->lastInsertId();
    }

    // Regenerar vector TF-IDF para esta intención (Nivel 3 embeddings)
    regenerarEmbedding($conn, $id, $nombre, $keywords, $templates);

    echo json_encode(['success' => true, 'id' => $id]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Genera y guarda el vector TF-IDF de una intención en intent_embeddings
 */
function regenerarEmbedding(PDO $conn, int $intentId, string $nombre, string $keywords, array $templates): void
{
    // Borrar embeddings anteriores de esta intención
    $conn->prepare("DELETE FROM intent_embeddings WHERE intent_id = :id")->execute([':id' => $intentId]);

    // Construir corpus: keywords + templates
    $corpus = implode(' ', array_merge(
        [$keywords],
        $templates
    ));

    // Tokenizar simple (igual que motor_bot.php)
    $txt = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $corpus));
    $txt = preg_replace('/[^a-z0-9\s]/', ' ', $txt);
    $tokens = array_filter(explode(' ', $txt), fn($t) => strlen($t) > 2);

    static $stopwords = ['de', 'la', 'el', 'en', 'y', 'a', 'que', 'es', 'no', 'lo', 'un', 'una', 'me', 'te', 'se', 'su', 'al', 'le', 'para', 'con', 'por', 'los', 'las'];
    $tokens = array_values(array_filter($tokens, fn($t) => !in_array($t, $stopwords)));

    if (empty($tokens))
        return;

    // TF
    $tf = array_count_values($tokens);
    $total = count($tokens);
    $vector = [];
    $mag = 0.0;

    foreach ($tf as $term => $count) {
        $vector[$term] = $count / $total;
        $mag += $vector[$term] ** 2;
    }
    $mag = sqrt($mag) ?: 1.0;

    // Normalizar y guardar
    $stmtEmb = $conn->prepare("
        INSERT INTO intent_embeddings (intent_id, term, tfidf_weight) VALUES (:iid, :term, :w)
    ");
    foreach ($vector as $term => $w) {
        $stmtEmb->execute([':iid' => $intentId, ':term' => $term, ':w' => round($w / $mag, 6)]);
    }
}
