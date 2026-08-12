<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Contracts;

use Tracezilla\Shopify\Shared\CatalogItem;

interface CatalogReader
{
    /** @return list<CatalogItem> */
    public function read(): array;
}
