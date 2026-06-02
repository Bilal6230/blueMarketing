<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Api\V1\Mobile\Concerns\ResolvesMobileProjectAccess;
use App\Models\Lead;
use Illuminate\Http\Request;

class MobileDashboardController extends BaseMobileController
{
    use ResolvesMobileProjectAccess;

    public function index(Request $request)
    {
        $request->validate([
            'project_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $selectedProjectId = $this->resolveSelectedProjectId($request, $user);
        $crmStats = null;

        if ($user->can('read lead')) {
            $crmStats = [
                'total_leads' => 0,
                'today_followups' => 0,
            ];

            if ($selectedProjectId !== null) {
                $crmStats['total_leads'] = Lead::where('project_id', $selectedProjectId)->count();
                $crmStats['today_followups'] = Lead::where('project_id', $selectedProjectId)
                    ->whereDate('follow_up', now()->toDateString())
                    ->count();
            }
        }

        return $this->successResponse('Dashboard loaded.', [
            'attendance' => null,
            'crm' => $crmStats,
            'stock' => null,
            'reports' => null,
            'approvals' => null,
        ], [
            'selected_project_id' => $selectedProjectId,
        ]);
    }
}
