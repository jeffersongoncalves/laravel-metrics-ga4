<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\MetricsGA4\Exceptions\AuthenticationException;
use JeffersonGoncalves\MetricsGA4\Exceptions\RateLimitException;
use JeffersonGoncalves\MetricsGA4\Facades\GA4;
use JeffersonGoncalves\MetricsGA4\Settings\GA4Settings;

const REPORT_URL = 'analyticsdata.googleapis.com/v1beta/properties/123456789:runReport';

/**
 * @param  list<string>  $dimensions
 * @param  list<string>  $metrics
 * @param  list<array{0: list<string>, 1: list<string|int|float>}>  $rows
 */
function ga4Report(array $dimensions, array $metrics, array $rows): array
{
    return [
        'dimensionHeaders' => array_map(fn ($name) => ['name' => $name], $dimensions),
        'metricHeaders' => array_map(fn ($name) => ['name' => $name, 'type' => 'TYPE_INTEGER'], $metrics),
        'rows' => array_map(fn ($row) => [
            'dimensionValues' => array_map(fn ($value) => ['value' => (string) $value], $row[0]),
            'metricValues' => array_map(fn ($value) => ['value' => (string) $value], $row[1]),
        ], $rows),
    ];
}

beforeEach(fn () => fakeAccessToken());

it('maps the GA4 totals onto the package metric names', function (): void {
    Http::fake([REPORT_URL => Http::response(ga4Report([], [
        'activeUsers', 'sessions', 'screenPageViews', 'bounceRate', 'averageSessionDuration',
    ], [[[], [120, 150, 340, '0.415', '95.5']]]))]);

    $stats = GA4::aggregate();

    expect($stats->visitors())->toBe(120)
        ->and($stats->visits())->toBe(150)
        ->and($stats->pageviews())->toBe(340)
        ->and($stats->bounceRate())->toBe(41.5)
        ->and($stats->visitDuration())->toBe(95.5);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-access-token')
        && $request['dateRanges'] === [['startDate' => '29daysAgo', 'endDate' => 'today']]
        && array_column($request['metrics'], 'name') === ['activeUsers', 'sessions', 'screenPageViews', 'bounceRate', 'averageSessionDuration']);
});

it('returns a daily timeseries with Y-m-d labels', function (): void {
    Http::fake([REPORT_URL => Http::response(ga4Report(['date'], ['activeUsers', 'screenPageViews'], [
        [['20261005'], [10, 25]],
        [['20261006'], [12, 30]],
    ]))]);

    $rows = GA4::timeseries(days: 7);

    expect(array_map(fn ($row) => $row->label, $rows))->toBe(['2026-10-05', '2026-10-06'])
        ->and($rows[1]->visitors())->toBe(12)
        ->and($rows[1]->pageviews())->toBe(30);

    Http::assertSent(fn ($request) => $request['orderBys'] === [['dimension' => ['dimensionName' => 'date']]]
        && $request['dateRanges'][0]['startDate'] === '6daysAgo');
});

it('builds top-N breakdowns ordered by the first metric', function (): void {
    Http::fake([REPORT_URL => Http::response(ga4Report(['pagePath'], ['activeUsers', 'screenPageViews'], [
        [['/'], [50, 80]],
        [['/pricing'], [20, 22]],
    ]))]);

    $pages = GA4::pages(limit: 10);

    expect($pages)->toHaveCount(2)
        ->and($pages[0]->label)->toBe('/')
        ->and($pages[0]->visitors())->toBe(50);

    Http::assertSent(fn ($request) => $request['limit'] === 10
        && $request['dimensions'] === [['name' => 'pagePath']]
        && $request['orderBys'] === [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]]);
});

it('reads realtime active users', function (): void {
    Http::fake(['analyticsdata.googleapis.com/v1beta/properties/123456789:runRealtimeReport' => Http::response(ga4Report([], ['activeUsers'], [[[], [7]]]))]);

    expect(GA4::realtimeVisitors())->toBe(7);
});

it('accepts a property id with the properties/ prefix', function (): void {
    Http::fake(['analyticsdata.googleapis.com/v1beta/properties/42:runReport' => Http::response(ga4Report([], ['activeUsers'], []))]);

    expect(GA4::aggregate(['visitors'], propertyId: 'properties/42')->visitors())->toBe(0);
});

it('explains a property the service account cannot read', function (): void {
    Http::fake([REPORT_URL => Http::response(['error' => ['message' => 'User does not have sufficient permissions']], 403)]);

    GA4::aggregate();
})->throws(AuthenticationException::class, 'Viewer of the property');

it('reports quota errors', function (): void {
    Http::fake([REPORT_URL => Http::response([], 429)]);

    GA4::aggregate();
})->throws(RateLimitException::class);

it('stores the service account key encrypted', function (): void {
    $payload = DB::table('settings')->where('name', 'service_account_json')->value('payload');

    expect($payload)->not->toContain('client_email')
        ->and(app(GA4Settings::class)->service_account_json)->toContain('client_email');
});
