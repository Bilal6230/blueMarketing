<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->scoped('finance.original.accounts', function () {
            return \App\Services\Finance\V2OriginalWorkflowAccess::enabled()
                ? \App\Models\ProjectHeadSubhead::with(['headAccounting', 'subheadAccounting'])
                    ->where('project_id', (int) getSelectedTown())->orderBy('id')->get()
                : collect();
        });
    }

    public function boot()
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
