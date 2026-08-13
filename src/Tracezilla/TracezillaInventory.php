<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Tracezilla;

use InvalidArgumentException;

final readonly class TracezillaInventory
{
    public function __construct(
        public string $sku,
        public float $traceableAvailable,
        public float $nonTraceableAvailable,
        public float $defaultUomConversion,
        public float $nonTraceableUomConversion,
    ) {
        if (trim($sku) === '') throw new InvalidArgumentException('Tracezilla inventory SKU must not be blank.');
    }
}
