<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tracezilla\Shopify\Contracts\ShopifyInventoryGateway;
use Tracezilla\Shopify\Contracts\TracezillaInventoryReader;
use Tracezilla\Shopify\Shopify\ShopifyInventoryItem;
use Tracezilla\Shopify\Tracezilla\TracezillaInventory;
use Tracezilla\Shopify\Workflows\SynchronizeInventory;
use Tracezilla\Shopify\Workflows\TracezillaInventoryToShopifyQuantityMapper;

final class SynchronizeInventoryTest extends TestCase
{
    public function test_dry_run_reports_changes_without_writing(): void
    {
        $target = new FakeShopifyInventory(['BANANA-1' => new ShopifyInventoryItem('variant-1', 'item-1', 'BANANA-1', true, 3)]);
        $result = $this->workflow($target)->run('gid://shopify/Location/1', 2, true, 10)->toArray();

        self::assertSame(1, $result['summary']['would_update']);
        self::assertSame([], $target->writes);
        self::assertSame(5, $result['items'][0]['to']);
    }

    public function test_execution_writes_changed_quantity_with_location(): void
    {
        $target = new FakeShopifyInventory(['BANANA-1' => new ShopifyInventoryItem('variant-1', 'item-1', 'BANANA-1', true, 3)]);
        $result = $this->workflow($target)->run('gid://shopify/Location/1', 2, false, 1)->toArray();

        self::assertSame(1, $result['summary']['updated']);
        self::assertSame([['BANANA-1', 5, 'gid://shopify/Location/1']], $target->writes);
    }

    public function test_mapper_rejects_fractional_shopify_quantity(): void
    {
        $this->expectExceptionMessage('non-negative whole number');
        (new TracezillaInventoryToShopifyQuantityMapper)->map(new TracezillaInventory('BANANA-1', 1.5, 0, 1, 1));
    }

    private function workflow(FakeShopifyInventory $target): SynchronizeInventory
    {
        $source = new class implements TracezillaInventoryReader {
            public function readWarehouse(int $locationNumber): array
            {
                TestCase::assertSame(2, $locationNumber);
                return [new TracezillaInventory('BANANA-1', 2, 1, 2, 1)];
            }
        };
        return new SynchronizeInventory($source, $target, new TracezillaInventoryToShopifyQuantityMapper);
    }
}

final class FakeShopifyInventory implements ShopifyInventoryGateway
{
    public array $writes = [];
    public function __construct(private array $items) {}
    public function readAtLocation(string $locationId): array { return $this->items; }
    public function setAvailable(ShopifyInventoryItem $item, int $quantity, string $locationId): void { $this->writes[] = [$item->sku, $quantity, $locationId]; }
}
