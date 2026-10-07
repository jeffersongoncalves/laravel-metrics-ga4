<?php

use Illuminate\Support\Facades\Cache;
use JeffersonGoncalves\MetricsGA4\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * Skip the access-token round trip in API tests: seed the cache entry ServiceAccountToken looks up.
 */
function fakeAccessToken(string $token = 'test-access-token'): void
{
    Cache::put('metrics-ga4:token:'.sha1('metrics@example.iam.gserviceaccount.com|test-key'), $token, 600);
}

/**
 * Fresh RSA key for signing tests. PHP builds without an openssl.cnf (e.g. some Windows setups) cannot
 * generate keys; those runs skip the signing tests instead of shipping a private key in the repository.
 */
function testPrivateKey(): string
{
    $key = @openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

    if ($key === false) {
        test()->markTestSkipped('openssl_pkey_new() is unavailable (no openssl.cnf); set OPENSSL_CONF to run the signing tests.');
    }

    openssl_pkey_export($key, $pem);

    return $pem;
}
