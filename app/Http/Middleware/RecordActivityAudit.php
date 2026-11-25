<?php

namespace App\Http\Middleware;

use Closure;
use App\Support\Audit\ActivityAuditCapture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

final class RecordActivityAudit
{
    /**
     * Log authenticated mutations after the response was produced. Never log
     * credentials, cookies, query strings, POST body, attachments or amounts.
     * Finance posting and authorization remain handled by their existing code.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Capture only authenticated admin mutations; avoid listeners on reads.
        $capture = null;
        if ($request->user() && in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $capture = new ActivityAuditCapture();
            $capture->start();
        }
        try {
            $response = $next($request);
        } finally {
            if ($capture) $capture->stop();
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
                foreach (array_chunk($capture->impacts(), 75) as $items) {
                    DB::table('activity_audit_impacts')->insert(array_map(
                        fn (array $impact): array => $impact + [
                            'activity_audit_id' => $auditId,
                            'created_at' => now(),
                        ], $items
                    ));
                }
            }
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
