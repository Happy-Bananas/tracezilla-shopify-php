<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Workflows;

use Tracezilla\Shopify\Shopify\ShopifyLocation;
use Tracezilla\Shopify\Shopify\ShopifyLocationService;

final readonly class ListShopifyLocations
{
    public function __construct(private ShopifyLocationService $locations) {}

    /** @return array{count:int,locations:list<array>} */
    public function run(): array
    {
        $locations = $this->locations->read();

        return [
            'count' => count($locations),
            'locations' => array_map(
                static fn (ShopifyLocation $location): array => $location->toArray(),
                $locations,
            ),
        ];
    }
}
