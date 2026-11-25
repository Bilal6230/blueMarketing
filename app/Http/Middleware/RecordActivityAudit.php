<?php

namespace App\Http\Middleware;

use Closure;
use App\Support\Audit\ActivityAuditCapture;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

final class RecordActivityAudit
{
    /**
     * Log authenticated mutations after the response was produced. Never log
     * credentials, cookies, query strings, POST body or attachments.
     * Transactional row capture allows selected accounting amounts only.
     * Finance posting and authorization remain handled by their existing code.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Capture only authenticated admin mutations; avoid listeners on reads.
        $capture = null;
        $captureToken = null;
        if ($request->user() && in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $capture = new ActivityAuditCapture();
            $capture->start();
            // The same connection is used by transactional JV/ledger writes.
            // Database triggers store verified row snapshots only under this
            // server-generated token; the snapshots roll back with the write.
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                try {
                    $captureToken = (string) Str::uuid();
                    DB::statement('SET @activity_audit_token = ?', [$captureToken]);
                } catch (\Throwable $exception) {
                    $captureToken = null;
                    Log::error('Audit row capture token could not be activated', [
                        'exception' => get_class($exception),
                    ]);
                }
            }
        }
        try {
            $response = $next($request);
        } finally {
            if ($capture) $capture->stop();
            if ($captureToken) {
                try {
                    DB::statement('SET @activity_audit_token = NULL');
                } catch (\Throwable $exception) {
                    Log::error('Audit row capture token could not be cleared', [
                        'exception' => get_class($exception),
                    ]);
                }
            }
        }

        $actor = $request->user();
        $method = strtoupper($request->method());
        $route = $request->route();
        $routeName = $route ? $route->getName() : null;
        if (!$actor || !in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            || !$route || str_starts_with((string) $routeName, 'activity-audit.')
            || $response->getStatusCode() >= 400) {
            return $response;
        }

        // An audit schema migration is required before recording can begin.
        try {
            if (!Schema::hasTable('activity_audits')) {
                return $response;
            }
            // POST is commonly used for read-only AJAX lookups in this ERP.
            // Only persist an activity when a business write was observed,
            // or transaction-backed trigger rows are waiting for correlation.
            $hasCapturedRows = $captureToken && Schema::hasTable('activity_audit_impacts')
                && DB::table('activity_audit_impacts')
                    ->where('request_token', $captureToken)
                    ->whereNull('activity_audit_id')->exists();
            if (!$hasCapturedRows && (!$capture || !$capture->hasObservedWrites())) {
                return $response;
            }
            DB::transaction(function () use ($actor, $method, $routeName, $request, $response, $capture, $captureToken): void {
            $auditId = DB::table('activity_audits')->insertGetId([
                'actor_id' => (int) $actor->getAuthIdentifier(),
                'action' => $method,
                'route_name' => $routeName ? mb_substr((string) $routeName, 0, 160) : null,
                'request_path' => mb_substr($request->path(), 0, 255),
                'response_status' => $response->getStatusCode(),
                'ip_address' => $request->ip(),
                'request_id' => mb_substr((string) $request->header('X-Request-ID', ''), 0, 40) ?: null,
                'created_at' => now(),
            ]);
            if ($capture && Schema::hasTable('activity_audit_impacts')) {
                $capturedTables = [];
                if ($captureToken) {
                    // The trigger rows already committed (or disappeared
                    // with the rolled-back source transaction).
                    $capturedTables = DB::table('activity_audit_impacts')
                        ->where('request_token', $captureToken)
                        ->whereNull('activity_audit_id')
                        ->distinct()->pluck('table_name')->all();
                    DB::table('activity_audit_impacts')
                        ->where('request_token', $captureToken)
                        ->whereNull('activity_audit_id')
                        ->update(['activity_audit_id' => $auditId, 'request_token' => null]);
                }
                $impacts = array_values(array_filter($capture->impacts(), function (array $impact) use ($capturedTables): bool {
                    return $impact['capture_type'] !== 'eloquent_row' ||
                        !in_array($impact['table_name'], $capturedTables, true);
                }));
                foreach (array_chunk($impacts, 75) as $items) {
                    DB::table('activity_audit_impacts')->insert(array_map(
                        fn (array $impact): array => $impact + [
                            'activity_audit_id' => $auditId,
                            'created_at' => now(),
                        ], $items
                    ));
                }
            }
            });
        } catch (\Throwable $exception) {
            // Audit storage failures must not replay a successfully committed
            // financial transaction, but must be visible to operators.
            Log::error('Activity audit could not be written', [
                'exception' => get_class($exception),
                'route' => $routeName,
                'actor_id' => (int) $actor->getAuthIdentifier(),
            ]);
        }
        return $response;
    }
}
