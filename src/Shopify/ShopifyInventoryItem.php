<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Shopify;

use InvalidArgumentException;

final readonly class ShopifyInventoryItem
{
    public function __construct(
        public string $variantId,
        public string $inventoryItemId,
        public string $sku,
        public bool $tracked,
        public ?int $available,
    ) {
        if (trim($variantId) === '' || trim($inventoryItemId) === '' || trim($sku) === '') {
            throw new InvalidArgumentException('A Shopify inventory item requires variant, inventory item, and SKU identifiers.');
        }
    }
}
