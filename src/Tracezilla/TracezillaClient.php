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

    /**
     * Every page of a list, following links.next_page until the last page.
     *
     * @return list<array<string, mixed>>
     */
    public function getAll(string $path, array $query = []): array
    {
        $records = [];
        $visitedPages = [];

        while (true) {
            $page = $this->get($path, $query);
            $records = array_merge($records, $this->recordsOf($page, $path));

            $nextPageQuery = $this->nextPageQuery($page, $path);
            if ($nextPageQuery === null) {
                return $records;
            }

            $query = array_merge($query, $nextPageQuery);
            $pageKey = http_build_query($query);
            if (isset($visitedPages[$pageKey])) {
                throw new RuntimeException("tracezilla returned the same page of {$path} repeatedly.");
            }
            $visitedPages[$pageKey] = true;
        }
    }

    /**
     * @param array<string, mixed> $page
     * @return list<array<string, mixed>>
     */
    private function recordsOf(array $page, string $path): array
    {
        if (! isset($page['data'])) {
            throw new RuntimeException("tracezilla response for {$path} is missing data.");
        }
        if (! is_array($page['data'])) {
            throw new RuntimeException("tracezilla response for {$path} is missing data.");
        }

        return array_values($page['data']);
    }

    /**
     * The query of the next page, or null on the last page.
     *
     * @param array<string, mixed> $page
     * @return array<string, mixed>|null
     */
    private function nextPageQuery(array $page, string $path): ?array
    {
        if (! isset($page['links']['next_page'])) {
            return null;
        }
        $nextPageUrl = $page['links']['next_page'];
        if (! is_string($nextPageUrl) || $nextPageUrl === '') {
            return null;
        }

        parse_str((string) parse_url($nextPageUrl, PHP_URL_QUERY), $query);
        if ($query === []) {
            throw new RuntimeException("tracezilla returned an invalid next-page URL for {$path}.");
        }

        return $query;
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
