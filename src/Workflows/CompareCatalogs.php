<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Workflows;

use InvalidArgumentException;
use Tracezilla\Shopify\Contracts\CatalogReader;

final readonly class CompareCatalogs
{
    public function __construct(private CatalogReader $shopify, private CatalogReader $tracezilla) {}

    public function run(int $limit = 10): CatalogComparisonResult
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('The comparison limit must be positive.');
        }

        $shopify = self::skuIndex($this->shopify->read());
        $tracezilla = self::skuIndex($this->tracezilla->read());
        $both = array_values(array_intersect(array_keys($shopify), array_keys($tracezilla)));
        $onlyShopify = array_values(array_diff(array_keys($shopify), array_keys($tracezilla)));
        $onlyTracezilla = array_values(array_diff(array_keys($tracezilla), array_keys($shopify)));
        sort($both);
        sort($onlyShopify);
        sort($onlyTracezilla);

        return new CatalogComparisonResult($both, $onlyShopify, $onlyTracezilla, $limit);
    }

    private static function skuIndex(array $items): array
    {
        $index = [];
        foreach ($items as $item) {
            $index[$item->sku] = $item;
        }
        return $index;
    }
}
