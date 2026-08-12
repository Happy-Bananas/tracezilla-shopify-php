<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Tracezilla\Mappers;

use Tracezilla\Shopify\Shared\CatalogItem;

final class TracezillaSkuMapper
{
    public function map(array $sku): ?CatalogItem
    {
        $code = trim((string) ($sku['sku_code'] ?? ''));
        if ($code === '') {
            return null;
        }

        return new CatalogItem(
            $code,
            (string) ($sku['id'] ?? $code),
            self::name($sku),
        );
    }

    private static function name(array $sku): ?string
    {
        foreach (['name', 'sku_name', 'description'] as $key) {
            if (is_string($sku[$key] ?? null) && trim($sku[$key]) !== '') {
                return trim($sku[$key]);
            }
        }
        return null;
    }
}
