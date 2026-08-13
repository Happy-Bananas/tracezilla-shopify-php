<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Tracezilla\Mappers;

use InvalidArgumentException;
use Tracezilla\Shopify\Shopify\ShopifyVariantData;
use Tracezilla\Shopify\Tracezilla\TracezillaSkuData;

final class ShopifyVariantToTracezillaSkuMapper
{
    public function map(ShopifyVariantData $variant): TracezillaSkuData
    {
        if ($variant->sku === null) {
            throw new InvalidArgumentException("Shopify variant [{$variant->id}] cannot be mapped without an SKU.");
        }

        // Example business mapping. Review these assumptions for every customer.
        return new TracezillaSkuData(
            skuCode: $variant->sku,
            globalName: $variant->sku,
            weightFactorNet: 1.0,
            weightFactorGross: 1.0,
            unitOfMeasure: 'pcs',
            lotUnit: 'colli',
            defaultUomConversion: 1.0,
        );
    }
}
