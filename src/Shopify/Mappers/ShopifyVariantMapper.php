<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Shopify\Mappers;

use Tracezilla\Shopify\Shared\CatalogItem;
use Tracezilla\Shopify\Shopify\ShopifyVariantData;

final class ShopifyVariantMapper
{
    public function map(ShopifyVariantData $variant): ?CatalogItem
    {
        if ($variant->sku === null) {
            return null;
        }

        return new CatalogItem($variant->sku, $variant->id, $variant->name);
    }
}
