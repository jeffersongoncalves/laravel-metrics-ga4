<?php

namespace JeffersonGoncalves\MetricsGA4\Facades;

use Illuminate\Support\Facades\Facade;
use JeffersonGoncalves\MetricsGA4\Data\AggregateStats;
use JeffersonGoncalves\MetricsGA4\Data\StatsRow;

/**
 * @method static array<int|string, mixed> runReport(array<string, mixed> $body, ?string $propertyId = null)
 * @method static AggregateStats aggregate(array<int, string> $metrics = [], int $days = 30, ?string $propertyId = null)
 * @method static list<StatsRow> timeseries(int $days = 30, array<int, string> $metrics = [], ?string $propertyId = null)
 * @method static list<StatsRow> breakdown(string $dimension, int $days = 30, array<int, string> $metrics = [], int $limit = 100, ?string $propertyId = null)
 * @method static list<StatsRow> pages(int $days = 30, int $limit = 100, ?string $propertyId = null)
 * @method static list<StatsRow> sources(int $days = 30, int $limit = 100, ?string $propertyId = null)
 * @method static list<StatsRow> countries(int $days = 30, int $limit = 100, ?string $propertyId = null)
 * @method static list<StatsRow> browsers(int $days = 30, int $limit = 100, ?string $propertyId = null)
 * @method static list<StatsRow> devices(int $days = 30, int $limit = 100, ?string $propertyId = null)
 * @method static int realtimeVisitors(?string $propertyId = null)
 *
 * @see \JeffersonGoncalves\MetricsGA4\GA4
 */
class GA4 extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'metrics-ga4';
    }
}
