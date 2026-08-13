<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Contracts;

interface ShopifyVariantReader
{
    /** @return list<\Tracezilla\Shopify\Shopify\ShopifyVariantData> */
    public function readVariants(): array;
}
