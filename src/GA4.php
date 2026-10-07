<?php

namespace JeffersonGoncalves\MetricsGA4;

use JeffersonGoncalves\MetricsGA4\Data\AggregateStats;
use JeffersonGoncalves\MetricsGA4\Data\StatsRow;

/**
 * Google Analytics 4 Data API with the same shape as the other jeffersongoncalves metrics packages
 * (visitors, visits, pageviews, bounce_rate, visit_duration).
 */
class GA4
{
    /** Package metric name => GA4 metric name. */
    public const METRICS = [
        'visitors' => 'activeUsers',
        'visits' => 'sessions',
        'pageviews' => 'screenPageViews',
        'bounce_rate' => 'bounceRate',
        'visit_duration' => 'averageSessionDuration',
    ];

    public function __construct(
        private readonly GA4Client $client,
    ) {}

    /**
     * Raw runReport call for anything the helpers below don't cover.
     *
     * @param  array<string, mixed>  $body
     * @return array<int|string, mixed>
     */
    public function runReport(array $body, ?string $propertyId = null): array
    {
        return $this->client->call('runReport', $body, $propertyId);
    }

    /**
     * Property-wide totals over the last $days days.
     *
     * @param  list<string>  $metrics  package metric names (see METRICS)
     */
    public function aggregate(array $metrics = [], int $days = 30, ?string $propertyId = null): AggregateStats
    {
        $rows = $this->report([], $metrics ?: array_keys(self::METRICS), $days, null, $propertyId);

        return AggregateStats::fromRow($rows[0] ?? null);
    }

    /**
     * Daily buckets over the last $days days; labels are Y-m-d.
     *
     * @param  list<string>  $metrics
     * @return list<StatsRow>
     */
    public function timeseries(int $days = 30, array $metrics = [], ?string $propertyId = null): array
    {
        $rows = $this->report(['date'], $metrics ?: ['visitors', 'pageviews'], $days, null, $propertyId, [
            'orderBys' => [['dimension' => ['dimensionName' => 'date']]],
        ]);

        return array_map(fn (StatsRow $row): StatsRow => new StatsRow(
            label: preg_replace('/^(\d{4})(\d{2})(\d{2})$/', '$1-$2-$3', $row->label) ?? $row->label,
            dimensions: $row->dimensions,
            metrics: $row->metrics,
        ), $rows);
    }

    /**
     * Top values of any GA4 dimension, ordered by the first metric.
     *
     * @param  list<string>  $metrics
     * @return list<StatsRow>
     */
    public function breakdown(string $dimension, int $days = 30, array $metrics = [], int $limit = 100, ?string $propertyId = null): array
    {
        return $this->report([$dimension], $metrics ?: ['visitors', 'pageviews'], $days, $limit, $propertyId);
    }

    /** @return list<StatsRow> */
    public function pages(int $days = 30, int $limit = 100, ?string $propertyId = null): array
    {
        return $this->breakdown('pagePath', $days, ['visitors', 'pageviews'], $limit, $propertyId);
    }

    /** @return list<StatsRow> */
    public function sources(int $days = 30, int $limit = 100, ?string $propertyId = null): array
    {
        return $this->breakdown('sessionSource', $days, ['visitors', 'bounce_rate'], $limit, $propertyId);
    }

    /** @return list<StatsRow> */
    public function countries(int $days = 30, int $limit = 100, ?string $propertyId = null): array
    {
        return $this->breakdown('country', $days, ['visitors'], $limit, $propertyId);
    }

    /** @return list<StatsRow> */
    public function browsers(int $days = 30, int $limit = 100, ?string $propertyId = null): array
    {
        return $this->breakdown('browser', $days, ['visitors'], $limit, $propertyId);
    }

    /** @return list<StatsRow> */
    public function devices(int $days = 30, int $limit = 100, ?string $propertyId = null): array
    {
        return $this->breakdown('deviceCategory', $days, ['visitors'], $limit, $propertyId);
    }

    /**
     * Active users in the last 30 minutes (GA4 realtime window).
     */
    public function realtimeVisitors(?string $propertyId = null): int
    {
        $response = $this->client->call('runRealtimeReport', ['metrics' => [['name' => 'activeUsers']]], $propertyId);

        $rows = StatsRow::collectionFromResponse($response, ['activeUsers' => 'visitors']);

        return $rows === [] ? 0 : $rows[0]->visitors();
    }

    /**
     * @param  list<string>  $dimensions  GA4 dimension names
     * @param  list<string>  $metrics  package metric names
     * @param  array<string, mixed>  $extra
     * @return list<StatsRow>
     */
    private function report(array $dimensions, array $metrics, int $days, ?int $limit, ?string $propertyId, array $extra = []): array
    {
        $ga4Metrics = array_map(fn (string $metric): string => self::METRICS[$metric] ?? $metric, $metrics);

        $body = [
            // N days including today
            'dateRanges' => [['startDate' => $days <= 1 ? 'today' : ($days - 1).'daysAgo', 'endDate' => 'today']],
            'metrics' => array_map(fn (string $name): array => ['name' => $name], $ga4Metrics),
        ] + ($dimensions === [] ? [] : ['dimensions' => array_map(fn (string $name): array => ['name' => $name], $dimensions)]);

        if ($limit !== null) {
            $body['limit'] = $limit;
            $body['orderBys'] = [['metric' => ['metricName' => $ga4Metrics[0]], 'desc' => true]];
        }

        $response = $this->runReport($body + $extra, $propertyId);

        return StatsRow::collectionFromResponse($response, array_flip(self::METRICS));
    }
}
