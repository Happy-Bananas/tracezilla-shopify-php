<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Workflows;

final readonly class CollectedOrderReportResult
{
    /** @param list<array{date:string,currency:string,sku:string,quantity:int,revenue:string}> $lines */
    public function __construct(
        public array $lines,
        public int $sourceOrders,
        public int $selectedOrders,
        public int $skippedOrders,
        public int $skippedLines,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
