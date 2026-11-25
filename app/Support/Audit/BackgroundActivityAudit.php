<?php

namespace App\Support\Audit;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Explicitly attributed background/CLI mutation entrypoint.
 *
 * Does not hook every scheduled command: jobs must deliberately opt in with
 * a real initiating user ID. A failed transaction cannot produce activity
 * evidence. No new schema, tables, or anonymous pseudo-user identity.
 */
final class BackgroundActivityAudit
{
    public function run(int $actorId, string $source, Closure $work)
    {
        if ($actorId < 1 || !preg_match('/^[a-zA-Z0-9_.:-]{1,110}$/D', $source)) {
            throw new InvalidArgumentException('Background audit requires a valid actor ID and source identifier.');
        }
        if (!Schema::hasTable('activity_audits') || !Schema::hasTable('activity_audit_impacts')) {
            throw new RuntimeException('Activity Audit migrations must be applied before audited background writes.');
        }
        if (!DB::table('users')->where('id', $actorId)->exists()) {
            throw new InvalidArgumentException('Background audit actor must refer to an existing user.');
        }
        if (DB::transactionLevel() !== 0) {
            throw new RuntimeException('Background audit must own its outer transaction.');
        }

        $capture = new ActivityAuditCapture();
        $token = (string) Str::uuid();
        $hasToken = false;
        $capture->start();

        try {
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::statement('SET @activity_audit_token = ?', [$token]);
                $hasToken = true;
            }

            // Business mutation and matching row-trigger evidence commit
            // together; exceptions roll back both.
            $result = DB::transaction(static function () use ($work) {
                return $work();
            });

            $capture->stop();
            $recorded = $capture->hasObservedWrites();
            $capturedTables = [];
            if ($hasToken) {
                $capturedTables = DB::table('activity_audit_impacts')
                    ->where('request_token', $token)
                    ->whereNull('activity_audit_id')
                    ->distinct()->pluck('table_name')->all();
                $recorded = $recorded || $capturedTables !== [];
            }
            if (!$recorded) {
                return $result;
            }

            try {
                DB::transaction(function () use ($actorId, $source, $token, $hasToken, $capture, $capturedTables): void {
                    $auditId = DB::table('activity_audits')->insertGetId([
                        'actor_id' => $actorId,
                        'action' => 'JOB',
                        'route_name' => 'background:'.$source,
                        'request_path' => 'background/'.$source,
                        'response_status' => 200,
                        'ip_address' => null,
                        'request_id' => null,
                        'created_at' => now(),
                    ]);

                    if ($hasToken) {
                        DB::table('activity_audit_impacts')
                            ->where('request_token', $token)
                            ->whereNull('activity_audit_id')
                            ->update(['activity_audit_id' => $auditId, 'request_token' => null]);
                    }

                    $impacts = array_filter($capture->impacts(), static function (array $impact) use ($capturedTables): bool {
                        return $impact['capture_type'] !== 'eloquent_row'
                            || !in_array($impact['table_name'], $capturedTables, true);
                    });
                    foreach (array_chunk(array_values($impacts), 75) as $chunk) {
                        DB::table('activity_audit_impacts')->insert(array_map(
                            static fn (array $impact): array => $impact + [
                                'activity_audit_id' => $auditId,
                                'created_at' => now(),
                            ], $chunk
                        ));
                    }
                });
            } catch (Throwable $exception) {
                // A committed business write cannot be replayed here.
                // Report failure for operators without double-running the job.
                Log::error('Background activity audit storage failed', [
                    'exception' => get_class($exception),
                    'source' => $source,
                    'actor_id' => $actorId,
                ]);
            }

            return $result;
        } finally {
            $capture->stop();
            if ($hasToken) {
                try {
                    DB::statement('SET @activity_audit_token = NULL');
                } catch (Throwable $exception) {
                    Log::error('Background activity audit token could not be cleared', [
                        'exception' => get_class($exception),
                        'source' => $source,
                    ]);
                }
            }
        }
    }
}
