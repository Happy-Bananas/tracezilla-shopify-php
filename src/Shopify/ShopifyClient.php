<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Shopify;

use GuzzleHttp\Client;
use RuntimeException;
use Tracezilla\Shopify\Configuration;

final class ShopifyClient
{
    private Client $http;

    public function __construct(private readonly Configuration $configuration)
    {
        $tokenClient = new Client(['timeout' => $configuration->timeout, 'connect_timeout' => $configuration->connectTimeout]);
        $response = $tokenClient->post("https://{$configuration->shopifyShopUrl}/admin/oauth/access_token", [
            'form_params' => [
                'grant_type' => 'client_credentials',
                'client_id' => $configuration->shopifyClientId,
                'client_secret' => $configuration->shopifyClientSecret,
                'scope' => $configuration->shopifyScope,
            ],
        ]);
        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($payload) || ! is_string($payload['access_token'] ?? null)) {
            throw new RuntimeException('Shopify authentication did not return an access token.');
        }

        $this->http = new Client([
            'base_uri' => "https://{$configuration->shopifyShopUrl}/admin/api/{$configuration->shopifyApiVersion}/",
            'timeout' => $configuration->timeout,
            'connect_timeout' => $configuration->connectTimeout,
            'headers' => ['Accept' => 'application/json', 'X-Shopify-Access-Token' => $payload['access_token']],
        ]);
    }

    public function graphql(string $query, array $variables = []): array
    {
        $response = $this->http->post('graphql.json', ['json' => ['query' => $query, 'variables' => $variables]]);
        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            throw new RuntimeException('Shopify returned an invalid GraphQL response.');
        }
        if (($payload['errors'] ?? []) !== []) {
            throw new RuntimeException('Shopify rejected the GraphQL query.');
        }

        return $payload;
    }
}
