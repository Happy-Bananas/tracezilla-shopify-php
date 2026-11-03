<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Tracezilla;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Tracezilla\Shopify\Configuration;

/**
 * Calls the tracezilla API with an OAuth access token that renews itself.
 */
final class TracezillaClient
{
    private Client $http;
    private TokenProvider $tokens;

    public function __construct(Configuration $configuration, ?TokenProvider $tokens = null, ?Client $http = null)
    {
        $this->tokens = $tokens ?? TokenProvider::fromConfiguration($configuration);
        $this->http = $http ?? new Client([
            'base_uri' => "{$configuration->tracezillaBaseUrl}/api/v1/{$configuration->tracezillaTeamSlug}/",
            'timeout' => $configuration->timeout,
            'connect_timeout' => $configuration->connectTimeout,
            'headers' => ['Accept' => 'application/json'],
        ]);
    }

    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $path, ['query' => $query]);
    }

    public function post(string $path, array $payload): array
    {
        return $this->send('POST', $path, ['json' => $payload]);
    }

    public function put(string $path, array $payload): array
    {
        return $this->send('PUT', $path, ['json' => $payload]);
    }

    private function send(string $method, string $path, array $options): array
    {
        $token = $this->tokens->accessToken();

        try {
            try {
                $response = $this->request($method, $path, $options, $token);
            } catch (RequestException $exception) {
                if ($exception->getResponse()?->getStatusCode() !== 401) {
                    throw $exception;
                }
                // A revoked or otherwise rejected token: renew it once and retry.
                $response = $this->request($method, $path, $options, $this->tokens->renewAfterRejection($token));
            }
        } catch (RequestException $exception) {
            // Validation errors are explained in the body; Guzzle's message truncates it.
            $body = $exception->getResponse()?->getBody()->__toString() ?? '';
            $status = $exception->getResponse()?->getStatusCode() ?? 0;
            throw new RuntimeException(
                "{$method} {$path} failed with HTTP {$status}: ".($body !== '' ? mb_substr($body, 0, 1000) : $exception->getMessage()),
                previous: $exception,
            );
        }

        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function request(string $method, string $path, array $options, string $token): ResponseInterface
    {
        $options['headers'] = ['Authorization' => "Bearer {$token}"];

        return $this->http->request($method, ltrim($path, '/'), $options);
    }
}
