<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Shopify\Mappers;

use InvalidArgumentException;
use Tracezilla\Shopify\Shopify\ShopifyInventoryItem;

final class ShopifyInventoryItemMapper
{
    public function map(array $variant): ?ShopifyInventoryItem
    {
        $sku = trim((string) ($variant['sku'] ?? ''));
        if ($sku === '') {
            return null;
        }
        $item = $variant['inventoryItem'] ?? null;
        if (! is_array($item) || trim((string) ($variant['id'] ?? '')) === '' || trim((string) ($item['id'] ?? '')) === '') {
            throw new InvalidArgumentException('Shopify inventory response is missing an ID.');
        }
        $available = null;
        foreach ($item['inventoryLevel']['quantities'] ?? [] as $quantity) {
            if (is_array($quantity) && ($quantity['name'] ?? null) === 'available') {
                $available = isset($quantity['quantity']) ? (int) $quantity['quantity'] : null;
            }
        }

        return new ShopifyInventoryItem((string) $variant['id'], (string) $item['id'], $sku, (bool) ($item['tracked'] ?? false), $available);
    }
}
