<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Shopify\Queries;

final class GetInventoryItems
{
    public const DOCUMENT = <<<'GRAPHQL'
query GetInventoryItems($first: Int!, $after: String, $locationId: ID!) {
  productVariants(first: $first, after: $after) {
    nodes {
      id
      sku
      inventoryItem {
        id
        tracked
        inventoryLevel(locationId: $locationId) {
          quantities(names: ["available"]) { name quantity }
        }
      }
    }
    pageInfo { hasNextPage endCursor }
  }
}
GRAPHQL;
}
