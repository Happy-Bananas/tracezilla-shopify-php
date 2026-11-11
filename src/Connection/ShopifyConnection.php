<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Connection;

/**
 * What the connection check needs from Shopify. The real store and the fake
 * store in the tests both provide it.
 */
interface ShopifyConnection
{
    /** @return array<string, mixed> Shopify's answer to a query for the shop's name and domain */
    public function shop(): array;
}
