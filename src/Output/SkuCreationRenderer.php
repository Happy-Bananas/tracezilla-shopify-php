<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Output;

use Tracezilla\Shopify\Workflows\CreateTracezillaSkusResult;

final class SkuCreationRenderer
{
    public function render(CreateTracezillaSkusResult $result): string
    {
        $data = $result->toArray();
        $summary = $data['summary'];
        $lines = [
            $summary['dry_run'] ? 'DRY RUN: no tracezilla SKUs were created.' : 'EXECUTION: tracezilla may have been updated.',
            sprintf(
                'Shopify variants: %d returned, %d selected, %d processed.',
                $summary['source_count'],
                $summary['selected_count'],
                $summary['processed_count'],
            ),
            sprintf(
                'Created: %d, would create: %d, skipped: %d, invalid: %d, failed: %d',
                $summary['created_count'],
                $summary['would_create_count'],
                $summary['skipped_count'],
                $summary['invalid_count'],
                $summary['failed_count'],
            ),
            '',
        ];

        foreach ($data['items'] as $item) {
            $lines[] = sprintf(
                '%-12s %-24s %s',
                strtoupper($item['status']),
                $item['sku'] ?? '(missing SKU)',
                $item['message'],
            );
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }
}
