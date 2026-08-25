<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Connection;

use RuntimeException;

final readonly class ConnectionChecker
{
    private const SHOP_QUERY = <<<'GRAPHQL'
query CheckConnection {
  shop {
    name
    myshopifyDomain
  }
}
GRAPHQL;

    public function __construct(
        private object $shopifyClient,
        private object $tracezillaClient,
    ) {}

    /** @return array{status: string, shopify: array{connected: true, shop_name: string, domain: ?string}, tracezilla: array{connected: true}} */
    public function check(): array
    {
        $shopify = $this->shopifyClient->graphql(self::SHOP_QUERY);
        $shop = $shopify['data']['shop'] ?? null;
        if (! is_array($shop) || ! is_string($shop['name'] ?? null) || trim($shop['name']) === '') {
            throw new RuntimeException('Shopify connection returned no shop.');
        }

        $tracezilla = $this->tracezillaClient->get('/skus', ['perPage' => 1]);
        if (! is_array($tracezilla['data'] ?? null)) {
            throw new RuntimeException('tracezilla connection returned no data collection.');
        }

        return [
            'status' => 'ok',
            'shopify' => [
                'connected' => true,
                'shop_name' => trim($shop['name']),
                'domain' => is_string($shop['myshopifyDomain'] ?? null) ? $shop['myshopifyDomain'] : null,
            ],
            'tracezilla' => ['connected' => true],
        ];
    }
}
