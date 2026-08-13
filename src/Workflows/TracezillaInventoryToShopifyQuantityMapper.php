<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Workflows;

use InvalidArgumentException;
use Tracezilla\Shopify\Tracezilla\TracezillaInventory;

final class TracezillaInventoryToShopifyQuantityMapper
{
    public function map(TracezillaInventory $inventory): int
    {
        $quantity = ($inventory->traceableAvailable * $inventory->defaultUomConversion)
            + ($inventory->nonTraceableAvailable * $inventory->nonTraceableUomConversion);
        if (! is_finite($quantity) || $quantity < 0 || floor($quantity) !== $quantity) {
            throw new InvalidArgumentException("Mapped Shopify quantity for SKU [{$inventory->sku}] must be a non-negative whole number.");
        }
        return (int) $quantity;
    }
}
