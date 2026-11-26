<?php

namespace App\Support;

use App\Models\Project;

/**
 * Project-scoped branding for authenticated ERP screens and print documents.
 * Landing and login branding continue to use the global Setting app_logo.
 */
final class ProjectBranding
{
    private static array $projectCache = [];

    public static function project($project = null): ?Project
    {
        if ($project instanceof Project) {
            return $project;
        }
        if ($project !== null && !is_numeric($project)) {
            return null;
        }
        $id = $project === null ? (int) getSelectedTown() : (int) $project;
        if ($id < 1) {
            return null;
        }
        if (!array_key_exists($id, self::$projectCache)) {
            self::$projectCache[$id] = Project::query()->find($id);
        }
        return self::$projectCache[$id];
    }

    public static function logoPath($project = null): string
    {
        $project = self::project($project);
        $filename = trim((string) ($project?->logo ?? ''));
        if ($filename !== '') {
            // Existing rows store a filename; older rows may store images/logo/name.
            // Always resolve from the controlled project-logo directory.
            $basename = basename(str_replace('\\', '/', $filename));
            if ($basename !== '' && $basename !== '.' && $basename !== '..') {
                $relative = 'images/logo/'.$basename;
                if (is_file(public_path($relative))) {
                    return $relative;
                }
            }
        }
        // A missing project logo must not silently display the global company logo.
        return 'images/logo/project-placeholder.svg';
    }

    public static function logoUrl($project = null): string
    {
        return asset(self::logoPath($project));
    }

    public static function title($project = null): string
    {
        return trim((string) (self::project($project)?->project ?? '')) ?: 'Project';
    }
}
