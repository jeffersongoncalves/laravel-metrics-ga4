<?php

use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\MetricsGA4\Auth\ServiceAccountToken;
use JeffersonGoncalves\MetricsGA4\Exceptions\AuthenticationException;

function serviceAccountJson(string $privateKey): string
{
    return (string) json_encode([
        'type' => 'service_account',
        'client_email' => 'ga4@project.iam.gserviceaccount.com',
        'private_key_id' => 'kid-1',
        'private_key' => $privateKey,
        'token_uri' => 'https://oauth2.googleapis.com/token',
    ]);
}

it('signs an RS256 JWT bearer assertion that verifies with the public key', function (): void {
    $pem = testPrivateKey();
    $token = new ServiceAccountToken(serviceAccountJson($pem));

    $jwt = $token->assertion($token->credentials(), now: 1_700_000_000);
    [$header, $payload, $signature] = explode('.', $jwt);

    $decode = fn (string $part): string => (string) base64_decode(strtr($part, '-_', '+/'));
    $claims = json_decode($decode($payload), true);
    $public = openssl_pkey_get_details(openssl_pkey_get_private($pem))['key'];

    expect(json_decode($decode($header), true))->toBe(['alg' => 'RS256', 'typ' => 'JWT'])
        ->and($claims['iss'])->toBe('ga4@project.iam.gserviceaccount.com')
        ->and($claims['scope'])->toBe(ServiceAccountToken::SCOPE)
        ->and($claims['aud'])->toBe('https://oauth2.googleapis.com/token')
        ->and($claims['exp'] - $claims['iat'])->toBe(3600)
        ->and(openssl_verify("{$header}.{$payload}", $decode($signature), $public, OPENSSL_ALGO_SHA256))->toBe(1);
});

it('exchanges the assertion for an access token and caches it', function (): void {
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.token', 'expires_in' => 3599])]);
    $token = new ServiceAccountToken(serviceAccountJson(testPrivateKey()));

    expect($token->get())->toBe('ya29.token')
        ->and($token->get())->toBe('ya29.token');

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
        && substr_count((string) $request['assertion'], '.') === 2);
});

it('reports Google rejecting the token request', function (): void {
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Invalid JWT Signature.'], 400)]);

    (new ServiceAccountToken(serviceAccountJson(testPrivateKey())))->get();
})->throws(AuthenticationException::class, 'Invalid JWT Signature.');

it('rejects missing or malformed credentials before calling Google', function (string $json, string $message): void {
    Http::fake();

    expect(fn () => (new ServiceAccountToken($json))->get())->toThrow(AuthenticationException::class, $message);

    Http::assertNothingSent();
})->with([
    'empty' => ['', 'not configured'],
    'not json' => ['{oops', 'not a valid service account'],
    'no private key' => ['{"client_email":"a@b.c"}', 'not a valid service account'],
]);
