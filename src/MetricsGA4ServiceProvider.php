<?php

namespace JeffersonGoncalves\MetricsGA4;

use Illuminate\Support\Facades\Config;
use JeffersonGoncalves\MetricsGA4\Auth\ServiceAccountToken;
use JeffersonGoncalves\MetricsGA4\Settings\GA4Settings;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MetricsGA4ServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('metrics-ga4');
    }

    public function packageRegistered(): void
    {
        Config::set('settings.settings', array_merge(
            Config::get('settings.settings', []),
            [GA4Settings::class]
        ));

        $this->app->singleton(GA4Client::class, function () {
            $settings = app(GA4Settings::class);

            return new GA4Client(
                token: new ServiceAccountToken($settings->service_account_json),
                propertyId: $settings->property_id,
            );
        });

        $this->app->singleton('metrics-ga4', fn ($app) => new GA4($app->make(GA4Client::class)));

        $this->app->alias('metrics-ga4', GA4::class);
    }

    public function packageBooted(): void
    {
        $migrationsPath = __DIR__.'/../database/settings';

        Config::set('settings.migrations_paths', array_merge(
            [$migrationsPath],
            Config::get('settings.migrations_paths', [])
        ));

        $this->publishes([
            $migrationsPath => database_path('settings'),
        ], 'metrics-ga4-settings-migrations');
    }
}
