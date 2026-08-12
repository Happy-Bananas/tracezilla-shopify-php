<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Output;

use Tracezilla\Shopify\Workflows\CatalogComparisonResult;

final class TableRenderer
{
    public function render(CatalogComparisonResult $result): string
    {
        $rows = [];
        foreach (array_slice($result->presentInBoth, 0, $result->limit) as $sku) {
            $rows[] = [$sku, 'Yes', 'Yes', 'Match'];
        }
        foreach (array_slice($result->onlyInShopify, 0, $result->limit) as $sku) {
            $rows[] = [$sku, 'Yes', 'No', 'Missing in tracezilla'];
        }
        foreach (array_slice($result->onlyInTracezilla, 0, $result->limit) as $sku) {
            $rows[] = [$sku, 'No', 'Yes', 'Missing in Shopify'];
        }
        usort($rows, static fn (array $left, array $right): int => $left[0] <=> $right[0]);

        $output = sprintf("%-24s %-10s %-12s %s\n", 'SKU', 'Shopify', 'tracezilla', 'Result');
        $output .= str_repeat('-', 72)."\n";
        foreach ($rows as $row) {
            $output .= sprintf("%-24s %-10s %-12s %s\n", ...$row);
        }
        return $output.sprintf(
            "\nMatched: %d; missing in tracezilla: %d; missing in Shopify: %d\n",
            count($result->presentInBoth),
            count($result->onlyInShopify),
            count($result->onlyInTracezilla),
        ).sprintf("Showing at most %d rows from each result category.\n", $result->limit);
    }
}
