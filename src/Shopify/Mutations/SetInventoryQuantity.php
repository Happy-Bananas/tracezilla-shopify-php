<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Shopify\Mutations;

final class SetInventoryQuantity
{
    public const DOCUMENT = <<<'GRAPHQL'
mutation SetInventoryQuantity($input: InventorySetQuantitiesInput!) {
  inventorySetQuantities(input: $input) {
    inventoryAdjustmentGroup { changes { name delta quantityAfterChange } }
    userErrors { code field message }
  }
}
GRAPHQL;
}
