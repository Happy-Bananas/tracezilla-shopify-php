<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Connection;

use Tracezilla\Shopify\Configuration;
use Tracezilla\Shopify\Shopify\ShopifyClient;

/**
 * Asks the real Shopify store for its name and domain.
 */
final class LiveShopifyConnection implements ShopifyConnection
{
    private const SHOP_QUERY = <<<'GRAPHQL'
query CheckConnection {
  shop {
    name
    myshopifyDomain
  }
}
GRAPHQL;

    public function shop(): array
    {
        // The settings are read here, not earlier, so a missing setting is
        // reported as a Shopify failure without stopping the tracezilla check.
        $configuration = Configuration::fromEnvironment();
        $client = new ShopifyClient($configuration);

        return $client->graphql(self::SHOP_QUERY);
    }
}
