<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Contracts;

use Tracezilla\Shopify\Tracezilla\TracezillaOrderContextData;
use Tracezilla\Shopify\Tracezilla\TracezillaSalesOrderData;

interface TracezillaSalesOrderGateway
{
    public function getContext(string $customerName, int $warehouseLocationNumber): TracezillaOrderContextData;

    /** @return array<string, true> */
    public function existingExternalReferences(string $prefix): array;

    public function createSalesOrder(TracezillaSalesOrderData $order): array;
}
