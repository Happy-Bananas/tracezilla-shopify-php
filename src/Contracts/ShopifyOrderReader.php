<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Contracts;

use DateTimeImmutable;

interface ShopifyOrderReader
{
    /** @return list<\Tracezilla\Shopify\Shopify\ShopifyOrderData> */
    public function readCreatedSince(DateTimeImmutable $createdSince): array;

    /**
     * The orders with these IDs (Shopify's numeric order IDs), however old.
     *
     * @param list<string> $legacyIds
     * @return list<\Tracezilla\Shopify\Shopify\ShopifyOrderData>
     */
    public function readByLegacyIds(array $legacyIds): array;
}
