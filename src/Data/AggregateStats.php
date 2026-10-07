<?php

namespace JeffersonGoncalves\MetricsGA4\Data;

class AggregateStats
{
    /**
     * @param  array<string, int|float|null>  $metrics
     */
    public function __construct(
        public readonly array $metrics = [],
    ) {}

    public static function fromRow(?StatsRow $row): self
    {
        return new self($row->metrics ?? []);
    }

    public function get(string $metric, int|float|null $default = null): int|float|null
    {
        return $this->metrics[$metric] ?? $default;
    }

    public function visitors(): int
    {
        return (int) $this->get('visitors', 0);
    }

    public function visits(): int
    {
        return (int) $this->get('visits', 0);
    }

    public function pageviews(): int
    {
        return (int) $this->get('pageviews', 0);
    }

    /** Percentage (0..100). */
    public function bounceRate(): float
    {
        return (float) $this->get('bounce_rate', 0);
    }

    /** Average session duration in seconds. */
    public function visitDuration(): float
    {
        return (float) $this->get('visit_duration', 0);
    }

    /**
     * @return array<string, int|float|null>
     */
    public function toArray(): array
    {
        return $this->metrics;
    }
}
