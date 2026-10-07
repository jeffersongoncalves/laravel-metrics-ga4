<?php

namespace JeffersonGoncalves\MetricsGA4\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\MetricsGA4\Exceptions\AuthenticationException;

/**
 * OAuth 2.0 access token for a Google service account (JWT bearer grant), cached until shortly before it expires.
 *
 * @see https://developers.google.com/identity/protocols/oauth2/service-account#httprest
 */
class ServiceAccountToken
{
    public const SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

    private const DEFAULT_TOKEN_URI = 'https://oauth2.googleapis.com/token';

    public function __construct(
        private readonly string $credentialsJson,
    ) {}

    public function get(): string
    {
        $credentials = $this->credentials();

        // keyed by account + key id so rotating the key in the settings never reuses a stale token
        $cacheKey = 'metrics-ga4:token:'.sha1($credentials['client_email'].'|'.($credentials['private_key_id'] ?? ''));

        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()->post($credentials['token_uri'] ?? self::DEFAULT_TOKEN_URI, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $this->assertion($credentials),
        ]);

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw AuthenticationException::tokenRequestFailed((string) ($response->json('error_description') ?? $response->json('error') ?? $response->body()));
        }

        Cache::put($cacheKey, $token, max(60, (int) $response->json('expires_in', 3600) - 120));

        return $token;
    }

    /**
     * @param  array{client_email: string, private_key: string, token_uri?: string}  $credentials
     */
    public function assertion(array $credentials, ?int $now = null): string
    {
        $now ??= time();

        $segments = [
            self::base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            self::base64Url((string) json_encode([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => $credentials['token_uri'] ?? self::DEFAULT_TOKEN_URI,
                'iat' => $now,
                'exp' => $now + 3600,
            ])),
        ];

        $key = openssl_pkey_get_private($credentials['private_key']);

        if ($key === false || ! openssl_sign(implode('.', $segments), $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw AuthenticationException::invalidPrivateKey();
        }

        $segments[] = self::base64Url($signature);

        return implode('.', $segments);
    }

    /**
     * @return array{client_email: string, private_key: string, private_key_id?: string, token_uri?: string}
     */
    public function credentials(): array
    {
        if (trim($this->credentialsJson) === '') {
            throw AuthenticationException::missingCredentials();
        }

        $credentials = json_decode($this->credentialsJson, true);

        if (! is_array($credentials)
            || ! is_string($credentials['client_email'] ?? null)
            || ! is_string($credentials['private_key'] ?? null)) {
            throw AuthenticationException::invalidCredentials();
        }

        /** @var array{client_email: string, private_key: string, private_key_id?: string, token_uri?: string} $credentials */
        return $credentials;
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
