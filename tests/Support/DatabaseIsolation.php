<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;

final class DatabaseIsolation
{
    public static function guard(Application $app): void
    {
        // Run after environment loading, before cached config or providers can
        // select a real database. Preserve any cache file for the operator.
        $app->beforeBootstrapping(LoadConfiguration::class, function ($app) {
            if ($app->configurationIsCached()) {
                throw new \RuntimeException('Tests refuse cached application configuration. No database accessed.');
            }
        });
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) {
            self::assertSafe($app['config']->all());
            // Explicit DB::connection('mysql') must also fail before connecting.
            $app['config']->set('database.connections', [
                'sqlite' => $app['config']->get('database.connections.sqlite'),
            ]);
        });
    }

    public static function assertSafe(array $config): void
    {
        $database = $config['database'] ?? [];
        $sqlite = $database['connections']['sqlite'] ?? [];
        if (($config['app']['env'] ?? null) !== 'testing'
            || ($database['default'] ?? null) !== 'sqlite'
            || ($sqlite['driver'] ?? null) !== 'sqlite'
            || ($sqlite['database'] ?? null) !== ':memory:'
            || !in_array($sqlite['url'] ?? null, [null, ''], true)
            || array_key_exists('read', $sqlite)
            || array_key_exists('write', $sqlite)) {
            throw new \RuntimeException('Tests require testing environment and SQLite :memory: without URL/read/write overrides.');
        }
    }
}
