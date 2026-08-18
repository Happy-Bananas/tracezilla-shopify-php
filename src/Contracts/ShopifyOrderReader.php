<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Contracts;

use DateTimeImmutable;

interface ShopifyOrderReader
{
    /** @return list<\Tracezilla\Shopify\Shopify\ShopifyOrderData> */
    public function readCreatedSince(DateTimeImmutable $createdSince): array;
}
