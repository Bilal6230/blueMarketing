<?php

namespace Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\DatabaseIsolation;

final class DatabaseIsolationTest extends TestCase
{
    private static function safeConfig(): array
    {
        return ['app' => ['env' => 'testing'], 'database' => [
            'default' => 'sqlite', 'connections' => [
                'sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'url' => ''],
                'mysql' => ['driver' => 'mysql', 'database' => 'bluenew'],
            ],
        ]];
    }

    public static function unsafeConfigurations(): array
    {
        $cases = [];
        foreach ([
            'production environment' => ['app.env', 'production'],
            'mysql default' => ['database.default', 'mysql'],
            'mysql driver' => ['database.connections.sqlite.driver', 'mysql'],
            'original database' => ['database.connections.sqlite.database', 'bluenew'],
            'staging database' => ['database.connections.sqlite.database', 'bluemarketing_l13_staging'],
            'URL override' => ['database.connections.sqlite.url', 'mysql://127.0.0.1/bluenew'],
            'read override' => ['database.connections.sqlite.read', ['database' => 'bluenew']],
            'write override' => ['database.connections.sqlite.write', ['database' => 'bluenew']],
        ] as $name => [$key, $value]) {
            $config = new Repository(self::safeConfig());
            $config->set($key, $value);
            $cases[$name] = [$config->all()];
        }
        return $cases;
    }

    #[DataProvider('unsafeConfigurations')]
    public function test_unsafe_configuration_is_rejected_without_connecting(array $config): void
    {
        $this->expectException(\RuntimeException::class);
        DatabaseIsolation::assertSafe($config);
    }

    public function test_guard_removes_nonmemory_connection_options_before_providers(): void
    {
        $app = new Application();
        $app->instance('config', new Repository(self::safeConfig()));
        DatabaseIsolation::guard($app);
        $app['events']->dispatch('bootstrapped: '.LoadConfiguration::class, [$app]);
        $this->assertSame(['sqlite'], array_keys($app['config']->get('database.connections')));
        $this->assertSame(':memory:', $app['config']->get('database.connections.sqlite.database'));
    }

    public function test_cached_configuration_is_refused_before_loading_or_connecting(): void
    {
        $app = $this->createPartialMock(Application::class, ['configurationIsCached']);
        $app->__construct();
        $app->expects($this->once())->method('configurationIsCached')->willReturn(true);
        DatabaseIsolation::guard($app);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tests refuse cached application configuration');
        $app['events']->dispatch('bootstrapping: '.LoadConfiguration::class, [$app]);
    }
}
