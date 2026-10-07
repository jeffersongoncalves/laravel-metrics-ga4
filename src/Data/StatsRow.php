<?php

namespace JeffersonGoncalves\MetricsGA4\Data;

class StatsRow
{
    /**
     * @param  array<string, string>  $dimensions
     * @param  array<string, int|float|null>  $metrics
     */
    public function __construct(
        public readonly string $label,
        public readonly array $dimensions = [],
        public readonly array $metrics = [],
    ) {}

    /**
     * Rows of a runReport/runRealtimeReport response, with GA4 metric names translated to package names.
     *
     * @param  array<int|string, mixed>  $response
     * @param  array<string, string>  $metricAliases  GA4 metric name => package metric name
     * @return list<self>
     */
    public static function collectionFromResponse(array $response, array $metricAliases = []): array
    {
        $dimensionNames = self::headerNames($response['dimensionHeaders'] ?? null);
        $metricNames = array_map(fn (string $name): string => $metricAliases[$name] ?? $name, self::headerNames($response['metricHeaders'] ?? null));

        $rows = [];

        foreach (is_array($response['rows'] ?? null) ? $response['rows'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $dimensions = [];
            foreach ($dimensionNames as $index => $name) {
                $dimensions[$name] = (string) ($row['dimensionValues'][$index]['value'] ?? '');
            }

            $metrics = [];
            foreach ($metricNames as $index => $name) {
                $metrics[$name] = self::normalize($name, $row['metricValues'][$index]['value'] ?? null);
            }

            $rows[] = new self(label: (string) (array_values($dimensions)[0] ?? ''), dimensions: $dimensions, metrics: $metrics);
        }

        return $rows;
    }

    public function metric(string $name, int|float|null $default = null): int|float|null
    {
        return $this->metrics[$name] ?? $default;
    }

    public function visitors(): int
    {
        return (int) $this->metric('visitors', 0);
    }

    public function pageviews(): int
    {
        return (int) $this->metric('pageviews', 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['label' => $this->label, 'dimensions' => $this->dimensions, 'metrics' => $this->metrics];
    }

    public static function normalize(string $metric, mixed $value): int|float|null
    {
        if (! is_numeric($value)) {
            return null;
        }

        // GA4 returns bounceRate as a 0..1 ratio; the package (like the other metrics packages) uses a percentage
        if ($metric === 'bounce_rate') {
            return round((float) $value * 100, 2);
        }

        return str_contains((string) $value, '.') || str_contains((string) $value, 'e') ? (float) $value : (int) $value;
    }

    /**
     * @return list<string>
     */
    private static function headerNames(mixed $headers): array
    {
        return array_values(array_map(
            fn (mixed $header): string => is_array($header) ? (string) ($header['name'] ?? '') : '',
            is_array($headers) ? $headers : [],
        ));
    }
}
