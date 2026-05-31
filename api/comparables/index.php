<?php
require_once __DIR__ . '/../../config/cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db.php';

session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$projectId = (int) ($_GET['project_id'] ?? 0);
if (!$projectId) {
    http_response_code(400);
    echo json_encode(['error' => 'project_id requerido']);
    exit;
}

// Verificar que el proyecto pertenece al usuario
$stmt = $pdo->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ?');
$stmt->execute([$projectId, (int)$_SESSION['user_id']]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT * FROM comparables WHERE project_id = ? ORDER BY sale_date DESC, created_at DESC'
    );
    $stmt->execute([$projectId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    foreach (['address', 'area_m2', 'total_price'] as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            http_response_code(400);
            echo json_encode(['error' => "Campo requerido: $field"]);
            exit;
        }
    }

    $stmt = $pdo->prepare(
        'INSERT INTO comparables (project_id, address, area_m2, total_price, source, sale_date, notes)
         VALUES (?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $projectId,
        $data['address'],
        (float) $data['area_m2'],
        (float) $data['total_price'],
        $data['source']    ?? null,
        $data['sale_date'] ?? null,
        $data['notes']     ?? null,
    ]);

    http_response_code(201);
    echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
