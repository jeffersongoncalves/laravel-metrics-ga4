<?php

namespace JeffersonGoncalves\MetricsGA4\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use JeffersonGoncalves\MetricsGA4\MetricsGA4ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\LaravelSettings\Migrations\SettingsMigrator;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
        $this->seedSettings();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelSettingsServiceProvider::class,
            MetricsGA4ServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUpDatabase(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group');
            $table->string('name');
            $table->boolean('locked')->default(false);
            $table->json('payload');
            $table->timestamps();
            $table->unique(['group', 'name']);
        });
    }

    protected function seedSettings(): void
    {
        $migrator = app(SettingsMigrator::class);

        $migrator->add('metrics-ga4.property_id', '123456789');
        $migrator->addEncrypted('metrics-ga4.service_account_json', (string) json_encode([
            'client_email' => 'metrics@example.iam.gserviceaccount.com',
            'private_key' => 'not-used-when-the-token-is-cached',
            'private_key_id' => 'test-key',
        ]));
    }
}
