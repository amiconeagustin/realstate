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

$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT p.*, m.roi_percent, m.gross_profit
         FROM projects p
         LEFT JOIN project_metrics m ON m.project_id = p.id
         WHERE p.user_id = ?
         ORDER BY p.updated_at DESC'
    );
    $stmt->execute([$userId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $required = ['name','location','type','total_area_m2','buildable_area_m2',
                 'land_cost','construction_cost_m2','sale_price_m2'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            http_response_code(400);
            echo json_encode(['error' => "Campo requerido: $field"]);
            exit;
        }
    }

    $stmt = $pdo->prepare(
        'INSERT INTO projects
            (user_id, name, location, type, total_area_m2, buildable_area_m2,
             land_cost, construction_cost_m2, sale_price_m2, other_costs, notes)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $userId,
        $data['name'],
        $data['location'],
        $data['type'],
        (float) $data['total_area_m2'],
        (float) $data['buildable_area_m2'],
        (float) $data['land_cost'],
        (float) $data['construction_cost_m2'],
        (float) $data['sale_price_m2'],
        (float) ($data['other_costs'] ?? 0),
        $data['notes'] ?? null,
    ]);

    $projectId = (int) $pdo->lastInsertId();
    recalcMetrics($pdo, $projectId, $data);

    http_response_code(201);
    echo json_encode(['success' => true, 'project_id' => $projectId]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);

function recalcMetrics(PDO $pdo, int $projectId, array $d): void
{
    $totalInvestment = (float)$d['land_cost']
        + ((float)$d['construction_cost_m2'] * (float)$d['buildable_area_m2'])
        + (float)($d['other_costs'] ?? 0);

    $totalRevenue = (float)$d['sale_price_m2'] * (float)$d['buildable_area_m2'];
    $grossProfit  = $totalRevenue - $totalInvestment;
    $roi          = $totalInvestment > 0 ? ($grossProfit / $totalInvestment) * 100 : 0;
    $breakEven    = (float)$d['buildable_area_m2'] > 0
        ? $totalInvestment / (float)$d['buildable_area_m2']
        : 0;

    $stmt = $pdo->prepare(
        'INSERT INTO project_metrics
            (project_id, total_investment, total_revenue, gross_profit, roi_percent, break_even_price_m2)
         VALUES (?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
            total_investment    = VALUES(total_investment),
            total_revenue       = VALUES(total_revenue),
            gross_profit        = VALUES(gross_profit),
            roi_percent         = VALUES(roi_percent),
            break_even_price_m2 = VALUES(break_even_price_m2)'
    );
    $stmt->execute([$projectId, $totalInvestment, $totalRevenue, $grossProfit, $roi, $breakEven]);
}
