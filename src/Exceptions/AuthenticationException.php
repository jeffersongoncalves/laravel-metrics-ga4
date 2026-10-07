<?php

namespace JeffersonGoncalves\MetricsGA4\Exceptions;

class AuthenticationException extends GA4Exception
{
    public static function missingCredentials(): self
    {
        return new self('Google Analytics service account credentials are not configured.');
    }

    public static function invalidCredentials(): self
    {
        return new self('The Google Analytics service account credentials are not a valid service account JSON key (client_email and private_key are required).');
    }

    public static function invalidPrivateKey(): self
    {
        return new self('The private_key of the Google Analytics service account could not be used to sign the token request.');
    }

    public static function tokenRequestFailed(string $reason): self
    {
        return new self("Google rejected the service account token request: {$reason}", 401);
    }

    public static function forbidden(): self
    {
        return new self('The service account has no access to this Google Analytics property. Add its client_email as a Viewer of the property.', 403);
    }

    public static function missingPropertyId(): self
    {
        return new self('Google Analytics property id is not configured.');
    }
}
