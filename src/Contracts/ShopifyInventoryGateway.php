<?php
declare(strict_types=1);
namespace Tracezilla\Shopify\Contracts;
use Tracezilla\Shopify\Shopify\ShopifyInventoryItem;
interface ShopifyInventoryGateway
{
    /** @return array<string, ShopifyInventoryItem> */
    public function readAtLocation(string $locationId): array;
    public function setAvailable(ShopifyInventoryItem $item, int $quantity, string $locationId): void;
}
