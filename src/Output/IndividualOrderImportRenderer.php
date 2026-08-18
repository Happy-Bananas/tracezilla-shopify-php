<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Output;

use Tracezilla\Shopify\Workflows\ImportIndividualOrdersResult;

final class IndividualOrderImportRenderer
{
    public function render(ImportIndividualOrdersResult $result): string
    {
        $data = $result->toArray();
        $output = sprintf("%-18s %-20s %-14s %s\n", 'Shopify order', 'External reference', 'Status', 'Message');
        $output .= str_repeat('-', 100)."\n";

        foreach ($data['items'] as $item) {
            $output .= sprintf(
                "%-18s %-20s %-14s %s\n",
                $item['shopify_order'],
                $item['external_reference'] ?? '-',
                strtoupper($item['status']),
                $item['message'],
            );
        }

        $summary = $data['summary'];
        return $output.sprintf(
            "\nCreated: %d; would create: %d; skipped: %d; invalid: %d; failed: %d.\n",
            $summary['created_count'],
            $summary['would_create_count'],
            $summary['skipped_count'],
            $summary['invalid_count'],
            $summary['failed_count'],
        ).sprintf(
            "Orders returned: %d; selected: %d; days: %d; limit: %d.\n",
            $summary['source_count'],
            $summary['selected_count'],
            $summary['days'],
            $summary['limit'],
        );
    }
}
