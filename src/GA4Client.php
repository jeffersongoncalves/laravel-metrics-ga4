<?php

namespace JeffersonGoncalves\MetricsGA4;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\MetricsGA4\Auth\ServiceAccountToken;
use JeffersonGoncalves\MetricsGA4\Exceptions\AuthenticationException;
use JeffersonGoncalves\MetricsGA4\Exceptions\GA4Exception;
use JeffersonGoncalves\MetricsGA4\Exceptions\RateLimitException;

class GA4Client
{
    public const BASE_URL = 'https://analyticsdata.googleapis.com/v1beta';

    public function __construct(
        private readonly ServiceAccountToken $token,
        private readonly string $propertyId,
    ) {}

    /**
     * POST properties/{id}:{method} (runReport, runRealtimeReport, ...).
     *
     * @param  array<string, mixed>  $body
     * @return array<int|string, mixed>
     */
    public function call(string $method, array $body, ?string $propertyId = null): array
    {
        $property = $this->property($propertyId);

        $response = Http::withToken($this->token->get())
            ->acceptJson()
            ->asJson()
            ->post(self::BASE_URL."/{$property}:{$method}", $body);

        return $this->handle($response);
    }

    private function property(?string $propertyId): string
    {
        $id = trim((string) ($propertyId ?? $this->propertyId));

        if ($id === '') {
            throw AuthenticationException::missingPropertyId();
        }

        return str_starts_with($id, 'properties/') ? $id : 'properties/'.$id;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function handle(Response $response): array
    {
        if ($response->status() === 403) {
            throw AuthenticationException::forbidden();
        }

        if ($response->status() === 429) {
            throw RateLimitException::exceeded();
        }

        if (! $response->successful()) {
            throw GA4Exception::fromResponse($response->status(), (string) ($response->json('error.message') ?? $response->body()));
        }

        $data = $response->json();

        return is_array($data) ? $data : [];
    }
}
