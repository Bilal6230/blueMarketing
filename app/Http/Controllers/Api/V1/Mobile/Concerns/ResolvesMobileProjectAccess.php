<?php

namespace App\Http\Controllers\Api\V1\Mobile\Concerns;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait ResolvesMobileProjectAccess
{
    protected function allowedProjects(User $user): Collection
    {
        $query = Project::query()
            ->select('id', 'project')
            ->where('is_active', 1)
            ->orderBy('project');

        if ($user->hasRole('superadmin')) {
            return $query->get();
        }

        if (empty($user->project_id)) {
            return collect();
        }

        // TODO: Replace this with a dedicated multi-project assignment lookup if one exists.
        return $query->where('id', $user->project_id)->get();
    }

    protected function resolveSelectedProjectId(Request $request, User $user): ?int
    {
        $projects = $this->allowedProjects($user);
        $requestedProjectId = $request->input('project_id');

        if ($requestedProjectId === null || $requestedProjectId === '') {
            return $projects->count() === 1 ? (int) $projects->first()->id : null;
        }

        $projectId = (int) $requestedProjectId;

        if (!$projects->contains('id', $projectId)) {
            throw new AuthorizationException('You do not have permission to perform this action.');
        }

        return $projectId;
    }
}
