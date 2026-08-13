<?php
declare(strict_types=1);
namespace Tracezilla\Shopify\Contracts;
use Tracezilla\Shopify\Tracezilla\TracezillaInventory;
interface TracezillaInventoryReader
{
    /** @return list<TracezillaInventory> */
    public function readWarehouse(int $locationNumber): array;
}
