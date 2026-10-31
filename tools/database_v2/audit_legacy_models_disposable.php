<?php
/**
 * Read-only inventory of ALL app/Models root-level legacy Eloquent classes
 * against the real V2 57-source baseline in disposable MySQL CI.
 * Missing legacy fields are reported, NEVER auto-created in historical SQL.
 */
declare(strict_types=1);

use App\Services\Finance\V2HistoricalImportTargetGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$settings = config('database.connections.' . config('database.default'));
if (!is_array($settings)) {
    throw new RuntimeException('V2_LEGACY_MODEL_AUDIT_CONFIG_MISSING');
}
V2HistoricalImportTargetGuard::assertAllowed($settings, (string) config('app.env'), true);

$issues = [];
$audited = 0;
$mapped = 0;
foreach (glob(app_path('Models/*.php')) ?: [] as $file) {
    $name = pathinfo($file, PATHINFO_FILENAME);
    $class = 'App\\Models\\' . $name;
    if (!class_exists($class)) {
        $issues[$name] = ['MODEL_CLASS_NAME_DRIFT'];
        continue;
    }
    $model = new $class();
    if (!$model instanceof Model) {
        $issues[$name] = ['NOT_ELOQUENT_MODEL'];
        continue;
    }
    $audited++;
    $table = $model->getTable();
    if (!Schema::hasTable($table)) {
        $issues[$name] = ['TABLE_NOT_IN_PINNED_V2_BASELINE:' . $table];
        continue;
    }
    $mapped++;
    $physical = Schema::getColumnListing($table);
    $missing = array_values(array_diff($model->getFillable(), $physical));
    $badCasts = array_values(array_diff(array_keys($model->getCasts()), $physical));
    $flags = [];
    if ($missing !== []) {
        $flags[] = 'MISSING_FILLABLE:' . implode(',', $missing);
    }
    if ($badCasts !== []) {
        $flags[] = 'MISSING_CAST_COLUMN:' . implode(',', $badCasts);
    }
    if ($flags !== []) {
        $issues[$name] = $flags;
    }
}
ksort($issues);
if ($audited < 40 || $mapped < 35) {
    throw new RuntimeException('V2_LEGACY_MODEL_AUDIT_UNEXPECTED_COVERAGE');
}
echo 'V2_LEGACY_MODEL_INVENTORY: audited=' . $audited
    . ', physical_source_models=' . $mapped
    . ', compatibility_findings=' . count($issues)
    . ', no database writes.' . PHP_EOL;
foreach ($issues as $className => $reasons) {
    echo 'V2_MODEL_COMPATIBILITY:' . $className . ': ' . implode(' | ', $reasons) . PHP_EOL;
}
