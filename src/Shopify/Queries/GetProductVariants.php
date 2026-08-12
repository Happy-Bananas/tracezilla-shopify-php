<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Shopify\Queries;

final class GetProductVariants
{
    public const DOCUMENT = <<<'GRAPHQL'
query GetProductVariants($first: Int!, $after: String) {
  productVariants(first: $first, after: $after) {
    nodes {
      id
      sku
      displayName
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }
}
GRAPHQL;
}
