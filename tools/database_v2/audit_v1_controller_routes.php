<?php
/**
 * No-DB controller rollout inventory: every registered legacy voucher /
 * booking / stock / labour write route that MUST be migrated or explicitly
 * preserved before a production cutover can be signed off.
 *
 * Does not modify a route, model, migration, business row or approval state.
 */
declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$legacy = [
    'App\\Http\\Controllers\\JournalVoucherController',
    'App\\Http\\Controllers\\CommissionVoucherController',
    'App\\Http\\Controllers\\Finance\\VoucherController',
    'App\\Http\\Controllers\\BookingController',
    'App\\Http\\Controllers\\StockController',
    'App\\Http\\Controllers\\LabourController',
    'App\\Http\\Controllers\\Finance\\PaymentClearanceController',
];
$counts = [];
$endpoints = [];
foreach ($legacy as $name) {
    $counts[$name] = ['reads' => 0, 'writes' => 0];
}
foreach (Route::getRoutes() as $route) {
    $action = $route->getActionName();
    if (!str_contains($action, '@')) {
        continue;
    }
    [$controller, $method] = explode('@', $action, 2);
    if (!isset($counts[$controller])) {
        continue;
    }
    $verbs = array_values(array_diff($route->methods(), ['HEAD']));
    $writes = array_intersect($verbs, ['POST', 'PUT', 'PATCH', 'DELETE']) !== [];
    $counts[$controller][$writes ? 'writes' : 'reads']++;
    if ($writes) {
        $classification = match (true) {
            in_array($method, [
                'GetComment', 'checkNewVoucherNumber', 'customer_report_display',
                'fatch_voucher', 'getCustomerPlots', 'getCustomers',
                'getCustomersbyPlot', 'getPlotCustomer', 'getPlots',
                'attendanceReport', 'checkValidate', 'personAttendanceReport',
                'printAttendanceReport', 'printPersonReport', 'printSiteReport',
            ], true) => 'POST_READ_CANDIDATE_REQUIRES_BODY_REVIEW',
            str_starts_with(strtolower($method), 'approve'),
            str_starts_with(strtolower($method), 'reject'),
            $method === 'updatePendingBankPaymentStatus' => 'APPROVAL_STATE_TRANSITION',
            default => 'WRITE_CANDIDATE_REQUIRES_BODY_REVIEW',
        };
        $endpoints[] = [
            'classification' => $classification,
            'controller' => $controller,
            'method' => $method,
            'route' => implode(',', $verbs) . ' ' . $route->uri(),
        ];
    }
}
usort($endpoints, static fn (array $a, array $b): int =>
    strcmp($a['controller'] . '@' . $a['method'] . $a['route'],
        $b['controller'] . '@' . $b['method'] . $b['route'])
);
$totals = ['reads' => 0, 'writes' => 0];
foreach ($counts as $counter) {
    $totals['reads'] += $counter['reads'];
    $totals['writes'] += $counter['writes'];
}
if ($totals['writes'] < 15) {
    throw new RuntimeException('V2_LEGACY_CONTROLLER_AUDIT_UNEXPECTEDLY_EMPTY');
}
$categories = array_count_values(array_column($endpoints, 'classification'));
ksort($categories);
echo 'V2_LEGACY_ROUTE_CLASSIFICATION: ' . json_encode($categories, JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'V2_LEGACY_CONTROLLER_ROLLOUT_AUDIT: '
    . count($legacy) . ' target controllers; '
    . $totals['writes'] . ' registered V1 mutating routes still require '
    . 'real workflow mapping and acceptance; '
    . $totals['reads'] . ' read routes; NO CUTOVER.' . PHP_EOL;
foreach ($endpoints as $e) {
    echo 'V2_LEGACY_WRITE_ROUTE_UNMIGRATED: [' . $e['classification'] . '] ' . $e['controller'] . '@'
        . $e['method'] . ' ' . $e['route'] . PHP_EOL;
}
