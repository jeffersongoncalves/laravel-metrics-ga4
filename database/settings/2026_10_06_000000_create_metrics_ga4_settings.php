<?php

use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('metrics-ga4', function (SettingsBlueprint $blueprint): void {
            $blueprint->add('property_id', env('GA4_PROPERTY_ID', ''));
            $blueprint->addEncrypted('service_account_json', env('GA4_SERVICE_ACCOUNT_JSON', ''));
        });
    }
};
