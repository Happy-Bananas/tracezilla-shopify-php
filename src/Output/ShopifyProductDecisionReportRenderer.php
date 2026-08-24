<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Output;

use Tracezilla\Shopify\Shared\CatalogItem;
use Tracezilla\Shopify\Workflows\ShopifyProductDecisionReportResult;

final class ShopifyProductDecisionReportRenderer
{
    public function render(ShopifyProductDecisionReportResult $result): string
    {
        $output = sprintf("%-24s %-36s %s\n", 'SKU', 'Name', 'tracezilla ID');
        $output .= str_repeat('-', 88)."\n";

        foreach (array_slice($result->candidates, 0, $result->limit) as $item) {
            /** @var CatalogItem $item */
            $output .= sprintf(
                "%-24s %-36s %s\n",
                $item->sku,
                $item->name ?? '-',
                $item->sourceId,
            );
        }

        $output .= sprintf("\nSKUs requiring a decision: %d. Showing at most %d.\n", count($result->candidates), $result->limit);
        if ($result->candidates !== []) {
            $output .= "For each SKU, decide whether to create a product, add a variant to an existing product, map/change the SKU, or exclude it.\n";
        }

        return $output;
    }
}
