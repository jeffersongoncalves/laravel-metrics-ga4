<?php

namespace JeffersonGoncalves\MetricsGA4\Settings;

use Spatie\LaravelSettings\Settings;

class GA4Settings extends Settings
{
    /** Numeric GA4 property id (Admin > Property details), with or without the "properties/" prefix. */
    public string $property_id;

    /** Full JSON key of a service account that is a Viewer of the property. Stored encrypted. */
    public string $service_account_json;

    public static function group(): string
    {
        return 'metrics-ga4';
    }

    /** @return list<string> */
    public static function encrypted(): array
    {
        return ['service_account_json'];
    }
}
