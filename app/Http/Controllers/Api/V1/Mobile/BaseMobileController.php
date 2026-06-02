<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class BaseMobileController extends Controller
{
    protected function successResponse(string $message, $data = [], array $meta = []): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
            'meta' => (object) $meta,
        ]);
    }

    protected function errorResponse(string $errorKey, string $message, int $status, array $errors = []): JsonResponse
    {
        $payload = [
            'status' => false,
            'error_key' => $errorKey,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $payload['errors'] = (object) $errors;
        }

        return response()->json($payload, $status);
    }

    protected function transformUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
        ];
    }

    protected function transformProjects(Collection $projects): array
    {
        return $projects
            ->map(fn ($project) => [
                'id' => $project->id,
                'name' => $project->project,
            ])
            ->values()
            ->all();
    }

    protected function roleNames(User $user): array
    {
        return $user->getRoleNames()->values()->all();
    }

    protected function permissionNames(User $user): array
    {
        return $user->getAllPermissions()->pluck('name')->values()->all();
    }
}
