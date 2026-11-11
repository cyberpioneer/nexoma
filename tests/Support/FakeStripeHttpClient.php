<?php

namespace Tests\Support;

use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

/**
 * Ersetzt den HTTP-Client des Stripe-SDK in Tests: liefert vorbereitete Antworten je "METHODE /pfad" und
 * protokolliert alle Aufrufe. Es findet kein Netzwerkverkehr statt.
 */
class FakeStripeHttpClient implements ClientInterface
{
    /** @var list<array{method: string, path: string, params: array<string, mixed>, headers: list<string>}> */
    public array $requests = [];

    /** @var array<string, array{0: int, 1: array<string, mixed>}> */
    private array $responses = [];

    public static function install(): self
    {
        $fake = new self;
        ApiRequestor::setHttpClient($fake);

        return $fake;
    }

    public static function uninstall(): void
    {
        ApiRequestor::setHttpClient(null);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function respond(string $method, string $path, array $body, int $status = 200): self
    {
        $this->responses[strtoupper($method).' '.$path] = [$status, $body];

        return $this;
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $path = (string) parse_url($absUrl, PHP_URL_PATH);
        $this->requests[] = ['method' => strtoupper($method), 'path' => $path, 'params' => $params, 'headers' => $headers];

        [$status, $body] = $this->responses[strtoupper($method).' '.$path]
            ?? [404, ['error' => ['type' => 'invalid_request_error', 'message' => 'Not faked: '.$method.' '.$path]]];

        return [json_encode($body), $status, []];
    }

    /**
     * @return list<array{method: string, path: string, params: array<string, mixed>, headers: list<string>}>
     */
    public function requestsTo(string $path): array
    {
        return array_values(array_filter($this->requests, fn (array $request): bool => $request['path'] === $path));
    }
}
