<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Workflows;

use Tracezilla\Shopify\Contracts\ShopifyInventoryGateway;
use Tracezilla\Shopify\Contracts\TracezillaInventoryReader;
use Throwable;

final readonly class SynchronizeInventory
{
    public function __construct(private TracezillaInventoryReader $tracezilla, private ShopifyInventoryGateway $shopify, private TracezillaInventoryToShopifyQuantityMapper $mapper) {}

    public function run(string $shopifyLocationId, int $warehouseNumber, bool $dryRun = true, ?int $limit = null): SynchronizeInventoryResult
    {
        $source = $this->tracezilla->readWarehouse($warehouseNumber);
        if ($limit !== null) $source = array_slice($source, 0, $limit);
        $target = $this->shopify->readAtLocation($shopifyLocationId); $result = new SynchronizeInventoryResult($dryRun);
        foreach ($source as $inventory) {
            $shopify = $target[$inventory->sku] ?? null;
            if ($shopify === null) { $result->add($inventory->sku, 'skipped', 'No Shopify variant has this SKU.'); continue; }
            if (! $shopify->tracked || $shopify->available === null) { $result->add($inventory->sku, 'skipped', 'Shopify does not track this item at the configured location.'); continue; }
            try {
                $quantity = $this->mapper->map($inventory);
                if ($quantity === $shopify->available) $result->add($inventory->sku, 'unchanged', "Quantity is already {$quantity}.", $quantity, $quantity);
                elseif ($dryRun) $result->add($inventory->sku, 'would_update', "Would change quantity from {$shopify->available} to {$quantity}.", $shopify->available, $quantity);
                else { $this->shopify->setAvailable($shopify, $quantity, $shopifyLocationId); $result->add($inventory->sku, 'updated', "Changed quantity from {$shopify->available} to {$quantity}.", $shopify->available, $quantity); }
            } catch (Throwable $exception) { $result->add($inventory->sku, 'failed', $exception->getMessage()); }
        }
        return $result;
    }
}
