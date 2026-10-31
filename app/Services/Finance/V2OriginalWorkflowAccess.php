<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\{Auth, DB, Gate};

final class V2OriginalWorkflowAccess
{
    public static function selected(): bool
    {
        $connection = config('database.default');
        return str_starts_with((string) config('database.connections.'.$connection.'.database'), 'bluemarketing_v2_')
            || (in_array(config('app.env'), ['local', 'testing'], true) && config('finance.v2_pilot_api_enabled'));
    }

    public static function enabled(): bool
    {
        return in_array(config('app.env'), ['local', 'testing'], true)
            && config('finance.v2_pilot_api_enabled') && config('finance.v2_pilot_write_enabled');
    }

    public static function authorize(int $projectId): void
    {
        abort_unless(self::enabled(), 409, 'Isolated financial workflow is disabled.');
        V2IsolatedConnectionGuard::assertAllowed();
        $user = Auth::user();
        abort_unless($user && $projectId > 0 && Gate::has('v2-finance-post'), 403);
        Gate::authorize('v2-finance-post');
        abort_unless($user->hasRole('superadmin') || (int) $user->project_id === $projectId, 403);
        abort_unless(DB::table('projects')->where('id', $projectId)->where('is_active', 1)->exists(), 403);
    }

    public static function authorizeRead(int $projectId): void
    {
        abort_unless(in_array(config('app.env'), ['local', 'testing'], true) && config('finance.v2_pilot_api_enabled'), 409);
        V2IsolatedConnectionGuard::assertAllowed();
        $user = Auth::user();
        abort_unless($user && $projectId > 0 && Gate::has('v2-finance-read'), 403);
        Gate::authorize('v2-finance-read');
        abort_unless($user->hasRole('superadmin') || (int) $user->project_id === $projectId, 403);
        abort_unless(DB::table('projects')->where('id', $projectId)->where('is_active', 1)->exists(), 403);
    }
}
