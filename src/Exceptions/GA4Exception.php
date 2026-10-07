<?php

namespace JeffersonGoncalves\MetricsGA4\Exceptions;

use Exception;

class GA4Exception extends Exception
{
    public static function fromResponse(int $statusCode, string $message): self
    {
        return new self("Google Analytics Data API error ({$statusCode}): {$message}", $statusCode);
    }
}
