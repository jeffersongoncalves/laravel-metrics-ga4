<?php

namespace JeffersonGoncalves\MetricsGA4\Exceptions;

class RateLimitException extends GA4Exception
{
    public static function exceeded(): self
    {
        return new self('Google Analytics Data API quota exceeded. Try again later.', 429);
    }
}
