<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Tracezilla\Mappers;

use InvalidArgumentException;
use Tracezilla\Shopify\Tracezilla\TracezillaInventory;

final class TracezillaInventoryMapper
{
    public function map(array $record): TracezillaInventory
    {
        $sku = is_array($record['sku'] ?? null) ? $record['sku'] : [];
        $code = $record['sku_code'] ?? $sku['sku_code'] ?? null;
        if (! is_scalar($code)) throw new InvalidArgumentException('Tracezilla inventory response is missing an SKU.');

        return new TracezillaInventory(
            trim((string) $code),
            (float) ($record['traceable_quantity_available'] ?? 0),
            (float) ($record['none_traceable_quantity_available'] ?? 0),
            (float) ($sku['default_uom_conversion'] ?? 1),
            (float) ($sku['none_traceable_uom_conversion'] ?? 1),
        );
    }
}
