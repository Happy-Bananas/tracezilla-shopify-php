<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Contracts;

use Tracezilla\Shopify\Tracezilla\TracezillaSkuData;

interface TracezillaSkuGateway
{
    /** @return list<string> */
    public function existingSkuCodes(): array;

    public function createSku(TracezillaSkuData $sku): array;
}
