<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Shared;

use InvalidArgumentException;

final readonly class CatalogItem
{
    public function __construct(
        public string $sku,
        public string $sourceId,
        public ?string $name = null,
    ) {
        if (trim($sku) === '') {
            throw new InvalidArgumentException('A catalog item must have an SKU code.');
        }
    }
}
