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

$userId    = (int) $_SESSION['user_id'];
$projectId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($projectId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de proyecto requerido']);
    exit;
}

// ---- GET: obtener proyecto ----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT p.*,
                m.total_investment, m.total_revenue, m.gross_profit,
                m.roi_percent, m.break_even_price_m2, m.calculated_at
         FROM projects p
         LEFT JOIN project_metrics m ON m.project_id = p.id
         WHERE p.id = ? AND p.user_id = ?'
    );
    $stmt->execute([$projectId, $userId]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Proyecto no encontrado']);
        exit;
    }

    // Extraer parámetros de cálculo guardados en notes (JSON)
    $params = null;
    if (!empty($row['notes'])) {
        $decoded = json_decode($row['notes'], true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['fee_percent'])) {
            $params          = $decoded;
            $row['notes']    = null; // notas limpias si eran solo params
        }
    }

    $row['calc_params'] = $params ?? [
        'fee_percent'               => 8.0,
        'operating_percent'         => 3.0,
        'commercialization_percent' => 3.0,
        'notarial_percent'          => 2.0,
        'tax_percent'               => 1.5,
        'discount_rate'             => 15.0,
        'construction_months'       => 18,
        'sales_start_month'         => 12,
        'project_months'            => 24,
    ];

    echo json_encode($row);
    exit;
}

// ---- PUT: actualizar proyecto ----
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    // Verificar que el proyecto pertenece al usuario
    $check = $pdo->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ?');
    $check->execute([$projectId, $userId]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Proyecto no encontrado']);
        exit;
    }

    $required = ['name', 'location', 'type', 'total_area_m2', 'buildable_area_m2',
                 'land_cost', 'construction_cost_m2', 'sale_price_m2'];
    foreach ($required as $f) {
        if (!isset($data[$f]) || $data[$f] === '') {
            http_response_code(400);
            echo json_encode(['error' => "Campo requerido: $f"]);
            exit;
        }
    }

    $allowedStatus = ['borrador', 'en_analisis', 'aprobado', 'rechazado'];
    $status = in_array($data['status'] ?? '', $allowedStatus) ? $data['status'] : 'en_analisis';

    // Guardar parámetros de cálculo en notes como JSON
    $calcParams = [
        'fee_percent'               => (float) ($data['fee_percent']               ?? 8.0),
        'operating_percent'         => (float) ($data['operating_percent']         ?? 3.0),
        'commercialization_percent' => (float) ($data['commercialization_percent'] ?? 3.0),
        'notarial_percent'          => (float) ($data['notarial_percent']           ?? 2.0),
        'tax_percent'               => (float) ($data['tax_percent']               ?? 1.5),
        'discount_rate'             => (float) ($data['discount_rate']             ?? 15.0),
        'construction_months'       => (int)   ($data['construction_months']       ?? 18),
        'sales_start_month'         => (int)   ($data['sales_start_month']         ?? 12),
        'project_months'            => (int)   ($data['project_months']            ?? 24),
    ];

    $stmt = $pdo->prepare(
        'UPDATE projects SET
            name                 = ?,
            location             = ?,
            type                 = ?,
            total_area_m2        = ?,
            buildable_area_m2    = ?,
            land_cost            = ?,
            construction_cost_m2 = ?,
            sale_price_m2        = ?,
            other_costs          = ?,
            status               = ?,
            notes                = ?
         WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([
        trim($data['name']),
        trim($data['location']),
        $data['type'],
        (float) $data['total_area_m2'],
        (float) $data['buildable_area_m2'],
        (float) $data['land_cost'],
        (float) $data['construction_cost_m2'],
        (float) $data['sale_price_m2'],
        (float) ($data['other_costs'] ?? 0),
        $status,
        json_encode($calcParams),
        $projectId,
        $userId,
    ]);

    // Recalcular métricas base (escenario efectivo)
    $buildableM2 = (float) $data['buildable_area_m2'];
    $totalInv    = (float) $data['land_cost']
                 + ((float) $data['construction_cost_m2'] * $buildableM2)
                 + (float) ($data['other_costs'] ?? 0);
    $totalRev    = (float) $data['sale_price_m2'] * $buildableM2;
    $profit      = $totalRev - $totalInv;
    $roi         = $totalInv > 0 ? ($profit / $totalInv) * 100 : 0;
    $breakEven   = $buildableM2 > 0 ? $totalInv / $buildableM2 : 0;

    $m = $pdo->prepare(
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
    $m->execute([$projectId, $totalInv, $totalRev, $profit, $roi, $breakEven]);

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
