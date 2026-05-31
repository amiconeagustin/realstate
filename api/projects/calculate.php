<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$data   = json_decode(file_get_contents('php://input'), true) ?? [];

$required = ['lot_address', 'lot_m2', 'land_price', 'sale_price_m2', 'buildable_m2', 'construction_cost_m2'];
foreach ($required as $f) {
    if (!isset($data[$f]) || $data[$f] === '') {
        http_response_code(400);
        echo json_encode(['error' => "Campo requerido: $f"]);
        exit;
    }
}

$lotAddress         = trim($data['lot_address']);
$lotM2              = (float) $data['lot_m2'];
$landPrice          = (float) $data['land_price'];
$salePriceM2        = (float) $data['sale_price_m2'];
$buildableM2        = (float) $data['buildable_m2'];
$constructionCostM2 = (float) $data['construction_cost_m2'];
$projectName        = trim($data['project_name'] ?? $lotAddress);
$projectType        = in_array($data['project_type'] ?? '', ['residencial','comercial','mixto'])
                        ? $data['project_type'] : 'residencial';

$feePercent         = (float) ($data['fee_percent']                ?? 8.0);
$operatingPercent   = (float) ($data['operating_percent']          ?? 3.0);
$commercPercent     = (float) ($data['commercialization_percent']  ?? 3.0);
$notarialPercent    = (float) ($data['notarial_percent']           ?? 2.0);
$taxPercent         = (float) ($data['tax_percent']                ?? 1.5);
$discountRate       = (float) ($data['discount_rate']              ?? 15.0);
$constructionMonths = max(1, (int) ($data['construction_months']   ?? 18));
$salesStartMonth    = max(1, (int) ($data['sales_start_month']     ?? 12));
$projectMonths      = max($salesStartMonth + 1, (int) ($data['project_months'] ?? 24));
$save               = !empty($data['save']);

// ---- Cálculo de un escenario dado landCashFraction (0 = canje puro, 1 = efectivo puro) ----
function calcScenario(
    float $landPrice,
    float $landCashFraction,
    float $buildableM2,
    float $salePriceM2,
    float $constructionCostM2,
    float $feePercent,
    float $operatingPercent,
    float $commercPercent,
    float $notarialPercent,
    float $taxPercent,
    float $discountRate,
    int   $constructionMonths,
    int   $salesStartMonth,
    int   $projectMonths
): array {
    $landCash   = $landPrice * $landCashFraction;
    $landSwap   = $landPrice * (1.0 - $landCashFraction);
    $m2Swapped  = ($salePriceM2 > 0) ? $landSwap / $salePriceM2 : 0.0;
    $m2Sellable = max(0.0, $buildableM2 - $m2Swapped);

    $grossRevenue     = $m2Sellable * $salePriceM2;
    $notarialCost     = $landPrice  * $notarialPercent / 100.0;
    $constructionCost = $buildableM2 * $constructionCostM2;
    $feeCost          = $constructionCost * $feePercent / 100.0;
    $operatingCost    = $grossRevenue * $operatingPercent / 100.0;
    $commercCost      = $grossRevenue * $commercPercent  / 100.0;
    $taxCost          = $grossRevenue * $taxPercent / 100.0;

    $totalCosts = $landCash + $notarialCost + $constructionCost + $feeCost
                + $operatingCost + $commercCost + $taxCost;
    $profit  = $grossRevenue - $totalCosts;
    $margin  = ($grossRevenue > 0) ? ($profit / $grossRevenue) * 100.0 : 0.0;

    // Flujo mensual
    $salesMonths      = $projectMonths - $salesStartMonth + 1;
    $monthlyCons      = ($constructionCost + $feeCost) / $constructionMonths;
    $monthlyRevenue   = ($salesMonths > 0) ? $grossRevenue / $salesMonths : 0.0;
    $monthlyRevCosts  = ($salesMonths > 0) ? ($operatingCost + $commercCost + $taxCost) / $salesMonths : 0.0;

    $cashflow = array_fill(0, $projectMonths + 1, 0.0);
    $cashflow[0] -= ($landCash + $notarialCost);
    for ($m = 0; $m < $constructionMonths && $m <= $projectMonths; $m++) {
        $cashflow[$m] -= $monthlyCons;
    }
    for ($m = $salesStartMonth; $m <= $projectMonths; $m++) {
        $cashflow[$m] += $monthlyRevenue - $monthlyRevCosts;
    }

    $rMonthly = pow(1.0 + $discountRate / 100.0, 1.0 / 12.0) - 1.0;
    $van      = npv($cashflow, $rMonthly);
    $tir      = tirAnual($cashflow);
    $color    = semaforo($van, $tir, $margin);

    return [
        'ingresos_brutos'        => round($grossRevenue, 2),
        'm2_vendibles'           => round($m2Sellable, 2),
        'm2_canjeados'           => round($m2Swapped, 2),
        'costo_terreno_efectivo' => round($landCash, 2),
        'costo_terreno_canje'    => round($landSwap, 2),
        'costo_notarial'         => round($notarialCost, 2),
        'costo_construccion'     => round($constructionCost, 2),
        'costo_honorarios'       => round($feeCost, 2),
        'costo_operativo'        => round($operatingCost, 2),
        'costo_comercializacion' => round($commercCost, 2),
        'costo_impuestos'        => round($taxCost, 2),
        'total_costos'           => round($totalCosts, 2),
        'utilidad'               => round($profit, 2),
        'margen'                 => round($margin, 2),
        'van'                    => round($van, 2),
        'tir'                    => ($tir !== null) ? round($tir, 2) : null,
        'viabilidad'             => $color,
    ];
}

function npv(array $cashflow, float $rMonthly): float {
    $van = 0.0;
    foreach ($cashflow as $t => $cf) {
        $van += $cf / pow(1.0 + $rMonthly, $t);
    }
    return $van;
}

function tirAnual(array $cashflow): ?float {
    $low  = -0.9999 / 12.0;
    $high = 10.0   / 12.0;
    $vLow = npv($cashflow, $low);
    $vHigh = npv($cashflow, $high);
    if ($vLow * $vHigh > 0) return null;

    for ($i = 0; $i < 200; $i++) {
        $mid  = ($low + $high) / 2.0;
        $vMid = npv($cashflow, $mid);
        if (abs($vMid) < 0.001) break;
        if ($vLow * $vMid < 0) { $high = $mid; $vHigh = $vMid; }
        else                   { $low  = $mid; $vLow  = $vMid; }
    }

    $tirM = ($low + $high) / 2.0;
    return (pow(1.0 + $tirM, 12.0) - 1.0) * 100.0;
}

function semaforo(float $van, ?float $tir, float $margin): string {
    if ($van > 0 && $tir !== null && $tir >= 20.0 && $margin >= 25.0) return 'verde';
    if ($van > 0 && $tir !== null && $tir >= 10.0 && $margin >= 15.0) return 'amarillo';
    return 'rojo';
}

// ---- Calcular los 3 escenarios ----
$baseArgs = [$landPrice, 0.0, $buildableM2, $salePriceM2, $constructionCostM2,
             $feePercent, $operatingPercent, $commercPercent, $notarialPercent,
             $taxPercent, $discountRate, $constructionMonths, $salesStartMonth, $projectMonths];

$scenarios = [
    'efectivo' => calcScenario($landPrice, 1.0, $buildableM2, $salePriceM2, $constructionCostM2,
                    $feePercent, $operatingPercent, $commercPercent, $notarialPercent,
                    $taxPercent, $discountRate, $constructionMonths, $salesStartMonth, $projectMonths),
    'mixta'    => calcScenario($landPrice, 0.7, $buildableM2, $salePriceM2, $constructionCostM2,
                    $feePercent, $operatingPercent, $commercPercent, $notarialPercent,
                    $taxPercent, $discountRate, $constructionMonths, $salesStartMonth, $projectMonths),
    'canje'    => calcScenario($landPrice, 0.0, $buildableM2, $salePriceM2, $constructionCostM2,
                    $feePercent, $operatingPercent, $commercPercent, $notarialPercent,
                    $taxPercent, $discountRate, $constructionMonths, $salesStartMonth, $projectMonths),
];

// ---- Guardar en BD si se solicita ----
$projectId = null;
if ($save) {
    $e         = $scenarios['efectivo'];
    $breakEven = ($buildableM2 > 0) ? $e['total_costos'] / $buildableM2 : 0.0;
    $roi       = ($e['total_costos'] > 0) ? ($e['utilidad'] / $e['total_costos']) * 100.0 : 0.0;
    $paramsNote = json_encode([
        'fee_percent'               => $feePercent,
        'operating_percent'         => $operatingPercent,
        'commercialization_percent' => $commercPercent,
        'notarial_percent'          => $notarialPercent,
        'tax_percent'               => $taxPercent,
        'discount_rate'             => $discountRate,
        'construction_months'       => $constructionMonths,
        'sales_start_month'         => $salesStartMonth,
        'project_months'            => $projectMonths,
    ]);

    $stmt = $pdo->prepare(
        'INSERT INTO projects
            (user_id, name, location, type, total_area_m2, buildable_area_m2,
             land_cost, construction_cost_m2, sale_price_m2, other_costs, status, notes)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $userId, $projectName, $lotAddress, $projectType,
        $lotM2, $buildableM2, $landPrice, $constructionCostM2, $salePriceM2,
        round($e['costo_honorarios'] + $e['costo_operativo'] + $e['costo_comercializacion'] + $e['costo_impuestos'], 2),
        'en_analisis',
        $paramsNote,
    ]);
    $projectId = (int) $pdo->lastInsertId();

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
    $stmt->execute([$projectId, $e['total_costos'], $e['ingresos_brutos'], $e['utilidad'], $roi, $breakEven]);
}

echo json_encode([
    'success'    => true,
    'project_id' => $projectId,
    'input' => [
        'lot_address'         => $lotAddress,
        'lot_m2'              => $lotM2,
        'land_price'          => $landPrice,
        'sale_price_m2'       => $salePriceM2,
        'buildable_m2'        => $buildableM2,
        'construction_cost_m2'=> $constructionCostM2,
    ],
    'params' => [
        'fee_percent'               => $feePercent,
        'operating_percent'         => $operatingPercent,
        'commercialization_percent' => $commercPercent,
        'notarial_percent'          => $notarialPercent,
        'tax_percent'               => $taxPercent,
        'discount_rate'             => $discountRate,
        'construction_months'       => $constructionMonths,
        'sales_start_month'         => $salesStartMonth,
        'project_months'            => $projectMonths,
    ],
    'scenarios' => $scenarios,
]);
