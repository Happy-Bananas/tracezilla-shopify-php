<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Workflows;

use InvalidArgumentException;
use Tracezilla\Shopify\Contracts\CatalogReader;
use Tracezilla\Shopify\Shared\CatalogItem;

final readonly class ReportTracezillaSkusNeedingShopifyDecision
{
    public function __construct(
        private CatalogReader $shopify,
        private CatalogReader $tracezilla,
    ) {}

    public function run(int $limit = 10): ShopifyProductDecisionReportResult
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('The report limit must be positive.');
        }

        $shopifySkus = [];
        foreach ($this->shopify->read() as $item) {
            $shopifySkus[$item->sku] = true;
        }

        $candidates = [];
        foreach ($this->tracezilla->read() as $item) {
            if (! isset($shopifySkus[$item->sku])) {
                $candidates[$item->sku] = $item;
            }
        }
        ksort($candidates);

        return new ShopifyProductDecisionReportResult(array_values($candidates), $limit);
    }
}
