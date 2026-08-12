<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Shopify\Mappers;

use InvalidArgumentException;
use Tracezilla\Shopify\Shared\CatalogItem;

final class ShopifyVariantMapper
{
    public function map(array $variant): ?CatalogItem
    {
        $sku = trim((string) ($variant['sku'] ?? ''));
        if ($sku === '') {
            return null;
        }
        if (! is_string($variant['id'] ?? null) || $variant['id'] === '') {
            throw new InvalidArgumentException('A Shopify variant is missing its ID.');
        }

        return new CatalogItem($sku, $variant['id'], self::optionalString($variant['displayName'] ?? null));
    }

    private static function optionalString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
