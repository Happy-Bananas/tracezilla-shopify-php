<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Output;

use Tracezilla\Shopify\Workflows\CollectedOrderReportResult;

final class CollectedOrderReportRenderer
{
    public function render(CollectedOrderReportResult $result): string
    {
        $output = sprintf("%-12s %-10s %-24s %10s %12s\n", 'Date', 'Currency', 'SKU', 'Quantity', 'Revenue');
        $output .= str_repeat('-', 74)."\n";

        foreach ($result->lines as $line) {
            $output .= sprintf(
                "%-12s %-10s %-24s %10d %12s\n",
                $line['date'],
                $line['currency'],
                $line['sku'],
                $line['quantity'],
                $line['revenue'],
            );
        }

        return $output.sprintf(
            "\nOrders returned: %d; selected: %d; skipped: %d; lines skipped: %d.\n",
            $result->sourceOrders,
            $result->selectedOrders,
            $result->skippedOrders,
            $result->skippedLines,
        );
    }
}
