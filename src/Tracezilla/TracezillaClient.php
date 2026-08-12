<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Tracezilla;

use GuzzleHttp\Client;
use Tracezilla\Shopify\Configuration;

final class TracezillaClient
{
    private Client $http;

    public function __construct(Configuration $configuration)
    {
        $this->http = new Client([
            'base_uri' => "{$configuration->tracezillaBaseUrl}/api/v1/{$configuration->tracezillaTeamSlug}/",
            'timeout' => $configuration->timeout,
            'connect_timeout' => $configuration->connectTimeout,
            'headers' => ['Accept' => 'application/json', 'Authorization' => "Bearer {$configuration->tracezillaApiKey}"],
        ]);
    }

    public function get(string $path, array $query = []): array
    {
        $response = $this->http->get(ltrim($path, '/'), ['query' => $query]);
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
